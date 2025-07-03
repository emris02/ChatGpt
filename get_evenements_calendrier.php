<?php
require_once 'db.php';
header('Content-Type: application/json');

try {
    $for_employe = isset($_GET['employe']) ? true : false;
    $where = $for_employe ? 'WHERE visible_employes = 1' : '';
    $stmt = $pdo->prepare("SELECT id, titre, description, date_event, type_event FROM evenements_calendrier $where ORDER BY date_event ASC");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $result = [];
    foreach ($events as $e) {
        $result[] = [
            'id' => $e['id'],
            'title' => $e['titre'],
            'start' => $e['date_event'],
            'description' => $e['description'],
            'type' => $e['type_event'],
            // FullCalendar attend un champ "allDay" pour les événements sur une seule journée
            'allDay' => true
        ];
    }
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
