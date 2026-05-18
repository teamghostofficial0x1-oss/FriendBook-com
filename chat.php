<?php
require_once 'src/api/endpoint/db/db_config.php';
session_start();
require_once 'status_tracker.php';

if (!isset($_SESSION['username'])) { header("Location: index.php"); exit; }
$sender = $_SESSION['username'];

if (isset($_GET['action']) && $_GET['action'] == 'send') {
    header('Content-Type: application/json');
    $msg = htmlspecialchars(trim($_POST['message'] ?? ''));
    $signal = $_POST['call_signal'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO messages (sender, message, call_signal) VALUES (?, ?, ?)");
    $stmt->execute([$sender, $msg, $signal]);
    echo json_encode(["status" => "success"]); exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'fetch') {
    header('Content-Type: application/json');
    $messages = $pdo->query("SELECT * FROM messages ORDER BY created_at ASC LIMIT 50")->fetchAll();
    echo json_encode($messages); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><title>Messenger - FriendBook</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-[#18191a] text-[#e4e6eb] min-h-screen flex flex-col overflow-hidden">

    <nav class="h-14 bg-[#242526] border-b border-[#393a3b] flex items-center justify-between px-4">
        <span class="font-bold">Logged in: <span class="text-green-400">@<?php echo $sender; ?></span></span>
        <a href="feed.php" class="text-xs text-blue-400 hover:underline font-bold">Back to Feed</a>
    </nav>

    <div id="videoCallContainer" class="hidden bg-black/90 p-4 flex flex-col items-center gap-4 border-b border-[#393a3b]">
        <div class="flex gap-4 w-full max-w-2xl justify-center">
            <div class="relative bg-neutral-800 rounded-lg overflow-hidden w-1/2 aspect-video"><video id="localVideo" autoplay muted playsinline class="w-full h-full object-cover"></video></div>
            <div class="relative bg-neutral-800 rounded-lg overflow-hidden w-1/2 aspect-video"><video id="remoteVideo" autoplay playsinline class="w-full h-full object-cover"></video></div>
        </div>
        <button onclick="endCall()" class="bg-red-600 px-6 py-2 rounded-full font-bold text-sm">End Call</button>
    </div>

    <div class="flex flex-1 flex-col overflow-hidden">
        <div class="p-3 bg-[#242526] border-b border-[#393a3b] flex justify-between items-center">
            <div>
                <h6 class="font-bold text-sm">Global Matrix Room</h6>
                <span class="text-xs text-green-400 flex items-center gap-1"><span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span> Active</span>
            </div>
            <div class="flex gap-4 text-xl text-[#1877f2]">
                <button onclick="startCall(false)"><i class="fas fa-phone"></i></button>
                <button onclick="startCall(true)"><i class="fas fa-video"></i></button>
            </div>
        </div>
        <div id="chatArea" class="flex-1 overflow-y-auto p-4 space-y-3"></div>
        <div class="p-3 bg-[#242526] border-t border-[#393a3b] flex gap-3">
            <input type="text" id="messageInput" placeholder="Aa" class="w-full bg-[#3a3b3c] rounded-full py-2 px-4 outline-none text-white">
            <button id="sendBtn" class="text-[#1877f2] text-xl"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>

    <script>
        const chatArea = document.getElementById('chatArea');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');
        const current_user = "<?php echo $sender; ?>";
        let localStream, peerConnection;
        const rtcConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };

        function fetchChat() {
            fetch('chat.php?action=fetch').then(res => res.json()).then(messages => {
                chatArea.innerHTML = '';
                messages.forEach(m => {
                    if(m.call_signal && m.sender !== current_user) handleSignalingData(JSON.parse(m.call_signal));
                    if(m.message) {
                        const b = document.createElement('div');
                        b.className = `p-2 my-1 max-w-[70%] rounded-xl text-sm \${m.sender === current_user ? 'bg-blue-600 ml-auto':'bg-neutral-700'}`;
                        b.innerHTML = `<strong>@\${m.sender}:</strong> \${m.message}`;
                        chatArea.appendChild(b);
                    }
                });
            });
        }

        function sendMsg(text, signal = null) {
            let fd = new FormData();
            if(text) fd.append('message', text);
            if(signal) fd.append('call_signal', JSON.stringify(signal));
            fetch('chat.php?action=send', { method: 'POST', body: fd });
        }

        async function startCall(videoEnabled) {
            document.getElementById('videoCallContainer').classList.remove('hidden');
            localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: videoEnabled });
            document.getElementById('localVideo').srcObject = localStream;
            peerConnection = new RTCPeerConnection(rtcConfig);
            localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
            peerConnection.onicecandidate = e => { if(e.candidate) sendMsg(null, { candidate: e.candidate }); };
            peerConnection.ontrack = e => { document.getElementById('remoteVideo').srcObject = e.streams[0]; };
            const offer = await peerConnection.createOffer();
            await peerConnection.setLocalDescription(offer);
            sendMsg("Started a call...", { offer: offer });
        }

        async function handleSignalingData(data) {
            if(!peerConnection) {
                document.getElementById('videoCallContainer').classList.remove('hidden');
                localStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
                document.getElementById('localVideo').srcObject = localStream;
                peerConnection = new RTCPeerConnection(rtcConfig);
                localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));
                peerConnection.onicecandidate = e => { if(e.candidate) sendMsg(null, { candidate: e.candidate }); };
                peerConnection.ontrack = e => { document.getElementById('remoteVideo').srcObject = e.streams[0]; };
            }
            if(data.offer) {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(data.offer));
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);
                sendMsg("Call Answered...", { answer: answer });
            } else if(data.answer) {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(data.answer));
            } else if(data.candidate) {
                await peerConnection.addIceCandidate(new RTCIceCandidate(data.candidate));
            }
        }

        function endCall() {
            if(localStream) localStream.getTracks().forEach(t => t.stop());
            if(peerConnection) peerConnection.close();
            document.getElementById('videoCallContainer').classList.add('hidden');
            sendMsg("Call ended.");
        }

        sendBtn.onclick = () => { if(messageInput.value.trim()){ sendMsg(messageInput.value); messageInput.value=''; } };
        setInterval(fetchChat, 2000);

        function sendHeartbeat() { fetch('status_tracker.php?action=heartbeat').catch(() => {}); }
        setInterval(sendHeartbeat, 10000); sendHeartbeat();
    </script>
</body>
</html>
