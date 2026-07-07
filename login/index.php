<?php
session_start();

include '../db.php';
$db = new Database();

// Redirect to dashboard if already logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header("Location: ../");
    exit;
}

// Handle the login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);
        exit;
    }

    // Validate input fields
    if (empty($_POST['email'])) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }

    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    if (empty($_POST['password'])) {
        echo json_encode(['success' => false, 'message' => 'Password is required']);
        exit;
    }

    if (strlen($_POST['password']) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        exit;
    }

    $email = $_POST['email'];
    $password = $_POST['password'];

    // Retrieve user from the database
    $users = $db->select('users', '*', 'email = ?', [$email]);
    $user = $users[0] ?? null;

    // Verify password and set session variables
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['loggedin'] = true;
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ];

        echo json_encode(['success' => true, 'message' => 'Login successful']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        exit;
    }
}

// Ensure a CSRF token exists
$db->generate_csrf_token();
?>

<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
        }
    </script>
    <style>
        body {
            background-color: #0f172a;
            background-image: radial-gradient(circle at 50% -20%, #3b82f6 0%, #0f172a 50%);
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center text-gray-200">

    <div class="glass-panel w-full max-w-md p-8 rounded-2xl shadow-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Welcome Back</h1>
            <p class="text-gray-400 text-sm">Please sign in to your account</p>
        </div>

        <form id="loginForm" method="post" class="space-y-6">
            <input type="hidden" name="csrf_token" id="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2" for="email">Email Address</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all placeholder-gray-500 text-white"
                    placeholder="name@example.com"
                    required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2" for="password">Password</label>
                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all placeholder-gray-500 text-white pr-12"
                        placeholder="••••••••"
                        required
                        minlength="8">

                    <!-- Password Visibility Toggle Button -->
                    <button type="button" id="togglePassword" class="absolute inset-y-0 right-0 pr-4 flex items-center text-gray-400 hover:text-white transition-colors">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg id="eyeSlashIcon" class="hidden w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>

            <button
                type="submit"
                class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors duration-200 shadow-lg shadow-blue-500/30">
                Sign In
            </button>

            <p class="text-center text-gray-400 text-sm mt-4">
                Don't have an account? <a href="../register/" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">Register</a>
            </p>
        </form>

        <!-- Corrected ID here: removed the space -->
        <div id="alertMessage" class="hidden mt-6 p-4 rounded-xl text-sm text-center"></div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const alertMessage = document.getElementById('alertMessage');
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');
        const eyeSlashIcon = document.getElementById('eyeSlashIcon');

        // Toggle Password Visibility
        togglePassword.addEventListener('click', () => {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);

            eyeIcon.classList.toggle('hidden');
            eyeSlashIcon.classList.toggle('hidden');
        });

        // Form Submission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            alertMessage.classList.add('hidden');

            const formData = new FormData(form);

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alertMessage.textContent = result.message;
                    alertMessage.className = 'mt-6 p-4 rounded-xl text-sm text-center bg-green-500/20 text-green-400 border border-green-500/20';
                    alertMessage.classList.remove('hidden');

                    setTimeout(() => {
                        window.location.href = '../';
                    }, 500);
                } else {
                    alertMessage.textContent = result.message;
                    alertMessage.className = 'mt-6 p-4 rounded-xl text-sm text-center bg-red-500/20 text-red-400 border border-red-500/20';
                    alertMessage.classList.remove('hidden');
                }
            } catch (error) {
                alertMessage.textContent = 'An error occurred. Please try again.';
                alertMessage.className = 'mt-6 p-4 rounded-xl text-sm text-center bg-red-500/20 text-red-400 border border-red-500/20';
                alertMessage.classList.remove('hidden');
            }
        });
    </script>
</body>

</html>