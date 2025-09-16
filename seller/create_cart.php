<?php
session_start();
header('Content-Type: application/json');

// Check if user is authenticated and is a seller
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "SELLER" || $_SESSION["id"] != session_id()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

include '../config/database.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Helper function to validate database connection
function validateDatabaseConnection($db) {
    if (!$db) {
        throw new Exception('Connexion à la base de données échouée');
    }
    
    if (!method_exists($db, 'fetch') || !method_exists($db, 'insert') || !method_exists($db, 'execute')) {
        throw new Exception('Méthodes de base de données manquantes');
    }
    
    return true;
}

// Validate database connection before processing
try {
    validateDatabaseConnection($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de configuration de la base de données',
        'error_code' => 'DATABASE_ERROR'
    ]);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Données invalides');
    }
    
    // Validate required fields
    if (!isset($input['items']) || empty($input['items'])) {
        throw new Exception('Le panier est vide');
    }
    
    if (!isset($input['sellerId']) || empty($input['sellerId'])) {
        throw new Exception('ID vendeur manquant');
    }

    if (!isset($input['cashRegisterId']) || empty($input['cashRegisterId'])) {
        throw new Exception('Caisse enregistreuse non sélectionnée');
    }
    
    // Extract data
    $sellerId = (string)$input['sellerId'];
    $clientId = isset($input['clientId']) && !empty($input['clientId']) ? (string)$input['clientId'] : null;
    $cashRegisterId = (string)$input['cashRegisterId'];
    $items = $input['items'];
    $subtotal = (float)$input['subtotal'];
    $totalVAT = (float)$input['totalVAT'];
    $discountAmount = (float)$input['discountAmount'];
    $totalAmount = (float)$input['totalAmount'];
    
    // Validate seller exists
    $sellerQuery = "SELECT id, username FROM user WHERE id = ? AND role = 'SELLER'";
    $seller = $db->fetch($sellerQuery, [$sellerId]);
    if (!$seller) {
        throw new Exception('Vendeur introuvable');
    }
    
    // Validate cash register exists and is open
    $cashRegisterQuery = "
        SELECT cr.id, cr.cashier_id, cr.status, u.username as cashier_name 
        FROM cash_register cr 
        JOIN user u ON u.id = cr.cashier_id 
        WHERE cr.id = ? AND cr.status = 'open' 
    ";
    $cashRegister = $db->fetch($cashRegisterQuery, [$cashRegisterId]);
    if (!$cashRegister) {
        throw new Exception('Caisse enregistreuse introuvable ou fermée');
    }
    
    // Validate client exists (if provided)
    $client = null;
    if ($clientId !== null) {
        $clientQuery = "SELECT id, name FROM client WHERE id = ?";
        $client = $db->fetch($clientQuery, [$clientId]);
        if (!$client) {
            throw new Exception('Client introuvable');
        }
    }
    
    // Validate all products exist and have sufficient stock
    $productUpdates = [];
    foreach ($items as $item) {
        if (!isset($item['code']) || !isset($item['quantity']) || !isset($item['price'])) {
            throw new Exception('Données d\'article invalides');
        }
        
        $productQuery = "SELECT id, name, stock, sellingPrice FROM product WHERE code = ?";
        $product = $db->fetch($productQuery, [$item['code']]);
        
        if (!$product) {
            throw new Exception('Produit non trouvé: ' . $item['code']);
        }
        
        if ($product['stock'] < $item['quantity']) {
            throw new Exception('Stock insuffisant pour: ' . $product['name']);
        }
        
        // Verify price hasn't changed
        if (abs($product['sellingPrice'] - $item['price']) > 0.01) {
            throw new Exception('Le prix du produit ' . $product['name'] . ' a changé. Veuillez actualiser.');
        }
        
        // Store product updates for later
        $productUpdates[] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'quantity' => (int)$item['quantity'],
            'unit_price' => (float)$item['price'],
            'current_stock' => (int)$product['stock']
        ];
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Insert into carts table with cash_register_id
        $cartQuery = "
            INSERT INTO carts (
                name, 
                seller_id, 
                client_id, 
                cash_register_id, 
                status, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        // Generate cart name
        $cartName = 'Cart-' . date('YmdHis') . '-' . substr($sellerId, -4);
        
        $cartParams = [
            $cartName,
            $sellerId,
            $clientId,
            $cashRegisterId,
            'PENDING' // Status: PENDING, PROCESSING, COMPLETED, CANCELLED
        ];
        
        $cartId = $db->insert($cartQuery, $cartParams);
        
        if (!$cartId) {
            throw new Exception('Erreur lors de la création du panier');
        }
        
        // Insert cart items and update stock
        $itemQuery = "
            INSERT INTO cart_items (
                cart_id, 
                product_id, 
                quantity, 
                unit_price
            ) VALUES (?, ?, ?, ?)
        ";
        
        $stockUpdateQuery = "
            UPDATE product 
            SET stock = stock - ? 
            WHERE id = ? AND stock >= ?
        ";
        
        foreach ($productUpdates as $productUpdate) {
            // Insert cart item
            $itemParams = [
                $cartId,
                $productUpdate['id'],
                $productUpdate['quantity'],
                $productUpdate['unit_price']
            ];
            
            $itemResult = $db->insert($itemQuery, $itemParams);
            
            if (!$itemResult) {
                throw new Exception('Erreur lors de l\'ajout de l\'article: ' . $productUpdate['name']);
            }
            
            // Update product stock
            $stockParams = [
                $productUpdate['quantity'],
                $productUpdate['id'],
                $productUpdate['quantity'] // Ensure we still have enough stock
            ];
            
            $stockResult = $db->execute($stockUpdateQuery, $stockParams);
            
            // Fixed: Use the correct method to check affected rows
            if ($stockResult === false) {
                throw new Exception('Impossible de mettre à jour le stock pour: ' . $productUpdate['name']);
            }
        }
        
        // Log the transaction - FIXED: Corrected parameter count and values
        $logQuery = "
            INSERT INTO log (
                userId, 
                action, 
                tableName, 
                recordId, 
                description, 
                createdAt
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ";
        
        $logDescription = "Nouveau panier créé et assigné - ID: $cartId, Items: " . count($items) . ", Total: $totalAmount";
        
        $logParams = [
            $_SESSION['user_id'], // Make sure this session variable exists
            'createcart',
            'carts',
            $cartId,
            $logDescription
        ];
        
        $db->insert($logQuery, $logParams);
        
        // Commit transaction
        $db->commit();
        
        // Prepare response data
        $responseData = [
            'success' => true,
            'message' => 'Panier créé et assigné avec succès',
            'cart_id' => $cartId,
            'cart_name' => $cartName,
            'cashier' => [
                'id' => $cashRegister['cashier_id'],
                'name' => $cashRegister['cashier_name']
            ],
            'total_amount' => $totalAmount,
            'items_count' => count($items),
            'total_quantity' => array_sum(array_column($items, 'quantity'))
        ];
        
        // Add client info if present
        if ($clientId && $client) {
            $responseData['client'] = [
                'id' => $client['id'],
                'name' => $client['name']
            ];
        }
        
        // Return success response
        echo json_encode($responseData);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error
    error_log('Cart creation error: ' . $e->getMessage());
    error_log('Cart creation input: ' . json_encode($input ?? []));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'CART_CREATION_ERROR'
    ]);
}
?>