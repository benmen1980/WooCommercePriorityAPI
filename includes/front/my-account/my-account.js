console.log('this is jquery for my account!');
jQuery(document).ready(function($) {
    $('#simply-obligo').submit(function(e){
        e.preventDefault();
        console.log('Open recipte by ajax!');
   /* var data = {
        'data' : jQuery("#simply-obligo").serialize(),
        'action': 'my_action',
        'whatever': 1234
    };*/

    // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
        /*jQuery.post(my_ajax_object.ajaxurl, data, function(response) {

            alert('Got this from the server: ' + response);
        });*/
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