<?php
require 'db.php'; // Inclure votre fichier de connexion à la base de données

// Vérifier si la requête est une requête POST et si des données JSON ont été envoyées
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] === 'application/json') {
    // Récupérer le contenu JSON brut de la requête
    $json_data = file_get_contents('php://input');

    // Décoder les données JSON en un tableau associatif PHP
    $pointages = json_decode($json_data, true);

    // Vérifier si le décodage a réussi et si le tableau n'est pas vide
    if ($pointages !== null && is_array($pointages) && !empty($pointages)) {
        $success = true;
        $errors = [];

        // Préparer une requête SQL pour l'insertion des données (à adapter à votre structure de table 'pointages')
        $stmt = $pdo->prepare("INSERT INTO pointages (employe_id, type, date_heure) VALUES (:employe_id, :type, :date_heure)");

        // Parcourir chaque pointage reçu
        foreach ($pointages as $pointage) {
            // Assurez-vous que les clés correspondent à celles que vous avez définies dans votre script JS
            $prenom = isset($pointage['prenom']) ? trim($pointage['prenom']) : '';
            $nom = isset($pointage['nom']) ? trim($pointage['nom']) : '';
            $type = isset($pointage['type']) ? trim(strtolower($pointage['type'])) : ''; // Convertir en lowercase pour la comparaison
            $date_heure_str = isset($pointage['date_heure']) ? trim($pointage['date_heure']) : '';

            // Récupérer l'ID de l'employé en fonction de son prénom et de son nom
            $stmt_employe = $pdo->prepare("SELECT id FROM employes WHERE prenom = :prenom AND nom = :nom");
            $stmt_employe->execute([':prenom' => $prenom, ':nom' => $nom]);
            $employe = $stmt_employe->fetch(PDO::FETCH_ASSOC);

            if ($employe && $type && in_array($type, ['arrivee', 'depart']) && $date_heure_str) {
                $employe_id = $employe['id'];

                // Essayer de créer un objet DateTime à partir de la chaîne de date et heure
                $date_heure = new DateTime($date_heure_str);
                $date_heure_formattee = $date_heure->format('Y-m-d H:i:s');

                // Exécuter la requête d'insertion pour ce pointage
                if (!$stmt->execute([':employe_id' => $employe_id, ':type' => $type, ':date_heure' => $date_heure_formattee])) {
                    $success = false;
                    $errors[] = "Erreur lors de l'insertion du pointage pour : " . $prenom . " " . $nom . " - " . $date_heure_str;
                }
            } else {
                $success = false;
                $errors[] = "Données de pointage invalides ou employé non trouvé : " . $prenom . " " . $nom . " - " . $date_heure_str;
            }
        }

        // Envoyer une réponse JSON indiquant le succès ou l'échec
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $success ? 'Pointages importés avec succès.' : implode("<br>", $errors)]);

    } else {
        // Si les données JSON sont invalides ou vides
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Données JSON invalides ou vides.']);
    }

} else {
    // Si la requête n'est pas une requête POST ou si le type de contenu n'est pas JSON
    header('HTTP/1.1 400 Bad Request');
    echo "Requête invalide.";
}
?>