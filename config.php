<?php
// Session configuration and initialization
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session cookie parameters if possible
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dating_db');

try {
    // Establish PDO connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    // In production, log the error and display a user-friendly message.
    // For development, we output the error details.
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

/**
 * Sanitizes outputs to protect against XSS (Cross-Site Scripting).
 *
 * @param string|null $data The raw input string
 * @return string The sanitized string
 */
function clean($data) {
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Checks if the user is authenticated.
 * If not, redirects to the login page.
 */
function check_auth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Formats a timestamp to a friendly French relative or absolute date.
 *
 * @param string $timestamp SQL formatted datetime
 * @return string Friendly date string
 */
function friendly_date($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) {
        return "À l'instant";
    } elseif ($diff < 3600) {
        $mins = round($diff / 60);
        return "Il y a " . $mins . " min";
    } elseif ($diff < 86400) {
        $hours = round($diff / 3600);
        return "Il y a " . $hours . " h";
    } else {
        return date('d/m/Y à H:i', $time);
    }
}
?>
