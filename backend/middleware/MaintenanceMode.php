<?php
/**
 * Maintenance Mode Middleware
 * Handles shop maintenance mode functionality
 */

class MaintenanceMode
{
    /**
     * Check if maintenance mode is enabled
     */
    public static function isEnabled()
    {
        // Try database first
        try {
            $settings = self::getSettingsFromDatabase();
            if ($settings && isset($settings['maintenance_mode'])) {
                return $settings['maintenance_mode'] === 'true' || $settings['maintenance_mode'] === true;
            }
        } catch (Exception $e) {
            // Fall back to file-based settings
        }

        // Fallback to file-based settings
        $value = self::getSettingsFromFile()['maintenance_mode'] ?? false;
        return $value === 'true' || $value === true;
    }

    /**
     * Get maintenance message
     */
    public static function getMessage()
    {
        // Try database first
        try {
            $settings = self::getSettingsFromDatabase();
            if ($settings && isset($settings['maintenance_message'])) {
                return $settings['maintenance_message'];
            }
        } catch (Exception $e) {
            // Fall back to file-based settings
        }

        // Fallback to file-based settings
        return self::getSettingsFromFile()['maintenance_message'] ?? 'We are currently performing scheduled maintenance. Please check back later.';
    }

    /**
     * Check if current user is admin
     */
    public static function isAdmin()
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        return isset($_SESSION['user_role']) &&
               in_array($_SESSION['user_role'], ['admin', 'inventory_manager']);
    }

    /**
     * Handle maintenance mode for current request
     */
    public static function handle()
    {
        if (self::isEnabled() && !self::isAdmin()) {
            self::renderMaintenancePage();
            exit;
        }
    }

    /**
     * Render maintenance page
     */
    public static function renderMaintenancePage()
    {
        $message = self::getMessage();
        $basePath = self::getBasePath();

        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code(503); // Service Unavailable

        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Maintenance Mode - PC Parts Central</title>
            <link rel="icon" type="image/png" href="<?php echo $basePath; ?>/ppc.png">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                :root {
                    --primary: #0066ff;
                    --accent: #00f5ff;
                    --bg-primary: #0a0e27;
                    --bg-secondary: #141829;
                    --bg-card: rgba(30, 36, 57, 0.7);
                    --text-primary: #ffffff;
                    --text-secondary: #b8c1ec;
                    --text-muted: #6b7897;
                    --border-color: rgba(255, 255, 255, 0.08);
                    --shadow-glow: 0 0 30px rgba(0, 245, 255, 0.4);
                }

                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    overflow: hidden;
                    position: relative;
                }

                /* Animated Background Elements */
                body::before {
                    content: '';
                    position: absolute;
                    width: 600px;
                    height: 600px;
                    background: radial-gradient(circle, rgba(0, 102, 255, 0.1) 0%, transparent 70%);
                    border-radius: 50%;
                    top: -300px;
                    right: -300px;
                    animation: float 20s infinite alternate;
                }

                body::after {
                    content: '';
                    position: absolute;
                    width: 400px;
                    height: 400px;
                    background: radial-gradient(circle, rgba(0, 245, 255, 0.1) 0%, transparent 70%);
                    border-radius: 50%;
                    bottom: -200px;
                    left: -200px;
                    animation: float 15s infinite alternate-reverse;
                }

                @keyframes float {
                    0% { transform: translate(0, 0) rotate(0deg); }
                    100% { transform: translate(50px, 50px) rotate(10deg); }
                }

                .maintenance-container {
                    background: var(--bg-card);
                    backdrop-filter: blur(20px);
                    -webkit-backdrop-filter: blur(20px);
                    border: 1px solid var(--border-color);
                    border-radius: 24px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), var(--shadow-glow);
                    padding: 60px 40px;
                    max-width: 600px;
                    width: 100%;
                    text-align: center;
                    position: relative;
                    z-index: 10;
                    animation: slideIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
                }

                @keyframes slideIn {
                    from {
                        opacity: 0;
                        transform: translateY(50px) scale(0.9);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0) scale(1);
                    }
                }

                .maintenance-icon-container {
                    position: relative;
                    display: inline-block;
                    margin-bottom: 30px;
                }

                .maintenance-icon {
                    font-size: 100px;
                    background: linear-gradient(135deg, var(--primary), var(--accent));
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    animation: pulse 2s infinite, rotate 10s linear infinite;
                    filter: drop-shadow(0 0 20px rgba(0, 245, 255, 0.5));
                }

                @keyframes pulse {
                    0%, 100% { transform: scale(1); }
                    50% { transform: scale(1.1); }
                }

                @keyframes rotate {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                .maintenance-title {
                    font-size: clamp(28px, 5vw, 40px);
                    font-weight: 900;
                    background: linear-gradient(135deg, var(--accent) 0%, var(--primary) 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                    margin-bottom: 20px;
                    text-shadow: 0 0 40px rgba(0, 245, 255, 0.3);
                }

                .maintenance-message {
                    font-size: 16px;
                    color: var(--text-secondary);
                    line-height: 1.8;
                    margin-bottom: 40px;
                    max-width: 90%;
                    margin-left: auto;
                    margin-right: auto;
                }

                .maintenance-loader {
                    margin: 40px 0;
                    position: relative;
                }

                .spinner {
                    border: 4px solid rgba(255, 255, 255, 0.1);
                    border-top: 4px solid var(--accent);
                    border-radius: 50%;
                    width: 60px;
                    height: 60px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto;
                    box-shadow: 0 0 30px rgba(0, 245, 255, 0.4);
                }

                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }

                .maintenance-footer {
                    font-size: 14px;
                    color: var(--text-muted);
                    margin-top: 40px;
                    padding-top: 30px;
                    border-top: 1px solid var(--border-color);
                }

                .logo {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    margin-bottom: 20px;
                    color: var(--text-primary);
                    font-size: 18px;
                    font-weight: 600;
                }

                .logo i {
                    color: var(--accent);
                    font-size: 24px;
                }

                /* Responsive Design */
                @media (max-width: 768px) {
                    .maintenance-container {
                        padding: 40px 30px;
                    }

                    .maintenance-icon {
                        font-size: 80px;
                    }

                    .maintenance-message {
                        font-size: 15px;
                    }

                    body::before,
                    body::after {
                        display: none;
                    }
                }

                /* Smooth Animations */
                * {
                    transition: all 0.3s ease;
                }
            </style>
        </head>
        <body>
            <div class="maintenance-container">
                <div class="logo">
                    <i class="fas fa-microchip"></i>
                    <span>PC Parts Central</span>
                </div>

                <div class="maintenance-icon-container">
                    <i class="fas fa-wrench maintenance-icon"></i>
                </div>

                <h1 class="maintenance-title">System Maintenance</h1>

                <p class="maintenance-message"><?php echo htmlspecialchars($message); ?></p>

                <div class="maintenance-loader">
                    <div class="spinner"></div>
                </div>

                <div class="maintenance-footer">
                    <i class="fas fa-clock" style="color: var(--accent); margin-right: 8px;"></i>
                    We apologize for any inconvenience. Our team is working to bring the system back online shortly.
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Get settings from database
     */
    private static function getSettingsFromDatabase()
    {
        require_once __DIR__ . '/../config/database.php';

        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("
                SELECT setting_key, setting_value
                FROM settings
                WHERE setting_key IN ('maintenance_mode', 'maintenance_message')
            ");
            $stmt->execute();

            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            return $settings;
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Get settings from file
     */
    private static function getSettingsFromFile()
    {
        $settingsFile = __DIR__ . '/../data/settings.json';

        if (file_exists($settingsFile)) {
            $content = file_get_contents($settingsFile);
            $settings = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $settings;
            }
        }

        // Default settings
        return [
            'maintenance_mode' => false,
            'maintenance_message' => 'We are currently performing scheduled maintenance. Please check back later.'
        ];
    }

    /**
     * Enable maintenance mode
     */
    public static function enable($message = null)
    {
        $message = $message ?: 'We are currently performing scheduled maintenance. Please check back later.';

        try {
            // Try database first
            self::updateDatabaseSetting('maintenance_mode', 'true');
            self::updateDatabaseSetting('maintenance_message', $message);
        } catch (Exception $e) {
            // Fall back to file
            self::updateFileSetting('maintenance_mode', true);
            self::updateFileSetting('maintenance_message', $message);
        }
    }

    /**
     * Disable maintenance mode
     */
    public static function disable()
    {
        try {
            // Try database first
            self::updateDatabaseSetting('maintenance_mode', 'false');
        } catch (Exception $e) {
            // Fall back to file
            self::updateFileSetting('maintenance_mode', false);
        }
    }

    /**
     * Update database setting
     */
    private static function updateDatabaseSetting($key, $value)
    {
        require_once __DIR__ . '/../config/database.php';

        $db = Database::getInstance();
        $pdo = $db->getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, category, updated_at)
            VALUES (?, ?, 'shop', NOW())
            ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW()
        ");
        $stmt->execute([$key, $value]);
    }

    /**
     * Update file setting
     */
    private static function updateFileSetting($key, $value)
    {
        $settingsFile = __DIR__ . '/../data/settings.json';

        $settings = self::getSettingsFromFile();
        $settings[$key] = $value;

        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    }

    /**
     * Get base path based on environment
     */
    private static function getBasePath()
    {
        // Check if running on localhost
        $isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1', 'localhost:80', 'localhost:8080']);
        return $isLocal ? '/core1' : '';
    }
}
?>
