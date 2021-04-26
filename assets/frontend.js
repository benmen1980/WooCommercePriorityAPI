jQuery(document).ready(function($) {

	$total_check_sum = 0;
	jQuery( ".obligo_checkbox" ).each(function(index) {
		jQuery(this).on("click", function(){
			$check_sum = jQuery(this).data('sum');
			//If the checkbox is checked.
			if(jQuery(this).is(':checked')){
				
				$total_check_sum+=$check_sum;
				console.log($total_check_sum);

			//Enable the submit button.
				jQuery('#obligoSubmit').attr("disabled", false);
			} else{
				$total_check_sum-=$check_sum;
				console.log($total_check_sum);
				//If it is not checked, disable the button.
				jQuery('#obligoSubmit').attr("disabled", true);
				jQuery('.obligo_checkbox').each(function(){
				var jQuerythis = jQuery(this);
				if (jQuerythis.is(':checked')) {
					jQuery('#obligoSubmit').attr("disabled", false);
				}
				});
			}
			jQuery(".total_payment_checked").text(($total_check_sum/1000).toFixed(3));

		});
	});


	// jQuery('.toggle').click(function(){
	jQuery(document).on('click','.cust-toggle', function() {
		console.log('clicked');
		var cls=jQuery(this).attr('id');
		if(jQuery('.subform-'+cls).hasClass('active')){
			jQuery(this).text('+').addClass('plus').removeClass('minus');
			//jQuery('.subform-'+cls).hide();
			jQuery('.subform-'+cls).css('display','none');
			jQuery('.subform-'+cls).removeClass('active');
		}else{
			jQuery('.cust-toggle').text('+').addClass('plus').removeClass('minus');
			jQuery(this).text('-').addClass('minus').removeClass('plus');
			//jQuery('.content_value').hide().removeClass('active');
			jQuery('.content_value').css('display','none').removeClass('active');	
			//jQuery('.subform-'+cls).show();
			jQuery('.subform-'+cls).css('display','table-row');
			jQuery('.subform-'+cls).addClass('active');


		}
	});

	jQuery( "#from-date" ).datepicker({dateFormat: 'dd-mm-yy'});
	jQuery( "#to-date" ).datepicker({dateFormat: 'dd-mm-yy'});

	jQuery.browser = {};
	(function () {
	    jQuery.browser.msie = false;
	    jQuery.browser.version = 0;
	    if (navigator.userAgent.match(/MSIE ([0-9]+)\./)) {
	        jQuery.browser.msie = true;
	        jQuery.browser.version = RegExp.$1;
	    }
	})();
});
