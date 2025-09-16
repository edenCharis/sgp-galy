<?php


session_start();


if(isset($_SESSION['user_id']) && isset($_SESSION['id']) === session_id())
{

    $logQuery = "INSERT INTO log (userId, action, tableName, recordId, description, createdAt) 
                             VALUES (:iduser, 'login', 'users', :recordId, 'utilisateur deconnecté avec succès', NOW())";
                $db->query($logQuery, [
                    'iduser' => $_SESSION['user_id'],
                    'recordId' => $_SESSION['user_id']
                ]);

                unset($_SESSION);

                header("location: index.php");
}else{

    header("location: index.php");
}

   
              