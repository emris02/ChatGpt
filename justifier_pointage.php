<?php
session_start();
require 'db.php';


// Autorisation :
// - admin peut tout justifier
// - employé peut justifier son propre retard/absence via AJAX
if (!isset($_SESSION['role']) && !isset($_SESSION['employe_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit();
    } else {
        header('Location: login.php');
        exit();
    }
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit();
    } else {
        header('Location: profil_employe.php?id=' . ($_POST['employe_id'] ?? ''));
        exit();
    }
}


$employe_id = $_POST['employe_id'];
$date = $_POST['date'];
$type = $_POST['type'];
$est_justifie = isset($_POST['est_justifie']) ? 1 : 0;
$commentaire = $_POST['commentaire'] ?? '';

// Sécurité :
// - admin peut tout faire
// - employé ne peut justifier que son propre retard/absence
if (
    isset($_SESSION['role']) && $_SESSION['role'] === 'admin'
) {
    $justifie_par = $_SESSION['user_id'];
} elseif (
    isset($_SESSION['employe_id']) && $_SESSION['employe_id'] == $employe_id
) {
    $justifie_par = $_SESSION['employe_id'];
} else {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit();
    } else {
        header('Location: login.php');
        exit();
    }
}


try {
    if ($type === 'retard') {
        // Justifier un retard
        $stmt = $pdo->prepare("UPDATE pointages 
                              SET est_justifie = ?, commentaire = ?, justifie_par = ?, date_justification = NOW()
                              WHERE employe_id = ? AND DATE(date_heure) = ? AND type = 'arrivee'");
        $stmt->execute([$est_justifie, $commentaire, $justifie_par, $employe_id, $date]);
    } else {
        // Justifier une absence
        $stmt = $pdo->prepare("SELECT id FROM absences WHERE employe_id = ? AND date_absence = ?");
        $stmt->execute([$employe_id, $date]);
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE absences 
                                  SET est_justifie = ?, commentaire = ?, justifie_par = ?, date_justification = NOW()
                                  WHERE employe_id = ? AND date_absence = ?");
            $stmt->execute([$est_justifie, $commentaire, $justifie_par, $employe_id, $date]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO absences 
                                  (employe_id, date_absence, est_justifie, commentaire, justifie_par, date_justification)
                                  VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$employe_id, $date, $est_justifie, $commentaire, $justifie_par]);
        }
    }

    // Réponse AJAX ou redirection classique
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit();
    } else {
        header('Location: profile.php?id=' . $employe_id . '&justified=1');
        exit();
    }
} catch (Exception $e) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Erreur SQL : ' . $e->getMessage()]);
        exit();
    } else {
        header('Location: profile.php?id=' . $employe_id . '&justified=0');
        exit();
    }
}