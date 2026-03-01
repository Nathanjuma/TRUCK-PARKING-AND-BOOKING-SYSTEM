<?php
require_once 'config.php';

// Get action from URL parameter
$action = $_GET['action'] ?? '';

switch($action) {
    case 'login':
        loginUser($conn);
        break;
    case 'register':
        registerUser($conn);
        break;
    default:
        getUsers($conn);
        break;
}

function getUsers($conn) {
    $stmt = $conn->query("SELECT id, name, email, role, created_at FROM users");
    respond($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function registerUser($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email']) || !isset($input['password']) || !isset($input['name'])) {
        respond(null, 'Missing required fields', 400);
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        respond(null, 'Email already registered', 409);
    }
    
    // Hash password
    $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
    $role = $input['role'] ?? 'driver';
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $result = $stmt->execute([$input['name'], $input['email'], $hashedPassword, $role]);
    
    if ($result) {
        $userId = $conn->lastInsertId();
        respond([
            'id' => $userId,
            'name' => $input['name'],
            'email' => $input['email'],
            'role' => $role
        ], 'Registration successful');
    } else {
        respond(null, 'Registration failed', 500);
    }
}

function loginUser($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['email']) || !isset($input['password'])) {
        respond(null, 'Email and password required', 400);
    }
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($input['password'], $user['password'])) {
        respond(null, 'Invalid credentials', 401);
    }
    
    unset($user['password']);
    respond(['user' => $user]);
}
?>