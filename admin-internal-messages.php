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
    if (isset($_POST['delete'])) { $pdo->prepare("DELETE FROM internal_thread_messages WHERE thread_id = ?")->execute([$tid]); $pdo->prepare("DELETE FROM internal_threads WHERE id = ?")->execute([$tid]); header("Location: admin-internal-messages.php"); exit(); }
}

$where = $status_filter !== 'all' ? "status = ?" : "1=1";
$params = $status_filter !== 'all' ? [$status_filter] : [];
$stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE $where ORDER BY last_message_at DESC");
$stmt->execute($params);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
$selected_thread = null;
$thread_messages = [];
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
    <title>Admin Masters | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css?v=108">
    <link rel="stylesheet" href="css/sidebar.css?v=108">
    <link rel="stylesheet" href="css/logout-modal.css?v=108">
    <style>
        /* OMNI-RESPONSIVE OVAL - ADMIN V108 */
        body { margin: 0; background: #f0f2f5 !important; overflow: hidden; font-family:'Poppins',sans-serif; height: 100vh; }
        .main-layout { display: grid; grid-template-columns: 260px 1fr; height: 100vh; width: 100%; position:fixed; left:0; top:0; }
        
        /* MOBILE NAV HEADER */
        .mobile-nav-header { display: none; background: white; padding: 10px 20px; align-items: center; justify-content: space-between; border-bottom: 1px solid #ddd; z-index: 1005; position: fixed; top: 0; left: 0; width: 100%; box-sizing: border-box; }
        .mobile-hamburger { font-size: 1.5rem; color: #2e8b57; cursor: pointer; }

        .main-content { grid-column: 2; height: 100vh; overflow-y: auto; padding: 24px !important; box-sizing: border-box !important; position:relative; }
        .messaging-wrapper { display: flex !important; gap: 20px; height: calc(100vh - 48px); width: 100% !important; margin: 0; transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .msg-sidebar, .msg-container { background: white; border-radius: 20px; display: flex; flex-direction: column; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #ddd; }
        .msg-sidebar { width: 340px; flex-shrink:0; }
        .msg-container { flex: 1; overflow: hidden; position:relative; }

        .contact-item { padding: 12px 18px; border-radius: 12px; display: flex; align-items: center; gap: 12px; cursor: pointer; border-bottom: 1px solid #f9f9f9; }
        .message-bubble { padding: 12px 18px; border-radius: 18px; max-width: 75%; font-size: 0.95rem; line-height: 1.5; }
        .message-wrapper.sent .message-bubble { background:#2E8B57; color:white !important; }
        .message-wrapper.received .message-bubble { background:#f1f1f1; color:#1a1a1a !important; }

        /* THE ELITE PILL-ADMIN */
        .chat-input-area { padding: 10px 15px; border-top: 1px solid #f0f0f0; background: white; }
        .input-pill { background: #f8f9fa; border: 1px solid #ddd; border-radius: 30px; display: flex; align-items: center; padding: 5px 15px; width: 100%; box-sizing: border-box; }
        .chat-input { flex: 1; border: none !important; background: transparent !important; padding: 8px 12px !important; outline: none !important; resize: none !important; font-size: 0.95rem; color: #1a1a1a !important; }
        
        /* SIDEBAR SYNC */
        .sidebar { background: #ffffff; border-right:1px solid rgba(46,139,87,0.2); padding:30px 0; display:flex; flex-direction:column; width:260px; height:100vh; position:sticky; top:0; z-index:2000; transition:transform 0.3s ease; }
        .logo { font-family:'Playfair Display',serif; font-size:24px; font-weight:700; color:#2e8b57; display:flex; align-items:center; text-decoration:none; padding:0 20px 20px; border-bottom:1px solid rgba(46,139,87,0.2); margin-bottom:15px; }
        .logo-img { height:45px; width:auto; }

        /* RESPONSIVE BREAKPOINTS */
        @media (max-width: 1024px) {
            .main-layout { grid-template-columns: 1fr; }
            .sidebar { position: fixed; left: 0; transform: translateX(-100%); width: 260px; box-shadow: 10px 0 30px rgba(0,0,0,0.1); }
            .sidebar.active { transform: translateX(0); }
            .main-content { grid-column: 1; padding: 70px 10px 10px 10px !important; height: calc(100vh); }
            .mobile-nav-header { display: flex; }
            .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1999; }
            .sidebar-overlay.active { display: block; }
        }

        @media (max-width: 768px) {
            .messaging-wrapper { gap: 0; height: calc(100vh - 80px); }
            .msg-sidebar { width: 100%; border-radius: 12px; }
            .msg-container { width: 100%; position: absolute; top: 0; left: 0; height: 100%; z-index: 5; transform: translateX(100%); transition: transform 0.3s ease; border-radius: 12px; }
            .messaging-wrapper.active-chat .msg-container { transform: translateX(0); }
            .messaging-wrapper.active-chat .msg-sidebar { transform: translateX(-20%); opacity: 0.5; }
            .chat-messages { padding: 15px; }
        }
    </style>
</head>
<body>
    <header class="mobile-nav-header">
        <div class="mobile-hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></div>
        <img src="assets/img/logo.png" alt="Logo" style="height: 35px;">
        <div style="width: 24px;"></div>
    </header>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="main-layout">
        <div class="sidebar" id="mainSidebar">
            <a class="logo" href="dashboard.php"><img src="assets/img/logo.png" alt="NutriDeq" class="logo-img"><span style="margin-left:10px;">NutriDeq</span></a>
            <ul class="nav-links">
                <?php foreach ($nav_links as $link): ?>
                    <?php if (isset($link['type']) && $link['type'] === 'header'): ?>
                        <li style="font-size:0.7rem; color:#999; text-transform:uppercase; margin-top:20px; margin-bottom:10px; padding-left:15px; font-weight:700;"><?= $link['text'] ?></li>
                    <?php else: ?>
                        <li><a href="<?= $link['href'] ?>" class="<?= !empty($link['active'])?'active':'' ?>"><i class="<?= $link['icon'] ?>"></i> <span><?= $link['text'] ?></span></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div style="padding: 15px 20px; border-top: 1px solid rgba(46, 139, 87, 0.2); display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #2e8b57, #4ca1af); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;"><?= $user_initials ?></div>
                <div style="font-size:0.8rem;">
                    <div style="font-weight:700; color:#1a1a1a;"><?= htmlspecialchars($user_name) ?></div>
                    <div style="color:#999;">Administrator</div>
                </div>
            </div>
            <div style="padding: 10px 20px; border-top: 1px solid rgba(46, 139, 87, 0.2);"><button id="logoutTrigger" style="color:#ff6b6b; border:none; background:none; cursor:pointer; font-weight:500; font-size:14px; display:flex; align-items:center; gap:10px;"><i class="fas fa-sign-out-alt"></i> Logout</button></div>
        </div>

        <main class="main-content">
            <div class="messaging-wrapper <?= $selected_thread ? 'active-chat' : '' ?>">
                <!-- LIST SIDEBAR -->
                <div class="msg-sidebar">
                    <div style="padding:24px; border-bottom:1px solid #eee; background:#fafafa;">
                        <h3 style="margin:0 0 15px 0;">Case Oversight</h3>
                        <form method="GET"><select name="status" onchange="this.form.submit()" style="width:100%; padding:10px; border-radius:10px; border:1px solid #ddd; font-family:inherit; color:#1a1a1a; font-size:0.9rem;">
                            <option value="all" <?= $status_filter=='all'?'selected':'' ?>>All Conversations</option>
                            <option value="open" <?= $status_filter=='open'?'selected':'' ?>>Active Cases</option>
                            <option value="resolved" <?= $status_filter=='resolved'?'selected':'' ?>>Resolved</option>
                        </select></form>
                    </div>
                    <div class="contact-list" style="overflow-y:auto; flex:1;">
                        <?php foreach ($threads as $thread): ?>
                            <div class="contact-item <?= ($selected_thread_id == $thread['id'])?'active':'' ?>" onclick="window.location.href='?thread_id=<?= $thread['id'] ?>&status=<?= $status_filter ?>'">
                                <div style="width:40px; height:40px; background:#f0f0f0; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#2e8b57;">#</div>
                                <div style="flex:1;"><div style="font-weight:700; color:#1a1a1a; font-size:0.9rem;"><?= htmlspecialchars($thread['title']) ?></div><div style="font-size:0.75rem; color:#888;"><?= ucfirst($thread['status']) ?></div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- CHAT CONTAINER -->
                <div class="msg-container">
                    <?php if ($selected_thread): ?>
                        <div style="padding:15px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#fafafa;">
                            <div style="display:flex; align-items:center; gap:15px;">
                                <a href="admin-internal-messages.php?status=<?= $status_filter ?>" style="color:#666; font-size:1.2rem; text-decoration:none;" class="mobile-only"><i class="fas fa-arrow-left"></i></a>
                                <h4 style="margin:0; font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?= htmlspecialchars($selected_thread['title']) ?></h4>
                            </div>
                            <?php if($selected_thread['status'] == 'open'): ?>
                            <form method="POST"><input type="hidden" name="thread_id" value="<?= $selected_thread_id ?>"><input type="hidden" name="update_status" value="1"><button type="submit" name="status" value="resolved" style="background:#2e8b57; color:white; border:none; padding:8px 12px; border-radius:8px; cursor:pointer; font-weight:600; font-size:0.8rem;"><i class="fas fa-check"></i></button></form>
                            <?php endif; ?>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($thread_messages as $msg): $isMe = ($msg['sender_id'] == $user_id); ?>
                                <div class="message-wrapper <?= $isMe ? 'sent' : 'received' ?>" id="msg-<?= $msg['id'] ?>">
                                    <div class="message-bubble">
                                        <?php if(!$isMe): ?><div style="font-size:0.75rem; color:#2e8b57; font-weight:700; margin-bottom:4px;"><?= htmlspecialchars($msg['sender_name']) ?></div><?php endif; ?>
                                        <div style="color:inherit !important;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        <div style="font-size:0.65rem; opacity:0.6; text-align:right; margin-top:5px;"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
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
                            <?php else: ?><div style="text-align:center; padding:10px; color:#999; font-style:italic;">Resolved.</div><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0.5;"><i class="fas fa-shield-alt fa-4x" style="margin-bottom:15px; color:#2e8b57;"></i><h3>Admin Control</h3></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- MODALS -->
            <div id="logoutModal" class="logout-modal"><div class="logout-modal-content"><i class="fas fa-sign-out-alt fa-3x" style="color:#ff6b6b; margin-bottom:20px;"></i><h3>Are you sure?</h3><div style="display:flex; gap:12px; justify-content:center; margin-top:25px;"><button onclick="document.getElementById('logoutModal').classList.remove('active')" style="padding:10px 20px; border-radius:10px; border:1px solid #ddd; background:none; cursor:pointer;">Cancel</button><button onclick="window.location.href='login-logout/logout.php'" style="padding:10px 20px; border-radius:10px; border:none; background:#ff6b6b; color:white; font-weight:600; cursor:pointer;">Logout</button></div></div></div>

            <script>const BASE_URL = '<?= rtrim(dirname($_SERVER['PHP_SELF']), '/') ?>/';</script>
            <script src="scripts/internal-chat-controller.js?v=108"></script>
            <script>
                const burger = document.getElementById('hamburgerBtn');
                const sidebar = document.getElementById('mainSidebar');
                const overlay = document.getElementById('sidebarOverlay');
                function toggleSidebar() { sidebar.classList.toggle('active'); overlay.classList.toggle('active'); }
                burger.onclick = toggleSidebar;
                overlay.onclick = toggleSidebar;
                document.getElementById('logoutTrigger').onclick = () => document.getElementById('logoutModal').classList.add('active');
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
