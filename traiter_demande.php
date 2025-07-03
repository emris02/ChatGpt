<?php
session_start();
require 'db.php';

// Vérification des droits admin
if (!isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['demande_id'], $data['action'], $data['admin_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit();
}

try {
    $pdo->beginTransaction();
    
    // 1. Mettre à jour le statut de la demande
    $stmt = $pdo->prepare("
        UPDATE demandes_badge 
        SET 
            statut = ?,
            date_traitement = NOW(),
            admin_id = ?,
            commentaire = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $data['action'],
        $data['admin_id'],
        $data['commentaire'] ?? null,
        $data['demande_id']
    ]);
    
    // 2. Si la demande est approuvée, générer un nouveau badge
    if ($data['action'] === 'approuve') {
        // Récupérer l'ID de l'employé
        $stmt = $pdo->prepare("SELECT employe_id FROM demandes_badge WHERE id = ?");
        $stmt->execute([$data['demande_id']]);
        $employe_id = $stmt->fetchColumn();
        
        if ($employe_id) {
            // Générer un nouveau token sécurisé
            $timestamp = time();
            $randomToken = bin2hex(random_bytes(16));
            $dataToHash = $employe_id . $timestamp . $randomToken;
            $secureHash = hash('sha256', $dataToHash);
            $token = $employe_id . '|' . $timestamp . '|' . $secureHash;
            $expires_at = date('Y-m-d H:i:s', $timestamp + 7200); // 2h de validité
            
            // Insérer le nouveau badge
            $stmt = $pdo->prepare("
                INSERT INTO badge_tokens 
                (employe_id, token_hash, created_at, expires_at) 
                VALUES (?, ?, NOW(), ?)
            ");
            $stmt->execute([$employe_id, $token, $expires_at]);
            
            // Envoyer un email de notification (à implémenter)
            // sendBadgeApprovalEmail($employe_id, $token);
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}