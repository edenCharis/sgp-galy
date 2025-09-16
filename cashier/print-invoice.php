<?php
session_start();

// Allow both CASHIER and SELLER to print invoices
if (!isset($_SESSION["role"]) || !in_array($_SESSION["role"], ["CASHIER", "SELLER"])) {
    die('Non autorisé');
}

try {
    include '../config/database.php';
    
    if (!isset($db)) {
        throw new Exception('Database connection not found');
    }

    $saleId = isset($_GET['saleId']) ? (string)$_GET['saleId'] : null;
    
    if (!$saleId) {
        throw new Exception('ID de vente manquant');
    }

    // Get sale details with related information
    $saleQuery = "SELECT s.*, 
                         c.name as clientName, c.contact as clientPhone,
                         cashier.username as cashierName,
                         seller.username as sellerName
                  FROM sale s Join cash_register cr ON s.cash_register_id = cr.id
                  LEFT JOIN client c ON s.clientId = c.id
                  LEFT JOIN user cashier ON cr.cashier_id = cashier.id
                  LEFT JOIN user seller ON s.sellerId = seller.id
               
                  WHERE s.id = ?";
    
    $sale = $db->fetch($saleQuery, [$saleId]);
    
    if (!$sale) {
        throw new Exception('Vente non trouvée');
    }

    // Get sale items with product details
    $itemsQuery = "SELECT si.*, p.name as productName, p.code, c.name as categoryName
                   FROM saleitem si
                   JOIN product p ON si.productId = p.id
                   JOIN category c ON p.categoryId = c.id
                   WHERE si.saleId = ?
                   ORDER BY p.name";
    
    $saleItems = $db->fetchAll($itemsQuery, [$saleId]);
    
    if (!$saleItems) {
        throw new Exception('Articles de vente non trouvés');
    }

    // Pharmacy information (you should store this in a settings table)
    $pharmacyInfo = [
        'name' => 'Pharmacie PharmaSys',
        'address' => '123 Avenue de la Santé',
        'city' => 'Brazzaville, République du Congo',
        'phone' => '+242 05 123 4567',
        'email' => 'contact@pharmasys.cg',
        'license' => 'Licence N° PH-2024-001',
        'siret' => '12345678901234'
    ];

} catch (Exception $e) {
    die('Erreur: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket N° <?php echo htmlspecialchars($sale['invoiceNumber']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.3;
            color: #000;
            background: #f5f5f5;
            padding: 20px;
        }

        .receipt-container {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 15px;
            border: 1px solid #000;
        }

        .pharmacy-header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #000;
        }

        .pharmacy-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 3px;
        }

        .pharmacy-info {
            font-size: 11px;
            margin-bottom: 2px;
        }

        .sale-info {
            margin: 10px 0;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
        }

        .items-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            border-bottom: 1px solid #000;
            padding: 3px 0;
            margin-bottom: 8px;
            font-size: 11px;
        }

        .item {
            margin-bottom: 8px;
            font-size: 11px;
        }

        .item-name {
            margin-bottom: 2px;
            font-weight: normal;
        }

        .item-calc {
            display: flex;
            justify-content: space-between;
            margin-left: 20px;
            font-family: 'Courier New', monospace;
        }

        .totals {
            border-top: 1px solid #000;
            padding-top: 8px;
            margin-top: 15px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .payment {
            margin: 10px 0;
            font-size: 12px;
        }

        .payment-line {
            margin-bottom: 2px;
        }

        .footer {
            text-align: center;
            margin-top: 15px;
            border-top: 1px solid #000;
            padding-top: 10px;
            font-size: 10px;
        }

        .print-controls {
            text-align: center;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border: 1px solid #ccc;
        }

        .btn {
            background: #333;
            color: white;
            border: none;
            padding: 6px 15px;
            margin: 0 5px;
            cursor: pointer;
            font-family: 'Courier New', monospace;
            font-size: 11px;
        }

        .btn:hover {
            background: #555;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .print-controls {
                display: none;
            }
            .receipt-container {
                border: none;
                max-width: none;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="print-controls">
        <button class="btn" onclick="window.print()">IMPRIMER</button>
        <button class="btn" onclick="window.location='index.php'">RETOUR</button>
    </div>

    <div class="receipt-container">
        <div class="pharmacy-header">
            <div class="pharmacy-name"><?php echo strtoupper(htmlspecialchars($pharmacyInfo['name'])); ?></div>
            <div class="pharmacy-info"><?php echo htmlspecialchars($pharmacyInfo['address']); ?></div>
            <div class="pharmacy-info"><?php echo htmlspecialchars($pharmacyInfo['city']); ?></div>
            <div class="pharmacy-info">Tel: <?php echo htmlspecialchars($pharmacyInfo['phone']); ?></div>
            <div class="pharmacy-info"><?php echo htmlspecialchars($pharmacyInfo['license']); ?></div>
        </div>

        <div class="sale-info">
            <span>Ticket N°: <?php echo htmlspecialchars($sale['invoiceNumber']); ?></span>
            <span>OP <?php echo strtoupper(htmlspecialchars($sale['cashierName'])); ?></span>
        </div>
        
        <div style="margin-bottom: 10px; font-size: 12px;">
            Vendeur : <?php echo strtoupper(htmlspecialchars($sale['sellerName'] ?: $sale['cashierName'])); ?>
        </div>

        <?php if ($sale['clientName']): ?>
        <div style="margin-bottom: 10px; font-size: 12px;">
            Client : <?php echo htmlspecialchars($sale['clientName']); ?>
            <?php if ($sale['clientPhone']): ?>
            <br>Tel: <?php echo htmlspecialchars($sale['clientPhone']); ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="items-header">
            <span>Désignation</span>
            <span>PU   Qte  Montant</span>
        </div>

        <?php foreach ($saleItems as $item): ?>
            <?php 
            $itemTotal = $item['quantity'] * $item['unitPrice'];
            $itemTotalWithDiscount = $itemTotal - $item['discount'];
            ?>
            <div class="item">
                <div class="item-name"><?php echo strtoupper(htmlspecialchars($item['productName'])); ?></div>
                <div class="item-calc">
                    <span><?php echo number_format($item['unitPrice'], 0, ',', ' '); ?> x <?php echo $item['quantity']; ?></span>
                    <span>= <?php echo number_format($itemTotalWithDiscount, 0, ',', ' '); ?></span>
                </div>
                <?php if ($item['discount'] > 0): ?>
                <div class="item-calc">
                    <span>Remise</span>
                    <span>- <?php echo number_format($item['discount'], 0, ',', ' '); ?></span>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="totals">
            <?php if ($sale['totalVAT'] > 0): ?>
            <div class="total-line">
                <span>Sous-total HT</span>
                <span><?php echo number_format($sale['totalAmount'] - $sale['totalVAT'], 0, ',', ' '); ?>F</span>
            </div>
            <div class="total-line">
                <span>TVA</span>
                <span><?php echo number_format($sale['totalVAT'], 0, ',', ' '); ?>F</span>
            </div>
            <?php endif; ?>
            <div class="total-line">
                <span>TTC</span>
                <span><?php echo number_format($sale['totalAmount'], 0, ',', ' '); ?>F</span>
            </div>
        </div>

        <div class="payment">
            <div class="payment-line">
                <?php 
                $paymentMethods = [
                    'cash' => 'ESPECES',
                    'card' => 'TPE',
                    'check' => 'CHEQUE',
                    'mixed' => 'MIXTE'
                ];
                echo $paymentMethods[$sale['paymentMethod']] ?? 'TPE';
                ?>
            </div>
            <div class="payment-line">Versé: <?php echo number_format($sale['cashReceived'] ?: $sale['totalAmount'], 0, ',', ' '); ?>F</div>
            <div class="payment-line">Rendu: <?php 
            $change = ($sale['cashReceived'] ?: $sale['totalAmount']) - $sale['totalAmount'];
            echo number_format(max(0, $change), 0, ',', ' '); 
            ?>F</div>
        </div>

        <?php if (isset($sale['insuranceId']) && $sale['insuranceId']): ?>
        <div style="margin: 10px 0; font-size: 11px; border-top: 1px solid #000; padding-top: 8px;">
            <div style="font-weight: bold;">TIERS PAYANT</div>
            <div>Assurance: <?php echo htmlspecialchars($sale['insuranceName']); ?></div>
            <div>Prise en charge: <?php echo $sale['coveragePercentage']; ?>%</div>
            <div>Montant couvert: <?php 
            $coveredAmount = ($sale['totalAmount'] * $sale['coveragePercentage']) / 100;
            echo number_format($coveredAmount, 0, ',', ' '); 
            ?>F</div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <div style="margin-bottom: 5px; font-weight: bold;">Merci de votre visite !</div>
            <div><?php echo date('d/m/Y', strtotime($sale['saleDate'])); ?>    <?php echo date('H:i', strtotime($sale['createdAt'])); ?>    © PharmaSys</div>
            <?php if ($pharmacyInfo['siret']): ?>
            <div style="margin-top: 3px;">SIRET: <?php echo htmlspecialchars($pharmacyInfo['siret']); ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>