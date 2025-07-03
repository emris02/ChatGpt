<?php
// Configuration de base
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db.php';

// Initialisation de la session
session_start();

// Message d'erreur par défaut
$message = "";

// Vérification si l'utilisateur est déjà connecté
if (isset($_SESSION['employe_id']) || isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Traitement du formulaire de connexion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération des données du formulaire
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);

    // Validation basique
    if (empty($email) || empty($password)) {
        $message = "Veuillez remplir tous les champs.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Format d'email invalide.";
    } else {
        try {
            // Tentative de connexion ADMIN
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();

            if ($admin) {
                if (password_verify($password, $admin['password'])) {
                    // Connexion admin réussie
                    $_SESSION['user_id'] = $admin['id'];
                    $_SESSION['role'] = $admin['role'];
                    $_SESSION['nom'] = $admin['nom'];
                    $_SESSION['prenom'] = $admin['prenom'];
                    
                    header("Location: admin_dashboard.php");
                    exit();
                }
            }

            // Tentative de connexion EMPLOYÉ
            $stmt = $pdo->prepare("SELECT * FROM employes WHERE email = ?");
            $stmt->execute([$email]);
            $employe = $stmt->fetch();

            if ($employe) {
                if (password_verify($password, $employe['password'])) {
                    // Connexion employé réussie
                    $_SESSION['employe_id'] = $employe['id'];
                    $_SESSION['role'] = $employe['role'];
                    $_SESSION['nom'] = $employe['nom'];
                    $_SESSION['prenom'] = $employe['prenom'];
                    
                    header("Location: employe_dashboard.php");
                    exit();
                }
            }

            // Si on arrive ici, les identifiants sont incorrects
            $message = "Email ou mot de passe incorrect.";

        } catch (PDOException $e) {
            // Journalisation de l'erreur
            error_log("Erreur de connexion: " . $e->getMessage());
            $message = "Une erreur technique est survenue. Veuillez réessayer plus tard.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Xpert Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .logo-container img {
            height: 70px;
            margin-bottom: 1rem;
        }
        
        .form-control {
            height: 50px;
            border-radius: 8px;
            padding-left: 15px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .input-group-text {
            background-color: transparent;
            border-right: none;
        }
        
        .btn-login {
            background-color: var(--primary-color);
            border: none;
            height: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #7f8c8d;
        }
        
        .footer-links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }
        
        @media (max-width: 576px) {
            .login-card {
                padding: 1.5rem;
                margin: 0 15px;
            }
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="logo-container">
        <img src="assets/xpertpro.png" alt="Xpert Pro" class="img-fluid">
        <h2 class="mb-4">Connexion à votre espace</h2>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="mb-3">
            <label for="email" class="form-label">Adresse email</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="votre@email.com" required
                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
            </div>
        </div>

        <div class="mb-3 position-relative">
            <label for="password" class="form-label">Mot de passe</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Votre mot de passe" required>
                <span class="password-toggle" onclick="togglePassword()">
                    <i class="fas fa-eye" id="toggleIcon"></i>
                </span>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember" name="remember">
                <label class="form-check-label" for="remember">Se souvenir de moi</label>
            </div>
            <a href="forgot_password.php" class="text-decoration-none">Mot de passe oublié ?</a>
        </div>

        <div class="d-grid gap-2 mb-3">
            <button type="submit" class="btn btn-primary btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Se connecter
            </button>
        </div>
    </form>

    <div class="footer-links">
        <p><a href="index.php" class="text-decoration-none"><i class="fas fa-arrow-left me-1"></i> Retour à l'accueil</a></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function togglePassword() {
        const passwordField = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            toggleIcon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            passwordField.type = 'password';
            toggleIcon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    }
    
    // Focus automatique sur le champ email
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('email').focus();
    });
</script>
</body>
</html>