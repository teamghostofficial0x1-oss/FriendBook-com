<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'src/api/endpoint/db/db_config.php';
session_start();

if (!isset($_SESSION['username']) || $_SESSION['username'] !== 'adminRubel') {
    die("ACCESS DENIED: Root Infra Privileges Required.");
}

$msg = '';

// 🎯 ভেরিফিকেশন স্টেট চেঞ্জার অ্যাকশন টগল
if (isset($_GET['action']) && $_GET['action'] === 'toggle_verify' && isset($_GET['username'])) {
    $target_user = trim($_GET['username']);
    $status_stmt = $pdo->prepare("SELECT is_verified FROM users WHERE username = ?");
    $status_stmt->execute([$target_user]);
    $current_status = $status_stmt->fetchColumn();

    // ০ থাকলে ১ করবে, ১ থাকলে ০ করবে (টগল লজিক)
    $new_status = ($current_status == 1) ? 0 : 1;
    
    $update_stmt = $pdo->prepare("UPDATE users SET is_verified = ? WHERE username = ?");
    $update_stmt->execute([$new_status, $target_user]);
    
    $msg = "User @{$target_user} Meta-Verification status updated successfully!";
}

// ইউজার ডিলিট লজিক
if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['username'])) {
    $target_del = trim($_GET['username']);
    if ($target_del !== 'adminRubel') {
        $pdo->prepare("DELETE FROM users WHERE username = ?")->execute([$target_del]);
        $pdo->prepare("DELETE FROM posts WHERE username = ?")->execute([$target_del]);
        $msg = "User @{$target_del} purged successfully!";
    }
}

$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$users_list = $pdo->query("SELECT id, username, email, is_verified FROM users ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DBMS Control Center - Verification Framework</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#0f1011] text-[#e4e6eb] min-h-screen p-6">

    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex justify-between items-center bg-[#18191a] p-4 rounded-xl border border-[#2f3031]">
            <h1 class="font-black text-sm uppercase tracking-wider text-white"><i class="fas fa-database text-blue-500 mr-2"></i>Core DBMS Coreframe</h1>
            <a href="feed.php" class="text-xs bg-[#242526] hover:bg-[#3a3b3c] px-3 py-1.5 rounded-lg font-bold">Back to Feed</a>
        </div>

        <?php if($msg): ?>
            <div class="p-3 text-xs font-mono rounded-lg bg-green-500/10 text-green-400 border border-green-500/20">[SYS LOG]: <?php echo $msg; ?></div>
        <?php endif; ?>

        <div class="bg-[#18191a] rounded-xl border border-[#2f3031] overflow-hidden">
            <div class="p-4 bg-[#242526] border-b border-[#2f3031] font-bold text-xs uppercase text-gray-300">User Infrastructure Registry</div>
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="bg-[#1c1d1e] text-gray-400 border-b border-[#2f3031]">
                        <th class="p-3">Username</th>
                        <th class="p-3">Email</th>
                        <th class="p-3">Status Badge</th>
                        <th class="p-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#2f3031]">
                    <?php foreach($users_list as $u): ?>
                        <tr class="hover:bg-[#242526]/40">
                            <td class="p-3 font-bold text-white">@<?php echo $u['username']; ?></td>
                            <td class="p-3 text-gray-400"><?php echo $u['email']; ?></td>
                            <td class="p-3">
                                <?php if($u['username'] === 'adminRubel'): ?>
                                    <span class="text-red-400 font-mono text-[10px] uppercase bg-red-500/10 px-2 py-0.5 rounded border border-red-500/20">Root Protected</span>
                                <?php elseif($u['is_verified'] == 1): ?>
                                    <span class="text-blue-400 font-mono text-[10px] uppercase bg-blue-500/10 px-2 py-0.5 rounded border border-blue-500/20"><i class="fas fa-check-circle mr-1"></i>Official Active</span>
                                <?php else: ?>
                                    <span class="text-gray-500 font-mono text-[10px] uppercase bg-[#242526] px-2 py-0.5 rounded">Standard Account</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3 text-center flex justify-center gap-2">
                                <?php if($u['username'] !== 'adminRubel'): ?>
                                    <a href="dbms-system-management.php?action=toggle_verify&username=<?php echo $u['username']; ?>" class="text-xs font-bold px-2 py-1 rounded <?php echo ($u['is_verified'] == 1) ? 'bg-amber-600/20 text-amber-400 border border-amber-500/20' : 'bg-blue-600/20 text-blue-400 border border-blue-500/20'; ?>">
                                        <?php echo ($u['is_verified'] == 1) ? 'Remove Badge' : 'Verify Member'; ?>
                                    </a>
                                    <a href="dbms-system-management.php?action=delete_user&username=<?php echo $u['username']; ?>" onclick="return confirm('Purge user?')" class="text-red-400 hover:bg-red-500/10 px-2 py-1 rounded border border-red-500/10 font-bold">Wipe</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</body>
</html>
