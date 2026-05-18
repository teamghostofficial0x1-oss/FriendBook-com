<?php
require_once 'src/api/endpoint/db/db_config.php';

// ১. কারেন্ট ইউজারের লাস্ট সিন আপডেট করার লজিক (AJAX Heartbeat)
if (isset($_GET['action']) && $_GET['action'] === 'heartbeat' && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    echo json_encode(["status" => "alive"]);
    exit;
}

// ২. কোনো ইউজার অনলাইন নাকি অফলাইন তা চেক করার হেল্পার ফাংশন
// শেষ ১ মিনিটের (৬০ সেকেন্ড) মধ্যে অ্যাক্টিভ থাকলে অনলাইন ধরবে
function getUserStatus($last_seen_time) {
    if (!$last_seen_time) return 'offline';
    $last_seen = strtotime($last_seen_time);
    $current_time = time();
    
    if (($current_time - $last_seen) <= 60) {
        return 'online';
    }
    return 'offline';
}
?>
