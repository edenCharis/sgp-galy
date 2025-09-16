<?php
session_start();
include 'database.php';



  
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      
        if(isset($_POST['username']) && isset($_POST['password'])){
            $username = $_POST['username'];
            $password = $_POST['password'];
            
           
            $query = "SELECT *, role FROM user WHERE username = :username AND password = :password";
            $user = $db->fetch($query, ['username' => $username, 'password' => $password]);
            
           
            if ($user) {
                

                
                $logQuery = "INSERT INTO log (userId, action, tableName, recordId, description, createdAt) 
                             VALUES (:iduser, 'login', 'users', :recordId, 'utilisateur connecté avec succ‘s', NOW())";
                $db->query($logQuery, [
                    'iduser' => $user['id'],
                    'recordId' => $user['id']
                ]);


                if($user["role"] === "SELLER")
                {

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['id'] = session_id();
                          header('Location: ./../seller/index.php');

                }else if ($user['role'] === 'CASHIER') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['id'] = session_id();
                    header('Location: ./../cashier/index.php');
                }else if ($user['role'] === 'ADMIN') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['id'] = session_id();
                    header('Location: ./../admin/index.php');
                }
                
                else{
                    header('Location: ./');
                }
                
              

                exit();
            } else {
                $_SESSION['error'] = 'Invalid username or password';
                header('Location: ../logout.php');
                exit();
            }
        }
    }

