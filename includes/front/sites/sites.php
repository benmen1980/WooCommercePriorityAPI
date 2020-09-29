<?php

// actions
	add_filter('wp_nav_menu_items','add_site_to_menu', 1000, 1000);
	add_action('wp_enqueue_scripts', 'wp2441_enqueue_scripts' );
    add_action('woocommerce_after_checkout_billing_form', 'simply_custom_checkout_fields');
    add_action('woocommerce_checkout_process', 'simply_custom_checkout_field_process');
    add_action('woocommerce_checkout_update_order_meta','simply_custom_checkout_field_update_order_meta' );
// functions
function wp2441_enqueue_scripts() {
	if ( !is_front_page() ){ // change for is_home() if you're not using a front page
		wp_enqueue_script( 'frontSites', plugins_url( 'sites.js' , __FILE__ ), array('jquery') );
		wp_localize_script( 'frontSites', 'site_ajax_page', array(
			'url' => admin_url( 'admin-ajax.php' ),
		));
	}
}
//  add site to check out form
function add_site_to_menu($items, $args) {
		$option          = "priority_customer_number";
		$customer_number = get_user_option( $option );
		$data            = $GLOBALS['wpdb']->get_results( '
                SELECT  sitecode,sitedesc
                FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_sites
                where customer_number = ' . $customer_number,
			ARRAY_A
		);
		$selectopen = '<select name="sites" id="p18a-sites">';
		$options = '';
		foreach ($data as $site){
			$sitedes = $site['sitedesc'];
			$sitecode = $site['sitecode'];
			$options .= '<option value="'.$sitecode.'">'.$sitedes.'</option>';
		}
		$selectclose = '</select>';
		$items .=  '<li class="menu-item">'
		           . '<p>'.$selectopen.$options.$selectclose.'</p>'
		           . '</li>';
	return $items;
}
// add site to check out
function simply_custom_checkout_fields($checkout){

    //  add site to check out form
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
        $sitelist = [];
        $finalsites = [];    //$sitelist;
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
        woocommerce_form_field( 'simply_site', $sites, $checkout->get_value( 'simply_site' ) );
}
function simply_custom_checkout_field_process() {
    // Check if set, if its not set add an error.
    if(isset($_POST['site'])){
        if ( ! $_POST['site'] && $this->option('sites') == true )
            wc_add_notice( __( 'Please enter site.' ), 'error' );
    }
}
function simply_custom_checkout_field_update_order_meta( $order_id ) {
    if ( ! empty( $_POST['site'] ) && $this->option('sites') == true ) {
        update_post_meta( $order_id, 'site', sanitize_text_field( $_POST['site'] ) );
    }
}


// this is for populate sites in front not in check out
function simply_populate_sites()
{
    // this function works with WC session
    $option = "priority_customer_number";
    $customer_number = get_user_option($option);
    $data = $GLOBALS['wpdb']->get_results('
                            SELECT  sitecode,sitedesc
                            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_sites
                            where customer_number = ' . $customer_number,
        ARRAY_A
    );

    $sitelist = array( // options for <select> or <input type="radio" />
        '' => __('Please select', 'p18a'),

    );

    $select_options = '<select name="sites" id="simply_sites">';
    $selected_code = WC()->session->get('sitecode');
    foreach ($data as $site) {
        if ($selected_code == $site['sitecode']) {
            $selected = ' selected';
        }
        $select_options .= '<option value="' . $site['sitecode'] . '"' . $selected . '>' . $site['sitedesc'] . '</option>';
        $selected = '';
    }
    $select_options .= '</select>';
    echo $select_options;
}




