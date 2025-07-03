<?php if (!isset($employe)) return; ?>

<!-- Modal : Réinitialisation mot de passe -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" action="reset_password.php" method="POST">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-key me-2"></i> Réinitialiser le mot de passe</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="employe_id" value="<?= $employe['id'] ?>">
        <div class="mb-3">
          <label for="new_password" class="form-label">Nouveau mot de passe</label>
          <input type="password" class="form-control" name="new_password" id="new_password" required>
        </div>
        <div class="mb-3">
          <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
          <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Réinitialiser</button>
      </div>
    </form>
  </div>
</div>
