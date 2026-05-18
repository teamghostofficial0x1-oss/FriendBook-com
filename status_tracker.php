<?php
require_once 'src/api/endpoint/db/db_config.php';

if (isset($_GET['action']) && $_GET['action'] === 'heartbeat' && isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE username = ?");
    $stmt->execute([$_SESSION['username']]);
    echo json_encode(["status" => "alive"]);
    exit;
}

function getUserStatus($last_seen_time) {
    if (!$last_seen_time) return 'offline';
    $last_seen = strtotime($last_seen_time);
    $current_time = time();
    return (($current_time - $last_seen) <= 60) ? 'online' : 'offline';
}
?>
