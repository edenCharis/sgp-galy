<!-- Sidebar Component -->
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
                    <div class="brand-subtitle">Gestion Pharmacie</div>
                </div>
            </a>
            <button id="sidebarClose" class="sidebar-close">
                <i data-lucide="x"></i>
            </button>
        </div>
    </div>

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
                    <a href="sales.php" class="sidebar-menu-link" data-page="new-sale">
                        <i data-lucide="shopping-cart" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Nouvelle vente</div>
                            <div class="menu-description">Créer une vente</div>
                        </div>
                        <div class="menu-badge info">Rapide</div>
                    </a>
                </div>
                <div class="sidebar-menu-item">
                    <a href="historique.php" class="sidebar-menu-link" data-page="sales-history">
                        <i data-lucide="history" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Historique des ventes</div>
                            <div class="menu-description">Toutes les ventes</div>
                        </div>
                    </a>
                </div>
                <div class="sidebar-menu-item">
                    <a href="products.php" class="sidebar-menu-link" data-page="products">
                        <i data-lucide="package" class="menu-icon"></i>
                        <div class="menu-content">
                            <div class="menu-title">Produits</div>
                            <div class="menu-description">Gestion stock</div>
                        </div>
                        <div class="menu-badge"><?php
                            require_once('../config/database.php');
                            $query = "SELECT COUNT(*) as count FROM product WHERE stock > 0";
                            $result = $db->fetch($query);
                           if (!$result) {
                                 echo '0';
                            } else {
                                 echo $result['count'];
                            }
                        ?></div>
                    </a>
                </div>
            </div>
        </div>

      

      
    
       
    </div>
</aside>