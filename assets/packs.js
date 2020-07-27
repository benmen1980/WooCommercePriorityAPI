jQuery(document).ready(function( $ ) {
    $('#pri-packs').on('change', function() {
        console.log(this.value);
        $('.qty').first().attr('step',this.value);
        $('.qty').first().attr('min',this.value);
        $('.qty').first().attr('value',this.value);
        console.log($('.qty').first().val());
    });

});