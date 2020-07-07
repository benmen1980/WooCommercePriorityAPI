console.log('this is jquery for my account!');
jQuery(document).ready(function($) {
    $('#simply-obligo').submit(function(e){
        e.preventDefault();
        $('#obligoSubmit').attr('disabled', 'disabled');
        console.log('Open recipte by ajax!');
        data = $( this );
        jQuery.ajax({
            type:"POST",
            url: my_ajax_object.ajax_url,
            data: {
                action: "my_action",
                data :   $( this ).serializeArray()
            },
            success: function(results){
                window.location.href = my_ajax_object.woo_cart_url;
            },
            error: function(results) {
                alert('There was an error ' + results);
            }
        });
    });
});
