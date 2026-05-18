<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

// 🚪 ১. লগআউট (Logout) হ্যান্ডলার - সেশন পুরোপুরি ধ্বংস করা
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array(); // সব সেশন ভেরিয়েবল খালি করা
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        ); // সেশন কুকি ডিলিট করা
    }
    
    session_destroy(); // সেশন ধ্বংস করা
    header("Location: index.php"); // ফ্রেশভাবে ইনডেক্স পেজে পাঠানো
    exit;
}

// 🔒 ২. অলরেডি লগইন থাকলে তাকে ফিড পেজে পাঠিয়ে দাও
if (isset($_SESSION['username'])) {
    header("Location: feed.php");
    exit;
}

$error = '';

// 🔑 ৩. লগইন ফর্ম প্রসেসিং লজিক
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // সেশন সেট করা
            $_SESSION['username'] = $user['username'];
            
            header("Location: feed.php");
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    } else {
        $error = "Please fill in all fields!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - FriendBook</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-[400px] bg-[#242526] rounded-2xl border border-[#2f3031] p-6 shadow-2xl space-y-6">
        
        <div class="text-center space-y-2">
            <h1 class="text-[#1877f2] text-4xl font-black tracking-tighter">FriendBook</h1>
            <p class="text-xs text-gray-400">Connect with your network securely.</p>
        </div>

        <?php if(!empty($error)): ?>
            <div class="p-3 bg-red-500/10 border border-red-500/20 text-red-400 text-xs font-bold rounded-xl text-center">
                <i class="fas fa-exclamation-circle mr-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST" class="space-y-4">
            <div class="space-y-1">
                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Username</label>
                <div class="relative">
                    <input type="text" name="username" required placeholder="e.g. adminRubel" class="w-full bg-[#3a3b3c] border border-transparent focus:border-[#1877f2] outline-none text-xs text-white pl-9 pr-4 py-2.5 rounded-xl transition-all">
                    <i class="fas fa-user absolute left-3 top-3 text-gray-500 text-xs"></i>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">Password</label>
                <div class="relative">
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-[#3a3b3c] border border-transparent focus:border-[#1877f2] outline-none text-xs text-white pl-9 pr-4 py-2.5 rounded-xl transition-all">
                    <i class="fas fa-lock absolute left-3 top-3 text-gray-500 text-xs"></i>
                </div>
            </div>

            <button type="submit" name="login" class="w-full bg-[#1877f2] hover:bg-blue-600 text-white text-xs font-black py-2.5 rounded-xl uppercase tracking-wider transition-all shadow-md">
                Log In System
            </button>
        </form>

        <div class="text-center pt-2 border-t border-[#393a3b]">
            <p class="text-[11px] text-gray-500">System Infrastructure v3.0 // Secured Connection</p>
        </div>

    </div>

</body>
</html>
