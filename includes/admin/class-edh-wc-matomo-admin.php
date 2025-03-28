<?php
declare(strict_types=1);

namespace EDH_WC_Matomo_Tracking\Admin;

/**
 * Admin class for handling plugin settings
 */
class EDH_WC_Matomo_Admin {
    /**
     * Plugin settings
     *
     * @var array
     */
    private array $settings;

    /**
     * Constructor
     *
     * @param array $settings Plugin settings.
     */
    public function __construct(array $settings) {
        $this->settings = $settings;
        $this->init();
    }

    /**
     * Initialize admin functionality
     */
    private function init(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    /**
     * Add admin menu item
     */
    public function add_admin_menu(): void {
        add_submenu_page(
            'woocommerce',
            __('Matomo Tracking Settings', 'edh-wc-matomo-tracking'),
            __('Matomo Tracking', 'edh-wc-matomo-tracking'),
            'manage_woocommerce',
            'edh-wc-matomo-tracking',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings(): void {
        register_setting('edh_wc_matomo_settings', 'edh_wc_matomo_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        add_settings_section(
            'edh_wc_matomo_main_section',
            __('Main Settings', 'edh-wc-matomo-tracking'),
            [$this, 'render_section_description'],
            'edh-wc-matomo-tracking'
        );

        add_settings_field(
            'matomo_url',
            __('Matomo URL', 'edh-wc-matomo-tracking'),
            [$this, 'render_matomo_url_field'],
            'edh-wc-matomo-tracking',
            'edh_wc_matomo_main_section'
        );

        add_settings_field(
            'site_id',
            __('Site ID', 'edh-wc-matomo-tracking'),
            [$this, 'render_site_id_field'],
            'edh-wc-matomo-tracking',
            'edh_wc_matomo_main_section'
        );

        add_settings_field(
            'auth_token',
            __('Auth Token', 'edh-wc-matomo-tracking'),
            [$this, 'render_auth_token_field'],
            'edh-wc-matomo-tracking',
            'edh_wc_matomo_main_section'
        );

        add_settings_field(
            'tracking_enabled',
            __('Enable Tracking', 'edh-wc-matomo-tracking'),
            [$this, 'render_tracking_enabled_field'],
            'edh-wc-matomo-tracking',
            'edh_wc_matomo_main_section'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'edh-wc-matomo-tracking'));
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('edh_wc_matomo_settings');
                do_settings_sections('edh-wc-matomo-tracking');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render section description
     */
    public function render_section_description(): void {
        echo '<p>' . esc_html__('Configure your Matomo tracking settings below.', 'edh-wc-matomo-tracking') . '</p>';
    }

    /**
     * Render Matomo URL field
     */
    public function render_matomo_url_field(): void {
        $value = $this->settings['matomo_url'] ?? '';
        ?>
        <input type="url" 
               name="edh_wc_matomo_settings[matomo_url]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               placeholder="https://analytics.example.com"
        />
        <p class="description">
            <?php esc_html_e('Enter the URL of your Matomo instance (e.g., https://analytics.example.com)', 'edh-wc-matomo-tracking'); ?>
        </p>
        <?php
    }

    /**
     * Render Site ID field
     */
    public function render_site_id_field(): void {
        $value = $this->settings['site_id'] ?? '';
        ?>
        <input type="number" 
               name="edh_wc_matomo_settings[site_id]" 
               value="<?php echo esc_attr($value); ?>" 
               class="small-text"
        />
        <p class="description">
            <?php esc_html_e('Enter your Matomo site ID', 'edh-wc-matomo-tracking'); ?>
        </p>
        <?php
    }

    /**
     * Render Auth Token field
     */
    public function render_auth_token_field(): void {
        $value = $this->settings['auth_token'] ?? '';
        ?>
        <input type="password" 
               name="edh_wc_matomo_settings[auth_token]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text"
               required
        />
        <p class="description">
            <?php esc_html_e('Enter your Matomo authentication token. This is required for secure server-side tracking.', 'edh-wc-matomo-tracking'); ?>
        </p>
        <?php
    }

    /**
     * Render Tracking Enabled field
     */
    public function render_tracking_enabled_field(): void {
        $value = $this->settings['tracking_enabled'] ?? true;
        ?>
        <label>
            <input type="checkbox" 
                   name="edh_wc_matomo_settings[tracking_enabled]" 
                   value="1" 
                   <?php checked($value, true); ?>
            />
            <?php esc_html_e('Enable WooCommerce order tracking in Matomo', 'edh-wc-matomo-tracking'); ?>
        </label>
        <?php
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input array.
     * @return array
     */
    public function sanitize_settings(array $input): array {
        $sanitized = [];

        if (isset($input['matomo_url'])) {
            $sanitized['matomo_url'] = esc_url_raw($input['matomo_url']);
        }

        if (isset($input['site_id'])) {
            $sanitized['site_id'] = absint($input['site_id']);
        }

        if (isset($input['auth_token'])) {
            $sanitized['auth_token'] = sanitize_text_field($input['auth_token']);
        }

        // Validate required fields
        if (empty($sanitized['auth_token'])) {
            add_settings_error(
                'edh_wc_matomo_settings',
                'auth_token_required',
                __('Authentication token is required for secure tracking.', 'edh-wc-matomo-tracking')
            );
            return get_option('edh_wc_matomo_settings');
        }

        $sanitized['tracking_enabled'] = !empty($input['tracking_enabled']);

        return $sanitized;
    }
} 