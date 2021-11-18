jQuery(document).ready(function($) {

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
	if($('#from-date,#to-date').length)
		jQuery( "#from-date,#to-date" ).datepicker({dateFormat: 'dd-mm-yy'});


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
