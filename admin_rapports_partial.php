<?php
require 'db.php';
// À adapter selon ta table de rapports/statistiques
$rapports = $pdo->query("SELECT titre, date_rapport, resume FROM rapports ORDER BY date_rapport DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
?>
<button id="retourDemandesRapports" class="btn btn-outline-primary mb-3">
    <i class="fas fa-arrow-left me-1"></i> Retour aux demandes
</button>
<h4>Liste des rapports</h4>
<table class="table table-striped">
    <thead>
        <tr>
            <th>Titre</th>
            <th>Date</th>
            <th>Résumé</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rapports as $rapport): ?>
        <tr>
            <td><?= htmlspecialchars($rapport['titre']) ?></td>
            <td><?= date('d/m/Y', strtotime($rapport['date_rapport'])) ?></td>
            <td><?= htmlspecialchars($rapport['resume']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>