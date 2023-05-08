/**
 * global triibo_payments_card_params, wc_checkout_params
 */

( function( $ )
{
    "use strict";


    $( function()
    {
        var tpc_submit = false;

        /**
         * Initialize mask of the payment form.
         */
        function triibo_payments_card_init_mask()
        {
            // cpf
            $( "#triibo_payments_card-card-holder-cpf"  ).mask( "000.000.000-00" );

            // validate
            $( "#triibo_payments_card-card-expiry"      ).parent().removeClass( "form-row-first" ).addClass( "form-row-wide" );

            // cvc
            $( "#triibo_payments_card-card-cvc"         ).css( "width", "" ).parent().removeClass( "form-row-last" ).addClass( "form-row-wide" );

            // phone
            let MaskBehavior = function( val ) {
                return val.replace( /\D/g, "" ).length === 11 ? "(00) 00000-0000" : "(00) 0000-00009";
            }, maskOptions = {
                onKeyPress: function( val, e, field, options ) { field.mask( MaskBehavior.apply( {}, arguments ), options ); }
            };
            $( "#triibo_payments_card-card-holder-phone" ).mask( MaskBehavior, maskOptions );
        }





        // Display the payment for and init the input masks.
        if ( typeof wc_checkout_params != "undefined" && wc_checkout_params.is_checkout === "1" )
        {
            $( "body" ).on( "updated_checkout", function() {
                triibo_payments_card_init_mask();
            } );
        }
        else
        {
            triibo_payments_card_init_mask();
        }
    } );

}( jQuery ) );