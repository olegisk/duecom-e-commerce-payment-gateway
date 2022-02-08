jQuery( function($) {
    Due.load.init(checkout_data_array.due_env);
    var $form = $('form.checkout,form#order_review');
    var $place_order = $('#place_order');
    function dueFormHandler(event){
        if ($('#payment_method_wpdp_due_payments').is(':checked')){
            if (!$('.due_risk_url').length){
                event.stopImmediatePropagation();
                $place_order.prop('disabled', true);
                if(validateCardForm()){
                    $form.append( '<input type="hidden" id="due_risk_url" class="due_risk_url" name="due_risk_url" value="' + window.location.href + '"/>' );
                    dueCheckout(dueResponseHandler);
                    return false;
                }
            }
        }
        $place_order.prop('disabled', false);
        return true;
    }

    function validateCardForm () {
        $form.find( '.woocommerce-error' ).remove();
        return true;
    }

    function dueResponseHandler ( data ) {
        if(data.current_url){
            $form
                .append( '<input type="hidden" id="due_card_id" class="due_card_id" name="due_card_id" value="' + data.card_id + '"/>' )
                .append( '<input type="hidden" id="due_risk_ip" class="due_risk_ip" name="due_risk_ip" value="' + data.customer_ip + '"/>' )
                .append( '<input type="hidden" id="due_risk_token" class="due_risk_token" name="due_risk_token" value="' + data.risk_token + '"/>' );
            $('#wpdp_due_payments-card-number').val('');
            $('#wpdp_due_payments-card-cvc').val('');
            $('#wpdp_due_payments-card-expiry').val('');
            $form.submit();
        }else{
            $( '.due_risk_token, .due_risk_ip, .due_risk_url, .due_risk_url' ).remove();
            $('#place_order').prop('disabled', false);
        }

        $( '.due_risk_token, .due_risk_ip, .due_risk_url, .due_risk_url' ).remove();
    }

    function dueCheckout(callback){
        var billing_name = ($('#billing_first_name').val() +' '+ $('#billing_last_name').val()).trim();
        var email = $('#billing_email').val();
        var postal_code = $('#billing_postcode').val();
        var card_number = $('#wpdp_due_payments-card-number').val();
        var card_cvv = $('#wpdp_due_payments-card-cvc').val();
        var card_exp = $('#wpdp_due_payments-card-expiry').val();
        var card_month= card_exp.substr(0, card_exp.indexOf('/')).trim();
        var card_year= card_exp.substr(card_exp.indexOf('/')+1).trim();

        Due.payments.card.create({
            "name":             billing_name,
            "email":            email,
            "card_number":      card_number,
            "cvv":              card_cvv,
            "exp_month":        card_month,
            "exp_year":         card_year,
            "address": {
                "postal_code":  postal_code
            }
        }, function(data) {
            callback(data);
        });
    }

    $('form.checkout').on( 'checkout_place_order', dueFormHandler );
    $( 'form#order_review' ).on( 'submit', dueFormHandler );
});