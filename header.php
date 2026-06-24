<?php
require_once 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['user_id']);
$user_data = null;

if ($is_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        if (!$user_data) {
            // If user session is active but user not in DB (deleted, etc.), clear session
            session_destroy();
            header("Location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        // Silent error, fallback
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kivi - Rencontres Modernes</title>
    <meta name="description" content="Trouvez l'amour et des connexions authentiques sur Kivi, le site de rencontre moderne et sécurisé.">
    <!-- Google Fonts & Stylesheets -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div class="nav-container">
            <a href="index.php" class="logo">Kivi</a>
            <nav>
                <ul>
                    <?php if ($is_logged_in): ?>
                        <li class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                            <a href="index.php">Tableau de bord</a>
                        </li>
                        <li class="<?php echo $current_page == 'members.php' ? 'active' : ''; ?>">
                            <a href="members.php">Découvrir</a>
                        </li>
                        <li class="<?php echo $current_page == 'messages.php' ? 'active' : ''; ?>">
                            <a href="messages.php">Messages</a>
                        </li>
                        <li class="<?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                            <a href="profile.php" class="nav-profile">
                                <img src="assets/uploads/<?php echo clean($user_data['profile_pic']); ?>" alt="Photo de <?php echo clean($user_data['name']); ?>">
                                <span><?php echo clean($user_data['name']); ?></span>
                            </a>
                        </li>
                        <li>
                            <a href="logout.php" class="nav-btn-outline" style="padding: 0.4rem 1rem;">Déconnexion</a>
                        </li>
                    <?php else: ?>
                        <li class="<?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                            <a href="index.php">Accueil</a>
                        </li>
                        <li class="<?php echo $current_page == 'login.php' ? 'active' : ''; ?>">
                            <a href="login.php">Connexion</a>
                        </li>
                        <li class="<?php echo $current_page == 'register.php' ? 'active' : ''; ?>">
                            <a href="register.php" class="nav-btn">S'inscrire</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main>
