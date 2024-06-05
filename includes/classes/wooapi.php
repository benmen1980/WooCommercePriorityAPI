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
        // set json serilaized 2 decimals
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set('serialize_precision', -1);
        }
        // get countries
        $this->countries = include(P18AW_INCLUDES_DIR . 'countries.php');
        /**
         * Schedule cron  syncs
         */
        $syncs = [
            'sync_items_priority' => 'syncItemsPriority',
            'sync_items_priority_variation' => 'syncItemsPriorityVariation',
            'sync_items_web' => 'syncItemsWeb',
            'sync_inventory_priority' => 'syncInventoryPriority',
            'sync_pricelist_priority' => 'syncPriceLists',
            'sync_productfamily_priority' => 'syncSpecialPriceProductFamily',
            'sync_receipts_priority' => 'syncReceipts',
            'sync_order_status_priority' => 'syncPriorityOrderStatus',
            'sync_sites_priority' => 'syncSites',
            'sync_c_products_priority' => 'syncCustomerProducts',
            'sync_customer_to_wp_user' => 'sync_priority_customers_to_wp'
        ];

        foreach ($syncs as $hook => $action) {
            // Schedule sync
            if ($this->option('auto_' . $hook, false)) {

                add_action($hook, [$this, $action]);

                if (!wp_next_scheduled($hook)) {
                    wp_schedule_event(time(), $this->option('auto_' . $hook), $hook);
                }

            }
        }

        // sync order control
        $syncs = [
            'cron_orders' => 'syncOrders',
            'cron_receipt' => 'syncReceipts',
            'cron_ainvoice' => 'syncAinvoices',
            'cron_otc' => 'syncOtcs'
        ];
        foreach ($syncs as $hook => $action) {
            // Schedule sync
         //   if ($this->option($hook, false)) {

              //  add_action($hook, [$this, $action]);

                if (!wp_next_scheduled($hook)) {
              //      wp_schedule_event(time(), $this->option($hook), $hook);
                }

         //   }
        }

        // add actions for user profile
        add_action('show_user_profile', array($this, 'crf_show_extra_profile_fields'), 99, 1);
        add_action('edit_user_profile', array($this, 'crf_show_extra_profile_fields'), 99, 1);

        add_action('personal_options_update', array($this, 'crf_update_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'crf_update_profile_fields'));

        /* hide price for not registered user */
        add_action('init', array($this, 'bbloomer_hide_price_add_cart_not_logged_in'));


        include P18AW_ADMIN_DIR . 'download_file.php';
        add_action('draft_to_publish', array($this, 'my_product_update'), 99, 1);


    }

    public function run()
    {
        return is_admin() ? $this->backend() : $this->frontend();

    }

    /* hide price for not registered user */
    function bbloomer_hide_price_add_cart_not_logged_in()
    {
        if (!is_user_logged_in() and $this->option('walkin_hide_price')) {
            remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
            remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);
            add_action('woocommerce_single_product_summary', array($this, 'bbloomer_print_login_to_see'), 31);
            add_action('woocommerce_after_shop_loop_item', array($this, 'bbloomer_print_login_to_see'), 11);
        }
    }

    function bbloomer_print_login_to_see()
    {
        // echo '<a href="' . get_permalink(wc_get_page_id('myaccount')) . '">' . __('Login to see prices', 'p18w') . '</a>';
        $text_display = !empty($this->option('text-display-for-non-register')) ? $this->option('text-display-for-non-register') : __('Login to see prices', 'p18w');
        $link_display = !empty($this->option('link-display-for-non-register'))? $this->option('link-display-for-non-register') : '#';
        echo '<a href="' .  $link_display . '">' . $text_display . '</a>';
    }

    /**
     * Frontend
     *
     */
    private function frontend()
    {
        //frontend test point
        // load obligo
        /*if($this->option('obligo')){
             require P18AW_FRONT_DIR.'my-account\obligo.php';
             \obligo::instance()->run();
         }*/
        // Sync customer and order data after order is proccessed
        //add_action( 'woocommerce_thankyou', [ $this, '' ],9999 );
        if(!($this->option('cardPos'))){
            add_action('woocommerce_payment_complete', [$this, 'syncDataAfterOrder'], 9999);
            add_action('woocommerce_order_status_changed', [$this, 'syncDataAfterOrder']);
        }
        // custom check out fields
        //add_action( 'woocommerce_after_checkout_billing_form', array( $this ,'custom_checkout_fields'));
        // add_action('woocommerce_checkout_process', array($this, 'my_custom_checkout_field_process'));
        //add_action('woocommerce_checkout_update_order_meta', array($this, 'my_custom_checkout_field_update_order_meta'));
        // sync user to priority after registration
        if ($this->option('post_customers') == true) {
            if ($this->option('post_customers_on_sign_in') == true) {
                add_action('user_register', [$this, 'syncCustomer'], 999);
                add_action('user_new_form', [$this, 'syncCustomer'], 999);
                add_action('woocommerce_save_account_details', [$this, 'syncCustomer'], 999);
                add_action('woocommerce_customer_save_address', [$this, 'syncCustomer'], 999);
            }
        }
        if ($this->option('sell_by_pl') == true) {
	        include_once P18AW_FRONT_DIR . 'priceList/price_list.php';
	        include_once P18AW_FRONT_DIR . 'price_list_variation/price_list_variation.php';
            // add overall customer discount
            add_action('woocommerce_cart_calculate_fees', [$this, 'add_customer_discount']);
            // filter products regarding to price list
            $config = json_decode(stripslashes(WooAPI::instance()->option('setting-config')));
            $hide_pdts_if_not_in_pricelist = $config->hide_pdts_if_not_in_pricelist ?? false;
            if($hide_pdts_if_not_in_pricelist == 'true'){
                add_filter('loop_shop_post_in', [$this, 'filterProductsByPriceList'], 9999999);
            }
            $hide_price_if_not_in_pricelist = $config->hide_price_if_not_in_pricelist ?? false;
            if($hide_price_if_not_in_pricelist == 'true'){
                add_filter( 'woocommerce_get_price_html', [$this, 'custom_price_message'] , 100 , 2 );
                add_filter('remove_add_to_cart', [$this,'my_woocommerce_is_purchasable'], 10, 2);
                add_filter( 'woocommerce_is_purchasable', [$this,'remove_add_to_cart_on_0'], 10, 2 );
	            // https://awhitepixel.com/blog/change-prices-woocommerce-by-code/
            }
            // filter priority price to sales price
	        $show_priority_price_as_sale_price = $config->show_priority_price_as_sale_price ?? false;
	        if($show_priority_price_as_sale_price == 'true'){
		        add_filter('woocommerce_product_get_sale_price', [$this, 'filterPrice'], 10, 2);
	        }
	        add_action('woocommerce_before_calculate_totals', [$this, 'simply_add_custom_price'],10,1);
	        add_action('woocommerce_after_add_to_cart_button', [$this, 'simply_after_add_to_cart_button']);
	        add_filter('woocommerce_product_get_price', [$this, 'filterPrice'], 10, 2);

            // filter product variation price regarding to price list
            add_filter('woocommerce_product_variation_get_price', [$this, 'filterPrice'], 10, 2);
            //add_filter('woocommerce_product_variation_get_regular_price', [$this, 'filterPrice'], 10, 2);
            // filter price range
            add_filter('woocommerce_variable_sale_price_html', [$this, 'filterPriceRange'], 10, 2);
            add_filter('woocommerce_variable_price_html', [$this, 'filterPriceRange'], 10, 2);
            // check if variation is available to the client

            add_filter('woocommerce_variation_prices', function ($transient_cached_prices) {
            $transient_cached_prices_new = [];
            foreach ($transient_cached_prices as $type_price => $variations) {
                foreach ($variations as $var_id => $price) {
                    $sku = get_post_meta($var_id, '_sku', true);
                    $data = $this->getProductDataBySku($sku);
                    if (!empty($data)) {
                        $transient_cached_prices_new[$type_price][$var_id] = $price;
                    }
                }
            }
            return $transient_cached_prices_new ? $transient_cached_prices_new : $transient_cached_prices;
            }, 10);

           add_filter('woocommerce_product_categories_widget_args', function ($list_args) {
                $user_id = get_current_user_id();
                $include = [];
                $exclude = [];
                $meta = get_user_meta($user_id, '_priority_price_list', true);
                if ($meta !== 'no-selected') {
                    $list = empty($meta) ? $this->basePriceCode : $meta;
                    $products = $GLOBALS['wpdb']->get_results('
                    SELECT product_sku
                    FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                    WHERE price_list_code = "' . esc_sql($list) . '"
                    AND blog_id = ' . get_current_blog_id(),
                        ARRAY_A
                    );
                    $cat_ids = [];
                    foreach ($products as $product) {
                        if ($id = wc_get_product_id_by_sku($product['product_sku'])) {
                            $parent_id = get_post($id)->post_parent;
                            if (isset($parent_id) && $parent_id) {
                                $cat_id = wc_get_product_cat_ids($parent_id);
                            }
                            if (isset($cat_id) && $cat_id) {
                                $cat_ids = array_unique(array_merge($cat_ids, $cat_id));
                            }
                        }
                    }
                    if ($cat_ids) {
                        $include = array_merge($include, $cat_ids);
                    } else {
                        $args = array_merge(['fields' => 'ids'], $list_args);
                        $exclude = array_merge($include, get_terms($args));
                    }
                }
                //check display categories
                if (empty($include)) {
                    $args = array_merge(['fields' => 'ids'], $list_args);
                    $include = get_terms($args);
                }
                global $wpdb;
                $term_ids = $wpdb->get_col("SELECT woocommerce_term_id as term_id FROM {$wpdb->prefix}woocommerce_termmeta WHERE meta_key = '_attribute_display_category' AND meta_value = '0'");
                if (!$term_ids) {
                    $term_ids = [];
                } else {
                    $term_ids = array_unique($term_ids);
                }
                $include = array_diff($include, $term_ids);
                //check display categories for user
                $cat_user = get_user_meta($user_id, '_display_product_cat', true);
                if (is_array($cat_user)) {
                    if ($cat_user) {
                        $include = array_intersect($include, $cat_user);
                    } else {
                        $args = array_merge(['fields' => 'ids'], $list_args);
                        $include = [];
                        $exclude = array_merge($exclude, get_terms($args));
                    }
                }
                $list_args['hide_empty'] = 1;
                $list_args['include'] = implode(',', array_unique($include));
                $list_args['exclude'] = implode(',', array_unique($exclude));
                return $list_args;
            });

        }
    }
    function cw_change_product_price_display( $price ) {
        if($price == 0){
            $price = ' At Each Item Product';
        }
        return $price;
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
        add_action('init', function () {

            // check priority data
            if (!$this->option('application') || !$this->option('environment') || !$this->option('url')) {
                return $this->notify('Priority API data not set', 'error');
            }

            

            // admin page
            add_action('admin_menu', function () {

                // list tables classes
                include P18AW_CLASSES_DIR . 'pricelist.php';
                include P18AW_CLASSES_DIR . 'productpricelist.php';
                include P18AW_CLASSES_DIR . 'sites.php';
                include P18AW_CLASSES_DIR . 'customersProducts.php';
                include P18AW_CLASSES_DIR . 'productfamily.php';
                

                add_menu_page(P18AW_PLUGIN_NAME, P18AW_PLUGIN_NAME, 'manage_options', P18AW_PLUGIN_ADMIN_URL, function () {

                    switch ($this->get('tab')) {
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
                                WHERE price_list_code = ' . intval($this->get('list')) .
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
                        case 'productfamily';

                            include P18AW_ADMIN_DIR . 'productfamily.php';

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
                            $data = get_post_meta($_GET['ord']);
                            $order_data = $order->get_data();
                            highlight_string("<?php\n\$data =\n" . var_export($data, true) . ";\n?>");
                            highlight_string("<?php\n\$data =\n" . var_export($order_data, true) . ";\n?>");
                            /*
                            $id = $_GET['ord'];
                            $inst = get_post_meta($id, 'מקור', true);
                            foreach ($order->get_items() as $item_id => $item) {
                                if (!empty($item->get_meta('מקור'))) {
                                    $inst = $item->get_meta('מקור');
                                    continue;
                                }
                            }
                            */
                            break;
                        case 'customersProducts';
                            include P18AW_ADMIN_DIR . 'customersProducts.php';
                            break;
                        case 'sync_attachments';
                            include P18AW_ADMIN_DIR . 'syncs/sync_product_attachemtns.php';
                            break;
                        case 'sync-customer';
                            $data = $this->syncCustomer($_GET['customer_id']);
                            highlight_string("<?php\n\$data =\n" . var_export($data, true) . ";\n?>");
                            break;
                        case 'sync-prospect';
                            $data = $this->syncProspect($_GET['ord']);
                            highlight_string("<?php\n\$data =\n" . var_export($data, true) . ";\n?>");
                            break;
                        case 'packs';
                            $this->syncPacksPriority();
                            break;
                        case 'syncItemsPriority';
                            $this->syncItemsPriority();
                            break;
                        case 'sync_items';
                            $this->syncItemsWeb();
                            break;
                        case 'order_status';
                            $order_id = 785;
                            $order = wc_get_order($order_id);
                            echo $order->get_status();
                            break;
                        case 'test';
                            echo 'this is just a test' . PHP_EOL;

                            break;
                        case 'syncPOS':
                            if($this->option('cardPos')){
                                include P18AW_ADMIN_DIR . 'syncPOS.php';     
                            }
                            break;
                        case 'myAccountUser':
                            include P18AW_ADMIN_DIR . 'account.php';     
                            break;    
                        //test sync price pos
                        case 'sync_price_pos';
                            $this->syncPricePriorityPos();
                            break;
                        default:
                            include P18AW_ADMIN_DIR . 'settings.php';
                    }

                });

            });

            // admin actions
            add_action('admin_init', function () {
                wp_enqueue_style('style-css', P18AW_ASSET_URL . 'style.css');
                wp_enqueue_style('select2-css', P18AW_ASSET_URL . 'select2.css');

                wp_enqueue_script('select2-script', P18AW_ASSET_URL . 'select2.min.js', array('jquery'));

                wp_enqueue_script('select2-he', P18AW_ASSET_URL . 'select2-he.js', array('jquery'));
                // enqueue admin scripts
                wp_enqueue_script('p18aw-admin-js', P18AW_ASSET_URL . 'admin.js', ['jquery']);
                wp_localize_script('p18aw-admin-js', 'P18AW', [
                    'nonce' => wp_create_nonce('p18aw_request'),
                    'working' => __('Working', 'p18a'),
                    'sync' => __('Sync', 'p18a'),
                    'asset_url' => P18AW_ASSET_URL
                ]);

            });

            // add post customers button
            add_action('restrict_manage_users', function () {
                printf(' &nbsp; <input id="post-query-submit" class="button" type="submit" value="' . __('Post Customers', 'p18a') . '" name="priority-post-customers">');
            });


            // add post orders button
            add_action('restrict_manage_posts', function ($type) {
                if ($type == 'shop_order') {
                    printf('<input id="post-query-submit" class="button alignright" type="submit" value="' . __('Post orders', 'p18a') . '" name="priority-post-orders">');
                }
            });


            // add column
            add_filter('manage_users_columns', function ($column) {

                $column['priority_customer'] = __('Priority Customer Number', 'p18a');
             // $column['priority_price_list'] = __('Price List', 'p18a');

                return $column;

            });

            // add attach list form to admin footer
            add_action('admin_footer', function () {
                echo '<form id="attach_list_form" name="attach_list_form" method="post" action="' . admin_url('users.php?paged=' . $this->get('paged')) . '"></form>';
            });

            // get column data
            add_filter('manage_users_custom_column', function ($value, $name, $user_id) {

                switch ($name) {

                    case 'priority_customer':


                        $meta = get_user_meta($user_id, 'priority_customer_number');

                        if (!empty($meta)) {
                            return $meta[0];
                        }

                        break;

/*
                    case 'priority_price_list':

                        $lists = $this->getPriceLists();
                        $meta = get_user_meta($user_id, '_priority_price_list');

                        if (empty($meta)) $meta[0] = "no-selected";

                        $html = '<input type="hidden" name="attach-list-nonce" value="' . wp_create_nonce('attach-list') . '" form="attach_list_form" />';
                        $html .= '<select name="price_list[' . $user_id . ']" onchange="window.attach_list_form.submit();" form="attach_list_form">';
                        $html .= '<option value="no-selected" ' . selected("no-selected", $meta[0], false) . '>Not Selected</option>';
                        foreach ($lists as $list) {

                            $selected = (isset($meta[0]) && $meta[0] == $list['price_list_code']) ? 'selected' : '';

                            $html .= '<option  value="' . urlencode($list['price_list_code']) . '" ' . $selected . '>' . $list['price_list_name'] . '</option>' . PHP_EOL;
                        }

                        $html .= '</select>';

                        return $html;

                        break;
*/
                    default:

                        return $value;

                }

            }, 10, 3);

            // save settings
            if ($this->post('p18aw-save-settings') && wp_verify_nonce($this->post('p18aw-nonce'), 'save-settings')) {

                $this->updateOption('walkin_number', $this->post('walkin_number'));
                $this->updateOption('price_method', $this->post('price_method'));
                $this->updateOption('item_status', $this->post('item_status'));
                $this->updateOption('variation_field', $this->post('variation_field'));
                $this->updateOption('variation_field_title', $this->post('variation_field_title'));
                $this->updateOption('sell_by_pl', $this->post('sell_by_pl'));
                $this->updateOption('product_family', $this->post('product_family'));
                $this->updateOption('walkin_hide_price', $this->post('walkin_hide_price'));
                $this->updateOption('text-display-for-non-register', $this->post('text-display-for-non-register'));
                $this->updateOption('link-display-for-non-register', $this->post('link-display-for-non-register'));
                $this->updateOption('sites', $this->post('sites'));
                $this->updateOption('update_image', $this->post('update_image'));
                $this->updateOption('mailing_list_field', $this->post('mailing_list_field'));
                // $this->updateOption('obligo', $this->post('obligo'));
                $this->updateOption('cardPos', $this->post('cardPos'));
                $this->updateOption('selectusers2', $this->post('selectusers2'));
                $this->updateOption('packs', $this->post('packs'));
                $this->updateOption('sync_personnel', $this->post('sync_personnel'));
                $this->updateOption('setting-config', $this->post('setting-config'));
                // save shipping conversion table
                if ($this->post('shipping')) {
                    foreach ($this->post('shipping') as $key => $value) {
                        $this->updateOption('shipping_' . $key, $value);
                    }
                }
                // save payment conversion table
                if ($this->post('payment')) {
                    foreach ($this->post('payment') as $key => $value) {
                        $this->updateOption('payment_' . $key, $value);
                    }
                }
                $this->notify('Settings saved');
            }
            // save sync settings
            if ($this->post('p18aw-save-sync') && wp_verify_nonce($this->post('p18aw-nonce'), 'save-sync')) {
                $this->updateOption('log_items_priority', $this->post('log_items_priority'));
                $this->updateOption('auto_sync_items_priority', $this->post('auto_sync_items_priority'));
                $this->updateOption('email_error_sync_items_priority', $this->post('email_error_sync_items_priority'));
                $this->updateOption('log_items_priority_variation', $this->post('log_items_priority_variation'));
                $this->updateOption('auto_sync_items_priority_variation', $this->post('auto_sync_items_priority_variation'));
                $this->updateOption('email_error_sync_items_priority_variation', $this->post('email_error_sync_items_priority_variation'));
                $this->updateOption('log_items_web', $this->post('log_items_web'));
                $this->updateOption('sync_items_web', $this->post('sync_items_web'));
                $this->updateOption('auto_sync_items_web', $this->post('auto_sync_items_web'));
                $this->updateOption('email_error_sync_items_web', $this->post('email_error_sync_items_web'));
                $this->updateOption('log_inventory_priority', $this->post('log_inventory_priority'));
                $this->updateOption('auto_sync_inventory_priority', $this->post('auto_sync_inventory_priority'));
                $this->updateOption('email_error_sync_inventory_priority', $this->post('email_error_sync_inventory_priority'));
                $this->updateOption('log_pricelist_priority', $this->post('log_pricelist_priority'));
                $this->updateOption('auto_sync_pricelist_priority', $this->post('auto_sync_pricelist_priority'));
                $this->updateOption('email_error_sync_pricelist_priority', $this->post('email_error_sync_pricelist_priority'));
                $this->updateOption('log_productfamily_priority', $this->post('log_productfamily_priority'));
                $this->updateOption('auto_sync_productfamily_priority', $this->post('auto_sync_productfamily_priority'));
                $this->updateOption('email_error_sync_productfamily_priority', $this->post('email_error_sync_productfamily_priority'));
                $this->updateOption('log_receipts_priority', $this->post('log_receipts_priority'));
                $this->updateOption('auto_sync_receipts_priority', $this->post('auto_sync_receipts_priority'));
                $this->updateOption('email_error_sync_receipts_priority', $this->post('email_error_sync_receipts_priority'));
                $this->updateOption('email_error_sync_customers_web', $this->post('email_error_sync_customers_web'));
                $this->updateOption('log_shipping_methods', $this->post('log_shipping_methods'));
                $this->updateOption('email_error_sync_orders_web', $this->post('email_error_sync_orders_web'));
                $this->updateOption('email_error_sync_ainvoices_priority', $this->post('email_error_sync_ainvoices_priority'));
                $this->updateOption('log_sync_order_status_priority', $this->post('log_sync_order_status_priority'));
                $this->updateOption('auto_sync_order_status_priority', $this->post('auto_sync_order_status_priority'));
                $this->updateOption('auto_sync_orders_priority', $this->post('auto_sync_orders_priority'));
                $this->updateOption('log_auto_post_orders_priority', $this->post('log_auto_post_orders_priority'));
                $this->updateOption('auto_sync_sites_priority', $this->post('auto_sync_sites_priority'));
                $this->updateOption('log_sites_priority', $this->post('log_sites_priority'));
                $this->updateOption('auto_sync_packs_priority', $this->post('auto_sync_packs_priority'));
                $this->updateOption('log_packs_priority', $this->post('log_packs_priority'));
                $this->updateOption('auto_sync_c_products_priority', $this->post('auto_sync_c_products_priority'));
                $this->updateOption('log_c_products_priority', $this->post('log_c_products_priority'));
                $this->updateOption('email_error_sync_einvoices_web', $this->post('email_error_sync_einvoices_web'));
                // extra data
                $this->updateOption('sync_inventory_warhsname', $this->post('sync_inventory_warhsname'));
                $this->updateOption('sync_pricelist_priority_warhsname', $this->post('sync_pricelist_priority_warhsname'));
                // sync orders control
                $this->updateOption('post_receipt_checkout', $this->post('post_receipt_checkout'));
                $this->updateOption('cron_receipt', $this->post('cron_receipt'));
                $this->updateOption('receipt_order_field', $this->post('receipt_order_field'));
                $this->updateOption('post_ainvoice_checkout', $this->post('post_ainvoice_checkout'));
                $this->updateOption('cron_ainvoice', $this->post('cron_ainvoice'));
                $this->updateOption('ainvoice_order_field', $this->post('ainvoice_order_field'));
                $this->updateOption('post_customers', $this->post('post_customers'));
                $this->updateOption('post_order_checkout', $this->post('post_order_checkout'));
                $this->updateOption('post_pos_checkout', $this->post('post_pos_checkout'));
                $this->updateOption('cron_orders', $this->post('cron_orders'));
                $this->updateOption('order_order_field', $this->post('order_order_field'));
                $this->updateOption('post_einvoice_checkout', $this->post('post_einvoice_checkout'));
                $this->updateOption('cron_otc', $this->post('cron_otc'));
                $this->updateOption('otc_order_field', $this->post('otc_order_field'));
                $this->updateOption('post_prospect', $this->post('post_prospect'));
                $this->updateOption('prospect_field', $this->post('prospect_field'));
                $this->updateOption('sync_items_priority_config', stripslashes($this->post('sync_items_priority_config')));
                $this->updateOption('sync_variations_priority_config', stripslashes($this->post('sync_variations_priority_config')));
                // customer_to_wp_user
                $this->updateOption('sync_c_products_priority', stripslashes($this->post('sync_c_products_priority')));
                $this->updateOption('sync_customer_to_wp_user', stripslashes($this->post('sync_customer_to_wp_user')));
                $this->updateOption('sync_customer_to_wp_user_config', stripslashes($this->post('sync_customer_to_wp_user_config')));
                $this->updateOption('auto_sync_customer_to_wp_user', stripslashes($this->post('auto_sync_customer_to_wp_user')));


                $this->notify('Sync settings saved');
            }
            // save my account settings
            if ($this->post('p18aw-save-my-account') && wp_verify_nonce($this->post('p18aw-nonce'), 'save-my-account')) {
                $this->updateOption('obligo', $this->post('obligo'));
                $this->updateOption('account_report', $this->post('account_report'));
                $this->updateOption('priority_orders', $this->post('priority_orders'));
                $this->updateOption('priority_quotes', $this->post('priority_quotes'));
                $this->updateOption('priority_invoices', $this->post('priority_invoices'));
                $this->updateOption('priority_receipts', $this->post('priority_receipts'));
                $this->updateOption('priority_documents', $this->post('priority_documents'));
                $this->updateOption('priority_delivery', $this->post('priority_delivery'));
                $this->updateOption('priority_return', $this->post('priority_return'));
                $this->updateOption('priority_cinvoices', $this->post('priority_cinvoices'));

                $this->notify('My Account saved');
            }


            // attach price list
            if ($this->post('price_list') && wp_verify_nonce($this->post('attach-list-nonce'), 'attach-list')) {

                foreach ($this->post('price_list') as $user_id => $list_id) {
                    update_user_meta($user_id, '_priority_price_list', urldecode($list_id));
                }

                $this->notify('User price list changed');

            }

            // post customers to priority
            if ($this->get('priority-post-customers') && $this->get('users')) {

                foreach ($this->get('users') as $id) {
                    //    $this->syncCustomer($id);
                }

                // redirect, otherwise will run twice
                if (wp_redirect(admin_url('users.php?notice=synced'))) {
                    exit;
                }

            }

            // post orders to priority
            if ($this->get('priority-post-orders') && $this->get('post')) {

                foreach ($this->get('post') as $id) {
                    $this->syncOrder($id);
                }

                // redirect
                if (wp_redirect(admin_url('edit.php?post_type=shop_order&notice=synced'))) {
                    exit;
                }

            }

            // display notice
            if ($this->get('notice') == 'synced') {
                $this->notify('Data synced');
            }

        });
        // check the version of woocommerce plugin
        $plugin_folder = get_plugins( '/woocommerce' );
        if ( isset( $plugin_folder[ 'woocommerce.php' ]['Version'] ) ) {
                $woocommerce_version = $plugin_folder[ 'woocommerce.php' ]['Version'];
        };
        if ($woocommerce_version >= '8.8.3') {
            $wc_orders_columns_hook = 'manage_woocommerce_page_wc-orders_columns';
            $wc_orders_custom_column_hook = 'manage_woocommerce_page_wc-orders_custom_column';
        };
        if ($woocommerce_version < '8.8.3') {
            $wc_orders_columns_hook = 'manage_edit-shop_order_columns';
            $wc_orders_custom_column_hook = 'manage_shop_order_posts_custom_column';
        };
        //  add Priority order status to orders page
        // ADDING A CUSTOM COLUMN TITLE TO ADMIN ORDER LIST
        add_filter($wc_orders_columns_hook,
            function ($columns) {
                // Set "Actions" column after the new colum
                $action_column = $columns['order_actions']; // Set the title in a variable
                unset($columns['order_actions']); // remove  "Actions" column


                //add the new column "Status"
                if ($this->option('post_order_checkout')) {
                    // add the Priority order number
                    $columns['priority_order_number'] = '<span>' . __('Priority Order', 'p18w') . '</span>'; // title
                    $columns['priority_order_status'] = '<span>' . __('Priority Order Status', 'p18w') . '</span>'; // title

                }

                //add the new column "Status"
                if ($this->option('post_pos_checkout')) {
                    // add the Priority order number
                    $columns['priority_pos_number'] = '<span>' . __('Priority POS', 'p18w') . '</span>'; // title
                    $columns['priority_pos_status'] = '<span>' . __('Priority POS Status', 'p18w') . '</span>'; // title

                }

                //add the new column "Status"
                if ($this->option('post_einvoice_checkout') || $this->option('post_ainvoice_checkout')) {
                    // add the Priority invoice number
                    $columns['priority_invoice_number'] = '<span>' . __('Priority Invoice', 'p18w') . '</span>'; // title
                    $columns['priority_invoice_status'] = '<span>' . __('Priority Invoice Status', 'p18w') . '</span>'; // title

                }
                //add the new column "Status"
                if ($this->option('post_receipt_checkout') || $this->option('obligo') == true) {
                    // add the Priority recipe number
                    $columns['priority_recipe_number'] = '<span>' . __('Priority Recipe', 'p18w') . '</span>'; // title
                    $columns['priority_recipe_status'] = '<span>' . __('Priority Recipe Status', 'p18w') . '</span>'; // title

                }
                //add the new column "post to Priority"
                $columns['order_post'] = '<span>' . __('Post to Priority', 'p18w') . '</span>'; // title


                // Set back "Actions" column
                $columns['order_actions'] = $action_column;

                return $columns;
            }, 999);

        // ADDING THE DATA FOR EACH ORDERS BY "Platform" COLUMN
        add_action($wc_orders_custom_column_hook,
            function ($column, $post_id) {

                if (is_object($post_id)) {
                    $post_id = $post_id>get_id();  
                }  
                // HERE get the data from your custom field (set the correct meta key below)
                if ($this->option('post_order_checkout')) {
                    $order_status = get_post_meta($post_id, 'priority_order_status', true);
                    $order_number = get_post_meta($post_id, 'priority_order_number', true);
                    if (empty($order_status)) $order_status = '';
                    if (strlen($order_status) > 25) $order_status = '<div class="tooltip">Error<span class="tooltiptext">' . $order_status . '</span></div>';
                    if (empty($order_number)) $order_number = '';
                }
                if ($this->option('post_einvoice_checkout') || $this->option('post_ainvoice_checkout')) {
                    $invoice_number = get_post_meta($post_id, 'priority_invoice_number', true);
                    $invoice_status = get_post_meta($post_id, 'priority_invoice_status', true);
                    if (empty($invoice_status)) $invoice_status = '';
                    if (strlen($invoice_status) > 15) $invoice_status = '<div class="tooltip">Error<span class="tooltiptext">' . $invoice_status . '</span></div>';
                    if (empty($invoice_number)) $invoice_number = '';
                }
                // recipe
                if ($this->option('post_receipt_checkout') || $this->option('obligo') == true) {
                    $recipe_status = get_post_meta($post_id, 'priority_recipe_status', true);
                    $recipe_number = get_post_meta($post_id, 'priority_recipe_number', true);
                    if (empty($recipe_status)) $recipe_status = '';
                    if (strlen($recipe_status) > 15) $recipe_status = '<div class="tooltip">Error<span class="tooltiptext">' . $recipe_status . '</span></div>';
                    if (empty($recipe_number)) $recipe_number = '';
                }
                //POS
                if ($this->option('post_pos_checkout')) {
                    $pos_status = get_post_meta($post_id, 'priority_pos_status', true);
                    $pos_number = get_post_meta($post_id, 'priority_pos_number', true);
                    if (empty($pos_status)) $pos_status = '';
                    if (strlen($pos_status) > 0 && $pos_status != 'Success') $pos_status = '<div class="tooltip">Error<span class="tooltiptext">' . $pos_status . '</span></div>';
                    if (empty($pos_number)) $pos_number = '';
                }
                switch ($column) {
                    // order
                    case 'priority_order_status' :
                        echo $order_status;
                        break;
                    case 'priority_order_number' :
                        echo '<span>' . $order_number . '</span>'; // display the data
                        break;
                    // invoice
                    case 'priority_invoice_status' :
                        if(!empty($invoice_status)){
	                        echo $invoice_status;
                        }
                        break;
                    case 'priority_invoice_number' :
                        if(!empty($invoice_number)){
                        echo '<span>' . $invoice_number . '</span>'; // display the data
                            }
                        break;
                    // reciept
                    case 'priority_recipe_status' :
                        echo $recipe_status;
                        break;
                    case 'priority_recipe_number' :
                        echo '<span>' . $recipe_number . '</span>'; // display the data
                        break;
                    // pos
                    case 'priority_pos_status' :
                        echo $pos_status;
                        break;
                    case 'priority_pos_number' :
                        echo '<span>' . $pos_number . '</span>'; // display the data
                        break;
                    // post order to API, using GET and
                    case 'order_post' :
                        $url = 'admin.php?page=priority-woocommerce-api&tab=post_order&ord=' . $post_id;
                        echo '<span><a href=' . $url . '>' . __('Re Post', 'p18w') . '</a></span>'; // display the data
                        break;
                }
            }, 999, 2);

        // MAKE 'stauts' METAKEY SEARCHABLE IN THE SHOP ORDERS LIST
        add_filter('woocommerce_shop_order_search_fields',
            function ($meta_keys) {
                $meta_keys[] = 'priority_order_status';
                $meta_keys[] = 'priority_order_number';
                $meta_keys[] = 'priority_invoice_status';
                $meta_keys[] = 'priority_invoice_number';
                $meta_keys[] = 'priority_recipe_status';
                $meta_keys[] = 'priority_recipe_number';
                $meta_keys[] = 'priority_pos_status';
                $meta_keys[] = 'priority_pos_number';
                return $meta_keys;
            }, 10, 1);

        // ajax action for manual syncs
        add_action('wp_ajax_p18aw_request', function () {

            // check nonce
            check_ajax_referer('p18aw_request', 'nonce');

            set_time_limit(420);

            // switch syncs
            switch ($_POST['sync']) {
                case 'auto_post_orders_priority':
                    try {
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

                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }
                    break;
                case 'sync_items_priority':

                    try {
                        $this->syncItemsPriority();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;
                case 'sync_items_priority_variation':

                    try {
                        $this->syncItemsPriorityVariation();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;
                case 'sync_items_web':

                    try {
                        $this->syncItemsWeb();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;
                case 'sync_inventory_priority':


                    try {
                        $this->syncInventoryPriority();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;

                case 'sync_pricelist_priority':


                    try {
                        $this->syncPriceLists();
                        $this->syncSpecialPriceItemCustomer();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;
                case 'sync_productfamily_priority':
                    try {
                        $this->syncSpecialPriceProductFamily();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }
                    break;

                case 'sync_sites_priority':


                    try {
                        $this->syncSites();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;
                case 'sync_packs_priority':


                    try {
                        $this->syncPacksPriority();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;
                case 'sync_receipts_priority':

                    try {

                        $this->syncReceipts();

                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                    break;

                case 'post_customers':

                    try {

                        $customers = get_users(['role' => 'customer']);

                        foreach ($customers as $customer) {
                            //  $this->syncCustomer($customer->ID);
                        }

                    } catch (Exception $e) {
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
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }
                case 'auto_sync_customer_to_wp_user':
                    try {
                        $this->sync_priority_customers_to_wp();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }
                    break;
                case 'sync_c_products_priority':
                    try {
                        $this->syncCustomerProducts();
                    } catch (Exception $e) {
                        exit(json_encode(['status' => 0, 'msg' => $e->getMessage()]));
                    }

                default:

                    exit(json_encode(['status' => 0, 'msg' => 'Unknown method ' . $_POST['sync']]));

            }

            exit(json_encode(['status' => 1, 'timestamp' => date('d/m/Y H:i:s')]));


        });

        // ajax action for manual syncs
        add_action('wp_ajax_p18aw_request_error', function () {

            $url = sprintf('https://%s/odata/Priority/%s/%s/%s',
                $this->option('url'),
                $this->option('application'),
                $this->option('environment'),
                ''
            );

            $GLOBALS['wpdb']->insert($GLOBALS['wpdb']->prefix . 'p18a_logs', [
                'blog_id' => get_current_blog_id(),
                'timestamp' => current_time('mysql'),
                'url' => $url,
                'request_method' => 'GET',
                'json_request' => '',
                'json_response' => 'AJAX ERROR ' . $_POST['msg'],
                'json_status' => 0
            ]);

            $this->sendEmailError(
                $this->option('email_error_' . $_POST['sync']),
                'Error ' . ucwords(str_replace('_', ' ', $_POST['sync'])),
                'AJAX ERROR<br>' . $_POST['msg']
            );

            exit(json_encode(['status' => 1, 'timestamp' => date('d/m/Y H:i:s')]));
        });
        // post order on status change
        // this is duplicate with action defined next to thank you action
        //add_action( 'woocommerce_order_status_changed', [ $this, 'syncDataAfterOrder' ],9999);
        //add_action( 'woocommerce_rest_insert_shop_order_object', [ $this, 'post_order_status_to_priority' ],10);
        //add_action( 'woocommerce_new_order', [ $this, 'syncDataAfterOrder' ]);
        add_action('woocommerce_order_status_changed', [$this, 'syncReceiptAfterOrder']);
        add_action('woocommerce_order_status_changed', [$this, 'syncDataAfterOrder']);
        add_action('woocommerce_order_status_changed', [$this, 'post_order_status_to_priority'], 10);
    }

// Generating dynamically the product "sale price"

    function custom_dynamic_sale_price($sale_price, $product)
    {
        $codeFamily = get_post_meta($product->get_id(), 'family_code', true);
        $user = wp_get_current_user();
        $custname = empty(get_user_meta($user->ID, 'priority_mcustomer_number', true))
            ? get_user_meta($user->ID, 'priority_customer_number', true) :
            get_user_meta($user->ID, 'priority_mcustomer_number', true);
        $family = $this->getFamilyProduct($custname, $codeFamily);
        if ($family > 0) {
            $rate = ($family * $product->get_regular_price()) / 100;
            if (empty($sale_price) || $sale_price == 0)
                return $product->get_regular_price() - $rate;
        } else
            return $sale_price;
    }

// Displayed formatted regular price + sale price
    function custom_dynamic_sale_price_html($price_html, $product)
    {
        if ($product->is_type('variable')) return $price_html;
        $price = $this->filterPrice($product->get_regular_price(), $product);
        if ($price != $product->get_regular_price()) {
            $sale_price = $this->custom_dynamic_sale_price($price, $product);

            if (!empty($sale_price) && $price > $sale_price) {
                $price_html = wc_format_sale_price(
                        wc_get_price_to_display($product, array('price' => $product->get_regular_price())),
                        wc_get_price_to_display($product, array('price' => $sale_price))) . $product->get_price_suffix();

            } else {
                $price_html = wc_format_sale_price(
                        wc_get_price_to_display($product, array('price' => $product->get_regular_price())),
                        wc_get_price_to_display($product, array('price' => $price))) . $product->get_price_suffix();
            }
        } else {
            $price_html = wc_price(wc_get_price_to_display($product, array('price' => $product->get_regular_price()))) . $product->get_price_suffix();
        }
        return $price_html;
    }
    public function my_product_update($post)
    {
        if ($post->post_type == "product") {
            $productId = $post->ID;
            add_post_meta($productId, 'family_code', '');
	        add_post_meta($productId, 'mpartname', '');
        }
    }
    public function post_order_status_to_priority($order_id)
    {
        // this code is currently working only for EINVOICES
        if (empty(get_post_meta($order_id, '_post_done', true))) {
            return;
        }
        $config = json_decode(stripslashes($this->option('setting-config')));
        $statdes = null;
        $order = new \WC_Order($order_id);
        foreach ($config->status_convert[0] as $key => $value) {
            if ($order->get_status() == $key) {
                $statdes = $value;
            }
        }
        if (!$statdes) {
            return;
        }
        // get the invoice number from Priority
        $order_field = $this->option('otc_order_field');
        $url_addition = 'EINVOICES?$filter=' . $order_field . ' eq \'' . $order_id . '\'';
        $response = $this->makeRequest('GET', $url_addition, [], false);
        if ($response['status']) {
            $response_data = json_decode($response['body_raw'], true);
            $invoice = $response_data['value'][0];
            $ivnum = $invoice['IVNUM'];
        }
        // post status to Priority
        $url_addition = 'EINVOICES(IVNUM=\'' . $ivnum . '\',IVTYPE=\'E\',DEBIT=\'D\')';
        $data = ['STATDES' => $statdes];
        $response = $this->makeRequest('PATCH', $url_addition, ['body' => json_encode($data)], false);
        if ($response['status']) {
            $response_data = json_decode($response['body_raw'], true);
        }
    }
    public function is_attribute_exists($slug)
    {
        $is_attr_exists = false;
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        if ($attribute_taxonomies) {
            foreach ($attribute_taxonomies as $tax) {
                if ($slug == $tax->attribute_name) {
                    $is_attr_exists = true;
                }
            }
        }
        return $is_attr_exists;
    }
    public function syncItemsPriority() {

	    $priority_version = (float) $this->option( 'priority-version' );
	    // config
	    $raw_option     = $this->option( 'sync_items_priority_config' );
	    $raw_option     = str_replace( array( "\n", "\t", "\r" ), '', $raw_option );
	    $config         = json_decode( stripslashes( $raw_option ) );
	    $image_base_url = $config->image_base_url;

	    if ( $config->sync_price == "true" ) {
		    $this->syncPricePriority();
		    // return;
	    }
	    $synclongtext        = $config->synclongtext;
	    $daysback            = ( ! empty( (int) $config->days_back ) ? $config->days_back : 1 );
	    $url_addition_config = ( ! empty( $config->additional_url ) ? $config->additional_url : '' );
	    $search_field        = ( ! empty( $config->search_by ) ? $config->search_by : 'PARTNAME' );
	    $search_field_web    = ( ! empty( $config->search_field_web ) ? $config->search_field_web : '_sku' );
	    $stock_status        = ( ! empty( $config->stock_status ) ? $config->stock_status : 'outofstock' );
	    $is_categories       = ( ! empty( $config->categories ) ? $config->categories : null );
	    $statdes             = ( ! empty( $config->statdes ) ? $config->statdes : false );
	    $is_attrs            = ( ! empty( $config->attrs ) ? $config->attrs : false );
	    $brands              = ( ! empty( $config->brands ) ? $config->brands : false );
	    $is_update_products  = ( ! empty( $config->is_update_products ) ? $config->is_update_products : false );
	    $show_in_web         = ( ! empty( $config->show_in_web ) ? $config->show_in_web : 'SHOWINWEB' );
	    $variation_field     = $this->option( 'variation_field' ) == 'true' ? $this->option( 'variation_field' ) : 'MPARTNAME';
	    // get the items simply by time stamp of today
	    $product_price_list = ( ! empty( $config->product_price_list ) ? $config->product_price_list : null );
	    $product_price_sale = ( ! empty( $config->product_price_sale ) ? $config->product_price_sale : null );
	    // get the items simply by time stamp of today
	    $stamp          = mktime( 0 - $daysback * 24, 0, 0 );
	    $bod            = date( DATE_ATOM, $stamp );
	    $date_filter    = 'UDATE ge ' . urlencode( $bod );
	    $data['select'] = 'PARTNAME,PARTDES,BASEPLPRICE,VATPRICE,STATDES,BARCODE,SHOWINWEB,SPEC1,SPEC2,SPEC3,SPEC4,SPEC5,SPEC6,SPEC7,SPEC8,SPEC9,SPEC10,SPEC11,SPEC12,SPEC13,SPEC14,SPEC15,SPEC16,SPEC17,SPEC18,SPEC19,SPEC20,FAMILYDES,INVFLAG,FAMILYNAME';
	    if ( $priority_version < 21.0 ) {
		    $data['select'] .= ',EXTFILENAME';
	    }
	    if ( $product_price_list != null ) {
		    $data['expand'] = '$expand=PARTUNSPECS_SUBFORM,PARTTEXT_SUBFORM,PARTINCUSTPLISTS_SUBFORM($select=PLNAME,PRICE,VATPRICE;$filter=PLNAME eq \'' . $product_price_list . '\')';
	    } else {
		    $data['expand'] = '$expand=PARTUNSPECS_SUBFORM,PARTTEXT_SUBFORM';
	    }
	    $data = apply_filters( 'simply_syncItemsPriority_data', $data );


	    if ( $config->ignore_variations == 'true' ) {
        $response = $this->makeRequest( 'GET',
            'LOGPART?$select=' . $data['select'].=',MPARTNAME'. '&$filter=' . $date_filter .  $url_addition_config .
            '&' . $data['expand'] . '', [],
            $this->option( 'log_items_priority', true ) );
	    } else {
	    $response = $this->makeRequest( 'GET',
		    'LOGPART?$select=' . $data['select'] . '&$filter=' . $date_filter . ' and ' . $variation_field . ' eq \'\' and ISMPART ne \'Y\' ' . $url_addition_config .
		    '&' . $data['expand'] . '', [],
		    $this->option( 'log_items_priority', true ) );
         }
        // check response status

        if ($response['status']) {
            $response_data = json_decode($response['body_raw'], true);
            try {
	            foreach ( $response_data['value'] as $item ) {
                    //if you want customized syncItemsPriority, activate the function
                    $item = apply_filters('simply_syncItemsPriorityAdapt', $item);

		            if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
			            error_log($item['PARTNAME']);
		            }
                    if($item['PARTNAME']=='HP-H200GS'){
                        $foo = 'Im here...';
                    }

		            // add long text from Priority
		            $content      = '';
		            $post_content = '';
		            if ( isset( $item['PARTTEXT_SUBFORM'] ) ) {
			            foreach ( $item['PARTTEXT_SUBFORM'] as $text ) {
                            $clean_text = preg_replace('/<style>.*?<\/style>/s', '', $text);
				            $content .= ' ' . html_entity_decode( $clean_text );
			            }
		            }
		            $data = [
			            'post_author' => 1,
			            //'post_content' =>  $content,
			            'post_status' => $this->option( 'item_status' ),
			            'post_title'  => $item['PARTDES'],
			            'post_parent' => '',
			            'post_type'   => 'product',
		            ];
		            if ( $synclongtext ) {
			            $data['post_content'] = $content;
		            }
		            // if product exsits, update
		            $search_by_value = (string) $item[ $search_field ];
		            $args            = array(
			            'post_type'   => array( 'product', 'product_variation' ),
			            'post_status' => array( 'publish', 'draft' ),
			            'meta_query'  => array(
				            array(
					            'key'   => $search_field_web,
					            'value' => $search_by_value
				            )
			            )
		            );
		            $product_id      = 0;
		            $my_query        = new \WP_Query( $args );
		            if ( $my_query->have_posts() ) {
			            while ( $my_query->have_posts() ) {
				            $my_query->the_post();
				            $product_id = get_the_ID();
			            }
		            }
		            // if product variation skip
		            if ( $product_id != 0 ) {
			            $_product = wc_get_product( $product_id );
			            if ( ! $_product->is_type( 'simple' ) ) {
				            $item['variation_id'] = $product_id;
				            do_action( 'simply_update_variation_data', $item );
				            /*
							$pri_price = wc_prices_include_tax() == true ? $item['VATPRICE'] : $item['BASEPLPRICE'];
							$foo = $_product->set_regular_price($pri_price);
							update_post_meta($product_id, '_regular_price',$pri_price);
							 */
				            continue;
			            }
		            }
		            // delete not active
		            if ( $statdes == true ) {
			            if ( $item['STATDES'] == "לא פעיל" ) {
				            if ( $product_id != 0 ) {
					            $_product->delete( true );

				            } else {
					            //continue;
				            }
				            continue;
			            }
		            }
		            // check if the item flagged as show in web, if not skip the item
		            if ( isset( $show_in_web ) ) {
			            if ( $product_id == 0 && $item[ $show_in_web ] != 'Y' ) {
				            continue;
			            }
			            if ( $product_id != 0 && $item[ $show_in_web ] != 'Y' ) {
				            $_product->set_status( 'draft' );
				            $_product->save();
				            continue;
			            }
		            }
		            // check if update existing products
		            if ( $product_id != 0 && false == $is_update_products ) {
                        $item['product_id'] = $product_id;
                        do_action('simply_update_product_price', $item);
			            continue;
		            }
		            // update product
		            if ( $product_id != 0 ) {
			            $data['ID'] = $product_id;
			            $_product->set_status($this->option('item_status'));
			            $_product->save();
			            // Update post
			            $id = $product_id;
			            global $wpdb;
			            // @codingStandardsIgnoreStart
			            if ( $synclongtext ) {
				            $wpdb->query(
					            $wpdb->prepare(
						            "
							UPDATE $wpdb->posts
							SET post_title = '%s',
							post_content = '%s'
							WHERE ID = '%s'
							",
						            $item['PARTDES'],
						            $content,
						            $id
					            )
				            );
			            } else {
				            $wpdb->query(
					            $wpdb->prepare(
						            "
							UPDATE $wpdb->posts
							SET post_title = '%s'
							WHERE ID = '%s'
							",
						            $item['PARTDES'],
						            $id
					            )
				            );
			            }
		            } else {
			            // Insert product
			            $id = wp_insert_post( $data );
			            if ( $id ) {
				            update_post_meta( $id, '_sku', $search_by_value );
				            update_post_meta( $id, '_stock_status', $stock_status );
				            if ( $stock_status == 'outofstock' ) {
					            update_post_meta( $id, '_stock', 0 );
					            wp_set_post_terms( $id, 'outofstock', 'product_visibility', true );
				            }
                            if ( ! empty( $item['INVFLAG'] ) ) {
                                update_post_meta( $id, '_manage_stock', ( $item['INVFLAG'] == 'Y' ) ? 'yes' : 'no' );
                            }
			            }
		            }

		            // And finally (optionally if needed)
		            wc_delete_product_transients( $id ); // Clear/refresh the variation cache
		            // update product price
		            $item['product_id'] = $id;
		            $item               = apply_filters( 'simply_syncItemsPriority_item', $item );
		            unset( $item['id'] );
                    //check if WooCommerce Tax Settings are set
                    $set_tax = get_option('woocommerce_calc_taxes');
		            if ( $product_price_list != null && ! empty( $item['PARTINCUSTPLISTS_SUBFORM'] ) ) {
			            $pri_price = (wc_prices_include_tax() == true || $set_tax == 'no') ? $item['PARTINCUSTPLISTS_SUBFORM'][0]['VATPRICE'] : $item['PARTINCUSTPLISTS_SUBFORM'][0]['PRICE'];

		            } else {
			            $pri_price = (wc_prices_include_tax() == true || $set_tax == 'no') ? $item['VATPRICE'] : $item['BASEPLPRICE'];
		            }
		            if ( $id ) {
			            $my_product = new \WC_Product( $id );
			            if ( ! empty( $show_in_web ) && $item[ $show_in_web ] != 'Y' ) {
				            $my_product->set_status( 'draft' );
				            $my_product->save();
				            continue;
			            }
			            // price
			            $my_product->set_regular_price( $pri_price );
			            if ( $product_price_sale != null && ! empty( $item[ $product_price_sale ] ) ) {
				            $price_sale = $item[ $product_price_sale ];
				            if ( $price_sale != 0 ) {
					            $my_product->set_sale_price( $price_sale );
				            }
			            }
			            // sales price make troubles. Roy need to think what to do with it.
//                    if (null == $my_product->get_sale_price()) {
			            //   $my_product->set_sale_price(0);
//                    }
			            if ( ! empty( $config->menu_order ) ) {
				            $my_product->set_menu_order( $item[ $config->menu_order ] );
			            }
			            if ( ! empty( $my_product->get_meta_data( 'family_code', true ) ) ) {
				            $my_product->update_meta_data( 'family_code', $item['FAMILYNAME'] );
			            } else {
				            $my_product->add_meta_data( 'family_code', $item['FAMILYNAME'] );
			            }
			            //$my_product->set_sale_price( $sales_price);
			            $my_product->save();
			            //update_post_meta($id, '_regular_price', $pri_price);
			            //update_post_meta($id, '_price',$pri_price );
			            $taxon = 'product_cat';
			            if ( ! empty( $config->parent_category ) || ! empty( $is_categories ) ) {
				            $terms = get_the_terms( $id, $taxon );
				            foreach ( $terms as $term ) {
					            wp_remove_object_terms( $id, $term->term_id, $taxon );
				            }
			            }
			            if ( ! empty( $config->parent_category ) ) {
				            $parent_category = wp_set_object_terms( $id, $item[ $config->parent_category ], $taxon, true );
			            }
			            if ( ! empty( $is_categories ) ) {
				            // update categories
				            $categories = [];
				            foreach ( explode( ',', $config->categories ) as $cat ) {
					            if ( ! empty( $item[ $cat ] ) ) {
						            array_push( $categories, $item[ $cat ] );
					            }
				            }
				            if ( ! empty( $categories ) ) {
					            $d     = 0;
					            $terms = $categories;
					            if ( ! empty( $config->parent_category ) && $parent_category[0] > 0 ) {
						            $term_exists = term_exists( $terms[0], $taxon, $parent_category );
						            $childs      = get_term_children( $parent_category[0], $taxon );
						            if ( ! empty( $childs ) ) {
							            foreach ( $childs as $child ) {
								            $cat_c = get_term_by( 'id', $child, $taxon, 'ARRAY_A' );
								            if ( $cat_c['name'] == $terms[0] ) {
									            $terms_cat = wp_set_object_terms( $id, $child, $taxon, true );
									            $d         = 1;
								            }
							            }
						            }
						            if ( empty( $term_exists ) || $d == 0 ) {
							            $terms = wp_insert_term( $terms[0], $taxon, array( 'parent' => $parent_category[0] ) );
						            }
						            if ( is_wp_error( $terms ) ) {
							            $error_message = $terms->get_error_message();
						            } else {
							            array_push( $terms, $item[ $config->parent_category ] );
						            }


					            }
					            if ( is_wp_error( $terms ) ) {

					            } else {
                                    if ( $d != 1 ) {
                                        wp_set_object_terms( $id, $terms, $taxon );
                                    } else {
                                        wp_set_object_terms( $id, $item[ $config->parent_category ], $taxon, true );
                                    }
				                 }


				            }
			            }

		            }
		            // update MPARTNAME
		            update_post_meta( $my_product->get_id(), 'mpartname', $item[ $variation_field ] );
		            // update attributes
		            if ( $is_attrs != false ) {
			            unset( $thedata );
			            foreach ( $item['PARTUNSPECS_SUBFORM'] as $attribute ) {
				            $attr_name  = $attribute['SPECDES'];
				            $attr_slug  = strtolower( $attribute['SPECNAME'] );
				            $attr_value = $attribute['VALUE'];
				            if ( ! $this->is_attribute_exists( $attr_slug ) ) {
					            $attribute_id = wc_create_attribute(
						            array(
							            'name'         => $attr_name,
							            'slug'         => $attr_slug,
							            'type'         => 'select',
							            'order_by'     => 'menu_order',
							            'has_archives' => 0,
						            )
					            );
				            }
				            wp_set_object_terms( $id, $attr_value, 'pa_' . $attr_slug, false );
				            $thedata[ 'pa_' . $attr_slug ] = array(
					            'name'        => 'pa_' . $attr_slug,
					            'value'       => '',
					            'is_visible'  => '1',
					            'is_taxonomy' => '1'
				            );
			            }
			            /* loop over array of custom attributes */
			            $custom_attrs = [];

			            $custom_attrs = apply_filters( 'simply_add_custom_attributes', $custom_attrs );

			            if ( ! empty( $custom_attrs ) ) {
				            foreach ( $custom_attrs as $attr ) {
					            $val = $attr[2];
					            if ( is_array( $val ) ) {
						            $val = array();
						            foreach ( $attr[2] as $v ) {
							            if ( ( $item[ $v ] ) != null ) {
								            $val[] = $item[ $v ];
							            }
						            }
					            } else if ( empty( $item[ $val ] ) ) {
						            continue;
					            } else {
						            $attr_value = $item[ $val ];
					            }

					            $attr_name = $attr[0];
					            $attr_slug = $attr[1];
					            if ( ! $this->is_attribute_exists( $attr_slug ) ) {
						            $attribute_id = wc_create_attribute(
							            array(
								            'name'         => $attr_name,
								            'slug'         => $attr_slug,
								            'type'         => 'select',
								            'order_by'     => 'menu_order',
								            'has_archives' => 0,
							            )
						            );
					            } else {
						            $attribute_id = 'pa_' . wc_sanitize_taxonomy_name( $attr_slug );
					            }

					            if ( is_array( $val ) ) {
						            $taxonomy = 'pa_' . wc_sanitize_taxonomy_name( $attr_slug );
						            $val_id   = array();
						            foreach ( $val as $option ) {
							            {

								            // Save the possible option value for the attribute which will be used for variation later
								            wp_set_object_terms( $id, $option, $taxonomy, true );
								            // Get the term ID
								            $val_id[] = get_term_by( 'name', $option, $taxonomy )->term_id;
							            }

						            }
						            if ( ! empty( $val_id ) ) {
							            $thedata[ $attribute_id ] = array(
								            'name'         => $attribute_id,
								            'value'        => $val_id, // Need to be term IDs
								            'is_visible'   => 1,
								            'is_variation' => 1,
								            'is_taxonomy'  => '1'
							            );
						            }

					            } else {
						            wp_set_object_terms( $id, $attr_value, $attribute_id, false );
						            $thedata[ $attribute_id ] = array(
							            'name'        => $attribute_id,
							            'value'       => $attr_value,
							            'is_visible'  => '1',
							            'is_taxonomy' => '1'
						            );
					            }
				            }
				            if ( ! empty( ( $thedata ) ) ) {
					            update_post_meta( $id, '_product_attributes', $thedata );
				            }
			            }
		            }
		            //sync Brands
		            if ( ( $brands ) != false ) {
			            if ( ! empty( $item[ $brands ] ) && $id ) {
				            $br_tex = 'pwb-brand';
				            $br_tex = apply_filters( 'simplyct_brand_tax', $br_tex );
				            wp_set_object_terms( $id, $item[ $brands ], $br_tex );

			            }
		            }

		            $item['product_id'] = $id;
		            do_action( 'simply_update_product_data', $item );

		            // sync image
		            $is_load_image = json_decode( $config->is_load_image );
		            if ( false == $is_load_image ) {
			            continue;
		            }
		            $sku          = $item[ $search_field ];
		            $is_has_image = get_the_post_thumbnail_url( $id );
		            if ( $this->option( 'update_image' ) == true || ! get_the_post_thumbnail_url( $id ) ) {
			            $file_     = $this->load_image( $item['EXTFILENAME'] ?? '', $image_base_url, $priority_version, $sku, $search_field );
			            $attach_id = $file_[0];
			            $file      = $file_[1];
			            if ( empty( $file ) ) {
				            continue;
			            }
			            include $file;
			            require_once( ABSPATH . '/wp-admin/includes/image.php' );
			            $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
			            wp_update_attachment_metadata( $attach_id, $attach_data );
			            set_post_thumbnail( $id, $attach_id );
		            }


	            }
                //activate the action if you have a syncItemsPriority match
                do_action('syncItemsPriorityAdapt');
            } catch (Exception $e) {
		    // Exception handling code
		    echo "Exception caught: " . $e->getMessage();
	         }
            // add timestamp
            $this->updateOption('items_priority_update', time());
        } else {
            $this->sendEmailError(
                $this->option('email_error_sync_items_priority'),
                'Error Sync Items Priority',
                $response['body']
            );
        }

        return $response;
    }
    public function syncPricePriority()
    {
        $raw_option = $this->option('sync_items_priority_config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $product_price_list = (!empty($config->product_price_list) ? $config->product_price_list : null);
        $product_price_sale = (!empty($config->product_price_sale) ? $config->product_price_sale : null);
        $daysback = (!empty((int)$config->days_back) ? $config->days_back : 1);
        $url_addition_config = (!empty($config->additional_url) ? '&$filter=' . $config->additional_url : '');
        $search_field = (!empty($config->search_by) ? $config->search_by : 'PARTNAME');
        $search_field_web = (!empty($config->search_field_web) ? $config->search_field_web : '_sku');
        $stamp = mktime(0 - $daysback * 24, 0, 0);
        $bod = date(DATE_ATOM, $stamp);
        $date_filter = 'UDATE ge ' . urlencode($bod);
        $data['select'] = 'PARTNAME,BASEPLPRICE,VATPRICE,BARCODE';
        if ($product_price_list != null) {
            $expand = '&$expand=PARTINCUSTPLISTS_SUBFORM($select=PLNAME,PRICE,VATPRICE;$filter=PLNAME eq \'' . $product_price_list . '\' and ' . $date_filter . ' )';
        }
        $data = apply_filters('simply_syncPricePriority', $data);
        if (empty($product_price_list)) {
            $data['select'] .= '&$filter=' . $date_filter . '';
        }
        $response = $this->makeRequest('GET', 'LOGPART?$select=PARTNAME,BARCODE' . $url_addition_config . '&' . $expand . ''
            , [], $this->option('log_items_priority', true));
        if ($response['status']) {

            $response_data = json_decode($response['body_raw'], true);
            foreach ($response_data['value'] as $item) {
                // if product exsits, update price
                $search_by_value = (string)$item[$search_field];
                $args = array('post_type' => array('product', 'product_variation'),
                    'post_status' => array('publish', 'draft'),
                    'meta_query' => array(
                        array(
                            'key' => $search_field_web,
                            'value' => $search_by_value
                        )
                    )
                );
                $product_id = 0;
                $my_query = new \WP_Query($args);
                if ($my_query->have_posts()) {
                    while ($my_query->have_posts()) {
                        $my_query->the_post();
                        $product_id = get_the_ID();
                    }
                }
                //check if WooCommerce Tax Settings are set
                $set_tax = get_option('woocommerce_calc_taxes');

                // if product variation skip
                if ($product_id != 0) {
                    if ($product_price_list != null) {
                        if (!empty($item['PARTINCUSTPLISTS_SUBFORM'])) {
                            $pri_price = (wc_prices_include_tax() || $set_tax == 'no') ? $item['PARTINCUSTPLISTS_SUBFORM'][0]['VATPRICE'] : $item['PARTINCUSTPLISTS_SUBFORM'][0]['PRICE'];

                        } else {
                            continue;
                        }
                    } else {
                        $pri_price = (wc_prices_include_tax() || $set_tax == 'no') ? $item['VATPRICE'] : $item['BASEPLPRICE'];

                    }
                    $product = get_post($product_id);
                    if ('product_variation' == $product->post_type) {
                        $my_product = new \WC_Product_Variation($product_id);
                    } else {
                        $my_product = new \WC_Product($product_id);
                    }
                    if ($product_price_sale != null && $item[$product_price_sale]) {
                        $price_sale = $item[$product_price_sale];
                        if ($price_sale != 0) {
                            $my_product->set_sale_price($price_sale);
                        }
                    }
                    $my_product->set_regular_price($pri_price);

                    $my_product->save();
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
        }
        return $response;
    }
    public function simply_posts_where($where, $query)
    {
        global $wpdb;
        // Check if our custom argument has been set on current query.
        if ($query->get('filename')) {
            $filename = $query->get('filename');
            // Add WHERE clause to SQL query.
            $where .= " AND $wpdb->posts.post_title LIKE '" . $filename . "'";
        }
        return $where;
    }
    public function simply_check_file_exists($file_name)
    {
        add_filter('posts_where', array($this, 'simply_posts_where'), 10, 2);
        $args = array(
            'post_type' => 'attachment',
            'posts_per_page' => '-1',
            'post_status' => 'any',
            'filename' => $file_name,
        );
        $the_query = new \WP_Query($args);
        remove_filter('posts_where', array($this, 'simply_posts_where'), 10);
// The Loop
        if ($the_query->have_posts()) {
            while ($the_query->have_posts()) {
                $the_query->the_post();
                return get_the_ID();
            }

        } else {
            // no posts found
            return false;
        }
    }
    function sync_product_attachemtns()
    {
        /*
        * the function pull the urls from Priority,
        * then check if the file already exists as attachemnt in WP
        * if is not exists, will download and attache
        * if exists, will pass but will keep the file attached
        * any file that exists in WP and not exists in Priority will remain
        * the function ignore other file extensions
        * you cant anyway attach files that are not images
        */
        $raw_option = $this->option('sync_items_priority_config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $search_field = (!empty($config->search_by) ? $config->search_by : 'PARTNAME');
        $search_field_web = (!empty($config->search_field_web) ? $config->search_field_web : '_sku');
        ob_start();
        //$allowed_sufix = ['jpg', 'jpeg', 'png'];
        $daysback = (!empty((int)$config->days_back) ? $config->days_back : 1);
        $stamp = mktime(0 - $daysback * 24, 0, 0);
        $bod = date(DATE_ATOM, $stamp);
        $search_field_select = $search_field == 'PARTNAME' ? $search_field : $search_field . ',PARTNAME';
        $priority_version = (float)$this->option('priority-version');
        if ($priority_version < 21.0) {
            $response = $this->makeRequest('GET',
                'LOGPART?$filter=UDATE ge ' . urlencode($bod) . ' and EXTFILEFLAG eq \'Y\' &$select=' . $search_field_select . '&$expand=PARTEXTFILE_SUBFORM($select=EXTFILENAME,EXTFILEDES,SUFFIX;$filter=SUFFIX eq \'png\' or SUFFIX eq \'jpeg\' or SUFFIX eq \'jpg\')'
                , [], $this->option('log_attachments_priority', true));
        }
        else{
            $response = $this->makeRequest('GET',
                'LOGPART?$filter=UDATE ge ' . urlencode($bod) . ' and EXTFILEFLAG eq \'Y\' &$select=' . $search_field_select, [], $this->option('log_attachments_priority', true));
        }
        $response_data = json_decode($response['body_raw'], true);
        foreach ($response_data['value'] as $item) {
            $search_by_value = $item[$search_field];
            $sku = $item[$search_field];
            //$product_id = wc_get_product_id_by_sku($sku);
            $args = array(
                'post_type' => 'product',
                'meta_query' => array(
                    array(
                        'key' => $search_field_web,
                        'value' => $search_by_value
                    )
                )
            );
            $product_id = 0;
            $my_query = new \WP_Query($args);
            if ($my_query->have_posts()) {
                $my_query->the_post();
                $product_id = get_the_ID();
            } else {
                $product_id = 0;
                continue;
            }
            //**********
            $product = new \WC_Product($product_id);
            $product_media = $product->get_gallery_image_ids();
            $attachments = [];
            echo 'Starting process for product ' . $sku . '<br>';
            if ($priority_version < 21.0) {
                foreach ($item['PARTEXTFILE_SUBFORM'] as $attachment) {
                    $file_path = $attachment['EXTFILENAME'];
                    $is_uri = strpos('1' . $file_path, 'http') ? false : true;
                    if (!empty($file_path)) {
                        $file_ext = $attachment['SUFFIX'];
                        $images_url = 'https://' . $this->option('url') . '/zoom/primail';
                        $image_base_url = $config->image_base_url;
                        if (!empty($image_base_url)) {
                            $images_url = $image_base_url;
                        }
                        $priority_image_path = $file_path;
                        $product_full_url = str_replace('../../system/mail', $images_url, $priority_image_path);
                        $product_full_url = str_replace(' ', '%20', $product_full_url);
                        $product_full_url = str_replace('‏‏', '%E2%80%8F%E2%80%8F', $product_full_url);
                        $file_n = 'simplyCT/' . $sku . $attachment['EXTFILEDES'] . '.' . $file_ext;
                    //  $upload_path = $sku . $attachment['EXTFILEDES'] . '.' . $file_ext;
                        $upload_path = wp_get_upload_dir()['basedir'] . '/' . $file_n;

                        if (file_exists($upload_path) == true) {
                            global $wpdb;
                            $id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_n' AND meta_key = '_wp_attached_file'");
                            if ($id) {
                                echo $file_path . ' already exists in media, add to product... <br>';
                                $is_existing_file = true;
                                array_push($attachments, (int)$id);
                                continue;
                            }
                        }
                        if ($priority_version < 21.0 && $is_uri) {
                            $attach_id = download_attachment($sku . $attachment['EXTFILEDES'], $product_full_url);

                        } else {
                            echo 'File ' . $file_path . ' not exsits, downloading from ' . $images_url, '<br>';
                            $file = $this->save_uri_as_image($product_full_url, $sku . $attachment['EXTFILEDES']);
                            $attach_id = $file[0];
                            // $file_name = $file[1];
                        }
                        if ($attach_id == null) {
                            continue;
                        }
                        if ($attach_id != 0) {
                            array_push($attachments, (int)$attach_id);
                        }


                    }
                };
            }
            else{
                $response_gallery = $this->makeRequest('GET', 'LOGPART?$filter=PARTNAME eq \'' . $search_by_value . '\' &$select=' . $search_field_select . '&$expand=PARTEXTFILE_SUBFORM($select=EXTFILENAME,EXTFILEDES,SUFFIX;$filter=ITAI_ADDKATALOG eq \'Y\' and (SUFFIX eq \'png\' or SUFFIX eq \'jpeg\' or SUFFIX eq \'jpg\'))', [], $this->option('log_attachments_priority', true));
                $data_gallery = json_decode($response_gallery['body']);
                $data_gallery_item = $data_gallery->value[0];

                foreach ($data_gallery_item->PARTEXTFILE_SUBFORM as $attachment) {
                    $file_path = $attachment->EXTFILENAME;
                    $is_uri = strpos('1' . $file_path, 'http') ? false : true;
                    if (!empty($file_path)) {
                        $file_ext = $attachment->SUFFIX;
                        $images_url = 'https://' . $this->option('url') . '/zoom/primail';
                        $image_base_url = $config->image_base_url;
                        if (!empty($image_base_url)) {
                            $images_url = $image_base_url;
                        }
                        $priority_image_path = $file_path;
                        $product_full_url = str_replace('../../system/mail', $images_url, $priority_image_path);
                        $product_full_url = str_replace(' ', '%20', $product_full_url);
                        $product_full_url = str_replace('‏‏', '%E2%80%8F%E2%80%8F', $product_full_url);
                        
                        $ar = explode(',', $product_full_url);
                        $image_data = $ar[0]; //data:image/jpeg;base64
                        $file_type = explode(';', explode(':', $image_data)[1])[0]; //image/jpeg
                        $extension = explode('/', $file_type)[1];  //jpeg
                        
                        $file_n = 'simplyCT/' . $sku . $attachment->EXTFILEDES . '.' . $file_ext; //simplyCT/0523805238-5.jpg
                        $file_n2 = 'simplyCT/' . $sku . $attachment->EXTFILEDES . '.' . $extension; //simplyCT/0523805238-5.jpeg
                        $file_name = $attachment->EXTFILEDES . '.' . $file_ext; //05238-5.jpg

                        $upload_path = wp_get_upload_dir()['basedir'] . '/' . $file_n; 
                        $upload_path_2 = wp_get_upload_dir()['basedir'] . '/' . $file_n2;
                        if ( ! function_exists( 'wp_crop_image' ) ) {
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                        }
                        // check if the item exists in media
                        //in the past we uploaded image like this: 05238-5.jpg
                        $id = $this->simply_check_file_exists($file_name);
                        global $wpdb;
                        $id = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_name' AND meta_key = '_wp_attached_file'" );
                        if($id){
                            echo $file_path . ' already exists in media, add to product... <br>';
                            array_push( $attachments,  (int)$id );
                            continue;
                        }
                        elseif (file_exists($upload_path) == true) {
                            global $wpdb;
                            $id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_n' AND meta_key = '_wp_attached_file'");
                            if ($id) {

                                // Generate the metadata for the attachment, and update the database record.
                                $attach_data = wp_generate_attachment_metadata( $id, $upload_path);
                                wp_update_attachment_metadata( $id, $attach_data );

                                echo $file_path . ' already exists in media, add to product... <br>';
                                $is_existing_file = true;
                                array_push($attachments, (int)$id);
                                continue;
                            }
                        }
                        elseif(file_exists($upload_path_2) == true){
                            global $wpdb;
                            $id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_value like  '%$file_n2' AND meta_key = '_wp_attached_file'");
                            if ($id) {

                                // Generate the metadata for the attachment, and update the database record.
                                $attach_data = wp_generate_attachment_metadata( $id, $upload_path_2);
                                wp_update_attachment_metadata( $id, $attach_data );

                                echo $file_path . ' already exists in media, add to product... <br>';
                                $is_existing_file = true;
                                array_push($attachments, (int)$id);
                                continue;
                            }

                        }
                        else {
                            echo 'File ' . $file_path . ' not exsits, downloading from ' . $images_url, '<br>';
                            $file = $this->save_uri_as_image($product_full_url, $sku . $attachment->EXTFILEDES);
                            $attach_id = $file[0];
                    
                            // $file_name = $file[1];
                        }
                        if ($attach_id == null) {
                            continue;
                        }
                        if ($attach_id != 0) {
                            array_push($attachments, (int)$attach_id);
                        }

                    }
                }
            }

            //  add here merge to files that exists in wp and not exists in the response from API
            $image_id_array = array_merge($product_media, $attachments);
            // https://stackoverflow.com/questions/43521429/add-multiple-images-to-woocommerce-product
            //update_post_meta($product_id, '_product_image_gallery',$image_id_array); not correct can not pass array
            update_post_meta($product_id, '_product_image_gallery', implode(',', $image_id_array));
        }
        $output_string = ob_get_contents();
        ob_end_clean();
        return $output_string;

    }
    public function syncItemsPriorityVariation()
    {
        $priority_version = (float)$this->option('priority-version');
        // config
        $raw_option = $this->option('sync_items_priority_config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
	    $product_price_list = (!empty($config->product_price_list) ? $config->product_price_list : null);
	    $product_price_sale = (!empty($config->product_price_sale) ? $config->product_price_sale : null);
        $is_load_image = (!empty($config->is_load_image) ? true : false);
        $search_field = (!empty($config->search_by) ? $config->search_by : 'PARTNAME');
        $is_categories = (!empty($config->categories) ? $config->categories : null);
        $show_in_web = (!empty($config->show_in_web) ? $config->show_in_web : 'SHOWINWEB');
        $is_update_products  = ( ! empty( $config->is_update_products ) ? $config->is_update_products : false );
        $image_base_url = $config->image_base_url;
        $res = $this->option('sync_variations_priority_config');
        $res = str_replace(array('.', "\n", "\t", "\r"), '', $res);
        $config_v = json_decode(stripslashes($res));
        $show_in_web = (!empty($config_v->show_in_web) ? $config_v->show_in_web :  $show_in_web);
        $is_update_products = !empty($config_v->is_update_products) ? $config_v->is_update_products : $is_update_products;
        $show_front = !empty($config_v->show_front) ? $config_v->show_front : null;
        $daysback = !empty((int)$config_v->days_back) ? $config_v->days_back : (!empty((int)$config->days_back) ? $config->days_back : 1);
        $stamp = mktime(0 - $daysback * 24, 0, 0);
        $bod = date(DATE_ATOM, $stamp);
        $url_addition = 'UDATE ge ' . $bod;
        $variation_field       = $this->option('variation_field') =='true' ? $this->option('variation_field') : 'MPARTNAME';
        $variation_field_title = $this->option('variation_field_title') == 'true' ? $this->option('variation_field_title') : 'MPARTDES';
        $data['select'] = 'PARTNAME,PARTDES,BASEPLPRICE,VATPRICE,STATDES,SHOWINWEB,SPEC1,SPEC2,SPEC3,
        SPEC4,SPEC5,SPEC6,SPEC7,SPEC8,SPEC9,SPEC10,SPEC11,SPEC12,SPEC13,SPEC14,SPEC15,SPEC16,SPEC17,SPEC18,SPEC19,SPEC20,INVFLAG,ISMPART,MPARTNAME,MPARTDES,FAMILYDES';
        if ($priority_version < 21.0) {
            $data['select'] .= 'EXTFILENAME';
        }
        $data['expand'] = '$expand=PARTUNSPECS_SUBFORM';
        $data = apply_filters('simply_syncItemsPriority_data', $data);
        $url_addition_config = (!empty($config_v->additional_url) ? $config_v->additional_url : '');
        $filter = $variation_field . ' ne \'\' and ' . urlencode($url_addition) . ' ' . $url_addition_config;
        $response = $this->makeRequest('GET',
            'LOGPART?$select=' . $data['select'] . '&$filter=' . $filter . '&'.$data['expand'],
            [], $this->option('log_items_priority_variation', true));
        // check response status
        if ($response['status']) {
            $response_data = json_decode($response['body_raw'], true);
            $product_cross_sells = [];
            $parents = [];
            $childrens = [];
            if ($response_data['value'][0] > 0) {
                foreach ($response_data['value'] as $item) {
                    // check if variation show be on web
	                if($item[$show_in_web] != 'Y'){
		                $variation_sku = $item[$search_field];
                        // Get the variation object
		                $variation = wc_get_product_id_by_sku($variation_sku);
		                if (!$variation) {
			                continue;
		                }
	                }
                    if ($item[$variation_field] !== '-') {
                        $search_by_value = (string)$item[$search_field];
                        $attributes = [];
                        if ($item['PARTUNSPECS_SUBFORM']) {
                            foreach ($item['PARTUNSPECS_SUBFORM'] as $attr) {
                                $attribute = $attr['SPECDES'];
                                $attributes[$attribute] = $attr['VALUE'];
                            }
                        }
	                    $item['attributes'] = $attributes;
                        $item = apply_filters('simply_ItemsAtrrVariation', $item);
                        $attributes = $item['attributes'];

                        //check if WooCommerce Tax Settings are set
                        $set_tax = get_option('woocommerce_calc_taxes');
                        if ($attributes) {
                            $price = (wc_prices_include_tax() == true || $set_tax == 'no') ? $item['VATPRICE'] : $item['BASEPLPRICE'];
                            // price refer to pricelist
	                        if ($product_price_list != null && !empty($item['PARTINCUSTPLISTS_SUBFORM'])) {
		                        $price = (wc_prices_include_tax() == true || $set_tax == 'no') ? $item['PARTINCUSTPLISTS_SUBFORM'][0]['VATPRICE'] : $item['PARTINCUSTPLISTS_SUBFORM'][0]['PRICE'];

	                        } else {
		                        $price = (wc_prices_include_tax() == true || $set_tax == 'no') ? $item['VATPRICE'] : $item['BASEPLPRICE'];
	                        }
                            if (isset($parents[$item[$variation_field]]['content'])) {
                                $parents[$item[$variation_field]]['content'] = '';
                            }
                            if (isset($item['PARTTEXT_SUBFORM'])) {
                                foreach ($item['PARTTEXT_SUBFORM'] as $text) {
                                    $clean_text = preg_replace('/<style>.*?<\/style>/s', '', $text);
                                    $parents[$item[$variation_field]]['content'] .= $clean_text;
                                }
                            }
                            $parents[$item[$variation_field]] = [
                                'sku' => $item[$variation_field],
                                //'crosssell' => $item['ROYL_SPECDES1'],
                                'title' => $item[$variation_field_title],
                                'stock' => 'Y',
                                'variation' => [],
                                'regular_price' => $price,
                                'post_content' => $parents[$item[$variation_field]]['content']
                                //isset($item['PARTTEXT_SUBFORM']['TEXT']) && !empty($item['PARTTEXT_SUBFORM']['TEXT']) ? $item['PARTTEXT_SUBFORM']['TEXT'] : $parents[$item[$variation_field]]['post_content']
                            ];

//                            if (isset($item['PARTTEXT_SUBFORM']['TEXT'])&&!empty($item['PARTTEXT_SUBFORM']['TEXT'])) {
//
//                            }

                            if ($priority_version >= 21.0 && true == $is_load_image) {
                                $response = $this->makeRequest('GET', 'LOGPART?$select=EXTFILENAME&$filter=PARTNAME eq \'' . $search_by_value . '\'', [], $this->option('log_items_priority', true));
                                $data = json_decode($response['body']);
                                $item['EXTFILENAME'] = $data->value[0]->EXTFILENAME;
                            }
                            if (!empty($show_in_web)) {
                                $parents[$item[$variation_field]][$show_in_web] = $item[$show_in_web];
                            }
                            $childrens[$item[$variation_field]][$search_by_value] = [
                                'sku' => $search_by_value,
                                'regular_price' => $price,
                                'stock' => $item['INVFLAG'],
                                'parent_title' => $item['MPARTDES'],
                                'title' => $item['PARTDES'],
                                'stock' => ($item['INVFLAG'] == 'Y') ? 'instock' : 'outofstock',
                                'image' => $item['EXTFILENAME'],
                                'categories' => [
                                    $item[$is_categories]
                                ],
                                'attributes' => $attributes,
                                'show_in_web' => $item[$show_in_web]

                            ];
                            /*
	                        if ($config->sync_price != "true") {
		                        $childrens[$item[$variation_field]][$search_by_value]['regular_price']= $price;
	                        }
                            */
                            if ($show_front != null) {
                                $childrens[$item[$variation_field]][$search_by_value]['show_front'] = $item[$show_front];
                            }
                        }
                    }
                }
                foreach ($parents as $partname => $value) {
                    if (count($childrens[$partname])) {
                        $parents[$partname]['categories'] = end($childrens[$partname])['categories'];
                        $parents[$partname]['tags'] = end($childrens[$partname])['tags'];
                        $parents[$partname]['variation'] = $childrens[$partname];
                        $parents[$partname]['title'] = $parents[$partname]['title'];
                        // $parents[$partname]['post_content'] = $parents[$partname]['post_content'];
                        foreach ($childrens[$partname] as $children) {
                            foreach ($children['attributes'] as $attribute => $attribute_value) {
                                if ($attributes) {
                                    if (!empty($parents[$partname]['attributes'][$attribute])) {
                                        if (!in_array($attribute_value, $parents[$partname]['attributes'][$attribute]))
                                            $parents[$partname]['attributes'][$attribute][] = $attribute_value;
                                    } else {
                                        $parents[$partname]['attributes'][$attribute][] = $attribute_value;
                                    }
                                }
                            }
                        }
                        $product_cross_sells[$value['cross_sells']][] = $partname;
                    } else {
                        unset($parents[$partname]);
                    }
                }
                if ($parents) {
                    foreach ($parents as $sku_parent => $parent) {
                        if (true == $is_load_image) {
                            $file_parent = $this->load_image('', $image_base_url, $priority_version, $sku_parent, $search_field);
                            $attach_id_parent = $file_parent[0];
                            $file_name_parent = $file_parent[1];
                        }
                        $parent_data = apply_filters('simply_modify_product_variable', ['sku' => $sku_parent, 'text' => '']);

                        $id = create_product_variable(array(
                            'author' => '', // optional
                            'title' => $parent['title'],
                            'content' => $parent_data['text'] != '' ? $parent_data['text'] : $parent['post_content'],                            'excerpt' => '',
                            'regular_price' => '', // product regular price
                            'sale_price' => '', // product sale price (optional)
                            'stock' => $parent['stock'], // Set a minimal stock quantity
                            'image_id' => (!empty($attach_id_parent) && $attach_id_parent != 0) ? $attach_id_parent : '', // optional
                            'image_file' => (!empty($file_name_parent)) ? $file_name_parent : '', // optional
                            'gallery_ids' => array(), // optional
                            'sku' => $sku_parent, // optional
                            'tax_class' => '', // optional
                            'weight' => '', // optional
                            // For NEW attributes/values use NAMES (not slugs)
                            'attributes' => $parent['attributes'],
                            'categories' => $parent['categories'],
                            'tags' => $parent['tags'],
                            'status' => $this->option('item_status'),
                            'show_in_web' => $parent_data['show_in_web'] != '' ? $parent_data['show_in_web'] : $parent['show_in_web'],
                            'is_update_products' => $is_update_products,
                            'shipping' => $parent_data['shipping'] != '' ? $parent_data['shipping'] : ''
                        ));

                        $parents[$sku_parent]['product_id'] = $id;
                        foreach ($parent['variation'] as $sku_children => $children) {
                            // The variation data
                            //sync image
                            if (true == $is_load_image) {
                                $file = $this->load_image('', $image_base_url, $priority_version, $sku_children, $search_field);
                                $attach_id = $file[0];
                                $file_name = $file[1];
                            }
                            $variation_data = array(
                                'attributes' => $children['attributes'],
                                'sku' => $sku_children,
                                'regular_price' => !empty($children['regular_price']) ? ($children['regular_price']) : $parent[$sku_children]['regular_price'],
                                'product_code' => $children['product_code'],
                                'sale_price' => '',
                                'content' => $children['content'],
                                'stock' => $children['stock'],
                                'image_id' => (!empty($attach_id) && $attach_id != 0) ? $attach_id : '', // optional
                                'image_file' => (!empty($file_name)) ? $file_name : '', // optional
                                'show_front' => $children['show_front'],
                                'show_in_web' => $children['show_in_web'],
                                'is_update_products' => $is_update_products,
                            );
                            // The function to be run
                            create_product_variation($id, $variation_data);
                        }
                        unset($parents[$sku_parent]['variation']);
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

                        if ($cross_sells_old = get_post_meta($parent['product_id'], '_crosssell_ids', true)) {
                            foreach ($cross_sells_old as $value)
                                if (!is_array($value)) $cross_sells_merge_array[] = $value;
                        }

                        $cross_sells = array_unique(array_filter(array_merge($cross_sells, $cross_sells_merge_array)));

                        /**
                         * end t205
                         */

                        update_post_meta($parent['product_id'], '_crosssell_ids', $cross_sells);
                    }
                }
            }
            // add timestamp
            $this->updateOption('items_priority_variation_update', time());
        } else {
            $this->sendEmailError(
                $this->option('email_error_sync_items_priority_variation'),
                'Error Sync Items Priority Variation',
                $response['body']
            );
            exit(json_encode(['status' => 0, 'msg' => 'Error Sync Items Priority Variation']));
        }
    }

    public
    function syncCustomerProducts()

    {


        $url_addition = '?$filter=CUSTPART eq \'Y\' &$select=CUSTNAME,MCUSTNAME ';

        $url_addition .= '&$expand=CUSTPART_SUBFORM($filter=NOTVALID ne \'Y\' ;)';


        $response = $this->makeRequest('GET', 'CUSTOMERS' . $url_addition,

            [], $this->option('log_sites_priority', true));

// check response status
        if ($response['status']) {

            // create the table

            $table = $GLOBALS['wpdb']->prefix . 'p18a_customersparts';

            $blog_id = get_current_blog_id();

            $GLOBALS['wpdb']->query('DELETE FROM ' . $table);

            $data = json_decode($response['body_raw'], true);
            if (isset($data['value'])) {

                foreach ($data['value'] as $list) {

                    $sub_form = 'CUSTPART_SUBFORM';

                    if (!empty($list['MCUSTNAME'])) {

                        $sub_form = 'ROYY_CUSTPART_SUBFORM';

                    }

                    if (isset($list[$sub_form])) {

                        {

                            foreach ($list[$sub_form] as $item) {

                                echo $GLOBALS['wpdb']->insert($table, [
                                    'blog_id' => $blog_id,
                                    'custname' => $list['CUSTNAME'],
                                    'partname' => $item['PARTNAME'],
                                    'custpartname' => $item['CUSTPARTNAME']

                                ]);

                            }

                        }

                    }

                }

                $this->updateOption('pricelist_priority_update', time());

            }

        } else {

            $this->sendEmailError(

                $this->option('email_error_sync_pricelist_priority'),

                'Error Sync Price Lists Priority',

                $response['body']

            );

        }

    }

    /**
     * sync items from web to priority
     *
     */
    public
    function syncItemsWeb()
    {
        $single_sku = explode(',', $this->option('sync_items_web'))[0];
        $daysback_options = explode(',', $this->option('sync_items_web'))[1];
        $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
        $stamp = mktime(1 - ($daysback * 24), 0, 0);
        $url_addition = '';
        $SKU = [];
        if (!empty($single_sku)) {
            $url_addition = '&$filter=PARTNAME eq \'' . $single_sku . '\'';
        }
        // get all items from priority
        $response = $this->makeRequest('GET', 'LOGPART?$select=PARTNAME' . $url_addition);
        if (!$response['status']) {
            $this->sendEmailError(
                $this->option('email_error_sync_items_web'),
                'Error Sync Items Web',
                $response['body']
            );
        } else {
            $data = json_decode($response['body_raw'], true);
            // Priority items SKU numbers
            // collect all SKU numbers
            foreach ($data['value'] as $item) {
                $SKU[] = $item['PARTNAME'];
            }
        }
        // get single product according to option
        if (!empty($single_sku)) {
            $args = [
                'post_type' => array('product', 'product_variation'),
                'post_status' => array('publish'),
                'meta_query' => array(
                    array(
                        'key' => '_sku',
                        'value' => $single_sku
                    )
                )
            ];
        } else {
            $year = date('Y', $stamp);
            $month = date('m', $stamp);
            $day = date('d', $stamp);
            $args = ['post_type' => array('product', 'product_variation'),
                'post_status' => array('publish'),
                'posts_per_page' => -1,
                'date_query' => [
                    'column' => 'post_modified',
                    'after' => [
                        'year' => $year,
                        'month' => $month,
                        'day' => $day,
                    ],]];
        }
        $products = get_posts($args);
        $requests = [];
        $json_requests = [];
        // loop trough products
        foreach ($products as $product) {

            $meta = get_post_meta($product->ID);
            $method = in_array($meta['_sku'][0], $SKU) ? 'PATCH' : 'POST';
            $terms = get_the_terms(($product->post_type == 'product_variation' ? $product->post_parent : $product->ID), 'product_cat');
            foreach ($terms as $term) {
                $cat_id = $term->term_id;
            }
            // Unused code that overrides the function
//            $attr = get_the_terms ( ($product->post_type == 'product_variation' ? $product->post_parent : $product->ID), 'pa_size' );
//            $size = $attr[0]->name;
//
//            $attributes = wc_get_product($product->ID)->get_attributes();
//            $product_attr = get_post_meta($product->ID, '_product_attributes' );
//            foreach ($product_attr as $attr) {
//                foreach ($attr as $attribute) {
//                    $attrnames = str_replace("pa_", "", $attribute['name']);
//                }
//            }
            $product_item = wc_get_product($product->ID);
            $body = [
                'PARTNAME' => $meta['_sku'][0],
                'PARTDES' => $product->post_title,
                'BASEPLPRICE' => (float)$meta['_regular_price'][0],
                'INVFLAG' => ($meta['_manage_stock'][0] == 'yes') ? 'Y' : 'N',
                'EXTFILENAME' => !empty(wp_get_attachment_url($product_item->get_image_id())) ? wp_get_attachment_url($product_item->get_image_id()) : '',
                'SPEC1' => $terms[0]->name
            ];
            // here I need to apply filter to manipulate the json
            $body['product'] = $product;
            $body = apply_filters('simply_sync_items_to_priority', $body);
            unset($body['product']);
            if ($method == "PATCH") {
                if ($single_sku == "") {
                    $sku = $meta['_sku'][0];
                } else {
                    $sku = $single_sku;
                }
                $url = "LOGPART('$sku')";
                unset($body ['PARTNAME']);
            } else if ($method == "POST") {
                if (empty($body['PARTNAME']))
                    continue;
                $url = "LOGPART";

            }
            $res = $this->makeRequest($method, $url, ['body' => json_encode($body)], $this->option('log_items_web', true));
            if (!$res['status']) {
                $this->sendEmailError(
                    $this->option('email_error_sync_items_web'),
                    'Error Sync Items Web',
                    $res['body']
                );
            }
        }

        // add timestamp
        $this->updateOption('items_web_update', time());


    }

    public
    function get_items_total_by_status($product_id)
    {

        //$statuses = ['on-hold','pending'];
        $statuses = explode(',', $this->option('sync_inventory_warhsname'))[4];
        // Get 'on-hold' customer ORDERS
        $orders_by_status = wc_get_orders(array(
            'limit' => -1,
            'status' => $statuses,
        ));

        $qty = 0;
        foreach ($orders_by_status as $order) {
            foreach ($order->get_items() as $item_id => $item) {
                $order_item_product_id = $item['product_id'];
                if ($order_item_product_id == $product_id) {
                    $qty += $item['qty'];
                }

            }
        }
        return $qty;

    }

    /**
     * sync inventory from priority
     */
    public
    function syncInventoryPriority()
    {

        // get the items simply by time stamp of today
        $daysback_options = explode(',', $this->option('sync_inventory_warhsname'))[3];
        $daysback = intval(!empty($daysback_options) ? $daysback_options : 1); // change days back to get inventory of prev days
        $stamp = mktime(1 - ($daysback * 24), 0, 0);
        $bod = date(DATE_ATOM, $stamp);
        $url_addition = '('. rawurlencode('WARHSTRANSDATE ge ' . $bod . ' or PURTRANSDATE ge ' . $bod . ' or SALETRANSDATE ge ' . $bod) . ')';
        $url_addition = apply_filters('simply_syncInventoryPriority_filter_addition', $url_addition);
        if ($this->option('variation_field')) {
            //  $url_addition .= ' and ' . $this->option( 'variation_field' ) . ' eq \'\' ';
        }
        $option_filed = explode(',', $this->option('sync_inventory_warhsname'))[2];
        $data['select'] = (!empty($option_filed) ? $option_filed . ',PARTNAME' : 'PARTNAME');

        $wh_name = explode(',', $this->option('sync_inventory_warhsname'))[0];
        $status = explode(',', $this->option('sync_inventory_warhsname'))[4];
        if (!empty($wh_name)) {
            if (!empty($status)) {
                $expand = '$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM($filter=WARHSNAME eq \'' . $wh_name . '\' and CUSTNAME eq \'' . $status . '\')';

            } else {
                $expand = '$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM($filter=WARHSNAME eq \'' . $wh_name . '\')';
            }
        } else if (!empty($status)) {
            $expand = '$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM($filter=CUSTNAME eq \'' . $status . '\')';
        } else {
            $expand = '$expand=LOGCOUNTERS_SUBFORM,PARTBALANCE_SUBFORM';
        }
        $data['expand'] = $expand;
	    $data = apply_filters('simply_syncInventoryPriority_data', $data);
        $response = $this->makeRequest('GET', 'LOGPART?$select='.$data['select'].'&$filter='.$url_addition.' and INVFLAG eq \'Y\' &' . $data['expand'], [], $this->option('log_inventory_priority', false));
        // check response status        // check response status
        if ($response['status']) {
            $data = json_decode($response['body_raw'], true);
            foreach ($data['value'] as $item) {
                // if product exsits, update
                $field = (!empty($option_filed) ? $option_filed : 'PARTNAME');
                $args = array(
                    'post_type' => array('product', 'product_variation'),
                    'meta_query' => array(
                        array(
                            'key' => '_sku',
                            'value' => $item[$field]
                        )
                    )
                );
                $my_query = new \WP_Query($args);
                if ($my_query->have_posts()) {
                    while ($my_query->have_posts()) {
                        $my_query->the_post();
                        $product_id = get_the_ID();
                    }
                } else {
                    $product_id = 0;
                }
                //if ($id = wc_get_product_id_by_sku($item['PARTNAME'])) {
                if (!$product_id == 0) {
                    // update_post_meta($product_id, '_sku', $item['PARTNAME']);
                    // get the stock by part availability
                    $stock = $item['LOGCOUNTERS_SUBFORM'][0]['DIFF'];

                    // get the stock by specific warehouse
                    $wh_name = explode(',', $this->option('sync_inventory_warhsname'))[0];
                    if (!empty($wh_name)) {
                        $stock = 0;
                    }
                    $foo = $this->option('sync_inventory_warhsname');
                    $foo2 = explode(',', $this->option('sync_inventory_warhsname'))[1];
                    $is_deduct_order = explode(',', $this->option('sync_inventory_warhsname'))[1] == 'ORDER';
                    $orders = $item['LOGCOUNTERS_SUBFORM'][0]['ORDERS'];
                    if(!empty($wh_name)) {
                        foreach ($item['PARTBALANCE_SUBFORM'] as $wh_stock) {
                            $stock += $wh_stock['TBALANCE'] > 0 ? $wh_stock['TBALANCE'] : 0; // stock
                        }
                    }
                    if ($is_deduct_order) {
                        $stock = $stock - $orders > 0 ? $stock - $orders : 0; // stock - orders
                    }
                    $statuses = explode(',', $this->option('sync_inventory_warhsname'))[4];
                    if (!empty($statuses)) {
                        $stock -= $this->get_items_total_by_status($product_id);
                        $item['order_status_qty'] = $this->get_items_total_by_status($product_id);
                    }
                    if($item['PARTNAME']=='8511'){
                        $foo = 'haaa';
                    }
                    $item['stock'] = $stock;
                    $item = apply_filters('simply_sync_inventory_priority', $item);
                    $stock = $item['stock'];
                    update_post_meta($product_id, '_stock', $stock);
                    // set stock status
                    if (intval($stock) > 0) {
                        // update_post_meta($product_id, '_stock_status', 'instock');
                        $stock_status = 'instock';
                    } else {
                        // update_post_meta($product_id, '_stock_status', 'outofstock');
                        $stock_status = 'outofstock';
                    }
                    //$variation = wc_get_product($product_id);
                    //$variation->set_stock_status($stock_status);
                    $product = wc_get_product($product_id);
                    if ($product->post_type == 'product_variation') {
                        $var = new \WC_Product_Variation($product_id);
                        $var->set_stock_status($stock_status);
                        $var->set_manage_stock(true);
                        $var->save();
                    }
                    if ($product->post_type == 'product') {
                        $product->set_stock_status($stock_status);
                        $product->set_manage_stock(true);
                    }
                    $product->save();
                }
                // add filter here
                if (function_exists('simply_code_after_sync_inventory'))
                {
                    simply_code_after_sync_inventory($product_id,$item);
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

    public
    function syncPacksPriority()
    {
        // get the items simply by time stamp of today
        $stamp = mktime(0, 0, 0);
        $bod = date(DATE_ATOM, $stamp);
        $list_packs = [];
        $url_addition = 'PARTPACKONE?$select=PACKCODE,PARTNAME,PACKNAME,PACKQUANT';
        $response = $this->makeRequest('GET', $url_addition, [], true);
        // check response status
        if ($response['status']) {
            $data = json_decode($response['body_raw'], true);
            foreach ($data['value'] as $item) {
                // if product exsits, update
                $args = array(
                    'post_type' => 'product',
                    'meta_query' => array(
                        array(
                            'key' => '_sku',
                            'value' => $item['PARTNAME']
                        )
                    )
                );
                $my_query = new \WP_Query($args);
                if ($my_query->have_posts()) {
                    while ($my_query->have_posts()) {
                        $my_query->the_post();
                        $product_id = get_the_ID();
                    }
                } else {
                    $product_id = 0;
                }

                //if ($id = wc_get_product_id_by_sku($item['PARTNAME'])) {
                if (!$product_id == 0) {
                    if ($list_packs[$product_id] != null) {
                        $i = count($list_packs[$product_id]);
                        $list_packs[$product_id][$i] = $item;
                    } else {
                        $list_packs[$product_id][0] = $item;
                    }
                }
            }
            foreach ($list_packs as $id => $p) {

                update_post_meta($id, 'pri_packs', $p);
            }
        }
    }

    /**
     * sync Customer by given ID
     *
     * @param [int] $id
     */
    public
    function syncCustomer($order)
    {
        $id = $order->get_user_id();
        if (null == $this->option('post_customers')) {
            $priority_customer_number = $this->option('walkin_number');
            $response['priority_customer_number'] = $priority_customer_number;
            $response['message'] = 'this is a walk in number';
            return $response;
        }
        // check user
        if ($user = get_userdata($id)) {
            $meta = get_user_meta($id);
            // if already assigned value it is stronger
            $priority_cust_from_wc = get_user_meta($id, 'priority_customer_number', true);
            // search customer number in Priority
            if (empty($priority_cust_from_wc)) {
                $custname = apply_filters('simply_search_customer_in_priority', ['user_id' => $id, 'order' => $order])['CUSTNAME'];
                if (!empty($custname)) {
                    update_user_meta($id, 'priority_customer_number', $custname);
                    $body = ['CUSTNAME' => $custname];
                    $response['body'] = json_encode($body);
                    return $response;
                }
            }
            if (!empty($custname)) {
                $priority_cust_from_wc = $custname;
            }
            if (!empty($priority_cust_from_wc)) {
                $priority_customer_number = $priority_cust_from_wc;
            } else {
                $priority_customer_number = 'WEB-' . (string)$user->data->ID;
                /* you can post the user by email or phone. this code executed before WP assign email or phone to user, and sometimes no phone on registration */
                if ('prospect_email' == $this->option('prospect_field')) {
                    $priority_customer_number = $user->data->user_email;
                    if (null == $priority_customer_number) {
                        return;
                    }
                }
                if ('prospect_cellphone' == $this->option('prospect_field')) {
                    $priority_customer_number = $meta['billing_phone'][0];
                    if (null == $priority_customer_number) {
                        return;
                    }
                }
            }

            $custdes = !empty($meta['billing_company'][0]) ? $meta['billing_company'][0] : $meta['first_name'][0] . ' ' . $meta['last_name'][0];
            $custdes = apply_filters('simply_syncCustdes', $custdes, $meta );
            
            $request = [
                'CUSTNAME' => $priority_customer_number,
                // 'CUSTDES' => empty($meta['first_name'][0]) ? $meta['nickname'][0] : $custdes,
                'CUSTDES' => !empty($custdes) ? $custdes : $meta['nickname'][0],
                'EMAIL' => $user->data->user_email,
                'ADDRESS' => isset($meta['billing_address_1']) ? $meta['billing_address_1'][0] : '',
                'ADDRESS2' => isset($meta['billing_address_2']) ? $meta['billing_address_2'][0] : '',
                'STATEA' => isset($meta['billing_city']) ? $meta['billing_city'][0] : '',
                'ZIP' => isset($meta['billing_postcode']) ? $meta['billing_postcode'][0] : '',
                //   'COUNTRYNAME' => isset($meta['billing_country']) ? $this->countries[$meta['billing_country'][0]] : '',
                'PHONE' => isset($meta['billing_phone']) ? $meta['billing_phone'][0] : '',
                'EDOCUMENTS' => 'Y',
                'NSFLAG' => 'Y',
            ];
            $method = !empty($priority_cust_from_wc) ? 'PATCH' : 'POST';
            $url_eddition = 'CUSTOMERS';
            if ($method == 'PATCH') {
                $url_eddition = 'CUSTOMERS(\'' . $priority_customer_number . '\')';
                unset($request['CUSTNAME']);
                $config = json_decode(stripslashes($this->option('setting-config')));
                 $no_update_customer = $config->no_update_customer;
                if ($no_update_customer === 'true') {
                    $response['code'] == '200';
                    return $response;
                }
            }
            $request["id"] = $id;
            $request = apply_filters('simply_syncCustomer', $request);
            unset($request["id"]);
            $json_request = json_encode($request);
            $response = $this->makeRequest($method, $url_eddition, ['body' => $json_request], true);
            if ($method == 'POST' && $response['code'] == '201' || $method == 'PATCH' && $response['code'] == '200') {
                $data = json_decode($response['body']);
                $priority_customer_number = $data->CUSTNAME;
                update_user_meta($id, 'priority_customer_number', $priority_customer_number);
            } // set priority customer id
            else {
                $this->sendEmailError(
                    [$this->option('email_error_sync_customers_web')],
                    'Error Sync Customers',
                    $response['body']
                );
            }
            // add timestamp
            //$this->updateOption('post_customers', time());
        }
        return $response;
    }
    public function syncPriorityOrderStatus()
    {
        // orders
        $url_addition = 'ORDERS?$filter=' . $this->option('order_order_field') . ' ne \'\'  and ';
        $date = date('Y-m-d');
        $prev_date = date(DATE_ATOM, strtotime($date . ' -10 day'));
        $url_addition .= 'CURDATE ge ' . urlencode($prev_date);
        $response = $this->makeRequest('GET', $url_addition, null, true);
        $orders = json_decode($response['body'], true)['value'];
        $output = '';
        foreach ($orders as $el) {
            $order_id = $el[$this->option('order_order_field')];
            $order = wc_get_order($order_id);
            $pri_status = $el['ORDSTATUSDES'];
            if ($order) {
                update_post_meta($order_id, 'priority_order_status', $pri_status);
                $output .= '<br>' . $order_id . ' ' . $pri_status . ' ';
            }
        }
        // invoice
        $url_addition = 'AINVOICES?$filter=' . $this->option('ainvoice_order_field') . ' ne \'\'  and ';
        //$date = date('Y-m-d');
        //$prev_date = date('Y-m-d', strtotime($date . ' -10 day'));
        $url_addition .= 'IVDATE ge ' . urlencode($prev_date);
        $url_addition .= ' and STORNOFLAG ne \'Y\'';

        $response = $this->makeRequest('GET', $url_addition, null, true);
        $orders = json_decode($response['body'], true)['value'];
        $output = '';
        foreach ($orders as $el) {
            $order_id = $el[$this->option('ainvoice_order_field')];
            $ivnum = $el['IVNUM'];
            $order = wc_get_order($order_id);
            $pri_status = $el['STATDES'];
            if ($order) {
                update_post_meta($order_id, 'priority_invoice_status', $pri_status);
                update_post_meta($order_id, 'priority_invoice_number', $ivnum);
                $output .= '<br>' . $order_id . ' ' . $pri_status . ' ';
            }
        }
        // OTC
        $url_addition = 'EINVOICES?$filter=' . $this->option('otc_order_field') . ' ne \'\'  and ';
        //$date = date('Y-m-d');
        //$prev_date = date('Y-m-d', strtotime($date . ' -10 day'));
        $url_addition .= 'IVDATE ge ' . urlencode($prev_date);
        $url_addition .= ' and STORNOFLAG ne \'Y\'';

        $response = $this->makeRequest('GET', $url_addition, null, true);
        $orders = json_decode($response['body'], true)['value'];
        $output = '';
        foreach ($orders as $el) {
            $order_id = $el[$this->option('otc_order_field')];
            $ivnum = $el['IVNUM'];
            $order = wc_get_order($order_id);
            $pri_status = $el['STATDES'];
            if ($order) {
                update_post_meta($order_id, 'priority_invoice_status', $pri_status);
                update_post_meta($order_id, 'priority_invoice_number', $ivnum);
                $output .= '<br>' . $order_id . ' ' . $pri_status . ' ';
            }
        }
        // recipe
        $url_addition = 'TINVOICES?$filter=' . $this->option('receipt_order_field') . ' ne \'\'  and ';
       // $date = date('Y-m-d');
        //$prev_date = date('Y-m-d', strtotime($date . ' -10 day'));
        $url_addition .= 'IVDATE ge ' . urlencode($prev_date);
        $url_addition .= ' and STORNOFLAG ne \'Y\'';

        $response = $this->makeRequest('GET', $url_addition, null, true);
        $orders = json_decode($response['body'], true)['value'];
        $output = '';
        foreach ($orders as $el) {
            $order_id = $el[$this->option('receipt_order_field')];
            $order = wc_get_order($order_id);
            $ivnum = $el['IVNUM'];
            $pri_status = $el['STATDES'];
            if ($order) {
                update_post_meta($order_id, 'priority_recipe_status', $pri_status);
                update_post_meta($order_id, 'priority_recipe_number', $ivnum);
                $output .= '<br>' . $order_id . ' ' . $pri_status . ' ';
            }
        }
        // end
        $this->updateOption('auto_sync_order_status_priority_update', time());
    }
    public function getPriorityCustomer(&$order)
    {
        $order_id = $order->get_id();
        $user_id = $order->get_user_id();
        if ($user_id == 0) {
            /*  לעשות קוד קאסטום שבודק מול שליפה מפרירויטי ואם מצא אז לא ממשיך */
            $custname = apply_filters('simply_search_customer_in_priority', ['order' => $order,
                'CUSTNAME' => null])['CUSTNAME'];
            if (!empty($custname)) {
                $body = ['CUSTNAME' => $custname];
                $response['body'] = json_encode($body);
                update_post_meta($order_id, 'prospect_custname', $custname);
                return $response;
            }
            $response = $this->syncProspect($order);
        } else {
            $response = $this->syncCustomer($order);
        }
        return $response;

        get_user_meta($order->get_user_id(), 'priority_customer_number', true);
        if (!empty(get_post_meta($order_id, 'cust_name', true))) {
            $response['args']['body'] = get_post_meta($order_id, 'cust_name', true);
            $response['message'] = "Exists cust_name";
            $response['body'] = "";
            return $response;
        }
        $cust_data = [$order, null, $this];
        $cust_data = apply_filters('simply_modify_customer_number', $cust_data);
        if (!empty($cust_data[1])) {
            $cust_number = $cust_data[1];
            add_post_meta($order_id, 'cust_name', $cust_number);
            $response['args']['body'] = get_post_meta($order_id, 'cust_name', true);
            $response['message'] = "simply_modify_customer_number";
            $response['body'] = "";
            return $response;
        }
        /*  לקוח מזדמן */
        $cust_numbers = explode('|', $this->option('walkin_number'));
        $country = (!empty($order->get_shipping_country()) ? $order->get_shipping_country() : $order->get_billing_country());
        $walk_in_customer = (($country == 'IL' ? $cust_numbers[0] : isset($cust_numbers[1])) ? $cust_numbers[1] : $cust_numbers[0]);
        $walk_in_customer = !empty($walk_in_customer) ? $walk_in_customer : $cust_numbers[0];
        /*   אם מסומן צק בוקס של register customer */
        if ($this->option('post_customers')) {
            if ($order->get_customer_id()) {
                $cust_number = get_user_meta($order->get_customer_id(), 'priority_customer_number', true);
                /* אם אין לו מספר אז תיצור אותו */
                if (empty($cust_number)) {
                    $response = $this->syncCustomer($order);
                    if ($response['code'] == '201') {
                        $cust_number = get_user_meta($order->get_customer_id(), 'priority_customer_number', true);
                        add_post_meta($order_id, 'cust_name', $cust_number);
                        $response['args']['body'] = "Registered Customers";
                        $response['message'] = "add Registered Customers To Priority With cust_name";
                        $response['body'] = get_post_meta($order_id, 'cust_name', true);
                        return $response;
                    } else {
                        $response['args']['body'] = "Eror in created New Registered Customer";
                        $response['message'] = "add Registered Customers To Priority With cust_name";
                        $response['body'] = $response["code"];
                        return $response;
                    }
                } else {
                    add_post_meta($order_id, 'cust_name', $cust_number);
                    $response['args']['body'] = "Registered Customers Exists";
                    $response['message'] = "Registered Customer Exists In Priority With cust_name";
                    $response['body'] = get_post_meta($order_id, 'cust_name', true);
                    return $response;
                }
            }
        }
        /*  אם מסומן צק בוקס של prospect */
        if ($this->option('post_prospect')) {
            $cust_number = $this->syncProspect($order);
            add_post_meta($order_id, 'cust_name', $cust_number);
            $response['args']['body'] = get_post_meta($order_id, 'cust_name', true);
            $response['message'] = "Add Customers";
            $response['body'] = "add Prospect Customers To Priority With cust_name";
            return $response;
        }
        // walk in customer
        update_post_meta($order_id, 'cust_name', $walk_in_customer);
        $response['args']['body'] = get_post_meta($order_id, 'cust_name', true);
        $response['message'] = "walk_in_customer";
        $response['body'] = "";
        return $response;
    }
    public function syncOrders()
    {
        $query = new \WC_Order_Query(array(
            //'limit' => get_option('posts_per_page'),
            'limit' => 1000,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
            'meta_key' => 'priority_order_status', // The postmeta key field
            'meta_compare' => 'NOT EXISTS', // The comparison argument
        ));

        $orders = $query->get_orders();
        foreach ($orders as $id) {
            $order = wc_get_order($id);
            $priority_status = $order->get_meta('priority_order_status');
            if (!$priority_status) {
                $response = $this->syncOrder($id, $this->option('log_auto_post_orders_priority', true));
            }
        };
        $this->updateOption('time_stamp_cron_receipt', time());
    }
    public function syncReceipts()
    {
        $query = new \WC_Order_Query(array(
            //'limit' => get_option('posts_per_page'),
            'limit' => 1000,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
            'meta_key' => 'priority_recipe_status', // The postmeta key field
            'meta_compare' => 'NOT EXISTS', // The comparison argument
        ));

        $orders = $query->get_orders();
        foreach ($orders as $id) {
            $order = wc_get_order($id);
            $priority_status = $order->get_meta('priority_recipe_status');
            if (!$priority_status) {
                $response = $this->syncReceipt($id);
            }
        };
        $this->updateOption('time_stamp_cron_order', time());
    }
    public function syncAinvoices()
    {
	    $args = array(
		    'post_type' => 'shop_order',
		    'meta_query' => array(
			    // Your meta query conditions
		    ),
		    'context' => 'specific_order_query'
	    );
	    $orders = wc_get_orders($args);
        foreach ($orders as $id) {
            $order = wc_get_order($id);
	        $metadata = $order->get_meta_data();
            $priority_status = $order->get_meta('priority_invoice_status');
            if (!$priority_status) {
                $response = $this->syncAinvoice($id);
            }
        };
        $this->updateOption('time_stamp_cron_ainvoice', time());
    }
    public function syncOtcs()
    {
        $query = new \WC_Order_Query(array(
            //'limit' => get_option('posts_per_page'),
            'limit' => 1000,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
            'meta_key' => 'priority_invoice_status', // The postmeta key field
            'meta_compare' => 'NOT EXISTS', // The comparison argument
        ));

        $orders = $query->get_orders();
        foreach ($orders as $id) {
            $order = wc_get_order($id);
            $priority_status = $order->get_meta('priority_invoice_status');
            if (!$priority_status) {
                $response = $this->syncOverTheCounterInvoice($id);
            }
        };
        $this->updateOption('time_stamp_cron_otc', time());
    }
    public function get_credit_card_data($order, $is_order)
    {
        $config = json_decode(stripslashes($this->option('setting-config')));
        $gateway = $config->gateway ?? 'debug';
        $paymentcode = $this->option('payment_' . $order->get_payment_method(), $order->get_payment_method());

        switch ($gateway) {
            //yaad-sarig
            case 'yaad-sarig';
                $yaad_credit_card_payment = $order->get_meta('yaad_credit_card_payment');
                $strArr = explode('&', $yaad_credit_card_payment);
                $var = $strArr[14];
                $s = explode('=', $var);
                $ccuid = $s[1];
                $var = $strArr[14];
                $s = explode('=', $var);
                $payaccount = $s[1];
                $var = $strArr[20];
                $s = explode('=', $var);
                $validmonth = $s[1];
                $var = $strArr[3];
                $s = explode('=', $var);
                $confnum = $s[1];
                $var = $strArr[10];
                $s = explode('=', $var);
                $numpay = $s[1];
                $var = $strArr[10];
                $s = explode('=', $var);
                $firstpay = $s[1];
                $var = $strArr[13];
                $s = explode('=', $var);
                $card_type = $s[1];
                $var = $strArr[12];
                $s = explode('=', $var);
                $payment_type = $s[1];
                break;
            // pelecard
            case 'pelecard';
                if ($order->get_payment_method_title() !== 'PayPal' && $order->get_payment_method_title() !== 'BUYME') {
                    $order_cc_meta = $order->get_meta('_transaction_data');
                    $paymentcode = !empty($order_cc_meta['CreditCardCompanyClearer']) ? $order_cc_meta['CreditCardCompanyClearer'] : $paymentcode;
                    // data
                    $payaccount = $order_cc_meta['CreditCardNumber'];
                    $ccuid = $order_cc_meta['Token'];
                    $validmonth = $order_cc_meta['CreditCardExpDate'] ?? '';
                    $confnum = $order_cc_meta['ConfirmationKey'];
                    $numpay = 1;
                    $firstpay = 0.0;
                }
                break;
            // credit guard
            case 'creditguard';
                $payaccount = $order->get_meta('_ccnumber');
                $ccuid = $order->get_meta('_creditguard_token');
                $validmonth = $order->get_meta('_creditguard_expiration') ?? '';
                $confnum = $order->get_meta('_creditguard_authorization');
                $numpay = $order->get_meta('_payments');
                $firstpay = $order->get_meta('_first_payment');
                $order_periodical_payment = $order->get_meta('_periodical_payment');
                break;
            // credit guard Direct Pay www.directpay.co.il
            case 'creditguard-directpay';
                $payaccount = $order->get_meta('_cardMask');
                $ccuid = $order->get_meta('_cardToken');
                $validmonth = $order->get_meta('_cardExp') ?? '';
                $confnum = $order->get_meta('_authNumber');
                $numpay = $order->get_meta('_numberOfPayments');
                $firstpay = $order->get_meta('_firstPayment');
                $order_periodical_payment = $order->get_meta('_periodicalPayment');
                $cardnum = $order->get_meta('CG Transaction Id');
                break;
            // card com
            case 'cardcom';
                $paymentcode = $order->get_meta('cc_Mutag');
                $payaccount = $order->get_meta('cc_number');
                $ccuid = $order->get_meta('CardcomInternalDealNumber');
                $validmonth = $order->get_meta('cc_Tokef') ?? '';
                $confnum = $order->get_meta('CardcomInternalDealNumber');
                $numpay = $order->get_meta('cc_numofpayments');
                $firstpay = floatval($order->get_meta('cc_firstpayment')) / 100;
                $card_type = $order->get_meta('cc_cardtype');
                $payment_type = $order->get_meta('cc_paymenttype');
                break;
            // tranzila
            case 'tranzila';
                //$paymentcode = $order->get_meta('cc_Mutag');
                $payaccount = $order->get_meta('cc_number_tranzila');
                $ccuid = $order->get_meta('tranzila_cc_last_4');
                $validmonth = !empty(get_post_meta($order->get_id(), 'expmonth', true)) ? get_post_meta($order->get_id(), 'expmonth', true) . '/' . get_post_meta($order->get_id(), 'expyear', true) : '';
                $confnum = $order->get_meta('ConfirmationCode');
                $numpay = $order->get_meta('cc_numofpayments_tranzila');
                $firstpay = floatval($order->get_meta('cc_firstpayment_tranzila'));
                $card_type = $order->get_meta('card_type');
                $payment_type = $order->get_meta('cc_paymenttype_tranzila');
                break;
            // gobit
            case 'gobit';
                //$paymentcode = $order->get_meta('cc_Mutag');
                $payaccount = $order->get_meta('_ccno');
                $ccuid = $order->get_meta('tranzila_authnr');
                $validmonth = !empty(get_post_meta($order->get_id(), 'expmonth', true)) ? get_post_meta($order->get_id(), 'expmonth', true) . '/' . get_post_meta($order->get_id(), 'expyear', true) : '';
                $confnum = $order->get_meta('_confirmationcode');
                $numpay = $order->get_meta('cc_numofpayments_tranzila');
                //$firstpay = floatval($order->get_meta('cc_firstpayment_tranzila'));
                $card_type = $order->get_meta('_cardtypeid');
                $card_type_desc = $order->get_meta('_cardtype');
                $payment_type = $order->get_meta('cc_paymenttype_tranzila');
                break;
            case 'gobit2';
                $payaccount = $order->get_meta('cc_last_4');
                // $validmonth = date_format(date_create(get_post_meta($order->get_id(), '_paid_date')[0]), 'd/m');
                $numpay = $order->get_meta('tranzila_F_number_of_payments');
                $ccuid = $order->get_meta('tranzila_authnr');
                break;
            // payplus
            case 'payplus';
                $firstpay = floatval(get_post_meta($order->get_id(), 'payplus_payments_firstAmount', true)) / 100;
                $ccuid = get_post_meta($order->get_id(), 'payplus_token', true);
                $payaccount = get_post_meta($order->get_id(), 'payplus_last_four', true);
                $validmonth = get_post_meta($order->get_id(), 'payplus_exp_date', true) ?? '';
                $numpay = get_post_meta($order->get_id(), 'payplus_number_of_payments', true);
                $confnum = get_post_meta($order->get_id(), 'payplus_voucher_id', true);

                break;
            // payplus2
            case 'payplus2';
                /* there is another plugin for payplus in Munier 27.6.2021 roy */
                $firstpay = floatval(get_post_meta($order->get_id(), 'payplus_payments_firstAmount', true)) / 100;
                $ccuid = get_post_meta($order->get_id(), 'payplus_token_uid', true);
                $payaccount = get_post_meta($order->get_id(), 'payplus_four_digits', true);
                $validmonth = !empty(get_post_meta($order->get_id(), 'payplus_expiry_month', true)) ?
                    get_post_meta($order->get_id(),
                        'payplus_expiry_month', true) . '/' . get_post_meta($order->get_id(),
                        'payplus_expiry_year', true) : '';
                $numpay = get_post_meta($order->get_id(), 'payplus_number_of_payments', true);
                $confnum = get_post_meta($order->get_id(), 'payplus_voucher_num', true);
                $payplus_identification_number = get_post_meta($order->get_id(), 'payplus_identification_number', true);
                break;

            case 'z-credit';
                $data = $order->get_meta('zc_response');
                $data = base64_decode($data);
                $payaccount = $this->getStringBetween($data, '"CardNum":"', '"');;
                $ccuid = $this->getStringBetween($data, '"Token":"', '"');
                $validmonth = $this->getStringBetween($data, '"ExpDate_MMYY":"', '"');
                $confnum = $this->getStringBetween($data, '"ApprovalNumber":"', '"');
                break;
            // debug
            case 'debug';
                $payaccount = '123456789';
                $validmonth = '01/25';
                $ccuid = '1234';
                $confnum = '987654321';
                $numpay = '';
                $firstpay = 0.0;
                $cardnum = '';
                break;

        }

        //paypel
        if ($order->get_payment_method() == 'paypal' || $order->get_payment_method_title() == 'PayPal' || $order->get_payment_method_title() == 'BUYME') {
            $data = [
                'PAYMENTCODE' => !empty($card_type) ? $card_type : $paymentcode,
                'QPRICE' => floatval($order->get_total())
            ];
        } else {
            $data = [
                'PAYMENTCODE' => !empty($card_type) ? $card_type : $paymentcode,
                'PAYACCOUNT' => substr($payaccount, strlen($payaccount) - 4, 4),
                'VALIDMONTH' => $validmonth,
                'QPRICE' => floatval($order->get_total()),
                'CCUID' => $ccuid,
                'CONFNUM' => $confnum,
                'PAYCODE' => (string)$numpay

            ];
        }
        if (!$is_order) {
            $data['CARDNUM'] = $cardnum;
            // add fields for not order objects
            if ($firstpay != 0.0) {
                $data['FIRSTPAY'] = (float)$firstpay;
                //  $data['OTHERPAYMENTS'] = (float)$order_periodical_payment;
            }
        }
        if (!$is_order) {
            $data['PAYDATE'] = date('Y-m-d');
        }
        if ($is_order) {
            $data['EMAIL'] = $order->get_billing_email();
        }
        return $data;
    }
    public function syncProspect($order)
    {
        if (null == $this->option('post_prospect')) {
            $priority_customer_number = $this->option('walkin_number');
            update_post_meta($order->ID, 'prospect_custname', $priority_customer_number);
            $response['priority_customer_number'] = $priority_customer_number;
            $response['message'] = 'this is a walk in number';
            return $response;
        }
        if ('prospect_email' == $this->option('prospect_field')) {
            $priority_customer_number = $order->get_billing_email();
        } elseif ('prospect_cellphone' == $this->option('prospect_field')) {
            $priority_customer_number = $order->get_billing_phone();
        }

        // if the CUSTNAME is empty, do not POST to Priority
        if (null == $priority_customer_number) {
            // I want to post to priority and get the number from the template
            //  return ;
        }

        $custdes = !empty($order->get_billing_company()) ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $custdes = apply_filters('simply_syncProspect_custdes', $custdes, $order );
        $json_request = [
            'CUSTNAME' => $priority_customer_number,
            'CUSTDES' => $custdes,
            'EMAIL' => $order->get_billing_email(),
            'ADDRESS' => $order->get_billing_address_1(),
            'ADDRESS2' => $order->get_billing_address_2(),
            'STATEA' => $order->get_billing_city(),
            'ZIP' => $order->get_shipping_postcode(),
            'PHONE' => $order->get_billing_phone(),
            'NSFLAG' => 'Y',
        ];

        //check whether the customer already exists in Priority
        $request = $this->makeRequest('GET', 
        'CUSTOMERS(\''.$priority_customer_number.' \')', [], 
        $this->option('log_customers', true));

        if ($request['status']) {
            if ($request['code'] == '200') {
                $is_customer = json_decode($request['body']);
                $priority_cust_from_priority = $priority_customer_number;
            }
        }
        //if it exists, update method patch
        $method = !empty($priority_cust_from_priority) ? 'PATCH' : 'POST';
        $url_eddition = 'CUSTOMERS';
        if ($method == 'PATCH') {
            $url_eddition = 'CUSTOMERS(\'' . $priority_customer_number . '\')';
            unset($json_request['CUSTNAME']);
        }
        //apply_filters
        $json_request['order_id'] = $order->get_id();
        $json_request = apply_filters('simply_post_prospect', $json_request);
        unset($json_request['order_id']);
       
        $response = $this->makeRequest($method, $url_eddition, ['body' => json_encode($json_request)], $this->option('log_customers_web', true));
        // set priority customer id
        if ($method == 'POST' && $response['code'] == '201' || $method == 'PATCH' && $response['code'] == '200') {
            $data = json_decode($response['body']);
            $priority_customer_number = $data->CUSTNAME;
            update_post_meta($order->ID, 'prospect_custname', $priority_customer_number);
        } else {
            $this->sendEmailError(
                [$this->option('email_error_sync_customers_web')],
                'Error Sync Customers',
                $response['body']
            );
        }
        // add timestamp
        $this->updateOption('time_stamp_cron_prospect', time());
        $response['priority_customer_number'] = $priority_customer_number;
        return $response;
    }
    public function syncReceiptAfterOrder($order_id)
    {

        $order = new \WC_Order($order_id);
        $config = json_decode(stripslashes($this->option('setting-config')));
        if (isset($config->post_receipt) != true) {
            return;
        }
        if ($order->get_status() == "completed") {
            if (empty(get_post_meta($order_id, '_payment_done', true))) {
                // get to order _payment_done with true
                update_post_meta($order_id, '_payment_done', true);
                $this->getPriorityCustomer($order);
                // sync receipts
                $this->syncReceipt($order_id);

            }
        }
    }
    public function syncDataAfterOrder($order_id)
    {
        $order = new \WC_Order($order_id);
        // check order status against config
        $config = json_decode(stripslashes($this->option('setting-config')));
        if (!isset($config->order_statuses)) {
            //$is_status = "processing";
            $statuses = ["processing"];
            $is_status = in_array($order->get_status(), $statuses);
        } else {
            $statuses = explode(',', $config->order_statuses);
            $is_status = in_array($order->get_status(), $statuses);
        }
        if (empty(get_post_meta($order_id, '_post_done', true)) && $is_status) {
            // get order
            update_post_meta($order_id, '_post_done', true);
            // sync payments
            //$is_payment = !empty(get_post_meta($order_id, 'priority_custname', true));
            $retrive_data = [];
            if (isset(WC()->session)) {
                $retrive_data = WC()->session->get('session_vars');
            }
            if (!empty($retrive_data) && ($retrive_data['ordertype'] == "obligo_payment")) {
                $is_payment = true;
            } else {
                $is_payment = false;
            }
            if ($is_payment) {
                $optional = array(
                    "custname" => get_post_meta($order_id, 'priority_custname', true)
                );
                $this->syncPayment($order_id, $optional);
                //unset session after payment
                if (isset(WC()->session)) {
                    $session = WC()->session->get('session_vars');
                    if ($session['ordertype'] == 'obligo_payment') {
                        WC()->session->set(
                            'session_vars',
                            array(
                                'ordertype' => ''));
                    }
                }
                return;
            }
            // customer
            $this->getPriorityCustomer($order);
            // sync order
            if ($this->option('post_order_checkout')) {
                $this->syncOrder($order_id);
            }
            // sync order
            if ($this->option('post_pos_checkout')) {
                $this->syncTransactionPos($order_id);
            }
            // sync OTC
            if ($this->option('post_einvoice_checkout') && empty(get_post_meta($order_id, 'priority_invoice_status', false)[0])) {
                // avoid repetition
                $order->update_meta_data('priority_invoice_status', 'Processing');
                $this->syncOverTheCounterInvoice($order_id);
            }
            // sync Ainvoices
            if ($this->option('post_ainvoice_checkout')) {
                $this->syncAinvoice($order_id);
            }
            // sync receipts
            if ($this->option('post_receipt_checkout')) {
                $this->syncReceipt($order_id);
            }
        }
    }
    public function syncPriceLists()
    {
        $priceListNumber = $this->option('sync_pricelist_priority_warhsname')[1];
        $priceList = !empty($priceListNumber) ? '&$filter=PLNAME eq ' . $priceListNumber . '' : '';
        $filter = empty(explode(',', $this->option('sync_pricelist_priority_warhsname'))[0]) ? '' : '$filter=STATDES eq \'פעיל\'';
        $response = $this->makeRequest('GET', 'PRICELIST?' . $filter . '&$select=PLNAME,PLDES,CODE' . $priceList . '&$expand=PARTPRICE2_SUBFORM($select=PARTNAME,QUANT,PRICE,VATPRICE,PERCENT,DPRICE,DVATPRICE)', [], $this->option('log_pricelist_priority', true));
        
        //check if WooCommerce Tax Settings are set
        $set_tax = get_option('woocommerce_calc_taxes');
        
        // check response status
        if ($response['status']) {

            // allow multisite
            $blog_id = get_current_blog_id();

            // price lists table
            $table = $GLOBALS['wpdb']->prefix . 'p18a_pricelists';

            // delete all existing data from price list table
            $GLOBALS['wpdb']->query('DELETE FROM ' . $table);

            // decode raw response
            $data = json_decode($response['body_raw'], true);

            $priceList = [];

            if (isset($data['value'])) {

                foreach ($data['value'] as $list) {
                    /*

                    Assign user to price list, no needed for now

                    // update customers price list
                    foreach($list['PLISTCUSTOMERS_SUBFORM'] as $customer) {
                        update_user_meta($customer['CUSTNAME'], '_priority_price_list', $list['PLNAME']);
                    }
                    */

                    // products price lists
                    foreach ($list['PARTPRICE2_SUBFORM'] as $product) {

                        if($product['PARTNAME'] =='1240-TX-240'){
                        $foo = $product['PARTNAME'];
                        }

                       $res =  $GLOBALS['wpdb']->insert($table, [
                            'product_sku' => $product['PARTNAME'],
                            'price_list_code' => $list['PLNAME'],
                            'price_list_name' => $list['PLDES'],
                            'price_list_currency' => $list['CODE'],
                            'price_list_price' => (wc_prices_include_tax() || $set_tax == 'no') ? $product['VATPRICE'] : (float)$product['PRICE'],
                            'price_list_quant' => $product['QUANT'],
                            'price_list_percent' => $product['PERCENT'],
                            'price_list_disprice' => wc_prices_include_tax() ? (float)$product['DVATPRICE'] : (float)$product['DPRICE'],
                            'blog_id' => $blog_id
                        ]);

                           //update regular price for WooCommerce product from base price list
                           if ($list['PLNAME'] == 'בסיס') {
                            $sku =  $product['PARTNAME'];
                            $items = get_posts(array(
                                        'post_type'      => 'product',
                                        'post_status'    => 'publish',
                                        'meta_query' => array(
                                            array(
                                                'key' => '_sku',
                                                'value' => $sku
                                            )
                                        )
                                    ));
                            if($items){
                                foreach ($items as $item) {
                                    $item_id = $item->ID;
                                    $my_product = new \WC_Product( $item_id );                              
                                    $price = (wc_prices_include_tax() == true || $set_tax == 'no') ? (float)$product['VATPRICE'] : (float)$product['PRICE'];                                   
                                    $my_product->set_regular_price( $price );
                                    $my_product->set_price( $price );
                                    $my_product->save();

                                }
                            }
                        }

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
    public function syncSites()
    {
        $response = $this->makeRequest('GET', 'CUSTOMERS?$select=CUSTNAME&$expand=CUSTDESTS_SUBFORM($select=CODE,CODEDES,ADDRESS,INACTIVE;$filter=INACTIVE ne \'Y\')', [], $this->option('log_sites_priority', true));

        // check response status
        if ($response['status']) {

            // allow multisite
            $blog_id = get_current_blog_id();

            // sites table
            $table = $GLOBALS['wpdb']->prefix . 'p18a_sites';

            // delete all existing data from price list table
            $GLOBALS['wpdb']->query('DELETE FROM ' . $table);

            // decode raw response
            $data = json_decode($response['body_raw'], true);

            $sites = [];

            if (isset($data['value'])) {

                foreach ($data['value'] as $list) {
                    // products price lists
                    foreach ($list['CUSTDESTS_SUBFORM'] as $site) {

                        $GLOBALS['wpdb']->insert($table, [
                            'sitecode' => $site['CODE'],
                            'sitedesc' => $site['CODEDES'],
                            'customer_number' => $list['CUSTNAME'],
                            'address1' => $site['ADDRESS']
                        ]);

                    }

                }

                // add timestamp
                $this->updateOption('sites_priority_update', time());

            }

        } else {
            /**
             * t149
             */
            $this->sendEmailError(
                $this->option('email_error_sync_site_priority'),
                'Error Sync Sites Priority',
                $response['body']
            );

        }

    }
    function get_cust_name($order)
    {
        $cust_number = apply_filters('simply_modify_customer_number', ['order' => $order,
            'CUSTNAME' => null])['CUSTNAME'];
        if (!empty($cust_number)) {
            return $cust_number;
        }
        $walk_in_number = $this->option('walkin_number');
        if ($order->get_user_id() != 0) {
            if ($this->option('post_customers') == true) {
                $cust_number = get_user_meta($order->get_user_id(), 'priority_customer_number', true);
            } else {
                $cust_number = $walk_in_number;
            }
        } else {
            if ($this->option('post_prospect')) {
                if ('prospect_email' == $this->option('prospect_field')) {
                    $cust_number = $order->get_billing_email();
                } else {
                    $cust_number = $order->get_billing_phone();
                }
            } else {
                $cust_number = $walk_in_number;
            }
            if (!empty(get_post_meta($order->ID, 'prospect_custname', true))) {
                $cust_number = get_post_meta($order->ID, 'prospect_custname', true);
            }

        }
        return $cust_number;
    }
    public function syncOrder($id)
    {
        if (isset(WC()->session)) {
            $session = WC()->session->get('session_vars');
            if ($session['ordertype'] == 'obligo_payment') {
                return;
            }
        }
        $order = new \WC_Order($id);
        $user = $order->get_user();
        $user_id = $order->get_user_id();
        // $user_id = $order->user_id;
        $order_user = get_userdata($user_id); //$user_id is passed as a parameter

        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array('.', "\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $discount_type = (!empty($config->discount_type) ? $config->discount_type : 'additional_line'); // header , in_line , additional_line

        //$cust_number = get_post_meta($order->get_id(), 'cust_name', true);
        $cust_number = $this->get_cust_name($order);
        $data = [
            'CUSTNAME' => $cust_number,
            'CDES' => !empty($order->get_billing_company()) ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'CURDATE' => date('Y-m-d', strtotime($order->get_date_created())),
            $this->option('order_order_field') => $order->get_order_number(),
            //'DCODE' => $priority_dep_number, // this is the site in Priority
            //'DETAILS' => $user_department,


        ];
        if (!empty($order->get_meta('site', true))) {
            $data['DCODE'] = $order->get_meta('site');
        }
        if ($this->option('sync_personnel') && $order_user) {
            $data['NAME'] = get_user_meta($user_id, 'first_name', true);
        }
        //        // CDES
        //        if(empty($order->get_customer_id()) || true != $this->option( 'post_customers' )){
        //            $data['CDES'] = !empty($order->get_billing_company()) ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        //        }

        // cart discount header
        $cart_discount = floatval($order->get_total_discount());
        $cart_discount_tax = floatval($order->get_discount_tax());
        $order_total = floatval($order->get_subtotal() + $order->get_shipping_total());
        if ($order_total != 0)
            $order_discount = ($cart_discount / $order_total) * 100.0;
        if ('header' == $discount_type) {
            $data['PERCENT'] = $order_discount;
        }

        // order comments
        $priority_version = (float)$this->option('priority-version');
        if ($priority_version > 19.1) {
            // for Priority version 20.0
            $data['ORDERSTEXT_SUBFORM'] = ['TEXT' => $order->get_customer_note()];
        } else {
            // for Priority version 19.1
            $data['ORDERSTEXT_SUBFORM'][] = ['TEXT' => $order->get_customer_note()];

        }


        // billing customer details
        $customer_data = [

            'PHONE' => $order->get_billing_phone(),
            'EMAIL' => $order->get_billing_email(),
            'ADRS' => $order->get_billing_address_1(),
            'ADRS2' => $order->get_billing_address_2(),
            'STATEA' => $order->get_billing_city(),
            'ZIP' => $order->get_shipping_postcode(),
        ];
        $data['ORDERSCONT_SUBFORM'][] = $customer_data;

        // shipping

        // shop address debug

        $shipping_data = [
            'NAME' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),/* איש קשר */
            'CUSTDES' => (!empty($order_user)) ? $order_user->user_firstname . ' ' . $order_user->user_lastname : ($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),/*שם*/
            'PHONENUM' => $order->get_billing_phone(),
            'ADDRESS' => $order->get_shipping_address_1(),
            'ADDRESS2' => $order->get_shipping_address_2(),
            'STATE' => $order->get_shipping_city(),
            'ZIP' => $order->get_shipping_postcode(),
        ];
        if ($priority_version > 19.1) {
            $shipping_data['EMAIL'] = $order->get_billing_email();
            $shipping_data['CELLPHONE'] = $order->get_billing_phone();
        }

        // add second address if entered
        if (!empty($order->get_shipping_address_2())) {
            $shipping_data['ADDRESS2'] = $order->get_shipping_address_2();
        }

        $data['SHIPTO2_SUBFORM'] = $shipping_data;

        // get shipping id
        $shipping_method = $order->get_shipping_methods();
        $shipping_method = array_shift($shipping_method);
        if (!empty($shipping_method['method_id'])) {
            $shipping_method_id = str_replace(':', '_', $shipping_method['method_id']);
        }

        // get parameters
        $params = [];


        // get ordered items
        foreach ($order->get_items() as $item_id => $item) {
            if ($this->option('packs') == true) {
                $pack_step = wc_get_order_item_meta($item_id, 'pack_step', true);
                $pack_code = wc_get_order_item_meta($item_id, 'pack_code', true);
            }
            $bool = apply_filters('sync_order_product', $item);
            if ($bool == 'no') {
                continue;
            }
            $product = $item->get_product();

            $parameters = [];

            // get tax
            // Initializing variables
            $tax_items_labels = array(); // The tax labels by $rate Ids
            $tax_label = 0.0; // The total VAT by order line
            $taxes = $item->get_taxes();
            // Loop through taxes array to get the right label
            foreach ($taxes['subtotal'] as $rate_id => $tax) {
                $tax_label = +$tax; // <== Here the line item tax label
            }

            // get meta
            foreach ($item->get_meta_data() as $meta) {

                if (isset($params[$meta->key])) {
                    $parameters[$params[$meta->key]] = $meta->value;
                }

            }

            if ($product) {

                /*start T151*/
                $new_data = [];

                $item_meta = wc_get_order_item_meta($item->get_id(), '_tmcartepo_data');

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
                $line_after_discount = (float)$item->get_total();
                $discount = ($line_before_discount - $line_after_discount) / ($line_before_discount ?: 1) * 100.0;
                $data['ORDERITEMS_SUBFORM'][] = [
                    $this->get_sku_prioirty_dest_field() => $product->get_sku(),
                    'TQUANT' => (int)$item->get_quantity(),
                    'VPRICE' => $discount_type == 'in_line' ? ($line_before_discount + $line_tax) / (int)$item->get_quantity() : 0.0,
                    'PERCENT' => $discount_type == 'in_line' ? $discount : 0.0,
                    'REMARK1' => isset($parameters['REMARK1']) ? $parameters['REMARK1'] : '',
                    'DUEDATE' => date('Y-m-d'),
                ];
                if ($this->option('packs') == true && !empty($pack_step) && !empty($pack_code)) {
                    $data['ORDERITEMS_SUBFORM'][count($data['ORDERITEMS_SUBFORM']) - 1]['NUMPACK'] = $pack_step > 0 ? (int)$item->get_quantity() / $pack_step : 0;
                    $data['ORDERITEMS_SUBFORM'][count($data['ORDERITEMS_SUBFORM']) - 1]['PACKCODE'] = $pack_code;

                }
                if ($discount_type != 'in_line') {
                    $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VATPRICE'] = $line_before_discount + $line_tax;
                }
                // if you want to show the sales price as percent of regular price
                if ($config->in_line_sales_discount == 'true') {
                    $regular_price = (float)$product->get_regular_price();
                    $sales_price = (float)$item->get_total() / $item->get_quantity();
                    $discount = (1 - ($sales_price / $regular_price)) * 100.0;
                    unset($data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VATPRICE']);
                    $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['VPRICE'] = $regular_price;
                    $data['ORDERITEMS_SUBFORM'][sizeof($data['ORDERITEMS_SUBFORM']) - 1]['PERCENT'] = $discount;
                }
            }
            $res_data = apply_filters('simply_modify_orderitem',['data' => $data,'item' => $item]);
            $data = $res_data['data'];
        }
        // additional line cart discount
        $config = json_decode(stripslashes($this->option('setting-config')));
        if (!empty($config)){
	        $coupon_num = $config->coupon_num;
            $add_fee_as_discount = $config->add_fee_as_discount == 'true';
        }
        // additional line cart discount
	    $fees = $order->get_fees();
	    $total_fee = 0;
	    foreach ( $fees as $fee ) {
            if($fee->get_total()<0 && $add_fee_as_discount == true){
	            $total_fee += ($fee->get_total()+$fee->get_total_tax()) * -1;
            }
	    }
	    if ($discount_type == 'additional_line' && ($order->get_discount_total() + $order->get_discount_tax() + $total_fee > 0)) {
            $priceDisplay = get_option('woocommerce_tax_display_cart');
            //check if WooCommerce Tax Settings are set
            $set_tax = get_option('woocommerce_calc_taxes');
            $price_discount = ($priceDisplay === 'incl' || $set_tax == 'no' ) ? 'with_tax' : 'without_tax';
            $data['ORDERITEMS_SUBFORM'][] = [
                $this->get_sku_prioirty_dest_field() => empty($coupon_num) ? '000' : $coupon_num, // change to other item
                'TQUANT' => -1,
                'VATPRICE' => ($price_discount === 'with_tax') ? 1 * floatval($order->get_discount_total() + $order->get_discount_tax() + $total_fee ) : 1 * floatval($order->get_discount_total() + $total_fee ),
                'DUEDATE' => date('Y-m-d'),


            ];
        }

        //$data = $this->get_coupons($data,$order);
        // shipping rate
        if (!empty($this->get_shipping_price($order, true))) {
            $data['ORDERITEMS_SUBFORM'][] = $this->get_shipping_price($order, true);
        }
        // payment info
        if ($order->get_payment_method()) {
            $data['PAYMENTDEF_SUBFORM'] = $this->get_credit_card_data($order, true);
        }
        // filter
        $data['orderId'] = $id;
        $data['doctype'] = "ORDERS";
        $data = apply_filters('simply_request_data', $data);
        unset($data['orderId']);
        unset($data['doctype']);
        $config = json_decode(stripslashes($this->option('setting-config')));
        if (!empty($config->formname)) {
            $form_name = $config->formname;
        } else {
            $form_name = 'ORDERS';
        }
        // make request
        $response = $this->makeRequest('POST', $form_name, ['body' => json_encode($data)], true);

        if ($response['code'] <= 201 && $response['code'] >= 200 ) {
            $body_array = json_decode($response["body"], true);
            $ord_status = $body_array["ORDSTATUSDES"];
            $ordname_field = $config->ordname_field ?? 'ORDNAME';
            $ord_number = $body_array[$ordname_field];
            $order->update_meta_data('priority_order_status', $ord_status);
            $order->update_meta_data('priority_order_number', $ord_number);
            $order->save();
        } else {
            $mes_arr = json_decode($response['body']);
            $message = $response['message'] . '' . json_encode($response);
            $message = $response['message'] . '<br>' . $response['body'] . '<br>';
            if(isset($mes_arr->FORM->InterfaceErrors->text)){
                $message = $mes_arr->FORM->InterfaceErrors->text;
            }
            $order->update_meta_data('priority_order_status', $message);
            $order->save();
        }
        // add timestamp
        return $response;
    }
    public function makeRequestPos($method, $url_addition = null, $options = [], $log = false)
    {
        $args = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'timeout' => 45,
            'method' => strtoupper($method),
            'sslverify' => $this->option('sslverify', false)
        ];

        if (!empty($options)) {
            $args = array_merge($args, $options);
        }
        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $ip = $config->IP;
        $url = sprintf('http://%s/PrioriPOSTestAPI/api/Transactions/%s',
            $ip,
            is_null($url_addition) ? '' : stripslashes($url_addition)
        );
        $response = wp_remote_request($url, $args);

        //print_r($response);die;

        $response_code = wp_remote_retrieve_response_code($response);
        $response_message = wp_remote_retrieve_response_message($response);
        $response_body = wp_remote_retrieve_body($response);

        $body_array = json_decode($response["body"], true);
        $error_code = $body_array["ErrorCode"];

        if ($error_code >= 1) {
            $response_body = strip_tags($response_body);
        }

        // decode hebrew
        $response_body_decoded = $this->decodeHebrew($response_body);
        // log request
        if ($log) {
            $GLOBALS['wpdb']->insert($GLOBALS['wpdb']->prefix . 'p18a_logs', [
                'blog_id' => get_current_blog_id(),
                'timestamp' => current_time('mysql'),
                'url' => $url,
                'request_method' => strtoupper($method),
                'json_request' => (isset($args['body'])) ? $this->decodeHebrew($args['body']) : '',
                'json_response' => ($response_body_decoded ? $response_body_decoded : $response_message . ' ' . $response_code),
                'json_status' => ($error_code == 0) ? 1 : 0
            ]);
        }


        return [
            'url' => $url,
            'args' => $args,
            'method' => strtoupper($method),
            'body' => $response_body_decoded,
            'body_raw' => $response_body,
            'code' => $response_code,
            'status' => ($error_code == 0) ? 1 : 0,
            'message' => ($response_message ? $response_message : $response->get_error_message())
        ];


    }

    public function syncTransactionPos($id)
    {
        if (isset(WC()->session)) {
            $session = WC()->session->get('session_vars');
            if ($session['ordertype'] == 'obligo_payment') {
                return;
            }
        }
        $order = new \WC_Order($id);
        $order_total = $order->get_total();
        $user = $order->get_user();
        $user_id = $order->get_user_id();
        if (!empty($user_id)) {
            $order_user = get_userdata($user_id); //$user_id is passed as a parameter
            $cust_number = get_user_meta($user_id, 'priority_customer_number', true);
        }


        $raw_option = $this->option('setting-config');


        $raw_option = str_replace(array('.', "\n", "\t", "\r"), '', $raw_option);

        $config = json_decode(stripslashes($raw_option));
        $branch_number = $config->BranchNumber;
        $pos_number = $config->POSNumber;
        $unique_identifier = $config->UniqueIdentifier;


        $data['Transaction'] = [
            //"TemporaryTransactionNumber" => $order->get_order_number(),
            "FinalTransactionNumber" => $order->get_order_number(),
            "TransactionDateTime" => date('Y-m-d', strtotime($order->get_date_created())),
            "IsOrder" => true,
            "IsCancelTransaction" => false,
            "POSCustomerNumber" => !empty($user_id) ? $cust_number : '',
            "PriorityCustomerName" => "",
            "TotalItemQuantity" => count($order->get_items()),
            "TotalBeforeGeneralDiscountIncludingVAT" => $order_total,
            "IsManualDiscount" => false,
            "GeneralDiscountSum" => 0,
            "GeneralDiscountPercent" => 0,
            "TotalAfterGeneralDiscountIncludingVAT" => 0,
            "ExternalOrderNumber" => $order->get_order_number(),
            "SupplyBranch" => "",
        ];

        //$data['Transaction']['TransactionExternalMetaData'] = [];
        $data['Transaction']['TransactionItems'] = [];

        // get ordered items
        $line_number = 1;
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $data['Transaction']['OrderItems'][] = [
                    "ItemCode" => $product->get_sku(),
                    "ItemQuantity" => $item->get_quantity(),
                    "PricePerItem" => $product->get_price(),
                    "CalculatePrice" => true,
                    "IsManualPrice" => false,
                    "IsManualDiscount" => false,
                    "TotalPrice" => $item->get_total(),
                    "VATPercent" => 17,
                    "ExternalOrderLineNumber" => $line_number,
                    "HasVAT" => true,
                    "PointsPerType" => []
                ];
            }
            $line_number++;

        }

        $payment_method = get_post_meta($order->get_id(), '_payment_method', true);
        $gateway = $config->gateway ?? 'debug';
        if ($gateway == 'pelecard') {
            $order_cc_meta = $order->get_meta('_transaction_data');
            $paymentcode = !empty($order_cc_meta['CreditCardCompanyClearer']) ? $order_cc_meta['CreditCardCompanyClearer'] : $paymentcode;

            $payaccount = $order_cc_meta['CreditCardNumber'];
            $ccuid = $order_cc_meta['Token'];
            $validmonth = $order_cc_meta['CreditCardExpDate'];
            $confnum = $order_cc_meta['ConfirmationKey'];
            $numpay = $order_cc_meta['TotalPayments'];
            $firstpay = $order_cc_meta['FirstPaymentTotal'] / 100;
            $vouchernumber = str_replace("-", "", $order_cc_meta['VoucherId']);
            $idnum = $order_cc_meta['CardHolderID'];

            $data['Transaction']['CreditCardPayments'][] = [
                "CardNumber" => $payaccount,
                "PaymentSum" => floatval($order->get_total()),
                "AuthorizationNumber" => $confnum,
                "CardIssuerCode" => 1,
                "CardClearingCode" => 1,
                "VoucherNumber" => $vouchernumber,
                "NumberOfPayments" => $numpay,
                "FirstPaymentSum" => $firstpay,
                "Token" => $ccuid,
                "ExpirationDate" => $validmonth,
                "CreditType" => 0,
                "IDNumber" => $idnum
            ];
        } else {
            //debug
            $data['Transaction']['CreditCardPayments'][] = [
                "CardNumber" => "12345",
                "PaymentSum" => 1,
                "AuthorizationNumber" => "12345",
                "CardIssuerCode" => 1,
                "CardClearingCode" => 1,
                "VoucherNumber" => "12345",
                "NumberOfPayments" => 1,
                "FirstPaymentSum" => 1,
                "Token" => 12345,
                "ExpirationDate" => "0126",
                "CreditType" => 0,
            ];

        }

        if (!empty($order->get_shipping_address_1())) {
            $address = $order->get_shipping_address_1();
        } else {
            $address = $order->get_billing_address_1();
        }


        if (!empty($order->get_shipping_city())) {
            $city = $order->get_shipping_city();
        } else {
            $city = $order->get_billing_city();
        }

        if (!empty($order->get_shipping_address_1())) {
            $contact_person = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        } else {
            $contact_person = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        }

        if (!empty(get_post_meta($order->get_id(), '_shipping_phone', true))) {
            $phone = get_post_meta($order->get_id(), '_shipping_phone', true);
        } else {
            $phone = $order->get_billing_phone();
        }

        $data['Transaction']['ShippingDetails'] = [
            "City" => $city,
            "ForeignLanguageCity" => "",
            "Address" => $address,
            "ForeignLanguageAddress" => "",
            "HouseNumber" => 0,
            "ApartmentNumber" => 0,
            "ZipCode" => "",
            "ContactPersonName" => $contact_person,
            "ForeignLanguageContactPersonName" => "",
            "Mail" => "",
            "Fax" => "",
            "SupplyDate" => "2022-04-19T06:56:24.279Z",
            "FromSupplyHour" => "2022-04-19T06:56:24.279Z",
            "ToSupplyHour" => "2022-04-19T06:56:24.279Z",
            "Remark" => "",
            "ForeignLanguageRemark" => "",
            "FirstPhoneNumber" => $phone,
            "SecondPhoneNumber" => "",
            "ShipMethod" => $order->get_shipping_method(),
            "Address2" => (!empty($order->get_shipping_address_2())) ? $order->get_shipping_address_2() : '',
            "Address3" => "",
            "Email" => $order->get_billing_email(),
            "CountryCode" => "",
            "StateCode" => ""
        ];
        $data['Transaction']['Remark'] = [
            "CustomerName" => "",
            "CustomerIDNumber" => "",
            "CustomerPhone" => "",
            "CustomerAddress" => "",
            "CustomerCity" => "",
            "CustomerZipCode" => "",
            "FirstCustomerRemark" => "",
            "SecondCustomerRemark" => "",
            "ParentPOSCustomerNumber" => "",
            "InvoiceCustomerName" => ""
        ];
        $data["CreatePriorityCustomer"] = false;
        $data["RegisterByGeneralPosCustomer"] = !empty($user_id) ? false : true;
        $data["CalculateTax"] = 0;

        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_number,
            "POSNumber" => $pos_number,
            "UniqueIdentifier" => $unique_identifier,
            "ChannelCode" => "",
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        // echo "<pre>";
        // print_r(json_encode($data));
        // echo "</pre>";
        // die;

        if (!empty($config->formname)) {
            $form_name = $config->formname;
        } else {
            $form_name = 'RegisterFinalOrder';
        }

        // make request
        $response = $this->makeRequestPos('POST', $form_name, ['body' => json_encode($data)], true);

        $body_array = json_decode($response["body"], true);
        $error_code = $body_array["ErrorCode"];
        if ($error_code == 0) {
            $ord_status = $body_array['EdeaError']['ErrorMessage']; //success
            $ord_number = $body_array["TransactionNumber"];
            $order->update_meta_data('priority_pos_status', $ord_status);

            $order->update_meta_data('priority_pos_number', $ord_number);
            $order->save();
        } else {
            $message = $body_array['EdeaError']['DisplayErrorMessage'];
            $order->update_meta_data('priority_pos_status', $message);
            $order->save();
        }
        // add timestamp
        return $response;
    }

    public function syncAinvoice($id)
    {
        if (isset(WC()->session)) {
            $session = WC()->session->get('session_vars');
            if ($session['ordertype'] == 'obligo_payment') {
                return;
            }
        }
        $order = new \WC_Order($id);
        $user = $order->get_user();
        $user_id = $order->get_user_id();
        // $user_id = $order->user_id;
        $order_user = get_userdata($user_id); //$user_id is passed as a parameter

        $config = json_decode(stripslashes($this->option('setting-config')));
        $discount_type = (!empty($config->discount_type) ? $config->discount_type : 'additional_line'); // header , in_line , additional_line

        //$cust_number = get_post_meta($order->get_id(), 'cust_name', true);
        $cust_number = $this->get_cust_name($order);

        $data = [
            'CUSTNAME' => $cust_number,
            'CDES' => !empty($order->get_billing_company()) ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'IVDATE' => date('Y-m-d', strtotime($order->get_date_created())),
            $this->option('ainvoice_order_field') => $order->get_order_number(),
            //'DCODE' => $priority_dep_number, // this is the site in Priority
            //'DETAILS' => $user_department,
        ];
        if (!empty($order->get_meta('site', true))) {
            $data['DCODE'] = $order->get_meta('site');
        }
        // CDES
//        if(empty($order->get_customer_id()) || true != $this->option( 'post_customers' )){
//            $data['CDES'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
//        }
        // cart discount header
        $cart_discount = floatval($order->get_total_discount());
        $cart_discount_tax = floatval($order->get_discount_tax());
        $order_total = floatval($order->get_subtotal() + $order->get_shipping_total());
        $order_discount = ($cart_discount / $order_total) * 100.0;
        if ('header' == $discount_type) {
            $data['PERCENT'] = $order_discount;
        }

// order comments
        $priority_version = (float)$this->option('priority-version');
        if ($priority_version > 19.1) {
            // for Priority version 20.0
            $data['PINVOICESTEXT_SUBFORM'] = ['TEXT' => $order->get_customer_note()];
        } else {
            // for Priority version 19.1
            $data['PINVOICESTEXT_SUBFORM'][] = ['TEXT' => $order->get_customer_note()];
        }
        // billing customer details
        $customer_data = [

            'PHONE' => $order->get_billing_phone(),
            'EMAIL' => $order->get_billing_email(),
            'ADRS' => $order->get_billing_address_1(),
            'ADRS2' => $order->get_billing_address_2(),
            'STATEA' => $order->get_billing_city(),
            'ZIP' => $order->get_shipping_postcode(),
        ];
        $data['AINVOICESCONT_SUBFORM'][] = $customer_data;
        // shipping
        // shop address debug
        $shipping_data = [
            'NAME' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),/* איש קשר */
            'CUSTDES' => (!empty($order_user)) ? $order_user->user_firstname . ' ' . $order_user->user_lastname : ($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),/*שם*/
            'PHONENUM' => $order->get_billing_phone(),
            'EMAIL' => $order->get_billing_email(),
            'CELLPHONE' => $order->get_billing_phone(),
            'ADDRESS' => $order->get_shipping_address_1(),
            'ADDRESS2' => $order->get_shipping_address_2(),
            'STATE' => $order->get_shipping_city(),
            'ZIP' => $order->get_shipping_postcode(),
        ];
        // add second address if entered
        if (!empty($order->get_shipping_address_2())) {
            $shipping_data['ADDRESS2'] = $order->get_shipping_address_2();
        }
        $data['SHIPTO2_SUBFORM'] = $shipping_data;
        // get shipping id
        $shipping_method = $order->get_shipping_methods();
        $shipping_method = array_shift($shipping_method);
        $shipping_method_id = str_replace(':', '_', $shipping_method['method_id']);
        // get parameters
        $params = [];
        // get ordered items
        foreach ($order->get_items() as $item) {
            $bool = apply_filters('sync_order_product', $item);
            if ($bool == 'no') {
                continue;
            }
            $product = $item->get_product();
            $parameters = [];
            // get tax
            // Initializing variables
            $tax_items_labels = array(); // The tax labels by $rate Ids
            $tax_label = 0.0; // The total VAT by order line
            $taxes = $item->get_taxes();
            // Loop through taxes array to get the right label
            foreach ($taxes['subtotal'] as $rate_id => $tax) {
                $tax_label = +$tax; // <== Here the line item tax label
            }
            // get meta
            foreach ($item->get_meta_data() as $meta) {
                if (isset($params[$meta->key])) {
                    $parameters[$params[$meta->key]] = $meta->value;
                }
            }
            if ($product) {
                /*start T151*/
                $new_data = [];
                $item_meta = wc_get_order_item_meta($item->get_id(), '_tmcartepo_data');
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
                $line_after_discount = (float)$item->get_total();
                $discount = ($line_before_discount - $line_after_discount) / $line_before_discount * 100.0;
                $data['AINVOICEITEMS_SUBFORM'][] = [
                    $this->get_sku_prioirty_dest_field() => $product->get_sku(),
                    'TQUANT' => (int)$item->get_quantity(),
                    'PRICE' => $discount_type == 'in_line' ? $line_before_discount / (int)$item->get_quantity() : 0.0,
                    'PERCENT' => $discount_type == 'in_line' ? $discount : 0.0,
                ];
                if ($discount_type != 'in_line') {
                    $data['AINVOICEITEMS_SUBFORM'][sizeof($data['AINVOICEITEMS_SUBFORM']) - 1]['TOTPRICE'] = $line_before_discount + $line_tax;
                }
            }

        }
        // additional line cart discount
        if ($discount_type == 'additional_line' && ($order->get_discount_total() + $order->get_discount_tax() > 0)) {
            //if($discount_type == 'additional_line'){
            $data['AINVOICEITEMS_SUBFORM'][] = [
                // 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
                $this->get_sku_prioirty_dest_field() => '000',
                // 'VATPRICE' => -1* floatval( $cart_discount + $cart_discount_tax),
                'TOTPRICE' => -1 * floatval($order->get_discount_total() + $order->get_discount_tax()),
                'TQUANT' => -1,

            ];
        }
        // shipping rate
        if (!empty($this->get_shipping_price($order, false))) {
            $data['AINVOICEITEMS_SUBFORM'][] = $this->get_shipping_price($order, false);
        }

        /*
        [
        // 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
        'PARTNAME' => $this->option( 'shipping_' . $shipping_method_id . '_'.$shipping_method['instance_id'], $order->get_shipping_method() ),
        'TQUANT'   => 1,
        'TOTPRICE' => floatval( $order->get_shipping_total()+$order->get_shipping_tax())
    ];*/

        // filter data
        $data['orderId'] = $id;
        $data['doctype'] = "AINVOICES";
        $data = apply_filters('simply_request_data', $data);
        unset($data['orderId']);
        unset($data['doctype']);
        // make request
        $response = $this->makeRequest('POST', 'AINVOICES', ['body' => json_encode($data)], true);

        if ($response['code'] <= 201 && $response['code'] >= 200) {
            $body_array = json_decode($response["body"], true);

            $ord_status = $body_array["STATDES"];
            $ord_number = $body_array["IVNUM"];
            $order->update_meta_data('priority_invoice_status', $ord_status);
            $order->update_meta_data('priority_invoice_number', $ord_number);
            $order->save();
        }else{
            $message = $response['message'] . '' . json_encode($response);
            $message = $response['message'] . '<br>' . $response['body'] . '<br>';
            $mes_arr = json_decode($response['body']);
            if(isset($mes_arr->FORM->InterfaceErrors->text)){
                $message = $mes_arr->FORM->InterfaceErrors->text;
            }
            $order->update_meta_data('priority_invoice_status', $message);
            $order->save();
        }
        // add timestamp
        return $response;
    }
    public function syncOverTheCounterInvoice($order_id)
    {
        $order = new \WC_Order($order_id);

        //check pament method user
        $is_continue = 'true';
        $is_continue = apply_filters('check_order_is_payment', $order);
        if ($is_continue == 'false') {
            return;
        }
        
        $user = $order->get_user();
        $user_id = $order->get_user_id();
        $order_user = get_userdata($user_id); //$user_id is passed as a parameter
        $user_meta = get_user_meta($user_id);
        //$cust_number = get_post_meta($order->get_id(), 'cust_name', true);
        $cust_number = $this->get_cust_name($order);

        $data = [
            'CUSTNAME' => $cust_number,
            'CDES' => !empty($order->get_billing_company()) ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'IVDATE' => date('Y-m-d', strtotime($order->get_date_created())),
            $this->option('otc_order_field') => $order->get_order_number(),

        ];
        // CDES
//        if(
//            (empty($order->get_customer_id()) && !$this->option('post_prospect')) ||
//            (true != $this->option( 'post_customers' )&& $order->get_customer_id())
//        ){
//            $data['CDES'] = empty($order->get_billing_company()) ? $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() : $order->get_billing_company();
//        }

        // order comments
        $priority_version = (float)$this->option('priority-version');
        $text = urlencode(str_replace(array("\n", "\t", "\r"), '', $order->get_customer_note()));
        if ($priority_version > 19.1) {
            // version 20.0
            $data['PINVOICESTEXT_SUBFORM'] = ['TEXT' => $text];
        } else {
            // version 19.1
            $data['PINVOICESTEXT_SUBFORM'][] = ['TEXT' => $text];
        }


        // billing customer details
        $customer_data = [

            'PHONE' => $order->get_billing_phone(),
            'EMAIL' => $order->get_billing_email(),
            'ADRS' => $order->get_billing_address_1(),
            'ADRS2' => $order->get_billing_address_2(),
            'STATEA' => $order->get_billing_city(),
            'ZIP' => $order->get_shipping_postcode(),
        ];
        $data['EINVOICESCONT_SUBFORM'][] = $customer_data;


        // shipping
        $shipping_data = [
            'NAME' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),/* איש קשר */
            'CUSTDES' => (!empty($order_user)) ? $order_user->user_firstname . ' ' . $order_user->user_lastname : ($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()),/*שם*/
            'PHONENUM' => $order->get_billing_phone(),
            'EMAIL' => $order->get_billing_email(),
            'CELLPHONE' => $order->get_billing_phone(),
            'ADDRESS' => $order->get_shipping_address_1(),
            'ADDRESS2' => $order->get_shipping_address_2(),
            'STATE' => $order->get_shipping_city(),
            'ZIP' => $order->get_shipping_postcode(),
        ];
        // add second address if entered
        if (!empty($order->get_shipping_address_2())) {
            $shipping_data['ADDRESS2'] = $order->get_shipping_address_2();
        }
        $data['SHIPTO2_SUBFORM'] = $shipping_data;
        // get ordered items
        foreach ($order->get_items() as $item) {
            $bool = apply_filters('sync_order_product', $item);
            if ($bool == 'no') {
                continue;
            }
            $product = $item->get_product();

            $parameters = [];

            // get tax
            // Initializing variables
            $tax_items_labels = array(); // The tax labels by $rate Ids
            $tax_label = 0.0; // The total VAT by order line
            $taxes = $item->get_taxes();
            // Loop through taxes array to get the right label
            foreach ($taxes['subtotal'] as $rate_id => $tax) {
                $tax_label = +$tax; // <== Here the line item tax label
            }


            if ($product) {

                $data['EINVOICEITEMS_SUBFORM'][] = [
                    $this->get_sku_prioirty_dest_field() => $product->get_sku(),
                    'TQUANT' => (int)$item->get_quantity(),
                    'TOTPRICE' => round((float)($item->get_total() + $tax_label), 2),
                    'id' => $item->get_id(),
                ];
            }

        }
        // shipping rate
        if (!empty($this->get_shipping_price($order, false))) {
            $data['EINVOICEITEMS_SUBFORM'][] = $this->get_shipping_price($order, false);
        }
        // payment info
        if ($order->get_total() > 0.0) {
            $data['EPAYMENT2_SUBFORM'][] = $this->get_credit_card_data($order, false);
        }
        $data['orderId'] = $order_id;
        for ($i = 0; $i < count($order->get_items()); $i++) {
            unset($data['EINVOICEITEMS_SUBFORM'][$i]['id']);
        }
        $data['doctype'] = "EINVOICES";
        $data = apply_filters('simply_request_data', $data);
        unset($data['orderId']);
        unset($data['doctype']);
        // make request
        $response = $this->makeRequest('POST', 'EINVOICES', ['body' => json_encode($data)], true);

        if($response['code'] <= 201 && $response['code'] >= 200){
            $body_array = json_decode($response["body"], true);

            $ord_status = $body_array["STATDES"];
            $ord_number = $body_array["IVNUM"];
            $order->update_meta_data('priority_invoice_status', $ord_status);
            $order->update_meta_data('priority_invoice_number', $ord_number);
            $order->save();
        }else{
            $message = $response['message'] . '' . json_encode($response);
            $message = $response['message'] . '<br>' . $response['body'] . '<br>';
            $mes_arr = json_decode($response['body']);
            if(isset($mes_arr->FORM->InterfaceErrors->text)){
                $message = $mes_arr->FORM->InterfaceErrors->text;
            }
            $order->update_meta_data('priority_invoice_status', $message);
            $order->save();
        }
        return $response;
    }
    public function syncReceipt($order_id)
    {
        $is_to_sync = true;
        $is_to_sync = apply_filters('simply_sync_receipt_true', $order_id);
        if($is_to_sync == false){
            return;
        }
        if (isset(WC()->session)) {
            $session = WC()->session->get('session_vars');
            if ($session['ordertype'] == 'obligo_payment') {
                return;
            }
        }
        $order = new \WC_Order($order_id);
        $user_id = $order->get_user_id();
        $order_user = get_userdata($user_id); //$user_id is passed as a parameter
        //$cust_number = get_post_meta($order->get_id(), 'cust_name', true);
        $cust_number = $this->get_cust_name($order);
        $data = [
            'CUSTNAME' => $cust_number,
            'CDES' => !empty($order->get_billing_company()) ? $order->get_billing_company() : $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'IVDATE' => date('Y-m-d', strtotime($order->get_date_created())),
            $this->option('receipt_order_field') => $order->get_order_number(),
        ];
        // CDES
//        if(empty($order->get_customer_id()) || true != $this->option( 'post_customers' )){
//            $data['CDES'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
//        }
        // ORDNAME
        $order_number = get_post_meta($order_id, 'priority_order_number', true);
        if (!empty($order_number)) {
            $data['ORDNAME'] = $order_number;
        }
        // cash payment
        if (strtolower($order->get_payment_method()) == 'cod') {
            $data['CASHPAYMENT'] = floatval($order->get_total());
        } else {
            // payment info
            $data['TPAYMENT2_SUBFORM'][] = $this->get_credit_card_data($order, false);
        }
        $data = apply_filters('simply_request_data_receipt', $data);
        // make request
        $response = $this->makeRequest('POST', 'TINVOICES', ['body' => json_encode($data, JSON_UNESCAPED_SLASHES)], $this->option('log_receipts_priority', true));
        if ($response['code'] <= 201 && $response['code'] >= 200) {
            $body_array = json_decode($response["body"], true);

            $ord_status = $body_array["STATDES"];
            $ord_number = $body_array["IVNUM"];
            $order->update_meta_data('priority_recipe_status', $ord_status);
            $order->update_meta_data('priority_recipe_number', $ord_number);
            $order->save();
        }else{
            $message = $response['message'] . '<br>' . $response['body'] . '<br>';
            $mes_arr = json_decode($response['body']);
            if(isset($mes_arr->FORM->InterfaceErrors->text)){
                $message = $mes_arr->FORM->InterfaceErrors->text;
            }
            $order->update_meta_data('priority_recipe_status', $message);
            // $order->update_meta_data('priority_ordnumber',$ord_number);
            $order->save();
        }
        // add timestamp
        $this->updateOption('receipts_priority_update', time());
        return $response;

    }
    public function syncPayment($order_id, $optional)
    {
        $order = new \WC_Order($order_id);
        $priority_customer_number = get_user_meta($order->get_customer_id(), 'priority_customer_number', true);
        if (!empty($optional['custname'])) {
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
        if (strtolower($order->get_payment_method()) == 'cod') {

            $data['CASHPAYMENT'] = floatval($order->get_total());

        } else {

            // payment info
            $data['TPAYMENT2_SUBFORM'][] = $this->get_credit_card_data($order, false);

        }
        foreach ($order->get_items() as $item) {
            $ivnum = $item->get_meta('product-ivnum');
            $data['TFNCITEMS_SUBFORM'][] = [
                'CREDIT' => (float)$item->get_total(),
                'FNCIREF1' => $ivnum
            ];
        }

        // order comments
        $priority_version = (float)$this->option('priority-version');
        if ($priority_version > 19.1) {
            // for Priority version 20.0
            $data['TINVOICESTEXT_SUBFORM'] = ['TEXT' => $order->get_customer_note()];
        } else {
            // for Priority version 19.1
            $data['TINVOICESTEXT_SUBFORM'][] = ['TEXT' => $order->get_customer_note()];
        }
        // billing customer details
        $customer_data = [
            'PHONE' => $order->get_billing_phone(),
            'EMAIL' => $order->get_billing_email(),
            'ADRS' => $order->get_billing_address_1(),
            'ADRS2' => $order->get_billing_address_2(),
            'ADRS3' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'STATEA' => $order->get_billing_city(),
            'ZIP' => $order->get_billing_postcode(),
        ];
        $data['TINVOICESCONT_SUBFORM'][] = $customer_data;
        $data['doctype'] = 'TINVOICES';
        $data = apply_filters('simply_request_data', $data);
        $doctype = $data['doctype'];
        unset($data['doctype']);
        // make request
        $response = $this->makeRequest('POST', $doctype, ['body' => json_encode($data)], true);
        if ($response['code'] <= 201 && $response['code'] >= 200){
            $body_array = json_decode($response["body"], true);
            $ord_status = $body_array["STATDES"];
            $ord_number = $body_array["IVNUM"];
            $order->update_meta_data('priority_recipe_status', $ord_status);
            $order->update_meta_data('priority_recipe_number', $ord_number);
        }else{
            $message = $response['message'] . '<br>' . $response['body'] . '<br>';
            $mes_arr = json_decode($response['body']);
            if(isset($mes_arr->FORM->InterfaceErrors->text)){
                $message = $mes_arr->FORM->InterfaceErrors->text;
            }
            $order->update_meta_data('priority_recipe_status', $message);
        }
        $order->save();
    }
    public function syncReceiptsCompleted()
    {
        // get all completed orders
        $orders = wc_get_orders(['status' => 'completed']);

        foreach ($orders as $order) {
            $this->syncReceipt($order->get_id());
        }
    }
    function simply_add_custom_price($cart_object)
    {
        if (is_cart()) {
            foreach ($cart_object->get_cart() as $hash => $value) {
                if (!empty($value['custom_data']['realprice'])) {
                    $custom_price = $value['custom_data']['realprice'];
                    // This will be your custom price
                    if (!empty($custom_price) && $custom_price > 0) {
                        $value['data']->set_price($custom_price);
                    }
                    remove_filter('woocommerce_product_get_price', [$this, 'filterPrice'], 10, 2);
                } else {
                    $value['data']->set_price($this->filterPrice($value['data']->get_price(), $value['data']));
                }
            }

        }

    }
    function simply_after_add_to_cart_button()
    {
        echo '<script type="text/javascript" >document.getElementsByName("quantity")[0].value = 1</script>';
    }
    public function filterProductsByPriceList($ids)
    {
        if ($user_id = get_current_user_id()) {
            $meta = get_user_meta($user_id, 'custpricelists', true);
            if ($meta[0]["PLNAME"] === 'no-selected') return $ids;
            $list = empty($meta) ? $this->basePriceCode : $meta[0]["PLNAME"];
            $products = $GLOBALS['wpdb']->get_results('
                SELECT product_sku
                FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                WHERE price_list_code = "' . esc_sql($list) . '"
                AND blog_id = ' . get_current_blog_id(),
                ARRAY_A
            );
            $ids = [];
            // get product id
            foreach ($products as $product) {
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
    public function getPriceLists()
    {
        if (empty(static::$priceList)) {
            static::$priceList = $GLOBALS['wpdb']->get_results('
                SELECT DISTINCT price_list_code, price_list_name FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                WHERE blog_id = ' . get_current_blog_id(),
                ARRAY_A
            );
        }

        return static::$priceList;
    }
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
    public function getProductDataBySku($sku)
    {
        if ($user_id = get_current_user_id()) {
            $meta = get_user_meta($user_id, '_priority_price_list', true);
            if ($meta === 'no-selected') return 'no-selected';
            $list = empty($meta) ? $this->basePriceCode : $meta[0]; // use base price list if there is no list assigned
            $data = $GLOBALS['wpdb']->get_row('
                SELECT price_list_price, price_list_currency,price_list_quant
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
    public function filterPrice($price, $product)
    {
        if(is_cart() || is_checkout()) return $price ;

        $user = wp_get_current_user();
        $transient = $user->ID . $product->get_id();
        $get_transient = get_transient($transient);
        if ($get_transient) {
          //  return (float)$get_transient;
        }
        // get the MCUSTNAME if any else get the cust
        $custname = empty(get_user_meta($user->ID, 'priority_mcustomer_number', true)) ? get_user_meta($user->ID, 'priority_customer_number', true) : get_user_meta($user->ID, 'priority_mcustomer_number', true);
        // check mpartname
	    $mpartname = get_post_meta($product->get_id(), 'mpartname', true);
        $sku = !empty($mpartname) ? $mpartname : $product->get_sku();
        // get special price
        $special_price = $this->getSpecialPriceCustomer($custname, $sku);
        if ($special_price != 0) {
            set_transient($transient, $special_price, 300);
            return $special_price;
        }
        // get the family code by customer discount as fraction
        $family_code = get_post_meta($product->get_id(), 'family_code', true);
        $family_discount = (100.0 - (float)$this->getFamilyProduct($custname, $family_code)) / 100.0;
        // get price list
        $plists = get_user_meta($user->ID, 'custpricelists', true);
        if (empty($plists)) {
            set_transient($transient, $price, 300);
            return $price;
        }
        foreach ($plists as $plist) {
            $data = $GLOBALS['wpdb']->get_row('
                    SELECT price_list_price,price_list_quant
                    FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                    WHERE product_sku = "' . esc_sql($sku) . '"
                    AND price_list_code = "' . esc_sql($plist['PLNAME']) . '"
                    AND blog_id = ' . get_current_blog_id(),
                ARRAY_A
            );
            if (isset($data['price_list_price'])) {
                if ($data['price_list_price'] != 0 && $data['price_list_quant'] == 1) {
                    set_transient($transient, $data['price_list_price'] * $family_discount, 300);
                    return $data['price_list_price'] * $family_discount;
                }
            }
        }

        set_transient($transient, (float)$price * $family_discount, 300);
        return (float)$price * $family_discount;
    }
    public function getFamilyProduct($custname, $family_code)
    {
        $data = $GLOBALS['wpdb']->get_row('
                SELECT discounts
                FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_sync_special_price_product_family
                WHERE familyname = "' . esc_sql($family_code) . '"
                AND custname = "' . esc_sql($custname) . '"
                AND blog_id = ' . get_current_blog_id(),
            ARRAY_A
        );
        if ($data != null) {
            return (float)$data['discounts'];
        }
        return 0;
    }
    public function getSpecialPriceCustomer($custname, $sku)
    {
        $data = $GLOBALS['wpdb']->get_row('
                SELECT price
                FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_special_price_item_customer
                WHERE partname = "' . esc_sql($sku) . '"
                AND custname = "' . esc_sql($custname) . '"
                AND blog_id = ' . get_current_blog_id(),
            ARRAY_A
        );
        if ($data != null && $data['price_list_price'] != 0) {
            return $data['price_list_price'];
        }
        return null;
    }
    public function filterPriceRange($price, $product)
    {
        $variations = $product->get_available_variations();
        $prices = [];
        foreach ($variations as $variation) {
            $data = $this->getProductDataBySku($variation['sku']);
            if ($data !== 'no-selected') {
                if (!empty($data['price_list_price'])) {
                    $prices[] = $data['price_list_price'];
                }
            }
        }
        if (!empty($prices) && min($prices)==max($prices)) {
            return wc_price(min($prices));
        }elseif(!empty($prices)){
	        return wc_price(min($prices)) . ' - ' . wc_price(max($prices));
        }else{
	        return $price;
        }

    }


    /**
     * Display "msg" instead of $0 if the item is free.
     *
     * @param string $price The current price label.
     * @param object $product The product object.
     * @return string
     */
    function custom_price_message( $price, $product ) {
        if ( empty( $product->get_price() ) ) {
            remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 9999);
            $price = __( 'This product is not available in your pricelist', 'p18w' );
        }
        return $price;
    }
    function remove_add_to_cart($is_purchasable, $product) {
        if( $product->get_price() == 0 )
            $is_purchasable = false;
            return $purchasable;   
    }


    function remove_add_to_cart_on_0 ( $purchasable, $product ){
        if( $product->get_price() == 0 )
            $purchasable = false;
        return $purchasable;
    }
    function crf_show_extra_profile_fields($user)
    {
        $priority_customer_number = get_the_author_meta('priority_customer_number', $user->ID);
        $priority_mcustomer_number = get_the_author_meta('priority_mcustomer_number', $user->ID);
        $custpricelists = get_the_author_meta('custpricelists', $user->ID);
        $customer_percents = get_the_author_meta('customer_percents', $user->ID);
	    $customer_paydes = get_the_author_meta('customer_paydes', $user->ID);
        $users = get_users(array('fields' => array('ID')));
        $selected_users = get_user_meta($user->ID, 'select_users', true);
        ?>
        <h3><?php esc_html_e('Priority API User Information', 'p18a'); ?></h3>

        <table class="form-table">
            <tr>
                <th><label for="select_users"><?php esc_html_e('Select User', 'p18a'); ?></label></th>
                <td>
                    <select name="select_users[]" id="select_users" multiple="multiple">
                        <?php
                        foreach ($users as $user1) {
                            $userid = $user1->ID;
                            //$user_info = get_userdata($userid);
                            //$selected = array();
                            $priority_cust_number = get_the_author_meta('priority_customer_number', $userid);
                            $first_name = get_the_author_meta('user_firstname', $userid);
                            $last_name = get_the_author_meta('user_lastname', $userid);
                            $selected = '';
                            if (is_array($selected_users)) {
                                $selected = in_array($userid, $selected_users) ? ' selected="selected" ' : '';
                            }

                            ?>
                            <option value="<?php echo $userid; ?>" <?php echo $selected; ?> ><?php echo $priority_cust_number . ' ' . $first_name . ' ' . $last_name ?></option>
                        <?php } ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="Priority Customer Number"><?php esc_html_e('Priority Customer Number', 'p18a'); ?></label>
                </th>
                <td>
                    <input type="text"

                           id="priority_customer_number"
                           name="priority_customer_number"
                           value="<?php echo esc_attr($priority_customer_number); ?>"
                           class="regular-text"
                    />
                </td>
            </tr>
            <tr>
                <th>
                    <label for="Priority MCustomer Number"><?php esc_html_e('Priority MCustomer Number', 'p18a'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="priority_mcustomer_number"
                           name="priority_mcustomer_number"
                           value="<?php echo esc_attr($priority_mcustomer_number); ?>"
                           class="regular-text"
                    />
                </td>
            </tr>
            <tr>
                <th><label for="Priority Price Lists"><?php esc_html_e('Priority Price Lists', 'p18a'); ?></label></th>
                <td>
                    <input type="text"
                           id="custpricelists"
                           name="custpricelists"
                           value="<?php if (!empty($custpricelists)) {
                               foreach ($custpricelists as $item) {
                                   echo $item['PLNAME'] . ' ';
                               }
                           }; ?>"
                           class="regular-text"
                    />
                </td>
            </tr>
            <tr>
                <th><label for="Priority Percents"><?php esc_html_e('Priority Customer Discounts', 'p18a'); ?></label>
                </th>
                <td>
                    <input type="text"
                           id="customer_percents"
                           name="customer_percents"
                           value="<?php if (!empty($customer_percents)) {
                               foreach ($customer_percents as $item) {
                                   echo $item["PERCENT"] . '% ';
                               }
                           }; ?>"
                           class="regular-text"
                    />
                </td>
            </tr>
            <tr>
                <th><label for="Priority PayDes"><?php esc_html_e('Priority Payment Description', 'p18a'); ?></label>
                </th>
                <td>
                    <input type="text" id="customer_paydes"  name="customer_paydes" value="<?php echo $customer_paydes;?>"class="regular-text"/>
                </td>
            </tr>
        </table>
        <?php
    }
    function crf_update_profile_fields($user_id)
    {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        if (!empty($_POST['select_users'])) {
            update_user_meta($user_id, 'select_users', $_POST['select_users']);
        } else {
            delete_user_meta($user_id, 'select_users');
        }

        if (!empty($_POST['priority_customer_number'])) {
            update_user_meta($user_id, 'priority_customer_number', $_POST['priority_customer_number']);
        } else {
            update_user_meta($user_id, 'priority_customer_number', '');
        }
        if (!empty($_POST['priority_mcustomer_number'])) {
            update_user_meta($user_id, 'priority_mcustomer_number', $_POST['priority_mcustomer_number']);
        } else {
            update_user_meta($user_id, 'priority_mcustomer_number', '');
        }
        if (!empty($_POST['custpricelists'])) {
            $custpricelists = $_POST['custpricelists'];
            $custpricelists_ar = explode(' ', $custpricelists);
           // if (!in_array($custpricelists, $custpricelists_ar)) {
                $custpricelists_result = [];
                foreach ($custpricelists_ar as $key => $cust) {
                    if (!empty($cust))
                        $custpricelists_result[] = array('PLNAME' => $cust);
                }
                update_user_meta($user_id, 'custpricelists', $custpricelists_result);
          //  }
        } else {
            update_user_meta($user_id, 'custpricelists', '');
        }
        if (isset($_POST['customer_percents'])) {
            $customer_percents = $_POST['customer_percents'];
            $customer_percents_ar = explode(' ', $customer_percents);
            $customer_percents_result = [];
            foreach ($customer_percents_ar as $key => $percent) {
                $customer_percents_result[] = array('PERCENT' => $percent);
            }
            update_user_meta($user_id, 'customer_percents', $customer_percents_result);
        } else {
            update_user_meta($user_id, 'customer_percents', '');
        }
        if (!empty($_POST['customer_paydes'])) {
            update_user_meta($user_id, 'customer_paydes', $_POST['customer_paydes']);
        } else {
            update_user_meta($user_id, 'customer_paydes', '');
        }
    }
    function get_shipping_price($order, $is_order)
    {
        $priceDisplay = get_option('woocommerce_tax_display_cart');
        //check if WooCommerce Tax Settings are set
        $set_tax = get_option('woocommerce_calc_taxes');
        // config
        $config = json_decode(stripslashes($this->option('setting-config')));
        $default_product = '000';
        $default_product = $config->SHIPPING_DEFAULT_PARTNAME ?? $default_product;
        $shipping_method = $order->get_shipping_methods();
        $shipping_method = array_shift($shipping_method);
        if (isset($shipping_method)) {
            $data = $shipping_method->get_data();
            $method_title = $data['method_title'];
            $method_id = $data['method_id'];
            $instance_id = $data['instance_id'];

            $price_filed = ($priceDisplay === 'incl' || $set_tax == 'no' ) ? ($is_order ? 'VATPRICE' : 'TOTPRICE') : 'PRICE';
            $shipping_price = ($price_filed === 'PRICE') ? $data['total'] : ($data['total'] + $data['total_tax']);

            $data = [
                // 'PARTNAME' => $this->option('shipping_' . $shipping_method_id, $order->get_shipping_method()),
                $this->get_sku_prioirty_dest_field() => $this->option('shipping_' . $method_id . '_' . $instance_id, $default_product),
                'TQUANT' => 1,
                $price_filed => floatval($shipping_price)
            ];
            if ($is_order) $data += ['DUEDATE' => date('Y-m-d')];
            if ($config->addShipPriceWherePriceZero == 'true') {
                return $data;
            } else {
                return ($shipping_price > 0 ? $data : null);
            }

        } else {
            return null;
        }
    }
    function sync_priority_customers_to_wp()
    {

        if ($this->option('sync_personnel')) {
            $this->sync_priority_personnel_customers_to_wp();
            return;
        }
        // config
        $json = $this->option('sync_customer_to_wp_user_config');
        $json = preg_replace('/\r|\n/', '', trim($json));
        $config = json_decode($json);
        $daysback = !empty((int)$config->days_back) ? (int)$config->days_back : 1;
        $statusdate = !empty($config->statusdate) ? 'STATUSDATE' : 'CREATEDDATE';
        $username_filed = $config->username_field ?? 'CUSTNAME';
        $password_field = $config->password_field;
        $first_name = $config->first_name ?? 'CUSTDES';
        $billing_company = $config->billing_company ?? 'CUSTDES';
        $url_addition_config = !empty($config->additional_url) ? $config->additional_url : '';
        $stamp = mktime(0 - $daysback * 24, 0, 0);
        $bod = urlencode(date(DATE_ATOM, $stamp));
        $url_addition = 'CUSTOMERS?$filter=EMAIL ne \'\' and ' . $statusdate . ' ge ' . $bod . ' ' . $url_addition_config . '&$select=EMAIL,CUSTDES,CUSTNAME,MCUSTNAME,WTAXNUM,ADDRESS,ADDRESS2,STATE,ZIP,PHONE,SPEC1,SPEC2,SPEC19,STATDES,PAYDES&$expand=CUSTPLIST_SUBFORM($select=PLNAME),CUSTDISCOUNT_SUBFORM($select=PERCENT)';
        $args = [];
        $res = apply_filters('simply_customers_url', ['url_addition' => $url_addition,'args' => $args]);
        $url_addition = $res['url_addition'];
        $response = $this->makeRequest('GET', $url_addition, [], true);
        // print_r( $response['status'] );
        if ($response['status']) {
            // decode raw response
            $data = json_decode($response['body_raw'], true)['value'];
            //  echo 'data:';print_r( $data );
            foreach ($data as $user) {
                $username = $user[$username_filed];
                $email = $user['EMAIL'];
                if (!is_email($email)) {
                    continue;
                }
                if (!validate_username($username)) {
                    continue;
                }
                $password = $user[$password_field] ?? '123456';
                $name = $user[$first_name];
                $company = $user[$billing_company];
                $user_obj = get_user_by('login', $username);
                $data = [
                    'ID' => isset($user_obj->ID) ? $user_obj->ID : null,
                    'user_login' => $username,
                    'user_pass' => $password,
                    'email' => $email,
                    'first_name' => $name,
                    //'last_name'  => 'Doe',
                    'user_nickname' => $user['CUSTDES'],
                    'display_name' => $user['CUSTDES'],
                    'billing_company' => $company,
                    'role' => 'customer'
                ];
                if (!isset($user_obj->ID)) {
                    if (!email_exists($email))
                        $data['user_email'] = $email;
                    $user_id = wp_insert_user($data);
                    wp_set_password($password, $user_id);
                } else {
                    $user_id = $user_obj->ID;
                }
                //wp_hash_password( $password);
                wp_update_user(array('ID' => $user_id, 'email' => $email));
                wp_update_user(array('ID' => $user_id, 'user_email' => $email));
                wp_update_user(array('ID' => $user_id, 'user_nickname' => $user['CUSTDES']));
                if (is_wp_error($user_id)) {
                    error_log('This is customer with error ' . $user['CUSTNAME']);
                    continue;
                }
                $user['user_id'] = $user_id;
                apply_filters('simply_sync_priority_customers_to_wp', $user);
                unset($user['user_id']);
                update_user_meta($user_id, 'priority_customer_number', $user['CUSTNAME']);
	            if(empty($user['CUSTPLIST_SUBFORM'])){
		            $user['CUSTPLIST_SUBFORM'][0] = ['PLNAME' => 'בסיס'];
	            }
                update_user_meta($user_id, 'custpricelists', $user['CUSTPLIST_SUBFORM']);
                update_user_meta($user_id, 'priority_mcustomer_number', $user['MCUSTNAME']);
                update_user_meta($user_id, 'customer_percents', $user['CUSTDISCOUNT_SUBFORM']);
                update_user_meta($user_id, 'priority_customer_rank', $user['ZYOU_RANKDES']);
                update_user_meta($user_id, 'billing_address_1', $user['ADDRESS']);
                update_user_meta($user_id, 'billing_address_2', $user['ADDRESS2']);
                update_user_meta($user_id, 'billing_city', $user['STATE']);
                update_user_meta($user_id, 'billing_phone', $user['PHONE']);
                update_user_meta($user_id, 'billing_postcode', $user['ZIP']);
	            update_user_meta($user_id, 'customer_paydes', $user['PAYDES']);
                update_user_meta($user_id, 'first_name', $name);
                update_user_meta($user_id, 'billing_company', $company);


                // $customer = new \WC_Customer($user_id);
                // $customer->set_billing_address_1($user['ADDRESS']);
                // $customer->set_billing_address_2($user['ADDRESS2']);
                // $customer->set_billing_city($user['STATE']);
                // $customer->set_billing_phone($user['PHONE']);
                // $customer->set_billing_postcode($user['ZIP']);
                // $customer->save();
            }
            //$index++;
        }
        if ($this->option('customer_Mcustname') == true) {
            $this->syncCastnameToMcustname();
        }

    }
    function sync_priority_personnel_customers_to_wp()
    {
        // config
        $json = $this->option('sync_customer_to_wp_user_config');
        $json = preg_replace('/\r|\n/', '', trim($json));
        $config = json_decode($json);
        $daysback = !empty((int)$config->days_back) ? (int)$config->days_back : 1;
        $statusdate = !empty($config->statusdate) ? 'STATUSDATE' : 'CREATEDDATE';
        $username_filed = $config->username_field ?? 'NAME';
        $password_field = $config->password_field;
        $url_addition_config = !empty($config->additional_url) ? $config->additional_url : '';
        $stamp = mktime(0 - $daysback * 24, 0, 0);
        $bod = urlencode(date(DATE_ATOM, $stamp));
        $url_addition = 'CUSTOMERS?$filter=EMAIL ne \'\' and ' . $statusdate . ' ge ' . $bod . ' ' . $url_addition_config . '&$select=CUSTNAME,MCUSTNAME,ADDRESS,ADDRESS2,STATE,ZIP,PHONE,SPEC1,SPEC2&$expand=CUSTPLIST_SUBFORM($select=PLNAME),CUSTDISCOUNT_SUBFORM($select=PERCENT),CUSTPERSONNEL_SUBFORM($select=NAME,EMAIL)';
        $args = ['bod'=>$bod,'statusdate'=>$statusdate,'url_addition_config'=>$url_addition_config];
        $res = apply_filters('simply_personnel_url', ['url_addition' => $url_addition,'args' => $args]);
        $url_addition = $res['url_addition'];
        $response = $this->makeRequest('GET', $url_addition, [], true);
        // print_r( $response['status'] );
        if ($response['status']) {
            // decode raw response
            $data = json_decode($response['body_raw'], true)['value'];
            //  echo 'data:';print_r( $data );
            foreach ($data as $user) {
                foreach ($user['CUSTPERSONNEL_SUBFORM'] as $person) {
                    $username = ($username_filed == 'NAME' || $username_filed == 'EMAIL') ? $person[$username_filed] : $user[$username_filed];
                    $email = $person['EMAIL'];
                    if (!is_email($email)) {
                        continue;
                    }
                    if (!validate_username($username)) {
                        continue;
                    }
                    $password = empty($password_field) ? '123456' : (($password_field == 'NAME' || $password_field == 'EMAIL') ? $person[$password_field] : $user[$password_field]);
                    $user_obj = get_user_by('login', $username);
                    $data = [
                        'ID' => isset($user_obj->ID) ? $user_obj->ID : null,
                        'user_login' => $username,
                        'user_pass' => $password,
                        'email' => $email,
                        'first_name' => $person['NAME'],
                        //'last_name'  => 'Doe',
                        'user_nickname' => $person['NAME'],
                        'display_name' => $person['NAME'],
                        'role' => 'customer'
                    ];
                    if (!isset($user_obj->ID)) {
                        if (!email_exists($email))
                            $data['user_email'] = $email;
                        $user_id = wp_insert_user($data);
                        wp_set_password($password, $user_id);
                    } else {
                        $user_id = $user_obj->ID;
                    }
                    //wp_hash_password( $password);
                    wp_update_user(array('ID' => $user_id, 'email' => $email));
                    wp_update_user(array('ID' => $user_id, 'user_email' => $email));
                    wp_update_user(array('ID' => $user_id, 'user_nickname' => $user['NAME']));
                    if (is_wp_error($user_id)) {
                        error_log('This is customer with error ' . $user['CUSTNAME']);
                        continue;
                    }
                    $user['user_id'] = $user_id;
                    apply_filters('simply_sync_priority_customers_to_wp', $user);
                    unset($user['user_id']);
                    update_user_meta($user_id, 'priority_customer_number', $user['CUSTNAME']);
                    update_user_meta($user_id, 'custpricelists', $user['CUSTPLIST_SUBFORM']);
                    update_user_meta($user_id, 'priority_mcustomer_number', $user['MCUSTNAME']);
                    update_user_meta($user_id, 'customer_percents', $user['CUSTDISCOUNT_SUBFORM']);
                    update_user_meta($user_id, 'priority_customer_rank', $user['ZYOU_RANKDES']);
                    update_user_meta($user_id, 'billing_address_1', $user['ADDRESS']);
                    update_user_meta($user_id, 'billing_address_2', $user['ADDRESS2']);
                    update_user_meta($user_id, 'billing_city', $user['STATE']);
                    update_user_meta($user_id, 'billing_phone', $user['PHONE']);
                    update_user_meta($user_id, 'billing_postcode', $user['ZIP']);

                }
            }
        }
    }
    function syncCastnameToMcustname()
    {

        $response = $this->makeRequest('GET', 'CUSTOMERS?$select=CUSTNAME,MCUSTNAME&$filter=MCUSTNAME ne \'\' ');
        if ($response['status']) {
            // decode raw response
            $data = json_decode($response['body_raw'], true)['value'];
            foreach ($data as $user) {
                $Mcustname[$user['MCUSTNAME']][] = $user['CUSTNAME'];
            }
            foreach ($Mcustname as $id => $value) {
                $user_m = get_users(array(
                    'meta_key' => 'priority_customer_number',
                    'meta_value' => $id
                ));
                if (!empty($user_m)) {
                    $user_m_id = $user_m[0]->ID;
                    foreach ($value as $v) {
                        $user_c = get_users(array(
                            'meta_key' => 'priority_customer_number',
                            'meta_value' => (int)$v
                        ));

                        if (!empty($user_c)) {
                            $id = [];
                            $id = get_user_meta($user_m_id, 'select_users');
                            $user_c_id = $user_c[0]->ID;
                            array_push($id, $user_c_id);
                            // echo 'user_c_id=' . $user_c_id .' user_m_id= '.$user_m_id.'<br>';
                            update_user_meta($user_m_id, 'select_users', $id);
                        }
                    }
                }
            }
        }
    }
    function sync_priority_customers_to_wp_orig()
    {
        // default values
        $daysback = 1;
        $url_addition_config = '';
        // config
        $json = $this->option('sync_customer_to_wp_user_config');
        $json = preg_replace('/\r|\n/', '', trim($json));
        $config = json_decode($json);
        $daysback = (int)$config->days_back;
        $username_filed = $config->username_field;
        $password_field = $config->password_field;
        $url_addition_config = $config->additional_url;
        $stamp = mktime(0 - $daysback * 24, 0, 0);
        $bod = urlencode(date(DATE_ATOM, $stamp));
        $url_addition = 'CUSTOMERS?$filter=CREATEDDATE ge ' . $bod . ' ' . $url_addition_config . '&$expand=CUSTPLIST_SUBFORM($select=PLNAME),CUSTDISCOUNT_SUBFORM($select=PERCENT)';
        $response = $this->makeRequest('GET', $url_addition, [], false);
        if ($response['status']) {
            // decode raw response
            $data = json_decode($response['body_raw'], true)['value'];

            foreach ($data as $user) {
                $username = $user[$username_filed];
                $email = $user['EMAIL'];
                if (!is_email($email)) {
                    continue;
                }
                if (!validate_username($username)) {
                    continue;
                }
                $password = $user[$password_field];
                $user_obj = get_user_by('login', $username);
                $data = [
                    'ID' => isset($user_obj->ID) ? $user_obj->ID : null,
                    'user_login' => $username,
                    'user_pass' => $password,
                    'email' => $email,
                    'first_name' => $user['CUSTDES'],
                    //'last_name'  => 'Doe',
                    'user_nicename' => $user['CUSTDES'],
                    'display_name' => $user['CUSTDES'],
                    'role' => 'customer'
                ];
                if (!isset($user_obj->ID)) {
                    $data['user_email'] = $email;
                }
                $user_id = wp_insert_user($data);
                wp_set_password($password, $user_id);
                wp_update_user(array('ID' => $user_id, 'email' => $email));
                wp_update_user(array('ID' => $user_id, 'user_email' => $email));
                if (is_wp_error($user_id)) {
                    error_log('This is customer with error ' . $user['CUSTNAME']);
                    continue;
                }
                update_user_meta($user_id, 'priority_customer_number', $user['CUSTNAME']);
                update_user_meta($user_id, 'custpricelists', $user['CUSTPLIST_SUBFORM']);
                update_user_meta($user_id, 'priority_mcustomer_number', $user['MCUSTNAME']);
                update_user_meta($user_id, 'customer_percents', $user['CUSTDISCOUNT_SUBFORM']);

                // $customer = new \WC_Customer($user_id);
                // $customer->set_billing_address_1($user['ADDRESS']);
                // $customer->set_billing_address_2($user['ADDRESS2']);
                // $customer->set_billing_city($user['STATE']);
                // $customer->set_billing_phone($user['PHONE']);
                // $customer->set_billing_postcode($user['ZIP']);
                // $customer->save();

                update_user_meta($user_id, 'billing_address_1', $user['ADDRESS']);
                update_user_meta($user_id, 'billing_address_2', $user['ADDRESS2']);
                update_user_meta($user_id, 'billing_city', $user['STATE']);
                update_user_meta($user_id, 'billing_phone', $user['PHONE']);
                update_user_meta($user_id, 'billing_postcode', $user['ZIP']);
            }
        }
    }
    function generate_settings($description, $name, $format, $format2)
    {
        ?>
        <tr>
            <td class="p18a-label">
                <?php _e($description, 'p18a'); ?>
            </td>
            <td>
                <input type="checkbox" name="sync_<?php echo $name ?>" form="p18aw-sync"
                       value="1" <?php if ($this->option('sync_' . $name)) echo 'checked'; ?> />
            </td>
            <td></td>
            <td>
                <select name="auto_sync_customer_to_wp_user" form="p18aw-sync">
                    <option value="" <?php if (!$this->option('auto_sync_' . $name)) echo 'selected'; ?>><?php _e('None', 'p18a'); ?></option>
                    <option value="hourly" <?php if ($this->option('auto_sync_' . $name) == 'hourly') echo 'selected'; ?>><?php _e('Every hour', 'p18a'); ?></option>
                    <option value="daily" <?php if ($this->option('auto_sync_' . $name) == 'daily') echo 'selected'; ?>><?php _e('Once a day', 'p18a'); ?></option>
                    <option value="twicedaily" <?php if ($this->option('auto_sync_' . $name) == 'twicedaily') echo 'selected'; ?>><?php _e('Twice a day', 'p18a'); ?></option>
                </select>
            </td>
            <td data-sync-time="auto_sync_customer_to_wp_user">
                <?php
                if ($timestamp = $this->option('auto_sync_' . $name . '_update', false)) {
                    echo(get_date_from_gmt(date($format, $timestamp), $format2));
                } else {
                    _e('Never', 'p18a');
                }
                ?>
            </td>
            <td>
                <a href="#" class="button p18aw-sync"
                   data-sync="auto_sync_<?php echo $name ?>"><?php _e('Sync', 'p18a'); ?></a>
            </td>
            <td>
					<textarea style="width:300px !important; height:45px !important;"
                              name="sync_<?php echo $name . '_config' ?>"
                              form="p18aw-sync"
                              placeholder="">
                        <?php echo stripslashes($this->option('sync_' . $name . '_config')) ?></textarea>
            </td>
            <td>

            </td>
        </tr>

        <?php

    }
    public function syncSpecialPriceItemCustomer()
    {
        $response = $this->makeRequest('GET', 'CUSTPARTPRICEONE?&$select=PARTNAME,CUSTNAME,PRICE', [], $this->option('log_pricelist_priority', true));
        // check response status
        if ($response['status']) {
            // allow multisite
            $blog_id = get_current_blog_id();
            // price lists table
            $table = $GLOBALS['wpdb']->prefix . 'p18a_special_price_item_customer';
            // delete all existing data from price list table
            $GLOBALS['wpdb']->query('DELETE FROM ' . $table);
            // decode raw response
            $data = json_decode($response['body_raw'], true);
            $priceList = [];
            if (isset($data['value'])) {
                foreach ($data['value'] as $list) {
                    $GLOBALS['wpdb']->insert($table, [
                        'partname' => $list['PARTNAME'],
                        'custname' => $list['CUSTNAME'],
                        'price' => (float)$list['PRICE'],
                        'blog_id' => $blog_id
                    ]);
                }
                // add timestamp
                // $this->updateOption('pricelist_priority_update', time());
            }
        } else {
            $this->sendEmailError(
                $this->option('email_error_sync_pricelist_priority'),
                'Error Sync Price Lists Priority',
                $response['body']
            );

        }
    }
    public function syncSpecialPriceProductFamily()
    {
        $response = $this->makeRequest('GET', 'CUSTFAMILYDISCONE?$select=FAMILYNAME,CUSTNAME,PERCENT', [], $this->option('log_productfamily_priority', true));
        // check response status
        if ($response['status']) {
            // allow multisite
            $blog_id = get_current_blog_id();
            // price lists table
            $table = $GLOBALS['wpdb']->prefix . 'p18a_sync_special_price_product_family';
            $table_temp = $GLOBALS['wpdb']->prefix . 'p18a_sync_special_price_product_family_temp';
            // delete all existing data from price list table
            // $GLOBALS['wpdb']->query('DELETE FROM ' . $table);
            // decode raw response
            $data = json_decode($response['body_raw'], true);
            if (isset($data['value'])) {
                foreach ($data['value'] as $list) {
                    $GLOBALS['wpdb']->insert($table_temp, [
                        'familyname' => $list['FAMILYNAME'],
                        'custname' => $list['CUSTNAME'],
                        'discounts' => (float)$list['PERCENT'],
                        'blog_id' => $blog_id
                    ]);
                }

                // truncate SpecialPriceProductFamily
                $GLOBALS['wpdb']->query('TRUNCATE TABLE ' . $table);

                $sql = " INSERT INTO $table
                        SELECT * FROM $table_temp ";
                $GLOBALS['wpdb']->query($sql);

            }
        } else {
            $this->sendEmailError(
                $this->option('email_error_sync_productfamily_priority'),
                'Error special price product family',
                $response['body']
            );

        }
    }
    function add_customer_discount()
    {
        global $woocommerce; //Set the price for user role.
        $user = wp_get_current_user();
        $percentages = get_user_meta($user->ID, 'customer_percents', true);
        if (empty($percentages)) {
            return;
        };
        foreach ($percentages as $item) {
            $percentage =+ is_numeric($item['PERCENT']) ? $item['PERCENT'] : 0.0;
        }
        //check if price display with tax

        $priceDisplay = get_option('woocommerce_tax_display_cart');
        //check if WooCommerce Tax Settings are set
        $set_tax = get_option('woocommerce_calc_taxes');
        if ($priceDisplay === 'incl' || $set_tax == 'no' ) {
            $subtotal = $woocommerce->cart->get_subtotal() + $woocommerce->cart->get_subtotal_tax();
        }
        else{
            $subtotal = $woocommerce->cart->get_subtotal();
        }

        $discount_price = $percentage * $subtotal / 100;
        $woocommerce->cart->add_fee(__('Discount ' . $percentage . '%', 'p18w'), -$discount_price, false, 'standard');
    }
    function get_coupons($data, $order)
    {
        foreach ($order->get_coupon_codes() as $coupon_code) {
            // Get the WC_Coupon object
            $coupon = new \WC_Coupon($coupon_code);
            $discount_type = $coupon->get_discount_type(); // Get coupon discount type
            $coupon_amount = $coupon->get_amount(); // Get coupon amount
            // $coupon_des = $coupon->get_name.''.$coupon->get_description();
            $data['ORDERITEMS_SUBFORM'][] = [
                'PARTNAME' => '000', // change to other item
                //  'PDES' => $coupon_des,
                'VATPRICE' => -1 * floatval($coupon_amount),
                'TQUANT' => -1,
            ];
        }
        return $data;
    }
    function get_sku_prioirty_dest_field()
    {
        $fieldname = 'PARTNAME';
        $fieldname = apply_filters('simply_set_priority_sku_field', $fieldname);
        return $fieldname;
    }
	function save_uri_as_image($base64_image, $title)
	{
		// Split the string.
		$parts = explode(',', $base64_image);
		// Split the first part on semicolon.
		$type = explode(';', $parts[0]);
		// Split the type part on slash.
		$format = explode('/', $type[0]);
		// The extension is the second part of the format.
		$extension = $format[1]; // This should be 'jpeg' for a JPEG image.
		$filename = $title . '.' . $extension;

		// Decode the base64 image.
		list($type, $base64_image) = explode(';', $base64_image);
		list(, $base64_image)      = explode(',', $base64_image);
		$base64_image = str_replace(' ', '+', $base64_image);
		$decoded_image = base64_decode($base64_image);

		// Save the image to the uploads directory.
		$upload_dir = wp_upload_dir();
		$file_path = $upload_dir['path'] . '/' . $filename;
		file_put_contents($file_path, $decoded_image);

		if (file_exists($file_path)) {
			$wp_filetype = wp_check_filetype($filename, null);
			$attachment = array(
				'guid' => $upload_dir['baseurl'] . '/' . $filename,
				'post_mime_type' => $wp_filetype['type'],
				'post_title' => sanitize_file_name($filename),
				'post_content' => '',
				'post_type' => 'listing_type',
				'post_status' => 'inherit',
			);
			$attach_id = wp_insert_attachment($attachment, $file_path);
			return [$attach_id, $filename];
		}
		$attachment = array(
			'post_mime_type' => $type,
			'post_title' => preg_replace('/\.[^.]+$/', '', basename($filename)),
			'post_content' => '',
			'post_status' => 'inherit',
			'guid' => $upload_dir['basedir'] . '/' . basename($filename)
		);
		$attach_id = wp_insert_attachment($attachment, $file_path);
		// Include the image.php file for the function wp_generate_attachment_metadata().
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		//Generate the metadata for the attachment, and update the database record.
		$attach_data = wp_generate_attachment_metadata( $attach_id, $file_path);
		wp_update_attachment_metadata( $attach_id, $attach_data );
		return [$attach_id, $filename];
	}
    public function getStringBetween($str, $from, $to)
    {
        $sub = substr($str, strpos($str, $from) + strlen($from), strlen($str));
        return substr($sub, 0, strpos($sub, $to));
    }
    function load_image($ext_file, $image_base_url, $priority_version, $sku, $search_field)
    {
        if ($priority_version >= 21.0 || $ext_file == '') {
            $response = $this->makeRequest('GET', 'LOGPART?$select=EXTFILENAME&$filter=' . $search_field . ' eq \'' . urlencode($sku) . '\'', [], $this->option('log_items_priority', true));
            $data = json_decode($response['body']);
            $ext_file = $data->value[0]->EXTFILENAME;
        }

        if (!empty($ext_file)) {
            if (filter_var($ext_file, FILTER_VALIDATE_URL) != false) {
                $file_path = $ext_file;
                $file_info = pathinfo($file_path);
                $file_name = $sku . '.' . $file_info['extension'];
                $url = wp_get_upload_dir()['baseurl'] . '/simplyCT/' . $file_name;
                if (file_exists($url) == false) {
                    $attach_id = download_attachment($sku, $ext_file);
                } else {
                    $attach_id = attachment_url_to_postid($url);
                }
            } else {
                $priority_image_path = $ext_file; //  "..\..\system\mail\pics\00093.jpg"
                $priority_image_path = str_replace('\\', '/', $priority_image_path);
                $images_url = 'https://' . $this->option('url') . '/primail';
                if (!empty($image_base_url)) {
                    $images_url = $image_base_url;
                }
                $product_full_url = str_replace('../../system/mail', $images_url, $priority_image_path);
                $product_full_url = str_replace('‏‏', '%E2%80%8F%E2%80%8F', $product_full_url);
                $is_uri = strpos('1' . $product_full_url, 'http') > 0 ? false : true;
                if ($priority_version >= 21.0 && $is_uri) {
                    $file = $this->save_uri_as_image($priority_image_path, $sku);
                    $attach_id = $file[0];
                    $file_name = $file[1];
                    if ($attach_id == 0) {
                        $attach_id = attachment_url_to_postid(wp_get_upload_dir()['baseurl'] . '/simplyCT/' . $file_name);
                    }
                } else {
                    $file_path = $ext_file;
                    $file_info = pathinfo($file_path);
                    $file_name = $sku . '.' . $file_info['extension'];
                    $url = wp_get_upload_dir()['baseurl'] . '/simplyCT/' . $file_name;
                    $attach_id = attachment_url_to_postid($url);

                }
                if ($attach_id == 0) {
                    $attach_id = download_attachment($sku, $product_full_url);
                }
            }
            $file = wp_get_upload_dir()['baseurl'] . '/simplyCT/' . $file_name;
            $arr = [$attach_id, $file];
            return $arr;
        }
    }

}

