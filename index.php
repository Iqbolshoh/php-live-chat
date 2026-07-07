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

$contacts = $db->select('users', '*', 'id != ?', [$_SESSION['user']['id']]);

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $messages = $db->select('messages', '*', '(sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)', [$_SESSION['user']['id'], $_GET['id'], $_GET['id'], $_SESSION['user']['id']]);
    $_SESSION['receiver']['id'] = $_GET['id'];
    $_SESSION['receiver']['name'] = $db->select('users', 'name', 'id = ?', [$_GET['id']])[0]['name'];
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

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

        .message-sent {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        .message-received {
            background: rgba(51, 65, 85, 0.8);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .user-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .user-card:hover {
            transform: translateX(4px);
            background: rgba(59, 130, 246, 0.1);
        }

        .user-card.active {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.3);
            transform: translateX(4px);
        }

        .chat-window {
            animation: slideIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 40;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease-in-out;
        }

        .sidebar-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .sidebar-panel {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.3);
            border-radius: 10px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(59, 130, 246, 0.5);
        }

        .pulse-dot {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .empty-state {
            animation: fadeIn 0.5s ease-out;
        }

        .logout-modal {
            animation: fadeIn 0.2s ease-out;
        }

        @media (max-width: 768px) {
            .sidebar-panel {
                position: fixed;
                left: 0;
                top: 0;
                bottom: 0;
                width: 85%;
                max-width: 320px;
                z-index: 50;
                transform: translateX(-100%);
            }

            .sidebar-panel.active {
                transform: translateX(0);
            }
        }
    </style>
</head>

<body class="text-gray-200 antialiased">

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay md:hidden" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Logout Confirmation Modal -->
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

    <div class="flex h-screen p-3 md:p-4 gap-3 md:gap-4">

        <!-- Sidebar - Users List -->
        <div class="sidebar-panel glass-panel rounded-2xl md:rounded-3xl flex flex-col flex-shrink-0 shadow-2xl md:relative md:transform-none" id="sidebar">
            <!-- Sidebar Header -->
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

                <!-- Search Bar -->
                <div class="relative">
                    <input type="text" placeholder="Qidirish..." class="w-full bg-slate-800/50 rounded-xl px-4 py-2.5 pl-10 focus:outline-none focus:ring-2 focus:ring-blue-500/50 text-sm transition-all">
                    <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-sm"></i>
                </div>
            </div>

            <!-- Users List -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-2 md:p-3 space-y-1">
                <!-- Contacts -->
                <?php foreach ($contacts as $contact) : ?>
                    <?php
                    $nameParts = explode(' ', trim($contact['name']));
                    $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                    $safeName = htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8');
                    $userId = $contact['id'];
                    $isActive = (isset($_GET['id']) && $_GET['id'] == $userId) ? 'active' : '';
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
                            </div>
                            <p class="text-xs text-gray-400">Onlayn</p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Sidebar Footer -->
            <div class="p-3 md:p-4 border-t border-gray-700/30">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-400 to-cyan-500 rounded-full flex items-center justify-center">
                        <span class="font-bold text-white text-sm">
                            <?php echo isset($_SESSION['user']['email']) ? strtoupper(substr($_SESSION['user']['email'], 0, 2)) : 'ME'; ?>
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate">
                            <?php echo isset($_SESSION['user']['email']) ? htmlspecialchars($_SESSION['user']['email']) : 'User'; ?>
                        </p>
                        <p class="text-xs text-gray-400">Mening profilim</p>
                    </div>
                    <button onclick="openLogoutModal()" class="w-8 h-8 rounded-lg hover:bg-red-500/20 flex items-center justify-center transition-colors group" title="Chiqish">
                        <i class="fas fa-sign-out-alt text-gray-400 group-hover:text-red-400 text-sm transition-colors"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 flex flex-col">
            <?php if (isset($_GET['id']) && !empty($_GET['id'])): ?>
                <!-- Chat Window -->
                <div class="glass-panel rounded-2xl md:rounded-3xl flex flex-col h-full shadow-2xl chat-window">
                    <!-- Chat Header -->
                    <div class="p-3 md:p-4 border-b border-gray-700/30 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button class="md:hidden w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center flex-shrink-0 transition-colors" onclick="toggleSidebar()">
                                <i class="fas fa-bars text-gray-400"></i>
                            </button>
                            <button class="hidden md:flex w-10 h-10 rounded-xl hover:bg-white/10 items-center justify-center flex-shrink-0 transition-colors" onclick="closeChat()">
                                <i class="fas fa-arrow-left text-gray-400"></i>
                            </button>
                            <div class="relative flex-shrink-0">
                                <div class="w-10 h-10 md:w-11 md:h-11 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center shadow-lg">
                                    <?php
                                    $receiverNameParts = explode(' ', trim($_SESSION['receiver']['name']));
                                    $receiverInitials = strtoupper(substr($receiverNameParts[0], 0, 1) . (isset($receiverNameParts[1]) ? substr($receiverNameParts[1], 0, 1) : ''));
                                    ?>
                                    <span class="font-bold text-white text-sm"><?= $receiverInitials ?></span>
                                </div>
                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-800 pulse-dot"></span>
                            </div>
                            <div>
                                <h3 class="font-bold text-sm md:text-base"><?= htmlspecialchars($_SESSION['receiver']['name']) ?></h3>
                                <p class="text-xs text-green-400">● Onlayn</p>
                            </div>
                        </div>
                        <div class="flex gap-1 md:gap-2">
                            <button class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors">
                                <i class="fas fa-phone text-gray-400"></i>
                            </button>
                            <button class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors hidden sm:flex">
                                <i class="fas fa-video text-gray-400"></i>
                            </button>
                            <button onclick="openLogoutModal()" class="w-10 h-10 rounded-xl hover:bg-red-500/20 flex items-center justify-center transition-colors group">
                                <i class="fas fa-sign-out-alt text-gray-400 group-hover:text-red-400 text-sm transition-colors"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Messages Container -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar p-4 md:p-6 space-y-4" id="messagesContainer">
                        <?php foreach ($messages as $m) : ?>
                            <?php if ($m['sender_id'] == $_SESSION['user']['id']) : ?>
                                <!-- Sent Message -->
                                <div class="flex items-start gap-2 md:gap-3 justify-end">
                                    <div class="max-w-[75%] md:max-w-md">
                                        <div class="message-sent text-white rounded-2xl rounded-tr-none px-3 py-2 md:px-4 md:py-3">
                                            <p class="text-sm"><?= htmlspecialchars($m['message']) ?></p>
                                        </div>
                                        <p class="text-[10px] text-gray-500 mt-1 mr-1 text-right flex items-center justify-end gap-1">
                                            <?= date('H:i', strtotime($m['created_at'] ?? 'now')) ?>
                                            <i class="fas fa-check-double text-blue-400 text-[10px]"></i>
                                        </p>
                                    </div>
                                </div>
                            <?php else : ?>
                                <!-- Received Message -->
                                <div class="flex items-start gap-2 md:gap-3">
                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-xs font-bold text-white"><?= $receiverInitials ?></span>
                                    </div>
                                    <div class="max-w-[75%] md:max-w-md">
                                        <div class="message-received rounded-2xl rounded-tl-none px-3 py-2 md:px-4 md:py-3">
                                            <p class="text-sm"><?= htmlspecialchars($m['message']) ?></p>
                                        </div>
                                        <p class="text-[10px] text-gray-500 mt-1 ml-1">
                                            <?= date('H:i', strtotime($m['created_at'] ?? 'now')) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Message Input -->
                    <div class="p-3 md:p-4 border-t border-gray-700/30">
                        <form id="messageForm" class="flex items-end gap-2 md:gap-3">
                            <button type="button" class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors flex-shrink-0">
                                <i class="fas fa-paperclip text-gray-400"></i>
                            </button>
                            <div class="flex-1 relative">
                                <textarea
                                    rows="1"
                                    placeholder="Xabar yozing..."
                                    class="w-full bg-slate-800/50 rounded-2xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-500/50 resize-none text-sm transition-all"
                                    style="min-height: 46px; max-height: 120px;"
                                    id="messageInput"></textarea>
                                <button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 flex items-center justify-center transition-all shadow-lg hover:shadow-blue-500/25">
                                    <i class="fas fa-paper-plane text-white text-xs"></i>
                                </button>
                            </div>
                            <button type="button" class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors flex-shrink-0 hidden sm:flex">
                                <i class="fas fa-smile text-gray-400"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Empty State -->
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

    <!-- Hidden form for CSRF token -->
    <form id="logoutForm" action="logout" method="POST" class="hidden">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    </form>

    <script>
        // Toggle sidebar for mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        // Close chat and return to empty state
        function closeChat() {
            window.location.href = window.location.pathname;
        }

        // Open logout modal
        function openLogoutModal() {
            const modal = document.getElementById('logoutModal');
            modal.classList.remove('hidden');
        }

        // Close logout modal
        function closeLogoutModal() {
            const modal = document.getElementById('logoutModal');
            modal.classList.add('hidden');
        }

        // Auto-resize textarea
        const textarea = document.getElementById('messageInput');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }

        // Handle message form submission
        const messageForm = document.getElementById('messageForm');
        if (messageForm) {
            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const messageInput = document.getElementById('messageInput');
                const message = messageInput.value.trim();

                if (message) {
                    // Add your message sending logic here
                    console.log('Sending message:', message);
                    messageInput.value = '';
                    messageInput.style.height = '46px';
                }
            });
        }

        // Scroll messages to bottom
        function scrollToBottom() {
            const container = document.getElementById('messagesContainer');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }

        // Initial scroll to bottom
        scrollToBottom();

        async function performLogout() {
            const logoutBtn = document.getElementById('logoutBtn');
            const originalHTML = logoutBtn.innerHTML;

            // Show loading state
            logoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Chiqilmoqda...</span>';
            logoutBtn.disabled = true;

            try {
                const csrfToken = '<?php echo $_SESSION["csrf_token"]; ?>';
                const formData = new FormData();
                formData.append('csrf_token', csrfToken);

                const response = await fetch('logout/index.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const data = await response.json();
                    if (data.success) {
                        window.location.href = 'login/';
                    } else {
                        alert(data.message || 'Xatolik yuz berdi.');
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

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLogoutModal();
            }
        });
    </script>
</body>

</html>