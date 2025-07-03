<?php
require 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<div class='alert alert-danger'>ID de demande manquant ou invalide.</div>";
    exit();
}

$demande_id = (int)$_GET['id'];

// On récupère la demande + l'employé + l'admin si traité
$stmt = $pdo->prepare("
    SELECT d.*, 
           e.nom AS emp_nom, e.prenom AS emp_prenom, e.email AS emp_email, e.telephone AS emp_tel, e.poste AS emp_poste, e.departement AS emp_dept, e.photo AS emp_photo,
           a.nom AS admin_nom, a.prenom AS admin_prenom
      FROM demandes d
      JOIN employes e ON d.employe_id = e.id
      LEFT JOIN admins a ON d.traite_par = a.id
     WHERE d.id = ?
     LIMIT 1
");
$stmt->execute([$demande_id]);
$demande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$demande) {
    echo "<div class='alert alert-danger'>Demande introuvable.</div>";
    exit();
}

$badge_class = [
    'en_attente' => 'warning',
    'approuve'   => 'success',
    'rejete'     => 'danger'
][$demande['statut']] ?? 'secondary';

?>
<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body text-center">
                <?php if (!empty($demande['emp_photo'])): ?>
                    <img src="<?= htmlspecialchars($demande['emp_photo']) ?>" class="rounded-circle mb-3" width="120" height="120" style="object-fit:cover;">
                <?php else: ?>
                    <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mb-3" style="width:120px;height:120px;font-size:2.5rem;color:#fff;">
                        <?= strtoupper(substr($demande['emp_prenom'],0,1).substr($demande['emp_nom'],0,1)) ?>
                    </div>
                <?php endif; ?>
                <h4><?= htmlspecialchars($demande['emp_prenom'].' '.$demande['emp_nom']) ?></h4>
                <p class="text-muted mb-1"><?= htmlspecialchars($demande['emp_poste']) ?></p>
                <p class="text-muted"><?= htmlspecialchars($demande['emp_dept']) ?></p>
                <hr>
                <p class="mb-1"><i class="fas fa-envelope me-1"></i> <?= htmlspecialchars($demande['emp_email']) ?></p>
                <p class="mb-0"><i class="fas fa-phone me-1"></i> <?= htmlspecialchars($demande['emp_tel']) ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-info-circle me-1"></i>Détails de la demande</h5>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Type :</strong> <span class="text-capitalize"><?= htmlspecialchars($demande['type'] ?? '-') ?></span></p>
                        <p><strong>Date demande :</strong> <?= date('d/m/Y H:i', strtotime($demande['date_demande'])) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Statut :</strong> 
                            <span class="badge bg-<?= $badge_class ?>">
                                <?= ucfirst($demande['statut']) ?>
                            </span>
                        </p>
                        <?php if ($demande['date_traitement']): ?>
                            <p><strong>Traité le :</strong> <?= date('d/m/Y H:i', strtotime($demande['date_traitement'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <h6>Motif / Raison :</h6>
                    <p class="p-3 bg-light rounded"><?= nl2br(htmlspecialchars($demande['raison'] ?? '-')) ?></p>
                </div>
                <?php if (!empty($demande['commentaire'])): ?>
                <div class="mb-3">
                    <h6>Commentaire :</h6>
                    <p class="p-3 bg-light rounded"><?= nl2br(htmlspecialchars($demande['commentaire'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($demande['statut']=='approuve' && $demande['admin_nom']): ?>
                    <p class="text-muted"><small>Approuvé par&nbsp;: <?= htmlspecialchars($demande['admin_prenom'].' '.$demande['admin_nom']) ?></small></p>
                <?php elseif ($demande['statut']=='rejete' && $demande['admin_nom']): ?>
                    <p class="text-muted"><small>Rejeté par&nbsp;: <?= htmlspecialchars($demande['admin_prenom'].' '.$demande['admin_nom']) ?></small></p>
                <?php elseif ($demande['admin_nom']): ?>
                    <p class="text-muted"><small>Traité par&nbsp;: <?= htmlspecialchars($demande['admin_prenom'].' '.$demande['admin_nom']) ?></small></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>