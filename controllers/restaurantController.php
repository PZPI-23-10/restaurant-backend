<?php
require_once 'db.php';
require_once 'middleware.php';
require_once 'services/jwtService.php';

function handleGetAllRestaurants() {
  $page = $_GET['page'] ?? 1;
  $pageSize = $_GET['pageSize'] ?? 2;
  $offset = ($page - 1) * $pageSize;

  $pdo = getDb();
  $stmt = $pdo->prepare("SELECT * FROM restaurants LIMIT ? OFFSET ?");
  $stmt->bindValue(1, (int)$pageSize, PDO::PARAM_INT);
  $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
  $stmt->execute();
  $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($restaurants as &$restaurant) {
    $restaurantId = $restaurant['id'];

    $restaurant['cuisines'] = getMany($pdo, "
      SELECT c.* FROM restaurant_cuisines rc
      JOIN cuisines c ON rc.cuisine_id = c.id
      WHERE rc.restaurant_id = ?", [$restaurantId]);

    $restaurant['tags'] = getMany($pdo, "
      SELECT t.* FROM restaurant_tags rt
      JOIN tags t ON rt.tag_id = t.id
      WHERE rt.restaurant_id = ?", [$restaurantId]);

    $restaurant['moderators'] = getMany($pdo, "
      SELECT u.* FROM restaurant_moderators rm
      JOIN users u ON rm.user_id = u.id
      WHERE rm.restaurant_id = ?", [$restaurantId]);

    $restaurant['dishes'] = getMany($pdo, "
      SELECT * FROM dishes WHERE restaurant_id = ?", [$restaurantId]);

    $restaurant['schedule'] = getMany($pdo, "
      SELECT * FROM schedules WHERE restaurant_id = ?", [$restaurantId]);

    $restaurant['reviews'] = getMany($pdo, "
      SELECT * FROM reviews WHERE restaurant_id = ?", [$restaurantId]);

    $restaurant['photos'] = getMany($pdo, "
      SELECT * FROM restaurant_photos WHERE restaurant_id = ?", [$restaurantId]);

    $restaurant['dressCodes'] = getMany($pdo, "
      SELECT d.* FROM restaurant_dress_codes rdc
      JOIN dress_codes d ON rdc.dress_code_id = d.id
      WHERE rdc.restaurant_id = ?", [$restaurantId]);

    $restaurant['user'] = getOne($pdo, "SELECT * FROM users WHERE id = ?", [$restaurant['user_id']]);
  }

  echo json_encode($restaurants);
}

function handleGetRestaurant() {
  $data = json_decode(file_get_contents('php://input'), true);

  if (empty($data['restaurantId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Restaurant ID is required.']);
    return;
  }

  $restaurantId = $data['restaurantId'];

  $pdo = getDb();
  $restaurant = getOne($pdo, "SELECT * FROM restaurants WHERE id = ?", [$restaurantId]);

  if (!$restaurant) {
    http_response_code(404);
    echo json_encode(['error' => 'Restaurant not found.']);
    return;
  }

  $restaurant['cuisines'] = getMany($pdo, "
    SELECT c.* FROM restaurant_cuisines rc
    JOIN cuisines c ON rc.cuisine_id = c.id
    WHERE rc.restaurant_id = ?", [$restaurantId]);

  $restaurant['tags'] = getMany($pdo, "
    SELECT t.* FROM restaurant_tags rt
    JOIN tags t ON rt.tag_id = t.id
    WHERE rt.restaurant_id = ?", [$restaurantId]);

  $restaurant['moderators'] = getMany($pdo, "
    SELECT u.* FROM restaurant_moderators rm
    JOIN users u ON rm.user_id = u.id
    WHERE rm.restaurant_id = ?", [$restaurantId]);

  $restaurant['dishes'] = getMany($pdo, "
    SELECT * FROM dishes WHERE restaurant_id = ?", [$restaurantId]);

  $restaurant['schedule'] = getMany($pdo, "
    SELECT * FROM schedules WHERE restaurant_id = ?", [$restaurantId]);

  $restaurant['reviews'] = getMany($pdo, "
    SELECT * FROM reviews WHERE restaurant_id = ?", [$restaurantId]);

  $restaurant['photos'] = getMany($pdo, "
    SELECT * FROM restaurant_photos WHERE restaurant_id = ?", [$restaurantId]);

  $restaurant['dressCodes'] = getMany($pdo, "
    SELECT d.* FROM restaurant_dress_codes rdc
    JOIN dress_codes d ON rdc.dress_code_id = d.id
    WHERE rdc.restaurant_id = ?", [$restaurantId]);

  $restaurant['user'] = getOne($pdo, "SELECT * FROM users WHERE id = ?", [$restaurant['user_id']]);

  echo json_encode($restaurant);
}

function handleCreateRestaurant() {
  $data = json_decode(file_get_contents('php://input'), true);

  $pdo = getDb();
  $user = $_SERVER['user'];
  $userId = $user['sub'];

  $stmt = $pdo->prepare("INSERT INTO restaurants (name, description, city, region, street, email, layout, organization, photo_url, latitude, longitude, has_parking, accessible, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $data['name'], $data['description'], $data['city'], $data['region'],
    $data['street'], $data['email'], json_encode($data['layout']), $data['organization'],
    $data['photoUrl'], $data['latitude'], $data['longitude'],
    $data['hasParking'], $data['accessible'], $userId
  ]);
  $restaurantId = $pdo->lastInsertId();

  insertRelatedEntities($pdo, $restaurantId, $data);
  echo json_encode(['status' => 'created', 'restaurantId' => $restaurantId]);
}

function handleEditRestaurant() {
  $data = json_decode(file_get_contents('php://input'), true);
  $pdo = getDb();
  $user = $_SERVER['user'];
  $userId = $user['sub'];

  $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
  $stmt->execute([$data['restaurantId']]);
  $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$restaurant) {
    http_response_code(404);
    echo json_encode(['error' => 'Restaurant not found']);
    return;
  }

  $isOwner = $restaurant['user_id'] == $userId;
  $isModerator = count(getMany($pdo, "SELECT * FROM restaurant_moderators WHERE restaurant_id = ? AND user_id = ?", [$restaurant['id'], $userId])) > 0;

  if (!$isOwner && !$isModerator) {
    http_response_code(403);
    echo json_encode(['error' => 'No permission']);
    return;
  }

  $stmt = $pdo->prepare("UPDATE restaurants SET name = ?, description = ?, city = ?, region = ?, street = ?, email = ?, layout = ?, organization = ?, photo_url = ?, latitude = ?, longitude = ?, has_parking = ?, accessible = ? WHERE id = ?");
  $stmt->execute([
    $data['name'], $data['description'], $data['city'], $data['region'],
    $data['street'], $data['email'], json_encode($data['layout']), $data['organization'],
    $data['photoUrl'], $data['latitude'], $data['longitude'],
    $data['hasParking'], $data['accessible'], $restaurant['id']
  ]);

  $restaurantId = $restaurant['id'];
  $pdo->exec("DELETE FROM dishes WHERE restaurant_id = $restaurantId");
  $pdo->exec("DELETE FROM restaurant_tags WHERE restaurant_id = $restaurantId");
  $pdo->exec("DELETE FROM restaurant_cuisines WHERE restaurant_id = $restaurantId");
  $pdo->exec("DELETE FROM restaurant_dress_codes WHERE restaurant_id = $restaurantId");
  $pdo->exec("DELETE FROM restaurant_moderators WHERE restaurant_id = $restaurantId");
  $pdo->exec("DELETE FROM schedules WHERE restaurant_id = $restaurantId");
  $pdo->exec("DELETE FROM restaurant_photos WHERE restaurant_id = $restaurantId");

  insertRelatedEntities($pdo, $restaurantId, $data);
  echo json_encode(['status' => 'updated']);
}

function handleDeleteRestaurant() {
  $data = json_decode(file_get_contents('php://input'), true);
  $pdo = getDb();
  $userId = $_SERVER['user']['sub'];

  $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ? AND user_id = ?");
  $stmt->execute([$data['restaurantId'], $userId]);
  $restaurant = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$restaurant) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not own this restaurant']);
    return;
  }

  $stmt = $pdo->prepare("DELETE FROM restaurants WHERE id = ?");
  $stmt->execute([$restaurant['id']]);

  echo json_encode(['status' => 'deleted']);
}

function insertRelatedEntities($pdo, $restaurantId, $data) {
  foreach ($data['tags'] ?? [] as $tagName) {
    $tagId = getOrInsertId($pdo, 'tags', 'name', $tagName, false);
    if(!$tagId)
        continue;
    $stmt = $pdo->prepare("INSERT INTO restaurant_tags (restaurant_id, tag_id) VALUES (?, ?)");
    $stmt->execute([$restaurantId, $tagId]);
  }

  foreach ($data['cuisine'] ?? [] as $name) {
    $cuisineId = getOrInsertId($pdo, 'cuisines', 'name', $name, false);
    if(!$cuisineId)
        continue;
    $stmt = $pdo->prepare("INSERT INTO restaurant_cuisines (restaurant_id, cuisine_id) VALUES (?, ?)");
    $stmt->execute([$restaurantId, $cuisineId]);
  }

  foreach ($data['dressCode'] ?? [] as $name) {
    $dressCodeId = getOrInsertId($pdo, 'dress_codes', 'name', $name, false);
    if(!$dressCodeId)
        continue;
    $stmt = $pdo->prepare("INSERT INTO restaurant_dress_codes (restaurant_id, dress_code_id) VALUES (?, ?)");
    $stmt->execute([$restaurantId, $dressCodeId]);
  }

  foreach ($data['moderatorEmails'] ?? [] as $email) {
    $user = getOne($pdo, "SELECT id FROM users WHERE email = ?", [$email]);
    if ($user) {
      $stmt = $pdo->prepare("INSERT INTO restaurant_moderators (restaurant_id, user_id) VALUES (?, ?)");
      $stmt->execute([$restaurantId, $user['id']]);
    }
  }

  foreach ($data['dishes'] ?? [] as $dish) {
    $stmt = $pdo->prepare("INSERT INTO dishes (title, photo_url, ingredients, price, weight, restaurant_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
      $dish['name'], $dish['photoUrl'], $dish['ingredients'],
      $dish['price'], $dish['weight'], $restaurantId
    ]);
  }

  foreach ($data['schedule'] ?? [] as $s) {
    $stmt = $pdo->prepare("INSERT INTO schedules (day, is_day_off, open, close, restaurant_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
      $s['day'], $s['isDayOff'], $s['open'], $s['close'], $restaurantId
    ]);
  }

  foreach ($data['gallery'] ?? [] as $url) {
    $stmt = $pdo->prepare("INSERT INTO restaurant_photos (url, restaurant_id) VALUES (?, ?)");
    $stmt->execute([$url, $restaurantId]);
  }
}

function getOrInsertId($pdo, $table, $column, $value, bool $shouldInsert = true) {
  $stmt = $pdo->prepare("SELECT id FROM $table WHERE $column = ?");
  $stmt->execute([$value]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($existing != null || !$shouldInsert) return $existing['id'];

  $stmt = $pdo->prepare("INSERT INTO $table ($column) VALUES (?)");
  $stmt->execute([$value]);
  return $pdo->lastInsertId();
}

function getOne($pdo, $query, $params) {
  $stmt = $pdo->prepare($query);
  $stmt->execute($params);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getMany($pdo, $query, $params) {
  $stmt = $pdo->prepare($query);
  $stmt->execute($params);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}