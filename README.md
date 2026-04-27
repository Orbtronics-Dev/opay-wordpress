# Opay Payment Gateway

A WordPress plugin that integrates the [Opay](https://orbtronics.co/opay) payment platform with WooCommerce. Customers are redirected to a secure hosted checkout page on your Opay backend. The plugin also provides a standalone payment button shortcode and Gutenberg block for use outside of WooCommerce.

---

## Features

- **WooCommerce gateway** — classic shortcode checkout and Block-based Checkout (WC 7+)
- **Test & Live modes** — separate publishable/secret key pairs, switchable from the admin
- **Encrypted key storage** — secret keys are AES-256-CBC encrypted using `AUTH_KEY` before being written to the database
- **Real-time webhooks** — REST endpoint at `/wp-json/opay/v1/webhook` updates WooCommerce order status on payment success/failure
- **Payment button shortcode** — `[opay_button]` renders a hosted-checkout button anywhere on the site
- **Gutenberg block** — drag-and-drop payment button block with editor preview
- **HPOS compatible** — declares compatibility with WooCommerce High-Performance Order Storage

---

## Requirements

| Dependency  | Minimum |
| ----------- | ------- |
| PHP         | 8.0     |
| WordPress   | 6.0     |
| WooCommerce | 7.0     |

---

## Local Development

The repo ships with a `compose.yaml` that bind-mounts the plugin source directly into a WordPress container — every file change on the host is immediately live with no re-upload.

```bash
# Start WordPress + MySQL
docker compose up -d

# WordPress is available at http://localhost:8080
```

First-run checklist:

1. Complete the WordPress installer at `http://localhost:8080`
2. Install & activate WooCommerce
3. Activate **Opay Payment Gateway** from **Plugins**
4. Go to **Opay Payments → Settings → General**, set your Backend URL and environment
5. Go to **Opay Payments → Settings → API Keys**, add your test keys
6. Go to **WooCommerce → Settings → Payments → Opay → Manage** and enable the gateway

---

## Configuration

### API Keys

Navigate to **Opay Payments → Settings → API Keys**.

| Field           | Description                                                        |
| --------------- | ------------------------------------------------------------------ |
| Publishable Key | Public key prefixed `opay_test_pk_` or `opay_live_pk_`             |
| Secret Key      | Server-side key used to create payment sessions. Stored encrypted. |

### General Settings

Navigate to **Opay Payments → Settings → General**.

| Field       | Description                                                              |
| ----------- | ------------------------------------------------------------------------ |
| Backend URL | Base URL of your Opay backend, e.g. `https://staging.opay.orbtronics.co` |
| Environment | `Test` or `Live` — determines which key pair is used                     |
| Webhook URL | Read-only. Register this in your Opay dashboard.                         |

---

## Plugin Structure

```
orbtronics-payment-gateway/
├── orbtronics-payment-gateway.php     # Plugin bootstrap, hooks, activation
├── uninstall.php                # Cleanup on deletion
│
├── includes/
│   ├── class-opay-auth.php      # Credential storage & AES-256 encryption
│   ├── class-opay-api.php       # HTTP client wrapping wp_remote_*
│   └── class-opay-webhook-handler.php  # REST webhook endpoint + WC order updates
│
├── admin/
│   ├── class-opay-admin.php     # Admin menu, AJAX dispatcher
│   └── views/
│       └── page-settings.php    # Settings page (API Keys + General tabs)
│
├── public/
│   ├── class-opay-shortcodes.php  # [opay_button] shortcode
│   └── class-opay-block.php       # Gutenberg block registration
│
├── woocommerce/
│   ├── class-opay-wc-gateway.php            # WC_Payment_Gateway implementation
│   └── class-opay-blocks-payment-method.php # AbstractPaymentMethodType for block checkout
│
├── block/
│   ├── block.json   # Block metadata
│   ├── edit.js      # Block editor component
│   └── view.js      # Front-end view script
│
├── assets/
│   ├── admin.js / admin.css     # Admin page scripts & styles
│   ├── public.js / public.css   # Front-end button scripts & styles
│   └── wc-opay-blocks.js        # WooCommerce Checkout Block registration
│
├── languages/                   # Translation files (.pot/.po/.mo)
├── readme.txt                   # WordPress.org plugin page
└── .github/workflows/release.yml
```

---

## Webhooks

Register your webhook URL (shown in **Settings → General**) in your Opay dashboard.

The endpoint accepts `POST /wp-json/opay/v1/webhook` and dispatches the following WordPress action hooks:

| Event                                                    | Action hook                 |
| -------------------------------------------------------- | --------------------------- |
| `payment.succeeded` / `payment_intent.succeeded`         | `opay_payment_succeeded`    |
| `payment.failed` / `payment_intent.payment_failed`       | `opay_payment_failed`       |
| `charge.refunded` / `refund.created`                     | `opay_refund_created`       |
| `customer.subscription.updated` / `subscription.updated` | `opay_subscription_updated` |
| Any other event                                          | `opay_webhook_received`     |

WooCommerce orders are automatically moved to `processing` on a succeeded event and `failed` on a failed event, matched via `metadata.order_id` in the payload.

All incoming webhook events are logged to the `{prefix}_opay_webhook_log` database table.

**Listening to webhook events from your own code:**

```php
add_action( 'opay_payment_succeeded', function ( array $payload ) {
    $order_id = $payload['data']['metadata']['order_id'] ?? null;
    // your logic here
} );
```

---

## Payment Button Shortcode

Use `[opay_button]` to embed a hosted-checkout button on any page or post.

```
[opay_button id="your-button-uuid" label="Buy Now"]
```

| Attribute | Required | Default   | Description                                      |
| --------- | -------- | --------- | ------------------------------------------------ |
| `id`      | Yes      | —         | The payment button UUID from your Opay dashboard |
| `label`   | No       | `Pay Now` | Button label text                                |

Button data is fetched from the Opay backend and cached as a WordPress transient for 5 minutes.

---

## Gutenberg Block

The **Opay Payment Button** block is available in the **Widgets** category of the block inserter. Select a payment button from the dropdown (populated from your Opay backend) and optionally override the label. The block renders using the `[opay_button]` shortcode server-side.

---

## API Client

`Opay_API` is a thin static wrapper around `wp_remote_*`. Authentication is resolved automatically per path:

| Path prefix                                | Auth                     |
| ------------------------------------------ | ------------------------ |
| `/api/v1/*`                                | `Bearer {secret_key}`    |
| `/api/auth/*`                              | None                     |
| `/api/pay/*`                               | None                     |
| `/api/account/*`, `/api/payment-buttons/*` | `Bearer {sanctum_token}` |

---

## Releasing

Releases are automated via GitHub Actions. Push a version tag and the workflow builds a clean ZIP and publishes a GitHub Release.

```bash
git tag v1.0.0
git push origin v1.0.0
```

The resulting ZIP is named `orbtronics-payment-gateway-{version}.zip` and excludes `.git`, `.github`, `.claude`, `compose.yaml`, and `README.md`. It is ready to upload directly to **Plugins → Add New → Upload Plugin** or to the WordPress.org SVN repository.

To build a ZIP locally:

```bash
make export
# or
just export
```

---

## License

GPL-2.0-or-later — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
