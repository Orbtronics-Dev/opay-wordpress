<?php
/**
 * Opay_WC_Gateway — WooCommerce payment gateway.
 *
 * Extends WC_Payment_Gateway. On checkout, creates an Opay payment session
 * via the secret API key and redirects the customer to the hosted checkout URL.
 */

defined( 'ABSPATH' ) || exit;

class Opay_WC_Gateway extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'opay';
        $this->method_title       = __( 'Opay', 'opay-payment-gateway' );
        $this->method_description = __( 'Accept payments via Opay. Customers are redirected to a secure hosted checkout page.', 'opay-payment-gateway' );
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
    // Gateway settings fields
    // -------------------------------------------------------------------------

    public function init_form_fields(): void {
        $this->form_fields = [
            'enabled'     => [
                'title'   => __( 'Enable / Disable', 'opay-payment-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Opay payment gateway', 'opay-payment-gateway' ),
                'default' => 'no',
            ],
            'title'       => [
                'title'       => __( 'Title', 'opay-payment-gateway' ),
                'type'        => 'text',
                'description' => __( 'Shown to customers on the checkout page.', 'opay-payment-gateway' ),
                'default'     => __( 'Pay with Opay', 'opay-payment-gateway' ),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __( 'Description', 'opay-payment-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Shown below the payment title at checkout.', 'opay-payment-gateway' ),
                'default'     => __( 'Secure payment powered by Opay.', 'opay-payment-gateway' ),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Process payment
    // -------------------------------------------------------------------------

    public function process_payment( $order_id ): array {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            wc_add_notice( __( 'Order not found.', 'opay-payment-gateway' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        // Validate that a secret key is available
        $sk = Opay_Auth::get_sk();
        if ( ! $sk ) {
            wc_add_notice(
                __( 'Payment configuration error. Please contact the site administrator.', 'opay-payment-gateway' ),
                'error'
            );
            return [ 'result' => 'failure' ];
        }

        // Build payload
        $payload = [
            'amount'      => (int) round( $order->get_total() * 100 ), // Convert to cents
            'currency'    => strtoupper( get_woocommerce_currency() ),
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
                    __( 'Payment error: %s', 'opay-payment-gateway' ),
                    esc_html( $result['error'] )
                ),
                'error'
            );
            return [ 'result' => 'failure' ];
        }

        $checkout_url   = $result['data']['checkout_url'] ?? $result['data']['url'] ?? '';
        $transaction_id = $result['data']['id'] ?? '';

        if ( ! $checkout_url ) {
            wc_add_notice( __( 'Payment gateway did not return a checkout URL.', 'opay-payment-gateway' ), 'error' );
            return [ 'result' => 'failure' ];
        }

        // Mark order as pending payment and store transaction ID
        $order->update_status( 'pending', __( 'Awaiting Opay payment.', 'opay-payment-gateway' ) );
        $order->update_meta_data( '_opay_transaction_id', $transaction_id );
        $order->add_order_note(
            sprintf(
                /* translators: %s: Opay transaction ID */
                __( 'Opay payment initiated. Transaction ID: %s', 'opay-payment-gateway' ),
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
