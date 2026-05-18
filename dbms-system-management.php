<?php
// 🛠️ ১. স্ক্রিন ব্ল্যাংক হওয়া বন্ধ করতে লাইভ এরর রিপোর্টিং অন করা হলো
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'src/api/endpoint/db/db_config.php';
session_start();

// status_tracker.php ফাইলটি থাকলে লোড করবে
if (file_exists('status_tracker.php')) {
    require_once 'status_tracker.php';
}

// 🔒 সিকিউরিটি গার্ড ১: লগইন না থাকলে ইনডেক্স পেজে পাঠিয়ে দাও
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$current_user = $_SESSION['username'];
$msg = ''; $status = '';

// 🚨 👮 সিকিউরিটি গার্ড ২: হার্ডকোডেড অ্যাডমিন লক!
if ($current_user !== 'adminRubel') {
    die("
    <div style='background:#111213; color:#ff4d4d; font-family:sans-serif; text-align:center; padding:50px; min-height:100vh; display:flex; flex-direction:column; justify-content:center; align-items:center;'>
        <h2 style='letter-spacing:-1px; font-size:32px; font-weight:900;'>⚠️ CRITICAL VIOLATION: ACCESS DENIED</h2>
        <p style='color:#a0a5ad; max-width:500px; font-size:14px; margin-top:5px; line-height:1.6;'>
            Your account <strong>@{$current_user}</strong> does not possess Root Infrastructure privileges.
        </p>
        <a href='feed.php' style='margin-top:25px; background:#1877f2; color:white; padding:10px 24px; border-radius:10px; font-size:13px; font-weight:bold; text-decoration:none;'>Return to NewsFeed</a>
    </div>
    ");
}

// --- ⚙️ ৩. DBMS অ্যাকশন হ্যান্ডলার (ইউজার ডিলিট ও পোস্ট মডারেশন) ---

// ইউজার ডিলিট করার লজিক
if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['username'])) {
    $target_del = trim($_GET['username']);
    if ($target_del !== 'adminRubel') {
        $del_u = $pdo->prepare("DELETE FROM users WHERE username = ?");
        $del_u->execute([$target_del]);
        $del_p = $pdo->prepare("DELETE FROM posts WHERE username = ?");
        $del_p->execute([$target_del]);
        $msg = "User @{$target_del} and their data purged successfully!"; $status = "success";
    }
}

// পোস্ট ডিলিট করার লজিক
if (isset($_GET['action']) && $_GET['action'] === 'delete_post' && isset($_GET['id'])) {
    $post_id = intval($_GET['id']);
    $del_post = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $del_post->execute([$post_id]);
    $msg = "Post ID #{$post_id} removed by Admin!"; $status = "success";
}

// --- 📊 ৪. ডাটাবেজ মেটা এনালিটিক্স ফেচিং ---
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_posts = $pdo->query("SELECT COUNT(*) FROM posts WHERE post_type = 'post'")->fetchColumn();
$total_reels = $pdo->query("SELECT COUNT(*) FROM posts WHERE post_type = 'reel'")->fetchColumn();

// টেবিল ডাটা লোড
$users_list = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$posts_list = $pdo->query("SELECT id, username, content, post_type, reach_count, views_count, created_at FROM posts ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DBMS Control Center - Admin Panel</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#0f1011] text-[#e4e6eb] min-h-screen font-sans">

    <nav class="h-14 bg-[#18191a] border-b border-[#2f3031] flex items-center justify-between px-6 shadow-md">
        <div class="flex items-center gap-2">
            <i class="fas fa-database text-blue-500 text-lg"></i>
            <span class="font-black text-sm tracking-wider text-white uppercase">Core DBMS Coreframe</span>
        </div>
        <div class="flex items-center gap-4">
            <a href="feed.php" class="text-xs bg-[#242526] hover:bg-[#3a3b3c] px-3 py-1.5 rounded-lg font-bold transition-all"><i class="fas fa-home mr-1"></i>Back to Feed</a>
            <a href="index.php?action=logout" class="text-xs bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white px-3 py-1.5 rounded-lg font-bold transition-all">System Logout</a>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto p-6 space-y-6">

        <?php if($msg): ?>
            <div class="p-3 text-xs font-mono rounded-lg bg-blue-500/10 text-blue-400 border border-blue-500/20">
                [SYSTEM LOG]: <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-[#18191a] p-4 rounded-xl border border-[#2f3031]">
                <span class="text-xs text-gray-400 block font-medium uppercase">Total Registered Users</span>
                <span class="text-3xl font-black text-white font-mono"><?php echo $total_users; ?></span>
            </div>
            <div class="bg-[#18191a] p-4 rounded-xl border border-[#2f3031]">
                <span class="text-xs text-gray-400 block font-medium uppercase">Standard Timeline Posts</span>
                <span class="text-3xl font-black text-blue-500 font-mono"><?php echo $total_posts; ?></span>
            </div>
            <div class="bg-[#18191a] p-4 rounded-xl border border-[#2f3031]">
                <span class="text-xs text-gray-400 block font-medium uppercase">Video Reels Published</span>
                <span class="text-3xl font-black text-red-500 font-mono"><?php echo $total_reels; ?></span>
            </div>
        </div>

        <div class="bg-[#18191a] rounded-xl border border-[#2f3031] overflow-hidden shadow-xl">
            <div class="p-4 bg-[#242526] border-b border-[#2f3031] font-bold text-xs uppercase tracking-wider text-gray-300">
                <i class="fas fa-users mr-1.5 text-blue-400"></i> User Infrastructure Registry
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-[#1c1d1e] text-gray-400 border-b border-[#2f3031]">
                            <th class="p-3">ID</th>
                            <th class="p-3">Username</th>
                            <th class="p-3">Email Address</th>
                            <th class="p-3 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#2f3031]">
                        <?php foreach($users_list as $u): ?>
                            <tr class="hover:bg-[#242526]/40 transition-all">
                                <td class="p-3 font-mono text-gray-500"><?php echo $u['id']; ?></td>
                                <td class="p-3 font-bold text-white">@<?php echo $u['username']; ?></td>
                                <td class="p-3 text-gray-400"><?php echo $u['email']; ?></td>
                                <td class="p-3 text-center">
                                    <?php if($u['username'] !== 'adminRubel'): ?>
                                        <a href="dbms-system-management.php?action=delete_user&username=<?php echo $u['username']; ?>" onclick="return confirm('Purge this user and all their data permanently?')" class="text-red-400 hover:bg-red-500/10 px-2 py-1 rounded border border-red-500/10 font-bold">Wipe User</a>
                                    <?php else: ?>
                                        <span class="text-blue-400 font-mono text-[10px] uppercase bg-blue-500/10 px-2 py-0.5 rounded border border-blue-500/20">Root Protected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-[#18191a] rounded-xl border border-[#2f3031] overflow-hidden shadow-xl">
            <div class="p-4 bg-[#242526] border-b border-[#2f3031] font-bold text-xs uppercase tracking-wider text-gray-300">
                <i class="fas fa-stream mr-1.5 text-red-400"></i> Content & Media Moderation Stream
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-[#1c1d1e] text-gray-400 border-b border-[#2f3031]">
                            <th class="p-3">ID</th>
                            <th class="p-3">Author</th>
                            <th class="p-3">Content Preview</th>
                            <th class="p-3">Type</th>
                            <th class="p-3 font-mono text-right">Analytics (Reach / Views)</th>
                            <th class="p-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#2f3031]">
                        <?php foreach($posts_list as $p): ?>
                            <tr class="hover:bg-[#242526]/40 transition-all">
                                <td class="p-3 font-mono text-gray-500">#<?php echo $p['id']; ?></td>
                                <td class="p-3 font-bold text-gray-300">@<?php echo $p['username']; ?></td>
                                <td class="p-3 text-gray-400 max-w-xs truncate"><?php echo htmlspecialchars($p['content']); ?></td>
                                <td class="p-3">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase <?php echo ($p['post_type'] === 'reel') ? 'bg-red-500/10 text-red-400 border border-red-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20'; ?>">
                                        <?php echo $p['post_type']; ?>
                                    </span>
                                </td>
                                <td class="p-3 font-mono text-right text-gray-400">
                                    <span class="text-green-400"><?php echo $p['reach_count']; ?> R</span> 
                                    <?php if($p['post_type'] === 'reel'): ?>
                                        / <span class="text-amber-400"><?php echo $p['views_count']; ?> V</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-center">
                                    <a href="dbms-system-management.php?action=delete_post&id=<?php echo $p['id']; ?>" onclick="return confirm('Delete this content instantly?')" class="text-orange-400 hover:bg-orange-500/10 px-2 py-1 rounded border border-orange-500/10 font-bold">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</body>
</html>
