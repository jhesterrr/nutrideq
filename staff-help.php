<?php
session_start();
// FORCE REFRESH - ANTI CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || ($_SESSION['user_role'] !== 'staff' && $_SESSION['user_role'] !== 'admin')) {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

require_once 'navigation.php';
require_once 'database.php';

$staff_id = $_SESSION['user_id'];
$staff_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$is_admin = ($user_role === 'admin');
$nav_links_array = getNavigationLinks($user_role, 'staff-help.php');

$database = new Database();
$pdo = $database->getConnection();

function getInitials($name) {
    if (!$name) return '?';
    $p = explode(' ', $name);
    $i = '';
    foreach ($p as $x) if ($x !== '') $i .= strtoupper($x[0]);
    return substr($i, 0, 2);
}

// Handle Thread Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_thread'])) {
    $thread_title = trim($_POST['thread_title'] ?? '');
    $initial_message = trim($_POST['initial_message'] ?? '');
    $selected_admins = $_POST['admins'] ?? [];
    $sender_role = $is_admin ? 'admin' : 'staff';
    if (!empty($thread_title) && !empty($initial_message) && !empty($selected_admins)) {
        try {
            $pdo->beginTransaction();
            $thread_uuid = bin2hex(random_bytes(16));
            $participants = array_merge([$staff_id], array_map('intval', $selected_admins));
            $stmt = $pdo->prepare("INSERT INTO internal_threads (thread_uuid, title, created_by, participants, status, last_message_at, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', NOW(), NOW(), NOW())");
            $stmt->execute([$thread_uuid, $thread_title, $staff_id, json_encode($participants)]);
            $new_thread_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO internal_thread_messages (thread_id, sender_id, sender_role, message, read_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$new_thread_id, $staff_id, $sender_role, $initial_message, json_encode([$staff_id])]);
            $pdo->commit();
            header("Location: staff-help.php?thread_id=" . $new_thread_id);
            exit();
        } catch (Exception $e) { $pdo->rollBack(); }
    }
}

// Fetch Threads
$threads = [];
try {
    $where = $is_admin ? "1=1" : "(JSON_CONTAINS(participants, ?, '$') OR created_by = ?)";
    $stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE $where ORDER BY last_message_at DESC");
    $is_admin ? $stmt->execute() : $stmt->execute([json_encode($staff_id), $staff_id]);
    $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch Admins
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'admin' AND status = 'active'");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
$selected_thread = null;
$thread_messages = [];
if ($selected_thread_id) {
    if ($is_admin) {
        $stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE id = ?");
        $stmt->execute([$selected_thread_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE id = ? AND (JSON_CONTAINS(participants, ?, '$') OR created_by = ?)");
        $stmt->execute([$selected_thread_id, json_encode($staff_id), $staff_id]);
    }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Hub | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css?v=102">
    <link rel="stylesheet" href="css/sidebar.css?v=102">
    <link rel="stylesheet" href="css/modern-messages.css?v=102">
    <link rel="stylesheet" href="css/responsive.css?v=102">
    <style>
        /* SITE-WIDE COMPATIBILITY FIXes */
        .main-content { padding: 24px !important; min-height: 100vh; background: #f0f2f5; }
        .messaging-wrapper { opacity: 1 !important; visibility: visible !important; display: flex !important; gap: 20px; height: calc(100vh - 120px) !important; width: 100% !important; margin: 0 auto; max-width: 1400px; }
        .page-container { width: 100%; height: 100%; }
        
        .new-thread-btn { background: #2E8B57; color: white; border: none; padding: 10px 18px; border-radius: 10px; font-weight: 600; cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; font-family: 'Poppins', sans-serif; }
        .new-thread-btn:hover { background: #1e6b42; transform: translateY(-2px); }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 2000; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-container { background: white; width: 100%; max-width: 480px; border-radius: 20px; padding: 25px; box-shadow: 0 15px 50px rgba(0,0,0,0.1); transform: translateY(20px); transition: 0.3s; }
        .modal-overlay.active .modal-container { transform: translateY(0); }
        .admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; max-height: 140px; overflow-y: auto; padding: 5px; }
        .admin-card { border: 2px solid #f0f0f0; padding: 8px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 6px; font-size: 0.85rem; }
        .admin-card input { display: none; }
        .admin-card.selected { background: #e6f4ea; border-color: #2e8b57; }
        .start-btn { width: 100%; background: #2E8B57; color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 700; margin-top: 15px; cursor: pointer; }

        @media screen and (max-width: 1024px) {
            .messaging-wrapper { flex-direction: column; height: auto !important; }
            .msg-sidebar, .msg-container { width: 100% !important; height: 500px !important; }
        }
    </style>
</head>
<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-container">
                <div class="messaging-wrapper <?= $selected_thread_id ? 'view-chat' : 'view-list' ?>">
                    <div class="msg-sidebar">
                        <div class="msg-sidebar-header">
                            <h2 style="font-family:'Playfair Display',serif; margin-bottom:15px;">Clinical Support</h2>
                            <button class="new-thread-btn" onclick="openSupportModal()"><i class="fas fa-plus"></i> New Inquiry</button>
                        </div>
                        <div class="contact-list">
                            <?php foreach ($threads as $thread): ?>
                                <div class="contact-item <?= ($selected_thread_id == $thread['id']) ? 'active' : '' ?>" onclick="window.location.href='?thread_id=<?= $thread['id'] ?>'">
                                    <div class="contact-avatar"><i class="fas fa-hashtag"></i></div>
                                    <div class="contact-info">
                                        <div class="contact-name"><?= htmlspecialchars($thread['title']) ?></div>
                                        <div class="contact-preview"><?= ucfirst($thread['status']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="msg-container">
                        <?php if ($selected_thread): ?>
                            <div class="chat-header">
                                <div class="chat-header-user">
                                    <div class="contact-avatar" style="background:#f0f7f4; color:#2e8b57;">#</div>
                                    <div class="header-name"><?= htmlspecialchars($selected_thread['title']) ?></div>
                                </div>
                            </div>
                            <div class="chat-messages" id="chatMessages">
                                <?php foreach ($thread_messages as $msg): 
                                    $isMe = ($msg['sender_id'] == $staff_id); ?>
                                    <div class="message-wrapper <?= $isMe ? 'sent' : 'received' ?>">
                                        <div class="message-bubble">
                                            <?php if(!$isMe): ?><div style="font-size:0.7rem; color:#2e8b57; font-weight:700; margin-bottom:3px;"><?= htmlspecialchars($msg['sender_name']) ?></div><?php endif; ?>
                                            <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                            <div style="font-size:0.6rem; opacity:0.6; text-align:right; margin-top:3px;"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="chat-input-area">
                                <form id="messageForm">
                                    <div class="input-pill-container">
                                        <textarea class="chat-input" id="messageInput" placeholder="Message admins..." rows="1"></textarea>
                                        <div class="input-actions"><button type="submit" class="icon-btn send-btn"><i class="fas fa-paper-plane"></i></button></div>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0.5;">
                                <i class="fas fa-comments fa-3x" style="margin-bottom:15px; color:#2e8b57;"></i>
                                <h3 style="font-family:'Playfair Display',serif;">Inquiry Hub</h3>
                                <p>Select a thread to communicate.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MODAL -->
                <div id="supportModal" class="modal-overlay">
                    <div class="modal-container">
                        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                            <h3 style="margin:0;">New Inquiry</h3>
                            <button onclick="closeSupportModal()" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
                        </div>
                        <form method="POST">
                            <div style="margin-bottom:15px;">
                                <label style="display:block; font-weight:600; margin-bottom:5px;">Subject</label>
                                <input type="text" name="thread_title" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" required>
                            </div>
                            <div style="margin-bottom:15px;">
                                <label style="display:block; font-weight:600; margin-bottom:5px;">Clinical Admins</label>
                                <div class="admin-grid">
                                    <?php foreach ($admins as $admin): ?>
                                        <label class="admin-card"><input type="checkbox" name="admins[]" value="<?= $admin['id'] ?>" onchange="this.closest('.admin-card').classList.toggle('selected', this.checked)"><span><?= htmlspecialchars($admin['name']) ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div style="margin-bottom:15px;">
                                <label style="display:block; font-weight:600; margin-bottom:5px;">Message</label>
                                <textarea name="initial_message" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px;" rows="3" required></textarea>
                            </div>
                            <button type="submit" name="create_thread" class="start-btn">Start Session</button>
                        </form>
                    </div>
                </div>
            </div>

            <script>const BASE_URL = '<?= rtrim(dirname($_SERVER['PHP_SELF']), '/') ?>/';</script>
            <script src="scripts/internal-chat-controller.js?v=102"></script>
            <script>
                function openSupportModal() { const m = document.getElementById('supportModal'); m.style.display = 'flex'; setTimeout(() => m.classList.add('active'), 10); }
                function closeSupportModal() { const m = document.getElementById('supportModal'); m.classList.remove('active'); setTimeout(() => m.style.display = 'none', 300); }
                document.addEventListener('DOMContentLoaded', () => {
                    <?php if ($selected_thread_id): ?>
                    new ChatController(<?= $staff_id ?>, '<?= $user_role ?>', <?= $selected_thread_id ?>);
                    const el = document.getElementById('chatMessages'); if(el) el.scrollTop = el.scrollHeight;
                    <?php endif; ?>
                });
            </script>
        </main>
    </div>
</body>
</html>
