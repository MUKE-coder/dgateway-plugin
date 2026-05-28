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

        // Default cadence mirrors the DGateway dashboard waiting screen:
        // 6 polls over a 120s window with intervals 10, 10, 10, 20, 30, 40
        // (cumulative 10, 20, 30, 50, 80, 120). Fast at the start to
        // catch the common "buyer approved in 5s" case, then backed off
        // so we don't hammer the API while the buyer hunts for their PIN.
        POLL_INTERVALS: [10, 10, 10, 20, 30, 40],
        TOTAL_WINDOW_SECONDS: 120,

        /**
         * Poll the verify endpoint following the smart cadence above.
         * Returns a controller object the caller can use to cancel
         * (e.g. when the buyer clicks "Cancel Payment" in the modal).
         *
         * @param {string}   reference
         * @param {Function} onSuccess  Called with { reference, status }
         * @param {Function} onFailed   Called with error message
         * @param {Object}   [opts]
         * @param {Array<number>} [opts.intervals]  Override seconds between polls
         * @param {number}   [opts.windowSeconds]   Override total window
         * @param {Function} [opts.onTick]          Called every 1s with { remaining, nextCheckIn, attempt, checking }
         * @returns {{ cancel: Function, checkNow: Function }}
         */
        pollStatus: function (reference, onSuccess, onFailed, opts) {
            opts = opts || {};
            var intervals = opts.intervals || this.POLL_INTERVALS;
            var windowSeconds = opts.windowSeconds || this.TOTAL_WINDOW_SECONDS;
            var onTick = typeof opts.onTick === 'function' ? opts.onTick : null;

            var self = this;
            var stopped = false;
            var checking = false;
            var pollIdx = 0;
            var elapsed = 0;
            var nextCheckIn = intervals[0];
            var pollTimer = null;
            var tickTimer = null;

            function stopAll() {
                stopped = true;
                if (pollTimer) clearTimeout(pollTimer);
                if (tickTimer) clearInterval(tickTimer);
                pollTimer = null;
                tickTimer = null;
            }

            function fireTick() {
                if (!onTick) return;
                onTick({
                    remaining: Math.max(0, windowSeconds - elapsed),
                    nextCheckIn: nextCheckIn,
                    attempt: pollIdx + 1,
                    totalAttempts: intervals.length,
                    checking: checking,
                });
            }

            function checkOnce() {
                if (stopped || checking) return Promise.resolve(false);
                checking = true;
                fireTick();
                return fetch(dgwCheckout.restUrl + 'verify', {
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
                        stopAll();
                        onSuccess({ reference: reference, status: 'completed' });
                        return true;
                    }
                    if (status === 'failed') {
                        stopAll();
                        onFailed('Payment failed. Please try again.');
                        return true;
                    }
                    return false;
                })
                .catch(function () { return false; })
                .then(function (done) {
                    checking = false;
                    fireTick();
                    return done;
                });
            }

            function schedulePoll() {
                if (stopped || pollIdx >= intervals.length) return;
                nextCheckIn = intervals[pollIdx];
                pollTimer = setTimeout(function () {
                    checkOnce().then(function (done) {
                        if (done) return;
                        pollIdx += 1;
                        schedulePoll();
                    });
                }, nextCheckIn * 1000);
            }

            // 1Hz wall-clock for the countdown + timeout.
            tickTimer = setInterval(function () {
                if (stopped) return;
                elapsed += 1;
                nextCheckIn = Math.max(0, nextCheckIn - 1);
                fireTick();
                if (elapsed >= windowSeconds) {
                    stopAll();
                    onFailed('Payment verification timed out after ' + windowSeconds + ' seconds. If you approved on your phone late, give it a moment — we will email a receipt if it goes through.');
                }
            }, 1000);

            schedulePoll();
            fireTick();

            return {
                cancel: function () { stopAll(); },
                checkNow: function () { return checkOnce(); },
            };
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
