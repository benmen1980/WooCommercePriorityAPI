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
});