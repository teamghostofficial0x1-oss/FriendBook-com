<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

// 🍪 অটো-লগইন সিঙ্ক
if (!isset($_SESSION['username']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmt = $pdo->prepare("SELECT username FROM users WHERE remember_token = ? AND remember_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) { $_SESSION['username'] = $user['username']; }
}

// সিকিউরিটি গার্ড লক
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$current_user = $_SESSION['username']; 
$message = ''; $status = '';

// ডাটাবেজ থেকে ইউজারের বর্তমান প্রোফাইল ডাটা তুলে আনা
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$current_user]);
$user_info = $stmt->fetch();

// ইমেজ আপলোড এবং প্রোফাইল সেভ মেকানিজম
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bio = htmlspecialchars(trim($_POST['bio'] ?? ''));
    $uploaded_file = $_FILES['profile_pic'] ?? null;
    $profile_pic_name = $user_info['profile_pic'] ?? 'default.png';

    if ($uploaded_file && $uploaded_file['error'] === UPLOAD_ERR_OK) {
        $target_dir = "src/api/endpoint/uploads/" . preg_replace('/[^a-zA-Z0-9_]/', '', $current_user) . "/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0755, true); }

        $file_name = basename($uploaded_file['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $new_file_name = "profile_" . uniqid() . "." . $file_ext;
            $target_file = $target_dir . $new_file_name;

            if (move_uploaded_file($uploaded_file['tmp_name'], $target_file)) {
                if ($user_info['profile_pic'] !== 'default.png' && file_exists($user_info['profile_pic'])) {
                    @unlink($user_info['profile_pic']);
                }
                $profile_pic_name = $target_file;
            }
        } else { $message = "Invalid image type!"; $status = "error"; }
    }

    if ($status !== 'error') {
        $update_stmt = $pdo->prepare("UPDATE users SET bio = ?, profile_pic = ?, updated_at = CURRENT_TIMESTAMP WHERE username = ?");
        $update_stmt->execute([$bio, $profile_pic_name, $current_user]);
        $message = "Profile synced successfully!"; $status = "success";
        $user_info['bio'] = $bio; $user_info['profile_pic'] = $profile_pic_name;
    }
}

$display_pic = (!empty($user_info['profile_pic']) && file_exists($user_info['profile_pic'])) ? $user_info['profile_pic'] : 'https://i.imgur.com/8Km9tLL.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Profile Settings - FriendBook</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex flex-col items-center justify-center p-4">

    <div class="bg-[#242526] border border-[#393a3b] p-6 rounded-xl w-full max-w-md shadow-xl">
        <div class="flex justify-between items-center mb-4">
            <a href="feed.php" class="text-xs text-[#1877f2] font-bold"><i class="fas fa-arrow-left mr-1"></i> Back to Feed</a>
            <h4 class="text-md font-bold">Edit Profile</h4>
        </div>

        <?php if($message): ?><div class="p-3 text-xs rounded mb-4 font-bold <?php echo $status==='success'?'bg-green-500/10 text-green-400':'bg-red-500/10 text-red-400';?>"><?php echo $message; ?></div><?php endif; ?>

        <form action="profile.php" method="POST" enctype="multipart/form-data" class="space-y-4 text-center">
            <div class="relative w-28 h-28 mx-auto rounded-full overflow-hidden border-4 border-[#1877f2]">
                <img src="<?php echo $display_pic; ?>" class="w-full h-full object-cover">
            </div>
            
            <div class="text-left">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Username</label>
                <input type="text" value="@<?php echo $current_user; ?>" disabled class="w-full bg-[#18191a] p-2.5 rounded text-sm text-gray-500 border border-[#393a3b] cursor-not-allowed">
            </div>

            <div class="text-left">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Update Avatar</label>
                <input type="file" name="profile_pic" class="w-full bg-[#3a3b3c] p-2 rounded text-xs">
            </div>

            <div class="text-left">
                <label class="block text-xs font-bold text-gray-400 uppercase mb-1">Bio Text</label>
                <textarea name="bio" rows="3" class="w-full bg-[#3a3b3c] p-2.5 rounded text-sm outline-none border border-[#393a3b] focus:border-[#1877f2]"><?php echo htmlspecialchars($user_info['bio'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="w-full bg-[#1877f2] py-2 rounded-lg font-bold text-sm hover:bg-[#1565c0]">Save Modifications</button>
        </form>
    </div>

</body>
</html>
