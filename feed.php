<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();
require_once 'status_tracker.php';

if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }
$current_user = $_SESSION['username'];

// --- ১. পোস্ট এবং রিলস/ভিডিও আপলোড হ্যান্ডলার ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_post') {
    header('Content-Type: application/json');
    $content = htmlspecialchars($_POST['content'] ?? '');
    $post_type = $_POST['post_type'] ?? 'post'; // 'post' অথবা 'reel'
    $upload_path = null;

    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        $allowed = ($post_type === 'reel') ? ['mp4', 'mov', 'avi', 'mkv'] : ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $upload_path = "uploads/" . $post_type . "_" . time() . "_" . uniqid() . "." . $ext;
            if (!is_dir('uploads/')) { mkdir('uploads/', 0777, true); }
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

// --- ২. ফেসবুক অ্যালগরিদম ভিত্তিক স্মার্ট ফিড লোডার (অ্যাডমিন প্রথমে -> তারপর ফ্রেন্ডস -> তারপর পাবলিক) ---
if (isset($_GET['action']) && $_GET['action'] === 'fetch_posts') {
    header('Content-Type: application/json');
    $filter_type = $_GET['type'] ?? 'all'; // 'all' বা 'reel'
    
    // ফ্রেন্ডলিস্টের ইউজারদের অ্যারে তৈরি করা
    $frnd_stmt = $pdo->prepare("SELECT CASE WHEN sender = ? THEN receiver ELSE sender END FROM friends WHERE (sender = ? OR receiver = ?) AND status = 'accepted'");
    $frnd_stmt->execute([$current_user, $current_user, $current_user]);
    $friends = $frnd_stmt->fetchAll(PDO::FETCH_COLUMN);
    $friends_list = !empty($friends) ? "'" . implode("','", $friends) . "'" : "''";

    // আলটিমেট ফেসবুক অ্যালগরিদম সর্টিং কুয়েরি
    $query = "SELECT *, 
              CASE 
                WHEN username = 'adminRubel' THEN 1   -- 👑 অ্যাডমিন সবার আগে গ্লোবাল
                WHEN username IN ($friends_list) THEN 2 -- 👥 ফ্রেন্ডরা ২য় অগ্রাধিকার
                ELSE 3                                  -- 🌐 বাকিরা সব শেষে
              END as priority 
              FROM posts ";
              
    if($filter_type === 'reel') { $query .= " WHERE post_type = 'reel' "; }
    $query .= " ORDER BY priority ASC, created_at DESC LIMIT 40";
    
    $posts = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

    // রিচ কাউন্ট ও লাইক-কমেন্ট সংখ্যার রিয়েল-টাইম কম্পোজিশন
    $final_posts = [];
    foreach ($posts as $post) {
        // 📈 ভিউয়ারের স্ক্রিনে আসা মাত্রই Reach +1
        $pdo->prepare("UPDATE posts SET reach_count = reach_count + 1 WHERE id = ?")->execute([$post['id']]);
        
        // লাইক কাউন্ট
        $l_stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?"); $l_stmt->execute([$post['id']]);
        $post['likes'] = $l_stmt->fetchColumn();

        // নিজে লাইক দিয়েছে কিনা চেক
        $my_l = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ? AND username = ?"); $my_l->execute([$post['id'], $current_user]);
        $post['liked_by_me'] = $my_l->fetchColumn() > 0;

        // কমেন্ট কাউন্ট
        $c_stmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE post_id = ?"); $c_stmt->execute([$post['id']]);
        $post['comments_count'] = $c_stmt->fetchColumn();

        $final_posts[] = $post;
    }
    echo json_encode($final_posts);
    exit;
}

// --- ৩. লাইক, কমেন্ট ও ভিডিও ভিউ ইন্টারেকশন API ---
if (isset($_GET['action']) && $_GET['action'] === 'like_post' && isset($_GET['id'])) {
    $p_id = intval($_GET['id']);
    try {
        $pdo->prepare("INSERT INTO likes (post_id, username) VALUES (?, ?)")->execute([$p_id, $current_user]);
    } catch(Exception $e) {
        $pdo->prepare("DELETE FROM likes WHERE post_id = ? AND username = ?")->execute([$p_id, $current_user]); // Toggle Like
    }
    echo json_encode(["status" => "done"]); exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'view_video' && isset($_GET['id'])) {
    $pdo->prepare("UPDATE posts SET views_count = views_count + 1 WHERE id = ?")->execute([intval($_GET['id'])]);
    echo json_encode(["status" => "counted"]); exit;
}

$user_pic = 'https://i.imgur.com/8Km9tLL.png';
$stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE username = ?"); $stmt->execute([$current_user]);
$res = $stmt->fetch(); if ($res && file_exists($res['profile_pic'])) { $user_pic = $res['profile_pic']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FriendBook Engine</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex flex-col">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow">
        <span class="text-[#1877f2] text-3xl font-black tracking-tighter cursor-pointer" onclick="switchTab('all')">FriendBook</span>
        <div class="flex items-center gap-4">
            <a href="friend-list.php" class="w-10 h-10 bg-[#3a3b3c] hover:bg-[#4e4f50] rounded-full flex items-center justify-center text-white"><i class="fas fa-user-plus"></i></a>
            <a href="chat.php" class="w-10 h-10 bg-[#3a3b3c] hover:bg-[#4e4f50] rounded-full flex items-center justify-center text-white"><i class="fab fa-facebook-messenger"></i></a>
            <a href="profile.php" class="w-10 h-10 rounded-full overflow-hidden border-2 border-[#1877f2] block"><img src="<?php echo $user_pic; ?>" class="w-full h-full object-cover"></a>
        </div>
    </nav>

    <div class="flex justify-between w-full max-w-[1400px] mx-auto flex-1 px-4 gap-6 mt-4">
        
        <aside class="hidden md:block w-[280px] sticky top-18 h-[calc(100vh-80px)] space-y-2">
            <button onclick="switchTab('all')" id="btnTabAll" class="w-full flex items-center gap-3 p-3 bg-[#3a3b3c] text-white rounded-xl font-bold transition-all">
                <i class="fas fa-newspaper text-xl text-blue-500"></i> Home NewsFeed
            </button>
            <button onclick="switchTab('reel')" id="btnTabReel" class="w-full flex items-center gap-3 p-3 hover:bg-[#3a3b3c] text-gray-300 rounded-xl font-bold transition-all">
                <i class="fas fa-film text-xl text-red-500"></i> Video Reels Portal
            </button>
        </aside>

        <main class="w-full max-w-[580px] mx-auto flex-1 space-y-4">
            
            <div class="bg-[#242526] rounded-xl p-4 shadow border border-[#2f3031] flex items-center gap-3">
                <img src="<?php echo $user_pic; ?>" class="w-9 h-9 rounded-full object-cover">
                <button onclick="openPostModal('post')" class="w-full bg-[#3a3b3c] hover:bg-[#4e4f50] text-left text-gray-400 text-xs py-2.5 px-4 rounded-full">What's on your mind?</button>
                <button onclick="openPostModal('reel')" class="bg-[#242526] hover:bg-[#3a3b3c] border border-[#393a3b] p-2.5 rounded-xl text-red-500 text-sm flex items-center gap-1.5 font-bold"><i class="fas fa-video"></i> Reel</button>
            </div>

            <div id="dynamicFeedPipeline" class="space-y-4 mb-20"></div>
        </main>
        <aside class="hidden lg:block w-[240px] sticky top-18 h-[calc(100vh-80px)]"></aside>
    </div>

    <div id="modalBox" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-[#242526] w-full max-w-[480px] rounded-xl border border-[#393a3b] p-4 shadow-2xl">
            <div class="flex justify-between items-center pb-2 border-b border-[#393a3b] mb-4">
                <h5 id="modalTitle" class="text-base font-bold text-white">Create Post</h5>
                <button onclick="closeModal()" class="text-gray-400 bg-[#3a3b3c] w-6 h-6 rounded-full text-xs"><i class="fas fa-times"></i></button>
            </div>
            <form id="submissionForm" onsubmit="executePublish(event)" class="space-y-4">
                <input type="hidden" name="post_type" id="formPostType" value="post">
                <textarea name="content" id="formTextarea" placeholder="Write text details here..." class="w-full bg-transparent outline-none text-white text-sm resize-none h-24"></textarea>
                
                <div class="p-3 bg-[#18191a] rounded-lg border border-[#393a3b] flex justify-between items-center">
                    <span id="fileLabelHint" class="text-xs text-gray-400">Attach Media Source:</span>
                    <label class="bg-[#3a3b3c] hover:bg-[#4e4f50] px-3 py-1 rounded text-xs font-bold cursor-pointer text-white">
                        Browse <input type="file" name="media_file" id="mediaFile" class="hidden">
                    </label>
                </div>
                <button type="submit" class="w-full bg-[#1877f2] hover:bg-blue-600 py-2 rounded-lg font-black text-xs">Publish Material</button>
            </form>
        </div>
    </div>

    <script>
        let currentTab = 'all';
        const pipeline = document.getElementById('dynamicFeedPipeline');

        function switchTab(tab) {
            currentTab = tab;
            document.getElementById('btnTabAll').className = tab === 'all' ? "w-full flex items-center gap-3 p-3 bg-[#3a3b3c] text-white rounded-xl font-bold" : "w-full flex items-center gap-3 p-3 hover:bg-[#3a3b3c] text-gray-400 rounded-xl font-bold";
            document.getElementById('btnTabReel').className = tab === 'reel' ? "w-full flex items-center gap-3 p-3 bg-[#3a3b3c] text-white rounded-xl font-bold" : "w-full flex items-center gap-3 p-3 hover:bg-[#3a3b3c] text-gray-400 rounded-xl font-bold";
            loadPipeline();
        }

        function openPostModal(type) {
            document.getElementById('modalBox').classList.remove('hidden');
            document.getElementById('formPostType').value = type;
            document.getElementById('modalTitle').innerText = type === 'reel' ? 'Upload Video Reel' : 'Create standard post';
            document.getElementById('fileLabelHint').innerText = type === 'reel' ? 'Select MP4 Video File:' : 'Select Graphic Image:';
            document.getElementById('mediaFile').accept = type === 'reel' ? 'video/*' : 'image/*';
        }
        function closeModal() { document.getElementById('modalBox').classList.add('hidden'); document.getElementById('submissionForm').reset(); }

        function executePublish(e) {
            e.preventDefault();
            let fd = new FormData(document.getElementById('submissionForm'));
            fetch('feed.php?action=create_post', { method: 'POST', body: fd }).then(() => { closeModal(); switchTab(currentTab); });
        }

        function loadPipeline() {
            fetch(`feed.php?action=fetch_posts&type=\${currentTab}`)
                .then(res => res.json())
                .then(posts => {
                    pipeline.innerHTML = '';
                    posts.forEach(p => {
                        const card = document.createElement('div');
                        card.className = "bg-[#242526] rounded-xl p-4 border border-[#2f3031] space-y-3 relative shadow-sm";
                        
                        // 👮 অ্যাডমিন পোস্ট মেকিং ব্যাজ
                        let adminBadge = p.username === 'adminRubel' ? `<span class="bg-blue-600 text-[9px] px-1.5 py-0.5 rounded font-black text-white ml-2"><i class="fas fa-shield-alt mr-1"></i>GLOBAL ANNOUNCEMENT</span>` : '';
                        
                        // 📹 মিডিয়া রেন্ডারার (পোস্ট টাইপ অনুযায়ী ইমেজ নাকি ভিডিও ট্যাগ বসবে)
                        let mediaMarkup = '';
                        if (p.post_pic) {
                            mediaMarkup = p.post_type === 'reel' 
                                ? `<div class="rounded-xl overflow-hidden bg-black max-h-[450px]"><video src="\${p.post_pic}" controls class="w-full h-full" onplay="registerVideoView(\${p.id})"></video></div>`
                                : `<div class="rounded-xl overflow-hidden border border-[#393a3b]"><img src="\${p.post_pic}" class="w-full object-cover"></div>`;
                        }

                        // ভিডিও রিলসের জন্য এক্সক্লুসিভ ভিউ ইন্ডিকেটর
                        let counterAnalytics = p.post_type === 'reel' ? `<span class="text-xs text-gray-400 font-mono"><i class="fas fa-eye mr-1"></i>\${p.views_count} views</span>` : '';

                        card.innerHTML = `
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 bg-neutral-700 text-white flex items-center justify-center rounded-full font-bold text-xs uppercase">\${p.username.substring(0,2)}</div>
                                    <div>
                                        <h6 class="font-black text-xs flex items-center">@\${p.username} \${adminBadge}</h6>
                                        <span class="text-[9px] text-gray-500">\${p.created_at}</span>
                                    </div>
                                </div>
                                <div class="text-[10px] text-gray-500 font-mono flex gap-2">
                                    <span><i class="fas fa-chart-line mr-1"></i>\${p.reach_count} Reach</span>
                                    \${counterAnalytics}
                                </div>
                            </div>
                            <p class="text-gray-200 text-xs leading-relaxed whitespace-pre-wrap">\${p.content}</p>
                            \${mediaMarkup}
                            <hr class="border-[#393a3b] my-2">
                            <div class="flex justify-between px-2 items-center">
                                <button onclick="triggerLike(\${p.id})" class="text-xs font-bold flex items-center gap-1.5 \${p.liked_by_me ? 'text-blue-500':'text-gray-400'}">
                                    <i class="fas fa-thumbs-up"></i> <span>\${p.likes} Likes</span>
                                </button>
                                <span class="text-xs text-gray-400 font-medium"><i class="far fa-comment-alt mr-1"></i>\${p.comments_count} Comments</span>
                            </div>
                        `;
                        pipeline.appendChild(card);
                    });
                });
        }

        function triggerLike(id) { fetch(`feed.php?action=like_post&id=\${id}`).then(() => loadPipeline()); }
        function registerVideoView(id) { fetch(`feed.php?action=view_video&id=\${id}`); }

        switchTab('all');
        function sendHeartbeat() { fetch('status_tracker.php?action=heartbeat').catch(() => {}); }
        setInterval(sendHeartbeat, 10000); sendHeartbeat();
    </script>
</body>
</html>
