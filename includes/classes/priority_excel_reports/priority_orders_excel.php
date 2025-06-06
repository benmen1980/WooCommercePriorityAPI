<?php

add_action('wp_ajax_get_order_url', [Priority_orders_excel::class, 'get_order_url_callback']);
add_action('wp_ajax_nopriv_get_order_url', [Priority_orders_excel::class, 'get_order_url_callback']);

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
		add_action ( 'wp_ajax_nopriv_my_action_exporttoexcel', [$this,'my_action_exporttoexcel'] );
		add_action( 'p18a_request_front_priorityorders',[$this,'request_front_priorityorders']);

		add_action( 'wp_enqueue_scripts', function() {
			wp_enqueue_script('priority-woo-api-frontend', P18AW_ASSET_URL.'frontend.js', array('jquery'), time());
			wp_enqueue_style( 'priority-woo-api-style', P18AW_ASSET_URL.'style.css', time() );
			wp_enqueue_script('priority-woo-api-jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js');
			wp_enqueue_style( 'priority-woo-api-jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
			wp_enqueue_script('ajax-scripts', P18AW_ASSET_URL.'ajax-script.js', array('jquery'));
			// The wp_localize_script allows us to output the ajax_url path for our script to use.
			wp_localize_script('ajax-scripts', 'ajax_obj', array( 
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			));
		});

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
			$items['priority-orders'] = __('Priority Orders', 'p18w');
			return $items;
		});

		add_action('woocommerce_account_priority-orders_endpoint', function() {

			?>

			<div class="woocommerce-MyAccount-content-priority-orders my-account-content">

				<p><?php _e('Priority Orders','p18w'); ?></p>
				<?php do_action('add_message_front_priorityOrders'); ?>
				<?php do_action('p18a_request_front_priorityorders'); ?>

			</div>

			<?php

		});
	}
	function request_front_priorityorders() {

		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );
		
		// get the date inputs
		if(isset($_POST['from-date']) && isset($_POST['to-date'])) {
			$fdate = date(DATE_ATOM, strtotime($_POST['from-date']));
			$tdate = date(DATE_ATOM, strtotime($_POST['to-date']. ' +1 day'));

			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

			$additionalurl = 'ORDERS?$filter=CURDATE ge '.$from_date.' and CURDATE le '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\' and ROYY_SHOWINWEB eq \'Y\'&$expand=ORDERITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE,PDES,Y_9950_5_ESHB,ICODE,QPRICE,TUNITNAME,PRDATE,AROW_MITKABEL,SPEC14,TBALANCE)';
        	
		} else 
		//by default enter date from begin of year to today
		{
			$begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
			$begindate = apply_filters('simply_excel_reports', $begindate);
			$todaydate = urlencode(date(DATE_ATOM, strtotime('now')));

			$additionalurl = 'ORDERS?$filter=CURDATE ge '.$begindate.' and CURDATE le '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\' and ROYY_SHOWINWEB eq \'Y\'&$expand=ORDERITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE,PDES,Y_9950_5_ESHB,ICODE,QPRICE,TUNITNAME,PRDATE,AROW_MITKABEL,SPEC14,TBALANCE)';
			$additionalurl = apply_filters('simply_orders_excel_data', $additionalurl);
			//$additionalurl = 'ORDERS?$filter=CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=ORDERITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE)';
		}

		//$additionalurl = 'ORDERS?$filter=CURDATE gt '.$from_date.' and CURDATE lt '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\'  &$expand=ORDERITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE)';
        
		$args= [];
		$response = $this->makeRequest( "GET", $additionalurl, $args, true );
		$data     = json_decode( $response['body'] );
		
		$in_fdata = isset($_POST['from-date']) ? $_POST['from-date'] : '';
		$in_tdata = isset($_POST['to-date']) ? $_POST['to-date'] : '';
		echo "<form class='priority_form' method='POST'>";
		echo __('FROM:','p18w')." <input type='text' name='from-date' id='from-date' placeholder='dd/mm/yyyy' value='".$in_fdata."' required />";
		echo __('TO:','p18w')." <input type='text' name='to-date' id='to-date' placeholder='dd/mm/yyyy' value='".$in_tdata."' required/>";
		echo "<input type='submit' value='".__('submit','p18w')."' name='date'/>";
		echo "</form>";
		echo "<a class='btn_export_excel' href='".admin_url( 'admin-ajax.php' )."?action=my_action_exporttoexcel&from_date=".$in_fdata."&to_date=".$in_tdata."' target='_blank'> 
		".__('Export Excel','p18w')." </a>";
		echo "<table class='priority-report-table'>";
		echo "<tr class='row-titles'><td></td><td></td><td>".__('Date','p18w')."</td><td>".__('Order Name','p18w')."</td><td>".__('Purchase Orders','p18w')."</td><td>".__('Status Order','p18w')."</td><td>".__('Price','p18w')."</td><td>".__('Percentage','p18w')."</td><td>".__('Discounted Price','p18w')."</td><td>".__('VAT','p18w')."</td><td>".__('Total Price Include VAT','p18w')."</td></tr>";
		date_default_timezone_set('Asia/Jerusalem');
		$i = 1;

		foreach ($data->value as $key => $value) {
			echo "<tr><td>";
			if(!empty($value->ORDERITEMS_SUBFORM)) {
				echo "<div class='cust-toggle plus' id='content-".$i."'>+</div>";
			}
			echo "</td>";
						
			echo "<td>
					<button style='font-size: 13px!important;' type='button' class='open_doc btn_open_order' data-order-name='" . htmlspecialchars($value->ORDNAME, ENT_QUOTES, 'UTF-8') . "'>" 
						. __('Order confirmation', 'p18w') . "
						<div class='loader_wrap'>
							<div class='loader_spinner'>
								<div class='line'></div>
								<div class='line'></div>
								<div class='line'></div>
							</div>
						</div>
					</button>
				</td>";
				
			echo "<td>".date( 'd/m/y',strtotime($value->CURDATE))."</td><td>".$value->ORDNAME."</td><td>".$value->REFERENCE."</td><td>".$value->ORDSTATUSDES."</td><td>".$value->QPRICE.' '.$value->CODE."</td><td>".(($value->PERCENT == 0) ? '' : $value->PERCENT)."</td><td>".$value->DISPRICE.' '.$value->CODE."</td><td>".$value->VAT.' '.$value->CODE."</td><td>".$value->TOTPRICE.' '.$value->CODE."</td></tr>";
				
			if(!empty($value->ORDERITEMS_SUBFORM)) {
				echo "<tr class='content_value subform-content-".$i."' style='display:none;'><td colspan='8'>";
				echo "<table class='table-orders'>";
				echo "<tr class='row-sub-titles'><td>".__('Part Name','p18w')."</td><td>".__('Manufacturer Part Number','p18w')."</td><td>".__('Description','p18w')."</td><td>".__('Delivery Date','p18w')."</td><td>".__('Quantity','p18w')."</td><td>".__('Balance','p18w')."</td><td>".__('Unit Measure','p18w')."</td><td>".__('Price Unit','p18w')."</td><td>".__('Total Price','p18w')."</td><td>".__('mifrat','p18w')."</td></tr>";
				foreach($value->ORDERITEMS_SUBFORM as $subform) {
					echo "<tr><td>".$subform->PARTNAME."</td><td>".$subform->Y_9950_5_ESHB."</td><td class='product-row'>".$subform->PDES."</td><td>".(($subform->AROW_MITKABEL == null) ? '' : ((date('d/m/y',strtotime($subform->AROW_MITKABEL)) >= date('d/m/y',strtotime($subform->PRDATE))) ? date('d/m/y',strtotime($subform->AROW_MITKABEL)) : date('d/m/y',strtotime($subform->PRDATE))))."</td><td>".$subform->QUANT."</td><td>".$subform->TBALANCE."</td><td>".$subform->TUNITNAME."</td><td class='price_row'>".$subform->PRICE.' '.$subform->ICODE."</td><td class='price_row'>".$subform->QPRICE.' '.$subform->ICODE;
					$attache = apply_filters('add_attache_priority', $subform->SPEC14);
					echo $attache;
					echo "</tr>";
				}
				echo "</table>";
				echo "</td></tr>";
			}
			$i++;
		}
		echo "</table>";
	}

	function my_action_exporttoexcel() {
		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );
		
		if(!empty($_REQUEST['from_date']) && !empty($_REQUEST['to_date'])) {
			$fdate = date(DATE_ATOM, strtotime($_REQUEST['from_date']));
			$tdate = date(DATE_ATOM, strtotime($_REQUEST['to_date']. ' +1 day'));
			
			$from_date = urlencode($fdate);  // get from $_POST['from date']
        	$to_date   = urlencode($tdate);  // get from $_POST['from date']

	   		$additionalurl = 'ORDERS?$filter=CURDATE ge '.$from_date.' and CURDATE le '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\' and ROYY_SHOWINWEB eq \'Y\'&$expand=ORDERITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE,PDES,Y_9950_5_ESHB,ICODE,QPRICE,TUNITNAME,PRDATE,AROW_MITKABEL,TBALANCE)';
		} else {
			$begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
			$begindate = apply_filters('simply_excel_reports', $begindate);
			$todaydate = urlencode(date(DATE_ATOM, strtotime('now')));
			$additionalurl = 'ORDERS?$filter=CURDATE ge '.$begindate.' and CURDATE le '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\' and ROYY_SHOWINWEB eq \'Y\'&$expand=ORDERITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE,PDES,Y_9950_5_ESHB,ICODE,QPRICE,TUNITNAME,PRDATE,AROW_MITKABEL,TBALANCE)';
			//$additionalurl = 'ORDERS?$filter=CUSTNAME eq \''.$priority_customer_number.'\' &$expand=ORDERITEMS_SUBFORM($select=PARTNAME,QUANT,PRICE)';
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
		$array=array(__('Date','p18w'),__('Delivery Date','p18w'),__('Order Name','p18w'),__('Purchase Orders','p18w'),__('Status Order','p18w'),__('Part Name','p18w'),__('Manufacturer Part Number','p18w'),__('Description','p18w'),__('Quantity','p18w'),__('Balance','p18w'),__('Unit Measure','p18w'),__('Price Unit','p18w'),__('Total Price','p18w'));
		fputcsv($f, $array);
		date_default_timezone_set('Asia/Jerusalem');
		foreach ($data->value as $key => $value) {
			if(!empty($value->ORDERITEMS_SUBFORM)) {
				foreach($value->ORDERITEMS_SUBFORM as $subform) {
					$array=array(date( 'd/m/y',strtotime($value->CURDATE)),(($subform->AROW_MITKABEL == null) ? '' : ((date('d/m/y',strtotime($subform->AROW_MITKABEL)) >= date('d/m/y',strtotime($subform->PRDATE))) ? date('d/m/y',strtotime($subform->AROW_MITKABEL)) : date('d/m/y',strtotime($subform->PRDATE)))),$value->ORDNAME,$value->REFERENCE,$value->ORDSTATUSDES,$subform->PARTNAME,$subform->Y_9950_5_ESHB,$subform->PDES,$subform->QUANT,$subform->TBALANCE,$subform->TUNITNAME,$subform->PRICE,$subform->QPRICE);
					fputcsv($f, $array);
				}
			}else {
				$array=array(date( 'd/m/y',strtotime($value->CURDATE)),(($subform->AROW_MITKABEL == null) ? '' : ((date('d/m/y',strtotime($subform->AROW_MITKABEL)) >= date('d/m/y',strtotime($subform->PRDATE))) ? date('d/m/y',strtotime($subform->AROW_MITKABEL)) : date('d/m/y',strtotime($subform->PRDATE)))),$value->ORDNAME,$value->REFERENCE,$value->ORDSTATUSDES,$value->QPRICE,(($value->PERCENT == 0) ? '' : $value->PERCENT),$value->DISPRICE,$value->VAT,$value->TOTPRICEE);
				fputcsv($f, $array);
			}
		}

	    wp_die(); // this is required to terminate immediately and return a proper response
	}

	public function create_hub2sdk_orders_request($priority_order_number){
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
		
        $array['ORDNAME'] = $priority_order_number;
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
            CURLOPT_URL            => 'prinodehub1-env.eba-gdu3xtku.us-west-2.elasticbeanstalk.com/printSalesOrder',
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

	public static function get_order_url_callback() {
		if (isset($_POST['ordname'])) {
			$ordname = sanitize_text_field($_POST['ordname']);

			// Get an instance of the class
			$instance = self::instance();
			// Call your function to get the order URL
			try {
				$response = json_decode($instance->create_hub2sdk_orders_request($ordname));
				$url = $response->order_url ?? '';
				if (!empty($url)) {
					do_action('simply_approve_order_request', $ordname);
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