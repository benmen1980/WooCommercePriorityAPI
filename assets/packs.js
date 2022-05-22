jQuery(document).ready(function( $ ) {
    $(document).on('change','.pri-packs', function(event ) {
        // shop- wait for Sunli to fix his bug T20
        // cart and product
        $(event.target).parent().find('.input-text.qty.text').first().attr('step',this.value);
        $(event.target).parent().find('.input-text.qty.text').first().val(0);
        $(event.target).parent().parent().parent().find('#num-packs').first().text(0)
    });
    $(document).on('change', '.input-text.qty.text', function (event) {
        // shop- wait for Sunli to fix his bug T20
        // cart and product
        let a = $(event.target).parent().find('.input-text.qty.text').first()[0].getAttribute('step');
        let b = $(event.target).parent().find('.input-text.qty.text').first()[0].value;
        if (b > 1) {
            let c = b / a
            $(event.target).parent().parent().parent().find('#num-packs').first().text(c)
        } else {
            $(event.target).parent().parent().parent().find('#num-packs').first().text(b)
        }
    });

    if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
        $(document).on('change','.pri-packs', function(event ) {
            // shop- wait for Sunli to fix his bug T20
            // cart and product
            $(event.target).parents('.p-pack').find('.quantity-pack .input-text.qty.text').first().attr('step',this.value);
            $(event.target).parents('.p-pack').find('.quantity-pack .input-text.qty.text').first().val(0);
            $(event.target).parent().parent().parent().find('#num-packs').first().text(0)
        });
    }
});
