<?php
loadEnv("appsettings.env");

require 'vendor/autoload.php';
require_once 'controllers/accountController.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$config = [
    'jwt_secret' => 'your_secret_key',
    'allowed_origins' => [
        'http://localhost:5291',
        'http://localhost:56082',
        'https://your-production-client.com',
    ],
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
if (in_array($origin, $config['allowed_origins'])) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
}

function loadEnv($path = __DIR__ . '/.env') {
  if (!file_exists($path)) return;
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    [$key, $value] = explode('=', $line, 2);
    putenv(trim($key) . '=' . trim($value));
  }
}