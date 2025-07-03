<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';
require_once 'models/BadgeValidator.php';

// Redirection si l'employé n'est pas connecté
if (!isset($_SESSION['employe_id'])) {
    header("Location: login.php");
    exit;
}

$employe_id = $_SESSION['employe_id'];

// Récupérer les informations complètes de l'employé avec son badge
// Amélioration : Récupérer les infos de l'employé + badge actif en une seule requête
$stmt = $pdo->prepare("
    SELECT
        e.*,
        b.token_hash AS token,
        b.expires_at
    FROM employes e
    LEFT JOIN badge_tokens b ON e.id = b.employe_id AND b.expires_at > NOW()
    WHERE e.id = ?
    ORDER BY b.created_at DESC
    LIMIT 1
");
$stmt->execute([$employe_id]);
$employe = $stmt->fetch();

// Redirection si employé ou badge non trouvé
if (!$employe || !isset($employe['badge_token'])) {
    $_SESSION['error'] = "Aucun badge actif trouvé";
    header('Location: employe_dashboard.php');
    exit();
}
// Amélioration : Simplifier la vérification du badge actif
$badge_actif = !empty($employe['token']) && strtotime($employe['expires_at']) > time();

// Vérification de la validité du badge
$now = new DateTime();
$expires_at = new DateTime($employe['badge_expires']);
if ($now > $expires_at) {
    $_SESSION['error'] = "Votre badge a expiré";
    header('Location: employe_dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Badge Professionnel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@latest/build/qrcode.min.js"></script>
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #28a745;
            --danger: #dc3545;
        }
        
        @page {
            size: landscape;
            margin: 0;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .badge-container {
            width: 250mm; /* Format A4 paysage */
            height: 210mm;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .badge-card {
            width: 260mm;
            height: 150mm;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            display: flex;
            overflow: hidden;
            position: relative;
        }
        .badge-left {
            width: 40%;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .badge-right {
            width: 60%;
            padding: 30px;
            display: flex;
            flex-direction: column;
        }
        .company-logo {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }
        .employee-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            object-fit: cover;
            margin-bottom: 20px;
        }
        .employee-name {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
        }
        .employee-position {
            font-size: 18px;
            text-align: center;
            margin-bottom: 30px;
        }
        .badge-qr {
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        .employee-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 30px;
        }
        .detail-item {
            margin-bottom: 10px;
        }
        .detail-label {
            font-size: 12px;
            color: #777;
        }
        .detail-value {
            font-weight: 500;
            font-size: 14px;
        }
        .badge-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            text-align: center;
            padding: 10px;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
        @media print {
            body {
                background: none;
            }
            .badge-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="badge-container">
        <div class="badge-card">
            <div class="badge-left">
                <button class="print-button" onclick="window.print()">
                    <i class="fas fa-print"></i>
                </button>
                
                <span class="badge-status">
                    <?= (new DateTime() < new DateTime($employe['badge_expires'])) ? 'ACTIF' : 'EXPIRÉ' ?>
                </span>
                
                <div class="company-logo">
                    <i class="fas fa-building" style="font-size: 40px; color: var(--primary);"></i>
                </div>
                
                <h2><?= htmlspecialchars($employe['entreprise'] ?? 'COMPANY NAME') ?></h2>
                <p>Badge Professionnel</p>
                
                <?php if (!empty($employe['photo'])): ?>
                    <img src="<?= htmlspecialchars($employe['photo']) ?>" class="employee-photo" alt="Photo employé">
                <?php else: ?>
                    <div class="employee-photo">
                        <i class="fas fa-user" style="font-size: 50px; color: #777;"></i>
                    </div>
                <?php endif; ?>
                
                <div class="employee-name"><?= htmlspecialchars($employe['prenom'] . ' ' . $employe['nom']) ?></div>
                <div class="employee-position"><?= htmlspecialchars($employe['poste']) ?></div>
                
                <div class="badge-qr" id="qr-code"></div>
                <div class="badge-id">BADGE ID: <?= htmlspecialchars($employe['badge_id']) ?></div>
            </div>
            
            <div class="badge-right">
                <h2 style="color: var(--secondary); border-bottom: 2px solid var(--primary); padding-bottom: 10px;">
                    INFORMATIONS EMPLOYÉ
                </h2>
                
                <div class="employee-details">
                    <div class="detail-item">
                        <div class="detail-label">ID Employé</div>
                        <div class="detail-value"><?= htmlspecialchars($employe['id']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Département</div>
                        <div class="detail-value"><?= htmlspecialchars($employe['departement'] ?? 'N/A') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date d'embauche</div>
                        <div class="detail-value"><?= htmlspecialchars($employe['date_embauche'] ?? 'N/A') ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Badge créé le</div>
                        <div class="detail-value"><?= htmlspecialchars(date('d/m/Y', strtotime($employe['badge_created']))) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Expire le</div>
                        <div class="detail-value"><?= htmlspecialchars(date('d/m/Y', strtotime($employe['badge_expires']))) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Statut</div>
                        <div class="detail-value">
                            <?php if ($badge_actif): ?>
                                <span style="color: var(--success);">Actif</span>
                            <?php else: ?>
                                <span style="color: var(--danger);">Inactif</span>
                            <?php endif; ?>
                        </div>
                     <!-- Badge d'accès -->
<div class="col-md-4 text-center">
    <h6 class="mb-3"><i class="fas fa-id-card me-2"></i>Badge d'accès</h6>
    <?php if ($badge_actif): ?>
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($employe['token']) ?>" 
            class="badge-qr mb-2" alt="Badge d'accès"
            data-bs-toggle="modal" data-bs-target="#badgeModal">
        <div class="badge-label">Badge actif</div>
        <div class="badge-expiry <?= (strtotime($employe['expires_at']) - time()) < 3600 ? 'badge-expiry-warning' : '' ?>">
            Valide jusqu'à <?= date('H:i', strtotime($employe['expires_at'])) ?>
        </div>
        <div id="badge-timer" class="fw-bold text-success mt-2" style="font-size: 1.2rem;"></div>
    <?php else: ?>
        <div class="alert alert-warning mt-3">
            <i class="fas fa-exclamation-triangle me-2"></i> Aucun badge actif
        </div>
       <!-- Remplacez le bouton par -->
<form action="demandes_badge.php" method="post">
    <input type="hidden" name="employe_id" value="<?= $employe['id'] ?>">
    <button type="submit" class="btn btn-primary mt-2">
        <i class="fas fa-sync-alt me-2"></i>Demander un nouveau badge
    </button>
</form>
        <div id="messageConfirmation" class="alert alert-success mt-2" style="display:none;">
            Votre demande de nouveau badge a été envoyée avec succès.
        </div>
    <?php endif; ?>
</div>   
                    </div>
                </div>
            </div>
            
            <div class="badge-footer">
                <p>Ce badge est la propriété de <?= htmlspecialchars($employe['entreprise'] ?? 'COMPANY NAME') ?></p>
                <p>En cas de perte, merci de contacter immédiatement les RH</p>
            </div>
        </div>
    </div>

    <script>
        // Données pour le QR Code (ID employé + token)
        const qrData = JSON.stringify({
            employeeId: <?= $employe['id'] ?>,
            token: "<?= $employe['badge_token'] ?>",
            badgeId: "<?= $employe['badge_id'] ?>",
            expires: "<?= $employe['badge_expires'] ?>"
        });

        // Génération du QR Code
        QRCode.toCanvas(document.getElementById('qr-code'), qrData, {
            width: 160,
            margin: 1,
            color: {
                dark: '#2c3e50',
                light: '#ffffff00'
            }
        }, function(error) {
            if (error) console.error(error);
        });
        
        // Téléchargement du badge
        function downloadBadge() {
            const link = document.createElement('a');
            link.download = 'badge-<?= $employe['id'] ?>.png';
            link.href = document.querySelector('.badge-card').toDataURL('image/png');
            link.click();
        }
    </script>
</body>
</html>