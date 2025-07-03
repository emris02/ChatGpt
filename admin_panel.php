<?php
session_start();
require 'db.php';

// Check user role and permissions
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['super_admin', 'admin'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Add your custom styles here */
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-lg-2 sidebar">
            <ul class="nav flex-column">
                <li><a href="#" class="nav-link" id="showDashboard"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="#" class="nav-link" id="showDemandes"><i class="fas fa-tasks"></i> Demandes</a></li>
                <li><a href="#" class="nav-link" id="showRetards"><i class="fas fa-clock"></i> Retards</a></li>
                <li><a href="#" class="nav-link" id="showPointages"><i class="fas fa-history"></i> Pointages</a></li>
                <li><a href="#" class="nav-link" id="showTempsTravail"><i class="fas fa-briefcase"></i> Temps de Travail</a></li>
                <li><a href="#" class="nav-link" id="showNotifications"><i class="fas fa-bell"></i> Notifications</a></li>
                <!-- Add more links as needed -->
            </ul>
        </div>
        <!-- Main Content -->
        <div class="col-lg-10 p-4">
            <div id="mainContent">
                <!-- Default view -->
                <?php include 'partials/admin_dashboard_main.php'; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    function loadView(view) {
        $('#mainContent').html('<div class="text-center my-5"><div class="spinner-border"></div> Chargement...</div>');
        $.get('partials/admin_' + view + '.php', function(data) {
            $('#mainContent').html(data);
        }).fail(function() {
            $('#mainContent').html('<div class="alert alert-danger">Erreur lors du chargement.</div>');
        });
    }

    $('#showDashboard').click(function(e) { e.preventDefault(); loadView('dashboard_main'); });
    $('#showDemandes').click(function(e) { e.preventDefault(); loadView('demandes'); });
    $('#showRetards').click(function(e) { e.preventDefault(); loadView('retards'); });
    $('#showPointages').click(function(e) { e.preventDefault(); loadView('pointages'); });
    $('#showTempsTravail').click(function(e) { e.preventDefault(); loadView('temps_travail'); });
    $('#showNotifications').click(function(e) { e.preventDefault(); loadView('notifications'); });
    // Add more click handlers as needed
});
</script>
</body>
</html> 