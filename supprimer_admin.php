<?php
session_start();
require 'db.php'; // Connexion à la base de données

// Vérifier si un admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_pointage.php');
    exit();
}

// Récupérer la liste des admins
$sql = "SELECT id, nom, role, email FROM admins";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$admins = $stmt->fetchAll();

// Supprimer un admin
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // Supprimer l'admin de la base de données
    $sql = "DELETE FROM admins WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $delete_id);
    $stmt->execute();

    // Rediriger après suppression
    header('Location: admin_pointage.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center mb-4">Supprimer un Admin</h2>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>role</th>
                    <th>Email</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $admin): ?>
                <tr>
                    <td><?= htmlspecialchars($admin['nom']) ?></td>
                    <td><?= htmlspecialchars($admin['role']) ?></td>
                    <td><?= htmlspecialchars($admin['email']) ?></td>
                    <td>
                        <!-- Bouton pour supprimer l'admin -->
                        <a href="supprimer_admin.php?delete_id=<?= $admin['id'] ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet admin ?')">Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
