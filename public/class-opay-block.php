<?php

/**
 * Opay_Block — registers the opay/payment-button Gutenberg block.
 *
 * The block is built from block/block.json and outputs a static
 * [opay_button id="..."] shortcode that is processed by Opay_Shortcodes.
 */
defined( 'ABSPATH' ) || exit;

class Opay_Block
{

    public function __construct()
    {
        add_action( 'init', [ $this, 'register_block' ] );
    }

    public function register_block(): void
    {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        register_block_type(
            OPAY_PLUGIN_DIR . 'block/block.json',
            [
                'render_callback' => [ $this, 'render' ],
            ]
        );

        // Pass button list to the block editor script
        add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_data' ] );
    }

    /**
     * Server-side render callback: output the shortcode for front-end display.
     */
    public function render( array $attributes ): string
    {
        $button_id = sanitize_text_field( $attributes['buttonId'] ?? '' );
        $label     = sanitize_text_field( $attributes['label'] ?? __( 'Pay Now', 'opay-payment-gateway' ) );

        if ( ! $button_id ) {
            return '';
        }

        return do_shortcode( '[opay_button id="' . esc_attr( $button_id ) . '" label="' . esc_attr( $label ) . '"]' );
    }

    /**
     * Pass available payment buttons to the block editor for the dropdown.
     */
    public function enqueue_editor_data(): void
    {
        $result  = Opay_API::list_payment_buttons( Opay_Auth::get_environment() );
        $buttons = [];

        if ( ! $result['error'] && isset( $result['data'] ) ) {
            $raw = $result['data']['data'] ?? $result['data'];

            if ( is_array( $raw ) ) {
                foreach ( $raw as $btn ) {
                    $buttons[] = [
                        'value' => $btn['id'] ?? '',
                        'label' => sprintf(
                            '%s — %s %s',
                            $btn['name'] ?? 'Button',
                            strtoupper( $btn['currency'] ?? 'USD' ),
                            number_format( ( $btn['amount'] ?? 0 ) / 100, 2 )
                        ),
                    ];
                }
            }
        }

        wp_localize_script(
            'opay-payment-button-editor-script',
            'opayBlockData',
            [
                'buttons'     => $buttons,
                'environment' => Opay_Auth::get_environment(),
            ]
        );
    }
}
