<?php
session_start();

include '../config/database.php';

 


try {


   
        if (isset($_POST['name']) && isset($_POST['tel']) && !empty($_POST['name']) && !empty($_POST['tel'])) {
            $client_name = trim($_POST['name']);
            $client_tel = trim($_POST['tel']);
            // Insert client
            $query = "INSERT INTO client (name, contact) VALUES (:name, :tel)";
            $result = $db->query($query, ['name' => $client_name, 'tel' => $client_tel]);
            
            if ($result) {
                $clientId = $pdo->lastInsertId();
                
                // Insert log entry (only if user is logged in)
                if (isset($_SESSION['user_id'])) {
                    $logQuery = "INSERT INTO log (userId, action, tableName, recordId, description, createdAt) 
                                VALUES (:iduser, 'createquote', 'client', :recordId, 'Nouveau client ajouté', NOW())";
                    
                    $db->query($logQuery, [
                        'iduser' => $_SESSION['user_id'],
                        'recordId' => $clientId
                    ]);
                }
                
                header('Location: sales.php');
                exit();
            } else {
                echo "Erreur lors de l'insertion du client.";
            }
        } else {
            echo "Les champs nom et téléphone sont requis et ne peuvent pas être vides.";
        }
  
} catch (Exception $e) {
     echo $e->getMessage();
    echo "Une erreur s'est produite. Veuillez réessayer.";
}
?>