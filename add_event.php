<?php
// add_event.php : ajoute un événement dans la table evenements
require 'db.php';
header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['titre'], $data['date'], $data['type'])) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}
$titre = $data['titre'];
$description = $data['description'] ?? '';
$date = $data['date'];
$type = $data['type'];
$created_by = $_SESSION['admin_id'] ?? 0;
$stmt = $pdo->prepare("INSERT INTO evenements (titre, description, date, type, created_by) VALUES (?, ?, ?, ?, ?)");
$ok = $stmt->execute([$titre, $description, $date, $type, $created_by]);
echo json_encode(['success' => $ok]);
