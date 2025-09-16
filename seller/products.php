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

    // Search functionality
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    $searchCondition = '';
    $params = [];

    if (!empty($searchTerm)) {
        $searchCondition = "WHERE p.name LIKE ? OR p.code LIKE ?";
        $searchLike = '%' . $searchTerm . '%';
        $params = [$searchLike, $searchLike];
    }

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM product as p " . $searchCondition;
    $totalResult = $db->fetchAll($countSql, $params);
    
    if (!$totalResult || !isset($totalResult[0]['total'])) {
        throw new Exception('Failed to get product count');
    }
    
    $totalProducts = $totalResult[0]['total'];
    $totalPages = ceil($totalProducts / $itemsPerPage);

    // Get products for current page
    $sql = "SELECT p.code, p.name, p.sellingPrice, p.stock, 
                   COALESCE(c.name, 'Non classé') as category 
            FROM product p
            LEFT JOIN category c ON p.categoryId = c.id
            " . $searchCondition . "
            ORDER BY p.name ASC 
            LIMIT ? OFFSET ?";

    $params[] = $itemsPerPage;
    $params[] = $offset;

    $products = $db->fetchAll($sql, $params);
    
    if ($products === false) {
        $products = []; // Set empty array if query fails
    }

} catch (Exception $e) {
    // Log error and show user-friendly message
    error_log('Products page error: ' . $e->getMessage());
    die('Error loading products: ' . $e->getMessage() . '<br><br><a href="dashboard.php">Return to Dashboard</a>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Gestion des Produits</title>
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
        .products-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .products-title {
            color: #059669;
            font-size: 1.875rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .search-section {
            flex: 1;
            max-width: 400px;
            min-width: 280px;
        }

        .search-container {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s;
            background: white;
        }

        .search-input:focus {
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

        .products-stats {
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

        .products-table-container {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table th {
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

        .products-table td {
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            color: #374151;
        }

        .products-table tbody tr:hover {
            background: #f9fafb;
        }

        .products-table tbody tr:last-child td {
            border-bottom: none;
        }

        .product-code {
            font-family: 'Courier New', monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .product-name {
            font-weight: 500;
            color: #111827;
        }

        .product-category {
            background: #e0f2fe;
            color: #0277bd;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .product-price {
            font-weight: 600;
            color: #059669;
            font-size: 1.125rem;
        }

        .stock-badge {
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            font-weight: 500;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stock-badge.in-stock {
            background: #dcfce7;
            color: #166534;
        }

        .stock-badge.low-stock {
            background: #fef3c7;
            color: #92400e;
        }

        .stock-badge.out-of-stock {
            background: #fee2e2;
            color: #991b1b;
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

        .no-products {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .no-products i {
            width: 4rem;
            height: 4rem;
            color: #d1d5db;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .products-header {
                flex-direction: column;
                align-items: stretch;
            }

            .search-section {
                max-width: none;
            }

            .products-stats {
                justify-content: center;
            }

            .products-table-container {
                overflow-x: auto;
            }

            .products-table {
                min-width: 600px;
            }

            .pagination-container {
                flex-direction: column;
                text-align: center;
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
                <!-- Products Header -->
                <div class="products-header">
                    <h1 class="products-title">
                        <i data-lucide="package"></i>
                        Gestion des Produits
                    </h1>
                    
                    <div class="search-section">
                        <form method="GET" action="">
                            <div class="search-container">
                                <i data-lucide="search" class="search-icon"></i>
                                <input 
                                    type="text" 
                                    name="search" 
                                    class="search-input" 
                                    placeholder="Rechercher par nom ou code produit..."
                                    value="<?php echo htmlspecialchars($searchTerm); ?>"
                                >
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products Stats -->
                <div class="products-stats">
                    <div class="stat-item text-success">
                        <i data-lucide="package"></i>
                        <span><?php echo $totalProducts; ?> produits total</span>
                    </div>
                    <div class="stat-item text-info">
                        <i data-lucide="check-circle"></i>
                        <span><?php 
                            $inStockCount = 0;
                            if (is_array($products)) {
                                foreach ($products as $product) {
                                    if (isset($product['stock']) && $product['stock'] > 10) $inStockCount++;
                                }
                            }
                            echo $inStockCount; 
                        ?> en stock</span>
                    </div>
                    <div class="stat-item text-warning">
                        <i data-lucide="alert-triangle"></i>
                        <span><?php 
                            $lowStockCount = 0;
                            if (is_array($products)) {
                                foreach ($products as $product) {
                                    if (isset($product['stock']) && $product['stock'] > 0 && $product['stock'] <= 10) $lowStockCount++;
                                }
                            }
                            echo $lowStockCount; 
                        ?> stock faible</span>
                    </div>
                    <div class="stat-item text-danger">
                        <i data-lucide="x-circle"></i>
                        <span><?php 
                            $outOfStockCount = 0;
                            if (is_array($products)) {
                                foreach ($products as $product) {
                                    if (isset($product['stock']) && $product['stock'] == 0) $outOfStockCount++;
                                }
                            }
                            echo $outOfStockCount; 
                        ?> en rupture</span>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="products-table-container">
                    <?php if (empty($products)): ?>
                        <div class="no-products">
                            <i data-lucide="package-x"></i>
                            <h3>Aucun produit trouvé</h3>
                            <p>
                                <?php if (!empty($searchTerm)): ?>
                                    Aucun produit ne correspond à votre recherche "<?php echo htmlspecialchars($searchTerm); ?>".
                                <?php else: ?>
                                    Il n'y a aucun produit dans votre inventaire.
                                <?php endif; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <table class="products-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Nom du produit</th>
                                    <th>Catégorie</th>
                                    <th>Prix de vente</th>
                                    <th>Stock</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <span class="product-code"><?php echo htmlspecialchars($product['code']); ?></span>
                                        </td>
                                        <td>
                                            <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                        </td>
                                        <td>
                                            <span class="product-category">
                                                <?php echo htmlspecialchars($product['category'] ?: 'Non classé'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="product-price"><?php echo number_format($product['sellingPrice'], 0); ?> XAF </span>
                                        </td>
                                        <td>
                                            <strong><?php echo $product['stock']; ?></strong> unités
                                        </td>
                                        <td>
                                            <?php if ($product['stock'] == 0): ?>
                                                <span class="stock-badge out-of-stock">
                                                    <i data-lucide="x-circle"></i>
                                                    Rupture
                                                </span>
                                            <?php elseif ($product['stock'] <= 10): ?>
                                                <span class="stock-badge low-stock">
                                                    <i data-lucide="alert-triangle"></i>
                                                    Stock faible
                                                </span>
                                            <?php else: ?>
                                                <span class="stock-badge in-stock">
                                                    <i data-lucide="check-circle"></i>
                                                    En stock
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination-info">
                            Affichage de <?php echo $offset + 1; ?> à <?php echo min($offset + $itemsPerPage, $totalProducts); ?> 
                            sur <?php echo $totalProducts; ?> produits
                        </div>
                        
                        <div class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <a href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
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
                                    <a href="?page=<?php echo $i; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?>">
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
    </script>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>