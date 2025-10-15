<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/google-auth-generic.php';


if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User not authenticated']);
    exit();
}

try {
    $pdo = get_db_connection();
    $preConsult = $pdo->prepare('SELECT id, username, email, profile_picture, profile_public, auth_provider FROM users WHERE id = :id');
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}

$userId = $_SESSION['user_id'] ?? null;
if (empty($userId)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid session user id']);
    exit();
}


$preConsult->execute(['id' => $userId]);
$user = $preConsult->fetch(PDO::FETCH_ASSOC);

if ($user) {
    if (!empty($user['profile_picture']) && strpos($user['profile_picture'], 'data:') !== 0) {
        // ajustar mime si sabes que la imagen es png/jpg
        $mime = 'image/jpeg';
        $user['profile_picture'] = 'data:' . $mime . ';base64,' . $user['profile_picture'];
    }
    
    header('Content-Type: application/json');
    echo json_encode($user);
} else {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'User not found']);
    exit();
}