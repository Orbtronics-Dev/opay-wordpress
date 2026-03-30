<?php

/**
 * Uninstall — runs when the plugin is deleted from the WordPress admin.
 * Removes all stored options and drops the webhook log table.
 */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Remove all plugin options
$opay_options = [
    'opay_backend_url',
    'opay_environment',
    'opay_test_pk',
    'opay_test_sk',
    'opay_live_pk',
    'opay_live_sk',
    'opay_sanctum_token',
    'opay_sanctum_expires',
    'opay_db_version',
];

foreach ( $opay_options as $opay_option ) {
    delete_option( $opay_option );
}

// Drop the webhook log table
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}opay_webhook_log" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

// Clear any payment button transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_opay_button_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_opay_button_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
