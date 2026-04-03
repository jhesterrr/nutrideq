<?php
session_start();
// Master Anti-Cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

require_once 'navigation.php';
require_once 'database.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$is_admin = ($user_role === 'admin');

function getInitials($name) {
    if (!$name) return '?';
    $p = explode(' ', $name); $i = '';
    foreach ($p as $x) if ($x !== '') $i .= strtoupper($x[0]);
    return substr($i, 0, 2);
}
$user_initials = getInitials($user_name);
$nav_links = getNavigationLinks($user_role, 'staff-help.php');

$database = new Database();
$pdo = $database->getConnection();

// Handle Thread Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_thread'])) {
    $thread_title = trim($_POST['thread_title'] ?? '');
    $initial_message = trim($_POST['initial_message'] ?? '');
    $selected_admins = $_POST['admins'] ?? [];
    if (!empty($thread_title) && !empty($initial_message) && !empty($selected_admins)) {
        try {
            $pdo->beginTransaction();
            $thread_uuid = bin2hex(random_bytes(16));
            $participants = array_merge([$user_id], array_map('intval', $selected_admins));
            $pdo->prepare("INSERT INTO internal_threads (thread_uuid, title, created_by, participants, status, last_message_at, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', NOW(), NOW(), NOW())")->execute([$thread_uuid, $thread_title, $user_id, json_encode($participants)]);
            $new_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO internal_thread_messages (thread_id, sender_id, sender_role, message, read_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$new_id, $user_id, $user_role, $initial_message, json_encode([$user_id])]);
            $pdo->commit();
            header("Location: staff-help.php?thread_id=$new_id"); exit();
        } catch (Exception $e) { $pdo->rollBack(); }
    }
}

// Fetch Threads
$where = $is_admin ? "1=1" : "(JSON_CONTAINS(participants, ?, '$') OR created_by = ?)";
$stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE $where ORDER BY last_message_at DESC");
$is_admin ? $stmt->execute() : $stmt->execute([json_encode($user_id), $user_id]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'admin' AND status = 'active'");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
$selected_thread = null;
$thread_messages = [];
if ($selected_thread_id) {
    $stmt = $pdo->prepare($is_admin ? "SELECT * FROM internal_threads WHERE id = ?" : "SELECT * FROM internal_threads WHERE id = ? AND (JSON_CONTAINS(participants, ?, '$') OR created_by = ?)");
    $is_admin ? $stmt->execute([$selected_thread_id]) : $stmt->execute([$selected_thread_id, json_encode($user_id), $user_id]);
    $selected_thread = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($selected_thread) {
        $stmt = $pdo->prepare("SELECT m.*, u.name as sender_name FROM internal_thread_messages m LEFT JOIN users u ON m.sender_id = u.id WHERE m.thread_id = ? ORDER BY m.created_at ASC");
        $stmt->execute([$selected_thread_id]);
        $thread_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Support Hub | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css?v=110">
    <link rel="stylesheet" href="css/sidebar.css?v=110">
    <link rel="stylesheet" href="css/logout-modal.css?v=110">
    <style>
        /* LUMINOUS CLARITY V110 */
        body { margin: 0; background: #f8fafc !important; overflow: hidden; font-family:'Poppins',sans-serif; height: 100vh; width: 100vw; }
        .main-layout { display: flex; height: 100vh; width: 100vw; position:fixed; left:0; top:0; }
        
        .mobile-nav-header { display: none; background: #ffffff; padding: 10px 20px; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; z-index: 2000; position: fixed; top: 0; left: 0; width: 100%; box-sizing: border-box; height: 60px; }
        .mobile-hamburger { font-size: 1.5rem; color: #2e8b57; cursor: pointer; padding: 5px; }

        .main-content { flex: 1; height: 100vh; overflow: hidden; padding: 20px !important; box-sizing: border-box !important; position:relative; display: flex; flex-direction: column; }
        .messaging-wrapper { display: flex !important; gap: 20px; flex: 1; min-height: 0; margin: 0; }
        
        .msg-sidebar, .msg-container { background: #ffffff; border-radius: 16px; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; overflow: hidden; }
        .msg-sidebar { width: 320px; flex-shrink:0; }
        .msg-container { flex: 1; min-width: 0; }

        .chat-messages { flex: 1; padding: 20px; overflow-y: auto !important; display: flex; flex-direction: column; gap: 16px; background:#fff; scroll-behavior: smooth; -webkit-overflow-scrolling: touch; }
        .message-bubble { padding: 12px 18px !important; border-radius: 14px !important; max-width: 85%; font-size: 0.92rem; line-height: 1.5; }

        /* THE ELASTIC INPUT V110 */
        .chat-input-area { padding: 15px 20px; border-top: 1px solid #f1f5f9; background: #ffffff; }
        .input-pill { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 24px; display: flex; align-items: flex-end; padding: 10px 16px; width: 100%; box-sizing: border-box; }
        .chat-input { flex: 1; border: none !important; background: transparent !important; padding: 4px 10px !important; outline: none !important; font-size: 0.95rem; resize: none !important; color: #1e293b !important; line-height: 1.5; max-height: 150px; overflow-y: auto; }
        
        /* SHARP SIDEBAR V110 */
        .sidebar { background: #ffffff !important; border-right: 1px solid #e2e8f0; padding: 25px 0; display: flex; flex-direction: column; width: 260px; height: 100vh; flex-shrink: 0; z-index: 50000; transition: transform 0.3s ease; }
        .nav-links a { pointer-events: auto !important; position: relative; z-index: 50001; }
        
        @media (max-width: 1024px) {
            .sidebar { position: fixed; left: 0; transform: translateX(-100%); box-shadow: 20px 0 50px rgba(0,0,0,0.2); }
            .sidebar.active { transform: translateX(0); }
            .main-content { padding: 80px 12px 12px 12px !important; }
            .mobile-nav-header { display: flex; }
            .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); z-index: 49999; }
            .sidebar-overlay.active { display: block; }
        }

        @media (max-width: 768px) {
            .messaging-wrapper { gap: 0; }
            .msg-sidebar { width: 100%; }
            .msg-container { width: 100%; position: absolute; inset: 0; z-index: 100; transform: translateX(110%); transition: transform 0.3s ease; }
            .messaging-wrapper.active-chat .msg-container { transform: translateX(0); }
        }
    </style>
</head>
<body>
    <header class="mobile-nav-header">
        <div class="mobile-hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></div>
        <img src="assets/img/logo.png" alt="Logo" style="height: 32px;">
        <div style="width: 32px;"></div>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-layout">
        <div class="sidebar" id="mainSidebar">
            <a class="logo" href="dashboard.php" style="pointer-events:auto;"><img src="assets/img/logo.png" alt="NutriDeq" class="logo-img"><span style="margin-left:10px; font-weight:700; color:#2e8b57; font-family:'Playfair Display',serif; font-size:20px;">NutriDeq</span></a>
            <ul class="nav-links">
                <?php foreach ($nav_links as $link): ?>
                    <?php if (isset($link['type']) && $link['type'] === 'header'): ?>
                        <li style="font-size:0.7rem; color:#94a3b8; text-transform:uppercase; margin-top:20px; margin-bottom:10px; padding-left:15px; font-weight:700;"><?= $link['text'] ?></li>
                    <?php else: ?>
                        <li><a href="<?= $link['href'] ?>" class="<?= !empty($link['active'])?'active':'' ?>"><i class="<?= $link['icon'] ?>"></i> <span><?= $link['text'] ?></span></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div style="padding: 15px 20px; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 12px;">
                <div style="width: 38px; height: 38px; border-radius: 50%; background: #2e8b57; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size:14px;"><?= $user_initials ?></div>
                <div style="font-size:0.8rem; overflow:hidden;">
                    <div style="font-weight:700; color:#1e293b;"><?= htmlspecialchars($user_name) ?></div>
                    <div style="color:#64748b; font-size:0.7rem;"><?= ucfirst($user_role) ?></div>
                </div>
            </div>
            <div style="padding: 10px 20px; border-top: 1px solid #f1f5f9;"><button id="logoutTrigger" style="color:#ef4444; border:none; background:none; cursor:pointer; font-weight:600; font-size:14px; display:flex; align-items:center; gap:10px; width:100%; text-align:left; pointer-events:auto;"><i class="fas fa-sign-out-alt"></i> Logout</button></div>
        </div>

        <main class="main-content">
            <div class="messaging-wrapper <?= $selected_thread_id ? 'active-chat' : '' ?>">
                <div class="msg-sidebar">
                    <div style="padding:20px; border-bottom:1px solid #f1f5f9; background:#fff;">
                        <h3 style="margin:0 0 15px 0; font-family:'Playfair Display',serif; color:#1e293b;">Inquiries</h3>
                        <button onclick="openModal()" style="width:100%; background:#2e8b57; color:white; border:none; padding:12px; border-radius:12px; font-weight:700; cursor:pointer;">New Consultation</button>
                    </div>
                    <div class="contact-list">
                        <?php foreach ($threads as $thread): ?>
                            <div class="contact-item <?= ($selected_thread_id == $thread['id'])?'active':'' ?>" onclick="window.location.href='?thread_id=<?= $thread['id'] ?>'">
                                <div style="width:40px; height:40px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#2e8b57; font-weight:bold;">#</div>
                                <div style="flex:1;"><div style="font-weight:700; color:#1e293b; font-size:0.9rem;"><?= htmlspecialchars($thread['title']) ?></div><div style="font-size:0.75rem; color:#64748b;"><?= strtoupper($thread['status']) ?></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="msg-container">
                    <?php if ($selected_thread): ?>
                        <div style="padding:15px 20px; border-bottom:1px solid #f1f5f9; display:flex; align-items:center; gap:15px; background:#fff;">
                            <a href="staff-help.php" style="color:#2e8b57; font-size:1.2rem; text-decoration:none;" class="mobile-only"><i class="fas fa-arrow-left"></i></a>
                            <h4 style="margin:0; font-weight:700; color:#1e293b; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($selected_thread['title']) ?></h4>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($thread_messages as $msg): $isMe = ($msg['sender_id'] == $user_id); ?>
                                <div class="message-wrapper <?= $isMe ? 'sent' : 'received' ?>" id="msg-<?= $msg['id'] ?>">
                                    <div class="message-bubble">
                                        <?php if(!$isMe): ?><div style="font-size:0.7rem; color:#2e8b57; font-weight:700; margin-bottom:4px;"><?= htmlspecialchars($msg['sender_name']) ?></div><?php endif; ?>
                                        <div style="color:inherit !important; word-break: break-word;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        <div style="font-size:0.65rem; opacity:0.6; text-align:right; margin-top:6px;"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="chat-input-area">
                            <form id="messageForm"><div class="input-pill">
                                <button type="button" class="icon-btn" id="attachBtn"><i class="fas fa-paperclip"></i></button>
                                <input type="file" id="fileInput" style="display:none;" accept=".pdf,.png,.jpg,.jpeg">
                                <textarea class="chat-input" id="messageInput" placeholder="Reply..." rows="1"></textarea>
                                <button type="submit" class="icon-btn" style="color:#2e8b57;"><i class="fas fa-paper-plane"></i></button>
                            </div></form>
                        </div>
                    <?php else: ?>
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px; color:#64748b;"><i class="fas fa-comment-medical fa-4x" style="margin-bottom:15px; color:#2e8b57; opacity:0.3;"></i><h3 style="font-family:'Playfair Display',serif;">Clinical Hub</h3><p>Select an inquiry to proceed.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="logoutModal" class="logout-modal"><div class="logout-modal-content"><i class="fas fa-sign-out-alt fa-3x" style="color:#ef4444; margin-bottom:20px;"></i><h3>Sign Out?</h3><div style="display:flex; gap:12px; justify-content:center; margin-top:25px;"><button onclick="document.getElementById('logoutModal').classList.remove('active')" style="padding:10px 20px; border-radius:10px; border:1px solid #e2e8f0; background:none; cursor:pointer; font-weight:600;">Cancel</button><button onclick="window.location.href='login-logout/logout.php'" style="padding:10px 20px; border-radius:10px; border:none; background:#ef4444; color:white; font-weight:700; cursor:pointer;">Logout</button></div></div></div>
            <div id="threadModal" class="modal-overlay"><div class="modal-body" style="background:white; padding:30px; border-radius:20px; max-width:400px; width:90%; animation: liquidIn 0.4s ease;"><div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;"><h3>New Consultation</h3><button onclick="closeModal()" style="border:none; background:none; font-size:24px; cursor:pointer;">&times;</button></div><form method="POST"><div style="margin-bottom:15px;"><label style="font-weight:600; font-size:0.9rem;">Subject</label><input type="text" name="thread_title" style="width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:12px;" required></div><div style="margin-bottom:15px;"><label style="font-weight:600; font-size:0.9rem;">To Admins</label><div style="max-height:100px; overflow-y:auto; border:1px solid #f1f5f9; padding:10px; border-radius:12px; font-size:0.9rem;">
                <?php foreach ($admins as $admin): ?><label style="display:flex; align-items:center; gap:10px; margin-bottom:8px;"><input type="checkbox" name="admins[]" value="<?= $admin['id'] ?>"> <?= htmlspecialchars($admin['name']) ?></label><?php endforeach; ?>
            </div></div><div style="margin-bottom:15px;"><label style="font-weight:600; font-size:0.9rem;">Initial Details</label><textarea name="initial_message" style="width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:12px; font-family:inherit;" rows="3" required></textarea></div><button type="submit" name="create_thread" style="width:100%; background:#2e8b57; color:white; border:none; padding:14px; border-radius:12px; font-weight:700; cursor:pointer;">Initiate</button></form></div></div>

            <script>const BASE_URL = '<?= rtrim(dirname($_SERVER['PHP_SELF']), '/') ?>/';</script>
            <script src="scripts/internal-chat-controller.js?v=110"></script>
            <script>
                const burger = document.getElementById('hamburgerBtn');
                const sidebar = document.getElementById('mainSidebar');
                const overlay = document.getElementById('sidebarOverlay');
                function toggleSidebar() { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); }
                if(burger) burger.onclick = toggleSidebar;
                if(overlay) overlay.onclick = toggleSidebar;
                document.getElementById('logoutTrigger').onclick = () => document.getElementById('logoutModal').classList.add('active');
                
                const tx = document.getElementById('messageInput');
                if(tx) { tx.addEventListener("input", function() { this.style.height = "auto"; this.style.height = (this.scrollHeight) + "px"; }, false); }

                document.addEventListener('DOMContentLoaded', () => {
                    <?php if ($selected_thread_id): ?>
                    new ChatController(<?= $user_id ?>, '<?= $user_role ?>', <?= $selected_thread_id ?>);
                    const el = document.getElementById('chatMessages'); if(el) el.scrollTop = el.scrollHeight;
                    <?php endif; ?>
                });
            </script>
        </main>
    </div>
</body>
</html>
