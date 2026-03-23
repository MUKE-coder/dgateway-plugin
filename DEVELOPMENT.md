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
   mklink /D "C:\path\to\wordpress\wp-content\plugins\dgateway" "D:\Projects\GO DGATEWAY\dgateway-plugin"

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
4. Make a test payment
5. Check that the webhook updates the WooCommerce order status
6. Check WordPress debug.log for webhook processing logs
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
