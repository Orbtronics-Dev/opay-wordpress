<?php
/**
 * Admin payment buttons page — list, create, delete.
 */
defined( 'ABSPATH' ) || exit;

$environment = Opay_Auth::get_environment();
?>
<div class="wrap opay-admin-wrap">
    <h1><?php esc_html_e( 'Opay — Payment Buttons', 'opay-payment-gateway' ); ?></h1>

    <?php if ( ! Opay_Auth::is_authenticated() && ! Opay_Auth::has_api_keys() ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php
                printf(
                    /* translators: %s: link to settings page */
                    esc_html__( 'Configure your Opay credentials on the %s first.', 'opay-payment-gateway' ),
                    '<a href="' . esc_url( admin_url( 'admin.php?page=opay-settings' ) ) . '">' . esc_html__( 'Settings page', 'opay-payment-gateway' ) . '</a>'
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div id="opay-notice" class="opay-notice" style="display:none;"></div>

    <!-- Create button form -->
    <div class="opay-card">
        <h2><?php esc_html_e( 'Create Payment Button', 'opay-payment-gateway' ); ?></h2>
        <form id="opay-create-button-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="opay-btn-name"><?php esc_html_e( 'Name', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="opay-btn-name" name="name" class="regular-text" required
                               placeholder="<?php esc_attr_e( 'e.g. Product Checkout', 'opay-payment-gateway' ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="opay-btn-amount"><?php esc_html_e( 'Amount (cents)', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <input type="number" id="opay-btn-amount" name="amount" class="small-text"
                               min="1" required placeholder="1000" />
                        <p class="description">
                            <?php esc_html_e( 'Enter the amount in cents. 1000 = $10.00', 'opay-payment-gateway' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="opay-btn-currency"><?php esc_html_e( 'Currency', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <input type="text" id="opay-btn-currency" name="currency" class="small-text"
                               value="USD" maxlength="3" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="opay-btn-description"><?php esc_html_e( 'Description', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <textarea id="opay-btn-description" name="description" rows="3"
                                  class="regular-text"></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="opay-btn-mode"><?php esc_html_e( 'Mode', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <select id="opay-btn-mode" name="mode">
                            <option value="test" <?php selected( $environment, 'test' ); ?>>
                                <?php esc_html_e( 'Test', 'opay-payment-gateway' ); ?>
                            </option>
                            <option value="live" <?php selected( $environment, 'live' ); ?>>
                                <?php esc_html_e( 'Live', 'opay-payment-gateway' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
            </table>
            <p>
                <button type="submit" class="button button-primary" id="opay-create-btn-submit">
                    <?php esc_html_e( 'Create Button', 'opay-payment-gateway' ); ?>
                </button>
                <span class="spinner" id="opay-btn-spinner"></span>
            </p>
        </form>
    </div>

    <!-- Existing buttons table -->
    <div class="opay-card">
        <h2>
            <?php esc_html_e( 'Existing Payment Buttons', 'opay-payment-gateway' ); ?>
            <button class="button button-small" id="opay-refresh-buttons" style="margin-left:10px;">
                <?php esc_html_e( 'Refresh', 'opay-payment-gateway' ); ?>
            </button>
        </h2>

        <table class="wp-list-table widefat fixed striped" id="opay-buttons-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Amount', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Currency', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Mode', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Shortcode', 'opay-payment-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'opay-payment-gateway' ); ?></th>
                </tr>
            </thead>
            <tbody id="opay-buttons-body">
                <tr>
                    <td colspan="6" class="opay-loading">
                        <?php esc_html_e( 'Loading payment buttons…', 'opay-payment-gateway' ); ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
