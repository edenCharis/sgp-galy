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

    // Pagination settings
    $itemsPerPage = 20;
    $currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($currentPage - 1) * $itemsPerPage;

    // Search and filter functionality
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
    $dateFilter = isset($_GET['date']) ? trim($_GET['date']) : '';
    
    $searchCondition = 'WHERE 1=1';
    $params = [];

    if (!empty($searchTerm)) {
        $searchCondition .= " AND (c.name LIKE ? OR cl.name LIKE ?)";
        $searchLike = '%' . $searchTerm . '%';
        $params[] = $searchLike;
        $params[] = $searchLike;
    }

    if (!empty($statusFilter)) {
        $searchCondition .= " AND carts.status = ?";
        $params[] = $statusFilter;
    }

    if (!empty($dateFilter)) {
        $searchCondition .= " AND DATE(carts.created_at) = ?";
        $params[] = $dateFilter;
    }

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total 
                 FROM carts 
                 LEFT JOIN client cl ON carts.client_id = cl.id 
                 " . $searchCondition;
    $totalResult = $db->fetchAll($countSql, $params);
    
    if (!$totalResult || !isset($totalResult[0]['total'])) {
        throw new Exception('Failed to get sales count');
    }
    
    $totalSales = $totalResult[0]['total'];
    $totalPages = ceil($totalSales / $itemsPerPage);

    // Get sales for current page with detailed information
    $sql = "SELECT carts.id as cart_id,
                   carts.name as cart_name,
                   carts.status,
                   carts.created_at,
                   cl.name as client_name,
                   cl.id as client_id,
                   COUNT(ci.id) as total_items,
                   SUM(ci.quantity * ci.unit_price) as total_amount
            FROM carts 
            LEFT JOIN client cl ON carts.client_id = cl.id
            LEFT JOIN cart_items ci ON carts.id = ci.cart_id
            " . $searchCondition . "
            GROUP BY carts.id, carts.name, carts.status, carts.created_at, cl.name, cl.id
            ORDER BY carts.created_at DESC 
            LIMIT ? OFFSET ?";

    $params[] = $itemsPerPage;
    $params[] = $offset;

    $sales = $db->fetchAll($sql, $params);
    
    if ($sales === false) {
        $sales = []; // Set empty array if query fails
    }

    // Get statistics
    $statsParams = [];
    $statsCondition = '';
    
    if (!empty($statusFilter) || !empty($dateFilter) || !empty($searchTerm)) {
        $statsCondition = $searchCondition;
        $statsParams = array_slice($params, 0, -2); // Remove LIMIT and OFFSET params
    }

    $statsSQL = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN carts.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN carts.status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                    SUM(CASE WHEN carts.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    COALESCE(SUM(CASE WHEN carts.status = 'completed' THEN ci.quantity * ci.unit_price ELSE 0 END), 0) as total_revenue
                 FROM carts
                 LEFT JOIN client cl ON carts.client_id = cl.id
                 LEFT JOIN cart_items ci ON carts.id = ci.cart_id
                 " . $statsCondition;

    $statsResult = $db->fetchAll($statsSQL, $statsParams);
    $stats = $statsResult ? $statsResult[0] : [
        'total_orders' => 0,
        'completed_orders' => 0,
        'pending_orders' => 0,
        'cancelled_orders' => 0,
        'total_revenue' => 0
    ];

} catch (Exception $e) {
    // Log error and show user-friendly message
    error_log('Sales history page error: ' . $e->getMessage());
    die('Error loading sales history: ' . $e->getMessage() . '<br><br><a href="dashboard.php">Return to Dashboard</a>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Historique des Ventes</title>
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
        .sales-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .sales-title {
            color: #059669;
            font-size: 1.875rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .filters-section {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            flex: 1;
            justify-content: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .filter-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .search-container, .filter-select, .filter-date {
            position: relative;
        }

        .search-input, .filter-select select, .filter-date input {
            padding: 0.75rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
            background: white;
        }

        .search-input {
            padding-left: 2.5rem;
            min-width: 250px;
        }

        .search-input:focus, .filter-select select:focus, .filter-date input:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            width: 1.25rem;
            height: 1.25rem;
        }

        .sales-stats {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #374151;
            font-weight: 500;
        }

        .stat-item i {
            color: #059669;
        }

        .sales-table-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
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
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .sales-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }

        .sales-table tbody tr:hover {
            background: #f9fafb;
        }

        .sales-table tbody tr:last-child td {
            border-bottom: none;
        }

        .order-id {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .order-name {
            font-weight: 500;
            color: #111827;
        }

        .client-name {
            color: #374151;
            font-weight: 500;
        }

        .client-name.anonymous {
            color: #9ca3af;
            font-style: italic;
        }

        .order-amount {
            font-weight: 600;
            color: #059669;
            font-size: 1.125rem;
        }

        .order-date {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .status-badge {
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .status-badge.completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .items-count {
            background: #e0f2fe;
            color: #0277bd;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-view {
            background: #059669;
            color: white;
            border: none;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-view:hover {
            background: #047857;
        }

        .pagination-container {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-top: 2rem;
            padding: 1rem 0;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .pagination-info {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .pagination {
            display: flex;
            gap: 0.5rem;
        }

        .pagination a, .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            color: #374151;
            text-decoration: none;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: #f9fafb;
            border-color: #059669;
        }

        .pagination .current {
            background: #059669;
            color: white;
            border-color: #059669;
        }

        .pagination .disabled {
            color: #9ca3af;
            cursor: not-allowed;
        }

        .no-sales {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .no-sales i {
            width: 4rem;
            height: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .sales-header {
                flex-direction: column;
                align-items: stretch;
            }

            .filters-section {
                justify-content: stretch;
                flex-direction: column;
            }

            .search-input {
                min-width: auto;
            }

            .sales-stats {
                justify-content: center;
            }

            .sales-table-container {
                overflow-x: auto;
            }

            .sales-table {
                min-width: 800px;
            }

            .pagination-container {
                flex-direction: column;
                text-align: center;
            }
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 900px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #059669;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #f3f4f6;
            color: #374151;
        }

        .modal-content {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .modal-loading {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }

        .spinner {
            width: 3rem;
            height: 3rem;
            border: 3px solid #e5e7eb;
            border-top: 3px solid #059669;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .order-info-section {
            margin-bottom: 2rem;
        }

        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .order-info-item label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .order-info-item span, .order-info-item div {
            font-weight: 500;
            color: #374151;
        }

        .client-contact {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .order-items-section {
            margin-top: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-items-table-container {
            background: #f9fafb;
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .order-items-table {
            width: 100%;
            border-collapse: collapse;
        }

        .order-items-table th {
            background: #f3f4f6;
            color: #374151;
            font-weight: 600;
            padding: 0.75rem;
            text-align: left;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .order-items-table td {
            padding: 0.75rem;
            border-top: 1px solid #e5e7eb;
            color: #374151;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .product-name {
            font-weight: 500;
            color: #111827;
        }

        .product-code {
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            color: #6b7280;
            background: #e5e7eb;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
            display: inline-block;
        }

        .product-description {
            font-size: 0.875rem;
            color: #6b7280;
            line-height: 1.4;
        }

        .item-category {
            background: #e0f2fe;
            color: #0277bd;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            display: inline-block;
        }

        .item-price {
            font-weight: 600;
            color: #059669;
        }

        .item-quantity {
            font-weight: 500;
            text-align: center;
        }

        .item-total {
            font-weight: 600;
            color: #059669;
        }

        .modal-error {
            text-align: center;
            padding: 2rem;
            color: #dc2626;
        }

        .modal-error i {
            width: 3rem;
            height: 3rem;
            margin-bottom: 1rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-primary {
            background: #059669;
            color: white;
            border: 1px solid #059669;
        }

        .btn-primary:hover {
            background: #047857;
        }

        @media (max-width: 640px) {
            .modal-container {
                max-height: 95vh;
                margin: 0.5rem;
            }

            .order-info-grid {
                grid-template-columns: 1fr;
            }

            .order-items-table-container {
                overflow-x: auto;
            }

            .order-items-table {
                min-width: 600px;
            }

            .modal-footer {
                flex-direction: column;
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
                <!-- Sales Header -->
                <div class="sales-header">
                    <h1 class="sales-title">
                        <i data-lucide="shopping-cart"></i>
                        Historique des Ventes
                    </h1>
                    
                    <div class="filters-section">
                        <form method="GET" action="" class="d-flex gap-3 flex-wrap">
                            <div class="filter-group">
                                <label class="filter-label">Rechercher</label>
                                <div class="search-container">
                                    <i data-lucide="search" class="search-icon"></i>
                                    <input 
                                        type="text" 
                                        name="search" 
                                        class="search-input" 
                                        placeholder="Nom commande ou client..."
                                        value="<?php echo htmlspecialchars($searchTerm); ?>"
                                    >
                                </div>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Statut</label>
                                <div class="filter-select">
                                    <select name="status">
                                        <option value="">Tous les statuts</option>
                                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Complété</option>
                                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Annulé</option>
                                    </select>
                                </div>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Date</label>
                                <div class="filter-date">
                                    <input 
                                        type="date" 
                                        name="date" 
                                        value="<?php echo htmlspecialchars($dateFilter); ?>"
                                    >
                                </div>
                            </div>

                            <div class="filter-group" style="justify-content: flex-end;">
                                <button type="submit" class="btn-view" style="margin-top: 1.25rem;">
                                    <i data-lucide="filter"></i>
                                    Filtrer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sales Stats -->
                <div class="sales-stats">
                    <div class="stat-item text-primary">
                        <i data-lucide="shopping-cart"></i>
                        <span><?php echo $stats['total_orders']; ?> commandes total</span>
                    </div>
                    <div class="stat-item text-success">
                        <i data-lucide="check-circle"></i>
                        <span><?php echo $stats['completed_orders']; ?> complétées</span>
                    </div>
                    <div class="stat-item text-warning">
                        <i data-lucide="clock"></i>
                        <span><?php echo $stats['pending_orders']; ?> en attente</span>
                    </div>
                    <div class="stat-item text-danger">
                        <i data-lucide="x-circle"></i>
                        <span><?php echo $stats['cancelled_orders']; ?> annulées</span>
                    </div>
                    <div class="stat-item text-info">
                        <i data-lucide="dollar-sign"></i>
                        <span><?php echo number_format($stats['total_revenue'], 0); ?> XAF revenus</span>
                    </div>
                </div>

                <!-- Sales Table -->
                <div class="sales-table-container">
                    <?php if (empty($sales)): ?>
                        <div class="no-sales">
                            <i data-lucide="shopping-cart"></i>
                            <h3>Aucune vente trouvée</h3>
                            <p>
                                <?php if (!empty($searchTerm) || !empty($statusFilter) || !empty($dateFilter)): ?>
                                    Aucune vente ne correspond à vos critères de recherche.
                                <?php else: ?>
                                    Il n'y a aucune vente enregistrée.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <table class="sales-table">
                            <thead>
                                <tr>
                                    <th>ID Commande</th>
                                    <th>Nom Commande</th>
                                    <th>Client</th>
                                    <th>Articles</th>
                                    <th>Montant Total</th>
                                    <th>Statut</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td>
                                            <span class="order-id">#<?php echo htmlspecialchars($sale['cart_id']); ?></span>
                                        </td>
                                        <td>
                                            <div class="order-name"><?php echo htmlspecialchars($sale['cart_name'] ?: 'Commande sans nom'); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($sale['client_name']): ?>
                                                <div class="client-name"><?php echo htmlspecialchars($sale['client_name']); ?></div>
                                            <?php else: ?>
                                                <div class="client-name anonymous">Client anonyme</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="items-count"><?php echo $sale['total_items']; ?> articles</span>
                                        </td>
                                        <td>
                                            <span class="order-amount"><?php echo number_format($sale['total_amount'], 0); ?> XAF</span>
                                        </td>
                                        <td>
                                            <?php if ($sale['status'] === 'completed'): ?>
                                                <span class="status-badge completed">
                                                    <i data-lucide="check-circle"></i>
                                                    Complété
                                                </span>
                                            <?php elseif ($sale['status'] === 'pending'): ?>
                                                <span class="status-badge pending">
                                                    <i data-lucide="clock"></i>
                                                    En attente
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge cancelled">
                                                    <i data-lucide="x-circle"></i>
                                                    Annulé
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="order-date">
                                                <?php echo date('d/m/Y H:i', strtotime($sale['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn-view" onclick="viewOrderDetails(<?php echo $sale['cart_id']; ?>)">
                                                    <i data-lucide="eye"></i>
                                                    Voir
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Order Details Modal -->
                <div id="orderDetailsModal" class="modal-overlay" style="display: none;">
                    <div class="modal-container">
                        <div class="modal-header">
                            <h2 class="modal-title">
                                <i data-lucide="shopping-cart"></i>
                                Détails de la commande
                            </h2>
                            <button class="modal-close" onclick="closeOrderModal()">
                                <i data-lucide="x"></i>
                            </button>
                        </div>
                        
                        <div class="modal-content">
                            <!-- Loading spinner -->
                            <div id="modalLoading" class="modal-loading">
                                <div class="spinner"></div>
                                <p>Chargement des détails...</p>
                            </div>
                            
                            <!-- Order details content -->
                            <div id="modalOrderContent" style="display: none;">
                                <!-- Order info -->
                                <div class="order-info-section">
                                    <div class="order-info-grid">
                                        <div class="order-info-item">
                                            <label>ID Commande</label>
                                            <span id="modalOrderId" class="order-id"></span>
                                        </div>
                                        <div class="order-info-item">
                                            <label>Nom de la commande</label>
                                            <span id="modalOrderName"></span>
                                        </div>
                                        <div class="order-info-item">
                                            <label>Client</label>
                                            <div id="modalClientInfo">
                                                <span id="modalClientName"></span>
                                                <div id="modalClientContact" class="client-contact"></div>
                                            </div>
                                        </div>
                                        <div class="order-info-item">
                                            <label>Date</label>
                                            <span id="modalOrderDate"></span>
                                        </div>
                                        <div class="order-info-item">
                                            <label>Statut</label>
                                            <span id="modalOrderStatus"></span>
                                        </div>
                                        <div class="order-info-item">
                                            <label>Montant total</label>
                                            <span id="modalOrderTotal" class="order-amount"></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Order items -->
                                <div class="order-items-section">
                                    <h3 class="section-title">
                                        <i data-lucide="package"></i>
                                        Articles commandés
                                    </h3>
                                    <div class="order-items-table-container">
                                        <table class="order-items-table">
                                            <thead>
                                                <tr>
                                                    <th>Produit</th>
                                                    <th>Description</th>
                                                    <th>Catégorie</th>
                                                    <th>Prix unitaire</th>
                                                    <th>Quantité</th>
                                                    <th>Total</th>
                                                </tr>
                                            </thead>
                                            <tbody id="modalOrderItems">
                                                <!-- Items will be populated here -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Error message -->
                            <div id="modalError" class="modal-error" style="display: none;">
                                <i data-lucide="alert-circle"></i>
                                <p>Erreur lors du chargement des détails de la commande.</p>
                            </div>
                        </div>
                        
                        <div class="modal-footer">
                            <button class="btn btn-secondary" onclick="closeOrderModal()">
                                Fermer
                            </button>
                            <button class="btn btn-primary" onclick="printOrder()">
                                <i data-lucide="printer"></i>
                                Imprimer
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Affichage de <?php echo $offset + 1; ?> à <?php echo min($offset + $itemsPerPage, $totalSales); ?> 
                            sur <?php echo $totalSales; ?> ventes
                        </div>
                        
                        <div class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?>">
                                    <i data-lucide="chevron-left"></i>
                                    Précédent
                                </a>
                            <?php else: ?>
                                <span class="disabled">
                                    <i data-lucide="chevron-left"></i>
                                    Précédent
                                </span>
                            <?php endif; ?>

                            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                                <?php if ($i == $currentPage): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''; ?>">
                                    Suivant
                                    <i data-lucide="chevron-right"></i>
                                </a>
                            <?php else: ?>
                                <span class="disabled">
                                    Suivant
                                    <i data-lucide="chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
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

        // View order details function
        function viewOrderDetails(cartId) {
            // Show modal
            const modal = document.getElementById('orderDetailsModal');
            const loading = document.getElementById('modalLoading');
            const content = document.getElementById('modalOrderContent');
            const error = document.getElementById('modalError');
            
            modal.style.display = 'flex';
            loading.style.display = 'block';
            content.style.display = 'none';
            error.style.display = 'none';

            // Fetch order details
            fetch(`get-order-details.php?id=${cartId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    populateOrderModal(data);
                    
                    loading.style.display = 'none';
                    content.style.display = 'block';
                })
                .catch(err => {
                    console.error('Error fetching order details:', err);
                    loading.style.display = 'none';
                    error.style.display = 'block';
                });
        }

        function populateOrderModal(data) {
            const order = data.order;
            const items = data.items;

            // Populate order info
            document.getElementById('modalOrderId').textContent = '#' + order.id;
            document.getElementById('modalOrderName').textContent = order.name;
            document.getElementById('modalOrderDate').textContent = order.formatted_date;
            document.getElementById('modalOrderTotal').textContent = order.totals.formatted_amount;

            // Client info
            document.getElementById('modalClientName').textContent = order.client.name;
            const clientContact = document.getElementById('modalClientContact');
            let contactInfo = '';
            if (order.client.phone) contactInfo += order.client.phone;
            if (order.client.email) {
                if (contactInfo) contactInfo += ' • ';
                contactInfo += order.client.email;
            }
            clientContact.textContent = contactInfo || 'Aucune information de contact';

            // Status badge
            const statusElement = document.getElementById('modalOrderStatus');
            let statusClass = '';
            let statusIcon = '';
            let statusText = '';

            switch (order.status) {
                case 'completed':
                    statusClass = 'status-badge completed';
                    statusIcon = 'check-circle';
                    statusText = 'Complété';
                    break;
                case 'pending':
                    statusClass = 'status-badge pending';
                    statusIcon = 'clock';
                    statusText = 'En attente';
                    break;
                case 'cancelled':
                    statusClass = 'status-badge cancelled';
                    statusIcon = 'x-circle';
                    statusText = 'Annulé';
                    break;
                default:
                    statusClass = 'status-badge';
                    statusIcon = 'help-circle';
                    statusText = order.status;
            }

            statusElement.className = statusClass;
            statusElement.innerHTML = `<i data-lucide="${statusIcon}"></i> ${statusText}`;

            // Populate items
            const itemsContainer = document.getElementById('modalOrderItems');
            itemsContainer.innerHTML = '';

            items.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <div class="product-info">
                            <div class="product-name">${escapeHtml(item.product_name || 'Produit supprimé')}</div>
                            <div class="product-code">${escapeHtml(item.product_code || 'N/A')}</div>
                        </div>
                    </td>
                    <td>
                        <div class="product-description">
                            ${escapeHtml(item.product_description || 'Aucune description')}
                        </div>
                    </td>
                    <td>
                        <span class="item-category">
                            ${escapeHtml(item.category_name || 'Non classé')}
                        </span>
                    </td>
                    <td>
                        <span class="item-price">${formatPrice(item.unit_price)} XAF</span>
                    </td>
                    <td>
                        <span class="item-quantity">${item.quantity}</span>
                    </td>
                    <td>
                        <span class="item-total">${formatPrice(item.total_price)} XAF</span>
                    </td>
                `;
                itemsContainer.appendChild(row);
            });

            // Re-initialize Lucide icons for the modal content
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function closeOrderModal() {
            const modal = document.getElementById('orderDetailsModal');
            modal.style.display = 'none';
        }

        function printOrder() {
            // Get order details from the modal
            const orderId = document.getElementById('modalOrderId').textContent;
            const orderName = document.getElementById('modalOrderName').textContent;
            const orderDate = document.getElementById('modalOrderDate').textContent;
            const orderTotal = document.getElementById('modalOrderTotal').textContent;
            const clientName = document.getElementById('modalClientName').textContent;
            const clientContact = document.getElementById('modalClientContact').textContent;

            // Get items
            const itemsTable = document.getElementById('modalOrderItems');
            let itemsHtml = '';
            
            Array.from(itemsTable.rows).forEach(row => {
                const cells = row.cells;
                itemsHtml += `
                    <tr>
                        <td>${cells[0].querySelector('.product-name').textContent}</td>
                        <td>${cells[0].querySelector('.product-code').textContent}</td>
                        <td>${cells[3].textContent}</td>
                        <td>${cells[4].textContent}</td>
                        <td>${cells[5].textContent}</td>
                    </tr>
                `;
            });

            // Create print content
            const printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Détails de commande - ${orderId}</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .header { text-align: center; margin-bottom: 30px; }
                        .order-info { margin-bottom: 30px; }
                        .order-info h2 { color: #059669; }
                        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
                        .info-item { margin-bottom: 10px; }
                        .info-item label { font-weight: bold; color: #666; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                        th { background-color: #f5f5f5; font-weight: bold; }
                        .total-row { font-weight: bold; background-color: #f9f9f9; }
                        @media print {
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>PharmaSys - Détails de commande</h1>
                        <p>Imprimé le ${new Date().toLocaleDateString('fr-FR')} à ${new Date().toLocaleTimeString('fr-FR')}</p>
                    </div>
                    
                    <div class="order-info">
                        <h2>Informations de la commande</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>ID Commande:</label> ${orderId}
                            </div>
                            <div class="info-item">
                                <label>Nom:</label> ${orderName}
                            </div>
                            <div class="info-item">
                                <label>Client:</label> ${clientName}
                            </div>
                            <div class="info-item">
                                <label>Date:</label> ${orderDate}
                            </div>
                            <div class="info-item">
                                <label>Contact:</label> ${clientContact}
                            </div>
                            <div class="info-item">
                                <label>Total:</label> ${orderTotal}
                            </div>
                        </div>
                    </div>
                    
                    <h2>Articles commandés</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Code</th>
                                <th>Prix unitaire</th>
                                <th>Quantité</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                    </table>
                </body>
                </html>
            `;

            // Open print window
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }

        // Utility functions
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('fr-FR').format(price);
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('orderDetailsModal');
            if (e.target === modal) {
                closeOrderModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeOrderModal();
            }
        });

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

        // Auto-submit search form with debounce
        let searchTimeout;
        const searchInput = document.querySelector('.search-input');
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
        }

        // Auto-submit when filter changes
        const statusSelect = document.querySelector('select[name="status"]');
        const dateInput = document.querySelector('input[name="date"]');
        
        if (statusSelect) {
            statusSelect.addEventListener('change', function() {
                this.form.submit();
            });
        }

        if (dateInput) {
            dateInput.addEventListener('change', function() {
                this.form.submit();
            });
        }
    </script>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>