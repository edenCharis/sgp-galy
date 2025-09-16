<!-- Admin Sidebar Component -->
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
                    <div class="brand-subtitle">ADMIN</div>
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

                <!-- NEW: Cash Register Management -->
                <div class="sidebar-menu-item">
                    <a href="cash-register.php" class="sidebar-menu-link" data-page="cash-register">
                        <i data-lucide="calculator" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Gestion Caisses</div>
                            <div class="menu-description">Caisses enregistreuses</div>
                        </div>
                        <div class="menu-badge info">Caisse</div>
                    </a>
                </div>
                
                <div class="sidebar-menu-item">
                    <a href="users.php" class="sidebar-menu-link" data-page="users">
                        <i data-lucide="users" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Utilisateurs</div>
                            <div class="menu-description">Gestion utilisateurs</div>    
                        </div>
                        <div class="menu-badge warning">Admin</div>
                    </a>
                </div>

                <div class="sidebar-menu-item">
                    <a href="suppliers.php" class="sidebar-menu-link" data-page="suppliers">
                        <i data-lucide="truck" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Fournisseurs</div>
                            <div class="menu-description">Gestion des fournisseurs</div>        
                        </div>
                    </a>    
                </div>

                <div class="sidebar-menu-item">
                    <a href="products.php" class="sidebar-menu-link" data-page="products">
                        <i data-lucide="package" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Produits</div>
                            <div class="menu-description">Gestion des produits</div>        
                        </div>  
                   </a>
                </div>

                <div class="sidebar-menu-item">
                    <a href="stock-deliveries.php" class="sidebar-menu-link" data-page="deliveries">
                        <i data-lucide="package-open" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Livraisons</div>
                            <div class="menu-description">Gestion des livraisons</div>        
                        </div>
                        <div class="menu-badge success">Stock</div>
                    </a>
                </div>

                <div class="sidebar-menu-item">
                    <a href="logs.php" class="sidebar-menu-link" data-page="logs">
                        <i data-lucide="file-text" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Logs</div>
                            <div class="menu-description">Activité système</div>        
                        </div>
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
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-weight: 600;
}

.menu-badge.success {
    background: #10b981;
    color: white;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-weight: 600;
}

/* NEW: Info badge style for cash register */
.menu-badge.info {
    background: #3b82f6;
    color: white;
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 0.375rem;
    font-weight: 600;
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