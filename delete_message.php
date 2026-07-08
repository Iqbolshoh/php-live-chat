<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'Avval tizimga kiring'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Noto\'g\'ri so\'rov'
    ]);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode([
        'success' => false,
        'message' => 'Xavfsizlik tokeni noto\'g\'ri.'
    ]);
    exit;
}

$messageId = (int)($_POST['message_id'] ?? 0);

include 'db.php';
$db = new Database();

// Check if message belongs to user (both sender and receiver can delete)
$existingMessage = $db->select(
    'messages',
    '*',
    'id = ? AND (sender_id = ? OR receiver_id = ?)',
    [$messageId, $_SESSION['user']['id'], $_SESSION['user']['id']]
);

if (empty($existingMessage)) {
    echo json_encode(['success' => false, 'message' => 'Xabar topilmadi!']);
    exit;
}

// Delete message
$deleted = $db->delete('messages', 'id = ?', [$messageId]);

if ($deleted) {
    // Remove the attached file from disk, if any
    $filePath = $existingMessage[0]['file_path'] ?? null;
    if ($filePath && is_file($filePath)) {
        unlink($filePath);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Xabar o\'chirildi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Xatolik yuz berdi'
    ]);
}
