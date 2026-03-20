<?php
/**
 * Opay_API — thin HTTP client wrapping wp_remote_* calls to the Opay backend.
 *
 * Auth strategy:
 *   /api/v1/*              → Bearer {secret_api_key}
 *   /api/auth/*            → no auth
 *   /api/account/*         → Bearer {sanctum_token}
 *   /api/payment-buttons/* → Bearer {sanctum_token}
 *   /api/pay/*             → no auth
 */

defined( 'ABSPATH' ) || exit;

class Opay_API {

    // -------------------------------------------------------------------------
    // Internal HTTP helpers
    // -------------------------------------------------------------------------

    private static function base_url(): string {
        $url = Opay_Auth::get_backend_url();
        if ( ! $url ) {
            return '';
        }
        return rtrim( $url, '/' ) . '/api';
    }

    /**
     * Resolve the correct Authorization header for a given path segment.
     */
    private static function auth_header( string $path ): array {
        if ( str_starts_with( $path, '/v1/' ) ) {
            $sk = Opay_Auth::get_sk();
            return $sk ? [ 'Authorization' => 'Bearer ' . $sk ] : [];
        }

        // Dashboard routes need the Sanctum token
        $token = Opay_Auth::get_sanctum_token();
        return $token ? [ 'Authorization' => 'Bearer ' . $token ] : [];
    }

    /**
     * Generic GET request.
     *
     * @param  string $path    e.g. '/account/transactions'
     * @param  array  $params  Query parameters
     * @return array{data: mixed, status: int, error: string|null}
     */
    private static function get( string $path, array $params = [] ): array {
        $base = self::base_url();
        if ( ! $base ) {
            return self::error( 'Backend URL is not configured.' );
        }

        $url = $base . $path;
        if ( $params ) {
            $url = add_query_arg( $params, $url );
        }

        $response = wp_remote_get(
            $url,
            [
                'headers' => array_merge(
                    [ 'Accept' => 'application/json' ],
                    self::auth_header( $path )
                ),
                'timeout' => 15,
            ]
        );

        return self::parse( $response );
    }

    /**
     * Generic POST request.
     *
     * @param  string $path
     * @param  array  $body
     * @return array{data: mixed, status: int, error: string|null}
     */
    private static function post( string $path, array $body = [] ): array {
        $base = self::base_url();
        if ( ! $base ) {
            return self::error( 'Backend URL is not configured.' );
        }

        $response = wp_remote_post(
            $base . $path,
            [
                'headers' => array_merge(
                    [
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json',
                    ],
                    self::auth_header( $path )
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 15,
            ]
        );

        return self::parse( $response );
    }

    /**
     * Generic DELETE request.
     */
    private static function delete( string $path ): array {
        $base = self::base_url();
        if ( ! $base ) {
            return self::error( 'Backend URL is not configured.' );
        }

        $response = wp_remote_request(
            $base . $path,
            [
                'method'  => 'DELETE',
                'headers' => array_merge(
                    [ 'Accept' => 'application/json' ],
                    self::auth_header( $path )
                ),
                'timeout' => 15,
            ]
        );

        return self::parse( $response );
    }

    /**
     * Parse a wp_remote_* response into a normalised array.
     */
    private static function parse( $response ): array {
        if ( is_wp_error( $response ) ) {
            return self::error( $response->get_error_message() );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );
        $data   = json_decode( $body, true );

        if ( $status >= 400 ) {
            $message = isset( $data['message'] ) ? $data['message'] : "HTTP {$status}";
            return [ 'data' => $data, 'status' => $status, 'error' => $message ];
        }

        return [ 'data' => $data, 'status' => $status, 'error' => null ];
    }

    private static function error( string $message ): array {
        return [ 'data' => null, 'status' => 0, 'error' => $message ];
    }

    // -------------------------------------------------------------------------
    // Auth endpoints
    // -------------------------------------------------------------------------

    /**
     * POST /api/auth/login
     *
     * @return array{data: array{token: string, user: array, client: array}|null, ...}
     */
    public static function login( string $email, string $password ): array {
        return self::post( '/auth/login', compact( 'email', 'password' ) );
    }

    // -------------------------------------------------------------------------
    // API-key management (requires Sanctum token)
    // -------------------------------------------------------------------------

    /**
     * GET /api/account/api-keys
     */
    public static function get_api_keys(): array {
        return self::get( '/account/api-keys' );
    }

    /**
     * POST /api/account/api-keys/generate
     *
     * @param string $env  'test' or 'live'
     */
    public static function generate_api_keys( string $env ): array {
        return self::post( '/account/api-keys/generate', [ 'environment' => $env ] );
    }

    /**
     * DELETE /api/account/api-keys/{key}
     */
    public static function delete_api_key( string $key ): array {
        return self::delete( '/account/api-keys/' . rawurlencode( $key ) );
    }

    // -------------------------------------------------------------------------
    // Transactions (requires Sanctum token)
    // -------------------------------------------------------------------------

    /**
     * GET /api/account/transactions
     *
     * @param array $filters  e.g. ['page'=>1,'status'=>'succeeded','search'=>'']
     */
    public static function list_transactions( array $filters = [] ): array {
        return self::get( '/account/transactions', $filters );
    }

    // -------------------------------------------------------------------------
    // Payment Buttons (requires Sanctum token)
    // -------------------------------------------------------------------------

    /**
     * GET /api/payment-buttons?mode=test|live
     */
    public static function list_payment_buttons( string $mode = '' ): array {
        $params = $mode ? [ 'mode' => $mode ] : [];
        return self::get( '/payment-buttons', $params );
    }

    /**
     * POST /api/payment-buttons
     */
    public static function create_payment_button( array $data ): array {
        return self::post( '/payment-buttons', $data );
    }

    /**
     * DELETE /api/payment-buttons/{id}
     */
    public static function delete_payment_button( string $id ): array {
        return self::delete( '/payment-buttons/' . rawurlencode( $id ) );
    }

    // -------------------------------------------------------------------------
    // Public payment API (uses secret API key via VerifyApiKey middleware)
    // -------------------------------------------------------------------------

    /**
     * POST /api/v1/payments
     *
     * @param array $data  Keys: amount (cents), currency, customer, success_url, cancel_url, metadata
     */
    public static function create_payment_session( array $data ): array {
        return self::post( '/v1/payments', $data );
    }

    /**
     * GET /api/v1/payments/{id}
     */
    public static function get_payment_status( string $id ): array {
        return self::get( '/v1/payments/' . rawurlencode( $id ) );
    }

    // -------------------------------------------------------------------------
    // Public payment button endpoints (no auth)
    // -------------------------------------------------------------------------

    /**
     * GET /api/pay/{buttonId}
     */
    public static function get_public_button( string $button_id ): array {
        $base = self::base_url();
        if ( ! $base ) {
            return self::error( 'Backend URL is not configured.' );
        }

        $response = wp_remote_get(
            $base . '/pay/' . rawurlencode( $button_id ),
            [
                'headers' => [ 'Accept' => 'application/json' ],
                'timeout' => 10,
            ]
        );

        return self::parse( $response );
    }

    /**
     * POST /api/pay/{buttonId}/checkout
     *
     * @param array $data  e.g. ['customer_email'=>'...','customer_name'=>'...']
     */
    public static function create_public_checkout( string $button_id, array $data ): array {
        $base = self::base_url();
        if ( ! $base ) {
            return self::error( 'Backend URL is not configured.' );
        }

        $response = wp_remote_post(
            $base . '/pay/' . rawurlencode( $button_id ) . '/checkout',
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'body'    => wp_json_encode( $data ),
                'timeout' => 15,
            ]
        );

        return self::parse( $response );
    }
}
