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
    
    // Get message from session and clear it
    $message = $_SESSION['flash_message'] ?? '';
    $messageType = $_SESSION['flash_message_type'] ?? '';
    unset($_SESSION['flash_message'], $_SESSION['flash_message_type']);

    // Handle POST requests with PRG pattern
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'open_register':
                $cashier_id = $_POST['cashier_id'];
                $initial_amount = floatval($_POST['initial_amount']);
                
                // Check if cashier already has an open register
                $checkSQL = "SELECT id FROM cash_register WHERE cashier_id = ? AND status = 'open'";
                $existingRegister = $db->fetch($checkSQL, [$cashier_id]);
                
                if ($existingRegister) {
                    $_SESSION['flash_message'] = "Ce caissier a déjà une caisse ouverte.";
                    $_SESSION['flash_message_type'] = 'error';
                } else {
                    $openSQL = "INSERT INTO cash_register (cashier_id, opening_time, status, initial_amount, final_amount) 
                               VALUES (?, NOW(), 'open', ?, 0)";
                    $result = $db->execute($openSQL, [$cashier_id, $initial_amount]);
                    
                    if ($result) {
                        $_SESSION['flash_message'] = "Caisse ouverte avec succès.";
                        $_SESSION['flash_message_type'] = 'success';
                    } else {
                        $_SESSION['flash_message'] = "Erreur lors de l'ouverture de la caisse.";
                        $_SESSION['flash_message_type'] = 'error';
                    }
                }
                
                // Redirect to prevent form resubmission (PRG pattern)
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
                break;
                
            case 'close_register':
                $register_id = $_POST['register_id'];
                $final_amount = floatval($_POST['final_amount']);
                
                $closeSQL = "UPDATE cash_register 
                            SET closing_time = NOW(), status = 'closed', final_amount = ? 
                            WHERE id = ? AND status = 'open'";
                $result = $db->execute($closeSQL, [$final_amount, $register_id]);
                
                if ($result) {
                    $_SESSION['flash_message'] = "Caisse fermée avec succès.";
                    $_SESSION['flash_message_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = "Erreur lors de la fermeture de la caisse.";
                    $_SESSION['flash_message_type'] = 'error';
                }
                
                // Redirect to prevent form resubmission (PRG pattern)
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
                break;
        }
    }

    // Get all cashiers and sellers
    $cashiersSQL = "SELECT id, username, role FROM user WHERE role IN ('CASHIER', 'SELLER') AND statut = 1 ORDER BY username";
    $cashiers = $db->fetchAll($cashiersSQL);
    if (!$cashiers) $cashiers = [];

    // Get open registers with cashier info and sales data
    $openRegistersSQL = "SELECT 
                            cr.id,
                            cr.cashier_id,
                            cr.opening_time,
                            cr.initial_amount,
                            u.username as cashier_name,
                            u.role as cashier_role,
                            COALESCE(SUM(s.totalAmount), 0) as total_sales,
                            COUNT(s.id) as sales_count,
                            COALESCE(SUM(s.cashReceived), 0) as total_cash_received
                         FROM cash_register cr
                         LEFT JOIN user u ON cr.cashier_id = u.id
                         LEFT JOIN sale s ON cr.id = s.cash_register_id
                         WHERE cr.status = 'open'
                         GROUP BY cr.id, cr.cashier_id, cr.opening_time, cr.initial_amount, u.username, u.role
                         ORDER BY cr.opening_time DESC";
    
    $openRegisters = $db->fetchAll($openRegistersSQL);
    if (!$openRegisters) $openRegisters = [];

    // Get recent closed registers
    $recentClosedSQL = "SELECT 
                           cr.id,
                           cr.cashier_id,
                           cr.opening_time,
                           cr.closing_time,
                           cr.initial_amount,
                           cr.final_amount,
                           u.username as cashier_name,
                           u.role as cashier_role,
                           COALESCE(SUM(s.totalAmount), 0) as total_sales,
                           COUNT(s.id) as sales_count,
                           TIMESTAMPDIFF(HOUR, cr.opening_time, cr.closing_time) as hours_open
                        FROM cash_register cr
                        LEFT JOIN user u ON cr.cashier_id = u.id
                        LEFT JOIN sale s ON cr.id = s.cash_register_id
                        WHERE cr.status = 'closed'
                        GROUP BY cr.id, cr.cashier_id, cr.opening_time, cr.closing_time, 
                                cr.initial_amount, cr.final_amount, u.username, u.role
                        ORDER BY cr.closing_time DESC
                        LIMIT 10";
    
    $recentClosed = $db->fetchAll($recentClosedSQL);
    if (!$recentClosed) $recentClosed = [];

    // Get daily statistics
    $todayStatsSQL = "SELECT 
                         COUNT(CASE WHEN cr.status = 'open' THEN 1 END) as open_registers,
                         COUNT(CASE WHEN cr.status = 'closed' AND DATE(cr.closing_time) = CURDATE() THEN 1 END) as closed_today,
                         COALESCE(SUM(CASE WHEN DATE(s.saleDate) = CURDATE() THEN s.totalAmount END), 0) as today_revenue,
                         COUNT(CASE WHEN DATE(s.saleDate) = CURDATE() THEN s.id END) as today_transactions
                      FROM cash_register cr
                      LEFT JOIN sale s ON cr.id = s.cash_register_id";
    
    $dailyStats = $db->fetch($todayStatsSQL);
    if (!$dailyStats) {
        $dailyStats = [
            'open_registers' => 0,
            'closed_today' => 0,
            'today_revenue' => 0,
            'today_transactions' => 0
        ];
    }

} catch (Exception $e) {
    $_SESSION['flash_message'] = "Erreur: " . $e->getMessage();
    $_SESSION['flash_message_type'] = 'error';
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Helper functions
function formatCurrency($amount) {
    return number_format($amount, 0) . ' XAF';
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

function formatDuration($hours) {
    if ($hours < 1) {
        return round($hours * 60) . ' min';
    }
    return round($hours, 1) . 'h';
}

function calculateExpectedAmount($initial, $totalSales) {
    return $initial + $totalSales;
}

function calculateDifference($expected, $actual) {
    return $actual - $expected;
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Gestion des Caisses</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
      <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        .cash-register-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        @media (max-width: 1200px) {
            .cash-register-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .register-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
        }
        
        .register-card.open {
            border-left: 4px solid #10b981;
        }
        
        .register-card.closed {
            border-left: 4px solid #6b7280;
        }
        
        .register-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .register-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1f2937;
        }
        
        .register-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .register-status.open {
            background-color: #d1fae5;
            color: #059669;
        }
        
        .register-status.closed {
            background-color: #f3f4f6;
            color: #6b7280;
        }
        
        .register-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .register-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-action {
            flex: 1;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-close {
            background-color: #ef4444;
            color: white;
        }
        
        .btn-close:hover {
            background-color: #dc2626;
        }
        
        .btn-view {
            background-color: #3b82f6;
            color: white;
        }
        
        .btn-view:hover {
            background-color: #2563eb;
        }
        
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .form-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-primary {
            background-color: #3b82f6;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background-color: #2563eb;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fca5a5;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stat-icon.primary { background-color: #dbeafe; color: #3b82f6; }
        .stat-icon.success { background-color: #d1fae5; color: #10b981; }
        .stat-icon.warning { background-color: #fef3c7; color: #f59e0b; }
        .stat-icon.info { background-color: #e0e7ff; color: #8b5cf6; }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }
        
        .recent-history {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }
        
        .history-info {
            display: flex;
            flex-direction: column;
        }
        
        .history-cashier {
            font-weight: 600;
            color: #1f2937;
        }
        
        .history-time {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .history-amounts {
            text-align: right;
        }
        
        .history-sales {
            font-weight: 600;
            color: #1f2937;
        }
        
        .history-duration {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .text-success {
            color: #10b981 !important;
        }

        .text-danger {
            color: #ef4444 !important;
        }

        /* Modal improvements */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .modal-header {
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 600;
            color: #1f2937;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #e5e7eb;
            padding: 1rem 1.5rem;
        }

        /* Loading state */
        .btn-loading {
            position: relative;
            pointer-events: none;
        }

        .btn-loading::after {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Error handling */
        .error-message {
            color: #dc2626;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .form-control.error {
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
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
                <!-- Page Title -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                    <i data-lucide="wallet"></i>
                        Gestion des Caisses
                    </h1>
                </div>

                <!-- Alert Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>" id="alertMessage">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <!-- Statistics Overview -->
                <div class="stats-overview">
                    <div class="stat-card">
                        <div class="stat-icon">
            <i data-lucide="wallet"></i>
        </div>
                        <div class="stat-value"><?php echo $dailyStats['open_registers']; ?></div>
                        <div class="stat-label">Caisses ouvertes</div>
                    </div>
                    
                    <div class="stat-card">
                      <div class="stat-icon">
            <i data-lucide="trending-up"></i>
        </div>
                        <div class="stat-value"><?php echo formatCurrency($dailyStats['today_revenue']); ?></div>
                        <div class="stat-label">CA aujourd'hui</div>
                    </div>
                    
                    <div class="stat-card">
                       <div class="stat-icon">
            <i data-lucide="shopping-cart"></i>
        </div>
                        <div class="stat-value"><?php echo $dailyStats['today_transactions']; ?></div>
                        <div class="stat-label">Ventes aujourd'hui</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
            <i data-lucide="check-circle"></i>
        </div>
                        <div class="stat-value"><?php echo $dailyStats['closed_today']; ?></div>
                        <div class="stat-label">Caisses fermées</div>
                    </div>
                </div>

                <!-- Open New Register Form -->
                <div class="form-section">
                   <h2 class="form-title">
        <i data-lucide="plus-circle"></i>
        Ouvrir une nouvelle caisse
    </h2>
                    <form method="POST" id="openRegisterForm" novalidate>
                        <input type="hidden" name="action" value="open_register">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Caissier/Vendeur</label>
                                <select name="cashier_id" class="form-control" required>
                                    <option value="">Sélectionner un utilisateur</option>
                                    <?php foreach ($cashiers as $cashier): ?>
                                        <option value="<?php echo $cashier['id']; ?>">
                                            <?php echo htmlspecialchars($cashier['username']); ?> 
                                            (<?php echo $cashier['role']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="error-message" id="cashier-error" style="display: none;"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Montant initial (XAF)</label>
                                <input type="number" name="initial_amount" class="form-control" 
                                       min="0" step="1" required placeholder="0">
                                <div class="error-message" id="amount-error" style="display: none;"></div>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn-primary" id="openRegisterBtn">
                                  
        <i data-lucide="plus-circle"></i>
        Ouvrir une nouvelle caisse
  
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Open Registers -->
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="card-title text-white">
                            <i class="bi bi-activity"></i>
                            Caisses ouvertes (<?php echo count($openRegisters); ?>)
                        </div>
                    </div>
                    <div class="card-content">
                        <?php if (empty($openRegisters)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-cash-coin" style="font-size: 3rem;"></i>
                                <p>Aucune caisse ouverte actuellement</p>
                            </div>
                        <?php else: ?>
                            <div class="cash-register-grid">
                                <?php foreach ($openRegisters as $register): ?>
                                    <div class="register-card open">
                                        <div class="register-header">
                                            <div class="register-title">
                                                Caisse #<?php echo $register['id']; ?>
                                            </div>
                                            <div class="register-status open">Ouverte</div>
                                        </div>
                                        
                                        <div class="register-info">
                                            <div class="info-item">
                                                <div class="info-label">Caissier</div>
                                                <div class="info-value">
                                                    <?php echo htmlspecialchars($register['cashier_name']); ?>
                                                    <small>(<?php echo $register['cashier_role']; ?>)</small>
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-label">Ouverture</div>
                                                <div class="info-value"><?php echo formatDateTime($register['opening_time']); ?></div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-label">Montant initial</div>
                                                <div class="info-value"><?php echo formatCurrency($register['initial_amount']); ?></div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-label">Ventes réalisées</div>
                                                <div class="info-value">
                                                    <?php echo formatCurrency($register['total_sales']); ?>
                                                    <small>(<?php echo $register['sales_count']; ?> transactions)</small>
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-label">Montant attendu</div>
                                                <div class="info-value">
                                                    <?php echo formatCurrency(calculateExpectedAmount($register['initial_amount'], $register['total_sales'])); ?>
                                                </div>
                                            </div>
                                            <div class="info-item">
                                                <div class="info-label">Espèces reçues</div>
                                                <div class="info-value"><?php echo formatCurrency($register['total_cash_received']); ?></div>
                                            </div>
                                        </div>
                                        
                                     <div class="register-actions">
    <button type="button" class="btn-action btn-danger" 
            data-bs-toggle="modal" 
            data-bs-target="#closeRegisterModal"
            data-register-id="<?php echo $register['id']; ?>"
            data-cashier-name="<?php echo htmlspecialchars($register['cashier_name']); ?>"
            data-expected-amount="<?php echo calculateExpectedAmount($register['initial_amount'], $register['total_sales']); ?>">
        <i data-lucide="x-circle"></i>
        <span>Fermer</span>
    </button>

    <a class="btn btn-sm btn-action btn-success" 
           href="cash-count.php?id=<?php echo $register['id']; ?>">
        <i data-lucide="calculator"></i>
        <span>Comptage</span>
                                </a>

    <a class="btn btn-sm btn-action btn-view" 
           href="register_details.php?id=<?php echo $register['id']; ?>">
        <i data-lucide="eye"></i>
        <span>Détails</span>
                                </a>
</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Closed Registers -->
               
            </main>
        </div>
    </div>

    <!-- Close Register Modal -->
    <div class="modal fade" id="closeRegisterModal" tabindex="-1" aria-labelledby="closeRegisterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="closeRegisterModalLabel">
                        <i class="bi bi-x-circle me-2"></i>
                        Fermer la caisse
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <form method="POST" id="closeRegisterForm" novalidate>
                    <input type="hidden" name="action" value="close_register">
                    <input type="hidden" name="register_id" id="closeRegisterId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="info-item">
                                <div class="info-label">Caissier</div>
                                <div class="info-value" id="closeCashierName">-</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="info-item">
                                <div class="info-label">Montant attendu</div>
                                <div class="info-value" id="expectedAmount">-</div>
                            </div>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Montant final en caisse (XAF) *</label>
                            <input type="number" name="final_amount" id="finalAmount" 
                                   class="form-control" min="0" step="1" required 
                                   placeholder="Entrez le montant final">
                            <div class="error-message" id="final-amount-error" style="display: none;"></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-item">
                                <div class="info-label">Écart calculé</div>
                                <div class="info-value" id="calculatedDifference">-</div>
                            </div>
                        </div>
                        <div class="alert alert-info" style="font-size: 0.875rem;">
                            <i class="bi bi-info-circle me-2"></i>
                            Vérifiez bien le montant final avant de fermer la caisse. Cette action ne peut pas être annulée.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-lg me-1"></i>
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-danger" id="closeRegisterBtn">
                            <i class="bi bi-check-lg me-1"></i>
                            Fermer la caisse
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cash Register Management JavaScript - Version corrigée avec PRG Pattern
        let currentExpectedAmount = 0;

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing app...');
            initApp();
        });

        function initApp() {
            try {
                setupSidebar();
                setupEventListeners();
                setupModalHandlers();
               
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                
                // Auto-hide alert messages
                setTimeout(hideAlertMessage, 5000);
                
                console.log('App initialized successfully');
            } catch (error) {
                console.error('Error initializing app:', error);
            }
        }

        // Setup sidebar functionality
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

        // Setup event listeners
        function setupEventListeners() {
            // Form validation for opening register
            const openForm = document.getElementById('openRegisterForm');
            if (openForm) {
                openForm.addEventListener('submit', handleOpenRegisterSubmit);
            }
            
            // Real-time calculation for close modal
            const finalAmountInput = document.getElementById('finalAmount');
            if (finalAmountInput) {
                finalAmountInput.addEventListener('input', calculateDifference);
                finalAmountInput.addEventListener('keyup', calculateDifference);
                finalAmountInput.addEventListener('blur', validateFinalAmount);
            }
            
            // Close modal form validation
            const closeForm = document.getElementById('closeRegisterForm');
            if (closeForm) {
                closeForm.addEventListener('submit', handleCloseRegisterSubmit);
            }
        }

        // Setup modal handlers using Bootstrap 5 events
        function setupModalHandlers() {
            const closeModal = document.getElementById('closeRegisterModal');
            if (closeModal) {
                // Bootstrap 5 modal events
                closeModal.addEventListener('show.bs.modal', handleModalShow);
                closeModal.addEventListener('hidden.bs.modal', handleModalHidden);
                
                console.log('Modal handlers setup complete');
            }
        }

        // Handle modal show event
        function handleModalShow(event) {
            console.log('Modal show event triggered');
            
            const button = event.relatedTarget; // Button that triggered the modal
            if (button) {
                const registerId = button.getAttribute('data-register-id');
                const cashierName = button.getAttribute('data-cashier-name');
                const expectedAmount = parseFloat(button.getAttribute('data-expected-amount')) || 0;
                
                console.log('Modal data:', { registerId, cashierName, expectedAmount });
                
                // Set modal data
                document.getElementById('closeRegisterId').value = registerId;
                document.getElementById('closeCashierName').textContent = cashierName;
                document.getElementById('expectedAmount').textContent = formatCurrency(expectedAmount);
                
                currentExpectedAmount = expectedAmount;
                
                // Reset form
                document.getElementById('finalAmount').value = '';
                document.getElementById('calculatedDifference').textContent = '-';
                clearErrors();
            }
        }

        // Handle modal hidden event
        function handleModalHidden(event) {
            console.log('Modal hidden event triggered');
            // Reset form and errors
            const form = document.getElementById('closeRegisterForm');
            if (form) {
                form.reset();
                clearErrors();
            }
            currentExpectedAmount = 0;
        }

        // Calculate and display the difference in real-time
        function calculateDifference() {
            const finalAmountInput = document.getElementById('finalAmount');
            const differenceElement = document.getElementById('calculatedDifference');
            
            if (!finalAmountInput || !differenceElement) return;
            
            const finalAmount = parseFloat(finalAmountInput.value) || 0;
            const difference = finalAmount - currentExpectedAmount;
            
            if (finalAmountInput.value === '' || finalAmountInput.value === '0') {
                differenceElement.textContent = '-';
                differenceElement.className = 'info-value';
                return;
            }
            
            const differenceText = formatCurrency(Math.abs(difference));
            
            if (difference > 0) {
                differenceElement.textContent = `+${differenceText} (Surplus)`;
                differenceElement.className = 'info-value text-success';
            } else if (difference < 0) {
                differenceElement.textContent = `-${differenceText} (Manquant)`;
                differenceElement.className = 'info-value text-danger';
            } else {
                differenceElement.textContent = 'Montant exact ✓';
                differenceElement.className = 'info-value text-success';
            }
        }

        // Validate open register form
        function handleOpenRegisterSubmit(e) {
            e.preventDefault();
            clearErrors();
            
            const form = e.target;
            const cashierSelect = form.querySelector('select[name="cashier_id"]');
            const initialAmountInput = form.querySelector('input[name="initial_amount"]');
            const submitBtn = form.querySelector('#openRegisterBtn');
            
            let isValid = true;
            
            // Validate cashier selection
            if (!cashierSelect.value) {
                showFieldError(cashierSelect, 'cashier-error', 'Veuillez sélectionner un caissier/vendeur');
                isValid = false;
            }
            
            // Validate initial amount
            const initialAmount = parseFloat(initialAmountInput.value);
            if (!initialAmountInput.value || isNaN(initialAmount) || initialAmount < 0) {
                showFieldError(initialAmountInput, 'amount-error', 'Veuillez entrer un montant initial valide (≥ 0)');
                isValid = false;
            }
            
            if (!isValid) {
                return false;
            }
            
            // Show loading state
            setButtonLoading(submitBtn, 'Ouverture en cours...');
            
            // Submit form - The redirect will be handled by PHP PRG pattern
            form.submit();
            
            return false;
        }

        // Validate close register form
        function handleCloseRegisterSubmit(e) {
            e.preventDefault();
            clearErrors();
            
            const form = e.target;
            const finalAmountInput = form.querySelector('#finalAmount');
            const submitBtn = form.querySelector('#closeRegisterBtn');
            
            let isValid = true;
            
            // Validate final amount
            const finalAmount = parseFloat(finalAmountInput.value);
            if (!finalAmountInput.value || isNaN(finalAmount) || finalAmount < 0) {
                showFieldError(finalAmountInput, 'final-amount-error', 'Veuillez entrer un montant final valide (≥ 0)');
                isValid = false;
            }
            
            if (!isValid) {
                return false;
            }
            
            const difference = finalAmount - currentExpectedAmount;
            
            // Confirm if there's a significant difference
            if (Math.abs(difference) > 1000) { // More than 1000 XAF difference
                const confirmMessage = difference > 0 
                    ? `⚠️ ATTENTION: Il y a un surplus de ${formatCurrency(Math.abs(difference))}.\n\nÊtes-vous sûr de vouloir fermer la caisse avec ce montant ?`
                    : `⚠️ ATTENTION: Il manque ${formatCurrency(Math.abs(difference))}.\n\nÊtes-vous sûr de vouloir fermer la caisse avec ce montant ?`;
                    
                if (!confirm(confirmMessage)) {
                    return false;
                }
            }
            
            // Show loading state
            setButtonLoading(submitBtn, 'Fermeture en cours...');
            
            // Submit form - The redirect will be handled by PHP PRG pattern
            form.submit();
            
            return false;
        }

        // Validate final amount field
        function validateFinalAmount() {
            const finalAmountInput = document.getElementById('finalAmount');
            const errorElement = document.getElementById('final-amount-error');
            
            if (!finalAmountInput.value) {
                showFieldError(finalAmountInput, 'final-amount-error', 'Le montant final est requis');
            } else {
                clearFieldError(finalAmountInput, 'final-amount-error');
            }
        }

        // Show field error
        function showFieldError(field, errorId, message) {
            field.classList.add('error');
            const errorElement = document.getElementById(errorId);
            if (errorElement) {
                errorElement.textContent = message;
                errorElement.style.display = 'block';
            }
        }

        // Clear field error
        function clearFieldError(field, errorId) {
            field.classList.remove('error');
            const errorElement = document.getElementById(errorId);
            if (errorElement) {
                errorElement.style.display = 'none';
            }
        }

        // Clear all errors
        function clearErrors() {
            const errorElements = document.querySelectorAll('.error-message');
            errorElements.forEach(el => el.style.display = 'none');
            
            const errorFields = document.querySelectorAll('.form-control.error');
            errorFields.forEach(field => field.classList.remove('error'));
        }

        // Set button loading state
        function setButtonLoading(button, text) {
            if (!button) return;
            
            button.disabled = true;
            button.classList.add('btn-loading');
            
            const originalText = button.innerHTML;
            button.setAttribute('data-original-text', originalText);
            button.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status"></span>${text}`;
            
            // Reset button after 10 seconds as failsafe
            setTimeout(() => {
                if (button.disabled) {
                    button.disabled = false;
                    button.classList.remove('btn-loading');
                    button.innerHTML = originalText;
                }
            }, 10000);
        }

        // View register details (placeholder for future implementation)
       function viewRegisterDetails(registerId) {
    window.location.href = `register_details.php?id=${registerId}`;
}
        // Show notification
        function showNotification(message, type = 'info') {
            // Remove existing notification
            const existingNotification = document.getElementById('dynamicNotification');
            if (existingNotification) {
                existingNotification.remove();
            }
            
            // Create new notification
            const notification = document.createElement('div');
            notification.id = 'dynamicNotification';
            
            const alertClass = type === 'error' ? 'alert-danger' : 
                              type === 'success' ? 'alert-success' : 
                              type === 'warning' ? 'alert-warning' : 'alert-info';
            
            notification.className = `alert ${alertClass} position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 1060;
                max-width: 400px;
                animation: slideInRight 0.3s ease-out;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                border: none;
            `;
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : 
                                    type === 'error' ? 'exclamation-triangle' : 
                                    type === 'warning' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                    <span>${message}</span>
                    <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
                </div>
            `;
            
            // Add animation styles if not exist
            if (!document.getElementById('notificationStyles')) {
                const style = document.createElement('style');
                style.id = 'notificationStyles';
                style.textContent = `
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOutRight {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification && notification.parentNode) {
                    notification.style.animation = 'slideOutRight 0.3s ease-out';
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 300);
                }
            }, 5000);
        }

        // Hide alert message
        function hideAlertMessage() {
            const alertMessage = document.getElementById('alertMessage');
            if (alertMessage) {
                alertMessage.style.transition = 'all 0.3s ease-out';
                alertMessage.style.opacity = '0';
                alertMessage.style.transform = 'translateY(-10px)';
                setTimeout(() => {
                    if (alertMessage.parentNode) {
                        alertMessage.remove();
                    }
                }, 300);
            }
        }

        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR').format(Math.round(amount)) + ' XAF';
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N: Open new register (focus on cashier select)
            if ((e.ctrlKey || e.metaKey) && e.key === 'n' && !e.target.matches('input, textarea, select')) {
                e.preventDefault();
                const cashierSelect = document.querySelector('select[name="cashier_id"]');
                if (cashierSelect) {
                    cashierSelect.focus();
                }
            }
            
            // Escape: Close modals
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    const modalInstance = bootstrap.Modal.getInstance(openModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }
            }
        });

        // Handle online/offline status
        window.addEventListener('online', function() {
            showNotification('Connexion internet rétablie', 'success');
        });

        window.addEventListener('offline', function() {
            showNotification('Connexion internet perdue. Certaines fonctionnalités peuvent être limitées.', 'warning');
        });

        // Handle page visibility change for performance
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                console.log('Page hidden, pausing non-essential operations');
            } else {
                console.log('Page visible, resuming operations');
            }
        });

        // Error handling for uncaught errors
        window.addEventListener('error', function(e) {
            console.error('Uncaught error:', e.error);
            showNotification('Une erreur inattendue s\'est produite. Veuillez rafraîchir la page si le problème persiste.', 'error');
        });

        // Prevent back button issues with POST data
        if (window.history && window.history.pushState) {
            // Replace current state to prevent form resubmission on back button
            window.addEventListener('popstate', function(e) {
                if (e.state === null) {
                    window.location.reload();
                }
            });
        }

        // Log when page is fully loaded
        window.addEventListener('load', function() {
            console.log('Page fully loaded');
        });
    </script>
</body>
</html>
<?php
} else {
    header("Location: ../login.php");
    exit();
}
?>