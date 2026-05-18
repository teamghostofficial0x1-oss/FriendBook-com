<?php
// ১. সেন্ট্রাল ডাটাবেজ কানেকশন লোড করা (আপনার db.php ফাইলটি ইনক্লুড করবে)
require_once __DIR__ . '/db.php';

try {
    // ২. সম্পূর্ণ ইউজার টেবিল (Security, Remember Me, Reset Token এবং Online Status সহ)
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
        last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- 🟢 অনলাইন/অফলাইন ট্র্যাক করার জন্য
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // ৩. মেইন পোস্ট/নিউজফিড টেবিল
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ৪. চ্যাট মেসেঞ্জার ও WebRTC ভিডিও কল সিগন্যালিং টেবিল
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id SERIAL PRIMARY KEY, 
        sender VARCHAR(50) NOT NULL, 
        message TEXT NOT NULL, 
        file_path VARCHAR(255) DEFAULT NULL,
        file_type VARCHAR(50) DEFAULT NULL,
        call_signal TEXT DEFAULT NULL, -- WebRTC অডিও-ভিডিও কলের লাইভ সিগন্যাল ডাটা রাখার জন্য
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ৫. ফ্রেন্ডশিপ ও ফ্রেন্ড রিকোয়েস্ট ট্র্যাকিং টেবিল (নতুন যুক্ত করা হয়েছে)
    $pdo->exec("CREATE TABLE IF NOT EXISTS friends (
        id SERIAL PRIMARY KEY,
        sender VARCHAR(50) NOT NULL,
        receiver VARCHAR(50) NOT NULL,
        status VARCHAR(20) DEFAULT 'pending', -- pending = রিকোয়েস্ট পাঠানো হয়েছে, accepted = ফ্রেন্ড হয়ে গেছে
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(sender, receiver) -- যেন একই ইউজার বারবার ডুপ্লিকেট রিকোয়েস্ট পাঠাতে না পারে
    )");

} catch (PDOException $e) {
    // ব্যাকগ্রাউন্ডে কোনো এরর হলে তা স্ক্রিনে বড় করে না দেখিয়ে লগ বা ইগনোর করবে যেন অ্যাপ ক্র্যাশ না করে
    error_log("Database Schema Error: " . $e->getMessage());
}
