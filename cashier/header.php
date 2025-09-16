
<header class="header">
    <div class="header-content">
        <!-- Left Section -->
        <div class="header-left">
            <button id="menuToggle" class="menu-toggle">
                <i data-lucide="menu"></i>
            </button>
                     
            <div class="logo-section">
                <div class="logo">
                    <i data-lucide="cross"></i>
                </div>
                <div class="page-info">
                    <h1 id="pageTitle">Caisse</h1>
                    <p id="pageDescription">Traitement des ventes</p>
                </div>
            </div>
        </div>

        <!-- Center Status -->
        <div class="header-center">
           
        </div>
              
        <!-- Right Section -->
        <div class="header-right">
            <!-- Quick Actions -->
       

            <!-- Notifications -->
            <button class="header-btn" id="notificationsBtn" title="Notifications">
                <i data-lucide="bell"></i>
                <div class="notification-badge" id="notificationCount">
                    <?php
                    try {
                        // Count urgent notifications (low stock, pending carts, etc.)
                        $urgentCount = 0;
                        
                        // Pending carts count
                        $query = "SELECT COUNT(*) as count FROM carts WHERE status = 'pending'";
                        $result = $db->fetch($query);
                        $urgentCount += $result ? $result['count'] : 0;
                        
                        // Low stock products count
                        $query = "SELECT COUNT(*) as count FROM product WHERE stock <= 5 AND stock > 0";
                        $result = $db->fetch($query);
                        $urgentCount += $result ? $result['count'] : 0;
                        
                        echo $urgentCount > 0 ? $urgentCount : '';
                    } catch (Exception $e) {
                        echo '';
                    }
                    ?>
                </div>
            </button>

            <!-- Cash Register Status -->
            <button class="header-btn cash-register-btn" id="cashRegisterBtn" title="État caisse">
                <i data-lucide="calculator"></i>
                <span class="cash-status-text">
                    <?php
                    // Simple cash register status - you can expand this
                    echo date('H:i');
                    ?>
                </span>
            </button>

            <!-- Theme Toggle -->
            <button class="header-btn" id="themeToggle" title="Changer thème">
                <i data-lucide="sun"></i>
            </button>

            <!-- User Menu -->
            <button class="user-menu" id="userMenuToggle">
                <div class="avatar"><?php echo strtoupper($_SESSION["username"][0]);?></div>
                <div class="user-info">
                    <div class="name"><?php echo $_SESSION["username"];?></div>
                    <div class="role">CAISSIER</div>
                </div>
                <i data-lucide="chevron-down"></i>
            </button>

            <!-- User Dropdown Menu -->
            <div class="user-dropdown" id="userDropdown">
                <div class="user-dropdown-header">
                    <div class="avatar large"><?php echo strtoupper($_SESSION["username"][0]);?></div>
                    <div>
                        <div class="name"><?php echo $_SESSION["username"];?></div>
                        <div class="role">Caissier</div>
                        <div class="status">En service</div>
                    </div>
                </div>
                <div class="user-dropdown-menu">
                    <a href="profile.php" class="dropdown-item">
                        <i data-lucide="user"></i>
                        Mon profil
                    </a>
                    <a href="cash-count.php" class="dropdown-item">
                        <i data-lucide="calculator"></i>
                        Comptage caisse
                    </a>
                    <a href="daily-report.php" class="dropdown-item">
                        <i data-lucide="file-text"></i>
                        Rapport du jour
                    </a>
                    <div class="dropdown-separator"></div>
                    <a href="settings.php" class="dropdown-item">
                        <i data-lucide="settings"></i>
                        Paramètres
                    </a>
                    <a href="../logout.php" class="dropdown-item danger">
                        <i data-lucide="log-out"></i>
                        Déconnexion
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
/* Header Center Status */
.header-center {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    max-width: 500px;
}

.cashier-status {
    display: flex;
    gap: 2rem;
    background: rgba(255, 255, 255, 0.1);
    padding: 0.75rem 1.5rem;
    border-radius: 0.75rem;
    backdrop-filter: blur(10px);
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: rgba(255, 255, 255, 0.9);
}

.status-item.primary {
    color: #10b981;
}

.status-item i {
    width: 1.25rem;
    height: 1.25rem;
}

.status-content {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.status-value {
    font-size: 1.125rem;
    font-weight: 700;
    line-height: 1;
}

.status-label {
    font-size: 0.75rem;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.025em;
}

/* Header Actions */
.header-actions {
    display: flex;
    gap: 0.5rem;
    margin-right: 1rem;
}

.header-action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.5rem;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.header-action-btn.primary {
    background: #059669;
    color: white;
}

.header-action-btn.primary:hover {
    background: #047857;
    transform: translateY(-1px);
}

.header-action-btn.success {
    background: #10b981;
    color: white;
}

.header-action-btn.success:hover {
    background: #059669;
    transform: translateY(-1px);
}

.action-text {
    display: none;
}

/* Cash Register Button */
.cash-register-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    padding: 0.5rem !important;
    min-width: 60px;
}

.cash-status-text {
    font-size: 0.75rem;
    font-weight: 500;
}

/* User Dropdown */
.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    background: white;
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    min-width: 280px;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.2s;
    z-index: 1000;
}

.user-dropdown.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.user-dropdown-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border-bottom: 1px solid #f3f4f6;
}

.user-dropdown-header .avatar.large {
    width: 3rem;
    height: 3rem;
    font-size: 1.25rem;
}

.user-dropdown-header .name {
    font-weight: 600;
    color: #111827;
}

.user-dropdown-header .role {
    font-size: 0.875rem;
    color: #6b7280;
}

.user-dropdown-header .status {
    font-size: 0.75rem;
    color: #10b981;
    font-weight: 500;
}

.user-dropdown-menu {
    padding: 0.5rem;
}

.dropdown-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: #374151;
    text-decoration: none;
    border-radius: 0.5rem;
    transition: all 0.2s;
}

.dropdown-item:hover {
    background: #f9fafb;
    color: #059669;
}

.dropdown-item.danger {
    color: #dc2626;
}

.dropdown-item.danger:hover {
    background: #fef2f2;
    color: #dc2626;
}

.dropdown-separator {
    height: 1px;
    background: #e5e7eb;
    margin: 0.5rem 0;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .header-center {
        display: none;
    }
    
    .header-actions {
        margin-right: 0.5rem;
    }
    
    .action-text {
        display: none;
    }
}

@media (max-width: 768px) {
    .header-actions {
        display: none;
    }
    
    .cashier-status {
        gap: 1rem;
        padding: 0.5rem 1rem;
    }
    
    .status-value {
        font-size: 1rem;
    }
    
    .status-label {
        font-size: 0.625rem;
    }
}

@media (min-width: 1200px) {
    .action-text {
        display: block;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // User menu dropdown
    const userMenuToggle = document.getElementById('userMenuToggle');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuToggle && userDropdown) {
        userMenuToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            userDropdown.classList.remove('show');
        });
        
        userDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Auto-refresh status every 30 seconds
    setInterval(function() {
        refreshCashierStatus();
    }, 30000);
});

function refreshCashierStatus() {
    // You can implement AJAX calls here to refresh the status counts
    // This is a placeholder for future enhancement
    fetch('get-cashier-status.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('pendingCartsCount').textContent = data.pendingCarts;
                document.getElementById('completedSalesToday').textContent = data.completedSales;
                document.getElementById('todayTotal').textContent = data.todayTotal + '€';
                
                const notificationCount = document.getElementById('notificationCount');
                if (data.notifications > 0) {
                    notificationCount.textContent = data.notifications;
                    notificationCount.style.display = 'block';
                } else {
                    notificationCount.style.display = 'none';
                }
            }
        })
        .catch(error => console.log('Status refresh failed:', error));
}
</script>