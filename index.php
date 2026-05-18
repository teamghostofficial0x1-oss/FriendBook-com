<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    header("Location: index.php");
    exit;
}

if (isset($_SESSION['username'])) {
    header("Location: feed.php");
    exit;
}

$error = '';
$success = '';

// 🔑 লগইন প্রসেস
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            header("Location: feed.php");
            exit;
        } else {
            $error = "ভুল ইউজারনেম অথবা পাসওয়ার্ড দিয়েছেন!";
        }
    } else {
        $error = "সবগুলো ঘর পূরণ করুন!";
    }
}

// 📝 রেজিস্ট্রেশন প্রসেস
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['reg_username']);
    $email = trim($_POST['reg_email']);
    $password = trim($_POST['reg_password']);

    if (!empty($username) && !empty($email) && !empty($password)) {
        // ইউজারনেম বা ইমেইল অলরেডি আছে কিনা চেক
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $chk->execute([$username, $email]);
        
        if ($chk->fetchColumn() > 0) {
            $error = "ইউজারনেম অথবা ইমেইলটি ইতিমধ্যে ব্যবহার করা হয়েছে!";
        } else {
            $hashed_pass = password_hash($password, PASSWORD_BCRYPT);
            $ins = $pdo->prepare("INSERT INTO users (username, email, password, profile_pic) VALUES (?, ?, ?, 'default.png')");
            if ($ins->execute([$username, $email, $hashed_pass])) {
                $success = "অ্যাকাউন্ট তৈরি সফল হয়েছে! এখন লগইন করুন।";
            } else {
                $error = "অ্যাকাউন্ট তৈরিতে কোনো সমস্যা হয়েছে!";
            }
        }
    } else {
        $error = "সবগুলো ঘর পূরণ করুন!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FriendBook - Log In or Sign Up</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#f0f2f5] text-[#1c1e21] min-h-screen flex items-center justify-center font-sans p-4">

    <div class="w-full max-w-[1000px] grid md:grid-cols-2 gap-12 items-center">
        <div class="text-center md:text-left space-y-4">
            <h1 class="text-[#1877f2] text-6xl font-black tracking-tight">FriendBook</h1>
            <p class="text-xl md:text-2xl font-medium text-[#1c1e21] leading-tight">FriendBook আপনাকে আপনার জীবনের মানুষদের সাথে সংযুক্ত হতে এবং শেয়ার করতে সাহায্য করে।</p>
        </div>

        <div class="bg-white p-5 rounded-xl shadow-xl space-y-4 border border-gray-200">
            <?php if($error): ?>
                <div class="p-3 bg-red-100 text-red-700 text-xs font-bold rounded-lg text-center"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="p-3 bg-green-100 text-green-700 text-xs font-bold rounded-lg text-center"><?php echo $success; ?></div>
            <?php endif; ?>

            <form action="index.php" method="POST" class="space-y-4">
                <input type="text" name="username" required placeholder="Email address or username" class="w-full border border-gray-300 focus:border-[#1877f2] outline-none text-base px-4 py-3 rounded-lg transition-all">
                <input type="password" name="password" required placeholder="Password" class="w-full border border-gray-300 focus:border-[#1877f2] outline-none text-base px-4 py-3 rounded-lg transition-all">
                <button type="submit" name="login" class="w-full bg-[#1877f2] text-white text-xl font-bold py-3 rounded-lg transition-all hover:bg-blue-600">Log In</button>
            </form>

            <div class="text-center border-t border-gray-300 pt-4">
                <button onclick="toggleRegisterModal(true)" class="bg-[#42b72a] hover:bg-[#36a420] text-white font-bold text-md px-5 py-3 rounded-lg transition-all">Create new account</button>
            </div>
        </div>
    </div>

    <div id="regModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-white w-full max-w-[430px] rounded-xl shadow-2xl p-4 relative border border-gray-200">
            <button onclick="toggleRegisterModal(false)" class="absolute right-4 top-4 text-gray-400 hover:text-black text-xl"><i class="fas fa-times"></i></button>
            <div class="mb-4 border-b pb-3">
                <h2 class="text-3xl font-extrabold">Sign Up</h2>
                <p class="text-sm text-gray-500">It's quick and easy.</p>
            </div>
            <form action="index.php" method="POST" class="space-y-4">
                <input type="text" name="reg_username" required placeholder="Choose a username" class="w-full border border-gray-300 px-3 py-2 text-sm bg-gray-50 rounded-md outline-none focus:border-[#1877f2]">
                <input type="email" name="reg_email" required placeholder="Email address" class="w-full border border-gray-300 px-3 py-2 text-sm bg-gray-50 rounded-md outline-none focus:border-[#1877f2]">
                <input type="password" name="reg_password" required placeholder="New password" class="w-full border border-gray-300 px-3 py-2 text-sm bg-gray-50 rounded-md outline-none focus:border-[#1877f2]">
                <p class="text-[11px] text-gray-500">By clicking Sign Up, you agree to our Terms, Data Policy and Cookies Policy.</p>
                <button type="submit" name="register" class="w-full bg-[#00a400] hover:bg-green-700 text-white font-bold text-lg py-2 rounded-lg">Sign Up</button>
            </form>
        </div>
    </div>

    <script>
        function toggleRegisterModal(show) {
            document.getElementById('regModal').classList.toggle('hidden', !show);
        }
    </script>
</body>
</html>
