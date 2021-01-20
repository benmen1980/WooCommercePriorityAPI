jQuery(document).ready(function( $ ) {
    $('#pri-packs').on('change', function() {
        $('.qty').first().attr('step',this.value);
        $('.qty').first().val(0);

    });

});
