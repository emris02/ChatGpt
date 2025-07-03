// === CONFIGURATION GLOBALE ===
const api = {
    scan: 'pointage.php',
    saveJustification: 'save_late_reason.php',
    updateField: 'employe_dashboard.php' // ➕ Utilise le même fichier PHP
};

// === ÉTAT GLOBAL DU SCANNER ===
const state = {
    isProcessing: false,
    lastScan: '',
    scanner: null,
    cameras: [],
    currentCam: 0
};

// === IMPORTS ===
import QrScanner from './js/qr-scanner.min.js';
QrScanner.WORKER_PATH = './js/qr-scanner-worker.min.js';

// === INITIALISATION DU SCANNER ===
export async function initScanner(videoElement, onValid, onError) {
    try {
        state.cameras = await QrScanner.listCameras(true);
        if (state.cameras.length === 0) throw new Error('Aucune caméra détectée.');

        state.scanner = new QrScanner(
            videoElement,
            (result) => handleScan(result.data, onValid, onError),
            {
                preferredCamera: state.cameras[state.currentCam]?.id,
                highlightScanRegion: true,
                highlightCodeOutline: true,
                maxScansPerSecond: 2
            }
        );

        await state.scanner.start();
    } catch (error) {
        onError?.(`Erreur initialisation caméra : ${error.message}`);
    }
}

export function switchCamera() {
    if (state.cameras.length < 2) return;
    state.currentCam = (state.currentCam + 1) % state.cameras.length;
    state.scanner.setCamera(state.cameras[state.currentCam].id);
}

export function restartScanner() {
    state.scanner?.start();
}

// === TRAITEMENT D'UN SCAN ===
async function handleScan(token, onValid, onError) {
    if (state.isProcessing || token === state.lastScan) return;
    state.isProcessing = true;
    state.lastScan = token;

    try {
        const res = await fetch(api.scan, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ badge_token: token }) // Correction ici
        });

        const data = await res.json();
        data.status === 'success'
            ? onValid?.(data)
            : onError?.(data.message || "Échec validation.");

    } catch (error) {
        onError?.("Erreur de réseau : " + error.message);
    } finally {
        state.isProcessing = false;
        setTimeout(() => {
            state.lastScan = '';
            restartScanner();
        }, 2000);
    }
// === AUTO-INIT POUR SCAN_QR.PHP ===
document.addEventListener('DOMContentLoaded', () => {
    const video = document.getElementById('qr-video');
    const feedback = document.getElementById('feedback-message');
    const scanStatus = document.getElementById('scan-status');
    if (video && feedback && scanStatus) {
        initScanner(
            video,
            (data) => {
                feedback.textContent = data.message || 'Succès';
                feedback.className = 'message-feedback text-success';
                scanStatus.innerHTML = '<i class="fas fa-check-circle me-2"></i> Badge validé !';
            },
            (errMsg) => {
                feedback.textContent = errMsg;
                feedback.className = 'message-feedback text-danger';
                scanStatus.innerHTML = '<i class="fas fa-times-circle me-2"></i> Erreur';
            }
        );
    }
});
}

// === ENVOI JUSTIFICATION DE RETARD ===
export async function sendJustification({ employe_id, scan_time, late_time, reason, comment }, onSuccess, onFail) {
    try {
        const response = await fetch(api.saveJustification, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                employee_id: employe_id,
                scan_time,
                late_time,
                reason,
                comment,
                status: 'pending'
            })
        });

        const result = await response.json();
        result.success ? onSuccess?.(result) : onFail?.(result.message);
    } catch (err) {
        onFail?.("Erreur réseau : " + err.message);
    }
}

// === UTILITAIRES UI ===
export function afficherNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => notification.remove(), 4000);
}

export function showToast(title, message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0 show`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                <strong>${title}</strong><br>${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
        </div>
    `;

    const container = document.getElementById('alertsContainer') || document.body;
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// === TIMER EXPIRATION BADGE ===
export function initBadgeTimer(expirationDateStr, timerSelector = '#badge-timer') {
    const timerEl = document.querySelector(timerSelector);
    if (!timerEl) return;

    const expiry = new Date(expirationDateStr).getTime();

    function updateTimer() {
        const now = new Date().getTime();
        const distance = expiry - now;

        if (distance <= 0) {
            timerEl.innerHTML = "⛔ Expiré";
            timerEl.classList.remove("text-success");
            timerEl.classList.add("text-danger");
            clearInterval(interval);
            return;
        }

        const h = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const m = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((distance % (1000 * 60)) / 1000);

        timerEl.innerHTML = `⏳ Expire dans ${h}h ${m}m ${s}s`;
        if (distance < 3600000) {
            timerEl.classList.remove("text-success");
            timerEl.classList.add("text-warning");
        }
    }

    updateTimer();
    const interval = setInterval(updateTimer, 1000);
}

// === TOOLTIP BOOTSTRAP + EDIT INLINE ===
document.addEventListener('DOMContentLoaded', () => {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));

    // Gestion inline des champs modifiables
    document.querySelectorAll('.edit-inline').forEach(btn => {
        btn.addEventListener('click', function () {
            const container = this.closest('.detail-item');
            const display = container.querySelector('.detail-value');
            const input = container.querySelector('input, textarea');
            const saveBtn = container.querySelector('.btn-success');

            display.classList.add('d-none');
            input.classList.remove('d-none');
            this.classList.add('d-none');
            saveBtn.classList.remove('d-none');
        });
    });
});

// === MISE À JOUR CHAMP INLINE ===
export function saveField(field, baseId) {
    const input = document.getElementById(`${baseId}Input`);
    const display = document.getElementById(`${baseId}Display`);
    const saveBtn = document.getElementById(`save${capitalize(baseId)}Btn`);
    const editBtn = saveBtn?.previousElementSibling;

    const value = input.value.trim();
    if (!value) return alert("Le champ ne peut pas être vide.");

    fetch(api.updateField, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ field, value })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            display.textContent = (field === 'mot_de_passe') ? '********' : value;
            input.classList.add('d-none');
            display.classList.remove('d-none');
            saveBtn.classList.add('d-none');
            editBtn?.classList.remove('d-none');
        } else {
            alert(data.message || "Erreur lors de la mise à jour.");
        }
    })
    .catch(err => alert("Erreur réseau : " + err.message));
}

function capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}
