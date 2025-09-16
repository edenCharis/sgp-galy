<?php

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Include database config with error handling
try {
    include '../config/database.php';
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Check session and role
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "SELLER" || 
    !isset($_SESSION["id"]) || $_SESSION["id"] != session_id()) {
    header("location: ../logout.php");
    exit();
}

// Initialize variables with defaults
$sellerId = $_SESSION["user_id"] ?? 1;
$today = date('Y-m-d');
$cartCount = 0;
$totalValue = 0;

try {
    // Get seller's daily cart statistics
    $dailyStatsQuery = "
        SELECT 
            COUNT(c.id) as cart_count,
            COALESCE(SUM(
                (SELECT SUM(ci.quantity * ci.unit_price) FROM cart_items ci WHERE ci.cart_id = c.id)
            ), 0) as total_value
        FROM carts c 
        WHERE c.seller_id = ? 
        AND DATE(c.created_at) = ?
    ";
    
    // Check if $db object exists and has fetch method
    if (isset($db) && method_exists($db, 'fetch')) {
        $dailyStats = $db->fetch($dailyStatsQuery, [$sellerId, $today]);
        
        if ($dailyStats) {
            $cartCount = $dailyStats['cart_count'] ?? 0;
            $totalValue = $dailyStats['total_value'] ?? 0;
        }
    }
} catch (Exception $e) {
    // Log error but don't break the page
    error_log("Daily stats query error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Nouvelle Vente</title>
    
    <!-- Use same icon approach as dashboard -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/seller-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Select2 CSS and JS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0/js/select2.min.js"></script>

    <style>
        /* All your existing CSS styles remain the same */
        .app-layout {
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        
        #sidebar {
            position: fixed;
            left: 0;
            top: 0;
            height: 100vh;
            width: 280px;
            z-index: 1000;
            overflow-y: auto;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow: hidden;
        }
        
        .main-content .header {
            position: fixed;
            top: 0;
            right: 0;
            left: 280px;
            z-index: 999;
            background: white;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .content-area {
            flex: 1;
            overflow-y: auto;
            padding-top: 80px;
        }
        
        /* Daily Stats Card */
        .daily-stats {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);;
            color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            position: sticky;
            top: 20px;
            z-index: 10;
        }
        
        .daily-stats h4 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .stat-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .sale-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .sale-header {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            padding: 20px;
            border-radius: 12px;
            color: white;
            margin-bottom: 30px;
        }
        
        .sale-header h1 {
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sale-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .sale-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .product-search {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .product-search h3 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
        }
        
        .search-input {
            position: relative;
        }
        
        .search-input input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .search-input input:focus {
            outline: none;
            border-color: #059669;
        }
        
        .search-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: #059669;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .search-btn:hover {
            background: #047857;
        }
        
        .product-results {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #f3f4f6;
            transition: background-color 0.2s;
        }
        
        .product-item:hover {
            background-color: #f9fafb;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .product-details {
            font-size: 14px;
            color: #6b7280;
        }
        
        .product-price {
            font-weight: 700;
            color: #059669;
            margin-right: 15px;
        }
        
        .add-btn {
            background: #059669;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .add-btn:hover {
            background: #047857;
        }
        
        .cart-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            height: fit-content;
        }
        
        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .cart-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .cart-items {
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .cart-item-price {
            font-size: 14px;
            color: #6b7280;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }
        
        .quantity-btn {
            width: 30px;
            height: 30px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .quantity-btn:hover {
            background: #f3f4f6;
        }
        
        .quantity {
            min-width: 30px;
            text-align: center;
            font-weight: 600;
        }
        
        .item-total {
            font-weight: 700;
            color: #059669;
            min-width: 80px;
            text-align: right;
        }
        
        .remove-btn {
            color: #dc2626;
            background: none;
            border: none;
            padding: 5px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .cart-summary {
            border-top: 2px solid #f3f4f6;
            padding-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .summary-row.total {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
            margin-top: 15px;
        }
        
        .client-selection {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .client-selection h3 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
        }
        
        .client-search {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .cashier-selection {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }
        
        .cashier-selection h3 {
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #1f2937;
        }

        .cashier-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }

        .cashier-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }

        .cashier-status.open {
            background: #dcfce7;
            color: #166534;
        }

        .cashier-status.busy {
            background: #fef3c7;
            color: #92400e;
        }

        .cashier-status.closed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #059669;
        }
        
        .btn-primary {
            background: #059669;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary:hover {
            background: #047857;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-success {
            background: #10b981;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-success:hover {
            background: #059669;
        }
        
        .empty-cart {
            text-align: center;
            color: #6b7280;
            padding: 40px 20px;
        }
        
        .empty-cart i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }
        
        .alert-info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
        }

        .alert-warning {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        
        /* Select2 customization */
        .select2-container .select2-selection--single {
            height: 50px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 46px;
            padding-left: 12px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
        }
        
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #059669;
            outline: none;
        }
        
        .select2-dropdown {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .select2-results__option {
            padding: 10px 15px;
        }
        
        .select2-results__option--highlighted {
            background-color: #059669 !important;
        }
        
        /* Mobile responsive */
        @media (max-width: 1200px) {
            #sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            #sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content .header {
                left: 0;
            }
            
            .sale-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .client-search {
                flex-direction: column;
                align-items: stretch;
            }
            
            .sale-container {
                padding: 15px;
            }
        }
    </style>
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
                <div class="sale-container">
                    <!-- Sale Header -->
                    <div class="sale-header">
                        <h1><i data-lucide="plus-circle"></i> Nouvelle Vente</h1>
                        <p>Créez un panier pour votre client et assignez-le à un caissier</p>
                    </div>

                    <!-- Client Selection -->
                    <div class="client-selection">
                        <h3><i data-lucide="user"></i> Informations Client (Optionnel)</h3>
                        <div class="alert alert-info">
                            <i data-lucide="info"></i>
                            <span>La sélection d'un client est optionnelle. Vous pouvez procéder sans client.</span>
                        </div>
                        <div class="client-search">
                            <div class="form-group">
                                <label for="clientSelect">Rechercher/Sélectionner un client</label>
                                <select id="clientSelect" style="width: 100%;" onchange="displaySelectedClient(this.options[this.selectedIndex])">
                                    <option value="">-- Aucun client sélectionné --</option>
                                    <?php
                                    try {
                                        // Fetch clients from database with error handling
                                        if (isset($db) && method_exists($db, 'fetch')) {
                                            $clientQuery = "SELECT id, name, contact as tel FROM client ORDER BY name ASC";
                                            $clients = $db->fetchAll($clientQuery);
                                            
                                            if ($clients && is_array($clients) && count($clients) > 0) {
                                                // Handle both single row and multiple rows
                                                $clientsArray = isset($clients[0]) ? $clients : [$clients];
                                                
                                                foreach ($clientsArray as $client) {
                                                    $clientId = htmlspecialchars($client['id']);
                                                    $clientName = htmlspecialchars($client['name']); 
                                                    $clientTel = htmlspecialchars($client['tel'] ?? 'N/A');
                                                    echo "<option value='$clientId' data-name='$clientName' data-tel='$clientTel'>$clientName - $clientTel</option>";
                                                }
                                            }
                                        }
                                    } catch (Exception $e) {
                                        error_log("Client query error: " . $e->getMessage());
                                    }
                                    ?>
                                </select>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="openNewClientModal()">
                                <i data-lucide="user-plus"></i>
                                Nouveau Client
                            </button>
                        </div>
                        
                        <div id="selectedClient" class="alert alert-success" style="display: none; margin-top: 15px;">
                            <i data-lucide="check-circle"></i>
                            <span>Client sélectionné: <strong id="clientName"></strong></span>
                        </div>
                    </div>

                    <!-- Cashier Selection -->
                    <div class="cashier-selection">
                        <h3><i data-lucide="calculator"></i> Sélectionner un Caissier (Obligatoire)</h3>
                        <div class="alert alert-warning">
                            <i data-lucide="alert-triangle"></i>
                            <span>Vous devez sélectionner un caissier pour traiter cette vente.</span>
                        </div>
                        <div class="form-group">
                            <label for="cashierSelect">Choisir un caissier disponible</label>
                            <select id="cashierSelect" style="width: 100%;" onchange="displaySelectedCashier(this.options[this.selectedIndex])">
                                <option value="">-- Sélectionner un caissier --</option>
                                <?php
                                try {
                                    // Fetch available cashiers (those with open cash registers)
                                    $cashierQuery = "
                                        SELECT 
                                            cr.id as register_id,
                                           u.username as cashier_name,
                                            cr.opening_time,
                                            cr.status,
                                            cr.initial_amount,
                                            (SELECT COUNT(*) FROM carts c WHERE c.cash_register_id = cr.id AND c.status = 'PENDING') as pending_carts
                                        FROM cash_register cr
                                        JOIN user u ON u.id = cr.cashier_id
                                        WHERE cr.status = 'OPEN' AND u.role = 'CASHIER' AND DATE(cr.opening_time) = CURDATE()
                                        ORDER BY pending_carts ASC, u.username ASC
                                    ";
                                    
                                    $cashiers = $db->fetchAll($cashierQuery);
                                    
                                    if ($cashiers && is_array($cashiers) && count($cashiers) > 0) {
                                        $cashiersArray = isset($cashiers[0]) ? $cashiers : [$cashiers];
                                        
                                        foreach ($cashiersArray as $cashier) {
                                            $registerId = htmlspecialchars($cashier['register_id']);
                                            $cashierId = htmlspecialchars($cashier['cashier_id']);
                                            $cashierName = htmlspecialchars($cashier['cashier_name']);
                                            $openingTime = htmlspecialchars($cashier['opening_time']);
                                            $pendingCarts = (int)$cashier['pending_carts'];
                                            $initialAmount = number_format($cashier['initial_amount'], 0, ',', ' ');
                                            
                                            $statusText = $pendingCarts == 0 ? 'Libre' : "$pendingCarts panier(s) en attente";
                                            
                                            echo "<option value='$registerId' 
                                                    data-cashier-id='$cashierId' 
                                                    data-cashier-name='$cashierName' 
                                                    data-opening-time='$openingTime'
                                                    data-pending-carts='$pendingCarts'
                                                    data-initial-amount='$initialAmount'>
                                                    $cashierName - $statusText
                                                  </option>";
                                        }
                                    } else {
                                        echo "<option disabled>Aucun caissier disponible</option>";
                                    }
                                } catch (Exception $e) {
                                    error_log("Cashier query error: " . $e->getMessage());
                                    echo "<option disabled>Erreur de chargement ".$e->getMessage()."</option>";
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div id="selectedCashier" class="cashier-info" style="display: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong id="selectedCashierName">Caissier sélectionné</strong>
                                <span id="cashierStatus" class="cashier-status open">Libre</span>
                            </div>
                            <div style="font-size: 14px; color: #6b7280;">
                                <div>Paniers en attente: <span id="cashierPendingCarts"></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- New Client Modal -->
                    <div id="newClientModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
                        <div class="modal-content" style="background-color: white; margin: 15% auto; padding: 20px; border-radius: 8px; width: 80%; max-width: 500px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <h3 style="margin: 0;"><i data-lucide="user-plus"></i> Nouveau Client</h3>
                                <button onclick="closeNewClientModal()" style="background: none; border: none; cursor: pointer;">
                                    <i data-lucide="x"></i>
                                </button>
                            </div>
                            <form method="POST" action="traitement.php">
                                  <div class="form-group" style="margin-bottom: 15px;">
                                    <label for="clientNameInput">Nom</label>
                                    <input type="text" id="clientNameInput" name="name" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" >
                                </div>
                                <div class="form-group" style="margin-bottom: 15px;">
                                    <label for="clientPhoneInput">Téléphone</label>
                                    <input type="tel" id="clientPhoneInput" name="tel" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" minlength="8" maxlength="20" pattern="[0-9+\-\s]+">
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                                    <button type="button" class="btn-secondary" onclick="closeNewClientModal()">Annuler</button>
                                    <button type="submit" class="btn-primary">Créer</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Main Sale Grid -->
                    <div class="sale-grid">
                        <!-- Product Search Section -->
                        <div>
                            <!-- Product Search -->
                            <div class="product-search">
                                <h3><i data-lucide="search"></i> Rechercher des produits</h3>
                                <div class="search-input">
                                    <input type="text" id="productSearch" placeholder="Rechercher par nom, code-barres ou référence...">
                                    <button class="search-btn" onclick="searchProducts()">
                                        <i data-lucide="search"></i>
                                    </button>
                                </div>
                                
                                <div id="productResults" class="product-results">
                                    <?php
                                    try {
                                        // Fetch products from database with error handling
                                            $query = "SELECT code, name, sellingPrice, stock FROM product WHERE stock > 0 ORDER BY name ASC";
                                            $products = $db->fetchAll($query);
                                            
                                            if ($products && is_array($products) && count($products) > 0) {
                                                // Handle both single row and multiple rows
                                                $productsArray = isset($products[0]) ? $products : [$products];
                                                
                                                foreach ($productsArray as $product) {
                                                    $code = htmlspecialchars($product['code']);
                                                    $name = htmlspecialchars($product['name']);
                                                    $price = number_format($product['sellingPrice'], 2, '.', '');
                                                    $stock = (int)$product['stock'];
                                                    $priceXaf = number_format($product['sellingPrice'], 0, ',', ' ');
                                                    echo <<<HTML
                                                    <div class="product-item">
                                                        <div class="product-info">
                                                            <div class="product-name">{$name}</div>
                                                            <div class="product-details">Code: {$code} • Stock: {$stock} • PRIX: {$price} XAF</div>
                                                        </div>
                                                        <div class="product-price">{$priceXaf} XAF</div>
                                                        <button class="add-btn" onclick="addToCart('{$code}', '{$name}', {$price}, {$stock})">
                                                            <i data-lucide="plus"></i>
                                                        </button>
                                                    </div>
                                            HTML;
                                                }
                                            } else {
                                                echo '<div class="empty-cart"><i data-lucide="package"></i> Aucun produit disponible.</div>';
                                            }
                                        } catch (Exception $e) {
                                            error_log("Product query error: " . $e->getMessage());
                                            echo '<div class="empty-cart"><i data-lucide="alert-triangle"></i> Erreur lors du chargement des produits.</div>';
                                        }
                                 
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Cart Section -->
                        <div class="cart-section">
                            <div class="cart-header">
                                <div class="cart-title">
                                    <i data-lucide="shopping-cart"></i>
                                    Panier (<span id="cartCount">0</span>)
                                </div>
                                <button class="btn btn-danger" onclick="clearCart()" style="padding: 8px 12px; font-size: 14px;">
                                    <i data-lucide="trash-2"></i>
                                </button>
                            </div>

                            <div id="cartItems" class="cart-items">
                                <div class="empty-cart">
                                    <i data-lucide="shopping-cart"></i>
                                    <p>Votre panier est vide</p>
                                    <small>Recherchez et ajoutez des produits</small>
                                </div>
                            </div>

                            <div class="cart-summary">
                                <div class="summary-row">
                                    <span>Sous-total:</span>
                                    <span id="subtotal">0.00 XAF</span>
                                </div>
                                <div class="summary-row">
                                    <span>TVA (18%):</span>
                                    <span id="vat">0.00 XAF</span>
                                </div>
                                <div class="summary-row">
                                    <span>Remise:</span>
                                    <span id="discount">0.00 XAF</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span id="total">0.00 XAF</span>
                                </div>
                                
                                <button class="btn-success" onclick="sendToCashier()" style="margin-top: 20px;">
                                    <i data-lucide="send"></i>
                                    Envoyer au Caissier
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Set favicon - same as dashboard
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

        // Sidebar functionality - same as dashboard
        function setupSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            const sidebarClose = document.getElementById('sidebarClose');

            function showSidebar() {
                if (sidebar) sidebar.classList.add('show');
                if (overlay) overlay.classList.add('show');
            }

            function hideSidebar() {
                if (sidebar) sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }

            if (menuToggle) menuToggle.addEventListener('click', showSidebar);
            if (sidebarClose) sidebarClose.addEventListener('click', hideSidebar);
            if (overlay) overlay.addEventListener('click', hideSidebar);
        }

        let cart = [];
        let selectedClient = null;
        let selectedCashRegister = null;

        // Initialize app
        function initApp() {
            setFavicon();
            setupSidebar();
            
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            // Initialize Select2
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $('#clientSelect').select2({
                    placeholder: 'Rechercher un client...',
                    allowClear: true,
                    language: {
                        noResults: function() {
                            return "Aucun client trouvé";
                        },
                        searching: function() {
                            return "Recherche...";
                        }
                    }
                });
                
                $('#cashierSelect').select2({
                    placeholder: 'Sélectionner un caissier...',
                    language: {
                        noResults: function() {
                            return "Aucun caissier disponible";
                        }
                    }
                });
                
                // Handle client selection
                $('#clientSelect').on('select2:select', function(e) {
                    const data = e.params.data;
                    if (data.id) {
                        const option = $(this).find('option:selected');
                        selectedClient = {
                            id: data.id,
                            name: option.data('name'),
                            tel: option.data('tel')
                        };
                        showSelectedClient();
                    } else {
                        selectedClient = null;
                        hideSelectedClient();
                    }
                });
                
                $('#clientSelect').on('select2:clear', function(e) {
                    selectedClient = null;
                    hideSelectedClient();
                });

                // Handle cashier selection
                $('#cashierSelect').on('select2:select', function(e) {
                    const data = e.params.data;
                    if (data.id) {
                        const option = $(this).find('option:selected');
                        selectedCashRegister = {
                            id: data.id,
                            cashierId: option.data('cashier-id'),
                            cashierName: option.data('cashier-name'),
                            openingTime: option.data('opening-time'),
                            pendingCarts: option.data('pending-carts'),
                            initialAmount: option.data('initial-amount')
                        };
                        showSelectedCashier();
                    } else {
                        selectedCashRegister = null;
                        hideSelectedCashier();
                    }
                });

                $('#cashierSelect').on('select2:clear', function(e) {
                    selectedCashRegister = null;
                    hideSelectedCashier();
                });
            }
            
            // Add event listeners
            const productSearchInput = document.getElementById('productSearch');
            if (productSearchInput) {
                productSearchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        searchProducts();
                    }
                });
            }
        }

        // Display selected cashier
        function displaySelectedCashier(option) {
            if (option && option.value) {
                const cashierName = option.getAttribute('data-cashier-name');
                const openingTime = option.getAttribute('data-opening-time');
                const pendingCarts = option.getAttribute('data-pending-carts');
                const initialAmount = option.getAttribute('data-initial-amount');
                
                selectedCashRegister = {
                    id: option.value,
                    cashierId: option.getAttribute('data-cashier-id'),
                    cashierName: cashierName,
                    openingTime: openingTime,
                    pendingCarts: parseInt(pendingCarts),
                    initialAmount: initialAmount
                };
                
                showSelectedCashier();
            } else {
                selectedCashRegister = null;
                hideSelectedCashier();
            }
        }

        // Show selected cashier
        function showSelectedCashier() {
            if (selectedCashRegister) {
                document.getElementById('selectedCashier').style.display = 'block';
                document.getElementById('selectedCashierName').textContent = selectedCashRegister.cashierName;
                document.getElementById('cashierOpeningTime').textContent = new Date(selectedCashRegister.openingTime).toLocaleString('fr-FR');
                document.getElementById('cashierInitialAmount').textContent = selectedCashRegister.initialAmount;
                document.getElementById('cashierPendingCarts').textContent = selectedCashRegister.pendingCarts;
                
                const statusElement = document.getElementById('cashierStatus');
                if (selectedCashRegister.pendingCarts === 0) {
                    statusElement.textContent = 'Libre';
                    statusElement.className = 'cashier-status open';
                } else {
                    statusElement.textContent = `${selectedCashRegister.pendingCarts} panier(s)`;
                    statusElement.className = 'cashier-status busy';
                }
            }
        }

        // Hide selected cashier
        function hideSelectedCashier() {
            document.getElementById('selectedCashier').style.display = 'none';
        }

        // Product search
        function searchProducts() {
            const query = document.getElementById('productSearch').value.trim();
            if (query.length < 2) {
                showErrorModal('Veuillez saisir au moins 2 caractères pour la recherche');
                return;
            }
            
            // AJAX search implementation
            fetch('search_products.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({query: query})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySearchResults(data.products);
                } else {
                    console.error('Search error:', data.message);
                    displaySearchResults([]);
                }
            })
            .catch(error => {
                console.error('Search error:', error);
                displaySearchResults([]);
            });
        }

        // Display search results
        function displaySearchResults(products) {
            const resultsContainer = document.getElementById('productResults');
            
            if (products.length === 0) {
                resultsContainer.innerHTML = '<div class="empty-cart"><i data-lucide="search"></i> Aucun produit trouvé.</div>';
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                return;
            }

            resultsContainer.innerHTML = products.map(product => {
                const code = product.code;
                const name = product.name;
                const price = parseFloat(product.sellingPrice);
                const stock = parseInt(product.stock);
                const priceFormatted = price.toFixed(2);
                const priceXaf = price.toLocaleString('fr-FR', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                
                return `
                    <div class="product-item">
                        <div class="product-info">
                            <div class="product-name">${name}</div>
                            <div class="product-details">Code: ${code} • Stock: ${stock} • PRIX: ${priceFormatted} XAF</div>
                        </div>
                        <div class="product-price">${priceXaf} XAF</div>
                        <button class="add-btn" onclick="addToCart('${code}', '${name}', ${price}, ${stock})">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                `;
            }).join('');
            
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Add product to cart
        function addToCart(code, name, price, stock) {
            const existingItem = cart.find(item => item.code === code);
            
            if (existingItem) {
                if (existingItem.quantity < stock) {
                    existingItem.quantity++;
                    updateCart();
                } else {
                    showErrorModal('Stock insuffisant!');
                }
            } else {
                cart.push({
                    code: code,
                    name: name,
                    price: parseFloat(price),
                    quantity: 1,
                    stock: stock
                });
                updateCart();
            }
        }

        // Update cart display
        function updateCart() {
            const cartItemsContainer = document.getElementById('cartItems');
            const cartCount = document.getElementById('cartCount');
            
            if (cart.length === 0) {
                cartItemsContainer.innerHTML = `
                    <div class="empty-cart">
                        <i data-lucide="shopping-cart"></i>
                        <p>Votre panier est vide</p>
                        <small>Recherchez et ajoutez des produits</small>
                    </div>
                `;
                cartCount.textContent = '0';
            } else {
                cartItemsContainer.innerHTML = cart.map(item => `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <div class="cart-item-name">${item.name}</div>
                            <div class="cart-item-price">${item.price.toFixed(2)} XAF / unité</div>
                            <div class="quantity-controls">
                                <button class="quantity-btn" onclick="updateQuantity('${item.code}', -1)">
                                    <i data-lucide="minus"></i>
                                </button>
                                <span class="quantity">${item.quantity}</span>
                                <button class="quantity-btn" onclick="updateQuantity('${item.code}', 1)">
                                    <i data-lucide="plus"></i>
                                </button>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div class="item-total">${(item.price * item.quantity).toFixed(2)} XAF</div>
                            <button class="remove-btn" onclick="removeFromCart('${item.code}')">
                                <i data-lucide="trash-2"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
                
                cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
            }
            
            updateSummary();
            
            // Reinitialize icons after DOM update
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Update quantity
        function updateQuantity(code, change) {
            const item = cart.find(item => item.code === code);
            if (item) {
                const newQuantity = item.quantity + change;
                if (newQuantity <= 0) {
                    removeFromCart(code);
                } else if (newQuantity <= item.stock) {
                    item.quantity = newQuantity;
                    updateCart();
                } else {
                    alert('Stock insuffisant!');
                }
            }
        }

        // Remove from cart
        function removeFromCart(code) {
            cart = cart.filter(item => item.code !== code);
            updateCart();
        }

        // Clear cart
        function clearCart() {
            if (cart.length > 0 && confirm('Êtes-vous sûr de vouloir vider le panier?')) {
                cart = [];
                updateCart();
            }
        }

        // Update summary
        function updateSummary() {
            const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const vatRate = 0.18; // 18% VAT
            const vat = subtotal * vatRate;
            const discount = 0; // Can be modified based on client/insurance
            const total = subtotal + vat - discount;

            document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' XAF';
            document.getElementById('vat').textContent = vat.toFixed(2) + ' XAF';
            document.getElementById('discount').textContent = discount.toFixed(2) + ' XAF';
            document.getElementById('total').textContent = total.toFixed(2) + ' XAF';
        }

        // New client modal functions
        function openNewClientModal() {
            document.getElementById('newClientModal').style.display = 'block';
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        function closeNewClientModal() {
            document.getElementById('newClientModal').style.display = 'none';
            document.getElementById('clientNameInput').value = '';
            document.getElementById('clientPhoneInput').value = '';
        }

        function displaySelectedClient(option) {
            if (option && option.value) {
                const name = option.getAttribute('data-name');
                const tel = option.getAttribute('data-tel');
                document.getElementById('selectedClient').style.display = 'flex';
                document.getElementById('clientName').textContent = `${name} (${tel})`;
                selectedClient = {
                    id: option.value,
                    name: name,
                    tel: tel
                };
            } else {
                document.getElementById('selectedClient').style.display = 'none';
                selectedClient = null;
            }
        }

        // Create new client
      
        // Error modal helper function
        function showErrorModal(message) {
            const modalHTML = `
                <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:400px;">
                        <div style="color:#dc2626; margin-bottom:15px;">
                            <i data-lucide="alert-circle"></i>
                            <strong>Erreur</strong>
                        </div>
                        <p>${message}</p>
                        <button onclick="this.parentElement.parentElement.remove()" class="btn btn-primary" style="width:100%;">OK</button>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Success modal helper function 
        function showSuccessModal(message) {
            const modalHTML = `
                <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:400px;">
                        <div style="color:#059669; margin-bottom:15px;">
                            <i data-lucide="check-circle"></i>
                            <strong>Succès</strong>
                        </div>
                        <p>${message}</p>
                        <button onclick="this.parentElement.parentElement.remove()" class="btn-primary" style="width:100%;">OK</button>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Show selected client
        function showSelectedClient() {
            if (selectedClient) {
                document.getElementById('selectedClient').style.display = 'flex';
                document.getElementById('clientName').textContent = `${selectedClient.name} (${selectedClient.tel})`;
            }
        }

        // Hide selected client
        function hideSelectedClient() {
            document.getElementById('selectedClient').style.display = 'none';
        }

        // Update daily stats after successful cart creation
        function updateDailyStats() {
            fetch('get_daily_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const cartCountElement = document.querySelector('.daily-stats .stat-item:first-child .stat-value');
                        const totalValueElement = document.querySelector('.daily-stats .stat-item:last-child .stat-value');
                        
                        if (cartCountElement) {
                            cartCountElement.textContent = data.cart_count;
                        }
                        if (totalValueElement) {
                            totalValueElement.textContent = new Intl.NumberFormat('fr-FR').format(data.total_value) + ' XAF';
                        }
                    }
                })
                .catch(error => console.error('Error updating daily stats:', error));
        }

        // Send to cashier - Modified to require cashier selection
        function sendToCashier() {
            if (cart.length === 0) {
                showErrorModal('Le panier est vide! Veuillez ajouter au moins un produit.');
                return;
            }

            if (!selectedCashRegister) {
                showErrorModal('Veuillez sélectionner un caissier avant d\'envoyer le panier.');
                return;
            }

            const clientMessage = selectedClient ? 
                `Client: ${selectedClient.name}<br>` : 
                'Aucun client sélectionné<br>';
            
            const totalAmount = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 1.18;
            
            const modalHTML = `
                <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:500px;">
                        <h3 style="margin-bottom:20px;">Confirmer l'envoi</h3>
                        <div style="margin-bottom:20px;">
                            ${clientMessage}
                            <p><strong>Caissier:</strong> ${selectedCashRegister.cashierName}</p>
                            <p><strong>Total:</strong> ${totalAmount.toFixed(2)} XAF</p>
                            <p>Envoyer ce panier au caissier sélectionné?</p>
                        </div>
                        <div style="display:flex; justify-content:flex-end; gap:10px;">
                            <button onclick="this.closest('.modal').remove()" class="btn-secondary">Annuler</button>
                            <button onclick="proceedWithSale(); this.closest('.modal').remove();" class="btn-primary">Confirmer</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Proceed with sale
        function proceedWithSale() {
            const saleData = {
                items: cart,
                clientId: selectedClient ? selectedClient.id : null, 
                cashRegisterId: selectedCashRegister.id,
                sellerId: <?php echo json_encode($sellerId); ?>,
                subtotal: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0),
                totalVAT: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 0.18,
                discountAmount: 0,
                totalAmount: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 1.18
            };

            // Send data to create_cart.php
            fetch('create_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(saleData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showSuccessModal('Panier envoyé au caissier avec succès!');

                    // Reset form
                    cart = [];
                    selectedClient = null;
                    selectedCashRegister = null;
                    
                    // Reset select2 if available
                    if (typeof $ !== 'undefined' && $.fn.select2) {
                        $('#clientSelect').val(null).trigger('change');
                        $('#cashierSelect').val(null).trigger('change');
                    }
                    
                    hideSelectedClient();
                    hideSelectedCashier();
                    updateCart();
                    
                    // Update daily stats
                    updateDailyStats();

                    // Redirect to dashboard after delay
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                } else {
                    showErrorModal(data.message || 'Une erreur est survenue');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorModal('Une erreur est survenue lors de l\'envoi du panier');
            });
        }

        // Start the app when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initApp();
        });

        // Also support jQuery ready if available
        if (typeof $ !== 'undefined') {
            $(document).ready(function() {
                initApp();
            });
        }
    </script>

    <script>
        // Add these CSS classes to hide/show elements
const additionalCSS = `
    .hidden {
        display: none !important;
    }
    
    .btn-success:disabled {
        background: #9ca3af !important;
        cursor: not-allowed !important;
        opacity: 0.6 !important;
    }
`;

// Add the CSS to the page
function addCustomCSS() {
    const style = document.createElement('style');
    style.textContent = additionalCSS;
    document.head.appendChild(style);
}

// Modified function to check if cart can be sent
function canSendToCart() {
    return cart.length > 0 && selectedCashRegister !== null;
}

// Function to update button state
function updateSendButton() {
    const sendButton = document.querySelector('.btn-success');
    if (sendButton) {
        if (canSendToCart()) {
            sendButton.disabled = false;
            sendButton.innerHTML = '<i data-lucide="send"></i> Envoyer au Caissier';
        } else {
            sendButton.disabled = true;
            if (cart.length === 0 && !selectedCashRegister) {
                sendButton.innerHTML = '<i data-lucide="send"></i> Ajoutez des produits et sélectionnez un caissier';
            } else if (cart.length === 0) {
                sendButton.innerHTML = '<i data-lucide="send"></i> Ajoutez des produits au panier';
            } else if (!selectedCashRegister) {
                sendButton.innerHTML = '<i data-lucide="send"></i> Sélectionnez un caissier';
            }
        }
        // Reinitialize icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
}

// Modified showSelectedClient function
function showSelectedClient() {
    if (selectedClient) {
        document.getElementById('selectedClient').style.display = 'flex';
        document.getElementById('clientName').textContent = `${selectedClient.name} (${selectedClient.tel})`;
        // Hide the client info alert
        const clientAlert = document.querySelector('.client-selection .alert-info');
        if (clientAlert) {
            clientAlert.classList.add('hidden');
        }
    }
}

// Modified hideSelectedClient function
function hideSelectedClient() {
    document.getElementById('selectedClient').style.display = 'none';
    // Show the client info alert
    const clientAlert = document.querySelector('.client-selection .alert-info');
    if (clientAlert) {
        clientAlert.classList.remove('hidden');
    }
}

// Modified showSelectedCashier function
function showSelectedCashier() {
    if (selectedCashRegister) {
        document.getElementById('selectedCashier').style.display = 'block';
        document.getElementById('selectedCashierName').textContent = selectedCashRegister.cashierName;
        document.getElementById('cashierPendingCarts').textContent = selectedCashRegister.pendingCarts;
        
        const statusElement = document.getElementById('cashierStatus');
        if (selectedCashRegister.pendingCarts === 0) {
            statusElement.textContent = 'Libre';
            statusElement.className = 'cashier-status open';
        } else {
            statusElement.textContent = `${selectedCashRegister.pendingCarts} panier(s)`;
            statusElement.className = 'cashier-status busy';
        }
        
        // Hide the cashier warning alert
        const cashierAlert = document.querySelector('.cashier-selection .alert-warning');
        if (cashierAlert) {
            cashierAlert.classList.add('hidden');
        }
        
        // Update button state
        updateSendButton();
    }
}

// Modified hideSelectedCashier function
function hideSelectedCashier() {
    document.getElementById('selectedCashier').style.display = 'none';
    // Show the cashier warning alert
    const cashierAlert = document.querySelector('.cashier-selection .alert-warning');
    if (cashierAlert) {
        cashierAlert.classList.remove('hidden');
    }
    
    // Update button state
    updateSendButton();
}

// Modified updateCart function to include button state update
function updateCart() {
    const cartItemsContainer = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartCount');
    
    if (cart.length === 0) {
        cartItemsContainer.innerHTML = `
            <div class="empty-cart">
                <i data-lucide="shopping-cart"></i>
                <p>Votre panier est vide</p>
                <small>Recherchez et ajoutez des produits</small>
            </div>
        `;
        cartCount.textContent = '0';
    } else {
        cartItemsContainer.innerHTML = cart.map(item => `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">${item.price.toFixed(2)} XAF / unité</div>
                    <div class="quantity-controls">
                        <button class="quantity-btn" onclick="updateQuantity('${item.code}', -1)">
                            <i data-lucide="minus"></i>
                        </button>
                        <span class="quantity">${item.quantity}</span>
                        <button class="quantity-btn" onclick="updateQuantity('${item.code}', 1)">
                            <i data-lucide="plus"></i>
                        </button>
                    </div>
                </div>
                <div style="text-align: right;">
                    <div class="item-total">${(item.price * item.quantity).toFixed(2)} XAF</div>
                    <button class="remove-btn" onclick="removeFromCart('${item.code}')">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </div>
        `).join('');
        
        cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
    }
    
    updateSummary();
    updateSendButton(); // Add this line
    
    // Reinitialize icons after DOM update
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Modified sendToCashier function with improved validation
function sendToCashier() {
    if (!canSendToCart()) {
        if (cart.length === 0) {
            showErrorModal('Le panier est vide! Veuillez ajouter au moins un produit.');
        } else if (!selectedCashRegister) {
            showErrorModal('Veuillez sélectionner un caissier avant d\'envoyer le panier.');
        }
        return;
    }

    const clientMessage = selectedClient ? 
        `Client: ${selectedClient.name}<br>` : 
        'Aucun client sélectionné<br>';
    
    const totalAmount = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0) * 1.18;
    
    const modalHTML = `
        <div class="modal" style="display:block; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5);">
            <div class="modal-content" style="background-color:white; margin:15% auto; padding:20px; border-radius:8px; width:80%; max-width:500px;">
                <h3 style="margin-bottom:20px;">Confirmer l'envoi</h3>
                <div style="margin-bottom:20px;">
                    ${clientMessage}
                    <p><strong>Caissier:</strong> ${selectedCashRegister.cashierName}</p>
                    <p><strong>Total:</strong> ${totalAmount.toFixed(2)} XAF</p>
                    <p>Envoyer ce panier au caissier sélectionné?</p>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button onclick="this.closest('.modal').remove()" class="btn-secondary">Annuler</button>
                    <button onclick="proceedWithSale(); this.closest('.modal').remove();" class="btn-primary">Confirmer</button>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

// Modified displaySelectedCashier function
function displaySelectedCashier(option) {
    if (option && option.value) {
        const cashierName = option.getAttribute('data-cashier-name');
        const openingTime = option.getAttribute('data-opening-time');
        const pendingCarts = option.getAttribute('data-pending-carts');
        const initialAmount = option.getAttribute('data-initial-amount');
        
        selectedCashRegister = {
            id: option.value,
            cashierId: option.getAttribute('data-cashier-id'),
            cashierName: cashierName,
            openingTime: openingTime,
            pendingCarts: parseInt(pendingCarts),
            initialAmount: initialAmount
        };
        
        showSelectedCashier();
    } else {
        selectedCashRegister = null;
        hideSelectedCashier();
    }
}

// Modified initApp function to include the new CSS and initial button state
function initApp() {
    setFavicon();
    setupSidebar();
    addCustomCSS(); // Add this line
    
    // Initialize Lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    // Initialize Select2
    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#clientSelect').select2({
            placeholder: 'Rechercher un client...',
            allowClear: true,
            language: {
                noResults: function() {
                    return "Aucun client trouvé";
                },
                searching: function() {
                    return "Recherche...";
                }
            }
        });
        
        $('#cashierSelect').select2({
            placeholder: 'Sélectionner un caissier...',
            language: {
                noResults: function() {
                    return "Aucun caissier disponible";
                }
            }
        });
        
        // Handle client selection
        $('#clientSelect').on('select2:select', function(e) {
            const data = e.params.data;
            if (data.id) {
                const option = $(this).find('option:selected');
                selectedClient = {
                    id: data.id,
                    name: option.data('name'),
                    tel: option.data('tel')
                };
                showSelectedClient();
            } else {
                selectedClient = null;
                hideSelectedClient();
            }
        });
        
        $('#clientSelect').on('select2:clear', function(e) {
            selectedClient = null;
            hideSelectedClient();
        });

        // Handle cashier selection
        $('#cashierSelect').on('select2:select', function(e) {
            const data = e.params.data;
            if (data.id) {
                const option = $(this).find('option:selected');
                selectedCashRegister = {
                    id: data.id,
                    cashierId: option.data('cashier-id'),
                    cashierName: option.data('cashier-name'),
                    openingTime: option.data('opening-time'),
                    pendingCarts: option.data('pending-carts'),
                    initialAmount: option.data('initial-amount')
                };
                showSelectedCashier();
            } else {
                selectedCashRegister = null;
                hideSelectedCashier();
            }
        });

        $('#cashierSelect').on('select2:clear', function(e) {
            selectedCashRegister = null;
            hideSelectedCashier();
        });
    }
    
    // Set initial button state
    updateSendButton();
    
    // Add event listeners
    const productSearchInput = document.getElementById('productSearch');
    if (productSearchInput) {
        productSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchProducts();
            }
        });
    }
}
    </script>
</body>
</html>

<?php 
// Remove in production - this is for debugging only
if (error_get_last()) {
    error_log("PHP Error in sale page: " . print_r(error_get_last(), true));
} else {
    // echo "No errors"; --- IGNORE ---
}


                      