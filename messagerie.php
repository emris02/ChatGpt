<?php
session_start();
require 'db.php';

// Auth check
if (!isset($_SESSION['employe_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['employe_id'] ?? $_SESSION['admin_id'];
$is_admin = isset($_SESSION['admin_id']);

// Fetch messages
if ($is_admin) {
    $query = "SELECT m.*, 
                     GROUP_CONCAT(CONCAT(e.prenom, ' ', e.nom) SEPARATOR ', ') AS destinataires 
              FROM messages m
              JOIN message_destinataires md ON m.id = md.message_id
              JOIN employes e ON md.destinataire_id = e.id
              WHERE m.expediteur_id = ?
              GROUP BY m.id
              ORDER BY m.date_envoi DESC";
} else {
    $query = "SELECT m.*, 
                     a.prenom AS expediteur_prenom, 
                     a.nom AS expediteur_nom, 
                     md.lu 
              FROM messages m
              JOIN message_destinataires md ON m.id = md.message_id
              JOIN admins a ON m.expediteur_id = a.id
              WHERE md.destinataire_id = ?
              ORDER BY m.date_envoi DESC";
}

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Marquer comme lu
if (isset($_GET['marquer_lu'], $_GET['id']) && !$is_admin) {
    $pdo->prepare("UPDATE message_destinataires 
                   SET lu = TRUE, date_lecture = NOW() 
                   WHERE message_id = ? AND destinataire_id = ?")
        ->execute([$_GET['id'], $user_id]);
    header("Location: messagerie.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Messagerie</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        .message-card {
            transition: 0.3s ease;
            border-left: 5px solid transparent;
        }
        .message-card:hover {
            background-color: #f0f0f0;
            transform: scale(1.01);
        }
        .message-non-lu {
            border-left-color: #0d6efd;
            background-color: #f9fbff;
        }
        .message-important {
            border-left-color: #dc3545;
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-envelope me-2"></i> Boîte de réception</h2>
        <?php if ($is_admin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nouveauMessageModal">
                <i class="fas fa-plus"></i> Nouveau message
            </button>
        <?php endif; ?>
    </div>

    <!-- Affichage des messages -->
    <div class="list-group shadow-sm">
        <?php if (empty($messages)): ?>
            <div class="text-muted p-3">Aucun message.</div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <a href="message_detail.php?id=<?= $message['id'] ?>&marquer_lu=1"
                   class="list-group-item list-group-item-action message-card 
                          <?= isset($message['lu']) && !$message['lu'] && !$is_admin ? 'message-non-lu' : '' ?>">
                    <div class="d-flex justify-content-between">
                        <h5 class="mb-1"><?= htmlspecialchars($message['sujet'], ENT_QUOTES) ?></h5>
                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($message['date_envoi'])) ?></small>
                    </div>
                    <p class="mb-1"><?= htmlspecialchars(substr($message['contenu'], 0, 100), ENT_QUOTES) ?>...</p>
                    <small class="text-muted">
                        <?php if ($is_admin): ?>
                            À : <?= htmlspecialchars($message['destinataires'], ENT_QUOTES) ?>
                        <?php else: ?>
                            De : <?= htmlspecialchars($message['expediteur_prenom'] . ' ' . $message['expediteur_nom'], ENT_QUOTES) ?>
                        <?php endif; ?>
                    </small>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nouveau Message -->
<?php if ($is_admin): ?>
<div class="modal fade" id="nouveauMessageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" action="envoyer_message.php" method="POST">
            <div class="modal-header">
                <h5 class="modal-title">Nouveau message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Type de destinataire</label>
                    <div class="d-flex gap-3 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="dest_type" id="dest_type_employe" value="employe" checked>
                            <label class="form-check-label" for="dest_type_employe">Employé individuel</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="dest_type" id="dest_type_departement" value="departement">
                            <label class="form-check-label" for="dest_type_departement">Département</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="dest_type" id="dest_type_general" value="general">
                            <label class="form-check-label" for="dest_type_general">Général (tous)</label>
                        </div>
                    </div>
                    <div id="select-employe" class="mt-2">
                        <label for="destinataires_employe">Sélectionner un ou plusieurs employés</label>
                        <select name="destinataires[]" id="destinataires_employe" class="form-select" multiple>
                            <?php
                            $employes = $pdo->query("SELECT id, prenom, nom FROM employes ORDER BY nom")->fetchAll();
                            foreach ($employes as $emp):
                            ?>
                                <option value="<?= $emp['id'] ?>">
                                    <?= htmlspecialchars($emp['prenom'] . ' ' . $emp['nom'], ENT_QUOTES) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="select-departement" class="mt-2" style="display:none;">
                        <label for="destinataires_departement">Sélectionner un département</label>
                        <select name="departement" id="destinataires_departement" class="form-select">
                            <option value="">-- Choisir un département --</option>
                            <?php
                            $departements = $pdo->query("SELECT DISTINCT departement FROM employes WHERE departement IS NOT NULL AND departement != '' ORDER BY departement")->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($departements as $dep):
                            ?>
                                <option value="<?= htmlspecialchars($dep, ENT_QUOTES) ?>">
                                    <?= htmlspecialchars(ucfirst(str_replace('depart_', '', $dep)), ENT_QUOTES) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="general" id="destinataires_general" value="0">
                </div>
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    function showDest(type) {
                        document.getElementById('select-employe').style.display = (type === 'employe') ? '' : 'none';
                        document.getElementById('select-departement').style.display = (type === 'departement') ? '' : 'none';
                        document.getElementById('destinataires_general').value = (type === 'general') ? '1' : '0';
                    }
                    document.querySelectorAll('input[name="dest_type"]').forEach(function(radio) {
                        radio.addEventListener('change', function() {
                            showDest(this.value);
                        });
                    });
                    showDest(document.querySelector('input[name="dest_type"]:checked').value);
                });
                </script>
                <div class="mb-3">
                    <label>Sujet</label>
                    <input type="text" name="sujet" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label>Message</label>
                    <textarea name="contenu" class="form-control" rows="5" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" class="btn btn-primary">Envoyer</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
