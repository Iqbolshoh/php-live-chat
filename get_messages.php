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

// Attach reactions to each message
if (!empty($messages)) {
    $messageIds = array_column($messages, 'id');
    $placeholders = implode(',', array_fill(0, count($messageIds), '?'));

    $reactionsSql = "
        SELECT r.message_id, r.emoji, r.user_id, u.name AS user_name
        FROM message_reactions r
        JOIN users u ON u.id = r.user_id
        WHERE r.message_id IN ($placeholders)
    ";
    $reactionsStmt = $db->execute($reactionsSql, $messageIds);
    $reactions = $reactionsStmt->fetchAll(PDO::FETCH_ASSOC);

    $reactionsByMessageId = [];
    foreach ($reactions as $reaction) {
        $reactionsByMessageId[$reaction['message_id']][] = [
            'emoji' => $reaction['emoji'],
            'user_id' => (int)$reaction['user_id'],
            'user_name' => $reaction['user_name'],
        ];
    }

    foreach ($messages as &$message) {
        $message['reactions'] = $reactionsByMessageId[$message['id']] ?? [];
    }
    unset($message);
}

echo json_encode([
    'success' => true,
    'messages' => 'Xabarlar topildi',
    'data' => $messages
]);
exit;
