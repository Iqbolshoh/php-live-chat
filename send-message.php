<?php
// send_message.php
session_start();

header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'Iltimos, avval tizimga kiring.'
    ]);
    exit;
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST is allowed.'
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

// Validate message content
if (!isset($_POST['message']) || empty(trim($_POST['message']))) {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar matni bo\'sh bo\'lishi mumkin emas.'
    ]);
    exit;
}

// Validate receiver ID
if (!isset($_POST['receiver_id']) || empty($_POST['receiver_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Qabul qiluvchi tanlanmagan.'
    ]);
    exit;
}

// Sanitize and validate inputs
$sender_id = $_SESSION['user']['id'];
$receiver_id = intval($_POST['receiver_id']);
$message = trim($_POST['message']);

// Check message length
if (strlen($message) > 5000) {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar juda uzun. Maksimum 5000 ta belgi.'
    ]);
    exit;
}

// Prevent sending message to yourself
if ($sender_id === $receiver_id) {
    echo json_encode([
        'success' => false,
        'message' => 'O\'zingizga xabar yubora olmaysiz.'
    ]);
    exit;
}

// Include database connection
include 'db.php';
$db = new Database();

// Check if receiver exists
$receiver = $db->select('users', '*', 'id = ?', [$receiver_id]);
if (!$receiver || count($receiver) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Qabul qiluvchi topilmadi.'
    ]);
    exit;
}

try {
    // Insert message into database
    $insertData = [
        'sender_id' => $sender_id,
        'receiver_id' => $receiver_id,
        'message' => $message,
        'status' => 'sent',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $insertId = $db->insert('messages', $insertData);

    if ($insertId) {
        // Return success response with message data
        // echo json_encode([
        //     'success' => true,
        //     'message' => 'Xabar muvaffaqiyatli yuborildi.',
        //     'data' => [
        //         'id' => $insertId,
        //         'sender_id' => $sender_id,
        //         'receiver_id' => $receiver_id,
        //         'message' => htmlspecialchars($message),
        //         'status' => 'sent',
        //         'created_at' => date('Y-m-d H:i:s')
        //     ]
        // ]);

        header('Location: index.php?id=' . $receiver_id);
        exit;
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Xabar yuborishda xatolik yuz berdi.'
        ]);
    }
} catch (Exception $e) {
    // Log error for debugging
    error_log('Message sending error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Server xatosi yuz berdi. Iltimos, qaytadan urinib ko\'ring.'
    ]);
}
