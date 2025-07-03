<?php
session_start();
require 'db.php';

// Vérification authentification
if (!isset($_SESSION['employe_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Vérifier si l'ID est présent
if (!isset($_GET['id'])) {
    header('Location: admin_dashboard.php');
    exit();
}

$employe_id = $_GET['id'];

// Récupérer les informations actuelles de l'employé
$stmt = $pdo->prepare("SELECT * FROM employes WHERE id = ?");
$stmt->execute([$employe_id]);
$employe = $stmt->fetch();

if (!$employe) {
    header('Location: admin_dashboard.php');
    exit();
}

$message = "";
$formData = $employe;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Nettoyage des données
    $formData = [
        'id' => $employe_id,
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
    $imagePath = $employe['photo'];
    if (!empty($_FILES['image']['name'])) {
        $targetDir = "uploads/employes/";
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
        $stmt = $pdo->prepare("UPDATE employes SET 
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
            $employe_id
        ]);

        if ($success) {
            $message = '<div class="alert alert-success">Employé modifié avec succès!</div>';
            // Recharger les données de l'employé
            $stmt = $pdo->prepare("SELECT * FROM employes WHERE id = ?");
            $stmt->execute([$employe_id]);
            $employe = $stmt->fetch();
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
    <title>Modifier Employé</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .avatar-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ddd;
        }
        .file-upload-label {
            cursor: pointer;
            display: inline-block;
            padding: 6px 12px;
            border: 1px dashed #ced4da;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-user-edit me-2"></i>Modifier Employé</h3>
                    </div>
                    <div class="card-body">
                        <?= $message ?>
                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="text-center mb-4">
                                <?php if (!empty($employe['photo'])): ?>
                                    <img src="<?= htmlspecialchars($employe['photo']) ?>" class="avatar-preview mb-2" id="imagePreview">
                                <?php else: ?>
                                    <div class="avatar-preview mb-2 bg-secondary text-white d-flex align-items-center justify-content-center" id="imagePreview">
                                        <i class="fas fa-user fa-3x"></i>
                                    </div>
                                <?php endif; ?>
                                <label for="image" class="file-upload-label">
                                    <i class="fas fa-camera me-2"></i>Changer la photo
                                    <input type="file" name="image" id="image" class="d-none" accept="image/*">
                                </label>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="prenom" class="form-label">Prénom</label>
                                    <input type="text" class="form-control" id="prenom" name="prenom" 
                                           value="<?= htmlspecialchars($employe['prenom']) ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="nom" class="form-label">Nom</label>
                                    <input type="text" class="form-control" id="nom" name="nom" 
                                           value="<?= htmlspecialchars($employe['nom']) ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($employe['email']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telephone" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="telephone" name="telephone" 
                                       value="<?= htmlspecialchars($employe['telephone']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="poste" class="form-label">Poste</label>
                                <input type="text" class="form-control" id="poste" name="poste" 
                                       value="<?= htmlspecialchars($employe['poste']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="departement" class="form-label">Département</label>
                                <select name="departement" class="form-select" required>
                                    <option value="depart_formation" <?= $employe['departement'] === 'depart_formation' ? 'selected' : '' ?>>Formation</option>
                                    <option value="depart_communication" <?= $employe['departement'] === 'depart_communication' ? 'selected' : '' ?>>Communication</option>
                                    <option value="depart_informatique" <?= $employe['departement'] === 'depart_informatique' ? 'selected' : '' ?>>Informatique</option>
                                    <option value="depart_consulting" <?= $employe['departement'] === 'depart_consulting' ? 'selected' : '' ?>>Consulting</option>
                                    <option value="depart_marketing&vente" <?= $employe['departement'] === 'depart_marketing&vente' ? 'selected' : '' ?>>Marketing & Vente</option>
                                    <option value="administration" <?= $employe['departement'] === 'administration' ? 'selected' : '' ?>>Administration</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="3"><?= htmlspecialchars($employe['adresse']) ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Enregistrer les modifications
                                </button>
                                <a href="profil_employe.php?id=<?= $employe_id ?>" class="btn btn-outline-secondary">
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
        // Aperçu de l'image sélectionnée
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    const preview = document.getElementById('imagePreview');
                    if (preview.tagName === 'IMG') {
                        preview.src = event.target.result;
                    } else {
                        // Remplacer la div par une image
                        const newPreview = document.createElement('img');
                        newPreview.id = 'imagePreview';
                        newPreview.className = 'avatar-preview mb-2';
                        newPreview.src = event.target.result;
                        preview.parentNode.replaceChild(newPreview, preview);
                    }
                }
                reader.readAsDataURL(file);
            }
        });

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