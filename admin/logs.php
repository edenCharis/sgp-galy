<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'ADMIN') {
    header('Location: ../logout.php');
    exit();
}

// Database connection
require_once '../config/database.php';

// Pagination settings
$recordsPerPage = 20;
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Search and filter parameters
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$userFilter = isset($_GET['user_filter']) ? (int)$_GET['user_filter'] : '';
$actionFilter = isset($_GET['action_filter']) ? trim($_GET['action_filter']) : '';
$dateFilter = isset($_GET['date_filter']) ? trim($_GET['date_filter']) : '';

// Build the WHERE clause for filtering
$whereClause = "WHERE 1=1";
$params = [];

if (!empty($searchTerm)) {
    $whereClause .= " AND (l.description LIKE ? OR l.tableName LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if (!empty($userFilter)) {
    $whereClause .= " AND l.userId = ?";
    $params[] = $userFilter;
}

if (!empty($actionFilter)) {
    $whereClause .= " AND l.action = ?";
    $params[] = $actionFilter;
}

if (!empty($dateFilter)) {
    $whereClause .= " AND DATE(l.createdAt) = ?";
    $params[] = $dateFilter;
}

// Get total count for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM log l 
    LEFT JOIN user u ON l.userId = u.id 
    $whereClause
";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $recordsPerPage);

// Get logs with user information
$query = "
    SELECT 
        l.id,
        l.userId,
        u.username,
        u.email,
        l.action,
        l.tableName,
        l.recordId,
        l.description,
        l.createdAt
    FROM log l
    LEFT JOIN user u ON l.userId = u.id
    $whereClause
    ORDER BY l.createdAt DESC
    LIMIT ? OFFSET ?
";

$params[] = $recordsPerPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get unique users for filter dropdown
$usersQuery = "SELECT DISTINCT u.id, u.username FROM log l JOIN user u ON l.userId = u.id ORDER BY u.username";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute();
$users = $usersStmt->fetchAll();

// Get unique actions for filter dropdown
$actionsQuery = "SELECT DISTINCT action FROM log ORDER BY action";
$actionsStmt = $pdo->prepare($actionsQuery);
$actionsStmt->execute();
$actions = $actionsStmt->fetchAll();

// Function to translate actions to French
function translateAction($action) {
    $translations = [
        'CREATE' => 'Création',
        'UPDATE' => 'Modification',
        'DELETE' => 'Suppression',
        'LOGIN' => 'Connexion',
        'LOGOUT' => 'Déconnexion',
        'VIEW' => 'Consultation',
        'EXPORT' => 'Export',
        'IMPORT' => 'Import'
    ];
    return isset($translations[$action]) ? $translations[$action] : $action;
}

// Function to translate table names to French
function translateTableName($tableName) {
    $translations = [
        'users' => 'Utilisateurs',
        'products' => 'Produits',
        'sales' => 'Ventes',
        'inventory' => 'Inventaire',
        'suppliers' => 'Fournisseurs',
        'categories' => 'Catégories',
        'cash_registers' => 'Caisses',
        'transactions' => 'Transactions'
    ];
    return isset($translations[$tableName]) ? $translations[$tableName] : $tableName;
}

// Function to get action badge class
function getActionBadgeClass($action) {
    switch(strtoupper($action)) {
        case 'CREATE':
        case 'LOGIN':
            return 'success';
        case 'UPDATE':
        case 'VIEW':
            return 'info';
        case 'DELETE':
        case 'LOGOUT':
            return 'danger';
        case 'EXPORT':
        case 'IMPORT':
            return 'warning';
        default:
            return 'secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs d'Activité - PharmaSys Admin</title>
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
        /* Reset and base layout fixes */
        .admin-layout {
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 0; /* Remove any left margin */
            width: calc(100% - 250px); /* Adjust based on sidebar width */
            min-height: 100vh;
        }

        /* Ensure content area takes full available space */
        .content-area {
            flex: 1;
            padding: 0;
            overflow-y: auto;
        }

        .logs-container {
            padding: 2rem;
            max-width: 100%;
            margin: 0;
            background: #f8fafc;
            min-height: calc(100vh - 80px); /* Adjust for header height */
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
            margin: 0;
            display: flex;
            align-items: center;
        }

        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .filter-input, .filter-select {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.2s;
            background: white;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: end;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
            color: white;
        }

        .logs-table-container {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logs-table th,
        .logs-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .logs-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .logs-table td {
            color: #4b5563;
            font-size: 0.875rem;
        }

        .logs-table tr:hover {
            background: #f9fafb;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }

        .badge-info {
            background: rgba(59, 130, 246, 0.1);
            color: #1e3a8a;
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #78350f;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #7f1d1d;
        }

        .badge-secondary {
            background: rgba(107, 114, 128, 0.1);
            color: #374151;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #1f2937;
        }

        .user-email {
            font-size: 0.75rem;
            color: #6b7280;
        }

        .timestamp {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .description-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .pagination-btn:hover {
            background: #f3f4f6;
            color: #374151;
            text-decoration: none;
        }

        .pagination-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }

        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        /* Mobile responsive adjustments */
        @media (max-width: 768px) {
            .main-content {
                width: 100%;
                margin-left: 0;
            }

            .logs-container {
                padding: 1rem;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                margin-top: 1rem;
            }

            .logs-table-container {
                overflow-x: auto;
            }

            .logs-table {
                min-width: 800px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body class="admin-layout">
    <div id="sidebarOverlay" class="sidebar-overlay"></div>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php include 'header.php'; ?>
        
        <!-- Content Area -->
        <div class="content-area">
            <div class="logs-container">
                <div class="page-header">
                    <h1 class="page-title">
                        <i data-lucide="file-text" style="width: 2rem; height: 2rem; margin-right: 0.5rem;"></i>
                        Logs d'Activité
                    </h1>
                    <div class="timestamp">
                        Dernière mise à jour: <?php echo date('d/m/Y H:i'); ?>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($totalRecords); ?></div>
                        <div class="stat-label">Total des entrées</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($users); ?></div>
                        <div class="stat-label">Utilisateurs actifs</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo count($actions); ?></div>
                        <div class="stat-label">Types d'actions</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $currentPage; ?>/<?php echo $totalPages; ?></div>
                        <div class="stat-label">Page actuelle</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <form method="GET" action="">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label class="filter-label">Recherche</label>
                                <input type="text" name="search" class="filter-input" 
                                       placeholder="Rechercher dans les descriptions..."
                                       value="<?php echo htmlspecialchars($searchTerm); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label class="filter-label">Utilisateur</label>
                                <select name="user_filter" class="filter-select">
                                    <option value="">Tous les utilisateurs</option>
                                    <?php foreach($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" 
                                                <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Action</label>
                                <select name="action_filter" class="filter-select">
                                    <option value="">Toutes les actions</option>
                                    <?php foreach($actions as $action): ?>
                                        <option value="<?php echo $action['action']; ?>"
                                                <?php echo $actionFilter == $action['action'] ? 'selected' : ''; ?>>
                                            <?php echo translateAction($action['action']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Date</label>
                                <input type="date" name="date_filter" class="filter-input"
                                       value="<?php echo htmlspecialchars($dateFilter); ?>">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i data-lucide="search" style="width: 1rem; height: 1rem;"></i>
                                Filtrer
                            </button>
                            <a href="logs.php" class="btn btn-secondary">
                                <i data-lucide="x" style="width: 1rem; height: 1rem;"></i>
                                Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Logs Table -->
                <div class="logs-table-container">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Utilisateur</th>
                                <th>Action</th>
                                <th>Table</th>
                                <th>ID Enregistrement</th>
                                <th>Description</th>
                                <th>Date/Heure</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($logs)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 3rem; color: #6b7280;">
                                        <i data-lucide="inbox" style="width: 3rem; height: 3rem; margin-bottom: 1rem; display: block; margin: 0 auto 1rem;"></i>
                                        <div>Aucun log trouvé avec les critères sélectionnés</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['id']; ?></td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                    <?php echo strtoupper(substr($log['username'] ?? 'U', 0, 1)); ?>
                                                </div>
                                                <div class="user-details">
                                                    <div class="user-name"><?php echo htmlspecialchars($log['username'] ?? 'Utilisateur supprimé'); ?></div>
                                                    <div class="user-email"><?php echo htmlspecialchars($log['email'] ?? ''); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo getActionBadgeClass($log['action']); ?>">
                                                <?php echo translateAction($log['action']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo translateTableName($log['tableName']); ?></td>
                                        <td><?php echo $log['recordId'] ?? '-'; ?></td>
                                        <td>
                                            <div class="description-cell" title="<?php echo htmlspecialchars($log['description']); ?>">
                                                <?php echo htmlspecialchars($log['description']); ?>
                                            </div>
                                        </td>
                                        <td class="timestamp">
                                            <?php echo date('d/m/Y H:i:s', strtotime($log['createdAt'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($userFilter) ? '&user_filter=' . $userFilter : ''; ?><?php echo !empty($actionFilter) ? '&action_filter=' . urlencode($actionFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . urlencode($dateFilter) : ''; ?>" 
                               class="pagination-btn">
                                <i data-lucide="chevron-left" style="width: 1rem; height: 1rem;"></i>
                                Précédent
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        for($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="?page=<?php echo $i; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($userFilter) ? '&user_filter=' . $userFilter : ''; ?><?php echo !empty($actionFilter) ? '&action_filter=' . urlencode($actionFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . urlencode($dateFilter) : ''; ?>" 
                               class="pagination-btn <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo !empty($userFilter) ? '&user_filter=' . $userFilter : ''; ?><?php echo !empty($actionFilter) ? '&action_filter=' . urlencode($actionFilter) : ''; ?><?php echo !empty($dateFilter) ? '&date_filter=' . urlencode($dateFilter) : ''; ?>" 
                               class="pagination-btn">
                                Suivant
                                <i data-lucide="chevron-right" style="width: 1rem; height: 1rem;"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Auto-submit form on filter change (optional)
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                // Uncomment the next line if you want auto-submit on filter change
                // this.form.submit();
            });
        });

        // Add tooltips for truncated descriptions
        document.querySelectorAll('.description-cell').forEach(cell => {
            if (cell.scrollWidth > cell.clientWidth) {
                cell.style.cursor = 'pointer';
                cell.addEventListener('click', function() {
                    alert(this.getAttribute('title'));
                });
            }
        });
    </script>
</body>
</html>