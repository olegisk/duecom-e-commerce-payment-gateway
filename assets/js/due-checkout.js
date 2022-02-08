(function ( $ ) {
	Due.load.init(Due_Woo.env);
	Due.load.setAppId(Due_Woo.appId);

	// Form handler
	function dueFormHandler() {
		var $form = $( 'form.checkout, form#order_review, form#add_payment_method' );
		var $place_order = $('#place_order');

		if ( ( $( '#payment_method_duecom' ).is( ':checked' ) && 'new' === $( 'input[name="wc-duecom-payment-token"]:checked' ).val() ) || ( '1' === $( '#woocommerce_add_payment_method' ).val() ) ) {
			// New Credit Card
			if (!parseInt($('#due_card_id').val())) {
				$place_order.prop('disabled', true);
				dueCreateCard(function (data) {
					$place_order.prop('disabled', false);

					var card_number = $('#duecom-card-number').val();
					var card_cvv = $('#duecom-card-cvc').val();
					var card_exp = $('#duecom-card-expiry').val();
					var card_month = card_exp.substr(0, card_exp.indexOf('/')).trim();
					var card_year = card_exp.substr(card_exp.indexOf('/') + 1).trim();
					var card_type = $.payment.cardType(card_number);
					var card_last4 = card_number.substr(card_number.length - 4);

					if (data.current_url) {
						$('.due_card_meta').remove();

						$form
							.append('<input type="hidden" id="due_card_id" class="due_card_meta" name="due_card_id" value="' + data.card_id + '"/>')
							.append('<input type="hidden" id="due_card_hash" class="due_card_meta" name="due_card_hash" value="' + data.card_hash + '"/>')
							.append('<input type="hidden" id="due_risk_token" class="due_card_meta" name="due_risk_token" value="' + data.risk_token + '"/>')
							.append('<input type="hidden" id="due_customer_ip" class="due_card_meta" name="due_customer_ip" value="' + data.customer_ip + '"/>')
							.append('<input type="hidden" id="due_card_type" class="due_card_meta" name="due_card_type" value="' + card_type + '"/>')
							.append('<input type="hidden" id="due_card_month" class="due_card_meta" name="due_card_month" value="' + card_month + '"/>')
							.append('<input type="hidden" id="due_card_year" class="due_card_meta" name="due_card_year" value="' + card_year + '"/>')
							.append('<input type="hidden" id="due_card_last4" class="due_card_meta" name="due_card_last4" value="' + card_last4 + '"/>');

						$form.submit();
					} else {
						// Error occured
						$( '.woocommerce-error, .due_card_meta').remove();
						$form.unblock();

						var message = 'Failed to get token';
						$('#wc-duecom-cc-form').prepend( '<ul class="woocommerce-error">' + message + '</ul>' );
					}
				});
			}
		}

		// Disallow form submit for "Add payment method" and "Payment Change" pages
		if ('1' === $( '#woocommerce_add_payment_method' ).val() || parseInt( $( 'input[name="woocommerce_change_payment"]' ).val() ) > 0) {
			if (parseInt($('#due_card_id').val()) > 0 || parseInt($( 'input[name="wc-duecom-payment-token"]:checked' ).val()) > 0) {
				return true;
			}

			return false;
		}
	}

	/**
	 * Create Credit Card Token
	 * @param callback
	 */
	function dueCreateCard(callback) {
		var billing_name = ($('#billing_first_name').val() + ' ' + $('#billing_last_name').val()).trim();
		var email = $('#billing_email').val();
		var postal_code = $('#billing_postcode').val();
		var card_number = $('#duecom-card-number').val();
		var card_cvv = $('#duecom-card-cvc').val();
		var card_exp = $('#duecom-card-expiry').val();
		var card_month = card_exp.substr(0, card_exp.indexOf('/')).trim();
		var card_year = card_exp.substr(card_exp.indexOf('/') + 1).trim();

		Due.payments.card.create({
			"name"       : billing_name,
			"email"      : email,
			"card_number": card_number,
			"cvv"        : card_cvv,
			"exp_month"  : card_month,
			"exp_year"   : card_year,
			"address"    : {
				"postal_code": postal_code
			}
		}, function (data) {
			callback(data);
		});
	}

	$( function () {
		/* Checkout Errors */
		$( document.body ).on( 'checkout_error', function () {
			$('.due_card_meta').remove();
		});

		// WooCommerce lets us return a false on checkout_place_order_{gateway} to keep the form from submitting
		$( 'form.checkout' ).on( 'checkout_place_order_duecom', function () {
			if (parseInt($('#due_card_id').val()) > 0 || parseInt($( 'input[name="wc-duecom-payment-token"]:checked' ).val()) > 0) {
				return true;
			}

			return false;
		});

		/* Checkout Form */
		$( 'form.checkout' ).on( 'checkout_place_order', function () {
			return dueFormHandler();
		});

		/* Pay Page Form */
		$( 'form#order_review' ).on( 'submit', function () {
			return dueFormHandler();
		});

		/* Pay Page Form */
		$( 'form#add_payment_method' ).on( 'submit', function () {
			return dueFormHandler();
		});

		/* Both Forms */
		$( 'form.checkout, form#order_review, form#add_payment_method' ).on( 'change', '#wc-duecom-cc-form input', function() {
			$('.due_card_meta').remove();
		});

	});

}( jQuery ) );