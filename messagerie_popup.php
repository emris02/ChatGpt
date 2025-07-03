<?php
require 'db.php';
if (!isset($_SESSION)) session_start();
$user_id = $_SESSION['employe_id'] ?? $_SESSION['admin_id'];
$is_admin = isset($_SESSION['admin_id']);
// Pas de header(), pas de <html> ni <body> ici !

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
?>
<div class="mb-4">
    <h2><i class="fas fa-envelope me-2"></i> Boîte de réception</h2>
</div>
<div class="list-group shadow-sm">
    <?php if (empty($messages)): ?>
        <div class="text-muted p-3">Aucun message.</div>
    <?php else: ?>
        <?php foreach ($messages as $message): ?>
            <a href="#" class="list-group-item list-group-item-action message-card <?= isset($message['lu']) && !$message['lu'] && !$is_admin ? 'message-non-lu' : '' ?>" data-id="<?= $message['id'] ?>">
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

<!-- Modal Nouveau Message (identique à messagerie.php) -->


<?php if ($is_admin): ?>
<form class="card p-3 mb-4" action="envoyer_message.php" method="POST" id="form-nouveau-message">
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
    <div class="mb-3">
        <label>Sujet</label>
        <input type="text" name="sujet" class="form-control" required>
    </div>
    <div class="mb-3">
        <label>Message</label>
        <textarea name="contenu" class="form-control" rows="5" required></textarea>
    </div>
    <div class="d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary">Envoyer</button>
    </div>
</form>
<script>
// Affichage dynamique des sélecteurs
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
<?php endif; ?>
