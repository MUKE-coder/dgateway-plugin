/**
 * DGateway WooCommerce Blocks payment method registration.
 * Uses vanilla JS with WP/WC globals — no build step required.
 */
(function () {
    'use strict';

    if (!window.wc || !window.wc.blocksRegistry) return;
    if (!window.wp || !window.wp.element) return;

    var blocksRegistry  = window.wc.blocksRegistry;
    var wcSettings      = window.wc.wcSettings;
    var wpElement       = window.wp.element;
    var wpHtmlEntities  = window.wp.htmlEntities;

    var createElement   = wpElement.createElement;
    var useState        = wpElement.useState;
    var useEffect       = wpElement.useEffect;
    var decodeEntities  = wpHtmlEntities.decodeEntities;

    var settings    = wcSettings.getSetting('dgateway_data', {});
    var label       = decodeEntities(settings.title || 'DGateway Payment');
    var description = decodeEntities(settings.description || '');

    /* ------------------------------------------------------------------ */
    /* Tab button style helper                                              */
    /* ------------------------------------------------------------------ */
    function tabStyle(active) {
        return {
            flex: 1,
            padding: '10px',
            border: '2px solid ' + (active ? '#4f46e5' : '#e0e0e0'),
            borderRadius: '8px',
            background: active ? '#eef2ff' : '#fff',
            cursor: 'pointer',
            fontSize: '13px',
            fontWeight: 600,
            color: active ? '#4f46e5' : 'inherit',
            transition: 'all 0.15s',
        };
    }

    /* ------------------------------------------------------------------ */
    /* Payment form component                                               */
    /* ------------------------------------------------------------------ */
    function DGatewayContent(props) {
        var eventRegistration = props.eventRegistration;
        var emitResponse      = props.emitResponse;

        var payMethodState = useState('momo');
        var payMethod      = payMethodState[0];
        var setPayMethod   = payMethodState[1];

        var phoneState = useState('');
        var phone      = phoneState[0];
        var setPhone   = phoneState[1];

        useEffect(function () {
            var unsubscribe = eventRegistration.onPaymentProcessing(function () {
                if (payMethod === 'momo' && (!phone || phone.length < 9)) {
                    return {
                        type: emitResponse.responseTypes.ERROR,
                        message: 'Please enter a valid phone number for Mobile Money payment.',
                    };
                }
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            dgw_payment_method: payMethod,
                            dgw_phone: phone,
                        },
                    },
                };
            });
            return unsubscribe;
        }, [payMethod, phone, eventRegistration.onPaymentProcessing, emitResponse.responseTypes]);

        return createElement(
            'div',
            { id: 'dgw-wc-payment-fields' },

            /* Description */
            description
                ? createElement('p', { style: { marginBottom: '12px', color: '#555' } }, description)
                : null,

            /* Tab bar */
            createElement(
                'div',
                { style: { display: 'flex', gap: '8px', marginBottom: '16px' } },
                createElement(
                    'button',
                    {
                        type: 'button',
                        style: tabStyle(payMethod === 'momo'),
                        onClick: function () { setPayMethod('momo'); },
                    },
                    '\uD83D\uDCF1 Mobile Money'
                ),
                createElement(
                    'button',
                    {
                        type: 'button',
                        style: tabStyle(payMethod === 'card'),
                        onClick: function () { setPayMethod('card'); },
                    },
                    '\uD83D\uDCB3 Card'
                )
            ),

            /* Mobile Money fields */
            payMethod === 'momo'
                ? createElement(
                    'div',
                    null,
                    createElement(
                        'label',
                        {
                            htmlFor: 'dgw_phone_blocks',
                            style: { display: 'block', marginBottom: '4px', fontWeight: 600, fontSize: '13px' },
                        },
                        'Phone Number'
                    ),
                    createElement('input', {
                        type: 'tel',
                        id: 'dgw_phone_blocks',
                        className: 'input-text',
                        placeholder: 'e.g. 256771234567',
                        value: phone,
                        onChange: function (e) { setPhone(e.target.value); },
                        style: { width: '100%', marginBottom: '4px' },
                    }),
                    createElement(
                        'small',
                        { style: { color: '#888', display: 'block', marginTop: '4px' } },
                        'Enter with country code (e.g. 256 for Uganda)'
                    )
                )
                : null,

            /* Card notice */
            payMethod === 'card'
                ? createElement(
                    'p',
                    { style: { color: '#555', fontSize: '13px' } },
                    'You will be prompted to enter your card details after placing the order.'
                )
                : null
        );
    }

    /* ------------------------------------------------------------------ */
    /* Register the payment method                                          */
    /* ------------------------------------------------------------------ */
    blocksRegistry.registerPaymentMethod({
        name: 'dgateway',
        label: label,
        content: createElement(DGatewayContent, null),
        edit: createElement(
            'div',
            { style: { padding: '12px', border: '1px solid #e0e0e0', borderRadius: '8px', fontSize: '13px' } },
            label + ' — Mobile Money & Card'
        ),
        canMakePayment: function () { return true; },
        ariaLabel: label,
        supports: {
            features: settings.supports || ['products'],
        },
    });
})();
