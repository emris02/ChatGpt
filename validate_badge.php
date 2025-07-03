<?php
require_once 'db.php';
require_once 'badge_function.php';

header('Content-Type: application/json');
date_default_timezone_set('Europe/Paris');

/**
 * Exceptions personnalisées
 */
class BadRequestException extends Exception {}
class UnauthorizedException extends Exception {}

class PointageService {
    private PDO $pdo;
    private string $secretKey;

    public function __construct(PDO $pdo, string $secretKey = SECRET_KEY) {
        $this->pdo = $pdo;
        $this->secretKey = $secretKey;
        
    }

    /**
     * Traite un pointage en validant le token puis en enregistrant l'arrivée ou le départ
     */
    public function traiterPointage(string $token): array {
        $this->validerTokenStructure($token);

        [$employe_id, $timestamp, $signature] = explode('|', $token);

        $this->verifierSignature($employe_id, $timestamp, $signature);
        $this->verifierExpiration($timestamp);

        $tokenData = $this->verifierTokenEnBase($token, $employe_id);

        return $this->enregistrerPointage($employe_id, (int)$tokenData['id']);
    }

    private function validerTokenStructure(string $token): void {
        if (count(explode('|', $token)) !== 3) {
            throw new BadRequestException('Format de token invalide');
        }
    }

    private function verifierSignature(string $employe_id, string $timestamp, string $signature): void {
        $expected = hash_hmac('sha256', "$employe_id|$timestamp", $this->secretKey);
        if (!hash_equals($expected, $signature)) {
            throw new UnauthorizedException('Signature invalide');
        }
    }

    private function verifierExpiration(string $timestamp): void {
        if (time() - (int)$timestamp > TOKEN_EXPIRATION) {
            throw new UnauthorizedException('Token expiré');
        }
    }

    private function verifierTokenEnBase(string $token, string $employe_id): array {
        $token_hash = hash('sha256', $token);

        $stmt = $this->pdo->prepare("SELECT * FROM badge_tokens WHERE token_hash = ? AND employe_id = ? AND expires_at > NOW()");
        $stmt->execute([$token_hash, $employe_id]);
        $record = $stmt->fetch();

        if (!$record) {
            throw new UnauthorizedException('Token invalide ou expiré');
        }
        if (!empty($record['used_at'])) {
            throw new UnauthorizedException('Token déjà utilisé');
        }
        return $record;
    }

    private function enregistrerPointage(int $employe_id, int $token_id): array {
        $this->pdo->beginTransaction();

        try {
            // Marquer token comme utilisé
            $stmt = $this->pdo->prepare("UPDATE badge_tokens SET used_at = NOW() WHERE id = ?");
            $stmt->execute([$token_id]);

            $type = $this->determinerTypePointage($employe_id);

            if ($type === 'arrivee') {
                $result = $this->enregistrerArrivee($employe_id);
            } else {
                $result = $this->enregistrerDepart($employe_id);
            }

            $this->pdo->commit();

            return $result;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function determinerTypePointage(int $employe_id): string {
        $current_date = date('Y-m-d');
        $stmt = $this->pdo->prepare("SELECT type FROM pointages WHERE employe_id = ? AND DATE(date_heure) = ? ORDER BY date_heure DESC LIMIT 1");
        $stmt->execute([$employe_id, $current_date]);
        $last = $stmt->fetch();

        return (!$last || $last['type'] === 'depart') ? 'arrivee' : 'depart';
    }

    private function enregistrerArrivee(int $employe_id): array {
        $current_time = date('Y-m-d H:i:s');
        $limite = strtotime(date('Y-m-d') . ' 09:00:00');
        $retard = strtotime($current_time) > $limite;

        $stmt = $this->pdo->prepare("INSERT INTO pointages (date_heure, employe_id, type, retard_justifie, retard_cause) VALUES (?, ?, 'arrivee', ?, ?)");
        $stmt->execute([
            $current_time,
            $employe_id,
            $retard ? 'non' : null,
            $retard ? 'Arrivée après 09h00' : null,
        ]);

        return [
            'status' => 'success',
            'message' => 'Arrivée enregistrée',
            'en_retard' => $retard,
            'timestamp' => $current_time,
        ];
    }

    private function enregistrerDepart(int $employe_id): array {
        $current_date = date('Y-m-d');
        $current_time = date('Y-m-d H:i:s');

        // Vérifier qu'une arrivée existe
        $stmt = $this->pdo->prepare("SELECT date_heure FROM pointages WHERE employe_id = ? AND DATE(date_heure) = ? AND type = 'arrivee' ORDER BY date_heure DESC LIMIT 1");
        $stmt->execute([$employe_id, $current_date]);
        $last = $stmt->fetch();

        if (!$last) {
            throw new Exception('Départ non autorisé sans arrivée préalable');
        }

        $start = new DateTime($last['date_heure']);
        $end = new DateTime($current_time);
        $diff = $start->diff($end);
        $seconds = ($diff->h * 3600) + ($diff->i * 60) + $diff->s;

        $pause = $seconds > 4 * 3600 ? 3600 : 0;
        $seconds -= $pause;
        $temps_total = gmdate('H:i:s', $seconds);

        $stmt = $this->pdo->prepare("INSERT INTO pointages (date_heure, employe_id, type, temps_total) VALUES (?, ?, 'depart', ?)");
        $stmt->execute([$current_time, $employe_id, $temps_total]);

        return [
            'status' => 'success',
            'message' => 'Départ enregistré',
            'temps_total' => $temps_total,
            'pause' => gmdate('H:i:s', $pause),
            'timestamp' => $current_time,
        ];
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new BadRequestException('Méthode non autorisée');
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['badge_token'] ?? $data['token'] ?? null;
    if (empty($token)) {
        throw new BadRequestException('Token manquant');
    }

    $pointageService = new PointageService($pdo);
    $response = $pointageService->traiterPointage($token);

    echo json_encode($response);
} catch (BadRequestException $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (UnauthorizedException $e) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Erreur interne serveur']);
}
exit;