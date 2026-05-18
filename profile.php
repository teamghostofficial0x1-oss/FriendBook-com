<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }
$logged_in_user = $_SESSION['username'];

// অন্য ইউজারের প্রোফাইল ব্রাউজ করার প্যারামিটার লিংক (?user=username)
$profile_target = $_GET['user'] ?? $logged_in_user;
$profile_target = htmlspecialchars(trim($profile_target));

// প্রোফাইল পিকচার আপলোডার মেকানিজম
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar_img'])) {
    if ($_FILES['avatar_img']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['avatar_img']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $target_dir = "src/api/endpoint/uploads/" . $logged_in_user . "/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            $new_pic_path = $target_dir . "profile_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['avatar_img']['tmp_name'], $new_pic_path)) {
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE username = ?");
                $stmt->execute([$new_pic_path, $logged_in_user]);
            }
        }
    }
    header("Location: profile.php?user=" . $profile_target);
    exit;
}

// ফ্রেন্ড রিকোয়েস্ট মেকানিজম অ্যাকশন কন্ট্রোলার (Add Friend / Accept)
if (isset($_GET['action']) && $_GET['action'] === 'friend_request') {
    if ($profile_target !== $logged_in_user) {
        try {
            $stmt = $pdo->prepare("INSERT INTO friends (sender, receiver) VALUES (?, ?)");
            $stmt->execute([$logged_in_user, $profile_target]);
        } catch (Exception $e) {}
    }
    header("Location: profile.php?user=" . $profile_target);
    exit;
}

// টার্গেট ইউজারের ফুল ডাটাবেজ ইনফো লোড
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$profile_target]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$user_info) { die("Database Fault: Target account footprint not registry."); }

$u_pic = 'https://i.imgur.com/8Km9tLL.png';
if (!empty($user_info['profile_pic']) && file_exists($user_info['profile_pic'])) { $u_pic = $user_info['profile_pic']; }

// ফ্রেন্ড রিকোয়েস্ট স্ট্যাটাস চেক
$f_chk = $pdo->prepare("SELECT status FROM friends WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?)");
$f_chk->execute([$logged_in_user, $profile_target, $profile_target, $logged_in_user]);
$friend_status = $f_chk->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Hub - <?php echo $profile_target; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen font-sans">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <a href="feed.php" class="text-[#1877f2] text-3xl font-black tracking-tighter">FriendBook</a>
        <a href="feed.php" class="bg-[#3a3b3c] hover:bg-[#4e4f50] px-4 py-2 rounded-xl text-xs font-bold text-white"><i class="fas fa-home mr-1"></i> Back to Newsfeed</a>
    </nav>

    <div class="w-full max-w-[850px] mx-auto bg-[#242526] rounded-b-xl border-x border-b border-[#2f3031] shadow-xl pb-6">
        <div class="h-48 md:h-64 bg-gradient-to-r from-blue-900 to-indigo-950 rounded-t-xl relative border-b border-[#393a3b]">
            <div class="absolute -bottom-16 left-6 md:left-10 w-32 h-32 md:w-36 md:h-36 rounded-full overflow-hidden border-4 border-[#242526] bg-[#18191a] relative group shadow-lg">
                <img src="<?php echo $u_pic; ?>" class="w-full h-full object-cover">
                <?php if($profile_target === $logged_in_user): ?>
                <form action="profile.php" method="POST" enctype="multipart/form-data" class="absolute inset-0 flex items-center justify-center bg-black/50 rounded-full opacity-0 group-hover:opacity-100 transition-all">
                    <label class="cursor-pointer text-white text-xs font-bold p-2 text-center">
                        <i class="fas fa-camera text-xl block mb-1"></i> Update
                        <input type="file" name="avatar_img" onchange="this.form.submit()" class="hidden" accept="image/*">
                    </label>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-20 px-6 md:px-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <h2 class="text-3xl font-black text-white flex items-center gap-2">
                    @<?php echo $user_info['username']; ?>
                    <?php if($user_info['username'] === 'adminRubel'): ?>
                        <span class="bg-red-600 text-white text-[10px] px-2 py-0.5 rounded font-black uppercase tracking-wider shadow-sm"><i class="fas fa-crown"></i> Admin Account</span>
                    <?php elseif($user_info['is_verified'] == 1): ?>
                        <span class="bg-[#1877f2] text-white text-sm w-6 h-6 flex items-center justify-center rounded-full shadow-md" title="Meta Global Verified Token Active"><i class="fas fa-check text-[10px]"></i></span>
                    <?php endif; ?>
                </h2>
                <p class="text-xs text-gray-400 font-mono"><i class="fas fa-envelope mr-1"></i> <?php echo $user_info['email']; ?></p>
            </div>

            <div class="flex gap-2">
                <?php if($profile_target === $logged_in_user): ?>
                    <button class="bg-[#3a3b3c] hover:bg-[#4e4f50] text-xs font-bold px-4 py-2.5 rounded-lg text-white"><i class="fas fa-pen mr-1"></i> Edit Profile Bio</button>
                <?php else: ?>
                    <?php if(!$friend_status): ?>
                        <a href="profile.php?user=<?php echo $profile_target; ?>&action=friend_request" class="bg-[#1877f2] hover:bg-blue-600 text-xs font-bold px-5 py-2.5 rounded-lg text-white"><i class="fas fa-user-plus mr-1"></i> Add Friend</a>
                    <?php else: ?>
                        <button class="bg-[#3a3b3c] text-xs font-bold px-5 py-2.5 rounded-lg text-gray-300 uppercase tracking-wide cursor-not-allowed"><i class="fas fa-user-clock mr-1"></i> <?php echo $friend_status; ?></button>
                    <?php endif; ?>
                    <button class="bg-[#3a3b3c] hover:bg-[#4e4f50] text-xs font-bold px-4 py-2.5 rounded-lg text-white"><i class="fab fa-facebook-messenger mr-1"></i> Message Account</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-6 px-6 md:px-10 border-t border-[#2f3031] pt-4 max-w-xl">
            <h5 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Intro Registry</h5>
            <div class="p-3 bg-[#18191a] border border-[#2f3031] rounded-xl text-sm italic text-gray-300">
                <i class="fas fa-quote-left text-xs text-gray-500 mr-1"></i> <?php echo htmlspecialchars($user_info['bio']); ?>
            </div>
        </div>
    </div>

</body>
</html>
