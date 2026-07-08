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

// Validate receiver ID
if (!isset($_POST['receiver_id']) || empty($_POST['receiver_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Qabul qiluvchi tanlanmagan.'
    ]);
    exit;
}

$senderId = $_SESSION['user']['id'];
$receiverId = intval($_POST['receiver_id']);
$allowedTypes = ['text', 'image', 'audio'];
$messageType = in_array($_POST['type'] ?? 'text', $allowedTypes, true) ? $_POST['type'] : 'text';
$messageText = trim($_POST['message'] ?? '');

// Prevent sending message to yourself
if ($senderId === $receiverId) {
    echo json_encode([
        'success' => false,
        'message' => 'O\'zingizga xabar yubora olmaysiz.'
    ]);
    exit;
}

// Check message length
if (strlen($messageText) > 5000) {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar juda uzun. Maksimum 5000 ta belgi.'
    ]);
    exit;
}

if ($messageType === 'text' && empty($messageText)) {
    echo json_encode([
        'success' => false,
        'message' => 'Xabar matni bo\'sh bo\'lishi mumkin emas.'
    ]);
    exit;
}

// Include database connection
include 'db.php';
$db = new Database();

// Check if receiver exists
$receiver = $db->select('users', 'id', 'id = ?', [$receiverId]);
if (empty($receiver)) {
    echo json_encode([
        'success' => false,
        'message' => 'Qabul qiluvchi topilmadi.'
    ]);
    exit;
}

$filePath = null;

if ($messageType !== 'text') {
    $maxFileSize = $messageType === 'image' ? 5 * 1024 * 1024 : 10 * 1024 * 1024;

    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'message' => 'Fayl yuklashda xatolik yuz berdi.'
        ]);
        exit;
    }

    $uploadedFile = $_FILES['attachment'];

    if ($uploadedFile['size'] > $maxFileSize) {
        $maxFileSizeMb = $maxFileSize / (1024 * 1024);
        echo json_encode([
            'success' => false,
            'message' => "Fayl hajmi {$maxFileSizeMb} MB dan oshmasligi kerak."
        ]);
        exit;
    }

    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $uploadedFile['tmp_name']);
    finfo_close($fileInfo);

    $allowedImageMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $allowedAudioMimeTypes = [
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
    ];

    if ($messageType === 'image') {
        if (!isset($allowedImageMimeTypes[$mimeType])) {
            echo json_encode([
                'success' => false,
                'message' => 'Faqat JPG, PNG, GIF yoki WEBP rasm formatlariga ruxsat berilgan.'
            ]);
            exit;
        }
        $extension = $allowedImageMimeTypes[$mimeType];
        $uploadDir = 'uploads/images/';
    } else {
        if (!isset($allowedAudioMimeTypes[$mimeType])) {
            echo json_encode([
                'success' => false,
                'message' => 'Faqat audio fayllarni yuborish mumkin.'
            ]);
            exit;
        }
        $extension = $allowedAudioMimeTypes[$mimeType];
        $uploadDir = 'uploads/audio/';
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo json_encode([
            'success' => false,
            'message' => 'Fayl uchun papka yaratilmadi.'
        ]);
        exit;
    }

    $storedFileName = uniqid($messageType . '_', true) . '.' . $extension;
    $destinationPath = $uploadDir . $storedFileName;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)) {
        echo json_encode([
            'success' => false,
            'message' => 'Faylni saqlashda xatolik yuz berdi.'
        ]);
        exit;
    }

    $filePath = $destinationPath;
}

try {
    // Insert message into database
    $insertData = [
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'message' => $messageText,
        'type' => $messageType,
        'file_path' => $filePath,
        'status' => 'sent',
        'created_at' => date('Y-m-d H:i:s')
    ];

    $insertId = $db->insert('messages', $insertData);

    if ($insertId) {
        echo json_encode([
            'success' => true,
            'message' => 'Xabar muvaffaqiyatli yuborildi.',
            'data' => [
                'id' => $insertId,
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'message' => htmlspecialchars($messageText),
                'type' => $messageType,
                'file_path' => $filePath,
                'status' => 'sent',
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'message' => 'Xabar yuborishda xatolik yuz berdi.'
    ]);
} catch (Exception $e) {
    // Log error for debugging
    error_log('Message sending error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Server xatosi yuz berdi. Iltimos, qaytadan urinib ko\'ring.'
    ]);
}
