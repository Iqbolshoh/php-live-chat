<?php
session_start();

// Redirect to login if the user is not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login/");
    exit;
}

include 'db.php';
$db = new Database();
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
            transition: opacity 0.3s ease-in-out;
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

            .sidebar-overlay {
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.6);
                backdrop-filter: blur(4px);
                z-index: 40;
                opacity: 0;
                pointer-events: none;
            }

            .sidebar-overlay.active {
                opacity: 1;
                pointer-events: auto;
            }
        }
    </style>
</head>

<body class="text-gray-200 antialiased">

    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay md:hidden" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="flex h-screen p-3 md:p-4 gap-3 md:gap-4">

        <!-- Sidebar - Users List -->
        <div class="sidebar-panel glass-panel rounded-2xl md:rounded-3xl flex flex-col flex-shrink-0 shadow-2xl" id="sidebar">
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

                <!-- User 1 -->
                <div class="user-card flex items-center gap-3 p-3 rounded-xl cursor-pointer active" onclick="openChat('Iqbolshoh Ilhomjonov', 'II', 'online')">
                    <div class="relative flex-shrink-0">
                        <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center shadow-lg">
                            <span class="font-bold text-white">II</span>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-400 rounded-full border-2 border-slate-800 pulse-dot"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <p class="font-semibold text-sm truncate">Iqbolshoh Ilhomjonov</p>
                            <span class="text-[10px] text-gray-500">10:35</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-400 truncate">Telefon daftar versiyasi...</p>
                            <span class="bg-blue-600 text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0">3</span>
                        </div>
                    </div>
                </div>

                <!-- User 2 -->
                <div class="user-card flex items-center gap-3 p-3 rounded-xl cursor-pointer" onclick="openChat('Simple User', 'SU', 'offline')">
                    <div class="relative flex-shrink-0">
                        <div class="w-12 h-12 bg-gradient-to-br from-emerald-400 to-cyan-500 rounded-full flex items-center justify-center shadow-lg">
                            <span class="font-bold text-white">SU</span>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-gray-500 rounded-full border-2 border-slate-800"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <p class="font-semibold text-sm truncate">Simple User</p>
                            <span class="text-[10px] text-gray-500">09:20</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-400 truncate">Rahmat! 😊</p>
                            <span class="bg-blue-600 text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0">2</span>
                        </div>
                    </div>
                </div>

                <!-- User 3 -->
                <div class="user-card flex items-center gap-3 p-3 rounded-xl cursor-pointer" onclick="openChat('Admin User', 'AD', 'online')">
                    <div class="relative flex-shrink-0">
                        <div class="w-12 h-12 bg-gradient-to-br from-purple-400 to-pink-500 rounded-full flex items-center justify-center shadow-lg">
                            <span class="font-bold text-white">AD</span>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-green-400 rounded-full border-2 border-slate-800 pulse-dot"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <p class="font-semibold text-sm truncate">Admin User</p>
                            <span class="text-[10px] text-gray-500">Kecha</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-400 truncate">Yordam kerakmi?</p>
                        </div>
                    </div>
                </div>

                <!-- User 4 -->
                <div class="user-card flex items-center gap-3 p-3 rounded-xl cursor-pointer" onclick="openChat('John Doe', 'JD', 'offline')">
                    <div class="relative flex-shrink-0">
                        <div class="w-12 h-12 bg-gradient-to-br from-orange-400 to-red-500 rounded-full flex items-center justify-center shadow-lg">
                            <span class="font-bold text-white">JD</span>
                        </div>
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-gray-500 rounded-full border-2 border-slate-800"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <p class="font-semibold text-sm truncate">John Doe</p>
                            <span class="text-[10px] text-gray-500">Dush</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-gray-400 truncate">Yaxshi, rahmat!</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Footer -->
            <div class="p-3 md:p-4 border-t border-gray-700/30">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-400 to-cyan-500 rounded-full flex items-center justify-center">
                        <span class="font-bold text-white text-sm">
                            <?php echo isset($_SESSION['username']) ? strtoupper(substr($_SESSION['username'], 0, 2)) : 'ME'; ?>
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate">
                            <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?>
                        </p>
                        <p class="text-xs text-gray-400">Mening profilim</p>
                    </div>
                    <button class="w-8 h-8 rounded-lg hover:bg-white/10 flex items-center justify-center transition-colors">
                        <i class="fas fa-cog text-gray-400 text-sm"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 flex flex-col">

            <!-- Empty State (when no chat is open) -->
            <div class="glass-panel rounded-2xl md:rounded-3xl flex-1 flex items-center justify-center shadow-2xl empty-state" id="emptyState">
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
                    <button class="md:hidden bg-gradient-to-r from-blue-600 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition-all transform hover:scale-105" onclick="toggleSidebar()">
                        <i class="fas fa-users mr-2"></i> Kontaktlar
                    </button>
                </div>
            </div>

            <!-- Chat Window (hidden by default) -->
            <div class="glass-panel rounded-2xl md:rounded-3xl flex-col h-full shadow-2xl chat-window hidden" id="chatWindow">

                <!-- Chat Header -->
                <div class="p-3 md:p-4 border-b border-gray-700/30 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button class="md:hidden w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center flex-shrink-0 transition-colors" onclick="toggleSidebar()">
                            <i class="fas fa-arrow-left text-gray-400"></i>
                        </button>
                        <button class="hidden md:flex w-10 h-10 rounded-xl hover:bg-white/10 items-center justify-center flex-shrink-0 transition-colors" onclick="closeChat()">
                            <i class="fas fa-arrow-left text-gray-400"></i>
                        </button>

                        <div class="relative flex-shrink-0">
                            <div class="w-10 h-10 md:w-11 md:h-11 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center shadow-lg" id="chatAvatar">
                                <span class="font-bold text-white text-sm">II</span>
                            </div>
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-800 pulse-dot" id="chatStatus"></span>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm md:text-base" id="chatName">Iqbolshoh Ilhomjonov</h3>
                            <p class="text-xs" id="chatOnlineStatus">
                                <span class="text-green-400">● Onlayn</span>
                            </p>
                        </div>
                    </div>
                    <div class="flex gap-1 md:gap-2">
                        <button class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors">
                            <i class="fas fa-phone text-gray-400"></i>
                        </button>
                        <button class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors hidden sm:flex">
                            <i class="fas fa-video text-gray-400"></i>
                        </button>
                        <button class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors">
                            <i class="fas fa-ellipsis-v text-gray-400"></i>
                        </button>
                    </div>
                </div>

                <!-- Messages Container -->
                <div class="flex-1 overflow-y-auto custom-scrollbar p-4 md:p-6 space-y-4" id="messagesContainer">
                    <!-- Received Message -->
                    <div class="flex items-start gap-2 md:gap-3">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-white">II</span>
                        </div>
                        <div class="max-w-[75%] md:max-w-md">
                            <div class="message-received rounded-2xl rounded-tl-none px-3 py-2 md:px-4 md:py-3">
                                <p class="text-sm">Assalomu alaykum! Mahsulotingiz haqida ma'lumot olmoqchi edim.</p>
                            </div>
                            <p class="text-[10px] text-gray-500 mt-1 ml-1">10:30</p>
                        </div>
                    </div>

                    <!-- Sent Message -->
                    <div class="flex items-start gap-2 md:gap-3 justify-end">
                        <div class="max-w-[75%] md:max-w-md">
                            <div class="message-sent text-white rounded-2xl rounded-tr-none px-3 py-2 md:px-4 md:py-3">
                                <p class="text-sm">Va alaykum assalom! Albatta, qaysi mahsulot haqida qiziqyapsiz?</p>
                            </div>
                            <p class="text-[10px] text-gray-500 mt-1 mr-1 text-right flex items-center justify-end gap-1">
                                10:32 <i class="fas fa-check-double text-blue-400 text-[10px]"></i>
                            </p>
                        </div>
                    </div>

                    <!-- Date Separator -->
                    <div class="flex items-center gap-3 my-4">
                        <div class="flex-1 h-px bg-gray-700/30"></div>
                        <span class="text-[10px] text-gray-500 bg-slate-800/50 px-3 py-1 rounded-full">Bugun</span>
                        <div class="flex-1 h-px bg-gray-700/30"></div>
                    </div>

                    <!-- Received Message -->
                    <div class="flex items-start gap-2 md:gap-3">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-400 to-purple-500 rounded-full flex items-center justify-center flex-shrink-0">
                            <span class="text-xs font-bold text-white">II</span>
                        </div>
                        <div class="max-w-[75%] md:max-w-md">
                            <div class="message-received rounded-2xl rounded-tl-none px-3 py-2 md:px-4 md:py-3">
                                <p class="text-sm">Telefon daftar versiyasi borligini eshitdim. Narxi va xususiyatlari qanday?</p>
                            </div>
                            <p class="text-[10px] text-gray-500 mt-1 ml-1">10:33</p>
                        </div>
                    </div>
                </div>

                <!-- Message Input -->
                <div class="p-3 md:p-4 border-t border-gray-700/30">
                    <div class="flex items-end gap-2 md:gap-3">
                        <button class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors flex-shrink-0">
                            <i class="fas fa-paperclip text-gray-400"></i>
                        </button>
                        <div class="flex-1 relative">
                            <textarea
                                rows="1"
                                placeholder="Xabar yozing..."
                                class="w-full bg-slate-800/50 rounded-2xl px-4 py-3 pr-12 focus:outline-none focus:ring-2 focus:ring-blue-500/50 resize-none text-sm transition-all"
                                style="min-height: 46px; max-height: 120px;"
                                id="messageInput"></textarea>
                            <button class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 flex items-center justify-center transition-all shadow-lg hover:shadow-blue-500/25">
                                <i class="fas fa-paper-plane text-white text-xs"></i>
                            </button>
                        </div>
                        <button class="w-10 h-10 rounded-xl hover:bg-white/10 flex items-center justify-center transition-colors flex-shrink-0 hidden sm:flex">
                            <i class="fas fa-smile text-gray-400"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentChat = null;

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');

            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');

            if (sidebar.classList.contains('active')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }

        function openChat(name, initials, status) {
            currentChat = {
                name,
                initials,
                status
            };

            // Update chat header
            document.getElementById('chatName').textContent = name;
            document.getElementById('chatAvatar').querySelector('span').textContent = initials;

            // Update status
            const statusElement = document.getElementById('chatOnlineStatus');
            const statusDot = document.getElementById('chatStatus');
            if (status === 'online') {
                statusElement.innerHTML = '<span class="text-green-400">● Onlayn</span>';
                statusDot.className = 'absolute bottom-0 right-0 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-800 pulse-dot';
            } else {
                statusElement.innerHTML = '<span class="text-gray-400">● Offline</span>';
                statusDot.className = 'absolute bottom-0 right-0 w-3 h-3 bg-gray-500 rounded-full border-2 border-slate-800';
            }

            // Show chat window, hide empty state
            document.getElementById('emptyState').classList.add('hidden');
            document.getElementById('chatWindow').classList.remove('hidden');
            document.getElementById('chatWindow').style.display = 'flex';

            // Add active class to selected user
            document.querySelectorAll('.user-card').forEach(card => {
                card.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            // Close sidebar on mobile
            if (window.innerWidth < 768) {
                toggleSidebar();
            }

            // Focus on message input
            setTimeout(() => {
                document.getElementById('messageInput').focus();
            }, 300);
        }

        function closeChat() {
            currentChat = null;

            // Hide chat window, show empty state
            document.getElementById('chatWindow').classList.add('hidden');
            document.getElementById('chatWindow').style.display = 'none';
            document.getElementById('emptyState').classList.remove('hidden');

            // Remove active class from all users
            document.querySelectorAll('.user-card').forEach(card => {
                card.classList.remove('active');
            });
        }

        // Auto-resize textarea
        const messageInput = document.getElementById('messageInput');
        if (messageInput) {
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });

            // Send message on Enter
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (this.value.trim()) {
                        sendMessage(this.value);
                        this.value = '';
                        this.style.height = 'auto';
                    }
                }
            });
        }

        function sendMessage(text) {
            const container = document.getElementById('messagesContainer');
            const time = new Date().toLocaleTimeString('uz-UZ', {
                hour: '2-digit',
                minute: '2-digit'
            });

            const messageHTML = `
                <div class="flex items-start gap-2 md:gap-3 justify-end">
                    <div class="max-w-[75%] md:max-w-md">
                        <div class="message-sent text-white rounded-2xl rounded-tr-none px-3 py-2 md:px-4 md:py-3">
                            <p class="text-sm">${text}</p>
                        </div>
                        <p class="text-[10px] text-gray-500 mt-1 mr-1 text-right flex items-center justify-end gap-1">
                            ${time} <i class="fas fa-check text-blue-400 text-[10px]"></i>
                        </p>
                    </div>
                </div>
            `;

            container.insertAdjacentHTML('beforeend', messageHTML);
            container.scrollTop = container.scrollHeight;
        }

        // Close sidebar on window resize (desktop)
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });

        // Initialize - show empty state
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('emptyState').classList.remove('hidden');
            document.getElementById('chatWindow').classList.add('hidden');
            document.getElementById('chatWindow').style.display = 'none';
        });
    </script>

</body>

</html>