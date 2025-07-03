<?php
session_start();
require 'db.php';

// Fonction de génération de badge sécurisée
function generateBadgeToken($employe_id) {
    $random = bin2hex(random_bytes(16));
    $timestamp = time();
    $data = "$employe_id|$random|$timestamp";
    $signature = hash_hmac('sha256', $data, SECRET_KEY);
    
    return [
        'token' => base64_encode("$employe_id|$random|$timestamp|$signature"),
        'expires_at' => date('Y-m-d H:i:s', $timestamp + (3600 * 2)) // 2h de validité
    ];
}

// Vérification des droits admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Traitement des actions sur les demandes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_demande'])) {
    $demande_id = (int)$_POST['id_demande'];
    $action = $_POST['action_demande'];
    $commentaire = trim($_POST['commentaire'] ?? '');

    // Validation des données
    if (!in_array($action, ['approuve', 'rejete'])) {
        $_SESSION['error_message'] = "Action invalide.";
        header("Location: gestion_demandes_badge.php");
        exit();
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM demandes_badge WHERE id = ? FOR UPDATE");
        $stmt->execute([$demande_id]);
        $demande = $stmt->fetch();

        if (!$demande) {
            throw new Exception("Demande introuvable.");
        }

        if ($demande['statut'] !== 'en_attente') {
            throw new Exception("Cette demande a déjà été traitée.");
        }

        if ($action === 'approuve') {
            // Générer un nouveau badge
            $tokenData = generateBadgeToken($demande['employe_id']);
            
            // Enregistrer le badge
            $stmt = $pdo->prepare("INSERT INTO badge_tokens (employe_id, token_hash, created_at, expires_at) 
                                  VALUES (?, ?, NOW(), ?)");
            $stmt->execute([
                $demande['employe_id'],
                password_hash($tokenData['token'], PASSWORD_BCRYPT),
                $tokenData['expires_at']
            ]);

            // Envoyer un email de notification
            sendBadgeApprovalEmail($demande['employe_id'], $tokenData['token']);
        }

        // Mettre à jour le statut de la demande
        $stmt = $pdo->prepare("UPDATE demandes_badge 
                              SET statut = ?, raison = ?, traite_par = ?, date_traitement = NOW() 
                              WHERE id = ?");
        $stmt->execute([
            $action === 'approuve' ? 'approuve' : 'rejete',
            $commentaire,
            $_SESSION['admin_id'],
            $demande_id
        ]);

        $pdo->commit();
        $_SESSION['success_message'] = $action === 'approuve' 
            ? "✅ Demande approuvée et badge généré." 
            : "❌ Demande rejetée.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "⚠️ Erreur: " . $e->getMessage();
    }

    header("Location: gestion_demandes_badge.php");
    exit();
}

// Fonction pour envoyer un email (simplifiée)
function sendBadgeApprovalEmail($employe_id, $token) {
    // Implémentation réelle utiliserait PHPMailer ou un service similaire
    // Ici nous enregistrons juste dans les logs pour l'exemple
    error_log("Badge approuvé pour employé $employe_id. Token: $token");
}

// Récupération des demandes avec pagination et filtres
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Filtres
$statut = $_GET['statut'] ?? 'tous';
$departement = $_GET['departement'] ?? 'tous';
$recherche = trim($_GET['recherche'] ?? '');

// Construction de la requête SQL
$where = [];
$params = [];

if ($statut !== 'tous') {
    $where[] = "d.statut = ?";
    $params[] = $statut;
}

if ($departement !== 'tous') {
    $where[] = "e.departement = ?";
    $params[] = $departement;
}

if (!empty($recherche)) {
    $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.email LIKE ? OR e.matricule LIKE ?)";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
    $params[] = "%$recherche%";
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// Compter le nombre total de demandes filtrées
$countQuery = "SELECT COUNT(*) FROM demandes_badge d JOIN employes e ON d.employe_id = e.id $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$total_demandes = $stmt->fetchColumn();
$total_pages = max(1, ceil($total_demandes / $limit));

// Récupérer les demandes paginées
$query = "
    SELECT d.*, 
           e.id as employe_id, e.nom, e.prenom, e.email, e.poste, e.departement, e.photo, e.matricule,
           b.token_hash AS dernier_badge,
           a.nom as admin_nom, a.prenom as admin_prenom
    FROM demandes_badge d
    JOIN employes e ON d.employe_id = e.id
    LEFT JOIN badge_tokens b ON (
        b.employe_id = e.id AND 
        b.id = (SELECT MAX(id) FROM badge_tokens WHERE employe_id = e.id)
    )
    LEFT JOIN admins a ON d.traite_par = a.id
    $whereClause
    ORDER BY 
        CASE WHEN d.statut = 'en_attente' THEN 0 ELSE 1 END,
        d.date_demande DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$demandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les statistiques et options de filtre
$stats = $pdo->query("
    SELECT 
        COUNT(*) AS total,
        SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) AS en_attente,
        SUM(CASE WHEN statut = 'approuve' THEN 1 ELSE 0 END) AS approuve,
        SUM(CASE WHEN statut = 'rejete' THEN 1 ELSE 0 END) AS rejete
    FROM demandes_badge
")->fetch(PDO::FETCH_ASSOC);

$departements = $pdo->query("SELECT DISTINCT departement FROM employes WHERE departement IS NOT NULL ORDER BY departement")->fetchAll(PDO::FETCH_COLUMN);
?>