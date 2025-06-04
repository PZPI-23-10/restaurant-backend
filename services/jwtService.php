<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
require_once 'jwtService.php';


function generate_jwt($id, $email, $rememberMe = false) {
  $secret = getenv('JWT_ACCESS_SECRET');
  $expireMinutes = getenv('JWT_ACCESS_EXPIRE_MINUTES') ?: 60;
  $rememberDays = getenv('JWT_REMEMBER_EXPIRE_DAYS') ?: 7;

  $expireTime = $rememberMe
    ? time() + ($rememberDays * 24 * 60 * 60)
    : time() + ($expireMinutes * 60);

  $payload = [
    'sub' => $id,
    'name' => $email,
    'iat' => time(),
    'exp' => $expireTime
  ];

  return JWT::encode($payload, $secret, 'HS256');
}

function decode_jwt($token) {
  $secret = getenv('JWT_ACCESS_SECRET');
  return (array) JWT::decode($token, new Key($secret, 'HS256'));
}

function get_jwt_expiration($token) {
  $decoded = JWT::decode($token, new Key(getenv('JWT_ACCESS_SECRET'), 'HS256'));
  return $decoded->exp;
}
