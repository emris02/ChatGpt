<?php
class BadgeService {
    private $db;
    private const TOKEN_EXPIRATION = 86400; // 24h en secondes
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    public function regenererToken(int $employeId, string $type): array {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + self::TOKEN_EXPIRATION);
        
        $stmt = $this->db->prepare(
            "INSERT INTO badge_tokens 
            (employe_id, token_hash, type, expires_at) 
            VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $employeId,
            hash('sha256', $token),
            $type,
            $expires
        ]);
        
        return [
            'token' => $token,
            'expires_at' => $expires
        ];
    }
}