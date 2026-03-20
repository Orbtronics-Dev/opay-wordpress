<?php
/**
 * Opay_Auth — credential storage and retrieval.
 *
 * Secrets (SK, Sanctum token) are encrypted with AES-256-CBC using AUTH_KEY
 * as the key material.  Publishable keys are stored in plain text since they
 * are intentionally public-facing.
 */

defined( 'ABSPATH' ) || exit;

class Opay_Auth {

    // -------------------------------------------------------------------------
    // Encryption helpers
    // -------------------------------------------------------------------------

    /**
     * Encrypt a string.  Returns base64-encoded cipher text or false on failure.
     */
    public static function encrypt( string $value ): string|false {
        $key    = substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
        $iv_len = openssl_cipher_iv_length( 'AES-256-CBC' );
        $iv     = openssl_random_pseudo_bytes( $iv_len );
        $cipher = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );

        if ( false === $cipher ) {
            return false;
        }

        return base64_encode( $iv . $cipher );
    }

    /**
     * Decrypt a value produced by self::encrypt().
     */
    public static function decrypt( string $value ): string|false {
        $key    = substr( hash( 'sha256', AUTH_KEY ), 0, 32 );
        $iv_len = openssl_cipher_iv_length( 'AES-256-CBC' );
        $decoded = base64_decode( $value, true );

        if ( false === $decoded || strlen( $decoded ) <= $iv_len ) {
            return false;
        }

        $iv     = substr( $decoded, 0, $iv_len );
        $cipher = substr( $decoded, $iv_len );

        return openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );
    }

    // -------------------------------------------------------------------------
    // Backend URL / environment
    // -------------------------------------------------------------------------

    public static function get_backend_url(): string {
        return rtrim( (string) get_option( 'opay_backend_url', '' ), '/' );
    }

    public static function set_backend_url( string $url ): void {
        update_option( 'opay_backend_url', esc_url_raw( $url ) );
    }

    public static function get_environment(): string {
        $env = get_option( 'opay_environment', 'test' );
        return in_array( $env, [ 'test', 'live' ], true ) ? $env : 'test';
    }

    public static function set_environment( string $env ): void {
        update_option( 'opay_environment', in_array( $env, [ 'test', 'live' ], true ) ? $env : 'test' );
    }

    // -------------------------------------------------------------------------
    // API Keys
    // -------------------------------------------------------------------------

    public static function get_pk( string $env = '' ): string {
        $env = $env ?: self::get_environment();
        return (string) get_option( "opay_{$env}_pk", '' );
    }

    public static function get_sk( string $env = '' ): string {
        $env       = $env ?: self::get_environment();
        $encrypted = get_option( "opay_{$env}_sk", '' );

        if ( ! $encrypted ) {
            return '';
        }

        $decrypted = self::decrypt( $encrypted );
        return false !== $decrypted ? $decrypted : '';
    }

    public static function set_pk( string $key, string $env = '' ): void {
        $env = $env ?: self::get_environment();
        update_option( "opay_{$env}_pk", sanitize_text_field( $key ) );
    }

    public static function set_sk( string $key, string $env = '' ): void {
        $env       = $env ?: self::get_environment();
        $encrypted = self::encrypt( $key );

        if ( false !== $encrypted ) {
            update_option( "opay_{$env}_sk", $encrypted );
        }
    }

    // -------------------------------------------------------------------------
    // Sanctum token (from Login with Opay)
    // -------------------------------------------------------------------------

    /** Token TTL in seconds — 24 hours. */
    const TOKEN_TTL = 86400;

    public static function set_sanctum_token( string $token ): void {
        $encrypted = self::encrypt( $token );
        if ( false !== $encrypted ) {
            update_option( 'opay_sanctum_token', $encrypted );
            update_option( 'opay_sanctum_expires', time() + self::TOKEN_TTL );
        }
    }

    public static function get_sanctum_token(): string {
        if ( self::is_token_expired() ) {
            return '';
        }

        $encrypted = get_option( 'opay_sanctum_token', '' );
        if ( ! $encrypted ) {
            return '';
        }

        $decrypted = self::decrypt( $encrypted );
        return false !== $decrypted ? $decrypted : '';
    }

    public static function is_token_expired(): bool {
        $expires = (int) get_option( 'opay_sanctum_expires', 0 );
        return $expires === 0 || time() >= $expires;
    }

    public static function clear_sanctum_token(): void {
        delete_option( 'opay_sanctum_token' );
        delete_option( 'opay_sanctum_expires' );
    }

    public static function is_authenticated(): bool {
        return ! self::is_token_expired() && self::get_sanctum_token() !== '';
    }

    // -------------------------------------------------------------------------
    // Convenience: are API keys present?
    // -------------------------------------------------------------------------

    public static function has_api_keys( string $env = '' ): bool {
        return self::get_pk( $env ) !== '' && self::get_sk( $env ) !== '';
    }
}
