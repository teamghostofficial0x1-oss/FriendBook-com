<?php
require_once __DIR__ . '/db.php';

try {
    // ১. ইউজার টেবিল (Security, Remember Me, Reset Token এবং Online Status সহ)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        bio TEXT DEFAULT 'Coding is life. Secure everything!',
        profile_pic VARCHAR(255) DEFAULT 'default.png',
        reset_token VARCHAR(64) DEFAULT NULL,
        token_expires TIMESTAMP DEFAULT NULL,
        remember_token VARCHAR(64) DEFAULT NULL,
        remember_expires TIMESTAMP DEFAULT NULL,
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 👮 👑 ৬. অটোমেটিক সুপার অ্যাডমিন (`adminRubel`) ইনজেকশন লজিক
    $admin_username = 'adminRubel';
    $admin_email = 'admin@friendbook.com';
    $admin_password_plain = 'Rubel@@@@';

    // চেক করা হচ্ছে অ্যাডমিন আগে থেকেই ডাটাবেজে আছে কিনা
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check_stmt->execute([$admin_username]);
    $admin_exists = $check_stmt->fetchColumn();

    if (!$admin_exists) {
        // পাসওয়ার্ডকে বি ক্রিপ্ট এনক্রিপশন করা হচ্ছে
        $admin_password_hash = password_hash($admin_password_plain, PASSWORD_BCRYPT);
        
        $insert_admin = $pdo->prepare("INSERT INTO users (username, email, password, bio) VALUES (?, ?, ?, ?)");
        $insert_admin->execute([
            $admin_username, 
            $admin_email, 
            $admin_password_hash, 
            'Root System Administrator. Complete DBMS Control.'
        ]);
    }
    
    // ২. নিউজফিড পোস্ট টেবিল
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ৩. চ্যাট ও WebRTC সিগন্যালিং টেবিল
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id SERIAL PRIMARY KEY, 
        sender VARCHAR(50) NOT NULL, 
        message TEXT NOT NULL, 
        file_path VARCHAR(255) DEFAULT NULL,
        file_type VARCHAR(50) DEFAULT NULL,
        call_signal TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ৪. ফ্রেন্ডশিপ ও রিকোয়েস্ট টেবিল
    $pdo->exec("CREATE TABLE IF NOT EXISTS friends (
        id SERIAL PRIMARY KEY,
        sender VARCHAR(50) NOT NULL,
        receiver VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(sender, receiver)
    )");

} catch (PDOException $e) {
    error_log("Database Schema Error: " . $e->getMessage());
}
