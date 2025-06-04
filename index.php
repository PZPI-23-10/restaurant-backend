<?php
require 'vendor/autoload.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($uri === '/api/protected' && $method === 'GET') {
    authenticate($config['jwt_secret']);
    echo json_encode(['message' => 'You are authenticated']);
    exit;
}

if ($uri === '/api/login' && $method === 'POST') {
    $payload = ['sub' => 'user123', 'iat' => time(), 'exp' => time() + 3600];
    $jwt = JWT::encode($payload, $config['jwt_secret'], 'HS256');

    echo json_encode(['token' => $jwt]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not Found']);


function authenticate($secret)
{
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Missing Authorization header']);
        exit;
    }

    $authHeader = $headers['Authorization'];
    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid token format']);
        exit;
    }

    $token = $matches[1];

    try {
        $decoded = JWT::decode($token, new Key($secret, 'HS256'));
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'Token invalid: ' . $e->getMessage()]);
        exit;
    }
}
