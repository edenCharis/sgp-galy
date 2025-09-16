<!-- Cashier Sidebar Component -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>
<aside id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <div class="flex items-center justify-between">
            <a href="/" class="sidebar-brand">
                <div class="brand-logo">
                    <i data-lucide="cross"></i>
                </div>
                <div>
                    <div class="brand-text">PharmaSys</div>
                    <div class="brand-subtitle">Caisse</div>
                </div>
            </a>
            <button id="sidebarClose" class="sidebar-close">
                <i data-lucide="x"></i>
            </button>
        </div>
    </div>

    <!-- Cash Register Status Section -->
  
<?php 

 $cashierId = $_SESSION["user_id"];


?>

    <div class="sidebar-content">
        <!-- Main Navigation -->
        <div class="sidebar-group">
            <div class="sidebar-menu">
                <div class="sidebar-menu-item">
                    <a href="index.php" class="sidebar-menu-link active" data-page="dashboard">
                        <i data-lucide="home" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Tableau de bord</div>
                            <div class="menu-description">Vue d'ensemble</div>
                        </div>
                    </a>
                </div>
                
                <div class="sidebar-menu-item">
                    <a href="pending-carts.php" class="sidebar-menu-link" data-page="pending-carts">
                        <i data-lucide="shopping-cart" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Paniers en attente</div>
                            <div class="menu-description">Finaliser les ventes</div>
                        </div>
                        <div class="menu-badge warning"><?php
                            try {
                                // Get pending carts for this cashier's open register
                                $pendingQuery = "SELECT COUNT(c.id) as count 
                                               FROM carts c 
                                               JOIN cash_register cr ON c.cash_register_id = cr.id 
                                               WHERE c.status = 'PENDING' 
                                               AND cr.cashier_id = ? 
                                               AND cr.status = 'OPEN'";
                                $result = $db->fetch($pendingQuery, [$cashierId]);
                                echo $result ? $result['count'] : '0';
                            } catch (Exception $e) {
                                echo '0';
                            }
                        ?></div>
                    </a>
                </div>

                <div class="sidebar-menu-item">
                    <a href="process-payment.php" class="sidebar-menu-link" data-page="payment">
                        <i data-lucide="credit-card" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Traitement paiement</div>
                            <div class="menu-description">Encaisser</div>
                        </div>
                        <div class="menu-badge success">Rapide</div>
                    </a>
                </div>

                <div class="sidebar-menu-item">
                    <a href="completed-sales.php" class="sidebar-menu-link" data-page="completed-sales">
                        <i data-lucide="check-circle" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Ventes terminées</div>
                            <div class="menu-description">Historique du jour</div>
                        </div>
                        <div class="menu-badge"><?php
                            try {
                                $salesQuery = "SELECT COUNT(s.id) as count 
                                             FROM sale s 
                                             JOIN cash_register cr ON s.cash_register_id = cr.id 
                                             WHERE DATE(s.createdAt) = CURDATE() 
                                             AND cr.cashier_id = ?";
                                $result = $db->fetch($salesQuery, [$cashierId]);
                                echo $result ? $result['count'] : '0';
                            } catch (Exception $e) {
                                echo '0';
                            }
                        ?></div>
                    </a>
                </div>
            </div>
        </div>

        <div class="sidebar-separator"></div>

        <!-- Settings -->
        <div class="sidebar-group">
            <div class="sidebar-menu">
                <div class="sidebar-menu-item">
                    <a href="../logout.php" class="sidebar-menu-link" data-page="logout">
                        <i data-lucide="log-out" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Déconnexion</div>
                            <div class="menu-description">Fermer session</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</aside>

<style>
/* Cash Register Status Styles */
.cash-register-status {
    margin: 1rem;
    margin-bottom: 0;
}

.cash-status {
    border-radius: 0.75rem;
    padding: 1.25rem;
    text-align: center;
    border: 2px solid;
    transition: all 0.3s ease;
}

.cash-status.open {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05));
    border-color: #10b981;
    color: #065f46;
}

.cash-status.closed {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05));
    border-color: #ef4444;
    color: #7f1d1d;
}

.cash-status.not-opened {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(245, 158, 11, 0.05));
    border-color: #f59e0b;
    color: #78350f;
}

.cash-status.error {
    background: linear-gradient(135deg, rgba(107, 114, 128, 0.1), rgba(107, 114, 128, 0.05));
    border-color: #6b7280;
    color: #374151;
}

.cash-status-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.cash-amount {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
}

.cash-details {
    margin-bottom: 1rem;
    font-size: 0.875rem;
}

.cash-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.25rem;
}

.cash-label {
    font-weight: 500;
    opacity: 0.8;
}

.cash-value {
    font-weight: 600;
}

.cash-message {
    font-size: 0.875rem;
    margin-bottom: 1rem;
    opacity: 0.9;
    line-height: 1.4;
}

.cash-actions {
    margin-top: 1rem;
}

.btn-open-cash, .btn-close-cash {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    width: 100%;
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.2s ease;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

.btn-open-cash {
    background: #10b981;
    color: white;
}

.btn-open-cash:hover {
    background: #059669;
    transform: translateY(-1px);
}

.btn-close-cash {
    background: #ef4444;
    color: white;
}

.btn-close-cash:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Existing styles */
.sidebar-footer {
    margin-top: auto;
    padding: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.menu-badge.warning {
    background: #f59e0b;
    color: white;
}

.menu-badge.success {
    background: #10b981;
    color: white;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .cash-status {
        margin: 0.5rem;
        padding: 1rem;
    }
    
    .cash-amount {
        font-size: 1.5rem;
    }
    
    .cash-status-header {
        font-size: 0.8rem;
    }
}
</style>

<script>
function openCashRegister() {
    // Show modal to enter initial amount
    const modalHTML = `
        <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
            <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:400px;">
                <h3 style="margin-bottom:20px; text-align:center;">Ouvrir la Caisse</h3>
                <form onsubmit="return submitOpenCash(event)">
                    <div style="margin-bottom:15px;">
                        <label for="initialAmount" style="display:block; margin-bottom:5px; font-weight:600;">Montant initial:</label>
                        <input type="number" id="initialAmount" required min="0" step="0.01" 
                               style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px;" 
                               placeholder="0.00">
                    </div>
                    <div style="display:flex; justify-content:space-between; gap:10px;">
                        <button type="button" onclick="this.closest('.modal').remove()" 
                                style="flex:1; padding:10px; background:#6b7280; color:white; border:none; border-radius:4px; cursor:pointer;">
                            Annuler
                        </button>
                        <button type="submit" 
                                style="flex:1; padding:10px; background:#10b981; color:white; border:none; border-radius:4px; cursor:pointer;">
                            Ouvrir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    document.getElementById('initialAmount').focus();
}

function closeCashRegister() {
    if (confirm('Êtes-vous sûr de vouloir fermer la caisse? Cette action est irréversible.')) {
        // Send request to close cash register
        fetch('close-cash-register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erreur lors de la fermeture: ' + (data.message || 'Erreur inconnue'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors de la fermeture de la caisse');
        });
    }
}

function submitOpenCash(event) {
    event.preventDefault();
    const initialAmount = parseFloat(document.getElementById('initialAmount').value);
    
    if (isNaN(initialAmount) || initialAmount < 0) {
        alert('Veuillez entrer un montant valide');
        return false;
    }
    
    // Send request to open cash register
    fetch('open-cash-register.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            initialAmount: initialAmount
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de l\'ouverture: ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de l\'ouverture de la caisse');
    });
    
    // Close modal
    event.target.closest('.modal').remove();
    return false;
}
</script>