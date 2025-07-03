<?php
// Script AJAX pour notifications enrichies côté admin
require_once 'db.php';
header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT n.*, p.type AS pointage_type, p.date_heure AS pointage_date_heure
        FROM notifications n
        LEFT JOIN pointages p ON n.pointage_id = p.id
        ORDER BY n.date DESC
        LIMIT 10");
    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Formatage des dates pour l'affichage
    foreach ($notifs as &$notif) {
        $notif['date'] = date('d/m/Y H:i', strtotime($notif['date']));
        if ($notif['pointage_date_heure']) {
            $notif['pointage_date_heure'] = date('d/m/Y H:i', strtotime($notif['pointage_date_heure']));
        }
    }
    echo json_encode($notifs);
} catch (Exception $e) {
    echo json_encode([]);
}
