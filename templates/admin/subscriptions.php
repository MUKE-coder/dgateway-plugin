<?php defined('ABSPATH') || exit; ?>
<div class="wrap dgw-admin-wrap">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <img src="<?php echo esc_url(DGW_PLUGIN_URL . 'assets/images/logo.png'); ?>" alt="DGateway" style="height:36px; width:auto;" />
        <h1 style="margin:0;"><?php esc_html_e('Subscriptions', 'dgateway'); ?></h1>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start;">
        <!-- Plans -->
        <div>
            <div class="dgw-table-wrap">
                <div class="dgw-table-header">
                    <h2><?php esc_html_e('Subscription Plans', 'dgateway'); ?></h2>
                </div>
                <table class="dgw-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Name', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Amount', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Interval', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Status', 'dgateway'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="dgw-plans-body">
                        <tr>
                            <td colspan="4">
                                <div class="dgw-loading"><span class="dgw-spinner"></span> Loading...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Active Subscriptions -->
        <div>
            <div class="dgw-table-wrap">
                <div class="dgw-table-header">
                    <h2><?php esc_html_e('Active Subscriptions', 'dgateway'); ?></h2>
                </div>

                <div class="dgw-filters">
                    <select id="dgw-sub-filter-status">
                        <option value=""><?php esc_html_e('All Statuses', 'dgateway'); ?></option>
                        <option value="trialing"><?php esc_html_e('Trialing', 'dgateway'); ?></option>
                        <option value="active"><?php esc_html_e('Active', 'dgateway'); ?></option>
                        <option value="past_due"><?php esc_html_e('Past Due', 'dgateway'); ?></option>
                        <option value="paused"><?php esc_html_e('Paused', 'dgateway'); ?></option>
                        <option value="cancelled"><?php esc_html_e('Cancelled', 'dgateway'); ?></option>
                        <option value="completed"><?php esc_html_e('Completed', 'dgateway'); ?></option>
                    </select>
                </div>

                <table class="dgw-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Reference', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Customer', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Plan', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Status', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Next Due', 'dgateway'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="dgw-subscriptions-body">
                        <tr>
                            <td colspan="5">
                                <div class="dgw-loading"><span class="dgw-spinner"></span> Loading...</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(function() {
        if (typeof dgwLoadPlans === 'function') dgwLoadPlans();
        if (typeof dgwLoadSubscriptions === 'function') dgwLoadSubscriptions();
    });
</script>
