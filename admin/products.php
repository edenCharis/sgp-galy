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
    if (!isset($pdo)) {
        throw new Exception('Database connection not found');
    }

    $admin_id = $_SESSION['user_id'];
    $success_message = '';
    $error_message = '';

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    $price = floatval($_POST['price']);
                    $stock = intval($_POST['stock']);
                    $purchasePrice = floatval($_POST['purchasePrice']);
                    $sellingPrice = floatval($_POST['sellingPrice']);
                    $vatRate = floatval($_POST['vatRate']);
                    $expiryDate = $_POST['expiryDate'];
                    $categoryId = intval($_POST['categoryId']);
                    $supplierId = intval($_POST['supplierId']);
                    $code = trim($_POST['code']);
                    $statut_TVA = $_POST['statut_TVA'];
                    
                    if (!empty($name) && !empty($code) && $price > 0 && $sellingPrice > 0) {
                        // Check if code already exists
                        $checkSQL = "SELECT COUNT(*) as count FROM product WHERE code = ?";
                        $stmt = $pdo->prepare($checkSQL);
                        $stmt->execute([$code]);
                        $codeExists = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($codeExists && $codeExists['count'] > 0) {
                            $error_message = "Ce code produit existe déjà.";
                        } else {
                            // Get the last ID from the database and increment
                            $stmt = $pdo->query("SELECT id FROM product ORDER BY CAST(id AS UNSIGNED) DESC LIMIT 1");
                            $lastId = $stmt->fetch(PDO::FETCH_COLUMN);
                            $id = $lastId ? (string)((int)$lastId + 1) : "1";
                            
                            $insertSQL = "INSERT INTO product (id, name, description, price, stock, purchasePrice, sellingPrice, vatRate, createdAt, updatedAt, expiryDate, categoryId, supplierId, code, statut_TVA) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?, ?)";
                            $stmt = $pdo->prepare($insertSQL);
                            $result = $stmt->execute([$id, $name, $description, $price, $stock, $purchasePrice, $sellingPrice, $vatRate, $expiryDate, $categoryId, $supplierId, $code, $statut_TVA]);
                            
                            if ($result) {
                                $success_message = "Produit ajouté avec succès.";
                            } else {
                                $error_message = "Erreur lors de l'ajout du produit.";
                            }
                        }
                    } else {
                        $error_message = "Veuillez remplir tous les champs obligatoires.";
                    }
                    break;

                case 'edit':
                    $id = (string)($_POST['id']);
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    $price = floatval($_POST['price']);
                    $stock = intval($_POST['stock']);
                    $purchasePrice = floatval($_POST['purchasePrice']);
                    $sellingPrice = floatval($_POST['sellingPrice']);
                    $vatRate = floatval($_POST['vatRate']);
                    $expiryDate = $_POST['expiryDate'];
                    $categoryId = intval($_POST['categoryId']);
                    $supplierId = intval($_POST['supplierId']);
                    $code = trim($_POST['code']);
                    $statut_TVA = $_POST['statut_TVA'];
                    
                    if (!empty($name) && !empty($code) && $price > 0 && $sellingPrice > 0 && !empty($id)) {
                        // Check if code already exists for other products
                        $checkSQL = "SELECT COUNT(*) as count FROM product WHERE code = ? AND id != ?";
                        $stmt = $pdo->prepare($checkSQL);
                        $stmt->execute([$code, $id]);
                        $codeExists = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($codeExists && $codeExists['count'] > 0) {
                            $error_message = "Ce code produit existe déjà pour un autre produit.";
                        } else {
                            $updateSQL = "UPDATE product SET name = ?, description = ?, price = ?, stock = ?, purchasePrice = ?, sellingPrice = ?, vatRate = ?, updatedAt = NOW(), expiryDate = ?, categoryId = ?, supplierId = ?, code = ?, statut_TVA = ? WHERE id = ?";
                            $stmt = $pdo->prepare($updateSQL);
                            $result = $stmt->execute([$name, $description, $price, $stock, $purchasePrice, $sellingPrice, $vatRate, $expiryDate, $categoryId, $supplierId, $code, $statut_TVA, $id]);
                            
                            if ($result) {
                                $success_message = "Produit modifié avec succès.";
                            } else {
                                $error_message = "Erreur lors de la modification du produit.";
                            }
                        }
                    } else {
                        $error_message = "Données invalides pour la modification.";
                    }
                    break;

                case 'delete':
                    $id = (string)($_POST['id']);
                    
                    if (!empty($id)) {
                        // Check if product is used in delivery_items
                        $checkSQL = "SELECT COUNT(*) as count FROM delivery_items WHERE productId = ?";
                        $stmt = $pdo->prepare($checkSQL);
                        $stmt->execute([$id]);
                        $checkResult = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($checkResult && $checkResult['count'] > 0) {
                            $error_message = "Impossible de supprimer ce produit car il est utilisé dans des livraisons.";
                        } else {
                            $deleteSQL = "DELETE FROM product WHERE id = ?";
                            $stmt = $pdo->prepare($deleteSQL);
                            $result = $stmt->execute([$id]);
                            
                            if ($result) {
                                $success_message = "Produit supprimé avec succès.";
                            } else {
                                $error_message = "Erreur lors de la suppression du produit.";
                            }
                        }
                    }
                    break;
            }
        }
    }

    // Get products with pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $categoryFilter = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $supplierFilter = isset($_GET['supplier']) ? intval($_GET['supplier']) : 0;

    $whereClause = '';
    $params = [];
    $conditions = [];
    
    if (!empty($search)) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.code LIKE ?)";
        $searchTerm = "%$search%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($categoryFilter > 0) {
        $conditions[] = "p.categoryId = ?";
        $params[] = $categoryFilter;
    }
    
    if ($supplierFilter > 0) {
        $conditions[] = "p.supplierId = ?";
        $params[] = $supplierFilter;
    }
    
    if (!empty($conditions)) {
        $whereClause = "WHERE " . implode(" AND ", $conditions);
    }

    // Get total count
    $countSQL = "SELECT COUNT(*) as total FROM product p $whereClause";
    $stmt = $pdo->prepare($countSQL);
    $stmt->execute($params);
    $countResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalProducts = $countResult ? $countResult['total'] : 0;
    $totalPages = ceil($totalProducts / $limit);

    // Get products with category and supplier names
    $productsSQL = "SELECT p.*, c.name as categoryName, s.name as supplierName 
                   FROM product p 
                   LEFT JOIN category c ON p.categoryId = c.id 
                   LEFT JOIN supplier s ON p.supplierId = s.id 
                   $whereClause 
                   ORDER BY p.createdAt DESC 
                   LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($productsSQL);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($products === false) {
        $products = [];
    }

    // Get categories for dropdown
    $categoriesSQL = "SELECT id, name FROM category ORDER BY name";
    $stmt = $pdo->prepare($categoriesSQL);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get suppliers for dropdown
    $suppliersSQL = "SELECT id, name FROM supplier ORDER BY name";
    $stmt = $pdo->prepare($suppliersSQL);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary statistics
    $lowStockCount = 0;
    $expiredCount = 0;
    $currentDate = date('Y-m-d');
    
    foreach ($products as $product) {
        if ($product['stock'] <= 10) $lowStockCount++;
        if (!empty($product['expiryDate']) && $product['expiryDate'] <= $currentDate) $expiredCount++;
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Helper functions
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Il y a quelques secondes';
    if ($time < 3600) return 'Il y a ' . floor($time/60) . ' min';
    if ($time < 86400) return 'Il y a ' . floor($time/3600) . 'h ' . floor(($time%3600)/60) . 'min';
    return 'Il y a ' . floor($time/86400) . ' jour(s)';
}

function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' FCFA';
}

function getStockStatus($stock) {
    if ($stock <= 0) return ['status' => 'danger', 'text' => 'Rupture'];
    if ($stock <= 10) return ['status' => 'warning', 'text' => 'Stock bas'];
    return ['status' => 'success', 'text' => 'En stock'];
}

function isExpired($expiryDate) {
    if (empty($expiryDate)) return false;
    return $expiryDate <= date('Y-m-d');
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
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .products-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .products-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .products-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .product-filters {
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
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-apply-filters:hover {
            background: #2563eb;
            color: white;
        }

        .product-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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

        .summary-card.total {
            border-left-color: #10b981;
        }

        .summary-card.low-stock {
            border-left-color: #f59e0b;
        }

        .summary-card.expired {
            border-left-color: #ef4444;
        }

        .summary-card.displayed {
            border-left-color: #3b82f6;
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

        .products-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-add-product {
            padding: 0.75rem 1.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }

        .btn-add-product:hover {
            background: #2563eb;
        }

        .section-content {
            padding: 1.5rem;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .data-table th {
            text-align: left;
            padding: 0.75rem 0.5rem;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            white-space: nowrap;
        }

        .data-table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .data-table tr:hover {
            background: #f9fafb;
        }

        .product-name {
            font-weight: 600;
            color: #1f2937;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .product-code {
            font-family: monospace;
            background: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
        }

        .product-price {
            font-weight: 600;
            color: #059669;
        }

        .stock-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .stock-badge.success {
            background: #d1fae5;
            color: #065f46;
        }

        .stock-badge.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .stock-badge.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .expired-badge {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .action-buttons {
            display: flex;
            gap: 0.25rem;
        }

        .btn-action {
            padding: 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            width: 32px;
            height: 32px;
        }

        .btn-edit {
            background: #f59e0b;
            color: white;
        }

        .btn-edit:hover {
            background: #d97706;
        }

        .btn-delete {
            background: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
        }

        .modal-lg {
            max-width: 800px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }

        .pagination {
            display: flex;
            gap: 0.25rem;
            margin: 0;
            list-style: none;
            padding: 0;
        }

        .page-item {
            display: flex;
        }

        .page-link {
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            color: #374151;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .page-link:hover {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .page-item.active .page-link {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
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
            transition: all 0.2s;
        }

        .btn-export:hover {
            background: #4b5563;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .no-data i {
            width: 48px;
            height: 48px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .table-responsive {
            overflow-x: auto;
        }

        @media (max-width: 768px) {
            .products-container {
                padding: 1rem;
            }
            
            .product-filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group {
                min-width: 100%;
            }
            
            .product-summary {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: stretch;
            }

            .form-row,
            .form-row-3 {
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
            <main class="content-area">
                <!-- Page Header -->
                <div class="products-header">
                    <h1 class="products-title">
                        <i data-lucide="package"></i>
                        Gestion des Produits
                    </h1>
                </div>

                <!-- Alerts -->
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i data-lucide="check-circle"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i data-lucide="alert-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div class="product-summary">
                    <div class="summary-card total">
                        <div class="summary-value"><?php echo $totalProducts; ?></div>
                        <div class="summary-label">Total Produits</div>
                    </div>
                    <div class="summary-card displayed">
                        <div class="summary-value"><?php echo count($products); ?></div>
                        <div class="summary-label">Affichés</div>
                    </div>
                    <div class="summary-card low-stock">
                        <div class="summary-value"><?php echo $lowStockCount; ?></div>
                        <div class="summary-label">Stock Bas</div>
                    </div>
                    <div class="summary-card expired">
                        <div class="summary-value"><?php echo $expiredCount; ?></div>
                        <div class="summary-label">Expirés</div>
                    </div>
                </div>

                <!-- Filters -->
                <form method="GET" class="product-filters">
                    <div class="filter-group">
                        <label for="search">Rechercher</label>
                        <input type="text" 
                               id="search"
                               name="search" 
                               placeholder="Nom, code ou description..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="category">Catégorie</label>
                        <select id="category" name="category">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" 
                                        <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="supplier">Fournisseur</label>
                        <select id="supplier" name="supplier">
                            <option value="">Tous les fournisseurs</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?php echo $supplier['id']; ?>" 
                                        <?php echo $supplierFilter == $supplier['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-apply-filters">
                        <i data-lucide="search"></i>
                        Rechercher
                    </button>
                    
                    <?php if (!empty($search) || $categoryFilter > 0 || $supplierFilter > 0): ?>
                        <a href="?" class="btn-apply-filters" style="background: #6b7280;">
                            <i data-lucide="x"></i>
                            Effacer
                        </a>
                    <?php endif; ?>
                </form>

                <!-- Export Buttons -->
                <div class="export-buttons">
                    <button onclick="exportToCSV()" class="btn-export">
                        <i data-lucide="download"></i>
                        Exporter CSV
                    </button>
                    <button onclick="printReport()" class="btn-export">
                        <i data-lucide="printer"></i>
                        Imprimer
                    </button>
                </div>

                <!-- Products Section -->
                <div class="products-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i data-lucide="list"></i>
                            Liste des Produits
                        </h2>
                        <button class="btn-add-product" data-bs-toggle="modal" data-bs-target="#addProductModal">
                            <i data-lucide="plus"></i>
                            Ajouter Produit
                        </button>
                    </div>

                    <div class="section-content">
                        <?php if (!empty($products)): ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Code</th>
                                            <th>Nom</th>
                                            <th>Catégorie</th>
                                            <th>Prix Vente</th>
                                            <th>Stock</th>
                                            <th>Fournisseur</th>
                                            <th>Expiration</th>
                                            <th>TVA</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <?php 
                                                $stockStatus = getStockStatus($product['stock']);
                                                $expired = isExpired($product['expiryDate']);
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="product-code"><?php echo htmlspecialchars($product['code']); ?></span>
                                                </td>
                                                <td>
                                                    <div class="product-name" title="<?php echo htmlspecialchars($product['name']); ?>">
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </div>
                                                    <?php if (!empty($product['description'])): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . (strlen($product['description']) > 50 ? '...' : ''); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['categoryName'] ?? 'Non définie'); ?></td>
                                                <td class="product-price"><?php echo formatPrice($product['sellingPrice']); ?></td>
                                                <td>
                                                    <span class="stock-badge <?php echo $stockStatus['status']; ?>">
                                                        <?php echo $product['stock'] . ' - ' . $stockStatus['text']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['supplierName'] ?? 'Non défini'); ?></td>
                                                <td>
                                                    <?php if (!empty($product['expiryDate'])): ?>
                                                        <?php if ($expired): ?>
                                                            <span class="expired-badge">Expiré</span>
                                                        <?php else: ?>
                                                            <?php echo date('d/m/Y', strtotime($product['expiryDate'])); ?>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $product['vatRate'] . '%'; ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn-action btn-edit" 
                                                                onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)" 
                                                                title="Modifier">
                                                            <i data-lucide="edit"></i>
                                                        </button>
                                                        <button class="btn-action btn-delete" 
                                                                onclick="deleteProduct('<?php echo $product['id']; ?>', '<?php echo htmlspecialchars($product['name']); ?>')" 
                                                                title="Supprimer">
                                                            <i data-lucide="trash-2"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div class="pagination-wrapper">
                                    <div class="pagination-info">
                                        Affichage de <?php echo ($offset + 1); ?> à <?php echo min($offset + $limit, $totalProducts); ?> 
                                        sur <?php echo $totalProducts; ?> produits
                                    </div>
                                    <ul class="pagination">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>&supplier=<?php echo $supplierFilter; ?>">
                                                    <i data-lucide="chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>&supplier=<?php echo $supplierFilter; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $categoryFilter; ?>&supplier=<?php echo $supplierFilter; ?>">
                                                    <i data-lucide="chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <i data-lucide="package"></i>
                                <h3>Aucun produit trouvé</h3>
                                <p>Commencez par ajouter un nouveau produit.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="plus"></i>
                        Ajouter un Produit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="addProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="form-row">
                            <div class="mb-3">
                                <label for="productName" class="form-label">Nom du Produit *</label>
                                <input type="text" class="form-control" id="productName" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="productCode" class="form-label">Code Produit *</label>
                                <input type="text" class="form-control" id="productCode" name="code" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="productDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="productDescription" name="description" rows="3"></textarea>
                        </div>

                        <div class="form-row-3">
                            <div class="mb-3">
                                <label for="productPrice" class="form-label">Prix Unitaire *</label>
                                <input type="number" class="form-control" id="productPrice" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="productPurchasePrice" class="form-label">Prix d'Achat</label>
                                <input type="number" class="form-control" id="productPurchasePrice" name="purchasePrice" step="0.01" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="productSellingPrice" class="form-label">Prix de Vente *</label>
                                <input type="number" class="form-control" id="productSellingPrice" name="sellingPrice" step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="form-row-3">
                            <div class="mb-3">
                                <label for="productStock" class="form-label">Stock Initial</label>
                                <input type="number" class="form-control" id="productStock" name="stock" min="0" value="0">
                            </div>
                            <div class="mb-3">
                                <label for="productVatRate" class="form-label">Taux TVA (%)</label>
                                <input type="number" class="form-control" id="productVatRate" name="vatRate" step="0.01" min="0" max="100" value="18">
                            </div>
                            <div class="mb-3">
                                <label for="productExpiryDate" class="form-label">Date d'Expiration</label>
                                <input type="date" class="form-control" id="productExpiryDate" name="expiryDate">
                            </div>
                        </div>

                        <div class="form-row-3">
                            <div class="mb-3">
                                <label for="productCategory" class="form-label">Catégorie</label>
                                <select class="form-control" id="productCategory" name="categoryId">
                                    <option value="0">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="productSupplier" class="form-label">Fournisseur</label>
                                <select class="form-control" id="productSupplier" name="supplierId">
                                    <option value="0">Sélectionner un fournisseur</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>">
                                            <?php echo htmlspecialchars($supplier['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="productStatutTVA" class="form-label">Statut TVA</label>
                                <select class="form-control" id="productStatutTVA" name="statut_TVA">
                                    <option value="taxable">Taxable</option>
                                    <option value="exempt">Exempté</option>
                                    <option value="zero_rate">Taux zéro</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="plus"></i>
                            Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="edit"></i>
                        Modifier le Produit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editProductForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="editProductId">
                        
                        <div class="form-row">
                            <div class="mb-3">
                                <label for="editProductName" class="form-label">Nom du Produit *</label>
                                <input type="text" class="form-control" id="editProductName" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="editProductCode" class="form-label">Code Produit *</label>
                                <input type="text" class="form-control" id="editProductCode" name="code" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="editProductDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="editProductDescription" name="description" rows="3"></textarea>
                        </div>

                        <div class="form-row-3">
                            <div class="mb-3">
                                <label for="editProductPrice" class="form-label">Prix Unitaire *</label>
                                <input type="number" class="form-control" id="editProductPrice" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="mb-3">
                                <label for="editProductPurchasePrice" class="form-label">Prix d'Achat</label>
                                <input type="number" class="form-control" id="editProductPurchasePrice" name="purchasePrice" step="0.01" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="editProductSellingPrice" class="form-label">Prix de Vente *</label>
                                <input type="number" class="form-control" id="editProductSellingPrice" name="sellingPrice" step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="form-row-3">
                            <div class="mb-3">
                                <label for="editProductStock" class="form-label">Stock</label>
                                <input type="number" class="form-control" id="editProductStock" name="stock" min="0">
                            </div>
                            <div class="mb-3">
                                <label for="editProductVatRate" class="form-label">Taux TVA (%)</label>
                                <input type="number" class="form-control" id="editProductVatRate" name="vatRate" step="0.01" min="0" max="100">
                            </div>
                            <div class="mb-3">
                                <label for="editProductExpiryDate" class="form-label">Date d'Expiration</label>
                                <input type="date" class="form-control" id="editProductExpiryDate" name="expiryDate">
                            </div>
                        </div>

                        <div class="form-row-3">
                            <div class="mb-3">
                                <label for="editProductCategory" class="form-label">Catégorie</label>
                                <select class="form-control" id="editProductCategory" name="categoryId">
                                    <option value="0">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editProductSupplier" class="form-label">Fournisseur</label>
                                <select class="form-control" id="editProductSupplier" name="supplierId">
                                    <option value="0">Sélectionner un fournisseur</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?php echo $supplier['id']; ?>">
                                            <?php echo htmlspecialchars($supplier['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editProductStatutTVA" class="form-label">Statut TVA</label>
                                <select class="form-control" id="editProductStatutTVA" name="statut_TVA">
                                    <option value="taxable">Taxable</option>
                                    <option value="exempt">Exempté</option>
                                    <option value="zero_rate">Taux zéro</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i>
                            Modifier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i data-lucide="alert-triangle"></i>
                        Confirmer la Suppression
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer le produit <strong id="deleteProductName"></strong> ?</p>
                    <p class="text-muted small">Cette action est irréversible et supprimera toutes les données associées au produit.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <form method="POST" id="deleteProductForm" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteProductId">
                        <button type="submit" class="btn btn-danger">
                            <i data-lucide="trash-2"></i>
                            Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Edit product function
        function editProduct(product) {
            document.getElementById('editProductId').value = product.id;
            document.getElementById('editProductName').value = product.name;
            document.getElementById('editProductCode').value = product.code;
            document.getElementById('editProductDescription').value = product.description || '';
            document.getElementById('editProductPrice').value = product.price;
            document.getElementById('editProductPurchasePrice').value = product.purchasePrice || '';
            document.getElementById('editProductSellingPrice').value = product.sellingPrice;
            document.getElementById('editProductStock').value = product.stock || 0;
            document.getElementById('editProductVatRate').value = product.vatRate || 18;
            document.getElementById('editProductExpiryDate').value = product.expiryDate || '';
            document.getElementById('editProductCategory').value = product.categoryId || 0;
            document.getElementById('editProductSupplier').value = product.supplierId || 0;
            document.getElementById('editProductStatutTVA').value = product.statut_TVA || 'taxable';
            
            const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
            editModal.show();
        }

        // Delete product function
        function deleteProduct(id, name) {
            document.getElementById('deleteProductId').value = id;
            document.getElementById('deleteProductName').textContent = name;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
            deleteModal.show();
        }

        // Export to CSV function
        function exportToCSV() {
            const table = document.querySelector('.data-table');
            let csv = [];
            const rows = table.querySelectorAll('tr');

            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length - 1; j++) { // Exclude actions column
                    let cellText = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + cellText + '"');
                }
                csv.push(row.join(','));
            }

            const csvFile = new Blob([csv.join('\n')], { type: 'text/csv' });
            const downloadLink = document.createElement('a');
            downloadLink.download = 'produits_' + new Date().toISOString().slice(0, 10) + '.csv';
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = 'none';
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
        }

        // Print report function
        function printReport() {
            const printWindow = window.open('', '_blank');
            const table = document.querySelector('.data-table').outerHTML;
            const totalProducts = <?php echo $totalProducts; ?>;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Rapport des Produits - PharmaSys</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { color: #1f2937; text-align: center; }
                        .header-info { text-align: center; margin-bottom: 30px; color: #6b7280; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f9fafb; font-weight: bold; }
                        tr:nth-child(even) { background-color: #f9fafb; }
                        .footer { margin-top: 30px; text-align: center; color: #6b7280; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <h1>Rapport des Produits</h1>
                    <div class="header-info">
                        <p>Date d'impression: ${new Date().toLocaleDateString('fr-FR')}</p>
                        <p>Total des produits: ${totalProducts}</p>
                    </div>
                    ${table}
                    <div class="footer">
                        <p>PharmaSys - Système de Gestion Pharmaceutique</p>
                    </div>
                    <style>
                        .action-buttons { display: none !important; }
                        th:last-child, td:last-child { display: none !important; }
                    </style>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Form validation
        function validateProductForm(formId) {
            const form = document.getElementById(formId);
            const name = form.querySelector('[name="name"]').value.trim();
            const code = form.querySelector('[name="code"]').value.trim();
            const price = parseFloat(form.querySelector('[name="price"]').value);
            const sellingPrice = parseFloat(form.querySelector('[name="sellingPrice"]').value);
            
            if (!name || !code) {
                alert('Veuillez remplir le nom et le code du produit.');
                return false;
            }
            
            if (price <= 0 || sellingPrice <= 0) {
                alert('Les prix doivent être supérieurs à zéro.');
                return false;
            }
            
            return true;
        }

        // Auto-calculate selling price based on purchase price and margin
        function calculateSellingPrice(purchasePriceInput, sellingPriceInput, marginPercent = 30) {
            const purchasePrice = parseFloat(purchasePriceInput.value);
            if (purchasePrice > 0) {
                const sellingPrice = purchasePrice * (1 + marginPercent / 100);
                sellingPriceInput.value = sellingPrice.toFixed(2);
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-dismiss alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        if (alert.parentNode) {
                            alert.parentNode.removeChild(alert);
                        }
                    }, 300);
                }, 5000);
            });

            // Form validation
            document.getElementById('addProductForm').addEventListener('submit', function(e) {
                if (!validateProductForm('addProductForm')) {
                    e.preventDefault();
                }
            });

            document.getElementById('editProductForm').addEventListener('submit', function(e) {
                if (!validateProductForm('editProductForm')) {
                    e.preventDefault();
                }
            });

            // Auto-calculate selling price
            document.getElementById('productPurchasePrice').addEventListener('blur', function() {
                const sellingPriceInput = document.getElementById('productSellingPrice');
                if (!sellingPriceInput.value) {
                    calculateSellingPrice(this, sellingPriceInput);
                }
            });

            document.getElementById('editProductPurchasePrice').addEventListener('blur', function() {
                const sellingPriceInput = document.getElementById('editProductSellingPrice');
                if (!sellingPriceInput.value) {
                    calculateSellingPrice(this, sellingPriceInput);
                }
            });

            // Clear forms when modals are hidden
            document.getElementById('addProductModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('addProductForm').reset();
            });

            document.getElementById('editProductModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('editProductForm').reset();
            });

            // Search functionality
            document.getElementById('search').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.form.submit();
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + K to focus search
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    document.getElementById('search').focus();
                }
                
                // Ctrl/Cmd + N to add new product
                if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                    e.preventDefault();
                    const addModal = new bootstrap.Modal(document.getElementById('addProductModal'));
                    addModal.show();
                }
            });

            // Loading states for forms
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        const originalText = submitBtn.innerHTML;
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
                        
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 5000);
                    }
                });
            });

            // Highlight search terms
            <?php if (!empty($search)): ?>
                const searchTerm = <?php echo json_encode($search); ?>;
                if (searchTerm) {
                    const regex = new RegExp('(' + searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\                                            <th>Prix Vente') + ')', 'gi');
                    document.querySelectorAll('.product-name, .product-code').forEach(element => {
                        element.innerHTML = element.innerHTML.replace(regex, '<mark>$1</mark>');
                    });
                }
            <?php endif; ?>

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
<?php
} else {
    header("Location: ../login.php");
    exit();
}
?>