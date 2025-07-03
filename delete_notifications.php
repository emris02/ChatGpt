<?php
session_start();
require_once 'db.php';

// Vérification de l'authentification
if (!isset($_SESSION['employe_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    exit(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

// Log incoming data for debugging
error_log("Données reçues pour suppression: " . print_r($_POST, true));

$employe_id = $_SESSION['employe_id'];

try {
    if (!empty($_POST['selected_notifications'])) {
        // Convertir en tableau d'entiers pour la sécurité
        $notifications_ids = array_map('intval', $_POST['selected_notifications']);
        $placeholders = implode(',', array_fill(0, count($notifications_ids), '?'));
        
        // Vérification de propriété avant suppression
        $checkStmt = $pdo->prepare("SELECT id FROM notifications 
                                   WHERE id IN ($placeholders) AND employe_id = ?");
        $checkStmt->execute(array_merge($notifications_ids, [$employe_id]));
        
        $valid_ids = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($valid_ids)) {
            $deleteStmt = $pdo->prepare("DELETE FROM notifications 
                                       WHERE id IN (" . implode(',', $valid_ids) . ")");
            $deleteStmt->execute();
            
            echo json_encode([
                'success' => true,
                'deleted_count' => $deleteStmt->rowCount()
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Aucune notification valide à supprimer'
            ]);
        }
    } elseif (isset($_POST['id'])) { // Handle individual deletion
        $notification_id = (int)$_POST['id'];
        
        // Vérification de propriété avant suppression
        $checkStmt = $pdo->prepare("SELECT id FROM notifications 
                                   WHERE id = ? AND employe_id = ?");
        $checkStmt->execute([$notification_id, $employe_id]);
        
        if ($checkStmt->fetchColumn()) {
            $deleteStmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
            $deleteStmt->execute([$notification_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Notification supprimée'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Notification non trouvée ou accès refusé'
            ]);
        }
    } else {
        header("HTTP/1.1 400 Bad Request");
        echo json_encode(['success' => false, 'message' => 'Aucune sélection']);
    }
} catch (PDOException $e) {
    error_log("Erreur suppression multiple: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}