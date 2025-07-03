<?php
class EmployeManager {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    public function getEmploye(int $id): array {
        $stmt = $this->db->prepare("SELECT * FROM employes WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: [];
    }
}