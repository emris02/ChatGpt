<?php
require_once 'db.php';
require_once 'BadgeManager.php';

date_default_timezone_set('Europe/Paris');

class PointageSystem {
    private PDO $pdo;
    private $logger;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->logger = new PointageLogger();
    }

    public function handlePointageRequest(array $requestData): array {
        try {
            // Validation stricte de l'entrée
            if (empty($requestData['badge_token'])) {
                throw new InvalidArgumentException("Token manquant pour le pointage");
            }

            // DEBUG : log du token reçu (brut et encodé)
            $logDebug = __DIR__ . '/logs/pointage_debug.log';
            file_put_contents($logDebug, "[".date('Y-m-d H:i:s')."] TOKEN recu : [".$requestData['badge_token']."]\n", FILE_APPEND);
            file_put_contents($logDebug, "Longueur : ".strlen($requestData['badge_token'])."\n", FILE_APPEND);
            file_put_contents($logDebug, "Hex : ".bin2hex($requestData['badge_token'])."\n", FILE_APPEND);

            // Vérification du token et extraction des infos employé
            $tokenData = BadgeManager::verifyToken($requestData['badge_token'], $this->pdo);
            if (!$tokenData) {
                throw new RuntimeException("Token invalide ou expiré");
            }

            // Enregistrement du pointage
            return $this->traiterPointage($tokenData);
        } catch (Exception $e) {
            $this->logger->logError($e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    private function traiterPointage(array $tokenData): array {
        $employeId = (int)$tokenData['employe_id'];
        $badgeTokenId = (int)$tokenData['id'];
        $dateCourante = date('Y-m-d');

        $this->pdo->beginTransaction();

        try {
            // Récupérer le dernier pointage du jour
            $lastPointage = $this->getLastPointage($employeId, $dateCourante);

            // Déterminer le type de pointage attendu
            $type = $this->determinerTypePointage($lastPointage);

            // Traiter le pointage
            if ($type === 'arrivee') {
                $response = $this->handleArrival($employeId, $badgeTokenId);
            } else {
                $response = $this->handleDeparture($employeId, $badgeTokenId, $lastPointage);
            }

            $this->pdo->commit();

            // Log du pointage
            $this->logger->logPointage(
                $employeId, 
                $type, 
                $response['timestamp'], 
                $tokenData['token']
            );

            return $response;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->logError("Erreur traitement: " . $e->getMessage());
            throw $e;
        }
    }

    private function getLastPointage(int $employeId, string $date): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pointages
            WHERE employe_id = ? 
            AND DATE(date_heure) = ?
            ORDER BY date_heure DESC
            LIMIT 1
        ");
        $stmt->execute([$employeId, $date]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function determinerTypePointage(?array $lastPointage): string {
        if (!$lastPointage) {
            return 'arrivee';
        }
        return ($lastPointage['type'] === 'depart') ? 'arrivee' : 'depart';
    }

    private function handleArrival(int $employeId, int $badgeTokenId): array {
        $now = date('Y-m-d H:i:s');
        $heureLimite = date('Y-m-d') . ' 09:00:00'; // 09h00 d'après ta table retards
        $isLate = strtotime($now) > strtotime($heureLimite);

        // Vérification de l'existence et validité du badge_token_id (utilise $now PHP pour la date)
        $check = $this->pdo->prepare("SELECT id FROM badge_tokens WHERE id = ? AND status = 'active' AND expires_at > ?");
        $check->execute([$badgeTokenId, $now]);
        if (!$check->fetchColumn()) {
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] ERREUR badge_token_id non valide ARRIVEE: $badgeTokenId\n", FILE_APPEND);
            throw new RuntimeException("Le badge utilisé n'est plus valide ou n'existe pas.");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO pointages (
                date_heure, employe_id, type, retard_cause, retard_justifie, badge_token_id, ip_address, device_info
            ) VALUES (?, ?, 'arrivee', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $now,
            $employeId,
            $isLate ? "Arrivée après 09h00" : null,
            $isLate ? 'non' : null,
            $badgeTokenId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
        file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] INSERT ARRIVEE OK pour employe $employeId, badge $badgeTokenId\n", FILE_APPEND);

        return [
            'status' => 'success',
            'type' => 'arrivee',
            'message' => 'Arrivée enregistrée',
            'retard' => $isLate,
            'timestamp' => $now,
        ];
    }

    private function handleDeparture(int $employeId, int $badgeTokenId, array $lastPointage): array {
        $now = date('Y-m-d H:i:s');

        if ($lastPointage['type'] !== 'arrivee') {
            throw new LogicException("Incohérence: Dernier pointage n'est pas une arrivée");
        }

        // Vérification de l'existence et validité du badge_token_id (utilise $now PHP pour la date)
        $check = $this->pdo->prepare("SELECT id FROM badge_tokens WHERE id = ? AND status = 'active' AND expires_at > ?");
        $check->execute([$badgeTokenId, $now]);
        if (!$check->fetchColumn()) {
            file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] ERREUR badge_token_id non valide DEPART: $badgeTokenId\n", FILE_APPEND);
            throw new RuntimeException("Le badge utilisé n'est plus valide ou n'existe pas.");
        }

        // Calcul du temps travaillé (avec pause)
        $workData = $this->calculerTempsTravail(
            $lastPointage['date_heure'],
            $now
        );

        $stmt = $this->pdo->prepare("
            INSERT INTO pointages (
                date_heure, employe_id, type, temps_total, badge_token_id, ip_address, device_info
            ) VALUES (?, ?, 'depart', ?, ?, ?, ?)
        ");
        $stmt->execute([
            $now,
            $employeId,
            $workData['temps_travail'],
            $badgeTokenId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
        file_put_contents(__DIR__ . '/logs/pointage_debug.log', "[".date('Y-m-d H:i:s')."] INSERT DEPART OK pour employe $employeId, badge $badgeTokenId\n", FILE_APPEND);

        // Badge: update status à "expired"
        $expireStmt = $this->pdo->prepare("UPDATE badge_tokens SET status = 'expired', expires_at = ? WHERE employe_id = ? AND status = 'active'");
        $expireStmt->execute([$now, $employeId]);

        // Générer un nouveau badge/token (pour le prochain pointage)
        $newTokenData = BadgeManager::generateToken($employeId);
        $insertStmt = $this->pdo->prepare("INSERT INTO badge_tokens (employe_id, token, created_at, expires_at, status, ip_address, user_agent) VALUES (?, ?, ?, ?, 'active', ?, ?)");
        $insertStmt->execute([
            $employeId,
            $newTokenData['token'],
            $now,
            $newTokenData['expires_at'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        return [
            'status' => 'success',
            'type' => 'depart',
            'message' => 'Départ enregistré. Nouveau badge généré.',
            'temps_travail' => $workData['temps_travail'],
            'timestamp' => $now,
        ];
    }

    private function calculerTempsTravail(string $debut, string $fin): array {
        $debutDt = new DateTime($debut);
        $finDt = new DateTime($fin);

        if ($finDt < $debutDt) {
            throw new InvalidArgumentException("Heure de fin antérieure au début");
        }

        $interval = $debutDt->diff($finDt);
        $totalSeconds = ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        // Pause d'1h si > 4h de travail effectif (cf. logique vue en SQL)
        $pauseSeconds = ($totalSeconds > 4 * 3600) ? 3600 : 0;
        $workSeconds = max(0, $totalSeconds - $pauseSeconds);

        return [
            'temps_travail' => gmdate('H:i:s', $workSeconds),
            'temps_pause' => gmdate('H:i:s', $pauseSeconds)
        ];
    }
}

class PointageLogger {
    private $logFile;

    public function __construct() {
        $dir = __DIR__ . '/logs/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $this->logFile = $dir . 'pointage_system.log';
    }

    public function logPointage(int $employeId, string $type, string $timestamp, string $tokenHash) {
        $entry = sprintf(
            "[%s] POINTAGE - Employé: %d | Type: %s | Token: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            $employeId,
            strtoupper($type),
            substr($tokenHash, 0, 12) . '...',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }

    public function logError(string $message) {
        $entry = sprintf(
            "[%s] ERREUR - %s | Trace: %s\n",
            date('Y-m-d H:i:s'),
            $message,
            json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3))
        );
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }
}

// API entry point
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $system = new PointageSystem($pdo);
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $response = $system->handlePointageRequest($data);
    } catch (Throwable $e) {
        $response = [
            'status' => 'error',
            'message' => 'Erreur système: ' . $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    echo json_encode($response);
    exit;
}