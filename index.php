<?php
require_once 'config.php';

$request = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Remove /truckpark from path (adjust based on your setup)
$path = str_replace('/truckpark', '', $path);

// Route requests
if ($path === '/users' && $request === 'GET') {
    require_once 'users.php';
    getUsers($conn);
} elseif ($path === '/users/login' && $request === 'POST') {
    require_once 'users.php';
    loginUser($conn);
} elseif ($path === '/users/register' && $request === 'POST') {
    require_once 'users.php';
    registerUser($conn);
} elseif ($path === '/stations' && $request === 'GET') {
    require_once 'stations.php';
    getStations($conn);
} elseif ($path === '/stations/create' && $request === 'POST') {
    require_once 'stations.php';
    createStation($conn);
} elseif (preg_match('/\/stations\/(\d+)\/update/', $path) && $request === 'POST') {
    require_once 'stations.php';
    $stationId = preg_replace('/[^0-9]/', '', $path);
    updateStation($conn, $stationId);
} elseif (preg_match('/\/stations\/(\d+)\/delete/', $path) && $request === 'POST') {
    require_once 'stations.php';
    $stationId = preg_replace('/[^0-9]/', '', $path);
    deleteStation($conn, $stationId);
} elseif ($path === '/bookings' && $request === 'GET') {
    require_once 'bookings.php';
    getBookings($conn);
} elseif ($path === '/bookings/create' && $request === 'POST') {
    require_once 'bookings.php';
    createBooking($conn);
} elseif (preg_match('/\/bookings\/(\d+)\/cancel/', $path) && $request === 'POST') {
    require_once 'bookings.php';
    $bookingId = preg_replace('/[^0-9]/', '', $path);
    cancelBooking($conn, $bookingId);
} elseif ($path === '/reviews' && $request === 'GET') {
    require_once 'reviews.php';
    getReviews($conn);
} elseif ($path === '/reviews/create' && $request === 'POST') {
    require_once 'reviews.php';
    createReview($conn);
} else {
    http_response_code(404);
    respond(null, 'Endpoint not found', 404);
}
?>