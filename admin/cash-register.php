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
                case 'open_register':
                    $cashier_id = $_POST['cashier_id'];
                    $initial_amount = $_POST['initial_amount'];
                    
                    // Check if cashier already has an open register
                    $checkSQL = "SELECT id FROM cash_register WHERE cashier_id = ? AND status = 'open'";
                    $existing = $db->fetch($checkSQL, [$cashier_id]);
                    
                    if ($existing) {
                        $message = "Ce caissier a déjà une caisse ouverte.";
                        $messageType = "error";
                    } else {
                        $openSQL = "INSERT INTO cash_register (cashier_id, opening_time, status, initial_amount) 
                                   VALUES (?, NOW(), 'open', ?)";
                        if ($db->query($openSQL, [$cashier_id, $initial_amount])) {
                            $message = "Caisse ouverte avec succès.";
                            $messageType = "success";
                        } else {
                            $message = "Erreur lors de l'ouverture de la caisse.";
                            $messageType = "error";
                        }
                    }
                    break;
                    
                case 'close_register':
                    $register_id = $_POST['register_id'];
                    $final_amount = $_POST['final_amount'];
                    
                    // Get register details for validation
                    $registerSQL = "SELECT cr.*, u.username as cashier_name,
                                          COALESCE(SUM(s.totalAmount), 0) as total_sales
                                   FROM cash_register cr
                                   LEFT JOIN user u ON cr.cashier_id = u.id
                                   LEFT JOIN sale s ON cr.id = s.cash_register_id
                                   WHERE cr.id = ? AND cr.status = 'open'
                                   GROUP BY cr.id";
                    $register = $db->fetch($registerSQL, [$register_id]);
                    
                    if ($register) {
                        $expected_amount = $register['initial_amount'] + $register['total_sales'];
                        $difference = $final_amount - $expected_amount;
                        
                        $closeSQL = "UPDATE cash_register 
                                    SET closing_time = NOW(), status = 'closed', final_amount = ? 
                                    WHERE id = ?";
                        if ($db->query($closeSQL, [$final_amount, $register_id])) {
                            $message = "Caisse fermée avec succès. ";
                            if ($difference != 0) {
                                $message .= "Écart détecté: " . number_format($difference, 0) . " XAF";
                            }
                            $messageType = "success";
                        } else {
                            $message = "Erreur lors de la fermeture de la caisse.";
                            $messageType = "error";
                        }
                    } else {
                        $message = "Caisse introuvable ou déjà fermée.";
                        $messageType = "error";
                    }
                    break;
            }
        }
    }

    // Get current date
    $today = date('Y-m-d');

    // Get all cashiers
    $cashiersSQL = "SELECT id, username FROM user WHERE role = 'CASHIER' ORDER BY username";
    $cashiers = $db->fetchAll($cashiersSQL);
    if ($cashiers === false) {
        $cashiers = [];
    }

    // Get open registers with details
    $openRegistersSQL = "SELECT cr.*, u.username as cashier_name,
                                COALESCE(SUM(s.totalAmount), 0) as current_sales,
                                COALESCE(COUNT(s.id), 0) as sales_count,
                                TIME_FORMAT(TIMEDIFF(NOW(), cr.opening_time), '%H:%i') as time_open
                         FROM cash_register cr
                         LEFT JOIN user u ON cr.cashier_id = u.id
                         LEFT JOIN sale s ON cr.id = s.cash_register_id
                         WHERE cr.status = 'open'
                         GROUP BY cr.id, cr.cashier_id, u.username, cr.opening_time, cr.initial_amount
                         ORDER BY cr.opening_time DESC";
    
    $openRegisters = $db->fetchAll($openRegistersSQL);
    if ($openRegisters === false) {
        $openRegisters = [];
    }

    // Get today's closed registers
    $closedRegistersSQL = "SELECT cr.*, u.username as cashier_name,
                                  COALESCE(SUM(s.totalAmount), 0) as total_sales,
                                  COALESCE(COUNT(s.id), 0) as sales_count,
                                  TIME_FORMAT(TIMEDIFF(cr.closing_time, cr.opening_time), '%H:%i') as duration,
                                  (cr.final_amount - (cr.initial_amount + COALESCE(SUM(s.totalAmount), 0))) as difference
                           FROM cash_register cr
                           LEFT JOIN user u ON cr.cashier_id = u.id
                           LEFT JOIN sale s ON cr.id = s.cash_register_id
                           WHERE cr.status = 'closed' AND DATE(cr.opening_time) = ?
                           GROUP BY cr.id, cr.cashier_id, u.username, cr.opening_time, cr.closing_time, 
                                   cr.initial_amount, cr.final_amount
                           ORDER BY cr.closing_time DESC";
    
    $closedRegisters = $db->fetchAll($closedRegistersSQL, [$today]);
    if ($closedRegisters === false) {
        $closedRegisters = [];
    }

    // Get daily statistics
    $dailyStatsSQL = "SELECT 
                         COUNT(DISTINCT cr.id) as total_registers_today,
                         COUNT(DISTINCT CASE WHEN cr.status = 'open' THEN cr.id END) as open_registers,
                         COUNT(DISTINCT CASE WHEN cr.status = 'closed' THEN cr.id END) as closed_registers,
                         COALESCE(SUM(CASE WHEN cr.status = 'closed' THEN s.totalAmount END), 0) as total_sales_closed,
                         COALESCE(SUM(CASE WHEN cr.status = 'open' THEN s.totalAmount END), 0) as total_sales_open,
                         COALESCE(SUM(CASE WHEN cr.status = 'closed' THEN 
                             (cr.final_amount - cr.initial_amount - COALESCE(sales_sum.total, 0)) END), 0) as total_difference
                      FROM cash_register cr
                      LEFT JOIN sale s ON cr.id = s.cash_register_id
                      LEFT JOIN (
                          SELECT cash_register_id, SUM(totalAmount) as total
                          FROM sale
                          GROUP BY cash_register_id
                      ) sales_sum ON cr.id = sales_sum.cash_register_id
                      WHERE DATE(cr.opening_time) = ?";
    
    $dailyStats = $db->fetch($dailyStatsSQL, [$today]);
    if (!$dailyStats) {
        $dailyStats = [
            'total_registers_today' => 0,
            'open_registers' => 0,
            'closed_registers' => 0,
            'total_sales_closed' => 0,
            'total_sales_open' => 0,
            'total_difference' => 0
        ];
    }

    // Recent transactions
    $recentTransactionsSQL = "SELECT s.*, u.username as seller_name, c.name as client_name,
                                    cr_user.username as cashier_name
                              FROM sale s
                              LEFT JOIN user u ON s.sellerId = u.id
                              LEFT JOIN client c ON s.clientId = c.id
                              LEFT JOIN cash_register cr ON s.cash_register_id = cr.id
                              LEFT JOIN user cr_user ON cr.cashier_id = cr_user.id
                              WHERE DATE(s.saleDate) = ?
                              ORDER BY s.createdAt DESC
                              LIMIT 10";
    
    $recentTransactions = $db->fetchAll($recentTransactionsSQL, [$today]);
    if ($recentTransactions === false) {
        $recentTransactions = [];
    }

} catch (Exception $e) {
    $message = "Erreur: " . $e->getMessage();
    $messageType = "error";
}

// Helper functions
function formatCurrency($amount) {
    return number_format($amount, 0) . ' XAF';
}

function formatTime($time) {
    return date('H:i', strtotime($time));
}

function formatDuration($duration) {
    return $duration ?: '00:00';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        .cash-register-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .register-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 1.5rem;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><g fill="white" fill-opacity="0.05"><circle cx="20" cy="20" r="2"/></g></svg>');
            pointer-events: none;
        }

        .register-card.open {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .register-card.closed {
            background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%);
        }

        .register-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .register-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.2);
        }

        .register-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .register-stat {
            text-align: center;
        }

        .register-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .register-stat-label {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .register-actions {
            display: flex;
            gap: 0.5rem;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .overview-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            text-align: center;
        }

        .overview-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .overview-label {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .modal-backdrop.show {
            opacity: 0.5;
        }

        .table-responsive {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .difference-positive {
            color: #059669;
            font-weight: 600;
        }

        .difference-negative {
            color: #dc2626;
            font-weight: 600;
        }

        .difference-zero {
            color: #6b7280;
        }

        @media (max-width: 768px) {
            .cash-register-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-overview {
                grid-template-columns: 1fr 1fr;
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
                    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 text-white mb-1">Gestion des Caisses</h1>
                        <p class="text-light opacity-75">Contrôle et supervision des caisses enregistreuses</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#openRegisterModal">
                        <i data-lucide="plus"></i>
                        Ouvrir une caisse
                    </button>
                </div>

                <!-- Daily Statistics -->
                <div class="stats-overview">
                    <div class="overview-card">
                        <div class="overview-value text-primary"><?php echo $dailyStats['total_registers_today']; ?></div>
                        <div class="overview-label">Caisses aujourd'hui</div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-value text-success"><?php echo $dailyStats['open_registers']; ?></div>
                        <div class="overview-label">Caisses ouvertes</div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-value text-info"><?php echo formatCurrency($dailyStats['total_sales_open'] + $dailyStats['total_sales_closed']); ?></div>
                        <div class="overview-label">CA Total</div>
                    </div>
                    <div class="overview-card">
                        <div class="overview-value <?php echo $dailyStats['total_difference'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo formatCurrency($dailyStats['total_difference']); ?>
                        </div>
                        <div class="overview-label">Écart global</div>
                    </div>
                </div>

                <!-- Open Registers -->
                <div class="cash-register-grid">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title text-white">
                                <i data-lucide="lock-open"></i>
                                Caisses Ouvertes (<?php echo count($openRegisters); ?>)
                            </div>
                        </div>
                        <div class="card-content">
                            <?php if (empty($openRegisters)): ?>
                                <div class="text-center text-muted py-4">
                                    <i data-lucide="coffee" style="width: 3rem; height: 3rem;" class="mb-3 opacity-50"></i>
                                    <p>Aucune caisse ouverte actuellement</p>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#openRegisterModal">
                                        Ouvrir une caisse
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($openRegisters as $register): ?>
                                    <div class="register-card open mb-3">
                                        <div class="register-header">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($register['cashier_name']); ?></h5>
                                            <span class="register-status">OUVERTE</span>
                                        </div>
                                        
                                        <div class="register-info">
                                            <div class="register-stat">
                                                <div class="register-stat-value"><?php echo formatCurrency($register['initial_amount']); ?></div>
                                                <div class="register-stat-label">Fonds initial</div>
                                            </div>
                                            <div class="register-stat">
                                                <div class="register-stat-value"><?php echo formatCurrency($register['current_sales']); ?></div>
                                                <div class="register-stat-label"><?php echo $register['sales_count']; ?> ventes</div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-between align-items-center">
                                            <small>
                                                Ouverte: <?php echo formatTime($register['opening_time']); ?> 
                                                (<?php echo formatDuration($register['time_open']); ?>)
                                            </small>
                                            <button class="btn btn-light btn-sm" 
                                                    onclick="openCloseModal(<?php echo $register['id']; ?>, '<?php echo htmlspecialchars($register['cashier_name']); ?>', <?php echo $register['initial_amount'] + $register['current_sales']; ?>)">
                                                <i data-lucide="lock"></i>
                                                Fermer
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="card-title text-white">
                                <i data-lucide="lock"></i>
                                Caisses Fermées Aujourd'hui (<?php echo count($closedRegisters); ?>)
                            </div>
                        </div>
                        <div class="card-content">
                            <?php if (empty($closedRegisters)): ?>
                                <div class="text-center text-muted py-4">
                                    <i data-lucide="archive" style="width: 3rem; height: 3rem;" class="mb-3 opacity-50"></i>
                                    <p>Aucune caisse fermée aujourd'hui</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($closedRegisters as $register): ?>
                                    <div class="register-card closed mb-3">
                                        <div class="register-header">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($register['cashier_name']); ?></h6>
                                            <span class="register-status">FERMÉE</span>
                                        </div>
                                        
                                        <div class="register-info">
                                            <div class="register-stat">
                                                <div class="register-stat-value"><?php echo formatCurrency($register['total_sales']); ?></div>
                                                <div class="register-stat-label"><?php echo $register['sales_count']; ?> ventes</div>
                                            </div>
                                            <div class="register-stat">
                                                <div class="register-stat-value <?php echo $register['difference'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                    <?php echo formatCurrency($register['difference']); ?>
                                                </div>
                                                <div class="register-stat-label">Écart</div>
                                            </div>
                                        </div>

                                        <small>
                                            <?php echo formatTime($register['opening_time']); ?> - <?php echo formatTime($register['closing_time']); ?>
                                            (<?php echo formatDuration($register['duration']); ?>)
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title text-white">
                            <i data-lucide="credit-card"></i>
                            Transactions Récentes
                        </div>
                    </div>
                    <div class="card-content">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>N° Facture</th>
                                        <th>Heure</th>
                                        <th>Caissier</th>
                                        <th>Vendeur</th>
                                        <th>Client</th>
                                        <th>Montant</th>
                                        <th>Espèces</th>
                                        <th>Rendu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentTransactions)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                Aucune transaction aujourd'hui
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentTransactions as $transaction): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($transaction['invoiceNumber']); ?></strong>
                                                </td>
                                                <td><?php echo date('H:i', strtotime($transaction['saleDate'])); ?></td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo htmlspecialchars($transaction['cashier_name'] ?: 'N/A'); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($transaction['seller_name']); ?></td>
                                                <td><?php echo htmlspecialchars($transaction['client_name'] ?: 'Anonyme'); ?></td>
                                                <td><strong><?php echo formatCurrency($transaction['totalAmount']); ?></strong></td>
                                                <td><?php echo formatCurrency($transaction['cashReceived']); ?></td>
                                                <td class="text-success"><?php echo formatCurrency($transaction['changeAmount']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Open Register Modal -->
    <div class="modal fade" id="openRegisterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="lock-open"></i>
                        Ouvrir une Caisse
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="open_register">
                        
                        <div class="mb-3">
                            <label class="form-label">Caissier *</label>
                            <select name="cashier_id" class="form-select" required>
                                <option value="">Sélectionner un caissier</option>
                                <?php foreach ($cashiers as $cashier): ?>
                                    <option value="<?php echo $cashier['id']; ?>">
                                        <?php echo htmlspecialchars($cashier['username']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Fonds de caisse initial *</label>
                            <div class="input-group">
                                <input type="number" name="initial_amount" class="form-control" 
                                       placeholder="0" min="0" step="100" required>
                                <span class="input-group-text">XAF</span>
                            </div>
                            <div class="form-text">Montant en espèces disponible au début</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-success">
                            <i data-lucide="check"></i>
                            Ouvrir la Caisse
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Close Register Modal -->
    <div class="modal fade" id="closeRegisterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="lock"></i>
                        Fermer la Caisse
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="close_register">
                        <input type="hidden" name="register_id" id="close_register_id">
                        
                        <div class="alert alert-info">
                            <strong id="close_cashier_name"></strong><br>
                            <small>Montant attendu: <span id="expected_amount"></span></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Montant final en caisse *</label>
                            <div class="input-group">
                                <input type="number" name="final_amount" id="final_amount" class="form-control" 
                                       placeholder="0" min="0" step="100" required>
                                <span class="input-group-text">XAF</span>
                            </div>
                            <div class="form-text">Comptage physique des espèces</div>
                        </div>

                        <div id="difference_alert" class="alert" style="display: none;">
                            <strong>Écart détecté: <span id="difference_amount"></span></strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">
                            <i data-lucide="lock"></i>
                            Fermer la Caisse
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let expectedAmount = 0;

        // Open close modal with register data
        function openCloseModal(registerId, cashierName, expected) {
            expectedAmount = expected;
            document.getElementById('close_register_id').value = registerId;
            document.getElementById('close_cashier_name').textContent = cashierName;
            document.getElementById('expected_amount').textContent = expected.toLocaleString() + ' XAF';
            document.getElementById('final_amount').value = expected;
            
            // Reset difference alert
            document.getElementById('difference_alert').style.display = 'none';
            
            const closeModal = new bootstrap.Modal(document.getElementById('closeRegisterModal'));
            closeModal.show();
        }

        // Calculate difference when final amount changes
        document.getElementById('final_amount').addEventListener('input', function() {
            const finalAmount = parseFloat(this.value) || 0;
            const difference = finalAmount - expectedAmount;
            const differenceAlert = document.getElementById('difference_alert');
            const differenceAmountSpan = document.getElementById('difference_amount');
            
            if (difference !== 0) {
                differenceAlert.style.display = 'block';
                differenceAmountSpan.textContent = difference.toLocaleString() + ' XAF';
                
                if (difference > 0) {
                    differenceAlert.className = 'alert alert-warning';
                    differenceAmountSpan.textContent = '+' + differenceAmountSpan.textContent + ' (Excédent)';
                } else {
                    differenceAlert.className = 'alert alert-danger';
                    differenceAmountSpan.textContent = differenceAmountSpan.textContent + ' (Manquant)';
                }
            } else {
                differenceAlert.style.display = 'none';
            }
        });

        // Auto-refresh page every 5 minutes
        setInterval(function() {
            window.location.reload();
        }, 300000);

        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            
            // Setup sidebar
            setupSidebar();
        });

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

        // Format currency display
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR').format(amount) + ' XAF';
        }

        // Confirm close register action
        document.addEventListener('DOMContentLoaded', function() {
            const closeForm = document.querySelector('#closeRegisterModal form');
            if (closeForm) {
                closeForm.addEventListener('submit', function(e) {
                    const finalAmountInput = document.getElementById('final_amount');
                    if (finalAmountInput) {
                        const finalAmount = parseFloat(finalAmountInput.value) || 0;
                        const difference = finalAmount - expectedAmount;
                        
                        if (Math.abs(difference) > 1000) { // If difference is more than 1000 XAF
                            const confirmMessage = `Attention! Un écart de ${Math.abs(difference).toLocaleString()} XAF a été détecté.\n\nÊtes-vous sûr de vouloir fermer cette caisse?`;
                            if (!confirm(confirmMessage)) {
                                e.preventDefault();
                            }
                        }
                    }
                });
            }
        });

        // Add loading states to buttons
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
                }
            });
        });

        // Enhanced table interactions
        document.querySelectorAll('table tbody tr').forEach(row => {
            row.addEventListener('click', function() {
                // Add click effect for better UX
                this.style.backgroundColor = '#f8f9fa';
                setTimeout(() => {
                    this.style.backgroundColor = '';
                }, 200);
            });
        });

        // Real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR');
            const clockElements = document.querySelectorAll('.live-clock');
            clockElements.forEach(el => {
                el.textContent = timeString;
            });
        }

        // Update clock every second
        setInterval(updateClock, 1000);
        updateClock(); // Initial call

        // Print functionality for receipts
        function printReceipt(transactionId) {
            // This would integrate with your receipt printing system
            console.log('Printing receipt for transaction:', transactionId);
        }

        // Export data functionality
        function exportToCSV() {
            const today = new Date().toISOString().split('T')[0];
            const filename = `caisses_${today}.csv`;
            
            // This would generate and download CSV data
            console.log('Exporting data to:', filename);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + N: Open new register
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                const openModal = new bootstrap.Modal(document.getElementById('openRegisterModal'));
                openModal.show();
            }
            
            // Escape: Close modals
            if (e.key === 'Escape') {
                const openModals = document.querySelectorAll('.modal.show');
                openModals.forEach(modal => {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                });
            }
        });

        // Show keyboard shortcuts help
        function showShortcuts() {
            alert('Raccourcis clavier:\n\nCtrl + N: Ouvrir une nouvelle caisse\nEchap: Fermer les fenêtres\nF5: Actualiser la page');
        }

        // Add tooltips to buttons
        document.querySelectorAll('[title]').forEach(element => {
            new bootstrap.Tooltip(element);
        });

        // Notification system for alerts
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // Check for low cash alerts
        function checkLowCashAlerts() {
            const openRegisters = document.querySelectorAll('.register-card.open');
            openRegisters.forEach(card => {
                const currentAmount = parseFloat(card.dataset.currentAmount) || 0;
                if (currentAmount < 10000) { // Less than 10,000 XAF
                    showNotification(`Alerte: Caisse ${card.dataset.cashierName} a un solde faible (${formatCurrency(currentAmount)})`, 'warning');
                }
            });
        }

        // Run alerts check every 10 minutes
        setInterval(checkLowCashAlerts, 600000);

        // Enhanced form validation
        document.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('blur', function() {
                const value = parseFloat(this.value);
                if (value < 0) {
                    this.value = 0;
                    showNotification('Les montants négatifs ne sont pas autorisés', 'warning');
                }
            });
        });
    </script>
</body>
</html>

<?php }else{
    header("location: ../logout.php");
}?>