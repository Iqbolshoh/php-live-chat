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

// Pagination: fetch the most recent page, or a page of messages older than "before_id"
$pageSize = 30;
$beforeId = isset($_POST['before_id']) && !empty($_POST['before_id']) ? intval($_POST['before_id']) : null;

include 'db.php';
$db = new Database();
$currentUserId = $_SESSION['user']['id'];

// Update message status to 'read' if the current user is the receiver and status is 'sent'
$updateStatusSql = "UPDATE messages SET status = 'read' WHERE receiver_id = ? AND sender_id = ? AND status = 'sent'";
$db->execute($updateStatusSql, [$currentUserId, $receiverId]);

// Fetch one page of messages (newest first), optionally older than a given message id.
// Requesting pageSize + 1 lets us tell whether an older page still exists.
if ($beforeId !== null) {
    $fetchMessagesSql = "
        SELECT * FROM messages
        WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
          AND id < ?
        ORDER BY id DESC
        LIMIT " . ($pageSize + 1) . "
    ";
    $params = [$currentUserId, $receiverId, $receiverId, $currentUserId, $beforeId];
} else {
    $fetchMessagesSql = "
        SELECT * FROM messages
        WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
        ORDER BY id DESC
        LIMIT " . ($pageSize + 1) . "
    ";
    $params = [$currentUserId, $receiverId, $receiverId, $currentUserId];
}

$stmt = $db->execute($fetchMessagesSql, $params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hasMore = count($messages) > $pageSize;
if ($hasMore) {
    array_pop($messages);
}

// Rows came back newest-first for the LIMIT to work; flip to chronological order for display
$messages = array_reverse($messages);

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
    'data' => $messages,
    'has_more' => $hasMore
]);
exit;
