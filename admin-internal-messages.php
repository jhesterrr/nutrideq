<?php
/**
 * Admin Internal Messaging Hub
 * Elite Standard for Administrative Control
 */
ob_start();
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['user_role'] !== 'admin') {
    header("Location: login-logout/NutriDeqN-Login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

require_once 'navigation.php';
$nav_links_array = getNavigationLinks($user_role, 'admin-internal-messages.php');

function getInitials($name) {
    if (!$name) return '?';
    $p = explode(' ', $name);
    $i = '';
    foreach ($p as $x) if ($x !== '') $i .= strtoupper($x[0]);
    return substr($i, 0, 2);
}

require_once 'database.php';
$database = new Database();
$pdo = $database->getConnection();

$filter_status = $_GET['status'] ?? 'all';
$search_term = $_GET['search'] ?? '';

// Handle Actions (Resolve, Re-open, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $thread_id = $_POST['thread_id'] ?? null;
    if (isset($_POST['update_thread_status'])) {
        $new_status = $_POST['status'] ?? 'open';
        $pdo->prepare("UPDATE internal_threads SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$new_status, $thread_id]);
        header("Location: admin-internal-messages.php?thread_id=$thread_id&status=$filter_status");
        exit();
    }
    if (isset($_POST['delete_thread'])) {
        $pdo->prepare("DELETE FROM internal_thread_messages WHERE thread_id = ?")->execute([$thread_id]);
        $pdo->prepare("DELETE FROM internal_threads WHERE id = ?")->execute([$thread_id]);
        header("Location: admin-internal-messages.php");
        exit();
    }
}

// Fetch Threads (Master Visibility for Admins)
$where_conditions = ["1=1"];
$params = [];
if ($filter_status !== 'all') { $where_conditions[] = "status = ?"; $params[] = $filter_status; }
if (!empty($search_term)) { $where_conditions[] = "title LIKE ?"; $params[] = "%$search_term%"; }
$where_sql = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE $where_sql ORDER BY last_message_at DESC");
$stmt->execute($params);
$threads = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
$selected_thread = null;
$thread_messages = [];
if ($selected_thread_id) {
    // Admin visibility is 1=1
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
    <title>Internal Messages | NutriDeq Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css?v=101">
    <link rel="stylesheet" href="css/sidebar.css?v=101">
    <link rel="stylesheet" href="css/modern-messages.css?v=101">
    <link rel="stylesheet" href="css/responsive.css?v=101">
    <style>
        /* FORCE REBUILD - VISIBILITY FIX */
        .messaging-wrapper { opacity: 1 !important; visibility: visible !important; animation: none !important; margin: 0 !important; height: calc(100vh - 40px) !important; width: 100% !important; display: flex !important; gap: 24px !important; }
        .main-content { padding: 0 !important; overflow: hidden !important; background-color: #FAFAFA; height: 100vh; }
        .hover-bg:hover { background: #f5f7fa; }
    </style>
</head>
<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="messaging-wrapper <?= $selected_thread_id ? 'view-chat' : 'view-list' ?>">
                <div class="msg-sidebar">
                    <div class="msg-sidebar-header">
                        <h2>Internal Masters</h2>
                        <form method="GET">
                            <select name="status" onchange="this.form.submit()" style="padding:4px 8px; border-radius:8px; border:1px solid #ddd; font-size:0.85rem;">
                                <option value="all" <?= $filter_status=='all'?'selected':'' ?>>All Chats</option>
                                <option value="open" <?= $filter_status=='open'?'selected':'' ?>>Open</option>
                                <option value="resolved" <?= $filter_status=='resolved'?'selected':'' ?>>Resolved</option>
                            </select>
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">
                        </form>
                    </div>
                    <div class="contact-list">
                        <?php foreach ($threads as $thread): ?>
                            <div class="contact-item <?= ($selected_thread_id == $thread['id']) ? 'active' : '' ?>" 
                                 onclick="window.location.href='?thread_id=<?= $thread['id'] ?>&status=<?= $filter_status ?>'">
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
                                <div class="contact-avatar" style="background:var(--accent-light); color:var(--primary-green);">#</div>
                                <div>
                                    <div class="header-name"><?= htmlspecialchars($selected_thread['title']) ?></div>
                                    <div class="header-status">Master Session • <?= ucfirst($selected_thread['status']) ?></div>
                                </div>
                            </div>
                            <div class="chat-actions">
                                <?php if ($selected_thread['status'] == 'open'): ?>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="thread_id" value="<?= $selected_thread_id ?>">
                                    <input type="hidden" name="update_thread_status" value="1">
                                    <button type="submit" name="status" value="resolved" class="btn btn-primary" style="padding:6px 14px; font-size:0.85rem; border-radius:10px;">
                                        <i class="fas fa-check-circle"></i> Resolve
                                    </button>
                                </form>
                                <?php endif; ?>
                                <button class="btn btn-outline" id="manageBtn" style="padding:6px 12px; font-size:0.9rem; border-radius:10px;">Manage</button>
                                <div id="manageMenu" style="display:none; position:absolute; right:20px; top:70px; background:white; border:1px solid #ddd; box-shadow:0 10px 30px rgba(0,0,0,0.1); border-radius:12px; z-index:100; min-width:160px;">
                                    <form method="POST">
                                        <input type="hidden" name="thread_id" value="<?= $selected_thread_id ?>">
                                        <input type="hidden" name="update_thread_status" value="1">
                                        <button type="submit" name="status" value="open" style="width:100%; padding:10px; border:none; background:none; text-align:left; cursor:pointer;" class="hover-bg">Re-open Thread</button>
                                        <button type="submit" name="status" value="archived" style="width:100%; padding:10px; border:none; background:none; text-align:left; cursor:pointer;" class="hover-bg">Archive Case</button>
                                    </form>
                                    <button onclick="document.getElementById('deleteModal').style.display='flex'" style="width:100%; padding:10px; border:none; background:none; text-align:left; cursor:pointer; color:red;" class="hover-bg">Delete Forever</button>
                                </div>
                            </div>
                        </div>
                        <div class="chat-messages" id="chatMessages">
                            <?php foreach ($thread_messages as $msg): 
                                $isMe = ($msg['sender_id'] == $admin_id);
                                ?>
                                <div class="message-wrapper <?= $isMe ? 'sent' : 'received' ?>">
                                    <div class="message-bubble">
                                        <?php if(!$isMe): ?>
                                            <div style="font-size:0.75rem; color:#2E8B57; font-weight:700; margin-bottom:4px;"><?= htmlspecialchars($msg['sender_name']) ?></div>
                                        <?php endif; ?>
                                        <div class="message-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        <div style="font-size:0.65rem; opacity:0.6; text-align:right; margin-top:4px;"><?= date('g:i A', strtotime($msg['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="chat-input-area">
                            <?php if ($selected_thread['status'] == 'open'): ?>
                            <form id="messageForm">
                                <div class="input-pill-container">
                                    <textarea class="chat-input" id="messageInput" placeholder="Type an internal response..." rows="1"></textarea>
                                    <div class="input-actions">
                                        <button type="submit" class="icon-btn send-btn"><i class="fas fa-paper-plane"></i></button>
                                    </div>
                                </div>
                            </form>
                            <?php else: ?>
                            <div style="text-align:center; padding:20px; color:var(--text-tertiary);">Thread is <?= $selected_thread['status'] ?>. Re-open to reply.</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0.6;">
                            <i class="fas fa-shield-alt fa-4x" style="margin-bottom:20px; color:#2E8B57;"></i>
                            <h3>Master Message Center</h3>
                            <p>Pick a staff thread to review history and provide guidance.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div id="deleteModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:2000;">
                <div style="background:white; padding:30px; border-radius:15px; width:300px; text-align:center;">
                    <h3>Delete Thread?</h3>
                    <form method="POST">
                        <input type="hidden" name="thread_id" value="<?= $selected_thread_id ?>">
                        <input type="hidden" name="delete_thread" value="1">
                        <div style="display:flex; gap:10px; justify-content:center; margin-top:20px;">
                            <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" class="btn btn-outline">Cancel</button>
                            <button type="submit" class="btn" style="background:red; color:white;">Delete</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>const BASE_URL = '<?= rtrim(dirname($_SERVER['PHP_SELF']), '/') ?>/';</script>
            <script src="scripts/internal-chat-controller.js?v=101"></script>
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    <?php if ($selected_thread_id): ?>
                    new ChatController(<?= $admin_id ?>, 'admin', <?= $selected_thread_id ?>);
                    const el = document.getElementById('chatMessages');
                    if(el) el.scrollTop = el.scrollHeight;
                    <?php endif; ?>

                    const mBtn = document.getElementById('manageBtn');
                    const mMenu = document.getElementById('manageMenu');
                    if(mBtn && mMenu) {
                        mBtn.onclick = (e) => { e.stopPropagation(); mMenu.style.display = mMenu.style.display === 'block' ? 'none' : 'block'; };
                        document.onclick = () => mMenu.style.display = 'none';
                    }
                });
            </script>
        </div>
    </div>
</body>
</html>
