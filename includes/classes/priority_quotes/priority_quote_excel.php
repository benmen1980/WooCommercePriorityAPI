<?php
class Priority_quotes_excel extends \PriorityAPI\API{
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

        add_action( 'wp_ajax_my_action_exporttoexcel_quote', [$this,'my_action_exporttoexcel_quote'] );
        add_action ( 'wp_ajax_nopriv_my_action_exporttoexcel_quote', [$this,'my_action_exporttoexcel_quote'] );
        add_action( 'p18a_request_front_priorityquotes',[$this,'request_front_priorityquotes']);

        add_action( 'wp_enqueue_scripts', function() {
            wp_enqueue_script('priority-woo-api-frontend', P18AW_ASSET_URL.'frontend.js', array('jquery'), time());
            wp_enqueue_style( 'priority-woo-api-style', P18AW_ASSET_URL.'style.css', time() );
            wp_enqueue_script('priority-woo-api-jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js');
            wp_enqueue_style( 'priority-woo-api-jquery-ui', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css' );
        });

        add_action('init', function() {
            add_rewrite_endpoint('priority-quotes', EP_ROOT | EP_PAGES);
        });

        function my_custom_flush_rewrite_rules_priorityquotes() {
            add_rewrite_endpoint( 'priority-quotes', EP_ROOT | EP_PAGES );
            flush_rewrite_rules();
        }
        register_activation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityquotes' );
        register_deactivation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priorityquotes' );
        add_filter('woocommerce_account_menu_items', function($items) {
            $items['priority-quotes'] = __('Priority Quotes', 'p18w');
            return $items;
        });

        add_action('woocommerce_account_priority-quotes_endpoint', function() {

            ?>

            <div class="woocommerce-MyAccount-content-priority-orders my-account-content">

                <p><?php _e('Priority Quotes','p18w'); ?></p>
                <?php do_action('add_message_front_priorityQuotes'); ?>
                <?php do_action('p18a_request_front_priorityquotes'); ?>

            </div>

            <?php

        });
    }
    function request_front_priorityquotes() {

        $current_user             = wp_get_current_user();
        $priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );        
        
        // get the date inputs
        if(isset($_POST['from-date']) && isset($_POST['to-date'])) {
            $fdate = date(DATE_ATOM, strtotime($_POST['from-date']));
            $tdate = date(DATE_ATOM, strtotime($_POST['to-date']. ' +1 day'));

            $from_date = urlencode($fdate);  // get from $_POST['from date']
            $to_date   = urlencode($tdate);  // get from $_POST['from date']

            $additionalurl = 'CPROF?$filter=PDATE ge '.$from_date.' and PDATE le '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\' and ROYY_SHOWINWEB eq \'Y\'&$expand=CPROFITEMS_SUBFORM($expand=CPROFITEMSTEXT_SUBFORM)';
            // $additionalurl = 'CPROF?$filter=PDATE ge '.$from_date.' and PDATE le '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\'and ROYY_SHOWINWEB eq \'Y\'&$expand=CPROFITEMS_SUBFORM($select=PARTNAME,TQUANT,PRICE,PDES,BARCODE,SUPTIME,PERCENTPRICE,QPRICE,Y_17934_5_ESHB,TUNITNAME,ICODE)';
            
        } else 
        //by default enter date from begin of year to today
        {
            $begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
            $begindate = apply_filters('simply_excel_reports', $begindate);
            $todaydate = urlencode(date(DATE_ATOM, strtotime('now')));

            $additionalurl = 'CPROF?$filter=PDATE ge '.$begindate.' and PDATE le '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\' and ROYY_SHOWINWEB eq \'Y\'&$expand=CPROFITEMS_SUBFORM($expand=CPROFITEMSTEXT_SUBFORM)';
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
        echo "<a class='btn_export_excel' href='".admin_url( 'admin-ajax.php' )."?action=my_action_exporttoexcel_quote&from_date=".$in_fdata."&to_date=".$in_tdata."' target='_blank'> 
        ".__('Export Excel','p18w')." </a>";
        echo "<table class='priority-report-table'>";
        echo "<tr class='row-titles'><td></td><td>".__('Date QUOTE','p18w')."</td><td>".__('Date Expiration','p18w')."</td><td>".__('Contact','p18w')."</td><td>".__('Quote Number','p18w')."</td><td>".__('Terms Payment','p18w')."</td>";
        echo "<td>".__(' ','p18w')."</td>";
        echo "</tr>"; 
        date_default_timezone_set('Asia/Jerusalem');
        $tableNumber = 1;
    

        foreach ($data->value as $key => $value) {
            echo "<tr><td>";
            if(!empty($value->CPROFITEMS_SUBFORM)) {
                echo "<div class='cust-toggle plus' id='content-".$tableNumber."'>+</div>";
            }
            echo "</td><td>".date( 'd/m/y',strtotime($value->PDATE))."</td><td>".date( 'd/m/y',strtotime($value->EXPIRYDATE))."</td><td>".$value->NAME."</td><td>".$value->CPROFNUM."</td><td>".$value->PAYDES."</td>";
            $button_cart = apply_filters('add_button_shopping_cart', $value);
            echo $button_cart;
            echo "</tr>";
            
            if(!empty($value->CPROFITEMS_SUBFORM)) {
                    echo "<tr class='content_value subform-content-".$tableNumber."' style='display:none;'><td colspan='8'>";
                    echo "<table class='table-quote'>";
                    $i = 1;
                    echo "<tr class='row-sub-titles'><td>".__('Counter','p18w')."</td><td>".__('Part Name','p18w')."</td><td>".__('Product Name','p18w')."</td><td>".__('Manufacturer Part Number','p18w')."</td><td>".__('Quantity','p18w')."</td><td>".__('Unit Measure','p18w')."</td><td>".__('Supply Time','p18w')."</td><td>".__('Price without Discount','p18w')."</td><td>".__('Price Discount','p18w')."</td><td>".__('Total Price','p18w')."</td><td>".__('mifrat','p18w')."</td></tr>";
                    foreach($value->CPROFITEMS_SUBFORM as $subform) {
                        // echo "<tr><td>";
                        echo "<tr><td>" . $i . "</td><td>";
                        // echo apply_filters('add_link_to_product', $subform);
                        // .$subform->PARTNAME.
                        $comment_text = $subform->CPROFITEMSTEXT_SUBFORM->TEXT;
                        $comment  = ' ' . html_entity_decode( $comment_text );

                        echo $subform->PARTNAME."</td><td class='product-row'>".$subform->PDES."<br/><p>".$comment."</p></td><td>".$subform->BARCODE."</td><td>".$subform->TQUANT."</td><td>".$subform->TUNITNAME."</td><td>".$subform->SUPTIME."</td><td class='price_row'>".$subform->PRICE.' '.$subform->ICODE."</td><td class='price_row'>".$subform->PERCENTPRICE.' '.$subform->ICODE."</td><td class='price_row'>".$subform->QPRICE.' '.$subform->ICODE."</td>";
                        $values = array(
                            'value1' => $subform->BARCODE,
                            'value2' => $value->CPROFNUM
                        );
                        $attache = apply_filters('add_attache_priority', $subform->Y_17934_5_ESHB);
                        echo $attache;
                        echo "</tr>";
                        $i++;
                    }
                    echo "</table>";
                    echo "</td></tr>";
            }
            $tableNumber++;
        }
        echo "</table>";
    }
    

    function my_action_exporttoexcel_quote() {
        $current_user             = wp_get_current_user();
        $priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );
        
        if(!empty($_REQUEST['from_date']) && !empty($_REQUEST['to_date'])) {
            $fdate = date(DATE_ATOM, strtotime($_REQUEST['from_date']));
            $tdate = date(DATE_ATOM, strtotime($_REQUEST['to_date']. ' +1 day'));
            
            $from_date = urlencode($fdate);  // get from $_POST['from date']
            $to_date   = urlencode($tdate);  // get from $_POST['from date']

            $additionalurl = 'CPROF?$filter=PDATE ge '.$from_date.' and PDATE le '.$to_date.' and CUSTNAME eq \''.$priority_customer_number.'\' and ROYY_SHOWINWEB eq \'Y\'&$expand=CPROFITEMS_SUBFORM($select=PARTNAME,TQUANT,PRICE,PDES,BARCODE,SUPTIME,PERCENTPRICE,QPRICE,Y_17934_5_ESHB,TUNITNAME,ICODE)';
        } else {
            $begindate = urlencode(date(DATE_ATOM, strtotime('first day of january this year')));
            $begindate = apply_filters('simply_request_data', $begindate);
            $todaydate = urlencode(date(DATE_ATOM, strtotime('now')));
            $additionalurl = 'CPROF?$filter=PDATE ge '.$begindate.' and PDATE le '.$todaydate.' and CUSTNAME eq \''.$priority_customer_number.'\' and ROYY_SHOWINWEB eq \'Y\'&$expand=CPROFITEMS_SUBFORM($select=PARTNAME,TQUANT,PRICE,PDES,BARCODE,SUPTIME,PERCENTPRICE,QPRICE,Y_17934_5_ESHB,TUNITNAME,ICODE)';
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
        $array=array(__('Quote Number','p18w'),__('Date QUOTE','p18w'),__('Date Expiration','p18w'),__('Part Name','p18w'),__('Manufacturer Part Number','p18w'),__('Product Name','p18w'),__('Quantity','p18w'),__('Unit Measure','p18w'),__('Supply Time','p18w'),__('Price without Discount','p18w'),__('Price Discount','p18w'),__('Total Price','p18w'));
        fputcsv($f, $array);
        date_default_timezone_set('Asia/Jerusalem');
        foreach ($data->value as $key => $value) {
            if(!empty($value->CPROFITEMS_SUBFORM)) {
                foreach($value->CPROFITEMS_SUBFORM as $subform) {
                    $array=array($value->CPROFNUM,date( 'd/m/y',strtotime($value->PDATE)),date( 'd/m/y',strtotime($value->EXPIRYDATE)),$subform->PARTNAME,$subform->BARCODE,$subform->PDES,$subform->TQUANT,$subform->TUNITNAME,$subform->SUPTIME,$subform->PRICE,$subform->PERCENTPRICE,$subform->QPRICE);
                    fputcsv($f, $array);
                }
            }else {
                $array=array($value->CPROFNUM,date( 'd/m/y',strtotime($value->PDATE)),date( 'd/m/y',strtotime($value->EXPIRYDATE)),$value->PAYDES);
                fputcsv($f, $array);
            }
        }

        wp_die(); // this is required to terminate immediately and return a proper response
    }
}