<?php
session_start();
require 'db.php';

// Vérification authentification
if (!isset($_SESSION['employe_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['employe_id'] ?? $_SESSION['admin_id'];
$is_admin = isset($_SESSION['admin_id']);

// Récupération du message
$query = $is_admin
    ? "SELECT m.*, GROUP_CONCAT(e.prenom, ' ', e.nom) AS destinataires 
       FROM messages m
       JOIN message_destinataires md ON m.id = md.message_id
       JOIN employes e ON md.destinataire_id = e.id
       WHERE m.id = ? AND m.expediteur_id = ?
       GROUP BY m.id"
    : "SELECT m.*, a.prenom AS expediteur_prenom, a.nom AS expediteur_nom 
       FROM messages m
       JOIN message_destinataires md ON m.id = md.message_id
       JOIN admins a ON m.expediteur_id = a.id
       WHERE m.id = ? AND md.destinataire_id = ?";

$stmt = $pdo->prepare($query);
$stmt->execute([$_GET['id'], $user_id]);
$message = $stmt->fetch();

if (!$message) {
    header("Location: messagerie.php");
    exit;
}

// Marquer comme lu (pour employés)
if (!$is_admin) {
    $pdo->prepare("UPDATE message_destinataires SET lu = TRUE, date_lecture = NOW() 
                   WHERE message_id = ? AND destinataire_id = ?")
       ->execute([$_GET['id'], $user_id]);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <a href="messagerie.php" class="btn btn-outline-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Retour
        </a>

        <div class="card">
            <div class="card-header">
                <h4><?= htmlspecialchars($message['sujet']) ?></h4>
                <div class="d-flex justify-content-between">
                    <small>
                        <?php if ($is_admin): ?>
                            À : <?= htmlspecialchars($message['destinataires']) ?>
                        <?php else: ?>
                            De : <?= htmlspecialchars($message['expediteur_prenom'] . ' ' . $message['expediteur_nom']) ?>
                        <?php endif; ?>
                    </small>
                    <small><?= date('d/m/Y H:i', strtotime($message['date_envoi'])) ?></small>
                </div>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($message['contenu'])) ?></p>
            </div>
        </div>

        <?php if (!$is_admin): ?>
        <div class="mt-3 text-muted">
            <small>Lu le : <?= date('d/m/Y H:i') ?></small>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>