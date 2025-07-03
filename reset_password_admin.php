<?php
session_start();
require 'db.php';

// Vérification des autorisations
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

// Vérifier si l'ID est présent
if (!isset($_GET['id'])) {
    header('Location: admin_dashboard.php');
    exit();
}

$admin_id = $_GET['id'];

// Vérifier si le formulaire est soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($new_password) || strlen($new_password) < 8) {
        $_SESSION['error'] = "Le mot de passe doit contenir au moins 8 caractères";
        header("Location: admin_dashboard.php?panel=admins");
        exit();
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = "Les mots de passe ne correspondent pas";
        header("Location: admin_dashboard.php?panel=admins");
        exit();
    }

    // Hachage du nouveau mot de passe
    $passwordHash = password_hash($new_password, PASSWORD_BCRYPT);

    // Mise à jour du mot de passe
    $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
    $success = $stmt->execute([$passwordHash, $admin_id]);

    if ($success) {
        $_SESSION['success'] = "Mot de passe réinitialisé avec succès";
    } else {
        $_SESSION['error'] = "Erreur lors de la réinitialisation";
    }

    header("Location: admin_dashboard.php?panel=admins");
    exit();
}

// Si méthode GET, afficher le formulaire
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-header bg-warning text-white">
                        <h3 class="mb-0"><i class="fas fa-key me-2"></i>Réinitialisation du mot de passe</h3>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Minimum 8 caractères</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning text-white">
                                    <i class="fas fa-save me-2"></i>Enregistrer
                                </button>
                                <a href="admin_dashboard.php?panel=admins" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>