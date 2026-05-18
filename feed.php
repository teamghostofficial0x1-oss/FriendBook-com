<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();
require_once 'status_tracker.php';

if (!isset($_SESSION['username']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    $stmt = $pdo->prepare("SELECT username FROM users WHERE remember_token = ? AND remember_expires > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if ($user) { $_SESSION['username'] = $user['username']; }
}

if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}
$current_user = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_post') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    if (!empty($input['content'])) {
        $content = htmlspecialchars($input['content']);
        try {
            $stmt = $pdo->prepare("INSERT INTO posts (username, content) VALUES (?, ?)");
            $stmt->execute([$current_user, $content]);
            echo json_encode(["status" => "success"]);
        } catch (PDOException $e) { echo json_encode(["status" => "error"]); }
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'fetch_posts') {
    header('Content-Type: application/json');
    try {
        $posts = $pdo->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 30")->fetchAll();
        echo json_encode($posts);
    } catch (PDOException $e) { echo json_encode([]); }
    exit;
}

$user_pic = 'https://i.imgur.com/8Km9tLL.png';
try {
    $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE username = ?");
    $stmt->execute([$current_user]);
    $res = $stmt->fetch();
    if ($res && file_exists($res['profile_pic'])) { $user_pic = $res['profile_pic']; }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FriendBook - NewsFeed</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex flex-col">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <span class="text-[#1877f2] text-3xl font-black tracking-tighter cursor-pointer" onclick="location.href='feed.php'">FriendBook</span>
        <div class="flex items-center gap-4">
            <a href="friend-list.php" class="w-10 h-10 bg-[#3a3b3c] hover:bg-[#4e4f50] rounded-full flex items-center justify-center text-white" title="Find Friends"><i class="fas fa-user-plus"></i></a>
            <a href="chat.php" class="w-10 h-10 bg-[#3a3b3c] hover:bg-[#4e4f50] rounded-full flex items-center justify-center text-white" title="Messenger"><i class="fab fa-facebook-messenger"></i></a>
            <a href="profile.php" class="w-10 h-10 rounded-full overflow-hidden border-2 border-[#1877f2] block"><img src="<?php echo $user_pic; ?>" class="w-full h-full object-cover"></a>
            <a href="index.php?action=logout" class="text-xs text-red-400 hover:underline font-bold">Logout</a>
        </div>
    </nav>

    <div class="flex justify-between w-full max-w-[1400px] mx-auto flex-1 px-4 gap-6 mt-4">
        <aside class="hidden xl:block w-[300px] sticky top-18 h-[calc(100vh-80px)] space-y-1">
            <a href="profile.php" class="flex items-center gap-3 p-2.5 hover:bg-[#3a3b3c] rounded-xl text-white font-semibold">
                <img src="<?php echo $user_pic; ?>" class="w-9 h-9 rounded-full object-cover">
                <span>@<?php echo $current_user; ?></span>
            </a>
            <a href="friend-list.php" class="flex items-center gap-3 p-2.5 bg-[#1877f2]/10 text-[#1877f2] rounded-xl font-bold border border-[#1877f2]/20">
                <i class="fas fa-user-friends text-xl w-8 text-center"></i> <span>Find Friends & Network</span>
            </a>
        </aside>

        <main class="w-full max-w-[620px] mx-auto flex-1">
            <div class="bg-[#242526] rounded-xl p-4 shadow-md mb-5 border border-[#2f3031]">
                <div class="flex items-center gap-3 pb-3">
                    <img src="<?php echo $user_pic; ?>" class="w-10 h-10 rounded-full object-cover">
                    <input type="text" id="postInput" placeholder="What's on your mind, <?php echo $current_user; ?>?" class="w-full bg-[#3a3b3c] hover:bg-[#4e4f50] rounded-full py-2.5 px-4 outline-none text-white text-sm cursor-pointer">
                </div>
            </div>
            <div id="newsFeed" class="space-y-4 mb-10"></div>
        </main>
        <aside class="hidden lg:block w-[280px] sticky top-18 h-[calc(100vh-80px)]"></aside>
    </div>

    <div id="postModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-[#242526] w-full max-w-[500px] rounded-xl border border-[#393a3b] p-4 shadow-2xl">
            <div class="flex justify-between items-center pb-2 border-b border-[#393a3b] mb-4">
                <h5 class="text-lg font-bold">Create Post</h5>
                <button onclick="closeModal()" class="text-gray-400 hover:text-white bg-[#3a3b3c] w-7 h-7 rounded-full"><i class="fas fa-times"></i></button>
            </div>
            <textarea id="modalTextarea" rows="4" placeholder="What's on your mind?" class="w-full bg-transparent outline-none text-white text-md resize-none"></textarea>
            <button onclick="submitPost()" class="w-full bg-[#1877f2] py-2 rounded-lg font-bold mt-4">Post to Feed</button>
        </div>
    </div>

    <script>
        const newsFeed = document.getElementById('newsFeed');
        const postModal = document.getElementById('postModal');
        const modalTextarea = document.getElementById('modalTextarea');
        document.getElementById('postInput').onclick = () => { postModal.classList.remove('hidden'); modalTextarea.focus(); };
        function closeModal() { postModal.classList.add('hidden'); modalTextarea.value = ''; }

        function submitPost() {
            if (modalTextarea.value.trim() === "") return;
            fetch('feed.php?action=create_post', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: modalTextarea.value })
            }).then(() => { closeModal(); loadFeed(); });
        }

        function loadFeed() {
            fetch('feed.php?action=fetch_posts')
                .then(res => res.json())
                .then(posts => {
                    newsFeed.innerHTML = '';
                    posts.forEach(post => {
                        const card = document.createElement('div');
                        card.className = "bg-[#242526] rounded-xl p-4 border border-[#2f3031]";
                        card.innerHTML = `
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-9 h-9 bg-[#1877f2] rounded-full flex items-center justify-center font-bold text-xs uppercase">\${post.username.substring(0,2)}</div>
                                <div><h6 class="font-bold text-sm">@\${post.username}</h6><span class="text-[10px] text-gray-400">\${post.created_at}</span></div>
                            </div>
                            <p class="text-gray-200 text-sm whitespace-pre-wrap leading-relaxed">\${post.content}</p>
                        `;
                        newsFeed.appendChild(card);
                    });
                });
        }
        setInterval(loadFeed, 5000); loadFeed();

        function sendHeartbeat() { fetch('status_tracker.php?action=heartbeat').catch(() => {}); }
        setInterval(sendHeartbeat, 10000); sendHeartbeat();
    </script>
</body>
</html>
