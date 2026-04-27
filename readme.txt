=== Orbtronics Payment Gateway ===
Contributors: orbtronicsknowspeter
Tags: payment, woocommerce, opay, checkout, payment gateway
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.3
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept payments via Opay. Integrates Opay payment infrastructure with WooCommerce for a seamless hosted checkout experience.

== Description ==

Orbtronics Payment Gateway integrates the Opay payment platform with WordPress and WooCommerce.

**Features:**

* WooCommerce checkout integration — customers are redirected to a secure hosted Opay checkout page
* Supports the WooCommerce Block-based Checkout
* Test and Live mode with separate API keys
* Encrypted storage of secret API keys
* Webhook handling for real-time payment status updates
* Payment button shortcode `[opay_button id="..." label="..."]`
* Gutenberg payment button block

**Third-Party Service**

This plugin connects to the Opay backend server whose URL you configure under **Opay Payments → Settings → General**. All payment processing — including payment session creation and order status updates via webhooks — is handled by that backend. Please review the terms of service and privacy policy of your Opay backend provider before using this plugin.

No data is sent to the Opay backend until a customer initiates a checkout or a payment button is rendered.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/orbtronics-payment-gateway/`, or install via the WordPress plugin screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **Opay Payments → Settings → General** and enter your Backend URL and select your environment (Test or Live).
4. Go to **Opay Payments → Settings → API Keys** and enter your Publishable and Secret keys.
5. Go to **WooCommerce → Settings → Payments**, find **Opay**, and click **Manage** to enable the gateway and configure the checkout title and description.

== Frequently Asked Questions ==

= Where do I get my API keys? =

API keys are provided by your Opay backend. Log in to your Opay dashboard to retrieve your publishable and secret keys.

= Is this plugin PCI compliant? =

Yes. Customers are redirected to a hosted checkout page on the Opay backend for card data entry. No payment card data is processed or stored on your WordPress site.

= What currencies are supported? =

The gateway passes the WooCommerce store currency to Opay. Supported currencies depend on your Opay account configuration.

= Where are webhooks received? =

Your webhook endpoint is shown in **Opay Payments → Settings → General**. Register this URL in your Opay dashboard to receive real-time payment status updates.

= Does this work with the WooCommerce block checkout? =

Yes. The plugin registers a payment method for the WooCommerce Checkout Block as well as the classic shortcode checkout.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
