<?php
require_once 'db.php';
require_once 'middleware.php';
require_once 'services/jwtService.php';


function handleGetTags()
{
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, ngrok-skip-browser-warning");
    header("Access-Control-Allow-Credentials: true");
    header('Content-Type: application/json');
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