/**
 * DGateway Modal JS
 *
 * Opens a modal overlay with payment form (Mobile Money + Card tabs).
 * Uses DGW_Checkout for payment logic.
 */
(function () {
    'use strict';

    var DGW_Modal = {

        _overlay: null,
        _state: 'idle', // idle, method_select, phone_input, creating, stripe_mount, processing, completed, failed
        _config: {},
        _reference: '',
        _stripe: null,
        _stripeElements: null,
        _error: '',

        /* ------------------------------------------------------------ */
        /*  Open Modal                                                    */
        /* ------------------------------------------------------------ */

        /**
         * Open the payment modal.
         *
         * @param {Object} config
         * @param {number}   config.amount
         * @param {string}   config.currency     Default: dgwCheckout.currency or 'UGX'
         * @param {string}   config.provider     "iotec", "stripe", or "" (show tabs)
         * @param {string}   config.description
         * @param {string}   config.successUrl   URL to redirect after success
         * @param {Function} config.onSuccess    Callback(reference)
         * @param {Function} config.onFailed     Callback(error)
         * @param {Function} config.onClose      Callback()
         */
        open: function (config) {
            this._config = config || {};
            this._state = config.provider ? 'phone_input' : 'method_select';
            this._reference = '';
            this._error = '';
            this._stripe = null;
            this._stripeElements = null;

            if (config.provider === 'stripe') {
                this._state = 'creating';
                this._initStripePayment();
            }

            this._render();
            this._show();
        },

        /* ------------------------------------------------------------ */
        /*  Close Modal                                                   */
        /* ------------------------------------------------------------ */

        close: function () {
            if (this._overlay) {
                this._overlay.remove();
                this._overlay = null;
            }
            if (this._config.onClose) {
                this._config.onClose();
            }
        },

        /* ------------------------------------------------------------ */
        /*  Internal: Show / Hide                                         */
        /* ------------------------------------------------------------ */

        _show: function () {
            if (!this._overlay) return;
            document.body.appendChild(this._overlay);
            document.body.style.overflow = 'hidden';
        },

        _hide: function () {
            document.body.style.overflow = '';
        },

        /* ------------------------------------------------------------ */
        /*  Internal: Render                                              */
        /* ------------------------------------------------------------ */

        _render: function () {
            var self = this;
            var cfg = this._config;
            var amount = cfg.amount || 0;
            var currency = cfg.currency || 'UGX';

            // Create overlay.
            if (this._overlay) this._overlay.remove();
            var overlay = document.createElement('div');
            overlay.className = 'dgw-modal-overlay';
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) self.close();
            });

            var modal = document.createElement('div');
            modal.className = 'dgw-modal';

            // Header.
            var title = cfg._isSubscribe ? 'Subscribe' : 'Payment';
            var logoUrl = (typeof dgwCheckout !== 'undefined' && dgwCheckout.logoUrl) ? dgwCheckout.logoUrl : '';
            var logoHtml = logoUrl ? '<img src="' + logoUrl + '" alt="DGateway" class="dgw-modal-logo" />' : '';
            var header = '<div class="dgw-modal-header">' +
                logoHtml +
                '<div class="dgw-modal-title">' + title + '</div>' +
                '<button class="dgw-modal-close" type="button">&times;</button>' +
                '</div>';

            // Amount display (hidden for subscribe modals).
            var amountDisplay = '';
            if (!cfg._isSubscribe) {
                amountDisplay = '<div class="dgw-modal-amount">' +
                    '<span class="dgw-modal-amount-label">Amount</span>' +
                    '<span class="dgw-modal-amount-value">' + currency + ' ' + this._formatNumber(amount) + '</span>' +
                    '</div>';
            }

            var body = '';

            switch (this._state) {
                case 'subscribe_info':
                    body = this._renderSubscribeInfo();
                    break;
                case 'method_select':
                    body = this._renderMethodSelect();
                    break;
                case 'phone_input':
                    body = this._renderPhoneInput();
                    break;
                case 'creating':
                    body = '<div class="dgw-modal-body"><div class="dgw-modal-processing">' +
                        '<div class="dgw-modal-spinner"></div>' +
                        '<p>Initiating payment...</p></div></div>';
                    break;
                case 'stripe_mount':
                    body = '<div class="dgw-modal-body">' +
                        '<div id="dgw-stripe-element"></div>' +
                        '<button type="button" class="dgw-modal-btn dgw-modal-btn-primary dgw-stripe-pay-btn" style="margin-top:16px;">Pay Now</button>' +
                        '</div>';
                    break;
                case 'processing':
                    var msg = (cfg.provider === 'iotec' || !cfg.provider) ?
                        'A payment prompt has been sent to your phone.<br>Please confirm on your phone.' :
                        'Processing your card payment...';
                    body = '<div class="dgw-modal-body"><div class="dgw-modal-processing">' +
                        '<div class="dgw-modal-spinner"></div>' +
                        '<p>' + msg + '</p>' +
                        '<p class="dgw-modal-ref">Ref: ' + this._escHtml(this._reference) + '</p>' +
                        '</div></div>';
                    break;
                case 'completed':
                    var completedTitle = cfg._completedTitle || 'Payment Successful!';
                    var completedMsg = cfg._completedMessage || ('Reference: ' + this._escHtml(this._reference));
                    body = '<div class="dgw-modal-body"><div class="dgw-modal-success">' +
                        '<div class="dgw-modal-check">&#10003;</div>' +
                        '<h3>' + this._escHtml(completedTitle) + '</h3>' +
                        '<p class="dgw-modal-ref">' + this._escHtml(completedMsg) + '</p>' +
                        '<button type="button" class="dgw-modal-btn dgw-modal-btn-primary dgw-modal-done-btn">Done</button>' +
                        '</div></div>';
                    break;
                case 'failed':
                    body = '<div class="dgw-modal-body"><div class="dgw-modal-failed">' +
                        '<div class="dgw-modal-x">&#10005;</div>' +
                        '<h3>Payment Failed</h3>' +
                        '<p>' + this._escHtml(this._error || 'Something went wrong.') + '</p>' +
                        '<button type="button" class="dgw-modal-btn dgw-modal-btn-retry">Try Again</button>' +
                        '</div></div>';
                    break;
            }

            // Error display.
            var errorHtml = this._error && this._state !== 'failed' ?
                '<div class="dgw-modal-error">' + this._escHtml(this._error) + '</div>' : '';

            modal.innerHTML = header + amountDisplay + errorHtml + body;
            overlay.appendChild(modal);
            this._overlay = overlay;

            // Bind events.
            this._bindEvents();

            // If stripe_mount, mount the element after DOM insertion.
            if (this._state === 'stripe_mount') {
                setTimeout(function () { self._mountStripeAfterRender(); }, 50);
            }
        },

        /* ------------------------------------------------------------ */
        /*  Internal: Render Parts                                        */
        /* ------------------------------------------------------------ */

        _renderMethodSelect: function () {
            return '<div class="dgw-modal-body">' +
                '<div class="dgw-modal-methods">' +
                    '<button type="button" class="dgw-modal-method" data-method="iotec">' +
                        '<span class="dgw-modal-method-icon">&#128241;</span>' +
                        '<span class="dgw-modal-method-title">Mobile Money</span>' +
                        '<span class="dgw-modal-method-desc">Pay via mobile money</span>' +
                    '</button>' +
                    '<button type="button" class="dgw-modal-method" data-method="stripe">' +
                        '<span class="dgw-modal-method-icon">&#128179;</span>' +
                        '<span class="dgw-modal-method-title">Card Payment</span>' +
                        '<span class="dgw-modal-method-desc">Pay with credit/debit card</span>' +
                    '</button>' +
                '</div>' +
            '</div>';
        },

        _renderPhoneInput: function () {
            return '<div class="dgw-modal-body">' +
                '<form class="dgw-modal-phone-form">' +
                    '<label class="dgw-modal-label">Phone Number</label>' +
                    '<input type="tel" class="dgw-modal-input" placeholder="e.g. 256771234567" name="phone" required />' +
                    '<p class="dgw-modal-hint">Enter with country code (e.g. 256 for Uganda)</p>' +
                    '<button type="submit" class="dgw-modal-btn dgw-modal-btn-primary">Pay with Mobile Money</button>' +
                '</form>' +
            '</div>';
        },

        /* ------------------------------------------------------------ */
        /*  Internal: Bind Events                                         */
        /* ------------------------------------------------------------ */

        _bindEvents: function () {
            var self = this;
            var overlay = this._overlay;
            if (!overlay) return;

            // Close button.
            var closeBtn = overlay.querySelector('.dgw-modal-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () { self.close(); });
            }

            // Method select.
            var methodBtns = overlay.querySelectorAll('.dgw-modal-method');
            for (var i = 0; i < methodBtns.length; i++) {
                methodBtns[i].addEventListener('click', function () {
                    var method = this.getAttribute('data-method');
                    self._config.provider = method;
                    if (method === 'stripe') {
                        self._state = 'creating';
                        self._render();
                        self._show();
                        self._initStripePayment();
                    } else {
                        self._state = 'phone_input';
                        self._render();
                        self._show();
                    }
                });
            }

            // Subscribe form submit.
            var subForm = overlay.querySelector('.dgw-modal-subscribe-form');
            if (subForm) {
                subForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var name = subForm.querySelector('input[name="customer_name"]').value.trim();
                    var email = subForm.querySelector('input[name="customer_email"]').value.trim();
                    var phone = subForm.querySelector('input[name="customer_phone"]').value.trim();
                    if (!name || !email) {
                        self._error = 'Name and email are required.';
                        self._render();
                        self._show();
                        return;
                    }
                    self._initSubscribe(name, email, phone);
                });
            }

            // Phone form submit.
            var phoneForm = overlay.querySelector('.dgw-modal-phone-form');
            if (phoneForm) {
                phoneForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var phone = phoneForm.querySelector('input[name="phone"]').value.trim();
                    if (!phone || phone.length < 9) {
                        self._error = 'Please enter a valid phone number (at least 9 digits).';
                        self._render();
                        self._show();
                        return;
                    }
                    self._initMoMoPayment(phone);
                });
            }

            // Stripe pay button.
            var stripePayBtn = overlay.querySelector('.dgw-stripe-pay-btn');
            if (stripePayBtn) {
                stripePayBtn.addEventListener('click', function () {
                    self._confirmStripe();
                });
            }

            // Retry button.
            var retryBtn = overlay.querySelector('.dgw-modal-btn-retry');
            if (retryBtn) {
                retryBtn.addEventListener('click', function () {
                    self._error = '';
                    self._state = self._config.provider ? 'phone_input' : 'method_select';
                    if (self._config.provider === 'stripe') {
                        self._state = 'creating';
                        self._render();
                        self._show();
                        self._initStripePayment();
                    } else {
                        self._render();
                        self._show();
                    }
                });
            }

            // Done button.
            var doneBtn = overlay.querySelector('.dgw-modal-done-btn');
            if (doneBtn) {
                doneBtn.addEventListener('click', function () {
                    self.close();
                    if (self._config.successUrl) {
                        window.location.href = self._config.successUrl;
                    }
                });
            }
        },

        /* ------------------------------------------------------------ */
        /*  Internal: Payment Flows                                       */
        /* ------------------------------------------------------------ */

        _initMoMoPayment: function (phone) {
            var self = this;
            var cfg = this._config;

            this._state = 'creating';
            this._error = '';
            this._render();
            this._show();

            DGW_Checkout.createPayment({
                provider: 'iotec',
                amount: cfg.amount,
                currency: cfg.currency || 'UGX',
                phone_number: phone,
                description: cfg.description || '',
                metadata: cfg.metadata || {},
            })
            .then(function (data) {
                var ref = data.data ? data.data.reference : (data.reference || '');
                self._reference = ref;
                self._state = 'processing';
                self._render();
                self._show();

                DGW_Checkout.pollStatus(ref,
                    function () { self._onSuccess(); },
                    function (err) { self._onFailed(err); }
                );
            })
            .catch(function (err) {
                self._onFailed(err.message || 'Failed to initiate payment');
            });
        },

        _initStripePayment: function () {
            var self = this;
            var cfg = this._config;

            DGW_Checkout.createPayment({
                provider: 'stripe',
                amount: cfg.amount,
                currency: cfg.currency || 'USD',
                description: cfg.description || '',
                metadata: cfg.metadata || {},
            })
            .then(function (data) {
                var d = data.data || data;
                var ref = d.reference || '';
                var pubKey = d.stripe_publishable_key;
                var secret = d.client_secret;

                if (!pubKey || !secret) {
                    throw new Error('Stripe is not configured on the server.');
                }

                self._reference = ref;

                return DGW_Checkout.loadStripe(pubKey).then(function (stripe) {
                    self._stripe = stripe;
                    self._clientSecret = secret;
                    self._state = 'stripe_mount';
                    self._render();
                    self._show();
                });
            })
            .catch(function (err) {
                self._onFailed(err.message || 'Failed to initiate card payment');
            });
        },

        _mountStripeAfterRender: function () {
            var container = document.getElementById('dgw-stripe-element');
            if (!container || !this._stripe || !this._clientSecret) return;

            var result = DGW_Checkout.mountStripeElements(this._stripe, this._clientSecret, container);
            this._stripeElements = result.elements;
        },

        _confirmStripe: function () {
            var self = this;
            if (!this._stripe || !this._stripeElements) return;

            var btn = this._overlay.querySelector('.dgw-stripe-pay-btn');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Processing...';
            }

            DGW_Checkout.confirmStripePayment(this._stripe, this._stripeElements)
            .then(function () {
                self._state = 'processing';
                self._render();
                self._show();

                DGW_Checkout.pollStatus(self._reference,
                    function () { self._onSuccess(); },
                    function (err) { self._onFailed(err); }
                );
            })
            .catch(function (err) {
                self._error = err.message || 'Card payment failed';
                if (btn) {
                    btn.disabled = false;
                    btn.textContent = 'Pay Now';
                }
                self._render();
                self._show();
            });
        },

        /* ------------------------------------------------------------ */
        /*  Subscribe Flow                                                */
        /* ------------------------------------------------------------ */

        /**
         * Open the subscribe modal.
         *
         * @param {Object} config
         * @param {number}   config.planId
         * @param {string}   config.description
         * @param {Function} config.onSuccess
         * @param {Function} config.onFailed
         * @param {Function} config.onClose
         */
        openSubscribe: function (config) {
            this._config = config || {};
            this._config._isSubscribe = true;
            this._state = 'subscribe_info';
            this._reference = '';
            this._error = '';

            this._render();
            this._show();
        },

        _renderSubscribeInfo: function () {
            var desc = this._config.description ? '<p class="dgw-modal-hint">' + this._escHtml(this._config.description) + '</p>' : '';
            return '<div class="dgw-modal-body">' +
                desc +
                '<form class="dgw-modal-subscribe-form">' +
                    '<label class="dgw-modal-label">Full Name</label>' +
                    '<input type="text" class="dgw-modal-input" name="customer_name" placeholder="Your full name" required />' +
                    '<label class="dgw-modal-label" style="margin-top:12px;">Email Address</label>' +
                    '<input type="email" class="dgw-modal-input" name="customer_email" placeholder="you@example.com" required />' +
                    '<label class="dgw-modal-label" style="margin-top:12px;">Phone Number</label>' +
                    '<input type="tel" class="dgw-modal-input" name="customer_phone" placeholder="e.g. 256771234567" />' +
                    '<button type="submit" class="dgw-modal-btn dgw-modal-btn-primary" style="margin-top:16px;">Subscribe</button>' +
                '</form>' +
            '</div>';
        },

        _initSubscribe: function (name, email, phone) {
            var self = this;
            var cfg = this._config;

            this._state = 'creating';
            this._error = '';
            this._render();
            this._show();

            var restUrl = (typeof dgwCheckout !== 'undefined' && dgwCheckout.restUrl) ? dgwCheckout.restUrl : '/wp-json/dgateway/v1/';

            var xhr = new XMLHttpRequest();
            xhr.open('POST', restUrl + 'subscribe');
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onload = function () {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (xhr.status >= 400 || data.error) {
                        self._onFailed((data.error && data.error.message) || 'Subscription failed');
                        return;
                    }
                    var d = data.data || data;
                    self._reference = d.reference || '';

                    // If there's a transaction (no trial), poll for payment
                    if (d.transaction_reference) {
                        self._state = 'processing';
                        self._render();
                        self._show();
                        DGW_Checkout.pollStatus(d.transaction_reference,
                            function () { self._onSubscribeSuccess(); },
                            function (err) { self._onFailed(err); }
                        );
                    } else {
                        // Trial subscription — immediately successful
                        self._onSubscribeSuccess();
                    }
                } catch (e) {
                    self._onFailed('Invalid response from server');
                }
            };
            xhr.onerror = function () {
                self._onFailed('Network error');
            };
            xhr.send(JSON.stringify({
                plan_id: cfg.planId,
                customer_name: name,
                customer_email: email,
                customer_phone: phone,
                provider: 'iotec',
            }));
        },

        _onSubscribeSuccess: function () {
            this._state = 'completed';
            this._config._completedTitle = 'Subscription Created!';
            this._config._completedMessage = 'Reference: ' + this._reference;
            this._render();
            this._show();

            DGW_Checkout.fireEvent('dgw:subscribe:success', { reference: this._reference });

            if (this._config.onSuccess) {
                this._config.onSuccess(this._reference);
            }
        },

        /* ------------------------------------------------------------ */
        /*  Internal: Callbacks                                           */
        /* ------------------------------------------------------------ */

        _onSuccess: function () {
            this._state = 'completed';
            this._render();
            this._show();

            DGW_Checkout.fireEvent('dgw:payment:success', { reference: this._reference });

            if (this._config.onSuccess) {
                this._config.onSuccess(this._reference);
            }
        },

        _onFailed: function (message) {
            this._error = message || 'Payment failed';
            this._state = 'failed';
            this._render();
            this._show();

            DGW_Checkout.fireEvent('dgw:payment:failed', { error: this._error });

            if (this._config.onFailed) {
                this._config.onFailed(this._error);
            }
        },

        /* ------------------------------------------------------------ */
        /*  Internal: Helpers                                             */
        /* ------------------------------------------------------------ */

        _formatNumber: function (n) {
            return parseFloat(n).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 });
        },

        _escHtml: function (str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },
    };

    // Expose globally.
    window.DGW_Modal = DGW_Modal;

    /* ------------------------------------------------------------------ */
    /*  Auto-init from data-dgw-* buttons                                  */
    /* ------------------------------------------------------------------ */

    document.addEventListener('click', function (e) {
        // Subscribe button auto-init.
        var subBtn = e.target.closest('[data-dgw-subscribe]');
        if (subBtn) {
            e.preventDefault();
            DGW_Modal.openSubscribe({
                planId: parseInt(subBtn.getAttribute('data-dgw-plan-id')) || 0,
                description: subBtn.getAttribute('data-dgw-description') || '',
            });
            return;
        }

        // Payment button auto-init.
        var btn = e.target.closest('[data-dgw-amount]');
        if (!btn) return;

        e.preventDefault();

        DGW_Modal.open({
            amount: parseFloat(btn.getAttribute('data-dgw-amount')) || 0,
            currency: btn.getAttribute('data-dgw-currency') || 'UGX',
            provider: btn.getAttribute('data-dgw-provider') || '',
            description: btn.getAttribute('data-dgw-description') || '',
            successUrl: btn.getAttribute('data-dgw-success-url') || '',
        });
    });

})();
