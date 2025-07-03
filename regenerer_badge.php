<?php
require_once 'db.php';
require_once 'badge_function.php';

date_default_timezone_set('Europe/Paris');

header('Content-Type: application/json');

$employe_id = $_POST['employe_id'] ?? $_GET['employe_id'] ?? null;

if (!$employe_id || !ctype_digit($employe_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'ID employé invalide']);
    exit;
}

$employe_id = (int)$employe_id;

// Vérification que l'employé existe (optionnel mais conseillé)
$stmt = $pdo->prepare("SELECT id FROM employes WHERE id = ? AND badge_actif = 1");
$stmt->execute([$employe_id]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Employé introuvable ou badge inactif']);
    exit;
}

$timestamp = time();
$signature = hash_hmac('sha256', "$employe_id|$timestamp", SECRET_KEY);
$token = "$employe_id|$timestamp|$signature";
$token_hash = hash('sha256', $token);
$expires = date('Y-m-d H:i:s', $timestamp + TOKEN_EXPIRATION);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO badge_tokens (employe_id, token_hash, created_at, expires_at) VALUES (?, ?, NOW(), ?)");
    $stmt->execute([$employe_id, $token_hash, $expires]);

    // Expire les anciens tokens encore valides, sauf le nouveau
    $stmt = $pdo->prepare("UPDATE badge_tokens SET expires_at = NOW() WHERE employe_id = ? AND expires_at > NOW() AND token_hash != ?");
    $stmt->execute([$employe_id, $token_hash]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'token' => $token,
        'expires_at' => $expires,
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erreur lors de la génération du badge',
        'details' => $e->getMessage(),
    ]);
}