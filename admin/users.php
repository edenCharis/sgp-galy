<?php
session_start();
if($_SESSION["role"] === "ADMIN" && $_SESSION["id"] == session_id()){

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include database connection
    include '../config/database.php';
    
    // Check if database connection exists
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    $admin_id = $_SESSION['user_id'];
    $message = '';
    $messageType = '';

    // Handle form submissions
    if ($_POST) {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_user':
                    $username = trim($_POST['username']);

                    $email = trim($_POST['email']); 
                   
                    $password = $_POST['password'];
                    $role = $_POST['role'];
                    
                    // Validate inputs
                    if (empty($username) || empty($email) || empty($password) || empty($role)) {
                        $message = 'Tous les champs sont obligatoires';
                        $messageType = 'error';
                    } else {
                        // Check if username or email already exists
                        $checkUserSQL = "SELECT id FROM user WHERE username = ? OR email = ?";
                        $existingUser = $db->fetch($checkUserSQL, [$username, $email]);
                        
                        if ($existingUser) {
                            $message = 'Nom d\'utilisateur ou email déjà utilisé';
                            $messageType = 'error';
                        } else {
                            // Hash password
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            
                            // Insert new user

                            $id = uniqid();
                            $insertUserSQL = "INSERT INTO user (id,username, email, password, role, createdAt) VALUES (?,?, ?, ?, ?, NOW())";
                            $result = $db->query($insertUserSQL, [$id,$username, $email, $hashedPassword, $role]);
                            
                            if ($result) {
                                $message = 'Utilisateur ajouté avec succès';
                                $messageType = 'success';
                            } else {
                                $message = 'Erreur lors de l\'ajout de l\'utilisateur';
                                $messageType = 'error';
                            }
                        }
                    }


                    break;
                    
                case 'edit_user':
                    $userId = (string)$_POST['user_id'];
                    $username = trim($_POST['username']);
                    $email = trim($_POST['email']);
                    $role = $_POST['role'];
                    $newPassword = $_POST['new_password'];
                    
                    if (empty($username) || empty($email) || empty($role)) {
                        $message = 'Nom d\'utilisateur, email et rôle sont obligatoires';
                        $messageType = 'error';
                    } else {
                        // Check if username or email already exists for other users
                        $checkUserSQL = "SELECT id FROM user WHERE (username = ? OR email = ?) AND id != ?";
                        $existingUser = $db->fetch($checkUserSQL, [$username, $email, $userId]);
                        
                        if ($existingUser) {
                            $message = 'Nom d\'utilisateur ou email déjà utilisé par un autre utilisateur';
                            $messageType = 'error';
                        } else {
                            // Update user
                            if (!empty($newPassword)) {
                                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                                $updateUserSQL = "UPDATE user SET username = ?, email = ?, role = ?, password = ? WHERE id = ?";
                                $result = $db->query($updateUserSQL, [$username, $email, $role, $hashedPassword, $userId]);
                            } else {
                                $updateUserSQL = "UPDATE user SET username = ?, email = ?, role = ? WHERE id = ?";
                                $result = $db->query($updateUserSQL, [$username, $email, $role, $userId]);
                            }
                            
                            if ($result) {
                                $message = 'Utilisateur modifié avec succès';
                                $messageType = 'success';
                            } else {
                                $message = 'Erreur lors de la modification de l\'utilisateur';
                                $messageType = 'error';
                            }
                        }
                    }
                    break;
                    
                case 'delete_user':
                    $userId = (string)$_POST['user_id'];
                    
                    // Don't allow deleting own account
                    if ($userId == $admin_id) {
                        $message = 'Vous ne pouvez pas supprimer votre propre compte';
                        $messageType = 'error';
                    } else {
                        // Check if user has any cart history before deleting
                        $checkCartsSQL = "SELECT COUNT(*) as cart_count FROM carts WHERE seller_id = ?";
                        $cartCount = $db->fetch($checkCartsSQL, [$userId]);
                        
                        if ($cartCount && $cartCount['cart_count'] > 0) {
                            // Soft delete or transfer ownership might be better
                            $message = 'Impossible de supprimer cet utilisateur car il a un historique de ventes';
                            $messageType = 'error';
                        } else {
                            $deleteUserSQL = "DELETE FROM user WHERE id = ?";
                            $result = $db->query($deleteUserSQL, [$userId]);
                            
                            if ($result) {
                                $message = 'Utilisateur supprimé avec succès';
                                $messageType = 'success';
                            } else {
                                $message = 'Erreur lors de la suppression de l\'utilisateur';
                                $messageType = 'error';
                            }
                        }
                    }
                    break;
            }
        }
    }

    // Pagination setup
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 10;
    $offset = ($page - 1) * $perPage;

    // Search functionality
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $roleFilter = isset($_GET['role']) ? $_GET['role'] : '';

    // Build WHERE clause
    $whereConditions = [];
    $params = [];

    if (!empty($search)) {
        $whereConditions[] = "(username LIKE ? OR email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if (!empty($roleFilter)) {
        $whereConditions[] = "role = ?";
        $params[] = $roleFilter;
    }

    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    // Get total count for pagination
    $countSQL = "SELECT COUNT(*) as total FROM user $whereClause";
    $totalResult = $db->fetch($countSQL, $params);
    $totalUsers = $totalResult ? $totalResult['total'] : 0;
    $totalPages = ceil($totalUsers / $perPage);

    // Get users with pagination
    $usersSQL = "SELECT id, username, email, role, createdAt, 
                        (SELECT COUNT(*) FROM carts WHERE seller_id = user.id) as total_sales
                 FROM user 
                 $whereClause
                 ORDER BY createdAt DESC 
                 LIMIT ? OFFSET ?";
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $users = $db->fetchAll($usersSQL, $params);
    if ($users === false) {
        $users = [];
    }

    // Get user statistics
    $statsSQL = "SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'ADMIN' THEN 1 ELSE 0 END) as admin_count,
                    SUM(CASE WHEN role = 'SELLER' THEN 1 ELSE 0 END) as seller_count,
                    SUM(CASE WHEN role = 'CASHIER' THEN 1 ELSE 0 END) as cashier_count
                 FROM user";
    $stats = $db->fetch($statsSQL);

} catch (Exception $e) {
    $message = $e->getMessage();
    $messageType = 'error';
}

// Helper functions
function formatDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function getRoleBadgeClass($role) {
    switch ($role) {
        case 'ADMIN': return 'badge-danger';
        case 'SELLER': return 'badge-primary';
        case 'CASHIER': return 'badge-success';
        default: return 'badge-secondary';
    }
}

function getRoleText($role) {
    switch ($role) {
        case 'ADMIN': return 'Administrateur';
        case 'SELLER': return 'Vendeur';
        case 'CASHIER': return 'Caissier';
        default: return $role;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Gestion des Utilisateurs</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lucide/0.263.1/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .users-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .users-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .users-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-filters {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .users-table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .users-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #3b82f6, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: #1f2937;
        }
        
        .user-details small {
            color: #6b7280;
        }
        
        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-primary { background: #dbeafe; color: #1e40af; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #f3f4f6; color: #374151; }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 6px;
        }
        
        .pagination-container {
            padding: 1.5rem;
            background: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .stat-icon.primary { background: #3b82f6; }
        .stat-icon.success { background: #10b981; }
        .stat-icon.warning { background: #f59e0b; }
        .stat-icon.danger { background: #ef4444; }
        
        .stat-content h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }
        
        .stat-content p {
            margin: 0;
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .modal {
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .users-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filters-row {
                flex-direction: column;
            }
            
            .filter-group {
                min-width: auto;
            }
            
            .users-table-container {
                overflow-x: auto;
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
                <!-- Alert Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType == 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="users-header">
                    <h1 class="users-title">
                        <i data-lucide="users"></i>
                        Gestion des Utilisateurs
                    </h1>
                    <div class="users-actions">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i data-lucide="user-plus"></i>
                            Ajouter Utilisateur
                        </button>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i data-lucide="users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_users'] ?? 0; ?></h3>
                            <p>Total Utilisateurs</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i data-lucide="shield"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['admin_count'] ?? 0; ?></h3>
                            <p>Administrateurs</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i data-lucide="shopping-bag"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['seller_count'] ?? 0; ?></h3>
                            <p>Vendeurs</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i data-lucide="calculator"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['cashier_count'] ?? 0; ?></h3>
                            <p>Caissiers</p>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="search-filters">
                    <form method="GET" action="users.php">
                        <div class="filters-row">
                            <div class="filter-group">
                                <label for="search">Rechercher</label>
                                <input type="text" 
                                       id="search" 
                                       name="search" 
                                       class="form-control" 
                                       placeholder="Nom d'utilisateur ou email..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="filter-group">
                                <label for="role">Rôle</label>
                                <select id="role" name="role" class="form-select">
                                    <option value="">Tous les rôles</option>
                                    <option value="ADMIN" <?php echo $roleFilter == 'ADMIN' ? 'selected' : ''; ?>>Administrateur</option>
                                    <option value="SELLER" <?php echo $roleFilter == 'SELLER' ? 'selected' : ''; ?>>Vendeur</option>
                                    <option value="CASHIER" <?php echo $roleFilter == 'CASHIER' ? 'selected' : ''; ?>>Caissier</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i data-lucide="search"></i>
                                    Filtrer
                                </button>
                                <a href="users.php" class="btn btn-outline-secondary">
                                    <i data-lucide="x"></i>
                                    Effacer
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Users Table -->
                <div class="users-table-container">
                    <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <i data-lucide="users"></i>
                            <h3>Aucun utilisateur trouvé</h3>
                            <p>Il n'y a aucun utilisateur correspondant à vos critères de recherche.</p>
                            <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                <i data-lucide="user-plus"></i>
                                Ajouter le premier utilisateur
                            </button>
                        </div>
                    <?php else: ?>
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Utilisateur</th>
                                    <th>Rôle</th>
                                   
                                    <th>Date création</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                                                </div>
                                                <div class="user-details">
                                                    <h6><?php echo htmlspecialchars($user['username']); ?></h6>
                                                    <small><?php echo !is_null($user['email']) ? htmlspecialchars($user['email']) : ''; ?></small></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="role-badge <?php echo getRoleBadgeClass($user['role']); ?>">
                                                <?php echo getRoleText($user['role']); ?>
                                            </span>
                                        </td>
                                      
                                        <td>
                                            <span class="fw-semibold"><?php echo formatDate($user['createdAt']); ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" 
                                                        class="btn btn-outline-primary btn-sm edit-user-btn" 
                                                        data-user-id="<?php echo $user['id']; ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        data-email="<?php echo !is_null($user['email']) ? htmlspecialchars($user['email']) : ''; ?>"
                                                        data-role="<?php echo $user['role']; ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#editUserModal">
                                                    <i data-lucide="edit"></i>
                                                </button>
                                                <?php if ($user['id'] != $admin_id): ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-danger btn-sm delete-user-btn" 
                                                            data-user-id="<?php echo $user['id']; ?>"
                                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteUserModal">
                                                        <i data-lucide="trash-2"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination-container">
                                <nav>
                                    <ul class="pagination justify-content-center">
                                        <!-- Previous button -->
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>">
                                                <i data-lucide="chevron-left"></i>
                                            </a>
                                        </li>

                                        <!-- Page numbers -->
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>

                                        <!-- Next button -->
                                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>">
                                                <i data-lucide="chevron-right"></i>
                                            </a>
                                        </li>
                                    </ul>
                                </nav>
                                <div class="text-center text-muted small">
                                    Affichage de <?php echo $offset + 1; ?> à <?php echo min($offset + $perPage, $totalUsers); ?> sur <?php echo $totalUsers; ?> utilisateurs
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="user-plus"></i>
                        Ajouter un utilisateur
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="users.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        
                        <div class="mb-3">
                            <label for="add_username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="add_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="add_email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_password" class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" id="add_password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="add_role" class="form-label">Rôle *</label>
                            <select class="form-select" id="add_role" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <option value="SELLER">Vendeur</option>
                                <option value="CASHIER">Caissier</option>
                                <option value="ADMIN">Administrateur</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i>
                            Ajouter l'utilisateur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="edit"></i>
                        Modifier l'utilisateur
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="users.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Rôle *</label>
                            <select class="form-select" id="edit_role" name="role" required>
                                <option value="SELLER">Vendeur</option>
                                <option value="CASHIER">Caissier</option>
                                <option value="ADMIN">Administrateur</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" id="edit_password" name="new_password">
                            <div class="form-text">Laissez vide pour conserver le mot de passe actuel</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i>
                            Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i data-lucide="alert-triangle"></i>
                        Supprimer l'utilisateur
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="users.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" id="delete_user_id" name="user_id">
                        
                        <div class="alert alert-warning">
                            <i data-lucide="alert-triangle"></i>
                            <strong>Attention !</strong> Cette action est irréversible.
                        </div>
                        
                        <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="delete_username"></strong> ?</p>
                        <p class="text-muted small">Tous les données associées à cet utilisateur seront perdues.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">
                            <i data-lucide="trash-2"></i>
                            Supprimer définitivement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Sidebar functionality
        function setupSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const menuToggle = document.getElementById('menuToggle');
            const sidebarClose = document.getElementById('sidebarClose');

            function showSidebar() {
                if (sidebar) sidebar.classList.add('show');
                if (overlay) overlay.classList.add('show');
            }

            function hideSidebar() {
                if (sidebar) sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            }

            if (menuToggle) menuToggle.addEventListener('click', showSidebar);
            if (sidebarClose) sidebarClose.addEventListener('click', hideSidebar);
            if (overlay) overlay.addEventListener('click', hideSidebar);
        }

        // Edit user modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            setupSidebar();
            
            // Edit user buttons
            const editButtons = document.querySelectorAll('.edit-user-btn');
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const username = this.dataset.username;
                    const email = this.dataset.email;
                    const role = this.dataset.role;
                    
                    document.getElementById('edit_user_id').value = userId;
                    document.getElementById('edit_username').value = username;
                    document.getElementById('edit_email').value = email;
                    document.getElementById('edit_role').value = role;
                });
            });
            
            // Delete user buttons
            const deleteButtons = document.querySelectorAll('.delete-user-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const userId = this.dataset.userId;
                    const username = this.dataset.username;
                    
                    document.getElementById('delete_user_id').value = userId;
                    document.getElementById('delete_username').textContent = username;
                });
            });
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
            
            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });
        });

        // Search functionality with debounce
        let searchTimeout;
        const searchInput = document.getElementById('search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    // Auto-submit search form after 500ms of no typing
                    this.form.submit();
                }, 500);
            });
        }

        // Role filter change
        const roleSelect = document.getElementById('role');
        if (roleSelect) {
            roleSelect.addEventListener('change', function() {
                this.form.submit();
            });
        }
    </script>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>