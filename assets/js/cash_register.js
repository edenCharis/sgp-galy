// cash_register.js - JavaScript corrigé pour la gestion des caisses

// Variables globales
let expectedAmount = 0;
let currentRegisterId = null;

// Initialisation de l'application
document.addEventListener('DOMContentLoaded', function() {
    // Vérifier la disponibilité des éléments requis
    checkRequiredElements();
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Setup sidebar functionality
    setupSidebar();
    
    // Initialize tooltips
    initializeTooltips();
    
    // Setup event listeners
    setupEventListeners();
    
    console.log('Cash Register Management System initialized');
});

// Vérifier que tous les éléments requis existent
function checkRequiredElements() {
    const requiredElements = [
        'closeRegisterModal',
        'close_register_id',
        'close_cashier_name',
        'expected_amount',
        'final_amount',
        'difference_alert',
        'difference_amount',
        'registerDetailsModal',
        'registerDetailsContent'
    ];
    
    const missingElements = [];
    requiredElements.forEach(id => {
        if (!document.getElementById(id)) {
            missingElements.push(id);
        }
    });
    
    if (missingElements.length > 0) {
        console.warn('Éléments manquants:', missingElements);
        return false;
    }
    return true;
}

// Configuration des écouteurs d'événements
function setupEventListeners() {
    // Écouteur pour le calcul de la différence
    const finalAmountInput = document.getElementById('final_amount');
    if (finalAmountInput) {
        finalAmountInput.addEventListener('input', calculateDifference);
    }
    
    // Validation des formulaires
    const closeForm = document.getElementById('closeRegisterForm');
    if (closeForm) {
        closeForm.addEventListener('submit', validateCloseForm);
    }
    
    const openForm = document.getElementById('openRegisterForm');
    if (openForm) {
        openForm.addEventListener('submit', validateOpenForm);
    }
    
    // Raccourcis clavier
    document.addEventListener('keydown', handleKeyboardShortcuts);
}

// FONCTION CORRIGÉE: Ouvrir la modal de fermeture de caisse
function openCloseModal(registerId, cashierName, expected) {
    // Vérifier que les éléments existent
    const modal = document.getElementById('closeRegisterModal');
    const registerIdInput = document.getElementById('close_register_id');
    const cashierNameSpan = document.getElementById('close_cashier_name');
    const expectedAmountSpan = document.getElementById('expected_amount');
    const finalAmountInput = document.getElementById('final_amount');
    const differenceAlert = document.getElementById('difference_alert');
    
    if (!modal || !registerIdInput || !cashierNameSpan || !expectedAmountSpan || !finalAmountInput) {
        console.error('Éléments de la modal de fermeture manquants');
        showNotification('Erreur: Interface incomplète', 'danger');
        return;
    }
    
    try {
        // Stocker les valeurs
        expectedAmount = parseFloat(expected) || 0;
        currentRegisterId = registerId;
        
        // Remplir les champs
        registerIdInput.value = registerId;
        cashierNameSpan.textContent = cashierName;
        expectedAmountSpan.textContent = formatNumber(expectedAmount) + ' XAF';
        finalAmountInput.value = expectedAmount;
        
        // Reset difference alert
        if (differenceAlert) {
            differenceAlert.style.display = 'none';
            differenceAlert.className = 'alert difference-alert';
        }
        
        // Afficher la modal
        const modalInstance = new bootstrap.Modal(modal);
        modalInstance.show();
        
        // Focus sur le champ montant final après ouverture
        modal.addEventListener('shown.bs.modal', function() {
            finalAmountInput.select();
        }, { once: true });
        
    } catch (error) {
        console.error('Erreur lors de l\'ouverture de la modal:', error);
        showNotification('Erreur lors de l\'ouverture de la modal', 'danger');
    }
}

// FONCTION CORRIGÉE: Voir les détails de la caisse
function viewRegisterDetails(registerId) {
    const modal = document.getElementById('registerDetailsModal');
    const detailsContent = document.getElementById('registerDetailsContent');
    
    if (!modal || !detailsContent) {
        console.error('Éléments de la modal de détails manquants');
        showNotification('Erreur: Interface incomplète', 'danger');
        return;
    }
    
    // Afficher un indicateur de chargement
    detailsContent.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2">Chargement des détails...</p>
        </div>
    `;
    
    // Afficher la modal
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    
    // Récupérer les détails via AJAX
    fetch(`get_register_details.php?id=${registerId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayRegisterDetails(data.register);
            } else {
                throw new Error(data.message || 'Erreur inconnue');
            }
        })
        .catch(error => {
            console.error('Erreur lors de la récupération des détails:', error);
            detailsContent.innerHTML = `
                <div class="alert alert-danger">
                    <i data-lucide="alert-triangle"></i>
                    <strong>Erreur:</strong> ${error.message}
                    <hr>
                    <p class="mb-0">Impossible de charger les détails de la caisse.</p>
                </div>
            `;
            // Re-initialize icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
}

// Afficher les détails de la caisse
function displayRegisterDetails(register) {
    const detailsContent = document.getElementById('registerDetailsContent');
    
    const statusClass = register.status === 'open' ? 'success' : 'secondary';
    const statusText = register.status === 'open' ? 'Ouverte' : 'Fermée';
    
    detailsContent.innerHTML = `
        <div class="row g-4">
            <!-- Informations générales -->
            <div class="col-12">
                <div class="card border-0 bg-light">
                    <div class="card-header bg-primary text-white">
                        <h6 class="mb-0"><i data-lucide="info"></i> Informations générales</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <strong>Caissier:</strong> ${register.cashier_name}
                            </div>
                            <div class="col-md-6">
                                <strong>Statut:</strong> 
                                <span class="badge bg-${statusClass}">${statusText}</span>
                            </div>
                            <div class="col-md-6">
                                <strong>Ouverture:</strong> ${register.opening_time}
                            </div>
                            <div class="col-md-6">
                                <strong>Fermeture:</strong> ${register.closing_time || 'En cours'}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Montants -->
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0"><i data-lucide="dollar-sign"></i> Montants</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Fonds initial:</strong> ${formatNumber(register.initial_amount)} XAF
                        </div>
                        <div class="mb-2">
                            <strong>Total ventes:</strong> ${formatNumber(register.total_sales)} XAF
                        </div>
                        ${register.status === 'closed' ? `
                            <div class="mb-2">
                                <strong>Montant final:</strong> ${formatNumber(register.final_amount)} XAF
                            </div>
                            <div class="mb-0">
                                <strong>Écart:</strong> 
                                <span class="${register.difference >= 0 ? 'text-success' : 'text-danger'}">
                                    ${register.difference >= 0 ? '+' : ''}${formatNumber(register.difference)} XAF
                                </span>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
            
            <!-- Statistiques -->
            <div class="col-md-6">
                <div class="card border-0 bg-light">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i data-lucide="bar-chart"></i> Statistiques</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Nombre de ventes:</strong> ${register.sales_count}
                        </div>
                        <div class="mb-2">
                            <strong>Vente moyenne:</strong> ${register.sales_count > 0 ? formatNumber(register.total_sales / register.sales_count) : '0'} XAF
                        </div>
                        ${register.duration ? `
                            <div class="mb-0">
                                <strong>Durée d'activité:</strong> ${register.duration}
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
            
            <!-- Transactions récentes -->
            ${register.recent_transactions && register.recent_transactions.length > 0 ? `
                <div class="col-12">
                    <div class="card border-0 bg-light">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i data-lucide="list"></i> Dernières transactions</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Heure</th>
                                            <th>N° Facture</th>
                                            <th>Client</th>
                                            <th>Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${register.recent_transactions.map(transaction => `
                                            <tr>
                                                <td>${transaction.time}</td>
                                                <td>${transaction.invoice_number}</td>
                                                <td>${transaction.client_name || 'Anonyme'}</td>
                                                <td>${formatNumber(transaction.total_amount)} XAF</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            ` : ''}
        </div>
    `;
    
    // Re-initialize icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Calculer la différence
function calculateDifference() {
    const finalAmount = parseFloat(this.value) || 0;
    const difference = finalAmount - expectedAmount;
    const differenceAlert = document.getElementById('difference_alert');
    const differenceAmountSpan = document.getElementById('difference_amount');
    
    if (differenceAlert && differenceAmountSpan) {
        if (Math.abs(difference) >= 1) {
            differenceAlert.style.display = 'block';
            
            if (difference > 0) {
                differenceAlert.className = 'alert alert-warning difference-alert';
                differenceAmountSpan.innerHTML = `+${formatNumber(difference)} XAF <small>(Excédent)</small>`;
            } else {
                differenceAlert.className = 'alert alert-danger difference-alert';
                differenceAmountSpan.innerHTML = `${formatNumber(difference)} XAF <small>(Manquant)</small>`;
            }
        } else {
            differenceAlert.style.display = 'none';
        }
    }
}

// Validation du formulaire de fermeture
function validateCloseForm(e) {
    const finalAmountInput = document.getElementById('final_amount');
    
    if (!finalAmountInput) {
        e.preventDefault();
        showNotification('Erreur: Champ montant final manquant', 'danger');
        return false;
    }
    
    const finalAmount = parseFloat(finalAmountInput.value);
    
    if (isNaN(finalAmount) || finalAmount < 0) {
        e.preventDefault();
        showNotification('Veuillez entrer un montant valide', 'danger');
        finalAmountInput.focus();
        return false;
    }
    
    // Confirmation si écart important
    const difference = Math.abs(finalAmount - expectedAmount);
    if (difference > 1000) { // Écart de plus de 1000 XAF
        if (!confirm(`Attention: Écart important détecté (${formatNumber(difference)} XAF).\nConfirmez-vous la fermeture de la caisse ?`)) {
            e.preventDefault();
            return false;
        }
    }
    
    // Ajouter loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Fermeture...';
    }
    
    return true;
}

// Validation du formulaire d'ouverture
function validateOpenForm(e) {
    const cashierSelect = e.target.querySelector('select[name="cashier_id"]');
    const initialAmountInput = e.target.querySelector('input[name="initial_amount"]');
    
    if (!cashierSelect || !cashierSelect.value) {
        e.preventDefault();
        showNotification('Veuillez sélectionner un caissier', 'danger');
        if (cashierSelect) cashierSelect.focus();
        return false;
    }
    
    if (!initialAmountInput || isNaN(parseFloat(initialAmountInput.value)) || parseFloat(initialAmountInput.value) < 0) {
        e.preventDefault();
        showNotification('Veuillez entrer un montant initial valide', 'danger');
        if (initialAmountInput) initialAmountInput.focus();
        return false;
    }
    
    // Ajouter loading state
    const submitBtn = e.target.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Ouverture...';
    }
    
    return true;
}

// Actualiser les données
function refreshData() {
    showNotification('Actualisation des données...', 'info');
    
    const refreshBtn = document.querySelector('[onclick="refreshData()"]');
    if (refreshBtn) {
        const originalContent = refreshBtn.innerHTML;
        refreshBtn.disabled = true;
        refreshBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>';
        
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    } else {
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
}

// Imprimer le rapport
function printRegisterReport() {
    if (!currentRegisterId) {
        showNotification('Aucune caisse sélectionnée', 'warning');
        return;
    }
    
    showNotification('Préparation du rapport...', 'info');
    
    // Récupérer les détails pour l'impression
    fetch(`get_register_details.php?id=${currentRegisterId}&format=print`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                generatePrintReport(data.register);
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('Erreur lors de la génération du rapport', 'danger');
        });
}

// Générer le rapport d'impression
function generatePrintReport(register) {
    const printContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Rapport de Caisse - ${register.cashier_name}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.4; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .logo { font-size: 24px; font-weight: bold; color: #667eea; margin-bottom: 10px; }
                .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
                .info-box { border: 1px solid #ddd; padding: 15px; }
                .info-box h4 { margin-top: 0; color: #333; }
                .amount { font-weight: bold; color: #28a745; }
                .difference.negative { color: #dc3545; }
                .footer { text-align: center; margin-top: 40px; font-size: 12px; color: #666; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="logo">PharmaSys</div>
                <h2>Rapport de Caisse</h2>
                <p><strong>Caissier:</strong> ${register.cashier_name}</p>
                <p><strong>Date:</strong> ${new Date().toLocaleDateString('fr-FR')}</p>
                <p><strong>Heure d'impression:</strong> ${new Date().toLocaleTimeString('fr-FR')}</p>
            </div>
            
            <div class="info-grid">
                <div class="info-box">
                    <h4>Période d'activité</h4>
                    <p><strong>Ouverture:</strong> ${register.opening_time || 'N/A'}</p>
                    <p><strong>Fermeture:</strong> ${register.closing_time || 'En cours'}</p>
                    <p><strong>Durée:</strong> ${register.duration || 'En cours'}</p>
                </div>
                
                <div class="info-box">
                    <h4>Statistiques</h4>
                    <p><strong>Nombre de ventes:</strong> ${register.sales_count}</p>
                    <p><strong>Vente moyenne:</strong> ${register.sales_count > 0 ? formatNumber(register.total_sales / register.sales_count) : '0'} XAF</p>
                </div>
                
                <div class="info-box">
                    <h4>Montants</h4>
                    <p><strong>Fonds initial:</strong> <span class="amount">${formatNumber(register.initial_amount)} XAF</span></p>
                    <p><strong>Total des ventes:</strong> <span class="amount">${formatNumber(register.total_sales)} XAF</span></p>
                    <p><strong>Attendu:</strong> <span class="amount">${formatNumber(register.initial_amount + register.total_sales)} XAF</span></p>
                </div>
                
                ${register.status === 'closed' ? `
                    <div class="info-box">
                        <h4>Fermeture</h4>
                        <p><strong>Montant final:</strong> <span class="amount">${formatNumber(register.final_amount)} XAF</span></p>
                        <p><strong>Écart:</strong> 
                            <span class="difference ${register.difference < 0 ? 'negative' : ''}">
                                ${register.difference >= 0 ? '+' : ''}${formatNumber(register.difference)} XAF
                            </span>
                        </p>
                    </div>
                ` : ''}
            </div>
            
            <div class="footer">
                <p>Rapport généré automatiquement par PharmaSys</p>
                <p>© ${new Date().getFullYear()} - Tous droits réservés</p>
            </div>
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank', 'width=800,height=600');
    if (printWindow) {
        printWindow.document.write(printContent);
        printWindow.document.close();
        printWindow.focus();
        
        setTimeout(() => {
            printWindow.print();
        }, 500);
    } else {
        showNotification('Impossible d\'ouvrir la fenêtre d\'impression', 'danger');
    }
}

// Utilitaires
function formatNumber(num) {
    return new Intl.NumberFormat('fr-FR').format(Math.round(num));
}

function showNotification(message, type = 'info') {
    // Supprimer les notifications existantes
    document.querySelectorAll('.custom-notification').forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible position-fixed custom-notification`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; animation: slideInRight 0.3s ease;';
    
    const iconMap = {
        success: 'check-circle',
        danger: 'x-circle',
        warning: 'alert-triangle',
        info: 'info'
    };
    
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i data-lucide="${iconMap[type] || 'info'}" class="me-2"></i>
            <div class="flex-grow-1">${message}</div>
            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

function setupSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuToggle = document.getElementById('menuToggle');
    const sidebarClose = document.getElementById('sidebarClose');

    function showSidebar() {
        if (sidebar) sidebar.classList.add('show');
        if (overlay) overlay.classList.add('show');
    }

    function hideSidebar() {
        if (sidebar) sidebar.classList.remove('show');
        if (overlay) overlay.classList.remove('show');
    }

    if (menuToggle) menuToggle.addEventListener('click', showSidebar);
    if (sidebarClose) sidebarClose.addEventListener('click', hideSidebar);
    if (overlay) overlay.addEventListener('click', hideSidebar);
}

function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

function handleKeyboardShortcuts(e) {
    if (e.ctrlKey && e.key === 'n') {
        e.preventDefault();
        const openModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('openRegisterModal'));
        openModal.show();
    }
    
    if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        refreshData();
    }
    
    if (e.key === 'Escape') {
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) modalInstance.hide();
        });
    }
}

// Styles CSS pour les animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    .custom-notification {
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
`;
document.head.appendChild(style);

console.log('Cash Register JavaScript - Version corrigée chargée');