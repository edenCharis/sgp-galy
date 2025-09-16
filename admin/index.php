<?php
session_start();
if($_SESSION["role"] === "ADMIN" && $_SESSION["id"] == session_id()){

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

    $admin_id = $_SESSION['user_id'];

    // Get today's date
    $today = date('Y-m-d');
    $thisMonth = date('Y-m-01'); // First day of current month

    // KPI 1: Total Revenue (All Sellers)
    $todayStart = $today . ' 00:00:00';
    $todayEnd = $today . ' 23:59:59';

    $totalRevenueSQL = "SELECT 
                            COALESCE(SUM(ci.quantity * ci.unit_price), 0) as total_revenue,
                            COUNT(DISTINCT c.id) as total_orders,
                            COUNT(DISTINCT c.seller_id) as active_sellers
                         FROM carts c
                         LEFT JOIN cart_items ci ON c.id = ci.cart_id
                         WHERE c.status = 'completed'";

    $revenueResult = $db->fetch($totalRevenueSQL);
    $totalRevenue = $revenueResult ? $revenueResult['total_revenue'] : 0;
    $totalOrders = $revenueResult ? $revenueResult['total_orders'] : 0;
    $activeSellers = $revenueResult ? $revenueResult['active_sellers'] : 0;

    // KPI 2: Total Users (Sellers + Clients)
    $sellersSQL = "SELECT COUNT(*) as total_sellers FROM user WHERE role = 'SELLER'";
    $clientsSQL = "SELECT COUNT(*) as total_clients FROM client";
    $cashierSQL = "SELECT COUNT(*) as total_cashiers FROM user WHERE role = 'CASHIER'";
    
    $sellersResult = $db->fetch($sellersSQL);
    $clientsResult = $db->fetch($clientsSQL);
    $cashierResult = $db->fetch($cashierSQL);
    
    $totalSellers = $sellersResult ? $sellersResult['total_sellers'] : 0;
    $totalClients = $clientsResult ? $clientsResult['total_clients'] : 0;
    $totalCashiers = $cashierResult ? $cashierResult['total_cashiers'] : 0;

    // KPI 3: Inventory Overview
    $inventorySQL = "SELECT 
                        COUNT(*) as total_products,
                        SUM(CASE WHEN stock > 10 THEN 1 ELSE 0 END) as in_stock,
                        SUM(CASE WHEN stock > 0 AND stock <= 10 THEN 1 ELSE 0 END) as low_stock,
                        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock
                     FROM product";
                     
    $inventoryResult = $db->fetch($inventorySQL);
    $inventoryData = $inventoryResult ? $inventoryResult : [
        'total_products' => 0,
        'in_stock' => 0,
        'low_stock' => 0,
        'out_of_stock' => 0
    ];

    // KPI 4: Monthly Performance
    $monthlyRevenueSQL = "SELECT 
                             COALESCE(SUM(ci.quantity * ci.unit_price), 0) as monthly_revenue,
                             COUNT(DISTINCT c.id) as monthly_orders
                          FROM carts c
                          LEFT JOIN cart_items ci ON c.id = ci.cart_id
                          WHERE c.created_at >= ? 
                            AND c.status = 'completed'";
    
    $monthlyResult = $db->fetch($monthlyRevenueSQL, [$thisMonth]);
    $monthlyRevenue = $monthlyResult ? $monthlyResult['monthly_revenue'] : 0;
    $monthlyOrders = $monthlyResult ? $monthlyResult['monthly_orders'] : 0;

    // Calculate growth (comparing with yesterday)
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $yesterdayStart = $yesterday . ' 00:00:00';
    $yesterdayEnd = $yesterday . ' 23:59:59';
    
    $yesterdayRevenueSQL = "SELECT COALESCE(SUM(ci.quantity * ci.unit_price), 0) as yesterday_revenue
                           FROM carts c
                           LEFT JOIN cart_items ci ON c.id = ci.cart_id
                           WHERE c.created_at >= ? AND c.created_at <= ?
                             AND c.status = 'completed'";
    $yesterdayResult = $db->fetch($yesterdayRevenueSQL, [$yesterdayStart, $yesterdayEnd]);
    $yesterdayRevenue = $yesterdayResult ? $yesterdayResult['yesterday_revenue'] : 0;
    
    $revenueGrowth = 0;
    if ($yesterdayRevenue > 0) {
        $revenueGrowth = (($totalRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100;
    } elseif ($totalRevenue > 0) {
        $revenueGrowth = 100;
    }

    // Top Performing Sellers
    $topSellersSQL = "SELECT 
                         u.username,
                         u.id as seller_id,
                         COALESCE(SUM(ci.quantity * ci.unit_price), 0) as revenue,
                         COUNT(DISTINCT c.id) as orders
                      FROM user u
                      LEFT JOIN carts c ON u.id = c.seller_id AND DATE(c.created_at) = ?
                      LEFT JOIN cart_items ci ON c.id = ci.cart_id
                      WHERE u.role = 'SELLER' AND c.status = 'completed'
                      GROUP BY u.id, u.username
                      ORDER BY revenue DESC
                      LIMIT 5";
    
    $topSellers = $db->fetchAll($topSellersSQL, [$today]);
    if ($topSellers === false) {
        $topSellers = [];
    }

    // Recent System Activity
    $recentActivitySQL = "SELECT 
                             c.id,
                             c.created_at,
                             u.username as seller_name,
                             cl.name as client_name,
                             COALESCE(SUM(ci.quantity * ci.unit_price), 0) as total_amount,
                             c.status
                          FROM carts c
                          LEFT JOIN user u ON c.seller_id = u.id
                          LEFT JOIN client cl ON c.client_id = cl.id
                          LEFT JOIN cart_items ci ON c.id = ci.cart_id
                          WHERE c.status = 'completed'
                          GROUP BY c.id, c.created_at, u.username, cl.name, c.status
                          ORDER BY c.created_at DESC
                          LIMIT 10";
    
    $recentActivity = $db->fetchAll($recentActivitySQL);
    if ($recentActivity === false) {
        $recentActivity = [];
    }

    // Critical Alerts (Low Stock)
    $criticalAlertsSQL = "SELECT p.name, stock, c.name as category
                         FROM product p join category c on p.categoryId = c.id
                         WHERE stock <= 5
                         ORDER BY stock ASC
                         LIMIT 5";
    
    $criticalAlerts = $db->fetchAll($criticalAlertsSQL);
    if ($criticalAlerts === false) {
        $criticalAlerts = [];
    }

    // Weekly Revenue Chart Data
    $weeklyRevenueSQL = "SELECT 
                            DATE(c.created_at) as sale_date,
                            COALESCE(SUM(ci.quantity * ci.unit_price), 0) as daily_revenue
                         FROM carts c
                         LEFT JOIN cart_items ci ON c.id = ci.cart_id
                         WHERE DATE(c.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                           AND c.status = 'completed'
                         GROUP BY DATE(c.created_at)
                         ORDER BY DATE(c.created_at) ASC";
    
    $weeklyRevenue = $db->fetchAll($weeklyRevenueSQL);
    if ($weeklyRevenue === false) {
        $weeklyRevenue = [];
    }

    // Prepare chart data
    $chartData = [];
    $chartLabels = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dayName = date('D', strtotime("-$i days"));
        
        $dayRevenue = 0;
        foreach ($weeklyRevenue as $revenue) {
            if ($revenue['sale_date'] === $date) {
                $dayRevenue = $revenue['daily_revenue'];
                break;
            }
        }
        
        $chartData[] = floatval($dayRevenue);
        $chartLabels[] = $dayName;
    }

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
    <title>PharmaSys - Administration</title>
     <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
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
                <div class="dashboard-grid">
                    <!-- Quick Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card primary">
                            <div class="stat-icon">
                                <i data-lucide="dollar-sign"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo formatCurrency($totalRevenue); ?></div>
                                <div class="stat-label">Chiffre d'affaires</div>
                                <div class="stat-change <?php echo $revenueGrowth >= 0 ? 'positive' : 'negative'; ?>">
                                    <i data-lucide="<?php echo $revenueGrowth >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                                    <?php echo formatPercentage($revenueGrowth); ?>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i data-lucide="users"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $totalSellers + $totalCashiers; ?></div>
                                <div class="stat-label">Utilisateurs Total</div>
                                
                                
                            </div>
                        </div>

                        <div class="stat-card warning">
                            <div class="stat-icon">
                                <i data-lucide="package"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $inventoryData['total_products']; ?></div>
                                <div class="stat-label">Produits en stock</div>
                                <div class="stat-change negative">
                                    <i data-lucide="alert-triangle"></i>
                                    <?php echo $inventoryData['low_stock']; ?> stock faible
                                </div>
                            </div>
                        </div>

                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i data-lucide="shopping-cart"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $totalOrders; ?></div>
                                <div class="stat-label">Paniers</div>
                                <div class="stat-change positive">
                                    <i data-lucide="trending-up"></i>
                                    <?php echo $activeSellers; ?> vendeurs actifs
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Dashboard Content -->
                    <div class="dashboard-main">
                        <!-- Revenue Chart -->
                     

                        <!-- Recent Activity -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title text-white">
                                    <i data-lucide="activity"></i>
                                    Activité récente
                                </div>
                                <a href="reports.php" class="text-link text-white">
                                    Voir les rapports
                                    <i data-lucide="arrow-right"></i>
                                </a>
                            </div>
                            <div class="card-content">
                                <div class="activity-list">
                                    <?php if (empty($recentActivity)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i data-lucide="activity" class="mb-2" style="width: 2rem; height: 2rem;"></i>
                                            <p>Aucune activité récente</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <div class="activity-item">
                                                <div class="activity-info">
                                                    <div class="activity-id">#<?php echo htmlspecialchars($activity['id']); ?></div>
                                                    <div class="activity-details">
                                                        <span class="seller">
                                                            <?php echo htmlspecialchars($activity['seller_name'] ?: 'Vendeur inconnu'); ?>
                                                        </span>
                                                        <span class="separator">→</span>
                                                        <span class="client">
                                                            <?php echo htmlspecialchars($activity['client_name'] ?: 'Client anonyme'); ?>
                                                        </span>
                                                        <span class="time"><?php echo timeAgo($activity['created_at']); ?></span>
                                                    </div>
                                                </div>
                                                <div class="activity-amount">
                                                    <span class="amount"><?php echo formatCurrency($activity['total_amount']); ?></span>
                                                    <span class="status completed">✓</span>
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
                                    <a href="users.php" class="quick-action-btn primary">
                                        <i data-lucide="user-plus"></i>
                                        Ajouter utilisateur
                                    </a>
                                    <a href="products.php" class="quick-action-btn secondary">
                                        <i data-lucide="package-plus"></i>
                                        Ajouter produit
                                    </a>
                                    <a href="reports.php" class="quick-action-btn secondary">
                                        <i data-lucide="file-text"></i>
                                        Générer rapport
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Top Sellers -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title text-white">
                                    <i data-lucide="trophy"></i>
                                    Top vendeurs
                                </div>
                            </div>
                            <div class="card-content">
                                <div class="top-sellers">
                                    <?php if (empty($topSellers)): ?>
                                        <div class="text-center text-muted py-2">
                                            <p class="small">Aucune vente aujourd'hui</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($topSellers as $index => $seller): ?>
                                            <div class="seller-item">
                                                <div class="seller-rank"><?php echo $index + 1; ?></div>
                                                <div class="seller-info">
                                                    <div class="seller-name"><?php echo htmlspecialchars($seller['username']); ?></div>
                                                    <div class="seller-stats">
                                                        <?php echo formatCurrency($seller['revenue']); ?> 
                                                        <span class="orders">• <?php echo $seller['orders']; ?> cmd</span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Critical Alerts -->
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title text-white">
                                    <i data-lucide="alert-triangle"></i>
                                    Alertes critiques
                                </div>
                            </div>
                            <div class="card-content">
                                <div class="alerts-list">
                                    <?php if (empty($criticalAlerts)): ?>
                                        <div class="text-center text-muted py-2">
                                            <p class="small text-success">Aucune alerte critique</p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($criticalAlerts as $alert): ?>
                                            <div class="alert-item">
                                                <div class="alert-icon">
                                                    <i data-lucide="package-x" class="text-danger"></i>
                                                </div>
                                                <div class="alert-info">
                                                    <div class="alert-title"><?php echo htmlspecialchars($alert['name']); ?></div>
                                                    <div class="alert-message">
                                                        Stock: <?php echo $alert['stock']; ?> unités restantes
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
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

        // Initialize the revenue chart
        function initRevenueChart() {
            const canvas = document.getElementById('revenueChart');
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            
            if (window.revenueChart) {
                window.revenueChart.destroy();
            }
            
            window.revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Revenus (XAF)',
                        data: chartData,
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
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
                    }
                }
            });
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

        // Initialize app
        function initApp() {
            setupSidebar();
            
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            setTimeout(initRevenueChart, 100);
        }

        document.addEventListener('DOMContentLoaded', initApp);

        // Auto-refresh every 10 minutes
        setInterval(function() {
            window.location.reload();
        }, 600000);
    </script>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>