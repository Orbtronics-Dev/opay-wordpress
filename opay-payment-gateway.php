<?php
/**
 * Plugin Name: Opay Payment Gateway
 * Plugin URI:  https://github.com/Orbtronics-Dev/opay-wordpress
 * Description: Integrates Opay payment infrastructure with WordPress and WooCommerce. Supports payment buttons, shortcodes, Gutenberg blocks, and a full WooCommerce checkout gateway.
 * Version:     1.0.0
 * Author:      Opay
 * Author URI:  https://orbtronics.co/opay
 * License:     GPL-2.0+
 * Text Domain: opay-payment-gateway
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 * WC tested up to: 8.0
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'OPAY_VERSION',    '1.0.0' );
define( 'OPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Core includes
require_once OPAY_PLUGIN_DIR . 'includes/class-opay-auth.php';
require_once OPAY_PLUGIN_DIR . 'includes/class-opay-api.php';
require_once OPAY_PLUGIN_DIR . 'includes/class-opay-webhook-handler.php';
require_once OPAY_PLUGIN_DIR . 'admin/class-opay-admin.php';
require_once OPAY_PLUGIN_DIR . 'public/class-opay-shortcodes.php';
require_once OPAY_PLUGIN_DIR . 'public/class-opay-block.php';

/**
 * Activation hook — creates the webhook log table.
 */
function opay_activate(): void {
    global $wpdb;

    $table      = $wpdb->prefix . 'opay_webhook_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        event_type  VARCHAR(100)    NOT NULL DEFAULT '',
        payload     LONGTEXT        NOT NULL,
        headers     TEXT            NOT NULL DEFAULT '',
        status      VARCHAR(20)     NOT NULL DEFAULT 'received',
        created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY event_type (event_type),
        KEY created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    add_option( 'opay_db_version', OPAY_VERSION );
}
register_activation_hook( __FILE__, 'opay_activate' );

/**
 * Boot the plugin after all plugins are loaded.
 */
function opay_init(): void {
    // Admin
    if ( is_admin() ) {
        new Opay_Admin();
    }

    // Public shortcodes
    new Opay_Shortcodes();

    // Gutenberg block
    new Opay_Block();

    // REST webhook endpoint
    add_action( 'rest_api_init', [ 'Opay_Webhook_Handler', 'register_routes' ] );

    // WooCommerce gateway
    add_filter( 'woocommerce_payment_gateways', 'opay_add_wc_gateway' );
}
add_action( 'plugins_loaded', 'opay_init' );

/**
 * Register the WooCommerce payment gateway class.
 */
function opay_add_wc_gateway( array $gateways ): array {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return $gateways;
    }

    require_once OPAY_PLUGIN_DIR . 'woocommerce/class-opay-wc-gateway.php';
    $gateways[] = 'Opay_WC_Gateway';

    return $gateways;
}

/**
 * Declare HPOS compatibility with WooCommerce.
 */
add_action( 'before_woocommerce_init', function (): void {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );