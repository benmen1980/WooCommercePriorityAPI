<?php
/**
 * @package     Priority Woocommerce API
 * @author      Ante Laca <ante.laca@gmail.com>
 * @copyright   2018 Roi Holdings
 */

namespace PriorityWoocommerceAPI;


use PHPMailer\PHPMailer\Exception;

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
         * Schedule cron  syncs
         */
        $syncs = [
            'sync_items_priority'           => 'syncItemsPriority',
            'sync_items_priority_variation' => 'syncItemsPriorityVariation',
            'sync_items_web'                => 'syncItemsWeb',
            'sync_inventory_priority'       => 'syncInventoryPriority',
            'sync_pricelist_priority'       => 'syncPriceLists',
            'sync_receipts_priority'        => 'syncReceipts',
            'sync_order_status_priority'    => 'syncPriorityOrderStatus',
            'sync_sites_priority'           => 'syncSites',
            'sync_c_products_priority'      => 'syncCustomerProducts',
            'sync_customer_to_wp_user'      => 'sync_priority_customers_to_wp'
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

        // sync order control
        $syncs = [
            'cron_orders'          => 'syncOrders',
            'cron_receipt'          => 'syncReceipts',
            'cron_ainvoice'          => 'syncAinvoices',
            'cron_otc'          => 'syncOtcs'
        ];
        foreach ($syncs as $hook => $action) {
            // Schedule sync
            if ($this->option( $hook, false)) {

                add_action($hook, [$this, $action]);

                if ( ! wp_next_scheduled($hook)) {
                    wp_schedule_event(time(), $this->option( $hook), $hook);
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
        add_action( 'woocommerce_thankyou', [ $this, 'syncDataAfterOrder' ],9999 );
        add_action( 'woocommerce_payment_complete', [ $this, 'syncDataAfterOrder' ],9999 );
        add_action( 'woocommerce_order_status_changed', [ $this, 'syncDataAfterOrder' ]);
        // custom check out fields
        //add_action( 'woocommerce_after_checkout_billing_form', array( $this ,'custom_checkout_fields'));
        add_action('woocommerce_checkout_process', array($this,'my_custom_checkout_field_process'));
        add_action( 'woocommerce_checkout_update_order_meta',array($this,'my_custom_checkout_field_update_order_meta' ));


        // sync user to priority after registration
        if ( $this->option( 'post_customers' ) == true ) {
            add_action( 'user_register', [ $this, 'syncCustomer' ],999 );
            //add_action( 'user_new_form', [ $this, 'syncCustomer' ],999 );
            add_action( 'woocommerce_customer_save_address', [ $this, 'syncCustomer' ],999 );
        }


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
                            include P18AW_ADMIN_DIR . 'customersProducts.php';
                            break;
                        case 'sync_attachments';
                            include P18AW_ADMIN_DIR . 'syncs/sync_product_attachemtns.php';
                            break;
                        case 'packs';
                            $this->syncPacksPriority();
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

                $this->updateOption('walkin_number',       $this->post('walkin_number'));
                $this->updateOption('price_method',        $this->post('price_method'));
                $this->updateOption('item_status',         $this->post('item_status'));
                $this->updateOption('variation_field',     $this->post('variation_field'));
                $this->updateOption('variation_field_title',  $this->post('variation_field_title'));
                $this->updateOption('sell_by_pl',          $this->post('sell_by_pl'));
                $this->updateOption('walkin_hide_price',   $this->post('walkin_hide_price'));
                $this->updateOption('sites',               $this->post('sites'));
                $this->updateOption('update_image',        $this->post('update_image'));
                $this->updateOption('mailing_list_field',  $this->post('mailing_list_field'));
                $this->updateOption('obligo',              $this->post('obligo'));
                $this->updateOption('setting-config',              $this->post('setting-config'));









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
                $this->updateOption('email_error_sync_customers_web',       $this->post('email_error_sync_customers_web'));
                $this->updateOption('log_shipping_methods',                 $this->post('log_shipping_methods'));
                $this->updateOption('email_error_sync_orders_web',          $this->post('email_error_sync_orders_web'));
                $this->updateOption('email_error_sync_ainvoices_priority',  $this->post('email_error_sync_ainvoices_priority'));
                $this->updateOption('log_sync_order_status_priority',       $this->post('log_sync_order_status_priority'));
                $this->updateOption('auto_sync_order_status_priority',      $this->post('auto_sync_order_status_priority'));
                $this->updateOption('auto_sync_orders_priority',            $this->post('auto_sync_orders_priority'));
                $this->updateOption('log_auto_post_orders_priority',        $this->post('log_auto_post_orders_priority'));
                $this->updateOption('auto_sync_sites_priority',             $this->post('auto_sync_sites_priority'));
                $this->updateOption('log_sites_priority',                   $this->post('log_sites_priority'));
                $this->updateOption('auto_sync_c_products_priority',        $this->post('auto_sync_c_products_priority'));
                $this->updateOption('log_c_products_priority',              $this->post('log_c_products_priority'));
                $this->updateOption('email_error_sync_einvoices_web',       $this->post('email_error_sync_einvoices_web'));
                // extra data
                $this->updateOption('sync_inventory_warhsname',       $this->post('sync_inventory_warhsname'));
                // sync orders control
                $this->updateOption('post_receipt_checkout',                $this->post('post_receipt_checkout'));
                $this->updateOption('cron_receipt',                $this->post('cron_receipt'));
                $this->updateOption('receipt_order_field',                $this->post('receipt_order_field'));
                $this->updateOption('post_ainvoice_checkout',               $this->post('post_ainvoice_checkout'));
                $this->updateOption('cron_ainvoice',               $this->post('cron_ainvoice'));
                $this->updateOption('ainvoice_order_field',               $this->post('ainvoice_order_field'));
                $this->updateOption('post_customers',                    $this->post('post_customers'));
                $this->updateOption('post_order_checkout',              $this->post('post_order_checkout'));
                $this->updateOption('cron_orders',                  $this->post('cron_orders'));
                $this->updateOption('order_order_field',                  $this->post('order_order_field'));
                $this->updateOption('post_einvoice_checkout',              $this->post('post_einvoice_checkout'));
                $this->updateOption('cron_otc',              $this->post('cron_otc'));
                $this->updateOption('otc_order_field',              $this->post('otc_order_field'));
                $this->updateOption('post_prospect',              $this->post('post_prospect'));
                $this->updateOption('prospect_field',              $this->post('prospect_field'));
                $this->updateOption('sync_items_priority_config',             stripslashes($this->post('sync_items_priority_config')));
                // customer_to_wp_user
                $this->updateOption('sync_customer_to_wp_user',             stripslashes($this->post('sync_customer_to_wp_user')));
                $this->updateOption('sync_customer_to_wp_user_config',             stripslashes($this->post('sync_customer_to_wp_user_config')));
                $this->updateOption('auto_sync_customer_to_wp_user',             stripslashes($this->post('auto_sync_customer_to_wp_user')));


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
                if($this->option('post_order_checkout')) {
                    // add the Priority order number
                    $columns['priority_order_number'] = '<span>' . __('Priority Order', 'woocommerce') . '</span>'; // title
                    $columns['priority_order_status'] = '<span>' . __('Priority Order Status', 'woocommerce') . '</span>'; // title

                }
                //add the new column "Status"
                if($this->option('post_einvoice_checkout')|| $this->option('post_ainvoice_checkout')) {
                    // add the Priority invoice number
                    $columns['priority_invoice_number'] = '<span>' . __('Priority Invoice', 'woocommerce') . '</span>'; // title
                    $columns['priority_invoice_status'] = '<span>' . __('Priority Invoice Status', 'woocommerce') . '</span>'; // title

                }
                //add the new column "Status"
                if($this->option('post_receipt_checkout')) {
                    // add the Priority recipe number
                    $columns['priority_recipe_number'] = '<span>' . __('Priority Recipe', 'woocommerce') . '</span>'; // title
                    $columns['priority_recipe_status'] = '<span>' . __('Priority Recipe Status', 'woocommerce') . '</span>'; // title

                }


                //add the new column "post to Priority"
                $columns['order_post'] = '<span>'.__( 'Post to Priority','woocommerce').'</span>'; // title


                // Set back "Actions" column
                $columns['order_actions'] = $action_column;

                return $columns;
            },999);

// ADDING THE DATA FOR EACH ORDERS BY "Platform" COLUMN
        add_action( 'manage_shop_order_posts_custom_column' ,
            function ( $column, $post_id )
            {

                // HERE get the data from your custom field (set the correct meta key below)
                if($this->option('post_order_checkout')) {
                    $order_status = get_post_meta($post_id, 'priority_order_status', true);
                    $order_number = get_post_meta($post_id, 'priority_order_number', true);
                    if (empty($order_status)) $order_status = '';
                    if (strlen($order_status) > 15) $order_status = '<div class="tooltip">Error<span class="tooltiptext">' . $order_status . '</span></div>';
                    if (empty($order_number)) $order_number = '';
                }
                if($this->option('post_einvoice_checkout')|| $this->option('post_ainvoice_checkout')){
                    $invoice_number = get_post_meta($post_id, 'priority_invoice_number', true);
                    $invoice_status = get_post_meta($post_id, 'priority_invoice_status', true);
                    if (empty($invoice_status)) $invoice_status = '';
                    if (strlen($invoice_status) > 15) $invoice_status = '<div class="tooltip">Error<span class="tooltiptext">' . $invoice_status . '</span></div>';
                    if (empty($invoice_number)) $invoice_number = '';
                }
                // recipe
                if($this->option('post_receipt_checkout')) {
                    $recipe_status = get_post_meta($post_id, 'priority_recipe_status', true);
                    $recipe_number = get_post_meta($post_id, 'priority_recipe_number', true);
                    if (empty($recipe_status)) $recipe_status = '';
                    if (strlen($recipe_status) > 15) $recipe_status = '<div class="tooltip">Error<span class="tooltiptext">' . $recipe_status . '</span></div>';
                    if (empty($recipe_number)) $recipe_number = '';
                }
                switch ( $column )
                {
                    // order
                    case 'priority_order_status' :
                        echo $order_status;
                        break;
                    case 'priority_order_number' :
                        echo '<span>'.$order_number.'</span>'; // display the data
                        break;
                    // invoice
                    case 'priority_invoice_status' :
                        echo $invoice_status;
                        break;
                    case 'priority_invoice_number' :
                        echo '<span>'.$invoice_number.'</span>'; // display the data
                        break;
                    // reciept
                    case 'priority_recipe_status' :
                        echo $recipe_status;
                        break;
                    case 'priority_recipe_number' :
                        echo '<span>'.$recipe_number.'</span>'; // display the data
                        break;
                    // post order to API, using GET and
                    case 'order_post' :
                        $url ='admin.php?page=priority-woocommerce-api&tab=post_order&ord='.$post_id ;
                        echo '<span><a href='.$url.'>Re Post</a></span>'; // display the data
                        break;
                }
            },999,2);

// MAKE 'stauts' METAKEY SEARCHABLE IN THE SHOP ORDERS LIST
        add_filter( 'woocommerce_shop_order_search_fields',
            function ( $meta_keys ){
                $meta_keys[] = 'priority_order_status';
                $meta_keys[] = 'priority_order_number';
                $meta_keys[] = 'priority_invoice_status';
                $meta_keys[] = 'priority_invoice_number';
                $meta_keys[] = 'priority_recipe_status';
                $meta_keys[] = 'priority_recipe_number';
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

                case 'post_customers':

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
                case 'auto_sync_customer_to_wp_user':
                    try{
                        $this->sync_priority_customers_to_wp();
                    }catch (Exception $e){
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
        // post order on status change
        add_action( 'woocommerce_order_status_changed', [ $this, 'syncDataAfterOrder' ],9999);
        add_action( 'woocommerce_order_status_changed', [ $this, 'post_order_status_to_priority' ],10);
    }
    public function post_order_status_to_priority($order_id){
        // this code is currently working only for EINVOICES
        if(empty(get_post_meta($order_id,'_post_done',true))){
            return;
        }
        $config = json_decode(stripslashes($this->option('setting-config')));
        $statdes = null;
        $order = new \WC_Order($order_id);
        foreach ($config->status_convert[0] as $key=>$value){
            if($order->get_status()==$key){
                $statdes = $value;
            }
        }
        if(!$statdes){
            return;
        }
        // get the invoice number from Priority
        $url_addition = 'EINVOICES?$filter=BOOKNUM eq \''.$order_id.'\'';
        $response = $this->makeRequest('GET', $url_addition,[],false);
        if ($response['status']) {
            $response_data = json_decode($response['body_raw'], true);
            $invoice = $response_data['value'][0];
            $ivnum = $invoice['IVNUM'];
        }
        // post status to Priority
        $url_addition = 'EINVOICES(IVNUM=\''.$ivnum.'\',IVTYPE=\'E\',DEBIT=\'D\')';
        $data = ['STATDES' => $statdes  ];
        $response = $this->makeRequest('PATCH', $url_addition,['body' => json_encode($data)],false);
        if ($response['status']) {
            $response_data = json_decode($response['body_raw'], true);
        }
    }

    /**
     * sync items from priority
     */
    public function syncItemsPriority()
    {
        // default values
        $daysback = 1;
        $url_addition_config = '';
        $search_field = 'PARTNAME';
        $is_update_products = (bool)true;
        // config
        $config = json_decode(stripslashes($this->option('sync_items_priority_config')));
        $daysback = (int)$config->days_back;
        $url_addition_config = $config->additional_url;
        $search_field = $config->search_by;
        $is_update_products = json_decode($config->is_update_products);
        //$response = $this->makeRequest('GET', 'LOGPART?$filter='.$this->option('variation_field').' eq \'\' and ROYY_ISUDATE eq \'Y\'', [], $this->option('log_items_priority', true));
        // $response = $this->makeRequest('GET', 'LOGPART?$filter='.$this->option('variation_field').' eq \'\' and ROYY_ISUDATE eq \'Y\'&$expand=PARTTEXT_SUBFORM', [], $this->option('log_items_priority', true));
        // get the items simply by time stamp of today
        $stamp = mktime(0 - $daysback*24, 0, 0);
        $bod = date(DATE_ATOM,$stamp);
        $url_addition = 'UDATE ge '.$bod;
        $response = $this->makeRequest('GET', 'LOGPART?$filter='.urlencode($url_addition.' '.$url_addition_config),[], $this->option('log_items_priority', true));
        // check response status
        if ($response['status']) {
            $response_data = json_decode($response['body_raw'], true);
            foreach($response_data['value'] as $item) {
                // add long text from Priority
                /*
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
                */
                $data = [
                    'post_author' => 1,
                    'post_content' =>  (isset($cleancontent[1]) ?  $cleancontent[1] : 'no content'),
                    'post_status'  => $this->option('item_status'),
                    'post_title'   => $item['PARTDES'],
                    'post_parent'  => '',
                    'post_type'    => 'product',
                ];
                // if product exsits, update
                $args = array(
                    'post_type'		=>	array('product', 'product_variation'),
                    'meta_query'	=>	array(
                        array(
                            'key'       => '_sku',
                            'value'	=>	$item[$search_field]
                        )
                    )
                );
                $product_id = 0;
                $my_query = new \WP_Query( $args );
                if ( $my_query->have_posts() ) {
                    while ( $my_query->have_posts() ) {
                        $my_query->the_post();
                        $product_id = get_the_ID();
                    }
                }
                if($product_id != 0 && false == $is_update_products){
                    continue;
                }
                if ($product_id != 0) {
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
                            //$post_content,
                            $id
                        )
                    );
                } else {
                    // Insert product
                    $id = wp_insert_post($data);
                    if ($id) {
                        update_post_meta($id,'_sku',$item[$search_field]);
                        update_post_meta($id, '_stock', 0);
                        update_post_meta($id, '_stock_status', 'outofstock');
                        $out_of_stock_staus = 'outofstock';
                        wp_set_post_terms( $id, 'outofstock', 'product_visibility', true );
                    }
                }
                // And finally (optionally if needed)
                wc_delete_product_transients( $id ); // Clear/refresh the variation cache
                // update product price
                $pri_price = $this->option('price_method') == true ? $item['VATPRICE'] : $item['BASEPLPRICE'];
                if ($id) {
		    $my_product = new \WC_Product( $id );
                    $my_product->set_regular_price($pri_price);
                    //$my_product->set_sale_price( $sales_price);
                    $my_product->save();
                    //update_post_meta($id, '_regular_price', $pri_price);
                    //update_post_meta($id, '_price',$pri_price );
                    update_post_meta($id, '_manage_stock', ($item['INVFLAG'] == 'Y') ? 'yes' : 'no');
                    // update categories
                    $categories = [];
                    foreach (explode(',',$config->categories) as $cat){
                        if(!empty($item[$cat])) {
                            array_push($categories, $item[$cat]);
                        }
                    }
                    $terms = $categories ;
                    wp_set_object_terms($id,$terms,'product_cat');
                }
                // sync image
                $is_load_image = json_decode($config->is_load_image);
                if(false == $is_load_image){
                    continue ;
                }
                $sku =  $item[$search_field];
                $is_has_image = get_the_post_thumbnail_url($id);
                if(!empty($item['EXTFILENAME'])
                    && ($this->option('update_image')==true || !get_the_post_thumbnail_url($id) )){
                    $priority_image_path = $item['EXTFILENAME'];
                    $images_url =  'https://'. $this->option('url').'/primail';
                    $product_full_url    = str_replace( '../../system/mail', $images_url, $priority_image_path );
                    $file_path = $item['EXTFILENAME'];
                    $file_info = pathinfo( $file_path );
                    $url = wp_get_upload_dir()['url'].'/'.$file_info['basename'];
                    $attach_id = attachment_url_to_postid($url);
                    if($attach_id != 0){
                    }
                    else{
                        $attach_id           = download_attachment( $sku, $product_full_url );
                    }
                    set_post_thumbnail( $id, $attach_id );
                }
            }
            // add timestamp
            $this->updateOption('items_priority_update', time());
        } else {
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
        $daysback = 10; // change days back to get inventory of prev days
        $stamp = mktime(1 - ($daysback*24), 0, 0);
        $bod = date(DATE_ATOM,$stamp);
        $url_addition = '(WARHSTRANSDATE ge '.$bod. ' or PURTRANSDATE ge '.$bod .' or SALETRANSDATE ge '.$bod.')';
        if($this->option('variation_field')) {
            $url_addition .= ' and ' . $this->option( 'variation_field' ) . ' eq \'\' ';
        }
        $response = $this->makeRequest('GET', 'LOGPART?$filter= '.urlencode($url_addition).' &$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM', [], $this->option('log_inventory_priority', true));

        // check response status
        if ($response['status']) {

            $data = json_decode($response['body_raw'], true);

            foreach($data['value'] as $item) {

                // if product exsits, update

                $args = array(
                    'post_type'      => array('product', 'product_variation'),
                    'meta_query'	=>	array(
                        array(
                            'key'       => '_sku',
                            'value'	=>	$item['BARCODE']
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
                    // update_post_meta($product_id, '_sku', $item['PARTNAME']);
                    // get the stock by part availability
                    $stock =  $item['LOGCOUNTERS_SUBFORM'][0]['DIFF'];
                    // get the stock by specific warehouse
                    $wh_name = $this->option('sync_inventory_warhsname');
                    $orders = $item['LOGCOUNTERS_SUBFORM'][0]['ORDERS'];
                    foreach($item['PARTBALANCE_SUBFORM'] as $wh_stock){
                        if($wh_stock['WARHSNAME'] == $wh_name)
                            //$stock = $wh_stock['TBALANCE'] - $orders > 0 ?  $wh_stock['TBALANCE'] - $orders : 0; // stock - orders
                            $stock = $wh_stock['TBALANCE']  > 0 ?  $wh_stock['TBALANCE']  : 0; // stock
                    }


                    update_post_meta($product_id, '_stock', $stock);

                    if (intval($stock) > 0) {
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
    public function syncPacksPriority()
    {
        // get the items simply by time stamp of today
        $stamp = mktime(0, 0, 0);
        $bod = date(DATE_ATOM,$stamp);
        $url_addition = 'LOGPART?$select=PARTNAME&$filter=ITAI_INKATALOG eq \'Y\'&$expand=PARTPACK_SUBFORM';
        $response = $this->makeRequest('GET', $url_addition, [],  true);
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
                    update_post_meta($product_id, 'pri_packs', $item['PARTPACK_SUBFORM']);
                }
            }
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
            if('prospect_email'==$this->option('prospect_field')){
                $priority_customer_number = $user->data->user_email;
            }
            if('prospect_cellphone'==$this->option('prospect_field')){
                $priority_customer_number = $meta['billing_phone'][0];
            }
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

            update_user_meta($id, 'priority_customer_number', $priority_customer_number, true);
            // set priority customer id
            if ($response['status']) {

            } else {
                $this->sendEmailError(
                    $this->option('email_error_sync_customers_web'),
                    'Error Sync Customers',
                    $response['body']
                );
            }
            // add timestamp
            $this->updateOption('post_customers', time());
        }
    }
    public function syncPriorityOrderStatus(){

        // orders
        $url_addition =  'ORDERS?$filter=BOOKNUM ne \'\'  and ';
        $date = date('Y-m-d');
        $prev_date = date('Y-m-d', strtotime($date .' -1 day'));
        $url_addition .= 'CURDATE ge '.$prev_date;

        $response     =  $this->makeRequest( 'GET', $url_addition, null, true ) ;
        $orders = json_decode($response['body'],true)['value'];
        $output = '';
        foreach ( $orders as $el ) {
            $order_id = $el['BOOKNUM'];
            $order = wc_get_order( $order_id );
            $pri_status = $el['ORDSTATUSDES'];
            if($order){
                update_post_meta($order_id,'priority_order_status',$pri_status);
                $output .= '<br>'.$order_id.' '.$pri_status.' ';
            }
        }
        // invoice
        $url_addition =  'AINVOICES?$filter=BOOKNUM ne \'\'  and ';
        $date = date('Y-m-d');
        $prev_date = date('Y-m-d', strtotime($date .' -1 day'));
        $url_addition .= 'IVDATE ge '.$prev_date;

        $response     =  $this->makeRequest( 'GET', $url_addition, null, true ) ;
        $orders = json_decode($response['body'],true)['value'];
        $output = '';
        foreach ( $orders as $el ) {
            $order_id = $el['BOOKNUM'];
            $ivnum = $el['IVNUM'];
            $order = wc_get_order( $order_id );
            $pri_status = $el['STATDES'];
            if($order){
                update_post_meta($order_id,'priority_invoice_status',$pri_status);
                update_post_meta($order_id,'priority_invoice_number',$ivnum);
                $output .= '<br>'.$order_id.' '.$pri_status.' ';
            }
        }
        // OTC
        $url_addition =  'EINVOICES?$filter=BOOKNUM ne \'\'  and ';
        $date = date('Y-m-d');
        $prev_date = date('Y-m-d', strtotime($date .' -1 day'));
        $url_addition .= 'IVDATE ge '.$prev_date;

        $response     =  $this->makeRequest( 'GET', $url_addition, null, true ) ;
        $orders = json_decode($response['body'],true)['value'];
        $output = '';
        foreach ( $orders as $el ) {
            $order_id = $el['BOOKNUM'];
            $ivnum = $el['IVNUM'];
            $order = wc_get_order( $order_id );
            $pri_status = $el['STATDES'];
            if($order){
                update_post_meta($order_id,'priority_invoice_status',$pri_status);
                update_post_meta($order_id,'priority_invoice_number',$ivnum);
                $output .= '<br>'.$order_id.' '.$pri_status.' ';
            }
        }
        // recipe
        $url_addition =  'TINVOICES?$filter=BOOKNUM ne \'\'  and ';
        $date = date('Y-m-d');
        $prev_date = date('Y-m-d', strtotime($date .' -1 day'));
        $url_addition .= 'IVDATE ge '.$prev_date;

        $response     =  $this->makeRequest( 'GET', $url_addition, null, true ) ;
        $orders = json_decode($response['body'],true)['value'];
        $output = '';
        foreach ( $orders as $el ) {
            $order_id = $el['BOOKNUM'];
            $order = wc_get_order( $order_id );
            $ivnum = $el['IVNUM'];
            $pri_status = $el['STATDES'];
            if($order){
                update_post_meta($order_id,'priority_recipe_status',$pri_status);
                update_post_meta($order_id,'priority_recipe_number',$ivnum);
                $output .= '<br>'.$order_id.' '.$pri_status.' ';
            }
        }
        // end
        $this->updateOption('auto_sync_order_status_priority_update', time());
    }
    public function getPriorityCustomer($order){
        $cust_numbers = explode('|',$this->option('walkin_number'));
        $country = !empty($order->get_shipping_country()) ? $order->get_shipping_country() : $order->get_billing_country();
        $walk_in_customer = $country == 'IL' ? $cust_numbers[0] : isset($cust_numbers[1])  ? $cust_numbers[1] : $cust_numbers[0] ;
        $walk_in_customer = !empty($walk_in_customer) ? $walk_in_customer : $cust_numbers[0];
        if ($order->get_customer_id()) {
            $cust_number = get_user_meta($order->get_customer_id(),'priority_customer_number',true);
            $cust_number = !empty($cust_number) ? $cust_number : $walk_in_customer;
        } else {
            $cust_number = $walk_in_customer;
            // check prospect
            if($this->option('post_prospect')){
                $cust_number = $this->post_prospect($order);
            }
        }
        return $cust_number;
    }
    public function syncOrders(){
        $query = new \WC_Order_Query( array(
            //'limit' => get_option('posts_per_page'),
            'limit' => 1000,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
            'meta_key'     => 'priority_order_status', // The postmeta key field
            'meta_compare' => 'NOT EXISTS', // The comparison argument
        ) );

        $orders = $query->get_orders();
        foreach ($orders as $id){
            $order =wc_get_order($id);
            $priority_status = $order->get_meta('priority_order_status');
            if(!$priority_status){
                $response = $this->syncOrder($id,$this->option('log_auto_post_orders_priority', true));
            }
        };
        $this->updateOption('time_stamp_cron_receipt', time());
    }
    public function syncReceipts(){
        $query = new \WC_Order_Query( array(
            //'limit' => get_option('posts_per_page'),
            'limit' => 1000,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
            'meta_key'     => 'priority_recipe_status', // The postmeta key field
            'meta_compare' => 'NOT EXISTS', // The comparison argument
        ) );

        $orders = $query->get_orders();
        foreach ($orders as $id){
            $order =wc_get_order($id);
            $priority_status = $order->get_meta('priority_recipe_status');
            if(!$priority_status){
                $response = $this->syncReceipt($id);
            }
        };
        $this->updateOption('time_stamp_cron_order', time());
    }
    public function syncAinvoices(){
        $query = new \WC_Order_Query( array(
            //'limit' => get_option('posts_per_page'),
            'limit' => 1000,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
            'meta_key'     => 'priority_invoice_status', // The postmeta key field
            'meta_compare' => 'NOT EXISTS', // The comparison argument
        ) );

        $orders = $query->get_orders();
        foreach ($orders as $id){
            $order =wc_get_order($id);
            $priority_status = $order->get_meta('priority_invoice_status');
            if(!$priority_status){
                $response = $this->syncAinvoice($id);
            }
        };
        $this->updateOption('time_stamp_cron_ainvoice', time());
    }
    public function syncOtcs(){
        $query = new \WC_Order_Query( array(
            //'limit' => get_option('posts_per_page'),
            'limit' => 1000,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
            'meta_key'     => 'priority_invoice_status', // The postmeta key field
            'meta_compare' => 'NOT EXISTS', // The comparison argument
        ) );

        $orders = $query->get_orders();
        foreach ($orders as $id){
            $order =wc_get_order($id);
            $priority_status = $order->get_meta('priority_invoice_status');
            if(!$priority_status){
                $response = $this->syncOverTheCounterInvoice($id);
            }
        };
        $this->updateOption('time_stamp_cron_otc', time());
    }
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
        $discount_type = 'additional_line'; // header , in_line , additional_line

        $cust_number = $this->getPriorityCustomer($order);

        $data = [
            'CUSTNAME' => $cust_number,
            'CURDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
            $this->option('order_order_field')  => $order->get_order_number(),
            //'DCODE' => $priority_dep_number, // this is the site in Priority
            //'DETAILS' => $user_department,

        ];
        // CDES
        if(empty($order->get_customer_id()) || true != $this->option( 'post_customers' )){
            $data['CDES'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        }

        // cart discount header
        $cart_discount = floatval($order->get_total_discount());
        $cart_discount_tax = floatval($order->get_discount_tax());
        $order_total = floatval($order->get_subtotal()+ $order->get_shipping_total());
        $order_discount = ($cart_discount/$order_total) * 100.0;
        if('header' == $discount_type){
            $data['PERCENT'] = $order_discount;
        }

// order comments
        $priority_version = (float)$this->option('priority-version');
        if($priority_version>19.1){
            // for Priority version 20.0
            $data['ORDERSTEXT_SUBFORM'] =   ['TEXT' => $order->get_customer_note()];
        }else{
            // for Priority version 19.1
            $data['ORDERSTEXT_SUBFORM'][] =   ['TEXT' => $order->get_customer_note()];

        }




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
                $line_before_discount = (float)$item->get_subtotal();
                $line_tax = (float)$item->get_subtotal_tax();
                $line_after_discount  = (float)$item->get_total();
                $discount = ($line_before_discount - $line_after_discount)/$line_before_discount * 100.0;
                $data['ORDERITEMS_SUBFORM'][] = [
                    'PARTNAME'         => $product->get_sku(),
                    'TQUANT'           => (int) $item->get_quantity(),
                    'PRICE'           => $discount_type == 'in_line' ? $line_before_discount/(int) $item->get_quantity() : 0.0,
                    'PERCENT'           => $discount_type == 'in_line' ? $discount : 0.0,
                    'REMARK1'          => isset($parameters['REMARK1']) ? $parameters['REMARK1'] : '',
                    //'DUEDATE' => date('Y-m-d', strtotime($campaign_duedate)),
                ];
                if($discount_type != 'in_line'){
                    $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM'])-1]['VATPRICE' ]= $line_before_discount + $line_tax;
                }
            }

        }
        // additional line cart discount
        if($discount_type == 'additional_line' && ($order->get_discount_total()+$order->get_discount_tax()>0)){
            $data['ORDERITEMS_SUBFORM'][] = [
                'PARTNAME' => '000', // change to other item
                'VATPRICE' => -1* floatval($order->get_discount_total()+$order->get_discount_tax()),
                'TQUANT'   => -1,

            ];
        }
        // shipping rate
        if(!empty($this->get_shipping_price($order,true))){
            $data['ORDERITEMS_SUBFORM'][] =  $this->get_shipping_price($order,true);
        }
        // payment info
         $data['PAYMENTDEF_SUBFORM'] = $this->get_credit_card_data($order,true);
        // make request
        $response = $this->makeRequest('POST', 'ORDERS', ['body' => json_encode($data)], true);

        if ($response['code']<=201) {
            $body_array = json_decode($response["body"],true);

            $ord_status = $body_array["ORDSTATUSDES"];
            $ord_number = $body_array["ORDNAME"];
            $order->update_meta_data('priority_order_status',$ord_status);
            $order->update_meta_data('priority_order_number',$ord_number);
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
    public function get_credit_card_data($order,$is_order){

        $config = json_decode(stripslashes($this->option('setting-config')));
        $gateway = $config->gateway ?? 'debug';
        switch($gateway){
            // pelecard
            case 'pelecard';
                $order_cc_meta = $order->get_meta('_transaction_data');
                // data
                $payaccount = $order_cc_meta['CreditCardNumber'];
                $ccuid =  $order_cc_meta['Token'];
                $validmonth =  $order_cc_meta['CreditCardExpDate'];
                $confnum = $order_cc_meta['ConfirmationKey'];
                $numpay = 1;
                $firstpay = 0.0;
            break;
            // credit guard
            case 'creditguard';
                $payaccount = $order->get_meta('_ccnumber');
                $ccuid = $order->get_meta('_creditguard_token');
                $validmonth = $order->get_meta('_creditguard_expiration');
                $confnum = $order->get_meta('_creditguard_authorization');
                $numpay = $order->get_meta('_payments');
                $firstpay = $order->get_meta('_first_payment');
                $order_periodical_payment = $order->get_meta('_periodical_payment');
            break;
            // card com
            case 'cardcom';
                $payaccount = $order->get_meta('cc_number');
                $ccuid = '';
                $validmonth = '';
                $confnum = $order->get_meta('CardcomInternalDealNumber');
                $numpay = $order->get_meta('cc_numofpayments');
                $firstpay = $order->get_meta('cc_firstpayment');
                $card_type = $order->get_meta('cc_cardtype');
                $payment_type = $order->get_meta('cc_paymenttype');
            break;
            // tranzila
            case 'tranzila';
            break;
            // payplus
            case 'payplus';
                $order_first_pay = floatval(get_post_meta($order->get_id(),'payplus_payments_firstAmount',true));
                $order_token = get_post_meta($order->get_id(),'payplus_token',true);
                $order_ccnumber = get_post_meta($order->get_id(),'payplus_last_four',true);
                $order_cc_expiration = get_post_meta($order->get_id(),'payplus_exp_date',true);
                $number_of_payments = get_post_meta($order->get_id(),'payplus_number_of_payments',true);
                $order_cc_authorization = get_post_meta($order->get_id(),'payplus_voucher_id',true);
            break;
            // debug
            case 'debug';
                $payaccount = '123456789';
                $validmonth =  '01/25';
                $ccuid = '1234';
                $confnum = '987654321';
                $numpay = 1;
                $firstpay = 0.0;
            break;

        }

       $data=[
            'PAYMENTCODE' => $this->option('payment_' . $order->get_payment_method(), $order->get_payment_method()),
            'PAYACCOUNT'  => substr($payaccount,strlen($payaccount) -4,4),
            'VALIDMONTH'  => $validmonth,
            'QPRICE'      => floatval($order->get_total()),
            'CCUID'       => $ccuid,
            'CONFNUM'     => $confnum,
            'PAYCODE'      => (string)$numpay

        ];
        // add fields for not order objects
        if(!$is_order&&$firstpay != 0.0){
            $data['FIRSTPAY'] = $firstpay ;
        }
        return $data;
    }
    public function post_prospect($order)
    {
        if('prospect_email'==$this->option('prospect_field')){
            $priority_customer_number = $order->get_billing_email();
        }else{
            $priority_customer_number = $order->get_billing_phone();
        }
        $json_request = json_encode([
            'CUSTNAME'    => $priority_customer_number,
            'CUSTDES'     => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'EMAIL'       => $order->get_billing_email(),
            'ADDRESS'     => $order->get_billing_address_1(),
            'ADDRESS2'    => $order->get_billing_address_2(),
            'STATEA'      => $order->get_billing_city(),
            'ZIP'         => $order->get_shipping_postcode(),
            'PHONE'       => $order->get_billing_phone(),
        ]);
        $method = 'POST';
        $response = $this->makeRequest($method, 'CUSTOMERS', ['body' => $json_request], $this->option('log_customers_web', true));
        // set priority customer id
        if ($response['status']) {

        } else {
            $this->sendEmailError(
                $this->option('email_error_sync_customers_web'),
                'Error Sync Customers',
                $response['body']
            );
        }
        // add timestamp
        $this->updateOption('time_stamp_cron_prospect', time());
        return $priority_customer_number;
    }
    public function syncDataAfterOrder($order_id)
    {
        $order = new \WC_Order($order_id);
        // checck order status against config
        $config = json_decode(stripslashes($this->option('setting-config')));
        if(!isset($config->order_statuses)){
            $is_status = true;
        }else{
            $statuses = explode(',', $config->order_statuses);
            $is_status = in_array($order->get_status(),$statuses);
        }
        if(empty(get_post_meta($order_id,'_post_done',true)) && $is_status){
            // get order
            update_post_meta($order_id,'_post_done',true);

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
            if($this->option('post_einvoice_checkout')&& empty(get_post_meta($order_id,'priority_invoice_status',false)[0])) {
                // avoid repetition
                $order->update_meta_data('priority_invoice_status','Processing');
                $this->syncOverTheCounterInvoice( $order_id );
            }
            // sync Ainvoices
            if($this->option('post_ainvoice_checkout')) {
                $this->syncAinvoice($order_id);
            }
            // sync receipts
            if($this->option('post_receipt_checkout')) {
                $this->syncReceipt($order_id);
            }
        }
        // sync payments
        if(isset(WC()->session)){
        $session = WC()->session->get('session_vars');
            if($session['ordertype']=='Recipe') {
                $optional = array(
                    "custname" => $session['custname']
                );
                $this->syncPayment($order_id,$optional);
            }
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
    public function syncAinvoice($id)
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
        $discount_type = 'additional_line'; // header , in_line , additional_line

        $cust_number = $this->getPriorityCustomer($order);

        $data = [
            'CUSTNAME' => $cust_number,
            'IVDATE'  => date('Y-m-d', strtotime($order->get_date_created())),
            $this->option('ainvoice_order_field')  => $order->get_order_number(),
            //'DCODE' => $priority_dep_number, // this is the site in Priority
            //'DETAILS' => $user_department,

        ];
        // CDES
        if(empty($order->get_customer_id()) || true != $this->option( 'post_customers' )){
            $data['CDES'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        }
        // cart discount header
        $cart_discount = floatval($order->get_total_discount());
        $cart_discount_tax = floatval($order->get_discount_tax());
        $order_total = floatval($order->get_subtotal()+ $order->get_shipping_total());
        $order_discount = ($cart_discount/$order_total) * 100.0;
        if('header' == $discount_type){
            $data['PERCENT'] = $order_discount;
        }

// order comments
        $priority_version = (float)$this->option('priority-version');
        if($priority_version>19.1){
            // for Priority version 20.0
            $data['PINVOICESTEXT_SUBFORM'] =   ['TEXT' => $order->get_customer_note()];
        }else{
            // for Priority version 19.1
            $data['PINVOICESTEXT_SUBFORM'][] =   ['TEXT' => $order->get_customer_note()];
        }
        // billing customer details
        $customer_data = [

            'PHONE'    => $order->get_billing_phone(),
            'EMAIL'       => $order->get_billing_email(),
            'ADRS'        => $order->get_billing_address_1(),
            'ADRS2'       => $order->get_billing_address_2(),
            'STATEA'      => $order->get_billing_city(),
            'ZIP'         => $order->get_shipping_postcode(),
        ];
        $data['AINVOICESCONT_SUBFORM'][] = $customer_data;
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
                $line_before_discount = (float)$item->get_subtotal();
                $line_tax = (float)$item->get_subtotal_tax();
                $line_after_discount  = (float)$item->get_total();
                $discount = ($line_before_discount - $line_after_discount)/$line_before_discount * 100.0;
                $data['AINVOICEITEMS_SUBFORM'][] = [
                    'PARTNAME'         => $product->get_sku(),
                    'TQUANT'           => (int) $item->get_quantity(),
                    'PRICE'           => $discount_type == 'in_line' ? $line_before_discount/(int) $item->get_quantity() : 0.0,
                    'PERCENT'           => $discount_type == 'in_line' ? $discount : 0.0,
                ];
                if($discount_type != 'in_line'){
                    $data['AINVOICEITEMS_SUBFORM'][sizeof($data['AINVOICEITEMS_SUBFORM'])-1]['TOTPRICE' ]= $line_before_discount + $line_tax;
                }
            }

        }
        // additional line cart discount
        if($discount_type == 'additional_line' && ($order->get_discount_total()+$order->get_discount_tax()>0)){
            //if($discount_type == 'additional_line'){
            $data['AINVOICEITEMS_SUBFORM'][] = [
                // 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
                'PARTNAME' => '000',
                // 'VATPRICE' => -1* floatval( $cart_discount + $cart_discount_tax),
                'TOTPRICE' => -1* floatval($order->get_discount_total()+$order->get_discount_tax()),
                'TQUANT'   => -1,

            ];
        }
        // shipping rate
        if(!empty($this->get_shipping_price($order,false))) {
            $data['AINVOICEITEMS_SUBFORM'][] = $this->get_shipping_price($order, false);
        }

                /*
                [
                // 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
                'PARTNAME' => $this->option( 'shipping_' . $shipping_method_id . '_'.$shipping_method['instance_id'], $order->get_shipping_method() ),
                'TQUANT'   => 1,
                'TOTPRICE' => floatval( $order->get_shipping_total()+$order->get_shipping_tax())
            ];*/


        // make request
        $response = $this->makeRequest('POST', 'AINVOICES', ['body' => json_encode($data)], true);

        if ($response['code']<=201) {
            $body_array = json_decode($response["body"],true);

            $ord_status = $body_array["STATDES"];
            $ord_number = $body_array["IVNUM"];
            $order->update_meta_data('priority_invoice_status',$ord_status);
            $order->update_meta_data('priority_invoice_number',$ord_number);
            $order->save();
        }
        if($response['code'] >= 400){
            $body_array = json_decode($response["body"],true);

            //$ord_status = $body_array["ORDSTATUSDES"];
            // $ord_number = $body_array["ORDNAME"];
            $order->update_meta_data('priority_invoice_status',$response["body"]);
            // $order->update_meta_data('priority_ordnumber',$ord_number);
            $order->save();
        }

        if (!$response['status']||$response['code'] >= 400) {
            /**
             * t149
             */
            $this->sendEmailError(
                $this->option('email_error_sync_ainvoices_priority'),
                'Error Sync Sales Invoice',
                $response['body']
            );
        }
        // add timestamp
        return $response;
    }
    public function syncOverTheCounterInvoice($order_id)
    {
        $order = new \WC_Order($order_id);
        $user = $order->get_user();
        $user_id = $order->get_user_id();
        $order_user = get_userdata($user_id); //$user_id is passed as a parameter
        $user_meta = get_user_meta($user_id);
        $cust_number = $this->getPriorityCustomer($order);
        $data = [
            'CUSTNAME'  => $cust_number,
            'IVDATE' => date('Y-m-d', strtotime($order->get_date_created())),
            $this->option('otc_order_field') => $order->get_order_number(),

        ];
        // CDES
        if(
            (empty($order->get_customer_id()) && !$this->option('post_prospect')) ||
            (true != $this->option( 'post_customers' )&& $order->get_customer_id())
        ){
            $data['CDES'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        }

        // order comments
        $priority_version = (float)$this->option('priority-version');
        if($priority_version>19.1){
            // version 20.0
            $data['PINVOICESTEXT_SUBFORM'] = ['TEXT' => $order->get_customer_note()];
        }else{
            // version 19.1
            $data['PINVOICESTEXT_SUBFORM'][] = ['TEXT' => $order->get_customer_note()];
        }


        // billing customer details
        $customer_data = [

            'PHONE'    => $order->get_billing_phone(),
            'EMAIL'       => $order->get_billing_email(),
            'ADRS'        => $order->get_billing_address_1(),
            'ADRS2'       => $order->get_billing_address_2(),
            'STATEA'      => $order->get_billing_city(),
            'ZIP'         => $order->get_shipping_postcode(),
        ];
        $data['EINVOICESCONT_SUBFORM'][] = $customer_data;


        // shipping
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
        // shipping rate
        if(!empty($this->get_shipping_price($order,false))) {
            $data['EINVOICEITEMS_SUBFORM'][] = $this->get_shipping_price($order, false);
        }
        // payment info
        if($order->get_total()>0.0) {
            $data['EPAYMENT2_SUBFORM'][] = $this->get_credit_card_data($order,false);
        }
        // make request
        $response = $this->makeRequest('POST', 'EINVOICES', ['body' => json_encode($data)], true);
        if ($response['code']<=201) {
            $body_array = json_decode($response["body"],true);

            $ord_status = $body_array["STATDES"];
            $ord_number = $body_array["IVNUM"];
            $order->update_meta_data('priority_invoice_status',$ord_status);
            $order->update_meta_data('priority_invoice_number',$ord_number);
            $order->save();
        }
        if($response['code'] >= 400){
            $body_array = json_decode($response["body"],true);

            //$ord_status = $body_array["ORDSTATUSDES"];
            // $ord_number = $body_array["ORDNAME"];
            $order->update_meta_data('priority_invoice_status',$response["body"]);
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

        $user_id = $order->get_user_id();
        $order_user = get_userdata($user_id); //$user_id is passed as a parameter
        $cust_number = $this->getPriorityCustomer($order);
        $data = [
            'CUSTNAME' => $cust_number,
            'IVDATE' => date('Y-m-d', strtotime($order->get_date_created())),
            $this->option('receipt_order_field') => $order->get_order_number(),

        ];
        // CDES
        if(empty($order->get_customer_id()) || true != $this->option( 'post_customers' )){
            $data['CDES'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        }
        // cash payment
        if(strtolower($order->get_payment_method()) == 'cod') {
            $data['CASHPAYMENT'] = floatval($order->get_total());
        } else {
            // payment info
            $data['TPAYMENT2_SUBFORM'][] = $this->get_credit_card_data($order,false);
        }
        // make request
        $response = $this->makeRequest('POST', 'TINVOICES', ['body' => json_encode($data)], $this->option('log_receipts_priority', true));
        if ($response['code']<=201) {
            $body_array = json_decode($response["body"],true);

            $ord_status = $body_array["STATDES"];
            $ord_number = $body_array["IVNUM"];
            $order->update_meta_data('priority_recipe_status',$ord_status);
            $order->update_meta_data('priority_recipe_number',$ord_number);
            $order->save();
        }
        if($response['code'] >= 400){
            $body_array = json_decode($response["body"],true);

            //$ord_status = $body_array["ORDSTATUSDES"];
            // $ord_number = $body_array["ORDNAME"];
            $order->update_meta_data('priority_recipe_status',$response["body"]);
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
        // add timestamp
        $this->updateOption('receipts_priority_update', time());
        return $response;

    }
    public function syncPayment($order_id,$optional)
    {

        $order = new \WC_Order($order_id);
        $priority_customer_number = get_user_meta( $order->get_customer_id(), 'priority_customer_number', true );
        if(!empty($optional['custname'])){
            $priority_customer_number = $optional['custname'];
        }
        $data = [
            'CUSTNAME' => $priority_customer_number,
            //'CDES' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'IVDATE' => date('Y-m-d', strtotime($order->get_date_created())),
            'BOOKNUM' => $order->get_order_number(),

        ];

        // currency
        if (class_exists('WOOCS')) {
            global $WOOCS;
            $data['CODE'] = $WOOCS->current_currency;
        }

        // cash payment
        if(strtolower($order->get_payment_method()) == 'cod') {

            $data['CASHPAYMENT'] = floatval($order->get_total());

        } else {

            // payment info
            $data['TPAYMENT2_SUBFORM'][] = $this->get_credit_card_data($order,false);

        }

        foreach ($order->get_items() as $item) {
            $ivnum = $item->get_meta('product-ivnum');
            $data['TFNCITEMS_SUBFORM'][] = [
                'CREDIT'    => (float) $item->get_total(),
                'FNCIREF1'  =>  $ivnum
            ];
        }

        // order comments
        $priority_version = (float)$this->option('priority-version');
        if($priority_version>19.1) {
            // for Priority version 20.0
            $data['TINVOICESTEXT_SUBFORM'] =   ['TEXT' => $order->get_customer_note()];
        }else{
            // for Priority version 19.1
            $data['TINVOICESTEXT_SUBFORM'][] =   ['TEXT' => $order->get_customer_note()];
        }





        // billing customer details
        $customer_data = [

            'PHONE'    => $order->get_billing_phone(),
            'EMAIL'       => $order->get_billing_email(),
            'ADRS'        => $order->get_billing_address_1(),
            'ADRS2'       => $order->get_billing_address_2(),
            'ADRS3'       => $order->get_billing_first_name().' '.$order->get_billing_last_name(),
            'STATEA'      => $order->get_billing_city(),
            'ZIP'         => $order->get_billing_postcode(),
        ];
        $data['TINVOICESCONT_SUBFORM'][] = $customer_data;


        // make request
        $response = $this->makeRequest('POST', 'TINVOICES', ['body' => json_encode($data)],true);
        if ($response['code']<=201) {
            /*
             $body_array = json_decode($response["body"],true);

             $ord_status = $body_array["STATDES"];
             $ord_number = $body_array["IVNUM"];
             $order->update_meta_data('priority_invoice_status',$ord_status);
             $order->update_meta_data('priority_invoice_number',$ord_number);
             $order->save();
            */
        }
        if($response['code'] >= 400){
            $body_array = json_decode($response["body"],true);
            $this->sendEmailError(
                $this->option('email_error_sync_einvoices_web'),
                'Error Sync payment',
                $response['body']
            );
        }
        if (!$response['status']) {
            $this->sendEmailError(
                $this->option('email_error_sync_einvoices_web'),
                'Error Sync payment',
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
    public function syncReceiptsCompleted()
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
    function get_shipping_price($order,$is_order){
        $price_filed = $is_order ? 'VATPRICE' : 'TOTPRICE';
        // config
        $config = json_decode(stripslashes($this->option('setting-config')));
        $default_product = '000';
        $default_product = $config->SHIPPING_DEFAULT_PARTNAME ?? $default_product;
        $shipping_method    = $order->get_shipping_methods();
        $shipping_method    = array_shift($shipping_method);
        if(isset($shipping_method)) {
            $data = $shipping_method->get_data();
            $method_title = $data['method_title'];
            $method_id = $data['method_id'];
            $instance_id = $data['instance_id'];
            $shipping_price = $data['total'];
            $data = [
                // 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
                'PARTNAME' => $this->option('shipping_' . $method_id . '_' . $instance_id, $default_product),
                'TQUANT' => 1,
                $price_filed => floatval($shipping_price)
            ];
            return $data;
        }else{
            return null;
        }
    }
    function sync_priority_customers_to_wp(){
        // default values
        $daysback = 1;
        $url_addition_config = '';
        // config
        $config = json_decode(stripslashes($this->option('sync_customer_to_wp_user_config')));
        $daysback = (int)$config->days_back;
        $username_filed = $config->username_field;
        $password_field = $config->password_field;
        $url_addition_config = $config->additional_url;
        $stamp = mktime(0 - $daysback*24, 0, 0);
        $bod = urlencode(date(DATE_ATOM,$stamp));
        $url_addition = 'CUSTOMERS?$filter=CREATEDDATE ge '.$bod;
        //$url_addition = 'CUSTOMERS?$filter=PRIVITAI_USEROFFON eq \'Y\' and EMAIL ne \'\' &$select=CUSTNAME,CUSTDES,EMAIL,ITAI_USERID,ITAI_USERPASS';
        // PRIVITAI_USEROFFON
        // ITAI_USERID,ITAI_USERPASS
        $response = $this->makeRequest('GET', $url_addition.' '.$url_addition_config, [],false);
        if ($response['status']) {
            // decode raw response
            $data = json_decode($response['body_raw'], true)['value'];
            foreach($data as $user){
                $username = $user[$username_filed];
                $email = $user['EMAIL'];
                if (!is_email($email)){
                    continue;
                }
                if(!validate_username($username)){
                    continue;
                }
                $password = $user[$password_field];
                $user_obj = get_user_by('login',$username);
                $data = [
                    'ID' => isset($user_obj->ID) ? $user_obj->ID : null,
                    'user_login' => $username,
                    'user_pass' => wp_hash_password($password),
                    'first_name' => $user['CUSTDES'],
                    //'last_name'  => 'Doe',
                    'user_nicename' => $user['CUSTDES'],
                    'display_name' => $user['CUSTDES'],
                    'role' => 'customer'
                ];
                if(!isset($user_obj->ID) ){
                    $data['user_email'] = $email;
                }
                $user_id = wp_insert_user($data);
                if(is_wp_error($user_id)){
                    error_log('This is customer with error '.$user['CUSTNAME']);
                }
                update_user_meta($user_id, 'priority_customer_number', $user['CUSTNAME']);
                $customer = new \WC_Customer($user_id);
                $customer->set_billing_address_1($user['ADDRESS']);
                $customer->set_billing_address_2($user['ADDRESS2']);
                $customer->set_billing_city($user['STATE']);
                $customer->set_billing_phone($user['PHONE']);
                $customer->set_billing_postcode($user['ZIP']);
                $customer->save();
            }
        }
    }
    function generate_settings($description,$name,$format,$format2){
        ?>
        <tr>
            <td class="p18a-label">
                <?php _e($description, 'p18a'); ?>
            </td>
            <td>
                <input type="checkbox" name="sync_<?php echo $name ?>" form="p18aw-sync" value="1" <?php if($this->option('sync_'.$name)) echo 'checked'; ?> />
            </td>
            <td></td>
            <td>
                <select name="auto_sync_customer_to_wp_user" form="p18aw-sync">
                    <option value="" <?php if( ! $this->option('auto_sync_'.$name)) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                    <option value="hourly" <?php if($this->option('auto_sync_'.$name) == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                    <option value="daily" <?php if($this->option('auto_sync_'.$name) == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                    <option value="twicedaily" <?php if($this->option('auto_sync_'.$name) == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                </select>
            </td>
            <td data-sync-time="auto_sync_customer_to_wp_user">
                <?php
                if ($timestamp = $this->option('auto_sync_'.$name.'_update', false)) {
                    echo(get_date_from_gmt(date($format, $timestamp),$format2));
                } else {
                    _e('Never', 'p18a');
                }
                ?>
            </td>
            <td>
                <a href="#" class="button p18aw-sync" data-sync="auto_sync_<?php echo $name ?>"><?php _e('Sync', 'p18a'); ?></a>
            </td>
            <td>
					<textarea style="width:300px !important; height:45px !important;"  name="sync_<?php echo $name.'_config' ?>"
                              form="p18aw-sync"
                              placeholder="">
                        <?php echo stripslashes($this->option('sync_'.$name.'_config' ))?></textarea >
            </td>
            <td>

            </td>
        </tr>

        <?php

    }

}
