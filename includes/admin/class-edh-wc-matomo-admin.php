<?php
declare(strict_types=1);

namespace EDH_WC_Matomo_Tracking\Admin;

use EDH_WC_Matomo_Tracking\EDH_WC_Matomo_Logger;

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
     * Logger instance
     *
     * @var EDH_WC_Matomo_Logger
     */
    private EDH_WC_Matomo_Logger $logger;

    /**
     * Constructor
     *
     * @param array $settings Plugin settings.
     * @param EDH_WC_Matomo_Logger $logger Logger instance.
     */
    public function __construct(array $settings, EDH_WC_Matomo_Logger $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
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

        // Check if we're viewing logs
        if (isset($_GET['view']) && $_GET['view'] === 'logs') {
            $this->render_logs_page();
        } else {
            woocommerce_admin_fields($this->get_settings());
        }
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
                'desc' => sprintf(
                    /* translators: %s: URL to logs page */
                    __('Configure your Matomo tracking settings below. <a href="%s">View transaction logs</a>.', 'edh-wc-matomo-tracking'),
                    add_query_arg(['view' => 'logs'], admin_url('admin.php?page=wc-settings&tab=edh_wc_matomo'))
                ),
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
            'log_retention_days' => [
                'name' => __('Log Retention (Days)', 'edh-wc-matomo-tracking'),
                'type' => 'number',
                'desc' => __('Number of days to keep transaction logs', 'edh-wc-matomo-tracking'),
                'id' => 'edh_wc_matomo_log_retention_days',
                'default' => get_option('edh_wc_matomo_log_retention_days', 30),
                'custom_attributes' => ['min' => '1', 'max' => '365'],
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
        
        // Update log retention days
        if (isset($_POST['edh_wc_matomo_log_retention_days'])) {
            $days = absint($_POST['edh_wc_matomo_log_retention_days']);
            if ($days >= 1 && $days <= 365) {
                update_option('edh_wc_matomo_log_retention_days', $days);
            }
        }
        
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

    /**
     * Render logs page
     */
    private function render_logs_page(): void {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'edh-wc-matomo-tracking'));
        }

        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $logs = $this->logger->get_recent_logs($page);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Matomo Tracking Logs', 'edh-wc-matomo-tracking'); ?></h1>
            
            <p class="description">
                <?php esc_html_e('View transaction logs for Matomo tracking.', 'edh-wc-matomo-tracking'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=edh_wc_matomo')); ?>">
                    <?php esc_html_e('← Back to Settings', 'edh-wc-matomo-tracking'); ?>
                </a>
            </p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'edh-wc-matomo-tracking'); ?></th>
                        <th><?php esc_html_e('Order ID', 'edh-wc-matomo-tracking'); ?></th>
                        <th><?php esc_html_e('Event Type', 'edh-wc-matomo-tracking'); ?></th>
                        <th><?php esc_html_e('Status', 'edh-wc-matomo-tracking'); ?></th>
                        <th><?php esc_html_e('Error Message', 'edh-wc-matomo-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs['logs'])) : ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e('No logs found.', 'edh-wc-matomo-tracking'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($logs['logs'] as $log) : ?>
                            <tr>
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['created_at']))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($log['order_id'])); ?>">
                                        #<?php echo esc_html($log['order_id']); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($log['event_type']); ?></td>
                                <td>
                                    <span class="edh-wc-matomo-status edh-wc-matomo-status-<?php echo esc_attr($log['status']); ?>">
                                        <?php echo esc_html(ucfirst($log['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($log['error_message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($logs['pages'] > 1) : ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg(['paged' => '%#%', 'view' => 'logs'], admin_url('admin.php?page=wc-settings&tab=edh_wc_matomo')),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $logs['pages'],
                            'current' => $page,
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .edh-wc-matomo-status {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                line-height: 1;
            }
            .edh-wc-matomo-status-success {
                background-color: #dff0d8;
                color: #3c763d;
            }
            .edh-wc-matomo-status-error {
                background-color: #f2dede;
                color: #a94442;
            }
        </style>
        <?php
    }
} 