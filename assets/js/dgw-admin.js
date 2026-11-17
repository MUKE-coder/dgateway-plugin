/**
 * DGateway Admin JS
 *
 * Handles: test connection, transaction list, withdrawal form, pagination.
 * Expects `dgwAdmin` to be localized via wp_localize_script:
 *   { ajaxUrl, nonce, restUrl }
 */
(function ($) {
    'use strict';

    /* ------------------------------------------------------------------ */
    /*  Test Connection                                                     */
    /* ------------------------------------------------------------------ */

    $('#dgw-test-connection').on('click', function () {
        var $btn = $(this);
        var $result = $('#dgw-test-result');

        $btn.prop('disabled', true).text('Testing...');
        $result.html('');

        $.post(dgwAdmin.ajaxUrl, {
            action: 'dgw_test_connection',
            nonce: dgwAdmin.nonce,
        })
        .done(function (res) {
            if (res.success) {
                $result.html('<span class="dgw-notice dgw-notice--success" style="display:inline-block">' + escHtml(res.data.message) + '</span>');
            } else {
                $result.html('<span class="dgw-notice dgw-notice--error" style="display:inline-block">' + escHtml(res.data.message || 'Connection failed') + '</span>');
            }
        })
        .fail(function () {
            $result.html('<span class="dgw-notice dgw-notice--error" style="display:inline-block">Network error. Could not reach server.</span>');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Test Connection');
        });
    });

    /* ------------------------------------------------------------------ */
    /*  Transactions Page                                                   */
    /* ------------------------------------------------------------------ */

    var txnPage = 1;

    window.dgwLoadTransactions = function (page) {
        page = page || 1;
        txnPage = page;

        var $wrap = $('#dgw-transactions-body');
        if (!$wrap.length) return;

        $wrap.html('<tr><td colspan="6"><div class="dgw-loading"><span class="dgw-spinner"></span> Loading transactions...</div></td></tr>');

        var params = { page: page, per_page: 20 };

        // Filters.
        var status = $('#dgw-filter-status').val();
        var provider = $('#dgw-filter-provider').val();
        var currency = $('#dgw-filter-currency').val();

        if (status) params.status = status;
        if (provider) params.provider = provider;
        if (currency) params.currency = currency;

        $.ajax({
            url: dgwAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgw_get_transactions',
                nonce: dgwAdmin.nonce,
                params: params,
            },
        })
        .done(function (res) {
            if (!res.success || !res.data.transactions) {
                $wrap.html('<tr><td colspan="6"><div class="dgw-empty"><p>No transactions found.</p></div></td></tr>');
                return;
            }

            var txns = res.data.transactions;
            var html = '';

            for (var i = 0; i < txns.length; i++) {
                var t = txns[i];
                var badgeClass = t.status === 'completed' ? 'dgw-badge-success' :
                                 t.status === 'failed' ? 'dgw-badge-danger' :
                                 t.status === 'pending' ? 'dgw-badge-warning' : '';
                html += '<tr>';
                html += '<td><code>' + escHtml(t.reference || '') + '</code></td>';
                html += '<td>' + escHtml(formatAmount(t.amount, t.currency)) + '</td>';
                html += '<td>' + escHtml(t.provider || '-') + '</td>';
                html += '<td><span class="dgw-badge ' + badgeClass + '">' + escHtml(t.status || '') + '</span></td>';
                html += '<td>' + escHtml(t.direction || '') + '</td>';
                html += '<td>' + escHtml(formatDate(t.created_at)) + '</td>';
                html += '</tr>';
            }

            $wrap.html(html);

            // Pagination.
            var total = res.data.total || 0;
            var totalPages = Math.ceil(total / 20);
            $('#dgw-txn-page-info').text('Page ' + txnPage + ' of ' + Math.max(1, totalPages));
            $('#dgw-txn-prev').prop('disabled', txnPage <= 1);
            $('#dgw-txn-next').prop('disabled', txnPage >= totalPages);
        })
        .fail(function () {
            $wrap.html('<tr><td colspan="6"><div class="dgw-notice dgw-notice--error">Failed to load transactions.</div></td></tr>');
        });
    };

    $(document).on('click', '#dgw-txn-prev', function () {
        if (txnPage > 1) dgwLoadTransactions(txnPage - 1);
    });

    $(document).on('click', '#dgw-txn-next', function () {
        dgwLoadTransactions(txnPage + 1);
    });

    $(document).on('change', '.dgw-filters select', function () {
        dgwLoadTransactions(1);
    });

    /* ------------------------------------------------------------------ */
    /*  Dashboard                                                           */
    /* ------------------------------------------------------------------ */

    window.dgwLoadDashboard = function () {
        // Load wallet balances.
        $.ajax({
            url: dgwAdmin.ajaxUrl,
            method: 'POST',
            data: { action: 'dgw_get_wallets', nonce: dgwAdmin.nonce },
        })
        .done(function (res) {
            if (res.success && res.data.wallets) {
                var wallets = res.data.wallets;
                var html = '';
                for (var i = 0; i < wallets.length; i++) {
                    var w = wallets[i];
                    html += '<div class="dgw-card">';
                    html += '<div class="dgw-card-label">' + escHtml(w.provider || 'Provider') + '</div>';
                    html += '<div class="dgw-card-value">' + escHtml(formatAmount(w.balance, w.currency)) + '</div>';
                    html += '<div class="dgw-card-sub">' + escHtml(w.currency || '') + '</div>';
                    html += '</div>';
                }
                $('#dgw-wallet-cards').html(html);
            }
        });

        // Load recent transactions.
        $.ajax({
            url: dgwAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgw_get_transactions',
                nonce: dgwAdmin.nonce,
                params: { page: 1, per_page: 10 },
            },
        })
        .done(function (res) {
            if (!res.success || !res.data.transactions || res.data.transactions.length === 0) {
                $('#dgw-recent-transactions').html('<div class="dgw-empty"><p>No recent transactions.</p></div>');
                return;
            }

            var txns = res.data.transactions;
            var html = '<table class="dgw-table"><thead><tr><th>Reference</th><th>Amount</th><th>Provider</th><th>Status</th><th>Date</th></tr></thead><tbody>';

            for (var i = 0; i < txns.length; i++) {
                var t = txns[i];
                var badgeClass = t.status === 'completed' ? 'dgw-badge-success' :
                                 t.status === 'failed' ? 'dgw-badge-danger' : 'dgw-badge-warning';
                html += '<tr>';
                html += '<td><code>' + escHtml(t.reference || '') + '</code></td>';
                html += '<td>' + escHtml(formatAmount(t.amount, t.currency)) + '</td>';
                html += '<td>' + escHtml(t.provider || '-') + '</td>';
                html += '<td><span class="dgw-badge ' + badgeClass + '">' + escHtml(t.status || '') + '</span></td>';
                html += '<td>' + escHtml(formatDate(t.created_at)) + '</td>';
                html += '</tr>';
            }

            html += '</tbody></table>';
            $('#dgw-recent-transactions').html(html);
        });
    };

    /* ------------------------------------------------------------------ */
    /*  Withdrawals Page                                                    */
    /* ------------------------------------------------------------------ */

    window.dgwLoadWithdrawals = function () {
        var $body = $('#dgw-withdrawals-body');
        if (!$body.length) return;

        $body.html('<tr><td colspan="6"><div class="dgw-loading"><span class="dgw-spinner"></span> Loading withdrawals...</div></td></tr>');

        $.ajax({
            url: dgwAdmin.ajaxUrl,
            method: 'POST',
            data: { action: 'dgw_get_withdrawals', nonce: dgwAdmin.nonce },
        })
        .done(function (res) {
            if (!res.success || !res.data.withdrawals || res.data.withdrawals.length === 0) {
                $body.html('<tr><td colspan="6"><div class="dgw-empty"><p>No withdrawals yet.</p></div></td></tr>');
                return;
            }

            var items = res.data.withdrawals;
            var html = '';

            for (var i = 0; i < items.length; i++) {
                var w = items[i];
                var badgeClass = w.status === 'completed' ? 'dgw-badge-success' :
                                 w.status === 'failed' ? 'dgw-badge-danger' :
                                 w.status === 'pending' ? 'dgw-badge-warning' : '';
                html += '<tr>';
                html += '<td>' + escHtml(formatAmount(w.amount, w.currency)) + '</td>';
                html += '<td>' + escHtml(w.method || '-') + '</td>';
                html += '<td>' + escHtml(w.destination || '-') + '</td>';
                html += '<td><span class="dgw-badge ' + badgeClass + '">' + escHtml(w.status || '') + '</span></td>';
                html += '<td>' + escHtml(formatDate(w.created_at)) + '</td>';
                html += '</tr>';
            }

            $body.html(html);
        })
        .fail(function () {
            $body.html('<tr><td colspan="6"><div class="dgw-notice dgw-notice--error">Failed to load withdrawals.</div></td></tr>');
        });

        // Load balances for withdrawal form.
        $.ajax({
            url: dgwAdmin.ajaxUrl,
            method: 'POST',
            data: { action: 'dgw_get_wallets', nonce: dgwAdmin.nonce },
        })
        .done(function (res) {
            if (res.success && res.data.wallets) {
                var $select = $('#dgw-wd-provider');
                $select.find('option:not(:first)').remove();
                for (var i = 0; i < res.data.wallets.length; i++) {
                    var w = res.data.wallets[i];
                    $select.append('<option value="' + escHtml(w.provider) + '" data-currency="' + escHtml(w.currency) + '" data-balance="' + w.balance + '">' +
                        escHtml(w.provider) + ' (' + escHtml(formatAmount(w.balance, w.currency)) + ' available)' +
                    '</option>');
                }
            }
        });
    };

    // Provider selection updates currency.
    $(document).on('change', '#dgw-wd-provider', function () {
        var $selected = $(this).find(':selected');
        var currency = $selected.data('currency');
        if (currency) {
            $('#dgw-wd-currency').val(currency);
        }
    });

    // Withdrawal form submit.
    $(document).on('submit', '#dgw-withdrawal-form', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');
        var $result = $('#dgw-wd-result');

        $btn.prop('disabled', true).text('Processing...');
        $result.html('');

        $.ajax({
            url: dgwAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgw_create_withdrawal',
                nonce: dgwAdmin.nonce,
                amount: $form.find('[name="amount"]').val(),
                currency: $form.find('[name="currency"]').val(),
                method: $form.find('[name="method"]').val(),
                destination: $form.find('[name="destination"]').val(),
                destination_name: $form.find('[name="destination_name"]').val(),
                note: $form.find('[name="note"]').val(),
            },
        })
        .done(function (res) {
            if (res.success) {
                $result.html('<div class="dgw-notice dgw-notice--success">Withdrawal request submitted successfully.</div>');
                $form[0].reset();
                dgwLoadWithdrawals();
            } else {
                $result.html('<div class="dgw-notice dgw-notice--error">' + escHtml(res.data.message || 'Failed to create withdrawal') + '</div>');
            }
        })
        .fail(function () {
            $result.html('<div class="dgw-notice dgw-notice--error">Network error.</div>');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Submit Withdrawal');
        });
    });

    /* ------------------------------------------------------------------ */
    /*  Subscriptions Page                                                   */
    /* ------------------------------------------------------------------ */

    window.dgwLoadPlans = function () {
        var $body = $('#dgw-plans-body');
        if (!$body.length) return;

        $body.html('<tr><td colspan="4"><div class="dgw-loading"><span class="dgw-spinner"></span> Loading...</div></td></tr>');

        $.ajax({
            url: dgwAdmin.ajaxUrl,
            method: 'POST',
            data: { action: 'dgw_get_plans', nonce: dgwAdmin.nonce },
        })
        .done(function (res) {
            if (!res.success || !res.data.plans || res.data.plans.length === 0) {
                $body.html('<tr><td colspan="4"><div class="dgw-empty"><p>No subscription plans.</p></div></td></tr>');
                return;
            }

            var plans = res.data.plans;
            var html = '';

            for (var i = 0; i < plans.length; i++) {
                var p = plans[i];
                var statusBadge = p.is_active ?
                    '<span class="dgw-badge dgw-badge-success">Active</span>' :
                    '<span class="dgw-badge dgw-badge-danger">Inactive</span>';
                html += '<tr>';
                html += '<td>' + escHtml(p.name || '') + '</td>';
                html += '<td>' + escHtml(formatAmount(p.amount, p.currency)) + '</td>';
                html += '<td>' + escHtml(p.interval || '') + '</td>';
                html += '<td>' + statusBadge + '</td>';
                html += '</tr>';
            }

            $body.html(html);
        })
        .fail(function () {
            $body.html('<tr><td colspan="4"><div class="dgw-notice dgw-notice--error">Failed to load plans.</div></td></tr>');
        });
    };

    window.dgwLoadSubscriptions = function () {
        var $body = $('#dgw-subscriptions-body');
        if (!$body.length) return;

        $body.html('<tr><td colspan="5"><div class="dgw-loading"><span class="dgw-spinner"></span> Loading...</div></td></tr>');

        var params = {};
        var status = $('#dgw-sub-filter-status').val();
        if (status) params.status = status;

        $.ajax({
            url: dgwAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgw_get_subscriptions',
                nonce: dgwAdmin.nonce,
                params: params,
            },
        })
        .done(function (res) {
            if (!res.success || !res.data.subscriptions || res.data.subscriptions.length === 0) {
                $body.html('<tr><td colspan="5"><div class="dgw-empty"><p>No subscriptions found.</p></div></td></tr>');
                return;
            }

            var subs = res.data.subscriptions;
            var html = '';

            for (var i = 0; i < subs.length; i++) {
                var s = subs[i];
                var badgeClass = s.status === 'active' ? 'dgw-badge-success' :
                                 s.status === 'cancelled' ? 'dgw-badge-danger' :
                                 s.status === 'past_due' ? 'dgw-badge-danger' :
                                 s.status === 'trialing' ? 'dgw-badge-info' :
                                 'dgw-badge-warning';
                var planName = s.plan ? s.plan.name : '-';
                html += '<tr>';
                html += '<td><code>' + escHtml(s.reference || '') + '</code></td>';
                html += '<td>' + escHtml(s.customer_email || '') + '</td>';
                html += '<td>' + escHtml(planName) + '</td>';
                html += '<td><span class="dgw-badge ' + badgeClass + '">' + escHtml(s.status || '') + '</span></td>';
                html += '<td>' + escHtml(s.next_due_date ? formatDate(s.next_due_date) : '-') + '</td>';
                html += '</tr>';
            }

            $body.html(html);
        })
        .fail(function () {
            $body.html('<tr><td colspan="5"><div class="dgw-notice dgw-notice--error">Failed to load subscriptions.</div></td></tr>');
        });
    };

    $(document).on('change', '#dgw-sub-filter-status', function () {
        dgwLoadSubscriptions();
    });

    /* ------------------------------------------------------------------ */
    /*  Helpers                                                             */
    /* ------------------------------------------------------------------ */

    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function formatAmount(amount, currency) {
        if (amount == null) return '-';
        var num = parseFloat(amount);
        return (currency || '') + ' ' + num.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        var d = new Date(dateStr);
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

})(jQuery);
