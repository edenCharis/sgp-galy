<?php
session_start();
if($_SESSION["role"] === "SELLER" && $_SESSION["id"] == session_id()){

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include database connection
    include '../config/database.php';
    
    // Check if database connection exists
    if (!isset($pdo)) {
        throw new Exception('Database connection not found');
    }

    $user_id = $_SESSION['user_id'];
    $success_message = '';
    $error_message = '';

    // Get current user information
    $userSQL = "SELECT id, username, email, role, createdAt, updatedAt, statut FROM user WHERE id = ?";
    $stmt = $pdo->prepare($userSQL);
    $stmt->execute([$user_id]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$currentUser) {
        throw new Exception('Utilisateur non trouvé');
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_profile':
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    
                    if (!empty($username) && !empty($email)) {
                        // Check if username already exists for other users
                        $checkUsernameSQL = "SELECT COUNT(*) FROM user WHERE username = ? AND id != ?";
                        $stmt = $pdo->prepare($checkUsernameSQL);
                        $stmt->execute([$username, $user_id]);
                        $usernameExists = $stmt->fetchColumn();
                        
                        // Check if email already exists for other users
                        $checkEmailSQL = "SELECT COUNT(*) FROM user WHERE email = ? AND id != ?";
                        $stmt = $pdo->prepare($checkEmailSQL);
                        $stmt->execute([$email, $user_id]);
                        $emailExists = $stmt->fetchColumn();
                        
                        if ($usernameExists > 0) {
                            $error_message = "Ce nom d'utilisateur est déjà utilisé.";
                        } elseif ($emailExists > 0) {
                            $error_message = "Cette adresse e-mail est déjà utilisée.";
                        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            $error_message = "Format d'e-mail invalide.";
                        } else {
                            $updateSQL = "UPDATE user SET username = ?, email = ?, updatedAt = NOW() WHERE id = ?";
                            $stmt = $pdo->prepare($updateSQL);
                            $result = $stmt->execute([$username, $email, $user_id]);
                            
                            if ($result) {
                                $success_message = "Profil mis à jour avec succès.";
                                // Update current user data
                                $currentUser['username'] = $username;
                                $currentUser['email'] = $email;
                                $currentUser['updatedAt'] = date('Y-m-d H:i:s');
                                
                                // Update session username if needed
                                $_SESSION['username'] = $username;
                            } else {
                                $error_message = "Erreur lors de la mise à jour du profil.";
                            }
                        }
                    } else {
                        $error_message = "Veuillez remplir tous les champs obligatoires.";
                    }
                    break;

                case 'change_password':
                    $currentPassword = $_POST['currentPassword'];
                    $newPassword = $_POST['newPassword'];
                    $confirmPassword = $_POST['confirmPassword'];
                    
                    if (!empty($currentPassword) && !empty($newPassword) && !empty($confirmPassword)) {
                        // Get current password hash
                        $passwordSQL = "SELECT password FROM user WHERE id = ?";
                        $stmt = $pdo->prepare($passwordSQL);
                        $stmt->execute([$user_id]);
                        $currentHash = $stmt->fetchColumn();
                        
                        if (password_verify($currentPassword, $currentHash)) {
                            if ($newPassword === $confirmPassword) {
                                if (strlen($newPassword) >= 6) {
                                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                                    $updatePasswordSQL = "UPDATE user SET password = ?, updatedAt = NOW() WHERE id = ?";
                                    $stmt = $pdo->prepare($updatePasswordSQL);
                                    $result = $stmt->execute([$newHash, $user_id]);
                                    
                                    if ($result) {
                                        $success_message = "Mot de passe modifié avec succès.";
                                    } else {
                                        $error_message = "Erreur lors du changement de mot de passe.";
                                    }
                                } else {
                                    $error_message = "Le nouveau mot de passe doit contenir au moins 6 caractères.";
                                }
                            } else {
                                $error_message = "Les nouveaux mots de passe ne correspondent pas.";
                            }
                        } else {
                            $error_message = "Mot de passe actuel incorrect.";
                        }
                    } else {
                        $error_message = "Veuillez remplir tous les champs du mot de passe.";
                    }
                    break;
            }
        }
    }

    // Get user activity stats
    $activitySQL = "SELECT 
                        COUNT(DISTINCT DATE(createdAt)) as activeDays,
                        MAX(updatedAt) as lastActivity
                    FROM delivery 
                    WHERE createdAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $pdo->prepare($activitySQL);
    $stmt->execute();
    $userActivity = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Helper functions
function timeAgo($datetime) {
    if (!$datetime) return 'Jamais';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Il y a quelques secondes';
    if ($time < 3600) return 'Il y a ' . floor($time/60) . ' min';
    if ($time < 86400) return 'Il y a ' . floor($time/3600) . 'h ' . floor(($time%3600)/60) . 'min';
    return 'Il y a ' . floor($time/86400) . ' jour(s)';
}

function getStatusBadge($status) {
    switch ($status) {
        case 1:
            return '<span class="badge badge-success">Actif</span>';
        case 0:
            return '<span class="badge badge-secondary">Inactif</span>';
           default:
            return '<span class="badge badge-secondary">Inconnu</span>';
    }
}

function getRoleBadge($role) {
    switch ($role) {
        case 'STOCK-MANAGER':
            return '<span class="badge badge-primary">Gestionnaire de Stock</span>';
        case 'ADMIN':
            return '<span class="badge badge-warning">Administrateur</span>';
        case 'USER':
            return '<span class="badge badge-info">Utilisateur</span>';
        default:
            return '<span class="badge badge-secondary">' . htmlspecialchars($role) . '</span>';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Mon Profil</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        .profile-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .profile-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .profile-main {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background: #f9fafb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-content {
            padding: 1.5rem;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 700;
            margin: 0 auto 1.5rem;
        }

        .profile-info {
            text-align: center;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .profile-email {
            color: #6b7280;
            margin-bottom: 1rem;
        }

        .profile-badges {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .info-grid {
            display: grid;
            gap: 1rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 8px;
        }

        .info-label {
            font-weight: 600;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-value {
            color: #1f2937;
            font-weight: 500;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .form-group input:disabled {
            background-color: #f3f4f6;
            color: #6b7280;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            width: 100%;
            justify-content: center;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #f3f4f6; color: #374151; }

        .alert {
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
            border: none;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-card {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.75rem;
        }

        .password-requirements {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .password-requirements li {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }

        @media (max-width: 768px) {
            .profile-container {
                padding: 1rem;
            }
            
            .profile-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include 'header.php'; ?>
            
            <!-- Content Area -->
            <main class="content-area">
                <div class="profile-container">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h1 class="page-title">
                            <i data-lucide="user"></i>
                            Mon Profil
                        </h1>
                    </div>

                    <!-- Alerts -->
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i data-lucide="check-circle"></i>
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i data-lucide="alert-circle"></i>
                            <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Profile Grid -->
                    <div class="profile-grid">
                        <!-- Profile Sidebar -->
                        <div class="profile-sidebar">
                            <!-- Profile Card -->
                            <div class="section-card">
                                <div class="section-content">
                                    <div class="profile-avatar">
                                        <?php echo strtoupper(substr($currentUser['username'], 0, 2)); ?>
                                    </div>
                                    <div class="profile-info">
                                        <div class="profile-name"><?php echo htmlspecialchars($currentUser['username']); ?></div>
                                        <div class="profile-email"><?php echo htmlspecialchars($currentUser['email']); ?></div>
                                        <div class="profile-badges">
                                            <?php echo getRoleBadge($currentUser['role']); ?>
                                            <?php echo getStatusBadge($currentUser['statut']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Account Info -->
                            <div class="section-card">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i data-lucide="info"></i>
                                        Informations du Compte
                                    </h3>
                                </div>
                                <div class="section-content">
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">
                                                <i data-lucide="hash"></i>
                                                ID Utilisateur
                                            </span>
                                            <span class="info-value">#<?php echo $currentUser['id']; ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">
                                                <i data-lucide="calendar-plus"></i>
                                                Membre depuis
                                            </span>
                                            <span class="info-value"><?php echo date('d/m/Y', strtotime($currentUser['createdAt'])); ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">
                                                <i data-lucide="clock"></i>
                                                Dernière modification
                                            </span>
                                            <span class="info-value"><?php echo timeAgo($currentUser['updatedAt']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Activity Stats -->
                            
                        </div>

                        <!-- Profile Main -->
                        <div class="profile-main">
                            <!-- Edit Profile -->
                            <div class="section-card">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i data-lucide="edit-3"></i>
                                        Modifier le Profil
                                    </h3>
                                </div>
                                <div class="section-content">
                                    <form method="POST" id="profileForm">
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div class="form-group">
                                            <label for="username">Nom d'utilisateur *</label>
                                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="email">Adresse e-mail *</label>
                                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="role">Rôle</label>
                                            <input type="text" id="role" name="role" value="<?php echo htmlspecialchars($currentUser['role']); ?>" disabled>
                                        </div>

                                        <div class="form-group">
                                            <label for="statute">Statut</label>
                                            <input type="text" id="statute" name="statute" value="<?php echo htmlspecialchars($currentUser['statut']); ?>" disabled>
                                        </div>
                                        
                                        <button type="submit" class="btn-primary">
                                            <i data-lucide="save"></i>
                                            Enregistrer les Modifications
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Change Password -->
                            <div class="section-card">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i data-lucide="lock"></i>
                                        Changer le Mot de Passe
                                    </h3>
                                </div>
                                <div class="section-content">
                                    <form method="POST" id="passwordForm">
                                        <input type="hidden" name="action" value="change_password">
                                        
                                        <div class="form-group">
                                            <label for="currentPassword">Mot de passe actuel *</label>
                                            <input type="password" id="currentPassword" name="currentPassword" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="newPassword">Nouveau mot de passe *</label>
                                            <input type="password" id="newPassword" name="newPassword" required minlength="6">
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="confirmPassword">Confirmer le nouveau mot de passe *</label>
                                            <input type="password" id="confirmPassword" name="confirmPassword" required minlength="6">
                                        </div>

                                        <div class="password-requirements">
                                            <strong>Exigences du mot de passe :</strong>
                                            <ul>
                                                <li>Au moins 6 caractères</li>
                                                <li>Recommandé : mélange de lettres, chiffres et symboles</li>
                                                <li>Évitez les mots de passe trop simples</li>
                                            </ul>
                                        </div>
                                        
                                        <button type="submit" class="btn-secondary" style="margin-top: 1rem;">
                                            <i data-lucide="key"></i>
                                            Changer le Mot de Passe
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Profile form validation
            const profileForm = document.getElementById('profileForm');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    const username = document.getElementById('username').value.trim();
                    const email = document.getElementById('email').value.trim();

                    if (!username || !email) {
                        e.preventDefault();
                        alert('Veuillez remplir tous les champs obligatoires.');
                        return false;
                    }

                    if (!isValidEmail(email)) {
                        e.preventDefault();
                        alert('Veuillez entrer une adresse e-mail valide.');
                        return false;
                    }
                });
            }

            // Password form validation
            const passwordForm = document.getElementById('passwordForm');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const currentPassword = document.getElementById('currentPassword').value;
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;

                    if (!currentPassword || !newPassword || !confirmPassword) {
                        e.preventDefault();
                        alert('Veuillez remplir tous les champs du mot de passe.');
                        return false;
                    }

                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('Les nouveaux mots de passe ne correspondent pas.');
                        return false;
                    }

                    if (newPassword.length < 6) {
                        e.preventDefault();
                        alert('Le nouveau mot de passe doit contenir au moins 6 caractères.');
                        return false;
                    }
                });

                // Real-time password confirmation check
                const newPasswordInput = document.getElementById('newPassword');
                const confirmPasswordInput = document.getElementById('confirmPassword');
                
                function checkPasswordMatch() {
                    if (newPasswordInput.value && confirmPasswordInput.value) {
                        if (newPasswordInput.value === confirmPasswordInput.value) {
                            confirmPasswordInput.setCustomValidity('');
                            confirmPasswordInput.style.borderColor = '#10b981';
                        } else {
                            confirmPasswordInput.setCustomValidity('Les mots de passe ne correspondent pas');
                            confirmPasswordInput.style.borderColor = '#ef4444';
                        }
                    }
                }

                newPasswordInput.addEventListener('input', checkPasswordMatch);
                confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            }

            // Sidebar overlay functionality
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay) {
                overlay.addEventListener('click', function() {
                    const sidebar = document.querySelector('.sidebar');
                    if (sidebar) {
                        sidebar.classList.remove('active');
                        overlay.classList.remove('active');
                    }
                });
            }
        });

        // Email validation function
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Sidebar functionality
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (sidebar && overlay) {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            }
        }
    </script>
</body>
</html>

<?php
} else {
    // Redirect to login if not authorized
    header("Location: ../logout.php");
    exit();
}
?>