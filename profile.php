<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }
$logged_in_user = $_SESSION['username'];

// 📸 প্রোফাইল পিকচার আপলোড ইঞ্জিন ফিক্স
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar_img'])) {
    if ($_FILES['avatar_img']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['avatar_img']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $target_dir = "src/api/endpoint/uploads/" . $logged_in_user . "/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            
            $new_pic_path = $target_dir . "profile_" . time() . "." . $ext;
            
            if (move_uploaded_file($_FILES['avatar_img']['tmp_name'], $new_pic_path)) {
                // ডাটাবেজে নতুন প্রোফাইল পিকচার সেভ করা
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = ? WHERE username = ?");
                $stmt->execute([$new_pic_path, $logged_in_user]);
            }
        }
    }
    header("Location: profile.php");
    exit;
}

// ইউজারের কারেন্ট ডাটা রিড
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$logged_in_user]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

$u_pic = 'https://i.imgur.com/8Km9tLL.png';
if (!empty($user_info['profile_pic']) && file_exists($user_info['profile_pic'])) {
    $u_pic = $user_info['profile_pic'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - FriendBook</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen font-sans">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <a href="feed.php" class="text-[#1877f2] text-3xl font-black tracking-tighter">FriendBook</a>
        <a href="feed.php" class="bg-[#3a3b3c] hover:bg-[#4e4f50] px-4 py-2 rounded-xl text-xs font-bold text-white"><i class="fas fa-home mr-2"></i>Home Feed</a>
    </nav>

    <div class="max-w-2xl mx-auto mt-6 p-4">
        <div class="bg-[#242526] rounded-2xl border border-[#2f3031] p-6 text-center space-y-4 shadow-xl">
            
            <div class="relative w-32 h-32 mx-auto group">
                <img src="<?php echo $u_pic; ?>" class="w-full h-full object-cover rounded-full border-4 border-[#1877f2] shadow-md">
                
                <form action="profile.php" method="POST" enctype="multipart/form-data" class="absolute inset-0 flex items-center justify-center bg-black/50 rounded-full opacity-0 group-hover:opacity-100 transition-all cursor-pointer">
                    <label class="cursor-pointer text-white text-xs font-bold text-center p-2">
                        <i class="fas fa-camera text-xl block mb-1"></i> Update
                        <input type="file" name="avatar_img" onchange="this.form.submit()" class="hidden" accept="image/*">
                    </label>
                </form>
            </div>

            <div>
                <h2 class="text-2xl font-black text-white flex items-center justify-center gap-1.5">
                    @<?php echo $user_info['username']; ?>
                    <?php if($user_info['is_verified'] == 1): ?>
                        <span class="bg-[#1877f2] text-white text-xs w-5 h-5 flex items-center justify-center rounded-full"><i class="fas fa-check text-[9px]"></i></span>
                    <?php endif; ?>
                </h2>
                <p class="text-xs text-gray-400 font-mono"><?php echo $user_info['email']; ?></p>
            </div>

            <p class="text-xs text-gray-300 italic p-3 bg-[#18191a] rounded-xl border border-[#2f3031] max-w-sm mx-auto">
                "<?php echo htmlspecialchars($user_info['bio']); ?>"
            </p>
        </div>
    </div>

</body>
</html>
