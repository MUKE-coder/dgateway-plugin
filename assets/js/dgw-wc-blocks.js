/**
 * DGateway WooCommerce Blocks payment method registration.
 *
 * v1.1: honours the per-rail Mobile Money / Card toggles and the
 * Stripe FX rate configured in WooCommerce → Settings → Payments →
 * DGateway. When the shop is priced in UGX (or any non-Stripe-
 * supported currency) and Card is enabled, we show the buyer the
 * converted amount they'll see on their card statement before they
 * place the order — Stripe chargeback rates jump when the statement
 * surprises the cardholder.
 */
(function () {
    'use strict';

    var wc = window.wc || {};
    var wp = window.wp || {};

    var registerPaymentMethod = wc.wcBlocksRegistry && wc.wcBlocksRegistry.registerPaymentMethod;
    var createElement = wp.element && wp.element.createElement;
    var useState = wp.element && wp.element.useState;
    var useEffect = wp.element && wp.element.useEffect;
    var getSetting = wc.wcSettings && wc.wcSettings.getSetting;

    if (!registerPaymentMethod || !createElement) {
        console.error('[DGateway] Missing globals - registerPaymentMethod:', !!registerPaymentMethod, 'createElement:', !!createElement);
        return;
    }

    var settings = getSetting ? (getSetting('dgateway_data', null) || getSetting('dgateway', {})) : {};
    var title = (settings && settings.title) || 'DGateway Payment';
    var desc  = (settings && settings.description) || 'Pay with Mobile Money or Card';

    // New in v1.1: rails are individually toggleable. enable_card is
    // ALREADY gated server-side by the "do we have an exchange rate
    // OR is the shop already in a Stripe currency" check, so we trust
    // the flag without re-deriving here.
    var enableMomo = settings.enable_momo !== false;            // default on
    var enableCard = settings.enable_card === true;             // default off
    var shopCurrency = (settings.shop_currency || 'UGX').toUpperCase();
    var stripeCurrency = (settings.stripe_currency || 'USD').toUpperCase();
    var exchangeRate = parseFloat(settings.exchange_rate || 0);

    /* ------------------------------------------------------------------ */
    /* Styles                                                               */
    /* ------------------------------------------------------------------ */
    var styles = {
        tabWrap: { display: 'flex', gap: '10px', marginBottom: '16px' },
        tabActive: {
            flex: 1, padding: '12px 16px', border: 'none', borderRadius: '10px',
            background: 'linear-gradient(135deg, #4f46e5, #7c3aed)', cursor: 'pointer',
            fontSize: '14px', fontWeight: 700, color: '#fff',
            boxShadow: '0 4px 14px rgba(79,70,229,0.35)',
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px',
        },
        tabInactive: {
            flex: 1, padding: '12px 16px', border: '2px solid #e5e7eb', borderRadius: '10px',
            background: '#f9fafb', cursor: 'pointer', fontSize: '14px', fontWeight: 600,
            color: '#6b7280', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '8px',
        },
        fieldWrap: { background: '#f9fafb', border: '1px solid #e5e7eb', borderRadius: '12px', padding: '16px' },
        label: {
            display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '8px',
            fontWeight: 700, fontSize: '13px', color: '#374151', textTransform: 'uppercase',
        },
        input: {
            width: '100%', padding: '12px 14px', border: '2px solid #e5e7eb', borderRadius: '8px',
            fontSize: '15px', color: '#111827', background: '#fff', outline: 'none', boxSizing: 'border-box',
        },
        hint: { marginTop: '8px', fontSize: '12px', color: '#9ca3af', display: 'flex', alignItems: 'center', gap: '4px' },
        cardNotice: {
            background: '#f0f9ff', border: '1px solid #bae6fd', borderRadius: '10px',
            padding: '14px 16px', color: '#0369a1', fontSize: '13px', lineHeight: '1.6',
            display: 'flex', alignItems: 'flex-start', gap: '10px',
        },
        cardChargedLine: { marginBottom: '8px', fontWeight: 600, color: '#0c4a6e' },
        cardChargedSub:  { fontWeight: 400, fontSize: '12px', color: '#0369a1', marginTop: '4px' },
    };

    /* ------------------------------------------------------------------ */
    /* Helpers                                                              */
    /* ------------------------------------------------------------------ */

    /** Format `amt` in `currency` — whole units for UGX/TZS/RWF, 2dp elsewhere. */
    function fmtAmount(amt, currency) {
        if (amt == null) return currency + ' —';
        var isWholeCcy = currency === 'UGX' || currency === 'TZS' || currency === 'RWF';
        var rounded = isWholeCcy ? Math.round(amt) : Math.round(amt * 100) / 100;
        var parts = rounded.toFixed(isWholeCcy ? 0 : 2).split('.');
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return currency + ' ' + parts.join('.');
    }

    /* ------------------------------------------------------------------ */
    /* Content component                                                    */
    /* ------------------------------------------------------------------ */
    function Content(props) {
        var evtReg = (props && props.eventRegistration) || {};
        var emitResp = (props && props.emitResponse) || {};
        var billing = (props && props.billing) || {};

        // Default to whichever rail is enabled. If both, default to MoMo.
        var initialMethod = enableMomo ? 'momo' : (enableCard ? 'card' : 'momo');
        var s1 = useState(initialMethod);
        var payMethod = s1[0], setPayMethod = s1[1];
        var s2 = useState('');
        var phone = s2[0], setPhone = s2[1];

        // Cart total arrives in integer minor units (cents). Convert
        // to major units for display.
        var cartTotal = 0;
        if (billing.cartTotal && typeof billing.cartTotal.value !== 'undefined') {
            cartTotal = parseInt(billing.cartTotal.value, 10) / 100;
        }
        var needsFx = enableCard && shopCurrency !== stripeCurrency && exchangeRate > 0;
        var stripeAmount = needsFx ? cartTotal / exchangeRate : cartTotal;

        if (useEffect) {
            useEffect(function () {
                if (!evtReg.onPaymentProcessing) return;
                var unsub = evtReg.onPaymentProcessing(function () {
                    if (payMethod === 'momo' && (!phone || phone.length < 9)) {
                        return {
                            type: emitResp.responseTypes.ERROR,
                            message: 'Please enter a valid phone number for Mobile Money payment.',
                        };
                    }
                    return {
                        type: emitResp.responseTypes.SUCCESS,
                        meta: {
                            paymentMethodData: {
                                dgw_payment_method: payMethod,
                                dgw_phone: phone,
                            },
                        },
                    };
                });
                return unsub;
            }, [payMethod, phone]);
        }

        // Tab strip only when BOTH rails are enabled — single-rail
        // configurations skip the tabs entirely so the buyer doesn't
        // see a useless one-option strip.
        var tabRow = null;
        if (enableMomo && enableCard) {
            tabRow = createElement('div', { style: styles.tabWrap },
                createElement('button', {
                    type: 'button',
                    style: payMethod === 'momo' ? styles.tabActive : styles.tabInactive,
                    onClick: function () { setPayMethod('momo'); },
                }, '📱 Mobile Money'),
                createElement('button', {
                    type: 'button',
                    style: payMethod === 'card' ? styles.tabActive : styles.tabInactive,
                    onClick: function () { setPayMethod('card'); },
                }, '💳 Card Payment')
            );
        }

        var momoBody = (enableMomo && payMethod === 'momo')
            ? createElement('div', { style: styles.fieldWrap },
                createElement('label', { htmlFor: 'dgw_phone_blocks', style: styles.label }, '📞 Phone Number'),
                createElement('input', {
                    type: 'tel', id: 'dgw_phone_blocks', placeholder: 'e.g. 256771234567',
                    value: phone, onChange: function (e) { setPhone(e.target.value); }, style: styles.input,
                }),
                createElement('p', { style: styles.hint }, 'ℹ️ Include country code — e.g. 256771234567 for Uganda')
            )
            : null;

        // Card body — when needsFx is true, show the explicit charged
        // amount in the Stripe settlement currency. This is the bit
        // that fixes "buyer sees mobile-money UI after picking Card"
        // by communicating clearly what the next step charges them.
        var cardBody = (enableCard && payMethod === 'card')
            ? createElement('div', { style: styles.cardNotice },
                createElement('span', { style: { fontSize: '20px' } }, '🔒'),
                createElement('div', null,
                    (needsFx && cartTotal > 0)
                        ? createElement('div', { style: styles.cardChargedLine },
                            'Charged on your card: ' + fmtAmount(stripeAmount, stripeCurrency),
                            createElement('div', { style: styles.cardChargedSub },
                                '≈ ' + fmtAmount(cartTotal, shopCurrency) + ' at ' + shopCurrency + ' ' + Math.round(exchangeRate).toLocaleString() + ' per 1 ' + stripeCurrency
                            )
                          )
                        : null,
                    createElement('span', null, 'Your card details will be collected securely on the next step — your card data never touches this site.')
                )
            )
            : null;

        return createElement('div', { style: { fontFamily: 'inherit' } },
            desc ? createElement('p', { style: { margin: '0 0 16px', color: '#6b7280', fontSize: '13px' } }, desc) : null,
            tabRow,
            momoBody,
            cardBody
        );
    }

    /* ------------------------------------------------------------------ */
    /* Register                                                             */
    /* ------------------------------------------------------------------ */
    try {
        registerPaymentMethod({
            name: 'dgateway',
            label: createElement('span', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, '💳', title),
            content: createElement(Content),
            edit: createElement('div', null, title),
            // Hide the method entirely when both rails are off — the
            // gateway is registered but the buyer should never see it
            // as a pickable option.
            canMakePayment: function () { return enableMomo || enableCard; },
            ariaLabel: title,
            supports: { features: settings.supports || ['products'] },
        });
        console.log('[DGateway] Payment method registered OK', { enableMomo: enableMomo, enableCard: enableCard, needsFx: shopCurrency !== stripeCurrency });
    } catch (e) {
        console.error('[DGateway] Registration error:', e);
    }
})();
