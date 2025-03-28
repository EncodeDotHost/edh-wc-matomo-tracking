<?php
declare(strict_types=1);

namespace EDH_WC_Matomo_Tracking;

use Automattic\WooCommerce\Utilities\OrderUtil;

/**
 * Main plugin class
 */
class EDH_WC_Matomo_Tracking {
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
     * Initialize the plugin
     */
    public function init(): void {
        $this->settings = get_option('edh_wc_matomo_settings', []);
        
        // Initialize logger
        $this->logger = new EDH_WC_Matomo_Logger();
        
        // Load admin functionality
        if (is_admin()) {
            require_once EDH_WC_MATOMO_PLUGIN_DIR . 'includes/admin/class-edh-wc-matomo-admin.php';
            new Admin\EDH_WC_Matomo_Admin($this->settings, $this->logger);
        }

        // Hook into WooCommerce order status changes
        add_action('woocommerce_order_status_changed', [$this, 'track_order_status_change'], 10, 3);
        
        // Hook into WooCommerce order creation
        add_action('woocommerce_new_order', [$this, 'track_new_order']);

        // Schedule log cleanup
        if (!wp_next_scheduled('edh_wc_matomo_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'edh_wc_matomo_cleanup_logs');
        }
        add_action('edh_wc_matomo_cleanup_logs', [$this, 'cleanup_logs']);
    }

    /**
     * Track order status changes in Matomo
     *
     * @param int    $order_id Order ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     * @return void
     */
    public function track_order_status_change(int $order_id, string $old_status, string $new_status): void {
        if (!$this->is_tracking_enabled()) {
            return;
        }

        $order = $this->get_order($order_id);
        if (!$order) {
            return;
        }

        $event_data = [
            'e_c' => 'WooCommerce',
            'e_a' => 'Order Status Change',
            'e_n' => 'Order #' . $order_id,
            'e_v' => $new_status,
            'order_id' => $order_id,
            'order_total' => $order->get_total(),
            'order_currency' => $order->get_currency(),
            'customer_id' => $order->get_customer_id(),
        ];

        $success = $this->send_to_matomo($event_data);
        $this->logger->log_transaction(
            $order_id,
            'status_change',
            $event_data,
            $success,
            $success ? null : 'Failed to send status change to Matomo'
        );
    }

    /**
     * Track new orders in Matomo
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function track_new_order(int $order_id): void {
        if (!$this->is_tracking_enabled()) {
            return;
        }

        $order = $this->get_order($order_id);
        if (!$order) {
            return;
        }

        $event_data = [
            'e_c' => 'WooCommerce',
            'e_a' => 'New Order',
            'e_n' => 'Order #' . $order_id,
            'e_v' => $order->get_total(),
            'order_id' => $order_id,
            'order_total' => $order->get_total(),
            'order_currency' => $order->get_currency(),
            'customer_id' => $order->get_customer_id(),
            'items' => $this->get_order_items_data($order),
        ];

        $success = $this->send_to_matomo($event_data);
        $this->logger->log_transaction(
            $order_id,
            'new_order',
            $event_data,
            $success,
            $success ? null : 'Failed to send new order to Matomo'
        );
    }

    /**
     * Get order object using HPOS-compatible method
     *
     * @param int $order_id Order ID.
     * @return \WC_Order|null
     */
    private function get_order(int $order_id): ?\WC_Order {
        if (class_exists(OrderUtil::class)) {
            $order_type = OrderUtil::get_order_type($order_id);
            if ($order_type) {
                return wc_get_order($order_id);
            }
        }
        return wc_get_order($order_id);
    }

    /**
     * Get order items data for tracking
     *
     * @param \WC_Order $order WooCommerce order object.
     * @return array
     */
    private function get_order_items_data(\WC_Order $order): array {
        $items = [];
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $items[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => $product->get_price(),
                'category' => $this->get_product_categories($product),
            ];
        }
        return $items;
    }

    /**
     * Get product categories
     *
     * @param \WC_Product $product WooCommerce product object.
     * @return string
     */
    private function get_product_categories(\WC_Product $product): string {
        $terms = get_the_terms($product->get_id(), 'product_cat');
        if (!$terms || is_wp_error($terms)) {
            return '';
        }

        return implode('|', wp_list_pluck($terms, 'name'));
    }

    /**
     * Send data to Matomo
     *
     * @param array $data Data to send.
     * @return bool Whether the request was successful.
     */
    private function send_to_matomo(array $data): bool {
        if (empty($this->settings['matomo_url']) || empty($this->settings['site_id']) || empty($this->settings['auth_token'])) {
            return false;
        }

        $url = rtrim($this->settings['matomo_url'], '/') . '/matomo.php';
        $params = [
            'idsite' => $this->settings['site_id'],
            'rec' => 1,
            'apiv' => 1,
            'e_a' => $data['e_a'],
            'e_c' => $data['e_c'],
            'e_n' => $data['e_n'],
            'e_v' => $data['e_v'],
            'url' => home_url(),
            'urlref' => wp_get_referer(),
            'uid' => get_current_user_id(),
            'rand' => wp_rand(),
            'token_auth' => $this->settings['auth_token'],
        ];

        // Add custom parameters
        foreach ($data as $key => $value) {
            if (!in_array($key, ['e_a', 'e_c', 'e_n', 'e_v'], true)) {
                $params['c_' . $key] = is_array($value) ? wp_json_encode($value) : $value;
            }
        }

        $response = wp_remote_post($url, [
            'body' => $params,
            'timeout' => 5,
            'blocking' => true,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        return $response_code === 200;
    }

    /**
     * Check if tracking is enabled
     *
     * @return bool
     */
    private function is_tracking_enabled(): bool {
        return !empty($this->settings['tracking_enabled']);
    }

    /**
     * Clean up old logs
     */
    public function cleanup_logs(): void {
        $days = get_option('edh_wc_matomo_log_retention_days', 30);
        $this->logger->cleanup_old_logs($days);
    }
} 