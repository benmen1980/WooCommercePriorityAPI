<?php 
/**
* @package     Priority Woocommerce API
* @author      Ante Laca <ante.laca@gmail.com>
* @copyright   2018 Roi Holdings
*/

namespace PriorityWoocommerceAPI;


class WooAPI extends \PriorityAPI\API
{

    private static $instance; // api instance
    private $countries = []; // countries list
    private static $priceList = []; // price lists
    private $basePriceCode = "בסיס";
    /**
    * PriorityAPI initialize
    *
    */
    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();    
        }
        
        return static::$instance;
    }
 
    private function __construct()
    {
        // get countries
        $this->countries = include(P18AW_INCLUDES_DIR . 'countries.php');

        /**
         * Schedule auto syncs
         */
        $syncs = [
            'sync_items_priority'           => 'syncItemsPriority',
            'sync_items_priority_variation' => 'syncItemsPriorityVariation',
            'sync_items_web'                => 'syncItemsWeb',
            'sync_inventory_priority'       => 'syncInventoryPriority',
            'sync_pricelist_priority'       => 'syncPriceLists',
            'sync_receipts_priority'        => 'syncReceipts',
            'sync_orders_priority'          => 'syncOrders',
            'sync_order_status_priority' => 'syncPriorityOrderStatus'
        ];

        foreach ($syncs as $hook => $action) {
            // Schedule sync
            if ($this->option('auto_' . $hook, false)) {

                add_action($hook, [$this, $action]);

                if ( ! wp_next_scheduled($hook)) {
                    wp_schedule_event(time(), $this->option('auto_' . $hook), $hook);
                }

            }

        }

        // add actions for user profile
	    add_action( 'show_user_profile',array($this,'crf_show_extra_profile_fields'),99,1 );
	    add_action( 'edit_user_profile',array($this,'crf_show_extra_profile_fields'),99,1 );

	    add_action( 'personal_options_update',array($this,'crf_update_profile_fields') );
	    add_action( 'edit_user_profile_update',array($this,'crf_update_profile_fields' ));

	    /* hide price for not registered user */
	    add_action( 'init',array($this, 'bbloomer_hide_price_add_cart_not_logged_in') );


	    include P18AW_ADMIN_DIR.'download_file.php';


    }
	// custom check out fields
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


	function my_custom_checkout_field_process() {
		// Check if set, if its not set add an error.
		if(isset($_POST['site'])){
			if ( ! $_POST['site'] && $this->option('sites') == true )
				wc_add_notice( __( 'Please enter site.' ), 'error' );
        }
	}


	function my_custom_checkout_field_update_order_meta( $order_id ) {
		if ( ! empty( $_POST['site'] ) && $this->option('sites') == true ) {
			update_post_meta( $order_id, 'site', sanitize_text_field( $_POST['site'] ) );
		}
	}

    public function run()
    {
        return is_admin() ? $this->backend(): $this->frontend();

    }

    /* hode price for not registered user */
	function bbloomer_hide_price_add_cart_not_logged_in() {
		if ( !is_user_logged_in() and  $this->option('walkin_hide_price') ) {
			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
			remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
			add_action( 'woocommerce_single_product_summary',array($this,'bbloomer_print_login_to_see'), 31 );
			add_action( 'woocommerce_after_shop_loop_item', array($this,'bbloomer_print_login_to_see'), 11 );
		}
	}




	function bbloomer_print_login_to_see() {
		echo '<a href="' . get_permalink(wc_get_page_id('myaccount')) . '">' . __('Login to see prices', 'theme_name') . '</a>';
	}



    /**
     * Frontend 
     *
     */
    private function frontend() {
    //frontenf test point

    // load obligo
   /*if($this->option('obligo')){
        require P18AW_FRONT_DIR.'my-account\obligo.php';
        \obligo::instance()->run();
    }*/

	// Sync customer and order data after order is proccessed
        add_action( 'woocommerce_thankyou', [ $this, 'syncDataAfterOrder' ] );
        // custom check out fields
	add_action( 'woocommerce_after_checkout_billing_form', array( $this ,'custom_checkout_fields'));
	add_action('woocommerce_checkout_process', array($this,'my_custom_checkout_field_process'));
	add_action( 'woocommerce_checkout_update_order_meta',array($this,'my_custom_checkout_field_update_order_meta' ));


	    // sync user to priority after registration
	    add_action( 'user_register', [ $this, 'syncCustomer' ] );
	    add_action( 'woocommerce_customer_save_address', [ $this, 'syncCustomer' ] );


	    if ( $this->option( 'sell_by_pl' ) == true ) {
		    // filter products regarding to price list
		    add_filter( 'loop_shop_post_in', [ $this, 'filterProductsByPriceList' ], 9999 );

		    // filter product price regarding to price list
		    add_filter( 'woocommerce_product_get_price', [ $this, 'filterPrice' ], 10, 2 );

		    // filter product variation price regarding to price list
		    add_filter( 'woocommerce_product_variation_get_price', [ $this, 'filterPrice' ], 10, 2 );
		    //add_filter('woocommerce_product_variation_get_regular_price', [$this, 'filterPrice'], 10, 2);


		    // filter price range
		    add_filter( 'woocommerce_variable_sale_price_html', [ $this, 'filterPriceRange' ], 10, 2 );
		    add_filter( 'woocommerce_variable_price_html', [ $this, 'filterPriceRange' ], 10, 2 );


		    // check if variation is available to the client
		    add_filter( 'woocommerce_variation_is_visible', function ( $status, $id, $parent, $variation ) {

			    $data = $this->getProductDataBySku( $variation->get_sku() );

			    return empty( $data ) ? false : true;

		    }, 10, 4 );

		    add_filter( 'woocommerce_variation_prices', function ( $transient_cached_prices ) {

			    $transient_cached_prices_new = [];

			    foreach ( $transient_cached_prices as $type_price => $variations ) {
				    foreach ( $variations as $var_id => $price ) {
					    $sku  = get_post_meta( $var_id, '_sku', true );
					    $data = $this->getProductDataBySku( $sku );
					    if ( ! empty( $data ) ) {
						    $transient_cached_prices_new[ $type_price ][ $var_id ] = $price;
					    }
				    }
			    }

			    return $transient_cached_prices_new ? $transient_cached_prices_new : $transient_cached_prices;
		    }, 10 );

		    /**
		     * t190 t214
		     */
		    add_filter( 'woocommerce_product_categories_widget_args', function ( $list_args ) {

			    $user_id = get_current_user_id();

			    $include = [];
			    $exclude = [];

			    $meta = get_user_meta( $user_id, '_priority_price_list', true );

			    if ( $meta !== 'no-selected' ) {
				    $list     = empty( $meta ) ? $this->basePriceCode : $meta;
				    $products = $GLOBALS['wpdb']->get_results( '
                    SELECT product_sku
                    FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                    WHERE price_list_code = "' . esc_sql( $list ) . '"
                    AND blog_id = ' . get_current_blog_id(),
					    ARRAY_A
				    );

				    $cat_ids = [];

				    foreach ( $products as $product ) {
					    if ( $id = wc_get_product_id_by_sku( $product['product_sku'] ) ) {
						    $parent_id = get_post( $id )->post_parent;
						    if ( isset( $parent_id ) && $parent_id ) {
							    $cat_id = wc_get_product_cat_ids( $parent_id );
						    }
						    if ( isset( $cat_id ) && $cat_id ) {
							    $cat_ids = array_unique( array_merge( $cat_ids, $cat_id ) );
						    }
					    }
				    }

				    if ( $cat_ids ) {
					    $include = array_merge( $include, $cat_ids );
				    } else {
					    $args    = array_merge( [ 'fields' => 'ids' ], $list_args );
					    $exclude = array_merge( $include, get_terms( $args ) );
				    }
			    }

			    //check display categories
			    if ( empty( $include ) ) {
				    $args    = array_merge( [ 'fields' => 'ids' ], $list_args );
				    $include = get_terms( $args );
			    }

			    global $wpdb;
			    $term_ids = $wpdb->get_col( "SELECT woocommerce_term_id as term_id FROM {$wpdb->prefix}woocommerce_termmeta WHERE meta_key = '_attribute_display_category' AND meta_value = '0'" );
			    if ( ! $term_ids ) {
				    $term_ids = [];
			    } else {
				    $term_ids = array_unique( $term_ids );
			    }

			    $include = array_diff( $include, $term_ids );

			    //check display categories for user
			    $cat_user = get_user_meta( $user_id, '_display_product_cat', true );

			    if ( is_array( $cat_user ) ) {
				    if ( $cat_user ) {
					    $include = array_intersect( $include, $cat_user );
				    } else {
					    $args    = array_merge( [ 'fields' => 'ids' ], $list_args );
					    $include = [];
					    $exclude = array_merge( $exclude, get_terms( $args ) );
				    }
			    }

			    $list_args['hide_empty'] = 1;
			    $list_args['include']    = implode( ',', array_unique( $include ) );
			    $list_args['exclude']    = implode( ',', array_unique( $exclude ) );

			    return $list_args;
		    } );
		    /**
		     * end t190 t214
		     */

		    // set shop currency regarding to price list currency
		    if ( $user_id = get_current_user_id() ) {

			    $meta = get_user_meta( $user_id, '_priority_price_list' );

			    $list = empty( $meta ) ? $this->basePriceCode : $meta[0]; // use base price list if there is no list assigned

			    if ( $data = $this->getPriceListData( $list ) ) {

				    add_filter( 'woocommerce_currency', function ( $currency ) use ( $data ) {

					    if ( $data['price_list_currency'] == '$' ) {
						    return 'USD';
					    }

					    if ( $data['price_list_currency'] == 'ש"ח' ) {
						    return 'ILS';
					    }

					    if ( $data['price_list_currency'] == 'שח' ) {
						    return 'ILS';
					    }

					    return $data['price_list_currency'];

				    }, 9999 );

			    }

		    }


	    }

    }

    /**
    * Backend - PriorityAPI Admin
    * 
    */
    private function backend()
    {    
        // load language
       // load_plugin_textdomain('p18w', false, plugin_basename(P18AW_DIR) . '/languages');
        // init admin
        add_action('init', function(){

            // check priority data
            if ( ! $this->option('application') || ! $this->option('environment') || ! $this->option('url')) {
                return $this->notify('Priority API data not set', 'error');
            }
          
            // admin page
            add_action('admin_menu', function(){

                // list tables classes
                include P18AW_CLASSES_DIR . 'pricelist.php';
                include P18AW_CLASSES_DIR . 'productpricelist.php';
	        include P18AW_CLASSES_DIR . 'sites.php';
		include P18AW_CLASSES_DIR . 'customersProducts.php';
                
		add_menu_page(P18AW_PLUGIN_NAME, P18AW_PLUGIN_NAME, 'manage_options', P18AW_PLUGIN_ADMIN_URL, function(){ 

                    switch($this->get('tab')) {

                        case 'syncs':
                            include P18AW_ADMIN_DIR . 'syncs.php';
                            break;

                        case 'pricelist':

                            
                            include P18AW_ADMIN_DIR . 'pricelist.php';

                            break;
                        
                        case 'show-products':

                            $data = $GLOBALS['wpdb']->get_row('
                                SELECT price_list_name 
                                FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists 
                                WHERE price_list_code = ' .  intval($this->get('list')) .
                                ' AND blog_id = ' . get_current_blog_id()
                            ); 

                            if (empty($data)) {
                                wp_redirect(admin_url('admin.php?page=' . P18AW_PLUGIN_ADMIN_URL) . '&tab=pricelist');
                            }

                            include P18AW_ADMIN_DIR . 'show_products.php';

                            break;
                        case 'sites';

	                        include P18AW_ADMIN_DIR . 'sites.php';

	                        break;

	                    case 'post_order';

		                    include P18AW_ADMIN_DIR . 'syncs/sync_order.php';
		                   /*
		                    $order_id =  $_GET['ord'];
		                   $response =  $this->syncOrder($order_id,'true');

		                    echo var_dump($response);
		                   */

		                    break;
			   
			    case 'order_meta';
		                $id = $_GET['ord'];
				$order = new \WC_Order($id);
                        	$data = get_post_meta($id);
				highlight_string("<?php\n\$data =\n" . var_export($data, true) . ";\n?>");


		                    break;
			   case 'customersProducts';		
				include P18AW_ADMIN_DIR . 'CustomersProducts.php';
				break;
			case 'sync_attachments';
				include P18AW_ADMIN_DIR . 'syncs/sync_product_attachemtns.php';
				break;
                        default:

                            include P18AW_ADMIN_DIR . 'settings.php';
                    }
                     
                });
                
            });

            // admin actions
            add_action('admin_init', function(){
                // enqueue admin scripts
                wp_enqueue_script('p18aw-admin-js', P18AW_ASSET_URL . 'admin.js', ['jquery']);
                wp_localize_script('p18aw-admin-js', 'P18AW', [
                    'nonce'         => wp_create_nonce('p18aw_request'),
                    'working'       => __('Working', 'p18a'),
                    'sync'          => __('Sync', 'p18a'),
                    'asset_url'     => P18AW_ASSET_URL
                ]);
                    
            });

            // add post customers button
            add_action('restrict_manage_users', function(){
                printf(' &nbsp; <input id="post-query-submit" class="button" type="submit" value="' . __('Post Customers', 'p18a') . '" name="priority-post-customers">');
            });




            // add post orders button
            add_action('restrict_manage_posts', function($type){
                if ($type == 'shop_order') {
                    printf('<input id="post-query-submit" class="button alignright" type="submit" value="' . __('Post orders', 'p18a') . '" name="priority-post-orders">');
                }
            });


            // add column
            add_filter('manage_users_columns', function($column) {

                $column['priority_customer'] = __('Priority Customer Number', 'p18a');
                $column['priority_price_list'] = __('Price List', 'p18a');

                return $column;

            });

            // add attach list form to admin footer
            add_action('admin_footer', function(){
                echo '<form id="attach_list_form" name="attach_list_form" method="post" action="' . admin_url('users.php?paged=' . $this->get('paged')) . '"></form>';
            });

            // get column data
            add_filter('manage_users_custom_column', function($value, $name, $user_id) {

                switch ($name) {

                    case 'priority_customer':


                        $meta = get_user_meta($user_id, 'priority_customer_number');

                        if ( ! empty($meta)) {
                            return $meta[0];
                        }

                        break;

                    
                    case 'priority_price_list':

                        $lists = $this->getPriceLists();
                        $meta  = get_user_meta($user_id, '_priority_price_list');

                        if (empty($meta)) $meta[0] = "no-selected";

                        $html  = '<input type="hidden" name="attach-list-nonce" value="' . wp_create_nonce('attach-list') . '" form="attach_list_form" />';
                        $html .= '<select name="price_list[' . $user_id . ']" onchange="window.attach_list_form.submit();" form="attach_list_form">';
                            $html .= '<option value="no-selected" ' . selected("no-selected", $meta[0], false) . '>Not Selected</option>';
                        foreach($lists as $list) {

                            $selected = (isset($meta[0]) && $meta[0] == $list['price_list_code']) ? 'selected' : '';

                            $html .= '<option  value="' . urlencode($list['price_list_code']) . '" ' . $selected . '>' . $list['price_list_name'] . '</option>' . PHP_EOL;
                        }

                        $html .= '</select>';

                        return $html;

                        break;

                    default:

                        return $value;

                }

            }, 10, 3);

            // save settings
            if ($this->post('p18aw-save-settings') && wp_verify_nonce($this->post('p18aw-nonce'), 'save-settings')) {

                $this->updateOption('walkin_number',  $this->post('walkin_number'));
	            $this->updateOption('price_method',  $this->post('price_method'));
	            $this->updateOption('item_status',  $this->post('item_status'));
	            $this->updateOption('variation_field',  $this->post('variation_field'));
	            $this->updateOption('variation_field_title',  $this->post('variation_field_title'));
	            $this->updateOption('sell_by_pl',  $this->post('sell_by_pl'));
	            $this->updateOption('walkin_hide_price',  $this->post('walkin_hide_price'));
	            $this->updateOption('sites',  $this->post('sites'));
	            $this->updateOption('update_image',  $this->post('update_image'));
	            $this->updateOption('mailing_list_field',  $this->post('mailing_list_field'));
	            $this->updateOption('obligo',  $this->post('obligo'));








                // save shipping conversion table
	            if($this->post('shipping')) {
		            foreach ( $this->post( 'shipping' ) as $key => $value ) {
			            $this->updateOption( 'shipping_' . $key, $value );
		            }
	            }

	            // save payment conversion table
	            if($this->post( 'payment' )) {
		            foreach ( $this->post( 'payment' ) as $key => $value ) {
			            $this->updateOption( 'payment_' . $key, $value );
		            }
	            }

                $this->notify('Settings saved');

            }

            // save sync settings
            if ($this->post('p18aw-save-sync') && wp_verify_nonce($this->post('p18aw-nonce'), 'save-sync')) {

                $this->updateOption('log_items_priority',                   $this->post('log_items_priority'));
                $this->updateOption('auto_sync_items_priority',             $this->post('auto_sync_items_priority'));
                $this->updateOption('email_error_sync_items_priority',      $this->post('email_error_sync_items_priority'));
                $this->updateOption('log_items_priority_variation',         $this->post('log_items_priority_variation'));
                $this->updateOption('auto_sync_items_priority_variation',   $this->post('auto_sync_items_priority_variation'));
                $this->updateOption('email_error_sync_items_priority_variation',      $this->post('email_error_sync_items_priority_variation'));
                $this->updateOption('log_items_web',                        $this->post('log_items_web'));
                $this->updateOption('auto_sync_items_web',                  $this->post('auto_sync_items_web'));
                $this->updateOption('email_error_sync_items_web',           $this->post('email_error_sync_items_web'));
                $this->updateOption('log_inventory_priority',               $this->post('log_inventory_priority'));
                $this->updateOption('auto_sync_inventory_priority',         $this->post('auto_sync_inventory_priority'));
                $this->updateOption('email_error_sync_inventory_priority',  $this->post('email_error_sync_inventory_priority'));
                $this->updateOption('log_pricelist_priority',               $this->post('log_pricelist_priority'));
                $this->updateOption('auto_sync_pricelist_priority',         $this->post('auto_sync_pricelist_priority'));
                $this->updateOption('email_error_sync_pricelist_priority',  $this->post('email_error_sync_pricelist_priority'));
                $this->updateOption('log_receipts_priority',                $this->post('log_receipts_priority'));
                $this->updateOption('auto_sync_receipts_priority',          $this->post('auto_sync_receipts_priority'));
                $this->updateOption('email_error_sync_receipts_priority',   $this->post('email_error_sync_receipts_priority'));
                $this->updateOption('log_customers_web',                    $this->post('log_customers_web'));
                $this->updateOption('email_error_sync_customers_web',       $this->post('email_error_sync_customers_web'));
                $this->updateOption('log_shipping_methods',                 $this->post('log_shipping_methods'));
                $this->updateOption('post_order_checkout',                  $this->post('post_order_checkout'));
                $this->updateOption('email_error_sync_orders_web',          $this->post('email_error_sync_orders_web'));
                $this->updateOption('sync_onorder_receipts',                $this->post('sync_onorder_receipts'));
	        $this->updateOption('log_sync_order_status_priority',       $this->post('log_sync_order_status_priority'));
	        $this->updateOption('auto_sync_order_status_priority',      $this->post('auto_sync_order_status_priority'));

                $this->updateOption('auto_sync_orders_priority',            $this->post('auto_sync_orders_priority'));
	        $this->updateOption('log_auto_post_orders_priority',        $this->post('log_auto_post_orders_priority'));

	        $this->updateOption('auto_sync_sites_priority',  $this->post('auto_sync_sites_priority'));
	        $this->updateOption('log_sites_priority',  $this->post('log_sites_priority'));

		$this->updateOption('auto_sync_c_products_priority',  $this->post('auto_sync_c_products_priority'));
		$this->updateOption('log_c_products_priority',  $this->post('log_c_products_priority'));
	        $this->updateOption('post_einvoices_checkout',                  $this->post('post_einvoices_checkout'));
		$this->updateOption('email_error_sync_einvoices_web',          $this->post('email_error_sync_einvoices_web'));



                $this->notify('Sync settings saved');
            }


            // attach price list
            if ($this->post('price_list') && wp_verify_nonce($this->post('attach-list-nonce'), 'attach-list')) {

                foreach($this->post('price_list') as $user_id => $list_id) {
                    update_user_meta($user_id, '_priority_price_list', urldecode($list_id));
                }

                $this->notify('User price list changed');

            }

            // post customers to priority
            if ($this->get('priority-post-customers') && $this->get('users')) {

                foreach($this->get('users') as $id) {
                    $this->syncCustomer($id);
                }

                // redirect, otherwise will run twice
                if ( wp_redirect(admin_url('users.php?notice=synced'))) {
                    exit;
                }
                
            }

            // post orders to priority
            if ($this->get('priority-post-orders') && $this->get('post')) {

                foreach($this->get('post') as $id) {
                    $this->syncOrder($id);
                }

                // redirect
                if ( wp_redirect(admin_url('edit.php?post_type=shop_order&notice=synced'))) {
                    exit;
                }
                
            }

            // display notice
            if ($this->get('notice') == 'synced') {
                $this->notify('Data synced');
            }

        });
	//  add Priority order status to orders page
	    // ADDING A CUSTOM COLUMN TITLE TO ADMIN ORDER LIST
	   add_filter( 'manage_edit-shop_order_columns',
	    function($columns)
	    {
		    // Set "Actions" column after the new colum
		    $action_column = $columns['order_actions']; // Set the title in a variable
		    unset($columns['order_actions']); // remove  "Actions" column


		    //add the new column "Status"
		    $columns['order_priority_status'] = '<span>'.__( 'Priority Status','woocommerce').'</span>'; // title
		    
		    // add the Priority order number
		    $columns['order_priority_number'] = '<span>'.__( 'Priority Order','woocommerce').'</span>'; // title

		    //add the new column "post to Priority"
		    $columns['order_post'] = '<span>'.__( 'Post to Priority','woocommerce').'</span>'; // title


		    // Set back "Actions" column
		    $columns['order_actions'] = $action_column;

		    return $columns;
	    });

// ADDING THE DATA FOR EACH ORDERS BY "Platform" COLUMN
	    add_action( 'manage_shop_order_posts_custom_column' ,
	    function ( $column, $post_id )
	    {

		    // HERE get the data from your custom field (set the correct meta key below)
		    $status = get_post_meta( $post_id, 'priority_status', true );
		    $order_number = get_post_meta( $post_id, 'priority_ordnumber', true );
				if( empty($status)) $status = '';
				if(strlen($status) > 15) $status = '<div class="tooltip">Error<span class="tooltiptext">'.$status.'</span></div>';
				if( empty($order_number)) $order_number = '';

		    switch ( $column )
		    {
			    case 'order_priority_status' :
				    echo $status;
				    break;
			    case 'order_priority_number' :
						echo '<span>'.$order_number.'</span>'; // display the data
						break;
                // post order to API, using GET and
                case 'order_post' :
                    $url ='admin.php?page=priority-woocommerce-api&tab=post_order&ord='.$post_id ;
	                echo '<span><a href='.$url.'>Re Post</a></span>'; // display the data
	                break;
		    }
	    },10,2);

// MAKE 'stauts' METAKEY SEARCHABLE IN THE SHOP ORDERS LIST
	    add_filter( 'woocommerce_shop_order_search_fields',
	    function ( $meta_keys ){
		    $meta_keys[] = 'priority_status';
		    $meta_keys[] = 'priority_ordnumber';
		    return $meta_keys;
	    }, 10, 1 );
	    
        // ajax action for manual syncs
        add_action('wp_ajax_p18aw_request', function(){

            // check nonce
            check_ajax_referer('p18aw_request', 'nonce');

            set_time_limit(420);

            // switch syncs
            switch($_POST['sync']) {
		 case 'auto_post_orders_priority':
                    try{
                        $this->syncOrders();
                        /*
	                    $query = new \WC_Order_Query( array(
		                    'limit' => 1000,
		                    'orderby' => 'date',
		                    'order' => 'DESC',
		                    'return' => 'ids',
		                    'priority_status' => 'NOT EXISTS',
	                    ) );
	                    $orders = $query->get_orders();
	                    foreach ($orders as $id){
		                    $order =wc_get_order($id);
		                    $priority_status = $order->get_meta('priority_status');
		                    if(!$priority_status){

			                    $response = $this->syncOrder($id,$this->option('log_auto_post_orders_priority', true));


		                    }

	                    };*/
	                    //$this->updateOption('auto_post_orders_priority_update', time());

                    }catch(Exception $e){
	                    exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }
                    break;
                case 'sync_items_priority':

                    try {
                        $this->syncItemsPriority();
                    } catch(Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;
                case 'sync_items_priority_variation':

                    try {
                        $this->syncItemsPriorityVariation();
                    } catch(Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;
                case 'sync_items_web':

                    try {
                        $this->syncItemsWeb();
                    } catch(Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;
                case 'sync_inventory_priority':


                    try {
                        $this->syncInventoryPriority();
                    } catch(Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;

                case 'sync_pricelist_priority':


                    try {
                        $this->syncPriceLists();
                    } catch(Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;

	            case 'sync_sites_priority':


		            try {
			            $this->syncSites();
		            } catch(Exception $e) {
			            exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
		            }

		            break;

                case 'sync_receipts_priority':

                    try {

                        $this->syncReceipts();               

                    } catch(Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;

                case 'sync_customers_web':
                
                    try {
                        
                        $customers = get_users(['role' => 'customer']);

                        foreach ($customers as $customer) {
                            $this->syncCustomer($customer->ID);
                        }

                    } catch(Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;

                case 'auto_sync_order_status_priority':
                    try {

                        $this->syncPriorityOrderStatus();
                    /*
	                    $url_addition = 'ORDERS';
	                    $response     =  $this->makeRequest( 'GET', $url_addition, null, true ) ;
	                    $orders = json_decode($response['body'],true)['value'];
	                    $output = '';
	                    foreach ( $orders as $el ) {
		                    $order_id = $el['BOOKNUM'];
		                    $order = wc_get_order( $order_id );
		                    $pri_status = $el['ORDSTATUSDES'];
		                    if($order){
			                    update_post_meta($order_id,'priority_status',$pri_status);
			                    $output .= '<br>'.$order_id.' '.$pri_status.' ';
		                    }
	                    }
	                    $this->updateOption('auto_sync_order_status_priority_update', time());
                            */
                    }catch(Exception $e) {
	                    exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }


                default: 

                    exit(json_encode(['status' => 0, 'msg' => 'Unknown method ' . $_POST['sync']]));

            }

            exit(json_encode(['status' => 1, 'timestamp' => date('d/m/Y H:i:s')]));


        });

        // ajax action for manual syncs
        add_action('wp_ajax_p18aw_request_error', function(){

            $url = sprintf('https://%s/odata/Priority/%s/%s/%s',
                $this->option('url'),
                $this->option('application'),
                $this->option('environment'),
                ''
            );

            $GLOBALS['wpdb']->insert($GLOBALS['wpdb']->prefix . 'p18a_logs', [
                'blog_id'        => get_current_blog_id(),
                'timestamp'      => current_time('mysql'),
                'url'            => $url,
                'request_method' => 'GET',
                'json_request'   => '',
                'json_response'  => 'AJAX ERROR ' . $_POST['msg'],
                'json_status'    => 0
            ]);

            $this->sendEmailError(
                $this->option('email_error_' . $_POST['sync']),
                'Error ' . ucwords(str_replace('_',' ', $_POST['sync'])),
                'AJAX ERROR<br>' . $_POST['msg']
            );

            exit(json_encode(['status' => 1, 'timestamp' => date('d/m/Y H:i:s')]));
        });


    }

    /**
     * sync items from priority
     */
    public function syncItemsPriority()
    {


       //$response = $this->makeRequest('GET', 'LOGPART?$filter='.$this->option('variation_field').' eq \'\' and ROYY_ISUDATE eq \'Y\'', [], $this->option('log_items_priority', true));
       // $response = $this->makeRequest('GET', 'LOGPART?$filter='.$this->option('variation_field').' eq \'\' and ROYY_ISUDATE eq \'Y\'&$expand=PARTTEXT_SUBFORM', [], $this->option('log_items_priority', true));
       // get the items simply by time stamp of today
	    $stamp = mktime(0, 0, 0);
	    $bod = date(DATE_ATOM,$stamp);
	    $url_addition = 'UDATE ge '.$bod;
	    if($this->option('variation_field')) {
		    $url_addition .= ' and ' . $this->option( 'variation_field' ) . ' eq \'\' ';
	    }
	    $response = $this->makeRequest('GET', 'LOGPART?$filter='.urlencode($url_addition),[], $this->option('log_items_priority', true));





        // check response status
        if ($response['status']) {

            $response_data = json_decode($response['body_raw'], true);

            foreach($response_data['value'] as $item) {

                // add long text from Priority
	            $content = '';
	            $post_content = '';
	            if(isset($item['PARTTEXT_SUBFORM']) ) {
		            foreach ( $item['PARTTEXT_SUBFORM'] as $text ) {
			            $content .= $text['TEXT'];
		            }
		            $content = str_replace("pdir","p dir",$content);
		            $cleancontent = explode("</style>",$content);

		            $post_content = $cleancontent[1];
	            }




                $data = [
	                'post_author' => 1,
                      'post_content' =>  (isset($cleancontent[1]) ?  $cleancontent[1] : 'no content'),
                    'post_status'  => $this->option('item_status'),
                    //  'post_status'  => 'draft',
                    'post_title'   => $item['PARTDES'],
                    'post_parent'  => '',
                    'post_type'    => 'product',

                ];

                // if product exsits, update

	            $args = array(
		            'post_type'		=>	'product',
		            'meta_query'	=>	array(
			            array(
				            'key'       => '_sku',
				            'value'	=>	$item['PARTNAME']
			            )
		            )
	            );
	            $my_query = new \WP_Query( $args );
	            if ( $my_query->have_posts() ) {
		            while ( $my_query->have_posts() ) {
			            $my_query->the_post();
			            $product_id = get_the_ID();


		            }
	            }else{
		            $product_id = 0;
	            }


                if ($product_id != 0 /* = wc_get_product_id_by_sku($item['PARTNAME'])*/) {

	                $data['ID'] = $product_id;
	                // Update post
	                $id = $product_id;
	                global $wpdb;
	                // @codingStandardsIgnoreStart
	                $wpdb->query(
		                $wpdb->prepare(
			                "
							UPDATE $wpdb->posts
							SET post_title = '%s',
							post_content = '%s'
							WHERE ID = '%s'
							",
			                $item['PARTDES'],
			               $post_content,
			                 $id

		                )
	                );

                } else {
                    // Insert product


                    $id = wp_insert_post($data);



                    if ($id) {
                        update_post_meta($id, '_stock', 0);
                        update_post_meta($id, '_stock_status', 'outofstock');
	                    wp_set_object_terms($id,[$item['FAMILYDES']],'product_cat');
                    }
                    

                }
	            $out_of_stock_staus = 'outofstock';

                // 1. Updating the stock quantity
	          //  update_post_meta($id, '_stock', 0);

                // 2. Updating the stock quantity
	           // update_post_meta( $id, '_stock_status', wc_clean( $out_of_stock_staus ) );

                // 3. Updating post term relationship
	            wp_set_post_terms( $id, 'outofstock', 'product_visibility', true );

                // And finally (optionally if needed)
	            wc_delete_product_transients( $id ); // Clear/refresh the variation cache

                // update product meta
	            $pri_price = $this->option('price_method') == true ? $item['VATPRICE'] : $item['BASEPLPRICE'];
                if ($id) {
                    update_post_meta($id, '_sku', $item['PARTNAME']);
                    update_post_meta($id, '_regular_price', $pri_price);
                    update_post_meta($id, '_price',$pri_price );
                    update_post_meta($id, '_manage_stock', ($item['INVFLAG'] == 'Y') ? 'yes' : 'no');
		    // update categories
                    $terms = [$item['SPEC1'],$item['SPEC2'],$item['SPEC3'],$item['SPEC4'],$item['SPEC5']];
		    wp_set_object_terms($id,$terms,'product_cat');
                }
                // sync image
                $sku =  $item['PARTNAME'];
                $is_has_image = get_the_post_thumbnail_url($id);
                if(!empty($item['EXTFILENAME'])
                    && ($this->option('update_image')==true || !get_the_post_thumbnail_url($id) )){
	                $priority_image_path = $item['EXTFILENAME'];
	                $images_url =  'https://'. $this->option('url').'/primail';
	                $product_full_url    = str_replace( '../../system/mail', $images_url, $priority_image_path );
	                $file_path = $item['EXTFILENAME'];
		        $file_info = pathinfo( $file_path );
		        $file_name = $file_info['basename'];
			$file_ext  = $file_info['extension'];
                  	$file_array = explod('.',$file_name);
			global $wpdb;
	                $attach_id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_array[0]%' AND meta_key = '_wp_attached_file'" );
	                if($attach_id){
                   		 }else{
                    			$attach_id           = download_attachment( $sku, $product_full_url );
                    			}
	                set_post_thumbnail( $id, $attach_id );
                }

            }

            // add timestamp
            $this->updateOption('items_priority_update', time());

        } else {
            /**
             * t149
             */
            $this->sendEmailError(
                $this->option('email_error_sync_items_priority'),
                'Error Sync Items Priority',
                $response['body']
            );

        }return $response;
    }

	
public function simply_posts_where( $where, $query ) {
		global $wpdb;
		// Check if our custom argument has been set on current query.
		if ( $query->get( 'filename' ) ) {
			$filename = $query->get( 'filename' );
			// Add WHERE clause to SQL query.
			$where .= " AND $wpdb->posts.post_title LIKE '".$filename."'";
		}
		return $where;
}
public function simply_check_file_exists($file_name){
		add_filter( 'posts_where', array($this,'simply_posts_where'), 10, 2 );
		$args = array(
			'post_type'  => 'attachment',
			'posts_per_page' => '-1',
			'post_status' => 'any',
			'filename'         => $file_name,
		);
		$the_query = new \WP_Query( $args);
		remove_filter( 'posts_where', array($this,'simply_posts_where'), 10 );
// The Loop
		if ( $the_query->have_posts() ) {
			while ( $the_query->have_posts() ) {
				$the_query->the_post();
				return  get_the_ID();
			}

		} else {
			// no posts found
			return false;
		}
}
	
	
public function sync_product_attachemtns(){
		 /*
		 * the function pull the urls from Priority,
		 * then check if the file already exists as attachemnt in WP
		 * if is not exists, will download and attache
		 * if exists, will pass but will keep the file attached
		 * any file that exists in WP and not exists in Priority will remain
		 * the function ignore other file extensions
		 * you cant anyway attach files that are not images
		 */

		ob_start();
		$allowed_sufix = ['jpg','jpeg','png'];
		$response = $this->makeRequest('GET','LOGPART?$filter=EXTFILEFLAG eq \'Y\' &$select=PARTNAME&$expand=PARTEXTFILE_SUBFORM');


		$response_data = json_decode($response['body_raw'], true);
		foreach($response_data['value'] as $item) {
			$sku =  $item['PARTNAME'];
			$main_attach_id = [];
			$attachments = [$main_attach_id];
			//$product_id = wc_get_product_id_by_sku($sku);

			$args = array(
				'post_type'		=>	'product',
				'meta_query'	=>	array(
					array(
						'key'       => '_sku',
						'value'	=>	$item['PARTNAME']
					)
				)
			);
			$my_query = new \WP_Query( $args );
			if ( $my_query->have_posts() ) {
				$my_query->the_post();
                $product_id = get_the_ID();
			}else{
				$product_id = 0;
				continue;
			}
			//**********
			$product = new \WC_Product($product_id);
			$product_media = $product->get_gallery_image_ids();
			echo 'Starting process for product '.$sku.'<br>';

			foreach ( $item['PARTEXTFILE_SUBFORM'] as $attachment ) {
				$file_path = $attachment['EXTFILENAME'];
				$file_info = pathinfo( $file_path );
				$file_name = $file_info['basename'];
				$file_ext  = $file_info['extension'];
				if (array_search( $file_ext, $allowed_sufix, false )!==false ) {
					$is_existing_file = false;
					// check if the item exists in media
					//$id = $this->simply_check_file_exists($file_name);
					 global $wpdb;
	            			$id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_name' AND meta_key = '_wp_attached_file'" );
					if($id){
						echo $file_path . ' already exists in media, add to product... <br>';
						$is_existing_file = true;
						array_push( $attachments, $id );
						continue;
					}
					// if is a new file, download from Priority and push to array
					if ( $is_existing_file !== true ) {
						$images_url =  'https://'. $this->option('url').'/primail';
						echo 'File '.$file_path.' not exsits, downloading from '.$images_url,'<br>';
						$priority_image_path = $file_path;
						$product_full_url    = str_replace( '../../system/mail', $images_url, $priority_image_path );
					 	$thumb_id = download_attachment( $sku, $product_full_url );
						array_push( $attachments, $thumb_id );
					};
				}
			};
			//  add here merge to files that exists in wp and not exists in the response from API
			$image_id_array = array_merge($product_media, $attachments);
			// https://stackoverflow.com/questions/43521429/add-multiple-images-to-woocommerce-product
			update_post_meta($product_id, '_product_image_gallery',$image_id_array);

		}
		$output_string = ob_get_contents();
		ob_end_clean();
		return $output_string;

	}

	
    /**
     * sync items width variation from priority
     */
    public function syncItemsPriorityVariation()
    {

        $response = $this->makeRequest('GET', 'LOGPART?$expand=PARTUNSPECS_SUBFORM&$filter='.$this->option('variation_field').' ne \'\'    and ROYY_ISUDATE eq \'Y\'', [], $this->option('log_items_priority_variation', true));

        // check response status
        if ($response['status']) {

            $response_data = json_decode($response['body_raw'], true);

            $product_cross_sells = [];
            $parents = [];
            $childrens = [];


	        foreach($response_data['value'] as $item) {
		        if ($item[$this->option('variation_field')] !== '-') {
			        $attributes = [];
			        if ($item['PARTUNSPECS_SUBFORM']) {
				        foreach ($item['PARTUNSPECS_SUBFORM'] as $attr) {
					      $attributes[$attr['SPECNAME']] = $attr['VALUE'];
				        }
			        }

			        if ($attributes) {
				        $parents[$item[$this->option('variation_field')]] = [
					        'sku'       => $item[$this->option('variation_field')],
					        //'crosssell' => $item['ROYL_SPECDES1'],
					        'title'     => $item[$this->option('variation_field_title')],
					        'stock'     => 'Y',
					        'variation' => []
				        ];
				        $childrens[$item[$this->option('variation_field')]][$item['PARTNAME']] = [
					        'sku'           => $item['PARTNAME'],
					        'regular_price' => $item['VATPRICE'],
					        'stock'         => $item['INVFLAG'],
					        'parent_title'  => $item['MPARTDES'],
					        'title'         => $item['PARTDES'],
					        'stock'         => ($item['INVFLAG'] == 'Y') ? 'instock' : 'outofstock',
					        /*'tags'          => [
								$item['ROYL_SPECEDES1'],
								$item['ROYL_SPECEDES2'],
								$item['FAMILYDES']
							],
							*/
					        'categories'    => [
						        $item['ROYY_MFAMILYDES']
					        ],
					        'attributes'    => $attributes
				        ];
			        }
		        }
	        }




            foreach ($parents as $partname => $value) {
                if (count($childrens[$partname])) {
                    $parents[$partname]['categories']  = end($childrens[$partname])['categories'];
                    $parents[$partname]['tags']        = end($childrens[$partname])['tags'];
                    $parents[$partname]['variation']   = $childrens[$partname];
                    $parents[$partname]['title']       = $parents[$partname]['title'];
                    foreach ($childrens[$partname] as $children) {
                        foreach ($children['attributes'] as $attribute => $attribute_value) {
                            if ($attribute_value && !in_array($attribute_value, $parents[$partname]['attributes'][$attribute]))
                                $parents[$partname]['attributes'][$attribute][] = $attribute_value;
                        }
                    }
                    $product_cross_sells[$value['cross_sells']][] = $partname;
                } else {
                    unset($parents[$partname]);
                }
            }

            if ($parents) {

                foreach ($parents as $sku_parent => $parent) {

                    $id = create_product_variable( array(
                        'author'        => '', // optional
                        'title'         => $parent['title'],
                        'content'       => '',
                        'excerpt'       => '',
                        'regular_price' => '', // product regular price
                        'sale_price'    => '', // product sale price (optional)
                        'stock'         => $parent['stock'], // Set a minimal stock quantity
                        'image_id'      => '', // optional
                        'gallery_ids'   => array(), // optional
                        'sku'           => $sku_parent, // optional
                        'tax_class'     => '', // optional
                        'weight'        => '', // optional
                        // For NEW attributes/values use NAMES (not slugs)
                        'attributes'    => $parent['attributes'],
                        'categories'    => $parent['categories'],
                        'tags'          => $parent['tags'],
	                    'status'        => $this->option('item_status')
                    ) );

                    $parents[$sku_parent]['product_id'] = $id;

                    foreach ($parent['variation'] as $sku_children => $children) {
	                    $pri_price = $this->option('price_method') == true ? $item['VATPRICE'] : $item['BASEPLPRICE'];
                        // The variation data
                        $variation_data =  array(
                            'attributes'    => $children['attributes'],
                            'sku'           => $sku_children,
                            'regular_price' => $pri_price,
                            'product_code'  => $children['product_code'],
                            'sale_price'    => '',
                            'stock'         => $children['stock'],
                        );

                        // The function to be run
                        create_product_variation( $id, $variation_data );

                    }

                    unset( $parents[$sku_parent]['variation']);

                }

                foreach ($product_cross_sells as $k => $product_cross_sell) {
                    foreach ($product_cross_sell as $key => $sku) {
                        $product_cross_sells[$k][$key] = $parents[$sku]['product_id'];
                    }
                }

                foreach ($parents as $sku_parent => $parent) {
                    $cross_sells = $product_cross_sells[$parent['cross_sells']];

                    if (($key = array_search($parent['product_id'], $cross_sells)) !== false) {
                        unset($cross_sells[$key]);
                    }
                    /**
                     * t205
                     */
                    $cross_sells_merge_array = [];

                    if ($cross_sells_old = get_post_meta($parent['product_id'], '_crosssell_ids', true)){
                        foreach ($cross_sells_old as $value)
                            if (!is_array($value)) $cross_sells_merge_array[] = $value;
                    }

                    $cross_sells = array_unique(array_filter(array_merge( $cross_sells, $cross_sells_merge_array)));

                    /**
                     * end t205
                     */

                    update_post_meta($parent['product_id'], '_crosssell_ids', $cross_sells);
                }

            }

            // add timestamp
            $this->updateOption('items_priority_variation_update', time());

        } else {
            /**
             * t149
             */
            $this->sendEmailError(
                $this->option('email_error_sync_items_priority_variation'),
                'Error Sync Items Priority Variation',
                $response['body']
            );

            exit(json_encode(['status' => 0, 'msg' => 'Error Sync Items Priority Variation']));

        }

    }


    /**
     * sync items from web to priority
     *
     */
    public function syncItemsWeb()
    {
        // get all items from priority
        $response = $this->makeRequest('GET', 'LOGPART');

        if (!$response['status']) {
            /**
             * t149
             */
            $this->sendEmailError(
                $this->option('email_error_sync_items_web'),
                'Error Sync Items Web',
                $response['body']
            );

        }

        $data = json_decode($response['body_raw'], true);

        $SKU = []; // Priority items SKU numbers

        // collect all SKU numbers
        foreach($data['value'] as $item) {
            $SKU[] = $item['PARTNAME'];
        }

        // get all products from woocommerce
        $products = get_posts(['post_type' => 'product', 'posts_per_page' => -1]); 

        $requests      = [];
        $json_requests = [];


        // loop trough products
        foreach($products as $product) {

            $meta   = get_post_meta($product->ID);
            $method = in_array($meta['_sku'][0], $SKU) ? 'PATCH' : 'POST';
            
            $json = json_encode([
                'PARTNAME'    => $meta['_sku'][0],
                'PARTDES'     => $product->post_title,
                'BASEPLPRICE' => (float) $meta['_regular_price'][0],
                'INVFLAG'     => ($meta['_manage_stock'][0] == 'yes') ? 'Y' : 'N'
            ]);  


            $this->makeRequest($method, 'LOGPART', ['body' => $json], $this->option('log_items_web', true));

        }

        // add timestamp
        $this->updateOption('items_web_update', time());


    }


    /**
     * sync inventory from priority
     */
    public function syncInventoryPriority()
    {
	// get the items simply by time stamp of today
    	$stamp = mktime(0, 0, 0);
    	$bod = date(DATE_ATOM,$stamp);
    	$url_addition = '(WARHSTRANSDATE ge '.$bod. ' or PURTRANSDATE ge '.$bod .' or SALETRANSDATE ge '.$bod.')';
    	if($this->option('variation_field')) {
	    $url_addition .= ' and ' . $this->option( 'variation_field' ) . ' eq \'\' ';
    	}
    	$response = $this->makeRequest('GET', 'LOGPART?$filter= '.urlencode($url_addition).' &$expand=LOGCOUNTERS_SUBFORM', [], $this->option('log_inventory_priority', true));

        // check response status
        if ($response['status']) {

            $data = json_decode($response['body_raw'], true);

            foreach($data['value'] as $item) {

	 // if product exsits, update

	            $args = array(
		            'post_type'		=>	'product',
		            'meta_query'	=>	array(
			            array(
				            'key'       => '_sku',
				            'value'	=>	$item['PARTNAME']
			            )
		            )
	            );
	            $my_query = new \WP_Query( $args );
	            if ( $my_query->have_posts() ) {
		            while ( $my_query->have_posts() ) {
			            $my_query->the_post();
			            $product_id = get_the_ID();


		            }
	            }else{
		            $product_id = 0;
	            }
		    
                //if ($id = wc_get_product_id_by_sku($item['PARTNAME'])) {
	     if(!$product_id == 0){
                    update_post_meta($product_id, '_sku', $item['PARTNAME']);
                    update_post_meta($product_id, '_stock', $item['LOGCOUNTERS_SUBFORM'][0]['DIFF']);

                    if (intval($item['LOGCOUNTERS_SUBFORM'][0]['DIFF']) > 0) {
                        update_post_meta($product_id, '_stock_status', 'instock');
                    } else {
                        update_post_meta($product_id, '_stock_status', 'outofstock');
                    }
                }
                
            }

            // add timestamp
            $this->updateOption('inventory_priority_update', time());

        } else {
            /**
             * t149
             */
            $this->sendEmailError(
                $this->option('email_error_sync_inventory_priority'),
                'Error Sync Inventory Priority',
                $response['body']
            );

        }

    }


    /**
     * sync Customer by given ID
     *
     * @param [int] $id
     */
       public function syncCustomer($id)
    {
        // check user
        if ($user = get_userdata($id)) {

            $meta = get_user_meta($id);
            $priority_customer_number = 'WEB-'.(string) $user->data->ID;
            $json_request = json_encode([
                'CUSTNAME'    => $priority_customer_number,
                'CUSTDES'     => empty($meta['first_name'][0]) ? $meta['nickname'][0] : $meta['first_name'][0] . ' ' . $meta['last_name'][0],
                'EMAIL'       => $user->data->user_email,
                'ADDRESS'     => isset($meta['billing_address_1']) ? $meta['billing_address_1'][0] : '',
                'ADDRESS2'    => isset($meta['billing_address_2']) ? $meta['billing_address_2'][0] : '',
                'STATEA'      => isset($meta['billing_city'])      ? $meta['billing_city'][0] : '',
                'ZIP'         => isset($meta['billing_postcode'])  ? $meta['billing_postcode'][0] : '',
                'COUNTRYNAME' => isset($meta['billing_country'])   ? $this->countries[$meta['billing_country'][0]] : '',
                'PHONE'       => isset($meta['billing_phone'])     ? $meta['billing_phone'][0] : '',
            ]);
    
            $method = isset($meta['priority_customer_number']) ? 'PATCH' : 'POST';
    
            $response = $this->makeRequest($method, 'CUSTOMERS', ['body' => $json_request], $this->option('log_customers_web', true));

            // set priority customer id
            if ($response['status']) {
                update_user_meta($id, 'priority_customer_number', $priority_customer_number, true);
            } else {
                /**
                 * t149
                 */
                $this->sendEmailError(
                    $this->option('email_error_sync_customers_web'),
                    'Error Sync Customers',
                    $response['body']
                );

            }
    
            // add timestamp
            $this->updateOption('customers_web_update', time());
    
        }

    }

    public function syncPriorityOrderStatus(){
	    $url_addition =  'ORDERS?$filter=BOOKNUM ne \'\'  and ';
	    $date = date('Y-m-d');
	    $prev_date = date('Y-m-d', strtotime($date .' -10 day'));
	    $url_addition .= 'CURDATE ge '.$prev_date;
	    
	    $response     =  $this->makeRequest( 'GET', $url_addition, null, true ) ;
	    $orders = json_decode($response['body'],true)['value'];
	    $output = '';
	    foreach ( $orders as $el ) {
		    $order_id = $el['BOOKNUM'];
		    $order = wc_get_order( $order_id );
		    $pri_status = $el['ORDSTATUSDES'];
		    if($order){
			    update_post_meta($order_id,'priority_status',$pri_status);
			    $output .= '<br>'.$order_id.' '.$pri_status.' ';
		    }
	    }
	    $this->updateOption('auto_sync_order_status_priority_update', time());
    }
    public function syncOrders(){
	    $query = new \WC_Order_Query( array(
		    //'limit' => get_option('posts_per_page'),
		    'limit' => 1000,
		    'orderby' => 'date',
		    'order' => 'DESC',
		    'return' => 'ids',
		    'meta_key'     => 'priority_status', // The postmeta key field
		    'meta_compare' => 'NOT EXISTS', // The comparison argument 
	    ) );
	    
	    $orders = $query->get_orders();
	    foreach ($orders as $id){
		    $order =wc_get_order($id);
		    $priority_status = $order->get_meta('priority_status');
		    if(!$priority_status){

			    $response = $this->syncOrder($id,$this->option('log_auto_post_orders_priority', true));


		    }

	    };
	    $this->updateOption('auto_post_orders_priority_update', time());
    }

	/**
	 * Sync order by id
	 *
	 * @param [int] $id
	 */
    public function syncOrder($id)
    {
	    if(isset(WC()->session)){
	    $session = WC()->session->get('session_vars');
	        if($session['ordertype']=='Recipe'){
		   return;
            }
	}
        $order = new \WC_Order($id);
	    $user = $order->get_user();
	    $user_id = $order->get_user_id();
	   // $user_id = $order->user_id;
	    $order_user = get_userdata($user_id); //$user_id is passed as a parameter

	 

	    

        if ($order->get_customer_id()) {
            $meta = get_user_meta($order->get_customer_id());
            $cust_number = ($meta['priority_customer_number']) ? $meta['priority_customer_number'][0] : $this->option('walkin_number');
        } else {
            $cust_number = $this->option('walkin_number');
        }

        $data = [
            'CUSTNAME' => $cust_number,
            'CDES'     => ($order->get_customer_id()) ? '' : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'CURDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
            'BOOKNUM'  => $order->get_order_number(),
            //'DCODE' => $priority_dep_number, // this is the site in Priority
            //'DETAILS' => $user_department,
           
        ];
	
// order comments
      $data['ORDERSTEXT_SUBFORM'] =   ['TEXT' => $order->get_customer_note()];
	
	    
	   // billing customer details
        $customer_data = [
          
            'PHONE'    => $order->get_billing_phone(),
            'EMAIL'       => $order->get_billing_email(),
            'ADRS'        => $order->get_billing_address_1(),
            'ADRS2'       => $order->get_billing_address_2(),
            'STATEA'      => $order->get_billing_city(),
            'ZIP'         => $order->get_shipping_postcode(),
        ];
        $data['ORDERSCONT_SUBFORM'][] = $customer_data;

	// shipping

        // shop address debug

        $shipping_data = [
            'NAME'        => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'CUSTDES'     => $order_user->user_firstname . ' ' . $order_user->user_lastname,
            'PHONENUM'    => $order->get_billing_phone(),
            'EMAIL'       => $order->get_billing_email(),
            'CELLPHONE'   => $order->get_billing_phone(),
            'ADDRESS'     => $order->get_shipping_address_1(),
            'ADDRESS2'    => $order->get_shipping_address_2(),
            'STATE'       => $order->get_shipping_city(),
            'ZIP'         => $order->get_shipping_postcode(),
        ];

        // add second address if entered
        if ( ! empty($order->get_shipping_address_2())) {
            $shipping_data['ADDRESS2'] = $order->get_shipping_address_2();
        }

        $data['SHIPTO2_SUBFORM'] = $shipping_data;

        // get shipping id
        $shipping_method    = $order->get_shipping_methods();
        $shipping_method    = array_shift($shipping_method);
        $shipping_method_id = str_replace(':', '_', $shipping_method['method_id']);

        // get parameters
        $params = [];


        // get ordered items
        foreach ($order->get_items() as $item) {

            $product = $item->get_product();

            $parameters = [];

	        // get tax
	        // Initializing variables
	        $tax_items_labels   = array(); // The tax labels by $rate Ids
	        $tax_label = 0.0 ; // The total VAT by order line
	        $taxes = $item->get_taxes();
	        // Loop through taxes array to get the right label
	        foreach( $taxes['subtotal'] as $rate_id => $tax ) {
		        $tax_label = + $tax; // <== Here the line item tax label
	        }

            // get meta
            foreach($item->get_meta_data() as $meta) {

                if(isset($params[$meta->key])) {
                    $parameters[$params[$meta->key]] = $meta->value;
                }

            }

            if ($product) {

                /*start T151*/
                $new_data = [];

                $item_meta = wc_get_order_item_meta($item->get_id(),'_tmcartepo_data');

                if ($item_meta && is_array($item_meta)) {
                    foreach ($item_meta as $tm_item) {
                        $new_data[] = [
                            'SPEC' => addslashes($tm_item['name']),
                            'VALUE' => htmlspecialchars(addslashes($tm_item['value']))
                        ];
                    }
                }

                /*end T151*/

                $data['ORDERITEMS_SUBFORM'][] = [
                    'PARTNAME'         => $product->get_sku(),
                    'TQUANT'           => (int) $item->get_quantity(),
                    'PRICE'            => (float) $item->get_total() ,  //  if you are working without tax prices you need to modify this line Roy 7.10.18
                    'REMARK1'          => isset($parameters['REMARK1']) ? $parameters['REMARK1'] : '',
                    //'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
                ];
            }
            
        }
	 // cart discount
	    if( get_post_meta($id,'_cart_discount')[0]) {
		    $data['ORDERITEMS_SUBFORM'][] = [
			    // 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
			    'PARTNAME' => '000',
			    'VATPRICE' => -1*floatval( get_post_meta($id,'_cart_discount')[0] ),
			    'TQUANT'   => -1,

		    ];
	    }
	 // shipping rate
        if( $order->get_shipping_method()) {
	        $data['ORDERITEMS_SUBFORM'][] = [
		        // 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
		        'PARTNAME' => $this->option( 'shipping_' . $shipping_method_id . '_'.$shipping_method['instance_id'], $order->get_shipping_method() ),
		        'TQUANT'   => 1,
		        'VATPRICE' => floatval( $order->get_shipping_total() ),
		        "REMARK1"  => "",
	        ];
        }

       
        /* get credit guard meta

	    $order_ccnumber = $order->get_meta('_ccnumber');
	    $order_token = $order->get_meta('_creditguard_token');
	    $order_creditguard_expiration = $order->get_meta('_creditguard_expiration');
	    $order_creditguard_authorization = $order->get_meta('_creditguard_authorization');
	    $order_payments = $order->get_meta('_payments');
	    $order_first_payment = $order->get_meta('_first_payment');
	    $order_periodical_payment = $order->get_meta('_periodical_payment');
	    */

	    /* credit guard dummy data
		$order_ccnumber = '1234';
		$order_token = '123456789';
		$order_creditguard_expiration = '0124';
		$order_creditguard_authorization = '09090909';
		$order_payments = $order->get_meta('_payments');
		$order_first_payment = $order->get_meta('_first_payment');
		$order_periodical_payment = $order->get_meta('_periodical_payment');
	    */


	    // pelecard dummy data
        /*
        $args =
                   [
	              'StatusCode' => '000',
                    'ErrorMessage' => 'operation success',
                    'TransactionId' => 'e19d3a85-4096-4d81-b028-bae50b2f4000',
                    'ShvaResult' => '000',
                    'AdditionalDetailsParamX' => '197',
                    'Token' => '' ,
                    'DebitApproveNumber' => '0080152',
                    'ConfirmationKey' => '36eddfff0cb4124a9b52bbac34ab3d6f',
                    'VoucherId' => '05-001-001',
                    'TransactionPelecardId' => '504338834',
                    'CardHolderID' => '040369662',
                    'CardHolderName' => '' ,
                    'CardHolderEmail' => '' ,
                    'CardHolderPhone'  => '' ,
                    'CardHolderAddress' => '' ,
                    'CardHolderCity' => '' ,
                    'CardHolderZipCode' => '' ,
                    'CardHolderCountry' => '' ,
                    'ShvaFileNumber' => '',
                    'StationNumber' =>  '1',
                    'Reciept' => '1',
                    'JParam'  => '4',
                    'CreditCardNumber' => '458003******1944',
                    'CreditCardExpDate'  => '1119',
                    'CreditCardCompanyClearer' => '6',
                    'CreditCardCompanyIssuer' => '6',
                    'CreditCardStarsDiscountTotal' => '0',
                    'CreditType' => '1',
                    'CreditCardAbroadCard' => '0',
                    'DebitType' => '1',
                    'DebitCode' => '50',
                    'DebitTotal' => '261',
                    'DebitCurrency' => '1',
                    'TotalPayments' => '1',
                    'FirstPaymentTotal' => '0',
                    'FixedPaymentTotal' => '0',
                    'CreditCardBrand' => '2',
                    'CardHebrewName' => 'לאומי קארד',
                    'ShvaOutput' => '0000000458003******194426000411191100000261 0000000060110150001008015200000000000000000005001001 ƒ˜€— ‰…€0 197',
                    'ApprovedBy' => '1',
                    'CallReason' => '0',
                    'TransactionInitTime' => '24\/07\/2019 23:38:13',
                    'TransactionUpdateTime' => '24\/07\/2019 23:39:08',
                    'Remarks' => '',
                    'BusinessNumber' => ''
                ];

	    $order->update_meta_data('_transaction_data',$args);
	    $order->save();
        */
	    /* get meta pelecard

        $order_cc_meta = $order->get_meta('_transaction_data');
        $order_ccnumber = $order_cc_meta['CreditCardNumber'];
  	    $order_token =  $order_cc_meta['Token'];
	    $order_cc_expiration =  $order_cc_meta['CreditCardExpDate'];
	    $order_cc_authorization = $order_cc_meta['ConfirmationKey'];

        */



	    // payment info
	  /*  $data['PAYMENTDEF_SUBFORM'] = [
		    'PAYMENTCODE' => $this->option('payment_' . $order->get_payment_method(), $order->get_payment_method()),
		    'QPRICE'      => floatval($order->get_total()),
		    'PAYACCOUNT'  => '',
		    'PAYCODE'     => '',
		    'PAYACCOUNT'  => $order_ccnumber,
		    'VALIDMONTH'  => $order_cc_expiration,
		    'CCUID' => $order_token,
		    'CONFNUM' => $order_cc_authorization,
		    //'ROYY_NUMBEROFPAY' => $order_payments,
		    //'FIRSTPAY' => $order_first_payment,
		    //'ROYY_SECONDPAYMENT' => $order_periodical_payment

	    ];*/

	    // HERE goes the condition to avoid the repetition
	    $post_done = get_post_meta( $order->get_id(), '_post_done', true);
	    if( empty($post_done) ) {

        // make request
        $response = $this->makeRequest('POST', 'ORDERS', ['body' => json_encode($data)], true);

        if ($response['code']<=201) {
	        $body_array = json_decode($response["body"],true);

	        $ord_status = $body_array["ORDSTATUSDES"];
	        $ord_number = $body_array["ORDNAME"];
	        $order->update_meta_data('priority_status',$ord_status);
	        $order->update_meta_data('priority_ordnumber',$ord_number);
	        $order->save();
        }
        if($response['code'] >= 400){
	        $body_array = json_decode($response["body"],true);

	        //$ord_status = $body_array["ORDSTATUSDES"];
	       // $ord_number = $body_array["ORDNAME"];
	        $order->update_meta_data('priority_status',$response["body"]);
	       // $order->update_meta_data('priority_ordnumber',$ord_number);
	        $order->save();
        }
        }
        if (!$response['status']||$response['code'] >= 400) {
            /**
             * t149
             */
            $this->sendEmailError(
            $this->option('email_error_sync_orders_web'),
            'Error Sync Orders',
            $response['body']
            );
        }

        // add timestamp
    return $response;
    }


    /**
     * Sync customer data and order data
     *
     * @param [int] $order_id
     */
    public function syncDataAfterOrder($order_id)
    {
	if(empty(get_post_meta($order_id,'priority_status',false)[0])){
		// get order
		$order = new \WC_Order($order_id);

		// sync customer if it's signed in / registered
		// guest user will have id 0
		/*if ($customer_id = $order->get_customer_id()) {
		    $this->syncCustomer($customer_id);
		}*/
		// sync order
		if($this->option('post_order_checkout')) {
			$this->syncOrder( $order_id );
		}
		// sync OTC
		if($this->option('post_einvoices_checkout')) {
			$this->syncOverTheCounterInvoice( $order_id );
		}
		if($this->option('sync_onorder_receipts')) {
		    // sync receipts 
		    $this->syncReceipt($order_id);
		}
	 }
	// sync payments
	    $session = WC()->session->get('session_vars');
	    if($session['ordertype']=='Recipe') {
	        $this->syncPayment($order_id);
	    }

    }


    /**
     * Sync price lists from priority to web
     */
    public function syncPriceLists()
    {
        $response = $this->makeRequest('GET', 'PRICELIST?$expand=PLISTCUSTOMERS_SUBFORM,PARTPRICE2_SUBFORM', [], $this->option('log_pricelist_priority', true));

        // check response status
        if ($response['status']) {

            // allow multisite
            $blog_id =  get_current_blog_id();

            // price lists table
            $table =  $GLOBALS['wpdb']->prefix . 'p18a_pricelists';

            // delete all existing data from price list table
            $GLOBALS['wpdb']->query('DELETE FROM ' . $table);

            // decode raw response
            $data = json_decode($response['body_raw'], true);

            $priceList = [];

            if (isset($data['value'])) {

                foreach($data['value'] as $list)
                {
                    /* 

                    Assign user to price list, no needed for now

                    // update customers price list
                    foreach($list['PLISTCUSTOMERS_SUBFORM'] as $customer) {
                        update_user_meta($customer['CUSTNAME'], '_priority_price_list', $list['PLNAME']);
                    }
                    */

                    // products price lists
                    foreach($list['PARTPRICE2_SUBFORM'] as $product) {

                        $GLOBALS['wpdb']->insert($table, [
                            'product_sku' => $product['PARTNAME'],
                            'price_list_code' => $list['PLNAME'],
                            'price_list_name' => $list['PLDES'],
                            'price_list_currency' => $list['CODE'],
                            'price_list_price' => $product['PRICE'],
                            'blog_id' => $blog_id
                        ]); 

                    }
                    
                }

                // add timestamp
                $this->updateOption('pricelist_priority_update', time());

            }

        } else {
            /**
             * t149
             */
            $this->sendEmailError(
                $this->option('email_error_sync_pricelist_priority'),
                'Error Sync Price Lists Priority',
                $response['body']
            );

        }

    }

    /* sync sites */
	public function syncSites()
	{
		$response = $this->makeRequest('GET', 'CUSTOMERS?$expand=CUSTDESTS_SUBFORM', [], $this->option('log_sites_priority', true));

		// check response status
		if ($response['status']) {

			// allow multisite
			$blog_id =  get_current_blog_id();

			// sites table
			$table =  $GLOBALS['wpdb']->prefix . 'p18a_sites';

			// delete all existing data from price list table
			$GLOBALS['wpdb']->query('DELETE FROM ' . $table);

			// decode raw response
			$data = json_decode($response['body_raw'], true);

			$sites = [];

			if (isset($data['value'])) {

				foreach($data['value'] as $list)
				{
					// products price lists
					foreach($list['CUSTDESTS_SUBFORM'] as $site) {

						$GLOBALS['wpdb']->insert($table, [
							'sitecode' => $site['CODE'],
							'sitedesc' => $site['CODEDES'],
							'customer_number' => $list['CUSTNAME'],
							'address1' => $site['ADDRESS']
						]);

					}

				}

				// add timestamp
				$this->updateOption('pricelist_priority_update', time());

			}

		} else {
			/**
			 * t149
			 */
			$this->sendEmailError(
				$this->option('email_error_sync_pricelist_priority'),
				'Error Sync Price Lists Priority',
				$response['body']
			);

		}

	}

	
	
	
	/* sync over the counter invoice EINVOICES */

  
public function syncOverTheCounterInvoice($order_id,$log)
	{
		$order = new \WC_Order($order_id);
		$user = $order->get_user();
		$user_id = $order->get_user_id();
		$order_user = get_userdata($user_id); //$user_id is passed as a parameter
		if ($order->get_customer_id()) {
			$meta = get_user_meta($order->get_customer_id());
			$cust_number = ($meta['priority_customer_number']) ? $meta['priority_customer_number'][0] : $this->option('walkin_number');
		} else {
			$cust_number = $this->option('walkin_number');
		}
		$data = [
			'CUSTNAME'  => $cust_number,
			'CDES'      => ($order->get_customer_id()) ? '' : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'IVDATE' => date('Y-m-d', strtotime($order->get_date_created())),
			'BOOKNUM' => $order->get_order_number(),

		];

		// order comments
		$order_comment_array = explode("\n", $order->get_customer_note());
		foreach($order_comment_array as $comment){
			$data['PINVOICESTEXT_SUBFORM'][] = [
				'TEXT' =>preg_replace('/(\v|\s)+/', ' ',$comment),
			];
		}
		// shipping
		$shipping_data = [
			'NAME'        => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'CUSTDES'     => $order_user->user_firstname . ' ' . $order_user->user_lastname,
			'PHONENUM'    => $order->get_billing_phone(),
			'EMAIL'       => $order->get_billing_email(),
			'CELLPHONE'   => $order->get_billing_phone(),
			'ADDRESS'     => $order->get_billing_address_1(),
			'ADDRESS2'    => $order->get_billing_address_2(),
			'STATE'       => $order->get_billing_city(),
			'ZIP'         => $order->get_shipping_postcode(),
		];

		// add second address if entered
		if ( ! empty($order->get_shipping_address_2())) {
			$shipping_data['ADDRESS2'] = $order->get_shipping_address_2();
		}

		$data['SHIPTO2_SUBFORM'] = $shipping_data;
		// get ordered items
        foreach ($order->get_items() as $item) {

			$product = $item->get_product();

			$parameters = [];

			// get tax
			// Initializing variables
			$tax_items_labels   = array(); // The tax labels by $rate Ids
			$tax_label = 0.0 ; // The total VAT by order line
			$taxes = $item->get_taxes();
			// Loop through taxes array to get the right label
			foreach( $taxes['subtotal'] as $rate_id => $tax ) {
				$tax_label = + $tax; // <== Here the line item tax label
			}


			if ($product) {

				$data['EINVOICEITEMS_SUBFORM'][] = [
					'PARTNAME'         => $product->get_sku(),
					'TQUANT'           => (int) $item->get_quantity(),
					'TOTPRICE'            => round((float) ($item->get_total() + $tax_label) ,2),


				];
			}

		}



		// pelecard
		$order_ccnumber = '';
		$order_token =  '';
		$order_cc_expiration = '';
		$order_cc_authorization = '';
		$order_cc_qprice = 0.0;
		/*
		$order_cc_meta = $order->get_meta('_transaction_data');
		$order_ccnumber = $order_cc_meta['CreditCardNumber'];
		$order_token =  $order_cc_meta['Token'];
		$order_cc_expiration =  $order_cc_meta['CreditCardExpDate'];
		$order_cc_authorization = $order_cc_meta['ConfirmationKey'];
		$order_cc_qprice = $order_cc_meta['DebitTotal']/100;
		*/
		// payment info
		$data['EPAYMENT2_SUBFORM'][] = [
			'PAYMENTCODE' => $this->option('payment_' . $order->get_payment_method(), $order->get_payment_method()),
			'QPRICE'      => floatval($order_cc_qprice), //floatval($order->get_total()),
			'FIRSTPAY'      => floatval($order_cc_qprice), //floatval($order->get_total()),
			'PAYACCOUNT'  => '',
			'PAYCODE'     => '',
			'PAYACCOUNT'  => $order_ccnumber,
			'VALIDMONTH'  => $order_cc_expiration,
			'CCUID' => $order_token,
			'CONFNUM' => $order_cc_authorization,
		];
		// make request
		$response = $this->makeRequest('POST', 'EINVOICES', ['body' => json_encode($data)], true);
		if ($response['code']<=201) {
			$body_array = json_decode($response["body"],true);

			$ord_status = $body_array["STATDES"];
			$ord_number = $body_array["IVNUM"];
			$order->update_meta_data('priority_status',$ord_status);
			$order->update_meta_data('priority_ordnumber',$ord_number);
			$order->save();
		}
		if($response['code'] >= 400){
			$body_array = json_decode($response["body"],true);

			//$ord_status = $body_array["ORDSTATUSDES"];
			// $ord_number = $body_array["ORDNAME"];
			$order->update_meta_data('priority_status',$response["body"]);
			// $order->update_meta_data('priority_ordnumber',$ord_number);
			$order->save();
		}
		if (!$response['status']) {
			/**
			 * t149
			 */
			$this->sendEmailError(
				$this->option('email_error_sync_einvoices_web'),
				'Error Sync OTC invoice',
				$response['body']
			);
		}


		return $response;



	}
	
    public function syncReceipt($order_id)
    {

        $order = new \WC_Order($order_id);

        $data = [
            'CUSTNAME' => ( ! $order->get_customer_id()) ? $this->option('walkin_number') : (string) $order->get_customer_id(),
            'CDES' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'IVDATE' => date('Y-m-d', strtotime($order->get_date_created())),
            'BOOKNUM' => $order->get_order_number(),

        ];

        // cash payment
        if(strtolower($order->get_payment_method()) == 'cod') {

            $data['CASHPAYMENT'] = floatval($order->get_total());

        } else {

             // payment info
            $data['TPAYMENT2_SUBFORM'][] = [
                'PAYMENTCODE' => $this->option('payment_' . $order->get_payment_method(), $order->get_payment_method()),
                'QPRICE'      => floatval($order->get_total()),
                'PAYACCOUNT'  => '',
                'PAYCODE'     => ''
            ];
            
        }


        // make request
        $response = $this->makeRequest('POST', 'TINVOICES', ['body' => json_encode($data)], $this->option('log_receipts_priority', true));
        if (!$response['status']) {
            /**
             * t149
             */
            $this->sendEmailError(
                $this->option('email_error_sync_receipts_priority'),
                'Error Sync Receipts',
                $response['body']
            );
        }
        // add timestamp
        $this->updateOption('receipts_priority_update', time());
        
    }
	public function syncPayment($order_id)
	{

		$order = new \WC_Order($order_id);
		$priority_customer_number = get_user_meta( $order->get_customer_id(), 'priority_customer_number', true );
		$data = [
			'CUSTNAME' => $priority_customer_number,
			'CDES' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
			'IVDATE' => date('Y-m-d', strtotime($order->get_date_created())),
			'BOOKNUM' => $order->get_order_number(),

		];

		// cash payment
		if(strtolower($order->get_payment_method()) == 'cod') {

			$data['CASHPAYMENT'] = floatval($order->get_total());

		} else {

			// payment info
			$data['TPAYMENT2_SUBFORM'][] = [
				'PAYMENTCODE' => $this->option('payment_' . $order->get_payment_method(), $order->get_payment_method()),
				'QPRICE'      => floatval($order->get_total()),
				'PAYACCOUNT'  => '',
				'PAYCODE'     => ''
			];

		}

		foreach ($order->get_items() as $item) {
            $ivnum = $item->get_meta('product-ivnum');
			$data['TFNCITEMS_SUBFORM'][] = [
				'CREDIT'    => (float) $item->get_total(),
				'FNCIREF1'  =>  $ivnum
			];
		}



		// make request
		$response = $this->makeRequest('POST', 'TINVOICES', ['body' => json_encode($data)], $this->option('log_receipts_priority', true));
		if (!$response['status']) {
			/**
			 * t149
			 */
			$this->sendEmailError(
				$this->option('email_error_sync_receipts_priority'),
				'Error Sync Receipts',
				$response['body']
			);
		}
		// add timestamp
		$this->updateOption('receipts_priority_update', time());

	}



    /**
     * Sync receipts for completed orders
     *
     * @return void
     */
    public function syncReceipts()
    {
        // get all completed orders
        $orders = wc_get_orders(['status' => 'completed']);
        
        foreach($orders as $order) {
            $this->syncReceipt($order->get_id());
        }
    }

    // filter products by user price list
    public function filterProductsByPriceList($ids)
    {

        if($user_id = get_current_user_id()) {

            $meta = get_user_meta($user_id, '_priority_price_list');

            if ($meta[0] === 'no-selected') return $ids;

            $list = empty($meta) ? $this->basePriceCode : $meta[0];

            $products = $GLOBALS['wpdb']->get_results('
                SELECT product_sku
                FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                WHERE price_list_code = "' . esc_sql($list) . '"
                AND blog_id = ' . get_current_blog_id(), 
                ARRAY_A
            );

            $ids = [];
        
            // get product id
            foreach($products as $product) {
                if ($id = wc_get_product_id_by_sku($product['product_sku'])) {
                    $parent_id = get_post($id)->post_parent;
                    if ($parent_id) $ids[] = $parent_id;
                    $ids[] = $id;
                }
            }

            $ids = array_unique($ids);

            // there is no products assigned to price list, return 0
            if (empty($ids)) return 0;

            // return ids
            return $ids;

        }

        // not logged in user
        return [];
    }


    /**
     * Get all price lists
     *
     */
    public function getPriceLists()
    {
        if (empty(static::$priceList))
        {
            static::$priceList = $GLOBALS['wpdb']->get_results('
                SELECT DISTINCT price_list_code, price_list_name FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                WHERE blog_id = ' . get_current_blog_id(), 
                ARRAY_A
            );
        }

        return static::$priceList;
    }

    /**
     * Get price list data by price list code
     *
     * @param  $code
     */
    public function getPriceListData($code)
    {
        $data = $GLOBALS['wpdb']->get_row('
            SELECT *
            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
            WHERE price_list_code = "' . esc_sql($code) . '"
            AND blog_id = ' . get_current_blog_id(), 
            ARRAY_A
        );

        return $data;

    }

    /**
     * Get product data regarding to price list assigned for user
     *
     * @param $id product id
     */
    public function getProductDataBySku($sku)
    {

        if($user_id = get_current_user_id()) {

            $meta = get_user_meta($user_id, '_priority_price_list');

            if ($meta[0] === 'no-selected') return 'no-selected';

            $list = empty($meta) ? $this->basePriceCode : $meta[0]; // use base price list if there is no list assigned

            $data = $GLOBALS['wpdb']->get_row('
                SELECT price_list_price, price_list_currency
                FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                WHERE product_sku = "' . esc_sql($sku) . '"
                AND price_list_code = "' . esc_sql($list) . '"
                AND blog_id = ' . get_current_blog_id(), 
                ARRAY_A
            );

            return $data;

        }

        return false;

    }   


    // filter product price
    public function filterPrice($price, $product)
    {
        $data = $this->getProductDataBySku($product->get_sku());

	    if ($data && $data !== 'no-selected') return $data['price_list_price'];
        //if ((!is_cart() && !is_checkout()) && $data && $data !== 'no-selected') return $data['price_list_price'];
        
        return $price;
    }

    // filter price range for products with variations
    public function filterPriceRange($price, $product) 
    {
        $variations = $product->get_available_variations();

        $prices = [];

        foreach($variations as $variation) {

            $data = $this->getProductDataBySku($variation['sku']);

            if ($data !== 'no-selected') {
                $prices[] = $data['price_list_price'];
            }

        }

        if ( ! empty($prices)) {
            return wc_price(min($prices)) . ' - ' . wc_price(max($prices));
        }

        return $price;

    }

	function crf_show_extra_profile_fields( $user ) {
		$priority_customer_number = get_the_author_meta( 'priority_customer_number', $user->ID );
		?>
		<h3><?php esc_html_e( 'Priority API User Information', 'p18a' ); ?></h3>

		<table class="form-table">
			<tr>
				<th><label for="Priority Customer Number"><?php esc_html_e( 'Priority Customer Number', 'p18a' ); ?></label></th>
				<td>
					<input type="text"

					       id="priority_customer_number"
					       name="priority_customer_number"
					       value="<?php echo esc_attr( $priority_customer_number ); ?>"
					       class="regular-text"
					/>
				</td>
			</tr>
		</table>
		<?php
	}

	function crf_update_profile_fields( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		if ( ! empty( $_POST['priority_customer_number'] ) ) {
			update_user_meta( $user_id, 'priority_customer_number',  $_POST['priority_customer_number']  );
		}
	}


}
