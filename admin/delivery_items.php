<?php
session_start();
if($_SESSION["role"] !== "ADMIN" || $_SESSION["id"] !== session_id()){
    header("Location: ../logout.php");
    exit();
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include database connection
    include '../config/database.php';
    
    if (!isset($pdo)) {
        throw new Exception('Database connection not found');
    }

    // Get delivery ID from URL
    $deliveryId = $_GET['id'] ?? '';
    if (empty($deliveryId)) {
        header("Location: stock-deliveries.php");
        exit();
    }

    $success_message = '';
    $error_message = '';

    // Handle AJAX requests for product search
    if (isset($_GET['action']) && $_GET['action'] === 'search_products') {
        $searchTerm = $_GET['term'] ?? '';
        $products = [];
        
        if (strlen($searchTerm) >= 2) {
            $searchSQL = "SELECT id, name, code, description, stock, purchasePrice, sellingPrice, statut_TVA 
                         FROM product 
                         WHERE (name LIKE ? OR code LIKE ? OR description LIKE ?) 
                         ORDER BY name ASC 
                         LIMIT 20";
            $searchTerm = "%{$searchTerm}%";
            $stmt = $pdo->prepare($searchSQL);
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
           
        header('Content-Type: application/json');
        echo json_encode($products);
        exit();
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_delivery_item':
                    $productId = trim($_POST['productId'] ?? '');
                    $quantity = intval($_POST['quantity'] ?? 0);
                    $priceCession = floatval($_POST['priceCession'] ?? 0);
                    $ASD = floatval($_POST['ASD'] ?? 0);
                    
                    // Validation
                    if (empty($productId) || $quantity <= 0 || $priceCession <= 0) {
                        $error_message = "Veuillez sélectionner un produit et saisir une quantité et un prix de cession valides.";
                        break;
                    }

                    $pdo->beginTransaction();
                    try {
                        // Get product info
                        $productSQL = "SELECT * FROM product WHERE id = ?";
                        $stmt = $pdo->prepare($productSQL);
                        $stmt->execute([$productId]);
                        $product = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if (!$product) {
                            throw new Exception('Produit non trouvé');
                        }

                        // Check if item already exists in delivery
                        $checkSQL = "SELECT quantity FROM delivery_items WHERE deliveryId = ? AND productId = ?";
                        $stmt = $pdo->prepare($checkSQL);
                        $stmt->execute([$deliveryId, $productId]);
                        $existingItem = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($existingItem) {
                            // Update existing item
                            $newQuantity = $existingItem['quantity'] + $quantity;
                            $updateSQL = "UPDATE delivery_items SET quantity = ?, priceCession = ?, ASD = ?, updatedAt = NOW() 
                                         WHERE deliveryId = ? AND productId = ?";
                            $stmt = $pdo->prepare($updateSQL);
                            $stmt->execute([$newQuantity, $priceCession, $ASD, $deliveryId, $productId]);
                        } else {
                            // Add new item
                            $insertSQL = "INSERT INTO delivery_items (deliveryId, productId, quantity, priceCession, ASD, statutTVA, validated, createdAt, updatedAt) 
                                         VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())";
                            $stmt = $pdo->prepare($insertSQL);
                            $stmt->execute([$deliveryId, $productId, $quantity, $priceCession, $ASD, $product['statut_TVA']]);
                        }

                        // Update product stock and prices
                        $newStock = $product['stock'] + $quantity;
                        $updateProductSQL = "UPDATE product SET stock = ?, purchasePrice = ?, updatedAt = NOW() WHERE id = ?";
                        $stmt = $pdo->prepare($updateProductSQL);
                        $stmt->execute([$newStock, $priceCession, $productId]);

                        $pdo->commit();
                        $success_message = "Article ajouté à la livraison avec succès.";
                        
                    } catch (Exception $e) {
                        $pdo->rollback();
                        $error_message = "Erreur lors de l'ajout: " . $e->getMessage();
                    }
                    break;

                case 'create_new_product':
                    $productCode = trim($_POST['productCode'] ?? '');
                    $productName = trim($_POST['productName'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $quantity = intval($_POST['quantity'] ?? 0);
                    $priceCession = floatval($_POST['priceCession'] ?? 0);
                    $ASD = floatval($_POST['ASD'] ?? 0);
                    $statutTVA = $_POST['statutTVA'] ?? 'Oui';
                    $categoryId = intval($_POST['categoryId'] ?? 0) ?: null;
                    
                    // Validation
                    if (empty($productCode) || empty($productName) || $quantity <= 0 || $priceCession <= 0) {
                        $error_message = "Veuillez remplir tous les champs obligatoires (Code, Nom, Quantité, Prix de cession).";
                        break;
                    }

                    $pdo->beginTransaction();
                    try {
                        // Check if product code already exists
                        $checkSQL = "SELECT id, stock FROM product WHERE code = ?";
                        $stmt = $pdo->prepare($checkSQL);
                        $stmt->execute([$productCode]);
                        $existingProduct = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existingProduct) {
                            // Product exists - update stock and add to delivery
                            $productId = $existingProduct['id'];
                            $newStock = $existingProduct['stock'] + $quantity;
                            
                            $updateProductSQL = "UPDATE product SET stock = ?, purchasePrice = ?, updatedAt = NOW() WHERE id = ?";
                            $stmt = $pdo->prepare($updateProductSQL);
                            $stmt->execute([$newStock, $priceCession, $productId]);
                        } else {
                            // Create new product
                            $stmt = $pdo->query("SELECT id FROM product ORDER BY CAST(id AS UNSIGNED) DESC LIMIT 1");
                            $lastId = $stmt->fetch(PDO::FETCH_COLUMN);
                            $productId = $lastId ? (string)((int)$lastId + 1) : "1";
                            
                            // Calculate selling price
                            $vatRate = ($statutTVA === 'Oui') ? 18 : 0;
                            $sellingPrice = $priceCession * ($statutTVA === 'Oui' ? 1.75 : 1.41);
                            
                            $insertProductSQL = "INSERT INTO product (id, name, description, price, stock, purchasePrice, sellingPrice, vatRate, createdAt, updatedAt, categoryId, code, statut_TVA) 
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?)";
                            $stmt = $pdo->prepare($insertProductSQL);
                            $stmt->execute([$productId, $productName, $description, $priceCession, $quantity, $priceCession, $sellingPrice, $vatRate, $categoryId, $productCode, $statutTVA]);
                        }
                        
                        // Add to delivery
                        $insertDeliveryItemSQL = "INSERT INTO delivery_items (deliveryId, productId, quantity, priceCession, ASD, statutTVA, validated, createdAt, updatedAt) 
                                                VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())";
                        $stmt = $pdo->prepare($insertDeliveryItemSQL);
                        $stmt->execute([$deliveryId, $productId, $quantity, $priceCession, $ASD, $statutTVA]);
                        
                        $pdo->commit();
                        $success_message = "Nouveau produit créé et ajouté à la livraison.";
                        
                    } catch (Exception $e) {
                        $pdo->rollback();
                        $error_message = "Erreur lors de la création du produit: " . $e->getMessage();
                    }
                    break;

                case 'validate_item':
                    $itemId = $_POST['itemId'] ?? '';
                    if (!empty($itemId)) {
                        $updateSQL = "UPDATE delivery_items SET validated = 1, updatedAt = NOW() WHERE deliveryId = ? AND productId = ?";
                        $stmt = $pdo->prepare($updateSQL);
                        $result = $stmt->execute([$deliveryId, $itemId]);
                        
                        if ($result) {
                            $success_message = "Article validé avec succès.";
                        } else {
                            $error_message = "Erreur lors de la validation.";
                        }
                    }
                    break;

                case 'validate_all':
                    $updateSQL = "UPDATE delivery_items SET validated = 1, updatedAt = NOW() WHERE deliveryId = ? AND validated = 0";
                    $stmt = $pdo->prepare($updateSQL);
                    $result = $stmt->execute([$deliveryId]);
                    
                    if ($result) {
                        $success_message = "Tous les articles ont été validés.";
                    } else {
                        $error_message = "Erreur lors de la validation globale.";
                    }
                    break;

                case 'delete_item':
                    $productId = $_POST['productId'] ?? '';
                    $quantity = intval($_POST['quantity'] ?? 0);
                    
                    if (!empty($productId)) {
                        $pdo->beginTransaction();
                        try {
                            // Remove from delivery_items
                            $deleteSQL = "DELETE FROM delivery_items WHERE deliveryId = ? AND productId = ?";
                            $stmt = $pdo->prepare($deleteSQL);
                            $stmt->execute([$deliveryId, $productId]);
                            
                            // Reduce product stock
                            $updateStockSQL = "UPDATE product SET stock = GREATEST(0, stock - ?) WHERE id = ?";
                            $stmt = $pdo->prepare($updateStockSQL);
                            $stmt->execute([$quantity, $productId]);
                            
                            $pdo->commit();
                            $success_message = "Article supprimé de la livraison.";
                            
                        } catch (Exception $e) {
                            $pdo->rollback();
                            $error_message = "Erreur lors de la suppression: " . $e->getMessage();
                        }
                    }
                    break;
            }
        }
        
        // Redirect to prevent form resubmission
        $redirect_url = "delivery_items.php?id=" . urlencode($deliveryId);
        if (!empty($success_message)) {
            $redirect_url .= "&success=" . urlencode($success_message);
        }
        if (!empty($error_message)) {
            $redirect_url .= "&error=" . urlencode($error_message);
        }
        header("Location: " . $redirect_url);
        exit();
    }

    // Handle messages from redirect
    if (isset($_GET['success'])) {
        $success_message = $_GET['success'];
    }
    if (isset($_GET['error'])) {
        $error_message = $_GET['error'];
    }

    // Get delivery information
    $deliverySQL = "SELECT d.*, s.name as supplierName FROM delivery d LEFT JOIN supplier s ON d.supplierId = s.id WHERE d.id = ?";
    $stmt = $pdo->prepare($deliverySQL);
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$delivery) {
        header("Location: stock-deliveries.php");
        exit();
    }

    // Get delivery items
    $itemsSQL = "SELECT di.*, p.name as productName, p.code as productCode, p.description as productDescription,
                 (di.quantity * di.priceCession) as totalValue,
                 CASE WHEN di.statutTVA = 'Oui' THEN di.priceCession * 1.75 ELSE di.priceCession * 1.41 END as calculatedPublicPrice
                 FROM delivery_items di 
                 LEFT JOIN product p ON di.productId = p.id 
                 WHERE di.deliveryId = ?
                 ORDER BY di.createdAt DESC";
    $stmt = $pdo->prepare($itemsSQL);
    $stmt->execute([$deliveryId]);
    $deliveryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get categories for dropdown
    $categoriesSQL = "SELECT id, name FROM category ORDER BY name";
    $stmt = $pdo->prepare($categoriesSQL);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate totals
    $totalQuantity = array_sum(array_column($deliveryItems, 'quantity'));
    $totalValue = array_sum(array_column($deliveryItems, 'totalValue'));
    $validatedCount = count(array_filter($deliveryItems, function($item) { return $item['validated'] == 1; }));

    // Determine delivery status
    $status = 'empty';
    if (count($deliveryItems) > 0) {
        if ($validatedCount === count($deliveryItems)) {
            $status = 'validated';
        } elseif ($validatedCount > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }
    }

} catch (Exception $e) {
    $error_message = "Erreur système: " . $e->getMessage();
}

// Helper functions
function formatPrice($price) {
    return number_format($price, 0, ',', ' ') . ' FCFA';
}

function getStatusBadge($status) {
    switch ($status) {
        case 'empty':
            return '<span class="badge badge-secondary">Vide</span>';
        case 'pending':
            return '<span class="badge badge-warning">En attente</span>';
        case 'partial':
            return '<span class="badge badge-info">Partiellement validée</span>';
        case 'validated':
            return '<span class="badge badge-success">Validée</span>';
        default:
            return '<span class="badge badge-secondary">Inconnu</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Livraison #<?php echo htmlspecialchars($deliveryId); ?></title>
    
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        .delivery-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }
        
        .delivery-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .info-label {
            font-size: 0.875rem;
            opacity: 0.8;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .main-grid {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 0.875rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.25);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            color: white;
        }
        
        .btn-success {
            background: #48bb78;
            color: white;
        }
        
        .btn-success:hover {
            background: #38a169;
        }
        
        .btn-danger {
            background: #f56565;
            color: white;
        }
        
        .btn-danger:hover {
            background: #e53e3e;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ced4da;
            border-top: none;
            border-radius: 0 0 6px 6px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }
        
        .search-results.show {
            display: block;
        }
        
        .search-result {
            padding: 0.75rem;
            border-bottom: 1px solid #f8f9fa;
            cursor: pointer;
            transition: background-color 0.15s ease;
        }
        
        .search-result:hover {
            background: #f8f9fa;
        }
        
        .search-result:last-child {
            border-bottom: none;
        }
        
        .result-name {
            font-weight: 500;
            color: #495057;
        }
        
        .result-info {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .selected-product {
            background: #e6f3ff;
            border: 1px solid #667eea;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .selected-product h6 {
            margin: 0 0 0.5rem 0;
            color: #495057;
        }
        
        .product-details {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .items-table th,
        .items-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .items-table tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 4px;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #cce7ff;
            color: #004085;
        }
        
        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid transparent;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .summary-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .summary-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .summary-label {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .delivery-info {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            <main class="content-area">
                <div style="max-width: 1600px; margin: 0 auto; padding: 2rem;">
                    <!-- Breadcrumb -->
                    <div style="margin-bottom: 1rem;">
                        <a href="stock-deliveries.php" style="color: #667eea; text-decoration: none;">← Retour aux livraisons</a>
                    </div>

                    <!-- Delivery Header -->
                    <div class="delivery-header">
                        <h1 style="margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="package-open"></i>
                            Livraison #<?php echo htmlspecialchars($deliveryId); ?>
                        </h1>
                        <div class="delivery-info">
                            <div class="info-card">
                                <div class="info-label">Fournisseur</div>
                                <div class="info-value"><?php echo htmlspecialchars($delivery['supplierName'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-card">
                                <div class="info-label">Date de livraison</div>
                                <div class="info-value"><?php echo date('d/m/Y', strtotime($delivery['deliveryDate'])); ?></div>
                            </div>
                            <div class="info-card">
                                <div class="info-label">Statut</div>
                                <div class="info-value"><?php echo getStatusBadge($status); ?></div>
                            </div>
                            <div class="info-card">
                                <div class="info-label">Articles</div>
                                <div class="info-value"><?php echo count($deliveryItems); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts -->
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i data-lucide="check-circle"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i data-lucide="alert-circle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Main Grid -->
                    <div class="main-grid">
                        <!-- Add Items Form -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <i data-lucide="plus-circle"></i>
                                    Ajouter des Articles
                                </h2>
                            </div>
                            <div class="card-body">
                                <!-- Existing Product Section -->
                                <div class="form-section">
                                    <h3 class="section-title">Produit Existant</h3>
                                    <form method="POST" id="existingProductForm">
                                        <input type="hidden" name="action" value="add_delivery_item">
                                        
                                        <div class="form-group">
                                            <label class="form-label">Rechercher un produit</label>
                                            <div class="search-container">
                                                <input type="text" id="productSearch" class="form-control" 
                                                       placeholder="Nom, code ou description du produit..." autocomplete="off">
                                                <div class="search-results" id="searchResults"></div>
                                            </div>
                                        </div>

                                        <div id="selectedProductInfo" class="selected-product" style="display: none;">
                                            <h6><i data-lucide="check"></i> Produit sélectionné</h6>
                                            <div class="product-details">
                                                <div><strong>Nom:</strong> <span id="selectedName"></span></div>
                                                <div><strong>Code:</strong> <span id="selectedCode"></span></div>
                                                <div><strong>Stock actuel:</strong> <span id="selectedStock"></span></div>
                                <div><strong>Prix d'achat:</strong> <span id="selectedPrice"></span></div>
                            </div>
                        </div>

                        <input type="hidden" name="productId" id="selectedProductId">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Quantité *</label>
                                <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prix de cession *</label>
                                <input type="number" name="priceCession" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">ASD</label>
                            <input type="number" name="ASD" class="form-control" step="0.01" min="0" value="0">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i data-lucide="plus"></i>
                            Ajouter à la livraison
                        </button>
                    </form>
                </div>

                <!-- New Product Section -->
                <div class="form-section">
                    <h3 class="section-title">Nouveau Produit</h3>
                    <form method="POST" id="newProductForm">
                        <input type="hidden" name="action" value="create_new_product">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Code produit *</label>
                                <input type="text" name="productCode" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Nom du produit *</label>
                                <input type="text" name="productName" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Quantité *</label>
                                <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Prix de cession *</label>
                                <input type="number" name="priceCession" class="form-control" step="0.01" min="0" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">ASD</label>
                                <input type="number" name="ASD" class="form-control" step="0.01" min="0" value="0">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Statut TVA *</label>
                                <select name="statutTVA" class="form-control" required>
                                    <option value="Oui">Avec TVA (18%)</option>
                                    <option value="Non">Sans TVA</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Catégorie</label>
                            <select name="categoryId" class="form-control">
                                <option value="">Aucune catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-block">
                            <i data-lucide="package-plus"></i>
                            Créer et ajouter
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Items List -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i data-lucide="list"></i>
                    Articles de la livraison (<?php echo count($deliveryItems); ?>)
                </h2>
                <?php if (count($deliveryItems) > 0 && $validatedCount < count($deliveryItems)): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="validate_all">
                        <button type="submit" class="btn btn-success btn-sm" 
                                onclick="return confirm('Valider tous les articles non validés ?');">
                            <i data-lucide="check-circle"></i>
                            Valider tout
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($deliveryItems)): ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Quantité</th>
                                <th>Prix cession</th>
                                <th>ASD</th>
                                <th>Prix public</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deliveryItems as $item): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['productName']); ?></strong>
                                            <br>
                                            <small style="color: #6c757d;">
                                                Code: <?php echo htmlspecialchars($item['productCode']); ?>
                                                <?php if ($item['productDescription']): ?>
                                                    <br><?php echo htmlspecialchars($item['productDescription']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo formatPrice($item['priceCession']); ?></td>
                                    <td><?php echo formatPrice($item['ASD']); ?></td>
                                    <td><?php echo formatPrice($item['calculatedPublicPrice']); ?></td>
                                    <td><strong><?php echo formatPrice($item['totalValue']); ?></strong></td>
                                    <td>
                                        <?php if ($item['validated']): ?>
                                            <span class="badge badge-success">Validé</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">En attente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 0.5rem;">
                                            <?php if (!$item['validated']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="validate_item">
                                                    <input type="hidden" name="itemId" value="<?php echo $item['productId']; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i data-lucide="check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_item">
                                                <input type="hidden" name="productId" value="<?php echo $item['productId']; ?>">
                                                <input type="hidden" name="quantity" value="<?php echo $item['quantity']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm"
                                                        onclick="return confirm('Supprimer cet article de la livraison ?');">
                                                    <i data-lucide="trash-2"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- Summary -->
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-value"><?php echo count($deliveryItems); ?></div>
                            <div class="summary-label">Produits</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo $totalQuantity; ?></div>
                            <div class="summary-label">Quantité totale</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo $validatedCount; ?></div>
                            <div class="summary-label">Validés</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value"><?php echo formatPrice($totalValue); ?></div>
                            <div class="summary-label">Valeur totale</div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i data-lucide="package" style="width: 64px; height: 64px; opacity: 0.3;"></i>
                        <h3 style="margin: 1rem 0 0.5rem 0;">Aucun article dans cette livraison</h3>
                        <p>Utilisez le formulaire ci-contre pour ajouter des articles.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        let searchTimeout;
        let selectedProduct = null;

        // Product search functionality
        document.getElementById('productSearch').addEventListener('input', function(e) {
            const query = e.target.value;
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }

            if (query.length < 2) {
                hideSearchResults();
                return;
            }

            searchTimeout = setTimeout(() => {
                searchProducts(query);
            }, 300);
        });

        function searchProducts(query) {
            const searchResults = document.getElementById('searchResults');
            searchResults.innerHTML = '<div class="search-result">Recherche...</div>';
            searchResults.classList.add('show');

            fetch(`?id=<?php echo $deliveryId; ?>&action=search_products&term=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(products => {
                    displaySearchResults(products);
                })
                .catch(error => {
                    console.error('Erreur de recherche:', error);
                    searchResults.innerHTML = '<div class="search-result">Erreur de recherche</div>';
                });
        }

        function displaySearchResults(products) {
            const searchResults = document.getElementById('searchResults');
            
            if (products.length === 0) {
                searchResults.innerHTML = '<div class="search-result">Aucun produit trouvé</div>';
                return;
            }

            let html = '';
            products.forEach(product => {
                html += `
                    <div class="search-result" onclick="selectProduct(${JSON.stringify(product).replace(/"/g, '&quot;')})">
                        <div class="result-name">${escapeHtml(product.name)}</div>
                        <div class="result-info">
                            Code: ${escapeHtml(product.code)} | 
                            Stock: ${product.stock} | 
                            Prix: ${formatPrice(product.purchasePrice)}
                        </div>
                    </div>
                `;
            });
            
            searchResults.innerHTML = html;
        }

        function selectProduct(product) {
            selectedProduct = product;
            
            // Update hidden field
            document.getElementById('selectedProductId').value = product.id;
            
            // Update display
            document.getElementById('selectedName').textContent = product.name;
            document.getElementById('selectedCode').textContent = product.code;
            document.getElementById('selectedStock').textContent = product.stock;
            document.getElementById('selectedPrice').textContent = formatPrice(product.purchasePrice);
            
            // Show selected product info
            document.getElementById('selectedProductInfo').style.display = 'block';
            
            // Set suggested price
            document.querySelector('[name="priceCession"]').value = product.purchasePrice;
            
            // Hide search results
            hideSearchResults();
            
            // Update search input
            document.getElementById('productSearch').value = `${product.name} (${product.code})`;
        }

        function hideSearchResults() {
            document.getElementById('searchResults').classList.remove('show');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatPrice(price) {
            return new Intl.NumberFormat('fr-FR').format(price) + ' FCFA';
        }

        // Hide search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-container')) {
                hideSearchResults();
            }
        });

        // Form validation
        document.getElementById('existingProductForm').addEventListener('submit', function(e) {
            if (!selectedProduct) {
                e.preventDefault();
                alert('Veuillez sélectionner un produit existant.');
                return false;
            }
        });

        // Auto-hide success messages
        setTimeout(() => {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                successAlert.style.opacity = '0';
                setTimeout(() => successAlert.remove(), 300);
            }
        }, 5000);

        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar && overlay) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            }
        }

        // Close sidebar on overlay click
        document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
            const sidebar = document.querySelector('.sidebar');
            if (sidebar) {
                sidebar.classList.remove('active');
                this.classList.remove('active');
            }
        });
    </script>
</body>
</html>