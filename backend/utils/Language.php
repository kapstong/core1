<?php
/**
 * Multi-Language Support Utility Class
 * Provides internationalization (i18n) support for the application
 */

class Language {
    private static $currentLanguage = 'en';
    private static $translations = [];
    private static $fallbackLanguage = 'en';
    private static $supportedLanguages = ['en', 'es', 'fr', 'de', 'zh', 'ja', 'ar'];

    /**
     * Initialize language system
     */
    public static function init($defaultLanguage = 'en') {
        self::$currentLanguage = $defaultLanguage;
        self::loadTranslations(self::$currentLanguage);

        // Set fallback language
        self::$fallbackLanguage = 'en';
    }

    /**
     * Set current language
     */
    public static function setLanguage($language) {
        if (in_array($language, self::$supportedLanguages)) {
            self::$currentLanguage = $language;
            self::loadTranslations($language);
            return true;
        }
        return false;
    }

    /**
     * Get current language
     */
    public static function getLanguage() {
        return self::$currentLanguage;
    }

    /**
     * Get supported languages
     */
    public static function getSupportedLanguages() {
        return self::$supportedLanguages;
    }

    /**
     * Get language info
     */
    public static function getLanguageInfo($language = null) {
        $language = $language ?? self::$currentLanguage;

        $languages = [
            'en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇺🇸', 'rtl' => false],
            'es' => ['name' => 'Spanish', 'native' => 'Español', 'flag' => '🇪🇸', 'rtl' => false],
            'fr' => ['name' => 'French', 'native' => 'Français', 'flag' => '🇫🇷', 'rtl' => false],
            'de' => ['name' => 'German', 'native' => 'Deutsch', 'flag' => '🇩🇪', 'rtl' => false],
            'zh' => ['name' => 'Chinese', 'native' => '中文', 'flag' => '🇨🇳', 'rtl' => false],
            'ja' => ['name' => 'Japanese', 'native' => '日本語', 'flag' => '🇯🇵', 'rtl' => false],
            'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'flag' => '🇸🇦', 'rtl' => true]
        ];

        return $languages[$language] ?? $languages['en'];
    }

    /**
     * Load translations for a language
     */
    private static function loadTranslations($language) {
        $translationFile = __DIR__ . '/../../languages/' . $language . '.php';

        if (file_exists($translationFile)) {
            self::$translations = require $translationFile;
        } else {
            // Load fallback language
            $fallbackFile = __DIR__ . '/../../languages/' . self::$fallbackLanguage . '.php';
            if (file_exists($fallbackFile)) {
                self::$translations = require $fallbackFile;
            } else {
                self::$translations = self::getDefaultTranslations();
            }
        }
    }

    /**
     * Translate a key
     */
    public static function translate($key, $placeholders = [], $language = null) {
        $lang = $language ?? self::$currentLanguage;

        // Load translations if not already loaded for this language
        if ($lang !== self::$currentLanguage && !isset(self::$translations[$lang])) {
            self::loadTranslations($lang);
        }

        $translation = self::$translations[$key] ?? $key;

        // Replace placeholders
        if (!empty($placeholders)) {
            foreach ($placeholders as $placeholder => $value) {
                $translation = str_replace('{' . $placeholder . '}', $value, $translation);
            }
        }

        return $translation;
    }

    /**
     * Alias for translate method
     */
    public static function t($key, $placeholders = [], $language = null) {
        return self::translate($key, $placeholders, $language);
    }

    /**
     * Translate with pluralization support
     */
    public static function translatePlural($key, $count, $placeholders = []) {
        $pluralKey = $count === 1 ? $key . '_singular' : $key . '_plural';

        // Try plural form first, then fallback to singular
        $translation = self::$translations[$pluralKey] ?? self::$translations[$key] ?? $key;

        // Add count to placeholders
        $placeholders['count'] = $count;

        return self::translate($translation, $placeholders);
    }

    /**
     * Get date format for current language
     */
    public static function getDateFormat($type = 'short') {
        $formats = [
            'en' => ['short' => 'M/d/Y', 'medium' => 'M d, Y', 'long' => 'F j, Y', 'full' => 'l, F j, Y'],
            'es' => ['short' => 'd/m/Y', 'medium' => 'd M Y', 'long' => 'j F Y', 'full' => 'l j F Y'],
            'fr' => ['short' => 'd/m/Y', 'medium' => 'd M Y', 'long' => 'j F Y', 'full' => 'l j F Y'],
            'de' => ['short' => 'd.m.Y', 'medium' => 'd.m.Y', 'long' => 'j. F Y', 'full' => 'l, j. F Y'],
            'zh' => ['short' => 'Y-m-d', 'medium' => 'Y年m月d日', 'long' => 'Y年m月d日', 'full' => 'Y年m月d日 l'],
            'ja' => ['short' => 'Y/m/d', 'medium' => 'Y年m月d日', 'long' => 'Y年m月d日', 'full' => 'Y年m月d日 l'],
            'ar' => ['short' => 'd/m/Y', 'medium' => 'd M Y', 'long' => 'j F Y', 'full' => 'l j F Y']
        ];

        return $formats[self::$currentLanguage][$type] ?? $formats['en'][$type];
    }

    /**
     * Get currency format for current language
     */
    public static function getCurrencyFormat($currency = 'PHP') {
        $formats = [
            'en' => ['symbol' => '₱', 'position' => 'before', 'separator' => ',', 'decimal' => '.'],
            'es' => ['symbol' => '₱', 'position' => 'before', 'separator' => '.', 'decimal' => ','],
            'fr' => ['symbol' => '₱', 'position' => 'before', 'separator' => ' ', 'decimal' => ','],
            'de' => ['symbol' => '₱', 'position' => 'before', 'separator' => '.', 'decimal' => ','],
            'zh' => ['symbol' => '₱', 'position' => 'before', 'separator' => ',', 'decimal' => '.'],
            'ja' => ['symbol' => '₱', 'position' => 'before', 'separator' => ',', 'decimal' => '.'],
            'ar' => ['symbol' => '₱', 'position' => 'after', 'separator' => ',', 'decimal' => '.']
        ];

        return $formats[self::$currentLanguage] ?? $formats['en'];
    }

    /**
     * Format currency
     */
    public static function formatCurrency($amount, $currency = 'PHP') {
        $format = self::getCurrencyFormat($currency);

        $formatted = number_format($amount, 2, $format['decimal'], $format['separator']);

        if ($format['position'] === 'before') {
            return $format['symbol'] . $formatted;
        } else {
            return $formatted . ' ' . $format['symbol'];
        }
    }

    /**
     * Format date
     */
    public static function formatDate($date, $type = 'short') {
        if (is_string($date)) {
            $date = strtotime($date);
        }

        $format = self::getDateFormat($type);
        return date($format, $date);
    }

    /**
     * Detect user's preferred language from browser
     */
    public static function detectBrowserLanguage() {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

            foreach ($languages as $language) {
                $lang = trim(explode(';', $language)[0]);
                $prefix = explode('-', $lang)[0];

                if (in_array($lang, self::$supportedLanguages)) {
                    return $lang;
                }

                if (in_array($prefix, self::$supportedLanguages)) {
                    return $prefix;
                }
            }
        }

        return self::$fallbackLanguage;
    }

    /**
     * Check if current language is RTL
     */
    public static function isRTL() {
        $info = self::getLanguageInfo();
        return $info['rtl'] ?? false;
    }

    /**
     * Get text direction
     */
    public static function getTextDirection() {
        return self::isRTL() ? 'rtl' : 'ltr';
    }

    /**
     * Get default translations
     */
    private static function getDefaultTranslations() {
        return [
            // Common UI elements
            'home' => 'Home',
            'dashboard' => 'Dashboard',
            'products' => 'Products',
            'categories' => 'Categories',
            'orders' => 'Orders',
            'customers' => 'Customers',
            'reports' => 'Reports',
            'settings' => 'Settings',
            'logout' => 'Logout',
            'login' => 'Login',
            'register' => 'Register',

            // Actions
            'add' => 'Add',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'save' => 'Save',
            'cancel' => 'Cancel',
            'submit' => 'Submit',
            'search' => 'Search',
            'filter' => 'Filter',
            'export' => 'Export',
            'import' => 'Import',

            // Status messages
            'success' => 'Success',
            'error' => 'Error',
            'warning' => 'Warning',
            'info' => 'Information',
            'loading' => 'Loading...',

            // Form labels
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'confirm_password' => 'Confirm Password',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'phone' => 'Phone',
            'address' => 'Address',
            'city' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'postal_code' => 'Postal Code',

            // Product related
            'product_name' => 'Product Name',
            'product_sku' => 'SKU',
            'product_price' => 'Price',
            'product_description' => 'Description',
            'product_category' => 'Category',
            'product_stock' => 'Stock',
            'product_image' => 'Image',

            // Order related
            'order_number' => 'Order Number',
            'order_date' => 'Order Date',
            'order_status' => 'Order Status',
            'order_total' => 'Order Total',
            'shipping_address' => 'Shipping Address',
            'billing_address' => 'Billing Address',

            // Dashboard
            'total_sales' => 'Total Sales',
            'total_revenue' => 'Total Revenue',
            'total_customers' => 'Total Customers',
            'total_products' => 'Total Products',
            'low_stock_alerts' => 'Low Stock Alerts',
            'recent_orders' => 'Recent Orders',
            'top_products' => 'Top Products',

            // Validation messages
            'required_field' => 'This field is required',
            'invalid_email' => 'Invalid email address',
            'password_mismatch' => 'Passwords do not match',
            'minimum_length' => 'Minimum length is {length} characters',

            // Error messages
            'page_not_found' => 'Page not found',
            'access_denied' => 'Access denied',
            'server_error' => 'Server error occurred',

            // Success messages
            'saved_successfully' => 'Saved successfully',
            'deleted_successfully' => 'Deleted successfully',
            'order_placed' => 'Order placed successfully'
        ];
    }

    /**
     * Add custom translation
     */
    public static function addTranslation($key, $translation, $language = null) {
        $lang = $language ?? self::$currentLanguage;

        if (!isset(self::$translations[$lang])) {
            self::$translations[$lang] = [];
        }

        self::$translations[$lang][$key] = $translation;
    }

    /**
     * Get all translations for current language
     */
    public static function getAllTranslations($language = null) {
        $lang = $language ?? self::$currentLanguage;
        return self::$translations[$lang] ?? [];
    }

    /**
     * Export translations to file
     */
    public static function exportTranslations($language = null, $filePath = null) {
        $lang = $language ?? self::$currentLanguage;
        $translations = self::getAllTranslations($lang);

        $filePath = $filePath ?? __DIR__ . '/../../languages/' . $lang . '_export.php';

        $content = "<?php\nreturn " . var_export($translations, true) . ";\n";

        return file_put_contents($filePath, $content);
    }
}

// Initialize language system
Language::init('en');
