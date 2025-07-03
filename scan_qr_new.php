<?php
/**
 * Nouveau scanner de badges - Architecture restructurée
 * Séparation claire entre logique métier et présentation
 */

session_start();
date_default_timezone_set('Europe/Paris');

// Configuration
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Inclusion des dépendances
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/controllers/PointageController.php';

try {
    // Vérification de la méthode pour les requêtes API
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Redirection vers l'API dédiée
        header('Location: /api/scan.php');
        exit;
    }
    
    // Affichage de l'interface utilisateur
    include __DIR__ . '/views/scanner.php';
    
} catch (Exception $e) {
    // Gestion d'erreur globale
    error_log('Erreur scanner: ' . $e->getMessage());
    
    // Affichage d'une page d'erreur conviviale
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur - Système de Pointage</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                            <h3 class="mb-3">Service temporairement indisponible</h3>
                            <p class="text-muted mb-4">
                                Une erreur technique empêche le fonctionnement du scanner. 
                                Veuillez réessayer dans quelques instants.
                            </p>
                            <a href="javascript:location.reload()" class="btn btn-primary">
                                <i class="fas fa-sync-alt me-2"></i> Réessayer
                            </a>
                            <a href="/" class="btn btn-outline-secondary ms-2">
                                <i class="fas fa-home me-2"></i> Accueil
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>

