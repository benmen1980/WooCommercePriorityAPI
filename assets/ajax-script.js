jQuery(document).ready(function($) {
    // console.log('ebter func');

    //Opening a quote from the website (use of plugin 3 - arrowcables)
    $('.btn_quote').click(function() {
        var button = $(this);

        // Add loader_active class to the clicked button
        button.addClass('loader_active');

        console.log( button.data('num'));
        $.ajax({
            type: 'post',
            url: ajax_obj.ajaxurl,
            data: {
                action: 'syncCPRofByNumber',
                CPROFNUM: button.data('num'),
            },
            beforeSend: function (response) {
                button.addClass('loader_active');
            },
            complete: function (response) {
                console.log('complete');
                console.log ('respone: ', response)
                button.removeClass('loader_active');
                //console.log(response);
                //window.location.reload();
            },
            success: function (response) {
                console.log( 'AJAX success:', response );
                // $(this).data('link', response) ;
                window.open(response, '_self');

                button.removeClass('loader_active');
            },
            error: function(response) {
                console.log( 'AJAX error: ', response);
                button.removeClass('loader_active');
            }
        });

    });

    //Opening priority order confirmation by AJAX and using hub2sdk
    $('.btn_open_order').click(function() {
        var button = $(this);

        // Add loader_active class to the clicked button
        button.addClass('loader_active');

        console.log( button.data('order-name'));
        $.ajax({
            type: 'post',
            url: ajax_obj.ajaxurl,
            data: {
                action: 'get_order_url',
                ordname: button.data('order-name'),
            },
            beforeSend: function (response) {
                button.addClass('loader_active');
            },
            complete: function (response) {
                button.removeClass('loader_active');
            },
            success: function(response) {
                if (response.success) {
                    console.log( 'AJAX sucsses: ', response.data);
                    window.open(response.data, '_blank');
                } else {
                    console.log('Error:', response);
                }
                button.removeClass('loader_active');
            },           
            error: function(response) {
                console.log( 'AJAX error: ', response);
                button.removeClass('loader_active');
            }
        });

    });
    
    //Displaying the priority invoice in AJAX and using hub2sdk
    $('.btn_open_ivnum').click(function() {
        var button = $(this);

        // Add loader_active class to the clicked button
        button.addClass('loader_active');

        console.log( button.data('ivnum'));
        $.ajax({
            type: 'post',
            url: ajax_obj.ajaxurl,
            data: {
                action: 'get_invoice_url',
                ivnum: button.data('ivnum'),
            },
            beforeSend: function (response) {
                button.addClass('loader_active');
            },
            complete: function (response) {
                button.removeClass('loader_active');
            },
            success: function(response) {
                if (response.success) {
                    console.log( 'AJAX sucsses: ', response.data);
                    window.open(response.data, '_blank');
                } else {
                    console.log('Error:', response);
                }
                button.removeClass('loader_active');
            },           
            error: function(response) {
                console.log( 'AJAX error: ', response);
                button.removeClass('loader_active');
            }
        });

    });
});