jQuery(document).ready(function( $ ) {
    $(document).on('change','.pri-packs', function(event ) {
        // shop- wait for Sunli to fix his bug T20
        // cart and product
        $(event.target).parent().find('.input-text.qty.text').first().attr('step',this.value);
        $(event.target).parent().find('.input-text.qty.text').first().val(0);
    });

    if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
        $(document).on('change','.pri-packs', function(event ) {
            // shop- wait for Sunli to fix his bug T20
            // cart and product
            $(event.target).parents('.p-pack').find('.quantity-pack .input-text.qty.text').first().attr('step',this.value);
            $(event.target).parents('.p-pack').find('.quantity-pack .input-text.qty.text').first().val(0);
        });
    }
});
