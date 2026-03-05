=== DGateway Payment Gateway ===
Contributors: desispay
Tags: payment gateway, mobile money, woocommerce, stripe, dgateway
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Mobile Money and Card payments via DGateway. Works standalone or with WooCommerce.

== Description ==

DGateway Payment Gateway allows WordPress and WooCommerce site owners to accept payments through DGateway, supporting both Mobile Money (via Iotec) and Card payments (via Stripe).

**Features:**

* WooCommerce payment gateway integration
* Standalone payment buttons via shortcodes
* Donation forms with preset amounts
* Product cards with buy buttons
* Modal popup checkout experience
* Mobile Money (USSD prompt) support
* Credit/debit card payments via Stripe
* Admin dashboard with transaction history
* Withdrawal management
* Webhook support for real-time payment updates
* Test and Live mode (auto-detected from API key)

**Shortcodes:**

* `[dgw_pay_button amount="5000" currency="UGX" label="Pay Now"]` - Simple payment button
* `[dgw_donation_form amounts="5000,10000,50000" currency="UGX"]` - Donation form
* `[dgw_product_card name="Product" price="25000" currency="UGX"]` - Product card

== Installation ==

1. Upload the `dgateway-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to DGateway > Settings and enter your API Key
4. Copy the Webhook URL and add it to your DGateway App settings
5. (Optional) Enable the DGateway payment method in WooCommerce > Settings > Payments

== Frequently Asked Questions ==

= Do I need WooCommerce? =

No. DGateway works standalone with shortcodes. WooCommerce integration is optional.

= How do I get an API key? =

Sign up at [dgatewayapi.desispay.com](https://dgatewayapi.desispay.com), create an app, and generate an API key.

= What payment methods are supported? =

Mobile Money (via Iotec) and Card payments (via Stripe). The payment method tabs are shown automatically in the checkout modal.

= How do I test payments? =

Use a test API key (starts with `dgw_test_`) for sandbox mode.

== Changelog ==

= 1.0.0 =
* Initial release
* WooCommerce payment gateway
* Shortcodes: pay button, donation form, product card
* Admin dashboard, transactions, withdrawals
* Mobile Money and Card payment support
* Webhook handling for real-time updates
