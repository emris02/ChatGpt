<?php
// Récupère l'historique de pointage des employés
require_once 'db.php';
header('Content-Type: application/json');

try {
    $sql = "SELECT e.prenom, e.nom, p.date_pointage, p.heure_pointage FROM pointage p JOIN employes e ON p.employe_id = e.id ORDER BY p.date_pointage DESC, p.heure_pointage DESC LIMIT 30";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
