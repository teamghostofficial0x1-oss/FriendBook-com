<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }
$logged_in_user = $_SESSION['username'];

// ডাইনামিক ইউজার ডিটেকশন 
$profile_user = $_GET['user'] ?? $logged_in_user;
$profile_user = htmlspecialchars(trim($profile_user));

// প্রোফাইল ইউজারের ডাটা তুলে আনা
$stmt = $pdo->prepare("SELECT username, email, bio, profile_pic FROM users WHERE username = ?");
$stmt->execute([$profile_user]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_info) {
    die("User not found in infrastructure database.");
}

// বায়ো আপডেট করার হ্যান্ডলার
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_bio']) && $profile_user === $logged_in_user) {
    $new_bio = htmlspecialchars($_POST['bio_text']);
    $up = $pdo->prepare("UPDATE users SET bio = ? WHERE username = ?");
    $up->execute([$new_bio, $logged_in_user]);
    header("Location: profile.php");
    exit;
}

// 🎯 ডাইনামিক সোর্স চেক: নতুন আর্কিটেকচার অনুযায়ী ইমেজ পাথ ডিটেকশন
$u_pic = 'https://i.imgur.com/8Km9tLL.png';
if (!empty($user_info['profile_pic']) && file_exists($user_info['profile_pic'])) {
    $u_pic = $user_info['profile_pic'];
}

// এই নির্দিষ্ট ইউজারের করা পোস্টগুলো শুধু কুয়েরি করা
$p_stmt = $pdo->prepare("SELECT * FROM posts WHERE username = ? ORDER BY created_at DESC");
$p_stmt->execute([$profile_user]);
$user_posts = $p_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@<?php echo $profile_user; ?> - Profile</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <a href="feed.php" class="text-[#1877f2] text-3xl font-black tracking-tighter">FriendBook</a>
        <div class="flex items-center gap-3">
            <a href="feed.php" class="bg-[#3a3b3c] hover:bg-[#4e4f50] px-4 py-2 rounded-xl text-sm font-bold transition-all"><i class="fas fa-home mr-2"></i>Feed</a>
            <?php if($logged_in_user === 'adminRubel'): ?>
                <a href="dbms-system-management.php" class="bg-blue-600 px-3 py-2 rounded-xl text-xs font-bold text-white"><i class="fas fa-screwdriver-wrench mr-1"></i>DBMS</a>
            <?php endif; ?>
            <a href="index.php?action=logout" class="bg-red-600/20 hover:bg-red-600 text-red-400 hover:text-white px-3 py-2 rounded-xl text-xs font-bold transition-all"><i class="fas fa-sign-out-alt mr-1"></i>Logout</a>
        </div>
    </nav>

    <div class="max-w-2xl mx-auto mt-6 p-4">
        <div class="bg-[#242526] rounded-2xl border border-[#2f3031] p-6 shadow-xl text-center space-y-4">
            <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-4 border-[#1877f2] bg-neutral-800">
                <img src="<?php echo $u_pic; ?>" class="w-full h-full object-cover">
            </div>
            <div>
                <h2 class="text-xl font-black text-white">@<?php echo $profile_user; ?></h2>
                <p class="text-xs text-gray-400"><?php echo $user_info['email']; ?></p>
            </div>
            
            <p class="text-xs text-gray-300 italic max-w-md mx-auto px-4 py-2 bg-[#18191a] rounded-xl border border-[#2f3031]">
                "<?php echo htmlspecialchars($user_info['bio']); ?>"
            </p>

            <?php if($profile_user === $logged_in_user): ?>
                <form action="profile.php" method="POST" class="pt-2 flex flex-col items-center gap-2">
                    <input type="text" name="bio_text" placeholder="Update your daily status/bio..." class="w-full max-w-xs bg-[#3a3b3c] text-xs outline-none py-2 px-3 rounded-xl text-white text-center border border-[#393a3b] focus:border-[#1877f2]">
                    <button type="submit" name="update_bio" class="bg-[#1877f2] hover:bg-blue-600 text-[10px] text-white font-black px-4 py-1.5 rounded-lg uppercase tracking-wide">Save Bio</button>
                </form>
            <?php else: ?>
                <div class="pt-2">
                    <a href="chat.php" class="bg-green-600 hover:bg-green-500 text-white text-xs font-bold px-4 py-2 rounded-xl inline-flex items-center gap-2 shadow"><i class="fab fa-facebook-messenger"></i> Send Message</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="mt-6 space-y-4">
            <h4 class="text-xs font-black text-gray-400 uppercase tracking-wider mb-2"><i class="fas fa-stream mr-1.5"></i> Posts by @<?php echo $profile_user; ?></h4>
            
            <?php if(empty($user_posts)): ?>
                <p class="text-xs text-gray-500 text-center py-10 bg-[#242526] rounded-xl border border-[#2f3031]">This user hasn't published any content yet.</p>
            <?php else: ?>
                <?php foreach($user_posts as $post): ?>
                    <div class="bg-[#242526] rounded-xl p-4 border border-[#2f3031] space-y-3 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-neutral-700 text-white flex items-center justify-center rounded-full font-bold text-xs uppercase"><?php echo substr($post['username'], 0, 2); ?></div>
                                <div>
                                    <h6 class="font-black text-xs">@<?php echo $post['username']; ?></h6>
                                    <span class="text-[9px] text-gray-500"><?php echo $post['created_at']; ?></span>
                                </div>
                            </div>
                            <span class="text-[9px] font-mono text-gray-500"><i class="fas fa-chart-line mr-1"></i><?php echo $post['reach_count']; ?> Reach</span>
                        </div>
                        <p class="text-gray-200 text-xs leading-relaxed whitespace-pre-wrap"><?php echo htmlspecialchars($post['content']); ?></p>
                        
                        <?php if(!empty($post['post_pic']) && file_exists($post['post_pic'])): ?>
                            <?php if($post['post_type'] === 'reel'): ?>
                                <div class="rounded-xl overflow-hidden bg-black max-h-[350px]"><video src="<?php echo $post['post_pic']; ?>" controls class="w-full h-full"></video></div>
                            <?php else: ?>
                                <div class="rounded-xl overflow-hidden border border-[#393a3b]"><img src="<?php echo $post['post_pic']; ?>" class="w-full object-cover"></div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
