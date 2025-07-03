<?php
// Fichier pour que l'admin marque un retard/absence comme autorisé ou non
define('IS_ADMIN_ACTION', true);
session_start();
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

echo json_encode(['success' => true]);

$autorise = isset($_POST['autorise']) ? 1 : 0;
$type = $_POST['type'] ?? '';
$employe_id = $_POST['employe_id'] ?? 0;
$date = $_POST['date'] ?? '';
$commentaire = $_POST['commentaire'] ?? '';
$admin_id = $_SESSION['user_id'];

// Déléguer à justifier_pointage.php pour garder la logique centralisée
$postData = http_build_query([
    'employe_id' => $employe_id,
    'date' => $date,
    'type' => $type,
    'est_justifie' => $autorise,
    'commentaire' => $commentaire,
    'admin_action' => 1 // pour différencier si besoin côté justifier_pointage.php
]);

$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => $postData
    ]
];
$context = stream_context_create($opts);
$result = file_get_contents(__DIR__ . '/justifier_pointage.php', false, $context);

// Retourner la réponse JSON de justifier_pointage.php
header('Content-Type: application/json');
echo $result;
