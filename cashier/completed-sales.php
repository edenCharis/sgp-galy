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

    $cashierId = $_SESSION["user_id"];
    
    // Pagination settings
    $limit = 20;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $limit;
    
    // Date filter
    $dateFilter = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
    $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
    $dateRange = isset($_GET['date_range']) ? $_GET['date_range'] : 'today';
    
    // Search filter
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build query conditions
    $conditions = ["cr.cashier_id = ?"];
    $params = [$cashierId];
    
    // Date range filtering
    switch ($dateRange) {
        case 'today':
            $conditions[] = "DATE(s.createdAt) = ?";
            $params[] = date('Y-m-d');
            break;
        case 'yesterday':
            $conditions[] = "DATE(s.createdAt) = ?";
            $params[] = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'week':
            $conditions[] = "DATE(s.createdAt) >= ?";
            $params[] = date('Y-m-d', strtotime('monday this week'));
            break;
        case 'month':
            $conditions[] = "DATE(s.createdAt) >= ?";
            $params[] = date('Y-m-01');
            break;
        case 'custom':
            if ($dateFrom && isset($_GET['date_to'])) {
                $conditions[] = "DATE(s.createdAt) BETWEEN ? AND ?";
                $params[] = $_GET['date_from'];
                $params[] = $_GET['date_to'];
            }
            break;
    }
    
    // Search filtering
    if (!empty($search)) {
        $conditions[] = "(s.invoiceNumber LIKE ? OR c.name LIKE ? OR sel.username LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $whereClause = "WHERE " . implode(" AND ", $conditions);
    
    // Get total count for pagination
    $countQuery = "SELECT COUNT(*) as total 
                   FROM sale s
                   LEFT JOIN client c ON s.clientId = c.id
                   LEFT JOIN user sel ON s.sellerId = sel.id
                   JOIN cash_register cr ON s.cash_register_id = cr.id
                   $whereClause";
    
    $countResult = $db->fetch($countQuery, $params);
    $totalSales = $countResult ? $countResult['total'] : 0;
    $totalPages = ceil($totalSales / $limit);
    
    // Get sales data
    $salesQuery = "SELECT s.*, c.name as clientName, sel.username as sellerName,
                   s.totalAmount, s.totalAmount
                   FROM sale s
                   LEFT JOIN client c ON s.clientId = c.id
                   LEFT JOIN user sel ON s.sellerId = sel.id
                   JOIN cash_register cr ON s.cash_register_id = cr.id
                   $whereClause
                   ORDER BY s.createdAt DESC
                   LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $sales = $db->fetchAll($salesQuery, $params);
    if (!$sales) $sales = [];
    
    // Calculate summary statistics
    $summaryQuery = "SELECT COUNT(*) as totalCount,
                     COALESCE(SUM(totalAmount), 0) as totalAmount,
                     COALESCE(AVG(totalAmount), 0) as avgAmount
                     FROM sale s
                     JOIN cash_register cr ON s.cash_register_id = cr.id
                     $whereClause";
    
    $summaryParams = array_slice($params, 0, -2); // Remove limit and offset
    $summary = $db->fetch($summaryQuery, $summaryParams);

} catch (Exception $e) {
    error_log('Completed sales error: ' . $e->getMessage());
    die('Error loading sales: ' . $e->getMessage() . '<br><br><a href="../logout.php">Logout</a>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Ventes Réalisées</title>
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
        .filters-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr auto;
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }

        .filter-select,
        .filter-input {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .filter-select:focus,
        .filter-input:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 2px rgba(5, 150, 105, 0.1);
        }

        .btn-filter {
            background: #059669;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-filter:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #059669;
        }

        .summary-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .sales-table-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-header {
            background: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            color: #059669;
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-export {
            background: #6366f1;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-export:hover {
            background: #4f46e5;
            color: white;
        }

        .sales-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sales-table th {
            background: #f8fafc;
            color: #374151;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 0.875rem;
        }

        .sales-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
            font-size: 0.875rem;
        }

        .sales-table tbody tr:hover {
            background: #f9fafb;
        }

        .invoice-number {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }

        .payment-method {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .payment-cash {
            background: #dcfce7;
            color: #166534;
        }

        .payment-card {
            background: #dbeafe;
            color: #1e40af;
        }

        .payment-mobile {
            background: #fef3c7;
            color: #92400e;
        }

        .amount {
            font-weight: 600;
            color: #059669;
        }

        .sale-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-view {
            background: #6366f1;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-view:hover {
            background: #4f46e5;
            color: white;
        }

        .btn-receipt {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-receipt:hover {
            background: #059669;
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem 0;
        }

        .pagination-btn {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            text-decoration: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .pagination-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #374151;
        }

        .pagination-btn.active {
            background: #059669;
            border-color: #059669;
            color: white;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #6b7280;
        }

        .empty-state i {
            width: 3rem;
            height: 3rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        .custom-date-inputs {
            display: none;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .custom-date-inputs.show {
            display: grid;
        }

        @media (max-width: 1024px) {
            .filter-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .summary-stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .table-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .table-actions {
                justify-content: center;
            }

            .sales-table {
                font-size: 0.75rem;
            }

            .sales-table th,
            .sales-table td {
                padding: 0.5rem;
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
                <!-- Filters Section -->
                <div class="filters-section">
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label class="filter-label">Période</label>
                                <select name="date_range" class="filter-select" id="dateRange" onchange="toggleCustomDates()">
                                    <option value="today" <?php echo $dateRange === 'today' ? 'selected' : ''; ?>>Aujourd'hui</option>
                                    <option value="yesterday" <?php echo $dateRange === 'yesterday' ? 'selected' : ''; ?>>Hier</option>
                                    <option value="week" <?php echo $dateRange === 'week' ? 'selected' : ''; ?>>Cette semaine</option>
                                    <option value="month" <?php echo $dateRange === 'month' ? 'selected' : ''; ?>>Ce mois</option>
                                    <option value="custom" <?php echo $dateRange === 'custom' ? 'selected' : ''; ?>>Période personnalisée</option>
                                </select>
                                <div class="custom-date-inputs <?php echo $dateRange === 'custom' ? 'show' : ''; ?>" id="customDates">
                                    <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                                    <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Page</label>
                                <select name="page" class="filter-select">
                                    <?php for ($i = 1; $i <= max(1, $totalPages); $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php echo $page === $i ? 'selected' : ''; ?>>
                                            Page <?php echo $i; ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Rechercher</label>
                                <input type="text" name="search" class="filter-input" placeholder="N° facture, client, vendeur..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <button type="submit" class="btn-filter">
                                    <i data-lucide="search"></i>
                                    Filtrer
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Summary Statistics -->
                <div class="summary-stats">
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $summary ? $summary['totalCount'] : 0; ?></div>
                        <div class="summary-label">Total des ventes</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $summary ? number_format($summary['totalAmount'], 0) : 0; ?> XAF</div>
                        <div class="summary-label">Chiffre d'affaires</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-value"><?php echo $summary ? number_format($summary['avgAmount'], 0) : 0; ?> XAF</div>
                        <div class="summary-label">Panier moyen</div>
                    </div>
                </div>

                <!-- Sales Table -->
                <div class="sales-table-container">
                    <div class="table-header">
                        <h2 class="table-title">
                            <i data-lucide="receipt"></i>
                            Ventes réalisées
                            <?php if ($totalSales > 0): ?>
                                <span style="font-weight: 400; color: #6b7280;">(<?php echo $totalSales; ?> résultat<?php echo $totalSales > 1 ? 's' : ''; ?>)</span>
                            <?php endif; ?>
                        </h2>
                        <div class="table-actions">
                            <a href="export-sales.php?<?php echo http_build_query($_GET); ?>" class="btn-export">
                                <i data-lucide="download"></i>
                                Exporter
                            </a>
                        </div>
                    </div>
                    
                    <?php if (empty($sales)): ?>
                        <div class="empty-state">
                            <i data-lucide="receipt"></i>
                            <h3>Aucune vente trouvée</h3>
                            <p>Aucune vente ne correspond à vos critères de recherche</p>
                        </div>
                    <?php else: ?>
                        <table class="sales-table">
                            <thead>
                                <tr>
                                    <th>Facture</th>
                                    <th>Date/Heure</th>
                                    <th>Client</th>
                                    <th>Vendeur</th>
                                    <th>Montant</th>
                                 
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td>
                                            <span class="invoice-number"><?php echo htmlspecialchars($sale['invoiceNumber']); ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $saleTime = new DateTime($sale['createdAt']);
                                            echo $saleTime->format('d/m/Y H:i'); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($sale['clientName'] ?? 'Client anonyme'); ?></td>
                                        <td><?php echo htmlspecialchars($sale['sellerName'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="amount"><?php echo number_format($sale['totalAmount'], 0); ?> XAF</span>
                                        </td>
                                      
                                        <td>
                                            <div class="sale-actions">
                                                <a href="sale-details.php?id=<?php echo $sale['id']; ?>" class="btn-view">
                                                    <i data-lucide="eye"></i>
                                                    Détails
                                                </a>
                                                <a href="print-receipt.php?id=<?php echo $sale['id']; ?>" class="btn-receipt" target="_blank">
                                                    <i data-lucide="printer"></i>
                                                    Reçu
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php
                                $currentParams = $_GET;
                                
                                // Previous page
                                if ($page > 1):
                                    $currentParams['page'] = $page - 1;
                                ?>
                                    <a href="?<?php echo http_build_query($currentParams); ?>" class="pagination-btn">
                                        <i data-lucide="chevron-left"></i>
                                        Précédent
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                // Page numbers
                                $start = max(1, $page - 2);
                                $end = min($totalPages, $page + 2);
                                
                                for ($i = $start; $i <= $end; $i++):
                                    $currentParams['page'] = $i;
                                ?>
                                    <a href="?<?php echo http_build_query($currentParams); ?>" 
                                       class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php
                                // Next page
                                if ($page < $totalPages):
                                    $currentParams['page'] = $page + 1;
                                ?>
                                    <a href="?<?php echo http_build_query($currentParams); ?>" class="pagination-btn">
                                        Suivant
                                        <i data-lucide="chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

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

        // Toggle custom date inputs
        function toggleCustomDates() {
            const dateRange = document.getElementById('dateRange').value;
            const customDates = document.getElementById('customDates');
            
            if (dateRange === 'custom') {
                customDates.classList.add('show');
            } else {
                customDates.classList.remove('show');
            }
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
            setFavicon();
            setupSidebar();
            
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
            
            if (pageTitle) pageTitle.textContent = 'Ventes Réalisées';
            if (pageDescription) pageDescription.textContent = 'Historique des ventes terminées';
        });
    </script>
</body>
</html>

<?php } else {
    header("location: ../logout.php");
} ?>