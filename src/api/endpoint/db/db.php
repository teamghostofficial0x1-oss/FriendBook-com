<?php
// Render বা এনভায়রনমেন্ট ভেরিয়েবল থেকে DATABASE_URL রিড করা
$db_url = getenv('DATABASE_URL') ?: "postgresql://adminrubel:26lwjTb0WlduAKMhLE6hcGRNy9YVvWPT@dpg-d85dkq6gvqtc73bohi5g-a/fndbook";

$db_info = parse_url($db_url);

$host = $db_info['host'];
$port = $db_info['port'] ?? 5432;
$user = $db_info['user'];
$pass = $db_info['pass'];
$dbname = ltrim($db_info['path'], '/');

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    die(json_encode(["status" => "error", "message" => "Database Connection failed: " . $e->getMessage()]));
}
?>
