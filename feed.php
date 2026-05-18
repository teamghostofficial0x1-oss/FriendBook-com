<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }
$current_user = $_SESSION['username'];

// --- ১. ফিক্সড পোস্ট ও রিল আপলোড প্রসেসর ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_post') {
    header('Content-Type: application/json');
    $content = htmlspecialchars($_POST['content'] ?? '');
    $post_type = $_POST['post_type'] ?? 'post'; 
    $upload_path = null;

    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        $allowed = ($post_type === 'reel') ? ['mp4', 'mov', 'avi'] : ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $target_dir = "src/api/endpoint/uploads/" . $current_user . "/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            
            $upload_path = $target_dir . $post_type . "_" . time() . "_" . uniqid() . "." . $ext;
            move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path);
        }
    }

    if (!empty($content) || $upload_path !== null) {
        $stmt = $pdo->prepare("INSERT INTO posts (username, content, post_pic, post_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$current_user, $content, $upload_path, $post_type]);
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "empty"]);
    }
    exit;
}

// --- ২. ফিক্সড নিউজফিড পোস্ট রিডার ---
if (isset($_GET['action']) && $_GET['action'] === 'fetch_posts') {
    header('Content-Type: application/json');
    $filter_type = $_GET['type'] ?? 'all'; 

    $query = "SELECT p.*, u.is_verified, u.profile_pic as u_pic 
              FROM posts p
              LEFT JOIN users u ON p.username = u.username ";
    if($filter_type === 'reel') { $query .= " WHERE p.post_type = 'reel' "; }
    $query .= " ORDER BY p.created_at DESC LIMIT 40";
    
    $stmt = $pdo->query($query);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($posts);
    exit;
}

// প্রোফাইল পিকচার ডিটেক্টর
$user_pic = 'https://i.imgur.com/8Km9tLL.png';
$stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE username = ?"); $stmt->execute([$current_user]);
$res = $stmt->fetch(); 
if ($res && !empty($res['profile_pic']) && file_exists($res['profile_pic'])) { 
    $user_pic = $res['profile_pic']; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FriendBook - Feed</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex flex-col font-sans">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <span class="text-[#1877f2] text-3xl font-black tracking-tighter cursor-pointer" onclick="switchTab('all')">FriendBook</span>
        <div class="flex items-center gap-4">
            <a href="feed.php" class="text-xl text-[#1877f2]"><i class="fas fa-home"></i></a>
            <a href="chat.php" class="text-xl text-gray-400 hover:text-white"><i class="fab fa-facebook-messenger"></i></a>
            <a href="profile.php" class="w-8 h-8 rounded-full overflow-hidden border border-gray-600 block"><img src="<?php echo $user_pic; ?>" class="w-full h-full object-cover"></a>
            <a href="index.php?action=logout" class="text-xs bg-red-600/20 text-red-400 px-3 py-1.5 rounded-lg"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </nav>

    <div class="flex justify-center w-full max-w-[1200px] mx-auto flex-1 px-4 gap-6 mt-4">
        
        <main class="w-full max-w-[580px] space-y-4">
            <div class="bg-[#242526] rounded-xl p-4 shadow border border-[#2f3031] space-y-3">
                <div class="flex items-center gap-3">
                    <img src="<?php echo $user_pic; ?>" class="w-10 h-10 rounded-full object-cover">
                    <button onclick="openPostModal('post')" class="w-full bg-[#3a3b3c] hover:bg-[#4e4f50] text-left text-gray-400 text-sm py-2.5 px-4 rounded-full">What's on your mind, <?php echo $current_user; ?>?</button>
                </div>
                <div class="flex justify-between border-t border-[#393a3b] pt-2">
                    <button onclick="openPostModal('post')" class="flex items-center justify-center gap-2 text-green-500 hover:bg-[#3a3b3c] w-full py-2 rounded-lg text-sm font-bold"><i class="fas fa-images"></i> Photo</button>
                    <button onclick="openPostModal('reel')" class="flex items-center justify-center gap-2 text-red-500 hover:bg-[#3a3b3c] w-full py-2 rounded-lg text-sm font-bold"><i class="fas fa-video"></i> Reel Video</button>
                </div>
            </div>

            <div id="dynamicFeedPipeline" class="space-y-4 mb-20"></div>
        </main>
    </div>

    <div id="modalBox" class="hidden fixed inset-0 bg-black/70 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-[#242526] w-full max-w-[500px] rounded-xl border border-[#393a3b] p-4">
            <div class="flex justify-between items-center pb-2 border-b border-[#393a3b] mb-4">
                <h5 id="modalTitle" class="text-lg font-bold text-white">Create Post</h5>
                <button onclick="closeModal()" class="text-gray-400 bg-[#3a3b3c] w-7 h-7 rounded-full"><i class="fas fa-times"></i></button>
            </div>
            <form id="submissionForm" onsubmit="executePublish(event)" class="space-y-4">
                <input type="hidden" name="post_type" id="formPostType" value="post">
                <textarea name="content" id="formTextarea" placeholder="What's on your mind?" class="w-full bg-transparent outline-none text-white text-sm resize-none h-24"></textarea>
                <div class="p-3 bg-[#18191a] rounded-lg border border-[#393a3b] flex justify-between items-center">
                    <span class="text-xs text-gray-400">Add to your post:</span>
                    <label class="bg-[#3a3b3c] hover:bg-[#4e4f50] px-4 py-1.5 rounded text-xs font-bold cursor-pointer text-white">
                        Choose File <input type="file" name="media_file" id="mediaFile" class="hidden">
                    </label>
                </div>
                <button type="submit" class="w-full bg-[#1877f2] hover:bg-blue-600 py-2.5 rounded-lg font-bold text-sm text-white shadow-md">Post</button>
            </form>
        </div>
    </div>

    <script>
        let currentTab = 'all';
        const pipeline = document.getElementById('dynamicFeedPipeline');

        function openPostModal(type) {
            document.getElementById('modalBox').classList.remove('hidden');
            document.getElementById('formPostType').value = type;
            document.getElementById('modalTitle').innerText = type === 'reel' ? 'Create Reel' : 'Create Post';
        }
        function closeModal() { document.getElementById('modalBox').classList.add('hidden'); document.getElementById('submissionForm').reset(); }

        function executePublish(e) {
            e.preventDefault();
            let fd = new FormData(document.getElementById('submissionForm'));
            fetch('feed.php?action=create_post', { method: 'POST', body: fd })
            .then(res => res.json())
            .then(() => { closeModal(); loadPipeline(); });
        }

        function loadPipeline() {
            fetch(`feed.php?action=fetch_posts&type=${currentTab}`)
                .then(res => res.json())
                .then(posts => {
                    pipeline.innerHTML = '';
                    if(posts.length === 0) {
                        pipeline.innerHTML = '<p class="text-xs text-gray-500 text-center py-10">No posts available.</p>';
                        return;
                    }
                    posts.forEach(p => {
                        const card = document.createElement('div');
                        card.className = "bg-[#242526] rounded-xl p-4 border border-[#2f3031] space-y-3 shadow-sm";
                        
                        let verifiedBadge = '';
                        if (p.username === 'adminRubel') {
                            verifiedBadge = `<span class="bg-red-600/20 text-red-400 border border-red-500/30 text-[9px] px-1.5 py-0.5 rounded font-black ml-1.5"><i class="fas fa-crown"></i> ADMIN</span>`;
                        } else if (p.is_verified == 1) {
                            verifiedBadge = `<span class="bg-[#1877f2] text-white text-[10px] w-4 h-4 inline-flex items-center justify-center rounded-full ml-1" title="Verified Member"><i class="fas fa-check text-[7px]"></i></span>`;
                        }
                        
                        let avatar = p.u_pic && p.u_pic !== 'default.png' ? p.u_pic : 'https://i.imgur.com/8Km9tLL.png';

                        let mediaMarkup = '';
                        if (p.post_pic && p.post_pic.trim() !== '') {
                            mediaMarkup = p.post_type === 'reel' 
                                ? `<div class="rounded-xl overflow-hidden bg-black max-h-[450px]"><video src="${p.post_pic}" controls class="w-full h-full"></video></div>`
                                : `<div class="rounded-xl overflow-hidden border border-[#393a3b]"><img src="${p.post_pic}" class="w-full object-cover"></div>`;
                        }

                        card.innerHTML = `
                            <div class="flex items-center gap-2">
                                <img src="${avatar}" class="w-9 h-9 rounded-full object-cover border border-gray-700">
                                <div>
                                    <h6 class="font-bold text-sm flex items-center text-white">@${p.username} ${verifiedBadge}</h6>
                                    <span class="text-[10px] text-gray-500">${p.created_at}</span>
                                </div>
                            </div>
                            <p class="text-gray-200 text-sm leading-relaxed">${p.content}</p>
                            ${mediaMarkup}
                        `;
                        pipeline.appendChild(card);
                    });
                });
        }
        loadPipeline();
    </script>
</body>
</html>
