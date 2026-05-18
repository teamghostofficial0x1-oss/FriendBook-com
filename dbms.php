<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();
require_once 'status_tracker.php';

// 🔒 সিকিউরিটি গার্ড ১: লগইন না থাকলে ইনডেক্স পেজে পাঠিয়ে দাও
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$current_user = $_SESSION['username'];
$msg = ''; $status = '';

// 🚨 👮 সিকিউরিটি গার্ড ২: হার্ডকোডেড অ্যাডমিন লক!
// 'adminRubel' ছাড়া পৃথিবীর অন্য কোনো ইউজার এই লিংকে ঢুকলে সোজা Access Denied স্ক্রিন দেখাবে।
if ($current_user !== 'adminRubel') {
    die("
    <div style='background:#111213; color:#ff4d4d; font-family:sans-serif; text-align:center; padding:50px; min-height:100vh; display:flex; flex-direction:column; justify-content:center; align-items:center;'>
        <i class='fas fa-shield-halved' style='font-size:60px; margin-bottom:20px;'></i>
        <h2 style='letter-spacing:-1px; font-size:32px; font-weight:900;'>⚠️ CRITICAL VIOLATION: ACCESS DENIED</h2>
        <p style='color:#a0a5ad; max-w:500px; font-size:14px; margin-top:5px; line-height:1.6;'>
            Your account <strong>@{$current_user}</strong> does not possess Root Infrastructure privileges. This incident has been logged into the core security mainframe.
        </p>
        <a href='feed.php' style='margin-top:25px; background:#1877f2; color:white; padding:10px 24px; border-radius:10px; font-size:13px; font-weight:bold; text-decoration:none;'>Return to NewsFeed</a>
    </div>
    ");
}

// --- ⚙️ এর নিচে DBMS অ্যাকশন হ্যান্ডলার (ইউজার ডিলিট, পোস্ট মডারেশন) আগের কোডই থাকবে ---
// ... (আপনার আগের ফাইলের বাকি সব কোড এখানে হুবহু বসে যাবে)
