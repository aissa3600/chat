<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input text data
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $age = intval($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $looking_for = trim($_POST['looking_for'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    // Validation
    if (empty($name)) {
        $errors[] = "Le nom est obligatoire.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Un email valide est obligatoire.";
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères.";
    }
    if ($age < 18) {
        $errors[] = "Vous devez avoir au moins 18 ans pour vous inscrire.";
    }
    if (empty($city)) {
        $errors[] = "La ville est obligatoire.";
    }
    if (!in_array($gender, ['Homme', 'Femme', 'Autre'])) {
        $errors[] = "Le genre sélectionné est invalide.";
    }
    if (!in_array($looking_for, ['Homme', 'Femme', 'Tous'])) {
        $errors[] = "La préférence de recherche sélectionnée est invalide.";
    }

    // Check if email already exists
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Cet email est déjà utilisé par un autre compte.";
        }
    }

    // Handle profile picture upload
    $profile_pic = 'default.svg';
    if (empty($errors) && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_pic'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Erreur lors du téléchargement de l'image.";
        } else {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_info = pathinfo($file['name']);
            $extension = strtolower($file_info['extension'] ?? '');

            // Verify extension
            if (!in_array($extension, $allowed_extensions)) {
                $errors[] = "Extension d'image non autorisée (JPG, PNG, GIF, WEBP uniquement).";
            }

            // Verify MIME Type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (!in_array($mime_type, $allowed_mimes)) {
                $errors[] = "Type de fichier invalide. Veuillez soumettre une image réelle.";
            }

            // Check size (max 3MB)
            if ($file['size'] > 3 * 1024 * 1024) {
                $errors[] = "L'image est trop volumineuse (maximum 3 Mo).";
            }

            if (empty($errors)) {
                // Ensure directory exists
                $upload_dir = 'assets/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Generate unique file name
                $new_filename = uniqid('profile_', true) . '.' . $extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $profile_pic = $new_filename;
                } else {
                    $errors[] = "Impossible d'enregistrer l'image sur le serveur.";
                }
            }
        }
    }

    // Register user if no errors
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password, age, gender, looking_for, city, bio, profile_pic)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name,
                $email,
                $password_hash,
                $age,
                $gender,
                $looking_for,
                $city,
                empty($bio) ? null : $bio,
                $profile_pic
            ]);

            // Get new user ID and login
            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $user_id;

            header("Location: index.php");
            exit();

        } catch (PDOException $e) {
            $errors[] = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    }
}

// Render Header
require_once 'header.php';
?>

<div class="auth-container glass-panel">
    <h2 class="page-title">Rejoignez l'aventure</h2>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Créez votre compte en quelques instants et découvrez des célibataires autour de vous.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <div>
                <?php foreach ($errors as $error): ?>
                    <p>• <?php echo clean($error); ?></p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form action="register.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="name">Nom complet ou Pseudo</label>
            <input type="text" id="name" name="name" class="form-control" placeholder="Ex: Julie" value="<?php echo isset($_POST['name']) ? clean($_POST['name']) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Adresse Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="Julie@exemple.com" value="<?php echo isset($_POST['email']) ? clean($_POST['email']) : ''; ?>" required>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <label for="age">Âge</label>
                <input type="number" id="age" name="age" class="form-control" min="18" max="120" placeholder="Ex: 24" value="<?php echo isset($_POST['age']) ? clean($_POST['age']) : ''; ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="gender">Je suis</label>
                <select id="gender" name="gender" class="form-control" required>
                    <option value="">Sélectionner...</option>
                    <option value="Homme" <?php echo isset($_POST['gender']) && $_POST['gender'] === 'Homme' ? 'selected' : ''; ?>>Un homme</option>
                    <option value="Femme" <?php echo isset($_POST['gender']) && $_POST['gender'] === 'Femme' ? 'selected' : ''; ?>>Une femme</option>
                    <option value="Autre" <?php echo isset($_POST['gender']) && $_POST['gender'] === 'Autre' ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
            <div class="form-group">
                <label for="looking_for">Je cherche</label>
                <select id="looking_for" name="looking_for" class="form-control" required>
                    <option value="">Sélectionner...</option>
                    <option value="Femme" <?php echo isset($_POST['looking_for']) && $_POST['looking_for'] === 'Femme' ? 'selected' : ''; ?>>Des femmes</option>
                    <option value="Homme" <?php echo isset($_POST['looking_for']) && $_POST['looking_for'] === 'Homme' ? 'selected' : ''; ?>>Des hommes</option>
                    <option value="Tous" <?php echo isset($_POST['looking_for']) && $_POST['looking_for'] === 'Tous' ? 'selected' : ''; ?>>Tout le monde</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label for="city">Ville</label>
            <input type="text" id="city" name="city" class="form-control" placeholder="Ex: Paris" value="<?php echo isset($_POST['city']) ? clean($_POST['city']) : ''; ?>" required>
        </div>

        <div class="form-group">
            <label for="bio">Ma description (Bio)</label>
            <textarea id="bio" name="bio" class="form-control" placeholder="Dites-nous en plus sur vous, vos passions, ce que vous recherchez..."><?php echo isset($_POST['bio']) ? clean($_POST['bio']) : ''; ?></textarea>
        </div>

        <div class="form-group">
            <label>Photo de profil</label>
            <div class="file-upload">
                <label for="profile_pic" class="file-upload-label">
                    <span>📷 Choisir une photo de profil</span>
                </label>
                <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
            </div>
            <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Recommandé : image carrée, max 3 Mo (JPG, PNG, WEBP).</small>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1.5rem;">S'inscrire</button>
    </form>

    <div style="text-align: center; margin-top: 1.5rem; font-size: 0.95rem; color: var(--text-muted);">
        Vous avez déjà un compte ? <a href="login.php" style="color: var(--primary-color); font-weight: 600;">Se connecter</a>
    </div>
</div>

<?php require_once 'footer.php'; ?>
