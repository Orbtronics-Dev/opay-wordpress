<?php

/**
 * Opay_Blocks_Payment_Method — registers the Opay gateway with the
 * WooCommerce Checkout Block via AbstractPaymentMethodType.
 */
defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Opay_Blocks_Payment_Method extends AbstractPaymentMethodType
{

    /** @var string Must match the gateway ID. */
    protected $name = 'opay';

    public function initialize(): void
    {
        $this->settings = get_option( 'woocommerce_opay_settings', [] );
    }

    public function is_active(): bool
    {
        return 'yes' === $this->get_setting( 'enabled', 'no' );
    }

    /**
     * Returns the script handle(s) that should be enqueued on the checkout page.
     */
    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            'wc-opay-blocks-integration',
            OPAY_PLUGIN_URL . 'assets/wc-opay-blocks.js',
            [ 'wc-blocks-registry', 'wc-settings', 'wp-element' ],
            OPAY_VERSION,
            true
        );

        return [ 'wc-opay-blocks-integration' ];
    }

    /**
     * Data exposed to the JS as getSetting('opay_data').
     */
    public function get_payment_method_data(): array
    {
        return [
            'title'       => $this->get_setting( 'title', __( 'Pay with Opay', 'opay-payment-gateway' ) ),
            'description' => $this->get_setting( 'description', __( 'Secure payment powered by Opay.', 'opay-payment-gateway' ) ),
            'icon'        => OPAY_PLUGIN_URL . 'assets/orbtronics.svg',
            // Hardcode the supported features — get_supported_features() relies on
            // WC payment gateways being fully initialised which is not guaranteed
            // at the point this data is collected for the Checkout Block.
            'supports'    => [ 'products' ],
        ];
    }
}
