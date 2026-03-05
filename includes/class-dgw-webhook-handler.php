<?php
defined('ABSPATH') || exit;

/**
 * Handles inbound webhooks from DGateway.
 * Updates WooCommerce order status when payments complete/fail.
 */
class DGW_Webhook_Handler {

    public function __construct() {
        // Nothing to hook in constructor — called via REST API.
    }

    /**
     * Process an inbound webhook from DGateway.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle(\WP_REST_Request $request) {
        $body = $request->get_json_params();

        // Handle subscription webhook events.
        $event = $body['event'] ?? '';
        if ($event && strpos($event, 'subscription.') === 0) {
            $subscription = $body['subscription'] ?? [];
            do_action('dgw_subscription_webhook', $event, $subscription, $body);
            $this->log('info', "Subscription webhook: event={$event}");
            return new \WP_REST_Response(['status' => 'received'], 200);
        }

        $reference = $body['reference'] ?? '';
        $status    = $body['status'] ?? '';

        if (empty($reference) || empty($status)) {
            return new \WP_REST_Response(['status' => 'ignored', 'reason' => 'missing reference or status'], 200);
        }

        $this->log('info', "Webhook received: ref={$reference} status={$status}");

        // Try to find a WooCommerce order with this reference.
        if (class_exists('WooCommerce')) {
            $this->update_wc_order($reference, $status);
        }

        // Fire action for custom integrations.
        do_action('dgw_webhook_received', $reference, $status, $body);

        return new \WP_REST_Response(['status' => 'received'], 200);
    }

    /**
     * Find and update a WooCommerce order by DGateway reference.
     */
    private function update_wc_order(string $reference, string $status) {
        $orders = wc_get_orders([
            'meta_key'   => '_dgw_reference',
            'meta_value' => $reference,
            'limit'      => 1,
        ]);

        if (empty($orders)) {
            $this->log('info', "No WC order found for reference: {$reference}");
            return;
        }

        $order = $orders[0];

        switch ($status) {
            case 'completed':
                if (!$order->is_paid()) {
                    $order->payment_complete($reference);
                    $order->add_order_note(
                        sprintf(__('DGateway payment completed. Reference: %s', 'dgateway'), $reference)
                    );
                    $this->log('info', "Order #{$order->get_id()} marked as paid via webhook");
                }
                break;

            case 'failed':
                if ($order->get_status() !== 'failed') {
                    $order->update_status('failed',
                        sprintf(__('DGateway payment failed. Reference: %s', 'dgateway'), $reference)
                    );
                    $this->log('info', "Order #{$order->get_id()} marked as failed via webhook");
                }
                break;

            default:
                $this->log('info', "Ignoring webhook status '{$status}' for order #{$order->get_id()}");
                break;
        }
    }

    private function log(string $level, string $message) {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->log($level, $message, ['source' => 'dgateway-webhook']);
        } else {
            error_log("[DGateway Webhook] {$message}");
        }
    }
}
