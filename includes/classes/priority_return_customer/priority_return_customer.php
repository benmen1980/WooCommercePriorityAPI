<?php
class Priority_return_customer extends \PriorityAPI\API{
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

		add_action( 'wp_ajax_my_action_exporttoexcel_return', [$this,'my_action_exporttoexcel_return'] );
		add_action ( 'wp_ajax_nopriv_my_action_exporttoexcel_return', [$this,'my_action_exporttoexcel_return'] );
		add_action( 'p18a_request_front_priorityreturn',[$this,'request_front_priorityreturn']);

		add_action( 'wp_enqueue_scripts', function() {
			wp_enqueue_script('priority-woo-api-frontend', P18AW_ASSET_URL.'frontend.js', array('jquery'), time());
			wp_enqueue_style( 'priority-woo-api-style', P18AW_ASSET_URL.'style.css', time() );
			wp_enqueue_script('priority-woo-api-jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js');
			wp_enqueue_style( 'priority-woo-api-jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
		});

		add_action('init', function() {
			add_rewrite_endpoint('priority-return', EP_ROOT | EP_PAGES);
		});

		function my_custom_flush_rewrite_rules_priorityreturn() {
			add_rewrite_endpoint( 'priority-return', EP_ROOT | EP_PAGES );
			flush_rewrite_rules();
		}
		register_activation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityreturn' );
		register_deactivation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityreturn' );
		add_filter('woocommerce_account_menu_items', function($items) {
			$items['priority-return'] = __('Priority return', 'p18w');
			return $items;
		});

		add_action('woocommerce_account_priority-return_endpoint', function() {

			?>

			<div class="woocommerce-MyAccount-content-priority-orders">

				<p><?php _e('Priority Return Customer','p18w'); ?></p>
				<?php do_action('p18a_request_front_priorityreturn'); ?>

			</div>

			<?php

		});
	}
	function request_front_priorityreturn() {

		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );
		
		// get the date inputs
		if(isset($_POST['from-date']) && isset($_POST['to-date'])) {
			$fdate = date(DATE_ATOM, strtotime($_POST['from-date']));
			$tdate = date(DATE_ATOM, strtotime($_POST['to-date']. ' +1 day'));

			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

        	$additionalurl = 'DOCUMENTS_N?$filter=CURDATE ge '.$from_date.' and CURDATE le '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=TRANSORDER_N_SUBFORM($select=PARTNAME,QUANT,PRICE,PDES,BARCODE,VPRICE,QPRICE)';
		} else {
			//by default, get return from beginning of year till today
			$begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
			$todaydate = urlencode(date(DATE_ATOM, strtotime('now')));

			$additionalurl = 'DOCUMENTS_N?$filter=CURDATE ge '.$begindate.' and CURDATE le '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=TRANSORDER_N_SUBFORM($select=PARTNAME,QUANT,PRICE,PDES,BARCODE,VPRICE,QPRICE)';
			
		}
        
		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );

		$in_fdata = isset($_POST['from-date']) ? $_POST['from-date'] : '';
		$in_tdata = isset($_POST['to-date']) ? $_POST['to-date'] : '';
		echo "<form class='priority_form' method='POST'>";
		echo __('FROM:','p18w')." <input type='text' name='from-date' id='from-date' placeholder='dd/mm/yyyy' value='".$in_fdata."' required readonly='true'/>";
		echo __('TO:','p18w')." <input type='text' name='to-date' id='to-date' placeholder='dd/mm/yyyy' value='".$in_tdata."' required readonly='true'/>";
		echo "<input type='submit' value='".__('submit','p18w')."' name='date'/>";
		echo "</form>";
		echo "<a class='btn_export_excel' href='".admin_url( 'admin-ajax.php' )."?action=my_action_exporttoexcel_return&from_date=".$in_fdata."&to_date=".$in_tdata."' target='_blank'> 
        ".__('Export Excel','p18w')." </a>";
		echo "<table>";
		echo "<tr class='row-titles'><td></td><td>".__('Date','p18w')."</td><td>".__('DOCNO','p18w')."</td><td>".__('CDES','p18w')."</td><td>".__('NAME','p18w')."</td><td>".__('Price','p18w')."</td><td>".__('DISPRICEEXCVAT','p18w')."</td><td>".__('VAT','p18w')."</td><td>".__('Total Price Include VAT','p18w')."</td></tr>";
		$i = 1;

		foreach ($data->value as $key => $value) {
			
			echo "<tr><td>";
			if(!empty($value->TRANSORDER_N_SUBFORM)) {
				echo "<div class='cust-toggle plus' id='content-".$i."'>+</div>";
			} 
			echo "</td><td>".date( 'd/m/y',strtotime($value->CURDATE))."</td><td>".$value->DOCNO."</td><td>".$value->CDES."</td><td>".$value->NAME."</td><td>".wc_price($value->QPRICE)."</td><td>".wc_price($value->DISPRICE)."</td><td>".wc_price($value->VAT)."</td><td>".wc_price($value->TOTPRICE)."</td></tr>";
				
				if(!empty($value->TRANSORDER_N_SUBFORM)) {
					echo "<tr class='content_value subform-content-".$i."' style='display:none;'><td colspan='8'>";
					echo "<table>";
					echo "<tr><th>".__('Part Name','p18w')."</th><th>".__('Quantity','p18w')."</th><th>".__('PDES','p18w')."</th><th>".__('BARCODE','p18w')."</th><th>".__('Price before vat','p18w')."</th><th>".__('Price after vat','p18w')."</th><th>".__('TOTPRICE','p18w')."</th></tr>";
					foreach($value->TRANSORDER_N_SUBFORM as $subform) {
						echo "<tr><td>".$subform->PARTNAME."</td><td>".$subform->QUANT."</td><td>".$subform->PDES."</td><td>".$subform->BARCODE."</td><td>".wc_price($subform->PRICE)."</td><td>".wc_price($subform->VPRICE)."</td><td>".wc_price($subform->QPRICE)."</td></tr>";
					}
					echo "</table>";
					echo "</td></tr>";
				}
			$i++;
		}
		echo "</table>";
	}
	

	function my_action_exporttoexcel_return() {
        $current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );

		if(!empty($_REQUEST['from_date']) && !empty($_REQUEST['to_date'])) {
			$fdate = date(DATE_ATOM, strtotime($_REQUEST['from_date']));
			$tdate = date(DATE_ATOM, strtotime($_REQUEST['to_date']. ' +1 day'));
			
			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

			$additionalurl = 'DOCUMENTS_N?$filter=CURDATE ge '.$from_date.' and CURDATE le '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=TRANSORDER_N_SUBFORM($select=PARTNAME,QUANT,PRICE,PDES,BARCODE,VPRICE,QPRICE)';
		} else {
			$begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
			$todaydate = urlencode(date(DATE_ATOM, strtotime('now')));

			$additionalurl = 'DOCUMENTS_N?$filter=CURDATE ge '.$begindate.' and CURDATE le '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=TRANSORDER_N_SUBFORM($select=PARTNAME,QUANT,PRICE,PDES,BARCODE,VPRICE,QPRICE)';
			
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
        $array=array(__('Date','p18w'),__('DOCNO','p18w'),__('CDES','p18w'),__('NAME','p18w'),__('Price','p18w'),__('DISPRICEEXCVAT','p18w'),__('VAT','p18w'),__('Total Price Include VAT','p18w'),__('Part Name','p18w'),__('Quantity','p18w'),__('PDES','p18w'),__('BARCODE','p18w'),__('Price before vat','p18w'),__('Price after vat','p18w'));
		//$array=array('Date','Customer Name','IVNUM','QPRICE','DISCOUNT','DISPRICE','VAT','Total Price','Partname','Quantity','Price');
		fputcsv($f, $array);
		foreach ($data->value as $key => $value) {
			if(!empty($value->TRANSORDER_N_SUBFORM)) {
				foreach($value->TRANSORDER_N_SUBFORM as $subform) {
					$array=array(date( 'd/m/y',strtotime($value->CURDATE)),$value->DOCNO,$value->CDES,$value->NAME,$value->QPRICE,$value->DISPRICE,$value->VAT,$value->TOTPRICE,$subform->PARTNAME,$subform->QUANT,$subform->PDES,$subform->BARCODE,$subform->PRICE,$subform->VPRICE,$subform->QPRICE);
					fputcsv($f, $array);
				}
			}else {
				$array=array(date( 'd/m/y',strtotime($value->CURDATE)),$value->DOCNO,$value->CDES,$value->NAME,$value->QPRICE,$value->DISPRICE,$value->VAT,$value->TOTPRICE);
				fputcsv($f, $array);
			}
		}

	    wp_die(); // this is required to terminate immediately and return a proper response
	}
}