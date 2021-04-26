<?php
class Priority_invoices extends \PriorityAPI\API{
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

		add_action( 'wp_ajax_my_action_exporttoexcel_invoice', [$this,'my_action_exporttoexcel_invoice'] );
		add_action ( 'wp_ajax_nopriv_my_action_exporttoexcel_invoice', [$this,'my_action_exporttoexcel_invoice'] );
		add_action( 'p18a_request_front_priorityinvoices',[$this,'request_front_priorityinvoices']);

		add_action( 'wp_enqueue_scripts', function() {
			//wp_enqueue_script('priority-woo-api-frontend', P18AW_ASSET_URL.'frontend.js', array('jquery'), time());
			wp_enqueue_style( 'priority-woo-api-style', P18AW_ASSET_URL.'style.css', time() );
			wp_enqueue_script('priority-woo-api-jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js');
			wp_enqueue_style( 'priority-woo-api-jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
		});

		add_action('init', function() {
			add_rewrite_endpoint('priority-invoices', EP_ROOT | EP_PAGES);
		});

		function my_custom_flush_rewrite_rules_priorityinvoices() {
			add_rewrite_endpoint( 'priority-invoices', EP_ROOT | EP_PAGES );
			flush_rewrite_rules();
		}
		register_activation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityinvoices' );
		register_deactivation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityinvoices' );
		add_filter('woocommerce_account_menu_items', function($items) {
			$items['priority-invoices'] = __('Priority Invoices', 'p18w');
			return $items;
		});

		add_action('woocommerce_account_priority-invoices_endpoint', function() {

			?>

			<div class="woocommerce-MyAccount-content-priority-orders">

				<p><?php _e('Priority Invoices','p18w'); ?></p>
				<?php do_action('p18a_request_front_priorityinvoices'); ?>

			</div>

			<?php

		});
	}
	function request_front_priorityinvoices() {

		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );
		
		// get the date inputs
		if(isset($_POST['from-date']) && isset($_POST['to-date'])) {
			$fdate = date(DATE_ATOM, strtotime($_POST['from-date']));
			$tdate = date(DATE_ATOM, strtotime($_POST['to-date']. ' +1 day'));

			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

        	$additionalurl = 'AINVOICES?$filter=IVDATE ge '.$from_date.' and IVDATE le '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=AINVOICEITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE)';
		} else {
			//by default, get invoices from beginning of year till today
			$begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
			$todaydate = urlencode(date(DATE_ATOM, strtotime('now')));

			$additionalurl = 'AINVOICES?$filter=IVDATE ge '.$begindate.' and IVDATE le '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=AINVOICEITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE)';
			//$additionalurl = 'AINVOICES?$filter=CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=AINVOICEITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE)';
		}
        
		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );

		$in_fdata = isset($_POST['from-date']) ? $_POST['from-date'] : '';
		$in_tdata = isset($_POST['to-date']) ? $_POST['to-date'] : '';
		echo "<form class='priority_form' method='POST'>";
		echo __('FROM:','p18w')." <input type='text' name='from-date' id='from-date' placeholder='dd/mm/yyyy' value='".$in_fdata."' required/>";
		echo __('TO:','p18w')." <input type='text' name='to-date' id='to-date' placeholder='dd/mm/yyyy' value='".$in_tdata."' required />";
		echo "<input type='submit' value='".__('submit','p18w')."' name='date'/>";
		echo "</form>";
		echo "<a class='btn_export_excel' href='".admin_url( 'admin-ajax.php' )."?action=my_action_exporttoexcel_invoice&from_date=".$in_fdata."&to_date=".$in_tdata."' target='_blank'> 
		".__('Export Excel','p18w')." </a>";
		echo "<table>";
		echo "<tr class='row-titles'><td></td><td>".__('Date','p18w')."</td><td>".__('Customer Name','p18w')."</td><td>".__('IVNUM','p18w')."</td><td>".__('QPRICE','p18w')."</td><td>".__('DISCOUNT','p18w')."</td><td>".__('DISPRICE','p18w')."</td><td>".__('VAT','p18w')."</td><td>".__('Total Price','p18w')."</td></tr>";
		$i = 1;

		foreach ($data->value as $key => $value) {
			echo "<tr><td>";
			if(!empty($value->AINVOICEITEMS_SUBFORM)) {
				echo "<div class='cust-toggle plus' id='content-".$i."'>+</div>";
			}
			echo "</td><td>".date( 'd/m/y',strtotime($value->IVDATE))."</td><td>".$value->CUSTNAME."</td><td>".$value->IVNUM."</td><td>".$value->QPRICE."</td><td>".$value->DISCOUNT."</td><td>".$value->DISPRICE."</td><td>".$value->VAT."</td><td>".$value->TOTPRICE."</td></tr>";
				
				if(!empty($value->AINVOICEITEMS_SUBFORM)) {
					echo "<tr class='content_value subform-content-".$i."' style='display:none;'><td colspan='8'>";
					echo "<table>";
					echo "<tr><td>".__('Part Name','p18w')."</td><td>".__('Quantity','p18w')."</td><td>".__('Price','p18w')."</td></tr>";
					foreach($value->AINVOICEITEMS_SUBFORM as $subform) {
						echo "<tr><td>".$subform->PARTNAME."</td><td>".$subform->QUANT."</td><td>".$subform->PRICE."</td></tr>";
					}
					echo "</table>";
					echo "</td></tr>";
				}
			$i++;
		}
		echo "</table>";
	}
	

	function my_action_exporttoexcel_invoice() {
		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );
		
		if(!empty($_REQUEST['from_date']) && !empty($_REQUEST['to_date'])) {
			$fdate = date(DATE_ATOM, strtotime($_REQUEST['from_date']));
			$tdate = date(DATE_ATOM, strtotime($_REQUEST['to_date']. ' +1 day'));
			
			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

        	$additionalurl = 'AINVOICES?$filter=IVDATE ge '.$from_date.' and IVDATE le '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\' &$expand=AINVOICEITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE)';
		} else {
			$begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
			$todaydate = urlencode(date(DATE_ATOM, strtotime('now')));

			$additionalurl = 'AINVOICES?$filter=IVDATE ge '.$begindate.' and IVDATE le '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=AINVOICEITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE)';
			
			//$additionalurl = 'AINVOICES?$filter=CUSTNAME eq \''.$priority_customer_number.'\' &$expand=AINVOICEITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE)';
		}
		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );

		header('Content-Type: application/csv');
	    // tell the browser we want to save it instead of displaying it
	    header('Content-Disposition: attachment; filename="export.csv";');
		$f = fopen('php://output', 'w');
		//add BOM to fix UTF-8 in Excel
        fputs($f, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
		$array=array(__('Date','p18w'),__('Customer Name','p18w'),__('IVNUM','p18w'),__('QPRICE','p18w'),__('DISCOUNT','p18w'),__('DISPRICE','p18w'),__('VAT','p18w'),__('Total Price','p18w'),__('Part Name','p18w'),__('Quantity','p18w'),__('Price','p18w'));
		//$array=array('Date','Customer Name','IVNUM','QPRICE','DISCOUNT','DISPRICE','VAT','Total Price','Partname','Quantity','Price');
		fputcsv($f, $array);
		foreach ($data->value as $key => $value) {
			if(!empty($value->AINVOICEITEMS_SUBFORM)) {
				foreach($value->AINVOICEITEMS_SUBFORM as $subform) {
					$array=array(date( 'd/m/y',strtotime($value->IVDATE)),$value->CUSTNAME,$value->IVNUM,$value->QPRICE,$value->DISCOUNT,$value->DISPRICE,$value->VAT,$value->TOTPRICE,$subform->PARTNAME,$subform->QUANT,$subform->PRICE);
					fputcsv($f, $array);
				}
			}else {
				$array=array(date( 'd/m/y',strtotime($value->IVDATE)),$value->CUSTNAME,$value->IVNUM,$value->QPRICE,$value->DISCOUNT,$value->DISPRICE,$value->VAT,$value->TOTPRICE);
				fputcsv($f, $array);
			}
		}

	    wp_die(); // this is required to terminate immediately and return a proper response
	}
}