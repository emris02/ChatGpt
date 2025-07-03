<?php
session_start();
require 'db.php';

// Vérification des autorisations
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: login.php'); 
    exit();
}

$message = "";
$formData = []; // Pour conserver les données en cas d'erreur

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage des données
    $formData = [
        'nom' => htmlspecialchars(trim($_POST['nom'])),
        'prenom' => htmlspecialchars(trim($_POST['prenom'])),
        'email' => filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL),
        'telephone' => preg_replace('/[^0-9]/', '', $_POST['telephone']),
        'poste' => htmlspecialchars(trim($_POST['poste'])),
        'departement' => htmlspecialchars($_POST['departement']),
        'role' => ($_SESSION['role'] === 'super_admin') ? $_POST['role'] : 'admin',
        'adresse' => htmlspecialchars(trim($_POST['adresse']))
    ];

    // Validation
    $errors = [];
    
    if (empty($formData['nom'])) $errors[] = "Le nom est requis";
    if (empty($formData['prenom'])) $errors[] = "Le prénom est requis";
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if (strlen($formData['telephone']) < 8) $errors[] = "Numéro de téléphone invalide";
    if (empty($_POST['password']) || strlen($_POST['password']) < 8) $errors[] = "Le mot de passe doit contenir au moins 8 caractères";

    if (empty($errors)) {
        // Vérification email existant
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $stmt->execute([$formData['email']]);
        
        if ($stmt->fetch()) {
            $message = '<div class="alert alert-danger">Un compte avec cet email existe déjà.</div>';
        } else {
            // Hachage du mot de passe
            $passwordHash = password_hash($_POST['password'], PASSWORD_BCRYPT);
            
            // Insertion
            $stmt = $pdo->prepare("INSERT INTO admins 
                (nom, prenom, email, telephone, poste, departement, adresse, password, role) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            $success = $stmt->execute([
                $formData['nom'],
                $formData['prenom'],
                $formData['email'],
                $formData['telephone'],
                $formData['poste'],
                $formData['departement'],
                $formData['adresse'],
                $passwordHash,
                $formData['role']
            ]);

            if ($success) {
                $message = '<div class="alert alert-success">Administrateur ajouté avec succès!</div>';
                $formData = []; // Réinitialiser après succès
            } else {
                $message = '<div class="alert alert-danger">Erreur lors de l\'ajout.</div>';
            }
        }
    } else {
        $message = '<div class="alert alert-danger">'.implode('<br>', $errors).'</div>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajout d'un Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .form-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            width: 100%;
            max-width: 600px;
        }
        .form-title {
            color: #2c3e50;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
            width: 100%;
            padding: 10px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        .input-group-text {
            background-color: #e9ecef;
        }
        .password-toggle {
            cursor: pointer;
        }
        .department-icon {
            margin-right: 8px;
            color: #3498db;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2 class="form-title"><i class="fas fa-user-shield me-2"></i>Ajouter un Administrateur</h2>
        
        <?= $message ?>
        
        <form action="" method="POST" autocomplete="off">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="nom" class="form-label">Nom</label>
                    <input type="text" class="form-control" id="nom" name="nom" 
                           value="<?= $formData['nom'] ?? '' ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="prenom" class="form-label">Prénom</label>
                    <input type="text" class="form-control" id="prenom" name="prenom" 
                           value="<?= $formData['prenom'] ?? '' ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= $formData['email'] ?? '' ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="telephone" class="form-label">Téléphone</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-phone"></i></span>
                    <input type="tel" class="form-control" id="telephone" name="telephone" 
                           value="<?= $formData['telephone'] ?? '' ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="poste" class="form-label">Poste</label>
                <input type="text" class="form-control" id="poste" name="poste" 
                       value="<?= $formData['poste'] ?? '' ?>" required>
            </div>

            <div class="mb-3">
                <label for="adresse" class="form-label">Adresse</label>
                <textarea class="form-control" id="adresse" name="adresse" rows="2"><?= $formData['adresse'] ?? '' ?></textarea>
            </div>

            <div class="mb-3">
                <label for="departement" class="form-label">Département</label>
                    <select name="departement" class="form-select" id="departement" required>
                        <option value="">-- Sélectionner --</option>
                        <option value="depart_formation" <?= ($formData['departement'] ?? '') === 'depart_formation' ? 'selected' : '' ?>>
                            <i class="fas fa-graduation-cap department-icon"></i>Département Formation
                        </option>
                        <option value="depart_communication" <?= ($formData['departement'] ?? '') === 'depart_communication' ? 'selected' : '' ?>>
                            <i class="fas fa-bullhorn department-icon"></i>Département Communication
                        </option>
                        <option value="depart_informatique" <?= ($formData['departement'] ?? '') === 'depart_informatique' ? 'selected' : '' ?>>
                            <i class="fas fa-laptop-code department-icon"></i>Département Informatique
                        </option>
                        <option value="depart_consulting" <?= ($formData['departement'] ?? '') === 'depart_consulting' ? 'selected' : '' ?>>
                            <i class="fas fa-users department-icon"></i>Département GRH
                        </option>
                        <option value="depart_marketing&vente" <?= ($formData['departement'] ?? '') === 'depart_marketing&vente' ? 'selected' : '' ?>>
                            <i class="fas fa-sell department-icon"></i>Département GRH
                        </option>
                        <option value="administration" <?= ($formData['departement'] ?? '') === 'administration' ? 'selected' : '' ?>>
                            <i class="fas fa-building department-icon"></i>Administration
                        </option>
                    </select>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Mot de passe</label>
                <div class="input-group">
                    <input type="password" class="form-control" id="password" name="password" required>
                    <span class="input-group-text password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </span>
                </div>
                <div class="form-text">Minimum 8 caractères</div>
            </div>

            <?php if ($_SESSION['role'] === 'super_admin'): ?>
            <div class="mb-3">
                <label for="role" class="form-label">Rôle</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="admin" <?= ($formData['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="super_admin" <?= ($formData['role'] ?? '') === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                </select>
            </div>
            <?php endif; ?>

            <div class="d-grid gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Ajouter l'administrateur
                </button>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
                </a>
            </div>
        </form>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Formatage automatique du téléphone
        document.getElementById('telephone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 2) {
                value = value.substring(0, 2) + ' ' + value.substring(2);
            }
            if (value.length > 5) {
                value = value.substring(0, 5) + ' ' + value.substring(5);
            }
            if (value.length > 8) {
                value = value.substring(0, 8) + ' ' + value.substring(8);
            }
            e.target.value = value;
        });
    </script>
</body>
</html>