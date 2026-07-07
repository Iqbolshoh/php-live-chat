<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Avval tizimga kiring']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Noto\'g\'ri so\'rov']);
    exit;
}

// if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
//     echo json_encode(['success' => false, 'message' => 'Xavfsizlik tokeni noto\'g\'ri']);
//     exit;
// }

$message_id = (int)$_POST['message_id'];

include 'db.php';
$db = new Database();

// Check if message belongs to user (both sender and receiver can delete)
$message = $db->select(
    'messages',
    '*',
    'id = ? AND (sender_id = ? OR receiver_id = ?)',
    [$message_id, $_SESSION['user']['id'], $_SESSION['user']['id']]
);

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Xabar topilmadi!']);
    exit;
}

// Delete message
$deleted = $db->delete('messages', 'id = ?', [$message_id]);

if ($deleted) {
    echo json_encode(['success' => true, 'message' => 'Xabar o\'chirildi']);
} else {
    echo json_encode(['success' => false, 'message' => 'Xatolik yuz berdi']);
}
