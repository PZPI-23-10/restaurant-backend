<?php
require_once 'db.php';
require_once 'jwt_helper.php';
require_once 'google_helper.php';
require_once 'jwtService.php';


header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

switch (true) {
  case $method === 'POST' && str_ends_with($path, '/api/account/Register'):
    handleRegister();
    break;

  case $method === 'POST' && str_ends_with($path, '/api/account/Login'):
    handleLogin();
    break;

  case $method === 'POST' && str_ends_with($path, '/api/account/EditUser'):
    authMiddleware();
    handleEditUser();
    break;

  case $method === 'POST' && str_ends_with($path, '/api/account/android/registerDevice'):
    authMiddleware();
    handleRegisterDevice();
    break;

  case $method === 'POST' && str_ends_with($path, '/api/account/android/google'):
    handleGoogleLogin('android');
    break;

  case $method === 'POST' && str_ends_with($path, '/api/account/web/google'):
    handleGoogleLogin('web');
    break;

  case $method === 'POST' && $path === '/api/account':
    handleGetUser();
    break;

  case $method === 'GET' && str_ends_with($path, '/api/account/ManageableRestaurants'):
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

  $required = ['FirstName', 'MiddleName', 'LastName', 'Email', 'Password', 'City', 'Street'];
  foreach ($required as $key) {
    if (empty($data[$key])) {
      http_response_code(400);
      echo json_encode(['error' => "$key is required."]);
      return;
    }
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$data['Email']]);

  if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'User with this email already exists.']);
    return;
  }

  if (strlen($data['Password']) < 5 || strlen($data['Password']) > 27) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be between 5 and 27 characters.']);
    return;
  }

  if (!preg_match('/^[a-zA-Z0-9!@#$%^&*]+$/', $data['Password']) ||
      !preg_match('/[!@#$%^&*]/', $data['Password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must contain special characters.']);
    return;
  }

  $hash = base64_encode(md5($data['Password'], true));

  $stmt = $pdo->prepare("INSERT INTO users (first_name, middle_name, last_name, email, password, city, street)
                         VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $data['FirstName'], $data['MiddleName'], $data['LastName'],
    $data['Email'], $hash, $data['City'], $data['Street']
  ]);

  echo json_encode(['status' => 'ok']);
}

function handleLogin() {
  $data = json_decode(file_get_contents("php://input"), true);
  if (empty($data['Email']) || empty($data['Password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required.']);
    return;
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$data['Email']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'User with this email does not exist.']);
    return;
  }

  if ($user['is_google_auth']) {
    http_response_code(400);
    echo json_encode(['error' => 'Use Google login.']);
    return;
  }

  $hash = base64_encode(md5($data['Password'], true));
  if ($user['password'] !== $hash) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid password.']);
    return;
  }

  $token = generate_jwt($user['id'], $user['email'], $data['RememberMe'] ?? false);
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
    $data['Email'], $data['City'], $data['Street'],
    $data['FirstName'], $data['MiddleName'], $data['LastName'],
    $user['sub']
  ]);

  echo json_encode(['status' => 'ok']);
}

function handleGetUser() {
  $data = json_decode(file_get_contents("php://input"), true);
  $userId = $data['UserId'] ?? null;

  if (!$userId || !preg_match('/^[a-f0-9-]{36}$/', $userId)) {
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

function handleRegisterDevice() {
  $data = json_decode(file_get_contents("php://input"), true);
  $token = $data['Token'] ?? null;
  $user = $_SERVER['user'] ?? null;

  if (!$token || !$user) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing token or user.']);
    return;
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM devices WHERE device_token = ?");
  $stmt->execute([$token]);
  if ($stmt->fetch()) {
    echo json_encode(['message' => 'Device already registered.']);
    return;
  }

  $stmt = $pdo->prepare("INSERT INTO devices (device_token, user_id) VALUES (?, ?)");
  $stmt->execute([$token, $user['sub']]);

  echo json_encode(['message' => 'Device registered successfully.']);
}

function handleManageableRestaurants() {
  $user = $_SERVER['user'] ?? null;
  if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    return;
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE owner_id = ?");
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
    'UserId' => $user['sub'],
    'OwnedRestaurants' => $owned,
    'ModeratedRestaurants' => $moderated
  ]);
}

function handleGoogleLogin($platform) {
  $data = json_decode(file_get_contents("php://input"), true);
  $googleToken = $data['GoogleToken'] ?? null;

  $clientId = ($platform === 'android') ? getenv('GOOGLE_ANDROID_CLIENT_ID') : getenv('GOOGLE_WEB_CLIENT_ID');
  $payload = verifyGoogleToken($googleToken, $clientId);

  if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid Google token.']);
    return;
  }

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
  $stmt->execute([$payload['email']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    $stmt = $pdo->prepare("INSERT INTO users (email, first_name, last_name, middle_name, city, street, password, is_google_auth) VALUES (?, ?, ?, '', '', '', '', 1)");
    $stmt->execute([$payload['email'], $payload['given_name'], $payload['family_name']]);
    $userId = $pdo->lastInsertId();
  } else {
    $userId = $user['id'];
  }

  $token = generate_jwt($userId, $payload['email'], $data['RememberMe'] ?? false);
  echo json_encode(['userId' => $userId, 'token' => $token]);
}