jQuery(document).ready(function( $ ) {
    $('.pri-packs').on('change', function(event ) {
        // shop- wait for Sunli to fix his bug T20
        // cart and product
        $(event.target).parent().find('.input-text.qty.text').first().attr('step',this.value);
        $(event.target).parent().find('.input-text.qty.text').first().val(0);
    });
});
