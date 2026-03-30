<?php

/**
 * Opay_Admin — registers admin menus, settings, and AJAX handlers.
 */
defined( 'ABSPATH' ) || exit;

class Opay_Admin
{

    public function __construct()
    {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX handlers
        $ajax_actions = [
            'opay_save_keys',
            'opay_save_settings',
            'opay_refresh_api_keys',
            'opay_login',
            'opay_logout',
            'opay_load_transactions',
            'opay_load_buttons',
            'opay_create_button',
            'opay_delete_button',
        ];

        foreach ( $ajax_actions as $action ) {
            add_action( 'wp_ajax_' . $action, [ $this, 'handle_ajax' ] );
        }
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public function register_menu(): void
    {
        $icon_path = OPAY_PLUGIN_DIR . 'assets/icon.svg';
        $menu_icon = file_exists( $icon_path )
            ? 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( $icon_path ) ) // phpcs:ignore WordPress.WP.AlternativeFunctions
            : 'dashicons-money-alt';

        add_menu_page(
            __( 'Opay Payments', 'opay-payment-gateway' ),
            __( 'Opay Payments', 'opay-payment-gateway' ),
            'manage_options',
            'opay-settings',
            [ $this, 'render_settings_page' ],
            $menu_icon,
            56
        );

        add_submenu_page(
            'opay-settings',
            __( 'Settings', 'opay-payment-gateway' ),
            __( 'Settings', 'opay-payment-gateway' ),
            'manage_options',
            'opay-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueue_assets( string $hook ): void
    {
        $opay_pages = [
            'toplevel_page_opay-settings',
        ];

        if ( ! in_array( $hook, $opay_pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'opay-admin',
            OPAY_PLUGIN_URL . 'assets/admin.css',
            [],
            OPAY_VERSION
        );

        wp_enqueue_script(
            'opay-admin',
            OPAY_PLUGIN_URL . 'assets/admin.js',
            [ 'jquery' ],
            OPAY_VERSION,
            true
        );

        wp_localize_script(
            'opay-admin',
            'opayAdmin',
            [
                'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
                'nonce'      => wp_create_nonce( 'opay_admin_nonce' ),
                'backendUrl' => Opay_Auth::get_backend_url(),
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Page renderers
    // -------------------------------------------------------------------------

    public function render_settings_page(): void
    {
        require OPAY_PLUGIN_DIR . 'admin/views/page-settings.php';
    }

    // -------------------------------------------------------------------------
    // AJAX dispatcher
    // -------------------------------------------------------------------------

    public function handle_ajax(): void
    {
        $action = sanitize_key( $_POST['action'] ?? '' );

        if ( ! check_ajax_referer( 'opay_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions.' ], 403 );
        }

        switch ( $action ) {
            case 'opay_save_keys':
                $this->ajax_save_keys();
                break;

            case 'opay_save_settings':
                $this->ajax_save_settings();
                break;

            case 'opay_refresh_api_keys':
                $this->ajax_refresh_api_keys();
                break;

            case 'opay_login':
                $this->ajax_login();
                break;

            case 'opay_logout':
                $this->ajax_logout();
                break;

            case 'opay_load_transactions':
                $this->ajax_load_transactions();
                break;

            case 'opay_load_buttons':
                $this->ajax_load_buttons();
                break;

            case 'opay_create_button':
                $this->ajax_create_button();
                break;

            case 'opay_delete_button':
                $this->ajax_delete_button();
                break;
            default:
                wp_send_json_error( [ 'message' => 'Unknown action.' ], 400 );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    private function ajax_save_keys(): void
    {
        // Nonce already verified in handle_ajax() before this method is called.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $env = sanitize_key( $_POST['environment'] ?? Opay_Auth::get_environment() );
        $pk  = sanitize_text_field( wp_unslash( $_POST['pk'] ?? '' ) );
        $sk  = sanitize_text_field( wp_unslash( $_POST['sk'] ?? '' ) );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( $pk ) {
            Opay_Auth::set_pk( $pk, $env );
        }

        if ( $sk ) {
            Opay_Auth::set_sk( $sk, $env );
        }

        wp_send_json_success( [ 'message' => 'API keys saved.' ] );
    }

    private function ajax_save_settings(): void
    {
        // Nonce already verified in handle_ajax() before this method is called.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $backend_url = esc_url_raw( wp_unslash( $_POST['backend_url'] ?? '' ) );
        $environment = sanitize_key( $_POST['environment'] ?? 'test' );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ( $backend_url ) {
            Opay_Auth::set_backend_url( $backend_url );
        }
        Opay_Auth::set_environment( $environment );

        // Only update webhook secret when explicitly submitted (key present in POST)
        if ( array_key_exists( 'webhook_secret', $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            Opay_Auth::set_webhook_secret(
                sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
            );
        }

        wp_send_json_success( [ 'message' => 'Settings saved.' ] );
    }

    private function ajax_refresh_api_keys(): void
    {
        $result = Opay_API::get_api_keys();

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        wp_send_json_success( $result['data'] );
    }

    private function ajax_login(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        $password = wp_unslash( $_POST['password'] ?? '' );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $result = Opay_API::login( $email, $password );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );

            return;
        }

        $token = $result['data']['token'] ?? '';

        if ( ! $token ) {
            wp_send_json_error( [ 'message' => 'No token in response.' ] );

            return;
        }

        Opay_Auth::set_sanctum_token( $token );
        wp_send_json_success( [ 'message' => 'Logged in.' ] );
    }

    private function ajax_logout(): void
    {
        Opay_Auth::clear_sanctum_token();
        wp_send_json_success( [ 'message' => 'Logged out.' ] );
    }

    private function ajax_load_transactions(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $filters = [ 'page' => max( 1, (int) ( $_POST['page'] ?? 1 ) ) ];

        foreach ( [ 'search', 'status', 'from', 'to' ] as $key ) {
            $val = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );

            if ( '' !== $val ) {
                $filters[ $key ] = $val;
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $result = Opay_API::list_transactions( $filters );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );

            return;
        }

        wp_send_json_success( $result['data'] );
    }

    private function ajax_load_buttons(): void
    {
        $mode   = sanitize_key( $_POST['mode'] ?? Opay_Auth::get_environment() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $result = Opay_API::list_payment_buttons( $mode );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );

            return;
        }

        wp_send_json_success( $result['data'] );
    }

    private function ajax_create_button(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $data = [
            'name'        => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
            'amount'      => (int) ( $_POST['amount'] ?? 0 ),
            'currency'    => strtoupper( sanitize_text_field( wp_unslash( $_POST['currency'] ?? '' ) ) ),
            'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
            'mode'        => sanitize_key( $_POST['mode'] ?? Opay_Auth::get_environment() ),
        ];
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $result = Opay_API::create_payment_button( $data );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );

            return;
        }

        wp_send_json_success( $result['data'] );
    }

    private function ajax_delete_button(): void
    {
        $id = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'Missing button ID.' ] );

            return;
        }

        $result = Opay_API::delete_payment_button( $id );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );

            return;
        }

        wp_send_json_success( [ 'message' => 'Button deleted.' ] );
    }
}
