<?php
require 'db.php';
session_start();
if (!isset($_GET['id'])) { die('Pointage introuvable'); }
$id = (int)$_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM pointages WHERE id = ? AND employe_id = ?");

$pointage = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pointage) { die('Pointage introuvable'); }

$stmt = $pdo->prepare("
    SELECT 
      n.id AS notif_id,
      n.titre,
      n.contenu,
      n.lue,
      n.date AS date_notif,
      p.type,
      p.date_pointage,
      p.heure_pointage
    FROM notifications n
    LEFT JOIN pointages p ON n.pointage_id = p.id
    WHERE n.employee_id = ?
    ORDER BY n.date DESC
");
$stmt->execute([$_SESSION['employe_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <title>Détail du pointage</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
  <div class="container">
    <h2>Détail du pointage manqué</h2>
    <ul class="list-group">
      <li class="list-group-item"><strong>Date :</strong> <?= htmlspecialchars($pointage['date_pointage']) ?></li>
      <li class="list-group-item"><strong>Type :</strong> <?= htmlspecialchars($pointage['type']) ?></li>
      <li class="list-group-item"><strong>Statut :</strong> <?= htmlspecialchars($pointage['etat']) ?></li>
    </ul>
    <a href="employe_dashboard.php" class="btn btn-secondary mt-3">Retour</a>
  </div>

  <div class="container mt-4">
    <h3>Notifications</h3>
    <ul class="list-group">
      <?php
      foreach ($notifications as $notification) {
          $type = $notification['type'] ? ucfirst($notification['type']) : 'Pointage';
          $date_pointage = date('d/m/Y', strtotime($notification['date_pointage']));
          $message = "Vous avez manqué le pointage de $type du $date_pointage.";
          
          if (!$notification['type']) {
              $message = "Vous avez manqué le pointage du jour $date_pointage.";
          }
          
          echo "<li>
                  <a href=\"detail_pointage.php?id={$notification['notif_id']}\" class=\"dropdown-item notification-item\">
                      <div>
                          <strong>$message</strong>
                          <div class=\"small text-muted\">{$notification['date_notif']}</div>
                      </div>
                  </a>
                </li>";
      }
      ?>
    </ul>
  </div>
</body>
</html> 