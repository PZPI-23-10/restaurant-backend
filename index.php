<?php
loadEnv("appsettings.env");

require 'vendor/autoload.php';
foreach (glob(__DIR__ . "/controllers/*.php") as $filename) {
    require_once $filename;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$allowedOrigins = [
    'http://localhost:5291',
    'http://localhost:56082',
    'https://your-production-client.com'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

header('Content-Type: application/json');

function loadEnv($path = __DIR__ . '/.env')
{
    if (!file_exists($path))
        return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#'))
            continue;
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

$method = $_SERVER['REQUEST_METHOD'];
// $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$fullUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);
$basePath = dirname($script);
$path = str_replace($basePath, '', $fullUri);

switch (true) {
    //ACCOUNT
    case $method === 'POST' && str_ends_with($path, '/api/account/register/'):
        handleRegister();
        break;

    case $method === 'POST' && str_ends_with($path, '/api/account/login'):
        handleLogin();
        break;

    case $method === 'POST' && str_ends_with($path, '/api/account/editUser/'):
        authMiddleware();
        handleEditUser();
        break;

    case $method === 'POST' && str_ends_with($path, '/api/account/'):
        handleGetUser();
        break;

    case $method === 'GET' && str_ends_with($path, '/api/account/manageableRestaurants/'):
        authMiddleware();
        handleManageableRestaurants();
        break;

    //RESTAURANTS
    case $method === 'POST' && str_ends_with($path, '/api/restaurant/get/'):
        handleGetRestaurant();
        break;

    case $method === 'GET' && str_ends_with($path, '/api/restaurant/'):
        handleGetAllRestaurants();
        break;

    case $method === 'DELETE' && str_ends_with($path, '/api/restaurant/'):
        authMiddleware();
        handleDeleteRestaurant();
        break;

    case $method === 'POST' && str_ends_with($path, '/api/restaurant/create/'):
        authMiddleware();
        handleCreateRestaurant();
        break;

    case $method === 'POST' && str_ends_with($path, '/api/restaurant/edit/'):
        authMiddleware();
        handleEditRestaurant();
        break;

    //REVIEWS
    case $method === 'POST' && str_ends_with($path, '/api/reviews/'):
        authMiddleware();
        handleCreateReview();
        break;

    case $method === 'GET' && str_ends_with($path, '/api/reviews/user'):
        handleGetUserReviews();
        break;

    case $method === 'GET' && str_ends_with($path, '/api/reviews/restaurant'):
        handleGetRestaurantReviews();
        break;

    //TAGS
    case $method === 'GET' && str_ends_with($path, '/api/tag/'):
        handleGetTags();
        break;
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        break;
}