<?php
/**
 * App Settings Helper
 * This file should be included in all pages to load app settings
 * Include with: require_once '../config/app_settings.php';
 */

// Prevent direct access

class AppSettings {
    private static $settings = null;
    private static $db = null;
    
    /**
     * Initialize settings with database connection
     */
    public static function init($database_connection) {
        self::$db = $database_connection;
        self::loadSettings();
    }
    
    /**
     * Load all settings from database
     */
    private static function loadSettings() {
        if (self::$db === null) {
            return;
        }
        
        try {
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
            self::$db->query($createTableSQL);
            
            // Load settings
            $settingsSQL = "SELECT setting_key, setting_value FROM app_settings";
            $results = self::$db->fetchAll($settingsSQL);
            
            if ($results) {
                self::$settings = [];
                foreach ($results as $row) {
                    self::$settings[$row['setting_key']] = $row['setting_value'];
                }
            }
            
            // If no settings exist, insert defaults
            if (empty(self::$settings)) {
                self::insertDefaultSettings();
                self::loadSettings(); // Reload after inserting defaults
            }
            
        } catch (Exception $e) {
            // Log error and use default settings
            error_log("Failed to load app settings: " . $e->getMessage());
            self::setDefaultSettings();
        }
    }
    
    /**
     * Insert default settings into database
     */
    private static function insertDefaultSettings() {
        $defaultSettings = [
            ['app_name', 'PharmaSys', 'text', 'Nom de l\'application'],
            ['app_icon', 'pill', 'text', 'Icône de l\'application (Lucide icon name)'],
            ['pharmacy_name', 'Pharmacie Centrale', 'text', 'Nom de la pharmacie'],
            ['pharmacy_address', '123 Avenue de la Santé, Brazzaville', 'textarea', 'Adresse de la pharmacie'],
            ['pharmacy_phone', '+242 06 123 45 67', 'text', 'Téléphone de la pharmacie'],
            ['pharmacy_email', 'contact@pharmacie-centrale.cg', 'text', 'Email de la pharmacie'],
            ['pharmacy_license', 'PH-2024-001', 'text', 'Numéro de licence'],
            ['primary_color', '#3b82f6', 'color', 'Couleur principale'],
            ['secondary_color', '#10b981', 'color', 'Couleur secondaire'],
            ['currency', 'FCFA', 'text', 'Devise utilisée'],
            ['timezone', 'Africa/Brazzaville', 'text', 'Fuseau horaire'],
            ['language', 'fr', 'text', 'Langue par défaut'],
            ['low_stock_threshold', '10', 'number', 'Seuil de stock faible'],
            ['company_description', 'Votre pharmacie de confiance depuis 1995. Nous nous engageons à fournir des soins de santé de qualité à notre communauté.', 'textarea', 'Description de l\'entreprise'],
            ['working_hours', 'Lun-Ven: 8h-18h, Sam: 8h-14h', 'text', 'Heures d\'ouverture']
        ];
        
        $insertSQL = "INSERT INTO app_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)";
        
        foreach ($defaultSettings as $setting) {
            try {
                self::$db->query($insertSQL, $setting);
            } catch (Exception $e) {
                // Continue if setting already exists
                continue;
            }
        }
    }
    
    /**
     * Set default settings in memory (fallback)
     */
    private static function setDefaultSettings() {
        self::$settings = [
            'app_name' => 'PharmaSys',
            'app_icon' => 'pill',
            'pharmacy_name' => 'Pharmacie Centrale',
            'pharmacy_address' => '123 Avenue de la Santé, Brazzaville',
            'pharmacy_phone' => '+242 06 123 45 67',
            'pharmacy_email' => 'contact@pharmacie-centrale.cg',
            'pharmacy_license' => 'PH-2024-001',
            'primary_color' => '#3b82f6',
            'secondary_color' => '#10b981',
            'currency' => 'FCFA',
            'timezone' => 'Africa/Brazzaville',
            'language' => 'fr',
            'low_stock_threshold' => '10',
            'company_description' => 'Votre pharmacie de confiance depuis 1995. Nous nous engageons à fournir des soins de santé de qualité à notre communauté.',
            'working_hours' => 'Lun-Ven: 8h-18h, Sam: 8h-14h'
        ];
    }
    
    /**
     * Get a specific setting value
     */
    public static function get($key, $default = '') {
        if (self::$settings === null) {
            return $default;
        }
        
        return isset(self::$settings[$key]) ? self::$settings[$key] : $default;
    }
    
    /**
     * Get all settings
     */
    public static function getAll() {
        return self::$settings ?: [];
    }
    
    /**
     * Check if settings are loaded
     */
    public static function isLoaded() {
        return self::$settings !== null;
    }
    
    /**
     * Reload settings from database
     */
    public static function reload() {
        self::$settings = null;
        self::loadSettings();
    }
    
    /**
     * Get formatted currency
     */
    public static function formatCurrency($amount) {
        $currency = self::get('currency', 'FCFA');
        return number_format($amount, 0, ',', ' ') . ' ' . $currency;
    }
    
    /**
     * Get low stock threshold
     */
    public static function getLowStockThreshold() {
        return (int) self::get('low_stock_threshold', 10);
    }
    
    /**
     * Get primary color for CSS
     */
    public static function getPrimaryColor() {
        return self::get('primary_color', '#3b82f6');
    }
    
    /**
     * Get secondary color for CSS
     */
    public static function getSecondaryColor() {
        return self::get('secondary_color', '#10b981');
    }
    
    /**
     * Generate CSS variables
     */
    public static function getCSSVariables() {
        return "
        <style>
        :root {
            --primary-color: " . self::getPrimaryColor() . ";
            --secondary-color: " . self::getSecondaryColor() . ";
        }
        </style>";
    }
    
    /**
     * Get page title with app name
     */
    public static function getPageTitle($pageTitle = '') {
        $appName = self::get('app_name', 'PharmaSys');
        return !empty($pageTitle) ? $pageTitle . ' - ' . $appName : $appName;
    }
    
    /**
     * Get app icon HTML
     */
    public static function getAppIcon($classes = '') {
        $icon = self::get('app_icon', 'pill');
        return '<i data-lucide="' . htmlspecialchars($icon) . '" class="' . $classes . '"></i>';
    }
    
    /**
     * Get pharmacy info for headers/footers
     */
    public static function getPharmacyInfo() {
        return [
            'name' => self::get('pharmacy_name', 'Pharmacie Centrale'),
            'address' => self::get('pharmacy_address', ''),
            'phone' => self::get('pharmacy_phone', ''),
            'email' => self::get('pharmacy_email', ''),
            'license' => self::get('pharmacy_license', ''),
            'description' => self::get('company_description', ''),
            'working_hours' => self::get('working_hours', '')
        ];
    }
}

// Global helper functions for easy access
function appSetting($key, $default = '') {
    return AppSettings::get($key, $default);
}

function appName() {
    return AppSettings::get('app_name', 'PharmaSys');
}

function pharmacyName() {
    return AppSettings::get('pharmacy_name', 'Pharmacie Centrale');
}

function formatAppCurrency($amount) {
    return AppSettings::formatCurrency($amount);
}

function getAppIcon($classes = '') {
    return AppSettings::getAppIcon($classes);
}

function getPageTitle($pageTitle = '') {
    return AppSettings::getPageTitle($pageTitle);
}

// Auto-initialize if database connection exists
if (isset($db)) {
    AppSettings::init($db);
} elseif (isset($pdo)) {
    // Adapt for PDO connections if needed
    AppSettings::init($pdo);
}

// Define that settings are available
define('APP_SETTINGS_LOADED', true);
?>