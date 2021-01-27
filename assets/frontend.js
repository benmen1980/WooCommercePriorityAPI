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
	jQuery('.toggle').click(function(){
		var cls=jQuery(this).attr('id');
		if(jQuery('.subform-'+cls).hasClass('active')){
			jQuery(this).text('+').addClass('plus').removeClass('minus');
			jQuery('.subform-'+cls).hide();	
			jQuery('.subform-'+cls).removeClass('active');
		}else{
			jQuery('.toggle').text('+').addClass('plus').removeClass('minus');
			jQuery(this).text('-').addClass('minus').removeClass('plus');
			jQuery('.content_value').hide().removeClass('active');	
			jQuery('.subform-'+cls).show();
			jQuery('.subform-'+cls).addClass('active');
		}
	});
});