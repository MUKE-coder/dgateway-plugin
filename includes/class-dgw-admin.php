<?php
defined('ABSPATH') || exit;

/**
 * Admin menu pages and settings.
 */
class DGW_Admin {

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menus']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_dgw_test_connection', [$this, 'ajax_test_connection']);
        add_action('wp_ajax_dgw_get_transactions', [$this, 'ajax_get_transactions']);
        add_action('wp_ajax_dgw_get_wallets', [$this, 'ajax_get_wallets']);
        add_action('wp_ajax_dgw_get_withdrawals', [$this, 'ajax_get_withdrawals']);
        add_action('wp_ajax_dgw_create_withdrawal', [$this, 'ajax_create_withdrawal']);
        add_action('wp_ajax_dgw_get_plans', [$this, 'ajax_get_plans']);
        add_action('wp_ajax_dgw_get_subscriptions', [$this, 'ajax_get_subscriptions']);
    }

    /* ------------------------------------------------------------------ */
    /*  Menu Registration                                                  */
    /* ------------------------------------------------------------------ */

    public function register_menus() {
        // Top-level menu.
        add_menu_page(
            __('DGateway', 'dgateway'),
            __('DGateway', 'dgateway'),
            'manage_options',
            'dgateway',
            [$this, 'render_dashboard'],
            'dashicons-money-alt',
            56
        );

        // Submenus.
        add_submenu_page('dgateway', __('Dashboard', 'dgateway'), __('Dashboard', 'dgateway'), 'manage_options', 'dgateway', [$this, 'render_dashboard']);
        add_submenu_page('dgateway', __('Transactions', 'dgateway'), __('Transactions', 'dgateway'), 'manage_options', 'dgateway-transactions', [$this, 'render_transactions']);
        add_submenu_page('dgateway', __('Withdrawals', 'dgateway'), __('Withdrawals', 'dgateway'), 'manage_options', 'dgateway-withdrawals', [$this, 'render_withdrawals']);
        add_submenu_page('dgateway', __('Subscriptions', 'dgateway'), __('Subscriptions', 'dgateway'), 'manage_options', 'dgateway-subscriptions', [$this, 'render_subscriptions']);
        add_submenu_page('dgateway', __('Settings', 'dgateway'), __('Settings', 'dgateway'), 'manage_options', 'dgateway-settings', [$this, 'render_settings']);
    }

    /* ------------------------------------------------------------------ */
    /*  Settings API                                                       */
    /* ------------------------------------------------------------------ */

    public function register_settings() {
        register_setting('dgw_settings', 'dgw_api_url', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('dgw_settings', 'dgw_api_key', ['sanitize_callback' => 'sanitize_text_field']);
        register_setting('dgw_settings', 'dgw_default_currency', ['sanitize_callback' => 'sanitize_text_field']);
    }

    /* ------------------------------------------------------------------ */
    /*  Page Renderers                                                     */
    /* ------------------------------------------------------------------ */

    public function render_dashboard() {
        include DGW_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    public function render_transactions() {
        include DGW_PLUGIN_DIR . 'templates/admin/transactions.php';
    }

    public function render_withdrawals() {
        include DGW_PLUGIN_DIR . 'templates/admin/withdrawals.php';
    }

    public function render_subscriptions() {
        include DGW_PLUGIN_DIR . 'templates/admin/subscriptions.php';
    }

    public function render_settings() {
        include DGW_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /* ------------------------------------------------------------------ */
    /*  Admin Scripts                                                       */
    /* ------------------------------------------------------------------ */

    public function enqueue_scripts($hook) {
        // Only load on DGateway admin pages.
        if (strpos($hook, 'dgateway') === false) {
            return;
        }

        wp_enqueue_style('dgw-admin', DGW_PLUGIN_URL . 'assets/css/dgw-admin.css', [], DGW_VERSION);
        wp_enqueue_script('dgw-admin', DGW_PLUGIN_URL . 'assets/js/dgw-admin.js', ['jquery'], DGW_VERSION, true);
        wp_localize_script('dgw-admin', 'dgwAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('dgw_admin_nonce'),
            'restUrl' => esc_url_raw(rest_url('dgateway/v1/')),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  AJAX Handlers                                                      */
    /* ------------------------------------------------------------------ */

    public function ajax_test_connection() {
        check_ajax_referer('dgw_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $api = DGW_Plugin::api();
        $result = $api->get_wallets();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Connection successful! DGateway API is reachable.', 'dgateway'),
            'wallets' => $result['data'] ?? [],
        ]);
    }

    public function ajax_get_transactions() {
        check_ajax_referer('dgw_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $params = isset($_POST['params']) && is_array($_POST['params']) ? $_POST['params'] : [];
        $safe = [];
        foreach (['page', 'per_page', 'status', 'provider', 'currency', 'direction', 'from', 'to'] as $key) {
            if (!empty($params[$key])) {
                $safe[$key] = sanitize_text_field($params[$key]);
            }
        }

        $api    = DGW_Plugin::api();
        $result = $api->get_transactions($safe);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'transactions' => $result['data'] ?? [],
            'total'        => $result['total'] ?? 0,
        ]);
    }

    public function ajax_get_wallets() {
        check_ajax_referer('dgw_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $api    = DGW_Plugin::api();
        $result = $api->get_wallets();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'wallets' => $result['data'] ?? [],
        ]);
    }

    public function ajax_get_withdrawals() {
        check_ajax_referer('dgw_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $api    = DGW_Plugin::api();
        $result = $api->get_withdrawals();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'withdrawals' => $result['data'] ?? [],
        ]);
    }

    public function ajax_create_withdrawal() {
        check_ajax_referer('dgw_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $data = [
            'amount'           => floatval($_POST['amount'] ?? 0),
            'currency'         => sanitize_text_field($_POST['currency'] ?? ''),
            'method'           => sanitize_text_field($_POST['method'] ?? ''),
            'destination'      => sanitize_text_field($_POST['destination'] ?? ''),
            'destination_name' => sanitize_text_field($_POST['destination_name'] ?? ''),
            'note'             => sanitize_text_field($_POST['note'] ?? ''),
        ];

        if ($data['amount'] <= 0) {
            wp_send_json_error(['message' => 'Amount must be greater than zero']);
        }

        $api    = DGW_Plugin::api();
        $result = $api->create_withdrawal($data);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['withdrawal' => $result['data'] ?? $result]);
    }

    public function ajax_get_plans() {
        check_ajax_referer('dgw_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $api    = DGW_Plugin::api();
        $result = $api->get_plans();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'plans' => $result['data'] ?? [],
        ]);
    }

    public function ajax_get_subscriptions() {
        check_ajax_referer('dgw_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $params = isset($_POST['params']) && is_array($_POST['params']) ? $_POST['params'] : [];
        $safe = [];
        foreach (['page', 'per_page', 'status', 'plan_id'] as $key) {
            if (!empty($params[$key])) {
                $safe[$key] = sanitize_text_field($params[$key]);
            }
        }

        $api    = DGW_Plugin::api();
        $result = $api->get_subscriptions($safe);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'subscriptions' => $result['data'] ?? [],
            'total'         => $result['total'] ?? 0,
        ]);
    }
}
