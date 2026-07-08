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

// Standard reaction set (matches the picker offered in the UI)
const ALLOWED_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

$messageId = (int)($_POST['message_id'] ?? 0);
$emoji = $_POST['emoji'] ?? '';
$currentUserId = $_SESSION['user']['id'];

if (!in_array($emoji, ALLOWED_REACTIONS, true)) {
    echo json_encode(['success' => false, 'message' => 'Noto\'g\'ri reaksiya.']);
    exit;
}

include 'db.php';
$db = new Database();

// The reacting user must be a participant (sender or receiver) of the message
$targetMessage = $db->select(
    'messages',
    'id',
    'id = ? AND (sender_id = ? OR receiver_id = ?)',
    [$messageId, $currentUserId, $currentUserId]
);

if (empty($targetMessage)) {
    echo json_encode(['success' => false, 'message' => 'Xabar topilmadi!']);
    exit;
}

$existingReaction = $db->select(
    'message_reactions',
    '*',
    'message_id = ? AND user_id = ?',
    [$messageId, $currentUserId]
);

if (!empty($existingReaction)) {
    if ($existingReaction[0]['emoji'] === $emoji) {
        // Tapping the same emoji again removes the reaction
        $db->delete('message_reactions', 'message_id = ? AND user_id = ?', [$messageId, $currentUserId]);
    } else {
        // Switching to a different emoji
        $db->update(
            'message_reactions',
            ['emoji' => $emoji],
            'message_id = ? AND user_id = ?',
            [$messageId, $currentUserId]
        );
    }
} else {
    $db->insert('message_reactions', [
        'message_id' => $messageId,
        'user_id' => $currentUserId,
        'emoji' => $emoji,
    ]);
}

// Return the up-to-date reaction list for this message so the UI can re-render it
$reactionsSql = "
    SELECT r.emoji, r.user_id, u.name AS user_name
    FROM message_reactions r
    JOIN users u ON u.id = r.user_id
    WHERE r.message_id = ?
";
$stmt = $db->execute($reactionsSql, [$messageId]);
$reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data' => $reactions
]);
exit;
