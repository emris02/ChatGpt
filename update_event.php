<?php
// update_event.php : met à jour la date d'un événement
require 'db.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['id'], $data['date'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}
$id = $data['id'];
$date = $data['date'];
$stmt = $pdo->prepare("UPDATE evenements SET date = ? WHERE id = ?");
$ok = $stmt->execute([$date, $id]);
echo json_encode(['success' => $ok]);
