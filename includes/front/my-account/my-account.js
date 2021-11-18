console.log('this is jquery for my account!');
jQuery(document).ready(function($) {
    var checked_rows = [];
    $('#simply-obligo').submit(function(e){
        e.preventDefault();
        $('#obligoSubmit').attr('disabled', 'disabled');
        $( ".obligo_checkbox" ).each(function(index) {
            if($(this).is(':checked')){
                checked_rows.push($(this).attr("name"));
            }
        });
        console.log('Open recipte by ajax!');
        console.log(checked_rows);
        data = $( this );
        jQuery.ajax({
            type:"POST",
            url: my_ajax_object.ajax_url,
            data: {
                action: "my_action",
                data :   $( this ).serializeArray()
            },
            success: function(results){
                console.log(results);
                console.log(my_ajax_object.woo_checkout_url);
                window.location.href = my_ajax_object.woo_checkout_url;
            },
            error: function(results) {
                alert('There was an error ' + results);
            }
        });
    });

    $('.back-to-purchase').click(function(e){
        $.ajax({
            url: my_ajax_object.ajax_url,
            method: 'POST',
            dataType: 'json',
            data: {
            'action': 'unset_customer_payment_session',
            },
            success: function(){
                console.log('success');
                window.location.href = my_ajax_object.woo_home_url;;
            },
            error: function(results) {
                alert('There was an error ' + results);
            }
        });
    });




    var sum = 0;
    var total_check_sum = 0;
    $(".obligo_checkbox:checked").each(function(){
        sum += $(this).data('sum');

    });
    total_check_sum+= sum;
    console.log(total_check_sum);
    if( total_check_sum != 0){
        $('#obligoSubmit').attr("disabled", false);
    }
    $(".total_payment_checked").text((total_check_sum).toFixed(2));

	
	$( ".obligo_checkbox" ).each(function(index) {
		$(this).on("click", function(){
			var check_sum = $(this).data('sum');
			//If the checkbox is checked.
			if($(this).is(':checked')){
				
				total_check_sum+= check_sum;
				console.log(total_check_sum);

			//Enable the submit button.
				$('#obligoSubmit').attr("disabled", false);
			} else{
				total_check_sum-= check_sum;
				console.log(total_check_sum);
				//If it is not checked, disable the button.
				$('#obligoSubmit').attr("disabled", true);
				$('.obligo_checkbox').each(function(){
				var $this = $(this);
				if ($this.is(':checked')) {
					$('#obligoSubmit').attr("disabled", false);
				}
				});
			}
			$(".total_payment_checked").text((total_check_sum).toFixed(2));

		});


        
	});
});
