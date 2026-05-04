<?php
/**
 * Opay_WC_Gateway — WooCommerce payment gateway.
 *
 * Extends WC_Payment_Gateway. On checkout, creates an Opay payment session
 * via the secret API key and redirects the customer to the hosted checkout URL.
 */
defined( 'ABSPATH' ) || exit;

class Opay_WC_Gateway extends WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id                 = 'opay';
        $this->icon               = apply_filters( 'opay_gateway_icon', OPAY_PLUGIN_URL . 'assets/orbtronics.svg' );
        $this->method_title       = __( 'Opay', 'orbtronics-payment-gateway' );
        $this->method_description = __( 'Accept payments via Opay. Customers are redirected to a secure hosted checkout page.', 'orbtronics-payment-gateway' );
        $this->has_fields         = false;
        $this->supports           = [ 'products' ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            [ $this, 'process_admin_options' ]
        );
    }

    // -------------------------------------------------------------------------
    // Admin options page — logo + status banner + standard settings table
    // -------------------------------------------------------------------------

    public function admin_options(): void
    {
        $has_keys     = Opay_Auth::has_api_keys();
        $backend_url  = Opay_Auth::get_backend_url();
        $settings_url = admin_url( 'admin.php?page=opay-settings' );
        ?>
        <div style="display:flex;align-items:center;gap:16px;margin:12px 0 16px;">
            <img src="<?php echo esc_url( OPAY_PLUGIN_URL . 'assets/orbtronics.svg' ); ?>"
                 alt="Opay"
                 style="height:44px;width:auto;display:block;" />
        </div>

        <div class="notice notice-info inline" style="margin:0 0 16px;">
            <p>
                <?php
                printf(
                    /* translators: %s: link to Opay settings page */
                    esc_html__( 'API credentials are managed on the %s — configure your backend URL and API keys there first, then enable this gateway below.', 'orbtronics-payment-gateway' ),
                    '<a href="' . esc_url( $settings_url ) . '"><strong>' . esc_html__( 'Opay Payments settings page', 'orbtronics-payment-gateway' ) . '</strong></a>'
                );
        ?>
            </p>
        </div>

        <?php if ( ! $backend_url ) { ?>
        <div class="notice notice-error inline" style="margin:0 0 16px;">
            <p><?php esc_html_e( 'Backend URL is not set. Go to Opay Payments → Settings → General to add it.', 'orbtronics-payment-gateway' ); ?></p>
        </div>
        <?php } elseif ( ! $has_keys ) { ?>
        <div class="notice notice-warning inline" style="margin:0 0 16px;">
            <p><?php esc_html_e( 'No API keys configured. Payments cannot be processed until credentials are set.', 'orbtronics-payment-gateway' ); ?></p>
        </div>
        <?php } else { ?>
        <div class="notice notice-success inline" style="margin:0 0 16px;">
            <p>
                <?php
        printf(
            /* translators: %s: environment label (Test / Live) */
            esc_html__( 'Connected — %s mode.', 'orbtronics-payment-gateway' ),
            '<strong>' . esc_html( ucfirst( Opay_Auth::get_environment() ) ) . '</strong>'
        );
            ?>
            </p>
        </div>
        <?php } ?>

        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        <?php
    }

    // -------------------------------------------------------------------------
    // Gateway settings fields
    // -------------------------------------------------------------------------

    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled'     => [
                'title'   => __( 'Enable / Disable', 'orbtronics-payment-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Opay payment gateway', 'orbtronics-payment-gateway' ),
                'default' => 'no',
            ],
            'title'       => [
                'title'       => __( 'Title', 'orbtronics-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Shown to customers on the checkout page.', 'orbtronics-payment-gateway' ),
                'default'     => __( 'Pay with Opay', 'orbtronics-payment-gateway' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Description', 'orbtronics-payment-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Shown below the payment title at checkout.', 'orbtronics-payment-gateway' ),
                'default'     => __( 'Pay securely using your card.', 'orbtronics-payment-gateway' ),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Process payment
    // -------------------------------------------------------------------------

    public function process_payment( $order_id ): array
    {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Order not found.', 'orbtronics-payment-gateway' ), 'error' );

            return [ 'result' => 'failure' ];
        }

        // Validate that a secret key is available
        $sk = Opay_Auth::get_sk();

        if ( ! $sk ) {
            wc_add_notice(
                __( 'Payment configuration error. Please contact the site administrator.', 'orbtronics-payment-gateway' ),
                'error'
            );

            return [ 'result' => 'failure' ];
        }

        // Build payload
        $payload = [
            'amount'      => (int) round( $order->get_total() * 100 ), // Convert to cents
            'currency'    => strtolower( get_woocommerce_currency() ),
            'customer'    => [
                'email' => $order->get_billing_email(),
                'name'  => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                'phone' => $order->get_billing_phone(),
            ],
            'success_url' => $this->get_return_url( $order ),
            'cancel_url'  => wc_get_checkout_url(),
            'metadata'    => [
                'order_id'     => (string) $order_id,
                'order_number' => $order->get_order_number(),
                'source'       => 'woocommerce',
            ],
        ];

        $result = Opay_API::create_payment_session( $payload );

        if ( $result['error'] ) {
            wc_add_notice(
                sprintf(
                    /* translators: %s: error message */
                    __( 'Payment error: %s', 'orbtronics-payment-gateway' ),
                    esc_html( $result['error'] )
                ),
                'error'
            );

            return [ 'result' => 'failure' ];
        }

        $checkout_url   = $result['data']['checkout_url'] ?? $result['data']['url'] ?? '';
        $transaction_id = $result['data']['id'] ?? '';

        if ( ! $checkout_url ) {
            wc_add_notice( __( 'Payment gateway did not return a checkout URL.', 'orbtronics-payment-gateway' ), 'error' );

            return [ 'result' => 'failure' ];
        }

        // Mark order as pending payment and store transaction ID
        $order->update_status( 'pending', __( 'Awaiting Opay payment.', 'orbtronics-payment-gateway' ) );
        $order->update_meta_data( '_opay_transaction_id', $transaction_id );
        $order->add_order_note(
            sprintf(
                /* translators: %s: Opay transaction ID */
                __( 'Opay payment initiated. Transaction ID: %s', 'orbtronics-payment-gateway' ),
                esc_html( $transaction_id )
            )
        );
        $order->save();

        // Empty the cart
        WC()->cart->empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $checkout_url,
        ];
    }
}
