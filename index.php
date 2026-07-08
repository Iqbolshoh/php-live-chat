<?php
session_start();

// Redirect to login if the user is not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login/");
    exit;
}

// Generate CSRF token for logout
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

$receiverInitials = '';

// Check if a chat is selected
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $_SESSION['receiver']['id'] = $_GET['id'];
    $_SESSION['receiver']['name'] = $db->select('users', 'name', 'id = ?', [$_GET['id']])[0]['name'];
    $receiverNameParts = explode(' ', trim($_SESSION['receiver']['name']));
    $receiverInitials = strtoupper(substr($receiverNameParts[0], 0, 1) . (isset($receiverNameParts[1]) ? substr($receiverNameParts[1], 0, 1) : ''));
} else {
    $messages = [];
    $_SESSION['receiver']['id'] = null;
    $_SESSION['receiver']['name'] = null;
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SocialChat - Muloqot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            background-attachment: fixed;
            height: 100vh;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
        }
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .message-sent { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); }
        .message-received { background: rgba(51, 65, 85, 0.8); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .user-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .user-card:hover { transform: translateX(4px); background: rgba(59, 130, 246, 0.1); }
        .user-card.active { background: rgba(59, 130, 246, 0.2); border: 1px solid rgba(59, 130, 246, 0.3); transform: translateX(4px); }
        .chat-window { animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        @keyframes slideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .sidebar-overlay {
            position: fixed; inset: 0; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(4px); z-index: 40;
            opacity: 0; pointer-events: none; transition: opacity 0.3s ease-in-out;
        }
        .sidebar-overlay.active { opacity: 1; pointer-events: auto; }
        .sidebar-panel { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.03); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(59, 130, 246, 0.3); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(59, 130, 246, 0.5); }
        .pulse-dot { animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .empty-state { animation: fadeIn 0.5s ease-out; }
        .logout-modal { animation: fadeIn 0.2s ease-out; }
        .message-menu-btn { opacity: 0; visibility: visible; transition: all 0.2s ease; }
        .message-group:hover .message-menu-btn { opacity: 1 !important; }
        .message-group:hover .message-menu-btn:hover { background: rgba(71, 85, 105, 0.9) !important; transform: scale(1.1); }
        .message-dropdown { animation: fadeIn 0.2s ease-out; transform-origin: top right; }
        .justify-end .message-dropdown { transform-origin: top left; }
        @media (max-width: 768px) {
            .sidebar-panel { position: fixed; left: 0; top: 0; bottom: 0; width: 85%; max-width: 320px; z-index: 50; transform: translateX(-100%); }
            .sidebar-panel.active { transform: translateX(0); }
        }
    </style>
</head>

<body class="text-gray-200 antialiased">

    <div class="sidebar-overlay md:hidden" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="fixed inset-0 z-[60] flex items-center justify-center hidden" id="logoutModal">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeLogoutModal()"></div>
        <div class="glass-panel rounded-2xl p-6 md:p-8 max-w-sm w-full mx-4 relative z-10 logout-modal">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-red-500/20 rounded-full flex items-center justify-center">
                    <i class="fas fa-sign-out-alt text-red-400 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold mb-2">Chiqish</h3>
                <p class="text-gray-400 text-sm mb-6">Haqiqatdan ham akkauntdan chiqmoqchimisiz?</p>
                <div class="flex gap-3">
                    <button onclick="closeLogoutModal()" class="flex-1 px-4 py-2.5 rounded-xl bg-slate-700/50 hover:bg-slate-700 transition-colors font-medium">
                        Bekor qilish
                    </button>
                    <button onclick="performLogout()" id="logoutBtn" class="flex-1 px-4 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 transition-all font-medium flex items-center justify-center gap-2">
                        <span>Chiqish</span>
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Generic confirmation modal (replaces native confirm()) -->
    <div class="fixed inset-0 z-[60] flex items-center justify-center hidden" id="confirmModal">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeConfirmModal()"></div>
        <div class="glass-panel rounded-2xl p-6 md:p-8 max-w-sm w-full mx-4 relative z-10 logout-modal">
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-red-500/20 rounded-full flex items-center justify-center">
                    <i class="fas fa-trash-alt text-red-400 text-2xl"></i>
                </div>
                <h3 class="text-xl font-bold mb-2" id="confirmModalTitle">Tasdiqlash</h3>
                <p class="text-gray-400 text-sm mb-6" id="confirmModalText"></p>
                <div class="flex gap-3">
                    <button onclick="closeConfirmModal()" class="flex-1 px-4 py-2.5 rounded-xl bg-slate-700/50 hover:bg-slate-700 transition-colors font-medium">
                        Bekor qilish
                    </button>
                    <button id="confirmModalActionBtn" class="flex-1 px-4 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-red-700 hover:from-red-500 hover:to-red-600 transition-all font-medium">
                        Ha
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- My profile modal -->
    <div class="fixed inset-0 z-[60] flex items-center justify-center hidden p-4" id="myProfileModal">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeMyProfileModal()"></div>
        <div class="glass-panel rounded-2xl p-6 md:p-8 max-w-md w-full relative z-10 logout-modal max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold">Mening profilim</h3>
                <button onclick="closeMyProfileModal()" class="w-8 h-8 rounded-lg hover:bg-white/10 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-gray-400"></i>
                </button>
            </div>

            <form id="myProfileNameForm" class="space-y-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2" for="myProfileName">Ism</label>
                    <input type="text" id="myProfileName" name="name" class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-white" required>
                </div>
                <p class="text-xs text-gray-500"><?= htmlspecialchars($_SESSION['user']['email'] ?? '') ?></p>
                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors">
                    Ismni saqlash
                </button>
            </form>

            <div class="border-t border-gray-700/50 pt-6">
                <h4 class="text-sm font-semibold text-gray-300 mb-4">Parolni o'zgartirish</h4>
                <form id="myProfilePasswordForm" class="space-y-4">
                    <input type="password" id="currentPassword" placeholder="Joriy parol" class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition-all text-white" required>
                    <input type="password" id="newPassword" placeholder="Yangi parol" minlength="8" class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition-all text-white" required>
                    <input type="password" id="confirmNewPassword" placeholder="Yangi parolni tasdiqlang" minlength="8" class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition-all text-white" required>
                    <button type="submit" class="w-full py-2.5 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white border border-red-500/20 font-semibold rounded-xl transition-all">
                        Parolni yangilash
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Receiver (chat partner) profile modal -->
    <div class="fixed inset-0 z-[60] flex items-center justify-center hidden p-4" id="receiverProfileModal">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeReceiverProfileModal()"></div>
        <div class="glass-panel rounded-2xl p-6 md:p-8 max-w-sm w-full relative z-10 logout-modal">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold">Foydalanuvchi</h3>
                <button onclick="closeReceiverProfileModal()" class="w-8 h-8 rounded-lg hover:bg-white/10 flex items-center justify-center transition-colors">
                    <i class="fas fa-times text-gray-400"></i>
                </button>
            </div>
            <div class="text-center">
                <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center text-2xl font-bold text-white shadow-lg" id="receiverProfileAvatar"></div>
                <h4 class="text-lg font-bold" id="receiverProfileName"></h4>
                <p class="text-gray-400 text-sm" id="receiverProfileEmail"></p>
            </div>
        </div>
    </div>

    <div class="flex h-screen p-3 md:p-4 gap-3 md:gap-4">

        <div class="sidebar-panel glass-panel rounded-2xl md:rounded-3xl flex flex-col flex-shrink-0 shadow-2xl md:relative md:transform-none" id="sidebar">
            <div class="p-4 md:p-5 border-b border-gray-700/30">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-comment-dots text-white text-sm"></i>
                        </div>
                        <h2 class="text-lg md:text-xl font-bold bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">
                            SocialChat
                        </h2>
                    </div>
                    <button class="md:hidden w-8 h-8 rounded-lg hover:bg-white/10 flex items-center justify-center transition-colors" onclick="toggleSidebar()">
                        <i class="fas fa-times text-gray-400"></i>
                    </button>
                </div>

                <div class="relative">
                    <input type="text" id="contactSearchInput" placeholder="Qidirish..." class="w-full bg-slate-800/50 rounded-xl px-4 py-2.5 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500/50 text-sm transition-all">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto custom-scrollbar p-2 md:p-3 space-y-1" id="contactsList">
                <?php foreach ($contacts as $contact) : ?>
                    <?php
                    $nameParts = explode(' ', trim($contact['name']));
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                    $safeName = htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8');
                    $userId = $contact['id'];
                    $isActive = (isset($_GET['id']) && $_GET['id'] == $userId) ? 'active' : '';
                    $unreadCount = (int)$contact['unread_count'];
                    ?>

                    <a href="?id=<?= $userId ?>" class="user-card flex items-center gap-3 p-3 rounded-xl cursor-pointer block hover:no-underline <?= $isActive ?>">
                        <div class="relative flex-shrink-0">
                            <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-cyan-500 rounded-full flex items-center justify-center shadow-lg">
                                <span class="font-bold text-white"><?= $initials ?></span>
                            </div>
                            <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-400 rounded-full border-2 border-slate-800 pulse-dot"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-0.5">
                                <p class="font-semibold text-sm truncate"><?= $safeName ?></p>
                                <?php if ($unreadCount > 0) : ?>
                                    <span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">
                                        <?= $unreadCount ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-xs text-gray-400">Onlayn</p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="p-3 md:p-4 border-t border-gray-700/30">
                <div class="flex items-center gap-3">
                    <button type="button" onclick="openMyProfileModal()" class="flex items-center gap-3 flex-1 min-w-0 text-left group/profile">
                        <div class="w-10 h-10 bg-gradient-to-br from-emerald-400 to-cyan-500 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="font-bold text-white text-sm">
                                <?php echo isset($_SESSION['user']['email']) ? strtoupper(substr($_SESSION['user']['email'], 0, 2)) : 'ME'; ?>
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate group-hover/profile:text-blue-400 transition-colors">
                                <?php echo isset($_SESSION['user']['email']) ? htmlspecialchars($_SESSION['user']['email']) : 'User'; ?>
                            </p>
                            <p class="text-xs text-gray-400">Mening profilim</p>
                        </div>
                    </button>
                    <button onclick="openLogoutModal()" class="w-8 h-8 rounded-lg hover:bg-red-500/20 flex items-center justify-center transition-colors group flex-shrink-0" title="Chiqish">
                        <i class="fas fa-sign-out-alt text-gray-400 group-hover:text-red-400 text-sm transition-colors"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex-1 flex flex-col">
            <?php if (isset($_GET['id']) && !empty($_GET['id'])): ?>
                <div class="glass-panel rounded-2xl md:rounded-3xl flex flex-col h-full shadow-2xl chat-window">
                    <div class="p-3 md:p-4 border-b border-gray-700/30 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button class="md:hidden w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center flex-shrink-0 transition-colors" onclick="toggleSidebar()">
                                <i class="fas fa-bars text-gray-400"></i>
                            </button>
                            <button class="hidden md:flex w-10 h-10 rounded-xl hover:bg-white/10 items-center justify-center flex-shrink-0 transition-colors" onclick="closeChat()">
                                <i class="fas fa-arrow-left text-gray-400"></i>
                            </button>
                            <button type="button" onclick="openReceiverProfileModal()" class="flex items-center gap-3 text-left hover:opacity-80 transition-opacity">
                                <div class="relative flex-shrink-0">
                                    <div class="w-10 h-10 md:w-11 md:h-11 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center shadow-lg">
                                        <span class="font-bold text-white text-sm"><?= $receiverInitials ?></span>
                                    </div>
                                    <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-800 pulse-dot"></span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm md:text-base"><?= htmlspecialchars($_SESSION['receiver']['name']) ?></h3>
                                    <p class="text-xs text-green-400">● Onlayn</p>
                                </div>
                            </button>
                        </div>
                        <div class="flex gap-1 md:gap-2">
                            <button type="button" onclick="toggleMessageSearch()" class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors" title="Xabarlarni qidirish">
                                <i class="fas fa-search text-gray-400"></i>
                            </button>
                            <button onclick="openLogoutModal()" class="w-10 h-10 rounded-xl hover:bg-red-500/20 flex items-center justify-center transition-colors group">
                                <i class="fas fa-sign-out-alt text-gray-400 group-hover:text-red-400 text-sm transition-colors"></i>
                            </button>
                        </div>
                    </div>

                    <div id="messageSearchBar" class="hidden items-center gap-2 px-4 py-2 border-b border-gray-700/30">
                        <i class="fas fa-search text-gray-500 text-sm"></i>
                        <input type="text" id="messageSearchInput" placeholder="Xabarlarni qidirish..." class="flex-1 bg-transparent focus:outline-none text-sm">
                        <span id="messageSearchCount" class="text-xs text-gray-500 flex-shrink-0"></span>
                        <button type="button" onclick="closeMessageSearch()" class="w-6 h-6 rounded-full hover:bg-white/10 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-times text-gray-400 text-xs"></i>
                        </button>
                    </div>

                    <div class="flex-1 overflow-y-auto custom-scrollbar p-4 md:p-6 space-y-4" id="messagesContainer">
                        </div>

                    <div class="p-3 md:p-4 border-t border-gray-700/30">
                        <div id="editBar" class="hidden items-center gap-3 mb-2 px-4 py-2 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                            <i class="fas fa-pen text-blue-400"></i>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-blue-400 font-semibold">Xabarni tahrirlash</p>
                                <p class="text-xs text-gray-400 truncate" id="editBarPreview"></p>
                            </div>
                            <button type="button" onclick="cancelEditMessage()" class="w-7 h-7 rounded-full hover:bg-white/10 flex items-center justify-center transition-colors">
                                <i class="fas fa-times text-gray-400"></i>
                            </button>
                        </div>
                        <div id="recordingBar" class="hidden items-center gap-3 mb-2 px-4 py-2 bg-red-500/10 border border-red-500/20 rounded-xl">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500 pulse-dot"></span>
                            <span class="text-sm text-red-400 flex-1">Ovozli xabar yozilmoqda... <span id="recordingTime">00:00</span></span>
                            <button type="button" onclick="cancelRecording()" class="text-xs text-gray-400 hover:text-white transition-colors">Bekor qilish</button>
                            <button type="button" onclick="stopRecording()" class="w-8 h-8 rounded-full bg-red-500 hover:bg-red-600 flex items-center justify-center transition-colors">
                                <i class="fas fa-stop text-white text-xs"></i>
                            </button>
                        </div>
                        <form class="flex items-end gap-2 md:gap-3" action="send_message.php" method="POST" id="messageForm">
                            <input type="file" id="attachmentInput" accept="image/*" class="hidden">
                            <button type="button" id="attachBtn" onclick="document.getElementById('attachmentInput').click()" class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors flex-shrink-0" title="Rasm yuborish (5 MB gacha)">
                                <i class="fas fa-paperclip text-gray-400"></i>
                            </button>
                            <button type="button" id="micBtn" onclick="toggleRecording()" class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors flex-shrink-0" title="Ovozli xabar (1 daqiqagacha)">
                                <i class="fas fa-microphone text-gray-400"></i>
                            </button>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                            <input type="hidden" name="receiver_id" value="<?= htmlspecialchars($_SESSION['receiver']['id']) ?>">
                            <div class="flex-1 relative">
                                <textarea
                                    rows="1"
                                    placeholder="Xabar yozing..."
                                    class="w-full bg-slate-800/50 rounded-2xl px-4 py-3 pr-20 focus:outline-none focus:ring-2 focus:ring-blue-500/50 resize-none text-sm transition-all"
                                    style="min-height: 46px; max-height: 120px;"
                                    id="messageInput" name="message"></textarea>
                                <button type="button" id="emojiBtn" onclick="toggleEmojiPicker(event)" class="absolute right-11 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full hover:bg-white/10 flex items-center justify-center transition-colors">
                                    <i class="fas fa-face-smile text-gray-400 text-sm"></i>
                                </button>
                                <div id="emojiPicker" class="hidden absolute bottom-full right-0 mb-2 w-64 max-h-56 overflow-y-auto custom-scrollbar bg-slate-800 rounded-xl shadow-2xl border border-gray-700/30 p-3 grid grid-cols-8 gap-1 z-50"></div>
                                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 flex items-center justify-center transition-all shadow-lg hover:shadow-blue-500/25">
                                    <i class="fas fa-paper-plane text-white text-xs"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="glass-panel rounded-2xl md:rounded-3xl flex-1 flex items-center justify-center shadow-2xl empty-state">
                    <div class="text-center px-6">
                        <div class="w-24 h-24 md:w-32 md:h-32 mx-auto mb-6 bg-gradient-to-br from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-2xl">
                            <i class="fas fa-comments text-4xl md:text-5xl text-white"></i>
                        </div>
                        <h2 class="text-2xl md:text-3xl font-bold mb-3 bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">
                            SocialChat'ga xush kelibsiz!
                        </h2>
                        <p class="text-gray-400 text-sm md:text-base mb-6 max-w-md">
                            Muloqotni boshlash uchun chap tomondan suhbatdosh tanlang
                        </p>
                        <div class="flex flex-col sm:flex-row gap-3 justify-center">
                            <button class="md:hidden bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all transform hover:scale-105" onclick="toggleSidebar()">
                                <i class="fas fa-users mr-2"></i> Kontaktlar
                            </button>
                            <button onclick="openLogoutModal()" class="bg-red-500/20 hover:bg-red-500/30 text-red-400 px-6 py-3 rounded-xl font-semibold transition-all flex items-center justify-center gap-2">
                                <i class="fas fa-sign-out-alt"></i> Chiqish
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <form id="logoutForm" action="logout" method="POST" class="hidden">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <script>
        // ============ Global Variables ============
        const receiverId = <?= isset($_GET['id']) ? (int)$_GET['id'] : 'null' ?>;
        const userId = <?= (int)$_SESSION['user']['id'] ?>;
        const receiverInitials = '<?= $receiverInitials ?>';
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        const STANDARD_REACTIONS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];
        const EMOJI_PICKER_LIST = [
            '😀', '😁', '😂', '🤣', '😊', '😍', '😘', '😜', '🤔', '😎', '😴', '😢',
            '😭', '😡', '😱', '🥳', '👍', '👎', '👏', '🙏', '💪', '🤝', '✌️', '🤞',
            '👌', '🤙', '💯', '🔥', '✨', '🎉', '❤️', '💔', '💕', '😇', '🙄', '😅',
            '😉', '🤗', '😋', '😏', '🤩', '🥰', '😐', '😬', '🤯', '🥺', '😷', '🤒'
        ];
        const MAX_RECORDING_MS = 60 * 1000;

        let messagesLoaded = false;
        let isUserScrolling = false;
        let lastContactsData = '';
        let messagesPollId = null;
        let contactsPollId = null;
        let hasMoreOlderMessages = true;
        let isLoadingOlderMessages = false;
        let editingMessageId = null;
        let confirmModalCallback = null;
        let latestContacts = [];
        let contactSearchTerm = '';

        // Tracks messages already in the DOM (id -> {data, signature, node}) so
        // polling only patches what actually changed instead of replacing the
        // whole list — this is what stops the container from resetting scroll
        // and "shaking" on every refresh.
        const renderedMessages = new Map();

        // Latest known contact list (id -> contact), used to populate the
        // receiver profile modal without an extra network round-trip.
        const contactsById = new Map();

        // Monitor scroll event to check if user is reading old messages, and
        // load an older page of history once they scroll near the top.
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContainer = document.getElementById('messagesContainer');
            if (messagesContainer) {
                messagesContainer.addEventListener('scroll', function() {
                    const distanceFromBottom = this.scrollHeight - this.scrollTop - this.clientHeight;
                    isUserScrolling = distanceFromBottom > 50;

                    if (this.scrollTop < 100) {
                        loadOlderMessages();
                    }
                });
            }
        });

        // ============ Escaping Helpers ============
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function escapeAttribute(text) {
            return String(text ?? '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        function formatTime(dateString) {
            if (!dateString) return 'Hozir';
            const date = new Date(dateString);
            const hours = date.getHours().toString().padStart(2, '0');
            const minutes = date.getMinutes().toString().padStart(2, '0');
            return `${hours}:${minutes}`;
        }

        // ============ Message Menu Functions ============
        function toggleMessageMenu(event, messageId) {
            event.stopPropagation();
            document.querySelectorAll('.reaction-picker').forEach(picker => picker.classList.add('hidden'));
            document.querySelectorAll('.message-dropdown').forEach(dropdown => {
                if (dropdown.id !== `messageMenu-${messageId}`) {
                    dropdown.classList.add('hidden');
                }
            });
            const dropdown = document.getElementById(`messageMenu-${messageId}`);
            if (dropdown) dropdown.classList.toggle('hidden');
        }

        function toggleReactionPicker(event, messageId) {
            event.stopPropagation();
            document.querySelectorAll('.message-dropdown').forEach(dropdown => dropdown.classList.add('hidden'));
            document.querySelectorAll('.reaction-picker').forEach(picker => {
                if (picker.id !== `reactionPicker-${messageId}`) {
                    picker.classList.add('hidden');
                }
            });
            const picker = document.getElementById(`reactionPicker-${messageId}`);
            if (picker) picker.classList.toggle('hidden');
        }

        document.addEventListener('click', function(event) {
            if (!event.target.closest('.message-menu-btn') && !event.target.closest('.message-dropdown')) {
                document.querySelectorAll('.message-dropdown').forEach(dropdown => {
                    dropdown.classList.add('hidden');
                });
            }
            if (!event.target.closest('.message-menu-btn') && !event.target.closest('.reaction-picker')) {
                document.querySelectorAll('.reaction-picker').forEach(picker => picker.classList.add('hidden'));
            }
            if (!event.target.closest('#emojiBtn') && !event.target.closest('#emojiPicker')) {
                const emojiPicker = document.getElementById('emojiPicker');
                if (emojiPicker) emojiPicker.classList.add('hidden');
            }
        });

        // ============ Inline Message Editing (Telegram-style) ============
        function editMessage(messageId) {
            document.getElementById(`messageMenu-${messageId}`).classList.add('hidden');
            const messageGroup = document.querySelector(`.message-group[data-message-id="${messageId}"]`);
            if (!messageGroup || messageGroup.dataset.type !== 'text') return;
            const messageP = messageGroup.querySelector('.message-sent p, .message-received p');
            if (!messageP) return;

            editingMessageId = Number(messageId);

            const textarea = document.getElementById('messageInput');
            textarea.value = messageP.textContent;
            textarea.dispatchEvent(new Event('input'));
            textarea.focus();

            document.getElementById('editBarPreview').textContent = messageP.textContent;
            const editBar = document.getElementById('editBar');
            editBar.classList.remove('hidden');
            editBar.classList.add('flex');
        }

        function cancelEditMessage() {
            editingMessageId = null;
            const textarea = document.getElementById('messageInput');
            textarea.value = '';
            textarea.style.height = 'auto';

            const editBar = document.getElementById('editBar');
            editBar.classList.add('hidden');
            editBar.classList.remove('flex');
        }

        async function updateMessage(messageId, newMessage) {
            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('message', newMessage);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('update_message.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    showNotification('Xabar yangilandi!', 'success');
                    loadMessages();
                    return true;
                }
                showNotification(data.message || 'Xatolik yuz berdi.', 'error');
                return false;
            } catch (error) {
                console.error('Update message error:', error);
                showNotification('Xatolik yuz berdi.', 'error');
                return false;
            }
        }

        // ============ Copy (with a fallback for non-secure contexts) ============
        function copyMessage(messageId) {
            document.getElementById(`messageMenu-${messageId}`).classList.add('hidden');
            const messageGroup = document.querySelector(`.message-group[data-message-id="${messageId}"]`);
            if (!messageGroup) return;
            const messageP = messageGroup.querySelector('.message-sent p, .message-received p');
            if (!messageP) return;
            copyTextToClipboard(messageP.textContent);
        }

        function copyTextToClipboard(text) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showNotification('Xabar nusxalandi!', 'success');
                }).catch(() => {
                    fallbackCopyToClipboard(text);
                });
            } else {
                fallbackCopyToClipboard(text);
            }
        }

        function fallbackCopyToClipboard(text) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();
            try {
                const successful = document.execCommand('copy');
                showNotification(successful ? 'Xabar nusxalandi!' : 'Nusxalashda xatolik!', successful ? 'success' : 'error');
            } catch (error) {
                showNotification('Nusxalashda xatolik!', 'error');
            }
            document.body.removeChild(textarea);
        }

        // ============ Confirmation Modal (replaces native confirm()) ============
        function openConfirmModal({ title, text, actionLabel = 'Ha', onConfirm }) {
            document.getElementById('confirmModalTitle').textContent = title;
            document.getElementById('confirmModalText').textContent = text;
            document.getElementById('confirmModalActionBtn').textContent = actionLabel;
            confirmModalCallback = onConfirm;
            document.getElementById('confirmModal').classList.remove('hidden');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.add('hidden');
            confirmModalCallback = null;
        }

        function deleteMessage(messageId) {
            document.getElementById(`messageMenu-${messageId}`).classList.add('hidden');
            openConfirmModal({
                title: 'Xabarni o\'chirish',
                text: 'Haqiqatdan ham ushbu xabarni o\'chirishni istaysizmi?',
                actionLabel: 'O\'chirish',
                onConfirm: () => performDeleteMessage(messageId)
            });
        }

        function performDeleteMessage(messageId) {
            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('csrf_token', csrfToken);

            fetch('delete_message.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const messageGroup = document.querySelector(`.message-group[data-message-id="${messageId}"]`);
                        if (messageGroup) {
                            messageGroup.style.transition = 'all 0.3s ease';
                            messageGroup.style.opacity = '0';
                            messageGroup.style.transform = 'scale(0.8)';
                            setTimeout(() => messageGroup.remove(), 300);
                        }
                        renderedMessages.delete(Number(messageId));
                        showNotification('Xabar o\'chirildi!', 'success');
                    } else {
                        showNotification(data.message || 'Xatolik yuz berdi.', 'error');
                    }
                });
        }

        // ============ Reactions ============
        function groupReactions(reactions) {
            const groups = new Map();
            (reactions || []).forEach(reaction => {
                if (!groups.has(reaction.emoji)) groups.set(reaction.emoji, []);
                groups.get(reaction.emoji).push(reaction);
            });
            return groups;
        }

        function buildReactionsHTML(message) {
            const groups = groupReactions(message.reactions);
            if (groups.size === 0) return '';

            let html = '<div class="flex flex-wrap gap-1 mt-1">';
            groups.forEach((reactors, emoji) => {
                const reactedByMe = reactors.some(reactor => reactor.user_id === userId);
                const names = reactors.map(reactor => reactor.user_name).join(', ');
                html += `<button type="button" onclick="reactToMessage(${message.id}, '${emoji}')" title="${escapeAttribute(names)}" class="flex items-center gap-1 px-2 py-0.5 rounded-full text-xs transition-colors ${reactedByMe ? 'bg-blue-500/30 border border-blue-400/50' : 'bg-slate-700/60 border border-transparent hover:border-gray-500'}">
                    <span>${emoji}</span><span class="text-gray-300">${reactors.length}</span>
                </button>`;
            });
            html += '</div>';
            return html;
        }

        function buildReactionPickerHTML(messageId) {
            return STANDARD_REACTIONS.map(emoji =>
                `<button type="button" onclick="reactToMessage(${messageId}, '${emoji}')" class="text-lg hover:scale-125 transition-transform">${emoji}</button>`
            ).join('');
        }

        async function reactToMessage(messageId, emoji) {
            document.querySelectorAll('.reaction-picker').forEach(picker => picker.classList.add('hidden'));

            const formData = new FormData();
            formData.append('message_id', messageId);
            formData.append('emoji', emoji);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('react_message.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    const cached = renderedMessages.get(Number(messageId));
                    if (cached) {
                        cached.data.reactions = data.data;
                        cached.signature = messageSignature(cached.data);
                        patchMessageNode(cached.node, cached.data);
                    }
                } else {
                    showNotification(data.message || 'Xatolik yuz berdi.', 'error');
                }
            } catch (error) {
                console.error('React error:', error);
                showNotification('Xatolik yuz berdi.', 'error');
            }
        }

        function showNotification(message, type = 'info') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
            const notification = document.createElement('div');
            notification.className = `fixed bottom-20 left-1/2 transform -translate-x-1/2 ${colors[type]} text-white px-6 py-3 rounded-xl shadow-lg z-50 transition-all`;
            notification.style.animation = 'fadeIn 0.3s ease-out';
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translate(-50%, 20px)';
                notification.style.transition = 'all 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 2000);
        }

        // ============ Sidebar and UI Functions ============
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function closeChat() {
            window.location.href = window.location.pathname;
        }

        function openLogoutModal() {
            document.getElementById('logoutModal').classList.remove('hidden');
        }

        function closeLogoutModal() {
            document.getElementById('logoutModal').classList.add('hidden');
        }

        async function performLogout() {
            const logoutBtn = document.getElementById('logoutBtn');
            const originalHTML = logoutBtn.innerHTML;
            logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Chiqilmoqda...</span>';
            logoutBtn.disabled = true;

            try {
                const csrfToken = '<?php echo $_SESSION["csrf_token"]; ?>';
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);

                const response = await fetch('logout/index.php', {
                    method: 'POST', body: formData, headers: { 'Accept': 'application/json' }
                });

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const data = await response.json();
                    if (data.success) {
                        window.location.href = 'login/';
                    } else {
                        showNotification(data.message || 'Xatolik yuz berdi.', 'error');
                        logoutBtn.innerHTML = originalHTML;
                        logoutBtn.disabled = false;
                    }
                } else {
                    window.location.href = 'login/';
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = 'login/';
            }
        }

        // ============ Message Rendering ============
        function statusIconHTML(status) {
            return status === 'read'
                ? '<i class="fas fa-check-double text-blue-400 text-[10px]"></i>'
                : '<i class="fas fa-check text-gray-400 text-[10px]"></i>';
        }

        function buildBubbleInner(message) {
            let mediaHtml = '';
            if (message.type === 'image' && message.file_path) {
                const source = escapeAttribute(message.file_path);
                mediaHtml = `<img src="${source}" alt="Rasm" class="rounded-lg max-w-full max-h-72 cursor-pointer" onclick="window.open('${source}', '_blank')" loading="lazy">`;
            } else if (message.type === 'audio' && message.file_path) {
                const source = escapeAttribute(message.file_path);
                mediaHtml = `<audio controls class="max-w-[240px] h-10 align-middle" src="${source}"></audio>`;
            }
            const captionHtml = message.message
                ? `<p class="text-sm ${mediaHtml ? 'mt-2' : ''}">${escapeHtml(message.message)}</p>`
                : '';
            return mediaHtml + captionHtml;
        }

        function buildMenuHTML(message, isOwn) {
            const items = [];
            if (isOwn && message.type === 'text') {
                items.push(`<button onclick="editMessage(${message.id})" class="w-full px-3 py-2 text-left text-xs hover:bg-slate-700 flex items-center gap-2 transition-colors"><i class="fas fa-edit text-blue-400"></i><span>Tahrirlash</span></button>`);
            }
            if (message.type === 'text') {
                items.push(`<button onclick="copyMessage(${message.id})" class="w-full px-3 py-2 text-left text-xs hover:bg-slate-700 flex items-center gap-2 transition-colors"><i class="fas fa-copy text-green-400"></i><span>Nusxalash</span></button>`);
            }
            items.push(`<button onclick="deleteMessage(${message.id})" class="w-full px-3 py-2 text-left text-xs hover:bg-slate-700 flex items-center gap-2 transition-colors text-red-400"><i class="fas fa-trash-alt"></i><span>O'chirish</span></button>`);
            return items.join('');
        }

        function messageSignature(message) {
            const reactionsKey = (message.reactions || [])
                .map(reaction => `${reaction.emoji}:${reaction.user_id}`)
                .sort()
                .join(',');
            return `${message.message}|${message.status}|${message.edited_at || ''}|${reactionsKey}`;
        }

        function buildMessageHTML(message) {
            const isOwn = userId === message.sender_id;
            const bubbleInner = buildBubbleInner(message);
            const menuInner = buildMenuHTML(message, isOwn);
            const reactionPickerInner = buildReactionPickerHTML(message.id);
            const reactionsHtml = buildReactionsHTML(message);
            const editedHidden = (message.type === 'text' && message.edited_at) ? '' : 'hidden';
            const bubblePadding = message.type === 'image' ? 'p-1.5' : 'px-3 py-2 md:px-4 md:py-3';
            const sideClass = isOwn ? '-left-14' : '-right-14';
            const menuSideClass = isOwn ? '-left-2' : '-right-2';

            const actionsHtml = `
                <div class="message-actions absolute top-2 ${sideClass} flex items-center gap-1 z-10">
                    <button onclick="toggleReactionPicker(event, ${message.id})" class="message-menu-btn w-6 h-6 rounded-full bg-slate-700/80 hover:bg-slate-600 flex items-center justify-center transition-all">
                        <i class="fas fa-face-smile text-gray-400 text-[10px]"></i>
                    </button>
                    <button onclick="toggleMessageMenu(event, ${message.id})" class="message-menu-btn w-6 h-6 rounded-full bg-slate-700/80 hover:bg-slate-600 flex items-center justify-center transition-all">
                        <i class="fas fa-ellipsis-v text-gray-400 text-[10px]"></i>
                    </button>
                </div>
                <div id="reactionPicker-${message.id}" class="reaction-picker hidden absolute ${menuSideClass} top-8 flex items-center gap-1.5 bg-slate-800 rounded-full shadow-2xl border border-gray-700/30 px-3 py-2 z-50">
                    ${reactionPickerInner}
                </div>
                <div id="messageMenu-${message.id}" class="message-dropdown hidden absolute ${menuSideClass} top-8 w-36 bg-slate-800 rounded-xl shadow-2xl border border-gray-700/30 overflow-hidden z-50">
                    ${menuInner}
                </div>`;

            const metaHtml = `
                <p class="message-meta text-[10px] text-gray-500 mt-1 ${isOwn ? 'mr-1 justify-end' : 'ml-1'} flex items-center gap-1">
                    <span class="message-time">${formatTime(message.created_at)}</span>
                    <span class="message-edited-tag italic ${editedHidden}">(tahrirlangan)</span>
                    ${isOwn ? `<span class="message-status">${statusIconHTML(message.status)}</span>` : ''}
                </p>`;

            const bubbleClass = isOwn ? 'message-sent text-white' : 'message-received';

            if (isOwn) {
                return `
                <div class="flex items-start gap-2 md:gap-3 justify-end message-group group" data-message-id="${message.id}" data-type="${message.type}">
                    <div class="max-w-[75%] md:max-w-md relative">
                        ${actionsHtml}
                        <div class="${bubbleClass} rounded-2xl rounded-tr-none ${bubblePadding}">${bubbleInner}</div>
                        <div class="message-reactions-slot">${reactionsHtml}</div>
                        ${metaHtml}
                    </div>
                </div>`;
            }

            return `
            <div class="flex items-start gap-2 md:gap-3 message-group group" data-message-id="${message.id}" data-type="${message.type}">
                <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                    <span class="text-xs font-bold text-white">${receiverInitials}</span>
                </div>
                <div class="max-w-[75%] md:max-w-md relative">
                    ${actionsHtml}
                    <div class="${bubbleClass} rounded-2xl rounded-tl-none ${bubblePadding}">${bubbleInner}</div>
                    <div class="message-reactions-slot">${reactionsHtml}</div>
                    ${metaHtml}
                </div>
            </div>`;
        }

        function patchMessageNode(node, message) {
            if (!node) return;

            const statusEl = node.querySelector('.message-status');
            if (statusEl) statusEl.innerHTML = statusIconHTML(message.status);

            const bubble = node.querySelector('.message-sent, .message-received');
            if (bubble) bubble.innerHTML = buildBubbleInner(message);

            const timeEl = node.querySelector('.message-time');
            if (timeEl) timeEl.textContent = formatTime(message.created_at);

            const editedEl = node.querySelector('.message-edited-tag');
            if (editedEl) {
                editedEl.classList.toggle('hidden', !(message.type === 'text' && message.edited_at));
            }

            const reactionsSlot = node.querySelector('.message-reactions-slot');
            if (reactionsSlot) reactionsSlot.innerHTML = buildReactionsHTML(message);
        }

        // ============ Message Loading ============
        function notifyNewReactions(previousReactions, currentReactions) {
            const previousKeys = new Set(previousReactions.map(reaction => `${reaction.user_id}:${reaction.emoji}`));
            currentReactions.forEach(reaction => {
                const key = `${reaction.user_id}:${reaction.emoji}`;
                if (!previousKeys.has(key) && reaction.user_id !== userId) {
                    showNotification(`${reaction.user_name} ${reaction.emoji} bilan reaksiya bildirdi`, 'info');
                }
            });
        }

        // Only the most recent page is polled; this returns the id of the oldest
        // message currently rendered so "load more" can page further back.
        function getOldestLoadedMessageId() {
            if (renderedMessages.size === 0) return null;
            return Math.min(...renderedMessages.keys());
        }

        function loadMessages() {
            if (!receiverId) return;

            const messagesContainer = document.getElementById('messagesContainer');
            if (!messagesContainer) return;

            // Show spinner only on initial load
            if (!messagesLoaded) {
                messagesContainer.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-gray-400 text-2xl"></i></div>';
            }

            const formData = new FormData();
            formData.append('id', receiverId);

            fetch('get_messages.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) return;
                    const messages = data.data || [];
                    hasMoreOlderMessages = !!data.has_more;

                    if (messages.length === 0) {
                        if (renderedMessages.size > 0 || !messagesLoaded) {
                            messagesContainer.innerHTML = `
                                <div class="flex items-center justify-center h-full">
                                    <div class="text-center text-gray-500">
                                        <i class="fas fa-comments text-4xl mb-3"></i>
                                        <p>Hali xabarlar yo'q</p>
                                        <p class="text-sm">Birinchi xabarni yuboring!</p>
                                    </div>
                                </div>`;
                            renderedMessages.clear();
                        }
                        messagesLoaded = true;
                        return;
                    }

                    // Clear the spinner/empty-state placeholder before appending real messages
                    if (renderedMessages.size === 0 && !messagesContainer.querySelector('.message-group')) {
                        messagesContainer.innerHTML = '';
                    }

                    // This fetch only ever covers the most recent page. Messages older
                    // than that (loaded via scroll-up pagination) must never be touched
                    // by the removal pass below, or "load more" history would vanish
                    // again on the next poll.
                    const minIdInResponse = Math.min(...messages.map(message => Number(message.id)));

                    const seenIds = new Set();
                    const wasNearBottom = !isUserScrolling;
                    let appendedNew = false;

                    messages.forEach(message => {
                        const id = Number(message.id);
                        seenIds.add(id);
                        const signature = messageSignature(message);
                        const cached = renderedMessages.get(id);

                        if (!cached) {
                            // Brand-new message -> append without touching existing nodes
                            const wrapper = document.createElement('div');
                            wrapper.innerHTML = buildMessageHTML(message);
                            const node = wrapper.firstElementChild;
                            messagesContainer.appendChild(node);
                            renderedMessages.set(id, { data: message, signature, node });
                            appendedNew = true;
                        } else if (cached.signature !== signature) {
                            // Changed (edited text, status flipped to read, new reaction) -> patch in place
                            const previousReactions = cached.data.reactions || [];
                            patchMessageNode(cached.node, message);
                            if (userId === message.sender_id) {
                                notifyNewReactions(previousReactions, message.reactions || []);
                            }
                            cached.data = message;
                            cached.signature = signature;
                        } else {
                            cached.data = message;
                        }
                    });

                    // Remove messages that were deleted elsewhere, but only within the
                    // freshly fetched window — never touch older paginated-in history.
                    renderedMessages.forEach((cached, id) => {
                        if (id >= minIdInResponse && !seenIds.has(id)) {
                            cached.node.remove();
                            renderedMessages.delete(id);
                        }
                    });

                    // Only auto-scroll on first load or when a new message arrived while
                    // already near the bottom — never on a plain status/reaction update.
                    if (!messagesLoaded || (appendedNew && wasNearBottom)) {
                        scrollToBottom();
                    }
                    messagesLoaded = true;
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                    if (!messagesLoaded) {
                        messagesContainer.innerHTML = `
                            <div class="flex items-center justify-center h-full">
                                <div class="text-center text-red-400">
                                    <i class="fas fa-exclamation-triangle text-4xl mb-3"></i>
                                    <p>Xabarlarni yuklashda xatolik yuz berdi</p>
                                    <button onclick="loadMessages()" class="mt-3 bg-blue-500/20 text-blue-400 px-4 py-2 rounded-lg hover:bg-blue-500/30 transition-colors">
                                        <i class="fas fa-redo mr-2"></i>Qayta urinish
                                    </button>
                                </div>
                            </div>`;
                    }
                });
        }

        function loadOlderMessages() {
            if (!receiverId || isLoadingOlderMessages || !hasMoreOlderMessages) return;
            const oldestId = getOldestLoadedMessageId();
            if (oldestId === null) return;

            isLoadingOlderMessages = true;
            const messagesContainer = document.getElementById('messagesContainer');

            const loadingIndicator = document.createElement('div');
            loadingIndicator.className = 'text-center py-3';
            loadingIndicator.innerHTML = '<i class="fas fa-spinner fa-spin text-gray-400"></i>';
            messagesContainer.insertBefore(loadingIndicator, messagesContainer.firstChild);

            const formData = new FormData();
            formData.append('id', receiverId);
            formData.append('before_id', oldestId);

            fetch('get_messages.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    loadingIndicator.remove();
                    if (!data.success) return;

                    hasMoreOlderMessages = !!data.has_more;
                    const messages = data.data || [];
                    if (messages.length === 0) return;

                    const prevScrollHeight = messagesContainer.scrollHeight;
                    const prevScrollTop = messagesContainer.scrollTop;

                    const fragment = document.createDocumentFragment();
                    messages.forEach(message => {
                        const id = Number(message.id);
                        if (renderedMessages.has(id)) return;
                        const wrapper = document.createElement('div');
                        wrapper.innerHTML = buildMessageHTML(message);
                        const node = wrapper.firstElementChild;
                        fragment.appendChild(node);
                        renderedMessages.set(id, { data: message, signature: messageSignature(message), node });
                    });
                    messagesContainer.insertBefore(fragment, messagesContainer.firstChild);

                    // Keep the user's visual position stable after prepending older content
                    messagesContainer.scrollTop = messagesContainer.scrollHeight - prevScrollHeight + prevScrollTop;
                })
                .catch(error => {
                    console.error('Error loading older messages:', error);
                    loadingIndicator.remove();
                })
                .finally(() => {
                    isLoadingOlderMessages = false;
                });
        }

        function scrollToBottom() {
            const container = document.getElementById('messagesContainer');
            if (container) {
                setTimeout(() => container.scrollTop = container.scrollHeight, 100);
            }
        }

        // ============ Contacts Sidebar ============
        function contactInitials(name) {
            const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
            return ((parts[0] ? parts[0][0] : '') + (parts[1] ? parts[1][0] : '')).toUpperCase();
        }

        function renderContacts(contacts) {
            const container = document.getElementById('contactsList');
            if (!container) return;

            if (!contacts || contacts.length === 0) {
                const message = contactSearchTerm ? 'Hech narsa topilmadi' : 'Kontaktlar topilmadi';
                container.innerHTML = `<p class="text-center text-gray-500 text-sm py-6">${message}</p>`;
                return;
            }

            container.innerHTML = contacts.map(contact => {
                const initials = contactInitials(contact.name);
                const safeName = escapeHtml(contact.name);
                const isActive = (receiverId && Number(contact.id) === Number(receiverId)) ? 'active' : '';
                const unreadCount = Number(contact.unread_count) || 0;
                const badge = unreadCount > 0
                    ? `<span class="bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full">${unreadCount}</span>`
                    : '';
                return `
                <a href="?id=${contact.id}" class="user-card flex items-center gap-3 p-3 rounded-xl cursor-pointer block hover:no-underline ${isActive}">
                    <div class="relative flex-shrink-0">
                        <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-cyan-500 rounded-full flex items-center justify-center shadow-lg">
                            <span class="font-bold text-white">${initials}</span>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-400 rounded-full border-2 border-slate-800 pulse-dot"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <p class="font-semibold text-sm truncate">${safeName}</p>
                            ${badge}
                        </div>
                        <p class="text-xs text-gray-400">Onlayn</p>
                    </div>
                </a>`;
            }).join('');
        }

        function applyContactFilter() {
            const term = contactSearchTerm.trim().toLowerCase();
            const filtered = term
                ? latestContacts.filter(contact =>
                    contact.name.toLowerCase().includes(term) || (contact.email || '').toLowerCase().includes(term))
                : latestContacts;
            renderContacts(filtered);
        }

        function setupContactSearch() {
            const input = document.getElementById('contactSearchInput');
            if (!input) return;
            input.addEventListener('input', function() {
                contactSearchTerm = this.value;
                applyContactFilter();
            });
        }

        function loadContacts() {
            fetch('get_contacts.php')
                .then(response => response.json())
                .then(data => {
                    if (!data.success) return;
                    const serialized = JSON.stringify(data.data);
                    if (serialized === lastContactsData) return;
                    lastContactsData = serialized;

                    latestContacts = data.data || [];
                    contactsById.clear();
                    latestContacts.forEach(contact => contactsById.set(Number(contact.id), contact));

                    const container = document.getElementById('contactsList');
                    const scrollTop = container ? container.scrollTop : 0;
                    applyContactFilter();
                    if (container) container.scrollTop = scrollTop;
                })
                .catch(error => console.error('Error loading contacts:', error));
        }

        // ============ Message Search (filters currently loaded messages) ============
        function toggleMessageSearch() {
            const bar = document.getElementById('messageSearchBar');
            if (!bar) return;
            if (bar.classList.contains('hidden')) {
                bar.classList.remove('hidden');
                bar.classList.add('flex');
                const input = document.getElementById('messageSearchInput');
                if (input) input.focus();
            } else {
                closeMessageSearch();
            }
        }

        function closeMessageSearch() {
            const bar = document.getElementById('messageSearchBar');
            if (!bar) return;
            bar.classList.add('hidden');
            bar.classList.remove('flex');
            const input = document.getElementById('messageSearchInput');
            if (input) input.value = '';
            filterRenderedMessages('');
        }

        function filterRenderedMessages(term) {
            const normalizedTerm = term.trim().toLowerCase();
            let matches = 0;
            renderedMessages.forEach(cached => {
                const text = (cached.data.message || '').toLowerCase();
                const isMatch = !normalizedTerm || text.includes(normalizedTerm);
                cached.node.style.display = isMatch ? '' : 'none';
                if (normalizedTerm && isMatch) matches++;
            });
            const countEl = document.getElementById('messageSearchCount');
            if (countEl) countEl.textContent = normalizedTerm ? `${matches} ta natija` : '';
        }

        function setupMessageSearch() {
            const input = document.getElementById('messageSearchInput');
            if (!input) return;
            input.addEventListener('input', function() {
                filterRenderedMessages(this.value);
            });
        }

        // ============ Attachments (Images) ============
        function handleAttachmentChange() {
            const input = document.getElementById('attachmentInput');
            const file = input.files[0];
            input.value = '';
            if (!file) return;

            const MAX_IMAGE_SIZE = 5 * 1024 * 1024;
            if (!file.type.startsWith('image/')) {
                showNotification('Faqat rasm fayllarini yuborish mumkin.', 'error');
                return;
            }
            if (file.size > MAX_IMAGE_SIZE) {
                showNotification('Rasm hajmi 5 MB dan oshmasligi kerak.', 'error');
                return;
            }

            uploadAttachment(file, 'image', file.name);
        }

        async function uploadAttachment(fileOrBlob, type, filename) {
            if (!receiverId) return;

            const attachBtn = document.getElementById('attachBtn');
            const micBtn = document.getElementById('micBtn');
            if (attachBtn) attachBtn.disabled = true;
            if (micBtn) micBtn.disabled = true;

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('receiver_id', String(receiverId));
            formData.append('type', type);
            formData.append('attachment', fileOrBlob, filename);

            try {
                const response = await fetch('send_message.php', { method: 'POST', body: formData });
                const data = await response.json();
                if (data.success) {
                    loadMessages();
                } else {
                    showNotification(data.message || 'Yuborishda xatolik!', 'error');
                }
            } catch (error) {
                console.error('Attachment send error:', error);
                showNotification('Xatolik yuz berdi!', 'error');
            } finally {
                if (attachBtn) attachBtn.disabled = false;
                if (micBtn) micBtn.disabled = false;
            }
        }

        // ============ Voice Recording ============
        let mediaRecorder = null;
        let recordedChunks = [];
        let activeStream = null;
        let recordingTimerId = null;
        let recordingAutoStopId = null;
        let recordingStartedAt = 0;
        let recordingCancelled = false;

        async function toggleRecording() {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                stopRecording();
                return;
            }
            await startRecording();
        }

        async function startRecording() {
            if (!receiverId) return;

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                showNotification('Brauzeringiz ovoz yozishni qo\'llab-quvvatlamaydi.', 'error');
                return;
            }

            try {
                activeStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (error) {
                showNotification('Mikrofonga ruxsat berilmadi.', 'error');
                return;
            }

            recordedChunks = [];
            recordingCancelled = false;

            const preferredMimeType = 'audio/webm';
            const supportsPreferred = window.MediaRecorder && MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(preferredMimeType);
            mediaRecorder = supportsPreferred ? new MediaRecorder(activeStream, { mimeType: preferredMimeType }) : new MediaRecorder(activeStream);

            mediaRecorder.addEventListener('dataavailable', (event) => {
                if (event.data && event.data.size > 0) recordedChunks.push(event.data);
            });

            mediaRecorder.addEventListener('stop', () => {
                activeStream.getTracks().forEach(track => track.stop());
                clearInterval(recordingTimerId);
                clearTimeout(recordingAutoStopId);

                const recordingBar = document.getElementById('recordingBar');
                recordingBar.classList.add('hidden');
                recordingBar.classList.remove('flex');
                document.getElementById('micBtn').classList.remove('bg-red-500/20');

                if (recordingCancelled || recordedChunks.length === 0) {
                    recordedChunks = [];
                    return;
                }

                const mimeType = mediaRecorder.mimeType || 'audio/webm';
                const blob = new Blob(recordedChunks, { type: mimeType });
                recordedChunks = [];
                const extension = mimeType.includes('ogg') ? 'ogg' : 'webm';
                uploadAttachment(blob, 'audio', `voice-message.${extension}`);
            });

            mediaRecorder.start();
            recordingStartedAt = Date.now();
            document.getElementById('micBtn').classList.add('bg-red-500/20');

            const recordingBar = document.getElementById('recordingBar');
            recordingBar.classList.remove('hidden');
            recordingBar.classList.add('flex');

            updateRecordingTime();
            recordingTimerId = setInterval(updateRecordingTime, 500);
            recordingAutoStopId = setTimeout(() => stopRecording(), MAX_RECORDING_MS);
        }

        function updateRecordingTime() {
            const elapsedMs = Math.min(Date.now() - recordingStartedAt, MAX_RECORDING_MS);
            const totalSeconds = Math.floor(elapsedMs / 1000);
            const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
            const seconds = String(totalSeconds % 60).padStart(2, '0');
            const timeEl = document.getElementById('recordingTime');
            if (timeEl) timeEl.textContent = `${minutes}:${seconds}`;
        }

        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
            }
        }

        function cancelRecording() {
            recordingCancelled = true;
            stopRecording();
        }

        // ============ Emoji Picker (composing) ============
        function renderEmojiPicker() {
            const picker = document.getElementById('emojiPicker');
            if (!picker || picker.dataset.rendered) return;
            picker.innerHTML = EMOJI_PICKER_LIST.map(emoji =>
                `<button type="button" class="text-xl hover:scale-125 transition-transform" onclick="insertEmoji('${emoji}')">${emoji}</button>`
            ).join('');
            picker.dataset.rendered = '1';
        }

        function toggleEmojiPicker(event) {
            event.stopPropagation();
            renderEmojiPicker();
            document.getElementById('emojiPicker').classList.toggle('hidden');
        }

        function insertEmoji(emoji) {
            const textarea = document.getElementById('messageInput');
            if (!textarea) return;
            const start = textarea.selectionStart ?? textarea.value.length;
            const end = textarea.selectionEnd ?? textarea.value.length;
            textarea.value = textarea.value.slice(0, start) + emoji + textarea.value.slice(end);
            const cursor = start + emoji.length;
            textarea.focus();
            textarea.setSelectionRange(cursor, cursor);
            textarea.dispatchEvent(new Event('input'));
        }

        // ============ Composer Setup ============
        function setupTextarea() {
            const textarea = document.getElementById('messageInput');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                });
                textarea.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        document.getElementById('messageForm').dispatchEvent(new Event('submit'));
                    }
                });
            }
        }

        function setupMessageForm() {
            const form = document.getElementById('messageForm');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const messageInput = document.getElementById('messageInput');
                    const message = messageInput.value.trim();
                    if (!message) return;

                    const submitBtn = form.querySelector('button[type="submit"]');
                    const originalHTML = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin text-white text-xs"></i>';
                    submitBtn.disabled = true;

                    try {
                        if (editingMessageId !== null) {
                            // Telegram-style inline edit: the compose box currently holds
                            // the edited text of an existing message, not a new one.
                            const success = await updateMessage(editingMessageId, message);
                            if (success) {
                                messageInput.value = '';
                                messageInput.style.height = 'auto';
                                cancelEditMessage();
                            }
                        } else {
                            const formData = new FormData(form);
                            const response = await fetch(form.action, { method: 'POST', body: formData });
                            const data = await response.json();

                            if (data.success) {
                                messageInput.value = '';
                                messageInput.style.height = 'auto';
                                loadMessages();
                            } else {
                                showNotification(data.message || 'Xabar yuborishda xatolik!', 'error');
                            }
                        }
                    } catch (error) {
                        console.error('Send message error:', error);
                        showNotification('Xatolik yuz berdi!', 'error');
                    } finally {
                        submitBtn.innerHTML = originalHTML;
                        submitBtn.disabled = false;
                    }
                });
            }
        }

        // ============ Profile Modals ============
        function openMyProfileModal() {
            document.getElementById('myProfileName').value = <?= json_encode($_SESSION['user']['name'] ?? '') ?>;
            document.getElementById('myProfileModal').classList.remove('hidden');
        }

        function closeMyProfileModal() {
            document.getElementById('myProfileModal').classList.add('hidden');
        }

        function openReceiverProfileModal() {
            if (!receiverId) return;
            const contact = contactsById.get(Number(receiverId));
            const name = contact ? contact.name : <?= json_encode($_SESSION['receiver']['name'] ?? '') ?>;
            const email = contact ? contact.email : '';

            document.getElementById('receiverProfileAvatar').textContent = receiverInitials;
            document.getElementById('receiverProfileName').textContent = name;
            document.getElementById('receiverProfileEmail').textContent = email;
            document.getElementById('receiverProfileModal').classList.remove('hidden');
        }

        function closeReceiverProfileModal() {
            document.getElementById('receiverProfileModal').classList.add('hidden');
        }

        function setupMyProfileForms() {
            const nameForm = document.getElementById('myProfileNameForm');
            if (nameForm) {
                nameForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const name = document.getElementById('myProfileName').value.trim();
                    if (!name) return;

                    const submitBtn = nameForm.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('update_profile', '1');
                    formData.append('name', name);
                    formData.append('csrf_token', csrfToken);

                    try {
                        const response = await fetch('profile.php', { method: 'POST', body: formData });
                        const data = await response.json();
                        showNotification(data.message, data.success ? 'success' : 'error');
                    } catch (error) {
                        showNotification('Profilni yangilashda xatolik yuz berdi.', 'error');
                    } finally {
                        submitBtn.disabled = false;
                    }
                });
            }

            const passwordForm = document.getElementById('myProfilePasswordForm');
            if (passwordForm) {
                passwordForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const currentPassword = document.getElementById('currentPassword').value;
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmNewPassword = document.getElementById('confirmNewPassword').value;

                    if (newPassword !== confirmNewPassword) {
                        showNotification('Yangi parollar mos kelmadi.', 'error');
                        return;
                    }

                    const submitBtn = passwordForm.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('update_password', '1');
                    formData.append('current_password', currentPassword);
                    formData.append('new_password', newPassword);
                    formData.append('confirm_password', confirmNewPassword);
                    formData.append('csrf_token', csrfToken);

                    try {
                        const response = await fetch('profile.php', { method: 'POST', body: formData });
                        const data = await response.json();
                        showNotification(data.message, data.success ? 'success' : 'error');
                        if (data.success) passwordForm.reset();
                    } catch (error) {
                        showNotification('Parolni yangilashda xatolik yuz berdi.', 'error');
                    } finally {
                        submitBtn.disabled = false;
                    }
                });
            }
        }

        // ============ Polling (paused while the tab is hidden) ============
        function startPolling() {
            if (receiverId && !messagesPollId) {
                messagesPollId = setInterval(loadMessages, 4000);
            }
            if (!contactsPollId) {
                contactsPollId = setInterval(loadContacts, 6000);
            }
        }

        function stopPolling() {
            if (messagesPollId) { clearInterval(messagesPollId); messagesPollId = null; }
            if (contactsPollId) { clearInterval(contactsPollId); contactsPollId = null; }
        }

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopPolling();
            } else {
                if (receiverId) loadMessages();
                loadContacts();
                startPolling();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLogoutModal();
                document.querySelectorAll('.message-dropdown').forEach(dropdown => dropdown.classList.add('hidden'));
                document.querySelectorAll('.reaction-picker').forEach(picker => picker.classList.add('hidden'));
                const emojiPicker = document.getElementById('emojiPicker');
                if (emojiPicker) emojiPicker.classList.add('hidden');
            }
            if (e.ctrlKey && e.key === 'Enter') {
                const messageForm = document.getElementById('messageForm');
                if (messageForm) messageForm.dispatchEvent(new Event('submit'));
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            if (receiverId) {
                loadMessages();
            }
            loadContacts();
            startPolling();
            setupTextarea();
            setupMessageForm();
            setupContactSearch();
            setupMessageSearch();
            setupMyProfileForms();

            const attachmentInput = document.getElementById('attachmentInput');
            if (attachmentInput) attachmentInput.addEventListener('change', handleAttachmentChange);

            const confirmModalActionBtn = document.getElementById('confirmModalActionBtn');
            if (confirmModalActionBtn) {
                confirmModalActionBtn.addEventListener('click', function() {
                    const callback = confirmModalCallback;
                    closeConfirmModal();
                    if (callback) callback();
                });
            }

            document.addEventListener('mouseover', function(e) {
                const messageGroup = e.target.closest('.message-group');
                if (messageGroup) {
                    messageGroup.querySelectorAll('.message-menu-btn').forEach(btn => btn.style.opacity = '1');
                }
            });

            document.addEventListener('mouseout', function(e) {
                const messageGroup = e.target.closest('.message-group');
                if (messageGroup) {
                    const dropdown = messageGroup.querySelector('.message-dropdown');
                    const reactionPicker = messageGroup.querySelector('.reaction-picker');
                    const dropdownOpen = dropdown && !dropdown.classList.contains('hidden');
                    const pickerOpen = reactionPicker && !reactionPicker.classList.contains('hidden');
                    if (!dropdownOpen && !pickerOpen) {
                        messageGroup.querySelectorAll('.message-menu-btn').forEach(btn => btn.style.opacity = '0');
                    }
                }
            });
        });

        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                if (sidebar) sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
            }
        });
    </script>
</body>

</html>