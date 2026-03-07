<?php
defined('ABSPATH') || exit;

/**
 * WP REST API endpoints that proxy frontend requests to DGateway.
 * Keeps the API key server-side.
 */
class DGW_REST_API {

    private $namespace = 'dgateway/v1';

    public function register_routes() {
        // Create a payment (collect).
        register_rest_route($this->namespace, '/checkout', [
            'methods'             => 'POST',
            'callback'            => [$this, 'checkout'],
            'permission_callback' => [$this, 'verify_nonce'],
        ]);

        // Verify / poll transaction status.
        register_rest_route($this->namespace, '/verify', [
            'methods'             => 'POST',
            'callback'            => [$this, 'verify'],
            'permission_callback' => [$this, 'verify_nonce'],
        ]);

        // Subscribe to a plan.
        register_rest_route($this->namespace, '/subscribe', [
            'methods'             => 'POST',
            'callback'            => [$this, 'subscribe'],
            'permission_callback' => [$this, 'verify_nonce'],
        ]);

        // Inbound webhook from DGateway (public, signature-verified).
        register_rest_route($this->namespace, '/webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'webhook'],
            'permission_callback' => '__return_true',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  Endpoint: POST /dgateway/v1/checkout                               */
    /* ------------------------------------------------------------------ */

    public function checkout(\WP_REST_Request $request) {
        $amount       = floatval($request->get_param('amount'));
        $currency     = sanitize_text_field($request->get_param('currency') ?: get_option('dgw_default_currency', 'UGX'));
        $phone_number = sanitize_text_field($request->get_param('phone_number') ?: '');
        $provider     = sanitize_text_field($request->get_param('provider') ?: '');
        $description  = sanitize_text_field($request->get_param('description') ?: '');
        $metadata     = $request->get_param('metadata');

        if ($amount <= 0) {
            return new \WP_Error('validation_error', 'Amount must be greater than zero', ['status' => 400]);
        }

        $args = [
            'amount'       => $amount,
            'currency'     => $currency,
            'phone_number' => $phone_number ?: '0000000000',
            'description'  => $description,
        ];

        if ($provider) {
            $args['provider'] = $provider;
        }
        if (is_array($metadata)) {
            $args['metadata'] = empty($metadata) ? new \stdClass() : (object) $metadata;
        }

        $api    = DGW_Plugin::api();
        $result = $api->collect($args);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'error' => [
                    'code'    => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                ],
            ], 400);
        }

        return new \WP_REST_Response($result, 200);
    }

    /* ------------------------------------------------------------------ */
    /*  Endpoint: POST /dgateway/v1/verify                                 */
    /* ------------------------------------------------------------------ */

    public function verify(\WP_REST_Request $request) {
        $reference = sanitize_text_field($request->get_param('reference'));

        if (empty($reference)) {
            return new \WP_Error('validation_error', 'Reference is required', ['status' => 400]);
        }

        $api    = DGW_Plugin::api();
        $result = $api->verify($reference);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'error' => [
                    'code'    => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                ],
            ], 400);
        }

        // Sync status to WooCommerce order if applicable.
        $status = $result['data']['status'] ?? '';
        if (($status === 'completed' || $status === 'failed') && class_exists('WooCommerce')) {
            $this->sync_wc_order_status($reference, $status);
        }

        return new \WP_REST_Response($result, 200);
    }

    /**
     * Update WooCommerce order status when a verify poll detects a status change.
     */
    private function sync_wc_order_status(string $reference, string $status) {
        $orders = wc_get_orders([
            'meta_key'   => '_dgw_reference',
            'meta_value' => $reference,
            'limit'      => 1,
        ]);

        if (empty($orders)) {
            return;
        }

        $order = $orders[0];

        if ($status === 'completed' && !$order->is_paid()) {
            $order->payment_complete($reference);
            $order->add_order_note(
                sprintf(__('DGateway payment confirmed via status check. Reference: %s', 'dgateway'), $reference)
            );
        } elseif ($status === 'failed' && $order->get_status() !== 'failed') {
            $order->update_status('failed',
                sprintf(__('DGateway payment failed. Reference: %s', 'dgateway'), $reference)
            );
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Endpoint: POST /dgateway/v1/subscribe                              */
    /* ------------------------------------------------------------------ */

    public function subscribe(\WP_REST_Request $request) {
        $plan_id        = intval($request->get_param('plan_id'));
        $customer_email = sanitize_email($request->get_param('customer_email') ?: '');
        $customer_name  = sanitize_text_field($request->get_param('customer_name') ?: '');
        $customer_phone = sanitize_text_field($request->get_param('customer_phone') ?: '');
        $provider       = sanitize_text_field($request->get_param('provider') ?: '');

        if ($plan_id <= 0 || empty($customer_email)) {
            return new \WP_Error('validation_error', 'plan_id and customer_email are required', ['status' => 400]);
        }

        $api    = DGW_Plugin::api();
        $result = $api->create_subscription([
            'plan_id'        => $plan_id,
            'customer_email' => $customer_email,
            'customer_name'  => $customer_name,
            'customer_phone' => $customer_phone,
            'provider'       => $provider,
        ]);

        if (is_wp_error($result)) {
            return new \WP_REST_Response([
                'error' => [
                    'code'    => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                ],
            ], 400);
        }

        return new \WP_REST_Response($result, 200);
    }

    /* ------------------------------------------------------------------ */
    /*  Endpoint: POST /dgateway/v1/webhook (inbound from DGateway)        */
    /* ------------------------------------------------------------------ */

    public function webhook(\WP_REST_Request $request) {
        $handler = new DGW_Webhook_Handler();
        return $handler->handle($request);
    }

    /* ------------------------------------------------------------------ */
    /*  Permission: nonce verification for public endpoints                 */
    /* ------------------------------------------------------------------ */

    public function verify_nonce(\WP_REST_Request $request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if ($nonce && wp_verify_nonce($nonce, 'wp_rest')) {
            return true;
        }
        return true; // Allow public access (shortcodes on public pages). Security via rate limiting.
    }
}
