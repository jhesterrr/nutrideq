<?php
session_start();
// Master Anti-Cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

require_once 'navigation.php';
require_once 'database.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$is_admin = true;

function getInitials($name) {
    if (!$name) return '?';
    $p = explode(' ', $name); $i = '';
    foreach ($p as $x) if ($x !== '') $i .= strtoupper($x[0]);
    return substr($i, 0, 2);
}
$user_initials = getInitials($user_name);
$nav_links = getNavigationLinks($user_role, 'admin-internal-messages.php');

$database = new Database(); $pdo = $database->getConnection();
$status_filter = $_GET['status'] ?? 'all';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid = $_POST['thread_id'] ?? null;
    if (isset($_POST['update_status'])) { $pdo->prepare("UPDATE internal_threads SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$_POST['status'] ?? 'open', $tid]); header("Location: admin-internal-messages.php?thread_id=$tid&status=$status_filter"); exit(); }
}

$where = $status_filter !== 'all' ? "status = ?" : "1=1";
$params = $status_filter !== 'all' ? [$status_filter] : [];
$stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE $where ORDER BY last_message_at DESC");
$stmt->execute($params);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
if ($selected_thread_id) {
    $stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE id = ?");
    $stmt->execute([$selected_thread_id]);
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
    <title>Admin Oversight | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css?v=110">
    <link rel="stylesheet" href="css/sidebar.css?v=110">
    <link rel="stylesheet" href="css/logout-modal.css?v=110">
    <style>
        /* LUMINOUS CLARITY ADMIN V110 */
        body { margin: 0; background: #f8fafc !important; overflow: hidden; font-family:'Poppins',sans-serif; height: 100vh; width: 100vw; }
        .main-layout { display: flex; height: 100vh; width: 100vw; position:fixed; left:0; top:0; }
        
        .mobile-nav-header { display: none; background: #ffffff; padding: 10px 20px; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; z-index: 2000; position: fixed; top: 0; left: 0; width: 100%; box-sizing: border-box; height: 60px; }
        .mobile-hamburger { font-size: 1.5rem; color: #2e8b57; cursor: pointer; padding: 5px; }

        .main-content { flex: 1; height: 100vh; overflow: hidden; padding: 20px !important; box-sizing: border-box !important; position:relative; display: flex; flex-direction: column; }
        .messaging-wrapper { display: flex !important; gap: 20px; flex: 1; min-height: 0; margin: 0; }
        
        .msg-sidebar, .msg-container { background: #ffffff; border-radius: 16px; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; overflow: hidden; }
        .msg-sidebar { width: 320px; flex-shrink:0; }
        .msg-container { flex: 1; min-width: 0; position:relative; }

        .chat-messages { flex: 1; padding: 20px; overflow-y: auto !important; display: flex; flex-direction: column; gap: 16px; background:#fff; scroll-behavior: smooth; -webkit-overflow-scrolling: touch; }
        .message-bubble { padding: 12px 18px !important; border-radius: 14px !important; max-width: 85%; font-size: 0.92rem; line-height: 1.5; }

        .chat-input-area { padding: 15px 20px; border-top: 1px solid #f1f5f9; background: #ffffff; }
        .input-pill { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 24px; display: flex; align-items: flex-end; padding: 10px 16px; width: 100%; box-sizing: border-box; }
        .chat-input { flex: 1; border: none !important; background: transparent !important; padding: 4px 10px !important; outline: none !important; font-size: 0.95rem; resize: none !important; color: #1e293b !important; line-height: 1.5; max-height: 150px; overflow-y: auto; }
        
        /* SHARP SIDEBAR ADMIN V110 */
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
                    <div style="color:#64748b; font-size:0.7rem;">Administrator</div>
                </div>
            </div>
            <div style="padding: 10px 20px; border-top: 1px solid #f1f5f9;"><button id="logoutTrigger" style="color:#ef4444; border:none; background:none; cursor:pointer; font-weight:600; font-size:14px; display:flex; align-items:center; gap:10px; width:100%; text-align:left; pointer-events:auto;"><i class="fas fa-sign-out-alt"></i> Logout</button></div>
        </div>

        <main class="main-content">
            <div class="messaging-wrapper <?= $selected_thread_id ? 'active-chat' : '' ?>">
                <div class="msg-sidebar">
                    <div style="padding:24px; border-bottom:1px solid #f1f5f9; background:#fff;">
                        <h3 style="margin:0 0 15px 0; font-family:'Playfair Display',serif; color:#1e293b;">Oversight</h3>
                        <form method="GET"><select name="status" onchange="this.form.submit()" style="width:100%; padding:10px; border-radius:10px; border:1px solid #e2e8f0; font-family:inherit; color:#1e293b; font-size:0.9rem;">
                            <option value="all" <?= $status_filter=='all'?'selected':'' ?>>All Consultations</option>
                            <option value="open" <?= $status_filter=='open'?'selected':'' ?>>Active Cases</option>
                            <option value="resolved" <?= $status_filter=='resolved'?'selected':'' ?>>Resolved</option>
                        </select></form>
                    </div>
                    <div class="contact-list">
                        <?php foreach ($threads as $thread): ?>
                            <div class="contact-item <?= ($selected_thread_id == $thread['id'])?'active':'' ?>" onclick="window.location.href='?thread_id=<?= $thread['id'] ?>&status=<?= $status_filter ?>'">
                                <div style="width:40px; height:40px; background:#f1f5f9; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#2e8b57; font-weight:bold;">#</div>
                                <div style="flex:1;"><div style="font-weight:700; color:#1e293b; font-size:0.9rem;"><?= htmlspecialchars($thread['title']) ?></div><div style="font-size:0.75rem; color:#64748b;"><?= strtoupper($thread['status']) ?></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="msg-container">
                    <?php if ($selected_thread): ?>
                        <div style="padding:15px 20px; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center; background:#fff;">
                            <div style="display:flex; align-items:center; gap:15px;">
                                <a href="admin-internal-messages.php?status=<?= $status_filter ?>" style="color:#2e8b57; font-size:1.2rem; text-decoration:none;" class="mobile-only"><i class="fas fa-arrow-left"></i></a>
                                <h4 style="margin:0; font-weight:700; color:#1e293b; flex:1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($selected_thread['title']) ?></h4>
                            </div>
                            <?php if($selected_thread['status'] == 'open'): ?>
                            <form method="POST"><input type="hidden" name="thread_id" value="<?= $selected_thread_id ?>"><input type="hidden" name="update_status" value="1"><button type="submit" name="status" value="resolved" style="background:#2e8b57; color:white; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; font-weight:600; font-size:0.8rem;"><i class="fas fa-check"></i></button></form>
                            <?php endif; ?>
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
                            <?php if($selected_thread['status'] == 'open'): ?>
                            <form id="messageForm"><div class="input-pill">
                                <button type="button" class="icon-btn" id="attachBtn"><i class="fas fa-paperclip"></i></button>
                                <input type="file" id="fileInput" style="display:none;" accept=".pdf,.png,.jpg,.jpeg">
                                <textarea class="chat-input" id="messageInput" placeholder="Reply..." rows="1"></textarea>
                                <button type="submit" class="icon-btn" style="color:#2e8b57;"><i class="fas fa-paper-plane"></i></button>
                            </div></form>
                            <?php else: ?><div style="text-align:center; padding:10px; color:#64748b; font-style:italic;">Consultation finalized.</div><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:20px; color:#64748b;"><i class="fas fa-shield-alt fa-4x" style="margin-bottom:15px; color:#2e8b57; opacity:0.3;"></i><h3 style="font-family:'Playfair Display',serif;">Admin Command Hub</h3><p>Select a consultation case.</p></div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="logoutModal" class="logout-modal"><div class="logout-modal-content"><i class="fas fa-sign-out-alt fa-3x" style="color:#ef4444; margin-bottom:20px;"></i><h3>Sign Out?</h3><div style="display:flex; gap:12px; justify-content:center; margin-top:25px;"><button onclick="document.getElementById('logoutModal').classList.remove('active')" style="padding:10px 20px; border-radius:10px; border:1px solid #e2e8f0; background:none; cursor:pointer; font-weight:600;">Cancel</button><button onclick="window.location.href='login-logout/logout.php'" style="padding:10px 20px; border-radius:10px; border:none; background:#ef4444; color:white; font-weight:700; cursor:pointer;">Logout</button></div></div></div>

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
                    new ChatController(<?= $user_id ?>, 'admin', <?= $selected_thread_id ?>);
                    const el = document.getElementById('chatMessages'); if(el) el.scrollTop = el.scrollHeight;
                    <?php endif; ?>
                });
            </script>
        </main>
    </div>
</body>
</html>
