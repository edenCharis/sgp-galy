<?php
session_start();
include '../config/database.php';

// Check session and role authorization
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "SELLER" || !isset($_SESSION["id"]) || $_SESSION["id"] !== session_id()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    $db = new Database();
    
    if (isset($_GET['query']) && !empty(trim($_GET['query']))) {
        $searchQuery = trim($_GET['query']);
        
        // Search clients by name, phone, or card number
        $query = "SELECT id, name, contact 
                  FROM client 
                  WHERE name LIKE :search 
                     OR tel LIKE :search 
                     OR cardNumber LIKE :search
                  ORDER BY name ASC 
                  LIMIT 10";
        
        $searchParam = '%' . $searchQuery . '%';
        $clients = $db->fetchAll($query, ['search' => $searchParam]);
        
        if ($clients) {
            $response = [];
            foreach ($clients as $client) {
                $response[] = [
                    'id' => $client['id'],
                    'name' => htmlspecialchars($client['name']),
                    'contact' => htmlspecialchars($client['contact'])
                ];
            }
            echo json_encode(['success' => true, 'clients' => $response]);
        } else {
            echo json_encode(['success' => true, 'clients' => []]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Query parameter required']);
    }
    
} catch (Exception $e) {
    error_log("Error in search_clients.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Search error']);
}
?>