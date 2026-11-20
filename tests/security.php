<?php
/** Standalone regression tests: php tests/security.php (no network or database). */
define('ABSPATH', __DIR__);

class WP_REST_Request {
    private $raw;
    private $headers;
    public function __construct($body, array $headers = []) {
        $this->raw = $body;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }
    public function get_body() { return $this->raw; }
    public function get_json_params() { return json_decode($this->raw, true); }
    public function get_param($key) { return $this->get_json_params()[$key] ?? null; }
    public function get_header($key) { return $this->headers[strtolower($key)] ?? null; }
}
class WP_REST_Response {
    public $data;
    public $status;
    public function __construct($data, $status) { $this->data = $data; $this->status = $status; }
}
class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct($code, $message, $data) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}
class WooCommerce {}
class TestOrder {
    public $status = 'on-hold';
    public $date_paid = null;
    public $method = 'dgateway';
    public $completions = 0;
    public $failures = 0;
    public $notes = 0;
    public function get_payment_method() { return $this->method; }
    public function is_paid() { return in_array($this->status, ['processing', 'completed'], true); }
    public function get_date_paid() { return $this->date_paid; }
    public function get_status() { return $this->status; }
    public function has_status($statuses) { return in_array($this->status, (array) $statuses, true); }
    public function get_id() { return 123; }
    public function payment_complete($reference) {
        ++$this->completions;
        $this->status = 'processing';
        $this->date_paid = '2026-11-16';
    }
    public function add_order_note($note) { ++$this->notes; }
    public function update_status($status, $note) { ++$this->failures; $this->status = $status; }
}
class TestAPI {
    public $result;
    public $calls = 0;
    public function verify($reference) { ++$this->calls; return $this->result; }
}
class DGW_Plugin {
    public static $client;
    public static function api() { return self::$client; }
}
function get_option($key, $default = false) { return $GLOBALS['options'][$key] ?? $default; }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid' && $action === 'wp_rest'; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($text, $domain) { return $text; }
function wc_get_orders($args) {
    ++$GLOBALS['lookups'];
    return $args['meta_value'] === 'txn_test' ? [$GLOBALS['order']] : [];
}
function do_action($name, ...$args) { $GLOBALS['actions'][] = $name; }
function wc_get_logger() {
    return new class { public function log($level, $message, $context) {} };
}

require __DIR__ . '/../includes/class-dgw-webhook-handler.php';
require __DIR__ . '/../includes/class-dgw-rest-api.php';

$checks = 0;
function check($condition, $message) {
    ++$GLOBALS['checks'];
    if (!$condition) { throw new RuntimeException($message); }
}
function reset_state() {
    $GLOBALS['options'] = ['dgw_webhook_secret' => 'dashboard-app-secret'];
    $GLOBALS['order'] = new TestOrder();
    $GLOBALS['actions'] = [];
    $GLOBALS['lookups'] = 0;
    DGW_Plugin::$client = new TestAPI();
}
function signed_request($raw, $secret = 'dashboard-app-secret') {
    return new WP_REST_Request($raw, ['X-DGateway-Signature' => hash_hmac('sha256', $raw, $secret)]);
}

$handler = new DGW_Webhook_Handler();
$rest = new DGW_REST_API();
$payment = '{"reference":"txn_test","status":"completed"}';
$subscription = '{"event":"subscription.created","subscription":{"id":12}}';

foreach ([$payment, $subscription] as $raw) {
    foreach ([null, '', str_repeat('0', 64), 'sha256=' . hash_hmac('sha256', $raw, 'dashboard-app-secret')] as $signature) {
        reset_state();
        $response = $handler->handle(new WP_REST_Request($raw, ['X-DGateway-Signature' => $signature]));
        check($response->status === 401, 'Unsigned/invalid events must be rejected');
        check($lookups === 0 && $actions === [], 'Rejected events must have no side effects');
    }
}
reset_state();
$options['dgw_webhook_secret'] = '';
check($handler->handle(signed_request($payment))->status === 503, 'Missing secret must fail closed');
check($lookups === 0 && $actions === [], 'Unconfigured webhook must have no side effects');
reset_state();
$tampered = new WP_REST_Request($payment . ' ', ['X-DGateway-Signature' => hash_hmac('sha256', $payment, 'dashboard-app-secret')]);
check($handler->handle($tampered)->status === 401, 'Even whitespace changes must invalidate signatures');
check($handler->handle(signed_request($payment, 'wrong-secret'))->status === 401, 'Wrong app secret must fail');

reset_state();
check($handler->handle(signed_request($payment))->status === 200, 'Valid payment accepted');
check($order->completions === 1, 'Valid payment marks matching order paid');
$handler->handle(signed_request($payment));
check($order->completions === 1 && $order->notes === 1, 'Duplicate completion is idempotent');
$handler->handle(signed_request('{"reference":"txn_test","status":"failed"}'));
check($order->failures === 0 && $order->is_paid(), 'Late failure must not downgrade paid order');

reset_state();
check($handler->handle(signed_request($subscription))->status === 200, 'Valid subscription accepted');
check($actions === ['dgw_subscription_webhook'], 'Signed subscription hook fires');
foreach (['null', 'false', '"text"', '{', '{"event":[]}', '{"event":"subscription.created","subscription":"bad"}', '{"reference":[],"status":"completed"}', '{"reference":"txn_test","status":[]}'] as $raw) {
    reset_state();
    check($handler->handle(signed_request($raw))->status === 400, 'Malformed signed payload rejected');
    check($lookups === 0 && $actions === [], 'Malformed payload has no side effects');
}

foreach (['webhook', 'poll'] as $source) {
    foreach (['processing', 'completed', 'refunded', 'cancelled', 'failed', 'pending', 'on-hold'] as $status) {
        reset_state();
        $order->status = $status;
        $expected = in_array($status, ['pending', 'on-hold'], true) ? 1 : 0;
        DGW_Plugin::$client->result = ['data' => ['status' => 'failed']];
        if ($source === 'webhook') {
            $handler->handle(signed_request('{"reference":"txn_test","status":"failed"}'));
        } else {
            $rest->verify(new WP_REST_Request('{"reference":"txn_test"}'));
        }
        check($order->failures === $expected, "$source failure transition from $status");
    }
    reset_state();
    $order->method = 'other-gateway';
    DGW_Plugin::$client->result = ['data' => ['status' => 'completed']];
    if ($source === 'webhook') {
        $handler->handle(signed_request($payment));
    } else {
        $rest->verify(new WP_REST_Request('{"reference":"txn_test"}'));
    }
    check($order->completions === 0, "$source must not alter another gateway's order");
}

reset_state();
DGW_Plugin::$client->result = ['data' => [
    'reference' => 'txn_test', 'status' => 'completed', 'customer_phone' => 'private',
    'metadata' => ['email' => 'private@example.test'], 'client_secret' => 'private-secret',
], 'extra' => 'private'];
$response = $rest->verify(new WP_REST_Request('{"reference":"txn_test"}'));
check($response->data === ['data' => ['reference' => 'txn_test', 'status' => 'completed']], 'Polling response must contain only reference/status');
check($order->completions === 1, 'Polling still updates order');
foreach (['{}', '{"reference":[]}', '{"reference":123}'] as $raw) {
    reset_state();
    check(is_wp_error($rest->verify(new WP_REST_Request($raw))), 'Invalid reference rejected');
    check(DGW_Plugin::$client->calls === 0, 'Invalid reference must not reach API');
}
foreach ([null, '', 'invalid', 'valid'] as $nonce) {
    $result = $rest->verify_nonce(new WP_REST_Request('{}', ['X-WP-Nonce' => $nonce]));
    check($nonce === 'valid' ? $result === true : is_wp_error($result), 'Nonce permission must fail closed');
}
echo "Passed {$checks} security checks.\n";
