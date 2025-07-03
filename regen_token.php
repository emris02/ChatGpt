<?php
// regen_token.php : à exécuter tous les jours à minuit (via CRON)

// Connexion à la base de données
require_once('db.php');


// Récupérer la date du jour
$date_jour = date('Y-m-d');

// Générer un token unique (64 caractères aléatoires)
$token = bin2hex(random_bytes(32)); // Plus sécurisé qu'un simple uniqid()

try {
    // Vérifier si un token existe déjà pour ce jour
    $check = $pdo->prepare("SELECT id FROM tokens WHERE date_jour = :date_jour");
    $check->bindParam(':date_jour', $date_jour);
    $check->execute();

    if ($check->rowCount() > 0) {
        // Mise à jour si un token existe déjà
        $update = $pdo->prepare("UPDATE tokens SET token = :token WHERE date_jour = :date_jour");
        $update->bindParam(':token', $token);
        $update->bindParam(':date_jour', $date_jour);
        $update->execute();
    } else {
        // Insertion d’un nouveau token pour aujourd’hui
        $insert = $pdo->prepare("INSERT INTO tokens (date_jour, token) VALUES (:date_jour, :token)");
        $insert->bindParam(':date_jour', $date_jour);
        $insert->bindParam(':token', $token);
        $insert->execute();
    }

    echo "✅ Nouveau token généré avec succès pour le $date_jour : $token\n";

} catch (PDOException $e) {
    echo "❌ Erreur : " . $e->getMessage();
}
?>
