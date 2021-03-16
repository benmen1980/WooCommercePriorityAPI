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
		add_action( 'wp_ajax_my_action_exporttoexcel_document', [$this,'my_action_exporttoexcel_document'] );
		add_action ( 'wp_ajax_nopriv_my_action_exporttoexcel_document', [$this,'my_action_exporttoexcel_document'] );
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
		
		// get the date inputs
		if(isset($_POST['from-date']) && isset($_POST['to-date'])) {
			$fdate = date(DATE_ATOM, strtotime($_POST['from-date']));
			$tdate = date(DATE_ATOM, strtotime($_POST['to-date']));

			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

        	$additionalurl = 'CUSTOMERS('."'".$priority_customer_number."'".')?$filter=CURDATE gt '.$from_date.' and CURDATE lt '.$to_date.' &$select=CUSTNAME&$expand=CUSTEXTFILE_SUBFORM';
		} else {
			$additionalurl = 'CUSTOMERS('."'".$priority_customer_number."'".')?$select=CUSTNAME&$expand=CUSTEXTFILE_SUBFORM';
		}
        
		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );
		
		$in_fdata = isset($_POST['from-date']) ? $_POST['from-date'] : '';
		$in_tdata = isset($_POST['to-date']) ? $_POST['to-date'] : '';
		echo "<form method='POST'>";
		echo __('FROM:','p18w')." <input type='text' name='from-date' id='from-date' placeholder='mm/dd/yyyy' value='".$in_fdata."' required />";
		echo __('TO:','p18w')." <input type='text' name='to-date' id='to-date' placeholder='mm/dd/yyyy' value='".$in_tdata."' required />";
		echo "<input type='submit' value='".__('submit','p18w')."' name='date'/>";
		echo "</form>";
		echo "<a href='".admin_url( 'admin-ajax.php' )."?action=my_action_exporttoexcel_document&from_date=".$in_fdata."&to_date=".$in_tdata."' target='_blank' 
		style='display: block; margin-bottom:5px; background: #4E9CAF; padding: 10px; text-align: center; border-radius: 5px; color: white; font-weight: bold; line-height: 25px; float: right; text-decoration: none;'> 
		".__('Export Excel','p18w')." </a>";
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
	

	function my_action_exporttoexcel_document() {
		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );

		if(!empty($_REQUEST['from_date']) && !empty($_REQUEST['to_date'])) {
			$fdate = date(DATE_ATOM, strtotime($_REQUEST['from_date']));
			$tdate = date(DATE_ATOM, strtotime($_REQUEST['to_date']));
			
			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

	   		$additionalurl = 'CUSTOMERS('."'".$priority_customer_number."'".')?$filter=CURDATE gt '.$from_date.' and CURDATE lt '.$to_date.' &$select=CUSTNAME&$expand=CUSTEXTFILE_SUBFORM';
		} else {
			$additionalurl = 'CUSTOMERS('."'".$priority_customer_number."'".')?$select=CUSTNAME&$expand=CUSTEXTFILE_SUBFORM';
		}
		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );

		header('Content-Type: application/csv');
	    // tell the browser we want to save it instead of displaying it
	    header('Content-Disposition: attachment; filename="export.csv";');
		$f = fopen('php://output', 'w');
		$array=array('Date','File Name','File Path','Suffix');
		fputcsv($f, $array);
		foreach ($data->CUSTEXTFILE_SUBFORM as $key => $value) {
			if($value->EXTFILENAME != ''){
				$priority_image_path = $value->EXTFILENAME;
				$images_url =  'https://'. $this->option('url').'/primail';
				$product_full_url    = str_replace( '../../system/mail', $images_url, $priority_image_path );
			}
				$array=array($value->CURDATE,$value->EXTFILEDES,$product_full_url,$value->SUFFIX);
				fputcsv($f, $array);
			
		}

	    wp_die(); // this is required to terminate immediately and return a proper response
	}
}