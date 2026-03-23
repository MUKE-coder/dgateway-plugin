/**
 * DGateway WooCommerce Blocks payment method registration.
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
    };

    /* ------------------------------------------------------------------ */
    /* Content component                                                    */
    /* ------------------------------------------------------------------ */
    function Content(props) {
        var evtReg = (props && props.eventRegistration) || {};
        var emitResp = (props && props.emitResponse) || {};

        var s1 = useState('momo');
        var payMethod = s1[0], setPayMethod = s1[1];
        var s2 = useState('');
        var phone = s2[0], setPhone = s2[1];

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

        return createElement('div', { style: { fontFamily: 'inherit' } },
            desc ? createElement('p', { style: { margin: '0 0 16px', color: '#6b7280', fontSize: '13px' } }, desc) : null,
            createElement('div', { style: styles.tabWrap },
                createElement('button', {
                    type: 'button',
                    style: payMethod === 'momo' ? styles.tabActive : styles.tabInactive,
                    onClick: function () { setPayMethod('momo'); },
                }, '\uD83D\uDCF1 Mobile Money'),
                createElement('button', {
                    type: 'button',
                    style: payMethod === 'card' ? styles.tabActive : styles.tabInactive,
                    onClick: function () { setPayMethod('card'); },
                }, '\uD83D\uDCB3 Card Payment')
            ),
            payMethod === 'momo'
                ? createElement('div', { style: styles.fieldWrap },
                    createElement('label', { htmlFor: 'dgw_phone_blocks', style: styles.label }, '\uD83D\uDCDE Phone Number'),
                    createElement('input', {
                        type: 'tel', id: 'dgw_phone_blocks', placeholder: 'e.g. 256771234567',
                        value: phone, onChange: function (e) { setPhone(e.target.value); }, style: styles.input,
                    }),
                    createElement('p', { style: styles.hint }, '\u2139\uFE0F Include country code — e.g. 256771234567 for Uganda')
                )
                : null,
            payMethod === 'card'
                ? createElement('div', { style: styles.cardNotice },
                    createElement('span', { style: { fontSize: '20px' } }, '\uD83D\uDD12'),
                    createElement('span', null, 'Your card details will be collected securely on the next step after placing your order.')
                )
                : null
        );
    }

    /* ------------------------------------------------------------------ */
    /* Register                                                             */
    /* ------------------------------------------------------------------ */
    try {
        registerPaymentMethod({
            name: 'dgateway',
            label: createElement('span', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, '\uD83D\uDCB3', title),
            content: createElement(Content),
            edit: createElement('div', null, title),
            canMakePayment: function () { return true; },
            ariaLabel: title,
            supports: { features: settings.supports || ['products'] },
        });
        console.log('[DGateway] Payment method registered OK');
    } catch (e) {
        console.error('[DGateway] Registration error:', e);
    }
})();
