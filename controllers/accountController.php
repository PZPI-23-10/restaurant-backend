<?php
require_once 'db.php';
require_once 'services/jwtService.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

switch (true) {
  case $method === 'POST' && str_ends_with($path, '/api/account/register'):
    handleRegister();
    break;

  case $method === 'POST' && str_ends_with($path, '/api/account/login'):
    handleLogin();
    break;

  case $method === 'POST' && str_ends_with($path, '/api/account/editUser'):
    authMiddleware();
    handleEditUser();
    break;

  case $method === 'POST' && str_ends_with($path, '/api/account'):
    handleGetUser();
    break;

  case $method === 'GET' && str_ends_with($path, '/api/account/manageableRestaurants'):
    authMiddleware();
    handleManageableRestaurants();
    break;

  default:
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
    break;
}

function authMiddleware() {
  $headers = getallheaders();
  if (!isset($headers['Authorization'])) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
  }

  $matches = [];
  if (!preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
    http_response_code(401);
    exit(json_encode(['error' => 'Invalid token format']));
  }

  $token = $matches[1];
  try {
    $payload = decode_jwt($token);
    $_SERVER['user'] = $payload;
  } catch (Exception $e) {
    http_response_code(401);
    exit(json_encode(['error' => 'Invalid token']));
  }
}

function handleRegister() {
  $data = json_decode(file_get_contents("php://input"), true);

  $required = ['firstName', 'middleName', 'lastName', 'email', 'password', 'city', 'street'];
  foreach ($required as $key) {
    if (empty($data[$key])) {
      http_response_code(400);
      echo json_encode(['error' => "$key is required."]);
      return;
    }
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$data['email']]);

  if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'User with this email already exists.']);
    return;
  }

  if (strlen($data['password']) < 5 || strlen($data['password']) > 27) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be between 5 and 27 characters.']);
    return;
  }

  if (!preg_match('/^[a-zA-Z0-9!@#$%^&*]+$/', $data['password']) ||
      !preg_match('/[!@#$%^&*]/', $data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must contain special characters.']);
    return;
  }

  $hash = base64_encode(md5($data['password'], true));

  $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, email, password, city, street)
                         VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $data['firstName'], $data['middleName'], $data['lastName'],
    $data['email'], $hash, $data['city'], $data['street']
  ]);

  echo json_encode(['status' => 'Ok']);
}

function handleLogin() {
  $data = json_decode(file_get_contents("php://input"), true);
  if (empty($data['email']) || empty($data['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required.']);
    return;
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$data['email']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'User with this email does not exist.']);
    return;
  }

  $hash = base64_encode(md5($data['password'], true));
  if ($user['password'] !== $hash) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid password.']);
    return;
  }

  $token = generate_jwt($user['id'], $user['email'], $data['rememberMe'] ?? false);
  echo json_encode(['userId' => $user['id'], 'token' => $token]);
}

function handleEditUser() {
  $data = json_decode(file_get_contents("php://input"), true);
  $user = $_SERVER['user'] ?? null;
  if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    return;
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("UPDATE users SET email = ?, city = ?, street = ?, first_name = ?, middle_name = ?, last_name = ? WHERE id = ?");
  $stmt->execute([
    $data['email'], $data['city'], $data['street'],
    $data['firstName'], $data['middleName'], $data['lastName'],
    $user['sub']
  ]);

  echo json_encode(['status' => 'ok']);
}

function handleGetUser() {
  $data = json_decode(file_get_contents("php://input"), true);
  $userId = $data['userId'] ?? null;

  if (!$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid User ID']);
    return;
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    return;
  }

  echo json_encode($user);
}

function handleManageableRestaurants() {
  $user = $_SERVER['user'] ?? null;
  if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    return;
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE user_id = ?");
  $stmt->execute([$user['sub']]);
  $owned = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare("SELECT r.* FROM restaurant_moderators rm JOIN restaurants r ON rm.restaurant_id = r.id WHERE rm.user_id = ?");
  $stmt->execute([$user['sub']]);
  $moderated = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($owned) && empty($moderated)) {
    http_response_code(404);
    echo json_encode(['error' => 'No manageable restaurants found.']);
    return;
  }

  echo json_encode([
    'userId' => $user['sub'],
    'ownedRestaurants' => $owned,
    'moderatedRestaurants' => $moderated
  ]);
}
