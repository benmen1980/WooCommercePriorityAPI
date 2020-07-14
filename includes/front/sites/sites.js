jQuery(document).ready(function($){
    $('#site').prop('disabled', 'disabled');
   site_changes();
   $('#p18a-sites').change(
       function(){
           site_changes();
       }
   );
});

function site_changes(){
    var selectedsite = jQuery('#p18a-sites').val();
    jQuery('#site').val(selectedsite);
}