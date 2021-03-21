<?php 
class Priority_documents extends \PriorityAPI\API{

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
		add_action( 'p18a_request_front_prioritydocuments',[$this,'request_front_prioritydocuments']);

		add_action( 'wp_enqueue_scripts', function() {
			//wp_enqueue_script('priority-woo-api-frontend', P18AW_ASSET_URL.'frontend.js', array('jquery'), time());
			wp_enqueue_style( 'priority-woo-api-style', P18AW_ASSET_URL.'style.css', time() );
			wp_enqueue_script('priority-woo-api-jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js');
			wp_enqueue_style( 'priority-woo-api-jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
		});

		add_action('init', function() {
			add_rewrite_endpoint('priority-documents', EP_ROOT | EP_PAGES);
		});

		function my_custom_flush_rewrite_rules_prioritydocuments() {
			add_rewrite_endpoint( 'priority-documents', EP_ROOT | EP_PAGES );
			flush_rewrite_rules();
		}
		register_activation_hook( __FILE__, 'my_custom_flush_rewrite_rules_prioritydocuments' );
		register_deactivation_hook( __FILE__, 'my_custom_flush_rewrite_rules_prioritydocuments' );
		add_filter('woocommerce_account_menu_items', function($items) {
			$items['priority-documents'] = __('Priority Customer Documents', 'p18w');
			return $items;
		});

		add_action('woocommerce_account_priority-documents_endpoint', function() {

			?>

			<div class="woocommerce-MyAccount-content-priority-orders">

				<p><?php _e('Priority Customer Documents','p18w'); ?></p>
				<?php do_action('p18a_request_front_prioritydocuments'); ?>

			</div>

			<?php

		});
	}
	function request_front_prioritydocuments() {
		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );
		
 		$additionalurl = 'CUSTOMERS('."'".$priority_customer_number."'".')?$select=CUSTNAME&$expand=CUSTEXTFILE_SUBFORM';
		
        
		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );
		
		echo "<table>";
		echo "<tr class='row-titles'><td>".__('Date','p18w')."</td><td>".__('File Name','p18w')."</td><td>".__('File Path','p18w')."</td><td>".__('Suffix','p18w')."</td></tr>";
		$i = 1;
		if(!empty($data->CUSTEXTFILE_SUBFORM)) {
			foreach ($data->CUSTEXTFILE_SUBFORM as $key => $value) {
				if($value->EXTFILENAME != ''){
                    $priority_image_path = $value->EXTFILENAME;
                    $images_url =  'https://'. $this->option('url').'/primail';
					$product_full_url    = str_replace( '../../system/mail', $images_url, $priority_image_path );
                }
				echo "<tr><td>".date( 'd/m/y',strtotime($value->CURDATE))."</td><td>".$value->EXTFILEDES."</td><td><a href="."'".$product_full_url."'"."target='blank'>".$product_full_url."</a></td><td>".$value->SUFFIX."</td></tr>";
				$i++;
			}
		}
		echo "</table>";
	}
	
}