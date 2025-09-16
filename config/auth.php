
<?php
/**
 * OTP Authentication System
 * File: config/auth.php
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'database.php';

class OTPAuth {
    private $db;
    private $otpExpiry = 300; // 5 minutes in seconds
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Generate a 6-digit OTP
     */
    private function generateOTP() {
        return sprintf("%06d", mt_rand(100000, 999999));
    }
    
    /**
     * Store OTP in database
     */
    private function storeOTP($userId, $otp) {
        $expiryTime = date('Y-m-d H:i:s', time() + $this->otpExpiry);
        
        // Delete any existing OTP for this user
        $deleteQuery = "DELETE FROM user_otp WHERE user_id = :user_id";
        $this->db->query($deleteQuery, ['user_id' => $userId]);
        
        // Insert new OTP
        $insertQuery = "INSERT INTO user_otp (user_id, otp_code, expires_at, created_at) 
                       VALUES (:user_id, :otp_code, :expires_at, NOW())";
        
        return $this->db->query($insertQuery, [
            'user_id' => $userId,
            'otp_code' => password_hash($otp, PASSWORD_DEFAULT), // Hash the OTP
            'expires_at' => $expiryTime
        ]);
    }
    
    /**
     * Send OTP via email (you'll need to implement actual email sending)*/
        private function sendOTPEmail($email, $username, $otp) {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';  // Replace with your SMTP host
            $mail->SMTPAuth = true;
            $mail->Username = 'your-email@gmail.com'; // Replace with your email
            $mail->Password = 'your-app-password';    // Replace with your app password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('your-email@gmail.com', 'Pharmacie');
            $mail->addAddress($email, $username);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Code de vérification - Pharmacie';
            $mail->Body = "
                <h2>Bonjour $username,</h2>
                <p>Votre code de vérification est: <strong>$otp</strong></p>
                <p>Ce code expire dans 5 minutes.</p>
                <p>Si vous n'avez pas demandé ce code, ignorez cet email.</p>
                <br>
                <p>Cordialement,<br>L'équipe Pharmacie</p>
            ";
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: {$mail->ErrorInfo}");
            return false;
        }
    }
    
    /**
     * Authenticate user credentials (step 1)
     */
    public function authenticateCredentials($username, $password) {
        $query = "SELECT id, username, email, role, password FROM user WHERE username = :username";
        $user = $this->db->fetch($query, ['username' => $username]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Nom d\'utilisateur incorrect'];
        }
        
        // Verify password (direct comparison since password is not hashed)
        if ($password !== $user['password']) {
            return ['success' => false, 'message' => 'Mot de passe incorrect'];
        }
        
        // Generate and send OTP
        $otp = $this->generateOTP();
        
        if ($this->storeOTP($user['id'], $otp)) {
            // In production, send via email
            $this->sendOTPEmail($user['email'], $user['username'], $otp);
            
            // Store user info in session for OTP verification
            $_SESSION['temp_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            return [
                'success' => true, 
                'message' => 'Code OTP envoyé à votre email',
                'otp_debug' => $otp // Remove this in production!
            ];
        }
        
        return ['success' => false, 'message' => 'Erreur lors de l\'envoi du code OTP'];
    }
    
    /**
     * Verify OTP (step 2)
     */
    public function verifyOTP($inputOTP) {
        if (!isset($_SESSION['temp_user'])) {
            return ['success' => false, 'message' => 'Session expirée. Reconnectez-vous.'];
        }
        
        $userId = $_SESSION['temp_user']['id'];
        
        $query = "SELECT otp_code, expires_at FROM user_otp 
                 WHERE user_id = :user_id AND expires_at > NOW() 
                 ORDER BY created_at DESC LIMIT 1";
        
        $otpRecord = $this->db->fetch($query, ['user_id' => $userId]);
        
        if (!$otpRecord) {
            return ['success' => false, 'message' => 'Code OTP expiré ou invalide'];
        }
        
        // Verify OTP
        if (password_verify($inputOTP, $otpRecord['otp_code'])) {
            // OTP is valid - complete login
            $user = $_SESSION['temp_user'];
            
            // Clear temporary session data
            unset($_SESSION['temp_user']);
            
            // Delete used OTP
            $deleteQuery = "DELETE FROM user_otp WHERE user_id = :user_id";
            $this->db->query($deleteQuery, ['user_id' => $userId]);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['id'] = session_id();
            $_SESSION['login_time'] = time();
            
            // Log successful login
            $logQuery = "INSERT INTO log (userId, action, tableName, recordId, description, createdAt)
                        VALUES (:iduser, 'login_success', 'user', :recordId, 'Utilisateur connecté avec OTP', NOW())";
            $this->db->query($logQuery, [
                'iduser' => $user['id'],
                'recordId' => $user['id']
            ]);
            
            return [
                'success' => true,
                'role' => $user['role'],
                'message' => 'Connexion réussie'
            ];
        }
        
        return ['success' => false, 'message' => 'Code OTP incorrect'];
    }
    
    /**
     * Resend OTP
     */
    public function resendOTP() {
        if (!isset($_SESSION['temp_user'])) {
            return ['success' => false, 'message' => 'Session expirée. Reconnectez-vous.'];
        }
        
        $user = $_SESSION['temp_user'];
        $otp = $this->generateOTP();
        
        if ($this->storeOTP($user['id'], $otp)) {
            $this->sendOTPEmail($user['email'], $user['username'], $otp);
            
            return [
                'success' => true,
                'message' => 'Nouveau code OTP envoyé',
                'otp_debug' => $otp // Remove this in production!
            ];
        }
        
        return ['success' => false, 'message' => 'Erreur lors de l\'envoi du code OTP'];
    }
}

// Initialize OTP Auth
$otpAuth = new OTPAuth($db);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Step 1: Verify credentials and send OTP
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        if (isset($_POST['username']) && isset($_POST['password'])) {
            $result = $otpAuth->authenticateCredentials($_POST['username'], $_POST['password']);
            echo json_encode($result);
            exit();
        }
    }
    
    // Step 2: Verify OTP
    if (isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
        if (isset($_POST['otp'])) {
            $result = $otpAuth->verifyOTP($_POST['otp']);
            
            if ($result['success']) {
                // Redirect based on role
                $redirectUrl = '';
                switch ($result['role']) {
                    case 'SELLER':
                        $redirectUrl = 'seller/index.php';
                        break;
                    case 'CASHIER':
                        $redirectUrl = 'cashier/index.php';
                        break;
                    case 'ADMIN':
                        $redirectUrl = 'admin/index.php';
                        break;
                    default:
                        $redirectUrl = '../';
                }
                $result['redirect'] = $redirectUrl;
            }
            
            echo json_encode($result);
            exit();
        }
    }
    
    // Resend OTP
    if (isset($_POST['action']) && $_POST['action'] === 'resend_otp') {
        $result = $otpAuth->resendOTP();
        echo json_encode($result);
        exit();
    }
}

// If not a POST request, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
?>

