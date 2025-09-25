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
    $success_message = '';
    $error_message = '';

    // Create app_settings table if it doesn't exist
    $createTableSQL = "CREATE TABLE IF NOT EXISTS app_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('text', 'textarea', 'image', 'color', 'number') DEFAULT 'text',
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->query($createTableSQL);

    // Default settings data
    $defaultSettings = [
        ['setting_key' => 'app_name', 'setting_value' => 'PharmaSys', 'setting_type' => 'text', 'description' => 'Nom de l\'application'],
        ['setting_key' => 'app_icon', 'setting_value' => 'pill', 'setting_type' => 'text', 'description' => 'Icône de l\'application (Lucide icon name)'],
        ['setting_key' => 'pharmacy_name', 'setting_value' => 'Pharmacie Centrale', 'setting_type' => 'text', 'description' => 'Nom de la pharmacie'],
        ['setting_key' => 'pharmacy_address', 'setting_value' => '123 Avenue de la Santé, Brazzaville', 'setting_type' => 'textarea', 'description' => 'Adresse de la pharmacie'],
        ['setting_key' => 'pharmacy_phone', 'setting_value' => '+242 06 123 45 67', 'setting_type' => 'text', 'description' => 'Téléphone de la pharmacie'],
        ['setting_key' => 'pharmacy_email', 'setting_value' => 'contact@pharmacie-centrale.cg', 'setting_type' => 'text', 'description' => 'Email de la pharmacie'],
        ['setting_key' => 'pharmacy_license', 'setting_value' => 'PH-2024-001', 'setting_type' => 'text', 'description' => 'Numéro de licence'],
        ['setting_key' => 'primary_color', 'setting_value' => '#3b82f6', 'setting_type' => 'color', 'description' => 'Couleur principale'],
        ['setting_key' => 'secondary_color', 'setting_value' => '#10b981', 'setting_type' => 'color', 'description' => 'Couleur secondaire'],
        ['setting_key' => 'currency', 'setting_value' => 'FCFA', 'setting_type' => 'text', 'description' => 'Devise utilisée'],
        ['setting_key' => 'timezone', 'setting_value' => 'Africa/Brazzaville', 'setting_type' => 'text', 'description' => 'Fuseau horaire'],
        ['setting_key' => 'language', 'setting_value' => 'fr', 'setting_type' => 'text', 'description' => 'Langue par défaut'],
        ['setting_key' => 'low_stock_threshold', 'setting_value' => '10', 'setting_type' => 'number', 'description' => 'Seuil de stock faible'],
        ['setting_key' => 'company_description', 'setting_value' => 'Votre pharmacie de confiance depuis 1995. Nous nous engageons à fournir des soins de santé de qualité à notre communauté.', 'setting_type' => 'textarea', 'description' => 'Description de l\'entreprise'],
        ['setting_key' => 'working_hours', 'setting_value' => 'Lun-Ven: 8h-18h, Sam: 8h-14h', 'setting_type' => 'text', 'description' => 'Heures d\'ouverture']
    ];

    // Insert default settings if they don't exist
    $checkSettingSQL = "SELECT COUNT(*) as count FROM app_settings WHERE setting_key = ?";
    $insertSettingSQL = "INSERT INTO app_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)";
    
    foreach ($defaultSettings as $setting) {
        $result = $db->fetch($checkSettingSQL, [$setting['setting_key']]);
        if ($result['count'] == 0) {
            $db->query($insertSettingSQL, [
                $setting['setting_key'], 
                $setting['setting_value'], 
                $setting['setting_type'], 
                $setting['description']
            ]);
        }
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'update_settings':
                    try {
                        $db->beginTransaction();
                        
                        $updateSQL = "UPDATE app_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
                        
                        foreach ($_POST as $key => $value) {
                            if ($key !== 'action' && !empty($key)) {
                                $db->query($updateSQL, [trim($value), $key]);
                            }
                        }
                        
                        $db->commit();
                        $success_message = "Paramètres mis à jour avec succès.";
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error_message = "Erreur lors de la mise à jour : " . $e->getMessage();
                    }
                    break;

                case 'reset_to_defaults':
                    try {
                        $db->beginTransaction();
                        
                        foreach ($defaultSettings as $setting) {
                            $resetSQL = "UPDATE app_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?";
                            $db->query($resetSQL, [$setting['setting_value'], $setting['setting_key']]);
                        }
                        
                        $db->commit();
                        $success_message = "Paramètres réinitialisés aux valeurs par défaut.";
                        
                    } catch (Exception $e) {
                        $db->rollback();
                        $error_message = "Erreur lors de la réinitialisation : " . $e->getMessage();
                    }
                    break;
            }
        }
    }

    // Get current settings
    $settingsSQL = "SELECT * FROM app_settings ORDER BY 
                    CASE setting_key 
                        WHEN 'app_name' THEN 1
                        WHEN 'app_icon' THEN 2
                        WHEN 'pharmacy_name' THEN 3
                        WHEN 'pharmacy_address' THEN 4
                        WHEN 'pharmacy_phone' THEN 5
                        WHEN 'pharmacy_email' THEN 6
                        WHEN 'pharmacy_license' THEN 7
                        WHEN 'primary_color' THEN 8
                        WHEN 'secondary_color' THEN 9
                        ELSE 10 
                    END, setting_key";
    $settings = $db->fetchAll($settingsSQL);

    // Group settings by category
    $settingsGroups = [
        'Application' => ['app_name', 'app_icon', 'primary_color', 'secondary_color', 'language', 'timezone'],
        'Pharmacie' => ['pharmacy_name', 'pharmacy_address', 'pharmacy_phone', 'pharmacy_email', 'pharmacy_license', 'working_hours', 'company_description'],
        'Système' => ['currency', 'low_stock_threshold']
    ];

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $settings = [];
}

// Helper function to get setting value
function getSettingValue($settings, $key) {
    foreach ($settings as $setting) {
        if ($setting['setting_key'] === $key) {
            return $setting['setting_value'];
        }
    }
    return '';
}

// Helper function to get setting by key
function getSetting($settings, $key) {
    foreach ($settings as $setting) {
        if ($setting['setting_key'] === $key) {
            return $setting;
        }
    }
    return null;
}

// Popular Lucide icons for app icon selection
$iconOptions = [
    'pill' => 'Pilule',
    'cross' => 'Croix médicale',
    'heart-pulse' => 'Pouls cardiaque',
    'stethoscope' => 'Stéthoscope',
    'shield-plus' => 'Bouclier médical',
    'activity' => 'Activité',
    'plus-circle' => 'Plus cercle',
    'home' => 'Maison',
    'building' => 'Bâtiment',
    'briefcase-medical' => 'Mallette médicale'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSettingValue($settings, 'app_name'); ?> - Personnalisation</title>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="../assets/css/base.css">
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <style>
        .customization-container {
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

        .settings-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .preview-panel {
            position: sticky;
            top: 2rem;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 1.5rem;
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

        .settings-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .settings-group {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
        }

        .group-header {
            background: #f9fafb;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
        }

        .group-content {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: border-color 0.2s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group .help-text {
            font-size: 0.75rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }

        .color-input-group {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .color-preview {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            border: 2px solid #e5e7eb;
            cursor: pointer;
        }

        .icon-selector {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .icon-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0.75rem 0.5rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            background: white;
        }

        .icon-option:hover {
            border-color: #3b82f6;
            background: #f8fafc;
        }

        .icon-option.selected {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .icon-option i {
            margin-bottom: 0.25rem;
        }

        .icon-option span {
            font-size: 0.75rem;
            text-align: center;
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
            justify-content: center;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .btn-danger {
            background: #ef4444;
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
            justify-content: center;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .preview-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .preview-header {
            background: var(--primary-color, #3b82f6);
            color: white;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .preview-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .preview-content {
            padding: 1.5rem;
        }

        .preview-field {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }

        .preview-field:last-child {
            border-bottom: none;
        }

        .preview-label {
            font-weight: 600;
            color: #374151;
        }

        .preview-value {
            color: #6b7280;
            text-align: right;
            max-width: 200px;
            word-wrap: break-word;
        }

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

        @media (max-width: 768px) {
            .customization-container {
                padding: 1rem;
            }
            
            .settings-grid {
                grid-template-columns: 1fr;
            }
            
            .icon-selector {
                grid-template-columns: repeat(3, 1fr);
            }

            .action-buttons {
                flex-direction: column;
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
                <div class="customization-container">
                    <!-- Page Header -->
                    <div class="page-header">
                        <h1 class="page-title">
                            <i data-lucide="settings"></i>
                            Personnalisation de l'Application
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

                    <!-- Settings Grid -->
                    <div class="settings-grid">
                        <!-- Preview Panel -->
                        <div class="preview-panel">
                            <div class="section-card">
                                <div class="section-header">
                                    <h3 class="section-title">
                                        <i data-lucide="eye"></i>
                                        Aperçu
                                    </h3>
                                </div>
                                <div class="section-content">
                                    <div class="preview-card">
                                        <div class="preview-header" id="previewHeader">
                                            <div class="preview-icon" id="previewIcon">
                                                <i data-lucide="<?php echo getSettingValue($settings, 'app_icon') ?: 'pill'; ?>"></i>
                                            </div>
                                            <div>
                                                <h3 id="previewAppName"><?php echo htmlspecialchars(getSettingValue($settings, 'app_name')); ?></h3>
                                                <p id="previewPharmacyName"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_name')); ?></p>
                                            </div>
                                        </div>
                                        <div class="preview-content">
                                            <div class="preview-field">
                                                <span class="preview-label">Adresse:</span>
                                                <span class="preview-value" id="previewAddress"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_address')); ?></span>
                                            </div>
                                            <div class="preview-field">
                                                <span class="preview-label">Téléphone:</span>
                                                <span class="preview-value" id="previewPhone"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_phone')); ?></span>
                                            </div>
                                            <div class="preview-field">
                                                <span class="preview-label">Email:</span>
                                                <span class="preview-value" id="previewEmail"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_email')); ?></span>
                                            </div>
                                            <div class="preview-field">
                                                <span class="preview-label">Licence:</span>
                                                <span class="preview-value" id="previewLicense"><?php echo htmlspecialchars(getSettingValue($settings, 'pharmacy_license')); ?></span>
                                            </div>
                                            <div class="preview-field">
                                                <span class="preview-label">Devise:</span>
                                                <span class="preview-value" id="previewCurrency"><?php echo htmlspecialchars(getSettingValue($settings, 'currency')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Settings Form -->
                        <div class="settings-form-panel">
                            <form method="POST" class="settings-form" id="settingsForm">
                                <input type="hidden" name="action" value="update_settings">
                                
                                <?php foreach ($settingsGroups as $groupName => $groupKeys): ?>
                                    <div class="settings-group">
                                        <div class="group-header">
                                            <i data-lucide="<?php echo $groupName === 'Application' ? 'smartphone' : ($groupName === 'Pharmacie' ? 'building' : 'cog'); ?>"></i>
                                            <?php echo $groupName; ?>
                                        </div>
                                        <div class="group-content">
                                            <?php foreach ($groupKeys as $key): ?>
                                                <?php $setting = getSetting($settings, $key); ?>
                                                <?php if ($setting): ?>
                                                    <div class="form-group">
                                                        <label for="<?php echo $key; ?>">
                                                            <?php echo htmlspecialchars($setting['description']); ?>
                                                        </label>
                                                        
                                                        <?php if ($key === 'app_icon'): ?>
                                                            <input type="hidden" name="<?php echo $key; ?>" id="<?php echo $key; ?>" value="<?php echo htmlspecialchars($setting['setting_value']); ?>">
                                                            <div class="icon-selector">
                                                                <?php foreach ($iconOptions as $icon => $label): ?>
                                                                    <div class="icon-option <?php echo $setting['setting_value'] === $icon ? 'selected' : ''; ?>" 
                                                                         onclick="selectIcon('<?php echo $icon; ?>', this)">
                                                                        <i data-lucide="<?php echo $icon; ?>"></i>
                                                                        <span><?php echo $label; ?></span>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            
                                                        <?php elseif ($setting['setting_type'] === 'color'): ?>
                                                            <div class="color-input-group">
                                                                <input type="color" 
                                                                       name="<?php echo $key; ?>" 
                                                                       id="<?php echo $key; ?>"
                                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                       onchange="updateColorPreview('<?php echo $key; ?>', this.value)">
                                                                <input type="text" 
                                                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                       readonly style="flex: 1;">
                                                            </div>
                                                            
                                                        <?php elseif ($setting['setting_type'] === 'textarea'): ?>
                                                            <textarea name="<?php echo $key; ?>" 
                                                                      id="<?php echo $key; ?>"
                                                                      onchange="updatePreview('<?php echo $key; ?>', this.value)"><?php echo htmlspecialchars($setting['setting_value']); ?></textarea>
                                                                      
                                                        <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                            <input type="number" 
                                                                   name="<?php echo $key; ?>" 
                                                                   id="<?php echo $key; ?>"
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   onchange="updatePreview('<?php echo $key; ?>', this.value)">
                                                                   
                                                        <?php else: ?>
                                                            <input type="text" 
                                                                   name="<?php echo $key; ?>" 
                                                                   id="<?php echo $key; ?>"
                                                                   value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                                                   onchange="updatePreview('<?php echo $key; ?>', this.value)">
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($key === 'app_icon'): ?>
                                                            <div class="help-text">Choisissez l'icône qui apparaîtra dans l'en-tête de l'application</div>
                                                        <?php elseif ($key === 'low_stock_threshold'): ?>
                                                            <div class="help-text">Seuil en dessous duquel un produit est considéré en stock faible</div>
                                                        <?php elseif ($key === 'primary_color'): ?>
                                                            <div class="help-text">Couleur principale utilisée dans l'interface</div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div class="action-buttons">
                                    <button type="submit" class="btn-primary">
                                        <i data-lucide="save"></i>
                                        Enregistrer les Modifications
                                    </button>
                                    <button type="button" class="btn-secondary" onclick="previewChanges()">
                                        <i data-lucide="eye"></i>
                                        Aperçu des Changements
                                    </button>
                                    <button type="button" class="btn-danger" onclick="resetToDefaults()">
                                        <i data-lucide="refresh-cw"></i>
                                        Réinitialiser
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Reset Confirmation Modal -->
    <div id="resetModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 400px; text-align: center;">
            <i data-lucide="alert-triangle" style="color: #ef4444; width: 3rem; height: 3rem; margin-bottom: 1rem;"></i>
            <h3 style="margin-bottom: 1rem;">Confirmer la Réinitialisation</h3>
            <p style="margin-bottom: 2rem; color: #6b7280;">Êtes-vous sûr de vouloir réinitialiser tous les paramètres aux valeurs par défaut ? Cette action est irréversible.</p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="reset_to_defaults">
                    <button type="submit" class="btn-danger">
                        <i data-lucide="check"></i>
                        Confirmer
                    </button>
                </form>
                <button type="button" class="btn-secondary" onclick="closeResetModal()">
                    <i data-lucide="x"></i>
                    Annuler
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Lucide icons
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }

            // Initialize color previews
            updateColorPreview('primary_color', document.getElementById('primary_color')?.value);
            updateColorPreview('secondary_color', document.getElementById('secondary_color')?.value);

            // Form validation
            const settingsForm = document.getElementById('settingsForm');
            if (settingsForm) {
                settingsForm.addEventListener('submit', function(e) {
                    const appName = document.getElementById('app_name')?.value?.trim();
                    const pharmacyName = document.getElementById('pharmacy_name')?.value?.trim();

                    if (!appName || !pharmacyName) {
                        e.preventDefault();
                        alert('Le nom de l\'application et le nom de la pharmacie sont obligatoires.');
                        return false;
                    }
                });
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

        // Icon selection functionality
        function selectIcon(iconName, element) {
            // Remove selected class from all options
            document.querySelectorAll('.icon-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Add selected class to clicked option
            element.classList.add('selected');
            
            // Update hidden input
            document.getElementById('app_icon').value = iconName;
            
            // Update preview
            updatePreview('app_icon', iconName);
            
            // Reinitialize icons to show the change
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        }

        // Color preview update
        function updateColorPreview(settingKey, color) {
            if (settingKey === 'primary_color') {
                document.documentElement.style.setProperty('--primary-color', color);
                const previewHeader = document.getElementById('previewHeader');
                if (previewHeader) {
                    previewHeader.style.background = color;
                }
            }
            
            // Update the text input next to color picker
            const colorInput = document.getElementById(settingKey);
            if (colorInput && colorInput.nextElementSibling) {
                colorInput.nextElementSibling.value = color;
            }
        }

        // Live preview updates
        function updatePreview(settingKey, value) {
            switch (settingKey) {
                case 'app_name':
                    const appNameEl = document.getElementById('previewAppName');
                    if (appNameEl) appNameEl.textContent = value || 'PharmaSys';
                    break;
                    
                case 'pharmacy_name':
                    const pharmacyNameEl = document.getElementById('previewPharmacyName');
                    if (pharmacyNameEl) pharmacyNameEl.textContent = value || 'Pharmacie Centrale';
                    break;
                    
                case 'pharmacy_address':
                    const addressEl = document.getElementById('previewAddress');
                    if (addressEl) addressEl.textContent = value || 'Adresse non définie';
                    break;
                    
                case 'pharmacy_phone':
                    const phoneEl = document.getElementById('previewPhone');
                    if (phoneEl) phoneEl.textContent = value || 'Téléphone non défini';
                    break;
                    
                case 'pharmacy_email':
                    const emailEl = document.getElementById('previewEmail');
                    if (emailEl) emailEl.textContent = value || 'Email non défini';
                    break;
                    
                case 'pharmacy_license':
                    const licenseEl = document.getElementById('previewLicense');
                    if (licenseEl) licenseEl.textContent = value || 'Licence non définie';
                    break;
                    
                case 'currency':
                    const currencyEl = document.getElementById('previewCurrency');
                    if (currencyEl) currencyEl.textContent = value || 'FCFA';
                    break;
                    
                case 'app_icon':
                    const iconEl = document.getElementById('previewIcon');
                    if (iconEl) {
                        iconEl.innerHTML = '<i data-lucide="' + value + '"></i>';
                        // Reinitialize icons
                        if (typeof lucide !== 'undefined') {
                            lucide.createIcons();
                        }
                    }
                    break;
            }
        }

        // Preview all changes
        function previewChanges() {
            const form = document.getElementById('settingsForm');
            const formData = new FormData(form);
            
            let previewText = 'Aperçu des modifications:\n\n';
            for (let [key, value] of formData.entries()) {
                if (key !== 'action') {
                    const label = document.querySelector(`label[for="${key}"]`)?.textContent || key;
                    previewText += `${label}: ${value}\n`;
                }
            }
            
            alert(previewText);
        }

        // Reset to defaults confirmation
        function resetToDefaults() {
            const modal = document.getElementById('resetModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeResetModal() {
            const modal = document.getElementById('resetModal');
            if (modal) {
                modal.style.display = 'none';
            }
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

        // Auto-save functionality (optional)
        let autoSaveTimeout;
        function scheduleAutoSave() {
            if (autoSaveTimeout) clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                // Could implement auto-save here if desired
                console.log('Auto-save triggered');
            }, 5000);
        }

        // Add event listeners for auto-save on all inputs
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('#settingsForm input, #settingsForm textarea, #settingsForm select');
            inputs.forEach(input => {
                input.addEventListener('input', scheduleAutoSave);
                input.addEventListener('change', scheduleAutoSave);
            });
        });
    </script>
</body>
</html>

<?php
} else {
    // Redirect to login if not admin
    header("Location: ../logout.php");
    exit();
}
?>