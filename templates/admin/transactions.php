<?php defined('ABSPATH') || exit; ?>
<div class="wrap dgw-admin-wrap">
    <div style="display:flex; align-items:center; gap:12px; margin-bottom:8px;">
        <img src="<?php echo esc_url(DGW_PLUGIN_URL . 'assets/images/logo.png'); ?>" alt="DGateway" style="height:36px; width:auto;" />
        <h1 style="margin:0;"><?php esc_html_e('Transactions', 'dgateway'); ?></h1>
    </div>

    <div class="dgw-table-wrap">
        <!-- Filters -->
        <div class="dgw-filters">
            <select id="dgw-filter-status">
                <option value=""><?php esc_html_e('All Statuses', 'dgateway'); ?></option>
                <option value="completed"><?php esc_html_e('Completed', 'dgateway'); ?></option>
                <option value="pending"><?php esc_html_e('Pending', 'dgateway'); ?></option>
                <option value="failed"><?php esc_html_e('Failed', 'dgateway'); ?></option>
            </select>

            <select id="dgw-filter-provider">
                <option value=""><?php esc_html_e('All Providers', 'dgateway'); ?></option>
                <option value="iotec"><?php esc_html_e('Iotec (MoMo)', 'dgateway'); ?></option>
                <option value="stripe"><?php esc_html_e('Stripe (Card)', 'dgateway'); ?></option>
            </select>

            <select id="dgw-filter-currency">
                <option value=""><?php esc_html_e('All Currencies', 'dgateway'); ?></option>
                <option value="UGX">UGX</option>
                <option value="USD">USD</option>
                <option value="KES">KES</option>
                <option value="EUR">EUR</option>
            </select>
        </div>

        <!-- Table -->
        <table class="dgw-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Reference', 'dgateway'); ?></th>
                    <th><?php esc_html_e('Amount', 'dgateway'); ?></th>
                    <th><?php esc_html_e('Provider', 'dgateway'); ?></th>
                    <th><?php esc_html_e('Status', 'dgateway'); ?></th>
                    <th><?php esc_html_e('Direction', 'dgateway'); ?></th>
                    <th><?php esc_html_e('Date', 'dgateway'); ?></th>
                </tr>
            </thead>
            <tbody id="dgw-transactions-body">
                <tr>
                    <td colspan="6">
                        <div class="dgw-loading"><span class="dgw-spinner"></span> Loading transactions...</div>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div class="dgw-pagination">
            <span id="dgw-txn-page-info">Page 1</span>
            <div class="dgw-pagination-buttons">
                <button type="button" id="dgw-txn-prev" disabled>&laquo; <?php esc_html_e('Previous', 'dgateway'); ?></button>
                <button type="button" id="dgw-txn-next"><?php esc_html_e('Next', 'dgateway'); ?> &raquo;</button>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(function() { if (typeof dgwLoadTransactions === 'function') dgwLoadTransactions(1); });
</script>
