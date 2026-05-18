<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();

if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }
$current_user = $_SESSION['username'];

// 💥 [DYNAMIC TABLE INFRASTRUCTURE JETPACK] - অটোমেটিক টেবিল তৈরি লজিক
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS likes (id SERIAL PRIMARY KEY, post_id INT, username VARCHAR(50), UNIQUE(post_id, username));");
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (id SERIAL PRIMARY KEY, post_id INT, username VARCHAR(50), content TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");
    $pdo->exec("CREATE TABLE IF NOT EXISTS friends (id SERIAL PRIMARY KEY, sender VARCHAR(50), receiver VARCHAR(50), status VARCHAR(20) DEFAULT 'pending', UNIQUE(sender, receiver));");
    $pdo->exec("CREATE TABLE IF NOT EXISTS stories (id SERIAL PRIMARY KEY, username VARCHAR(50), media_path VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP);");
} catch (Exception $e) {}

// --- ১. পোস্ট, রিল এবং স্টোরি ক্রিয়েশন ইঞ্জিন ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_post') {
    header('Content-Type: application/json');
    $content = htmlspecialchars($_POST['content'] ?? '');
    $post_type = $_POST['post_type'] ?? 'post'; // post, reel, or story
    $upload_path = null;

    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        $allowed = ($post_type === 'reel') ? ['mp4', 'mov'] : ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $target_dir = "src/api/endpoint/uploads/" . $current_user . "/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            $upload_path = $target_dir . $post_type . "_" . time() . "_" . uniqid() . "." . $ext;
            move_uploaded_file($_FILES['media_file']['tmp_name'], $upload_path);
        }
    }

    if ($post_type === 'story' && $upload_path !== null) {
        $stmt = $pdo->prepare("INSERT INTO stories (username, media_path) VALUES (?, ?)");
        $stmt->execute([$current_user, $upload_path]);
        echo json_encode(["status" => "success"]);
    } elseif (!empty($content) || $upload_path !== null) {
        $stmt = $pdo->prepare("INSERT INTO posts (username, content, post_pic, post_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$current_user, $content, $upload_path, $post_type]);
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "empty"]);
    }
    exit;
}

// --- ২. রিয়েল-টাইম ফেসবুক লাইক ও কমেন্ট API ---
if (isset($_GET['action']) && $_GET['action'] === 'like_post' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $p_id = intval($_GET['id']);
    try {
        $stmt = $pdo->prepare("INSERT INTO likes (post_id, username) VALUES (?, ?)");
        $stmt->execute([$p_id, $current_user]);
        echo json_encode(["status" => "liked"]);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("DELETE FROM likes WHERE post_id = ? AND username = ?");
        $stmt->execute([$p_id, $current_user]);
        echo json_encode(["status" => "unliked"]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'add_comment') {
    header('Content-Type: application/json');
    $p_id = intval($_POST['post_id']);
    $cmt = htmlspecialchars(trim($_POST['comment_text']));
    if (!empty($cmt)) {
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, username, content) VALUES (?, ?, ?)");
        $stmt->execute([$p_id, $current_user, $cmt]);
        echo json_encode(["status" => "success"]);
    }
    exit;
}

// --- ৩. নিউজফিড গ্লোবাল ফেচ ম্যানেজার (পোস্ট + লাইক + কমেন্ট ডাটা জয়েন) ---
if (isset($_GET['action']) && $_GET['action'] === 'fetch_posts') {
    header('Content-Type: application/json');
    $filter_type = $_GET['type'] ?? 'all'; 

    $query = "SELECT p.*, COALESCE(u.is_verified, 0) as is_verified, u.profile_pic as u_pic 
              FROM posts p
              LEFT JOIN users u ON p.username = u.username ";
    if($filter_type === 'reel') { $query .= " WHERE p.post_type = 'reel' "; }
    $query .= " ORDER BY p.created_at DESC LIMIT 40";
    
    $stmt = $pdo->query($query);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $final_feed = [];
    foreach($posts as $post) {
        // লাইক কাউন্ট ও আপনি নিজে লাইক দিয়েছেন কিনা চেক
        $l_stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?"); $l_stmt->execute([$post['id']]);
        $post['likes_count'] = $l_stmt->fetchColumn();

        $my_l = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ? AND username = ?"); $my_l->execute([$post['id'], $current_user]);
        $post['liked_by_me'] = $my_l->fetchColumn() > 0;

        // কমেন্টস তুলে আনা
        $c_stmt = $pdo->prepare("SELECT c.*, u.profile_pic FROM comments c LEFT JOIN users u ON c.username = u.username WHERE c.post_id = ? ORDER BY c.created_at ASC");
        $c_stmt->execute([$post['id']]);
        $post['comments'] = $c_stmt->fetchAll(PDO::FETCH_ASSOC);

        $final_feed[] = $post;
    }
    echo json_encode($final_feed);
    exit;
}

// --- ৪. ২৪ ঘণ্টার ফেসবুক স্টোরি ফেচ কুয়েরি ---
if (isset($_GET['action']) && $_GET['action'] === 'fetch_stories') {
    header('Content-Type: application/json');
    // ২৪ ঘণ্টার ভেতরের একটিভ স্টোরি ফিল্টার
    $stmt = $pdo->query("SELECT s.*, u.profile_pic FROM stories s LEFT JOIN users u ON s.username = u.username WHERE s.created_at >= NOW() - INTERVAL '1 DAY' ORDER BY s.id DESC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// কারেন্ট ইউজারের প্রোফাইল পিকচার ডিটেক্টর
$user_pic = 'https://i.imgur.com/8Km9tLL.png';
$stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE username = ?"); $stmt->execute([$current_user]);
$res = $stmt->fetch(); if ($res && !empty($res['profile_pic']) && file_exists($res['profile_pic'])) { $user_pic = $res['profile_pic']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FriendBook - Facebook Elite Engine</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex flex-col font-sans antialiased">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <div class="flex items-center gap-2">
            <span class="text-[#1877f2] text-3xl font-black tracking-tighter cursor-pointer" onclick="switchTab('all')">FriendBook</span>
        </div>
        
        <div class="flex h-full items-center justify-center gap-10">
            <button onclick="switchTab('all')" id="tabHome" class="h-full border-b-4 border-[#1877f2] text-[#1877f2] px-8 text-xl transition-all"><i class="fas fa-home"></i></button>
            <button onclick="switchTab('reel')" id="tabReels" class="h-full border-b-4 border-transparent text-gray-400 hover:text-white px-8 text-xl transition-all"><i class="fas fa-film"></i></button>
        </div>

        <div class="flex items-center gap-3">
            <?php if($current_user === 'adminRubel'): ?>
                <a href="dbms-system-management.php" class="text-xs bg-red-600 font-bold px-3 py-1.5 rounded-lg text-white"><i class="fas fa-user-shield"></i> Admin Panel</a>
            <?php endif; ?>
            <a href="profile.php" class="w-9 h-9 rounded-full overflow-hidden border border-gray-600 block"><img src="<?php echo $user_pic; ?>" class="w-full h-full object-cover"></a>
            <a href="index.php?action=logout" class="bg-[#3a3b3c] hover:bg-red-600 text-gray-200 hover:text-white w-9 h-9 flex items-center justify-center rounded-full text-sm transition-all"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </nav>

    <div class="flex justify-center w-full max-w-[1250px] mx-auto flex-1 px-4 gap-8 mt-4">
        
        <main class="w-full max-w-[580px] space-y-4">
            
            <div class="flex gap-2 overflow-x-auto pb-2 no-scrollbar" id="storiesTray">
                <div onclick="openPostModal('story')" class="w-28 h-44 bg-[#242526] rounded-xl overflow-hidden relative border border-[#2f3031] flex flex-col justify-between cursor-pointer group shrink-0 shadow-sm">
                    <div class="h-32 bg-gray-800 overflow-hidden"><img src="<?php echo $user_pic; ?>" class="w-full h-full object-cover group-hover:scale-105 transition-all"></div>
                    <div class="bg-[#242526] h-12 relative text-center flex flex-col items-center justify-center pt-2">
                        <div class="w-8 h-8 bg-[#1877f2] rounded-full absolute -top-4 border-4 border-[#242526] flex items-center justify-center text-white text-xs"><i class="fas fa-plus"></i></div>
                        <span class="text-[10px] font-black text-gray-300">Create Story</span>
                    </div>
                </div>
                <div id="dynamicStoriesPipeline" class="flex gap-2"></div>
            </div>

            <div class="bg-[#242526] rounded-xl p-4 shadow-md border border-[#2f3031] space-y-3">
                <div class="flex items-center gap-3">
                    <img src="<?php echo $user_pic; ?>" class="w-10 h-10 rounded-full object-cover">
                    <button onclick="openPostModal('post')" class="w-full bg-[#3a3b3c] hover:bg-[#4e4f50] text-left text-gray-400 text-sm py-2.5 px-4 rounded-full">What's on your mind, <?php echo $current_user; ?>?</button>
                </div>
                <div class="flex justify-between border-t border-[#393a3b] pt-2">
                    <button onclick="openPostModal('post')" class="flex items-center justify-center gap-2 text-[#45bd62] hover:bg-[#3a3b3c] w-full py-2 rounded-lg text-sm font-bold"><i class="fas fa-images text-lg"></i> Photo/Video</button>
                    <button onclick="openPostModal('reel')" class="flex items-center justify-center gap-2 text-[#f3425f] hover:bg-[#3a3b3c] w-full py-2 rounded-lg text-sm font-bold"><i class="fas fa-video text-lg"></i> Reel Portal</button>
                </div>
            </div>

            <div id="dynamicFeedPipeline" class="space-y-4 mb-24"></div>
        </main>
    </div>

    <div id="modalBox" class="hidden fixed inset-0 bg-black/70 backdrop-blur-xs z-50 flex items-center justify-center p-4">
        <div class="bg-[#242526] w-full max-w-[500px] rounded-xl border border-[#393a3b] p-4 relative shadow-2xl">
            <div class="flex justify-between items-center pb-2 border-b border-[#393a3b] mb-4">
                <h5 id="modalTitle" class="text-lg font-bold text-white">Create Post</h5>
                <button onclick="closeModal()" class="text-gray-400 hover:text-white bg-[#3a3b3c] w-8 h-8 rounded-full"><i class="fas fa-times"></i></button>
            </div>
            <form id="submissionForm" onsubmit="executePublish(event)" class="space-y-4">
                <input type="hidden" name="post_type" id="formPostType" value="post">
                <textarea name="content" id="formTextarea" placeholder="What's on your mind?" class="w-full bg-transparent outline-none text-white text-sm resize-none h-24 focus:ring-0"></textarea>
                <div class="p-3 bg-[#18191a] rounded-lg border border-[#393a3b] flex justify-between items-center">
                    <span id="uploadHintLabel" class="text-xs text-gray-400">Add asset to your post:</span>
                    <label class="bg-[#3a3b3c] hover:bg-[#4e4f50] px-4 py-1.5 rounded text-xs font-bold cursor-pointer text-white">
                        Browse <input type="file" name="media_file" id="mediaFile" class="hidden">
                    </label>
                </div>
                <button type="submit" class="w-full bg-[#1877f2] hover:bg-blue-600 py-2.5 rounded-lg font-bold text-sm text-white shadow-md transition-all">Publish</button>
            </form>
        </div>
    </div>

    <script>
        let currentTab = 'all';
        const pipeline = document.getElementById('dynamicFeedPipeline');

        function switchTab(tab) {
            currentTab = tab;
            document.getElementById('tabHome').className = tab === 'all' ? 'h-full border-b-4 border-[#1877f2] text-[#1877f2] px-8 text-xl' : 'h-full border-b-4 border-transparent text-gray-400 hover:text-white px-8 text-xl';
            document.getElementById('tabReels').className = tab === 'reel' ? 'h-full border-b-4 border-[#1877f2] text-[#1877f2] px-8 text-xl' : 'h-full border-b-4 border-transparent text-gray-400 hover:text-white px-8 text-xl';
            loadPipeline();
        }

        function openPostModal(type) {
            document.getElementById('modalBox').classList.remove('hidden');
            document.getElementById('formPostType').value = type;
            if(type === 'story') {
                document.getElementById('modalTitle').innerText = 'Add Photo Story';
                document.getElementById('formTextarea').classList.add('hidden');
                document.getElementById('uploadHintLabel').innerText = 'Select a photo for your 24h story:';
            } else {
                document.getElementById('modalTitle').innerText = type === 'reel' ? 'Create Reel' : 'Create Post';
                document.getElementById('formTextarea').classList.remove('hidden');
                document.getElementById('uploadHintLabel').innerText = 'Add asset to your post:';
            }
        }
        function closeModal() { document.getElementById('modalBox').classList.add('hidden'); document.getElementById('submissionForm').reset(); }

        function executePublish(e) {
            e.preventDefault();
            let fd = new FormData(document.getElementById('submissionForm'));
            fetch('feed.php?action=create_post', { method: 'POST', body: fd })
            .then(() => { closeModal(); loadPipeline(); loadStories(); });
        }

        function triggerLike(id, btn) {
            fetch(`feed.php?action=like_post&id=${id}`)
            .then(res => res.json())
            .then(data => {
                const countSpan = document.getElementById(`like-count-${id}`);
                let count = parseInt(countSpan.innerText);
                if(data.status === 'liked') {
                    btn.className = "flex items-center justify-center gap-2 text-[#1877f2] w-full py-1.5 rounded-lg hover:bg-[#3a3b3c] font-bold transition-all";
                    countSpan.innerText = count + 1;
                } else {
                    btn.className = "flex items-center justify-center gap-2 text-gray-400 w-full py-1.5 rounded-lg hover:bg-[#3a3b3c] font-bold transition-all";
                    countSpan.innerText = count - 1;
                }
            });
        }

        function submitComment(e, id) {
            e.preventDefault();
            let fd = new FormData(e.target);
            fetch('feed.php?action=add_comment', { method: 'POST', body: fd })
            .then(() => { loadPipeline(); });
        }

        function loadStories() {
            fetch('feed.php?action=fetch_stories')
            .then(res => res.json())
            .then(stories => {
                const tray = document.getElementById('dynamicStoriesPipeline');
                tray.innerHTML = '';
                stories.forEach(s => {
                    const scard = document.createElement('div');
                    scard.className = "w-28 h-44 rounded-xl overflow-hidden relative border border-[#2f3031] flex-shrink-0 cursor-pointer shadow-md";
                    scard.innerHTML = `
                        <img src="${s.media_path}" class="w-full h-full object-cover hover:scale-105 transition-all">
                        <div class="absolute top-2 left-2 w-7 h-7 rounded-full overflow-hidden border-2 border-[#1877f2]"><img src="${s.profile_pic || 'https://i.imgur.com/8Km9tLL.png'}" class="w-full h-full object-cover"></div>
                        <span class="absolute bottom-2 left-2 text-[10px] font-bold text-white drop-shadow-md">@${s.username}</span>
                    `;
                    tray.appendChild(scard);
                });
            });
        }

        function loadPipeline() {
            fetch(`feed.php?action=fetch_posts&type=${currentTab}`)
                .then(res => res.json())
                .then(posts => {
                    pipeline.innerHTML = '';
                    posts.forEach(p => {
                        const card = document.createElement('div');
                        card.className = "bg-[#242526] rounded-xl border border-[#2f3031] p-4 space-y-3 shadow-md";
                        
                        let badge = '';
                        if (p.username === 'adminRubel') {
                            badge = `<span class="bg-red-500/20 text-red-400 border border-red-500/30 text-[9px] px-1.5 py-0.5 rounded font-black ml-1.5"><i class="fas fa-crown"></i> ADMIN</span>`;
                        } else if (p.is_verified == 1) {
                            badge = `<span class="bg-[#1877f2] text-white text-[9px] w-4 h-4 inline-flex items-center justify-center rounded-full ml-1.5 font-bold"><i class="fas fa-check text-[7px]"></i></span>`;
                        }
                        
                        let media = '';
                        if (p.post_pic && p.post_pic.trim() !== '') {
                            media = p.post_type === 'reel' 
                                ? `<div class="rounded-xl overflow-hidden bg-black max-h-[460px]"><video src="${p.post_pic}" controls class="w-full h-full"></video></div>`
                                : `<div class="rounded-xl overflow-hidden border border-[#393a3b]"><img src="${p.post_pic}" class="w-full object-cover"></div>`;
                        }

                        let commentLoop = '';
                        p.comments.forEach(c => {
                            commentLoop += `
                                <div class="flex gap-2 items-start text-xs bg-[#18191a] p-2 rounded-xl border border-[#2f3031]">
                                    <img src="${c.profile_pic || 'https://i.imgur.com/8Km9tLL.png'}" class="w-6 h-6 rounded-full object-cover">
                                    <div>
                                        <span class="font-bold text-white">@${c.username}</span>
                                        <p class="text-gray-300 mt-0.5">${c.content}</p>
                                    </div>
                                </div>
                            `;
                        });

                        card.innerHTML = `
                            <div class="flex items-center gap-2">
                                <img src="${p.u_pic || 'https://i.imgur.com/8Km9tLL.png'}" class="w-9 h-9 rounded-full object-cover">
                                <div>
                                    <h6 class="font-bold text-sm text-white flex items-center">@${p.username} ${badge}</h6>
                                    <span class="text-[10px] text-gray-500">${p.created_at}</span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-200 leading-snug">${p.content}</p>
                            ${media}
                            <div class="flex justify-between text-xs text-gray-400 border-b border-[#393a3b] pb-2 px-1">
                                <span class="flex items-center gap-1 text-[#1877f2] font-medium"><i class="fas fa-thumbs-up"></i> <b id="like-count-${p.id}">${p.likes_count}</b> people liked</span>
                                <span>${p.comments.length} comments</span>
                            </div>
                            <div class="flex justify-between border-b border-[#393a3b] py-0.5">
                                <button onclick="triggerLike(${p.id}, this)" class="${p.liked_by_me ? 'text-[#1877f2]' : 'text-gray-400'} flex items-center justify-center gap-2 w-full py-1.5 rounded-lg hover:bg-[#3a3b3c] font-bold text-xs"><i class="fas fa-thumbs-up"></i> Like</button>
                                <button class="text-gray-400 flex items-center justify-center gap-2 w-full py-1.5 rounded-lg hover:bg-[#3a3b3c] font-bold text-xs"><i class="fas fa-comment"></i> Comment</button>
                            </div>
                            <div class="space-y-2 max-h-40 overflow-y-auto pt-1">${commentLoop}</div>
                            <form onsubmit="submitComment(event, ${p.id})" class="flex gap-2 items-center pt-2">
                                <input type="hidden" name="post_id" value="${p.id}">
                                <input type="text" name="comment_text" required placeholder="Write a comment..." class="w-full bg-[#3a3b3c] text-xs px-3 py-2 outline-none rounded-full border border-transparent focus:border-[#1877f2] text-white">
                                <button type="submit" class="text-[#1877f2] text-sm px-2"><i class="fas fa-paper-plane"></i></button>
                            </form>
                        `;
                        pipeline.appendChild(card);
                    });
                });
        }
        loadPipeline();
        loadStories();
    </script>
</body>
</html>
