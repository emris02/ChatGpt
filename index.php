<?php
require_once 'db.php';
require_once 'BadgeManager.php';
date_default_timezone_set('Europe/Paris');

// Récupérer tous les employés
$employes = $pdo->query("SELECT id, nom, prenom FROM employes ORDER BY nom, prenom")->fetchAll();

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['verif_token']) && !empty($_POST['token_test'])) {
        // Vérification d'un token arbitraire
        $token_test = trim($_POST['token_test']);
        try {
            require_once 'BadgeManager.php';
            $tokenData = BadgeManager::verifyToken($token_test, $pdo);
            $result = [
                'status' => 'success',
                'message' => 'Token VALIDE et ACTIF',
                'token_actif' => $token_test,
                'expires_at' => $tokenData['expires_at'] ?? null,
                'employe_id' => $tokenData['employe_id'] ?? null,
                'nom' => $tokenData['nom'] ?? '',
                'prenom' => $tokenData['prenom'] ?? '',
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        } catch (Exception $e) {
            $result = [
                'status' => 'error',
                'message' => 'Token INVALIDE ou INACTIF : ' . $e->getMessage(),
                'token_actif' => $token_test,
                'timestamp' => date('Y-m-d H:i:s'),
            ];
        }
    } else {
        $employe_id = (int)($_POST['employe_id'] ?? 0);
        // Récupérer le badge actif
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("SELECT token, expires_at FROM badge_tokens WHERE employe_id = ? AND status = 'active' AND expires_at > ? ORDER BY expires_at DESC LIMIT 1");
        $stmt->execute([$employe_id, $now]);
        $badge = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$badge) {
            $result = [ 'status' => 'error', 'message' => "Aucun badge actif pour cet employé." ];
        } else {
            // Vérification du token comme dans le scan
            try {
                require_once 'BadgeManager.php';
                $tokenData = BadgeManager::verifyToken($badge['token'], $pdo);
                $result = [
                    'status' => 'success',
                    'message' => 'Token VALIDE et ACTIF (test API)',
                    'token_actif' => $badge['token'],
                    'expires_at' => $tokenData['expires_at'] ?? $badge['expires_at'],
                    'employe_id' => $tokenData['employe_id'] ?? $employe_id,
                    'nom' => $tokenData['nom'] ?? '',
                    'prenom' => $tokenData['prenom'] ?? '',
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
            } catch (Exception $e) {
                $result = [
                    'status' => 'error',
                    'message' => 'Token INVALIDE ou INACTIF (test API) : ' . $e->getMessage(),
                    'token_actif' => $badge['token'],
                    'timestamp' => date('Y-m-d H:i:s'),
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Pointage Manuel | Xpert+</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background:#f6f8fa;">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-10">
            <div class="card shadow-lg rounded-4">
                <div class="card-header bg-primary text-white d-flex align-items-center gap-2">
                    <i class="fas fa-vial fa-lg"></i>
                    <h3 class="mb-0">Test manuel de pointage</h3>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3 align-items-end">
                        <div class="col-md-7">
                            <label for="employe_id" class="form-label">Employé à pointer</label>
                            <select name="employe_id" id="employe_id" class="form-select" required>
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($employes as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= (isset($_POST['employe_id']) && $_POST['employe_id'] == $e['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['nom'].' '.$e['prenom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5 d-flex gap-2">
                            <button type="submit" name="type" value="arrivee" class="btn btn-success w-100">
                                <i class="fas fa-sign-in-alt"></i> Pointer Arrivée
                            </button>
                            <button type="submit" name="type" value="depart" class="btn btn-danger w-100">
                                <i class="fas fa-sign-out-alt"></i> Pointer Départ
                            </button>
                        </div>
                    </form>
                    <hr class="my-4">
                    <form method="post" class="row g-3 align-items-end">
                        <div class="col-md-7">
                            <label for="token_test" class="form-label">Vérifier un token (copier/coller depuis un badge physique ou QR)</label>
                            <input type="text" name="token_test" id="token_test" class="form-control" placeholder="Collez ici le token à tester">
                        </div>
                        <div class="col-md-5 d-flex gap-2">
                            <button type="submit" name="verif_token" value="1" class="btn btn-warning w-100">
                                <i class="fas fa-search"></i> Vérifier ce token
                            </button>
                        </div>
                    </form>
                    <?php if ($result): ?>
                        <div class="alert mt-4 <?= $result['status']==='success' ? 'alert-success' : 'alert-danger' ?>">
                            <strong><?= ucfirst($result['status']) ?> :</strong> <?= htmlspecialchars($result['message'] ?? '') ?><br>
                            <?php if (!empty($result['timestamp'])): ?>
                                <span class="text-muted small">Horodatage : <?= htmlspecialchars($result['timestamp']) ?></span><br>
                            <?php endif; ?>
                            <?php if (!empty($result['token_actif'])): ?>
                                <span class="text-muted small">Token actif : <code><?= htmlspecialchars($result['token_actif']) ?></code></span><br>
                                <span class="text-muted small">Expiration : <?= htmlspecialchars($result['expires_at']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-4 text-muted small">
                        <i class="fas fa-info-circle"></i> Ce test simule un scan QR sans caméra. Utilisez-le pour valider le workflow de pointage sans badge physique.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>