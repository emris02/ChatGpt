<?php
// Récupère les derniers événements du journal de pointage (logs)
header('Content-Type: application/json');
$logFile = __DIR__ . '/logs/pointage_system.log';

$result = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines); // Plus récents d'abord
    foreach ($lines as $line) {
        // Exemple de ligne : [2025-07-01 09:00:00] POINTAGE - Employé: 12 | Type: ARRIVEE | Token: XPERT... | IP: 127.0.0.1
        // ou : [2025-07-01 09:00:00] ERREUR - ...
        if (preg_match('/^\[(.*?)\] (POINTAGE|ERREUR) - (.*)$/', $line, $matches)) {
            $result[] = [
                'datetime' => $matches[1],
                'type' => $matches[2],
                'message' => $matches[3]
            ];
        }
        if (count($result) >= 30) break;
    }
}
echo json_encode(['success' => true, 'data' => $result]);
