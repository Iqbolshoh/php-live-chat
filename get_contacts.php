<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'Avval tizimga kiring'
    ]);
    exit;
}

// Check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Noto\'g\'ri so\'rov'
    ]);
    exit;
}

// Validate receiver ID
if (isset($_POST['id']) and !empty($_POST['id'])) {
    $receiverId = intval($_POST['id']);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar topilmadi'
    ]);
    exit;
}

include 'db.php';
$db = new Database();
$currentUserId = $_SESSION['user']['id'];

// Update message status to 'read' if the current user is the receiver and status is 'sent'
$updateStatusSql = "UPDATE messages SET status = 'read' WHERE receiver_id = ? AND sender_id = ? AND status = 'sent'";
$db->execute($updateStatusSql, [$currentUserId, $receiverId]);

// Fetch messages between the two users ordered by time
$fetchMessagesSql = "
    SELECT * FROM messages 
    WHERE (sender_id = ? AND receiver_id = ?) 
       OR (sender_id = ? AND receiver_id = ?) 
    ORDER BY created_at ASC
";
$stmt = $db->execute($fetchMessagesSql, [$currentUserId, $receiverId, $receiverId, $currentUserId]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'messages' => 'Xabarlar topildi',
    'data' => $messages
]);
exit;