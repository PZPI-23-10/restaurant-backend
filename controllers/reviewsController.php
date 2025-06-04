<?php
require_once 'db.php';
require_once 'middleware.php';
require_once 'services/jwtService.php';

function handleGetUserReviews()
{
    $userId = $_GET['id'];

    if (empty($userId)) {
        http_response_code(400);
        echo json_encode(['error' => 'User ID is required.']);
        return;
    }

    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ?");
    $stmt->execute([$userId]);

    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($reviews) {
        echo json_encode($reviews);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'No reviews found for this user.']);
    }
}

function handleGetRestaurantReviews()
{
    $restaurantId = $_GET['id'];

    if (empty($restaurantId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Restaurant ID is required.']);
        return;
    }

    $pdo = getDb();
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE restaurant_id = ?");
    $stmt->execute([$restaurantId]);

    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($reviews) {
        echo json_encode($reviews);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'No reviews found for this restaurant.']);
    }
}

function handleCreateReview()
{
    $user = $_SERVER['user'];
    $user = $_SERVER['user'] ?? null;
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    $userId = $user['sub'];

    $data = json_decode(file_get_contents("php://input"), true);
    $restaurantId = $data['restaurantId'] ?? null;
    $rating = $data['rating'] ?? null;
    $comment = $data['comment'];

    if (!$restaurantId || !$rating || !is_numeric($rating) || empty($comment)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields.']);
        return;
    }

    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Rating must be between 1 and 5.']);
        return;
    }

    $pdo = getDb();
    $stmt = $pdo->prepare("INSERT INTO reviews (user_id, restaurant_id, rating, comment) VALUES (?, ?, ?, ?)");

    try {
        $stmt->execute([$userId, $restaurantId, $rating, $comment]);
        echo json_encode(['status' => 'Review created successfully.']);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create review: ' . $e->getMessage()]);
    }
}