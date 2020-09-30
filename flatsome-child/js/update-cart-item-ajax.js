jQuery(document).ready(function(){
	jQuery('.winn_cart_notes').on('change keyup paste',function(){
	 	/*jQuery('.cart_totals').block({
	 		message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
	 	});*/
	 	var cart_id = jQuery(this).data('cart-id');
	 	jQuery.ajax(
	 	{
	 		type: 'POST',
	 		url: admin_vars.ajaxurl,
	 		data: {
	 			action: 'winn_update_cart_notes',
	 			security: jQuery('#woocommerce-cart-nonce').val(),
	 			notes: jQuery('#cart_notes_' + cart_id).val(),
	 			cart_id: cart_id
	 		},
		 	success: function( response ) {
		 		//jQuery('.cart_totals').unblock();
		 	}
		}
	 	)
	});
});