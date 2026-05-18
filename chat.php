<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();
require_once 'status_tracker.php';

if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }
$me = $_SESSION['username'];

// --- ১. চ্যাট মেসেজ সেন্ড করার পিএইচপি API (নির্দিষ্ট ফ্রেন্ডের জন্য) ---
if (isset($_GET['action']) && $_GET['action'] == 'send') {
    header('Content-Type: application/json');
    $msg = htmlspecialchars(trim($_POST['message'] ?? ''));
    $receiver = trim($_POST['receiver'] ?? '');
    $signal = $_POST['call_signal'] ?? null;
    
    if(!empty($receiver) && (!empty($msg) || $signal !== null)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender, receiver, message, call_signal) VALUES (?, ?, ?, ?)");
        $stmt->execute([$me, $receiver, $msg, $signal]);
        echo json_encode(["status" => "success"]);
    }
    exit;
}

// --- ২. নির্দিষ্ট দুই বন্ধুর প্রাইভেট মেসেজ লোড করার API ---
if (isset($_GET['action']) && $_GET['action'] == 'fetch' && isset($_GET['friend'])) {
    header('Content-Type: application/json');
    $friend = trim($_GET['friend']);
    
    // শুধু কারেন্ট ইউজার এবং সিলেক্টেড ফ্রেন্ডের আদান-প্রদান করা মেসেজ কুয়েরি
    $stmt = $pdo->prepare("SELECT * FROM messages WHERE (sender = ? AND receiver = ?) OR (sender = ? AND receiver = ?) ORDER BY created_at ASC LIMIT 100");
    $stmt->execute([$me, $friend, $friend, $me]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// --- ৩. শুধুমাত্র রিয়াল ফ্রেন্ডদের লিস্ট কুয়েরি করা (Accepted Request Only) ---
$friends_stmt = $pdo->prepare("SELECT CASE WHEN sender = ? THEN receiver ELSE sender END as friend_name FROM friends WHERE (sender = ? OR receiver = ?) AND status = 'accepted'");
$friends_stmt->execute([$me, $me, $me]);
$my_friends_list = $friends_stmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Messenger - FriendBook</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex flex-col overflow-hidden">

    <nav class="h-14 bg-[#242526] border-b border-[#393a3b] flex items-center justify-between px-4 shrink-0 shadow-md">
        <span class="font-bold flex items-center gap-2">Messenger / <span class="text-[#1877f2]">@<?php echo $me; ?></span></span>
        <a href="feed.php" class="text-xs bg-[#3a3b3c] hover:bg-[#4e4f50] px-3 py-1.5 rounded-xl font-bold transition-all"><i class="fas fa-arrow-left mr-1"></i>Back to Feed</a>
    </nav>

    <div class="flex flex-1 overflow-hidden w-full max-w-[1400px] mx-auto">
        
        <aside class="w-80 border-r border-[#393a3b] bg-[#242526] flex flex-col overflow-y-auto">
            <div class="p-4 border-b border-[#393a3b] font-black text-lg text-gray-200">Chats</div>
            <div class="flex-1 p-2 space-y-1">
                <?php if(empty($my_friends_list)): ?>
                    <p class="text-xs text-gray-500 text-center py-8">No friends yet. Add friends to chat!</p>
                <?php else: ?>
                    <?php foreach($my_friends_list as $f_name): 
                        // প্রতিটি ফ্রেন্ডের লাস্ট সিন তুলে নিয়ে লাইভ অনলাইন স্ট্যাটাস মার্কিং
                        $st = $pdo->prepare("SELECT last_seen FROM users WHERE username = ?"); $st->execute([$f_name]);
                        $status_dot = getUserStatus($st->fetchColumn());
                    ?>
                    <div onclick="selectFriend('<?php echo $f_name; ?>')" id="user-<?php echo $f_name; ?>" class="friend-card flex items-center gap-3 p-3 hover:bg-[#3a3b3c] rounded-xl cursor-pointer transition-all border border-transparent">
                        <div class="relative">
                            <div class="w-10 h-10 bg-[#1877f2] rounded-full flex items-center justify-center font-black text-xs uppercase"><?php echo substr($f_name,0,2); ?></div>
                            <span class="absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-[#242526] <?php echo $status_dot === 'online' ? 'bg-green-500 animate-pulse' : 'bg-gray-500';?>"></span>
                        </div>
                        <div><h6 class="font-bold text-sm">@<?php echo $f_name; ?></h6><span class="text-[10px] text-gray-400 capitalize"><?php echo $status_dot; ?></span></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <main class="flex-1 bg-[#18191a] flex flex-col overflow-hidden relative">
            <div id="blankBox" class="absolute inset-0 bg-[#18191a] z-10 flex flex-col items-center justify-center text-gray-500 text-sm font-semibold p-4">
                <i class="fab fa-facebook-messenger text-5xl text-[#3a3b3c] mb-3"></i> Select a friend from the sidebar to start a secure conversation.
            </div>

            <div class="p-3 bg-[#242526] border-b border-[#393a3b] flex justify-between items-center shrink-0">
                <div><h6 id="chattingWithHeader" class="font-bold text-sm text-white">@Username</h6></div>
                <div class="flex gap-4 text-xl text-[#1877f2] opacity-50 cursor-not-allowed"><i class="fas fa-phone"></i><i class="fas fa-video"></i></div>
            </div>
            
            <div id="chatArea" class="flex-1 overflow-y-auto p-4 space-y-2 flex flex-col"></div>
            
            <div class="p-3 bg-[#242526] border-t border-[#393a3b] flex gap-3 shrink-0">
                <input type="text" id="messageInput" placeholder="Type a secure message..." class="w-full bg-[#3a3b3c] rounded-full py-2 px-4 outline-none text-white text-sm">
                <button id="sendBtn" onclick="triggerSendMessage()" class="text-[#1877f2] text-xl px-2"><i class="fas fa-paper-plane"></i></button>
            </div>
        </main>
    </div>

    <script>
        let activeFriend = '';
        const chatArea = document.getElementById('chatArea');
        const messageInput = document.getElementById('messageInput');

        function selectFriend(friendName) {
            activeFriend = friendName;
            document.getElementById('blankBox').classList.add('hidden');
            document.getElementById('chattingWithHeader').innerText = '@' + friendName;
            
            document.querySelectorAll('.friend-card').forEach(el => el.classList.remove('bg-[#3a3b3c]', 'border-[#1877f2]/30'));
            document.getElementById('user-' + friendName).classList.add('bg-[#3a3b3c]', 'border-[#1877f2]/30');
            
            fetchChat();
        }

        function fetchChat() {
            if(!activeFriend) return;
            fetch(`chat.php?action=fetch&friend=\${activeFriend}`)
                .then(res => res.json())
                .then(messages => {
                    chatArea.innerHTML = '';
                    messages.forEach(m => {
                        const b = document.createElement('div');
                        b.className = `p-2.5 my-1 max-w-[70%] rounded-2xl text-sm shadow \${m.sender === "<?php echo $me; ?>" ? 'bg-[#1877f2] text-white ml-auto rounded-br-none' : 'bg-[#3a3b3c] text-gray-100 rounded-bl-none'}`;
                        b.innerHTML = m.message;
                        chatArea.appendChild(b);
                    });
                    chatArea.scrollTop = chatArea.scrollHeight;
                });
        }

        function triggerSendMessage() {
            const text = messageInput.value.trim();
            if(!text || !activeFriend) return;
            
            let fd = new FormData();
            fd.append('message', text);
            fd.append('receiver', activeFriend);
            
            fetch('chat.php?action=send', { method: 'POST', body: fd })
                .then(() => { messageInput.value = ''; fetchChat(); });
        }

        messageInput.addEventListener("keypress", (e) => { if(e.key === "Enter") triggerSendMessage(); });
        setInterval(fetchChat, 2500);

        function sendHeartbeat() { fetch('status_tracker.php?action=heartbeat').catch(() => {}); }
        setInterval(sendHeartbeat, 10000); sendHeartbeat();
    </script>
</body>
</html>
