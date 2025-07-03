<?php
session_start();
require 'db.php';
require_once 'BadgeManager.php';

// Message de succès
if (isset($_GET['success']) && $_GET['success'] === 'badge_regenerer') {
    $success_message = 'Le badge a été régénéré avec succès.';
} elseif (isset($_GET['success']) && $_GET['success'] === 'badge_cree') {
    $success_message = 'Le badge a été créé avec succès.';
}

// Vérification des autorisations
// Autoriser aussi l'employé connecté pour AJAX badge
if (!isset($_SESSION['role']) && !isset($_SESSION['employe_id'])) {
    if (
        isset($_POST['demander_badge']) &&
        (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'))
    ) {
        // autorisé
    } else {
        header('Location: login.php');
        exit();
    }
}

// Si accès AJAX pour génération badge employé connecté, autoriser sans id
if (!isset($_GET['id'])) {
    // Si POST AJAX pour badge, autoriser pour l'employé connecté
    if (
        isset($_POST['demander_badge']) &&
        (isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')) &&
        isset($_SESSION['employe_id'])
    ) {
        $employe_id = $_SESSION['employe_id'];
    } else {
        header('Location: admin_dashboard.php');
        exit();
    }
} else {
    $employe_id = $_GET['id'];
}

// ✅ Récupérer les informations de l'employé + son badge actif
$stmt = $pdo->prepare("
    SELECT e.*, 
           b.token AS token, 
           b.token_hash, 
           b.created_at, 
           b.expires_at,
           b.type
    FROM employes e 
    LEFT JOIN (
        SELECT employe_id, token, token_hash, created_at, expires_at, type
        FROM badge_tokens
        WHERE employe_id = ?
          AND status = 'active'
          AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ) b ON e.id = b.employe_id
    WHERE e.id = ?
");
$stmt->execute([$employe_id, $employe_id]);
$employe = $stmt->fetch();

// Redirection si employé non trouvé
if (!$employe) {
    header('Location: admin_dashboard.php');
    exit();
}


// Statut actif du badge
$badge_actif = !empty($employe['token']) && strtotime($employe['expires_at']) > time();

// ⚙️ Format des infos
$departement = ucfirst(str_replace('depart_', '', $employe['departement']));
$initiale = strtoupper(substr($employe['prenom'], 0, 1)) . strtoupper(substr($employe['nom'], 0, 1));

// 🎨 Couleurs selon département
$departementColors = [
    'depart_formation' => 'bg-info',
    'depart_communication' => 'bg-warning',
    'depart_informatique' => 'bg-primary',
    'depart_grh' => 'bg-success',
    'administration' => 'bg-secondary'
];
$departementClass = $departementColors[$employe['departement']] ?? 'bg-dark';

// 📊 Pointages récents
$pointages = $pdo->prepare("SELECT type, date_heure FROM pointages WHERE employe_id = ? ORDER BY date_heure DESC LIMIT 10");
$pointages->execute([$employe_id]);

// 🕒 Temps de travail mensuel
$temps_mensuel = $pdo->prepare("
    SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(temps_total))) as total 
    FROM pointages 
    WHERE employe_id = ? 
      AND type = 'depart' 
      AND date_heure BETWEEN DATE_FORMAT(NOW(), '%Y-%m-01') AND LAST_DAY(NOW())
");
$temps_mensuel->execute([$employe_id]);
$temps = $temps_mensuel->fetch();
$temps_travail = $temps['total'] ?? '00:00:00';

$badge_type = $employe['type'] ?? 'inconnu';
// Correction : forcer l'id employé même si badge inconnu
$employe_id_for_js = $employe['id'] ?? ($_SESSION['employe_id'] ?? null);

// Traitement régénération via POST
if (isset($_POST['regenerer_badge'])) {
    $employe_id = $_POST['employe_id'];
    try {
        $result = BadgeManager::regenerateToken($employe_id, $pdo);
        if ($result['status'] === 'success') {
            header("Location: profil_employe.php?id=$employe_id&success=badge_regenerer");
            exit();
        } else {
            $error_message = 'Erreur lors de la régénération du badge.';
        }
    } catch (Exception $e) {
        $error_message = 'Erreur lors de la régénération du badge : ' . $e->getMessage();
    }
}

// Traitement création initiale de badge via POST (support AJAX)
if (isset($_POST['demander_badge'])) {
    $employe_id = $_POST['employe_id'] ?? ($_SESSION['employe_id'] ?? null);
    $isAjax = isset($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    try {
        $result = BadgeManager::regenerateToken($employe_id, $pdo);
        if ($result['status'] === 'success') {
            if ($isAjax) {
                // Format date FR
                $expires_at_fr = null;
                if (!empty($result['expires_at'])) {
                    $dt = new DateTime($result['expires_at'], new DateTimeZone('Europe/Paris'));
                    $expires_at_fr = $dt->format('d/m/Y à H:i');
                }
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Badge généré',
                    'token' => $result['token'] ?? null,
                    'expires_at' => $result['expires_at'] ?? null,
                    'expires_at_fr' => $expires_at_fr,
                    'type' => $result['type'] ?? 'inconnu'
                ]);
                exit();
            } else {
                header("Location: profil_employe.php?id=$employe_id&success=badge_cree");
                exit();
            }
        } else {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du badge.']);
                exit();
            } else {
                $error_message = 'Erreur lors de la création du badge.';
            }
        }
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du badge : ' . $e->getMessage()]);
            exit();
        } else {
            $error_message = 'Erreur lors de la création du badge : ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil de <?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="profil.css">
</head>
<body>
    <div class="profile-container">
        <!-- En-tête du profil -->
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center text-md-start mb-3 mb-md-0">
                    <?php if (!empty($employe['photo'])): ?>
                        <img src="<?= htmlspecialchars($employe['photo']) ?>" 
                             class="profile-avatar" 
                             alt="<?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?>">
                    <?php else: ?>
                        <div class="avatar-initials <?= $departementClass ?> mx-auto">
                            <?= $initiale ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-7 text-center text-md-start">
                    <h1 class="profile-name"><?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?></h1>
                    <p class="profile-position mb-2"><?= htmlspecialchars($employe['poste']) ?></p>
                    <span class="department-badge <?= $departementClass ?>">
                        <i class="fas fa-building me-1"></i>
                        <?= htmlspecialchars($departement) ?>
                    </span>
                </div>
                
                <div class="col-md-3 profile-actions mt-3 mt-md-0 text-center text-md-end">
                    <a href="admin_dashboard.php" class="btn btn-outline-light btn-sm">
                        <i class="fas fa-arrow-left me-1"></i> Retour
                    </a>
                    <a href="mailto:<?= htmlspecialchars($employe['email']) ?>" class="btn btn-light btn-sm ms-2">
                        <i class="fas fa-envelope me-1"></i> Email
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Messages d'alerte -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

    <!-- Corps du profil -->
    <div class="row g-2">
        <!-- Colonne Informations personnelles -->
        <div class="col-12 col-lg-4">
            <div class="card h-100">
                <div class="card-header py-2 bg-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-1"></i>Informations</h5>
                </div>
                <div class="card-body p-2">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item py-2 px-2 d-flex justify-content-between align-items-center">
                            <span class="text-nowrap"><i class="fas fa-envelope me-1 text-muted"></i> Email</span>
                            <a href="mailto:<?= htmlspecialchars($employe['email']) ?>" class="text-truncate ms-2" style="max-width: 60%">
                                <?= htmlspecialchars($employe['email']) ?>
                            </a>
                        </li>
                        <li class="list-group-item py-2 px-2 d-flex justify-content-between align-items-center">
                            <span class="text-nowrap"><i class="fas fa-phone me-1 text-muted"></i> Téléphone</span>
                            <a href="tel:<?= htmlspecialchars($employe['telephone']) ?>" class="text-decoration-none">
                                <?= htmlspecialchars($employe['telephone']) ?>
                            </a>
                        </li>
                        <li class="list-group-item py-2 px-2 d-flex justify-content-between align-items-center">
                            <span class="text-nowrap"><i class="fas fa-map-marker-alt me-1 text-muted"></i> Adresse</span>
                            <p class="mt-1 mb-0 small"><?= htmlspecialchars($employe['adresse']) ?></p>
                        </li>
                        <li class="list-group-item py-2 px-2 d-flex justify-content-between align-items-center">
                            <span class="text-nowrap"><i class="fas fa-calendar-alt me-1 text-muted"></i> Date d'ajout</span>
                            <span class="small"><?= date('d/m/Y', strtotime($employe['date_creation'])) ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Colonne centrale - Badge et statistiques -->
        <div class="col-12 col-lg-4">
            <!-- Badge d'accès -->
            <div class="card mb-2">
                <div class="card-header py-2 bg-white">
                    <h5 class="mb-0"><i class="fas fa-id-card me-1"></i>Badge d'accès - <?= htmlspecialchars($badge_type) ?></h5>
                </div>
                <div class="card-body p-2 text-center">
                    <?php if ($badge_actif): ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($employe['token_hash'] ?? '') ?>" 
                             class="badge-qr mb-1" 
                             alt="Badge d'accès"
                             data-bs-toggle="modal" 
                             data-bs-target="#badgeModal">
                        
                        <div class="badge-label small fw-bold">Badge actif</div>
                        <div class="badge-expiry small <?= (strtotime($employe['expires_at']) - time()) < 3600 ? 'text-warning' : 'text-muted' ?>">
                            <i class="fas fa-clock me-1"></i>
                            Valide jusqu'à <?= date('H:i', strtotime($employe['expires_at'])) ?>
                        </div>
                        
                        <div id="badge-timer" class="small fw-bold mt-1"></div>
                        
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="employe_id" value="<?= $employe['id'] ?>">
                            <button type="submit" name="regenerer_badge" class="btn btn-sm btn-outline-primary w-100">
                                <i class="fas fa-sync-alt me-1"></i> Régénérer
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 mb-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Badge expiré</strong>
                            <?php if (!empty($employe['expires_at'])): ?>
                                <p class="mb-0 small">Expiré depuis le <?= date('d/m/Y H:i', strtotime($employe['expires_at'])) ?></p>
                            <?php else: ?>
                                <p class="mb-0 small">Aucun badge valide généré</p>
                            <?php endif; ?>
                        </div>
                        
                        <form method="post">
                            <input type="hidden" name="employe_id" value="<?= $employe['id'] ?>">
                            <button type="submit" name="demander_badge" class="btn btn-sm btn-primary w-100">
                                <i class="fas fa-plus-circle me-1"></i>Créer badge
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="card">
                <div class="card-header py-2 bg-white">
                    <h5 class="mb-0"><i class="fas fa-chart-bar me-1"></i>Statistiques</h5>
                </div>
                <div class="card-body p-2">
                    <div class="row g-1">
                        <div class="col-6">
                            <div class="stat-card p-2 text-center bg-light rounded">
                                <div class="text-primary mb-1">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <h6 class="mb-0"><?= $temps_travail ?></h6>
                                <small class="text-muted">Temps mensuel</small>
                            </div>
                        </div>
                        
                        <div class="col-6">
                            <div class="stat-card p-2 text-center bg-light rounded">
                                <div class="text-success mb-1">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h6 class="mb-0"><?= $pointages->rowCount() ?></h6>
                                <small class="text-muted">Pointages</small>
                            </div>
                        </div>
                        
                        <div class="col-12 mt-2">
                            <a href="historique_pointages.php?id=<?= $employe['id'] ?>" class="btn btn-sm btn-outline-secondary w-100">
                                <i class="fas fa-list me-1"></i>Voir historique
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Colonne de droite - Pointages et actions -->
        <div class="col-12 col-lg-4">
            <!-- Derniers pointages -->
            <div class="card mb-2">
                <div class="card-header py-2 bg-white">
                    <h5 class="mb-0"><i class="fas fa-history me-1"></i>Derniers pointages</h5>
                </div>
                <div class="card-body p-2">
                    <?php if ($pointages->rowCount() > 0): ?>
                        <ul class="list-unstyled timeline">
                            <?php foreach ($pointages as $pointage): ?>
                                <li class="mb-2 d-flex">
                                    <div class="timeline-badge <?= $pointage['type'] === 'arrivee' ? 'bg-success' : 'bg-danger' ?>">
                                        <i class="fas fa-<?= $pointage['type'] === 'arrivee' ? 'sign-in-alt' : 'sign-out-alt' ?>"></i>
                                    </div>
                                    <div class="timeline-panel flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-0 small"><?= $pointage['type'] === 'arrivee' ? 'Arrivée' : 'Départ' ?></h6>
                                            <small class="text-muted"><?= date('H:i', strtotime($pointage['date_heure'])) ?></small>
                                        </div>
                                        <small class="text-muted"><?= date('d/m/Y', strtotime($pointage['date_heure'])) ?></small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <div class="text-center py-2">
                            <i class="fas fa-inbox text-muted mb-2"></i>
                            <p class="text-muted small">Aucun pointage enregistré</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-header py-2 bg-white">
                    <h5 class="mb-0"><i class="fas fa-cogs me-1"></i>Actions</h5>
                </div>
                <div class="card-body p-2">
                    <div class="d-grid gap-1">
                        <a href="modifier_employe.php?id=<?= $employe['id'] ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-1"></i> Modifier profil
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                            <i class="fas fa-key me-1"></i> Réinitialiser MDP
                        </button>
                        <form method="POST" action="delete_employe.php" class="d-grid">
                            <input type="hidden" name="id" value="<?= $employe['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                    onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet employé ?')">
                                <i class="fas fa-trash-alt me-1"></i> Supprimer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Réinitialisation mot de passe -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Réinitialiser le mot de passe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="reset_password.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="employe_id" value="<?= $employe['id'] ?>">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Nouveau mot de passe</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <script>
    // Compte à rebours du badge
    function updateBadgeTimer() {
        const expiresAt = new Date("<?= $employe['expires_at'] ?>").getTime();
        const now = new Date().getTime();
        const diff = expiresAt - now;
        
        if (diff > 0) {
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            
            let timerText = '';
            if (hours > 0) timerText += `${hours}h `;
            timerText += `${minutes}min`;
            
            const timerElement = document.getElementById("badge-timer");
            if (timerElement) {
                timerElement.innerHTML = `<i class="fas fa-hourglass-half me-1"></i>${timerText}`;
                
                if (hours === 0 && minutes < 30) {
                    timerElement.className = 'small fw-bold mt-1 text-warning';
                } else {
                    timerElement.className = 'small fw-bold mt-1 text-success';
                }
            }
        } else if (document.getElementById("badge-timer")) {
            document.getElementById("badge-timer").innerHTML = 
                `<i class="fas fa-exclamation-triangle me-1"></i>EXPIRÉ`;
            document.getElementById("badge-timer").className = 'small fw-bold mt-1 text-danger';
        }
    }
    
    // Initialiser et mettre à jour chaque minute
    if (document.getElementById("badge-timer")) {
        setInterval(updateBadgeTimer, 60000);
        updateBadgeTimer();
    }
    
    // Toggle password visibility
    document.getElementById('togglePassword')?.addEventListener('click', function() {
        const passwordInput = document.getElementById('new_password');
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
    });
</script>
</body>
</html>