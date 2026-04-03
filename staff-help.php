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
            $pdo->prepare("INSERT INTO internal_threads (thread_uuid, title, created_by, participants, status, last_message_at, created_at, updated_at) VALUES (?, ?, ?, ?, 'open', NOW(), NOW(), NOW())")->execute([$thread_uuid, $thread_title, $staff_id, json_encode($participants)]);
            $new_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO internal_thread_messages (thread_id, sender_id, sender_role, message, read_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())")->execute([$new_id, $staff_id, $sender_role, $initial_message, json_encode([$staff_id])]);
            $pdo->commit();
            header("Location: staff-help.php?thread_id=$new_id"); exit();
        } catch (Exception $e) { $pdo->rollBack(); }
    }
}

// Fetch Threads (Master Admin Visibility)
$where = $is_admin ? "1=1" : "(JSON_CONTAINS(participants, ?, '$') OR created_by = ?)";
$stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE $where ORDER BY last_message_at DESC");
$is_admin ? $stmt->execute() : $stmt->execute([json_encode($staff_id), $staff_id]);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Admins
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'admin' AND status = 'active'");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
$selected_thread = null;
$thread_messages = [];
if ($selected_thread_id) {
    $stmt = $pdo->prepare($is_admin ? "SELECT * FROM internal_threads WHERE id = ?" : "SELECT * FROM internal_threads WHERE id = ? AND (JSON_CONTAINS(participants, ?, '$') OR created_by = ?)");
    $is_admin ? $stmt->execute([$selected_thread_id]) : $stmt->execute([$selected_thread_id, json_encode($staff_id), $staff_id]);
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css?v=103">
    <link rel="stylesheet" href="css/sidebar.css?v=103">
    <style>
        /* IMMORTAL CLINICAL UI - RECOVERY V103 */
        body { background-color: #f0f2f5 !important; margin: 0; }
        .main-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .main-content { grid-column: 2; padding: 24px !important; display: block !important; visibility: visible !important; }
        .page-container { max-width: 1400px; margin: 0 auto; width: 100%; position: relative; }

        /* HIDE TOP-LEFT NOISE */
        .mobile-header { display: none !important; }

        .messaging-wrapper { display: flex !important; opacity: 1 !important; visibility: visible !important; gap: 20px; height: calc(100vh - 120px); width: 100% !important; animation: none !important; }
        .msg-sidebar { width: 320px; background: white; border-radius: 20px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #eee; }
        .msg-sidebar-header { padding: 20px; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
        .contact-list { flex: 1; overflow-y: auto; padding: 10px; }
        .contact-item { padding: 12px; border-radius: 12px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: 0.2s; margin-bottom: 5px; }
        .contact-item:hover { background: #f5f7fa; transform: translateX(5px); }
        .contact-item.active { background: #e6f4ea; border-left: 4px solid #2e8b57; }

        .msg-container { flex: 1; background: white; border-radius: 20px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 4px 25px rgba(0,0,0,0.05); border: 1px solid #eee; }
        .chat-header { padding: 15px 25px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .chat-messages { flex: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; background: #fdfdfd; }
        
        .message-wrapper { display: flex; max-width: 80%; }
        .message-wrapper.sent { align-self: flex-end; flex-direction: row-reverse; }
        .message-bubble { padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.5; }
        .message-wrapper.sent .message-bubble { background: #2E8B57; color: white !important; border-bottom-right-radius: 4px; }
        .message-wrapper.received .message-bubble { background: #f1f3f5; color: #1a1a1a !important; border-bottom-left-radius: 4px; }
        .message-text { color: inherit !important; }

        .chat-input-area { padding: 20px; border-top: 1px solid #f0f0f0; background: white; }
        .input-pill { background: #f8f9fa; border: 1px solid #eee; border-radius: 30px; display: flex; align-items: center; padding: 8px 15px; }
        .chat-input { flex: 1; border: none; background: transparent; padding: 8px 12px; outline: none; font-size: 0.95rem; resize: none; font-family: inherit; color: #1a1a1a !important; }
        .send-btn { background: #2e8b57; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; }
        .send-btn:hover { transform: scale(1.1); background: #1e6b42; }

        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 2000; }
        .modal-overlay.active { display: flex; }
        .modal-body { background: white; padding: 30px; border-radius: 20px; width: 450px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .new-thread-btn { background: #2e8b57; color: white; border: none; padding: 10px 18px; border-radius: 10px; cursor: pointer; font-weight: 600; }
    </style>
</head>
<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="page-container">
                <div class="messaging-wrapper">
                    <div class="msg-sidebar">
                        <div class="msg-sidebar-header">
                            <h2 style="font-family:'Playfair Display',serif; margin:0 0 15px 0;">Help Center</h2>
                            <button class="new-thread-btn" onclick="openModal()"><i class="fas fa-plus"></i> New Inquiry</button>
                        </div>
                        <div class="contact-list">
                            <?php foreach ($threads as $thread): ?>
                                <div class="contact-item <?= ($selected_thread_id == $thread['id']) ? 'active' : '' ?>" onclick="window.location.href='?thread_id=<?= $thread['id'] ?>'">
                                    <div class="contact-avatar">#</div>
                                    <div class="contact-info">
                                        <div style="font-weight:600; color:#1a1a1a;"><?= htmlspecialchars($thread['title']) ?></div>
                                        <div style="font-size:0.8rem; color:#666;"><?= ucfirst($thread['status']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="msg-container">
                        <?php if ($selected_thread): ?>
                            <div class="chat-header">
                                <div style="font-weight:700; color:#1a1a1a;"><?= htmlspecialchars($selected_thread['title']) ?></div>
                            </div>
                            <div class="chat-messages" id="chatMessages">
                                <?php foreach ($thread_messages as $msg): 
                                    $isMe = ($msg['sender_id'] == $staff_id); ?>
                                    <div class="message-wrapper <?= $isMe ? 'sent' : 'received' ?>" id="msg-<?= $msg['id'] ?>">
                                        <div class="message-bubble" style="background:<?= $isMe ? '#2E8B57' : '#f1f1f1' ?>; color:<?= $isMe ? 'white' : '#1a1a1a' ?> !important;">
                                            <?php if(!$isMe): ?><div style="font-size:0.7rem; color:#2e8b57; font-weight:700; margin-bottom:3px;"><?= htmlspecialchars($msg['sender_name']) ?></div><?php endif; ?>
                                            <div class="message-text" style="color:inherit !important;"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                            <div style="font-size:0.6rem; opacity:0.6; text-align:right; margin-top:5px; color:<?= $isMe ? 'rgba(255,255,255,0.7)' : '#666' ?>;"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="chat-input-area">
                                <form id="messageForm">
                                    <div class="input-pill">
                                        <textarea class="chat-input" id="messageInput" placeholder="Reply to inquiry..." rows="1"></textarea>
                                        <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0.6;">
                                <i class="fas fa-comments fa-3x" style="margin-bottom:15px; color:#2e8b57;"></i>
                                <h3 style="font-family:'Playfair Display',serif; color:#1a1a1a;">Inquiry Hub</h3>
                                <p style="color:#666;">Select a thread to respond.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- MODAL -->
            <div id="threadModal" class="modal-overlay">
                <div class="modal-body">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                        <h3 style="margin:0; color:#1a1a1a;">New Inquiry</h3>
                        <button onclick="closeModal()" style="border:none; background:none; font-size:24px; cursor:pointer; color:#666;">&times;</button>
                    </div>
                    <form method="POST">
                        <div style="margin-bottom:15px;">
                            <label style="display:block; font-weight:600; margin-bottom:5px; color:#1a1a1a;">Subject</label>
                            <input type="text" name="thread_title" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; color:#1a1a1a;" required>
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="display:block; font-weight:600; margin-bottom:5px; color:#1a1a1a;">Clinical Admins</label>
                            <div style="max-height:100px; overflow-y:auto; padding:5px; border:1px solid #f0f0f0; border-radius:10px; color:#1a1a1a;">
                                <?php foreach ($admins as $admin): ?>
                                    <label style="display:block; margin:5px 0;"><input type="checkbox" name="admins[]" value="<?= $admin['id'] ?>"> <?= htmlspecialchars($admin['name']) ?></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div style="margin-bottom:15px;">
                            <label style="display:block; font-weight:600; margin-bottom:5px; color:#1a1a1a;">Message</label>
                            <textarea name="initial_message" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:10px; color:#1a1a1a;" rows="3" required></textarea>
                        </div>
                        <button type="submit" name="create_thread" class="new-thread-btn" style="width:100%; padding:12px;">Start Consultation</button>
                    </form>
                </div>
            </div>

            <script src="scripts/internal-chat-controller.js?v=103"></script>
            <script>
                const BASE_URL = '<?= rtrim(dirname($_SERVER['PHP_SELF']), '/') ?>/';
                function openModal() { document.getElementById('threadModal').classList.add('active'); }
                function closeModal() { document.getElementById('threadModal').classList.remove('active'); }
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
