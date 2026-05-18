<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();
require_once 'status_tracker.php';

// 🔒 সেশন গার্ড: লগইন না থাকলে ইনডেক্সে পাঠাও
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$current_user = $_SESSION['username'];
$msg = ''; $status = '';

// --- ১. প্রোফাইল ইনফো ও পিকচার আপডেট হ্যান্ডলার ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $bio = htmlspecialchars(trim($_POST['bio'] ?? ''));
    $profile_pic = 'default.png';

    // আগের ইমেজ ট্র্যাক করা
    $img_stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE username = ?");
    $img_stmt->execute([$current_user]);
    $old_pic = $img_stmt->fetchColumn();
    if ($old_pic) { $profile_pic = $old_pic; }

    // ফাইল আপলোড লজিক
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_name = $_FILES['profile_pic']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $new_file_name = "profile_" . $current_user . "_" . time() . "." . $ext;
            $upload_dir = "uploads/";
            
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            
            $target_path = $upload_dir . $new_file_name;
            if (move_uploaded_file($file_tmp, $target_path)) {
                $profile_pic = $target_path;
            }
        }
    }

    $update_stmt = $pdo->prepare("UPDATE users SET bio = ?, profile_pic = ? WHERE username = ?");
    $update_stmt->execute([$bio, $profile_pic, $current_user]);
    $msg = "Profile updated successfully!"; $status = "success";
}

// --- ২. প্রোফাইল ওয়াল থেকে সরাসরি পোস্ট করার হ্যান্ডলার ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_post'])) {
    $content = htmlspecialchars(trim($_POST['content'] ?? ''));
    if (!empty($content)) {
        $stmt = $pdo->prepare("INSERT INTO posts (username, content) VALUES (?, ?)");
        $stmt->execute([$current_user, $content]);
        $msg = "Post published on your wall!"; $status = "success";
    }
}

// --- ৩. প্রোফাইল থেকে পোস্ট ডিলিট করার হ্যান্ডলার (Self Delete Only) ---
if (isset($_GET['action']) && $_GET['action'] === 'delete_post' && isset($_GET['id'])) {
    $post_id = intval($_GET['id']);
    
    // সিকিউরিটি চেক: পোস্টটি আসলেই এই ইউজারের কিনা!
    $check_stmt = $pdo->prepare("SELECT username FROM posts WHERE id = ?");
    $check_stmt->execute([$post_id]);
    $post_owner = $check_stmt->fetchColumn();

    if ($post_owner === $current_user) {
        $delete_stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $delete_stmt->execute([$post_id]);
        $msg = "Post deleted successfully!"; $status = "success";
    } else {
        $msg = "Unauthorized action!"; $status = "error";
    }
}

// --- ৪. কারেন্ট ইউজারের লাইভ ডাটা কুয়েরি ---
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$user_stmt->execute([$current_user]);
$my_info = $user_stmt->fetch();

$my_pic = (!empty($my_info['profile_pic']) && file_exists($my_info['profile_pic'])) ? $my_info['profile_pic'] : 'https://i.imgur.com/8Km9tLL.png';
$my_bio = $my_info['bio'] ?? 'Coding is life. Secure everything!';

// শুধুমাত্র এই ইউজারের করা পোস্টগুলোই ওয়াল-এ দেখানোর কুয়েরি
$my_posts_stmt = $pdo->prepare("SELECT * FROM posts WHERE username = ? ORDER BY created_at DESC");
$my_posts_stmt->execute([$current_user]);
$my_posts = $my_posts_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@<?php echo $current_user; ?> - Profile</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <a href="feed.php" class="text-[#1877f2] text-3xl font-black tracking-tighter">FriendBook</a>
        <div class="flex items-center gap-3">
            <a href="feed.php" class="bg-[#3a3b3c] hover:bg-[#4e4f50] px-4 py-2 rounded-xl text-sm font-semibold transition-all"><i class="fas fa-home mr-2"></i>Feed</a>
            <?php if($current_user === 'adminRubel'): ?>
                <a href="dbms-system-management.php" class="bg-red-600 px-3 py-2 rounded-xl text-xs font-bold text-white"><i class="fas fa-screwdriver-wrench mr-1"></i>DBMS</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto p-4 space-y-6 mt-4">

        <?php if($msg): ?>
            <div class="p-3 text-xs rounded-xl border font-bold <?php echo $status==='success'?'bg-green-500/10 text-green-400 border-green-500/20':'bg-red-500/10 text-red-400 border-red-500/20';?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="bg-[#242526] rounded-2xl border border-[#393a3b] overflow-hidden shadow-xl">
            <div class="h-36 bg-gradient-to-r from-[#1877f2] to-purple-600 relative"></div>
            <div class="px-6 pb-6 pt-1 flex flex-col sm:flex-row items-center sm:items-end justify-between gap-4 -mt-16 relative z-10">
                <div class="flex flex-col sm:flex-row items-center sm:items-end gap-4 text-center sm:text-left">
                    <img src="<?php echo $my_pic; ?>" class="w-28 h-28 rounded-full object-cover border-4 border-[#242526] shadow-2xl bg-[#18191a]">
                    <div class="mb-2">
                        <h2 class="text-2xl font-black text-white tracking-tight">@<?php echo $current_user; ?></h2>
                        <p class="text-xs text-gray-400 max-w-sm mt-1"><?php echo htmlspecialchars($my_bio); ?></p>
                    </div>
                </div>
                <button onclick="document.getElementById('editModal').classList.remove('hidden')" class="bg-[#3a3b3c] hover:bg-[#4e4f50] text-sm px-4 py-2 rounded-xl font-bold transition-all mb-2"><i class="fas fa-pen mr-2"></i>Edit Profile</button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div class="space-y-4">
                <div class="bg-[#242526] p-4 rounded-2xl border border-[#393a3b] shadow">
                    <h4 class="text-sm font-bold text-gray-300 mb-3 uppercase tracking-wider">Intro</h4>
                    <p class="text-xs text-gray-400 leading-relaxed"><i class="fas fa-quote-left mr-1.5 text-[#1877f2]"></i><?php echo htmlspecialchars($my_bio); ?></p>
                    <hr class="border-[#393a3b] my-3">
                    <div class="text-xs text-gray-400 space-y-2">
                        <div><i class="fas fa-calendar-alt w-5"></i> Joined: FriendBook Matrix</div>
                        <div><i class="fas fa-circle text-green-400 text-[9px] animate-pulse w-5"></i> Status: Active Online</div>
                    </div>
                </div>
            </div>

            <div class="md:col-span-2 space-y-4">
                
                <div class="bg-[#242526] p-4 rounded-2xl border border-[#2f3031] shadow-md">
                    <form action="profile.php" method="POST" class="space-y-3">
                        <div class="flex items-start gap-3">
                            <img src="<?php echo $my_pic; ?>" class="w-9 h-9 rounded-full object-cover">
                            <textarea name="content" placeholder="Write something directly on your profile wall..." required class="w-full bg-[#3a3b3c] rounded-xl p-3 outline-none text-white text-sm resize-none h-20 focus:border-[#1877f2] border border-transparent"></textarea>
                        </div>
                        <div class="flex justify-end border-t border-[#393a3b] pt-2">
                            <button type="submit" name="profile_post" class="bg-[#1877f2] hover:bg-blue-600 px-5 py-1.5 rounded-lg text-xs font-bold transition-all">Publish Post</button>
                        </div>
                    </form>
                </div>

                <div class="space-y-4">
                    <h3 class="text-md font-bold text-gray-300 px-1"><i class="fas fa-images mr-2"></i>Your Wall Posts</h3>
                    
                    <?php if(empty($my_posts)): ?>
                        <p class="text-xs text-gray-500 py-6 text-center bg-[#242526] rounded-2xl border border-[#393a3b]">You haven't posted anything on your profile yet.</p>
                    <?php else: ?>
                        <?php foreach($my_posts as $post): ?>
                            <div class="bg-[#242526] p-4 rounded-2xl border border-[#2f3031] shadow-sm relative group">
                                <div class="flex items-center justify-between mb-3">
                                    <div class="flex items-center gap-3">
                                        <img src="<?php echo $my_pic; ?>" class="w-9 h-9 rounded-full object-cover">
                                        <div>
                                            <h6 class="font-bold text-sm text-white">@<?php echo $current_user; ?></h6>
                                            <span class="text-[10px] text-gray-500 font-mono"><?php echo $post['created_at']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <a href="profile.php?action=delete_post&id=<?php echo $post['id']; ?>" onclick="return confirm('Delete this post permanently from your timeline?')" class="text-gray-500 hover:text-red-400 p-1.5 rounded-lg bg-[#3a3b3c]/30 hover:bg-red-500/10 transition-all text-xs font-bold">
                                        <i class="fas fa-trash-can mr-1"></i>Delete
                                    </a>
                                </div>
                                <p class="text-gray-200 text-sm whitespace-pre-wrap leading-relaxed px-1"><?php echo htmlspecialchars($post['content']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <div id="editModal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-[#242526] w-full max-w-md rounded-2xl border border-[#393a3b] p-5 shadow-2xl">
            <div class="flex justify-between items-center pb-2 border-b border-[#393a3b] mb-4">
                <h5 class="text-base font-bold text-white">Edit Profile Meta</h5>
                <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-white bg-[#3a3b3c] w-7 h-7 rounded-full flex items-center justify-center text-xs"><i class="fas fa-times"></i></button>
            </div>
            
            <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Upload New Profile Picture</label>
                    <input type="file" name="profile_pic" class="w-full text-xs text-gray-400 bg-[#3a3b3c] p-2.5 rounded-xl border border-[#393a3b] file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-bold file:bg-[#1877f2] file:text-white hover:file:bg-blue-600 cursor-pointer">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Your Bio / Status</label>
                    <textarea name="bio" rows="3" class="w-full bg-[#3a3b3c] rounded-xl p-3 outline-none text-white text-sm resize-none focus:border-[#1877f2] border border-[#393a3b]"><?php echo htmlspecialchars($my_bio); ?></textarea>
                </div>
                <button type="submit" name="update_profile" class="w-full bg-[#1877f2] hover:bg-blue-600 py-2 rounded-xl font-bold text-xs transition-all shadow-md">Save Dynamic Changes</button>
            </form>
        </div>
    </div>

    <script>
        // 🟢 গ্লোবাল অ্যাক্টিভ স্ট্যাটাস ট্র্যাকার হার্টবিট লুপ
        function sendHeartbeat() { fetch('status_tracker.php?action=heartbeat').catch(() => {}); }
        setInterval(sendHeartbeat, 10000); sendHeartbeat();
    </script>
</body>
</html>
