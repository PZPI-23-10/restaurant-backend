<?php
require_once 'db.php';
require_once 'middleware.php';
require_once 'services/jwtService.php';

function handleGetTags() {
  $pdo = getDb();

  $tags = getMany($pdo, "SELECT * FROM tags", []);
  $cuisines = getMany($pdo, "SELECT * FROM cuisines", []);
  $dressCodes = getMany($pdo, "SELECT * FROM dress_codes", []);

  if (empty($tags) && empty($cuisines) && empty($dressCodes)) {
    http_response_code(404);
    echo json_encode(['error' => 'No tags found.']);
    return;
  }

  echo json_encode([
    'tags' => $tags,
    'cuisines' => $cuisines,
    'dressCodes' => $dressCodes
  ]);
}


function getMany($pdo, $query, $params) {
  $stmt = $pdo->prepare($query);
  $stmt->execute($params);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}