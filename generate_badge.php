<?php
session_start();
require_once 'db.php';
require_once 'badge_function.php';

if (!function_exists('generateBadgeToken')) {
    function generateBadgeToken($employe_id) {
        $random = bin2hex(random_bytes(16));
        $timestamp = time();
        $data = "$employe_id|$random|$timestamp";
        $signature = hash_hmac('sha256', $data, SECRET_KEY);
        return [
            'token' => "$employe_id|$random|$timestamp|$signature",
            'expires_at' => date('Y-m-d H:i:s', $timestamp + 7200)
        ];
    }
}

header('Content-Type: application/json');

try {
    // Vérification des autorisations
    if (!isset($_SESSION['role'])) {
        throw new RuntimeException("Accès non autorisé", 403);
    }

    // Validation des paramètres
    if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
        throw new InvalidArgumentException("ID employé manquant ou invalide", 400);
    }

    if (!isset($_GET['token'])) {
        throw new InvalidArgumentException("Token manquant", 400);
    }

    $employe_id = (int)$_GET['id'];
    $token = $_GET['token'];

    // Vérification de l'employé
    $stmt = $pdo->prepare("SELECT id FROM employes WHERE id = ? AND actif = 1");
    $stmt->execute([$employe_id]);
    
    if (!$stmt->fetch()) {
        throw new RuntimeException("Employé non trouvé ou inactif", 404);
    }

    // Validation du token
    $parts = explode('|', $token);
    if (count($parts) !== 4) {
        throw new RuntimeException("Format de token invalide", 400);
    }

    [$token_employe_id, $random, $timestamp, $signature] = $parts;

    // Vérification cohérence employé
    if ($token_employe_id != $employe_id) {
        throw new RuntimeException("Incohérence token/employé", 403);
    }

    // Vérification signature
    $data = "$token_employe_id|$random|$timestamp";
    if (!hash_equals(hash_hmac('sha256', $data, SECRET_KEY), $signature)) {
        throw new RuntimeException("Signature token invalide", 403);
    }

    // Vérification expiration (2h)
    if (time() - $timestamp > 7200) {
        throw new RuntimeException("Token expiré", 403);
    }

    // Vérification en base
    $token_hash = hash('sha256', $token);
    $stmt = $pdo->prepare("
        SELECT * FROM badge_tokens 
        WHERE token_hash = ? 
        AND employe_id = ?
        AND expires_at > NOW()
        AND (used_at IS NULL OR used_at = '0000-00-00 00:00:00')
    ");
    $stmt->execute([$token_hash, $employe_id]);
    
    if (!$stmt->fetch()) {
        throw new RuntimeException("Token invalide ou déjà utilisé", 403);
    }

    // Traitement du pointage
    $pdo->beginTransaction();

    try {
        // Marquer le token comme utilisé
        $stmt = $pdo->prepare("UPDATE badge_tokens SET used_at = NOW() WHERE token_hash = ?");
        $stmt->execute([$token_hash]);

        // Déterminer le type de pointage
        $pointageType = determinePointageType($pdo, $employe_id);
        $now = date('Y-m-d H:i:s');

        if ($pointageType === 'arrivee') {
            $result = recordArrival($pdo, $employe_id, $now);
        } else {
            $result = recordDeparture($pdo, $employe_id, $now);
        }

        // Générer un nouveau token
        $newToken = generateBadgeToken($employe_id);
        saveToken($pdo, $newToken);

        $pdo->commit();

        // Réponse JSON
        echo json_encode([
            'status' => 'success',
            'message' => "Pointage $pointageType enregistré",
            'new_token' => $newToken['token'],
            'data' => $result
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (InvalidArgumentException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (RuntimeException $e) {
    http_response_code($e->getCode() ?: 403);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur interne du serveur']);
}