<?php
session_start();
require_once 'db.php';

// Vérification authentification
if (!isset($_SESSION['employe_id']) && !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
$is_super_admin = ($_SESSION['role'] === 'super_admin');
$message = "";
$employe_id = $_SESSION['employe_id'] ?? 0; // Initialisation de $employe_id

// Récupération des notifications
try {
    $stmt = $pdo->prepare("SELECT n.id, n.titre, n.contenu, n.date, n.lue, p.type, p.date_heure 
                          FROM notifications n 
                          LEFT JOIN pointages p ON n.pointage_id = p.id 
                          WHERE n.employe_id = ? 
                          ORDER BY n.date DESC");
    $stmt->execute([$employe_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de base de données: " . $e->getMessage());
    $notifications = [];
}

/**
 * Vérifie les pointages manquants
 */
function checkForMissedPointage($employe_id, $date) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pointages WHERE employe_id = ? AND DATE(date_heure) = ?");
        $stmt->execute([$employe_id, $date]);
        return $stmt->fetchColumn() == 0;
    } catch (PDOException $e) {
        error_log("Erreur de vérification de pointage: " . $e->getMessage());
        return false;
    }
}

/**
 * Envoie une notification
 */
function sendNotification($employe_id, $titre, $contenu, $pointage_id = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (employe_id, titre, contenu, lue, date, date_lecture, pointage_id) 
                              VALUES (?, ?, ?, 0, NOW(), NULL, ?)");
        $stmt->execute([$employe_id, $titre, $contenu, $pointage_id]);
    } catch (PDOException $e) {
        error_log("Erreur d'envoi de notification: " . $e->getMessage());
    }
}

// Vérification des pointages manquants et retards
$date = date('Y-m-d');
if (checkForMissedPointage($employe_id, $date)) {
    sendNotification($employe_id, 'Pointage manquant', "Vous avez manqué le pointage du $date.");
}

// Récupération du dernier pointage
try {
    $stmt = $pdo->prepare("SELECT * FROM pointages WHERE employe_id = ? AND DATE(date_heure) = ? ORDER BY date_heure DESC LIMIT 1");
    $stmt->execute([$employe_id, $date]);
    $pointage = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur de récupération de pointage: " . $e->getMessage());
    $pointage = null;
}

// Vérification des retards
if ($pointage && isset($pointage['arrivee'])) {
    $heureArrivee = strtotime(date('H:i:s', strtotime($pointage['arrivee'])));
    if ($heureArrivee > strtotime('09:00:00')) {
        sendNotification($employe_id, 'Retard', "Vous êtes arrivé(e) à " . date('H:i', $heureArrivee) . " le $date.", $pointage['id']);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Gestion RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #f8f9fa;
            --danger-color: #e74c3c;
        }
        
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .notification-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.05);
        }
        
        .notification-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .notification-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 15px;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .notification-card.unread {
            background-color: rgba(52, 152, 219, 0.05);
            border-left: 4px solid var(--danger-color);
        }
        
        .notification-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .badge-unread {
            background-color: var(--danger-color);
        }
        
        .action-buttons {
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        
        .notification-card:hover .action-buttons {
            opacity: 1;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="notification-container">
        <div class="notification-header d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-bell me-2"></i> Mes Notifications</h2>
            <span class="badge bg-primary rounded-pill"><?= count($notifications) ?></span>
        </div>
        
        <form id="notificationForm" method="post" action="delete_notifications.php">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm me-2" id="selectAll">
                        <i class="fas fa-check-circle me-1"></i> Tout sélectionner
                    </button>
                    <button type="submit" class="btn btn-danger btn-sm" id="deleteSelected">
                        <i class="fas fa-trash-alt me-1"></i> Supprimer la sélection
                    </button>
                </div>
                <a href="mark_notif_read.php" class="btn btn-success btn-sm">
                    <i class="fas fa-check-double me-1"></i> Tout marquer comme lu
                </a>
            </div>
            
            <?php if (count($notifications) > 0): ?>
                <div class="list-group">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item notification-card <?= $notification['lue'] ? '' : 'unread' ?>">
                            <div class="d-flex align-items-start">
                                <input type="checkbox" class="form-check-input me-3 mt-1" name="selected_notifications[]" value="<?= $notification['id'] ?>">
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <h5 class="mb-1"><?= htmlspecialchars($notification['titre']) ?></h5>
                                        <span class="badge <?= $notification['lue'] ? 'bg-secondary' : 'badge-unread' ?> rounded-pill">
                                            <?= $notification['lue'] ? 'Lu' : 'Non lu' ?>
                                        </span>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($notification['titre'] ?? 'Notification sans titre') ?></p>
                                    
                                    <small class="notification-time">
                                        <i class="far fa-clock me-1"></i> <?= date('d/m/Y à H:i', strtotime($notification['date'] ?? 'now')) ?>
                                        <?php if ($notification['type']): ?>
                                            | <i class="fas fa-fingerprint me-1"></i> <?= htmlspecialchars($notification['type']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="action-buttons ms-3">
                                    <a href="mark_notif_read.php?id=<?= $notification['id'] ?>" class="btn btn-sm btn-outline-success" title="Marquer comme lu">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteNotification(<?= $notification['id'] ?>)" title="Supprimer">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="far fa-bell-slash"></i>
                    <h4>Aucune notification</h4>
                    <p>Vous n'avez aucune notification pour le moment.</p>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sélection/désélection de toutes les notifications
        document.getElementById('selectAll').addEventListener('click', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_notifications[]"]');
            const allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            
            this.innerHTML = allChecked 
                ? '<i class="fas fa-check-circle me-1"></i> Tout sélectionner' 
                : '<i class="fas fa-times-circle me-1"></i> Tout désélectionner';
        });

        // Suppression d'une notification
        function deleteNotification(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette notification ?')) {
                fetch('delete_notifications.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const card = document.querySelector(`.notification-card input[value="${id}"]`)?.closest('.notification-card');
                        if (card) {
                            card.style.opacity = '0';
                            setTimeout(() => card.remove(), 300);
                        }
                    } else {
                        alert('Une erreur est survenue lors de la suppression.');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Une erreur est survenue.');
                });
            }
        }
        // Dans votre fichier notification.php, mettez à jour les fonctions JS :
function markAsRead(notificationId) {
    fetch('mark_notif_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const card = document.querySelector(`.notification-card input[value="${notificationId}"]`)
                         .closest('.notification-card');
            card.classList.remove('unread');
            card.querySelector('.badge').className = 'badge bg-secondary rounded-pill';
            card.querySelector('.badge').textContent = 'Lu';
        }
    });
}

function markAllAsRead() {
    fetch('mark_notif_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'mark_all=true'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notification-card').forEach(card => {
                card.classList.remove('unread');
                card.querySelector('.badge').className = 'badge bg-secondary rounded-pill';
                card.querySelector('.badge').textContent = 'Lu';
            });
            alert(`${data.count} notifications marquées comme lues`);
        }
    });
}

// Pour la suppression multiple
document.getElementById('notificationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (confirm('Êtes-vous sûr de vouloir supprimer les notifications sélectionnées ?')) {
        const formData = new FormData(this);
        
        fetch('delete_notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('input[name="selected_notifications[]"]:checked')
                      .forEach(checkbox => {
                          checkbox.closest('.notification-card').remove();
                      });
                alert(`${data.deleted_count} notifications supprimées`);
            } else {
                alert(data.message || 'Erreur lors de la suppression');
            }
        });
    }
});
    </script>
</body>
</html>