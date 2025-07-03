<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';

header("Cache-Control: no-store, no-cache, must-revalidate");
header("Pragma: no-cache");

if (!isset($_SESSION['employe_id'])) {
    header("Location: login.php");
    exit;
}

$employe_id = $_SESSION['employe_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    str_contains($_SERVER["CONTENT_TYPE"] ?? '', 'application/json')) {

    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $field = $input['field'] ?? null;
    $value = trim($input['value'] ?? '');

    $allowedFields = ['nom', 'prenom', 'telephone', 'adresse', 'mot_de_passe'];

    if (!$field || !in_array($field, $allowedFields) || empty($value)) {
        echo json_encode(['success' => false, 'message' => 'Requête invalide.']);
        exit;
    }

    try {
        if ($field === 'mot_de_passe') {
            $value = password_hash($value, PASSWORD_DEFAULT);
        }

        $stmt = $pdo->prepare("UPDATE employes SET `$field` = :value WHERE id = :id");
        $stmt->execute([
            ':value' => $value,
            ':id' => $employe_id
        ]);

        echo json_encode(['success' => true, 'field' => $field, 'value' => $value]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
    }

    exit;
}


$stmt = $pdo->prepare("
    SELECT e.*, 
           b.token AS token, 
           b.token_hash, 
           b.created_at, 
           b.expires_at
    FROM employes e 
    LEFT JOIN (
        SELECT employe_id, token, token_hash, created_at, expires_at
        FROM badge_tokens
        WHERE employe_id = ? AND status = 'active' AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ) b ON e.id = b.employe_id
    WHERE e.id = ?
");
$stmt->execute([$employe_id, $employe_id]);
$employe = $stmt->fetch();

$departement = ucfirst(str_replace('depart_', '', $employe['departement']));
$initiale = strtoupper(substr($employe['prenom'], 0, 1)) . strtoupper(substr($employe['nom'], 0, 1));
$badge_actif = !empty($employe['token']) && strtotime($employe['expires_at']) > time();
$badge_type = $employe['type'] ?? 'inconnu';

$departementColors = [
    'depart_formation' => 'bg-info',
    'depart_communication' => 'bg-warning',
    'depart_informatique' => 'bg-primary',
    'depart_grh' => 'bg-success',
    'administration' => 'bg-secondary'
];
$departementClass = $departementColors[$employe['departement']] ?? 'bg-dark';

$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : date('m');
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

$selected_month = max(1, min(12, $selected_month));
$selected_year = max(2020, min(2030, $selected_year));

$prev_month = $selected_month - 1;
$prev_year = $selected_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $selected_month + 1;
$next_year = $selected_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

$current_month = date('m');
$current_year = date('Y');
$allow_next = !($selected_month == $current_month && $selected_year == $current_year);

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);

$workingDays = [];
for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $day);
    $dayOfWeek = date('N', strtotime($date));
    if ($dayOfWeek < 6) {
        $workingDays[] = $date;
    }
}

$stmt = $pdo->prepare("
    SELECT
        DATE(date_heure) AS jour,
        type,
        TIME(date_heure) AS heure
    FROM pointages
    WHERE employe_id = ?
    AND MONTH(date_heure) = ?
    AND YEAR(date_heure) = ?
    ORDER BY date_heure
");
$stmt->execute([$employe_id, $selected_month, $selected_year]);
$pointages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT DISTINCT DATE(date_heure) AS date
        FROM pointages
        WHERE employe_id = :id_employe
          AND MONTH(date_heure) = :month
          AND YEAR(date_heure) = :year";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    'id_employe' => $employe_id,
    'month' => $selected_month,
    'year' => $selected_year
]);
$pointedDates = $stmt->fetchAll(PDO::FETCH_COLUMN);

$absentDays = array_diff($workingDays, $pointedDates);
$absencesAutorisees = 0;
$absencesNonAutorisees = 0;

foreach ($absentDays as $absentDate) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM absences
        WHERE id_employe = :id_employe
          AND DATE(date_absence) = :date
          AND statut = 'autorisé'
    ");
    $stmt->execute([
        'id_employe' => $employe_id,
        'date' => $absentDate
    ]);
    if ($stmt->fetchColumn()) {
        $absencesAutorisees++;
    } else {
        $absencesNonAutorisees++;
    }
}

$temps_mensuel = $pdo->prepare("
    SELECT SEC_TO_TIME(SUM(TIME_TO_SEC(temps_total))) AS total
    FROM pointages
    WHERE employe_id = ?
      AND type = 'depart'
      AND MONTH(date_heure) = ?
      AND YEAR(date_heure) = ?
");
$temps_mensuel->execute([$employe_id, $selected_month, $selected_year]);
$temps = $temps_mensuel->fetch();
$temps_travail_mois = $temps['total'] ?? '00:00:00';

$pointages_par_jour = [];
foreach ($pointages as $pointage) {
    $date = $pointage['jour'];
    if (!isset($pointages_par_jour[$date])) {
        $pointages_par_jour[$date] = ['arrivee' => null, 'depart' => null];
    }
    if ($pointage['type'] === 'arrivee') {
        $pointages_par_jour[$date]['arrivee'] = $pointage['heure'];
    } elseif ($pointage['type'] === 'depart') {
        $pointages_par_jour[$date]['depart'] = $pointage['heure'];
    }
}

$stats = [
    'jours_presents' => 0,
    'jours_retards' => 0,
    'jours_absents' => 0,
    'jours_weekend' => 0,
    'temps_total' => 0
];

foreach ($pointages_par_jour as $date => $pointageDuJour) {
    $dayOfWeek = date('N', strtotime($date));

    if ($dayOfWeek == 7) {
        $stats['jours_weekend']++;
        continue;
    }

    if (isset($pointageDuJour['arrivee']) && !empty($pointageDuJour['arrivee'])) {
        $stats['jours_presents']++;

        if (strtotime($pointageDuJour['arrivee']) > strtotime('09:00:00')) {
            $stats['jours_retards']++;
        }
    } else {
        $dateWorkingDay = date('Y-m-d', strtotime($date));
        if (in_array($dateWorkingDay, $workingDays) && !in_array($dateWorkingDay, $pointedDates)) {
            $stats['jours_absents']++;
        }
    }
}

$retardsJustifies = 0;
$retardsNonJustifies = $stats['jours_retards'];

$stmt_derniers_pointages = $pdo->prepare("
    SELECT
        DATE(date_heure) AS jour,
        type,
        TIME(date_heure) AS heure
    FROM pointages
    WHERE employe_id = ?
    ORDER BY date_heure DESC
    LIMIT 5
");
$stmt_derniers_pointages->execute([$employe_id]);
$derniers_pointages = $stmt_derniers_pointages->fetchAll(PDO::FETCH_ASSOC);

$date_actuelle = date('d F Y');
$mois_annee = date('F Y', mktime(0, 0, 0, $selected_month, 1, $selected_year));
$jours_semaine = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

$dernier_depart = $pdo->prepare("SELECT date_heure FROM pointages WHERE employe_id = ? AND type = 'depart' ORDER BY date_heure DESC LIMIT 1");
$dernier_depart->execute([$employe_id]);
$depart = $dernier_depart->fetch();
$doit_regenerer = false;
if ($depart && strtotime($depart['date_heure']) < time() - 3600) {
    $doit_regenerer = true;
}

// Fetch the last 5 notifications for the employee
$stmt = $pdo->prepare("SELECT n.id, n.titre, n.contenu, n.date, p.type, p.date_heure FROM notifications n LEFT JOIN pointages p ON n.pointage_id = p.id WHERE n.employe_id = ? ORDER BY n.date DESC LIMIT 5");
$stmt->execute([$employe_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord - <?= htmlspecialchars($employe['prenom']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="employe_dash.css">
    <link rel="stylesheet" href="employe.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
    <style>
        .notification-item {
            border-bottom: 1px solid #e9ecef;
            padding: 10px;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-time {
            font-size: 12px;
            color: #6c757d;
        }

        .notification-content {
            margin-top: 5px;
            font-size: 14px;
        }

        .notification-item:hover {
            background-color: #f0f0f0;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- PANEL CALENDRIER (identique aux autres panels, bien positionné sous le header) -->
        <div class="container-fluid dashboard-content mt-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div id="calendrier" class="panel-section" style="display:none;">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <h4 class="mb-0"><i class="fas fa-calendar-alt me-2"></i> Calendrier des événements</h4>
                            </div>
                            <div class="card-body">
                                <div id="calendar-employe"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <div class="profile-card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-user me-2"></i>Mon Profil</h4>
                    <div class="position-relative">
                        <button class="btn btn-light position-relative" 
                                id="notificationDropdown" 
                                data-bs-toggle="dropdown" 
                                aria-expanded="false">
                            <i class="fas fa-bell fa-lg"></i>
                            <?php if (count($notifications) > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    <?= count($notifications) ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                            <li><h6 class="dropdown-header">Notifications récentes</h6></li>
                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center" href="detail_pointage.php?id=<?= $notification['id'] ?>">
                                            <div class="notification-icon bg-primary">
                                                <i class="fas fa-info-circle"></i>
                                            </div>
                                            <div class="ms-2">
                                                <div class="notification-title">
                                                    <?= htmlspecialchars($notification['titre'] ?? 'Notification') ?>
                                                </div>
                                                <div class="notification-content small">
                                                    <?= htmlspecialchars($notification['contenu'] ?? 'Contenu indisponible') ?>
                                                </div>
                                                <div class="notification-time text-muted small">
                                                    <?= date('d/m/Y H:i', strtotime($notification['date'] ?? 'now')) ?>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li class="text-center py-2">
                                    <a href="notifications.php" class="btn btn-link">Voir toutes les notifications</a>
                                </li>
                            <?php else: ?>
                                <li class="text-center py-3">
                                    <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                                    <p class="mb-0 text-muted">Aucune notification</p>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="card-content">
                <div class="row">
                    <div class="col-md-8">
                        <div class="profile-section">
                            <div class="d-flex flex-column">
                                <div class="d-flex align-items-center mb-4">
                                    <img src="<?= !empty($employe['photo']) ? htmlspecialchars($employe['photo']) : 'assets/default-profile.jpg' ?>" 
                                         class="profile-avatar me-4" 
                                         alt="Photo de profil">
                                    
                                    <div>
                                        <h3><?= htmlspecialchars($employe['prenom']) ?> <?= htmlspecialchars($employe['nom']) ?></h3>
                                        <p class="text-accent mb-2"><?= htmlspecialchars($employe['poste']) ?> | <?= htmlspecialchars($departement) ?></p>
                                        <div class="d-flex">
                                            <button class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#editPhotoModal">
                                                <i class="fas fa-camera me-1"></i> Changer photo
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editPasswordModal">
                                                <i class="fas fa-lock me-1"></i> Modifier mot de passe
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="profile-details-vertical">
                                    <div class="detail-item">
                                        <div class="detail-label">Matricule</div>
                                        <div class="detail-value">XPERT-<?= strtoupper(substr($employe['departement'], 0, 3)) ?><?= $employe['id'] ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Email</div>
                                        <div class="detail-value"><?= htmlspecialchars($employe['email']) ?></div>
                                    </div>
                                    
<!-- 📱 Téléphone -->
                <div class="detail-item">
                        <div class="detail-label">Téléphone</div>
                        <div class="detail-value" id="phoneDisplay"><?= htmlspecialchars($employe['telephone']) ?></div>
                        <input type="text" class="form-control d-none" id="phoneInput" value="<?= htmlspecialchars($employe['telephone']) ?>">    
                        <button class="btn btn-sm btn-outline-primary edit-inline"><i class="fas fa-edit"></i></button>

                        <button class="btn btn-sm btn-success d-none" id="savePhoneBtn" onclick="saveField('telephone', 'phone')">
                        <i class="fas fa-check"></i>
                        </button>
                </div>

<!-- 🏠 Adresse -->
                <div class="detail-item">
                        <div class="detail-label">Adresse</div>
                        <div class="detail-value" id="addressDisplay"><?= htmlspecialchars($employe['adresse']) ?></div>
                        <textarea class="form-control d-none" id="addressInput"><?= htmlspecialchars($employe['adresse']) ?></textarea>
                        <button class="btn btn-sm btn-outline-primary edit-inline"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-success d-none" id="saveAddressBtn" onclick="saveField('adresse', 'address')">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
                                  
                                    <div class="detail-item">
                                        <div class="detail-label">Embauché le</div>
                                        <div class="detail-value"><?= date('d/m/Y', strtotime($employe['date_creation'])) ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Dernier pointage</div>
                                        <div class="detail-value"><?= !empty($derniers_pointages) ? $derniers_pointages[0]['heure'] . ' (' . ucfirst($derniers_pointages[0]['type']) . ')' : 'Aucun' ?></div>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <div class="detail-label">Département</div>
                                        <div class="detail-value"><?= htmlspecialchars($departement) ?></div>
                                    </div>
                                </div>
                                
                                <div class="d-flex flex-wrap gap-2 mt-4">
                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                        <i class="fas fa-user-edit me-1"></i> Modifier mon profil
                                    </button>
                                    <button class="btn btn-outline-success">
                                        <i class="fas fa-file-pdf me-1"></i> Télécharger mon CV
                                    </button>
                                    <button class="btn btn-outline-info">
                                        <i class="fas fa-question-circle me-1"></i> Demander de l'aide
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="badge-section">
                            <div class="badge-header">
                                <h5><i class="fas fa-id-card me-2"></i>Badge d'accès - <span id="badge-type-label"><?= $badge_type ?></span></h5>
                                <div class="employee-id" id="badge-employee-id">XPERT-<?= strtoupper(substr($employe['departement'], 0, 3)) ?><?= $employe['id'] ?></div>
                            </div>
                            
                <?php if ($badge_actif): ?>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($employe['token'] ?? '') ?>" 
                             class="badge-qr mb-1" 
                             alt="Badge d'accès"
                             data-bs-toggle="modal" 
                             data-bs-target="#badgeModal">        
                        <div class="badge-label small fw-bold">Badge actif</div>
                        <div class="badge-expiry <?= (strtotime($employe['expires_at']) - time()) < 3600 ? 'badge-expiry-warning' : '' ?>">
                            Valide jusqu'au <?= date('d/m/Y à H:i', strtotime($employe['expires_at'])) ?>
                        </div>
                        
                        <div id="badge-timer" class="small fw-bold mt-1"></div>
                
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#badgeModal">
                                <i class="fas fa-expand me-1"></i> Voir en grand
                            </button>
                            <button class="btn btn-sm btn-outline-success flex-grow-1">
                                <i class="fas fa-print me-1"></i> Imprimer
                            </button>
                        </div>
                                
<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success mt-2">
        <?= $_SESSION['success_message'] ?>
    </div>
    <?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php else: ?>
    <div class="no-badge">
        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
        <p class="mb-3">Aucun badge actif</p>
        <div id="badge-success-message"></div>
        <div class="alert alert-info mt-2" id="badge-info-message">
            <strong>À propos du badge :</strong><br>
            <ul class="mb-0 ps-3">
                <li>Un badge d'accès est personnel et unique : il permet de pointer vos arrivées et départs.</li>
                <li>La création du badge est immédiate : cliquez sur "Demander un badge" pour générer votre premier badge.</li>
                <li>Un badge est valide pour une durée limitée (généralement 24h ou selon la politique de l'entreprise).</li>
                <li>Après chaque pointage de départ, un nouveau badge est automatiquement généré pour la prochaine session.</li>
                <li>En cas d'expiration ou de perte, vous pouvez régénérer un badge à tout moment.</li>
                <li>Présentez le QR code de votre badge à l'entrée/sortie pour valider votre présence.</li>
            </ul>
        </div>
        <button id="demanderBadgeBtn" type="button" class="btn btn-primary w-100">
            <i class="fas fa-sync-alt me-2"></i>Demander un badge
        </button>
        <div id="badge-loader" class="text-center mt-2" style="display:none;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <div>Génération du badge...</div>
        </div>
    </div>
<?php endif; ?>
                        <div class="additional-info mt-4">
                            <h5><i class="fas fa-info-circle me-2"></i> Informations complémentaires</h5>
                            <ul class="info-list">
                                <li>
                                    <i class="fas fa-medal text-primary"></i>
                                    <span>Ancienneté: <?= date('Y') - date('Y', strtotime($employe['date_creation'])) ?> ans</span>
                                </li>
                                <li>
                                    <i class="fas fa-calendar-check text-success"></i>
                                    <span>Congés restants: 
                                        <?php
                                        $stmt = $pdo->prepare("SELECT jours_restants FROM conges WHERE employe_id = ?");
                                        $stmt->execute([$employe_id]);
                                        $conges = $stmt->fetch();
                                        echo $conges ? htmlspecialchars($conges['jours_restants']) : '12';
                                        ?> jours
                                    </span>
                                </li>
                                <li>
                                    <i class="fas fa-file-contract text-info"></i>
                                    <span>Contrat: <?= htmlspecialchars($employe['type_contrat'] ?? 'CDI') ?></span>
                                </li>
                                <li>
                                    <i class="fas fa-user-tie text-warning"></i>
                                    <span>Manager: 
                                        <?php
                                        $stmt = $pdo->prepare("SELECT CONCAT(prenom, ' ', nom) AS manager 
                                                              FROM admins 
                                                              WHERE poste = ? AND role = 'manager' 
                                                              LIMIT 1");
                                        $stmt->execute([$employe['departement']]);
                                        $manager = $stmt->fetch();
                                        echo $manager ? htmlspecialchars($manager['manager']) : 'Non assigné';
                                        ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editEmailModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier mon email</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="emailForm">
                            <div class="mb-3">
                                <label for="currentEmail" class="form-label">Email actuel</label>
                                <input type="email" class="form-control" id="currentEmail" value="<?= htmlspecialchars($employe['email']) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="newEmail" class="form-label">Nouvel email</label>
                                <input type="email" class="form-control" id="newEmail" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirmEmail" class="form-label">Confirmer le nouvel email</label>
                                <input type="email" class="form-control" id="confirmEmail" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary">Enregistrer</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editPhoneModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier mon numéro de téléphone</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="phoneForm">
                            <div class="mb-3">
                                <label for="currentPhone" class="form-label">Téléphone actuel</label>
                                <input type="text" class="form-control" id="currentPhone" value="<?= htmlspecialchars($employe['telephone']) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="newPhone" class="form-label">Nouveau numéro</label>
                                <input type="text" class="form-control" id="newPhone" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary">Enregistrer</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editAddressModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier mon adresse</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addressForm">
                            <div class="mb-3">
                                <label for="currentAddress" class="form-label">Adresse actuelle</label>
                                <textarea class="form-control" id="currentAddress" rows="2" readonly><?= htmlspecialchars($employe['adresse']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="newAddress" class="form-label">Nouvelle adresse</label>
                                <textarea class="form-control" id="newAddress" rows="3" required></textarea>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary">Enregistrer</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editPhotoModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Changer ma photo de profil</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-4">
                            
                            <img src="<?= !empty($employe['photo']) ? htmlspecialchars($employe['photo']) : 'assets/default-profile.jpg' ?>" 
                                 class="img-fluid rounded-circle mb-3" 
                                 alt="Photo actuelle"
                                 style="width: 150px; height: 150px;">
                        </div>
                        <form id="photoForm">
                            <div class="mb-3">
                                <label for="formFile" class="form-label">Sélectionner une nouvelle photo</label>
                                <input class="form-control" type="file" id="formFile" accept="image/*">
                            </div>
                            <div class="form-text mb-3">
                                Taille maximale: 2MB. Formats acceptés: JPG, PNG.
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary">Télécharger</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editPasswordModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier mon mot de passe</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="passwordForm">
                            <div class="mb-3">
                                <label for="currentPassword" class="form-label">Mot de passe actuel</label>
                                <input type="password" class="form-control" id="currentPassword" required>
                            </div>
                            <div class="mb-3">
                                <label for="newPassword" class="form-label">Nouveau mot de passe</label>
                                <input type="password" class="form-control" id="newPassword" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirmer le nouveau mot de passe</label>
                                <input type="password" class="form-control" id="confirmPassword" required>
                            </div>
                            <div class="form-text">
                                Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="button" class="btn btn-primary">Enregistrer</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- MODAL de demande de badge -->

        <!-- Suppression du modal de demande de badge, plus nécessaire avec génération directe -->
        
        <div class="stats-grid">
            <div class="stat-card">
                <h5><i class="fas fa-clock me-2"></i> Présence ce mois</h5>
                <h2><?= $stats['jours_presents'] ?></h2>
                <small>Jours de présence</small>
            </div>
            <div class="stat-card">
                <h5><i class="fas fa-calendar-check me-2"></i> Pointages</h5>
                <h2><?= count($pointages) ?></h2>
                <small>Enregistrements</small>
            </div>
            <div class="stat-card">
                <h5><i class="fas fa-exclamation-triangle me-2"></i> Retards</h5>
                <h2><?= $stats['jours_retards'] ?></h2>
                <small>Jours avec retard</small>
            </div>
            <div class="stat-card">
                <h5><i class="fas fa-user-times me-2"></i> Absences</h5>
                <h2><?= $stats['jours_absents'] ?></h2>
                <small>Jours sans pointage</small>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="calendar-card" id="calendar-section">
                    <div class="card-header">
                        <h4><i class="fas fa-calendar-alt me-2"></i> Calendrier de présence</h4>
                    </div>
                    <div class="card-content">
                        <div class="calendar-header">
                            <div class="calendar-title">
                                <i class="fas fa-calendar me-2"></i> <?= $mois_annee ?>
                            </div>
                            <div class="calendar-nav">
                                <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>#calendar-section" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <a href="<?= $allow_next ? '?month='.$next_month.'&year='.$next_year.'#calendar-section' : '#' ?>" 
                                   class="btn btn-sm btn-outline-primary ms-2 <?= !$allow_next ? 'disabled' : '' ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="days-header">
                            <?php foreach ($jours_semaine as $jour): ?>
                                <div><?= $jour ?></div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="calendar-grid">
                            <?php
                            $firstDayOfMonth = date('N', strtotime("$selected_year-$selected_month-01"));
                            for ($i = 1; $i < $firstDayOfMonth; $i++): ?>
                                <div class="day-box future"></div>
                            <?php endfor;
                            
                            // Réinitialiser les stats pour recalcul avec demi-journées
                            $stats = [
                                'jours_presents' => 0,
                                'jours_retards' => 0,
                                'jours_absents' => 0,
                                'jours_weekend' => 0,
                                'demi_jours' => 0
                            ];
                            
                            for ($day = 1; $day <= $daysInMonth; $day++):
                                $date = sprintf('%04d-%02d-%02d', $selected_year, $selected_month, $day);
                                $class = 'future';
                                $status = '';
                                $isToday = ($day == date('j') && $selected_month == date('m') && $selected_year == date('Y'));
                                $dayOfWeek = date('N', strtotime($date));
                                $isHalfDay = ($dayOfWeek == 6); // Samedi = demi-journée
                                
                                if ($dayOfWeek == 7) { // Dimanche
                                    $class = 'weekend';
                                    $status = 'Week-end';
                                    $stats['jours_weekend']++;
                                } elseif (strtotime($date) <= time()) {
                                    if ($isHalfDay) {
                                        // Traitement spécial pour les samedis (demi-journées)
                                        $isPresent = false;
                                        $isLate = false;
                                        
                                        if (isset($pointages_par_jour[$date]) && !empty($pointages_par_jour[$date]['arrivee'])) {
                                            $heureArriveeTimestamp = strtotime($pointages_par_jour[$date]['arrivee']);
                                            
                                            // Vérifier si l'employé est arrivé avant 14h pour la demi-journée
                                            if ($heureArriveeTimestamp !== false && $heureArriveeTimestamp < strtotime('14:00:00')) {
                                                $isPresent = true;
                                                
                                                // Vérifier s'il est arrivé après 9h pour un retard
                                                if ($heureArriveeTimestamp > strtotime('09:00:00')) {
                                                    $class = 'retard';
                                                    $status = 'Retard';
                                                    $isLate = true;
                                                } else {
                                                    $class = 'presence';
                                                    $status = 'Présent';
                                                }
                                            }
                                        }
                                        
                                        if ($isPresent) {
                                            $stats['jours_presents'] += 0.5;
                                            if ($isLate) {
                                                $stats['jours_retards'] += 0.5;
                                            }
                                        } else {
                                            $class = 'absence';
                                            $status = 'Absent';
                                            $stats['jours_absents'] += 0.5;
                                        }
                                        
                                        $stats['demi_jours']++;
                                    } else {
                                        // Journée complète (lundi-vendredi)
                                        if (isset($pointages_par_jour[$date]) && !empty($pointages_par_jour[$date]['arrivee'])) {
                                            $heureArriveeTimestamp = strtotime($pointages_par_jour[$date]['arrivee']);
                                            if ($heureArriveeTimestamp !== false) {
                                                if ($heureArriveeTimestamp > strtotime('09:00:00')) {
                                                    $class = 'retard';
                                                    $status = 'Retard';
                                                    $stats['jours_retards']++;
                                                } else {
                                                    $class = 'presence';
                                                    $status = 'Présent';
                                                }
                                                $stats['jours_presents']++;
                                            }
                                        } else {
                                            $class = 'absence';
                                            $status = 'Absent';
                                            $stats['jours_absents']++;
                                        }
                                    }
                                }
                                ?>
                                <div class="day-box <?= $class ?> <?= $isToday ? 'today' : '' ?> <?= $isHalfDay ? 'half-day' : '' ?>"
                                     title="<?= htmlspecialchars("Jour $day - $status") ?>">
                                    <span class="day-number"><?= $day ?></span>
                                    <?php if ($status): ?>
                                        <span class="day-status"><?= $status ?></span>
                                    <?php endif; ?>
                                    <?php if ($isHalfDay): ?>
                                        <span class="half-day-indicator">½</span>
                                    <?php endif; ?>
                                </div>
                            <?php endfor;
                            
                            $lastDayOfMonth = date('N', strtotime("$selected_year-$selected_month-$daysInMonth"));
                            if ($lastDayOfMonth < 7) {
                                for ($i = $lastDayOfMonth; $i < 7; $i++) {
                                    echo '<div class="day-box future"></div>';
                                }
                            }
                            ?>
                        </div>
                        
                        <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                            <span class="status-badge bg-success">
                                <i class="fas fa-circle"></i> Présent <?= $stats['jours_presents'] ?> j
                            </span>
                            <span class="status-badge bg-warning">
                                <i class="fas fa-clock"></i> Retard <?= $stats['jours_retards'] ?> j
                            </span>
                            <span class="status-badge bg-danger">
                                <i class="fas fa-times"></i> Absent <?= $stats['jours_absents'] ?> j
                            </span>
                            <span class="status-badge bg-secondary">
                                <i class="fas fa-umbrella-beach"></i> Week-end <?= $stats['jours_weekend'] ?> j
                            </span>
                            <?php if ($stats['demi_jours'] > 0): ?>
                            <span class="status-badge bg-info">
                                <i class="fas fa-clock-half"></i> Demi-journées: <?= $stats['demi_jours'] ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="pointages-card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4><i class="fas fa-history me-2"></i> Mes derniers pointages</h4>
                            <?php if ($badge_actif): ?>
                                <a href="index.php?employe_id=<?= $_SESSION['employe_id'] ?>" class="btn btn-sm btn-light">
                                    <i class="fas fa-id-card me-1"></i> Afficher mon badge
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-content">
                        <?php if (!empty($derniers_pointages)): ?>
                            <div class="pointages-list">
                                <?php foreach ($derniers_pointages as $pointage): ?>
                                    <div class="pointage-item">
                                        <div class="pointage-header">
                                            <div>
                                                <strong class="<?= $pointage['type'] === 'arrivee' ? 'text-success' : 'text-danger' ?>">
                                                    <?= ucfirst($pointage['type']) ?>
                                                </strong>
                                                <span class="pointage-date ms-2"><?= date('d/m/Y', strtotime($pointage['jour'])) ?></span>
                                            </div>
                                            <div class="pointage-time">
                                                <?= $pointage['heure'] ?>
                                                <?php if ($pointage['type'] === 'arrivee' && strtotime($pointage['heure']) > strtotime('09:00:00')): ?>
                                                    <i class="fas fa-clock ms-1 text-warning"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun pointage enregistré</p>
                                <?php if ($badge_actif): ?>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#badgeModal">
                                        <i class="fas fa-id-card me-1"></i> Afficher mon badge
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-card mt-4">
                    <div class="card-header">
                        <h4><i class="fas fa-tasks me-2"></i> Actions rapides</h4>
                    </div>
                    <div class="card-content">
                        <div class="btn-group">
                            <a href="scan_qr.php" class="btn btn-primary">
                                <i class="fas fa-camera me-2"></i> Zone de pointage
                            </a>
                            <a href="historique_pointages.php" class="btn btn-outline-primary">
                                <i class="fas fa-history me-2"></i> Historique
                            </a>
                            <a href="logout.php" class="btn btn-outline-danger" onclick="return confirm('Déconnexion ?')">
                                <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="badgeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mon badge d'accès</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <?php if ($badge_actif): ?>
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=<?= urlencode($employe['token'] ?? '') ?>" 
                             class="img-fluid mb-3" 
                             alt="Badge d'accès">
                        <h5><?= htmlspecialchars($employe['prenom'] ?? '') ?> <?= htmlspecialchars($employe['nom'] ?? '') ?></h5>
                        <p class="text-muted mb-1"><?= htmlspecialchars($employe['poste'] ?? '') ?></p>
                        <p class="text-muted"><?= htmlspecialchars($departement ?? '') ?></p>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-clock me-2"></i>
                            Valide jusqu'au <?= date('d/m/Y à H:i', strtotime($employe['expires_at'])) ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> Aucun badge actif
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (element) {
            return new bootstrap.Tooltip(element);
        });     
        
        <?php if (isset($_GET['new_badge'])): ?>
            setTimeout(() => {
                const toast = document.createElement('div');
                toast.className = 'position-fixed bottom-0 end-0 p-3';
                toast.style.zIndex = '11';
                toast.innerHTML = `
                    <div class="toast show" role="alert">
                        <div class="toast-header bg-success text-white">
                            <strong class="me-auto">Nouveau badge généré</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            Votre badge a été régénéré avec succès. Valide jusqu'au <?= date('d/m/Y H:i', strtotime($badge_tokens['expires_at'])) ?>
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);          
                setTimeout(() => {
                    toast.remove();
                }, 5000);
            }, 1000);
        <?php endif; ?>

        <?php if (isset($_GET['badge_updated'])): ?>
            showToast('Badge mis à jour', 'Un nouveau badge a été généré automatiquement', 'success');
        <?php endif; ?>
        
        function showToast(title, message, type) {
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0 show`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${title}</strong><br>${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            document.getElementById('alertsContainer').appendChild(toast);
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }

        function checkBadgeExpiry() {
            const expiryElement = document.querySelector('.badge-expiry');
            if (expiryElement) {
                const expiryText = expiryElement.innerText;
                if (expiryText.includes("Badge actif") && expirationTimestamp <= Math.floor(Date.now() / 1000)) {
                    showToast('Badge Expire', 'Votre badge d\'accès a expiré. Veuillez le renouveler.', 'danger');
                    expiryElement.innerText = "Badge expiré";
                    expiryElement.classList.remove("text-success");
                    expiryElement.classList.add("text-danger");
                    clearInterval(timerInterval);
                }
            }
        }
        setInterval(checkBadgeExpiry, 60000);
        
    </script>
    <script>
    document.querySelectorAll('.edit-inline').forEach(btn => {
    btn.addEventListener('click', function () {
        const container = this.closest('.detail-item');
        const label = container.querySelector('.detail-label').textContent.trim().toLowerCase();
        const fieldMap = {
            'nom': 'nom',
            'prénom': 'prenom',
            'téléphone': 'telephone',
            'adresse': 'adresse',
            'email': 'email'
        };

        const field = fieldMap[label];
        const valueElement = container.querySelector('.detail-value');
        const currentValue = valueElement.textContent.trim();

        const input = document.createElement('input');
        input.type = 'text';
        input.value = currentValue;
        input.className = 'form-control form-control-sm mt-2';

        valueElement.replaceWith(input);
        this.textContent = '✔️';
        this.classList.remove('btn-outline-primary');
        this.classList.add('btn-success');

        this.addEventListener('click', function save() {
            fetch('employe_dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ field: field, value: input.value })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const newSpan = document.createElement('div');
                    newSpan.className = 'detail-value';
                    newSpan.textContent = input.value;
                    input.replaceWith(newSpan);
                    this.innerHTML = '<i class="fas fa-edit"></i>';
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-primary');
                } else {
                    alert(data.message || 'Erreur inconnue');
                }
            })
            .catch(err => alert('Erreur réseau : ' + err.message));
        }, { once: true });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="assets/js/calendar-employe.js"></script>
<script src="main.js"></script>
<script>
// Correction affichage du type de badge et de l'ID même si badge inconnu
document.addEventListener('DOMContentLoaded', function() {
    // Si le type est "inconnu" mais l'employé existe, afficher l'ID
    const badgeType = document.getElementById('badge-type-label');
    const badgeId = document.getElementById('badge-employee-id');
    if (badgeType && badgeType.textContent.trim() === 'inconnu' && badgeId) {
        badgeId.style.opacity = 1;
    }
});
</script>
<script>
// Génération immédiate du badge via AJAX
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('demanderBadgeBtn');
    if (btn) {
        btn.addEventListener('click', function() {
            btn.disabled = true;
            document.getElementById('badge-success-message').innerHTML = '';
            document.getElementById('badge-loader').style.display = 'block';
            fetch('profil_employe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'demander_badge=1&ajax=1'
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('badge-loader').style.display = 'none';
                if (data.success && data.token && data.expires_at) {
                    document.getElementById('badge-success-message').innerHTML = '<div class="alert alert-success mt-2">Badge généré avec succès !</div>';
                    var infoMsg = document.getElementById('badge-info-message');
                    if (infoMsg) infoMsg.style.display = 'none';
                    // Remplacer la zone no-badge par le badge actif
                    var noBadge = document.querySelector('.no-badge');
                    if (noBadge) {
                        noBadge.innerHTML = `
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=${encodeURIComponent(data.token)}" 
                                 class="badge-qr mb-1" 
                                 alt="Badge d'accès"
                                 data-bs-toggle="modal" 
                                 data-bs-target="#badgeModal">
                            <div class="badge-label small fw-bold">Badge actif</div>
                            <div class="badge-expiry">
                                Valide jusqu'au ${data.expires_at_fr || data.expires_at}
                            </div>
                            <div id="badge-timer" class="small fw-bold mt-1"></div>
                            <div class="d-flex gap-2 mt-3">
                                <button class="btn btn-sm btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#badgeModal">
                                    <i class="fas fa-expand me-1"></i> Voir en grand
                                </button>
                                <button class="btn btn-sm btn-outline-success flex-grow-1">
                                    <i class="fas fa-print me-1"></i> Imprimer
                                </button>
                            </div>
                        `;
                    }
                } else {
                    document.getElementById('badge-success-message').innerHTML = '<div class="alert alert-danger mt-2">' + (data.message || 'Erreur lors de la génération du badge') + '</div>';
                    btn.disabled = false;
                }
            })
            .catch(() => {
                document.getElementById('badge-loader').style.display = 'none';
                document.getElementById('badge-success-message').innerHTML = '<div class="alert alert-danger mt-2">Erreur réseau</div>';
                btn.disabled = false;
            });
        });
    }
});
</script>
</body>
</html>