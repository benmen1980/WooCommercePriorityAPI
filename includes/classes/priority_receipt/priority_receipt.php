<?php
class Priority_receipt extends \PriorityAPI\API{
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

		add_action( 'wp_ajax_my_action_exporttoexcel_receipt', [$this,'my_action_exporttoexcel_receipt'] );
		add_action ( 'wp_ajax_nopriv_my_action_exporttoexcel_receipt', [$this,'my_action_exporttoexcel_receipt'] );
		add_action( 'p18a_request_front_priorityreceipt',[$this,'request_front_priorityreceipt']);

		add_action( 'wp_enqueue_scripts', function() {
			//wp_enqueue_script('priority-woo-api-frontend', P18AW_ASSET_URL.'frontend.js', array('jquery'), time());
			wp_enqueue_style( 'priority-woo-api-style', P18AW_ASSET_URL.'style.css', time() );
			wp_enqueue_script('priority-woo-api-jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js');
			wp_enqueue_style( 'priority-woo-api-jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
		});

		add_action('init', function() {
			add_rewrite_endpoint('priority-receipt', EP_ROOT | EP_PAGES);
		});

		function my_custom_flush_rewrite_rules_priorityreceipt() {
			add_rewrite_endpoint( 'priority-receipt', EP_ROOT | EP_PAGES );
			flush_rewrite_rules();
		}
		register_activation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityreceipt' );
		register_deactivation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityreceipt' );
		add_filter('woocommerce_account_menu_items', function($items) {
			$items['priority-receipt'] = __('Priority Receipts', 'p18w');
			return $items;
		});

		add_action('woocommerce_account_priority-receipt_endpoint', function() {

			?>

			<div class="woocommerce-MyAccount-content-priority-orders">

				<p><?php _e('Priority Receipts','p18w'); ?></p>
				<?php do_action('p18a_request_front_priorityreceipt'); ?>

			</div>

			<?php

		});
	}
	function request_front_priorityreceipt() {

		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );

		if(isset($_POST['from-date']) && isset($_POST['to-date'])) {
			$fdate = date(DATE_ATOM, strtotime($_POST['from-date']));
			$tdate = date(DATE_ATOM, strtotime($_POST['to-date']));

			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

			$additionalurl = 'TINVOICES?$filter=IVDATE gt '.$from_date.' and IVDATE lt '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\' &$select=IVNUM,DEBIT,IVTYPE,ACCNAME,QPRICE,IVDATE&$expand=TPAYMENT2_SUBFORM($select=PAYMENTCODE,PAYMENTNAME,QPRICE)';
		} else {
			//by default, get invoices from beginning of year till today
			$begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
			$todaydate = urlencode(date(DATE_ATOM, strtotime('now')));

			$additionalurl = 'TINVOICES?$filter=IVDATE gt '.$begindate.' and IVDATE lt '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\' &$select=IVNUM,DEBIT,IVTYPE,ACCNAME,QPRICE,IVDATE&$expand=TPAYMENT2_SUBFORM($select=PAYMENTCODE,PAYMENTNAME,QPRICE)';
			
		}
		
        //$additionalurl = 'TINVOICES?$select=IVNUM,DEBIT,IVTYPE,ACCNAME,QPRICE,IVDATE&$expand=TPAYMENT2_SUBFORM($select=PAYMENTCODE,PAYMENTNAME,QPRICE)';

		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );
		
		$in_fdata = isset($_POST['from-date']) ? $_POST['from-date'] : '';
		$in_tdata = isset($_POST['to-date']) ? $_POST['to-date'] : '';
		echo "<form class='priority_form' method='POST'>";
		echo __('FROM:','p18w')." <input type='text' name='from-date' id='from-date' placeholder='mm/dd/yyyy' value='".$in_fdata."' required />";
		echo __('TO:','p18w')." <input type='text' name='to-date' id='to-date' placeholder='mm/dd/yyyy' value='".$in_tdata."' required />";
		echo "<input type='submit' value='".__('submit','p18w')."' name='date'/>";
		echo "</form>";
		echo "<a class='btn_export_excel' href='".admin_url( 'admin-ajax.php' )."?action=my_action_exporttoexcel_receipt&from_date=".$in_fdata."&to_date=".$in_tdata."' target='_blank'> 
		".__('Export Excel','p18w')." </a>";
		echo "<table>";
		echo "<tr class='row-titles'><td></td><td>".__('Date','p18w')."</td><td>".__('IVNUM','p18w')."</td><td>".__('DEBIT','p18w')."</td><td>".__('IVTYPE','p18w')."</td><td>".__('ACCNAME','p18w')."</td><td>".__('QPRICE','p18w')."</td></tr>";
		$i = 1;

		foreach ($data->value as $key => $value) {
			echo "<tr><td>";
			if(!empty($value->TPAYMENT2_SUBFORM)) {
				echo "<div class='cust-toggle plus' id='content-".$i."'>+</div>";
			}
			echo "</td><td>".date( 'd/m/y',strtotime($value->IVDATE))."</td><td>".$value->IVNUM."</td><td>".$value->DEBIT."</td><td>".$value->IVTYPE."</td><td>".$value->ACCNAME."</td><td>".$value->QPRICE."</td></tr>";
				
				if(!empty($value->TPAYMENT2_SUBFORM)) {
					echo "<tr class='content_value subform-content-".$i."' style='display:none;'><td colspan='8'>";
					echo "<table>";
					echo "<tr><td>".__('PAYMENT CODE','p18w')."</td><td>".__('PAYMENT NAME','p18w')."</td><td>".__('QPRICE','p18w')."</td></tr>";
					foreach($value->TPAYMENT2_SUBFORM as $subform) {
						echo "<tr><td>".$subform->PAYMENTCODE."</td><td>".$subform->PAYMENTNAME."</td><td>".$subform->QPRICE."</td></tr>";
					}
					echo "</table>";
					echo "</td></tr>";
				}
			$i++;
		}
		echo "</table>";
	}
	

	function my_action_exporttoexcel_receipt() {

		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );
		
		if(!empty($_REQUEST['from_date']) && !empty($_REQUEST['to_date'])) {
			$fdate = date(DATE_ATOM, strtotime($_REQUEST['from_date']));
			$tdate = date(DATE_ATOM, strtotime($_REQUEST['to_date']));
			
			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

        	$additionalurl = 'TINVOICES?$filter=IVDATE gt '.$from_date.' and IVDATE lt '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\' &$select=IVNUM,DEBIT,IVTYPE,ACCNAME,QPRICE,IVDATE&$expand=TPAYMENT2_SUBFORM($select=PAYMENTCODE,PAYMENTNAME,QPRICE)';
		} else {
			$begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
			$todaydate = urlencode(date(DATE_ATOM, strtotime('now')));

			$additionalurl = 'TINVOICES?$filter=IVDATE gt '.$begindate.' and IVDATE lt '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\' &$select=IVNUM,DEBIT,IVTYPE,ACCNAME,QPRICE,IVDATE&$expand=TPAYMENT2_SUBFORM($select=PAYMENTCODE,PAYMENTNAME,QPRICE)';
			
		}

		//$additionalurl = 'TINVOICES?$select=IVNUM,DEBIT,IVTYPE,ACCNAME,QPRICE,IVDATE&$expand=TPAYMENT2_SUBFORM($select=PAYMENTCODE,PAYMENTNAME,QPRICE)';
		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );

		header('Content-Type: application/csv');
	    // tell the browser we want to save it instead of displaying it
	    header('Content-Disposition: attachment; filename="export.csv";');
		$f = fopen('php://output', 'w');
		$array=array('Date','IVNUM','DEBIT','IVTYPE','ACCNAME','QPRICE','IVDATE','PAYMENTCODE','PAYMENTNAME','QPRICE');
		fputcsv($f, $array);
		foreach ($data->value as $key => $value) {
			if(!empty($value->TPAYMENT2_SUBFORM)) {
				foreach($value->TPAYMENT2_SUBFORM as $subform) {
					$array=array(date( 'd/m/y',strtotime($value->IVDATE)),$value->IVNUM,$value->DEBIT,$value->IVTYPE,$value->ACCNAME,$value->QPRICE,$subform->PAYMENTCODE,$subform->PAYMENTNAME,$subform->QPRICE);
					fputcsv($f, $array);
				}
			}else {
				$array=array(date( 'd/m/y',strtotime($value->IVDATE)),$value->IVNUM,$value->DEBIT,$value->IVTYPE,$value->ACCNAME,$value->QPRICE);
				fputcsv($f, $array);
			}
		}

	    wp_die(); // this is required to terminate immediately and return a proper response
	}
}