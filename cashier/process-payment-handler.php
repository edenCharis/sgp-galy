<?php
session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is a cashier
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "CASHIER") {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    // Get POST data
    $cartId = (string)$_POST['cartId'];
    $invoiceNumber = $_POST['invoiceNumber'];
    $subtotal = (float)$_POST['subtotal'];
    $totalVAT = (float)$_POST['totalVAT'];
    $discountAmount = (float)$_POST['discountAmount'];
    $totalAmount = (float)$_POST['totalAmount'];
    $cashReceived = (float)$_POST['cashReceived'];
    $updatedItems = json_decode($_POST['updatedItems'], true);

    // Validate required fields
    if (!$cartId || !$invoiceNumber || !$totalAmount) {
        throw new Exception('Données manquantes pour traiter le paiement');
    }

    // Validate cash received
    if ($cashReceived < $totalAmount) {
        throw new Exception('Le montant reçu doit être supérieur ou égal au total à payer');
    }

    // Calculate change
    $change = $cashReceived - $totalAmount;

    // Start transaction
    $db->beginTransaction();

    // Get cart details
    $cartQuery = "SELECT * FROM carts WHERE id = ? AND status = 'pending'";
    $cart = $db->fetch($cartQuery, [$cartId]);
    
    if (!$cart) {
        throw new Exception('Panier non trouvé ou déjà traité');
    }

    // Get cart items with product details
    $itemsQuery = "SELECT ci.*, p.name, p.code, p.sellingPrice, p.vatRate, p.stock
                   FROM cart_items ci
                   JOIN product p ON ci.product_id = p.id
                   WHERE ci.cart_id = ?";
    $cartItems = $db->fetchAll($itemsQuery, [$cartId]);

    if (!$cartItems) {
        throw new Exception('Aucun article trouvé dans le panier');
    }

    // Update quantities if modified
    if (!empty($updatedItems)) {
        foreach ($updatedItems as $updatedItem) {
            $itemId = (int)$updatedItem['itemId'];
            $newQuantity = (int)$updatedItem['quantity'];
            
            // Update cart item quantity
            $updateItemQuery = "UPDATE cart_items SET quantity = ? WHERE id = ?";
            $db->execute($updateItemQuery, [$newQuantity, $itemId]);
        }
    }

    // Check stock availability
    foreach ($cartItems as $item) {
        // Find updated quantity for this item
        $finalQuantity = $item['quantity'];
        if (!empty($updatedItems)) {
            foreach ($updatedItems as $updatedItem) {
                if ($updatedItem['itemId'] == $item['id']) {
                    $finalQuantity = (int)$updatedItem['quantity'];
                    break;
                }
            }
        }
        
        if ($finalQuantity > $item['stock']) {
            throw new Exception("Stock insuffisant pour {$item['name']}. Stock disponible: {$item['stock']}");
        }
    }

    // Generate UUID for sale ID
    $saleId = uniqid('SALE_', true);
    $cash_registerId = $cart['cash_register_id'];
    $currentDateTime = date('Y-m-d H:i:s');

    // Create sale record with cash details
    $saleQuery = "INSERT INTO sale (
        id, saleDate, totalAmount, totalVAT, discountAmount, invoiceNumber,
        cash_register_id, sellerId, clientId, cashReceived, changeAmount,
        createdAt, updatedAt
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $saleParams = [
        $saleId,
        date('Y-m-d'),
        $totalAmount,
        $totalVAT,
        $discountAmount,
        $invoiceNumber,
        $cash_registerId,
        $cart['seller_id'],
        $cart['client_id'],
        $cashReceived,
        $change,
        $currentDateTime,
        $currentDateTime
    ];

    // Debug: Log the parameter count
    error_log("Sale Query placeholders: " . substr_count($saleQuery, '?'));
    error_log("Sale Params count: " . count($saleParams));
    error_log("Sale Params: " . print_r($saleParams, true));

    $db->execute($saleQuery, $saleParams);
   
    // Create sale items and update product stock
    foreach ($cartItems as $item) {
        // Find final quantity for this item
        $finalQuantity = $item['quantity'];
        if (!empty($updatedItems)) {
            foreach ($updatedItems as $updatedItem) {
                if ($updatedItem['itemId'] == $item['id']) {
                    $finalQuantity = (int)$updatedItem['quantity'];
                    break;
                }
            }
        }

        // Calculate item totals
        $unitPrice = $item['sellingPrice'];
        $itemSubtotal = $finalQuantity * $unitPrice;
        $itemVAT = $itemSubtotal * ($item['vatRate'] / 100);
        $itemDiscount = 0; // You can implement item-level discounts later

        // Insert sale item
        $saleItemId = uniqid('SALEITEM_', true);
        $saleItemQuery = "INSERT INTO saleitem (
            id, saleId, productId, quantity, unitPrice, discount, vatAmount, createdAt, updatedAt
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $saleItemParams = [
            $saleItemId,
            $saleId,
            $item['product_id'],
            $finalQuantity,
            $unitPrice,
            $itemDiscount,
            $itemVAT,
            $currentDateTime,
            $currentDateTime
        ];

        // Debug: Log the parameter count
        error_log("SaleItem Query placeholders: " . substr_count($saleItemQuery, '?'));
        error_log("SaleItem Params count: " . count($saleItemParams));

        $db->execute($saleItemQuery, $saleItemParams);

        // Update product stock
        $updateStockQuery = "UPDATE product SET stock = stock - ? WHERE id = ?";
        $db->execute($updateStockQuery, [$finalQuantity, $item['product_id']]);
    }

    // Update cart status
    $updateCartQuery = "UPDATE carts SET status = 'completed', process_at = NOW() WHERE id = ?";
    $db->execute($updateCartQuery, [$cartId]);

    // Add log entry
    $logQuery = "INSERT INTO log (userId, action, tableName, recordId, description, createdAt) 
                 VALUES (?, ?, ?, ?, ?, NOW())";
    $logParams = [
        $_SESSION['user_id'], 
        'payment', 
        'sale', 
        $saleId, 
        "Paiement traité avec succès - Cash reçu: {$cashReceived} XAF, Rendu: {$change} XAF"
    ];
    
    $db->execute($logQuery, $logParams);

    // Commit transaction
    $db->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Vente finalisée avec succès',
        'invoiceNumber' => $invoiceNumber,
        'saleId' => $saleId,
        'totalAmount' => $totalAmount,
        'cashReceived' => $cashReceived,
        'change' => $change
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($db)) {
        $db->rollback();
    }
    
    error_log('Process payment error: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>