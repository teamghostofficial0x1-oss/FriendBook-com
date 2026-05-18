<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

// 🍪 অটো-লগইন চেক (৭ দিনের Remember Me)
if (!isset($_SESSION['username']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmt = $pdo->prepare("SELECT username FROM users WHERE remember_token = ? AND remember_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['username'] = $user['username'];
        header("Location: index.php");
        exit;
    }
}

// 🚪 লগআউট প্রসেস
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (isset($_SESSION['username'])) {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_expires = NULL WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
    }
    session_destroy();
    setcookie('remember_me', '', time() - 3600, "/");
    header("Location: auth.php");
    exit;
}

$error = ''; $success = '';
$view = $_GET['view'] ?? 'login';

// সাইনআপ প্রসেস
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

// লগইন প্রসেস
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
        header("Location: index.php");
        exit;
    } else { $error = "Wrong username or password!"; }
}

// ফরগেট পাসওয়ার্ড
if ($_SERVER
