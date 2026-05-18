<?php
// ১. ডাটাবেজ কনফিগারেশন ইনক্লুড করা
require_once 'src/api/endpoint/db/db_config.php';

// ২. সিকিউরিটি এবং WAF প্রটেকশন হেডার্স (XSS, Clickjacking, MIME-Sniffing রোধ করতে)
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ৩. AJAX নতুন পোস্ট সাবমিশন হ্যান্ডলার
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_post') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!empty($input['content'])) {
        $content = htmlspecialchars($input['content']);
        try {
            $stmt = $pdo->prepare("INSERT INTO posts (content) VALUES (?)");
            $stmt->execute([$content]);
            echo json_encode(["status" => "success", "message" => "Post created!"]);
        } catch (PDOException $e) {
            echo json_encode(["status" => "error", "message" => $e->getMessage()]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Empty content"]);
    }
    exit;
}

// ৪. AJAX লাইভ ফিড রিডার এন্ডপয়েন্ট
if (isset($_GET['action']) && $_GET['action'] === 'fetch_posts') {
    header('Content-Type: application/json');
    try {
        $posts = $pdo->query("SELECT * FROM posts ORDER BY created_at DESC LIMIT 30")->fetchAll();
        echo json_encode($posts);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
    exit;
}

// ডাটাবেজ থেকে ইউজারের প্রোফাইল পিকচার পাথের স্ট্যাটাস আনা
$user_pic = 'https://i.imgur.com/8Km9tLL.png'; // ডিফল্ট
try {
    $stmt = $pdo->prepare("SELECT profile_pic FROM users WHERE username = 'CyberNinja'");
    $stmt->execute();
    $res = $stmt->fetch();
    if ($res && file_exists($res['profile_pic'])) {
        $user_pic = $res['profile_pic'];
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FriendBook - High FPS Dashboard</title>
    
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https: 'unsafe-inline' 'unsafe-eval'; img-src 'self' data: https:;">

    <style>
        body { background-color: #18191a; color: #e4e6eb; font-family: Segoe UI, Helvetica, Arial, sans-serif; overflow-x: hidden; }
        .smooth-transition { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); will-change: transform, opacity; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #18191a; }
        ::-webkit-scrollbar-thumb { background: #3a3b3c; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#18191a] min-h-screen flex flex-col">

    <nav class="sticky top-0 z-50 bg-[#242526] border-b border-[#393a3b] h-14 flex items-center justify-between px-4 shadow-md">
        <div class="flex items-center gap-2">
            <span class="text-[#1877f2] text-3xl font-black tracking-tighter cursor-pointer" onclick="location.href='index.php'">FriendBook</span>
            <div class="hidden lg:flex items-center bg-[#3a3b3c] rounded-full px-3 py-2 text-gray-400 gap-2 w-60">
                <i class="fas fa-search text-sm"></i>
                <input type="text" placeholder="Search FriendBook" class="bg-transparent border-none outline-none text-sm w-full text-white">
            </div>
        </div>
        
        <div class="hidden md:flex items-center gap-2 text-2xl text-gray-400 h-full">
            <a href="index.php" class="text-[#1877f2] border-b-4 border-[#1877f2] h-full flex items-center px-8"><i class="fas fa-home"></i></a>
            <div class="hover:text-gray-200 h-full flex items-center px-8 cursor-pointer opacity-50"><i class="fas fa-tv"></i></div>
            <div class="hover:text-gray-200 h-full flex items-center px-8 cursor-pointer opacity-50"><i class="fas fa-store"></i></div>
            <div class="hover:text-gray-200 h-full flex items-center px-8 cursor-pointer opacity-50"><i class="fas fa-users"></i></div>
        </div>

        <div class="flex items-center gap-2">
            <a href="chat.php" class="w-10 h-10 bg-[#3a3b3c] hover:bg-[#4e4f50] rounded-full flex items-center justify-center cursor-pointer smooth-transition text-white text-lg" title="Messenger">
                <i class="fab fa-facebook-messenger"></i>
            </a>
            <div class="w-10 h-10 bg-[#3a3b3c] hover:bg-[#4e4f50] rounded-full flex items-center justify-center cursor-pointer smooth-transition text-white text-lg" title="Notifications">
                <i class="fas fa-bell"></i>
            </div>
            <a href="profile.php" class="w-10 h-10 rounded-full overflow-hidden border-2 border-[#1877f2] block smooth-transition hover:scale-105" title="View Profile">
                <img src="<?php echo $user_pic; ?>" class="w-full h-full object-cover">
            </a>
        </div>
    </nav>

    <div class="flex justify-between w-full max-w-[1400px] mx-auto flex-1 px-4 gap-6 mt-4">
        
        <aside class="hidden xl:block w-[300px] sticky top-18 h-[calc(100vh-80px)] overflow-y-auto space-y-1 pr-2">
            <a href="profile.php" class="flex items-center gap-3 p-2.5 hover:bg-[#3a3b3c] rounded-xl smooth-transition text-white font-semibold">
                <img src="<?php echo $user_pic; ?>" class="w-9 h-9 rounded-full object-cover">
                <span>CyberNinja (Profile)</span>
            </a>
            <div class="flex items-center gap-3 p-2.5 hover:bg-[#3a3b3c] rounded-xl smooth-transition text-white cursor-pointer"><i class="fas fa-user-friends text-xl text-cyan-400 w-8 text-center"></i> <span>Friends</span></div>
            <div class="flex items-center gap-3 p-2.5 hover:bg-[#3a3b3c] rounded-xl smooth-transition text-white cursor-pointer"><i class="fas fa-users-cog text-xl text-[#1877f2] w-8 text-center"></i> <span>Groups</span></div>
            <div class="flex items-center gap-3 p-2.5 hover:bg-[#3a3b3c] rounded-xl smooth-transition text-white cursor-pointer"><i class="fas fa-store text-xl text-emerald-400 w-8 text-center"></i> <span>Marketplace</span></div>
            <div class="flex items-center gap-3 p-2.5 hover:bg-[#3a3b3c] rounded-xl smooth-transition text-white cursor-pointer"><i class="fas fa-history text-xl text-orange-400 w-8 text-center"></i> <span>Memories</span></div>
            <div class="flex items-center gap-3 p-2.5 hover:bg-[#3a3b3c] rounded-xl smooth-transition text-white cursor-pointer"><i class="fas fa-bookmark text-xl text-purple-400 w-8 text-center"></i> <span>Saved Posts</span></div>
            <div class="flex items-center gap-3 p-2.5 hover:bg-[#3a3b3c] rounded-xl smooth-transition text-white cursor-pointer"><i class="fas fa-flag text-xl text-red-400 w-8 text-center"></i> <span>Pages</span></div>
            <div class="flex items-center gap-3 p-2.5 hover:bg-[#3a3b3c] rounded-xl smooth-transition text-white cursor-pointer"><i class="fas fa-calendar-alt text-xl text-[#f3425f] w-8 text-center"></i> <span>Events</span></div>
            <hr class="border-[#393a3b] my-2">
        </aside>

        <main class="w-full max-w-[620px] mx-auto flex-1">
            
            <div class="bg-[#242526] rounded-xl p-4 shadow-md mb-5 border border-[#2f3031]">
                <div class="flex items-center gap-3 pb-3 border-b border-[#393a3b]">
                    <img src="<?php echo $user_pic; ?>" class="w-10 h-10 rounded-full object-cover">
                    <input type="text" id="postInput" placeholder="What's on your mind, CyberNinja?" 
                           class="w-full bg-[#3a3b3c] hover:bg-[#4e4f50] rounded-full py-2.5 px-4 outline-none text-white placeholder-gray-400 cursor-pointer smooth-transition text-sm">
                </div>
                <div class="flex justify-between pt-3 text-gray-400 text-sm font-semibold">
                    <div class="flex items-center gap-2 hover:bg-[#3a3b3c] p-2 rounded-lg cursor-pointer w-full justify-center smooth-transition text-[#f3425f]"><i class="fas fa-video"></i> Live Video</div>
                    <div onclick="openModal()" class="flex items-center gap-2 hover:bg-[#3a3b3c] p-2 rounded-lg cursor-pointer w-full justify-center smooth-transition text-[#45bd62]"><i class="fas fa-images"></i> Photo/video</div>
                    <div class="flex items-center gap-2 hover:bg-[#3a3b3c] p-2 rounded-lg cursor-pointer w-full justify-center smooth-transition text-[#f7b928]"><i class="fas fa-smile"></i> Feeling/activity</div>
                </div>
            </div>

            <div id="newsFeed" class="space-y-4 mb-10"></div>
        </main>

        <aside class="hidden lg:block w-[280px] sticky top-18 h-[calc(100vh-80px)] overflow-y-auto space-y-4">
            <div>
                <h5 class="text-gray-400 font-bold text-sm mb-2 tracking-wider uppercase">Sponsored</h5>
                <div class="flex items-center gap-3 p-2 hover:bg-[#3a3b3c] rounded-xl cursor-pointer smooth-transition">
                    <div class="w-20 h-14 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center font-bold text-xs">WAF Node</div>
                    <div>
                        <h6 class="text-sm font-semibold">Secure Cloud Server</h6>
                        <span class="text-xs text-gray-400">render.com</span>
                    </div>
                </div>
            </div>
            <hr class="border-[#393a3b]">
            <div>
                <div class="flex justify-between text-gray-400 font-bold text-sm mb-2 tracking-wider uppercase">
                    <span>Contacts</span>
                    <div class="flex gap-3"><i class="fas fa-video cursor-pointer"></i><i class="fas fa-search cursor-pointer"></i></div>
                </div>
                <a href="chat.php" class="flex items-center gap-3 p-2 hover:bg-[#3a3b3c] rounded-xl smooth-transition text-white">
                    <div class="relative w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center font-bold text-xs">AI</div>
                    <span class="text-sm">Global Chat Matrix</span>
                </a>
            </div>
        </aside>

    </div>

    <div id="postModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-[#242526] w-full max-w-[500px] rounded-xl border border-[#393a3b] shadow-2xl overflow-hidden scale-95 transition-transform duration-200">
            <div class="flex items-center justify-between p-4 border-b border-[#393a3b]">
                <h5 class="text-xl font-bold text-center w-full ml-6">Create post</h5>
                <button onclick="closeModal()" class="text-gray-400 hover:text-white bg-[#3a3b3c] w-8 h-8 rounded-full flex items-center justify-center smooth-transition"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4">
                <div class="flex items-center gap-3 mb-4">
                    <img src="<?php echo $user_pic; ?>" class="w-10 h-10 rounded-full object-cover">
                    <div>
                        <h6 class="font-bold text-sm">CyberNinja</h6>
                        <span class="bg-[#3a3b3c] text-xs text-gray-300 px-2 py-0.5 rounded-md font-semibold"><i class="fas fa-globe-americas"></i> Public</span>
                    </div>
                </div>
                <textarea id="modalTextarea" rows="5" placeholder="What's on your mind?" class="w-full bg-transparent text-white outline-none resize-none text-lg placeholder-gray-500"></textarea>
                <button onclick="submitPost()" class="w-full bg-[#1877f2] hover:bg-[#1565c0] text-white font-bold py-2.5 rounded-lg mt-4 smooth-transition shadow-lg">Post</button>
            </div>
        </div>
    </div>

    <script>
        const newsFeed = document.getElementById('newsFeed');
        const postModal = document.getElementById('postModal');
        const modalTextarea = document.getElementById('modalTextarea');
        const postInput = document.getElementById('postInput');

        postInput.onclick = openModal;
        function openModal() { postModal.classList.remove('hidden'); modalTextarea.focus(); }
        function closeModal() { postModal.classList.add('hidden'); modalTextarea.value = ''; }

        function submitPost() {
            const text = modalTextarea.value.trim();
            if (text === "") return;

            fetch('index.php?action=create_post', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: text })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    closeModal();
                    loadFeed();
                }
            });
        }

        function loadFeed() {
            fetch('index.php?action=fetch_posts')
                .then(res => res.json())
                .then(posts => {
                    newsFeed.innerHTML = '';
                    if(posts.length === 0) {
                        newsFeed.innerHTML = `<p class="text-center text-gray-500 mt-10">No posts shared yet. Start the global conversation!</p>`;
                        return;
                    }

                    posts.forEach(post => {
                        const postCard = document.createElement('div');
                        postCard.className = "bg-[#242526] rounded-xl p-4 shadow-md border border-[#2f3031] smooth-transition";
                        
                        postCard.innerHTML = `
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-[#1877f2] rounded-full flex items-center justify-center font-bold text-white text-sm">CN</div>
                                    <div>
                                        <h6 class="font-bold text-sm hover:underline cursor-pointer">@\${post.username}</h6>
                                        <span class="text-xs text-gray-400">\${post.created_at} · <i class="fas fa-globe-americas text-[10px]"></i></span>
                                    </div>
                                </div>
                                <div class="text-gray-400 hover:bg-[#3a3b3c] w-8 h-8 rounded-full flex items-center justify-center cursor-pointer smooth-transition"><i class="fas fa-ellipsis-h"></i></div>
                            </div>
                            <p class="text-gray-200 whitespace-pre-wrap text-[15px] mb-4 leading-relaxed">\${post.content}</p>
                            <div class="flex justify-between items-center text-gray-400 text-xs pb-2 border-b border-[#393a3b] px-1">
                                <div><i class="fas fa-thumbs-up text-[#1877f2]"></i> Liked by CyberNinja and others</div>
                                <div>0 Comments · 0 Shares</div>
                            </div>
                            <div class="flex justify-between pt-2 text-gray-400 text-sm font-semibold px-1">
                                <button class="flex items-center gap-2 hover:bg-[#3a3b3c] py-2 px-4 rounded-lg cursor-pointer w-full justify-center smooth-transition"><i class="far fa-thumbs-up"></i> Like</button>
                                <button class="flex items-center gap-2 hover:bg-[#3a3b3c] py-2 px-4 rounded-lg cursor-pointer w-full justify-center smooth-transition"><i class="far fa-comment"></i> Comment</button>
                                <button class="flex items-center gap-2 hover:bg-[#3a3b3c] py-2 px-4 rounded-lg cursor-pointer w-full justify-center smooth-transition"><i class="far fa-share-square"></i> Share</button>
                            </div>
                        `;
                        newsFeed.appendChild(postCard);
                    });
                });
        }

        setInterval(loadFeed, 3000);
        loadFeed();
    </script>
</body>
</html>
