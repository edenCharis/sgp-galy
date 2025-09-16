<?php
session_start();
if($_SESSION["role"] === "CASHIER" && $_SESSION["id"] == session_id()){

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include database connection
    include '../config/database.php';
    
    // Check if database connection exists
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    // Get cashier ID
    $cashierId = $_SESSION["user_id"];
    
    // Handle search and filters
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sellerFilter = isset($_GET['seller']) ? trim($_GET['seller']) : '';
    $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : '';
    
    // Build the WHERE clause
    $whereConditions = ["c.status = 'pending'", "cr.cashier_id = ?"];
    $queryParams = [$cashierId];
    
    if (!empty($searchTerm)) {
        $whereConditions[] = "(cl.name LIKE ? OR sel.username LIKE ? OR c.id LIKE ?)";
        $searchParam = "%{$searchTerm}%";
        $queryParams[] = $searchParam;
        $queryParams[] = $searchParam;
        $queryParams[] = $searchParam;
    }
    
    if (!empty($sellerFilter)) {
        $whereConditions[] = "sel.username LIKE ?";
        $queryParams[] = "%{$sellerFilter}%";
    }
    
    if (!empty($dateFilter)) {
        $whereConditions[] = "DATE(c.created_at) = ?";
        $queryParams[] = $dateFilter;
    }
    
    $whereClause = implode(" AND ", $whereConditions);

    // Get all pending carts with details
    $pendingCartsQuery = "SELECT c.*, 
                                 sel.username as sellerName,
                                 cl.name as clientName,
                                 cl.contact as clientPhone,
                                 COUNT(ci.id) as itemCount,
                                 SUM(ci.quantity * ci.unit_price) as totalAmount
                          FROM carts c
                          JOIN cash_register cr ON c.cash_register_id = cr.id
                          LEFT JOIN user sel ON c.seller_id = sel.id
                          LEFT JOIN client cl ON c.client_id = cl.id
                          LEFT JOIN cart_items ci ON c.id = ci.cart_id
                          WHERE {$whereClause}
                          GROUP BY c.id
                          ORDER BY c.created_at ASC";
                          
    $pendingCarts = $db->fetchAll($pendingCartsQuery, $queryParams);
    if (!$pendingCarts) $pendingCarts = [];

    // Get sellers for filter dropdown
    $sellersQuery = "SELECT DISTINCT u.username 
                     FROM carts c
                     JOIN cash_register cr ON c.cash_register_id = cr.id
                     LEFT JOIN user u ON c.seller_id = u.id
                     WHERE c.status = 'pending' AND cr.cashier_id = ?
                     ORDER BY u.username";
    $sellers = $db->fetchAll($sellersQuery, [$cashierId]);
    if (!$sellers) $sellers = [];

    // Statistics
    $totalPendingCarts = count($pendingCarts);
    $totalPendingAmount = array_sum(array_column($pendingCarts, 'totalAmount'));

} catch (Exception $e) {
    error_log('Pending carts error: ' . $e->getMessage());
    die('Error loading pending carts: ' . $e->getMessage() . '<br><br><a href="../logout.php">Logout</a>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Paniers en attente</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/seller-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

    <style>
        .page-header {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .page-title {
            color: #059669;
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-subtitle {
            color: #6b7280;
            margin: 0.5rem 0 0 0;
            font-size: 1.125rem;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }

        .stat-badge {
            background: #f0f9ff;
            border: 1px solid #0ea5e9;
            color: #0c4a6e;
            padding: 0.75rem 1.25rem;
            border-radius: 0.75rem;
            text-align: center;
        }

        .stat-badge.pending {
            background: #fef3c7;
            border-color: #f59e0b;
            color: #92400e;
        }

        .stat-badge.amount {
            background: #ecfdf5;
            border-color: #10b981;
            color: #065f46;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 500;
        }

        .filters-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-input {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: #059669;
            color: white;
        }

        .btn-primary:hover {
            background: #047857;
            color: white;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .carts-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            color: #059669;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cart-grid {
            display: grid;
            gap: 1px;
            background: #f3f4f6;
        }

        .cart-card {
            background: white;
            padding: 1.5rem;
            transition: all 0.2s;
            border-left: 4px solid #e5e7eb;
        }

        .cart-card:hover {
            background: #f9fafb;
            border-left-color: #059669;
            transform: translateX(2px);
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .cart-info {
            flex: 1;
        }

        .cart-id {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            color: #6b7280;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .cart-seller {
            font-weight: 600;
            color: #111827;
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
        }

        .cart-client {
            color: #059669;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .cart-phone {
            color: #6b7280;
            font-size: 0.875rem;
            font-family: 'Courier New', monospace;
        }

        .cart-meta {
            text-align: right;
        }

        .cart-time {
            background: #e0f2fe;
            color: #0277bd;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .cart-amount {
            font-size: 1.25rem;
            font-weight: 700;
            color: #059669;
        }

        .cart-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .cart-items {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .cart-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-process {
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.2s;
        }

        .btn-process:hover {
            background: #059669;
            color: white;
            transform: translateY(-1px);
        }

        .btn-view {
            background: #3b82f6;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #2563eb;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .empty-state i {
            width: 4rem;
            height: 4rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .refresh-button {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #059669;
            color: white;
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .refresh-button:hover {
            background: #047857;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.4);
        }

        @media (max-width: 1024px) {
            .filters-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .cart-header {
                flex-direction: column;
                gap: 1rem;
            }

            .cart-meta {
                text-align: left;
            }

            .cart-details {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include 'header.php'; ?>
            
            <!-- Content Area -->
            <main class="content-area">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">
                        <i data-lucide="shopping-cart"></i>
                        Paniers en attente
                    </h1>
                    <p class="page-subtitle">Traitement des paniers clients en attente de paiement</p>
                    
                    <!-- Statistics -->
                    <div class="stats-row">
                        <div class="stat-badge pending">
                            <div class="stat-value"><?php echo $totalPendingCarts; ?></div>
                            <div class="stat-label">Paniers en attente</div>
                        </div>
                        <div class="stat-badge amount">
                            <div class="stat-value"><?php echo number_format($totalPendingAmount, 0); ?> XAF</div>
                            <div class="stat-label">Montant total</div>
                        </div>
                        <div class="stat-badge">
                            <div class="stat-value">
                                <?php echo $totalPendingCarts > 0 ? number_format($totalPendingAmount / $totalPendingCarts, 0) : 0; ?> XAF
                            </div>
                            <div class="stat-label">Panier moyen</div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
              

                <!-- Carts Section -->
                <div class="carts-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i data-lucide="list"></i>
                            Liste des paniers
                            <?php if ($totalPendingCarts > 0): ?>
                                <span style="color: #6b7280; font-weight: normal; font-size: 0.875rem;">
                                    (<?php echo $totalPendingCarts; ?> trouvé<?php echo $totalPendingCarts > 1 ? 's' : ''; ?>)
                                </span>
                            <?php endif; ?>
                        </h2>
                        
                        <button type="button" class="btn btn-secondary" onclick="location.reload()">
                            <i data-lucide="refresh-cw"></i>
                            Actualiser
                        </button>
                    </div>
                    
                    <div class="cart-grid">
                        <?php if (empty($pendingCarts)): ?>
                            <div class="empty-state">
                                <i data-lucide="shopping-cart"></i>
                                <h3>Aucun panier trouvé</h3>
                                <?php if (!empty($searchTerm) || !empty($sellerFilter) || !empty($dateFilter)): ?>
                                    <p>Aucun panier ne correspond aux critères de recherche</p>
                                    <a href="pending-carts.php" class="btn btn-primary" style="margin-top: 1rem;">
                                        Voir tous les paniers
                                    </a>
                                <?php else: ?>
                                    <p>Tous les paniers ont été traités!</p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingCarts as $cart): ?>
                                <div class="cart-card">
                                    <div class="cart-header">
                                        <div class="cart-info">
                                            <div class="cart-id">Panier #<?php echo $cart['id']; ?></div>
                                            <div class="cart-seller"><?php echo htmlspecialchars($cart['sellerName'] ?? 'Vendeur inconnu'); ?></div>
                                            <div class="cart-client">
                                                <?php echo htmlspecialchars($cart['clientName'] ?? 'Client anonyme'); ?>
                                            </div>
                                            <?php if (!empty($cart['clientPhone'])): ?>
                                                <div class="cart-phone"><?php echo htmlspecialchars($cart['clientPhone']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="cart-meta">
                                            <div class="cart-time">
                                                <?php 
                                                $createdTime = new DateTime($cart['created_at']);
                                                $now = new DateTime();
                                                $diff = $now->diff($createdTime);
                                                
                                                if ($diff->h > 0) {
                                                    echo "Il y a {$diff->h}h {$diff->i}min";
                                                } elseif ($diff->i > 0) {
                                                    echo "Il y a {$diff->i}min";
                                                } else {
                                                    echo "À l'instant";
                                                }
                                                ?>
                                            </div>
                                            <div class="cart-amount"><?php echo number_format($cart['totalAmount'] ?: 0, 0); ?> XAF</div>
                                        </div>
                                    </div>
                                    
                                    <div class="cart-details">
                                        <div class="cart-items">
                                            <i data-lucide="package"></i>
                                            <?php echo $cart['itemCount']; ?> article<?php echo $cart['itemCount'] > 1 ? 's' : ''; ?>
                                        </div>
                                        
                                        <div class="cart-actions">
                                           
                                            <a href="process-payment.php?cartId=<?php echo $cart['id']; ?>" class="btn-process">
                                                <i data-lucide="credit-card"></i>
                                                Traiter
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Refresh Button -->
    <button class="refresh-button" onclick="location.reload()" title="Actualiser">
        <i data-lucide="refresh-cw"></i>
    </button>

    <script>
        // Set favicon
        function setFavicon() {
            const svgData = `
                <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg">
                    <path d="M60 20 L140 20 L140 60 L180 60 L180 140 L140 140 L140 180 L60 180 L60 140 L20 140 L20 60 L60 60 Z" fill="#059669"/>
                    <path d="M75 35 L125 35 L125 75 L165 75 L165 125 L125 125 L125 165 L75 165 L75 125 L35 125 L35 75 L75 75 Z" fill="white"/>
                    <g fill="#059669">
                        <rect x="97" y="50" width="6" height="100"/>
                        <rect x="50" y="97" width="100" height="6"/>
                    </g>
                </svg>
            `;
            
            const favicon = `data:image/svg+xml;base64,${btoa(svgData)}`;
            
            const existingFavicon = document.querySelector('link[rel="icon"]') || document.querySelector('link[rel="shortcut icon"]');
            if (existingFavicon) {
                existingFavicon.remove();
            }
            
            const link = document.createElement('link');
            link.rel = 'icon';
            link.type = 'image/svg+xml';
            link.href = favicon;
            document.head.appendChild(link);
        }

        // Sidebar functionality
        function setupSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            const sidebarClose = document.getElementById('sidebarClose');

            function showSidebar() {
                sidebar.classList.add('show');
                overlay.classList.add('show');
            }

            function hideSidebar() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
            }

            if (menuToggle) menuToggle.addEventListener('click', showSidebar);
            if (sidebarClose) sidebarClose.addEventListener('click', hideSidebar);
            if (overlay) overlay.addEventListener('click', hideSidebar);
        }

        // Auto refresh every 2 minutes
        function setupAutoRefresh() {
            setInterval(() => {
                // Only auto-refresh if no filters are applied to avoid losing search state
                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.has('search') && !urlParams.has('seller') && !urlParams.has('date')) {
                    location.reload();
                }
            }, 120000); // 2 minutes
        }

        // Initialize app
        function initApp() {
            setFavicon();
            setupSidebar();
            setupAutoRefresh();
            
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Start the app when DOM is loaded
        document.addEventListener('DOMContentLoaded', initApp);

        // Update page title dynamically
        document.addEventListener('DOMContentLoaded', function() {
            const pageTitle = document.getElementById('pageTitle');
            const pageDescription = document.getElementById('pageDescription');
            
            if (pageTitle) pageTitle.textContent = 'Paniers en attente';
            if (pageDescription) pageDescription.textContent = 'Gestion des paniers clients';
        });

        // Add subtle animation to cart cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cartCards = document.querySelectorAll('.cart-card');
            cartCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.3s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>