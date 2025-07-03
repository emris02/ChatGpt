<?php
session_start();
require 'db.php';

// Vérification admin seulement
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    $dest_type = $_POST['dest_type'] ?? 'employe';
    $sujet = trim($_POST['sujet']);
    $contenu = trim($_POST['contenu']);
    $destinataires = [];

    if ($dest_type === 'employe') {
        $destinataires = $_POST['destinataires'] ?? [];
    } elseif ($dest_type === 'departement') {
        $departement = $_POST['departement'] ?? '';
        if ($departement) {
            $stmt = $pdo->prepare("SELECT id FROM employes WHERE departement = ?");
            $stmt->execute([$departement]);
            $destinataires = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }
    } elseif ($dest_type === 'general' || (isset($_POST['general']) && $_POST['general'] == '1')) {
        $stmt = $pdo->query("SELECT id FROM employes");
        $destinataires = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (empty($destinataires) || empty($sujet) || empty($contenu)) {
        $_SESSION['erreur'] = "Tous les champs sont obligatoires";
        header("Location: messagerie.php");
        exit;
    }

    // Enregistrement du message
    $pdo->beginTransaction();
    try {
        // Insertion du message
        $stmt = $pdo->prepare("INSERT INTO messages (expediteur_id, sujet, contenu) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['admin_id'], $sujet, $contenu]);
        $message_id = $pdo->lastInsertId();

        // Ajout des destinataires
        $stmt = $pdo->prepare("INSERT INTO message_destinataires (message_id, destinataire_id) VALUES (?, ?)");
        foreach ($destinataires as $dest_id) {
            $stmt->execute([$message_id, $dest_id]);
        }

        $pdo->commit();
        $_SESSION['succes'] = "Message envoyé avec succès";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['erreur'] = "Erreur lors de l'envoi du message";
    }

    header("Location: messagerie.php");
    exit;
}