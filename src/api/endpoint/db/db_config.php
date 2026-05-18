<?php
// সেন্ট্রাল ডাটাবেজ কানেকশন লোড করা
require_once __DIR__ . '/db.php';

try {
    // ১. সম্পূর্ণ ইউজার টেবিল (Remember Me ও Reset Token সহ)
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // ২. মেইন পোস্ট টেবিল
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) DEFAULT 'CyberNinja',
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ৩. চ্যাট ও কল সিগন্যালিং টেবিল
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id SERIAL PRIMARY KEY, 
        sender VARCHAR(50) NOT NULL, 
        message TEXT NOT NULL, 
        file_path VARCHAR(255) DEFAULT NULL,
        file_type VARCHAR(50) DEFAULT NULL,
        call_signal TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // ব্যাকগ্রাউন্ডে এরর ইগনোর করবে যেন অ্যাপ ক্র্যাশ না করে
}<?php
// সেন্ট্রাল ডাটাবেজ কানেকশন লোড করা
require_once __DIR__ . '/db.php';

try {
    // ১. সম্পূর্ণ ইউজার টেবিল (Remember Me ও Reset Token সহ)
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
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // ২. মেইন পোস্ট টেবিল
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) DEFAULT 'CyberNinja',
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ৩. চ্যাট ও কল সিগন্যালিং টেবিল
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id SERIAL PRIMARY KEY, 
        sender VARCHAR(50) NOT NULL, 
        message TEXT NOT NULL, 
        file_path VARCHAR(255) DEFAULT NULL,
        file_type VARCHAR(50) DEFAULT NULL,
        call_signal TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // ব্যাকগ্রাউন্ডে এরর ইগনোর করবে যেন অ্যাপ ক্র্যাশ না করে
}
