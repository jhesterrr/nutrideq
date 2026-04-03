<?php
/**
 * Admin Internal Messaging Hub - IMMORTAL VERSION
 * Elite Standard for Administrative Control
 */
ob_start();
session_start();
// MASTER ANTI-CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
require_once 'navigation.php';
require_once 'database.php';
$nav_links_array = getNavigationLinks($user_role, 'admin-internal-messages.php');
$database = new Database(); $pdo = $database->getConnection();

$status_filter = $_GET['status'] ?? 'all';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid = $_POST['thread_id'] ?? null;
    if (isset($_POST['update_status'])) { $pdo->prepare("UPDATE internal_threads SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$_POST['status'] ?? 'open', $tid]); header("Location: admin-internal-messages.php?thread_id=$tid&status=$status_filter"); exit(); }
    if (isset($_POST['delete'])) { $pdo->prepare("DELETE FROM internal_thread_messages WHERE thread_id = ?")->execute([$tid]); $pdo->prepare("DELETE FROM internal_threads WHERE id = ?")->execute([$tid]); header("Location: admin-internal-messages.php"); exit(); }
}

// Fetch Threads (Master Visibility)
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internal Masters | NutriDeq Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css?v=103">
    <link rel="stylesheet" href="css/sidebar.css?v=103">
    <style>
        /* IMMORTAL MASTER UI */
        body { background-color: #f0f2f5 !important; margin: 0; font-family: 'Poppins', sans-serif; }
        .main-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; width:100%; }
        .main-content { grid-column: 2; padding: 24px !important; display: block !important; visibility: visible !important; min-width: 0; }
        
        .messaging-wrapper { 
            display: flex !important; opacity: 1 !important; visibility: visible !important; gap: 20px; 
            height: calc(100vh - 100px); width: 100% !important; animation: none !important; margin:0 auto; max-width:1400px;
        }
        .msg-sidebar { 
            width: 320px; background: white; border-radius: 20px; display: flex; flex-direction: column; overflow: hidden; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #eee; flex-shrink: 0;
        }
        .msg-sidebar-header { padding: 20px; border-bottom: 1px solid #f0f0f0; background: #fafafa; }
        .contact-list { flex: 1; overflow-y: auto; padding: 10px; }
        .contact-item { padding: 12px; border-radius: 12px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: 0.2s; margin-bottom: 5px; }
        .contact-item:hover { background: #f5f7fa; transform: translateX(5px); }
        .contact-item.active { background: #e6f4ea; border-left: 4px solid #2e8b57; }

        .msg-container { 
            flex: 1; min-width: 0; background: white; border-radius: 20px; display: flex; flex-direction: column; 
            overflow: hidden; box-shadow: 0 4px 25px rgba(0,0,0,0.05); border: 1px solid #eee;
        }
        .chat-header { padding: 15px 25px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .chat-messages { flex: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; background: #fdfdfd; }
        
        .message-wrapper { display: flex; max-width: 80%; width: fit-content; }
        .message-wrapper.sent { align-self: flex-end; flex-direction: row-reverse; }
        .message-bubble { padding: 12px 18px; border-radius: 18px; font-size: 0.95rem; line-height: 1.5; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .message-wrapper.sent .message-bubble { background: #2E8B57; color: white; border-bottom-right-radius: 4px; }
        .message-wrapper.received .message-bubble { background: #f1f1f1; color: #333; border-bottom-left-radius: 4px; }

        .chat-input-area { padding: 20px; border-top: 1px solid #f0f0f0; background: white; }
        .input-pill { background: #f8f9fa; border: 1px solid #eee; border-radius: 30px; display: flex; align-items: center; padding: 10px 15px; }
        .chat-input { flex: 1; border: none; background: transparent; padding: 0 15px; outline: none; font-size: 0.95rem; resize: none; min-height: 24px; font-family: inherit; }
        .send-btn { background: #2e8b57; color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
        .send-btn:hover { background: #1e6b42; transform: scale(1.1); }

        .btn-primary { background: #2E8B57; color: white; border: none; padding: 8px 16px; border-radius: 10px; cursor: pointer; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        <main class="main-content">
            <div class="messaging-wrapper">
                <div class="msg-sidebar">
                    <div class="msg-sidebar-header">
                        <h2>Master Hub</h2>
                        <form method="GET">
                            <select name="status" onchange="this.form.submit()" style="width:100%; padding:8px; border-radius:10px; border:1px solid #eee;">
                                <option value="all" <?= $status_filter=='all'?'selected':'' ?>>All Conversations</option>
                                <option value="open" <?= $status_filter=='open'?'selected':'' ?>>Open Cases</option>
                                <option value="resolved" <?= $status_filter=='resolved'?'selected':'' ?>>Resolved</option>
                            </select>
                        </form>
                    </div>
                    <div class="contact-list">
                        <?php foreach ($threads as $thread): ?>
                            <div class="contact-item <?= ($selected_thread_id == $thread['id']) ? 'active' : '' ?>" onclick="window.location.href='?thread_id=<?= $thread['id'] ?>&status=<?= $status_filter ?>'">
                                <div class="contact-avatar">#</div>
                                <div class="contact-info">
                                    <div style="font-weight:600; font-size:0.9rem; color:#333;"><?= htmlspecialchars($thread['title']) ?></div>
                                    <div style="font-size:0.75rem; color:#999;"><?= ucfirst($thread['status']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="msg-container">
                    <?php if ($selected_thread): ?>
                        <div class="chat-header">
                            <div style="font-weight:700; color:#333;"><?= htmlspecialchars($selected_thread['title']) ?></div>
                            <div class="chat-actions">
                                <?php if ($selected_thread['status'] == 'open'): ?>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="thread_id" value="<?= $selected_thread_id ?>">
                                    <input type="hidden" name="update_status" value="1">
                                    <button type="submit" name="status" value="resolved" class="btn-primary"><i class="fas fa-check"></i> Resolve</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($thread_messages as $msg): 
                                $isMe = ($msg['sender_id'] == $admin_id); ?>
                                <div class="message-wrapper <?= $isMe ? 'sent' : 'received' ?>">
                                    <div class="message-bubble">
                                        <?php if(!$isMe): ?><div style="font-size:0.75rem; color:#2e8b57; font-weight:700; margin-bottom:4px;"><?= htmlspecialchars($msg['sender_name']) ?></div><?php endif; ?>
                                        <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        <div style="font-size:0.65rem; opacity:0.6; text-align:right; margin-top:5px;"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="chat-input-area">
                            <?php if ($selected_thread['status'] == 'open'): ?>
                            <form id="messageForm">
                                <div class="input-pill">
                                    <textarea class="chat-input" id="messageInput" placeholder="Send an administrative reply..." rows="1"></textarea>
                                    <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
                                </div>
                            </form>
                            <?php else: ?>
                            <div style="text-align:center; padding:15px; color:#999;">Case is <?= $selected_thread['status'] ?>. Re-open to reply.</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0.5;">
                            <i class="fas fa-shield-alt fa-3x" style="margin-bottom:15px; color:#2e8b57;"></i>
                            <h3>Admin Command Hub</h3>
                            <p>Pick a staff case to oversee.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script src="scripts/internal-chat-controller.js?v=103"></script>
            <script>
                const BASE_URL = '<?= rtrim(dirname($_SERVER['PHP_SELF']), '/') ?>/';
                document.addEventListener('DOMContentLoaded', () => {
                    <?php if ($selected_thread_id): ?>
                    new ChatController(<?= $admin_id ?>, 'admin', <?= $selected_thread_id ?>);
                    const el = document.getElementById('chatMessages'); if(el) el.scrollTop = el.scrollHeight;
                    <?php endif; ?>
                });
            </script>
        </main>
    </div>
</body>
</html>
