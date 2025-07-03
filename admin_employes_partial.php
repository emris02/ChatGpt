<?php
require 'db.php';
$employes = $pdo->query('SELECT id, prenom, nom, poste, email, photo FROM employes')->fetchAll(PDO::FETCH_ASSOC);
?>
<table class="table table-hover">
    <thead>
        <button id="retourDemandes" class="btn btn-outline-primary mb-3">
    <i class="fas fa-arrow-left me-1"></i> Retour aux demandes
</button>
        <tr>
            <th>Photo</th>
            <th>Nom</th>
            <th>Poste</th>
            <th>Email</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($employes as $employe): ?>
        <tr>
            <td>
                <?php if (!empty($employe['photo'])): ?>
                    <img src="<?= htmlspecialchars($employe['photo']) ?>" alt="" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <div style="width:40px;height:40px;border-radius:50%;background:#ccc;display:flex;align-items:center;justify-content:center;">
                        <?= strtoupper(substr($employe['prenom'],0,1).substr($employe['nom'],0,1)) ?>
                    </div>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($employe['prenom'].' '.$employe['nom']) ?></td>
            <td><?= htmlspecialchars($employe['poste']) ?></td>
            <td><?= htmlspecialchars($employe['email']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>