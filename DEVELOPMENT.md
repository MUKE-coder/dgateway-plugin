# DGateway Plugin — Development & Deployment Guide

## Local Development & Testing

### Prerequisites

- A local WordPress installation. Recommended tools:
  - [Local by Flywheel](https://localwp.com/) (easiest, one-click WordPress)
  - [XAMPP](https://www.apachefriends.org/) or [MAMP](https://www.mamp.info/)
  - [Docker with WordPress](https://hub.docker.com/_/wordpress)
- PHP 7.4 or higher
- WordPress 5.8 or higher
- WooCommerce 5.0+ (optional, for WooCommerce integration testing)
- A DGateway account with an API key

### Setting Up for Local Testing

1. **Install WordPress locally**

   Using Local by Flywheel (recommended):
   ```
   1. Download and install Local from https://localwp.com/
   2. Click "Create a New Site"
   3. Name it "DGateway Test"
   4. Choose PHP 7.4+ and MySQL 8.0
   5. Set admin credentials and click "Add Site"
   ```

2. **Symlink or copy the plugin**

   Option A — Symlink (recommended for active development):
   ```bash
   # Windows (run as Administrator)
   mklink /D "C:\path\to\wordpress\wp-content\plugins\dgateway" "D:\path\to\dgateway-plugin"

   # macOS / Linux
   ln -s /path/to/dgateway-plugin /path/to/wordpress/wp-content/plugins/dgateway
   ```

   Option B — Copy:
   ```bash
   cp -r /path/to/dgateway-plugin /path/to/wordpress/wp-content/plugins/dgateway
   ```

3. **Activate the plugin**

   Go to WordPress Admin → Plugins → Find "DGateway Payment Gateway" → Click "Activate"

4. **Configure the plugin**

   Go to DGateway → Settings and enter:
   - API URL: `https://dgatewayapi.desispay.com` (or your local DGateway API URL)
   - API Key: Your app's API key from the DGateway dashboard
   - Webhook Secret: Your app's webhook signing secret from the DGateway dashboard (must match exactly)
   - Default Currency: `UGX` (or your preferred currency)

### Testing Scenarios

#### 1. Standalone Payment Button

```
1. Create a new Page in WordPress
2. Add this shortcode: [dgw_pay_button amount="1000" currency="UGX" description="Test Payment"]
3. View the page on the frontend
4. Click the "Pay Now" button
5. Enter a valid phone number (e.g., 256771234567)
6. Verify the USSD prompt is sent and payment processes
```

#### 2. Standalone Subscribe Button

```
1. Create a subscription plan in DGateway dashboard (note the plan_id)
2. Create a new Page in WordPress
3. Add this shortcode: [dgw_subscribe_button plan_id="YOUR_PLAN_ID" label="Subscribe"]
4. View the page on the frontend
5. Click "Subscribe", fill in name/email/phone
6. Verify the subscription is created in DGateway
```

#### 3. WooCommerce Payment Gateway

```
1. Install and activate WooCommerce
2. Run the WooCommerce Setup Wizard
3. Go to WooCommerce → Settings → Payments
4. Enable "DGateway Payment Gateway"
5. Create a simple product (e.g., "Test Product" for 5000 UGX)
6. Add it to cart and go to checkout
7. Select "DGateway Payment Gateway"
8. Enter phone number and place order
9. Verify payment processes and order status updates
```

#### 4. WooCommerce Subscription Product

```
1. Create a subscription plan in DGateway (e.g., monthly, 10000 UGX)
2. Go to Products → Add New
3. Select product type "DGateway Subscription"
4. Set price to 10000
5. In the "Subscription" tab, enter:
   - Plan ID: (from step 1)
   - Billing Interval: Monthly
   - Trial Days: 0 (or 7 for testing trial)
6. Publish the product
7. Add to cart and checkout
8. Verify subscription is created and first payment collected
```

#### 5. Webhook Testing

```
1. Use a tool like ngrok to expose your local WordPress:
   ngrok http 80
2. Copy the ngrok URL (e.g., https://abc123.ngrok.io)
3. In DGateway dashboard, set your app's webhook URL to:
   https://abc123.ngrok.io/wp-json/dgateway/v1/webhook
4. Copy the app's webhook secret from DGateway into DGateway → Settings → Webhook Secret
5. Make a test payment
6. Check that the signed webhook updates the WooCommerce order status
7. Check WordPress debug.log for webhook processing logs
```

### Debugging

Enable debug logging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

View logs at: `wp-content/debug.log`

If WooCommerce is active, also check: WooCommerce → Status → Logs → "dgateway" source

---

## Deploying to WordPress.org

### Preparing for Submission

1. **Review WordPress Plugin Guidelines**

   Read the official guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

2. **Plugin Header Requirements**

   The main plugin file (`dgateway.php`) must have these headers (already configured):
   ```php
   /**
    * Plugin Name: DGateway Payment Gateway
    * Plugin URI: https://dgatewayapi.desispay.com
    * Description: Accept Mobile Money and Card payments via DGateway.
    * Version: 1.0.0
    * Author: DesisPay
    * Author URI: https://desispay.com
    * License: GPL-2.0-or-later
    * Text Domain: dgateway
    * Requires at least: 5.8
    * Requires PHP: 7.4
    */
   ```

3. **Create a readme.txt**

   WordPress.org requires a `readme.txt` in the plugin root. Create one following the standard format:

   ```
   === DGateway Payment Gateway ===
   Contributors: desispay
   Tags: payment gateway, mobile money, mtn, airtel, woocommerce, subscriptions
   Requires at least: 5.8
   Tested up to: 6.7
   Requires PHP: 7.4
   Stable tag: 1.0.0
   License: GPL-2.0-or-later
   License URI: https://www.gnu.org/licenses/gpl-2.0.html

   Accept Mobile Money (MTN, Airtel) and Card payments via DGateway. Works standalone or with WooCommerce.

   == Description ==

   DGateway Payment Gateway is a WordPress plugin that lets you accept payments
   through Mobile Money and Card via the DGateway payment platform.

   **Features:**
   * Accept Mobile Money payments (MTN, Airtel) via Iotec and Relworx
   * Accept Card payments via Stripe
   * Works standalone with shortcodes — no WooCommerce required
   * Full WooCommerce payment gateway integration
   * Subscription/recurring payment support
   * WooCommerce subscription product type
   * Admin dashboard for transactions and subscriptions
   * Webhook support for real-time payment updates

   == Installation ==

   1. Upload `dgateway` folder to `/wp-content/plugins/`
   2. Activate the plugin through the 'Plugins' menu
   3. Go to DGateway → Settings to configure your API key
   4. Start accepting payments!

   == Frequently Asked Questions ==

   = Do I need WooCommerce? =
   No! The plugin works standalone with shortcodes. WooCommerce integration
   is optional for merchants who want full e-commerce functionality.

   = What payment providers are supported? =
   Iotec (MTN/Airtel Mobile Money), Relworx (Mobile Money),
   and Stripe (International Cards).

   = How do subscriptions work? =
   DGateway uses a "scheduled invoice" model. When payment is due,
   customers are notified and re-initiate payment. No auto-debit.

   == Changelog ==

   = 1.0.0 =
   * Initial release
   * Mobile Money and Card payment support
   * WooCommerce gateway integration
   * Subscription plans and recurring payments
   * Subscribe and Pay shortcodes
   * Admin dashboard
   ```

4. **Add required assets**

   Create an `assets/` directory for WordPress.org listing images:
   - `assets/banner-772x250.png` — Plugin banner
   - `assets/icon-128x128.png` — Plugin icon
   - `assets/screenshot-1.png` — Screenshot of settings page
   - `assets/screenshot-2.png` — Screenshot of checkout modal

### Submitting to WordPress.org

1. **Create a WordPress.org account**

   Register at https://wordpress.org/support/register.php

2. **Submit the plugin for review**

   Go to https://wordpress.org/plugins/developers/add/ and submit your plugin.

   What to include:
   - Plugin name: DGateway Payment Gateway
   - Plugin slug: dgateway
   - Plugin URL: https://dgatewayapi.desispay.com
   - A brief description of what the plugin does

3. **Wait for review**

   The WordPress.org plugin review team will review your submission. This typically takes 1-5 business days. They may request changes.

4. **Once approved — Set up SVN**

   WordPress.org uses SVN (Subversion) for plugin hosting. After approval, you'll receive SVN credentials.

   ```bash
   # Check out the SVN repository
   svn co https://plugins.svn.wordpress.org/dgateway dgateway-svn

   # Copy plugin files to trunk
   cp -r dgateway-plugin/* dgateway-svn/trunk/

   # Add and commit
   cd dgateway-svn
   svn add trunk/*
   svn ci -m "Initial release v1.0.0" --username your_wp_username

   # Tag the release
   svn cp trunk tags/1.0.0
   svn ci -m "Tagging v1.0.0" --username your_wp_username
   ```

5. **Updating the plugin**

   For future updates:
   ```bash
   # Update version in dgateway.php, readme.txt
   # Copy new files to trunk
   cp -r dgateway-plugin/* dgateway-svn/trunk/

   # Commit trunk changes
   cd dgateway-svn
   svn ci -m "Update to v1.1.0" --username your_wp_username

   # Tag new version
   svn cp trunk tags/1.1.0
   svn ci -m "Tagging v1.1.0" --username your_wp_username
   ```

### Alternative: Distribute via GitHub

If you don't want to go through WordPress.org review, you can distribute directly:

1. **Create a release on GitHub**
   ```bash
   # Create a ZIP file
   cd /path/to/dgateway-plugin
   zip -r dgateway-plugin-v1.0.0.zip . -x ".git/*" -x ".gitignore" -x "DEVELOPMENT.md"
   ```

2. **Create a GitHub release**
   - Go to your GitHub repo → Releases → Create new release
   - Tag: v1.0.0
   - Title: DGateway Payment Gateway v1.0.0
   - Attach the ZIP file
   - Users can download and install via WordPress Admin → Plugins → Upload Plugin

3. **Auto-updates from GitHub** (optional)
   Use the [Plugin Update Checker](https://github.com/YahniS/plugin-update-checker) library to enable auto-updates from your GitHub releases.

## Security review and proposed fixes

Reviewed plugin version 1.1.4 at commit `c0e7bbd` on 2026-11-16. The review covers PHP entry points, REST routes, administrator AJAX actions, frontend scripts, templates, WooCommerce order changes, API requests, update handling, and the release workflow. This is a source review with isolated regression tests; it does not establish the security of the DGateway service or a deployed WordPress installation. No live payments or requests against a merchant installation were made.

### Findings addressed in this patch

1. **Critical: unauthenticated webhook order changes and integration events.** `DGW_Webhook_Handler::handle()` previously trusted JSON before any authentication. An unauthenticated sender knowing a pending order's `_dgw_reference` could submit `{"reference":"<that-reference>","status":"completed"}` to `/wp-json/dgateway/v1/webhook` and trigger `payment_complete()` without a payment. `failed` could also alter orders, and arbitrary `subscription.*` events reached custom hooks without authentication. The payment reference is exposed to the purchaser during checkout; guessing references is unnecessary for a purchaser attacking their own order. The patch requires a hex HMAC-SHA256 `X-DGateway-Signature` over the exact raw body and uses `hash_equals()` before parsing, lookup, logging payload values, or dispatching actions. Missing configuration returns 503; missing/invalid signatures return 401. Signed malformed payloads return 400. Both payment and subscription events use the same authentication gate. This follows [DGateway's documented webhook protocol](https://dgateway.desispay.com/docs/getting-started/accept-your-first-payment).

2. **High: stored script injection through shortcode currency and success URL.** Shortcode attributes were escaped in the initial HTML, but the browser decoded the currency data attribute and concatenated it into modal `innerHTML`. A content author able to save a shortcode could use `[dgw_pay_button amount="100" currency="&lt;img src=x onerror=alert(document.domain)&gt;"]`. The entity-encoded payload survives HTML tag filtering and is decoded when read from the HTML attribute. Opening the button's modal created executable markup for the viewer. Content publication permissions and a viewer clicking the button are prerequisites; role-specific post filtering still needs validation in WordPress. The patch escapes currency at the final DOM sink. It also rejects executable redirect schemes such as `javascript:` in shortcode success URLs before navigation after a completed payment. Normal HTTP(S) and relative success URLs still work.

3. **Medium: public verification returns the merchant API's entire transaction response.** Anyone with a transaction reference could submit it to `/verify` and receive every upstream field. The route did not check transaction ownership, and the nonce callback always returned true. Whether customer data, metadata, or provider secrets were disclosed depends on the upstream response; no production response containing those fields was obtained. The patch allows only reference and normalized polling status through, preserving the frontend's `data.status` contract. Polling remains public to support guests, and a reference holder can still learn its status. Upstream errors are still returned, so they should contain no sensitive details.

4. **Medium: failure notifications can downgrade paid orders.** Both webhook handling and status polling previously changed any order whose status was not already `failed` to `failed`, including paid orders. With the original unsigned webhook, an attacker knowing a reference could directly exploit this. Signed delayed events or stale upstream results could also cause the transition. The patch permits failure only for unpaid `pending` or `on-hold` DGateway orders with no paid date, and ignores orders using other payment methods. Repeated completion notifications do not repeat payment completion for an already paid order.

The patch also removes the unconditional success path from nonce verification, sends the nonce from subscription requests, and escapes upstream connection-test messages in the administrator UI. The latter is defensive hardening: an attacker-controlled upstream message source was not demonstrated. A guest WordPress nonce is publicly obtainable and is **not authorization or a bot defense**, as explained in [WordPress's nonce guidance](https://developer.wordpress.org/apis/security/nonces/). No claim of abuse prevention is made for this change.

### Upgrade and compatibility

- Before relying on webhook delivery, set **DGateway → Settings → Webhook Secret** to the secret for the same DGateway app as the API key. Previously installed sites may hold a random locally generated value; it is preserved, but must be replaced unless it already matches the app secret. New installations start with an empty value. Never put this secret in public JavaScript or the webhook URL.
- Test a provider-signed webhook after saving the secret. Unsigned callbacks are deliberately rejected, including subscription callbacks. Existing custom callers of checkout, verify, or subscribe must now supply the frontend `X-WP-Nonce`; cached pages may need refreshing.
- Integrations reading additional fields from the public `/verify` response must switch to a server-side authenticated API call. Public polling returns only `data.reference` and `data.status`; unfamiliar statuses appear as `pending` while polling waits for a terminal state.
- HMAC authenticates origin and integrity, but the documented signature has no timestamp. It cannot prove freshness. Paid-order completion is idempotent and late failures cannot downgrade paid orders; custom action consumers must independently deduplicate events before side effects. Durable event deduplication requires a provider event ID/freshness contract.

### Validation

Run from the repository root:

```bash
php tests/security.php
node tests/security.js
```

The dependency-free PHP harness stubs WordPress and WooCommerce and tests the real handler/REST classes without making HTTP requests. It covers invalid and absent signatures, missing secrets, tampered raw bytes, wrong secrets, signed payment/subscription events, duplicate completion, malformed payloads, both failure-update paths, cross-gateway isolation, response filtering, invalid references, and nonce rejection. The Node harness exercises modal HTML generation, URL validation, and the administrator's actual AJAX message handler with a minimal DOM/jQuery stub. These tests do not replace real browser or WooCommerce integration testing.

Before releasing, test guest and logged-in shortcode payments/subscriptions, classic and Blocks checkout, Mobile Money and Stripe, signed completion/failure callbacks, and HPOS enabled/disabled on a staging store. Confirm the signing secret migration and any custom webhook consumers. PHP 7.4 compatibility and a real WordPress/WooCommerce runtime were not executed locally; local validation used PHP 8.5 and Node 22.

### Remaining design risks and proposed follow-up work

- Public checkout/subscription endpoints allow visitors to initiate merchant API calls using arbitrary amounts, providers, phone numbers, or plan IDs. The repository contains no rate limiter despite the old permission callback's comment. Enforce request limits at the reverse proxy/WAF, and design server-issued checkout intents bound to published prices, currency, allowed plans/providers, and permitted metadata. Guest nonces do not solve this. Standalone product prices can currently be altered in the request; the built-in WooCommerce flow independently uses the order total and does not use the public checkout request to set its order reference. No WooCommerce underpayment exploit through the public checkout route was established.
- Subscription checkout treats a response lacking `transaction_reference` as a free trial and selects one subscription plan for the order. Require an explicit validated trial state and define mixed-cart/quantity billing semantics before relying on this flow for fulfillment. The subscription product currently maps to `WC_Product_Simple`, so the intended `dgw_subscription` runtime type also needs functional validation; an exploitable trial bypass was not established.
- The configurable API URL accepts HTTP and the HTTP client follows redirects by default. Require HTTPS for production credentials and review redirect behavior for the `X-Api-Key` header. Choosing a custom API endpoint requires administrator privileges, so this review does not claim an unauthenticated SSRF or credential-theft finding.
- The updater trusts the HTTPS metadata service and its download URL. Signed release artifacts and download-origin validation would reduce supply-chain trust, but compromise of that trusted service was not demonstrated.

### Suggested pull request description

**Title:** Authenticate DGateway webhooks and harden payment status and shortcode handling

Forged webhook requests previously reached WooCommerce payment completion and subscription hooks without validating the sender. Require the documented raw-body HMAC signature and expose the app webhook secret in administrator settings. Reject malformed payloads before side effects.

Escape shortcode currency at the modal HTML sink, reject executable success URL schemes, limit public verification responses to reference/status, and prevent failure notifications from downgrading paid orders. Enforce the existing REST nonce requirement and include it in subscription requests. Escape connection-test messages in the admin UI.

Merchants must configure the matching DGateway app webhook secret after upgrading; previously generated local secrets may not match. Public polling clients receive a reduced response schema. Includes standalone PHP/Node security regressions and staging validation instructions. No version bump, commit, remote push, or PR submission is included in this working-tree patch.
