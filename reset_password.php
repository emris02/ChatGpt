<?php
require 'db.php';
session_start();

$message = '';
$validToken = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Vérifier le token en base
    $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $request = $stmt->fetch();
    
    if ($request) {
        $validToken = true;
        $email = $request['email'];
        
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $newPassword = $_POST['password'];
            $confirmPassword = $_POST['confirm_password'];
            
            if ($newPassword === $confirmPassword) {
                // Hacher le nouveau mot de passe
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Mettre à jour le mot de passe (dans les deux tables)
                $stmt = $pdo->prepare("UPDATE employes SET password = ? WHERE email = ?");
                $stmt->execute([$hashedPassword, $email]);
                
                $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE email = ?");
                $stmt->execute([$hashedPassword, $email]);
                
                // Supprimer le token utilisé
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->execute([$token]);
                
                // Enregistrer l'action
                file_put_contents('password_reset_emails.log', 
                    "[" . date('Y-m-d H:i:s') . "] Mot de passe réinitialisé pour: $email\n", 
                    FILE_APPEND);
                
                $_SESSION['message'] = "Votre mot de passe a été réinitialisé avec succès.";
                header('Location: login.php');
                exit();
            } else {
                $message = "Les mots de passe ne correspondent pas.";
            }
        }
    } else {
        $message = "Lien invalide ou expiré.";
    }
} else {
    $message = "Aucun token fourni.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .card {
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="card p-4">
        <h2 class="text-center mb-4">Réinitialiser le mot de passe</h2>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= $validToken ? 'warning' : 'danger' ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($validToken): ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="password" class="form-label">Nouveau mot de passe</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Réinitialiser</button>
            </form>
        <?php else: ?>
            <div class="text-center">
                <a href="forgot_password.php" class="btn btn-secondary">Demander un nouveau lien</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>