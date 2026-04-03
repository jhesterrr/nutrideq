<?php
session_start();
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

// Handle Thread Creation (POST)
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

            $stmt = $pdo->prepare("INSERT INTO internal_thread_messages (thread_id, sender_id, sender_role, message, read_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$new_thread_id, $staff_id, $sender_role, $initial_message, json_encode([$staff_id])]);

            $pdo->commit();
            header("Location: staff-help.php?thread_id=" . $new_thread_id);
            exit();
        } catch (Exception $e) { $pdo->rollBack(); }
    }
}

// Fetch Threads (Admins see ALL)
$threads = [];
try {
    $where = $is_admin ? "1=1" : "(JSON_CONTAINS(participants, ?, '$') OR created_by = ?)";
    $stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE $where ORDER BY last_message_at DESC");
    $is_admin ? $stmt->execute() : $stmt->execute([json_encode($staff_id), $staff_id]);
    $threads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch Admins for Modal
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'admin' AND status = 'active'");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$selected_thread_id = isset($_GET['thread_id']) ? intval($_GET['thread_id']) : null;
$selected_thread = null;
if ($selected_thread_id) {
    $stmt = $pdo->prepare("SELECT * FROM internal_threads WHERE id = ?");
    $stmt->execute([$selected_thread_id]);
    $selected_thread = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="scripts/theme-toggle.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Hub | NutriDeq</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/base.css?v=101">
    <link rel="stylesheet" href="css/sidebar.css?v=101">
    <link rel="stylesheet" href="css/modern-messages.css?v=101">
    <link rel="stylesheet" href="css/responsive.css?v=101">
    <style>
        /* ELITE HELP CENTER STYLES */
        .new-thread-btn {
            background: linear-gradient(135deg, #2E8B57, #3cb371);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(46, 139, 87, 0.2);
            width: fit-content;
        }
        .new-thread-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(46, 139, 87, 0.3);
            filter: brightness(1.1);
        }
        .new-thread-btn i { font-size: 0.9rem; }

        /* GLASSMORPHIC MODAL */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-container {
            background: white;
            width: 100%;
            max-width: 500px;
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            transform: translateY(30px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-overlay.active .modal-container { transform: translateY(0); }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .modal-header h3 { font-size: 1.5rem; font-weight: 700; color: #1a1a1a; margin: 0; }
        .close-modal { 
            background: #f5f7fa;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            transition: 0.2s;
        }
        .close-modal:hover { background: #fee2e2; color: #ef4444; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 8px; color: #4b5563; }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 1rem;
            transition: 0.2s;
            font-family: inherit;
        }
        .form-control:focus { border-color: #2E8B57; outline: none; box-shadow: 0 0 0 4px rgba(46, 139, 87, 0.1); }

        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            max-height: 150px;
            overflow-y: auto;
            padding: 4px;
        }
        .admin-card {
            border: 2px solid #f3f4f6;
            padding: 10px;
            border-radius: 12px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        .admin-card input { display: none; }
        .admin-card.selected { background: #ecfdf5; border-color: #10b981; color: #064e3b; }

        .start-btn {
            width: 100%;
            background: #2E8B57;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
        }
        .start-btn:hover { background: #1e6b42; transform: scale(1.02); }
    </style>
</head>
<body>
    <div class="main-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="messaging-wrapper <?= $selected_thread_id ? 'view-chat' : 'view-list' ?>">
                <div class="msg-sidebar">
                    <div class="msg-sidebar-header">
                        <h2>Support Center</h2>
                        <button class="new-thread-btn" onclick="openSupportModal()">
                            <i class="fas fa-plus"></i> New Inquiry
                        </button>
                    </div>
                    <div class="contact-list">
                        <?php foreach ($threads as $thread): ?>
                            <div class="contact-item <?= ($selected_thread_id == $thread['id']) ? 'active' : '' ?>" 
                                 onclick="window.location.href='?thread_id=<?= $thread['id'] ?>'">
                                <div class="contact-avatar"><i class="fas fa-headset"></i></div>
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
                                <div class="header-name"><?= htmlspecialchars($selected_thread['title']) ?></div>
                            </div>
                        </div>
                        <div class="chat-messages" id="chatMessages"></div>
                        <div class="chat-input-area">
                            <form id="messageForm">
                                <input type="file" id="fileInput" style="display:none;">
                                <div class="input-pill-container">
                                    <button type="button" class="icon-btn" id="attachBtn"><i class="fas fa-plus"></i></button>
                                    <textarea class="chat-input" id="messageInput" placeholder="Type a message..." rows="1"></textarea>
                                    <div class="input-actions">
                                        <button type="submit" class="icon-btn send-btn"><i class="fas fa-paper-plane"></i></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; opacity:0.6;">
                            <i class="fas fa-stethoscope fa-4x" style="margin-bottom:20px; color:#2E8B57;"></i>
                            <h3>Select a clinical inquiry</h3>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PREMUM MODAL -->
            <div id="supportModal" class="modal-overlay">
                <div class="modal-container">
                    <div class="modal-header">
                        <h3>New Clinical Inquiry</h3>
                        <button class="close-modal" onclick="closeSupportModal()">&times;</button>
                    </div>
                    <form method="POST">
                        <div class="form-group">
                            <label>Inquiry Subject</label>
                            <input type="text" name="thread_title" class="form-control" placeholder="e.g. Nutritional Analysis Query" required>
                        </div>
                        <div class="form-group">
                            <label>Designated Admins</label>
                            <div class="admin-grid">
                                <?php foreach ($admins as $admin): ?>
                                    <label class="admin-card">
                                        <input type="checkbox" name="admins[]" value="<?= $admin['id'] ?>" onchange="toggleCard(this)">
                                        <i class="fas fa-user-md"></i>
                                        <span><?= htmlspecialchars($admin['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Initial Message</label>
                            <textarea name="initial_message" class="form-control" rows="3" placeholder="Explain your inquiry in detail..." required></textarea>
                        </div>
                        <button type="submit" name="create_thread" class="start-btn">Start Conversation</button>
                    </form>
                </div>
            </div>

            <script>const BASE_URL = '<?= rtrim(dirname($_SERVER['PHP_SELF']), '/') ?>/';</script>
            <script src="scripts/internal-chat-controller.js?v=101"></script>
            <script>
                function openSupportModal() {
                    const m = document.getElementById('supportModal');
                    m.style.display = 'flex';
                    setTimeout(() => m.classList.add('active'), 10);
                }
                function closeSupportModal() {
                    const m = document.getElementById('supportModal');
                    m.classList.remove('active');
                    setTimeout(() => m.style.display = 'none', 400);
                }
                function toggleCard(input) {
                    input.closest('.admin-card').classList.toggle('selected', input.checked);
                }
                document.addEventListener('DOMContentLoaded', () => {
                    <?php if ($selected_thread_id): ?>
                    new ChatController(<?= $staff_id ?>, '<?= $user_role ?>', <?= $selected_thread_id ?>);
                    <?php endif; ?>
                });
            </script>
        </div>
    </div>
</body>
</html>
