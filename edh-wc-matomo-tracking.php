<?php
/**
 * Plugin Name: EDH WooCommerce Matomo Tracking
 * Plugin URI: https://encode.host
 * Description: Sends WooCommerce order details to Matomo for enhanced analytics tracking.
 * Version: 1.1.0
 * Author: EncodeDotHost
 * Author URI: https://encode.host
 * Text Domain: edh-wc-matomo-tracking
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package EDH_WC_Matomo_Tracking
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EDH_WC_MATOMO_VERSION', '1.1.0');
define('EDH_WC_MATOMO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EDH_WC_MATOMO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    $prefix = 'EDH_WC_Matomo_Tracking\\';
    $base_dir = EDH_WC_MATOMO_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function edh_wc_matomo_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('EDH WooCommerce Matomo Tracking requires WooCommerce to be installed and active.', 'edh-wc-matomo-tracking'); ?></p>
            </div>
            <?php
        });
        return;
    }

    // Initialize the main plugin class
    require_once EDH_WC_MATOMO_PLUGIN_DIR . 'includes/class-edh-wc-matomo-tracking.php';
    $plugin = new \EDH_WC_Matomo_Tracking\EDH_WC_Matomo_Tracking();
    $plugin->init();
}
add_action('plugins_loaded', 'edh_wc_matomo_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create necessary database tables and options
    add_option('edh_wc_matomo_settings', [
        'matomo_url' => '',
        'site_id' => '',
        'auth_token' => '',
        'tracking_enabled' => true,
    ]);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if necessary
}); 