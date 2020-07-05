<?php
/**
 * Created by PhpStorm.
 * User: רועי
 * Date: 05/07/2020
 * Time: 23:51
 */
PriorityWoocommerceAPI;
class Obligo extends \PriorityAPI\API{
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
		add_filter( 'woocommerce_get_item_data', [$this,'render_custom_data_on_cart_checkout'], 10, 2 );
		add_filter( 'woocommerce_add_cart_item_data',[$this,'split_product_individual_cart_items'], 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', [$this,'add_custom_price']);
		add_action( 'p18a_request_front_obligo',[$this,'request_front_obligo']);

		add_action('init', function() {
			add_rewrite_endpoint('obligo', EP_ROOT | EP_PAGES);
		});
		add_action( 'wp_enqueue_scripts', function(){
			wp_enqueue_script( 'my_custom_script',  P18AW_ASSET_URL . 'frontend.js',['jquery']);

			wp_localize_script( 'my_custom_script', 'ajax_object', array('ajaxurl' => admin_url( 'admin-ajax.php' )));
		});
		function my_custom_flush_rewrite_rules() {
			add_rewrite_endpoint( 'obligo', EP_ROOT | EP_PAGES );
			flush_rewrite_rules();
		}
		register_activation_hook( __FILE__, 'my_custom_flush_rewrite_rules' );
		register_deactivation_hook( __FILE__, 'my_custom_flush_rewrite_rules' );
		add_filter('woocommerce_account_menu_items', function($items) {
			$items['obligo'] = __('Obligo', 'woo');
			return $items;
		});

		add_action('woocommerce_account_obligo_endpoint', function() {

			?>

			<div class="woocommerce-MyAccount-content-obligo">

				<p>Obligo</p>
				<?php
				$foo = 2;
				do_action('p18a_request_front_obligo');?>

			</div>

			<?php

		});
	}
	/****** add same item with different price to cart *********/
	function split_product_individual_cart_items( $cart_item_data, $product_id ){
		if($_POST['obligoSubmit']){
	    $unique_cart_item_key = uniqid();
		$cart_item_data['unique_key'] = $unique_cart_item_key;
		//$cart_item_data['_other_options']['product-price'] = rand(1,22) ;
		}
		return $cart_item_data;
	}
	function add_custom_price( $cart_object ) {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$custom_price = $cart_item['_other_options']['product-price']; // This will be your custom price
			$cart_item['data']->set_price($custom_price);
		}
	}
	function render_custom_data_on_cart_checkout( $cart_data, $cart_item = null ) {
		$custom_items = array();
		/* Woo 2.4.2 updates */
		if( !empty( $cart_data ) ) {
			$custom_items = $cart_data;
		}
		if( isset( $cart_item['_other_options']['product-ivnum'] ) ) {
			$custom_items[] = array( "name" => "IVNUM", "value" => $cart_item['_other_options']['product-ivnum'] );
		}
		return $custom_items;
	}
	function request_front_obligo() {

	$current_user             = wp_get_current_user();
	$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );

	$additionalurl = 'OBLIGO?&$expand=OBLIGO_FNCITEMS_SUBFORM&$filter=CUSTNAME eq \'' . $priority_customer_number . '\'';
	$args= [];
	$response = $this->makeRequest( "GET", $additionalurl, $args, true );
	$data     = json_decode( $response['body'] );

	if ( ! empty( $data->value ) ) {
		echo "<table>";
		foreach ( $data->value[0] as $key => $value ) {
			if ( $key == 'OBLIGO_FNCITEMS_SUBFORM' ) {
				continue;
			}
			echo "<tr>";
			echo "<td>" . $key . "</td><td>" . $value . "</td>";
			echo "</tr>";
		}
		echo "</table>";
		echo "<form action='' method='post'>";

		echo '<input type="hidden" name="action" value="my_action_obligo" />';
		echo "<button type='submit' name='obligoSubmit' id='obligoSubmit' style='float: right;' disabled> Open Payment </button>";

		echo "<table> <tr>";
		echo "<th></th><th>BALDATE</th> <th>FNCNUM</th> <th>IVNUM</th> <th>DETAILS</th> <th>SUM1</th>";
		echo "</tr>";
		global $woocommerce;
		$items     = $woocommerce->cart->get_cart();
		$cartcheck = empty( $items ) ? '' : 'disabled';
		$i         = 0;
		foreach ( $data->value[0]->OBLIGO_FNCITEMS_SUBFORM as $key => $value ) {
			echo "<tr>";
			$arr = array( 'sum' => $value->SUM1, 'ivnum' => $value->IVNUM );
			echo '<td><input type="checkbox" name="obligo_chk-'. $i .'" class="obligo_checkbox" data-sum=' . $value->SUM1 . ' data-IVNUM=' . $value->IVNUM . ' $cartcheck value="obligo_chk_sum' . $i . '"></td>';
			// echo "<input type='hidden'name='obligo_chk_sum" . $i . "' value='" . $value->SUM1 . "'>";
			// echo "<input type='hidden'name='obligo_chk_ivnum" . $i . "' value='" . $value->IVNUM . "'>";

			echo "<input type='hidden' name='obligo_chk_sum[]' value='" . $value->SUM1 . "'>";
			echo "<input type='hidden' name='obligo_chk_ivnum[]' value='" . $value->IVNUM . "'>";

			foreach ( $value as $Fkey => $Fvalue ) {
				if ( $Fkey == 'BALDATE' || $Fkey == 'FNCNUM' || $Fkey == 'IVNUM' || $Fkey == 'DETAILS' || $Fkey == 'SUM1' ) {
					if ( $Fkey == 'BALDATE' ) {
						$timestamp = strtotime( $Fvalue );
						echo "<td>" . date( 'd/m/y', $timestamp ) . "</td>";
					} else {
						echo "<td>" . $Fvalue . "</td>";
					}
				}
			}
			echo "</tr>";
			$i ++;
		}
		echo "</table>";
		echo "</form>";
		if ( isset( $_POST['obligoSubmit'] ) ) {
			$obligo_chk = $_POST['obligo_chk_sum'];
			$ivnums = $_POST['obligo_chk_ivnum'];
			foreach ( $obligo_chk as $key => $value ) {
				if(isset($_POST['obligo_chk-'.$key])){
					$cart_item_data = [];
					$cart_item_data['_other_options']['product-price'] = $value ;
					$cart_item_data['_other_options']['product-ivnum'] = $ivnums[$key] ;
					$cart           = WC()->cart->add_to_cart( 3048, 1, null, null, $cart_item_data );
				}
			}
		}
	}
}

}