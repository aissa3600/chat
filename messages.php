<?php
require_once 'config.php';

// Check auth
check_auth();

$user_id = $_SESSION['user_id'];
$active_partner_id = isset($_GET['chat_with']) ? intval($_GET['chat_with']) : 0;
$active_partner = null;

try {
    // 1. If we have an active chat_with parameter, fetch the partner's details
    if ($active_partner_id > 0) {
        $stmt_partner = $pdo->prepare("SELECT id, name, profile_pic, city, age FROM users WHERE id = ? AND id != ?");
        $stmt_partner->execute([$active_partner_id, $user_id]);
        $active_partner = $stmt_partner->fetch();
        if (!$active_partner) {
            // Reset parameter if user doesn't exist
            $active_partner_id = 0;
        }
    }

    // 2. Fetch all users with whom we have had a conversation
    $stmt_convs = $pdo->prepare("
        SELECT 
            u.id, 
            u.name, 
            u.profile_pic,
            (
                SELECT message_text 
                FROM messages 
                WHERE (sender_id = u.id AND receiver_id = :my_id) 
                   OR (sender_id = :my_id AND receiver_id = u.id) 
                ORDER BY sent_at DESC LIMIT 1
            ) as last_message,
            (
                SELECT sent_at 
                FROM messages 
                WHERE (sender_id = u.id AND receiver_id = :my_id) 
                   OR (sender_id = :my_id AND receiver_id = u.id) 
                ORDER BY sent_at DESC LIMIT 1
            ) as last_time,
            (
                SELECT COUNT(*) 
                FROM messages 
                WHERE sender_id = u.id AND receiver_id = :my_id AND is_read = 0
            ) as unread_count
        FROM users u
        WHERE u.id IN (
            SELECT DISTINCT sender_id FROM messages WHERE receiver_id = :my_id
            UNION
            SELECT DISTINCT receiver_id FROM messages WHERE sender_id = :my_id
        )
        ORDER BY last_time DESC
    ");
    $stmt_convs->execute(['my_id' => $user_id]);
    $conversations = $stmt_convs->fetchAll();

    // 3. If there is no active chat_with parameter, but we have conversations,
    // load the most recent conversation partner by default
    if ($active_partner_id === 0 && !empty($conversations)) {
        $active_partner_id = intval($conversations[0]['id']);
        $active_partner = $conversations[0];
    }

    // 4. If we chose a partner but they are NOT in the active conversations list yet (first message)
    // we manually add them at the top of the local conversations array to render them in the sidebar
    $partner_in_list = false;
    foreach ($conversations as $conv) {
        if (intval($conv['id']) === $active_partner_id) {
            $partner_in_list = true;
            break;
        }
    }

    if ($active_partner_id > 0 && !$partner_in_list && $active_partner) {
        // Prepend new temporary partner to the list
        array_unshift($conversations, [
            'id' => $active_partner['id'],
            'name' => $active_partner['name'],
            'profile_pic' => $active_partner['profile_pic'],
            'last_message' => 'Commencer la conversation...',
            'last_time' => date('Y-m-d H:i:s'),
            'unread_count' => 0
        ]);
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Render Header
require_once 'header.php';
?>

<div class="messaging-layout glass-panel" style="padding: 0;">
    <!-- Left Column: Conversations list -->
    <div class="conversations-sidebar">
        <div class="sidebar-header">
            Messagerie Directe
        </div>
        <ul class="conversations-list">
            <?php if (!empty($conversations)): ?>
                <?php foreach ($conversations as $conv): ?>
                    <li class="conversation-item <?php echo $active_partner_id === intval($conv['id']) ? 'active' : ''; ?>">
                        <a href="messages.php?chat_with=<?php echo $conv['id']; ?>" class="conversation-link">
                            <img src="assets/uploads/<?php echo clean($conv['profile_pic']); ?>" alt="Photo de <?php echo clean($conv['name']); ?>" class="conv-avatar">
                            <div class="conv-details">
                                <div class="conv-meta">
                                    <span class="conv-name"><?php echo clean($conv['name']); ?></span>
                                    <?php if (!empty($conv['last_time'])): ?>
                                        <span class="conv-time"><?php echo friendly_date($conv['last_time']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="conv-preview">
                                    <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo clean($conv['last_message']); ?>
                                    </span>
                                    <?php if (intval($conv['unread_count']) > 0): ?>
                                        <span class="conv-unread"><?php echo $conv['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li style="padding: 2rem 1rem; text-align: center; color: var(--text-muted); font-size: 0.9rem;">
                    Aucune discussion en cours.<br>Allez dans l'annuaire pour envoyer un message !
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <!-- Right Column: Chat Window -->
    <div class="chat-area">
        <?php if ($active_partner): ?>
            <!-- Partner info header -->
            <div class="chat-header">
                <div class="chat-partner">
                    <img src="assets/uploads/<?php echo clean($active_partner['profile_pic']); ?>" alt="Photo de <?php echo clean($active_partner['name']); ?>">
                    <div>
                        <div class="chat-partner-name"><?php echo clean($active_partner['name']); ?></div>
                        <div class="chat-partner-status"><?php echo isset($active_partner['age']) ? clean($active_partner['age']) . ' ans - ' . clean($active_partner['city']) : 'En ligne'; ?></div>
                    </div>
                </div>
                <div>
                    <a href="members.php" class="btn btn-secondary" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Fermer</a>
                </div>
            </div>

            <!-- Messages lists (Polled by JS) -->
            <div class="chat-messages" id="chat-messages">
                <!-- Javascript will load bubbles here -->
                <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    Chargement des messages...
                </div>
            </div>

            <!-- Input message bar -->
            <div class="chat-input-bar">
                <form id="chat-form" data-partner-id="<?php echo $active_partner_id; ?>" data-user-id="<?php echo $user_id; ?>" class="chat-input-form">
                    <input type="text" id="message-input" class="form-control" placeholder="Écrivez votre message..." autocomplete="off" required>
                    <button type="submit" class="btn btn-primary" style="padding: 0 1.5rem;">Envoyer 🚀</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Empty state when no conversation exists/is selected -->
            <div class="empty-chat">
                <div class="empty-chat-icon">✉</div>
                <h3>Vos messages privés</h3>
                <p style="margin-top: 0.5rem; max-width: 400px;">Sélectionnez une discussion à gauche, ou parcourez l'annuaire des membres pour initier un contact.</p>
                <a href="members.php" class="btn btn-primary" style="margin-top: 1.5rem;">Rechercher des membres</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Dynamic Chat Operations -->
<script src="assets/js/chat.js"></script>

<?php require_once 'footer.php'; ?>
