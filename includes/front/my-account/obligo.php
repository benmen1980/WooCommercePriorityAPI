<?php

use PriorityWoocommerceAPI\WooAPI;
add_action('wp_ajax_get_invoice_url', [Obligo::class, 'get_invoice_url_callback']);
add_action('wp_ajax_nopriv_get_invoice_url', [Obligo::class, 'get_invoice_url_callback']);

/**
 * Created by PhpStorm.
 * User: רועי
 * Date: 05/07/2020
 * Time: 23:51
 */
class Obligo extends \PriorityAPI\API
{
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
        //return is_admin() ? $this->backend(): $this->frontend();
    }

    private function __construct()
    {
        add_filter('woocommerce_get_item_data', [$this, 'render_custom_data_on_cart_checkout'], 10, 2);
        add_filter('woocommerce_add_cart_item_data', [$this, 'split_product_individual_cart_items'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'add_custom_price']);
        add_action('p18a_request_front_obligo', [$this, 'request_front_obligo']);

        //	add_action('woocommerce_before_checkout_form',[$this,'add_item_from_url']);
        // JS
        add_action('wp_ajax_my_action', [$this, 'my_action']);
        add_action('wp_enqueue_scripts', [$this, 'my_enqueue']);

        add_action('wp_ajax_unset_customer_payment_session', [$this, 'unset_customer_payment_session']);
        //add_action( 'wp_ajax_nopriv_unset_customer_payment_session', [$this,'unset_customer_payment_session'] );

        add_action('init', function () {
            add_rewrite_endpoint('obligo', EP_ROOT | EP_PAGES);
        });
        add_action('wp_enqueue_scripts', function () {
			wp_enqueue_script('priority-woo-api-frontend', P18AW_ASSET_URL.'frontend.js', array('jquery'), time());

            wp_localize_script('my_custom_script', 'ajax_object', array('ajaxurl' => admin_url('admin-ajax.php')));

            wp_localize_script('ajax-scripts', 'ajax_obj', array( 
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			));
        });
        function my_custom_flush_rewrite_rules()
        {
            add_rewrite_endpoint('obligo', EP_ROOT | EP_PAGES);
            flush_rewrite_rules();
        }

        register_activation_hook(__FILE__, 'my_custom_flush_rewrite_rules');
        register_deactivation_hook(__FILE__, 'my_custom_flush_rewrite_rules');
        add_filter('woocommerce_account_menu_items', function ($items) {
            $items['obligo'] = __('Obligo', 'p18w');
            return $items;
        });

        add_action('woocommerce_account_obligo_endpoint', function () {

            ?>

            <div class="woocommerce-MyAccount-content-obligo">

                <p><?php _e('Obligo', 'p18w'); ?></p>
                <?php
                $foo = 2;
                do_action('p18a_request_front_obligo'); ?>

            </div>

            <?php

        });

        // menu manipulation
        add_filter('wp_nav_menu_items', [$this, 'add_search_form'], 999, 999);

        add_action('woocommerce_check_cart_items', [$this, 'skyverge_empty_cart_notice']);


        add_action('wp_head', [$this, 'add_content_after_header']);

        // update order item by cart item data
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'custom_field_update_order_item_meta'], 20, 4);

        //check all orders that have payment item
        add_action('woocommerce_thankyou', [$this, 'check_payment_order']);
        //after payment obligo, put back products to bag , products from session
        add_action('woocommerce_thankyou', [$this, 'create_order']);

        //redirect cart to checkout if  תשלום חוב
        add_action('template_redirect', [$this, 'redirect_visitor']);

        //disable add to cart button if תשלום חוב
        add_filter('woocommerce_is_purchasable', [$this, 'disable_add_to_cart_if_obligo_set'], 10, 2);

        // modify check out fields
        //add_filter( 'woocommerce_checkout_fields' , [$this,'custom_override_checkout_fields'],10,1 );
        //add_filter( 'woocommerce_checkout_get_value',[$this,'override_checkout__fields'],10,2);
    }
    /****** add same item with different price to cart *********/
    /*****************************************/
    public function my_enqueue()
    {
        wp_enqueue_script('ajax-script', plugin_dir_url(__FILE__) . '/my-account.js', array('jquery'));
        wp_enqueue_style('obligo-style', plugin_dir_url(__FILE__) . '/obligo-style.css', time());

        wp_localize_script('ajax-script', 'my_ajax_object',
            array('ajax_url' => admin_url('admin-ajax.php'),
                'woo_checkout_url' => wc_get_checkout_url(),
                'woo_home_url' => get_home_url()
                //'woo_cart_url' => get_permalink( wc_get_page_id( 'cart' ) )
            )
        );
    }
    // public function add_item_from_url(){
    // 	$cart_item_data['_other_options']['product-price'] = 177.77 ;
    // 	$cart_item_data['_other_options']['product-ivnum'] = 'MY_IV000001' ;
    // 	$product_id = wc_get_product_id_by_sku('PAYMENT');
    // 	$cart           = WC()->cart->add_to_cart( $product_id, 1, null, null, $cart_item_data );
    // }
    public function my_action()
    {
        $data = $_POST['data'];
        array_shift($data);
        $response = true;
        $product_ivnum = array();
        foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
            $product_ivnum[] = $values['_other_options']['product-ivnum'];
        }
        //unset session cart item
        if (isset(WC()->session)) {
            WC()->session->set('cart_items', null);
        }
        //save in session product in cart , and remove them to add payment product only
        foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
            $_product = wc_get_product($values['data']->get_id());
            $pdt_sku = $_product->get_sku();
            if ($pdt_sku != 'PAYMENT') {
                if (isset(WC()->session)) {
                    $retrive_cart_items = WC()->session->get('cart_items');
                    if (!empty($retrive_cart_items)) {
                        array_push($retrive_cart_items, [$_product->id, $values['quantity'], $variation_id, $variation, $cart_item_data]);
                        WC()->session->set('cart_items', $retrive_cart_items);
                    } else {
                        WC()->session->set('cart_items', array([$_product->id, $values['quantity'], $variation_id, $variation, $cart_item_data]));
                    }
                    WC()->cart->remove_cart_item($cart_item_key);

                }
            } else {
                //if already in cart remove it
                WC()->cart->remove_cart_item($cart_item_key);
            }

        }
        //print_r($data);
        foreach ($data as $key => $value) {
            $arr = explode('#', $value['name']);
            $cart_item_data = [];
            $cart_item_data['_other_options']['product-price'] = $arr[0];
            $cart_item_data['_other_options']['product-ivnum'] = $arr[1];
            $cart_item_data['_other_options']['product-date'] = $arr[2];
            $cart_item_data['_other_options']['product-detail'] = $arr[3];


            $product_id = wc_get_product_id_by_sku('PAYMENT');
            $cart = WC()->cart->add_to_cart($product_id, 1, '0', array(), $cart_item_data);

            if (!$cart) {
                $response = false;
            }
        }
        if ($response) {
            WC()->session->set(
                'session_vars',
                array('ordertype' => 'obligo_payment')
            );
        }
        $data = [$response];
        wp_send_json_success($data);

        wp_die(); // this is required to terminate immediately and return a proper response
    }

    function unset_customer_payment_session()
    {
        if (isset(WC()->session)) {
            $retrive_data = WC()->session->get('session_vars');
            if (!empty($retrive_data['ordertype'])) {
                WC()->session->set('session_vars', array('ordertype' => ''));
                WC()->cart->empty_cart();
                $retrive_cart_items = WC()->session->get('cart_items');
                if (!empty($retrive_cart_items)) {
                    foreach ($retrive_cart_items as $item) {
                        $pdt_id = $item[0];
                        $qtty = $item[1];
                        WC()->cart->add_to_cart($pdt_id, $qtty);
                    }
                }
            }
        }
    }

    function split_product_individual_cart_items($cart_item_data, $product_id)
    {
        if (isset($_POST['obligoSubmit'])) {
            $unique_cart_item_key = uniqid();
            $cart_item_data['unique_key'] = $unique_cart_item_key;
            //$cart_item_data['_other_options']['product-price'] = rand(1,22) ;
        }
        return $cart_item_data;
    }

    function add_custom_price($cart_object)
    {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['_other_options'])) {
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

    function render_custom_data_on_cart_checkout($cart_data, $cart_item = null)
    {
        $custom_items = array();
        /* Woo 2.4.2 updates */
        if (!empty($cart_data)) {
            $custom_items = $cart_data;
        }
        $item_detail = 'IVNUM_';
        //require P18AW_CLASSES_DIR . 'wooapi.php';
        $config = json_decode(stripslashes($this->option('setting-config')));
        $item_detail = $config->simply_pay_note ?? 'IVNUM';
        if (isset($cart_item['_other_options']['product-ivnum'])) {
            $custom_items[] = array("name" => $item_detail, "value" => $cart_item['_other_options']['product-ivnum']);
        }
        return $custom_items;
    }

    function request_front_obligo()
    {
        $current_user = wp_get_current_user();
        $priority_customer_number = get_user_meta($current_user->ID, 'priority_customer_number', true);
        // $priority_customer_number = apply_filters('simply_priority_customer_number_obligo', $current_user);
        $additionalurl = 'OBLIGO?$select=CUSTDES,MAX_OBLIGO,ACC_DEBIT,CUST&$expand=OBLIGO_FNCITEMS_SUBFORM&$filter=CUSTNAME eq \'' . $priority_customer_number . '\'';
        $args = [];
        $response = $this->makeRequest("GET", $additionalurl, $args, true);
        $data = json_decode($response['body']);

        if (!empty($data->value)) {
            //The arrangement of the object is adjusted to the order
            $order_data = ['CUSTDES', 'MAX_OBLIGO', 'ACC_DEBIT', 'CUST'];
            $sorted_data = new stdClass();
            foreach ($order_data as $key) {
                $sorted_data->$key = property_exists($data->value[0], $key) ? $data->value[0]->$key : null;
            }            
            echo "<table style='width: 50%;'>";
            foreach ($sorted_data as $key => $value) {
                if ($key == 'OBLIGO_FNCITEMS_SUBFORM' || $key == 'CUST') {
                    continue;
                }
                echo "<tr>";
                if ( $key == 'MAX_OBLIGO' || $key == 'ACC_DEBIT' )
                    $value = number_format($value, 2, '.', ',') . ' ש"ח';
                echo "<td>" . __($key, 'p18w') . "</td><td>" . __($value, 'p18w') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<form id='simply-obligo' action='' method='post'>";

            echo '<input type="hidden" name="action" value="my_action_obligo" />';

            $save_number = $priority_customer_number;

            $accounts_table  = apply_filters( 'simply_accounts_receivable_table', $priority_customer_number );

            if ($accounts_table !== $save_number && !empty($accounts_table)) 
                return;

            echo "<table> <tr>";
            echo "<th></th><th>" . esc_html__('BALDATE', 'p18w') . "</th> <th>" . esc_html__('FNCDATE', 'p18w') . "</th> <th>" . esc_html__('FNCNUM', 'p18w') . "</th> <th>" . esc_html__('IVNUM', 'p18w') . "</th> <th>" . esc_html__('DETAILS', 'p18w') . "</th> <th>" . esc_html__('SUM1', 'p18w') . "</th><th></th>";
            echo "</tr>";
            global $woocommerce;
            $items = $woocommerce->cart->get_cart();
            $retrive_data = WC()->session->get('session_vars');
            $retrieve_ivnum = WC()->session->get('pdt_ivnum');

            $pdts_in_cart = array();
            if (!empty($retrive_data['ordertype'])) {
                foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                    $pdts_in_cart[] = $cart_item['_other_options']['product-ivnum'];
                }
            }

            $i = 1;
            $todayDate = new DateTime();
            foreach ($data->value[0]->OBLIGO_FNCITEMS_SUBFORM as $key => $value) {
                //Checking whether the date has passed
                $fncDate = new DateTime($value->FNCDATE);
                $red = ($todayDate > $fncDate) ? 'red' : ''; 

                echo "<tr class='pass_date " . $red . "'>";
                $arr = array('sum' => $value->SUM1, 'ivnum' => $value->IVNUM);

                //check if already pay for this ivnum
                $disabled = '';
                if (!empty($retrieve_ivnum)) {
                    if (in_array($value->IVNUM, $retrieve_ivnum)) {
                        $disabled = 'disabled="disabled"';
                    } else {
                       // $disabled = '';
                    }
                }
                if (!empty($pdts_in_cart)) {
                    if (in_array($value->IVNUM, $pdts_in_cart)) {
                        $checked = 'checked';
                    } else {
                        $checked = '';
                    }
                }

                $date = $value->BALDATE;
                $createDate = new DateTime($date);
                $strip = $createDate->format('d/m/y');
                if (!isset($checked)) {
                    $checked = '';
                }
                echo '<td><input type="checkbox" ' . $checked . ' ' . $disabled . ' name="' . $value->SUM1 . '#' . $value->IVNUM . '#' . $strip . '#' . $value->DETAILS . '" class="obligo_checkbox" data-sum=' . $value->SUM1 . ' data-IVNUM=' . $value->IVNUM . ' value="obligo_chk_sum' . $i . '"></td>';
                //echo "<input type='hidden'name='obligo_chk_sum" . $i . "' value='" . $value->SUM1 . "'>";
                //echo "<input type='hidden'name='obligo_chk_ivnum" . $i . "' value='" . $value->IVNUM . "'>";

                //echo "<input type='hidden' name='obligo_chk_sum[]' value='" . $value->SUM1 . "'>";
                //echo "<input type='hidden' name='obligo_chk_ivnum[]' value='" . $value->IVNUM . "'>";

                // Create the array object in a new order
                $order = ['BALDATE', 'FNCDATE', 'FNCNUM', 'IVNUM', 'FNCPATNAME', 'DETAILS', 'SUM1', 'CODE', 'FNCREF2', 'FNCIREF1', 'FNCIREF2'];
                $sortedValue = new stdClass();
                foreach ($order as $key) {
                    $sortedValue->$key = property_exists($value, $key) ? $value->$key : null;
                }

                foreach ($sortedValue as $Fkey => $Fvalue) {
                    if ($Fkey == 'BALDATE' || $Fkey == 'FNCDATE' || $Fkey == 'FNCNUM' || $Fkey == 'IVNUM' || $Fkey == 'DETAILS' || $Fkey == 'SUM1') {
                        if ($Fkey == 'BALDATE') {
                            $timestamp = strtotime($Fvalue);
                            echo "<td>" . date('d/m/y', $timestamp) . "</td>";
                        } elseif ($Fkey == 'FNCDATE') {
                            $timestampFnc = strtotime($Fvalue);
                            echo "<td>" . date('d/m/y', $timestampFnc) . "</td>";
                        } else {
                            if ( $Fkey == 'SUM1' )
                                $Fvalue = number_format($Fvalue, 2, '.', ',') . ' ש"ח';
                            echo "<td>" . $Fvalue . "</td>";
                        }
                    }
                }
                                                    			
                echo "<td>
                        <button style='font-size: 13px!important;' type='button' class='btn_open_ivnum' data-ivnum='" . htmlspecialchars($value->IVNUM, ENT_QUOTES, 'UTF-8') . "'>" 
                            . __('Presentation of the invoice', 'p18w') . "
                            <div class='loader_wrap'>
                                <div class='loader_spinner'>
                                    
                                    <div class='line'></div>
                                    <div class='line'></div>
                                    <div class='line'></div>
                                </div>
                            </div>
                        </button>
                    </td>";
                echo "</tr>";
                $i++;
            }
            echo "</table>"; ?>
            <p>
                <?php echo __('Total Payment: ', 'p18w'); ?>
                <span class="total_payment_checked">0</span>
                <span><?php echo get_woocommerce_currency_symbol(); ?></span>
            </p>
            <?php echo "<button type='submit' name='obligoSubmit' id='obligoSubmit' style='float: right;' disabled>" . __('Pay now', 'p18w') . "</button>";

            echo "</form>";

        }
        return 'Recipet opened...';
    }

    function add_search_form($items, $args)
    {
        $session = WC()->session->get('session_vars');
        if ($session['ordertype'] == 'obligo_payment') {
            $items .= '<li class="menu-item">'
                . '<p><span style="color: #0000ff;"><em>Obligo payment</em></span></p>'

                . '</li>';
        }
        return $items;
    }

    function add_content_after_header()
    {
        if (isset(WC()->session)) {
            $retrive_data = WC()->session->get('session_vars');
            if (!empty($retrive_data) && ($retrive_data['ordertype'] == "obligo_payment")) {
                ?>
                <div class="banner_obligo">
                    <h2>
                        <?php echo __('לתשומת ליבך! הנך נמצא במצב של תשלום חוב', 'p18w'); ?>
                    </h2>
                    <button class="back-to-purchase">
                        <?php echo __('יציאה ממצב תשלום חוב', 'p18w'); ?>
                    </button>
                </div>
            <?php }
        }

    }

    function skyverge_empty_cart_notice()
    {

        if (WC()->cart->get_cart_contents_count() == 0) {
            if (isset(WC()->session)) {
                WC()->session->set(
                    'session_vars',
                    array(
                        'ordertype' => ''));
                // $retrive_cart_items = WC()->session->get( 'cart_items' );
                // if(!empty($retrive_cart_items)){
                // 	WC()->session->set( 'cart_items', null );
                // }
            }
        }

    }

    function custom_field_update_order_item_meta($item, $cart_item_key, $values, $order)
    {
        if (!isset($values['_other_options']))
            return;
        $custom_data = $values['_other_options'];
        $ivnum = $custom_data['product-ivnum'];
        if ($ivnum)
            $item->update_meta_data(__('product-ivnum'), $ivnum);

        //return $cart_item_data;
    }

    function check_payment_order()
    {
        global $wpdb;
        $results = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE post_type LIKE 'shop_order'");
        $pdt_ivnums = array();
        // Loop through each order post object
        foreach ($results as $result) {
            $order_id = $result->ID; // The Order ID
            $order = new WC_Order($order_id);
            if (empty($order)) return false;
            $items = $order->get_items();
            foreach ($order->get_items() as $item_id => $item) {
                $product_ivnum = $item->get_meta('product-ivnum');
                $pdt_ivnums[] = $product_ivnum;
                //put in session all 'payment' items from all order after checkout
                WC()->session->set(
                    'pdt_ivnum', $pdt_ivnums);
            }
        }
        //after checkout, empty ordertype session
        //remove because we empty session afrer sync payment
        // WC()->session->set(
        // 	'session_vars',
        // 	array(
        // 		'ordertype' => ''));

    }

    //after payment obligo, put back products to bag , products from session
    function create_order($order_id)
    {
        if (isset(WC()->session)) {
            $session = WC()->session->get('session_vars');
            if ($session['ordertype'] == 'obligo_payment') {
                $retrive_cart_items = WC()->session->get('cart_items');
                if (!empty($retrive_cart_items)) {
                    foreach ($retrive_cart_items as $item) {
                        $pdt_id = $item[0];
                        $qtty = $item[1];
                        WC()->cart->add_to_cart($pdt_id, $qtty);

                    }
                }
                //after checkout, empty ordertype session - unset the session after sync payment in wooapi
                // WC()->session->set(
                //     'session_vars',
                //     array(
                //         'ordertype' => ''));
                // echo 'cart content';
                // print_r($retrive_cart_items);
            } else {
                $retrive_cart_items = WC()->session->get('cart_items');
                if (!empty($retrive_cart_items)) {
                    WC()->session->set('cart_items', null);
                }
            }
        }

    }

    //precent user to add to bag product when תשלום חוב
    function disable_add_to_cart_if_obligo_set($is_purchasable, $product)
    {

        $pdt_sku = $product->get_sku();
        if (isset(WC()->session)) {
            $session = WC()->session->get('session_vars');
            if (!empty($session['ordertype']) && $session['ordertype'] == 'obligo_payment') {
                if ($pdt_sku != 'PAYMENT') {
                    return false;
                }
            }
        }
        return $is_purchasable;
    }

    function redirect_visitor()
    {
        if (is_page('cart') || is_cart()) {
            $retrive_data = WC()->session->get('session_vars');
            // check if תשלום חוב session is set
            if (!empty($retrive_data) && ($retrive_data['ordertype'] == "obligo_payment")) {
                global $woocommerce;
                $checkout_url = $woocommerce->cart->get_checkout_url();
                wp_safe_redirect($checkout_url);
                exit;
            }
        }
    }

    public function create_hub2sdk_invoice_request($invoice_number){
        $username = $this->option('username');
        $password = $this->option('password');
        $url = 'https://'.$this->option('url');
        if( false !== strpos( $url, 'p.priority-connect.online' ) ) {
            $url = 'https://p.priority-connect.online/wcf/service.svc';
        }
        $tabulaini = $this->option('application');
        $language = '1';
        $company = $this->option('environment');
        $devicename = 'devicename';
        $appid = $this->option('X-App-Id');
        $appkey = $this->option('X-App-Key');
		
        $array['IVNUM'] = $invoice_number;
        $array['credentials']['appname'] = 'demo';
        $array['credentials']['username'] = $username;
        $array['credentials']['password'] = $password;
        $array['credentials']['url'] = $url;
        $array['credentials']['tabulaini'] = $tabulaini;
        $array['credentials']['language'] = $language;
        $array['credentials']['profile']['company'] = $company;
        $array['credentials']['devicename'] = $devicename;
        $array['credentials']['appid'] = $appid;
        $array['credentials']['appkey'] = $appkey;

        $curl = curl_init();
        curl_setopt_array( $curl, array(
            CURLOPT_URL            => 'prinodehub1-env.eba-gdu3xtku.us-west-2.elasticbeanstalk.com/printCinvoice',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode($array),
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json'
            ),
        ) );

        $response = curl_exec( $curl );

        curl_close( $curl );
        return $response;

    }

	public static function get_invoice_url_callback() {
		if (isset($_POST['ivnum'])) {
			$ivnumber = sanitize_text_field($_POST['ivnum']);

			// Get an instance of the class
			$instance = self::instance();
			// Call your function to get the order URL
			try {
				$response = json_decode($instance->create_hub2sdk_invoice_request($ivnumber));
				$url = $response->invoice_url ?? '';
				if (!empty($url)) {
					wp_send_json_success($url);
				} else {
					wp_send_json_error(['message' => 'URL not found']);
				}
			} catch (Exception $e) {
				wp_send_json_error(['message' => $e->getMessage()]);
			}
	
		} else {
			wp_send_json_error($response);
		}
	}
}