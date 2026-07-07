<?php
session_start();

header('Content-Type: application/json');

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST is allowed.'
    ]);
    exit;
}

// Validate the CSRF token to prevent cross-site request forgery
if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token.'
    ]);
    exit;
}

// Clear all session variables
$_SESSION = [];

// Destroy the session completely
session_destroy();

// Return a success response
echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully.'
]);
exit;
