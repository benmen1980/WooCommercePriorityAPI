<?php

PriorityWoocommerceAPI;
class Priority_orders_excel extends \PriorityAPI\API{
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
		add_action( 'wp_ajax_my_action_exporttoexcel', [$this,'my_action_exporttoexcel'] );
		add_action( 'p18a_request_front_priorityorders',[$this,'request_front_priorityorders']);

		add_action('init', function() {
			add_rewrite_endpoint('priority-orders', EP_ROOT | EP_PAGES);
		});
		
		function my_custom_flush_rewrite_rules_priorityorders() {
			add_rewrite_endpoint( 'priority-orders', EP_ROOT | EP_PAGES );
			flush_rewrite_rules();
		}
		register_activation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityorders' );
		register_deactivation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityorders' );
		add_filter('woocommerce_account_menu_items', function($items) {
			$items['priority-orders'] = __('Priority Orders', 'woo');
			return $items;
		});

		add_action('woocommerce_account_priority-orders_endpoint', function() {

			?>

			<div class="woocommerce-MyAccount-content-priority-orders">

				<p>Priority Orders</p>
				<?php
				
				do_action('p18a_request_front_priorityorders');?>

			</div>

			<?php

		});
	}
	function request_front_priorityorders() {

		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );

		$additionalurl = 'ORDERS?$filter=CUSTNAME eq \'02\'';
		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );
		
		echo "<a href='".admin_url( 'admin-ajax.php' )."?action=my_action_exporttoexcel' target='_blank' style='display: block; width: 115px; height: 45px; background: #4E9CAF; padding: 10px; text-align: center; border-radius: 5px; color: white; font-weight: bold; line-height: 25px; float: right; text-decoration: none;'> Export Excel </a>";
		echo "<table>";
		echo "<tr><td>Date</td><td>Order Name</td><td>BOOK Number</td><td>Quantity</td><td>Price</td><td>Percentage</td><td>Discounted Price</td><td>VAT</td><td>Total Price</td></tr>";
		foreach ($data->value as $key => $value) {
			
			echo "<tr><td>".date( 'd/m/y',strtotime($value->CURDATE))."</td><td>".$value->ORDNAME."</td><td>".$value->BOOKNUM."</td><td>".$value->QUANT."</td><td>".$value->QPRICE."</td><td>".$value->PERCENT."</td><td>".$value->DISPRICE."</td><td>".$value->VAT."</td><td>".$value->TOTPRICE."</td></tr>";

		}
		echo "</table>";
	}	
	

	function my_action_exporttoexcel() {

	   $additionalurl = 'ORDERS?$filter=CUSTNAME eq \'02\'';
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );
		header('Content-Type: application/csv');
	    // tell the browser we want to save it instead of displaying it
	    header('Content-Disposition: attachment; filename="export.csv";');
		$f = fopen('php://output', 'w');
		$array=array('Date','Order Name','BOOK Number','Quantity','Price','Percentage','Discounted Price','VAT','Total Price');
		fputcsv($f, $array);
		foreach ($data->value as $key => $value) {
			$array=array($value->CURDATE,$value->ORDNAME,$value->BOOKNUM,$value->QUANT,$value->QPRICE,$value->PERCENT,$value->DISPRICE,$value->VAT,$value->TOTPRICE);
			fputcsv($f, $array);
		}
		
	    wp_die(); // this is required to terminate immediately and return a proper response
	}
}