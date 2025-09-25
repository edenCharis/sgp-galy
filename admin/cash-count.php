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

    // Get register ID from URL
    $register_id = $_GET['id'] ?? null;
    if (!$register_id) {
        $_SESSION['flash_message'] = "ID de caisse manquant.";
        $_SESSION['flash_message_type'] = 'error';
        header("Location: cash-register.php");
        exit();
    }

    // Get register details
    $registerSQL = "SELECT 
                        cr.id,
                        cr.cashier_id,
                        cr.opening_time,
                        cr.initial_amount,
                        cr.status,
                        u.username as cashier_name,
                        u.role as cashier_role,
                        COALESCE(SUM(s.totalAmount), 0) as total_sales,
                        COALESCE(SUM(s.cashReceived), 0) as total_cash_received,
                        COUNT(s.id) as sales_count
                    FROM cash_register cr
                    LEFT JOIN user u ON cr.cashier_id = u.id
                    LEFT JOIN sale s ON cr.id = s.cash_register_id
                    WHERE cr.id = ? AND cr.status = 'open'
                    GROUP BY cr.id, cr.cashier_id, cr.opening_time, cr.initial_amount, 
                             cr.status, u.username, u.role";
    
    $register = $db->fetch($registerSQL, [$register_id]);
    
    if (!$register) {
        $_SESSION['flash_message'] = "Caisse introuvable ou déjà fermée.";
        $_SESSION['flash_message_type'] = 'error';
        header("Location: cash-register.php");
        exit();
    }

    // Calculate expected amounts
    $expected_total = $register['initial_amount'] + $register['total_cash_received'];

    // Get existing counting data if any
    $existingCountSQL = "SELECT * FROM cash_counting WHERE register_id = ? ORDER BY created_at DESC LIMIT 1";
    $existingCount = $db->fetch($existingCountSQL, [$register_id]);

    // Handle POST request for saving counting
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'save_counting') {
            // Get denomination counts
            $denominations = [
                '10000' => intval($_POST['count_10000'] ?? 0),
                '5000' => intval($_POST['count_5000'] ?? 0),
                '2000' => intval($_POST['count_2000'] ?? 0),
                '1000' => intval($_POST['count_1000'] ?? 0),
                '500' => intval($_POST['count_500'] ?? 0),
                '250' => intval($_POST['count_250'] ?? 0),
                '200' => intval($_POST['count_200'] ?? 0),
                '100' => intval($_POST['count_100'] ?? 0),
                '50' => intval($_POST['count_50'] ?? 0),
                '25' => intval($_POST['count_25'] ?? 0),
                '10' => intval($_POST['count_10'] ?? 0),
                '5' => intval($_POST['count_5'] ?? 0),
                '1' => intval($_POST['count_1'] ?? 0)
            ];

            // Get other payment methods
            $cards_amount = floatval($_POST['cards_amount'] ?? 0);
            $checks_amount = floatval($_POST['checks_amount'] ?? 0);
            $vouchers_amount = floatval($_POST['vouchers_amount'] ?? 0);

            // Calculate cash total
            $cash_total = 0;
            foreach ($denominations as $value => $count) {
                $cash_total += (intval($value) * $count);
            }

            $physical_total = $cash_total + $cards_amount + $checks_amount + $vouchers_amount;
            $difference = $physical_total - $expected_total;

            // Get justification
            $justification = trim($_POST['justification'] ?? '');
            $category = $_POST['category'] ?? 'other';

            // Validate if significant difference requires justification
            $significant_threshold = 500; // 500 XAF
            if (abs($difference) > $significant_threshold && empty($justification)) {
                $_SESSION['flash_message'] = "Une justification est obligatoire pour un écart supérieur à " . number_format($significant_threshold) . " XAF.";
                $_SESSION['flash_message_type'] = 'error';
                header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $register_id);
                exit();
            }

            try {
                $db->beginTransaction();

                // Save or update counting
                if ($existingCount) {
                    $updateSQL = "UPDATE cash_counting SET 
                                    count_10000 = ?, count_5000 = ?, count_2000 = ?, count_1000 = ?,
                                    count_500 = ?, count_250 = ?,count_200=?, count_100 = ?, count_50 = ?,
                                    count_25 = ?, count_10 = ?, count_5 = ?, count_1 = ?,
                                    cards_amount = ?, checks_amount = ?, vouchers_amount = ?,
                                    cash_total = ?, physical_total = ?, expected_total = ?,
                                    difference = ?, justification = ?, category = ?,
                                    updated_at = NOW(), updated_by = ?
                                  WHERE id = ?";
                    
                    $db->execute($updateSQL, [
                        $denominations['10000'], $denominations['5000'], $denominations['2000'], $denominations['1000'],
                        $denominations['500'], $denominations['250'],$denominations['200'],$denominations['100'], $denominations['50'],
                        $denominations['25'], $denominations['10'], $denominations['5'], $denominations['1'],
                        $cards_amount, $checks_amount, $vouchers_amount,
                        $cash_total, $physical_total, $expected_total,
                        $difference, $justification, $category,
                        $admin_id, $existingCount['id']
                    ]);
                } else {
                    $insertSQL = "INSERT INTO cash_counting (
                                    register_id, count_10000, count_5000, count_2000, count_1000,
                                    count_500, count_250,count_200, count_100, count_50, count_25, count_10, count_5, count_1,
                                    cards_amount, checks_amount, vouchers_amount,
                                    cash_total, physical_total, expected_total, difference,
                                    justification, category, created_at, created_by
                                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                    
                    $db->execute($insertSQL, [
                        $register_id,
                        $denominations['10000'], $denominations['5000'], $denominations['2000'], $denominations['1000'],
                        $denominations['500'], $denominations['250'], $denominations['200'],$denominations['100'], $denominations['50'],
                        $denominations['25'], $denominations['10'], $denominations['5'], $denominations['1'],
                        $cards_amount, $checks_amount, $vouchers_amount,
                        $cash_total, $physical_total, $expected_total, $difference,
                        $justification, $category, $admin_id
                    ]);
                }

                $db->commit();

                $_SESSION['flash_message'] = "Comptage sauvegardé avec succès.";
                $_SESSION['flash_message_type'] = 'success';

                header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $register_id);
                exit();

            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['flash_message'] = "Erreur lors de la sauvegarde : " . $e->getMessage();
                $_SESSION['flash_message_type'] = 'error';
                header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . $register_id);
                exit();
            }
        }
    }

    // Reload existing count after potential update
    $existingCount = $db->fetch($existingCountSQL, [$register_id]);

} catch (Exception $e) {
    $_SESSION['flash_message'] = "Erreur: " . $e->getMessage();
    $_SESSION['flash_message_type'] = 'error';
    header("Location: cash-register.php");
    exit();
}

// Helper functions
function formatCurrency($amount) {
    return number_format($amount, 0, ',', ' ') . ' XAF';
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Comptage de Caisse</title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        .counting-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .register-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .info-card {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .info-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0.25rem;
        }
        
        .info-value {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .counting-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .denominations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }
        
        .denomination-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .denomination-item:hover {
            border-color: #3b82f6;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
        }
        
        .denomination-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .denomination-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }
        
        .bill { background: linear-gradient(135deg, #10b981, #059669); }
        .coin { background: linear-gradient(135deg, #f59e0b, #d97706); }
        
        .denomination-details {
            display: flex;
            flex-direction: column;
        }
        
        .denomination-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .denomination-type {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .count-input-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .count-input {
            width: 80px;
            text-align: center;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.5rem;
            font-weight: 500;
        }
        
        .count-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .count-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #d1d5db;
            background: white;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .count-btn:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
        }
        
        .subtotal {
            font-weight: 600;
            color: #059669;
            min-width: 100px;
            text-align: right;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .payment-method-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        
        .payment-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .cards { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
        .checks { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .vouchers { background: linear-gradient(135deg, #ef4444, #dc2626); }
        
        .payment-input {
            flex: 1;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.75rem;
            font-size: 1rem;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }
        
        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .counting-container {
                padding: 0 10px;
            }
            
            .register-header {
                padding: 1rem;
            }
            
            .register-info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
        }
        
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .summary-item:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .summary-label {
            color: #6b7280;
        }
        
        .summary-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .difference-positive {
            color: #059669;
        }
        
        .difference-negative {
            color: #dc2626;
        }
        
        .difference-zero {
            color: #6b7280;
        }
        
        .justification-section {
            background: #fefce8;
            border: 1px solid #fde047;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .justification-section.required {
            background: #fef2f2;
            border-color: #fca5a5;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
            color: white;
            text-decoration: none;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
        
        .totals-display {
            position: sticky;
            top: 100px;
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 2px solid #f3f4f6;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            font-size: 1.1rem;
        }
        
        .total-row.main {
            border-top: 2px solid #e5e7eb;
            margin-top: 0.5rem;
            padding-top: 1rem;
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .auto-save-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #10b981;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 1000;
        }
        
        .auto-save-indicator.show {
            opacity: 1;
        }
        
        /* Fix for missing payment method inputs */
        .payment-method-input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 0.75rem;
            font-size: 1rem;
        }
        
        /* Fix for responsive design */
        @media (max-width: 576px) {
            .denomination-item {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .count-input-group {
                justify-content: center;
            }
            
            .subtotal {
                text-align: center;
            }
        }


        .denominations-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
}

.bills-section,
.coins-section {
    display: flex;
    flex-direction: column;
}

.section-subtitle {
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.bills-grid,
.coins-grid {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.denomination-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    transition: all 0.2s;
}

.denomination-item:hover {
    border-color: #3b82f6;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
}

.denomination-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.denomination-icon {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: white;
}

.bill { 
    background: linear-gradient(135deg, #10b981, #059669); 
}

.coin { 
    background: linear-gradient(135deg, #f59e0b, #d97706); 
}

.denomination-details {
    display: flex;
    flex-direction: column;
}

.denomination-value {
    font-weight: 600;
    color: #1f2937;
}

.denomination-type {
    font-size: 0.875rem;
    color: #6b7280;
}

.count-input-group {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.count-input {
    width: 80px;
    text-align: center;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    padding: 0.5rem;
    font-weight: 500;
}

.count-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.count-btn {
    width: 32px;
    height: 32px;
    border: 1px solid #d1d5db;
    background: white;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.count-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
}

.subtotal {
    font-weight: 600;
    color: #059669;
    min-width: 100px;
    text-align: right;
}

/* Responsive design */
@media (max-width: 768px) {
    .denominations-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .denomination-item {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .count-input-group {
        justify-content: center;
    }
    
    .subtotal {
        text-align: center;
    }
}

@media (max-width: 576px) {
    .denomination-info {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .denomination-details {
        text-align: center;
    }
}
    </style>
</head>
<body>
    <div class="app-layout">
        <div id="sidebarOverlay" class="sidebar-overlay"></div>
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php include 'header.php'; ?>
            
            <main class="content-area">
                <div class="counting-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0 text-gray-800 d-flex align-items-center gap-2">
                            <i data-lucide="calculator"></i>
                            Comptage de Caisse
                        </h1>
                        <a href="cash-register.php" class="btn-secondary">
                            <i data-lucide="arrow-left"></i>
                            Retour aux Caisses
                        </a>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" id="alertMessage">
                            <i data-lucide="<?php echo $messageType === 'success' ? 'check-circle' : 'alert-circle'; ?>"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <div class="register-header">
                        <h2 class="mb-0 d-flex align-items-center gap-2">
                            <i data-lucide="wallet"></i>
                            Caisse #<?php echo $register['id']; ?> - <?php echo htmlspecialchars($register['cashier_name']); ?>
                        </h2>
                        <div class="register-info-grid">
                            <div class="info-card">
                                <div class="info-label">Ouverture</div>
                                <div class="info-value"><?php echo formatDateTime($register['opening_time']); ?></div>
                            </div>
                            <div class="info-card">
                                <div class="info-label">Montant initial</div>
                                <div class="info-value"><?php echo formatCurrency($register['initial_amount']); ?></div>
                            </div>
                            <div class="info-card">
                                <div class="info-label">Ventes (<?php echo $register['sales_count']; ?>)</div>
                                <div class="info-value"><?php echo formatCurrency($register['total_sales']); ?></div>
                            </div>
                            <div class="info-card">
                                <div class="info-label">Montant attendu</div>
                                <div class="info-value"><?php echo formatCurrency($expected_total); ?></div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" id="countingForm">
                        <input type="hidden" name="action" value="save_counting">
                        
                        <div class="summary-grid">
                            <div>
                                <div class="counting-section">
                                    <h3 class="section-title">
                                        <i data-lucide="banknote"></i>
                                        Espèces - Billets et Pièces
                                    </h3>
                                  <div class="denominations-grid">
    <div class="bills-section">
        <h4 class="section-subtitle">
            <i data-lucide="banknote"></i>
            Billets
        </h4>
        <div class="bills-grid">
            <?php
            $bills = [
                '10000' => 'Billets de 10 000',
                '5000' => 'Billets de 5 000',  
                '2000' => 'Billets de 2 000',
                '1000' => 'Billets de 1 000',
                '500' => 'Billets de 500'
            ];
            
            foreach ($bills as $value => $label): 
                $count = $existingCount ? intval($existingCount["count_$value"]) : 0;
            ?>
                <div class="denomination-item">
                    <div class="denomination-info">
                        <div class="denomination-icon bill">
                            <?php echo number_format($value, 0, '', ' '); ?>
                        </div>
                        <div class="denomination-details">
                            <div class="denomination-value"><?php echo $label; ?></div>
                            <div class="denomination-type">Billet</div>
                        </div>
                    </div>
                    <div class="count-input-group">
                        <button type="button" class="count-btn" onclick="adjustCount('count_<?php echo $value; ?>', -1)">
                            <i data-lucide="minus" style="width: 16px; height: 16px;"></i>
                        </button>
                        <input type="number" name="count_<?php echo $value; ?>" id="count_<?php echo $value; ?>" 
                               class="count-input" min="0" value="<?php echo $count; ?>"
                               onchange="calculateTotals()" oninput="calculateTotals()">
                        <button type="button" class="count-btn" onclick="adjustCount('count_<?php echo $value; ?>', 1)">
                            <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
                        </button>
                    </div>
                    <div class="subtotal" id="subtotal_<?php echo $value; ?>">
                        <?php echo formatCurrency($count * intval($value)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="coins-section">
        <h4 class="section-subtitle">
            <i data-lucide="circle"></i>
            Pièces
        </h4>
        <div class="coins-grid">
            <?php
            $coins = [
                '250' => 'Pièces de 250',
                '200' => 'Pièces de 200',
                '100' => 'Pièces de 100',
                '50' => 'Pièces de 50',
                '25' => 'Pièces de 25',
                '10' => 'Pièces de 10',
                '5' => 'Pièces de 5',
                '1' => 'Pièces de 1'
            ];
            
            foreach ($coins as $value => $label): 
                $count = $existingCount ? intval($existingCount["count_$value"]) : 0;
            ?>
                <div class="denomination-item">
                    <div class="denomination-info">
                        <div class="denomination-icon coin">
                            <?php echo $value; ?>
                        </div>
                        <div class="denomination-details">
                            <div class="denomination-value"><?php echo $label; ?></div>
                            <div class="denomination-type">Pièce</div>
                        </div>
                    </div>
                    <div class="count-input-group">
                        <button type="button" class="count-btn" onclick="adjustCount('count_<?php echo $value; ?>', -1)">
                            <i data-lucide="minus" style="width: 16px; height: 16px;"></i>
                        </button>
                        <input type="number" name="count_<?php echo $value; ?>" id="count_<?php echo $value; ?>" 
                               class="count-input" min="0" value="<?php echo $count; ?>"
                               onchange="calculateTotals()" oninput="calculateTotals()">
                        <button type="button" class="count-btn" onclick="adjustCount('count_<?php echo $value; ?>', 1)">
                            <i data-lucide="plus" style="width: 16px; height: 16px;"></i>
                        </button>
                    </div>
                    <div class="subtotal" id="subtotal_<?php echo $value; ?>">
                        <?php echo formatCurrency($count * intval($value)); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
                                </div>

                                <!-- Payment Methods Section - FIXED: Added missing payment method inputs -->
                               
                            </div>

                            <div>
                                <div class="totals-display">
                                    <h3 class="section-title">
                                        <i data-lucide="calculator"></i>
                                        Récapitulatif
                                    </h3>
                                    
                                    <div class="total-row">
                                        <span>Total espèces:</span>
                                        <span id="cash-total">0 XAF</span>
                                    </div>
                                    
                                    <div class="total-row">
                                        <span>Cartes bancaires:</span>
                                        <span id="cards-total">0 XAF</span>
                                    </div>
                                    
                                    <div class="total-row">
                                        <span>Chèques:</span>
                                        <span id="checks-total">0 XAF</span>
                                    </div>
                                    
                                    <div class="total-row">
                                        <span>Bons/Tickets:</span>
                                        <span id="vouchers-total">0 XAF</span>
                                    </div>
                                    
                                    <div class="total-row main">
                                        <span>Total physique:</span>
                                        <span id="physical-total">0 XAF</span>
                                    </div>
                                    
                                    <div class="total-row">
                                        <span>Total attendu:</span>
                                        <span><?php echo formatCurrency($expected_total); ?></span>
                                    </div>
                                    
                                    <div class="total-row main">
                                        <span>Écart:</span>
                                        <span id="difference" class="difference-zero">0 XAF</span>
                                    </div>
                                </div>

                                <div id="justification-section" class="justification-section" style="display: none;">
                                    <h4 class="mb-3">
                                        <i data-lucide="message-circle"></i>
                                        Justification de l'écart
                                    </h4>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Catégorie d'écart</label>
                                        <select name="category" class="form-control">
                                            <option value="error_change" <?php echo ($existingCount && $existingCount['category'] === 'error_change') ? 'selected' : ''; ?>>Erreur de rendu de monnaie</option>
                                            <option value="error_input" <?php echo ($existingCount && $existingCount['category'] === 'error_input') ? 'selected' : ''; ?>>Erreur de saisie</option>
                                            <option value="theft" <?php echo ($existingCount && $existingCount['category'] === 'theft') ? 'selected' : ''; ?>>Vol/Perte</option>
                                            <option value="damage" <?php echo ($existingCount && $existingCount['category'] === 'damage') ? 'selected' : ''; ?>>Dégradation billet/pièce</option>
                                            <option value="bank_error" <?php echo ($existingCount && $existingCount['category'] === 'bank_error') ? 'selected' : ''; ?>>Erreur bancaire</option>
                                            <option value="other" <?php echo ($existingCount && $existingCount['category'] === 'other') ? 'selected' : ''; ?>>Autre</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Explication détaillée *</label>
                                        <textarea name="justification" class="form-control" rows="3" 
                                                  placeholder="Décrivez précisément l'origine de cet écart..."
                                                  ><?php echo $existingCount ? htmlspecialchars($existingCount['justification']) : ''; ?></textarea>
                                    </div>
                                </div>

                                <div style="margin-top: 2rem;">
                                    <button type="submit" class="btn-primary w-100 mb-3">
                                        <i data-lucide="save"></i>
                                        Sauvegarder le comptage
                                    </button>
                                    
                                    <?php if ($existingCount): ?>
                                        <div class="text-center text-muted">
                                            <small>
                                                <i data-lucide="clock"></i>
                                                Dernière modification: <?php echo formatDateTime($existingCount['updated_at'] ?? $existingCount['created_at']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <div id="autoSaveIndicator" class="auto-save-indicator">
        <i data-lucide="check" style="width: 16px; height: 16px;"></i>
        Sauvegardé automatiquement
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Initialize Lucide icons
    document.addEventListener('DOMContentLoaded', function() {
        // Wait for Lucide to load
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        } else {
            // Retry if Lucide isn't loaded yet
            setTimeout(() => {
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
            }, 100);
        }
    });
</script>
    
    <script>
        // Constants
        const SIGNIFICANT_THRESHOLD = 500; // XAF
        const EXPECTED_TOTAL = <?php echo $expected_total; ?>;
        
        // Auto-save functionality
        let autoSaveTimeout;
        let hasChanges = false;

        // Initialize the application when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing cash counting app...');
            initializeApp();
        });

        function initializeApp() {
            try {
                console.log('Initializing application...');
                setupSidebar();
                calculateTotals();
                setupAutoSave();
                
                // Initialize Lucide icons
                if (typeof lucide !== 'undefined') {
                    lucide.createIcons();
                }
                
                // Auto-hide alert messages
                setTimeout(hideAlertMessage, 5000);
                
                console.log('Cash counting app initialized successfully');
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

        // Adjust count with buttons
        function adjustCount(inputId, delta) {
            const input = document.getElementById(inputId);
            if (!input) return;
            
            const currentValue = parseInt(input.value) || 0;
            const newValue = Math.max(0, currentValue + delta);
            
            input.value = newValue;
            calculateTotals();
            markChanged();
        }

        // Calculate all totals and differences
        function calculateTotals() {
            let cashTotal = 0;
            
            // Calculate cash from denominations
            const denominations = ['10000', '5000', '2000', '1000', '500', '250', '100', '50', '25', '10', '5', '1'];
            
            denominations.forEach(denom => {
                const countElement = document.getElementById(`count_${denom}`);
                if (countElement) {
                    const count = parseInt(countElement.value) || 0;
                    const value = parseInt(denom);
                    const subtotal = count * value;
                    
                    cashTotal += subtotal;
                    
                    // Update individual subtotal display
                    const subtotalElement = document.getElementById(`subtotal_${denom}`);
                    if (subtotalElement) {
                        subtotalElement.textContent = formatCurrency(subtotal);
                    }
                }
            });

            // Get other payment methods
            const cardsAmount = parseFloat(document.getElementById('cards_amount').value) || 0;
            const checksAmount = parseFloat(document.getElementById('checks_amount').value) || 0;
            const vouchersAmount = parseFloat(document.getElementById('vouchers_amount').value) || 0;

            // Calculate totals
            const physicalTotal = cashTotal + cardsAmount + checksAmount + vouchersAmount;
            const difference = physicalTotal - EXPECTED_TOTAL;

            // Update display
            document.getElementById('cash-total').textContent = formatCurrency(cashTotal);
            document.getElementById('cards-total').textContent = formatCurrency(cardsAmount);
            document.getElementById('checks-total').textContent = formatCurrency(checksAmount);
            document.getElementById('vouchers-total').textContent = formatCurrency(vouchersAmount);
            document.getElementById('physical-total').textContent = formatCurrency(physicalTotal);

            // Update difference with styling
            const differenceElement = document.getElementById('difference');
            if (differenceElement) {
                // Remove all difference classes
                differenceElement.classList.remove('difference-positive', 'difference-negative', 'difference-zero');
                
                // Clear any existing warning text
                const existingWarning = differenceElement.querySelector('.warning-text');
                if (existingWarning) {
                    existingWarning.remove();
                }
                
                if (difference > 0) {
                    differenceElement.textContent = '+' + formatCurrency(difference);
                    differenceElement.classList.add('difference-positive');
                } else if (difference < 0) {
                    differenceElement.textContent = '-' + formatCurrency(Math.abs(difference));
                    differenceElement.classList.add('difference-negative');
                } else {
                    differenceElement.textContent = formatCurrency(0);
                    differenceElement.classList.add('difference-zero');
                }

                // Show/hide justification section
                const justificationSection = document.getElementById('justification-section');
                if (justificationSection) {
                    if (Math.abs(difference) > SIGNIFICANT_THRESHOLD) {
                        justificationSection.style.display = 'block';
                        justificationSection.classList.add('required');
                        
                        // Add warning to difference
                        const warningText = document.createElement('div');
                        warningText.className = 'warning-text';
                        warningText.style.fontSize = '0.8rem';
                        warningText.style.fontWeight = 'normal';
                        warningText.style.color = '#dc2626';
                        warningText.textContent = 'Justification requise';
                        differenceElement.appendChild(warningText);
                    } else {
                        justificationSection.style.display = 'none';
                        justificationSection.classList.remove('required');
                    }
                }
            }
        }

        // Mark form as changed
        function markChanged() {
            hasChanges = true;
            
            // Clear existing auto-save timeout
            if (autoSaveTimeout) {
                clearTimeout(autoSaveTimeout);
            }
            
            // Set new auto-save timeout
            autoSaveTimeout = setTimeout(autoSave, 2000);
        }

        // Setup auto-save functionality
        function setupAutoSave() {
            const form = document.getElementById('countingForm');
            if (form) {
                const inputs = form.querySelectorAll('input, select, textarea');
                
                inputs.forEach(input => {
                    input.addEventListener('input', markChanged);
                    input.addEventListener('change', markChanged);
                });
            }
        }

        // Auto-save function
        async function autoSave() {
            if (!hasChanges) return;
            
            try {
                const form = document.getElementById('countingForm');
                if (!form) return;
                
                const formData = new FormData(form);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    showAutoSaveIndicator();
                    hasChanges = false;
                    console.log('Auto-saved successfully');
                } else {
                    console.error('Auto-save failed:', response.statusText);
                }
            } catch (error) {
                console.error('Auto-save error:', error);
            }
        }

        // Show auto-save indicator
        function showAutoSaveIndicator() {
            const indicator = document.getElementById('autoSaveIndicator');
            if (indicator) {
                indicator.classList.add('show');
                
                setTimeout(() => {
                    indicator.classList.remove('show');
                }, 2000);
            }
        }

        // Format currency
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-FR').format(Math.round(amount)) + ' XAF';
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

        // Form validation before submit
        const countingForm = document.getElementById('countingForm');
        if (countingForm) {
            countingForm.addEventListener('submit', function(e) {
                const physicalTotalText = document.getElementById('physical-total').textContent;
                const physicalTotal = parseFloat(physicalTotalText.replace(/[^\d]/g, '')) || 0;
                const difference = physicalTotal - EXPECTED_TOTAL;
                
                // Check if justification is required
                if (Math.abs(difference) > SIGNIFICANT_THRESHOLD) {
                    const justificationTextarea = document.querySelector('textarea[name="justification"]');
                    const justification = justificationTextarea ? justificationTextarea.value.trim() : '';
                    if (!justification) {
                        e.preventDefault();
                        alert('Une justification est obligatoire pour un écart supérieur à ' + formatCurrency(SIGNIFICANT_THRESHOLD) + '.');
                        if (justificationTextarea) justificationTextarea.focus();
                        return false;
                    }
                }
                
                // Confirm significant differences
                if (Math.abs(difference) > 2000) {
                    const confirmMessage = difference > 0 
                        ? `⚠️ ATTENTION: Surplus important de ${formatCurrency(Math.abs(difference))}.\n\nConfirmez-vous ce comptage ?`
                        : `⚠️ ATTENTION: Manque important de ${formatCurrency(Math.abs(difference))}.\n\nConfirmez-vous ce comptage ?`;
                        
                    if (!confirm(confirmMessage)) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                return true;
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S: Save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const form = document.getElementById('countingForm');
                if (form) form.submit();
            }
            
            // Ctrl/Cmd + R: Recalculate
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                calculateTotals();
            }
        });

        // Warn before leaving if there are unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
                return 'Vous avez des modifications non sauvegardées. Êtes-vous sûr de vouloir quitter ?';
            }
        });
    </script>
</body>
</html>
<?php
} else {
    header("Location: ../logout.php");
    exit();
}
?>