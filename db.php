<?php
// db.php : Configuration et connexion PDO

// Constantes de sécurité
define('SECRET_KEY', 'GroupeXpert2025!'); // Clé secrète pour HMAC
define('TOKEN_PREFIX', 'XPERT'); // Préfixe pour les tokens
define('TOKEN_EXPIRATION', 7200); // secondes (2 heures)

// Configuration base
$host = "localhost";
$dbname = "pointage";
$username = "root";
$password = "";

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Erreur BDD: " . $e->getMessage());
    // Ne PAS exposer le détail en prod
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Service indisponible']);
    exit;
}