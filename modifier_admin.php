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

// Récupérer les informations actuelles de l'admin
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    header('Location: admin_dashboard.php');
    exit();
}

$message = "";
$formData = $admins;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage des données
    $formData = [
        'id' => $admins_id,
        'nom' => htmlspecialchars(trim($_POST['nom'])),
        'prenom' => htmlspecialchars(trim($_POST['prenom'])),
        'email' => filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL),
        'telephone' => preg_replace('/[^0-9]/', '', $_POST['telephone']),
        'poste' => htmlspecialchars(trim($_POST['poste'])),
        'departement' => htmlspecialchars($_POST['departement']),
        'adresse' => htmlspecialchars(trim($_POST['adresse']))
    ];

    // Validation
    $errors = [];
    
    if (empty($formData['nom'])) $errors[] = "Le nom est requis";
    if (empty($formData['prenom'])) $errors[] = "Le prénom est requis";
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide";
    if (strlen($formData['telephone']) < 8) $errors[] = "Numéro de téléphone invalide";

    // Gestion de l'upload d'image
    $imagePath = $admins['photo'];
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "uploads/amin$adminss/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['image']['name']);
        $targetFile = $targetDir . $fileName;
        $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
        
        // Vérification du fichier
        $check = getimagesize($_FILES['image']['tmp_name']);
        if ($check === false) {
            $errors[] = "Le fichier n'est pas une image";
        } elseif ($_FILES['image']['size'] > 5000000) {
            $errors[] = "L'image est trop volumineuse (max 5MB)";
        } elseif (!in_array($imageFileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            $errors[] = "Seuls JPG, JPEG, PNG et GIF sont autorisés";
        } elseif (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            // Supprimer l'ancienne image si elle existe
            if ($imagePath && file_exists($imagePath)) {
                unlink($imagePath);
            }
            $imagePath = $targetFile;
        } else {
            $errors[] = "Erreur lors de l'upload de l'image";
        }
    }

    if (empty($errors)) {
        // Mise à jour dans la base de données
        $stmt = $pdo->prepare("UPDATE amin$adminss SET 
            nom = ?, prenom = ?, email = ?, telephone = ?, 
            poste = ?, departement = ?, adresse = ?, photo = ?
            WHERE id = ?");
            
        $success = $stmt->execute([
            $formData['nom'],
            $formData['prenom'],
            $formData['email'],
            $formData['telephone'],
            $formData['poste'],
            $formData['departement'],
            $formData['adresse'],
            $imagePath,
            $admins_id
        ]);

        if ($success) {
            $message = '<div class="alert alert-success">Employé modifié avec succès!</div>';
            // Recharger les données de l'employé
            $stmt = $pdo->prepare("SELECT * FROM amin$adminss WHERE id = ?");
            $stmt->execute([$admins_id]);
            $admins = $stmt->fetch();
        } else {
            $message = '<div class="alert alert-danger">Erreur lors de la modification.</div>';
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
    <title>Modifier Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ddd;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h3 class="mb-0"><i class="fas fa-user-cog me-2"></i>Modifier Administrateur</h3>
                    </div>
                    <div class="card-body">
                        <?= $message ?>
                        <form action="" method="POST">
                            <div class="text-center mb-4">
                                <div class="avatar-preview bg-primary mx-auto">
                                    <?= strtoupper(substr($admin['prenom'], 0, 1)) . strtoupper(substr($admin['nom'], 0, 1)) ?>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="prenom" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                           value="<?= htmlspecialchars($admin['prenom']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nom" class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?= htmlspecialchars($admin['nom']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($admin['email']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" 
                                       value="<?= htmlspecialchars($admin['telephone']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Rôle</label>
                                <select name="role" id="role" class="form-select" required>
                                    <option value="admin" <?= $admin['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                    <option value="super_admin" <?= $admin['role'] === 'super_admin' ? 'selected' : '' ?>>Super Administrateur</option>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Enregistrer
                                </button>
                                <a href="admin_dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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