<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

// 🍪 অটো-লগইন মেকানিজম: যদি সেশন না থাকে কিন্তু 'remember_me' কুকি থাকে
if (!isset($_SESSION['username']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    
    // ডাটাবেজে চেক করা এই টোকেনটি ভ্যালিড এবং মেয়াদ (৭ দিন) আছে কিনা
    $stmt = $pdo->prepare("SELECT username FROM users WHERE remember_token = ? AND remember_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['username'] = $user['username']; // অটো লগইন সফল!
        header("Location: index.php");
        exit;
    }
}

// লগআউট প্রসেস (যদি কেউ ম্যানুয়ালি লগআউট করতে চায়, তবে কুকিও ডিলিট হবে)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['username'])) {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
    }
    session_destroy();
    setcookie('remember_me', '', time() - 3600, "/"); // কুকি রিমুভ
    header("Location: auth.php");
    exit;
}

$error = ''; $success = '';
$view = $_GET['view'] ?? 'login';

// --- রেজিস্ট্রেশন প্রসেস ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['username']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    if ($username && $email && $_POST['password']) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $password]);
            $success = "Registration successful! Please login.";
            $view = 'login';
        } catch (PDOException $e) { $error = "Username or Email already exists!"; }
    } else { $error = "Invalid inputs!"; }
}

// --- লগইন প্রসেস (With 7 Days Remember Me) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember_me']); // চেকবক্স টিক দেওয়া আছে কিনা

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['username'] = $user['username'];

        // যদি ইউজার ৭ দিন লগইন রাখতে চায়
        if ($remember) {
            $cookie_token = bin2hex(random_bytes(32)); // সিকিউর টোকেন
            $days = 7;
            $expiry_time = date('Y-m-d H:i:s', strtotime("+$days days"));
            
            // ডাটাবেজে টোকেন এবং এক্সপায়ারি টাইম সেভ করা
            $update_stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_expires = ? WHERE username = ?");
            $update_stmt->execute([$cookie_token, $expiry_time, $user['username']]);
            
            // ব্রাউজারে ৭ দিনের জন্য কুকি সেট করা
            setcookie('remember_me', $cookie_token, time() + ($days * 24 * 60 * 60), "/", "", false, true); // HttpOnly true (XSS প্রটেকশন)
        }

        header("Location: index.php");
        exit;
    } else { $error = "Wrong username or password!"; }
}

// --- ফরগেট পাসওয়ার্ড ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forget'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if ($email) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, token_expires = ? WHERE email = ?");
        $stmt->execute([$token, $expires, $email]);
        
        if ($stmt->rowCount() > 0) {
            $success = "Reset link generated! click here: <a href='auth.php?view=reset&token=$token' class='text-blue-400 underline'>Reset Password</a>";
        } else { $error = "Email not found!"; }
    } else { $error = "Invalid email!"; }
}

// --- পাসওয়ার্ড রিসেট সাবমিট ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $new_pass = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expires > NOW()");
    $stmt->execute([$token]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expires = NULL WHERE reset_token = ?");
        $stmt->execute([$new_pass, $token]);
        $success = "Password changed successfully! Login now.";
        $view = 'login';
    } else { $error = "Invalid or expired token!"; }
}
?>
<!DOCTYPE html>
<html lang="en"><head>
    <meta charset="UTF-8"><title>Auth - FriendBook</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex items-center justify-center p-4">
    <div class="bg-[#242526] p-6 rounded-xl border border-[#393a3b] w-full max-w-md shadow-lg">
        <h2 class="text-3xl font-black text-[#1877f2] text-center mb-6">FriendBook</h2>
        
        <?php if($error): ?><div class="bg-red-500/10 text-red-400 p-3 rounded mb-4 text-sm border border-red-500/20"><?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div class="bg-green-500/10 text-green-400 p-3 rounded mb-4 text-sm border border-green-500/20"><?php echo $success; ?></div><?php endif; ?>

        <?php if($view === 'login'): ?>
            <form action="auth.php?view=login" method="POST" class="space-y-4">
                <input type="text" name="username" placeholder="Username" required class="w-full bg-[#3a3b3c] p-2.5 rounded outline-none border border-[#393a3b] focus:border-[#1877f2]">
                <input type="password" name="password" placeholder="Password" required class="w-full bg-[#3a3b3c] p-2.5 rounded outline-none border border-[#393a3b] focus:border-[#1877f2]">
                
                <div class="flex items-center justify-between px-1 text-sm text-gray-400">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="remember_me" checked class="w-4 h-4 accent-[#1877f2] bg-[#3a3b3c] rounded border-gray-600">
                        <span>Remember me for 7 days</span>
                    </label>
                </div>

                <button type="submit" name="login" class="w-full bg-[#1877f2] py-2 rounded font-bold mt-2 hover:bg-[#1565c0] transition-colors">Log In</button>
                <div class="text-center text-sm pt-2">
                    <a href="auth.php?view=forget" class="text-[#1877f2] hover:underline">Forgotten password?</a>
                    <hr class="border-[#393a3b] my-4">
                    <a href="auth.php?view=register" class="bg-[#42b72a] px-4 py-2 rounded font-bold text-white inline-block hover:bg-[#369622] transition-colors">Create new account</a>
                </div>
            </form>

        <?php elseif($view === 'register'): ?>
            <form action="auth.php?view=register" method="POST" class="space-y-4">
                <input type="text" name="username" placeholder="Username (e.g. CyberNinja)" required class="w-full bg-[#3a3b3c] p-2.5 rounded outline-none">
                <input type="email" name="email" placeholder="Email Address" required class="w-full bg-[#3a3b3c] p-2.5 rounded outline-none">
                <input type="password" name="password" placeholder="New Password" required class="w-full bg-[#3a3b3c] p-2.5 rounded outline-none">
                <button type="submit" name="register" class="w-full bg-[#42b72a] py-2 rounded font-bold">Sign Up</button>
                <p class="text-center text-sm"><a href="auth.php?view=login" class="text-[#1877f2] hover:underline">Already have an account?</a></p>
            </form>

        <?php elseif($view === 'forget'): ?>
            <form action="auth.php?view=forget" method="POST" class="space-y-4">
                <p class="text-sm text-gray-400">Enter your email to search for your account.</p>
                <input type="email" name="email" placeholder="Email Address" required class="w-full bg-[#3a3b3c] p-2.5 rounded outline-none">
                <button type="submit" name="forget" class="w-full bg-[#1877f2] py-2 rounded font-bold">Search / Generate Token</button>
                <p class="text-center text-sm"><a href="auth.php?view=login" class="text-[#1877f2] hover:underline">Cancel</a></p>
            </form>

        <?php elseif($view === 'reset'): ?>
            <form action="auth.php?view=reset" method="POST" class="space-y-4">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                <input type="password" name="password" placeholder="Enter New Password" required class="w-full bg-[#3a3b3c] p-2.5 rounded outline-none">
                <button type="submit" name="reset_password" class="w-full bg-[#1877f2] py-2 rounded font-bold">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
