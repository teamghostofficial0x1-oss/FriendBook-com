<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

if (!isset($_SESSION['username']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmt = $pdo->prepare("SELECT username FROM users WHERE remember_token = ? AND remember_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) { $_SESSION['username'] = $user['username']; }
}

if (isset($_SESSION['username'])) {
    header("Location: feed.php");
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['username'])) {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
    }
    session_destroy();
    setcookie('remember_me', '', time() - 3600, "/");
    header("Location: index.php");
    exit;
}

$error = ''; $success = '';
$view = $_GET['view'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $username = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['username']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    if ($username && $email && $_POST['password']) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $password]);
            $success = "Registration successful! Please login below.";
            $view = 'login';
        } catch (PDOException $e) { $error = "Username or Email already exists!"; }
    } else { $error = "Invalid inputs!"; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember_me']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['username'] = $user['username'];

        if ($remember) {
            $cookie_token = bin2hex(random_bytes(32));
            $expiry_time = date('Y-m-d H:i:s', strtotime("+7 days"));
            $update_stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_expires = ? WHERE username = ?");
            $update_stmt->execute([$cookie_token, $expiry_time, $user['username']]);
            setcookie('remember_me', $cookie_token, time() + (7 * 24 * 60 * 60), "/", "", false, true);
        }
        header("Location: feed.php");
        exit;
    } else { $error = "Wrong username or password!"; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forget'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if ($email) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, token_expires = ? WHERE email = ?");
        $stmt->execute([$token, $expires, $email]);
        if ($stmt->rowCount() > 0) {
            $success = "Reset link generated! <a href='index.php?view=reset&token=$token' class='text-blue-400 font-bold underline ml-1'>Click here to Reset</a>";
        } else { $error = "Email address not found!"; }
    } else { $error = "Invalid email formatting!"; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $new_pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE reset_token = ? AND token_expires > NOW()");
    $stmt->execute([$token]);
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expires = NULL WHERE reset_token = ?");
        $stmt->execute([$new_pass, $token]);
        $success = "Password reset success! Log in now.";
        $view = 'login';
    } else { $error = "Token is invalid or has expired!"; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FriendBook - Login or Sign Up</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex items-center justify-center p-4">
    <div class="bg-[#242526] p-6 rounded-xl border border-[#393a3b] w-full max-w-md shadow-2xl">
        <h2 class="text-4xl font-black text-[#1877f2] text-center mb-2 tracking-tighter">FriendBook</h2>
        <p class="text-center text-xs text-gray-400 mb-6 font-semibold uppercase tracking-wider">Secure Social Engine</p>
        
        <?php if($error): ?><div class="bg-red-500/10 text-red-400 p-3 rounded mb-4 text-xs border border-red-500/20 font-medium"><?php echo $error; ?></div><?php endif; ?>
        <?php if($success): ?><div class="bg-green-500/10 text-green-400 p-3 rounded mb-4 text-xs border border-green-500/20 font-medium"><?php echo $success; ?></div><?php endif; ?>

        <?php if($view === 'login'): ?>
            <form action="index.php?view=login" method="POST" class="space-y-4">
                <input type="text" name="username" placeholder="Username" required class="w-full bg-[#3a3b3c] p-3 rounded outline-none border border-[#393a3b] text-sm focus:border-[#1877f2]">
                <input type="password" name="password" placeholder="Password" required class="w-full bg-[#3a3b3c] p-3 rounded outline-none border border-[#393a3b] text-sm focus:border-[#1877f2]">
                <div class="flex items-center px-1 text-sm text-gray-400">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="remember_me" checked class="w-4 h-4 accent-[#1877f2] bg-[#3a3b3c] rounded border-gray-600">
                        <span>Remember me for 7 days</span>
                    </label>
                </div>
                <button type="submit" name="login" class="w-full bg-[#1877f2] py-2.5 rounded-lg font-bold text-sm hover:bg-[#1565c0]">Log In</button>
                <div class="text-center text-sm pt-2">
                    <a href="index.php?view=forget" class="text-[#1877f2] text-xs hover:underline">Forgotten password?</a>
                    <hr class="border-[#393a3b] my-4">
                    <a href="index.php?view=register" class="bg-[#42b72a] px-5 py-2.5 rounded-lg text-sm font-bold text-white inline-block hover:bg-[#369622]">Create new account</a>
                </div>
            </form>
        <?php elseif($view === 'register'): ?>
            <form action="index.php?view=register" method="POST" class="space-y-4">
                <input type="text" name="username" placeholder="Choose Username" required class="w-full bg-[#3a3b3c] p-3 rounded outline-none text-sm">
                <input type="email" name="email" placeholder="Email Address" required class="w-full bg-[#3a3b3c] p-3 rounded outline-none text-sm">
                <input type="password" name="password" placeholder="Create Strong Password" required class="w-full bg-[#3a3b3c] p-3 rounded outline-none text-sm">
                <button type="submit" name="register" class="w-full bg-[#42b72a] py-2.5 rounded-lg font-bold text-sm hover:bg-[#369622]">Sign Up</button>
                <p class="text-center text-xs mt-2"><a href="index.php?view=login" class="text-[#1877f2] font-semibold hover:underline">Already have an account? Log In</a></p>
            </form>
        <?php elseif($view === 'forget'): ?>
            <form action="index.php?view=forget" method="POST" class="space-y-4">
                <p class="text-xs text-gray-400 leading-relaxed">Enter your registered email address below to simulate a secure reset link instantly.</p>
                <input type="email" name="email" placeholder="Enter Account Email" required class="w-full bg-[#3a3b3c] p-3 rounded outline-none text-sm">
                <button type="submit" name="forget" class="w-full bg-[#1877f2] py-2.5 rounded-lg font-bold text-sm">Find Account / Token</button>
                <p class="text-center text-xs"><a href="index.php?view=login" class="text-[#1877f2] font-semibold hover:underline">Back to Login</a></p>
            </form>
        <?php elseif($view === 'reset'): ?>
            <form action="index.php?view=reset" method="POST" class="space-y-4">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                <input type="password" name="password" placeholder="Type New Strong Password" required class="w-full bg-[#3a3b3c] p-3 rounded outline-none text-sm">
                <button type="submit" name="reset_password" class="w-full bg-[#1877f2] py-2.5 rounded-lg font-bold text-sm">Update Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
