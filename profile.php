<?php
require_once 'config.php';

// Check if user is logged in
check_auth();

$user_id = $_SESSION['user_id'];
$errors = [];
$success = "";

// Fetch current user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $age = intval($_POST['age'] ?? 0);
    $gender = trim($_POST['gender'] ?? '');
    $looking_for = trim($_POST['looking_for'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    // Validation
    if (empty($name)) {
        $errors[] = "Le nom est obligatoire.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Un email valide est obligatoire.";
    }
    if ($age < 18) {
        $errors[] = "Vous devez avoir au moins 18 ans.";
    }
    if (empty($city)) {
        $errors[] = "La ville est obligatoire.";
    }
    if (!in_array($gender, ['Homme', 'Femme', 'Autre'])) {
        $errors[] = "Le genre est invalide.";
    }
    if (!in_array($looking_for, ['Homme', 'Femme', 'Tous'])) {
        $errors[] = "La préférence de recherche est invalide.";
    }

    // Check if email already exists for another user
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = "Cette adresse email est déjà prise par un autre compte.";
        }
    }

    // Handle Profile Pic Upload
    $profile_pic = $user['profile_pic'];
    if (empty($errors) && isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_pic'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Erreur lors du transfert de la photo.";
        } else {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $file_info = pathinfo($file['name']);
            $extension = strtolower($file_info['extension'] ?? '');

            if (!in_array($extension, $allowed_extensions)) {
                $errors[] = "L'extension de fichier n'est pas autorisée (JPG, PNG, GIF, WEBP uniquement).";
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

            if (!in_array($mime_type, $allowed_mimes)) {
                $errors[] = "Le fichier doit être une image valide.";
            }

            if ($file['size'] > 3 * 1024 * 1024) {
                $errors[] = "L'image ne doit pas dépasser 3 Mo.";
            }

            if (empty($errors)) {
                $upload_dir = 'assets/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $new_filename = uniqid('profile_', true) . '.' . $extension;
                $destination = $upload_dir . $new_filename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    // Delete old profile picture if it wasn't default
                    if ($user['profile_pic'] !== 'default.svg' && file_exists($upload_dir . $user['profile_pic'])) {
                        @unlink($upload_dir . $user['profile_pic']);
                    }
                    $profile_pic = $new_filename;
                    $user['profile_pic'] = $profile_pic; // Update local reference for immediate rendering
                } else {
                    $errors[] = "Erreur de stockage de l'image.";
                }
            }
        }
    }

    // Process Update
    if (empty($errors)) {
        try {
            // Build query dynamically based on password change
            if (!empty($new_password)) {
                if (strlen($new_password) < 6) {
                    $errors[] = "Le nouveau mot de passe doit faire au moins 6 caractères.";
                } else {
                    $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, password = ?, age = ?, gender = ?, looking_for = ?, city = ?, bio = ?, profile_pic = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $email, $password_hash, $age, $gender, $looking_for, $city, empty($bio) ? null : $bio, $profile_pic, $user_id]);
                }
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, email = ?, age = ?, gender = ?, looking_for = ?, city = ?, bio = ?, profile_pic = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $email, $age, $gender, $looking_for, $city, empty($bio) ? null : $bio, $profile_pic, $user_id]);
            }

            if (empty($errors)) {
                $success = "Votre profil a été mis à jour avec succès.";
                $_SESSION['user_name'] = $name; // Update session name reference
                
                // Fetch updated user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            }

        } catch (PDOException $e) {
            $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
        }
    }
}

// Render Header
require_once 'header.php';
?>

<h2 class="page-title" style="margin-bottom: 2rem;">Paramètres de mon profil</h2>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <div>
            <?php foreach ($errors as $error): ?>
                <p>• <?php echo clean($error); ?></p>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <div>
            <p>✔ <?php echo clean($success); ?></p>
        </div>
    </div>
<?php endif; ?>

<div class="glass-panel">
    <form action="profile.php" method="POST" enctype="multipart/form-data">
        <div class="profile-edit-layout">
            <!-- Left Column: Avatar Display & File Upload -->
            <div class="profile-pic-container">
                <img src="assets/uploads/<?php echo clean($user['profile_pic']); ?>" alt="Photo de <?php echo clean($user['name']); ?>" class="profile-edit-pic">
                
                <div class="form-group">
                    <label>Changer ma photo</label>
                    <div class="file-upload">
                        <label for="profile_pic" class="file-upload-label">
                            <span>📷 Télécharger</span>
                        </label>
                        <input type="file" id="profile_pic" name="profile_pic" accept="image/*">
                    </div>
                </div>
                <small style="color: var(--text-muted); display: block; margin-top: 0.5rem;">
                    Format JPG, PNG ou WEBP. Max 3 Mo.
                </small>
            </div>

            <!-- Right Column: Account Fields -->
            <div class="profile-fields-container">
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Nom / Pseudo</label>
                        <input type="text" id="name" name="name" class="form-control" value="<?php echo clean($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Adresse Email</label>
                        <input type="email" id="email" name="email" class="form-control" value="<?php echo clean($user['email']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="age">Âge</label>
                        <input type="number" id="age" name="age" class="form-control" min="18" max="120" value="<?php echo clean($user['age']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="city">Ville</label>
                        <input type="text" id="city" name="city" class="form-control" value="<?php echo clean($user['city']); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="gender">Je suis</label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="Homme" <?php echo $user['gender'] === 'Homme' ? 'selected' : ''; ?>>Un homme</option>
                            <option value="Femme" <?php echo $user['gender'] === 'Femme' ? 'selected' : ''; ?>>Une femme</option>
                            <option value="Autre" <?php echo $user['gender'] === 'Autre' ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="looking_for">Je recherche</label>
                        <select id="looking_for" name="looking_for" class="form-control" required>
                            <option value="Femme" <?php echo $user['looking_for'] === 'Femme' ? 'selected' : ''; ?>>Des femmes</option>
                            <option value="Homme" <?php echo $user['looking_for'] === 'Homme' ? 'selected' : ''; ?>>Des hommes</option>
                            <option value="Tous" <?php echo $user['looking_for'] === 'Tous' ? 'selected' : ''; ?>>Tout le monde</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="bio">Ma description (Bio)</label>
                    <textarea id="bio" name="bio" class="form-control" placeholder="Parlez-nous de vous..."><?php echo clean($user['bio']); ?></textarea>
                </div>

                <div class="form-group" style="margin-top: 2rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                    <label for="new_password">Modifier le mot de passe <span style="font-weight: 400; color: var(--text-muted);">(Laissez vide pour conserver l'actuel)</span></label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Nouveau mot de passe">
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2.5rem; justify-content: flex-end;">
                    <a href="index.php" class="btn btn-secondary">Retour</a>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>
