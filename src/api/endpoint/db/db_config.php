<?php
require_once __DIR__ . '/db.php';

try {
    // আপডেটেড ইউজার টেবিল (Remember Me টোকেন কলামসহ)
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        bio TEXT DEFAULT 'Hello World!',
        profile_pic VARCHAR(255) DEFAULT 'default.png',
        reset_token VARCHAR(64) DEFAULT NULL,
        token_expires TIMESTAMP DEFAULT NULL,
        remember_token VARCHAR(64) DEFAULT NULL,       -- নতুন কলাম
        remember_expires TIMESTAMP DEFAULT NULL,     -- নতুন কলাম
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
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
    // Error handling
}
