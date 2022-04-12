<?php
use PriorityWoocommerceAPI\WooAPI;
/**
 * Created by PhpStorm.
 * User: רועי
 * Date: 05/07/2020
 * Time: 23:51
 */
class simplypay extends \PriorityAPI\API{
    private static $instance; // api instance
    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }
    public function run()
    {

    }
    private function __construct()
    {
        if(isset($_GET['c'])||isset($_GET['i'])){
            add_filter( 'wc_add_to_cart_message_html', [$this,'remove_add_to_cart_message']);
            // remove this if you want to allow adding paymnets to cart with different iv or price
            add_filter( 'woocommerce_add_to_cart_validation', [$this,'simply_custom_add_to_cart_before'] );
        }
        if(isset($_GET['currency'])){
            add_filter( 'woocommerce_currency',[$this,'simply_change_existing_currency_symbol'],9999,2 );
        }
        add_filter( 'woocommerce_add_cart_item_data',[$this,'simplypay'], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [$this,'custom_field_update_order_item_meta'], 20, 4 );
        // simplypay filters
        add_action( 'woocommerce_before_calculate_totals', [$this,'add_custom_price']);
        add_filter( 'woocommerce_checkout_fields' , [$this,'custom_override_checkout_fields'],10,1 );
        add_filter( 'woocommerce_checkout_get_value',[$this,'override_checkout__fields'],10,2);
        add_filter( 'woocommerce_get_item_data', [$this,'render_custom_data_on_cart_checkout'], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [$this,'custom_field_update_order_item_meta'], 20, 4 );
        add_action( 'woocommerce_checkout_update_order_meta', [$this,'my_custom_checkout_field_update_order_meta']);
        add_action( 'woocommerce_after_order_notes',[$this,'my_custom_checkout_field']);
    }
    /****** add same item with different price to cart *********/
    /*****************************************/
    public function my_enqueue() {

        wp_enqueue_script( 'ajax-script',  plugin_dir_url(__FILE__).'/my-account.js', array('jquery') );

        wp_localize_script( 'ajax-script', 'my_ajax_object',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ),
                'woo_checkout_url' => wc_get_checkout_url()
                //'woo_cart_url' => get_permalink( wc_get_page_id( 'cart' ) )
            )
        );
    }
    public function add_item_from_url(){
        $cart_item_data['_other_options']['product-price'] = 177.77 ;
        $cart_item_data['_other_options']['product-ivnum'] = 'MY_IV000001' ;
        $product_id = wc_get_product_id_by_sku('PAYMENT');
        $cart           = WC()->cart->add_to_cart( $product_id, 1, null, null, $cart_item_data );
    }
    public function my_action() {
        $data = $_POST['data'];
        array_shift($data);
        $response = true;
        $product_ivnum = array();
        foreach( WC()->cart->get_cart() as $cart_item_key => $values ) {
            $product_ivnum[] = $values['_other_options']['product-ivnum'];
        }
        foreach ( $data as $key => $value ) {
            $arr= explode('#',$value['name']);
            $cart_item_data = [];
            $cart_item_data['_other_options']['product-price'] = $arr[0] ;
            $cart_item_data['_other_options']['product-ivnum'] = $arr[1] ;

            //check that this item is not already in cart
            if(!(in_array($arr[1], $product_ivnum))){
                $product_id = wc_get_product_id_by_sku('PAYMENT');
                $cart           = WC()->cart->add_to_cart( $product_id, 1, '0', array(), $cart_item_data );
            }
            if(!$cart){
                $response = false;
            }
        }
        if($response){
            WC()->session->set(
                'session_vars',
                array('ordertype' => 'obligo_payment' )
            );
        }
        $data = [$response];
        wp_send_json_success($data);

        wp_die(); // this is required to terminate immediately and return a proper response
    }
    // simply pay module
    // remove check out fields
    function custom_override_checkout_fields( $fields ) {
        unset($fields['billing']['billing_company']);
        unset($fields['billing']['billing_address_2']);
        unset($fields['billing']['billing_country']);
        unset($fields['billing']['billing_state']);
        return $fields;
    }
    // modify fields
    function override_checkout__fields($input, $key ) {
        // here wee need to get data from  session
        $retrive_data = WC()->session->get( 'session_vars' );
        $first_name = $retrive_data['first_name'] ?? '';
        $last_name  = $retrive_data['last_name'] ?? '';
        $billing_email = $retrive_data['email'] ?? '' ;
        $billing_phone = $retrive_data['phone'] ?? '';
        $billing_address_1 = $retrive_data['street_address'] ?? '';
        $billing_city      = $retrive_data['city'] ?? '';
        $billing_postcode  = $retrive_data['postcode'] ?? '';
        global $current_user;
        switch ($key) :
            case 'billing_first_name':
                return $first_name;
                break;
            case 'billing_last_name':
                return $last_name;
                break;
            case 'billing_email':
                return $billing_email;
                break;
            case 'billing_phone':
                return $billing_phone;
                break;
            case 'billing_address_1';
                return $billing_address_1;
                break;
           /* case 'billing_country';
                return 'Israel';
                break;*/
            case 'billing_company';
                return '';
                break;
            case 'billing_address_2';
                return '  ';
                break;
            case 'billing_city';
                return $billing_city;
                break;
            case 'billing_postcode';
                return $billing_postcode;
                break;
        endswitch;
    }
    function simplypay(){
        if(isset($_GET['i'])){
            global $wpdb;
            $sql_result = $wpdb->get_results(
                'select
                            p.order_id,
                            p.order_item_id,
                            p.order_item_name,
                            p.order_item_type,
                            pm.meta_value    
                                                   
                            from
                            '.$wpdb->prefix.'woocommerce_order_items as p,
                            '.$wpdb->prefix.'woocommerce_order_itemmeta as pm,
                             '.$wpdb->prefix.'posts
                            where order_item_type = \'line_item\' 
                            and p.order_item_id = pm.order_item_id
                            and pm.meta_key = \'product-ivnum\' 
                            and p.order_item_id = pm.order_item_id 
                            and pm.meta_value = \''.$_GET['i'].'\'
                            and p.order_id = '.$wpdb->prefix.'posts.ID
                            and '.$wpdb->prefix.'posts.post_status <> \'wc-cancelled\'
                            group by
                            p.order_item_id'
            );
            if(sizeof($sql_result)>0){
                if(empty($_GET['debug'])) {
                    wp_die(__('This invoice had already been paid!', 'simply'));
                    $url = home_url() . '/duplicate-invoice';
                    wp_redirect($url);
                    exit;
                }
            }
            $cart_item_data['_other_options']['product-ivnum'] = $_GET['i'] ;
            // get the customer info according to the IVNUM
            $customer_info = [
                'docno'           => $_GET['i'],
                'price'           => $_GET['pr'],
                'first_name'      => '',
                'last_name'       => '',
                'street'  => '',
                'postcode'        => '',
                'city'            => '',
                'phone'           => '',
                'email'           => '',
                'data'            => ''
            ];
            // need to filter here
            $customer_info = apply_filters( 'simply_request_customer_data', $customer_info );
            $cart_item_data['_other_options']['product-price'] = $customer_info['price'] ;
            WC()->session->set(
                'session_vars',
                array(
                    'ordertype'       => 'Recipe',
                    'custname'        => isset($_GET['c']) ? $_GET['c'] : null,
                    'first_name'      => $customer_info['first_name'],
                    'last_name'       => $customer_info['last_name'],
                    'street_address'  => $customer_info['street'],
                    'postcode'        => $customer_info['postcode'],
                    'city'            => $customer_info['city'],
                    'phone'           => $customer_info['phone'],
                    'email'           => $customer_info['email'],
                    'data'            => $customer_info['data']  // for extra custom fields
                )
            );
            return $cart_item_data;
        }
    }
    function remove_add_to_cart_message( $message ){
        return '';
    }
    function simply_custom_add_to_cart_before( $cart_item_data ) {
        global $woocommerce;
        $woocommerce->cart->empty_cart();
        // Do nothing with the data and return
        return true;
    }
    function simply_change_existing_currency_symbol(  $currency ) {
        return $_GET['currency']; // <=== HERE define the targeted currency code
    }
    function add_custom_price( $cart_object ) {
        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            if(isset($cart_item['_other_options'])){
                $custom_price = $cart_item['_other_options']['product-price']; // This will be your custom price
                // currency
                if (class_exists('WOOCS')) {
                    global $WOOCS;
                    if ($WOOCS->is_multiple_allowed) {
                        $currrent = $WOOCS->current_currency;
                        if ($currrent != $WOOCS->default_currency) {

                            $currencies = $WOOCS->get_currencies();
                            $rate = $currencies[$currrent]['rate'];
                            $custom_price = $custom_price / $rate;
                        }
                    }
                }
                $cart_item['data']->set_price($custom_price);
            }
        }
    }
    function render_custom_data_on_cart_checkout( $cart_data, $cart_item = null ) {
        $custom_items = array();
        /* Woo 2.4.2 updates */
        if( !empty( $cart_data ) ) {
            $custom_items = $cart_data;
        }
        $item_detail = 'IVNUM_';
        //require P18AW_CLASSES_DIR . 'wooapi.php';
        $config = json_decode(stripslashes($this->option('setting-config')));
        $item_detail = $config->simply_pay_note ?? 'IVNUM';
        if( isset( $cart_item['_other_options']['product-ivnum'] ) ) {
            $custom_items[] = array( "name" => $item_detail, "value" => $cart_item['_other_options']['product-ivnum'] );
        }
        return $custom_items;
    }
    function custom_field_update_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! isset( $values['_other_options'] ) )
            return;
        $custom_data = $values['_other_options'];
        $ivnum = $custom_data['product-ivnum'];
        if ( $ivnum )
            $item->update_meta_data( __('product-ivnum'), $ivnum );

        //return $cart_item_data;
    }
    function my_custom_checkout_field_update_order_meta( $order_id )
    {
        if (!empty($_POST['priority_custname'])) {
            update_post_meta($order_id, 'priority_custname', sanitize_text_field($_POST['priority_custname']));
        }
    }
    function my_custom_checkout_field( $checkout ) {
        echo '<div style="display: none" id="my_custom_checkout_field"><h2>' . __('My Field') . '</h2>';
        woocommerce_form_field( 'priority_custname', array(
            'type'          => 'text',
            'class'         => array('priority_custname form-row-hide'),
            'label'         => __('Fill in this field'),
            'placeholder'   => __('Enter something'),
            'default'       => $_GET['c'],
        ), $checkout->get_value('priority_custname'));
        echo '</div>';
    }

}


