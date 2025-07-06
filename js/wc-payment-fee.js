jQuery( function( $ ) {
    // On payment method change, trigger update_checkout to recalculate fees
    $( 'form.checkout' ).on( 'change', 'input[name="payment_method"]', function() {
        $( document.body ).trigger( 'update_checkout' );
    } );
} );
