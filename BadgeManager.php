<?php
// Forcer la timezone ici aussi pour éviter tout décalage
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Europe/Paris');
}
// Assurez-vous que SECRET_KEY est défini dans votre config/db.php ou un fichier de configuration central.
// Exemple : define('SECRET_KEY', 'votre_cle_ultra_secrete_et_longue');

if (!defined('SECRET_KEY') || empty(SECRET_KEY)) {
    throw new Exception('Clé secrète non définie dans la configuration.');
}

class BadgeManager {
    const TOKEN_PREFIX = 'XPERT-';
    const TOKEN_VALIDITY = 7200; // 2 heures en secondes
    const TOKEN_HASH_ALGO = 'sha256';
    const TOKEN_FORMAT_VERSION = 3;

    /**
     * Génère un nouveau badge / QR code pour un employé
     */
    public static function generateToken(int $employe_id): array {
        $random = bin2hex(random_bytes(16));
        $timestamp = time();
        $version = self::TOKEN_FORMAT_VERSION;
        $data = "$employe_id|$random|$timestamp|$version";
        $signature = hash_hmac(self::TOKEN_HASH_ALGO, $data, SECRET_KEY);

        // Calcul dynamique de l'expiration selon le jour
        $now = new DateTime();
        $jour = (int)$now->format('N'); // 1 = lundi, 7 = dimanche
        if ($jour >= 1 && $jour <= 5) {
            $descente = clone $now;
            $descente->setTime(18, 0, 0);
            $expiration = clone $descente;
            $expiration->modify('+1 hour');
        } elseif ($jour === 6) {
            $descente = clone $now;
            $descente->setTime(14, 0, 0);
            $expiration = clone $descente;
            $expiration->modify('+1 hour');
        } else {
            $expiration = clone $now;
            $expiration->modify('+2 hours');
        }
        // Si déjà après l'heure de descente, badge = 1h seulement
        if (isset($descente) && $now > $descente) {
            $expiration = clone $now;
            $expiration->modify('+1 hour');
        }

        $token = "$data|$signature";
        $token_hash = hash('sha256', $token);

        return [
            'token' => $token,
            'expires_at' => $expiration->format('Y-m-d H:i:s'),
            'token_hash' => $token_hash
        ];
    }

    /**
     * Régénère un badge/QR pour un employé (révoque l'ancien, insère le nouveau)
     */
    public static function regenerateToken(int $employe_id, PDO $pdo): array {
        try {
            $pdo->beginTransaction();

            // Génération du nouveau token
            $newToken = self::generateToken($employe_id);

            // SUPPRESSION DÉFINITIVE des anciens badges (avant insertion du nouveau)
            $stmt = $pdo->prepare("DELETE FROM badge_tokens WHERE employe_id = ?");
            $stmt->execute([$employe_id]);

            // Insérer le nouveau token comme actif
            $stmt = $pdo->prepare("INSERT INTO badge_tokens (employe_id, token, token_hash, created_at, expires_at, ip_address, device_info, status, created_by) 
                VALUES (?, ?, ?, NOW(), ?, ?, ?, 'active', ?)");
            $stmt->execute([
                $employe_id,
                $newToken['token'],
                $newToken['token_hash'],
                $newToken['expires_at'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                self::TOKEN_FORMAT_VERSION
            ]);

            $pdo->commit();
            self::logAction($employe_id, 'regeneration', $newToken['token_hash']);

            return [
                'status' => 'success',
                'token' => $newToken['token'],
                'token_hash' => $newToken['token_hash'],
                'expires_at' => $newToken['expires_at'],
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            $pdo->rollBack();
            self::logAction($employe_id, 'error', $e->getMessage());
            throw new RuntimeException("Échec de la régénération: " . $e->getMessage());
        }
    }

    /**
     * Vérifie la validité d'un token (signature, structure, DB)
     */
    public static function verifyToken(string $token, PDO $pdo): array {
        $parts = explode('|', $token);
        if (count($parts) !== 5) {
            throw new InvalidArgumentException("Format de token invalide");
        }
        list($employe_id, $random, $timestamp, $version, $signature) = $parts;
        $data = "$employe_id|$random|$timestamp|$version";
        $expectedSignature = hash_hmac(self::TOKEN_HASH_ALGO, $data, SECRET_KEY);

        if (!hash_equals($signature, $expectedSignature)) {
            throw new RuntimeException("Signature invalide");
        }

        $token_hash = hash('sha256', $token);

        // DEBUG LOG
        $debugFile = __DIR__ . '/logs/badge_verify_debug.log';
        file_put_contents($debugFile, "[".date('Y-m-d H:i:s')."]\nTOKEN: [$token]\nTOKEN_HASH: [$token_hash]\n", FILE_APPEND);

        $stmt = $pdo->prepare("SELECT bt.*, e.* 
                               FROM badge_tokens bt
                               JOIN employes e ON bt.employe_id = e.id
                               WHERE bt.token_hash = ?
                               AND bt.expires_at > NOW()
                               AND bt.status = 'active'");
        $stmt->execute([$token_hash]);
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        file_put_contents($debugFile, "Résultat SQL: ".($tokenData ? 'TROUVÉ' : 'AUCUN')."\n\n", FILE_APPEND);

        if (!$tokenData) {
            throw new RuntimeException("Token invalide ou expiré");
        }

        $tokenData['validation'] = [
            'signature_valid' => true,
            'format_version' => (int)$version,
            'generated_at' => date('Y-m-d H:i:s', (int)$timestamp),
            'checked_at' => date('Y-m-d H:i:s')
        ];
        return $tokenData;
    }

    /**
     * Logging sécurisé dans logs/badge_system.log
     */
    private static function logAction(int $employe_id, string $action, string $details): void {
        $logDir = __DIR__ . '/logs/';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        $logFile = $logDir . 'badge_system.log';
        $log = sprintf(
            "[%s] %s - Employé: %d | Détails: %s | IP: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($action),
            $employe_id,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        );
        file_put_contents($logFile, $log, FILE_APPEND);
    }
}