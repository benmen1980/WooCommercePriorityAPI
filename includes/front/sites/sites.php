<?php

// actions
if (\PriorityWoocommerceAPI\WooAPI::instance()->option( 'sites' ) == true ) {
	add_filter('wp_nav_menu_items','add_site_to_menu', 1000, 1000);
	add_action( 'wp_enqueue_scripts', 'wp2441_enqueue_scripts' );
}
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
			$options .= ' <option value="'.$sitecode.'">'.$sitedes.'</option>';
		}
		$selectclose = '</select>';
		$items .=  '<li class="menu-item">'
		           . '<p>'.$selectopen.$options.$selectclose.'</p>'
		           . '</li>';
	return $items;
}