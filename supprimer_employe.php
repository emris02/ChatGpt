<?php
session_start();
require 'db.php'; // Connexion à la base de données

// Vérifier si l'ID de l'employé est bien reçu
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $id = $_GET['id'];

    // Vérifier si l'employé existe avant de supprimer
    $stmt = $pdo->prepare("SELECT * FROM employes WHERE id = ?");
    $stmt->execute([$id]);
    $employe = $stmt->fetch();

    if ($employe) {
        // Supprimer l'employé
        $stmt = $pdo->prepare("DELETE FROM employes WHERE id = ?");
        if ($stmt->execute([$id])) {
            $_SESSION['message'] = "L'employé a été supprimé avec succès.";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Erreur lors de la suppression de l'employé.";
            $_SESSION['message_type'] = "danger";
        }
    } else {
        $_SESSION['message'] = "Employé introuvable.";
        $_SESSION['message_type'] = "warning";
    }
} else {
    $_SESSION['message'] = "ID d'employé invalide.";
    $_SESSION['message_type'] = "danger";
}

// Redirection vers la page admin_pointage.php avec le message
header("Location: super_admin_dashboard.php");
exit();
?>
