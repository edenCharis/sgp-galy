<?php
// get-order-details.php
session_start();
header('Content-Type: application/json');

if($_SESSION["role"] !== "SELLER" || $_SESSION["id"] !== session_id()){
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

try {
    // Include database connection
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    $orderId = (int)$_GET['id'];

    // Get order details
    $orderSql = "SELECT carts.id as cart_id,
                        carts.name as cart_name,
                        carts.status,
                        carts.created_at,
                        cl.name as client_name,
                        cl.id as client_id,
                        cl.contact as client_phone,
                       
                 FROM carts
                 LEFT JOIN client cl ON carts.client_id = cl.id
                 WHERE carts.id = ?";
    
    $orderResult = $db->fetchAll($orderSql, [$orderId]);
    
    if (empty($orderResult)) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }
    
    $order = $orderResult[0];

    // Get order items with product details
    $itemsSql = "SELECT ci.id as item_id,
                        ci.quantity,
                        ci.unit_price,
                        p.id as product_id,
                        p.name as product_name,
                        p.description as product_description,
                        p.code as product_code,
                        c.name as category_name,
                        (ci.quantity * ci.unit_price) as total_price
                 FROM cart_items ci
                 LEFT JOIN product p ON ci.product_id = p.id
                 LEFT JOIN category c ON p.categoryId = c.id
                 WHERE ci.cart_id = ?
                 ORDER BY p.name ASC";
    
    $items = $db->fetchAll($itemsSql, [$orderId]);
    
    if ($items === false) {
        $items = [];
    }

    // Calculate totals
    $totalAmount = 0;
    $totalItems = 0;
    
    foreach ($items as $item) {
        $totalAmount += $item['total_price'];
        $totalItems += $item['quantity'];
    }

    // Prepare response
    $response = [
        'order' => [
            'id' => $order['cart_id'],
            'name' => $order['cart_name'] ?: 'Commande sans nom',
            'status' => $order['status'],
            'created_at' => $order['created_at'],
            'formatted_date' => date('d/m/Y à H:i', strtotime($order['created_at'])),
            'client' => [
                'id' => $order['client_id'],
                'name' => $order['client_name'] ?: 'Client anonyme',
                'phone' => $order['client_phone'] ?: null
           
            ],
            'totals' => [
                'amount' => $totalAmount,
                'items_count' => $totalItems,
                'formatted_amount' => number_format($totalAmount, 0) . ' XAF'
            ]
        ],
        'items' => $items
    ];

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Get order details error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>