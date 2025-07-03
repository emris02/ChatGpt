<?php
require_once 'db.php';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="export_pointages.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['ID', 'Employé', 'Date', 'Arrivée', 'Départ', 'Temps total', 'Type']);

$stmt = $pdo->query("SELECT p.*, e.nom, e.prenom 
                     FROM pointages p 
                     JOIN employes e ON p.employe_id = e.id 
                     ORDER BY p.date_heure DESC");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['id'],
        $row['nom'] . ' ' . $row['prenom'],
        substr($row['date_heure'], 0, 10),
        $row['arrivee'],
        $row['depart'],
        $row['temps_total'],
        $row['type']
    ]);
}
fclose($output);
exit();
