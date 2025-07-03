<?php
session_start();

// Vérifier si l'employé est toujours connecté
if (isset($_SESSION['employe_id'])) {
    // Si l'employé est toujours connecté, on le déconnecte
    session_destroy();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte Supprimé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5 text-center">
    <h2 class="text-success">Votre compte a été supprimé avec succès.</h2>
    <p>Nous sommes désolés de vous voir partir. Si vous changez d'avis, vous pouvez toujours revenir et vous réinscrire.</p>
    <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
</div>
</body>
</html>
