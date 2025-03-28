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
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_edh_wc_matomo', [$this, 'render_settings_tab']);
        add_action('woocommerce_update_options_edh_wc_matomo', [$this, 'update_settings']);
    }

    /**
     * Add settings tab to WooCommerce settings
     *
     * @param array $settings_tabs Array of WooCommerce settings tabs.
     * @return array
     */
    public function add_settings_tab(array $settings_tabs): array {
        $settings_tabs['edh_wc_matomo'] = __('Matomo Tracking', 'edh-wc-matomo-tracking');
        return $settings_tabs;
    }

    /**
     * Render settings tab content
     */
    public function render_settings_tab(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'edh-wc-matomo-tracking'));
        }

        woocommerce_admin_fields($this->get_settings());
    }

    /**
     * Get settings fields
     *
     * @return array
     */
    private function get_settings(): array {
        return [
            'section_title' => [
                'name' => __('Matomo Tracking Settings', 'edh-wc-matomo-tracking'),
                'type' => 'title',
                'desc' => __('Configure your Matomo tracking settings below.', 'edh-wc-matomo-tracking'),
                'id' => 'edh_wc_matomo_section_title',
            ],
            'matomo_url' => [
                'name' => __('Matomo URL', 'edh-wc-matomo-tracking'),
                'type' => 'text',
                'desc' => __('Enter the URL of your Matomo instance (e.g., https://analytics.example.com)', 'edh-wc-matomo-tracking'),
                'id' => 'edh_wc_matomo_settings[matomo_url]',
                'default' => $this->settings['matomo_url'] ?? '',
                'placeholder' => 'https://analytics.example.com',
            ],
            'site_id' => [
                'name' => __('Site ID', 'edh-wc-matomo-tracking'),
                'type' => 'number',
                'desc' => __('Enter your Matomo site ID', 'edh-wc-matomo-tracking'),
                'id' => 'edh_wc_matomo_settings[site_id]',
                'default' => $this->settings['site_id'] ?? '',
            ],
            'auth_token' => [
                'name' => __('Auth Token', 'edh-wc-matomo-tracking'),
                'type' => 'password',
                'desc' => __('Enter your Matomo authentication token. This is required for secure server-side tracking.', 'edh-wc-matomo-tracking'),
                'id' => 'edh_wc_matomo_settings[auth_token]',
                'default' => $this->settings['auth_token'] ?? '',
                'custom_attributes' => ['required' => 'required'],
            ],
            'tracking_enabled' => [
                'name' => __('Enable Tracking', 'edh-wc-matomo-tracking'),
                'type' => 'checkbox',
                'desc' => __('Enable WooCommerce order tracking in Matomo', 'edh-wc-matomo-tracking'),
                'id' => 'edh_wc_matomo_settings[tracking_enabled]',
                'default' => $this->settings['tracking_enabled'] ?? true,
            ],
            'section_end' => [
                'type' => 'sectionend',
                'id' => 'edh_wc_matomo_section_end',
            ],
        ];
    }

    /**
     * Update settings
     */
    public function update_settings(): void {
        if (!isset($_POST['edh_wc_matomo_settings'])) {
            return;
        }

        $input = $_POST['edh_wc_matomo_settings'];
        $sanitized = $this->sanitize_settings($input);

        if (empty($sanitized['auth_token'])) {
            WC_Admin_Settings::add_error(__('Authentication token is required for secure tracking.', 'edh-wc-matomo-tracking'));
            return;
        }

        update_option('edh_wc_matomo_settings', $sanitized);
        WC_Admin_Settings::add_message(__('Settings saved successfully.', 'edh-wc-matomo-tracking'));
    }

    /**
     * Sanitize settings
     *
     * @param array $input Input array.
     * @return array
     */
    private function sanitize_settings(array $input): array {
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

        $sanitized['tracking_enabled'] = !empty($input['tracking_enabled']);

        return $sanitized;
    }
} 