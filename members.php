<?php
require_once 'config.php';

// Check auth
check_auth();

$user_id = $_SESSION['user_id'];

// Default filter values
$search_city = isset($_GET['city']) ? trim($_GET['city']) : '';
$min_age = isset($_GET['min_age']) && $_GET['min_age'] !== '' ? intval($_GET['min_age']) : 18;
$max_age = isset($_GET['max_age']) && $_GET['max_age'] !== '' ? intval($_GET['max_age']) : 99;

// Build search query securely
$sql = "SELECT id, name, age, city, bio, profile_pic FROM users WHERE id != ?";
$params = [$user_id];

if ($search_city !== '') {
    $sql .= " AND city LIKE ?";
    $params[] = '%' . $search_city . '%';
}

if ($min_age >= 18) {
    $sql .= " AND age >= ?";
    $params[] = $min_age;
}

if ($max_age <= 120) {
    $sql .= " AND age <= ?";
    $params[] = $max_age;
}

$sql .= " ORDER BY id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $members = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur de recherche : " . $e->getMessage());
}

// Render Header
require_once 'header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h2 class="page-title" style="margin-bottom: 0;">Découvrir des célibataires</h2>
    <span style="color: var(--text-muted); font-weight: 500; font-size: 0.95rem;">
        <?php echo count($members); ?> membre(s) trouvé(s)
    </span>
</div>

<!-- Filters Bar -->
<div class="filters-bar">
    <form action="members.php" method="GET" class="filters-form">
        <div class="form-group" style="margin-bottom: 0;">
            <label for="city" style="font-size: 0.85rem;">Ville</label>
            <input type="text" id="city" name="city" class="form-control" placeholder="Ex: Paris" value="<?php echo clean($search_city); ?>">
        </div>
        
        <div class="form-group" style="margin-bottom: 0;">
            <label for="min_age" style="font-size: 0.85rem;">Âge minimum</label>
            <input type="number" id="min_age" name="min_age" class="form-control" min="18" max="100" placeholder="18" value="<?php echo $min_age != 18 ? $min_age : ''; ?>">
        </div>

        <div class="form-group" style="margin-bottom: 0;">
            <label for="max_age" style="font-size: 0.85rem;">Âge maximum</label>
            <input type="number" id="max_age" name="max_age" class="form-control" min="18" max="100" placeholder="99" value="<?php echo $max_age != 99 ? $max_age : ''; ?>">
        </div>

        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">Rechercher</button>
            <?php if ($search_city !== '' || $min_age != 18 || $max_age != 99): ?>
                <a href="members.php" class="btn btn-secondary" style="padding: 0.75rem 1rem;" title="Réinitialiser les filtres">✖</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Members Grid -->
<?php if (!empty($members)): ?>
    <div class="members-grid">
        <?php foreach ($members as $member): ?>
            <div class="member-card">
                <div class="member-card-img">
                    <img src="assets/uploads/<?php echo clean($member['profile_pic']); ?>" alt="Photo de <?php echo clean($member['name']); ?>">
                    <div class="member-card-overlay">
                        <div class="member-info">
                            <h3><?php echo clean($member['name']); ?>, <span class="age"><?php echo clean($member['age']); ?></span></h3>
                            <div class="member-city"><?php echo clean($member['city']); ?></div>
                        </div>
                    </div>
                </div>
                
                <div style="padding: 1.2rem; background-color: var(--bg-secondary); flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.2rem; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                        <?php echo !empty($member['bio']) ? clean($member['bio']) : 'Aucune description disponible.'; ?>
                    </p>
                    <div class="member-actions">
                        <a href="messages.php?chat_with=<?php echo $member['id']; ?>" class="card-btn card-btn-primary">
                            Discuter 💬
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="glass-panel" style="text-align: center; padding: 4rem 2rem; color: var(--text-muted);">
        <p style="font-size: 4rem; margin-bottom: 1.5rem; opacity: 0.3;">🔍</p>
        <h3>Aucun membre ne correspond à vos critères de recherche</h3>
        <p style="margin-top: 0.5rem;">Essayez d'élargir votre zone géographique ou de modifier les tranches d'âge.</p>
        <a href="members.php" class="btn btn-secondary" style="margin-top: 1.5rem;">Afficher tous les membres</a>
    </div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
