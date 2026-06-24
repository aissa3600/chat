<?php
require_once 'config.php';

$is_logged_in = isset($_SESSION['user_id']);

if ($is_logged_in) {
    // ----------------------------------------------------
    // LOGGED IN DASHBOARD VIEW
    // ----------------------------------------------------
    $user_id = $_SESSION['user_id'];

    try {
        // Fetch current user details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user) {
            // Safe fallback if user got deleted from DB
            session_destroy();
            header("Location: login.php");
            exit();
        }

        // Statistics
        // 1. Total members count
        $stmt_members = $pdo->query("SELECT COUNT(*) as total FROM users WHERE id != " . (int)$user_id);
        $total_members = $stmt_members->fetch()['total'];

        // 2. Unread messages count for this user
        $stmt_unread = $pdo->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt_unread->execute([$user_id]);
        $total_unread = $stmt_unread->fetch()['total'];

        // 3. Conversations count
        $stmt_convs = $pdo->prepare("
            SELECT COUNT(DISTINCT chat_partner) as total FROM (
                SELECT sender_id as chat_partner FROM messages WHERE receiver_id = ?
                UNION
                SELECT receiver_id as chat_partner FROM messages WHERE sender_id = ?
            ) as partners
        ");
        $stmt_convs->execute([$user_id, $user_id]);
        $total_convs = $stmt_convs->fetch()['total'];

        // Fetch recent members to show in dashboard (excluding ourselves)
        // If user has a preference, prioritize it
        $gender_pref = $user['looking_for'];
        if ($gender_pref === 'Tous') {
            $stmt_recent = $pdo->prepare("SELECT id, name, age, city, profile_pic FROM users WHERE id != ? ORDER BY id DESC LIMIT 3");
            $stmt_recent->execute([$user_id]);
        } else {
            $stmt_recent = $pdo->prepare("SELECT id, name, age, city, profile_pic FROM users WHERE id != ? AND gender = ? ORDER BY id DESC LIMIT 3");
            $stmt_recent->execute([$user_id, $gender_pref]);
        }
        $recent_members = $stmt_recent->fetchAll();
        
        // If search returned empty, fall back to any users
        if (empty($recent_members)) {
            $stmt_recent = $pdo->prepare("SELECT id, name, age, city, profile_pic FROM users WHERE id != ? ORDER BY id DESC LIMIT 3");
            $stmt_recent->execute([$user_id]);
            $recent_members = $stmt_recent->fetchAll();
        }

    } catch (PDOException $e) {
        die("Erreur de base de données : " . $e->getMessage());
    }

} else {
    // Landing page (guest mode)
}

require_once 'header.php';
?>

<?php if ($is_logged_in): ?>
    <!-- Logged In: Dashboard -->
    <h2 class="page-title" style="margin-bottom: 2rem;">Tableau de bord</h2>

    <div class="dashboard-grid">
        <!-- Sidebar: User Info Summary -->
        <div class="dashboard-sidebar">
            <div class="glass-panel profile-summary">
                <img src="assets/uploads/<?php echo clean($user['profile_pic']); ?>" alt="Photo de <?php echo clean($user['name']); ?>" class="profile-summary-img">
                <h2><?php echo clean($user['name']); ?>, <span style="font-weight: 400; color: var(--text-muted);"><?php echo clean($user['age']); ?> ans</span></h2>
                <div class="member-city" style="justify-content: center; font-size: 1rem; margin-bottom: 1.5rem;">
                    <?php echo clean($user['city']); ?>
                </div>
                
                <?php if (!empty($user['bio'])): ?>
                    <p style="font-style: italic; color: var(--text-muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                        "<?php echo clean($user['bio']); ?>"
                    </p>
                <?php else: ?>
                    <p style="color: rgba(255,255,255,0.3); margin-bottom: 1.5rem; font-size: 0.95rem;">
                        Ajoutez une description dans votre profil pour attirer plus de regards !
                    </p>
                <?php endif; ?>

                <a href="profile.php" class="btn btn-secondary btn-block">Modifier mon profil</a>

                <div class="stat-row">
                    <div class="stat-card">
                        <div class="value"><?php echo $total_convs; ?></div>
                        <div class="label">Discussions</div>
                    </div>
                    <div class="stat-card">
                        <div class="value" style="color: <?php echo $total_unread > 0 ? 'var(--primary-color)' : 'var(--text-muted)'; ?>">
                            <?php echo $total_unread; ?>
                        </div>
                        <div class="label">Non lus</div>
                    </div>
                </div>
            </div>

            <!-- Quick Action Links -->
            <ul class="sidebar-menu">
                <li><a href="members.php">🔍 Rechercher des célibataires</a></li>
                <li><a href="messages.php">✉ Voir mes messages privés</a></li>
                <li><a href="profile.php">👤 Paramètres de compte</a></li>
                <li><a href="logout.php" style="color: var(--danger);">🚪 Se déconnecter</a></li>
            </ul>
        </div>

        <!-- Main Panel: Matches/Recent Signups -->
        <div class="dashboard-main">
            <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem;">
                <h3 style="font-size: 1.4rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    ✨ Profils recommandés pour vous
                </h3>

                <?php if (!empty($recent_members)): ?>
                    <div class="members-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($recent_members as $member): ?>
                            <div class="member-card" style="min-height: 320px;">
                                <div class="member-card-img" style="height: 220px;">
                                    <img src="assets/uploads/<?php echo clean($member['profile_pic']); ?>" alt="Photo de <?php echo clean($member['name']); ?>">
                                    <div class="member-card-overlay">
                                        <div class="member-info">
                                            <h3 style="font-size: 1.1rem; color: white;">
                                                <?php echo clean($member['name']); ?>, <span class="age"><?php echo clean($member['age']); ?></span>
                                            </h3>
                                            <div class="member-city" style="font-size: 0.8rem; margin-bottom: 0;">
                                                <?php echo clean($member['city']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div style="padding: 0.75rem; background-color: var(--bg-secondary); display: flex;">
                                    <a href="messages.php?chat_with=<?php echo $member['id']; ?>" class="card-btn card-btn-primary" style="padding: 0.5rem; font-size: 0.85rem;">
                                        Discuter 💬
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: right; margin-top: 1.5rem;">
                        <a href="members.php" style="color: var(--primary-color); font-weight: 600; font-size: 0.95rem;">Voir tous les membres &rarr;</a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                        <p style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;">🔎</p>
                        <p>Aucun nouveau membre pour le moment. Invitez vos amis à s'inscrire !</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Welcome/Tips banner -->
            <div class="glass-panel" style="padding: 2rem; background: linear-gradient(135deg, rgba(255, 62, 108, 0.05) 0%, rgba(124, 58, 237, 0.05) 100%);">
                <h3 style="margin-bottom: 0.75rem; color: var(--primary-color);">💡 Conseil de rencontre</h3>
                <p style="color: var(--text-muted); font-size: 0.95rem; line-height: 1.5;">
                    Les profils ayant une biographie complète et une photo claire reçoivent en moyenne <strong>5 fois plus de messages</strong>. 
                    Prenez quelques minutes pour exprimer qui vous êtes réellement dans la rubrique <a href="profile.php" style="color: var(--primary-color); font-weight: 600; text-decoration: underline;">Modifier mon profil</a>.
                </p>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Guest Mode: Landing Page -->
    <div class="hero">
        <div class="hero-content">
            <span class="hero-badge">Rejoignez plus de 10 000 célibataires</span>
            <h1>Trouvez des connexions <span>authentiques</span></h1>
            <p>
                Kivi est une plateforme de rencontre moderne, sécurisée et conçue pour vous aider à rencontrer des personnes qui partagent réellement vos passions et votre vision de la vie.
            </p>
            <div class="hero-cta">
                <a href="register.php" class="btn btn-primary">Créer un compte gratuitement</a>
                <a href="login.php" class="btn btn-secondary">Se connecter</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
