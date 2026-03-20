<?php
/**
 * Opay_Shortcodes — registers [opay_button id="uuid" label="Pay Now"].
 *
 * Fetches button data from the Opay backend (cached for 5 min) and renders
 * a form that POSTs to the backend's /pay/{buttonId}/checkout endpoint.
 */

defined( 'ABSPATH' ) || exit;

class Opay_Shortcodes {

    public function __construct() {
        add_shortcode( 'opay_button', [ $this, 'render_button' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets(): void {
        if ( ! is_singular() ) {
            return;
        }

        global $post;

        // Only load on pages/posts that actually contain the shortcode
        if ( $post && has_shortcode( $post->post_content, 'opay_button' ) ) {
            wp_enqueue_script(
                'opay-public',
                OPAY_PLUGIN_URL . 'assets/public.js',
                [],
                OPAY_VERSION,
                true
            );

            wp_localize_script(
                'opay-public',
                'opayPublic',
                [
                    'backendUrl' => Opay_Auth::get_backend_url(),
                    'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                ]
            );
        }
    }

    /**
     * Shortcode handler.
     *
     * @param array  $atts  Shortcode attributes: id, label
     * @param string $content  Unused
     */
    public function render_button( $atts, string $content = '' ): string {
        $atts = shortcode_atts(
            [
                'id'    => '',
                'label' => __( 'Pay Now', 'opay-payment-gateway' ),
            ],
            $atts,
            'opay_button'
        );

        $button_id = sanitize_text_field( $atts['id'] );

        if ( ! $button_id ) {
            return '<!-- opay_button: missing id attribute -->';
        }

        // Try transient cache first
        $cache_key   = 'opay_button_' . md5( $button_id );
        $button_data = get_transient( $cache_key );

        if ( false === $button_data ) {
            $result = Opay_API::get_public_button( $button_id );

            if ( $result['error'] || ! isset( $result['data'] ) ) {
                return '<!-- opay_button: could not load button data -->';
            }

            $button_data = $result['data'];
            set_transient( $cache_key, $button_data, 5 * MINUTE_IN_SECONDS );
        }

        $label       = esc_html( $atts['label'] );
        $backend_url = esc_attr( Opay_Auth::get_backend_url() );
        $amount      = isset( $button_data['amount'] ) ? (int) $button_data['amount'] : 0;
        $currency    = isset( $button_data['currency'] ) ? strtoupper( $button_data['currency'] ) : 'USD';
        $name        = isset( $button_data['name'] ) ? esc_html( $button_data['name'] ) : '';

        // Format amount for display
        $display_amount = number_format( $amount / 100, 2 );

        ob_start();
        ?>
        <div class="opay-button-wrap"
             data-button-id="<?php echo esc_attr( $button_id ); ?>"
             data-backend-url="<?php echo $backend_url; ?>">
            <button type="button"
                    class="opay-pay-btn"
                    data-button-id="<?php echo esc_attr( $button_id ); ?>">
                <?php echo $label; ?>
                <span class="opay-amount">
                    (<?php echo esc_html( $currency . ' ' . $display_amount ); ?>)
                </span>
            </button>

            <!-- Optional: collect customer info before redirect -->
            <div class="opay-customer-form" style="display:none;">
                <input type="email"
                       class="opay-customer-email"
                       placeholder="<?php esc_attr_e( 'Your email', 'opay-payment-gateway' ); ?>"
                       required />
                <input type="text"
                       class="opay-customer-name"
                       placeholder="<?php esc_attr_e( 'Your name', 'opay-payment-gateway' ); ?>" />
                <button type="button" class="opay-checkout-btn">
                    <?php esc_html_e( 'Continue to Payment', 'opay-payment-gateway' ); ?>
                </button>
            </div>

            <div class="opay-button-error" style="display:none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
