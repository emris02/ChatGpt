<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Gestion des préférences (stockées en localStorage côté client)
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true; // On affiche juste un message de succès, le JS s'occupe du stockage
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Paramètres du profil admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f5f7fb; }
        .settings-card { max-width: 500px; margin: 40px auto; border-radius: 16px; box-shadow: 0 8px 32px rgba(67,97,238,0.08); }
        .avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid #4361ee; margin-bottom: 10px; }
        .form-label { font-weight: 500; }
        .form-control:focus { border-color: #4361ee; box-shadow: 0 0 0 0.2rem rgba(67,97,238,0.15); }
        .btn-primary { background: linear-gradient(90deg,#4361ee,#3f37c9); border: none; }
        .btn-primary:hover { background: #3f37c9; }
        .settings-title { letter-spacing: 1px; }
    </style>
</head>
<body>
<div class="container">
    <div class="settings-card bg-white p-4 mt-5">
        <div class="text-center mb-4">
            <img src="assets/xpertpro.png" alt="Avatar" class="avatar shadow">
            <h3 class="settings-title mb-0">Paramètres de l'interface</h3>
            <div class="text-muted small">Personnalisez votre expérience d'administration</div>
        </div>
        <?php if ($success): ?>
            <div class="alert alert-success">✅ Préférences enregistrées dans votre navigateur.</div>
        <?php endif; ?>
        <form method="post" id="settings-form" autocomplete="off">
            <div class="mb-4">
                <label class="form-label">Thème de l'interface</label>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="theme" id="theme-clair" value="clair" checked>
                        <label class="form-check-label" for="theme-clair"><i class="fas fa-sun me-1"></i>Clair</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="theme" id="theme-sombre" value="sombre">
                        <label class="form-check-label" for="theme-sombre"><i class="fas fa-moon me-1"></i>Sombre</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="theme" id="theme-auto" value="auto">
                        <label class="form-check-label" for="theme-auto"><i class="fas fa-adjust me-1"></i>Auto</label>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Notifications</label>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="notif-switch" name="notifications" checked>
                    <label class="form-check-label" for="notif-switch">Activer les notifications système</label>
                </div>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" id="notif-mail" name="notif_mail">
                    <label class="form-check-label" for="notif-mail">Recevoir les alertes importantes par email</label>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Affichage & Expérience</label>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="anim-switch" name="animations" checked>
                            <label class="form-check-label" for="anim-switch">Animations de transition</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="table-switch" name="table_compact">
                            <label class="form-check-label" for="table-switch">Tableaux compacts</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="sidebar-mini" name="sidebar_mini">
                            <label class="form-check-label" for="sidebar-mini">Menu latéral réduit</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dashboard-graph" name="dashboard_graph" checked>
                            <label class="form-check-label" for="dashboard-graph">Graphiques statistiques</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dashboard-cards" name="dashboard_cards" checked>
                            <label class="form-check-label" for="dashboard-cards">Cartes animées</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="dashboard-welcome" name="dashboard_welcome">
                            <label class="form-check-label" for="dashboard-welcome">Message d'accueil</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Accessibilité</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="font-large" name="font_large">
                    <label class="form-check-label" for="font-large">Police agrandie</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="contrast" name="contrast">
                    <label class="form-check-label" for="contrast">Contraste élevé</label>
                </div>
            </div>
            <div class="d-grid gap-2 mb-3">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save me-2"></i>Enregistrer mes préférences</button>
            </div>
        </form>
        <div class="alert alert-info mb-2"><i class="fas fa-lightbulb me-2"></i>Astuce : Personnalisez votre interface pour plus de confort !</div>
        <div class="text-center mt-3">
            <a href="mailto:support@xpertpro.com" class="text-decoration-none text-primary"><i class="fas fa-life-ring me-1"></i>Besoin d'aide ? Contacter le support</a>
        </div>
        <div class="text-center mt-2">
            <a href="#" class="text-decoration-none text-secondary" data-bs-toggle="modal" data-bs-target="#faqModal"><i class="fas fa-question-circle me-1"></i>FAQ / Aide rapide</a>
        </div>
    </div>
    <!-- Modal FAQ -->
    <div class="modal fade" id="faqModal" tabindex="-1" aria-labelledby="faqModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="faqModalLabel"><i class="fas fa-question-circle me-2"></i>FAQ & Aide rapide</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
          </div>
          <div class="modal-body">
            <ul class="list-group mb-3">
              <li class="list-group-item"><b>Comment changer mon mot de passe ?</b><br>Utilisez le bouton "Modifier mes informations" ci-dessus.</li>
              <li class="list-group-item"><b>Comment activer le mode sombre ?</b><br>Sélectionnez "Sombre" dans le menu Thème puis enregistrez.</li>
              <li class="list-group-item"><b>Comment contacter le support ?</b><br>Un lien direct est disponible en bas de cette page.</li>
              <li class="list-group-item"><b>Mes préférences sont-elles conservées ?</b><br>Oui, elles sont enregistrées dans votre navigateur.</li>
            </ul>
            <div class="text-center text-muted small">Pour toute question, contactez le support Xpert Pro.</div>
          </div>
        </div>
      </div>
    </div>
</div>
<script>
// Gestion des préférences en localStorage
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('settings-form');
    // Charger les préférences si elles existent
    const prefs = JSON.parse(localStorage.getItem('admin_prefs') || '{}');
    if (prefs.theme) {
        document.getElementById('theme-' + prefs.theme).checked = true;
    }
    [
        'notif-switch', 'notif-mail', 'anim-switch', 'table-switch', 'sidebar-mini',
        'dashboard-graph', 'dashboard-cards', 'dashboard-welcome', 'font-large', 'contrast'
    ].forEach(id => {
        if (prefs[id] !== undefined) {
            document.getElementById(id).checked = !!prefs[id];
        }
    });

    form.addEventListener('submit', function(e) {
        // On ne bloque pas le submit pour afficher le message PHP
        const data = new FormData(form);
        const toStore = {};
        toStore.theme = data.get('theme');
        [
            'notif-switch', 'notif-mail', 'anim-switch', 'table-switch', 'sidebar-mini',
            'dashboard-graph', 'dashboard-cards', 'dashboard-welcome', 'font-large', 'contrast'
        ].forEach(id => {
            toStore[id] = document.getElementById(id).checked;
        });
        localStorage.setItem('admin_prefs', JSON.stringify(toStore));
    });

    // Application du thème en live (optionnel)
    document.querySelectorAll('input[name="theme"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.body.setAttribute('data-theme', this.value);
        });
    });
    if (prefs.theme) {
        document.body.setAttribute('data-theme', prefs.theme);
    }
});
</script>
</body>
</html>
