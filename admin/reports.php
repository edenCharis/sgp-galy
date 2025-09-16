<?php
session_start();
require_once '../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in and has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN'){
    header('Location: ../logout.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['role'];
$current_date = date('Y-m-d');
$current_month = date('Y-m');
$current_year = date('Y');

// Get filter parameters
$report_type = $_GET['type'] ?? 'sales';
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? $current_date;
$cashier_filter = $_GET['cashier'] ?? '';
$product_filter = $_GET['product'] ?? '';

// Function to get sales report data
function getSalesReport($pdo, $date_from, $date_to, $cashier_filter = '', $product_filter = '') {
    $sql = "SELECT 
                DATE(s.saleDate) as sale_date,
                COUNT(DISTINCT s.id) as total_transactions,
                SUM(si.quantity) as total_items,
                SUM(si.quantity * si.unitPrice) as total_amount,
                AVG(s.totalAmount) as avg_transaction_value
            FROM sale s 
            JOIN saleitem si ON s.id = si.saleId 
            JOIN product p ON si.productId = p.id
            JOIN cash_register cr ON s.cash_register_id = cr.id 
            WHERE DATE(s.saleDate) BETWEEN ? AND ?";
    
    $params = [$date_from, $date_to];
    
    if ($cashier_filter) {
        $sql .= " AND cr.cashier_id = ?";
        $params[] = $cashier_filter;
    }
    
    if ($product_filter) {
        $sql .= " AND p.name LIKE ?";
        $params[] = "%$product_filter%";
    }
    
    $sql .= " GROUP BY DATE(s.saleDate) ORDER BY saleDate DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get top selling products
function getTopProducts($pdo, $date_from, $date_to, $limit = 10) {
    $sql = "SELECT 
                p.name,
                c.name as category,
                SUM(si.quantity) as total_sold,
                SUM(si.quantity * si.unitPrice) as total_revenue,
                COUNT(DISTINCT s.id) as transactions_count
            FROM saleitem si
            JOIN sale s ON si.saleId = s.id
            JOIN product p ON si.productId = p.id
            JOIN category c ON p.categoryId = c.id
            WHERE DATE(s.saleDate) BETWEEN ? AND ?
            GROUP BY p.id, p.name, c.name
            ORDER BY total_sold DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_from, $date_to, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get low stock report
function getLowStockReport($pdo, $threshold = 10) {
    $sql = "SELECT 
                p.name,
                c.name as category,
                p.stock,

                p.supplier,
                CASE 
                    WHEN p.stock = 0 THEN 'Out of Stock'
                    WHEN p.stock <= 10 THEN 'Low Stock'
                    ELSE 'Normal'
                END as status
            FROM product p join category c on p.categoryId = c.id
            WHERE p.stock <= ?
            ORDER BY p.stock ASC, p.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$threshold]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get cashier performance
function getCashierPerformance($pdo, $date_from, $date_to) {
    $sql = "SELECT 
                u.username,

                COUNT(DISTINCT s.id) as total_transactions,
                SUM(s.totalAmount) as total_sales,
                AVG(s.totalAmount) as avg_transaction,
                SUM(si.quantity) as total_items_sold
            FROM sale s
            JOIN cash_register cr ON s.cash_register_id = cr.id
            JOIN user u ON cr.cashier_id = u.id
            JOIN saleitem si ON s.id = si.saleId
            WHERE DATE(s.saleDate) BETWEEN ? AND ?
            GROUP BY u.id, u.username
            ORDER BY total_sales DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get financial summary
function getFinancialSummary($pdo, $date_from, $date_to) {
    $sql = "SELECT 
                SUM(s.totalAmount) as total_revenue,
                COUNT(DISTINCT s.id) as total_transactions,
                SUM(si.quantity) as total_items_sold,
                AVG(s.totalAmount) as avg_transaction_value,
                MAX(s.totalAmount) as highest_sale,
                MIN(s.totalAmount) as lowest_sale
            FROM sale s
            JOIN saleitem si ON s.id = si.saleId
            WHERE DATE(s.saleDate) BETWEEN ? AND ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get all cashiers for filter
$cashiers_sql = "SELECT id, username FROM user WHERE role  IN ('cashier', 'admin', 'seller') ORDER BY username";
$cashiers = $pdo->query($cashiers_sql)->fetchAll(PDO::FETCH_ASSOC);

// Get data based on report type
$report_data = [];
switch ($report_type) {
    case 'sales':
        $report_data = getSalesReport($pdo, $date_from, $date_to, $cashier_filter, $product_filter);
        break;
    case 'products':
        $report_data = getTopProducts($pdo, $date_from, $date_to);
        break;
    case 'inventory':
        $report_data = getLowStockReport($pdo);
        break;
    case 'cashiers':
        $report_data = getCashierPerformance($pdo, $date_from, $date_to);
        break;
}

$financial_summary = getFinancialSummary($pdo, $date_from, $date_to);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - PharmaSys</title>
    
    <!-- External Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        .reports-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .reports-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .reports-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .report-filters {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .filter-group select,
        .filter-group input {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            min-width: 150px;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            ring: 2px solid rgba(59, 130, 246, 0.1);
        }

        .btn-apply-filters {
            padding: 0.75rem 1.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            height: fit-content;
        }

        .btn-apply-filters:hover {
            background: #2563eb;
        }

        .report-tabs {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 2rem;
            background: #f3f4f6;
            padding: 0.25rem;
            border-radius: 12px;
            overflow-x: auto;
        }

        .report-tab {
            padding: 0.75rem 1.5rem;
            background: transparent;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .report-tab.active {
            background: white;
            color: #3b82f6;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .financial-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid;
        }

        .summary-card.revenue {
            border-left-color: #10b981;
        }

        .summary-card.transactions {
            border-left-color: #3b82f6;
        }

        .summary-card.items {
            border-left-color: #f59e0b;
        }

        .summary-card.average {
            border-left-color: #8b5cf6;
        }

        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .summary-label {
            color: #6b7280;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .report-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-content {
            padding: 1.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 1rem;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .data-table tr:hover {
            background: #f9fafb;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.out-of-stock {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.low-stock {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.normal {
            background: #d1fae5;
            color: #065f46;
        }

        .currency {
            font-weight: 600;
            color: #059669;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .btn-export {
            padding: 0.5rem 1rem;
            background: #6b7280;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-export:hover {
            background: #4b5563;
        }

        .chart-container {
            margin: 2rem 0;
            height: 400px;
        }

        @media (max-width: 768px) {
            .reports-container {
                padding: 1rem;
            }
            
            .report-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .financial-summary {
                grid-template-columns: 1fr;
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
              <main class="main-content">
       

            <!-- Filters -->
            <form method="GET" class="report-filters">
                <div class="filter-group">
                    <label for="date_from">Date de début</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                
                <div class="filter-group">
                    <label for="date_to">Date de fin</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                
                <?php if ($report_type === 'sales'): ?>
                <div class="filter-group">
                    <label for="cashier">Caissier</label>
                    <select id="cashier" name="cashier">
                        <option value="">Tous les caissiers</option>
                        <?php foreach ($cashiers as $cashier): ?>
                        <option value="<?php echo $cashier['id']; ?>" <?php echo $cashier_filter == $cashier['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cashier['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="product">Produit</label>
                    <input type="text" id="product" name="product" placeholder="Nom du produit..." value="<?php echo htmlspecialchars($product_filter); ?>">
                </div>
                <?php endif; ?>
                
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($report_type); ?>">
                <button type="submit" class="btn-apply-filters">
                    <i data-lucide="filter"></i>
                    Appliquer
                </button>
            </form>

            <!-- Report Tabs -->
            <div class="report-tabs">
                <button class="report-tab <?php echo $report_type === 'sales' ? 'active' : ''; ?>" 
                        onclick="changeReportType('sales')">
                    <i data-lucide="trending-up"></i>
                    Ventes
                </button>
                <button class="report-tab <?php echo $report_type === 'products' ? 'active' : ''; ?>" 
                        onclick="changeReportType('products')">
                    <i data-lucide="package"></i>
                    Produits
                </button>
                <button class="report-tab <?php echo $report_type === 'inventory' ? 'active' : ''; ?>" 
                        onclick="changeReportType('inventory')">
                    <i data-lucide="alert-triangle"></i>
                    Stock Faible
                </button>
                <button class="report-tab <?php echo $report_type === 'cashiers' ? 'active' : ''; ?>" 
                        onclick="changeReportType('cashiers')">
                    <i data-lucide="users"></i>
                    Performance
                </button>
            </div>

            <!-- Financial Summary -->
            <?php if (in_array($report_type, ['sales', 'products', 'cashiers'])): ?>
            <div class="financial-summary">
                <div class="summary-card revenue">
                    <div class="summary-value"><?php echo number_format($financial_summary['total_revenue'] ?? 0, 0, ',', ' '); ?> FCFA</div>
                    <div class="summary-label">Chiffre d'affaires total</div>
                </div>
                <div class="summary-card transactions">
                    <div class="summary-value"><?php echo number_format($financial_summary['total_transactions'] ?? 0, 0, ',', ' '); ?></div>
                    <div class="summary-label">Total transactions</div>
                </div>
                <div class="summary-card items">
                    <div class="summary-value"><?php echo number_format($financial_summary['total_items_sold'] ?? 0, 0, ',', ' '); ?></div>
                    <div class="summary-label">Articles vendus</div>
                </div>
                <div class="summary-card average">
                    <div class="summary-value"><?php echo number_format($financial_summary['avg_transaction_value'] ?? 0, 0, ',', ' '); ?> FCFA</div>
                    <div class="summary-label">Panier moyen</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Report Content -->
            <div class="report-section">
                <div class="section-header">
                    <div class="section-title">
                        <?php
                        $icons = [
                            'sales' => 'trending-up',
                            'products' => 'package',
                            'inventory' => 'alert-triangle',
                            'cashiers' => 'users'
                        ];
                        
                        $titles = [
                            'sales' => 'Rapport de Ventes',
                            'products' => 'Top Produits',
                            'inventory' => 'Stock Faible',
                            'cashiers' => 'Performance des Caissiers'
                        ];
                        ?>
                        <i data-lucide="<?php echo $icons[$report_type]; ?>"></i>
                        <?php echo $titles[$report_type]; ?>
                    </div>
                </div>
                
                <div class="section-content">
                    <div class="export-buttons">
                        <button class="btn-export" onclick="exportToCSV()">
                            <i data-lucide="download"></i>
                            Export CSV
                        </button>
                        <button class="btn-export" onclick="printReport()">
                            <i data-lucide="printer"></i>
                            Imprimer
                        </button>
                    </div>
                    
                    <?php if (empty($report_data)): ?>
                    <div class="no-data">
                        <i data-lucide="inbox" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>Aucune donnée trouvée pour cette période.</p>
                    </div>
                    <?php else: ?>
                    
                    <?php if ($report_type === 'sales'): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Transactions</th>
                                <th>Articles vendus</th>
                                <th>Montant total</th>
                                <th>Panier moyen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($row['sale_date'])); ?></td>
                                <td><?php echo number_format($row['total_transactions'], 0, ',', ' '); ?></td>
                                <td><?php echo number_format($row['total_items'], 0, ',', ' '); ?></td>
                                <td class="currency"><?php echo number_format($row['total_amount'], 0, ',', ' '); ?> FCFA</td>
                                <td class="currency"><?php echo number_format($row['avg_transaction_value'], 0, ',', ' '); ?> FCFA</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php elseif ($report_type === 'products'): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Catégorie</th>
                                <th>Quantité vendue</th>
                                <th>Chiffre d'affaires</th>
                                <th>Transactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo number_format($row['total_sold'], 0, ',', ' '); ?></td>
                                <td class="currency"><?php echo number_format($row['total_revenue'], 0, ',', ' '); ?> FCFA</td>
                                <td><?php echo number_format($row['transactions_count'], 0, ',', ' '); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php elseif ($report_type === 'inventory'): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Catégorie</th>
                                <th>Stock actuel</th>
                               
                                <th>Fournisseur</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo number_format($row['stock_quantity'], 0, ',', ' '); ?></td>
                                 <td><?php echo htmlspecialchars($row['supplier'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php elseif ($report_type === 'cashiers'): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Caissier</th>
                                <th>Transactions</th>
                                <th>Ventes totales</th>
                                <th>Panier moyen</th>
                                <th>Articles vendus</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['username'] . ' ' . $row['username']); ?></td>
                                <td><?php echo number_format($row['total_transactions'], 0, ',', ' '); ?></td>
                                <td class="currency"><?php echo number_format($row['total_sales'], 0, ',', ' '); ?> FCFA</td>
                                <td class="currency"><?php echo number_format($row['avg_transaction'], 0, ',', ' '); ?> FCFA</td>
                                <td><?php echo number_format($row['total_items_sold'], 0, ',', ' '); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
        </div>
    </div>
    
  

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Change report type
        function changeReportType(type) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('type', type);
            currentUrl.searchParams.delete('cashier');
            currentUrl.searchParams.delete('product');
            window.location.href = currentUrl.toString();
        }

        // Export to CSV
        function exportToCSV() {
            const table = document.querySelector('.data-table');
            if (!table) return;
            
            let csv = '';
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = Array.from(cols).map(col => {
                    return '"' + col.textContent.replace(/"/g, '""') + '"';
                });
                csv += rowData.join(',') + '\n';
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `rapport_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Print report
        function printReport() {
            const originalTitle = document.title;
            const reportType = '<?php echo $report_type; ?>';
            const dateFrom = '<?php echo $date_from; ?>';
            const dateTo = '<?php echo $date_to; ?>';
            
            document.title = `Rapport_${reportType}_${dateFrom}_${dateTo}`;
            
            // Hide non-essential elements
            const sidebar = document.querySelector('.sidebar');
            const filters = document.querySelector('.report-filters');
            const tabs = document.querySelector('.report-tabs');
            const exportButtons = document.querySelector('.export-buttons');
            
            const elementsToHide = [sidebar, filters, tabs, exportButtons];
            elementsToHide.forEach(el => {
                if (el) el.style.display = 'none';
            });
            
            window.print();
            
            // Restore elements
            elementsToHide.forEach(el => {
                if (el) el.style.display = '';
            });
            
            document.title = originalTitle;
        }

        // Auto-refresh data every 5 minutes for real-time reports
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                location.reload();
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>