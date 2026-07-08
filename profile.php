<?php
session_start();

// Redirect to login if the user is not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login/");
    exit;
}

include 'db.php';
$db = new Database();

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $db->generate_csrf_token();
}

$currentUser = $_SESSION['user'];

// Handle profile or password update form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // --- HANDLE PROFILE NAME UPDATE ---
    if (isset($_POST['update_profile'])) {
        $newName = trim($_POST['name'] ?? '');

        if (empty($newName)) {
            echo json_encode(['success' => false, 'message' => 'Name cannot be empty']);
            exit;
        }

        try {
            $db->update('users', ['name' => $newName], 'id = ?', [$currentUser['id']]);
            $_SESSION['user']['name'] = $newName;

            echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
            exit;
        }
    }

    // --- HANDLE PASSWORD UPDATE ---
    if (isset($_POST['update_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            echo json_encode(['success' => false, 'message' => 'All password fields are required']);
            exit;
        }

        if (strlen($newPassword) < 8) {
            echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters long']);
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }

        // Fetch user to verify current password
        $userRecord = $db->select('users', 'password', 'id = ?', [$currentUser['id']]);

        if (empty($userRecord) || !password_verify($currentPassword, $userRecord[0]['password'])) {
            echo json_encode(['success' => false, 'message' => 'Incorrect current password']);
            exit;
        }

        // Hash the new password and save it
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            $db->update('users', ['password' => $hashedPassword], 'id = ?', [$currentUser['id']]);
            echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Failed to update password']);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        body {
            background-color: #0f172a;
            background-image: radial-gradient(circle at 50% 0%, #3b82f6 0%, #0f172a 60%);
            background-attachment: fixed;
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
    </style>
</head>

<body class="min-h-screen text-gray-200 p-6 flex flex-col items-center justify-center">

    <div class="w-full max-w-2xl">
        <!-- Back Button -->
        <a href="./" class="inline-flex items-center text-gray-400 hover:text-white mb-6 transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Back to Dashboard
        </a>

        <!-- Profile Card -->
        <div class="glass-panel rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <!-- Background Glow -->
            <div class="absolute top-0 right-0 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl"></div>

            <div class="flex items-center space-x-6 mb-8 relative z-10 border-b border-gray-700/50 pb-8">
                <!-- Avatar Placeholder -->
                <div class="w-24 h-24 rounded-full bg-gradient-to-tr from-blue-600 to-blue-400 flex items-center justify-center text-3xl font-bold text-white shadow-lg shadow-blue-500/30">
                    <?= strtoupper(substr($currentUser['name'], 0, 1)) ?>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-white mb-1" id="displayNameHeader"><?= htmlspecialchars($currentUser['name']) ?></h1>
                    <p class="text-gray-400"><?= htmlspecialchars($currentUser['email']) ?></p>
                    <span class="inline-block mt-2 px-3 py-1 bg-blue-500/10 text-blue-400 border border-blue-500/20 rounded-lg text-xs font-semibold uppercase tracking-wider">
                        <?= htmlspecialchars($currentUser['role']) ?>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-10 relative z-10">

                <!-- General Info Section -->
                <div>
                    <h2 class="text-xl font-semibold text-white mb-4">General Details</h2>
                    <form id="profileForm" class="space-y-5">
                        <input type="hidden" name="update_profile" value="1">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2" for="name">Display Name</label>
                            <input type="text" name="name" id="name" value="<?= htmlspecialchars($currentUser['name']) ?>" class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-white" required>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500 mb-2" for="email">Email Address (Read-only)</label>
                            <input type="email" value="<?= htmlspecialchars($currentUser['email']) ?>" class="w-full px-4 py-3 bg-gray-900/50 border border-gray-700 rounded-xl text-gray-500 cursor-not-allowed" disabled>
                        </div>

                        <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-xl transition-colors duration-200 shadow-lg shadow-blue-500/30">
                            Update Profile
                        </button>
                    </form>
                    <div id="profileAlert" class="hidden mt-4 p-4 rounded-xl text-sm"></div>
                </div>

                <!-- Security / Password Section -->
                <div>
                    <h2 class="text-xl font-semibold text-white mb-4">Security</h2>
                    <form id="passwordForm" class="space-y-5">
                        <input type="hidden" name="update_password" value="1">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2" for="current_password">Current Password</label>
                            <div class="relative">
                                <input type="password" name="current_password" id="current_password" class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-white pr-12" required>
                                <button type="button" class="toggle-password absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-white" data-target="current_password">
                                    <svg class="eye-icon w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg class="eye-slash-icon hidden w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2" for="new_password">New Password</label>
                            <div class="relative">
                                <input type="password" name="new_password" id="new_password" class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-white pr-12" required minlength="8">
                                <button type="button" class="toggle-password absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-white" data-target="new_password">
                                    <svg class="eye-icon w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg class="eye-slash-icon hidden w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2" for="confirm_password">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" name="confirm_password" id="confirm_password" class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-white pr-12" required minlength="8">
                                <button type="button" class="toggle-password absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-white" data-target="confirm_password">
                                    <svg class="eye-icon w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg class="eye-slash-icon hidden w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="w-full py-3 bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white border border-red-500/20 font-semibold rounded-xl transition-all duration-200">
                            Update Password
                        </button>
                    </form>
                    <div id="passwordAlert" class="hidden mt-4 p-4 rounded-xl text-sm"></div>
                </div>

            </div>
        </div>
    </div>

    <script>
        const displayAlert = (element, result) => {
            element.textContent = result.message;
            element.className = `mt-4 p-4 rounded-xl text-sm ${result.success ? 'bg-green-500/20 text-green-400 border border-green-500/20' : 'bg-red-500/20 text-red-400 border border-red-500/20'}`;
            element.classList.remove('hidden');
        };

        // Profile Form Handler
        const profileForm = document.getElementById('profileForm');
        const profileAlert = document.getElementById('profileAlert');

        profileForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            profileAlert.classList.add('hidden');

            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    body: new FormData(profileForm)
                });
                const result = await response.json();
                displayAlert(profileAlert, result);

                if (result.success) {
                    document.getElementById('displayNameHeader').textContent = document.getElementById('name').value;
                }
            } catch (error) {
                displayAlert(profileAlert, {
                    success: false,
                    message: 'An error occurred while updating the profile.'
                });
            }
        });

        // Password Form Handler
        const passwordForm = document.getElementById('passwordForm');
        const passwordAlert = document.getElementById('passwordAlert');

        passwordForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            passwordAlert.classList.add('hidden');

            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;

            if (newPass !== confirmPass) {
                displayAlert(passwordAlert, {
                    success: false,
                    message: 'New passwords do not match'
                });
                return;
            }

            try {
                const response = await fetch('profile.php', {
                    method: 'POST',
                    body: new FormData(passwordForm)
                });
                const result = await response.json();
                displayAlert(passwordAlert, result);

                if (result.success) {
                    passwordForm.reset();
                }
            } catch (error) {
                displayAlert(passwordAlert, {
                    success: false,
                    message: 'An error occurred while updating the password.'
                });
            }
        });

        // Password Visibility Toggle
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const inputField = document.getElementById(targetId);
                const eyeIcon = this.querySelector('.eye-icon');
                const eyeSlashIcon = this.querySelector('.eye-slash-icon');

                if (inputField.type === 'password') {
                    inputField.type = 'text';
                    eyeIcon.classList.add('hidden');
                    eyeSlashIcon.classList.remove('hidden');
                } else {
                    inputField.type = 'password';
                    eyeIcon.classList.remove('hidden');
                    eyeSlashIcon.classList.add('hidden');
                }
            });
        });
    </script>
</body>

</html>