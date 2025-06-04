<?php
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