console.log('new file');

jQuery(document).ready(function($) {
    console.log('ebter func');
    $('.btn_quote').click(function() {
        var button = $(this);

        // Add loader_active class to the clicked button
        button.addClass('loader_active');

        console.log( $(this).data('num'));
        $.ajax({
            type: 'post',
            url: ajax_obj.ajaxurl,
            data: {
                action: 'syncCPRofByNumber',
                CPROFNUM: $(this).data('num'),
            },
            beforeSend: function (response) {
                button.addClass('loader_active');
            },
            complete: function (response) {
                console.log('complete');
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
            error: function() {
                console.log( 'AJAX error:');
                button.removeClass('loader_active');
            }
        });

    });
});