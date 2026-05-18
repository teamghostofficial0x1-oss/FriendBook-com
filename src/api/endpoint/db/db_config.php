<?php
// একই ফোল্ডারে থাকা db.php কে ইনক্লুড করা
require_once __DIR__ . '/db.php';

// অটো-টেবিল ক্রিয়েশন মেকানিজম
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id SERIAL PRIMARY KEY, 
        username VARCHAR(50) DEFAULT 'CyberNinja', 
        content TEXT NOT NULL, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id SERIAL PRIMARY KEY, 
        sender VARCHAR(50) NOT NULL, 
        message TEXT NOT NULL, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // টেবিল অলরেডি থাকলে এরর ইগনোর করবে
}

// সেন্ট্রালাইজড ডেটা সিঙ্ক API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['sync'])) {
    header('Content-Type: application/json');
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['target_table']) || !isset($data['row_data'])) {
        echo json_encode(["status" => "error", "message" => "Invalid Payload Format"]);
        exit;
    }

    $table = $data['target_table'];
    $rowData = $data['row_data'];

    try {
        if ($table === 'posts' && isset($rowData['content'])) {
            $stmt = $pdo->prepare("INSERT INTO posts (content) VALUES (?)");
            $stmt->execute([htmlspecialchars($rowData['content'])]);
            echo json_encode(["status" => "success", "message" => "Post synced successfully"]);
            exit;
        } 
        
        if ($table === 'messages' && isset($rowData['sender']) && isset($rowData['message'])) {
            $stmt = $pdo->prepare("INSERT INTO messages (sender, message) VALUES (?, ?)");
            $stmt->execute([htmlspecialchars($rowData['sender']), htmlspecialchars($rowData['message'])]);
            echo json_encode(["status" => "success", "message" => "Message synced successfully"]);
            exit;
        }

        echo json_encode(["status" => "error", "message" => "Target table or columns mismatch"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Sync failed: " . $e->getMessage()]);
    }
    exit;
}
?>
