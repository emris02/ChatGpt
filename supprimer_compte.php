<?php
session_start();
require 'db.php'; // Connexion à la base

// Vérifier si l'employé est connecté
if (!isset($_SESSION['employe_id'])) {
    header("Location: login.php");
    exit();
}

$employe_id = $_SESSION['employe_id'];

// Vérifier si le formulaire de suppression est soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Suppression des données liées à l'employé (points de pointage, retards, etc.)
        $stmt_pointages = $pdo->prepare("DELETE FROM pointages WHERE employe_id = :employe_id");
        $stmt_pointages->execute([':employe_id' => $employe_id]);

        $stmt_retards = $pdo->prepare("DELETE FROM retards WHERE employe_id = :employe_id");
        $stmt_retards->execute([':employe_id' => $employe_id]);

        // Suppression du compte employé
        $stmt = $pdo->prepare("DELETE FROM employes WHERE id = :employe_id");
        $stmt->execute([':employe_id' => $employe_id]);

        // Détruire la session pour déconnecter l'utilisateur
        session_destroy();
        header("Location: goodbye.php"); // Redirection vers une page de confirmation
        exit();
    } catch (PDOException $e) {
        $error = "Une erreur est survenue lors de la suppression de votre compte.";
        error_log("Erreur SQL: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer mon compte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h2 class="text-danger">Êtes-vous sûr de vouloir supprimer votre compte ?</h2>
    <p class="text-danger">Cette action est irréversible. Toutes vos données seront supprimées.</p>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <button type="submit" class="btn btn-danger">Supprimer mon compte</button>
    </form>
    <a href="employe_dashboard.php" class="btn btn-secondary mt-3">Annuler</a>
</div>
</body>
</html>
