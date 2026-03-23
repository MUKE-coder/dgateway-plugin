<?php defined('ABSPATH') || exit; ?>
<div class="wrap dgw-admin-wrap">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <img src="<?php echo esc_url(DGW_PLUGIN_URL . 'assets/images/logo.png'); ?>" alt="DGateway" style="height:36px; width:auto;" />
        <h1 style="margin:0;"><?php esc_html_e('DGateway Dashboard', 'dgateway'); ?></h1>
    </div>

    <?php
    $api_key = get_option('dgw_api_key', '');
    if (empty($api_key)) : ?>
        <div class="dgw-notice dgw-notice--info">
            <?php printf(
                __('Welcome to DGateway! Please <a href="%s">configure your API key</a> to get started.', 'dgateway'),
                esc_url(admin_url('admin.php?page=dgateway-settings'))
            ); ?>
        </div>
    <?php else : ?>

        <!-- Wallet Balances -->
        <h2><?php esc_html_e('Wallet Balances', 'dgateway'); ?></h2>
        <div class="dgw-cards" id="dgw-wallet-cards">
            <div class="dgw-loading"><span class="dgw-spinner"></span> Loading balances...</div>
        </div>

        <!-- Recent Transactions -->
        <div class="dgw-table-wrap">
            <div class="dgw-table-header">
                <h2><?php esc_html_e('Recent Transactions', 'dgateway'); ?></h2>
                <a href="<?php echo esc_url(admin_url('admin.php?page=dgateway-transactions')); ?>" class="button button-small">
                    <?php esc_html_e('View All', 'dgateway'); ?>
                </a>
            </div>
            <div id="dgw-recent-transactions">
                <div class="dgw-loading"><span class="dgw-spinner"></span> Loading transactions...</div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="dgw-cards" style="margin-top:24px;">
            <div class="dgw-card">
                <div class="dgw-card-label"><?php esc_html_e('Shortcodes', 'dgateway'); ?></div>
                <p style="font-size:12px;color:#555;margin:8px 0 0;line-height:2;">
                    <strong>Pay Button</strong><br>
                    <code>[dgw_pay_button amount="5000" currency="UGX" description="Premium Access" label="Pay Now"]</code><br>
                    <strong>Donation Form</strong><br>
                    <code>[dgw_donation_form amounts="5000,10000,50000" title="Support Us" collect_name="yes" collect_email="yes"]</code><br>
                    <strong>Product Card</strong><br>
                    <code>[dgw_product_card name="T-Shirt" price="25000" currency="UGX" description="Official merch" image="https://..." button_label="Buy Now"]</code><br>
                    <strong>Subscribe Button</strong><br>
                    <code>[dgw_subscribe_button plan_id="1" label="Subscribe Monthly"]</code>
                </p>
            </div>
            <div class="dgw-card">
                <div class="dgw-card-label"><?php esc_html_e('Webhook URL', 'dgateway'); ?></div>
                <p style="font-size:13px;margin:8px 0 0;">
                    <code style="word-break:break-all;"><?php echo esc_url(rest_url('dgateway/v1/webhook')); ?></code>
                </p>
            </div>
            <div class="dgw-card">
                <div class="dgw-card-label"><?php esc_html_e('Mode', 'dgateway'); ?></div>
                <p style="margin:8px 0 0;">
                    <?php
                    if (strpos($api_key, 'dgw_test') === 0) {
                        echo '<span class="dgw-badge dgw-badge-warning">TEST</span>';
                    } elseif (strpos($api_key, 'dgw_live') === 0) {
                        echo '<span class="dgw-badge dgw-badge-success">LIVE</span>';
                    } else {
                        echo '<span class="dgw-badge">Unknown</span>';
                    }
                    ?>
                </p>
            </div>
        </div>

        <script>
            jQuery(function() { if (typeof dgwLoadDashboard === 'function') dgwLoadDashboard(); });
        </script>

    <?php endif; ?>
</div>
