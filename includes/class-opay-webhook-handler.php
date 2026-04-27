<?php

/**
 * Opay_Webhook_Handler — registers POST /wp-json/opay/v1/webhook.
 *
 * Logs every incoming event to wp_opay_webhook_log, then fires action hooks
 * and (when WooCommerce is active) updates the corresponding order.
 */
defined( 'ABSPATH' ) || exit;

class Opay_Webhook_Handler
{

    /**
         * Register the REST route.  Called on rest_api_init.
         */
    public static function register_routes(): void
    {
        register_rest_route(
            'opay/v1',
            '/webhook',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'handle' ],
                'permission_callback' => '__return_true', // Auth via payload signature
            ]
        );
    }

    /**
     * Handle an incoming webhook POST.
     */
    public static function handle( WP_REST_Request $request ): WP_REST_Response
    {
        $body = $request->get_body();

        // Validate HMAC-SHA256 signature when a webhook secret is configured.
        $secret = Opay_Auth::get_webhook_secret();

        if ( $secret ) {
            $signature = (string) $request->get_header( 'x-opay-signature' );
            $expected  = hash_hmac( 'sha256', $body, $secret );

            if ( ! hash_equals( $expected, $signature ) ) {
                self::log_invalid_signature( $signature );

                return new WP_REST_Response( [ 'error' => 'Invalid signature' ], 401 );
            }
        }

        $payload = json_decode( $body, true );

        if ( ! is_array( $payload ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid payload' ], 400 );
        }

        $event_type = sanitize_text_field( $payload['event'] ?? $payload['type'] ?? 'unknown' );

        // Capture headers for logging
        $headers = [];

        foreach ( $request->get_headers() as $key => $values ) {
            $headers[ $key ] = implode( ', ', (array) $values );
        }

        // Log to DB
        self::log_event( $event_type, $body, $headers );

        // Fire action hooks
        self::dispatch_hooks( $event_type, $payload );

        return new WP_REST_Response( [ 'received' => true ], 200 );
    }

    // -------------------------------------------------------------------------
    // Action hook dispatch
    // -------------------------------------------------------------------------

    private static function dispatch_hooks( string $event_type, array $payload ): void
    {
        switch ( $event_type ) {
            case 'payment.succeeded':
            case 'payment_intent.succeeded':
                do_action( 'opay_payment_succeeded', $payload );
                self::maybe_complete_wc_order( $payload );
                break;

            case 'payment.failed':
            case 'payment_intent.payment_failed':
                do_action( 'opay_payment_failed', $payload );
                self::maybe_fail_wc_order( $payload );
                break;

            case 'charge.refunded':
            case 'refund.created':
                do_action( 'opay_refund_created', $payload );
                break;

            case 'customer.subscription.updated':
            case 'subscription.updated':
                do_action( 'opay_subscription_updated', $payload );
                break;

            default:
                do_action( 'opay_webhook_received', $event_type, $payload );
        }
    }

    // -------------------------------------------------------------------------
    // WooCommerce order integration
    // -------------------------------------------------------------------------

    private static function maybe_complete_wc_order( array $payload ): void
    {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return;
        }

        $order_id      = $payload['data']['metadata']['order_id']
                      ?? $payload['metadata']['order_id']
                      ?? null;
        $transaction_id = $payload['data']['id']
                       ?? $payload['transaction_id']
                       ?? '';

        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( (int) $order_id );

        if ( ! $order ) {
            return;
        }

        if ( ! in_array( $order->get_status(), [ 'completed', 'processing' ], true ) ) {
            $order->payment_complete( $transaction_id );
            $order->add_order_note(
                sprintf(
                    /* translators: %s: Opay transaction ID */
                    __( 'Opay webhook: payment succeeded. Transaction ID: %s', 'orbtronics-payment-gateway' ),
                    esc_html( $transaction_id )
                )
            );
        }
    }

    private static function maybe_fail_wc_order( array $payload ): void
    {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return;
        }

        $order_id = $payload['data']['metadata']['order_id']
                 ?? $payload['metadata']['order_id']
                 ?? null;

        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( (int) $order_id );

        if ( ! $order ) {
            return;
        }

        if ( ! in_array( $order->get_status(), [ 'failed', 'cancelled' ], true ) ) {
            $order->update_status( 'failed' );
            $order->add_order_note( __( 'Opay webhook: payment failed.', 'orbtronics-payment-gateway' ) );
        }
    }

    // -------------------------------------------------------------------------
    // DB logging
    // -------------------------------------------------------------------------

    private static function log_event( string $event_type, string $payload, array $headers ): void
    {
        global $wpdb;

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prefix . 'opay_webhook_log',
            [
                'event_type' => $event_type,
                'payload'    => $payload,
                'headers'    => wp_json_encode( $headers ),
                'status'     => 'received',
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s' ]
        );
    }

    private static function log_invalid_signature( string $received ): void
    {
        $preview = $received ? ( substr( $received, 0, 8 ) . '…' ) : '(none)';
        wc_get_logger()->warning(
            "Webhook rejected — invalid X-Opay-Signature: {$preview}",
            [ 'source' => 'opay-webhook' ]
        );
    }
}
