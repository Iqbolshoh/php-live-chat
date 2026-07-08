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

$message_id = (int)$_POST['message_id'];
$new_message = trim($_POST['message'] ?? '');

if (empty($new_message)) {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar matni bo\'sh bo\'lishi mumkin emas.'
    ]);
    exit;
}

if (strlen($new_message) > 5000) {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar juda uzun. Maksimum 5000 ta belgi.'
    ]);
    exit;
}

include 'db.php';
$db = new Database();

// Only the original sender may edit their own message
$message = $db->select('messages', '*', 'id = ? AND sender_id = ?', [$message_id, $_SESSION['user']['id']]);

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Xabar topilmadi!']);
    exit;
}

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
