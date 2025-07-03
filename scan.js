
import QrScanner from './js/qr-scanner.min.js';

const video = document.getElementById('qr-video');
const feedback = document.getElementById('feedback-message');
const historique = document.getElementById('historique');
const journal = document.getElementById('journal');
const scanStatus = document.getElementById('scan-status');
const restartBtn = document.getElementById('restart-scan');

// API endpoint
const api = {
    scan: 'pointage.php'
};

let lastScan = '';
let scanner = null;
let isProcessing = false;

// Centraliser l'affichage des notifications
function afficherNotification(message, type = 'info') {
    feedback.textContent = message;
    feedback.className = `message-feedback text-${type}`;
}


// Afficher un pointage dans l'historique (prénom, nom, date, heure)
function addHistoriqueItem({prenom, nom, date_pointage, heure_pointage}) {
    const li = document.createElement('li');
    li.className = 'list-group-item d-flex justify-content-between align-items-center';
    li.innerHTML = `<span><strong>${prenom} ${nom}</strong></span><span class="text-muted small">${date_pointage} à ${heure_pointage}</span>`;
    historique.appendChild(li);
}

// Charger l'historique réel depuis le backend
async function chargerHistorique() {
    try {
        const res = await fetch('get_pointage_history.php');
        const data = await res.json();
        historique.innerHTML = '';
        if (data.success && Array.isArray(data.data) && data.data.length > 0) {
            data.data.forEach(addHistoriqueItem);
        } else {
            const li = document.createElement('li');
            li.className = 'list-group-item text-muted';
            li.textContent = 'Aucun pointage récent.';
            historique.appendChild(li);
        }
    } catch (e) {
        const li = document.createElement('li');
        li.className = 'list-group-item text-danger';
        li.textContent = "Erreur lors du chargement de l'historique.";
        historique.appendChild(li);
    }
}



// Ajouter un événement au journal (dynamique)
function addJournalItem({datetime, type, message}) {
    const li = document.createElement('li');
    let color = 'secondary';
    if (type === 'POINTAGE') color = 'success';
    if (type === 'ERREUR') color = 'danger';
    li.className = `list-group-item d-flex justify-content-between align-items-center text-${color}`;
    li.innerHTML = `<span><strong>${type}</strong> - ${message}</span><span class="small text-muted">${datetime}</span>`;
    journal.appendChild(li);
}

// Ajoute une entrée simple au journal (fallback pour les appels addJournal)
function addJournal(message, type = 'info') {
    const li = document.createElement('li');
    let color = 'secondary';
    if (type === 'success') color = 'success';
    if (type === 'danger') color = 'danger';
    li.className = `list-group-item d-flex justify-content-between align-items-center text-${color}`;
    const now = new Date();
    const datetime = now.toLocaleString('fr-FR');
    li.innerHTML = `<span><strong>${type.toUpperCase()}</strong> - ${message}</span><span class="small text-muted">${datetime}</span>`;
    journal.appendChild(li);
}

// Charger le journal des événements depuis le backend
async function chargerJournal() {
    try {
        const res = await fetch('get_pointage_journal.php');
        const data = await res.json();
        journal.innerHTML = '';
        if (data.success && Array.isArray(data.data) && data.data.length > 0) {
            data.data.forEach(addJournalItem);
        } else {
            const li = document.createElement('li');
            li.className = 'list-group-item text-muted';
            li.textContent = 'Aucun événement récent.';
            journal.appendChild(li);
        }
    } catch (e) {
        const li = document.createElement('li');
        li.className = 'list-group-item text-danger';
        li.textContent = "Erreur lors du chargement du journal.";
        journal.appendChild(li);
    }
}

// Traiter le scan
async function handleScan(token) {
    if (isProcessing || token === lastScan) return;
    isProcessing = true;
    lastScan = token;
    // Afficher le token scanné pour debug
    if (scanStatus) {
        scanStatus.innerHTML = `<span class='text-info'>Token scanné :</span><br><code style='font-size:0.9em;'>${token}</code><br><i class="fas fa-spinner fa-spin me-2"></i> Vérification...`;
    }

    try {
        // Toujours envoyer en JSON
        const res = await fetch(api.scan, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ badge_token: token })
        });
        const data = await res.json();

        if (data.status === 'success') {
            afficherNotification(data.message, 'success');
            addJournal(data.message, 'success');
            // Si retard, afficher le modal
            if (data.retard === true || data.retard === 1) {
                showJustifModal(data);
            }
        } else {
            afficherNotification(data.message || "Échec validation.", 'danger');
            addJournal(data.message || "Échec validation.", 'danger');
        }

    } catch (error) {
        afficherNotification("⛔ Erreur de réseau : " + error.message, 'danger');
        addJournal("Erreur réseau : " + error.message, 'danger');
    } finally {
        isProcessing = false;
        scanStatus.innerHTML = '<i class="fas fa-search me-2"></i> En attente de détection...';
        setTimeout(() => {
            lastScan = '';
            scanner.start();
        }, 2000);
    }
}

// Initialiser le scanner
function initScanner() {
    scanner = new QrScanner(
        video,
        result => {
            const qrText = result.data ?? result;
            handleScan(qrText);
        },
        {
            highlightScanRegion: true,
            maxScansPerSecond: 2
        }
    );
    scanner.start();
}

// Bouton de redémarrage
if (restartBtn) {
    restartBtn.addEventListener('click', () => {
        if (scanner) {
            lastScan = '';
            scanner.start();
            afficherNotification("Redémarrage du scanner", 'info');
        }
    });
}

// Affichage du modal de justification de retard
function showJustifModal(data) {
    const modal = new bootstrap.Modal(document.getElementById('modalJustifRetard'));
    document.getElementById('justifEmployeId').value = data.employe_id || '';
    document.getElementById('justifDate').value = (data.timestamp || '').split(' ')[0];
    document.getElementById('justifComment').value = '';
    modal.show();
}

// Gestion de l'envoi du formulaire de justification
window.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formJustifRetard');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const employe_id = document.getElementById('justifEmployeId').value;
            const date = document.getElementById('justifDate').value;
            const comment = document.getElementById('justifComment').value;
            try {
                const res = await fetch('justifier_pointage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        employe_id,
                        date,
                        type: 'retard',
                        est_justifie: 1,
                        commentaire: comment
                    })
                });
                if (res.ok) {
                    afficherNotification('Justification envoyée.', 'success');
                    bootstrap.Modal.getInstance(document.getElementById('modalJustifRetard')).hide();
                } else {
                    afficherNotification('Erreur lors de la justification.', 'danger');
                }
            } catch (err) {
                afficherNotification('Erreur réseau.', 'danger');
            }
        });
    }
});

// Lancer le scanner et charger l'historique + journal au chargement
window.addEventListener('DOMContentLoaded', () => {
    initScanner();
    chargerHistorique();
    chargerJournal();
});