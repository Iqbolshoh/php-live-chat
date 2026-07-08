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

if (isset($_POST['id']) and !empty($_POST['id'])) {
    $id = intval($_POST['id']);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar topilmadi'
    ]);
    exit;
}

include 'db.php';
$db = new Database();

$messages = $db->select(
    'messages',
    '*',
    'sender_id = ? AND receiver_id = ? OR sender_id = ? AND receiver_id = ?',
    [
        $_SESSION['user']['id'],
        $id,
        $id,
        $_SESSION['user']['id']
    ]
);

echo json_encode([
    'success' => true,
    'messages' => 'Xabarlar topildi',
    'data' => $messages
]);
exit;
