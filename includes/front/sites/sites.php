<?php
add_shortcode('priority-site','simply_populate_sites');
add_action( 'wp_footer', 'simply_ajax_without_file' );
/* handle session on frontend */
function simply_ajax_without_file() { ?>
    <script type="text/javascript" >
        jQuery(".simply_sites").change(function($) {
            ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ) ?>'; // get ajaxurl
            var data = {
                'action': 'set_site_session', // your action name
                'sitecode': this.value,
                'sitedesc': this.innerHTML
            }
            jQuery.ajax({
                url: ajaxurl, // this will point to admin-ajax.php
                type: 'POST',
                data: data,
                success: function (response) {
                    console.log(response);
                }
            });
        });
    </script>
    <?php
}
add_action("wp_ajax_set_site_session" , "set_site_session");
add_action("wp_ajax_nopriv_set_site_session" , "set_site_session");
function set_site_session(){
    WC()->session->set( 'sitecode', $_POST['sitecode'] );
    WC()->session->set( 'sitedesc', $_POST['sitedesc'] );
    echo WC()->session->get( 'sitedesc');
    wp_die();
}
// this is for populate sites in front not in check out
function simply_populate_sites()
{
    // this function works with WC session
    $option = "priority_customer_number";
    $customer_number = get_user_option($option);
    if(!empty(WC()->session->get( 'simply_sites'))){
        $data = WC()->session->get( 'simply_sites');
    }else {
        $data = $GLOBALS['wpdb']->get_results('
                            SELECT  sitecode,sitedesc
                            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_sites
                            where customer_number = ' . $customer_number,
            ARRAY_A
        );
    }
    if(empty($data)){
        return __('No Sites','p18a');
    }
    WC()->session->set( 'simply_sites', $data);
    // remove the ship to different address
    add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false');
    $sitelist = array( // options for <select> or <input type="radio" />
        '' => __('Please select', 'p18a'),

    );
    $select_options = '<select name="sites" id="simply_sites" class="simply_sites">';
    $selected_code = WC()->session->get('sitecode');
    $selected = ' selected';
    foreach ($data as $site) {
        if ($selected_code == $site['sitecode']) {
            $selected = ' selected';
        }
        $select_options .= '<option value="' . $site['sitecode'] . '"' . $selected . '>' . $site['sitedesc'] . '</option>';
        $selected = '';
    }
    $select_options .= '</select>';
    $items =  '<li class="menu-item">'
        . '<p>'.$select_options.'</p>'
        . '</li>';
    return $items;
}

// add the site to order meta
add_action('woocommerce_checkout_create_order', 'simply_before_checkout_create_order', 20, 2);
function simply_before_checkout_create_order( $order, $data ) {
    $order->update_meta_data( 'priority_site_code', WC()->session->get( 'sitedesc') );
}


// add check out, currently not in use
//add_action( 'woocommerce_after_checkout_billing_form', array( $this ,'custom_checkout_fields'));
/*
function custom_checkout_fields( $checkout ){

    //  add site to check out form
    if($this->option('sites') == true) {
        $option          = "priority_customer_number";
        $customer_number = get_user_option( $option );
        $data            = $GLOBALS['wpdb']->get_results( '
            SELECT  sitecode,sitedesc
            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_sites
            where customer_number = ' . $customer_number,
            ARRAY_A
        );

        $sitelist = array( // options for <select> or <input type="radio" />
            '' => __('Please select','p18a'),

        );

        $finalsites = $sitelist;
        foreach ( $data as $site ) {
            $finalsites +=  [$site['sitecode'] => str_replace('"', '', $site['sitedesc'])];
        }
        //$i = 0;
        //$site = array($data[$i]['sitecode'] => $data[$i]['sitedesc']);

        $sites = array(
            'type'        => 'select',
            // text, textarea, select, radio, checkbox, password, about custom validation a little later
            'required'    => true,
            // actually this parameter just adds "*" to the field
            'class'       => array( 'misha-field', 'form-row-wide' ),
            // array only, read more about classes and styling in the previous step
            'label'       => __('Priority ERP Order site ','p18a'),
            'label_class' => 'misha-label',
            // sometimes you need to customize labels, both string and arrays are supported
            'options'     => $finalsites
        );
        woocommerce_form_field( 'site', $sites, $checkout->get_value( 'site' ) );
    }



}
*/

