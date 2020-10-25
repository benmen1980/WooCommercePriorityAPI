<?php
/**
* @package     Priority Woocommerce API
* @author      Ante Laca <ante.laca@gmail.com>
* @copyright   2018 Roi Holdings
*
* @wordpress-plugin
* Plugin Name: Priority Woocommerce API 
* Plugin URI: http://www.roi-holdings.com
* Description: Priority Woocommerce API extension
* Version: 1.05
* Author: Roi Holdings
* Author URI: http://www.roi-holdings.com
* Licence: GPLv2
* Text Domain: p18w
* Domain Path: /languages  
* 
*/

namespace PriorityWoocommerceAPI;

// Priority Woocommerce API
define('P18AW_VERSION'       , '1.0');
define('P18AW_SELF'          , __FILE__);
define('P18AW_URI'           , plugin_dir_url(__FILE__));
define('P18AW_DIR'           , plugin_dir_path(__FILE__)); 
define('P18AW_ASSET_DIR'     , trailingslashit(P18AW_DIR)    . 'assets/');
define('P18AW_ASSET_URL'     , trailingslashit(P18AW_URI)    . 'assets/');
define('P18AW_INCLUDES_DIR'  , trailingslashit(P18AW_DIR)    . 'includes/');
define('P18AW_CLASSES_DIR'   , trailingslashit(P18AW_DIR)    . 'includes/classes/');
define('P18AW_ADMIN_DIR'     , trailingslashit(P18AW_DIR)    . 'includes/admin/');
define('P18AW_FRONT_DIR'     , trailingslashit(P18AW_DIR)    . 'includes/front/');

// define plugin name and plugin admin url
define('P18AW_PLUGIN_NAME'      , 'Priority WooCommerce API');
define('P18AW_PLUGIN_ADMIN_URL' , sanitize_title(P18AW_PLUGIN_NAME));


register_activation_hook(P18AW_SELF, function(){

    global $wp_rewrite;
    $table = $GLOBALS['wpdb']->prefix . 'p18a_pricelists'; 
         
    $sql = "CREATE TABLE $table (
        id  INT AUTO_INCREMENT,
        blog_id INT,
        product_sku VARCHAR(32),
        price_list_code VARCHAR(32),
        price_list_name VARCHAR(256),
        price_list_currency VARCHAR(6), 
        price_list_price DECIMAL(6,2), 
        PRIMARY KEY  (id)
    )";

    /* This is used add the endpoint and menu item in woocommerce account menu. */
    add_rewrite_endpoint('obligo', EP_PERMALINK | EP_ROOT | EP_PAGES);

    /* When we add a new endpoint we need to flush the rewrite rules otherwise it would return 404 */
    $wp_rewrite->flush_rules( false );
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
    /* sites */
	$table = $GLOBALS['wpdb']->prefix . 'p18a_sites';

	$sql = "CREATE TABLE $table (
        id  INT AUTO_INCREMENT,
        blog_id INT,
        sitecode VARCHAR(32),
        sitedesc VARCHAR(32),
        customer_number VARCHAR(30),
        address1 VARCHAR(80),
        PRIMARY KEY  (id)
    )";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    dbDelta($sql);

});

// housekeeping
register_deactivation_hook(P18AW_SELF, function(){

    # $GLOBALS['wpdb']->query('DROP TABLE IF EXISTS ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists;');
    
});




// hook up
add_action('plugins_loaded', function(){

    $plugins = get_option('active_plugins');

    // check for PriorityAPI
    
     if (in_array('PriorityAPI/priority18-api.php', $plugins)) {

        // and check for Woocommerce
        if (in_array('woocommerce/woocommerce.php', $plugins)) {
            
	    load_plugin_textdomain( 'p18w', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );

            require P18AW_CLASSES_DIR . 'wooapi.php';
            
            WooAPI::instance()->run();

	        // load obligo
	        if(WooAPI::instance()->option('obligo')){
				 require P18AW_FRONT_DIR.'my-account/obligo.php';
				 \obligo::instance()->run();
			 }
	       require P18AW_ADMIN_DIR.'packs.php';
		if(WooAPI::instance()->option('sites')){
                include_once dirname(__FILE__).'/includes/front/sites/sites.php';
            	}


        } else {
            add_action('admin_notices', function(){
                printf('<div class="notice notice-error"><p>%s</p></div>', __('In order to use Priority WooCommerce API extension, WooCommerce must be activated', 'p18a'));
            });
        }

    } else {

        add_action('admin_notices', function(){
            printf('<div class="notice notice-error"><p>%s</p></div>', __('In order to use Priority WooCommerce API extension, Priority 18 API must be activated', 'p18a'));
        });

    }

});

include_once dirname(__FILE__) . '/includes/wc_variation_product.php';

