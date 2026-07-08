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

include 'db.php';
$db = new Database();

$currentUserId = $_SESSION['user']['id'];

// Fetch users with the latest message date and unread message count using JOIN
$sql = "
    SELECT
        u.id,
        u.name,
        u.email,
        MAX(m.created_at) as last_message_date,
        SUM(CASE WHEN m.sender_id = u.id AND m.receiver_id = ? AND m.status = 'sent' THEN 1 ELSE 0 END) as unread_count
    FROM users u
    LEFT JOIN messages m ON (u.id = m.sender_id AND m.receiver_id = ?)
                         OR (u.id = m.receiver_id AND m.sender_id = ?)
    WHERE u.id != ?
    GROUP BY u.id, u.name, u.email
    ORDER BY (MAX(m.created_at) IS NULL) ASC, MAX(m.created_at) DESC
";

$stmt = $db->execute($sql, [$currentUserId, $currentUserId, $currentUserId, $currentUserId]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($contacts as &$contact) {
    $contact['unread_count'] = (int)$contact['unread_count'];
}
unset($contact);

echo json_encode([
    'success' => true,
    'data' => $contacts
]);
exit;
