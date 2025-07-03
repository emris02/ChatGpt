<?php
session_start();
require 'db.php';

// Vérification de session sécurisée
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: login.php");
    exit();
}

// Initialisation sécurisée des variables
$_SESSION['admin_id'] = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 0;
$_SESSION['last_activity'] = time();

// Traitement AJAX sécurisé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $demande_id = filter_input(INPUT_POST, 'id_demande', FILTER_VALIDATE_INT);
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        $commentaire = isset($_POST['commentaire']) ? htmlspecialchars(trim($_POST['commentaire']), ENT_QUOTES, 'UTF-8') : '';

        if (!$demande_id || !in_array($action, ['approuve', 'rejete'])) {
            throw new Exception('Requête invalide');
        }

        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE demandes 
                              SET statut = ?, 
                                  commentaire = ?,
                                  date_traitement = NOW(),
                                  traite_par = ?
                              WHERE id = ?");
        $stmt->execute([$action, $commentaire, $_SESSION['admin_id'], $demande_id]);
        
        // Journalisation
        $pdo->prepare("INSERT INTO admin_logs (admin_id, action, details) VALUES (?, ?, ?)")
           ->execute([$_SESSION['admin_id'], 'demande_'.$action, "Demande ID: $demande_id"]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => "Demande ".($action === 'approuve' ? 'approuvée' : 'rejetée')]);
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}

// Configuration de la pagination
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres sécurisés
$filtre_statut = isset($_GET['statut']) ? htmlspecialchars($_GET['statut']) : 'tous';
$filtre_type = isset($_GET['type']) ? htmlspecialchars($_GET['type']) : 'tous';
$filtre_date = isset($_GET['date']) ? htmlspecialchars($_GET['date']) : '';

// Construction de la requête sécurisée
$where = [];
$params = [];

$valid_statuts = ['en_attente', 'approuve', 'rejete'];
if ($filtre_statut !== 'tous' && in_array($filtre_statut, $valid_statuts)) {
    $where[] = "d.statut = ?";
    $params[] = $filtre_statut;
}

$valid_types = ['conge', 'retard', 'absence'];
if ($filtre_type !== 'tous' && in_array($filtre_type, $valid_types)) {
    $where[] = "d.type = ?";
    $params[] = $filtre_type;
}

if (!empty($filtre_date) && DateTime::createFromFormat('Y-m-d', $filtre_date)) {
    $where[] = "DATE(d.date_demande) = ?";
    $params[] = $filtre_date;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Comptage
$count_sql = "SELECT COUNT(*) FROM demandes d $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_demandes = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_demandes / $limit));

// Requête principale
$sql = "
    SELECT d.*, 
           e.nom, e.prenom, e.email, e.poste, e.departement, e.photo,
           a.nom AS admin_nom, a.prenom AS admin_prenom,
           TIMESTAMPDIFF(HOUR, d.date_demande, NOW()) AS heures_ecoulees
    FROM demandes d
    JOIN employes e ON d.employe_id = e.id
    LEFT JOIN admins a ON d.traite_par = a.id
    $where_clause
    ORDER BY 
        CASE WHEN d.statut = 'en_attente' THEN 0 ELSE 1 END,
        d.date_demande DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(statut = 'en_attente') AS en_attente,
        SUM(statut = 'approuve') AS approuve,
        SUM(statut = 'rejete') AS rejete,
        SUM(type = 'conge') AS conges,
        SUM(type = 'retard') AS retards,
        SUM(type = 'absence') AS absences
    FROM demandes
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Gestion des demandes | Admin</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css'>
    <link href='https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --warning-color: #f8961e;
            --danger-color: #f94144;
            --light-bg: #f8f9fa;
            --dark-bg: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            color: white;
            min-height: 100vh;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            border-radius: 5px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card {
            border-left: 4px solid;
        }
        
        .stat-card.total { border-left-color: var(--primary-color); }
        .stat-card.en_attente { border-left-color: var(--warning-color); }
        .stat-card.approuve { border-left-color: var(--success-color); }
        .stat-card.rejete { border-left-color: var(--danger-color); }
        
        .badge {
            padding: 8px 12px;
            font-weight: 500;
            border-radius: 50px;
        }
        
        .badge-en_attente { 
            background-color: rgba(var(--bs-warning-rgb), 0.1); 
            color: var(--warning-color);
        }
        
        .badge-approuve { 
            background-color: rgba(var(--bs-success-rgb), 0.1); 
            color: var(--success-color);
        }
        
        .badge-rejete { 
            background-color: rgba(var(--bs-danger-rgb), 0.1); 
            color: var(--danger-color);
        }
        
        .avatar {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .urgent {
            position: relative;
        }
        
        .urgent::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background-color: var(--danger-color);
            border-radius: 5px 0 0 5px;
        }
        
        .btn-action {
            border-radius: 50px;
            padding: 5px 15px;
            font-weight: 500;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        .table-hover tbody tr {
            transition: all 0.2s;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(var(--primary-color), 0.05);
        }
        
        .filter-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                margin-bottom: 20px;
            }
            
            .stat-card {
                margin-bottom: 15px;
            }
        }
        /* Responsive Modal Body */
@media (max-width: 767.98px) {
    .modal-lg {
        max-width: 98vw;
    }
    .modal .row {
        flex-direction: column;
    }
    .modal .col-md-4, .modal .col-md-8 {
        max-width: 100%;
        flex: 0 0 100%;
    }
}

/* Avatar employé dans la modale */
.modal .card-body.text-center img.rounded-circle,
.modal .card-body.text-center .rounded-circle {
    width: 100px;
    height: 100px;
    object-fit: cover;
    margin-bottom: 0.75rem;
    border: 3px solid #f1f1f1;
    box-shadow: 0 2px 8px rgba(68, 68, 68, 0.05);
    background: #fafbfc;
}

.modal .card-body.text-center h4 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.modal .card-body.text-center p {
    margin-bottom: 0.25rem;
}

/* Détails demande */
.modal .card-title {
    font-weight: 600;
    color: #4361ee;
    margin-bottom: 1rem;
}

.modal .badge {
    font-size: 1em;
    padding: 0.45em 0.8em;
    border-radius: 1.5em;
}

.modal .bg-light {
    background: #f8f9fa !important;
}

.modal .p-3 {
    font-size: 1em;
    word-break: break-word;
    line-height: 1.5;
}

.modal .mb-3:last-child {
    margin-bottom: 0 !important;
}

/* Responsive table if you ever add lists */
.modal table {
    width: 100%;
    font-size: 1em;
}

@media (max-width: 575.98px) {
    .modal .card-title {
        font-size: 1.1rem;
    }
    .modal .card-body.text-center h4 {
        font-size: 1rem;
    }
    .modal .p-3 {
        font-size: 0.98em;
    }
}
    </style>
</head>
<body>
    <div class='container-fluid p-0'>
        <div class='row g-0'>
            <!-- Sidebar -->
            <div class='col-lg-2 d-none d-lg-block sidebar p-0'>
                <div class='p-4'>
                    <div class='d-flex align-items-center mb-4'>
                        <i class='fas fa-shield-alt fs-3 me-2'></i>
                        <h4 class='mb-0'>Espace Admin</h4>
                    </div>
                    <ul class='nav nav-pills flex-column'>
                        <li class='nav-item'>
                            <a href='admin_dashboard.php' class='nav-link'><i class='fas fa-tachometer-alt me-2'></i>Dashboard</a>
                        </li>
                        <li class='nav-item'>
                            <a href='admin_demandes.php' class='nav-link active'><i class='fas fa-tasks me-2'></i>Demandes</a>
                        </li>
                        <li class='nav-item'>
                            <a href='admin_demandes.php?type=retard' class='nav-link'><i class='fas fa-clock me-2'></i>Retards
                                <span class='badge bg-warning ms-2'><?= (int)($stats['retards'] ?? 0) ?></span>
                            </a>
                        </li>
                        <li class='nav-item'>
                            <a href='admin_demandes.php?type=absence' class='nav-link'><i class='fas fa-user-slash me-2'></i>Absences
                                <span class='badge bg-danger ms-2'><?= (int)($stats['absences'] ?? 0) ?></span>
                            </a>
                        </li>
                        <li class='nav-item'>
                            <a href='admin_demandes.php?type=maladie' class='nav-link'><i class='fas fa-head-side-cough me-2'></i>Maladies
                                <span class='badge bg-info ms-2'><?= (int)($stats['maladies'] ?? 0) ?></span>
                            </a>
                        </li>
                        <li class='nav-item'>
                            <a href='admin_demandes.php?type=conge' class='nav-link'><i class='fas fa-umbrella-beach me-2'></i>Congés
                                <span class='badge bg-success ms-2'><?= (int)($stats['conges'] ?? 0) ?></span>
                            </a>
                        </li>
                        <li class='nav-item'>
                           <a href="#" class="nav-link" id="showEmployes"><i class="fas fa-users me-2"></i>Employés</a>
                        </li>
                        <li class='nav-item'>
                            <a href='admin_reports.php' class='nav-link'><i class='fas fa-chart-bar me-2'></i>Rapports</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class='col-lg-10 p-4'>
            <!-- Liste Employés dynamique -->
            <div id="listeEmployes" style="display:none;"></div>
            <!-- Contenu principal demandes -->
            <div id="mainDemandesContent">             
                <!-- Top Bar -->
                <div class='d-flex justify-content-between align-items-center mb-4'>
                    <div>
                        <h2 class='h4 mb-0'><i class='fas fa-tasks me-2'></i>Gestion des demandes</h2>
                        <nav aria-label='breadcrumb'>
                            <ol class='breadcrumb'>
                                <li class='breadcrumb-item'><a href='admin_dashboard.php'>Dashboard</a></li>
                                <li class='breadcrumb-item active' aria-current='page'>Demandes</li>
                            </ol>
                        </nav>
                    </div>
                    <div class='dropdown'>
                        <button class='btn btn-outline-light dropdown-toggle' type='button' data-bs-toggle='dropdown'>
                            <i class='fas fa-user-circle me-1'></i> <?= htmlspecialchars($_SESSION['admin_nom'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>
                        </button>
                        <ul class='dropdown-menu dropdown-menu-end'>
                            <li><a class='dropdown-item' href='admin_profile.php'><i class='fas fa-user me-2'></i>Profil</a></li>
                            <li><a class='dropdown-item' href='admin_settings.php'><i class='fas fa-cog me-2'></i>Paramètres</a></li>
                            <li><hr class='dropdown-divider'></li>
                            <li><a class='dropdown-item text-danger' href='logout.php'><i class='fas fa-sign-out-alt me-2'></i>Déconnexion</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class='alert alert-success alert-dismissible fade show'>
                        <i class='fas fa-check-circle me-2'></i>
                        <?= htmlspecialchars($_SESSION['success_message'], ENT_QUOTES, 'UTF-8') ?>
                        <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class='row g-3 mb-4'>
                    <div class='col-md-6 col-lg-3'>
                        <div class='card stat-card total h-100'>
                            <div class='card-body'>
                                <div class='d-flex justify-content-between align-items-center'>
                                    <div>
                                        <h6 class='text-muted mb-2'>Total Demandes</h6>
                                        <h3 class='mb-0'><?= $stats['total'] ?></h3>
                                        <small class='text-muted'><?= date('d M Y') ?></small>
                                    </div>
                                    <div class='bg-primary bg-opacity-10 p-3 rounded'>
                                        <i class='fas fa-list-alt text-primary fs-4'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class='col-md-6 col-lg-3'>
                        <div class='card stat-card en_attente h-100'>
                            <div class='card-body'>
                                <div class='d-flex justify-content-between align-items-center'>
                                    <div>
                                        <h6 class='text-muted mb-2'>En Attente</h6>
                                        <h3 class='mb-0'><?= $stats['en_attente'] ?></h3>
                                        <small class='text-muted'>Non traitées</small>
                                    </div>
                                    <div class='bg-warning bg-opacity-10 p-3 rounded'>
                                        <i class='fas fa-clock text-warning fs-4'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class='col-md-6 col-lg-3'>
                        <div class='card stat-card approuve h-100'>
                            <div class='card-body'>
                                <div class='d-flex justify-content-between align-items-center'>
                                    <div>
                                        <h6 class='text-muted mb-2'>Approuvées</h6>
                                        <h3 class='mb-0'><?= $stats['approuve'] ?></h3>
                                        <small class='text-muted'>Ce mois-ci</small>
                                    </div>
                                    <div class='bg-success bg-opacity-10 p-3 rounded'>
                                        <i class='fas fa-check-circle text-success fs-4'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class='col-md-6 col-lg-3'>
                        <div class='card stat-card rejete h-100'>
                            <div class='card-body'>
                                <div class='d-flex justify-content-between align-items-center'>
                                    <div>
                                        <h6 class='text-muted mb-2'>Rejetées</h6>
                                        <h3 class='mb-0'><?= $stats['rejete'] ?></h3>
                                        <small class='text-muted'>Ce mois-ci</small>
                                    </div>
                                    <div class='bg-danger bg-opacity-10 p-3 rounded'>
                                        <i class='fas fa-times-circle text-danger fs-4'></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class='filter-card mb-4'>
                    <h5 class='mb-3'><i class='fas fa-filter me-2'></i>Filtres</h5>
                    <form id='filterForm' class='row g-3'>
                        <div class='col-md-3'>
                            <label class='form-label'>Statut</label>
                            <select name='statut' class='form-select'>
                                <option value='tous'>Tous</option>
                                <option value='en_attente' <?= $filtre_statut === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                <option value='approuve' <?= $filtre_statut === 'approuve' ? 'selected' : '' ?>>Approuvé</option>
                                <option value='rejete' <?= $filtre_statut === 'rejete' ? 'selected' : '' ?>>Rejeté</option>
                            </select>
                        </div>
                        <div class='col-md-3'>
                            <label class='form-label'>Type</label>
                            <select name='type' class='form-select'>
                                <option value='tous'>Tous</option>
                                <option value='conge' <?= $filtre_type === 'conge' ? 'selected' : '' ?>>Congé</option>
                                <option value='retard' <?= $filtre_type === 'retard' ? 'selected' : '' ?>>Retard</option>
                                <option value='absence' <?= $filtre_type === 'absence' ? 'selected' : '' ?>>Absence</option>
                            </select>
                        </div>
                        <div class='col-md-3'>
                            <label class='form-label'>Date</label>
                            <input type='date' name='date' class='form-control' value='<?= htmlspecialchars($filtre_date, ENT_QUOTES, 'UTF-8') ?>'>
                        </div>
                        <div class='col-md-3 d-flex align-items-end'>
                            <button type='submit' class='btn btn-primary me-2'><i class='fas fa-filter me-1'></i>Filtrer</button>
                            <a href='admin_demandes.php' class='btn btn-outline-secondary'>Réinitialiser</a>
                        </div>
                    </form>
                </div>

                <!-- Demandes List -->
                <div class='card'>
                    <div class='card-header bg-white border-0'>
                        <div class='d-flex justify-content-between align-items-center'>
                            <h5 class='mb-0'><i class='fas fa-list me-2'></i>Liste des demandes</h5>
                            <span class='badge bg-primary'>
                                <?= $total_demandes ?> demande(s) trouvée(s)
                            </span>
                        </div>
                    </div>
                    <div class='card-body'>
                        <?php if (empty($demandes)): ?>
                            <div class='text-center py-5'>
                                <i class='fas fa-inbox fa-4x text-muted mb-3'></i>
                                <h5 class='mt-3'>Aucune demande trouvée</h5>
                                <p class='text-muted'>Aucune demande ne correspond à vos critères de recherche</p>
                                <a href='admin_demandes.php' class='btn btn-primary mt-2'>
                                    <i class='fas fa-sync-alt me-1'></i> Réinitialiser les filtres
                                </a>
                            </div>
                            <div id="listeEmployes" style="display:none;"></div>
                        <?php else: ?>
<div class='table-responsive'>
    <table class='table table-hover align-middle' id='demandesTable'>
        <thead class='table-light'>
            <tr>
                <th>Employé</th>
                <th>Type</th>
                <th>Date</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
<tbody>
<?php foreach ($demandes as $demande): ?>
    <?php
        $nomComplet = trim(($demande['prenom'] ?? '') . ' ' . ($demande['nom'] ?? ''));
        $poste = $demande['poste'] ?? '';
        $departement = $demande['departement'] ?? '';
        $type = $demande['type'] ?? '';
        $dateDemande = !empty($demande['date_demande']) ? date('d/m/Y H:i', strtotime($demande['date_demande'])) : '';
        $statut = $demande['statut'] ?? '';
        $heuresEcoulees = $demande['heures_ecoulees'] ?? 0;
        $isUrgent = ($heuresEcoulees < 24 && $statut === 'en_attente');
        $badgeClass = [
            'approuve' => 'success',
            'rejete' => 'danger',
            'en_attente' => 'warning'
        ][$statut] ?? 'secondary';
        $badgeIcon = [
            'approuve' => 'check',
            'rejete' => 'times',
            'en_attente' => 'clock'
        ][$statut] ?? 'question';
        $initiales = strtoupper(substr($demande['prenom'] ?? '',0,1) . substr($demande['nom'] ?? '',0,1));
    ?>
    <tr class="<?= $isUrgent ? 'table-danger' : '' ?>">
        <td>
            <div class="d-flex align-items-center">
                <?php if (!empty($demande['photo'])): ?>
                    <img src="<?= htmlspecialchars($demande['photo']) ?>"
                         class="avatar me-3"
                         style="width:40px;height:40px;object-fit:cover;border-radius:50%;"
                         alt="Photo de <?= htmlspecialchars($nomComplet) ?>"
                         onerror="this.src='assets/default-avatar.png'">
                <?php else: ?>
                    <div class="avatar-initials bg-secondary text-white d-flex align-items-center justify-content-center me-3"
                         style="width:40px;height:40px;border-radius:50%;font-weight:bold;">
                        <?= $initiales ?>
                    </div>
                <?php endif; ?>
                <div>
                    <h6 class="mb-0"><?= htmlspecialchars($nomComplet) ?></h6>
                    <small class="text-muted">
                        <?= htmlspecialchars($poste) ?> • <?= htmlspecialchars($departement) ?>
                    </small>
                </div>
            </div>
        </td>
        <td>
            <span class="badge bg-primary bg-opacity-10 text-primary">
                <?= htmlspecialchars(ucfirst($type)) ?>
            </span>
        </td>
        <td>
            <?= $dateDemande ?>
            <?php if ($isUrgent): ?>
                <span class="badge bg-danger bg-opacity-10 text-danger ms-2" aria-label="Demande urgente">URGENT</span>
            <?php endif; ?>
        </td>
        <td>
            <span class="badge bg-<?= $badgeClass ?> badge-status">
                <i class="fas fa-<?= $badgeIcon ?> me-1"></i>
                <?= htmlspecialchars(ucfirst($statut)) ?>
            </span>
        </td>
        <td>
            <button class="btn btn-sm btn-outline-primary btn-action view-details details-btn"
                    data-id="<?= (int)$demande['id'] ?>"
                    data-bs-toggle="modal"
                    data-bs-target="#detailsModal"
                    title="Détails de la demande">
                <i class="fas fa-eye me-1"></i> Détails
            </button>
            <?php if ($demande['statut'] === 'en_attente'): ?>
                <button class="btn btn-sm btn-success btn-action ms-1" onclick="traiterDemande(<?= (int)$demande['id'] ?>, 'approuve')">
                    <i class="fas fa-check"></i> Accorder
                </button>
                <button class="btn btn-sm btn-danger btn-action ms-1" onclick="traiterDemande(<?= (int)$demande['id'] ?>, 'rejete')">
                    <i class="fas fa-times"></i> Rejeter
                </button>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php if ($total_pages > 1): ?>
    <?php
        $queryParams = $_GET;
        unset($queryParams['page']);
        $queryString = $queryParams ? '&' . http_build_query($queryParams) : '';
    ?>
    <nav class="mt-4" aria-label="Pagination">
        <ul class="pagination justify-content-center">
            <!-- Page précédente -->
            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
                <a class="page-link" href="?page=<?= max(1, $page-1) . $queryString ?>" aria-label="Page précédente">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <!-- Numéros de page intelligents (affiche [1] ... [n-2][n-1][n][n+1][n+2] ... [total]) -->
            <?php
                $range = 2; // nombre de pages à afficher autour de la page courante
                $start = max(1, $page - $range);
                $end = min($total_pages, $page + $range);

                if ($start > 1) {
                    // Première page
                    echo '<li class="page-item"><a class="page-link" href="?page=1' . $queryString . '">1</a></li>';
                    if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }

                for ($i = $start; $i <= $end; $i++) {
                    $active = $i == $page ? ' active' : '';
                    echo '<li class="page-item' . $active . '">';
                    if ($active) {
                        echo '<span class="page-link">' . $i . ' <span class="visually-hidden">(page courante)</span></span>';
                    } else {
                        echo '<a class="page-link" href="?page=' . $i . $queryString . '">' . $i . '</a>';
                    }
                    echo '</li>';
                }

                if ($end < $total_pages) {
                    if ($end < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                    // Dernière page
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . $queryString . '">' . $total_pages . '</a></li>';
                }
            ?>
            <!-- Page suivante -->
            <li class="page-item<?= $page >= $total_pages ? ' disabled' : '' ?>">
                <a class="page-link" href="?page=<?= min($total_pages, $page+1) . $queryString ?>" aria-label="Page suivante">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
        <div class="text-center text-muted small mt-2">
            Page <strong><?= $page ?></strong> sur <strong><?= $total_pages ?></strong>
        </div>
    </nav>
<?php endif; ?>
    </table>
</div> <!-- Fin .table-responsive -->
</div> <!-- Fin #mainDemandesContent -->
<?php endif; // <-- fermeture du else ?>

<div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Détails de la demande</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="modalDetailsContent">
        <!-- Contenu chargé dynamiquement -->
      </div>
    </div>
  </div>
</div>

    <!-- JavaScript Libraries -->
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
    <script src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
    <script src='https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js'></script>
    <script src='https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    
    <script>
    $(document).ready(function() {
        // Initialize DataTable with better configuration
        $('#demandesTable').DataTable({
            responsive: true,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json',
                emptyTable: 'Aucune donnée disponible',
                info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
                infoEmpty: 'Affichage de 0 à 0 sur 0 entrées',
                infoFiltered: '(filtré de _MAX_ entrées au total)',
                lengthMenu: 'Afficher _MENU_ entrées',
                loadingRecords: 'Chargement...',
                processing: 'Traitement...',
                search: 'Rechercher :',
                zeroRecords: 'Aucun résultat trouvé',
                paginate: {
                    first: 'Premier',
                    last: 'Dernier',
                    next: 'Suivant',
                    previous: 'Précédent'
                },
                aria: {
                    sortAscending: ': activer pour trier la colonne par ordre croissant',
                    sortDescending: ': activer pour trier la colonne par ordre décroissant'
                }
            },
            dom: "<'row'<'col-12 col-md-6'l><'col-12 col-md-6'f>>" +
                 "<'row'<'col-12'tr>>" +
                 "<'row'<'col-12 col-md-5'i><'col-12 col-md-7'p>>",
            pageLength: 10,
            order: [[2, 'desc']],
            columnDefs: [
                { orderable: false, targets: [4] }
            ],
            initComplete: function() {
                $('.dataTables_filter input').addClass('form-control').attr('placeholder', 'Rechercher...');
                $('.dataTables_length select').addClass('form-select');
            }
        });

        // Load modal content via AJAX with better error handling
        $('.view-details').click(function() {
            const demandeId = $(this).data('id');
            const modalContent = $('#modalDetailsContent');
            modalContent.html(`
                <div class='text-center py-5'>
                    <div class='spinner-border text-primary' role='status'>
                        <span class='visually-hidden'>Chargement...</span>
                    </div>
                    <p class='mt-3'>Chargement des détails...</p>
                </div>
            `);
            $.ajax({
                url: 'get_demande_details.php',
                type: 'GET',
                data: { id: demandeId },
                success: function(response) {
                    modalContent.html(response);
                },
                error: function() {
                    modalContent.html(`
                        <div class='alert alert-danger'>
                            <i class='fas fa-exclamation-triangle me-2'></i>
                            Impossible de charger les détails de la demande. Veuillez réessayer.
                        </div>
                    `);
                }
            });
        });

        // Enhanced AJAX form submission with SweetAlert
        $(document).on('submit', '.demande-form', function(e) {
            e.preventDefault();
            const form = $(this);
            const action = form.find('button[type="submit"]').val();
            const formData = form.serialize() + '&ajax_action=1&action=' + action;
            
            Swal.fire({
                title: 'Confirmation',
                text: `Voulez-vous vraiment ${action === 'approuve' ? 'approuver' : 'rejeter'} cette demande ?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Confirmer',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'POST',
                        url: window.location.href,
                        data: formData,
                        dataType: 'json',
                        beforeSend: function() {
                            Swal.fire({
                                title: 'Traitement en cours',
                                html: 'Veuillez patienter...',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                        },
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    title: 'Succès',
                                    text: response.message,
                                    icon: 'success',
                                    confirmButtonText: 'OK'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Erreur',
                                    text: response.error || 'Une erreur est survenue',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                title: 'Erreur',
                                text: 'Une erreur est survenue lors de la communication avec le serveur',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
        });

        // Real-time updates with notification badge
        function checkUpdates() {
            $.get('check_demandes_updates.php', function(data) {
                if (data.new_demandes > 0) {
                    $('#newDemandesBadge').text(data.new_demandes).removeClass('d-none');
                    
                    // Desktop notification
                    if (Notification.permission === 'granted') {
                        new Notification('Nouvelles demandes', {
                            body: `${data.new_demandes} nouvelle(s) demande(s) en attente`,
                            icon: 'assets/notification-icon.png'
                        });
                    }
                }
            }, 'json').fail(function() {
                console.error('Error checking for updates');
            });
        }
        
        // Request notification permission
        if (Notification.permission !== 'denied') {
            Notification.requestPermission();
        }
        
        // Check for updates every 2 minutes
        setInterval(checkUpdates, 120000);
        checkUpdates(); // Initial check
    });
    
$(document).ready(function() {
    // Clic sur "Employés" dans la sidebar
    $('#showEmployes').click(function(e) {
        e.preventDefault();
        $('#mainDemandesContent').hide();      // Masque la vue des demandes
        $('#listeEmployes').show().html('<div class="text-center my-5"><div class="spinner-border"></div> Chargement...</div>');
        $.ajax({
            url: 'admin_employes_partial.php',
            type: 'GET',
            success: function(data) {
                $('#listeEmployes').html(data);
            },
            error: function() {
                $('#listeEmployes').html('<div class="alert alert-danger">Erreur lors du chargement des employés.</div>');
            }
        });
    });

    // Clic sur bouton "Retour" dans la liste des employés (à ajouter dans admin_employes_partial.php)
    $(document).on('click', '#retourDemandes', function() {
        $('#listeEmployes').hide();
        $('#mainDemandesContent').show();
    });
});
$('.details-btn').click(function() {
    const demandeId = $(this).data('id');
    $('#modalDetailsContent').html('<div class="text-center py-5"><div class="spinner-border"></div> Chargement...</div>');
    $.get('get_demande_details.php', { id: demandeId }, function(html) {
        $('#modalDetailsContent').html(html);
    });
});
</script>
<script>
function traiterDemande(id, action) {
    if (!confirm('Confirmer cette action ?')) return;
    fetch('admin_demandes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ ajax_action: 1, id_demande: id, action: action })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Erreur lors du traitement.');
        }
    })
    .catch(() => alert('Erreur réseau.'));
}
</script>
</body>
</html>