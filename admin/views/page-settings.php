<?php
/**
 * Admin settings page — API Keys tab + Login with Opay tab.
 */
defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Read-only tab navigation, no data mutation.
$opay_active_tab = sanitize_key( $_GET['tab'] ?? 'api-keys' );
$environment = Opay_Auth::get_environment(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$backend_url = Opay_Auth::get_backend_url(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="wrap opay-admin-wrap">
    <div class="opay-admin-header">
        <img src="<?php echo esc_url( OPAY_PLUGIN_URL . 'assets/orbtronics.svg' ); ?>"
             alt="<?php esc_attr_e( 'Opay', 'opay-payment-gateway' ); ?>"
             class="opay-admin-logo" />
        <h1><?php esc_html_e( 'Settings', 'opay-payment-gateway' ); ?></h1>
    </div>

    <nav class="nav-tab-wrapper">
        <a href="?page=opay-settings&tab=api-keys"
           class="nav-tab <?php echo $opay_active_tab === 'api-keys' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'API Keys', 'opay-payment-gateway' ); ?>
        </a>
<a href="?page=opay-settings&tab=general"
           class="nav-tab <?php echo $opay_active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e( 'General', 'opay-payment-gateway' ); ?>
        </a>
    </nav>

    <div id="opay-notice" class="opay-notice" style="display:none;"></div>

    <?php if ( $opay_active_tab === 'general' ) : ?>
    <!-- ------------------------------------------------------------------ -->
    <!-- General / Backend URL                                                -->
    <!-- ------------------------------------------------------------------ -->
    <div class="opay-card">
        <h2><?php esc_html_e( 'General Settings', 'opay-payment-gateway' ); ?></h2>
        <form id="opay-general-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="opay-backend-url"><?php esc_html_e( 'Backend URL', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <input type="url" id="opay-backend-url" name="backend_url"
                               class="regular-text"
                               value="<?php echo esc_attr( $backend_url ); ?>"
                               placeholder="https://api.opay.example.com" />
                        <p class="description">
                            <?php esc_html_e( 'The URL of your Opay backend server.', 'opay-payment-gateway' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="opay-environment"><?php esc_html_e( 'Environment', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <select id="opay-environment" name="environment">
                            <option value="test" <?php selected( $environment, 'test' ); ?>>
                                <?php esc_html_e( 'Test', 'opay-payment-gateway' ); ?>
                            </option>
                            <option value="live" <?php selected( $environment, 'live' ); ?>>
                                <?php esc_html_e( 'Live', 'opay-payment-gateway' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="opay-webhook-secret"><?php esc_html_e( 'Webhook Secret', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <input type="password" id="opay-webhook-secret" name="webhook_secret"
                               class="regular-text"
                               value="<?php echo esc_attr( Opay_Auth::get_webhook_secret() ? str_repeat( '•', 24 ) : '' ); ?>"
                               autocomplete="new-password"
                               placeholder="<?php esc_attr_e( 'Leave blank to skip signature validation', 'opay-payment-gateway' ); ?>" />
                        <p class="description">
                            <?php esc_html_e( 'Used to verify the X-Opay-Signature header on incoming webhooks. Must match the secret configured on the backend.', 'opay-payment-gateway' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"></th>
                    <td>
                        <p class="description opay-webhook-url">
                            <?php esc_html_e( 'Webhook URL:', 'opay-payment-gateway' ); ?>
                            <code><?php echo esc_url( rest_url( 'opay/v1/webhook' ) ); ?></code>
                        </p>
                    </td>
                </tr>
            </table>
            <p>
                <button type="submit" class="button button-primary" id="opay-save-settings">
                    <?php esc_html_e( 'Save Settings', 'opay-payment-gateway' ); ?>
                </button>
            </p>
        </form>
    </div>

    <?php elseif ( $opay_active_tab === 'api-keys' ) : ?>
    <!-- ------------------------------------------------------------------ -->
    <!-- API Keys                                                              -->
    <!-- ------------------------------------------------------------------ -->
    <div class="opay-card">
        <h2><?php esc_html_e( 'API Keys', 'opay-payment-gateway' ); ?></h2>
        <p><?php esc_html_e( 'Paste your Opay API keys below. Secret keys are stored encrypted.', 'opay-payment-gateway' ); ?></p>

        <?php foreach ( [ 'test', 'live' ] as $env ) : // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound ?>
        <h3><?php echo esc_html( ucfirst( $env ) ) . ' ' . esc_html__( 'Keys', 'opay-payment-gateway' ); ?></h3>
        <form class="opay-keys-form" data-env="<?php echo esc_attr( $env ); ?>">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Publishable Key', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="pk" class="regular-text opay-pk-field"
                               value="<?php echo esc_attr( Opay_Auth::get_pk( $env ) ); ?>"
                               placeholder="opay_<?php echo esc_attr( $env ); ?>_pk_..." />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label><?php esc_html_e( 'Secret Key', 'opay-payment-gateway' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="sk" class="regular-text opay-sk-field"
                               value="<?php echo esc_attr( Opay_Auth::get_sk( $env ) ? str_repeat( '•', 24 ) : '' ); ?>"
                               placeholder="opay_<?php echo esc_attr( $env ); ?>_sk_..."
                               autocomplete="new-password" />
                        <p class="description">
                            <?php esc_html_e( 'Leave blank to keep the existing secret key.', 'opay-payment-gateway' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <button type="submit" class="button button-secondary">
                <?php
                /* translators: %s: environment name, e.g. "Test" or "Live" */
                printf( esc_html__( 'Save %s Keys', 'opay-payment-gateway' ), esc_html( ucfirst( $env ) ) );
                ?>
            </button>
        </form>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</div>
