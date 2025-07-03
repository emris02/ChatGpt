<?php
class PointageController {
    private $db;
    
    public function __construct(PDO $db) {
        $this->db = $db;
    }
    
    public function traiterPointage(): void {
        header('Content-Type: application/json');
        
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validation et traitement...
            $pointageService = new PointageService($this->db);
            $result = $pointageService->traiterPointage($data);
            
            echo json_encode($result);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}