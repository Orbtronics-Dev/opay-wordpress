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
            'opay_login',
            'opay_logout',
            'opay_save_keys',
            'opay_save_settings',
            'opay_create_button',
            'opay_delete_button',
            'opay_load_transactions',
            'opay_load_buttons',
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
        add_menu_page(
            __( 'Opay Payments', 'opay-payment-gateway' ),
            __( 'Opay Payments', 'opay-payment-gateway' ),
            'manage_options',
            'opay-settings',
            [ $this, 'render_settings_page' ],
            'dashicons-money-alt',
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

        add_submenu_page(
            'opay-settings',
            __( 'Transactions', 'opay-payment-gateway' ),
            __( 'Transactions', 'opay-payment-gateway' ),
            'manage_options',
            'opay-transactions',
            [ $this, 'render_transactions_page' ]
        );

        add_submenu_page(
            'opay-settings',
            __( 'Payment Buttons', 'opay-payment-gateway' ),
            __( 'Payment Buttons', 'opay-payment-gateway' ),
            'manage_options',
            'opay-payment-buttons',
            [ $this, 'render_buttons_page' ]
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueue_assets( string $hook ): void {
        $opay_pages = [
            'toplevel_page_opay-settings',
            'opay-payments_page_opay-transactions',
            'opay-payments_page_opay-payment-buttons',
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

    public function render_transactions_page(): void {
        require OPAY_PLUGIN_DIR . 'admin/views/page-transactions.php';
    }

    public function render_buttons_page(): void {
        require OPAY_PLUGIN_DIR . 'admin/views/page-payment-buttons.php';
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
            case 'opay_login':
                $this->ajax_login();
                break;
            case 'opay_logout':
                $this->ajax_logout();
                break;
            case 'opay_save_keys':
                $this->ajax_save_keys();
                break;
            case 'opay_save_settings':
                $this->ajax_save_settings();
                break;
            case 'opay_create_button':
                $this->ajax_create_button();
                break;
            case 'opay_delete_button':
                $this->ajax_delete_button();
                break;
            case 'opay_load_transactions':
                $this->ajax_load_transactions();
                break;
            case 'opay_load_buttons':
                $this->ajax_load_buttons();
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

    private function ajax_login(): void {
        $email    = sanitize_email( $_POST['email'] ?? '' );
        $password = $_POST['password'] ?? '';

        if ( ! $email || ! $password ) {
            wp_send_json_error( [ 'message' => 'Email and password are required.' ] );
        }

        $result = Opay_API::login( $email, $password );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        $token = $result['data']['token'] ?? '';
        if ( ! $token ) {
            wp_send_json_error( [ 'message' => 'No token in response.' ] );
        }

        Opay_Auth::set_sanctum_token( $token );

        // Auto-fetch API keys after login
        $keys_result = Opay_API::get_api_keys();

        wp_send_json_success( [
            'user'    => $result['data']['user'] ?? [],
            'client'  => $result['data']['client'] ?? [],
            'api_keys' => $keys_result['data'] ?? [],
        ] );
    }

    private function ajax_logout(): void {
        Opay_Auth::clear_sanctum_token();
        wp_send_json_success( [ 'message' => 'Logged out.' ] );
    }

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

    private function ajax_create_button(): void {
        $data = [
            'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
            'amount'      => (int) ( $_POST['amount'] ?? 0 ),
            'currency'    => sanitize_text_field( $_POST['currency'] ?? 'USD' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'mode'        => sanitize_key( $_POST['mode'] ?? Opay_Auth::get_environment() ),
        ];

        if ( ! $data['name'] || ! $data['amount'] ) {
            wp_send_json_error( [ 'message' => 'Name and amount are required.' ] );
        }

        $result = Opay_API::create_payment_button( $data );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        wp_send_json_success( $result['data'] );
    }

    private function ajax_delete_button(): void {
        $id = sanitize_text_field( $_POST['id'] ?? '' );

        if ( ! $id ) {
            wp_send_json_error( [ 'message' => 'Button ID is required.' ] );
        }

        $result = Opay_API::delete_payment_button( $id );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        wp_send_json_success( [ 'message' => 'Button deleted.' ] );
    }

    private function ajax_load_transactions(): void {
        $filters = [
            'page'    => max( 1, (int) ( $_POST['page'] ?? 1 ) ),
            'search'  => sanitize_text_field( $_POST['search'] ?? '' ),
            'status'  => sanitize_text_field( $_POST['status'] ?? '' ),
            'from'    => sanitize_text_field( $_POST['from'] ?? '' ),
            'to'      => sanitize_text_field( $_POST['to'] ?? '' ),
        ];

        $result = Opay_API::list_transactions( array_filter( $filters ) );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        wp_send_json_success( $result['data'] );
    }

    private function ajax_load_buttons(): void {
        $mode   = sanitize_key( $_POST['mode'] ?? Opay_Auth::get_environment() );
        $result = Opay_API::list_payment_buttons( $mode );

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        wp_send_json_success( $result['data'] );
    }

    private function ajax_refresh_api_keys(): void {
        $result = Opay_API::get_api_keys();

        if ( $result['error'] ) {
            wp_send_json_error( [ 'message' => $result['error'] ] );
        }

        wp_send_json_success( $result['data'] );
    }
}
