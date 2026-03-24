<?php
/**
 * Opay_Admin — registers admin menus, settings, and AJAX handlers.
 */

defined( 'ABSPATH' ) || exit;

class Opay_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX handlers
        $ajax_actions = [
            'opay_save_keys',
            'opay_save_settings',
            'opay_refresh_api_keys',
        ];

        foreach ( $ajax_actions as $action ) {
            add_action( 'wp_ajax_' . $action, [ $this, 'handle_ajax' ] );
        }
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public function register_menu(): void {
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

    public function enqueue_assets( string $hook ): void {
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
                'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
                'nonce'     => wp_create_nonce( 'opay_admin_nonce' ),
                'backendUrl' => Opay_Auth::get_backend_url(),
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Page renderers
    // -------------------------------------------------------------------------

    public function render_settings_page(): void {
        require OPAY_PLUGIN_DIR . 'admin/views/page-settings.php';
    }

    // -------------------------------------------------------------------------
    // AJAX dispatcher
    // -------------------------------------------------------------------------

    public function handle_ajax(): void {
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
            default:
                wp_send_json_error( [ 'message' => 'Unknown action.' ], 400 );
        }
    }

    // -------------------------------------------------------------------------
    // AJAX handlers
    // -------------------------------------------------------------------------

    private function ajax_save_keys(): void {
        $env    = sanitize_key( $_POST['environment'] ?? Opay_Auth::get_environment() );
        $pk     = sanitize_text_field( $_POST['pk'] ?? '' );
        $sk     = sanitize_text_field( $_POST['sk'] ?? '' );

        if ( $pk ) {
            Opay_Auth::set_pk( $pk, $env );
        }
        if ( $sk ) {
            Opay_Auth::set_sk( $sk, $env );
        }

        wp_send_json_success( [ 'message' => 'API keys saved.' ] );
    }

    private function ajax_save_settings(): void {
        $backend_url = esc_url_raw( $_POST['backend_url'] ?? '' );
        $environment = sanitize_key( $_POST['environment'] ?? 'test' );

        if ( $backend_url ) {
            Opay_Auth::set_backend_url( $backend_url );
        }
        Opay_Auth::set_environment( $environment );

        wp_send_json_success( [ 'message' => 'Settings saved.' ] );
    }

    private function ajax_refresh_api_keys(): void {
        $result = Opay_API::get_api_keys();

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        wp_send_json_success( $result['data'] );
    }
}
