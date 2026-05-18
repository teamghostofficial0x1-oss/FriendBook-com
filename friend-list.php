<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$current_user = $_SESSION['username'];
$msg = ''; $status = '';

// --- ১. ফ্রেন্ড রিকোয়েস্ট পাঠানো, এক্সেপ্ট বা রিজেক্ট করার হ্যান্ডলার ---
if (isset($_GET['action']) && isset($_GET['user'])) {
    $target_user = trim($_GET['user']);
    $action = $_GET['action'];

    if ($action === 'send' && $target_user !== $current_user) {
        try {
            $stmt = $pdo->prepare("INSERT INTO friends (sender, receiver, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$current_user, $target_user]);
            $msg = "Friend request sent to @{$target_user}!"; $status = "success";
        } catch (PDOException $e) { $msg = "Request already pending or connected!"; $status = "error"; }
    }
    
    if ($action === 'accept') {
        $stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE sender = ? AND receiver = ?");
        $stmt->execute([$target_user, $current_user]);
        $msg = "You are now friends with @{$target_user}!"; $status = "success";
    }

    if ($action === 'reject') {
        $stmt = $pdo->prepare("DELETE FROM friends WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?)");
        $stmt->execute([$target_user, $current_user, $current_user, $target_user]);
        $msg = "Request removed!"; $status = "success";
    }
}

// --- ২. সার্চ বা ফিল্টার লজিক ---
$search = $_GET['search'] ?? '';
try {
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT username, profile_pic, bio FROM users WHERE username LIKE ? AND username != ? LIMIT 20");
        $stmt->execute(["%$search%", $current_user]);
    } else {
        $stmt = $pdo->prepare("SELECT username, profile_pic, bio FROM users WHERE username != ? ORDER BY id DESC LIMIT 20");
        $stmt->execute([$current_user]);
    }
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stmt = $pdo->prepare("SELECT username FROM users WHERE username != ?");
    $stmt->execute([$current_user]);
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- ৩. পেন্ডিং রিকোয়েস্ট লিস্ট তুলে আনা ---
$req_stmt = $pdo->prepare("SELECT sender FROM friends WHERE receiver = ? AND status = 'pending'");
$req_stmt->execute([$current_user]);
$pending_requests = $req_stmt->fetchAll(PDO::FETCH_COLUMN);

// --- ৪. অলরেডি ফ্রেন্ড কারা আছে তাদের লিস্ট ---
$frnd_stmt = $pdo->prepare("SELECT CASE WHEN sender = ? THEN receiver ELSE sender END FROM friends WHERE (sender = ? OR receiver = ?) AND status = 'accepted'");
$frnd_stmt->execute([$current_user, $current_user, $current_user]);
$my_friends = $frnd_stmt->fetchAll(PDO::FETCH_COLUMN);

// --- ৫. আমি কাকে কাকে রিকোয়েস্ট পাঠিয়ে রেখেছি তা চেক করা ---
$sent_stmt = $pdo->prepare("SELECT receiver FROM friends WHERE sender = ? AND status = 'pending'");
$sent_stmt->execute([$current_user]);
$sent_requests = $sent_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Friends - FriendBook</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <a href="feed.php" class="text-[#1877f2] text-3xl font-black tracking-tighter">FriendBook</a>
        <a href="feed.php" class="bg-[#3a3b3c] hover:bg-[#4e4f50] px-4 py-2 rounded-xl text-sm font-bold transition-all"><i class="fas fa-home mr-2"></i>NewsFeed</a>
    </nav>

    <div class="max-w-4xl mx-auto p-4 space-y-6">

        <?php if($msg): ?>
            <div class="p-3 text-xs rounded-xl border font-bold bg-blue-500/10 text-blue-400 border-blue-500/20">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($pending_requests)): ?>
            <div class="bg-[#242526] p-4 rounded-xl border border-[#393a3b] shadow">
                <h3 class="text-sm font-bold text-white mb-3 uppercase tracking-wider text-amber-400"><i class="fas fa-bell mr-2"></i>Incoming Friend Requests</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php foreach($pending_requests as $sender_name): ?>
                        <div class="p-3 bg-[#18191a] rounded-xl border border-[#2f3031] flex justify-between items-center">
                            <a href="profile.php?user=<?php echo $sender_name; ?>" class="font-bold text-sm hover:underline hover:text-[#1877f2] cursor-pointer">@<?php echo $sender_name; ?></a>
                            <div class="flex gap-2">
                                <a href="friend-list.php?action=accept&user=<?php echo $sender_name; ?>" class="bg-green-600 hover:bg-green-500 text-white text-xs px-3 py-1.5 rounded-lg font-bold">Confirm</a>
                                <a href="friend-list.php?action=reject&user=<?php echo $sender_name; ?>" class="bg-neutral-700 hover:bg-neutral-600 text-gray-300 text-xs px-3 py-1.5 rounded-lg font-bold">Delete</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-[#242526] p-5 rounded-xl border border-[#2f3031] shadow-md">
            <div class="sm:flex justify-between items-center mb-4 gap-4">
                <h3 class="text-md font-bold text-white mb-2 sm:mb-0"><i class="fas fa-globe mr-2 text-[#1877f2]"></i>Discover New Friends</h3>
                <form action="friend-list.php" method="GET" class="relative flex-1 max-w-xs">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search users by name..." class="w-full bg-[#3a3b3c] pl-9 pr-4 py-1.5 rounded-lg text-xs outline-none text-white border border-transparent focus:border-[#1877f2]">
                    <i class="fas fa-search absolute left-3 top-2.5 text-xs text-gray-500"></i>
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php if(empty($all_users)): ?>
                    <p class="text-xs text-gray-500 text-center py-6 col-span-2">No users found to connect.</p>
                <?php else: ?>
                    <?php foreach($all_users as $user): 
                        $u_name = $user['username'];
                        $u_pic = (!empty($user['profile_pic']) && file_exists($user['profile_pic'])) ? $user['profile_pic'] : 'https://i.imgur.com/8Km9tLL.png';
                        $u_bio = $user['bio'] ?? 'Hello World! Connected via FriendBook.';
                    ?>
                        <div class="p-4 bg-[#18191a] rounded-xl border border-[#2f3031] flex items-center justify-between gap-3">
                            
                            <a href="profile.php?user=<?php echo $u_name; ?>" class="flex items-center gap-3 hover:opacity-80 cursor-pointer group">
                                <img src="<?php echo $u_pic; ?>" class="w-10 h-10 rounded-full object-cover bg-neutral-800 border border-transparent group-hover:border-[#1877f2]">
                                <div>
                                    <h5 class="font-bold text-sm text-white group-hover:underline group-hover:text-[#1877f2]">@<?php echo $u_name; ?></h5>
                                    <p class="text-[11px] text-gray-400 line-clamp-1 max-w-[180px]"><?php echo htmlspecialchars($u_bio); ?></p>
                                </div>
                            </a>

                            <div>
                                <?php if(in_array($u_name, $my_friends)): ?>
                                    <span class="text-xs text-green-400 font-bold bg-green-500/10 px-2.5 py-1.5 rounded-lg border border-green-500/10"><i class="fas fa-check mr-1"></i> Friends</span>
                                <?php elseif(in_array($u_name, $sent_requests)): ?>
                                    <a href="friend-list.php?action=reject&user=<?php echo $u_name; ?>" class="text-xs text-amber-400 font-bold bg-amber-500/10 px-2.5 py-1.5 rounded-lg border border-amber-500/10 hover:bg-red-500/20 hover:text-red-400 transition-all" title="Click to Cancel">Requested</a>
                                <?php elseif(in_array($u_name, $pending_requests)): ?>
                                    <a href="friend-list.php?action=accept&user=<?php echo $u_name; ?>" class="bg-blue-600 hover:bg-blue-500 text-white text-xs px-3 py-1.5 rounded-lg font-bold">Accept</a>
                                <?php else: ?>
                                    <a href="friend-list.php?action=send&user=<?php echo $u_name; ?>" class="bg-[#1877f2] hover:bg-blue-600 text-white text-xs px-3 py-1.5 rounded-lg font-bold transition-all"><i class="fas fa-user-plus mr-1"></i> Add</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</body>
</html>
