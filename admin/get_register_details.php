<?php
// get_register_details.php - Récupération des détails de caisse via AJAX
session_start();

// Vérification des permissions
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "ADMIN" || $_SESSION["id"] != session_id()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès interdit']);
    exit;
}

// Headers pour JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Vérification de l'ID de caisse
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID de caisse invalide']);
        exit;
    }
    
    $register_id = (int)$_GET['id'];
    $format = $_GET['format'] ?? 'json';
    
    // Inclure la connexion à la base de données
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Connexion à la base de données non disponible');
    }
    
    // Récupérer les détails de la caisse
    $registerDetailsSQL = "SELECT 
                              cr.*,
                              u.username as cashier_name,
                              COALESCE(SUM(s.totalAmount), 0) as total_sales,
                              COALESCE(COUNT(s.id), 0) as sales_count,
                              CASE 
                                  WHEN cr.status = 'closed' THEN 
                                      TIME_FORMAT(TIMEDIFF(cr.closing_time, cr.opening_time), '%H:%i')
                                  ELSE 
                                      TIME_FORMAT(TIMEDIFF(NOW(), cr.opening_time), '%H:%i')
                              END as duration,
                              CASE 
                                  WHEN cr.status = 'closed' THEN 
                                      (cr.final_amount - (cr.initial_amount + COALESCE(SUM(s.totalAmount), 0)))
                                  ELSE 
                                      NULL
                              END as difference
                           FROM cash_register cr
                           LEFT JOIN user u ON cr.cashier_id = u.id
                           LEFT JOIN sale s ON cr.id = s.cash_register_id
                           WHERE cr.id = ?
                           GROUP BY cr.id, cr.cashier_id, u.username, cr.opening_time, 
                                   cr.closing_time, cr.initial_amount, cr.final_amount, cr.status";
    
    $register = $db->fetch($registerDetailsSQL, [$register_id]);
    
    if (!$register) {
        echo json_encode(['success' => false, 'message' => 'Caisse non trouvée']);
        exit;
    }
    
    // Récupérer les transactions récentes pour cette caisse
    $transactionsSQL = "SELECT 
                           s.id,
                           s.invoiceNumber as invoice_number,
                           s.totalAmount as total_amount,
                           s.saleDate,
                           TIME(s.saleDate) as time,
                           c.name as client_name,
                           u.username as seller_name
                        FROM sale s
                        LEFT JOIN client c ON s.clientId = c.id
                        LEFT JOIN user u ON s.sellerId = u.id
                        WHERE s.cash_register_id = ?
                        ORDER BY s.saleDate DESC
                        LIMIT 10";
    
    $transactions = $db->fetchAll($transactionsSQL, [$register_id]);
    
    if ($transactions === false) {
        $transactions = [];
    }
    
    // Formater les données
    $register_data = [
        'id' => (int)$register['id'],
        'cashier_name' => $register['cashier_name'],
        'status' => $register['status'],
        'opening_time' => date('d/m/Y H:i', strtotime($register['opening_time'])),
        'closing_time' => $register['closing_time'] ? date('d/m/Y H:i', strtotime($register['closing_time'])) : null,
        'initial_amount' => (float)$register['initial_amount'],
        'final_amount' => $register['final_amount'] ? (float)$register['final_amount'] : null,
        'total_sales' => (float)$register['total_sales'],
        'sales_count' => (int)$register['sales_count'],
        'duration' => $register['duration'],
        'difference' => $register['difference'] ? (float)$register['difference'] : 0,
        'recent_transactions' => []
    ];
    
    // Formater les transactions
    foreach ($transactions as $transaction) {
        $register_data['recent_transactions'][] = [
            'id' => (int)$transaction['id'],
            'invoice_number' => $transaction['invoice_number'],
            'total_amount' => (float)$transaction['total_amount'],
            'time' => date('H:i', strtotime($transaction['saleDate'])),
            'client_name' => $transaction['client_name'],
            'seller_name' => $transaction['seller_name']
        ];
    }
    
    // Réponse JSON
    echo json_encode([
        'success' => true,
        'register' => $register_data,
        'message' => 'Détails récupérés avec succès'
    ]);
    
} catch (Exception $e) {
    error_log("Erreur get_register_details.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de la récupération des détails: ' . $e->getMessage()
    ]);
}
?>