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

$message_id = (int)$_POST['message_id'];
$new_message = $_POST['message'];

include 'db.php';
$db = new Database();

$updated = $db->update(
    'messages',
    [
        'message' => $new_message
    ],
    'id = ?',
    [$message_id]
);

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => 'Xabar mufaqqiyatli yangilandi'
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Xabar yangilanmadi'
]);
exit;
