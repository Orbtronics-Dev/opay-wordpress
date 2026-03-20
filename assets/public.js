/**
 * Opay Public — payment button click handler.
 *
 * Localised as: opayPublic { backendUrl, ajaxUrl }
 *
 * Flow:
 *   1. User clicks .opay-pay-btn
 *   2. Show inline customer info form (.opay-customer-form)
 *   3. User fills email/name and clicks "Continue to Payment"
 *   4. POST to {backendUrl}/api/pay/{buttonId}/checkout
 *   5. On success, redirect to checkout_url
 */

( function () {
    'use strict';

    const { backendUrl = '' } = window.opayPublic || {};

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function showError( $wrap, message ) {
        const $err = $wrap.querySelector( '.opay-button-error' );
        if ( $err ) {
            $err.textContent = message;
            $err.style.display = 'block';
        }
    }

    function hideError( $wrap ) {
        const $err = $wrap.querySelector( '.opay-button-error' );
        if ( $err ) {
            $err.style.display = 'none';
        }
    }

    async function doCheckout( buttonId, customerData, $wrap ) {
        hideError( $wrap );

        const $checkout = $wrap.querySelector( '.opay-checkout-btn' );
        if ( $checkout ) {
            $checkout.disabled = true;
            $checkout.textContent = 'Processing…';
        }

        try {
            const response = await fetch(
                `${ backendUrl }/api/pay/${ encodeURIComponent( buttonId ) }/checkout`,
                {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify( customerData ),
                }
            );

            const data = await response.json();

            if ( ! response.ok ) {
                throw new Error( data.message || `HTTP ${ response.status }` );
            }

            const url = data.checkout_url || data.url;
            if ( ! url ) {
                throw new Error( 'No checkout URL returned.' );
            }

            window.location.href = url;

        } catch ( err ) {
            showError( $wrap, err.message || 'Payment failed. Please try again.' );

            if ( $checkout ) {
                $checkout.disabled = false;
                $checkout.textContent = 'Continue to Payment';
            }
        }
    }

    // -------------------------------------------------------------------------
    // Event delegation
    // -------------------------------------------------------------------------

    document.addEventListener( 'click', function ( e ) {

        // "Pay Now" button — show customer form
        const $payBtn = e.target.closest( '.opay-pay-btn' );
        if ( $payBtn && ! $payBtn.disabled ) {
            const $wrap = $payBtn.closest( '.opay-button-wrap' );
            if ( ! $wrap ) return;

            $payBtn.style.display = 'none';
            const $form = $wrap.querySelector( '.opay-customer-form' );
            if ( $form ) {
                $form.style.display = 'block';
                const $emailInput = $form.querySelector( '.opay-customer-email' );
                if ( $emailInput ) $emailInput.focus();
            }
        }

        // "Continue to Payment" button
        const $checkoutBtn = e.target.closest( '.opay-checkout-btn' );
        if ( $checkoutBtn ) {
            const $wrap    = $checkoutBtn.closest( '.opay-button-wrap' );
            if ( ! $wrap ) return;

            const buttonId = $wrap.dataset.buttonId || '';
            const email    = ( $wrap.querySelector( '.opay-customer-email' )?.value || '' ).trim();
            const name     = ( $wrap.querySelector( '.opay-customer-name' )?.value || '' ).trim();

            if ( ! email || ! email.includes( '@' ) ) {
                showError( $wrap, 'Please enter a valid email address.' );
                return;
            }

            doCheckout( buttonId, { customer_email: email, customer_name: name }, $wrap );
        }
    } );

    // Allow pressing Enter in the email field to proceed
    document.addEventListener( 'keypress', function ( e ) {
        if ( e.key !== 'Enter' ) return;

        const $emailInput = e.target.closest( '.opay-customer-email' );
        if ( ! $emailInput ) return;

        const $checkoutBtn = $emailInput
            .closest( '.opay-customer-form' )
            ?.querySelector( '.opay-checkout-btn' );

        if ( $checkoutBtn ) {
            $checkoutBtn.click();
        }
    } );

} )();
