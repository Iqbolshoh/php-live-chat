<?php
session_start();
header('Content-Type: application/json');

// Pure backend endpoint — the profile UI itself lives in index.php's modals.
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Avval tizimga kiring']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Noto\'g\'ri so\'rov']);
    exit;
}

include 'db.php';
$db = new Database();

if (!isset($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Xavfsizlik tokeni noto\'g\'ri.']);
    exit;
}

$currentUser = $_SESSION['user'];

// --- HANDLE PROFILE NAME UPDATE ---
if (isset($_POST['update_profile'])) {
    $newName = trim($_POST['name'] ?? '');

    if (empty($newName)) {
        echo json_encode(['success' => false, 'message' => 'Ism bo\'sh bo\'lishi mumkin emas']);
        exit;
    }

    try {
        $db->update('users', ['name' => $newName], 'id = ?', [$currentUser['id']]);
        $_SESSION['user']['name'] = $newName;

        echo json_encode(['success' => true, 'message' => 'Profil muvaffaqiyatli yangilandi']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Profilni yangilab bo\'lmadi']);
        exit;
    }
}

// --- HANDLE PASSWORD UPDATE ---
if (isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        echo json_encode(['success' => false, 'message' => 'Barcha parol maydonlari to\'ldirilishi shart']);
        exit;
    }

    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Yangi parol kamida 8 ta belgidan iborat bo\'lishi kerak']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Yangi parollar mos kelmadi']);
        exit;
    }

    $userRecord = $db->select('users', 'password', 'id = ?', [$currentUser['id']]);

    if (empty($userRecord) || !password_verify($currentPassword, $userRecord[0]['password'])) {
        echo json_encode(['success' => false, 'message' => 'Joriy parol noto\'g\'ri']);
        exit;
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    try {
        $db->update('users', ['password' => $hashedPassword], 'id = ?', [$currentUser['id']]);
        echo json_encode(['success' => true, 'message' => 'Parol muvaffaqiyatli yangilandi']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Parolni yangilab bo\'lmadi']);
        exit;
    }
}

// --- HANDLE AVATAR UPDATE ---
if (isset($_POST['update_avatar'])) {
    $maxAvatarSize = 3 * 1024 * 1024;

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Rasm yuklashda xatolik yuz berdi']);
        exit;
    }

    $uploadedFile = $_FILES['avatar'];

    if ($uploadedFile['size'] > $maxAvatarSize) {
        echo json_encode(['success' => false, 'message' => 'Rasm hajmi 3 MB dan oshmasligi kerak']);
        exit;
    }

    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($fileInfo, $uploadedFile['tmp_name']);
    finfo_close($fileInfo);

    $allowedImageMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedImageMimeTypes[$mimeType])) {
        echo json_encode(['success' => false, 'message' => 'Faqat JPG, PNG yoki WEBP rasm formatlariga ruxsat berilgan']);
        exit;
    }

    $uploadDir = 'uploads/avatars/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        echo json_encode(['success' => false, 'message' => 'Fayl uchun papka yaratilmadi']);
        exit;
    }

    $extension = $allowedImageMimeTypes[$mimeType];
    $storedFileName = 'avatar_' . $currentUser['id'] . '_' . uniqid('', true) . '.' . $extension;
    $destinationPath = $uploadDir . $storedFileName;

    if (!move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)) {
        echo json_encode(['success' => false, 'message' => 'Faylni saqlashda xatolik yuz berdi']);
        exit;
    }

    // Remove the previous avatar file, if any, now that the new one is saved
    $previousAvatarPath = $db->select('users', 'avatar', 'id = ?', [$currentUser['id']])[0]['avatar'] ?? null;

    try {
        $db->update('users', ['avatar' => $destinationPath], 'id = ?', [$currentUser['id']]);
        $_SESSION['user']['avatar'] = $destinationPath;

        if ($previousAvatarPath && is_file($previousAvatarPath)) {
            unlink($previousAvatarPath);
        }

        echo json_encode(['success' => true, 'message' => 'Avatar yangilandi', 'avatar' => $destinationPath]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Avatarni saqlab bo\'lmadi']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Noma\'lum amal']);
