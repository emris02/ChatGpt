<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    if ($action === 'add') {
        $titre = trim($data['titre'] ?? '');
        $date_event = $data['date_event'] ?? '';
        $type_event = $data['type_event'] ?? 'evenement';
        $desc = trim($data['description'] ?? '');
        $visible = isset($data['visible_employes']) ? (int)$data['visible_employes'] : 1;
        if (!$titre || !$date_event) throw new Exception('Titre et date obligatoires');
        $stmt = $pdo->prepare("INSERT INTO evenements_calendrier (titre, description, date_event, type_event, created_by, visible_employes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$titre, $desc, $date_event, $type_event, $_SESSION['admin_id'], $visible]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'move') {
        $id = (int)($data['id'] ?? 0);
        $date_event = $data['date_event'] ?? '';
        if (!$id || !$date_event) throw new Exception('ID et date requis');
        $stmt = $pdo->prepare("UPDATE evenements_calendrier SET date_event = ? WHERE id = ?");
        $stmt->execute([$date_event, $id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete') {
        $id = (int)($data['id'] ?? 0);
        if (!$id) throw new Exception('ID requis');
        $stmt = $pdo->prepare("DELETE FROM evenements_calendrier WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'edit') {
        $id = (int)($data['id'] ?? 0);
        $titre = trim($data['titre'] ?? '');
        $desc = trim($data['description'] ?? '');
        $type_event = $data['type_event'] ?? 'evenement';
        $visible = isset($data['visible_employes']) ? (int)$data['visible_employes'] : 1;
        if (!$id || !$titre) throw new Exception('ID et titre requis');
        $stmt = $pdo->prepare("UPDATE evenements_calendrier SET titre = ?, description = ?, type_event = ?, visible_employes = ? WHERE id = ?");
        $stmt->execute([$titre, $desc, $type_event, $visible, $id]);
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Action inconnue');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
