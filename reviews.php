<?php
require_once 'config.php';

// Get action from URL parameter
$action = $_GET['action'] ?? '';

switch($action) {
    case 'create':
        createReview($conn);
        break;
    default:
        getReviews($conn);
        break;
}

function getReviews($conn) {
    $stmt = $conn->query("SELECT * FROM reviews");
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    respond($reviews);
}

function createReview($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['user_id']) || !isset($input['station_id'])) {
        respond(null, 'Missing required fields', 400);
    }
    
    $stmt = $conn->prepare("INSERT INTO reviews (user_id, station_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
    $result = $stmt->execute([
        $input['user_id'],
        $input['station_id'],
        $input['rating'] ?? 5,
        $input['comment'] ?? ''
    ]);
    
    if ($result) {
        $reviewId = $conn->lastInsertId();
        
        // Fetch the newly created review
        $stmt = $conn->prepare("SELECT * FROM reviews WHERE id = ?");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch(PDO::FETCH_ASSOC);
        
        respond($review, 'Review created successfully');
    } else {
        respond(null, 'Review failed', 500);
    }
}
?>