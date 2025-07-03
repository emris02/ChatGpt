<?php
// save_late_reason.php
require_once 'db.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['employe_id', 'scan_time', 'late_time', 'reason'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception("Champ $field manquant");
        }
    }
    
    // Enregistrement en base
    $stmt = $pdo->prepare("INSERT INTO late_reasons 
                          (employe_id, scan_time, late_time, reason, comment, status) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $input['employe_id'],
        $input['scan_time'],
        $input['late_time'],
        $input['reason'],
        $input['comment'] ?? '',
        $input['status'] ?? 'pending'
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Justification enregistrée']);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}