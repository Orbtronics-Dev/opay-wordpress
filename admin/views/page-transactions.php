<?php
/**
 * Admin transactions page — paginated, AJAX-loaded table.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap opay-admin-wrap">
    <h1><?php esc_html_e( 'Opay — Transactions', 'opay-payment-gateway' ); ?></h1>

    <?php if ( ! Opay_Auth::is_authenticated() && ! Opay_Auth::has_api_keys() ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    /* translators: %s: link to settings page */
                    esc_html__( 'Configure your Opay credentials on the %s before viewing transactions.', 'opay-payment-gateway' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=opay-settings' ) ) . '">' . esc_html__( 'Settings page', 'opay-payment-gateway' ) . '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Search / filter toolbar -->
    <div class="opay-toolbar">
        <input type="text" id="opay-tx-search" placeholder="<?php esc_attr_e( 'Search by ID, email…', 'opay-payment-gateway' ); ?>" class="regular-text" />

        <select id="opay-tx-status">
            <option value=""><?php esc_html_e( 'All statuses', 'opay-payment-gateway' ); ?></option>
            <option value="succeeded"><?php esc_html_e( 'Succeeded', 'opay-payment-gateway' ); ?></option>
            <option value="pending"><?php esc_html_e( 'Pending', 'opay-payment-gateway' ); ?></option>
            <option value="failed"><?php esc_html_e( 'Failed', 'opay-payment-gateway' ); ?></option>
            <option value="refunded"><?php esc_html_e( 'Refunded', 'opay-payment-gateway' ); ?></option>
        </select>

        <input type="date" id="opay-tx-from" title="<?php esc_attr_e( 'From', 'opay-payment-gateway' ); ?>" />
        <input type="date" id="opay-tx-to" title="<?php esc_attr_e( 'To', 'opay-payment-gateway' ); ?>" />

        <button class="button" id="opay-tx-search-btn">
            <?php esc_html_e( 'Search', 'opay-payment-gateway' ); ?>
        </button>
    </div>

    <!-- Transactions table -->
    <div id="opay-transactions-wrap">
        <table class="wp-list-table widefat fixed striped opay-tx-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'ID', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Customer', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Currency', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Mode', 'opay-payment-gateway' ); ?></th>
                </tr>
            </thead>
            <tbody id="opay-tx-body">
                <tr>
                    <td colspan="7" class="opay-loading">
                        <?php esc_html_e( 'Loading transactions…', 'opay-payment-gateway' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Pagination -->
        <div id="opay-tx-pagination" class="opay-pagination"></div>
    </div>
</div>
