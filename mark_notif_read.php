<?php
session_start();
require_once 'db.php';

// Define ENVIRONMENT constant if not already defined
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'production'); // Change to 'development' if needed
}

// Vérification de l'authentification
if (!isset($_SESSION['employe_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Méthode non autorisée']));
}

// Log incoming data for debugging
error_log("Données reçues pour marquage comme lu: " . print_r($_POST, true));

$employe_id = (int)$_SESSION['employe_id'];

try {
    // Marquer une notification spécifique comme lue
    if (isset($_POST['notification_id'])) {
        $notification_id = (int)$_POST['notification_id'];
        
        if ($notification_id <= 0) {
            http_response_code(400);
            exit(json_encode(['success' => false, 'message' => 'ID de notification invalide']));
        }

        $stmt = $pdo->prepare("UPDATE notifications 
                              SET lue = 1, date_lecture = NOW() 
                              WHERE id = ? AND employe_id = ?");
        $stmt->execute([$notification_id, $employe_id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification marquée comme lue'
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Notification non trouvée ou accès refusé'
            ]);
        }
    } 
    // Marquer toutes les notifications comme lues
    elseif (isset($_POST['mark_all']) && $_POST['mark_all'] === '1') {
        $stmt = $pdo->prepare("UPDATE notifications 
                              SET lue = 1, date_lecture = NOW() 
                              WHERE employe_id = ? AND lue = 0");
        $stmt->execute([$employe_id]);
        
        echo json_encode([
            'success' => true,
            'count' => $stmt->rowCount(),
            'message' => 'Toutes les notifications marquées comme lues'
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Paramètres de requête invalides'
        ]);
    }
} catch (PDOException $e) {
    error_log("Erreur de mise à jour notification: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur',
        'debug' => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $e->getMessage() : null
    ]);
}