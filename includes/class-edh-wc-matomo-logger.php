<?php
declare(strict_types=1);

namespace EDH_WC_Matomo_Tracking;

/**
 * Logger class for Matomo transactions
 */
class EDH_WC_Matomo_Logger {
    /**
     * Table name for logs
     *
     * @var string
     */
    private string $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'edh_wc_matomo_logs';
        $this->create_table();
    }

    /**
     * Create the logs table if it doesn't exist
     */
    private function create_table(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_data longtext NOT NULL,
            status varchar(20) NOT NULL,
            error_message text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY event_type (event_type),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Log a transaction
     *
     * @param int    $order_id Order ID.
     * @param string $event_type Event type (e.g., 'new_order', 'status_change').
     * @param array  $event_data Event data.
     * @param bool   $success Whether the transaction was successful.
     * @param string $error_message Optional error message.
     * @return int|false The ID of the inserted log entry, or false on failure.
     */
    public function log_transaction(int $order_id, string $event_type, array $event_data, bool $success, ?string $error_message = null): int|false {
        global $wpdb;

        $data = [
            'order_id' => $order_id,
            'event_type' => $event_type,
            'event_data' => wp_json_encode($event_data),
            'status' => $success ? 'success' : 'error',
            'error_message' => $error_message,
        ];

        $result = $wpdb->insert($this->table_name, $data);

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get logs for a specific order
     *
     * @param int $order_id Order ID.
     * @return array Array of log entries.
     */
    public function get_order_logs(int $order_id): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE order_id = %d ORDER BY created_at DESC",
                $order_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get recent logs with pagination
     *
     * @param int $page Page number.
     * @param int $per_page Number of items per page.
     * @return array Array of log entries and total count.
     */
    public function get_recent_logs(int $page = 1, int $per_page = 20): array {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        
        $logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");

        return [
            'logs' => $logs,
            'total' => (int) $total,
            'pages' => ceil($total / $per_page),
        ];
    }

    /**
     * Clean up old logs
     *
     * @param int $days Number of days to keep logs.
     * @return int Number of deleted rows.
     */
    public function cleanup_old_logs(int $days = 30): int {
        global $wpdb;
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
} 