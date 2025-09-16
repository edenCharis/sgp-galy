
     
<?php
session_start();
if($_SESSION["role"] === "SELLER" && $_SESSION["id"] == session_id()){

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

    $seller_id = $_SESSION['user_id'];

    // Get today's date
    $today = date('Y-m-d');
    $thisWeek = date('Y-m-d', strtotime('-7 days'));

    // KPI 1: Today's Sales Revenue - FIXED table name and date format
    $todayStart = $today . ' 00:00:00';
    $todayEnd = $today . ' 23:59:59';

    // Fixed: Use correct table name 'carts' (not 'cart') and proper date comparison
    $todaySalesSQL = "SELECT 
                          COALESCE(SUM(ci.quantity * ci.unit_price), 0) as today_revenue,
                          COUNT(DISTINCT c.id) as today_orders
                       FROM carts c
                       LEFT JOIN cart_items ci ON c.id = ci.cart_id
                       WHERE c.created_at >= ? AND c.created_at <= ?
                         AND c.status = 'completed' 
                         AND c.seller_id = ?";

    $todaySalesResult = $db->fetch($todaySalesSQL, [$todayStart, $todayEnd, $seller_id]);

    $todayRevenue = $todaySalesResult ? $todaySalesResult['today_revenue'] : 0;
    $todayOrders = $todaySalesResult ? $todaySalesResult['today_orders'] : 0;

    // KPI 2: Products in stock - FIXED to filter by seller_id
    $stockSQL = "SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as in_stock,
                    SUM(CASE WHEN stock > 0 AND stock <= 10 THEN 1 ELSE 0 END) as low_stock,
                    SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock
                 FROM product";
                 
    $stockResult = $db->fetch($stockSQL); // Fixed: use fetch instead of fetchAll
    $stockData = $stockResult ? $stockResult : [
        'total_products' => 0,
        'in_stock' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0
    ];

    // KPI 3: Unique customers served today - FIXED table name
    $customersSQL = "SELECT COUNT(DISTINCT client_id) as customers_served
                     FROM carts 
                     WHERE DATE(created_at) = ? AND status = 'completed' AND seller_id = ?";
    $customersResult = $db->fetch($customersSQL, [$today, $seller_id]); // Fixed: use fetch
    $customersServed = $customersResult ? $customersResult['customers_served'] : 0;

    // Calculate growth percentages (comparing with yesterday)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    // Yesterday's revenue - FIXED table name and query structure
    $yesterdayStart = $yesterday . ' 00:00:00';
    $yesterdayEnd = $yesterday . ' 23:59:59';
    
    $yesterdayRevenueSQL = "SELECT COALESCE(SUM(ci.quantity * ci.unit_price), 0) as yesterday_revenue
                            FROM carts c
                            LEFT JOIN cart_items ci ON c.id = ci.cart_id
                            WHERE c.created_at >= ? AND c.created_at <= ?
                              AND c.status = 'completed' 
                              AND c.seller_id = ?";
    $yesterdayResult = $db->fetch($yesterdayRevenueSQL, [$yesterdayStart, $yesterdayEnd, $seller_id]);
    $yesterdayRevenue = $yesterdayResult ? $yesterdayResult['yesterday_revenue'] : 0;
    
    $revenueGrowth = 0;
    if ($yesterdayRevenue > 0) {
        $revenueGrowth = (($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100;
    } elseif ($todayRevenue > 0) {
        $revenueGrowth = 100; // 100% growth if we had sales today but none yesterday
    }

    // Yesterday's orders - FIXED table name
    $yesterdayOrdersSQL = "SELECT COUNT(*) as yesterday_orders
                           FROM carts  c
                           WHERE c.created_at >= ? AND c.created_at <= ?
                             AND status = 'completed' 
                             AND seller_id = ?";
    $yesterdayOrdersResult = $db->fetch($yesterdayOrdersSQL, [$yesterdayStart, $yesterdayEnd, $seller_id]);
    $yesterdayOrders = $yesterdayOrdersResult ? $yesterdayOrdersResult['yesterday_orders'] : 0;
    
    $ordersGrowth = 0;
    if ($yesterdayOrders > 0) {
        $ordersGrowth = (($todayOrders - $yesterdayOrders) / $yesterdayOrders) * 100;
    } elseif ($todayOrders > 0) {
        $ordersGrowth = 100; // 100% growth if we had orders today but none yesterday
    }

    // Get recent transactions - FIXED table relationships
    $recentTransactionsSQL = "SELECT c.id, c.name as cart_name, c.created_at,
                                     cl.name as client_name,
                                     COUNT(ci.id) as product_count,
                                     COALESCE(SUM(ci.quantity * ci.unit_price), 0) as total_amount,
                                     c.status
                              FROM carts c
                              LEFT JOIN client cl ON c.client_id = cl.id
                              LEFT JOIN cart_items ci ON c.id = ci.cart_id
                              WHERE c.status = 'completed' AND c.seller_id = ?
                              GROUP BY c.id, c.name, c.created_at, cl.name, c.status
                              ORDER BY c.created_at DESC
                              LIMIT 5";
    $recentTransactions = $db->fetchAll($recentTransactionsSQL, [$seller_id]);
    if ($recentTransactions === false) {
        $recentTransactions = [];
    }

    // Get low stock products for alerts - FIXED to filter by seller
    $lowStockProductsSQL = "SELECT name, stock
                            FROM product
                            WHERE stock > 0 AND stock <= 10
                            ORDER BY stock ASC
                            LIMIT 5";
    $lowStockProducts = $db->fetchAll($lowStockProductsSQL,);
    if ($lowStockProducts === false) {
        $lowStockProducts = [];
    }

    // Get weekly sales data for chart
    $weeklySalesSQL = "SELECT DATE(c.created_at) as sale_date,
                              COALESCE(SUM(ci.quantity * ci.unit_price), 0) as daily_revenue
                       FROM carts c
                       LEFT JOIN cart_items ci ON c.id = ci.cart_id
                       WHERE DATE(c.created_at) >= ? 
                         AND c.status = 'completed' 
                         AND c.seller_id = ?
                       GROUP BY DATE(c.created_at)
                       ORDER BY DATE(c.created_at) ASC";
    $weeklySales = $db->fetchAll($weeklySalesSQL, [$thisWeek, $seller_id]);
    if ($weeklySales === false) {
        $weeklySales = [];
    }

    // Prepare chart data
    $chartData = [];
    $chartLabels = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayName = date('D', strtotime("-$i days"));
        
        $dayRevenue = 0;
        foreach ($weeklySales as $sale) {
            if ($sale['sale_date'] === $date) {
                $dayRevenue = $sale['daily_revenue'];
                break;
            }
        }
        
        $chartData[] = floatval($dayRevenue);
        $chartLabels[] = $dayName;
    }

    // Debug information - Remove this in production
    error_log("Debug Info:");
    error_log("Seller ID: " . $seller_id);
    error_log("Today Revenue: " . $todayRevenue);
    error_log("Today Orders: " . $todayOrders);
    error_log("Stock Data: " . print_r($stockData, true));
    error_log("Recent Transactions Count: " . count($recentTransactions));

} catch (Exception $e) {
   echo $e->getMessage();
}

// Helper functions
function formatCurrency($amount) {
    return number_format($amount, 0) . ' XAF';
}

function formatPercentage($percentage) {
    return ($percentage >= 0 ? '+' : '') . number_format($percentage, 1) . '%';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Il y a quelques secondes';
    if ($time < 3600) return 'Il y a ' . floor($time/60) . ' min';
    if ($time < 86400) return 'Il y a ' . floor($time/3600) . 'h ' . floor(($time%3600)/60) . 'min';
    return 'Il y a ' . floor($time/86400) . ' jour(s)';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Tableau de bord</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
     <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
        <link rel="stylesheet" href="../assets/css/seller-dashboard.css">
         <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <!-- Development version -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>

</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
      <?php
       include 'sidebar.php';
      ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
          <?php 
              include 'header.php';
          ?>
            <!-- Content Area -->
            <main class="content-area">
                <div class="dashboard-grid">
                    <!-- Quick Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card primary">
                            <div class="stat-icon">
                                <i data-lucide="dollar-sign"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo formatCurrency($todayRevenue); ?></div>
                                <div class="stat-label">Ventes aujourd'hui</div>
                                <div class="stat-change <?php echo $revenueGrowth >= 0 ? 'positive' : 'negative'; ?>">
                                    <i data-lucide="<?php echo $revenueGrowth >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                                    <?php echo formatPercentage($revenueGrowth); ?>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i data-lucide="shopping-cart"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $todayOrders; ?></div>
                                <div class="stat-label">Panier aujourd'hui</div>
                                <div class="stat-change <?php echo $ordersGrowth >= 0 ? 'positive' : 'negative'; ?>">
                                    <i data-lucide="<?php echo $ordersGrowth >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                                    <?php echo formatPercentage($ordersGrowth); ?>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card warning">
                            <div class="stat-icon">
                                <i data-lucide="package"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $stockData['in_stock']; ?></div>
                                <div class="stat-label">Produits en stock</div>
                                <div class="stat-change negative">
                                    <i data-lucide="alert-triangle"></i>
                                    <?php echo $stockData['out_of_stock']; ?> en rupture
                                </div>
                            </div>
                        </div>

                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i data-lucide="users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $customersServed; ?></div>
                                <div class="stat-label">Clients servis</div>
                                <div class="stat-change positive">
                                    <i data-lucide="users"></i>
                                    Aujourd'hui
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Dashboard Content -->
                    <div class="dashboard-main">
                        <!-- Recent Sales Chart -->
                      
                        <!-- Recent Transactions -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title text-white">
                                    <i data-lucide="clock"></i>
                                    Dernières transactions
                                </div>
                                <a href="historique.php" class="text-link text-white">
                                    Voir tout
                                    <i data-lucide="arrow-right"></i>
                                </a>
                            </div>
                            <div class="card-content">
                                <div class="transaction-list">
                                    <?php if (empty($recentTransactions)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i data-lucide="shopping-cart" class="mb-2" style="width: 2rem; height: 2rem;"></i>
                                            <p>Aucune transaction récente</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentTransactions as $transaction): ?>
                                            <div class="transaction-item">
                                                <div class="transaction-info">
                                                    <div class="transaction-id">#<?php echo htmlspecialchars($transaction['id']); ?></div>
                                                    <div class="transaction-details">
                                                        <span class="customer">
                                                            <?php echo htmlspecialchars($transaction['client_name'] ?: 'Client anonyme'); ?>
                                                        </span>
                                                        <span class="time"><?php echo timeAgo($transaction['created_at']); ?></span>
                                                    </div>
                                                </div>
                                                <div class="transaction-products">
                                                    <span class="product-count">
                                                        <?php echo $transaction['product_count']; ?> produit<?php echo $transaction['product_count'] > 1 ? 's' : ''; ?>
                                                    </span>
                                                </div>
                                                <div class="transaction-amount">
                                                    <span class="amount"><?php echo formatCurrency($transaction['total_amount']); ?></span>
                                                    <span class="status completed">Terminé</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Info -->
                    <div class="dashboard-sidebar">
                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title text-white">
                                    <i data-lucide="zap"></i>
                                    Actions rapides
                                </div>
                            </div>
                            <div class="card-content">
                                <div class="quick-actions">
                                    <a href="sales.php" class="quick-action-btn primary">
                                        <i data-lucide="plus"></i>
                                        Nouvelle vente
                                    </a>
                                    <a href="products.php" class="quick-action-btn secondary">
                                        <i data-lucide="search"></i>
                                        Rechercher produit
                                    </a>
                                    <button class="quick-action-btn secondary" onclick="alert('Fonctionnalité scanner à venir')">
                                        <i data-lucide="scan-line"></i>
                                        Scanner code-barres
                                    </button>
                                </div>
                            </div>
                        </div>

                   
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Chart data from PHP
        const chartData = <?php echo json_encode($chartData); ?>;
        const chartLabels = <?php echo json_encode($chartLabels); ?>;

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

        // Initialize the sales chart with Chart.js
        function initSalesChart() {
            const canvas = document.getElementById('salesChart');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            
            // Destroy existing chart if it exists
            if (window.salesChart) {
                window.salesChart.destroy();
            }
            
            window.salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Revenus (XAF)',
                        data: chartData,
                        backgroundColor: 'rgba(5, 150, 105, 0.8)',
                        borderColor: 'rgba(5, 150, 105, 1)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString() + ' XAF';
                                },
                                color: '#6b7280'
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#6b7280'
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    elements: {
                        bar: {
                            borderRadius: 4
                        }
                    }
                }
            });
        }

        // Initialize app
        function initApp() {
            setFavicon();
            setupSidebar();
            
            // Initialize Lucide icons first
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            // Initialize chart after a short delay to ensure DOM is ready
            setTimeout(initSalesChart, 100);
        }

        // Start the app when DOM is loaded
        document.addEventListener('DOMContentLoaded', initApp);

        // Auto-refresh dashboard every 5 minutes
        setInterval(function() {
            window.location.reload();
        }, 300000); // 5 minutes
    </script>

    <style>
        .quick-stats {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .quick-stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .quick-stat-item:last-child {
            border-bottom: none;
        }

        .quick-stat-item .label {
            color: #d1d5db;
            font-size: 0.875rem;
        }

        .quick-stat-item .value {
            font-weight: 600;
            color: white;
        }

        .text-warning {
            color: #f59e0b !important;
        }

        .text-danger {
            color: #ef4444 !important;
        }

        .w-full {
            width: 100%;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .py-3 {
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .py-4 {
            padding-top: 1.5rem;
            padding-bottom: 1.5rem;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #6b7280;
        }
    </style>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>