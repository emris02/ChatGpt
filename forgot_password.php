<?php
require 'db.php';
session_start();

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    // Vérifier si l'email existe
    $stmt = $pdo->prepare("SELECT id FROM employes WHERE email = ? UNION SELECT id FROM admins WHERE email = ?");
    $stmt->execute([$email, $email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Générer un token sécurisé
        $token = bin2hex(random_bytes(50));
        $expires = date("Y-m-d H:i:s", time() + 3600); // 1 heure
        
        // Stocker le token en base
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $token, $expires]);
        
        // Créer le lien de réinitialisation
        $resetLink = "http://localhost/reset_password.php?token=$token";
        
        // Enregistrer dans un fichier
        $emailContent = "
            Date: " . date('Y-m-d H:i:s') . "
            À: $email
            Objet: Réinitialisation de votre mot de passe
            
            Bonjour,
            
            Vous avez demandé à réinitialiser votre mot de passe. 
            Cliquez sur le lien suivant pour procéder :
            
            $resetLink
            
            Ce lien expirera dans 1 heure.
            
            Cordialement,
            L'équipe Xpert Pro
            --------------------------
        ";
        
        file_put_contents('password_reset_emails.log', $emailContent . "\n\n", FILE_APPEND);
        
        $_SESSION['message'] = "Un email de réinitialisation a été généré. Vérifiez le fichier password_reset_emails.log";
        header('Location: login.php');
        exit();
    } else {
        $message = "Aucun compte trouvé avec cette adresse email.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mot de passe oublié</title>
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
        <h2 class="text-center mb-4">Mot de passe oublié</h2>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Adresse email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Envoyer le lien</button>
        </form>
        
        <div class="text-center mt-3">
            <a href="login.php">Retour à la connexion</a>
        </div>
    </div>
</body>
</html>