<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Scan de Badge - Xpert Pro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="scan.css">
</head>
<body>
    <div class="scan-container">
        <div class="scan-header">
            <h2><i class="fas fa-qrcode me-2"></i> Scan de Badge</h2>
            <p class="mb-0">Positionnez votre badge devant la caméra</p>
        </div>
        
        <div class="p-4">
            <div class="text-center position-relative">
                <video id="qr-video" class="pulse"></video>
                <div id="scan-status" class="scan-status waiting mt-3">
                    <i class="fas fa-search me-2"></i> En attente de détection...
                </div>
                <div id="feedback-message" class="mt-2"></div>
            </div>
            
            <div class="d-flex justify-content-center gap-3 mt-4">
                <button id="restart-scan" class="btn btn-warning">
                    <i class="fas fa-redo me-2"></i> Recommencer
                </button>
                <a href="employe_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-home me-2"></i> Tableau de bord
                </a>
            </div>
        </div>
        
        <div class="p-4 bg-light">
            <style>
                .event-card-list {
                    background: #fff;
                    border-radius: 12px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                    padding: 12px 0 12px 0;
                    margin-bottom: 0;
                    max-height: 260px;
                    overflow-y: auto;
                }
                .event-card-list .list-group-item {
                    border: none;
                    border-radius: 8px;
                    margin-bottom: 4px;
                    background: #f8f9fa;
                    font-size: 0.97rem;
                    word-break: break-word;
                }
                .event-card-list .list-group-item:last-child {
                    margin-bottom: 0;
                }
                .event-card.card, .history-card.card {
                    border-radius: 16px;
                    box-shadow: 0 4px 16px rgba(67,97,238,0.07);
                }
                .event-card .card-header, .history-card .card-header {
                    border-radius: 16px 16px 0 0;
                }
            </style>
            <div class="row g-4 align-items-stretch">
                <div class="col-md-6 h-100 d-flex flex-column">
                    <div class="history-card card h-100 flex-grow-1">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-history me-2"></i> Historique des scans</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul id="historique" class="list-group event-card-list"></ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 h-100 d-flex flex-column">
                    <div class="journal-card card h-100 flex-grow-1 event-card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-list me-2"></i> Journal des événements</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul id="journal" class="list-group event-card-list"></ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Justification de Retard -->
    <div class="modal fade" id="modalJustifRetard" tabindex="-1" aria-labelledby="modalJustifRetardLabel" aria-hidden="true">
      <div class="modal-dialog">
        <form class="modal-content" id="formJustifRetard">
          <div class="modal-header">
            <h5 class="modal-title" id="modalJustifRetardLabel"><i class="fas fa-clock me-2"></i> Justification de retard</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label for="justifComment" class="form-label">Motif du retard</label>
              <textarea class="form-control" id="justifComment" name="comment" rows="3" required></textarea>
            </div>
            <input type="hidden" id="justifEmployeId" name="employe_id">
            <input type="hidden" id="justifDate" name="date">
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="submit" class="btn btn-primary">Envoyer la justification</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Place ici ton script JS séparé (versionné pour forcer le cache) -->
    <script type="module" src="scan.js?v=20250701"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>