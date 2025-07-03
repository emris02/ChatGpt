<?php
session_start();
require 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];
$admin = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$admin->execute([$admin_id]);
$admin = $admin->fetch();

// Gestion de la mise à jour
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Vérification du mot de passe actuel
    if (!empty($password)) {
        if (password_verify($password, $admin['password'])) {
            // Mise à jour des infos
            $updateFields = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email
            ];
            if (!empty($new_password) && $new_password === $confirm_password) {
                $updateFields['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            } elseif (!empty($new_password)) {
                $error = "Les mots de passe ne correspondent pas.";
            }
            if (!$error) {
                $set = [];
                $params = [];
                foreach ($updateFields as $k => $v) {
                    $set[] = "$k = ?";
                    $params[] = $v;
                }
                $params[] = $admin_id;
                $sql = "UPDATE admins SET ".implode(', ', $set)." WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute($params);
                if ($success) {
                    $_SESSION['admin_nom'] = $nom;
                    $admin = array_merge($admin, $updateFields);
                }
            }
        } else {
            $error = "Mot de passe actuel incorrect.";
        }
    } else {
        $error = "Veuillez saisir votre mot de passe actuel pour valider les modifications.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paramètres du profil admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fb; }
        .settings-card { max-width: 500px; margin: 40px auto; border-radius: 16px; box-shadow: 0 8px 32px rgba(67,97,238,0.08); }
        .avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #4361ee; margin-bottom: 10px; }
        .form-label { font-weight: 500; }
        .form-control:focus { border-color: #4361ee; box-shadow: 0 0 0 0.2rem rgba(67,97,238,0.15); }
        .btn-primary { background: linear-gradient(90deg,#4361ee,#3f37c9); border: none; }
        .btn-primary:hover { background: #3f37c9; }
        .settings-title { letter-spacing: 1px; }
    </style>
</head>
<body>
<div class="container">
    <div class="settings-card bg-white p-4 mt-5">
        <div class="text-center mb-4">
            <img src="assets/xpertpro.png" alt="Avatar" class="avatar shadow">
            <h3 class="settings-title mb-0">Paramètres du profil</h3>
            <div class="text-muted small">Modifiez vos informations personnelles et votre mot de passe</div>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success">✅ Profil mis à jour avec succès.</div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" value="<?= htmlspecialchars($admin['nom']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Prénom</label>
                <input type="text" name="prenom" class="form-control" value="<?= htmlspecialchars($admin['prenom']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email']) ?>" required>
            </div>
            <hr>
            <div class="mb-3">
                <label class="form-label">Mot de passe actuel <span class="text-danger">*</span></label>
                <input type="password" name="password" class="form-control" required placeholder="Obligatoire pour valider les modifications">
            </div>
            <div class="mb-3">
                <label class="form-label">Nouveau mot de passe</label>
                <input type="password" name="new_password" class="form-control" placeholder="Laisser vide pour ne pas changer">
            </div>
            <div class="mb-3">
                <label class="form-label">Confirmer le nouveau mot de passe</label>
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirmer le nouveau mot de passe">
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Enregistrer</button>
                <a href="admin_dashboard.php" class="btn btn-outline-secondary">Retour au dashboard</a>
            </div>
        </form>
    </div>
    <div class="text-center mt-4">
        <a href="mailto:support@xpertpro.com" class="text-decoration-none text-primary"><i class="fas fa-life-ring me-1"></i>Besoin d'aide ? Contacter le support</a>
    </div>
</div>
</body>
</html>
