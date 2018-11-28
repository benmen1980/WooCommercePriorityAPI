jQuery(document).ready(function($) {

    $(document).on('click', '.p18aw-sync', function(e){
        e.preventDefault();

        if($(this).attr('disabled')) return false;

        var that = this,
            sync_action = $(that).data('sync');

        $(that).html('<img src="' + P18AW.asset_url + 'load.gif" />');
        $('.p18aw-sync').attr('disabled', true);

        $.ajax({
            method: "POST",
            url: ajaxurl,
            data: {
                action: "p18aw_request",
                nonce: P18AW.nonce,
                sync : sync_action
            },
            dataType : 'json',
            error: function (jqXHR, exception) {
                let msg = '';
                if (jqXHR.status === 0) {
                    msg = 'Not connect.\nVerify Network.';
                } else if (jqXHR.status == 404) {
                    msg = 'Requested page not found. [404]';
                } else if (jqXHR.status == 500) {
                    msg = 'Internal Server Error [500].';
                } else if (exception === 'parsererror') {
                    msg = 'Requested JSON parse failed.';
                } else if (exception === 'timeout') {
                    msg = 'Time out error.';
                } else if (exception === 'abort') {
                    msg = 'Ajax request aborted.';
                } else {
                    msg = 'Uncaught Error.\n' + jqXHR.responseText;
                }
                $.ajax({
                    type: 'POST',
                    dataType: 'json',
                    url: ajaxurl,
                    data: {
                        action: "p18aw_request_error",
                        msg: msg,
                        sync : sync_action
                    },
                    success: function (response) {
                        $('[data-sync-time="' + sync_action + '"]').text(response.timestamp);
                        $('.p18aw-sync').attr('disabled', false);
                        $(that).html(P18AW.sync);
                    }
                });
            }
        }).done(function(response) {

            if(response.status) {
                $('[data-sync-time="' + sync_action + '"]').text(response.timestamp);
            }

            $('.p18aw-sync').attr('disabled', false);
            $(that).html(P18AW.sync);
            
        });

    });
    
});