<?php
// ১. সেন্ট্রাল ডাটাবেজ কনফিগারেশন ইনক্লুড করা
require_once 'src/api/endpoint/db/db_config.php';

// 🔴 অটোমেটিক ইউজার টেবিল তৈরি এবং ডেমো ডাটা ইনসার্ট মেকানিজম
try {
    // টেবিল না থাকলে তৈরি করবে
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        bio TEXT,
        profile_pic VARCHAR(255) DEFAULT 'default.png',
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // ডেমো ইউজার 'CyberNinja' অলরেডি আছে কিনা চেক করে ইনসার্ট করা
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'CyberNinja'");
    $check_stmt->execute();
    
    if ($check_stmt->fetchColumn() == 0) {
        $insert_demo = $pdo->prepare("INSERT INTO users (username, bio, profile_pic) VALUES (?, ?, ?)");
        $insert_demo->execute(['CyberNinja', 'Coding is life. Secure everything!', 'default.png']);
    }
} catch (PDOException $e) {
    // কোনো এরর হলে সেটা ব্যাকগ্রাউন্ডে ইগনোর করবে যেন পেজ ক্র্যাশ না করে
}

// আপাতত ডেমো ইউজার হিসেবে কাজ করছি (ভবিষ্যতে লগইন সিস্টেমের সাথে সিঙ্ক হবে)
$current_user = 'CyberNinja'; 
$message = '';
$status = '';

// ডাটাবেজ থেকে ইউজারের বর্তমান তথ্য তুলে আনা
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$current_user]);
$user_info = $stmt->fetch();

// যদি কোনো কারণে ইউজার তথ্য না পাওয়া যায় (সেফটি চেক)
if (!$user_info) {
    $user_info = ['username' => $current_user, 'bio' => 'Coding is life. Secure everything!', 'profile_pic' => 'default.png'];
}

// ফরম সাবমিট এবং ফাইল আপলোড হ্যান্ডলিং (Sanitized & Secured)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = htmlspecialchars(trim($_POST['bio'] ?? ''));
    $uploaded_file = $_FILES['profile_pic'] ?? null;
    $profile_pic_name = $user_info['profile_pic']; // ডিফল্ট বা আগের ছবি রেখে দেওয়া

    // ১. ফাইল আপলোড এবং স্যানিটাইজেশন মেকানিজম
    if ($uploaded_file && $uploaded_file['error'] === UPLOAD_ERR_OK) {
        
        // ডাইনামিক পাথ তৈরি: src/api/endpoint/uploads/{username}/
        $target_dir = "src/api/endpoint/uploads/" . preg_replace('/[^a-zA-Z0-9_]/', '', $current_user) . "/";
        
        // ফোল্ডার না থাকলে পারমিশনসহ তৈরি করা
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0755, true);
        }

        $file_name = basename($uploaded_file['name']);
        $file_size = $uploaded_file['size'];
        $file_tmp  = $uploaded_file['tmp_name'];
        
        // ফাইলের এক্সটেনশন চেক
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

        // সিকিউরিটি চেক: MIME-Type ভ্যালিডেশন
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];

        if (!in_array($file_ext, $allowed_exts) || !in_array($mime_type, $allowed_mimes)) {
            $message = "Invalid file type! Only JPG, PNG, and GIF are allowed.";
            $status = "error";
        } elseif ($file_size > 2 * 1024 * 1024) { // সাইজ লিমিট: ২ মেগাবাইট
            $message = "File is too large! Maximum size limit is 2MB.";
            $status = "error";
        } else {
            // ফাইল নাম স্যানিটাইজেশন
            $new_file_name = "profile_" . uniqid() . "." . $file_ext;
            $target_file = $target_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $target_file)) {
                // পুরানো ছবি ডিলিট করা (ডিফল্ট ছবি না হলে)
                if ($user_info['profile_pic'] !== 'default.png' && file_exists($user_info['profile_pic'])) {
                    @unlink($user_info['profile_pic']);
                }
                $profile_pic_name = $target_file; // নতুন পাথ সেভ হবে
            } else {
                $message = "Failed to upload image due to server permission.";
                $status = "error";
            }
        }
    }

    // ২. ডাটাবেজ আপডেট (যদি কোনো সিকিউরিটি এরর না থাকে)
    if ($status !== 'error') {
        try {
            $update_stmt = $pdo->prepare("UPDATE users SET bio = ?, profile_pic = ?, updated_at = CURRENT_TIMESTAMP WHERE username = ?");
            $update_stmt->execute([$bio, $profile_pic_name, $current_user]);
            
            $message = "Profile updated successfully!";
            $status = "success";
            
            // লোকাল ভেরিয়েবল রিফ্রেশ করা
            $user_info['bio'] = $bio;
            $user_info['profile_pic'] = $profile_pic_name;
        } catch (PDOException $e) {
            $message = "Database update failed: " . $e->getMessage();
            $status = "error";
        }
    }
}

// ডিফল্ট ছবির ক্ষেত্রে প্লেসহোল্ডার অ্যাভাটার ব্যবহার
$display_pic = (file_exists($user_info['profile_pic'])) ? $user_info['profile_pic'] : 'https://i.imgur.com/8Km9tLL.png';
?>
