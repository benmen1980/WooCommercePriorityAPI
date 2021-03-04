jQuery(document).ready(function($) {
	jQuery('.obligo_checkbox').click(function(){
		 //If the checkbox is checked.
		if(jQuery(this).is(':checked')){
		//Enable the submit button.
			jQuery('#obligoSubmit').attr("disabled", false);
		} else{
			//If it is not checked, disable the button.
			jQuery('#obligoSubmit').attr("disabled", true);
			jQuery('.obligo_checkbox').each(function(){
			var jQuerythis = jQuery(this);
			if (jQuerythis.is(':checked')) {
				jQuery('#obligoSubmit').attr("disabled", false);
			}
			});
		}
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

	$( "#from-date" ).datepicker();
	$( "#to-date" ).datepicker();

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