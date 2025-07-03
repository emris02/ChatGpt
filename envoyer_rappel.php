<?php
require_once 'db.php';
session_start();

// Ce script doit être lancé par une tâche planifiée (cron ou Windows Task Scheduler)
// Il envoie un rappel dans la messagerie interne 10 minutes avant la descente

$now = new DateTime();
$jour = (int)$now->format('N'); // 1 = lundi, 6 = samedi

if ($jour >= 1 && $jour <= 5) {
    $descente = clone $now;
    $descente->setTime(18, 0, 0);
    $rappel = clone $descente;
    $rappel->modify('-10 minutes');
} elseif ($jour === 6) {
    $descente = clone $now;
    $descente->setTime(14, 0, 0);
    $rappel = clone $descente;
    $rappel->modify('-10 minutes');
} else {
    // Dimanche : pas de rappel
    exit;
}

// Si ce n'est pas l'heure du rappel, on quitte
$nowStr = $now->format('Y-m-d H:i');
if ($nowStr !== $rappel->format('Y-m-d H:i')) {
    exit;
}

// Récupérer les employés présents aujourd'hui (arrivés mais pas encore partis)
$today = $now->format('Y-m-d');
$sql = "
    SELECT e.id, e.prenom, e.nom
    FROM employes e
    JOIN pointages p ON p.employe_id = e.id AND DATE(p.date_heure) = ? AND p.type = 'arrivee'
    WHERE NOT EXISTS (
        SELECT 1 FROM pointages p2 WHERE p2.employe_id = e.id AND DATE(p2.date_heure) = ? AND p2.type = 'depart'
    )
    GROUP BY e.id
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$today, $today]);
$employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$employes) exit;

// L'admin système (expéditeur) : id 1 par défaut
$expediteur_id = 1;
$sujet = "Rappel : Pensez à pointer votre départ !";
$contenu = "Il est bientôt l'heure de la descente. Merci de ne pas oublier de scanner votre badge pour enregistrer votre départ.";

// Insérer le message
$msgStmt = $pdo->prepare("INSERT INTO messages (expediteur_id, sujet, contenu) VALUES (?, ?, ?)");
$msgStmt->execute([$expediteur_id, $sujet, $contenu]);
$message_id = $pdo->lastInsertId();

// Ajouter chaque employé comme destinataire
$destStmt = $pdo->prepare("INSERT INTO message_destinataires (message_id, destinataire_id) VALUES (?, ?)");
foreach ($employes as $emp) {
    $destStmt->execute([$message_id, $emp['id']]);
}

// Fin du script