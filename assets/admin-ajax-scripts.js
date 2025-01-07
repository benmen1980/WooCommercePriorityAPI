$ = jQuery.noConflict();

jQuery(document).ready(function() {

    //sync custom order to priority - unidress
    console.log('admin ajax - unidress');
    $('#post-query-submit').on('click', function(e) {
        console.log('post custom order - unidress');

        let selectedOrders = [];

        $('input[name="post[]"]:checked').each(function() {
            selectedOrders.push($(this).val());
        });
        console.log('selectedOrders: ', selectedOrders);

        if (selectedOrders.length > 5) {
            e.preventDefault();
            alert('You can select a maximum of 5 orders to sync to Priority.');
            return; // Exit the function if more than 5 checkboxes are selected
        }

        $.ajax({
            type: 'POST',
            url: ajax_obj.ajaxurl,
            data: {
                action: 'process_selected_orders',
                orders: selectedOrders
            },
            success: function(response) {
                console.log(response);
                if (response.success) {
                    console.log(response.data); // Logs selected orders and their responses
                }         },
            error: function(response) {
                console.error(response);
                // Handle errors here.
            }
        });
    });
    
});