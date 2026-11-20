<?php defined('ABSPATH') || exit; ?>
<div class="wrap dgw-admin-wrap">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <img src="<?php echo esc_url(DGW_PLUGIN_URL . 'assets/images/logo.png'); ?>" alt="DGateway" style="height:36px; width:auto;" />
        <h1 style="margin:0;"><?php esc_html_e('DGateway Settings', 'dgateway'); ?></h1>
    </div>

    <form method="post" action="options.php" class="dgw-settings-form">
        <?php settings_fields('dgw_settings'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="dgw_api_url"><?php esc_html_e('API URL', 'dgateway'); ?></label></th>
                <td>
                    <input type="url" id="dgw_api_url" name="dgw_api_url"
                           value="<?php echo esc_attr(get_option('dgw_api_url', 'https://dgatewayapi.desispay.com')); ?>"
                           class="regular-text" />
                    <p class="description"><?php esc_html_e('DGateway API base URL.', 'dgateway'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="dgw_api_key"><?php esc_html_e('API Key', 'dgateway'); ?></label></th>
                <td>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="password" id="dgw_api_key" name="dgw_api_key"
                               value="<?php echo esc_attr(get_option('dgw_api_key', '')); ?>"
                               class="regular-text" autocomplete="off" />
                        <button type="button" class="button" onclick="var el=document.getElementById('dgw_api_key'); el.type = el.type==='password'?'text':'password';">
                            <?php esc_html_e('Show/Hide', 'dgateway'); ?>
                        </button>
                    </div>
                    <p class="description">
                        <?php esc_html_e('Your DGateway API key. Starts with dgw_live_ or dgw_test_.', 'dgateway'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="dgw_default_currency"><?php esc_html_e('Default Currency', 'dgateway'); ?></label></th>
                <td>
                    <?php $currency = get_option('dgw_default_currency', 'UGX'); ?>
                    <select id="dgw_default_currency" name="dgw_default_currency">
                        <?php foreach (['UGX', 'USD', 'KES', 'EUR', 'GBP', 'TZS', 'RWF'] as $code) : ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                                <?php echo esc_html($code); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Webhook URL', 'dgateway'); ?></th>
                <td>
                    <code id="dgw-webhook-url"><?php echo esc_url(rest_url('dgateway/v1/webhook')); ?></code>
                    <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(document.getElementById('dgw-webhook-url').textContent); this.textContent='Copied!';">
                        <?php esc_html_e('Copy', 'dgateway'); ?>
                    </button>
                    <p class="description">
                        <?php esc_html_e('Add this URL as the Webhook URL in your DGateway App settings.', 'dgateway'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="dgw_webhook_secret"><?php esc_html_e('Webhook Secret', 'dgateway'); ?></label></th>
                <td>
                    <input type="password" id="dgw_webhook_secret" name="dgw_webhook_secret"
                           value="<?php echo esc_attr(get_option('dgw_webhook_secret', '')); ?>"
                           class="regular-text" autocomplete="new-password" />
                    <p class="description">
                        <?php esc_html_e('Copy your app webhook secret from the DGateway dashboard. It must match exactly. Unsigned webhooks and signatures made with a different secret are rejected. After upgrading, replace any previously generated local secret with the app secret.', 'dgateway'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Mode', 'dgateway'); ?></th>
                <td>
                    <?php
                    $key = get_option('dgw_api_key', '');
                    $mode = 'Not configured';
                    $mode_class = '';
                    if (strpos($key, 'dgw_test') === 0) {
                        $mode = 'TEST';
                        $mode_class = 'dgw-badge-warning';
                    } elseif (strpos($key, 'dgw_live') === 0) {
                        $mode = 'LIVE';
                        $mode_class = 'dgw-badge-success';
                    }
                    ?>
                    <span class="dgw-badge <?php echo esc_attr($mode_class); ?>"><?php echo esc_html($mode); ?></span>
                    <p class="description"><?php esc_html_e('Determined automatically from your API key prefix.', 'dgateway'); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>

    <hr />

    <h2><?php esc_html_e('Test Connection', 'dgateway'); ?></h2>
    <p><?php esc_html_e('Verify your API key and connection to DGateway.', 'dgateway'); ?></p>
    <button type="button" id="dgw-test-connection" class="button button-secondary">
        <?php esc_html_e('Test Connection', 'dgateway'); ?>
    </button>
    <span id="dgw-test-result" style="margin-left:12px;"></span>
</div>
