<?php defined('ABSPATH') || exit; ?>
<div class="wrap dgw-admin-wrap">
    <h1><?php esc_html_e('Withdrawals', 'dgateway'); ?></h1>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:start;">
        <!-- Withdrawal Form -->
        <div class="dgw-withdrawal-form">
            <h3><?php esc_html_e('New Withdrawal', 'dgateway'); ?></h3>

            <form id="dgw-withdrawal-form">
                <div class="dgw-form-group">
                    <label for="dgw-wd-provider"><?php esc_html_e('Provider', 'dgateway'); ?></label>
                    <select id="dgw-wd-provider" name="provider" required>
                        <option value=""><?php esc_html_e('Select provider...', 'dgateway'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Select which provider to withdraw from.', 'dgateway'); ?></p>
                </div>

                <div class="dgw-form-group">
                    <label for="dgw-wd-amount"><?php esc_html_e('Amount', 'dgateway'); ?></label>
                    <input type="number" id="dgw-wd-amount" name="amount" min="1" step="any" required />
                </div>

                <div class="dgw-form-group">
                    <label for="dgw-wd-currency"><?php esc_html_e('Currency', 'dgateway'); ?></label>
                    <input type="text" id="dgw-wd-currency" name="currency"
                           value="<?php echo esc_attr(get_option('dgw_default_currency', 'UGX')); ?>" required />
                </div>

                <div class="dgw-form-group">
                    <label for="dgw-wd-method"><?php esc_html_e('Method', 'dgateway'); ?></label>
                    <select id="dgw-wd-method" name="method" required>
                        <option value="mobile_money"><?php esc_html_e('Mobile Money', 'dgateway'); ?></option>
                        <option value="bank_transfer"><?php esc_html_e('Bank Transfer', 'dgateway'); ?></option>
                    </select>
                </div>

                <div class="dgw-form-group">
                    <label for="dgw-wd-destination"><?php esc_html_e('Destination', 'dgateway'); ?></label>
                    <input type="text" id="dgw-wd-destination" name="destination"
                           placeholder="<?php esc_attr_e('Phone or account number', 'dgateway'); ?>" required />
                </div>

                <div class="dgw-form-group">
                    <label for="dgw-wd-dest-name"><?php esc_html_e('Recipient Name', 'dgateway'); ?></label>
                    <input type="text" id="dgw-wd-dest-name" name="destination_name"
                           placeholder="<?php esc_attr_e('Account holder name', 'dgateway'); ?>" />
                </div>

                <div class="dgw-form-group">
                    <label for="dgw-wd-note"><?php esc_html_e('Note', 'dgateway'); ?></label>
                    <textarea id="dgw-wd-note" name="note" rows="2"
                              placeholder="<?php esc_attr_e('Optional note', 'dgateway'); ?>"></textarea>
                </div>

                <button type="submit" class="button button-primary" style="width:100%;">
                    <?php esc_html_e('Submit Withdrawal', 'dgateway'); ?>
                </button>
            </form>

            <div id="dgw-wd-result" style="margin-top:12px;"></div>
        </div>

        <!-- Withdrawal History -->
        <div>
            <div class="dgw-table-wrap">
                <div class="dgw-table-header">
                    <h2><?php esc_html_e('Withdrawal History', 'dgateway'); ?></h2>
                </div>
                <table class="dgw-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Amount', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Method', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Destination', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Status', 'dgateway'); ?></th>
                            <th><?php esc_html_e('Date', 'dgateway'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="dgw-withdrawals-body">
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
    jQuery(function() { if (typeof dgwLoadWithdrawals === 'function') dgwLoadWithdrawals(); });
</script>
