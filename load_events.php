<?php
// load_events.php : retourne les événements au format FullCalendar
require 'db.php';
header('Content-Type: application/json');
$events = $pdo->query("SELECT id, titre AS title, description, date AS start, type FROM evenements")->fetchAll(PDO::FETCH_ASSOC);
foreach ($events as &$e) {
    $e['allDay'] = true;
    // Couleur selon le type
    switch ($e['type']) {
        case 'réunion': $e['backgroundColor'] = '#007bff'; break;
        case 'congé': $e['backgroundColor'] = '#28a745'; break;
        case 'formation': $e['backgroundColor'] = '#ffc107'; break;
        default: $e['backgroundColor'] = '#6c757d'; break;
    }
}
echo json_encode($events);
