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

    // Get register ID from URL parameter
    $register_id = isset($_GET['id']) ? ($_GET['id']) : 0;
    
    if (!$register_id) {
        throw new Exception('ID de caisse manquant');
    }

    // Get register information with cashier details
    $registerSQL = "SELECT 
                        cr.id,
                        cr.cashier_id,
                        cr.opening_time,
                        cr.closing_time,
                        cr.status,
                        cr.initial_amount,
                        cr.final_amount,
                        u.username as cashier_name,
                        u.role as cashier_role,
                        COALESCE(SUM(s.totalAmount), 0) as total_sales,
                        COUNT(s.id) as total_transactions,
                        COALESCE(SUM(s.cashReceived), 0) as total_cash_received,
                        COALESCE(SUM(s.changeAmount), 0) as total_change_given,
                        COALESCE(SUM(s.totalVAT), 0) as total_vat,
                        COALESCE(SUM(s.discountAmount), 0) as total_discount
                    FROM cash_register cr
                    LEFT JOIN user u ON cr.cashier_id = u.id
                    LEFT JOIN sale s ON cr.id = s.cash_register_id
                    WHERE cr.id = ?
                    GROUP BY cr.id, cr.cashier_id, cr.opening_time, cr.closing_time, 
                            cr.status, cr.initial_amount, cr.final_amount, u.username, u.role";
    
    $register = $db->fetch($registerSQL, [$register_id]);
    
    if (!$register) {
        throw new Exception('Caisse introuvable');
    }

    // Get all sales for this register with client information
    $salesSQL = "SELECT 
                    s.id,
                    s.saleDate,
                    s.totalAmount,
                    s.totalVAT,
                    s.discountAmount,
                    s.invoiceNumber,
                    s.sellerId,
                    s.clientId,
                    s.cashReceived,
                    s.changeAmount,
                    seller.username as seller_name,
                    client.name as client_name,
                    client.contact as client_phone
                 FROM sale s
                 LEFT JOIN user seller ON s.sellerId = seller.id
                 LEFT JOIN client ON s.clientId = client.id
                 WHERE s.cash_register_id = ?
                 ORDER BY s.saleDate DESC";
    
    $sales = $db->fetchAll($salesSQL, [$register_id]);
    if (!$sales) $sales = [];

    // Get sale items for each sale
    $saleItems = [];
    foreach ($sales as $sale) {
        $itemsSQL = "SELECT 
                        si.id,
                        si.saleId,
                        si.productId,
                        si.quantity,
                        si.unitPrice,
                        si.discount,
                        si.vatAmount,
                        p.name as product_name,
                        p.description as product_description,
                        (si.quantity * si.unitPrice - si.discount + si.vatAmount) as line_total
                     FROM saleitem si
                     LEFT JOIN product p ON si.productId = p.id
                     WHERE si.saleId = ?
                     ORDER BY si.id";
        
        $items = $db->fetchAll($itemsSQL, [$sale['id']]);
        $saleItems[$sale['id']] = $items ? $items : [];
    }

    // Calculate additional statistics
    $statistics = [
        'expected_amount' => $register['initial_amount'] + $register['total_sales'],
        'difference' => $register['status'] === 'closed' ? ($register['final_amount'] - ($register['initial_amount'] + $register['total_sales'])) : 0,
        'average_sale' => $register['total_transactions'] > 0 ? ($register['total_sales'] / $register['total_transactions']) : 0,
        'duration_hours' => $register['closing_time'] ? round((strtotime($register['closing_time']) - strtotime($register['opening_time'])) / 3600, 1) : 0
    ];

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

// Helper functions
function formatCurrency($amount) {
    return number_format($amount, 0) . ' XAF';
}

function formatDateTime($datetime) {
    return date('d/m/Y H:i', strtotime($datetime));
}

function formatDate($date) {
    return date('d/m/Y', strtotime($date));
}

function formatTime($datetime) {
    return date('H:i', strtotime($datetime));
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PharmaSys - Détails Caisse #<?php echo $register_id; ?></title>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        .register-details-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .register-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
            margin-left: 1rem;
        }

        .register-status.open {
            background-color: rgba(16, 185, 129, 0.2);
            color: #10b981;
            border: 2px solid #10b981;
        }

        .register-status.closed {
            background-color: rgba(107, 114, 128, 0.2);
            color: #6b7280;
            border: 2px solid #6b7280;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
            border: 1px solid #e5e7eb;
        }

        .stat-icon {
            width: 3rem;
            height: 3rem;
            margin: 0 auto 1rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.primary { background-color: #dbeafe; color: #3b82f6; }
        .stat-icon.success { background-color: #d1fae5; color: #10b981; }
        .stat-icon.warning { background-color: #fef3c7; color: #f59e0b; }
        .stat-icon.danger { background-color: #fee2e2; color: #ef4444; }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6b7280;
            font-weight: 500;
        }

        .sales-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .table-header {
            background-color: #f8fafc;
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sale-row {
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background-color 0.2s;
            user-select: none;
        }

        .sale-row:hover {
            background-color: #f9fafb;
        }

        .sale-row.expanded {
            background-color: #f0f9ff;
        }

        .sale-header {
            padding: 1rem 1.5rem;
            display: grid;
            grid-template-columns: auto 1fr auto auto auto;
            gap: 1rem;
            align-items: center;
        }

        .sale-details {
            background-color: #fafbfc;
            padding: 0;
            border-top: 1px solid #e5e7eb;
            display: none;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .sale-details.show {
            display: block !important;
        }

        .items-table {
            width: 100%;
            margin: 0;
        }

        .items-table thead th {
            background-color: #f1f5f9;
            padding: 0.75rem;
            font-weight: 600;
            color: #475569;
            border: none;
            font-size: 0.875rem;
        }

        .items-table tbody td {
            padding: 0.75rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.875rem;
        }

        .expand-icon {
            transition: transform 0.2s;
            color: #6b7280;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .expand-icon.rotated {
            transform: rotate(90deg);
        }

        .sale-info {
            display: flex;
            flex-direction: column;
        }

        .sale-invoice {
            font-weight: 600;
            color: #1f2937;
        }

        .sale-time {
            font-size: 0.875rem;
            color: #6b7280;
        }

        .sale-client {
            font-size: 0.875rem;
            color: #6366f1;
            font-weight: 500;
        }

        .sale-amount {
            font-weight: 600;
            color: #1f2937;
            font-size: 1.1rem;
        }

        .back-button {
            background-color: #6b7280;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .back-button:hover {
            background-color: #4b5563;
            color: white;
            text-decoration: none;
        }

        .print-button {
            background-color: #3b82f6;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.2s;
        }

        .print-button:hover {
            background-color: #2563eb;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .text-success { color: #10b981 !important; }
        .text-danger { color: #ef4444 !important; }
        .text-warning { color: #f59e0b !important; }

        @media (max-width: 768px) {
            .register-details-header {
                padding: 1.5rem 1rem;
            }

            .sale-header {
                grid-template-columns: 1fr;
                gap: 0.5rem;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .actions-bar {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }

        .no-sales-message {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }

        .no-sales-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #d1d5db;
        }

        /* Styles d'impression améliorés */
        @media print {
            body * {
                visibility: hidden;
            }
            
            .print-content, 
            .print-content * {
                visibility: visible;
            }
            
            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            
            /* Masquer tout sauf le contenu d'impression */
            .no-print {
                display: none !important;
            }
            
            /* Styles pour l'impression du rapport */
            .print-header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 20px;
            }
            
            .print-header h1 {
                font-size: 24px;
                margin: 0;
                color: #333;
            }
            
            .print-header p {
                margin: 5px 0;
                font-size: 14px;
                color: #666;
            }
            
            .print-stats {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
                margin-bottom: 30px;
            }
            
            .print-stat {
                border: 1px solid #ddd;
                padding: 15px;
                text-align: center;
            }
            
            .print-stat-label {
                font-weight: bold;
                margin-bottom: 5px;
                font-size: 12px;
                color: #666;
            }
            
            .print-stat-value {
                font-size: 16px;
                font-weight: bold;
                color: #333;
            }
            
            .print-sales-table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
                font-size: 12px;
            }
            
            .print-sales-table th,
            .print-sales-table td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }
            
            .print-sales-table th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            
            .print-sales-table .items-details {
                font-size: 10px;
                color: #666;
                margin-top: 5px;
            }
            
            /* Page break settings */
            .print-content {
                page-break-inside: avoid;
            }
            
            .print-sales-table tr {
                page-break-inside: avoid;
            }
        }

        /* Contenu d'impression masqué par défaut */
        .print-content {
            display: none;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <div id="sidebarOverlay" class="sidebar-overlay no-print"></div>
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="no-print">
                <?php include 'header.php'; ?>
            </div>
            
            <!-- Content Area -->
            <main class="content-area">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <i data-lucide="alert-circle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="cash-register.php" class="back-button">
                            <i data-lucide="arrow-left"></i>
                            Retour aux caisses
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Actions Bar -->
                    <div class="actions-bar no-print">
                        <a href="cash-register.php" class="back-button">
                            <i data-lucide="arrow-left"></i>
                            Retour aux caisses
                        </a>
                        <button onclick="window.print()" class="print-button">
                            <i data-lucide="printer"></i>
                            Imprimer
                        </button>
                    </div>

                    <!-- Register Header -->
                    <div class="register-details-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="h2 mb-2">
                                    <i data-lucide="wallet"></i>
                                    Caisse #<?php echo $register['id']; ?>
                                    <span class="register-status <?php echo $register['status']; ?>">
                                        <?php echo $register['status'] === 'open' ? 'Ouverte' : 'Fermée'; ?>
                                    </span>
                                </h1>
                                <p class="mb-0 opacity-75">
                                    Caissier: <strong><?php echo htmlspecialchars($register['cashier_name']); ?></strong>
                                    (<?php echo $register['cashier_role']; ?>)
                                </p>
                            </div>
                            <div class="text-end">
                                <div class="mb-2">
                                    <strong>Ouvert le:</strong><br>
                                    <?php echo formatDateTime($register['opening_time']); ?>
                                </div>
                                <?php if ($register['closing_time']): ?>
                                    <div>
                                        <strong>Fermé le:</strong><br>
                                        <?php echo formatDateTime($register['closing_time']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Grid -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon primary">
                                <i data-lucide="banknote"></i>
                            </div>
                            <div class="stat-value"><?php echo formatCurrency($register['initial_amount']); ?></div>
                            <div class="stat-label">Montant initial</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon success">
                                <i data-lucide="trending-up"></i>
                            </div>
                            <div class="stat-value"><?php echo formatCurrency($register['total_sales']); ?></div>
                            <div class="stat-label">Total des ventes</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon warning">
                                <i data-lucide="shopping-cart"></i>
                            </div>
                            <div class="stat-value"><?php echo $register['total_transactions']; ?></div>
                            <div class="stat-label">Transactions</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon primary">
                                <i data-lucide="calculator"></i>
                            </div>
                            <div class="stat-value"><?php echo formatCurrency($statistics['average_sale']); ?></div>
                            <div class="stat-label">Vente moyenne</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon success">
                                <i data-lucide="dollar-sign"></i>
                            </div>
                            <div class="stat-value"><?php echo formatCurrency($register['total_cash_received']); ?></div>
                            <div class="stat-label">Espèces reçues</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon warning">
                                <i data-lucide="coins"></i>
                            </div>
                            <div class="stat-value"><?php echo formatCurrency($register['total_change_given']); ?></div>
                            <div class="stat-label">Monnaie rendue</div>
                        </div>

                        <?php if ($register['status'] === 'closed'): ?>
                            <div class="stat-card">
                                <div class="stat-icon primary">
                                    <i data-lucide="wallet"></i>
                                </div>
                                <div class="stat-value"><?php echo formatCurrency($register['final_amount']); ?></div>
                                <div class="stat-label">Montant final</div>
                            </div>

                            <div class="stat-card">
                                <div class="stat-icon <?php echo $statistics['difference'] >= 0 ? 'success' : 'danger'; ?>">
                                    <i data-lucide="<?php echo $statistics['difference'] >= 0 ? 'trending-up' : 'trending-down'; ?>"></i>
                                </div>
                                <div class="stat-value <?php echo $statistics['difference'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php 
                                    echo $statistics['difference'] >= 0 ? '+' : '';
                                    echo formatCurrency($statistics['difference']); 
                                    ?>
                                </div>
                                <div class="stat-label">Écart</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sales List -->
                    <div class="sales-table">
                        <div class="table-header">
                            <h3 class="table-title">
                                <i data-lucide="receipt"></i>
                                Détail des ventes (<?php echo count($sales); ?>)
                            </h3>
                        </div>

                        <?php if (empty($sales)): ?>
                            <div class="no-sales-message">
                                <div class="no-sales-icon">
                                    <i data-lucide="shopping-cart"></i>
                                </div>
                                <h4>Aucune vente enregistrée</h4>
                                <p>Cette caisse n'a encore enregistré aucune vente.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($sales as $sale): ?>
                                <div class="sale-row" data-sale-id="<?php echo $sale['id']; ?>">
                                    <div class="sale-header">
                                        <div class="expand-icon" id="icon-<?php echo $sale['id']; ?>">
                                            <i data-lucide="chevron-right"></i>
                                        </div>
                                        
                                        <div class="sale-info">
                                            <div class="sale-invoice">Facture #<?php echo htmlspecialchars($sale['invoiceNumber']); ?></div>
                                            <div class="sale-time"><?php echo formatDateTime($sale['saleDate']); ?></div>
                                            <?php if ($sale['client_name']): ?>
                                                <div class="sale-client">
                                                    <?php echo htmlspecialchars($sale['client_name']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="text-center">
                                            <div class="sale-amount"><?php echo formatCurrency($sale['totalAmount']); ?></div>
                                            <small class="text-muted"><?php echo count($saleItems[$sale['id']]); ?> article(s)</small>
                                        </div>
                                        
                                        <div class="text-center">
                                            <div class="fw-bold"><?php echo formatCurrency($sale['cashReceived']); ?></div>
                                            <small class="text-muted">Reçu</small>
                                        </div>
                                        
                                        <div class="text-center">
                                            <div class="fw-bold"><?php echo formatCurrency($sale['changeAmount']); ?></div>
                                            <small class="text-muted">Rendu</small>
                                        </div>
                                    </div>
                                    
                                    <div class="sale-details" id="details-<?php echo $sale['id']; ?>">
                                        <?php if (!empty($saleItems[$sale['id']])): ?>
                                            <table class="items-table">
                                                <thead>
                                                    <tr>
                                                        <th>Produit</th>
                                                        <th>Quantité</th>
                                                        <th>Prix unitaire</th>
                                                        <th>Remise</th>
                                                        <th>TVA</th>
                                                        <th>Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($saleItems[$sale['id']] as $item): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="fw-bold"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                                                <?php if ($item['product_description']): ?>
                                                                    <small class="text-muted"><?php echo htmlspecialchars($item['product_description']); ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo $item['quantity']; ?></td>
                                                            <td><?php echo formatCurrency($item['unitPrice']); ?></td>
                                                            <td><?php echo formatCurrency($item['discount']); ?></td>
                                                            <td><?php echo formatCurrency($item['vatAmount']); ?></td>
                                                            <td class="fw-bold"><?php echo formatCurrency($item['line_total']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="p-4 text-center text-muted">
                                                Aucun détail d'article disponible
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Contenu d'impression séparé -->
    <div class="print-content">
        <div class="print-header">
            <h1>Rapport de Caisse #<?php echo $register['id']; ?></h1>
            <p>PharmaSys - Système de Gestion Pharmaceutique</p>
            <p>Caissier: <?php echo htmlspecialchars($register['cashier_name']); ?> (<?php echo $register['cashier_role']; ?>)</p>
            <p>Période: Du <?php echo formatDateTime($register['opening_time']); ?> 
            <?php if ($register['closing_time']): ?>
                au <?php echo formatDateTime($register['closing_time']); ?>
            <?php endif; ?>
            </p>
            <p>Status: <?php echo $register['status'] === 'open' ? 'Ouvert' : 'Fermé'; ?></p>
        </div>

        <div class="print-stats">
            <div class="print-stat">
                <div class="print-stat-label">Montant Initial</div>
                <div class="print-stat-value"><?php echo formatCurrency($register['initial_amount']); ?></div>
            </div>
            <div class="print-stat">
                <div class="print-stat-label">Total des Ventes</div>
                <div class="print-stat-value"><?php echo formatCurrency($register['total_sales']); ?></div>
            </div>
            <div class="print-stat">
                <div class="print-stat-label">Nombre de Transactions</div>
                <div class="print-stat-value"><?php echo $register['total_transactions']; ?></div>
            </div>
            <div class="print-stat">
                <div class="print-stat-label">Vente Moyenne</div>
                <div class="print-stat-value"><?php echo formatCurrency($statistics['average_sale']); ?></div>
            </div>
            <div class="print-stat">
                <div class="print-stat-label">Espèces Reçues</div>
                <div class="print-stat-value"><?php echo formatCurrency($register['total_cash_received']); ?></div>
            </div>
            <div class="print-stat">
                <div class="print-stat-label">Monnaie Rendue</div>
                <div class="print-stat-value"><?php echo formatCurrency($register['total_change_given']); ?></div>
            </div>
            <?php if ($register['status'] === 'closed'): ?>
            <div class="print-stat">
                <div class="print-stat-label">Montant Final</div>
                <div class="print-stat-value"><?php echo formatCurrency($register['final_amount']); ?></div>
            </div>
            <div class="print-stat">
                <div class="print-stat-label">Écart</div>
                <div class="print-stat-value" style="color: <?php echo $statistics['difference'] >= 0 ? '#10b981' : '#ef4444'; ?>">
                    <?php 
                    echo $statistics['difference'] >= 0 ? '+' : '';
                    echo formatCurrency($statistics['difference']); 
                    ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($sales)): ?>
        <table class="print-sales-table">
            <thead>
                <tr>
                    <th>Facture</th>
                    <th>Date/Heure</th>
                    <th>Client</th>
                    <th>Articles</th>
                    <th>Total</th>
                    <th>Reçu</th>
                    <th>Rendu</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales as $sale): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($sale['invoiceNumber']); ?></td>
                    <td><?php echo formatDateTime($sale['saleDate']); ?></td>
                    <td><?php echo $sale['client_name'] ? htmlspecialchars($sale['client_name']) : 'Client anonyme'; ?></td>
                    <td>
                        <?php echo count($saleItems[$sale['id']]); ?> article(s)
                        <div class="items-details">
                            <?php if (!empty($saleItems[$sale['id']])): ?>
                                <?php foreach ($saleItems[$sale['id']] as $item): ?>
                                    <?php echo htmlspecialchars($item['product_name']); ?> (<?php echo $item['quantity']; ?>)<br>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td><?php echo formatCurrency($sale['totalAmount']); ?></td>
                    <td><?php echo formatCurrency($sale['cashReceived']); ?></td>
                    <td><?php echo formatCurrency($sale['changeAmount']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; margin-top: 30px; font-style: italic;">Aucune vente enregistrée pour cette caisse.</p>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Lucide icons
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
            setupSidebar();
            
            // Debug: Vérifier que les éléments existent
            console.log('Page chargée, vérification des éléments...');
            const saleRows = document.querySelectorAll('.sale-row');
            console.log('Nombre de lignes de vente trouvées:', saleRows.length);
        });

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

        // Toggle sale details - FONCTION ENTIÈREMENT RÉÉCRITE
        function toggleSaleDetails(saleId) {
            console.log('toggleSaleDetails appelée avec ID:', saleId);
            
            const details = document.getElementById('details-' + saleId);
            const icon = document.getElementById('icon-' + saleId);
            const row = details ? details.closest('.sale-row') : null;
            
            console.log('Éléments trouvés:');
            console.log('- details:', details);
            console.log('- icon:', icon);
            console.log('- row:', row);
            
            if (!details) {
                console.error('Élément details-' + saleId + ' non trouvé');
                return;
            }
            
            if (!icon) {
                console.error('Élément icon-' + saleId + ' non trouvé');
                return;
            }
            
            // Basculer l'affichage
            const isCurrentlyVisible = details.style.display === 'block' || details.classList.contains('show');
            console.log('Actuellement visible:', isCurrentlyVisible);
            
            if (isCurrentlyVisible) {
                // Fermer
                details.style.display = 'none';
                details.classList.remove('show');
                icon.classList.remove('rotated');
                if (row) row.classList.remove('expanded');
                console.log('Détails fermés');
            } else {
                // Ouvrir
                details.style.display = 'block';
                details.classList.add('show');
                icon.classList.add('rotated');
                if (row) row.classList.add('expanded');
                console.log('Détails ouverts');
            }
        }

        // Alternative: Ajouter des event listeners directement
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter des event listeners à toutes les lignes de vente
            const saleRows = document.querySelectorAll('.sale-row');
            console.log('Ajout d\'event listeners à', saleRows.length, 'lignes');
            
            saleRows.forEach(function(row) {
                const saleId = row.querySelector('.sale-details') ? 
                    row.querySelector('.sale-details').id.replace('details-', '') : null;
                
                if (saleId) {
                    console.log('Ajout event listener pour vente ID:', saleId);
                    row.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        console.log('Clic détecté sur ligne vente ID:', saleId);
                        toggleSaleDetails(saleId);
                    });
                    
                    // Style du curseur pour indiquer que c'est cliquable
                    row.style.cursor = 'pointer';
                }
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape: Go back
            if (e.key === 'Escape') {
                window.location.href = 'cash-register.php';
            }
            
            // Ctrl/Cmd + P: Print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
        });

        // Print functionality - AMÉLIORÉE
        window.addEventListener('beforeprint', function() {
            // Afficher le contenu d'impression
            const printContent = document.querySelector('.print-content');
            if (printContent) {
                printContent.style.display = 'block';
            }
        });

        window.addEventListener('afterprint', function() {
            // Masquer le contenu d'impression après impression
            const printContent = document.querySelector('.print-content');
            if (printContent) {
                printContent.style.display = 'none';
            }
        });

        // Alternative print function si les événements ne marchent pas
        function customPrint() {
            // Créer une nouvelle fenêtre pour l'impression
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            const printContent = document.querySelector('.print-content').innerHTML;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Rapport de Caisse #<?php echo $register['id']; ?></title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .print-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                        .print-header h1 { font-size: 24px; margin: 0; color: #333; }
                        .print-header p { margin: 5px 0; font-size: 14px; color: #666; }
                        .print-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
                        .print-stat { border: 1px solid #ddd; padding: 15px; text-align: center; }
                        .print-stat-label { font-weight: bold; margin-bottom: 5px; font-size: 12px; color: #666; }
                        .print-stat-value { font-size: 16px; font-weight: bold; color: #333; }
                        .print-sales-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
                        .print-sales-table th, .print-sales-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        .print-sales-table th { background-color: #f5f5f5; font-weight: bold; }
                        .items-details { font-size: 10px; color: #666; margin-top: 5px; }
                    </style>
                </head>
                <body>
                    ${printContent}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
            printWindow.close();
        }

        // Utiliser la fonction custom print si nécessaire
        document.querySelector('.print-button').addEventListener('click', function(e) {
            e.preventDefault();
            window.print();
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