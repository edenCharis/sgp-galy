<?php 
session_start(); 
header('Content-Type: application/json');

// Check if user is authenticated and is a seller
// FIXED: Removed problematic session_id() check
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "SELLER") {
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

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['query'])) {
        throw new Exception('Paramètre de recherche manquant');
    }
    
    $query = trim($input['query']);
    
    if (strlen($query) < 2) {
        throw new Exception('La recherche doit contenir au moins 2 caractères');
    }
    
    // DEBUGGING: Log the search query
    error_log('Searching for: ' . $query);
    
    // Prepare search query - search by name, code, or barcode
    $searchTerm = '%' . $query . '%';
    $exactStart = $query . '%'; // For exact matches at start
    
    // FIXED: Corrected SQL query with proper parameter count
    $sql = "
        SELECT code, name, sellingPrice, stock 
        FROM product 
        WHERE stock > 0 
        AND (
            name LIKE ? OR 
            code LIKE ?
        )
        ORDER BY 
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN code LIKE ? THEN 2
                ELSE 3
            END,
            name ASC
        LIMIT 20
    ";
    
    // FIXED: Proper parameter array matching SQL placeholders
    $params = [
        $searchTerm,    // name LIKE ?
        $searchTerm,    // code LIKE ?
        $exactStart,    // ORDER BY name LIKE ? (for exact start matches)
        $exactStart     // ORDER BY code LIKE ? (for exact start matches)
    ];
    
    // DEBUGGING: Log SQL and parameters
    error_log('SQL: ' . $sql);
    error_log('Params: ' . print_r($params, true));
    
    $products = $db->fetchAll($sql, $params);
    
    // DEBUGGING: Log raw results
    error_log('Raw products result: ' . print_r($products, true));
    
    if (!$products) {
        $products = [];
    }
    
    // Format products for response
    $formattedProducts = [];
    foreach ($products as $product) {
        $formattedProducts[] = [
            'code' => $product['code'],
            'name' => $product['name'],
            'sellingPrice' => floatval($product['sellingPrice']),
            'stock' => intval($product['stock'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'products' => $formattedProducts,
        'count' => count($formattedProducts)
    ]);
    
} catch (Exception $e) {
    // Log error with more details
    error_log('Product search error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'products' => []
    ]);
}

// DEBUGGING: Add a simple test query to verify database connection
try {
    $testQuery = "SELECT COUNT(*) as total FROM product WHERE stock > 0";
    $testResult = $db->fetchAll($testQuery);
    error_log('Total products with stock > 0: ' . print_r($testResult, true));
} catch (Exception $e) {
    error_log('Database test failed: ' . $e->getMessage());
}
?>