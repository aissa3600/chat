<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Veuillez entrer une adresse email valide.";
    }
    if (empty($password)) {
        $errors[] = "Veuillez entrer votre mot de passe.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, password, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Password is correct, start user session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];

                header("Location: index.php");
                exit();
            } else {
                $errors[] = "Adresse email ou mot de passe incorrect.";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur de connexion : " . $e->getMessage();
        }
    }
}

// Render Header
require_once 'header.php';
?>

<div class="auth-container glass-panel">
    <h2 class="page-title">Ravi de vous revoir !</h2>
    <p style="color: var(--text-muted); margin-bottom: 2rem;">Connectez-vous pour voir qui vous a envoyé un message et faire de nouvelles rencontres.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <div>
                <?php foreach ($errors as $error): ?>
                    <p>• <?php echo clean($error); ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form action="login.php" method="POST">
        <div class="form-group">
            <label for="email">Adresse Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="Julie@exemple.com" value="<?php echo isset($_POST['email']) ? clean($_POST['email']) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top: 2rem;">Se connecter</button>
    </form>

    <div style="text-align: center; margin-top: 2rem; font-size: 0.95rem; color: var(--text-muted);">
        Nouveau sur Kivi ? <a href="register.php" style="color: var(--primary-color); font-weight: 600;">Créer un compte</a>
    </div>
</div>

<?php require_once 'footer.php'; ?>
