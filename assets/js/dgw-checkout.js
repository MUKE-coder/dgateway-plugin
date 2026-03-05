/**
 * DGateway Checkout JS
 *
 * Core payment logic for Mobile Money + Stripe card payments.
 * Expects `dgwCheckout` to be localized:
 *   { restUrl, nonce }
 */
(function () {
    'use strict';

    var DGW_Checkout = {

        /* ------------------------------------------------------------ */
        /*  Create Payment                                               */
        /* ------------------------------------------------------------ */

        /**
         * Initiate a payment via the WP REST proxy.
         *
         * @param {Object} opts
         * @param {string} opts.provider      "iotec" or "stripe"
         * @param {number} opts.amount
         * @param {string} opts.currency
         * @param {string} opts.phone_number  Required for MoMo
         * @param {string} opts.description
         * @param {Object} opts.metadata      Optional
         * @returns {Promise<Object>}
         */
        createPayment: function (opts) {
            return fetch(dgwCheckout.restUrl + 'checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': dgwCheckout.nonce,
                },
                body: JSON.stringify({
                    amount: opts.amount,
                    currency: opts.currency,
                    phone_number: opts.phone_number || '0000000000',
                    provider: opts.provider || '',
                    description: opts.description || '',
                    metadata: opts.metadata || {},
                }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data.error) {
                    throw new Error(data.error.message || 'Payment creation failed');
                }
                return data;
            });
        },

        /* ------------------------------------------------------------ */
        /*  Poll Status                                                   */
        /* ------------------------------------------------------------ */

        /**
         * Poll the verify endpoint until completed, failed, or timeout.
         *
         * @param {string}   reference
         * @param {Function} onSuccess  Called with { reference, status }
         * @param {Function} onFailed   Called with error message
         * @param {number}   maxAttempts Default 60
         * @param {number}   interval   Default 5000ms
         */
        pollStatus: function (reference, onSuccess, onFailed, maxAttempts, interval) {
            maxAttempts = maxAttempts || 60;
            interval = interval || 5000;
            var attempts = 0;

            var poll = function () {
                if (attempts >= maxAttempts) {
                    onFailed('Payment verification timed out. Please check your transaction history.');
                    return;
                }
                attempts++;

                fetch(dgwCheckout.restUrl + 'verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': dgwCheckout.nonce,
                    },
                    body: JSON.stringify({ reference: reference }),
                })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    var status = (data.data && data.data.status) || '';
                    if (status === 'completed') {
                        onSuccess({ reference: reference, status: 'completed' });
                        return;
                    }
                    if (status === 'failed') {
                        onFailed('Payment failed. Please try again.');
                        return;
                    }
                    setTimeout(poll, interval);
                })
                .catch(function () {
                    // Network error — keep polling.
                    setTimeout(poll, interval);
                });
            };

            // Start after a short initial delay.
            setTimeout(poll, 3000);
        },

        /* ------------------------------------------------------------ */
        /*  Stripe Helpers                                                */
        /* ------------------------------------------------------------ */

        /**
         * Dynamically load Stripe.js if not already loaded.
         *
         * @param {string} publishableKey
         * @returns {Promise<Stripe>}
         */
        loadStripe: function (publishableKey) {
            if (window.Stripe) {
                return Promise.resolve(window.Stripe(publishableKey));
            }

            return new Promise(function (resolve, reject) {
                var script = document.createElement('script');
                script.src = 'https://js.stripe.com/v3/';
                script.onload = function () {
                    if (window.Stripe) {
                        resolve(window.Stripe(publishableKey));
                    } else {
                        reject(new Error('Failed to load Stripe'));
                    }
                };
                script.onerror = function () {
                    reject(new Error('Failed to load Stripe.js'));
                };
                document.head.appendChild(script);
            });
        },

        /**
         * Mount Stripe PaymentElement into a container.
         *
         * @param {Stripe}  stripe
         * @param {string}  clientSecret
         * @param {Element} container  DOM element to mount into
         * @returns {Object} { elements, paymentElement }
         */
        mountStripeElements: function (stripe, clientSecret, container) {
            var elements = stripe.elements({
                clientSecret: clientSecret,
                appearance: {
                    theme: 'stripe',
                    variables: { colorPrimary: '#4f46e5' },
                },
            });

            var paymentElement = elements.create('payment');
            paymentElement.mount(container);

            return { elements: elements, paymentElement: paymentElement };
        },

        /**
         * Confirm a Stripe payment.
         *
         * @param {Stripe}          stripe
         * @param {StripeElements}  elements
         * @returns {Promise<Object>}  { paymentIntent } or throws on error
         */
        confirmStripePayment: function (stripe, elements) {
            return stripe.confirmPayment({
                elements: elements,
                redirect: 'if_required',
            }).then(function (result) {
                if (result.error) {
                    throw new Error(result.error.message || 'Card payment failed');
                }
                return result;
            });
        },

        /* ------------------------------------------------------------ */
        /*  Events                                                        */
        /* ------------------------------------------------------------ */

        /**
         * Fire a custom DOM event.
         *
         * @param {string} name    e.g. "dgw:payment:success"
         * @param {Object} detail  Event detail data
         */
        fireEvent: function (name, detail) {
            var event;
            if (typeof CustomEvent === 'function') {
                event = new CustomEvent(name, { detail: detail, bubbles: true });
            } else {
                event = document.createEvent('CustomEvent');
                event.initCustomEvent(name, true, true, detail);
            }
            document.dispatchEvent(event);
        },
    };

    // Expose globally.
    window.DGW_Checkout = DGW_Checkout;

})();
