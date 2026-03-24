/**
 * Opay — WooCommerce Checkout Block payment method registration.
 *
 * Registers the Opay payment method with the @woocommerce/blocks-registry so
 * it appears inside the block-based Cart and Checkout blocks (WC 7+).
 *
 * Data is passed from PHP via getSetting('opay_data') which is populated by
 * Opay_Blocks_Payment_Method::get_payment_method_data().
 */
( function () {
    'use strict';

    var registerPaymentMethod = window.wc?.wcBlocksRegistry?.registerPaymentMethod;
    var createElement         = window.wp?.element?.createElement;
    var getSetting            = window.wc?.wcSettings?.getSetting;

    // Bail if WC Blocks aren't available (e.g. classic checkout page)
    if ( ! registerPaymentMethod || ! createElement ) {
        return;
    }

    var settings = getSetting ? getSetting( 'opay_data', {} ) : {};
    var title    = settings.title       || 'Pay with Opay';
    var desc     = settings.description || '';
    var icon     = settings.icon        || '';

    // -------------------------------------------------------------------------
    // Label — logo + gateway title
    // -------------------------------------------------------------------------

    function Label() {
        if ( ! icon ) {
            return createElement( 'span', null, title );
        }
        return createElement(
            'span',
            { style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
            [
                createElement( 'img', {
                    key:   'opay-icon',
                    src:   icon,
                    alt:   'Opay',
                    style: { height: '24px', width: 'auto' },
                } ),
                createElement( 'span', { key: 'opay-title' }, title ),
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Content — shown below the label when the method is selected
    // -------------------------------------------------------------------------

    function Content() {
        if ( ! desc ) return null;
        return createElement( 'p', { style: { margin: '8px 0 0' } }, desc );
    }

    // -------------------------------------------------------------------------
    // Register
    // -------------------------------------------------------------------------

    registerPaymentMethod( {
        name:            'opay',
        label:           createElement( Label, null ),
        content:         createElement( Content, null ),
        edit:            createElement( Content, null ),
        canMakePayment:  function () { return true; },
        ariaLabel:       title,
        supports: {
            features: settings.supports || [ 'products' ],
        },
    } );
} )();
