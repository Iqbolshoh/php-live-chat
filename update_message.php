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
$newMessageText = trim($_POST['message'] ?? '');

if (empty($newMessageText)) {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar matni bo\'sh bo\'lishi mumkin emas.'
    ]);
    exit;
}

if (strlen($newMessageText) > 5000) {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar juda uzun. Maksimum 5000 ta belgi.'
    ]);
    exit;
}

include 'db.php';
$db = new Database();

// Only the original sender may edit their own message
$existingMessage = $db->select('messages', '*', 'id = ? AND sender_id = ?', [$messageId, $_SESSION['user']['id']]);

if (empty($existingMessage)) {
    echo json_encode(['success' => false, 'message' => 'Xabar topilmadi!']);
    exit;
}

// Only plain text messages can be edited
if ($existingMessage[0]['type'] !== 'text') {
    echo json_encode(['success' => false, 'message' => 'Faqat matnli xabarlarni tahrirlash mumkin.']);
    exit;
}

$updated = $db->update(
    'messages',
    [
        'message' => $newMessageText,
        'edited_at' => date('Y-m-d H:i:s')
    ],
    'id = ?',
    [$messageId]
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
