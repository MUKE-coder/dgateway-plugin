<?php
defined('ABSPATH') || exit;

/**
 * DGateway API HTTP client.
 *
 * Wraps all v1 endpoints using wp_remote_*.
 */
class DGW_API_Client {

    private $api_url;
    private $api_key;

    public function __construct(string $api_url, string $api_key) {
        $this->api_url = rtrim($api_url, '/');
        $this->api_key = $api_key;
    }

    /* ------------------------------------------------------------------ */
    /*  Public Methods                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Collect (pull) a payment from a customer.
     *
     * @param array $args {
     *     @type float  $amount
     *     @type string $currency      e.g. "UGX", "USD"
     *     @type string $phone_number  required for mobile money
     *     @type string $provider      optional: "iotec", "stripe", etc.
     *     @type string $description
     *     @type array  $metadata
     * }
     * @return array|WP_Error
     */
    public function collect(array $args) {
        return $this->request('POST', '/v1/payments/collect', $args);
    }

    /**
     * Verify (poll) a transaction's status.
     *
     * @param string $reference DGateway transaction reference.
     * @return array|WP_Error
     */
    public function verify(string $reference) {
        return $this->request('POST', '/v1/webhooks/verify', [
            'reference' => $reference,
        ]);
    }

    /**
     * List transactions (paginated).
     *
     * @param array $params Query params: page, per_page, status, direction, provider, currency, from, to.
     * @return array|WP_Error
     */
    public function get_transactions(array $params = []) {
        $query = http_build_query($params);
        $endpoint = '/v1/payments/transactions' . ($query ? '?' . $query : '');
        return $this->request('GET', $endpoint);
    }

    /**
     * Get a single transaction by reference.
     *
     * @param string $ref
     * @return array|WP_Error
     */
    public function get_transaction(string $ref) {
        return $this->request('GET', '/v1/payments/transactions/' . urlencode($ref));
    }

    /**
     * Get wallet balances for all configured providers.
     *
     * @return array|WP_Error
     */
    public function get_wallets() {
        return $this->request('GET', '/v1/wallets');
    }

    /**
     * Create a withdrawal request.
     *
     * @param array $data {
     *     @type float  $amount
     *     @type string $currency
     *     @type string $method       "mobile_money" or "bank_transfer"
     *     @type string $destination  phone or account number
     *     @type string $destination_name
     *     @type string $bank_name
     *     @type string $note
     * }
     * @return array|WP_Error
     */
    public function create_withdrawal(array $data) {
        return $this->request('POST', '/v1/withdrawals', $data);
    }

    /**
     * List withdrawals (paginated).
     *
     * @param array $params Query params: page, per_page, status.
     * @return array|WP_Error
     */
    public function get_withdrawals(array $params = []) {
        $query = http_build_query($params);
        $endpoint = '/v1/withdrawals' . ($query ? '?' . $query : '');
        return $this->request('GET', $endpoint);
    }

    /* ------------------------------------------------------------------ */
    /*  Subscription Methods                                               */
    /* ------------------------------------------------------------------ */

    public function create_plan(array $data) {
        return $this->request('POST', '/v1/subscriptions/plans', $data);
    }

    public function get_plans(array $params = []) {
        $query = http_build_query($params);
        $endpoint = '/v1/subscriptions/plans' . ($query ? '?' . $query : '');
        return $this->request('GET', $endpoint);
    }

    public function get_plan(int $id) {
        return $this->request('GET', '/v1/subscriptions/plans/' . $id);
    }

    public function create_subscription(array $data) {
        return $this->request('POST', '/v1/subscriptions', $data);
    }

    public function get_subscriptions(array $params = []) {
        $query = http_build_query($params);
        $endpoint = '/v1/subscriptions' . ($query ? '?' . $query : '');
        return $this->request('GET', $endpoint);
    }

    public function get_subscription(int $id) {
        return $this->request('GET', '/v1/subscriptions/' . $id);
    }

    public function cancel_subscription(int $id) {
        return $this->request('POST', '/v1/subscriptions/' . $id . '/cancel');
    }

    public function charge_subscription(int $id, array $data) {
        return $this->request('POST', '/v1/subscriptions/' . $id . '/charge', $data);
    }

    /* ------------------------------------------------------------------ */
    /*  Internal HTTP helper                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Make an authenticated request to the DGateway API.
     *
     * @param string     $method   HTTP method.
     * @param string     $endpoint Path after base URL.
     * @param array|null $body     JSON body for POST/PUT.
     * @return array|WP_Error  Decoded response on success, WP_Error on failure.
     */
    private function request(string $method, string $endpoint, ?array $body = null) {
        $url = $this->api_url . $endpoint;

        $args = [
            'method'  => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'X-Api-Key'    => $this->api_key,
            ],
            'timeout' => 30,
        ];

        if ($body !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        // Network / cURL error.
        if (is_wp_error($response)) {
            $this->log('error', 'Request failed: ' . $response->get_error_message(), [
                'url'    => $url,
                'method' => $method,
            ]);
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw    = wp_remote_retrieve_body($response);
        $data   = json_decode($raw, true);

        // DGateway error response format: { "error": { "code": "...", "message": "..." } }
        if ($status >= 400 || (is_array($data) && isset($data['error']))) {
            $code    = $data['error']['code'] ?? 'API_ERROR';
            $message = $data['error']['message'] ?? "HTTP {$status} error";

            $this->log('error', "API error [{$code}]: {$message}", [
                'url'    => $url,
                'status' => $status,
            ]);

            return new \WP_Error($code, $message, ['status' => $status]);
        }

        return $data;
    }

    /**
     * Log a message (uses WC_Logger if available, otherwise error_log).
     */
    private function log(string $level, string $message, array $context = []) {
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->log($level, $message . ' ' . wp_json_encode($context), ['source' => 'dgateway']);
        } else {
            error_log('[DGateway] ' . $message . ' ' . wp_json_encode($context));
        }
    }
}
