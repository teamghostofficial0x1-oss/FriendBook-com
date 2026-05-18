<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();
require_once 'status_tracker.php';

if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }
$me = $_SESSION['username'];

if (isset($_GET['action']) && isset($_GET['user'])) {
    $target_user = trim($_GET['user']);
    $action = $_GET['action'];

    if ($action === 'add') {
        try {
            $pdo->prepare("INSERT INTO friends (sender, receiver, status) VALUES (?, ?, 'pending')")->execute([$me, $target_user]);
        } catch (Exception $e) {}
    }
    if ($action === 'confirm') {
        $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE sender = ? AND receiver = ?")->execute([$target_user, $me]);
    }
    if ($action === 'unfriend' || $action === 'cancel') {
        $pdo->prepare("DELETE FROM friends WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?)")->execute([$me, $target_user, $target_user, $me]);
    }
    header("Location: friend-list.php"); exit;
}

$friend_requests = $pdo->prepare("SELECT sender FROM friends WHERE receiver = ? AND status = 'pending'");
$friend_requests->execute([$me]);
$req_list = $friend_requests->fetchAll(PDO::FETCH_COLUMN);

$friends_stmt = $pdo->prepare("SELECT CASE WHEN sender = ? THEN receiver ELSE sender END FROM friends WHERE (sender = ? OR receiver = ?) AND status = 'accepted'");
$friends_stmt->execute([$me, $me, $me]);
$my_friends = $friends_stmt->fetchAll(PDO::FETCH_COLUMN);

$all_users = $pdo->prepare("SELECT username, profile_pic, bio, last_seen FROM users WHERE username != ?");
$all_users->execute([$me]);
$user_rows = $all_users->fetchAll(PDO::FETCH_ASSOC);

$sent_requests = $pdo->prepare("SELECT receiver FROM friends WHERE sender = ? AND status = 'pending'");
$sent_requests->execute([$me]);
$sent_list = $sent_requests->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Network - FriendBook</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen">
    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <a href="feed.php" class="text-[#1877f2] text-3xl font-black tracking-tighter">FriendBook</a>
        <a href="feed.php" class="bg-[#3a3b3c] hover:bg-[#4e4f50] px-4 py-2 rounded-xl text-sm font-semibold"><i class="fas fa-home mr-2"></i>Back to Feed</a>
    </nav>

    <div class="max-w-4xl mx-auto p-4 space-y-6 mt-4">
        <?php if(!empty($req_list)): ?>
        <section class="bg-[#242526] p-4 rounded-xl border border-[#393a3b]">
            <h3 class="text-md font-bold text-yellow-400 mb-3"><i class="fas fa-user-plus mr-2"></i>Friend Requests</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach($req_list as $u_req): ?>
                <div class="flex items-center justify-between p-3 bg-[#3a3b3c] rounded-xl">
                    <span class="font-bold text-sm">@<?php echo $u_req; ?></span>
                    <div class="flex gap-2">
                        <a href="friend-list.php?action=confirm&user=<?php echo $u_req; ?>" class="bg-[#1877f2] px-3 py-1.5 rounded-lg text-xs font-bold">Confirm</a>
                        <a href="friend-list.php?action=unfriend&user=<?php echo $u_req; ?>" class="bg-neutral-600 px-3 py-1.5 rounded-lg text-xs font-bold">Delete</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="bg-[#242526] p-4 rounded-xl border border-[#393a3b]">
            <h3 class="text-md font-bold text-[#1877f2] mb-3"><i class="fas fa-user-friends mr-2"></i>Your Friends (<?php echo count($my_friends); ?>)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach($my_friends as $frnd): ?>
                <div class="flex items-center justify-between p-3 bg-[#18191a] rounded-xl border border-[#393a3b]">
                    <span class="font-bold text-sm">@<?php echo $frnd; ?></span>
                    <a href="chat.php" class="bg-blue-600 px-3 py-1.5 rounded-lg text-xs font-bold"><i class="fab fa-facebook-messenger mr-1"></i>Chat</a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bg-[#242526] p-4 rounded-xl border border-[#393a3b]">
            <h3 class="text-md font-bold mb-3"><i class="fas fa-search mr-2"></i>Discover People</h3>
            <div class="space-y-3">
                <?php foreach($user_rows as $row): 
                    $u_name = $row['username']; if(in_array($u_name, $my_friends)) continue;
                    $online_status = getUserStatus($row['last_seen']);
                    $pic = (!empty($row['profile_pic']) && file_exists($row['profile_pic'])) ? $row['profile_pic'] : 'https://i.imgur.com/8Km9tLL.png';
                ?>
                <div class="flex items-center justify-between p-3 bg-[#18191a] rounded-xl border border-[#2f3031]">
                    <div class="flex items-center gap-3">
                        <div class="relative">
                            <img src="<?php echo $pic; ?>" class="w-10 h-10 rounded-full object-cover border border-[#393a3b]">
                            <span class="absolute bottom-0 right-0 w-3 h-3 border-2 border-[#18191a] rounded-full <?php echo $online_status==='online'?'bg-green-500':'bg-gray-500';?>"></span>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold">@<?php echo $u_name; ?> <span class="text-[10px] font-normal text-gray-500">(<?php echo $online_status; ?>)</span></h5>
                            <p class="text-xs text-gray-400 truncate max-w-[200px]"><?php echo htmlspecialchars($row['bio']); ?></p>
                        </div>
                    </div>
                    <div>
                        <?php if(in_array($u_name, $sent_list)): ?>
                            <a href="friend-list.php?action=cancel&user=<?php echo $u_name; ?>" class="bg-yellow-600/20 text-yellow-400 border border-yellow-600/30 px-3 py-1.5 rounded-lg text-xs font-bold">Cancel Request</a>
                        <?php else: ?>
                            <a href="friend-list.php?action=add&user=<?php echo $u_name; ?>" class="bg-[#1877f2] px-4 py-1.5 rounded-lg text-xs font-bold"><i class="fas fa-user-plus mr-1"></i>Add</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <script>
        function sendHeartbeat() { fetch('status_tracker.php?action=heartbeat').catch(() => {}); }
        setInterval(sendHeartbeat, 10000); sendHeartbeat();
    </script>
</body>
</html>
