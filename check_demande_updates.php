<?php
require 'db.php';
header('Content-Type: application/json');

try {
    // Dernières demandes en attente (pour badge de notification)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as new_demandes 
        FROM demandes 
        WHERE statut = 'en_attente' 
        AND date_demande > DATE_SUB(NOW(), INTERVAL 1 DAY)
    ");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Dernières activités (pour historique)
    $stmt = $pdo->query("
        SELECT d.id, e.prenom, e.nom, d.type, d.statut, d.date_demande
        FROM demandes d
        JOIN employes e ON d.employe_id = e.id
        ORDER BY d.date_demande DESC
        LIMIT 5
    ");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'new_demandes' => (int)$result['new_demandes'],
        'activities' => $activities,
        'timestamp' => time()
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données']);
}
?>