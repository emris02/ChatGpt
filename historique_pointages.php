<?php
require_once 'db.php';

// Requête pour afficher tous les pointages détaillés des employés
$sql = "SELECT 
            e.nom,
            e.prenom,
            e.email,
            e.departement,
            e.poste,
            p.date_heure,
            p.type,
            p.retard_cause,
            p.retard_justifie,
            p.est_justifie,
            p.commentaire,
            p.type_justification,
            p.temps_total,
            p.date_justification
        FROM pointages p
        INNER JOIN employes e ON p.employe_id = e.id
        ORDER BY p.date_heure DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Historique des Pointages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f9f9f9;
            padding: 2rem;
        }
        h2 {
            text-align: center;
            margin-bottom: 2rem;
        }
        table {
            font-size: 0.95rem;
        }
        th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }
        td {
            text-align: center;
            vertical-align: middle;
        }
        .badge {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

<h2>Historique complet des pointages des employés</h2>

<div class="table-responsive">
    <table class="table table-bordered table-striped shadow">
        <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Email</th>
                <th>Département</th>
                <th>Poste</th>
                <th>Date & Heure</th>
                <th>Type</th>
                <th>Temps Travaillé</th>
                <th>Retard ?</th>
                <th>Cause</th>
                <th>Justifié ?</th>
                <th>Type de Justification</th>
                <th>Date de Justification</th>
                <th>Commentaire</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pointages as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nom']) ?></td>
                    <td><?= htmlspecialchars($p['prenom']) ?></td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td><?= htmlspecialchars($p['departement'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['poste'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['date_heure']) ?></td>
                    <td>
                        <span class="badge <?= $p['type'] == 'arrivee' ? 'bg-success' : 'bg-warning text-dark' ?>">
                            <?= ucfirst($p['type']) ?>
                        </span>
                    </td>
                    <td><?= $p['temps_total'] ?? '—' ?></td>
                    <td>
                        <?php if ($p['retard_justifie'] === 'oui'): ?>
                            <span class="badge bg-danger">Oui</span>
                        <?php elseif ($p['retard_justifie'] === 'non'): ?>
                            <span class="badge bg-secondary">Non</span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($p['retard_cause'] ?? '-') ?></td>
                    <td>
                        <?= $p['est_justifie'] ? '<span class="badge bg-success">Oui</span>' : '<span class="badge bg-danger">Non</span>' ?>
                    </td>
                    <td><?= $p['type_justification'] ?? '-' ?></td>
                    <td><?= $p['date_justification'] ?? '-' ?></td>
                    <td><?= htmlspecialchars($p['commentaire'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($pointages)): ?>
                <tr><td colspan="14">Aucun pointage trouvé.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
