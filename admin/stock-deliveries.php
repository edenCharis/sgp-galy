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
                case 'create_delivery':
                    $supplierId = (string)($_POST['supplierId']);
                    $deliveryDate = $_POST['deliveryDate'];
                    
                    if (!empty($supplierId) && !empty($deliveryDate)) {
                        // Get the last delivery ID and increment
                        $stmt = $pdo->query("SELECT id FROM delivery ORDER BY CAST(id AS UNSIGNED) DESC LIMIT 1");
                        $lastId = $stmt->fetch(PDO::FETCH_COLUMN);
                        $deliveryId = $lastId ? (string)((int)$lastId + 1) : "1";
                        
                        $insertSQL = "INSERT INTO delivery (id, supplierId, deliveryDate, createdAt, updatedAt) VALUES (?, ?, ?, NOW(), NOW())";
                        $stmt = $pdo->prepare($insertSQL);
                        $result = $stmt->execute([$deliveryId, $supplierId, $deliveryDate]);
                        
                        if ($result) {
                            $success_message = "Livraison créée avec succès (ID: $deliveryId).";
                        } else {
                            $error_message = "Erreur lors de la création de la livraison.";
                        }
                    } else {
                        $error_message = "Veuillez sélectionner un fournisseur et une date.";
                    }
                    break;

                case 'delete_delivery':
                    $deliveryId = $_POST['deliveryId'];
                    
                    if (!empty($deliveryId)) {
                        $pdo->beginTransaction();
                        
                        try {
                            // First, check if delivery has items
                            $checkSQL = "SELECT COUNT(*) FROM delivery_items WHERE deliveryId = ?";
                            $stmt = $pdo->prepare($checkSQL);
                            $stmt->execute([$deliveryId]);
                            $itemCount = $stmt->fetchColumn();
                            
                            if ($itemCount > 0) {
                                // Update stock for all items before deletion
                                $itemsSQL = "SELECT productId, quantity FROM delivery_items WHERE deliveryId = ?";
                                $stmt = $pdo->prepare($itemsSQL);
                                $stmt->execute([$deliveryId]);
                                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($items as $item) {
                                    $updateStockSQL = "UPDATE product SET stock = stock - ? WHERE id = ?";
                                    $stmt = $pdo->prepare($updateStockSQL);
                                    $stmt->execute([$item['quantity'], $item['productId']]);
                                }
                                
                                // Delete delivery items
                                $deleteItemsSQL = "DELETE FROM delivery_items WHERE deliveryId = ?";
                                $stmt = $pdo->prepare($deleteItemsSQL);
                                $stmt->execute([$deliveryId]);
                            }
                            
                            // Delete delivery
                            $deleteSQL = "DELETE FROM delivery WHERE id = ?";
                            $stmt = $pdo->prepare($deleteSQL);
                            $stmt->execute([$deliveryId]);
                            
                            $pdo->commit();
                            $success_message = "Livraison supprimée avec succès.";
                            
                        } catch (Exception $e) {
                            $pdo->rollback();
                            $error_message = "Erreur lors de la suppression: " . $e->getMessage();
                        }
                    }
                    break;
            }
        }
    }

    // Get suppliers for dropdown
    $suppliersSQL = "SELECT id, name FROM supplier ORDER BY name";
    $stmt = $pdo->prepare($suppliersSQL);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all deliveries with details
    $deliveriesSQL = "SELECT d.*, s.name as supplierName, 
                      (SELECT COUNT(*) FROM delivery_items di WHERE di.deliveryId = d.id) as itemCount,
                      (SELECT SUM(di.quantity * di.priceCession) FROM delivery_items di WHERE di.deliveryId = d.id) as totalValue,
                      (SELECT COUNT(*) FROM delivery_items di WHERE di.deliveryId = d.id AND di.validated = 1) as validatedItems,
                      CASE 
                        WHEN (SELECT COUNT(*) FROM delivery_items di WHERE di.deliveryId = d.id) = 0 THEN 'empty'
                        WHEN (SELECT COUNT(*) FROM delivery_items di WHERE di.deliveryId = d.id AND di.validated = 1) = 
                             (SELECT COUNT(*) FROM delivery_items di WHERE di.deliveryId = d.id) THEN 'validated'
                        WHEN (SELECT COUNT(*) FROM delivery_items di WHERE di.deliveryId = d.id AND di.validated = 0 ) > 0 THEN 'partial'
                        ELSE 'pending'
                      END as status
                      FROM delivery d 
                      LEFT JOIN supplier s ON d.supplierId = s.id 
                      ORDER BY d.createdAt DESC";
    $stmt = $pdo->prepare($deliveriesSQL);
    $stmt->execute();
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get stock summary
    $stockSummarySQL = "SELECT COUNT(*) as totalProducts, 
                        SUM(CASE WHEN stock <= 10 THEN 1 ELSE 0 END) as lowStockCount,
                        SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as outOfStockCount,
                        SUM(stock * sellingPrice) as totalStockValue
                        FROM product";
    $stmt = $pdo->prepare($stockSummarySQL);
    $stmt->execute();
    $stockSummary = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Helper functions
function formatPrice($price) {
    return number_format($price, 2, ',', ' ') . ' FCFA';
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Il y a quelques secondes';
    if ($time < 3600) return 'Il y a ' . floor($time/60) . ' min';
    if ($time < 86400) return 'Il y a ' . floor($time/3600) . 'h ' . floor(($time%3600)/60) . 'min';
    return 'Il y a ' . floor($time/86400) . ' jour(s)';
}

function getStatusBadge($status, $itemCount) {
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
    <title>PharmaSys - Gestion des Livraisons</title>
     <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <!-- Alternative CDN if the above doesn't work -->
    <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/lucide.min.js"></script> -->
  <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        .delivery-container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stock-summary {
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

        .summary-card.total { border-left-color: #10b981; }
        .summary-card.low-stock { border-left-color: #f59e0b; }
        .summary-card.out-stock { border-left-color: #ef4444; }
        .summary-card.value { border-left-color: #3b82f6; }

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

        .main-sections {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .section-card {
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

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-success {
            background: #10b981;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-info {
            background: #0ea5e9;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .btn-info:hover {
            background: #0284c7;
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
        }

        .data-table td {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: middle;
        }

        .data-table tr:hover {
            background: #f9fafb;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-secondary { background: #f3f4f6; color: #374151; }

        .no-data {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
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

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .delivery-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .delivery-meta {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.75rem;
        }

        .stat-item {
            text-align: center;
            padding: 0.25rem;
            background: #f9fafb;
            border-radius: 4px;
        }

        .create-form-card {
            position: sticky;
            top: 2rem;
        }

        @media (max-width: 768px) {
            .delivery-container {
                padding: 1rem;
            }
            
            .main-sections {
                grid-template-columns: 1fr;
            }
            
            .stock-summary {
                grid-template-columns: 1fr;
            }

            .action-buttons {
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
                <div class="delivery-container">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h1 class="page-title">
                            <i data-lucide="truck"></i>
                            Gestion des Livraisons
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

                    <!-- Stock Summary -->
                    <div class="stock-summary">
                        <div class="summary-card total">
                            <div class="summary-value"><?php echo $stockSummary['totalProducts'] ?? 0; ?></div>
                            <div class="summary-label">Total Produits</div>
                        </div>
                        <div class="summary-card low-stock">
                            <div class="summary-value"><?php echo $stockSummary['lowStockCount'] ?? 0; ?></div>
                            <div class="summary-label">Stock Bas (≤10)</div>
                        </div>
                        <div class="summary-card out-stock">
                            <div class="summary-value"><?php echo $stockSummary['outOfStockCount'] ?? 0; ?></div>
                            <div class="summary-label">Rupture de Stock</div>
                        </div>
                        <div class="summary-card value">
                            <div class="summary-value"><?php echo formatPrice($stockSummary['totalStockValue'] ?? 0); ?></div>
                            <div class="summary-label">Valeur du Stock</div>
                        </div>
                    </div>

                    <!-- Main Sections -->
                    <div class="main-sections">
                        <!-- Create Delivery Section -->
                        <div class="section-card create-form-card">
                            <div class="section-header">
                                <h2 class="section-title">
                                    <i data-lucide="plus-circle"></i>
                                    Créer une Livraison
                                </h2>
                            </div>
                            <div class="section-content">
                                <form method="POST" id="createDeliveryForm">
                                    <input type="hidden" name="action" value="create_delivery">
                                    
                                    <div class="form-group">
                                        <label for="supplierId">Fournisseur *</label>
                                        <select id="supplierId" name="supplierId" required>
                                            <option value="">Sélectionner un fournisseur</option>
                                            <?php foreach ($suppliers as $supplier): ?>
                                                <option value="<?php echo $supplier['id']; ?>">
                                                    <?php echo htmlspecialchars($supplier['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="deliveryDate">Date de Livraison *</label>
                                        <input type="date" id="deliveryDate" name="deliveryDate" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <button type="submit" class="btn-primary">
                                        <i data-lucide="plus"></i>
                                        Créer la Livraison
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Deliveries List Section -->
                        <div class="section-card">
                            <div class="section-header">
                                <h2 class="section-title">
                                    <i data-lucide="package"></i>
                                    Liste des Livraisons (<?php echo count($deliveries); ?>)
                                </h2>
                            </div>
                            <div class="section-content">
                                <?php if (!empty($deliveries)): ?>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Livraison</th>
                                                    <th>Fournisseur</th>
                                                    <th>Statut</th>
                                                    <th>Articles</th>
                                                    <th>Valeur</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($deliveries as $delivery): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="delivery-info">
                                                                <strong>#<?php echo $delivery['id']; ?></strong>
                                                                <div class="delivery-meta">
                                                                    <?php echo date('d/m/Y', strtotime($delivery['deliveryDate'])); ?>
                                                                </div>
                                                                <div class="delivery-meta">
                                                                    Créé <?php echo timeAgo($delivery['createdAt']); ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($delivery['supplierName'] ?? 'N/A'); ?></td>
                                                        <td><?php echo getStatusBadge($delivery['status'], $delivery['itemCount']); ?></td>
                                                        <td>
                                                            <div class="stats-grid">
                                                                <div class="stat-item">
                                                                    <div><?php echo $delivery['itemCount'] ?? 0; ?></div>
                                                                    <div>Articles</div>
                                                                </div>
                                                                <div class="stat-item">
                                                                    <div><?php echo $delivery['validatedItems'] ?? 0; ?></div>
                                                                    <div>Validés</div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo formatPrice($delivery['totalValue'] ?? 0); ?></td>
                                                        <td>
                                                            <div class="action-buttons">
                                                                <a href="delivery_items.php?id=<?php echo $delivery['id']; ?>" class="btn-info">
                                                                    <i data-lucide="package-open"></i>
                                                                    Gérer
                                                                </a>
                                                                <?php if ($delivery['status'] === 'empty'): ?>
                                                                    <form method="POST" style="display: inline;">
                                                                        <input type="hidden" name="action" value="delete_delivery">
                                                                        <input type="hidden" name="deliveryId" value="<?php echo $delivery['id']; ?>">
                                                                        <button type="submit" class="btn-danger" onclick="return confirm('Supprimer cette livraison vide ?');">
                                                                            <i data-lucide="trash-2"></i>
                                                                        </button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="no-data">
                                        <i data-lucide="package"></i>
                                        <h3>Aucune livraison enregistrée</h3>
                                        <p>Commencez par créer votre première livraison.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

  <script>
        // Initialize Lucide icons - CORRECTED
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Form validation
            const createForm = document.getElementById('createDeliveryForm');
            if (createForm) {
                createForm.addEventListener('submit', function(e) {
                    const supplierId = document.getElementById('supplierId').value;
                    const deliveryDate = document.getElementById('deliveryDate').value;

                    if (!supplierId || !deliveryDate) {
                        e.preventDefault();
                        alert('Veuillez remplir tous les champs obligatoires.');
                        return false;
                    }
                });
            }

            // Sidebar overlay functionality
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.addEventListener('click', function() {
                    const sidebar = document.querySelector('.sidebar');
                    if (sidebar) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                });
            }
        });

        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar && overlay) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            }
        }
    </script>
</body>
</html>

<?php
} else {
    // Redirect to login if not admin
    header("Location: ../logout.php");
    exit();
}
?>