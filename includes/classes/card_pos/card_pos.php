<?php

use PriorityWoocommerceAPI\WooAPI;

class CardPOS extends \PriorityAPI\API
{
    private static $instance; // api instance
    private $countries = []; // countries list
    private static $priceList = []; // price lists
    private $basePriceCode = "בסיס";

    /**
     * PriorityAPI initialize
     *
     */
    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    private function __construct()
    {
        // set json serilaized 2 decimals
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set('serialize_precision', -1);
        }
        /**
         * Schedule cron  syncs
         */
        $syncs = [
            'sync_items_priority_pos' => 'syncItemsPriorityPos',
            'sync_items_web_pos' => 'syncItemsWebPos',
            'sync_inventory_priority_pos' => 'syncInventoryPriorityPos',
            'sync_price_priority_pos' => 'syncPricePriorityPos',
            'sync_sale_price_priority_pos' => 'syncSalePricePriorityPos',
            'sync_order_status_priority_pos' => 'syncPriorityOrderStatusPos'
            //'sync_color_details' => 'syncColorDetails'
        ];

        foreach ($syncs as $hook => $action) {
            // Schedule sync
            if ($this->option('auto_' . $hook, false)) {

                add_action($hook, [$this, $action]);

                if (!wp_next_scheduled($hook)) {
                    wp_schedule_event(time(), $this->option('auto_' . $hook), $hook);
                }

            }
        }
        add_action( 'woocommerce_checkout_update_order_meta',[$this,'approve_transaction'], 1, 9999 );
        //add_action('woocommerce_payment_complete', [$this, 'close_transaction'], 9999);
        add_action('woocommerce_order_status_changed', [$this, 'close_transaction'], 99999);
        add_action('wp_authenticate', [$this,'check_user_in_priority'], 9999, 2);
        add_action( 'template_redirect', [$this,'get_user_details_after_registration'], 9999);
        add_action('sync_inventory_year_back_priority_pos',  [$this,'syncInventoryYearBackPriorityPos']);

        if (!wp_next_scheduled('sync_inventory_year_back_priority_pos')) {
            $res = wp_schedule_event(time(), 'daily', 'sync_inventory_year_back_priority_pos');
        }

    }

    public function run()
    {
        return is_admin() ? $this->backend() : $this->frontend();

    }


    /**
     * Frontend
     *
     */
    private function frontend(){}

    /**
     * Backend - PriorityAPI Admin
     *
     */
    private function backend()
    {
        add_action('init', function () {


            // save sync POS settings
            if ($this->post('p18aw-save-sync-pos') && wp_verify_nonce($this->post('p18aw-nonce'), 'save-sync-pos')) {
                
                $this->updateOption('sync_items_priority_pos_config', stripslashes($this->post('sync_items_priority_pos_config')));
                $this->updateOption('sync_inventory_pos_config', stripslashes($this->post('sync_inventory_pos_config')));
                $this->updateOption('sync_price_pos_config', stripslashes($this->post('sync_price_pos_config')));
                $this->updateOption('sync_sale_price_pos_config', stripslashes($this->post('sync_sale_price_pos_config')));
                $this->updateOption('sync_orders_status_pos_config', stripslashes($this->post('sync_orders_status_pos_config')));
                
                $this->updateOption('sync_items_priority_pos', $this->post('sync_items_priority_pos'));
                $this->updateOption('auto_sync_items_priority_pos', $this->post('auto_sync_items_priority_pos'));
                $this->updateOption('log_items_priority_variation_pos', $this->post('log_items_priority_variation_pos'));

                $this->updateOption('sync_inventory_priority_pos', $this->post('sync_inventory_priority_pos'));
                $this->updateOption('auto_sync_inventory_priority_pos', $this->post('auto_sync_inventory_priority_pos'));

                $this->updateOption('sync_price_priority_pos', $this->post('sync_price_priority_pos'));
                $this->updateOption('auto_sync_price_priority_pos', $this->post('auto_sync_price_priority_pos'));

                $this->updateOption('sync_sale_price_priority_pos', $this->post('sync_sale_price_priority_pos'));
                $this->updateOption('auto_sync_sale_price_priority_pos', $this->post('auto_sync_sale_price_priority_pos'));

                $this->updateOption('sync_orders_status_priority_pos', $this->post('sync_orders_status_priority_pos'));
                $this->updateOption('auto_sync_orders_status_priority_pos', $this->post('auto_sync_orders_status_priority_pos'));

                $this->updateOption('sync_color_details', $this->post('sync_color_details'));
                $this->updateOption('auto_sync_color_details', $this->post('auto_sync_color_details'));

                $this->updateOption('sync_items_web_pos', $this->post('sync_items_web_pos'));
                $this->updateOption('auto_sync_items_web_pos', $this->post('auto_sync_items_web_pos'));

                $this->updateOption('sync_order_status_priority_pos', $this->post('sync_order_status_priority_pos'));
                $this->updateOption('auto_sync_order_status_priority_pos', $this->post('auto_sync_order_status_priority_pos'));
                //$this->updateOption('log_sync_order_status_priority_pos', $this->post('log_sync_order_status_priority_pos'));
                
                $this->updateOption('email_error_sync_items_priority_variation', $this->post('email_error_sync_items_priority_variation'));
            }
        });

        //  add Priority pos cart status to orders page
        // ADDING A CUSTOM COLUMN TITLE TO ADMIN ORDER LIST
        add_filter('manage_edit-shop_order_columns',
            function ($columns) {
                // Set "Actions" column after the new colum
                $action_column = $columns['order_actions']; // Set the title in a variable
                unset($columns['order_actions']); // remove  "Actions" column


                //add the new column "Status"
                if ($this->option('cardPos')) {
                    // add the Priority order number
                    $columns['priority_pos_cart_number'] = '<span>' . __('POS CART Close Transaction Number ', 'p18w') . '</span>'; // title
                    $columns['priority_pos_cart_status'] = '<span>' . __('POS CART Close Transaction Status', 'p18w') . '</span>'; // title
                }

                // Set back "Actions" column
                $columns['order_actions'] = $action_column;

                return $columns;
            }, 
        999);

         // ADDING THE DATA FOR EACH ORDERS BY "Platform" COLUMN
         add_action('manage_shop_order_posts_custom_column',
         function ($column, $post_id) {
             //POS CART
             if ($this->option('cardPos')) {
                 $pos_status = get_post_meta($post_id, 'priority_pos_cart_status', true);
                 $pos_number = get_post_meta($post_id, 'priority_pos_cart_number', true);
                 if (empty($pos_status)) $pos_status = '';
                 if (strlen($pos_status) > 0 && $pos_status != 'Success') $pos_status = '<div class="tooltip">Error<span class="tooltiptext">' . $pos_status . '</span></div>';
                 if (empty($pos_number)) $pos_number = '';
             }
             switch ($column) {
                 // pos cart
                 case 'priority_pos_cart_status' :
                    echo $pos_status;
                    break;
                 case 'priority_pos_cart_number' :
                    echo '<span>' . $pos_number . '</span>'; // display the data
                    break;
             }
         }, 999, 2);

        
    }



    function makeRequestCardPos($method, $form_name = null, $form_action = null, $options = [], $log = false){
        $args = [
            'headers' => [
                'Content-Type'  => 'application/json'
            ],
            'timeout'   => 45,
            'method'    => strtoupper('POST'),
            'sslverify' => WooAPI::instance()->option('sslverify', false)
        ];
        if ( ! empty($options)) {
            $args = array_merge($args, $options);
        }
    
        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $ip = $config->IP;
        $base_url = $config->BaseUrl;
    
        $url = sprintf('https://%s/%s/api/%s/%s',
            $ip,
            $base_url,
            is_null($form_name) ? '' : stripslashes($form_name),
            is_null($form_action) ? '' : stripslashes($form_action),
        );
    
        $response = wp_remote_request($url, $args);
        if($response){
            $body_array = json_decode($response["body"], true);
        }
        else{
            $body_array = [];
        }
        // echo '<pre>';
        // print_r($body_array);
        // echo '</pre>';
    
    
        return $body_array;
    
    
    
    }
    

    //new function makeRequestCardPos with retry
    // function makeRequestCardPos($method, $form_name = null, $form_action = null, $options = [], $log = false){
    //     $args = [
    //         'headers' => [
    //             'Content-Type'  => 'application/json'
    //         ],
    //         'timeout'   => 45,
    //         'method'    => strtoupper('POST'),
    //         'sslverify' => WooAPI::instance()->option('sslverify', false)
    //     ];
    //     if ( ! empty($options)) {
    //         $args = array_merge($args, $options);
    //     }
    
    //     $raw_option = WooAPI::instance()->option('setting-config');
    //     $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
    //     $config = json_decode(stripslashes($raw_option));
    //     $ip = $config->IP;
    
    //     $url = sprintf('http://%s/PrioriPOSTestAPI/api/%s/%s',
    //         $ip,
    //         is_null($form_name) ? '' : stripslashes($form_name),
    //         is_null($form_action) ? '' : stripslashes($form_action),
    //     );
    
    //     //$response = wp_remote_request($url, $args);
    //     $body_array = $this->makeRequestWithRetry($url, $args);
    //     if(is_array( $body_array)){
    //         return $body_array;
    //     }
    //     else{
    //         $msg_error = $body_array;
    //         $body_array = [];
    //         $body_array['EdeaError']['DisplayErrorMessage'] = $msg_error;
    //         return $body_array;
    //     }

    // }
    // function makeRequestWithRetry($url, $args) {
    //     $maxRetries = 1;
    //     $retryInterval = 1000000; // 3 seconds
    
    //     $attempt = 1;

    //     while ($attempt <= $maxRetries) {
    //         $response = wp_remote_request($url, $args);
    
    //         // Check if the response indicates a timeout
    //         if (is_wp_error( $response ) ) {
    //             $body_array = $response->get_error_message();
    //             usleep($retryInterval);
    //             $attempt++;
    //         } else {
    //             $body_array = json_decode($response["body"], true);
    //             $msg_error = $body_array['EdeaError']['ErrorMessage'];
    //             if(!empty($msg_error) && $msg_error == 'General Error'){
    //                 usleep($retryInterval);
    //                 $attempt++;
    //             }
    //             else{
    //                 return $body_array;
    //             }
    //         }
    //     }
    //     return $body_array;
        
    // }


    function check_user_by_mobile_phone($mobile_phone){

        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;

        $data = [
            "MobilePhone" => $mobile_phone, 
        ];
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'PosCustomers';

        $form_action = 'GetPOSCustomer';
    
        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
    
        return $body_array;
    
        
    }
    
    function check_user_by_phone($phone){

        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;

        $data = [
            "PhoneNumber" => $phone, 
        ];
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'PosCustomers';

        $form_action = 'GetPOSCustomer';
    
        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
    
        return $body_array;
    }
    
    function check_user_by_id_num($id_num){

        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;

        $data = [
            "IDNumber" => $id_num, 
        ];
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'PosCustomers';

        $form_action = 'GetPOSCustomer';
    
        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
    
        return $body_array;
    }

    function check_user_by_mobile_phone_and_id($mobile_phone, $birth_id){

        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;

        $data = [
            "MobilePhone" => $mobile_phone, 
            "IDNumber" => $birth_id
        ];
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'PosCustomers';

        $form_action = 'GetPOSCustomer';
    
        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
    
        return $body_array;
    
        
    }

    function check_user_in_priority($user_login, $user_password ) {
        $classes = get_body_class(); 
        //check if in my account: login page
          if($_SERVER['REQUEST_URI'] == "/my-account/"){
            if(!username_exists($user_login)) {
                $body_array = $this->check_user_by_mobile_phone($user_login); 
                $error_code = $body_array["ErrorCode"];
                if ($error_code === 0) {
                    $PosCustomersResult = $body_array["POSCustomersMembershipDetails"][0];
                   //if exist in priority check id and phone in login validation page 
                    if(!empty($PosCustomersResult)){
                        $encryption_key = "my_secret_key";

                        // Define the number to be encrypted
                        $encrypted_number = $user_password;

                        // Encrypt the number using the AES algorithm
                        $encrypted_number = openssl_encrypt($encrypted_number, "AES-256-CBC", $encryption_key);

                        // Save the encrypted number to a session variable
                        $_SESSION['encrypted_number'] = $encrypted_number;
                        $login_page_url = home_url( '/login-validation/' ); // Replace with the URL of your login page
                        wp_redirect( $login_page_url);
                        exit;
                    }
                }
                else {
                        $message = $body_array['EdeaError']['DisplayErrorMessage'];
                        $multiple_recipients = array(
                            get_bloginfo('admin_email'),
                            'elisheva.g@simplyct.co.il'
                        );
                        $subj = 'Error check user exist with mobile phone in priority';
                        wp_mail( $multiple_recipients, $subj, $message );
                }
            }
            //the user exist
            //check if is club and save their points in user
            else{
                $user = get_user_by('login',$user_login);
                if($user){
                    $user_id = $user->ID;
                    $priority_customer_number = get_user_meta($user_id, 'priority_customer_number', true);
                    if(!empty($priority_customer_number)){
                        $raw_option = $this->option('setting-config');
                        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
                        $config = json_decode(stripslashes($raw_option));
                        $branch_num = $config->BranchNumber;
                        $unique_id = $config->UniqueIdentifier;
                        $ChannelCode = $config->ChannelCode;
                        $pos_num = $config->POSNumber;
        
                        $data["POSCustomerNumber"] = $priority_customer_number;
                        $data["ClubCode"] = "01";
                        $data['UniquePOSIdentifier'] = [
                            "BranchNumber" => $branch_num,
                            "POSNumber" => $pos_num,
                            "UniqueIdentifier" => $unique_id,
                            "ChannelCode" => $ChannelCode,
                            "VendorCode" => "",
                            "ExternalAccountID" => ""
                        ];
        
                        $form_name =  'PosCustomers';
        
                        $form_action = 'GetPOSCustomerWithExtendedDetails';
        
                        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action, ['body' => json_encode($data)], true);
                        $error_code = $body_array["ErrorCode"];
                        if($error_code === 0){
                            // on login, if user is club in priority check that he is club in the site
                            if(!empty($body_array["POSCustomerExtendedDetails"]["ClubsMemberships"])){
                                if(get_user_meta( $user_id, 'is_club', true ) == 0){
                                    update_user_meta($user_id, 'is_club', 1);
                                    update_user_meta($user_id, 'club_fee_paid', 1);
                                } 
                            }
                            if(!empty($body_array["POSCustomerExtendedDetails"]["PointsPerPointsTypeDetails"])){
                                $points = $body_array["POSCustomerExtendedDetails"]["PointsPerPointsTypeDetails"][0]["TotalPoints"];
                                update_user_meta( $user_id, 'points_club', $points);
                            } 
                            if(!empty($body_array["POSCustomerExtendedDetails"]["CouponEligibilities"])){
                                $coupon = $body_array["POSCustomerExtendedDetails"]["CouponEligibilities"][0]["OriginalCouponDescription"];
                                $coupon_code = $body_array["POSCustomerExtendedDetails"]["CouponEligibilities"][0]["CouponCode"];
                                if($coupon == "יומהולדת"){
                                    update_user_meta( $user_id, 'birthday_coupon', $coupon_code);
                                }
                            }
                            else{
                                if(get_user_meta( $user_id, 'birthday_coupon', true ))
                                    delete_user_meta( $user_id, 'birthday_coupon' );
                            }
                        }
                        else{
                            $message = $body_array['EdeaError']['DisplayErrorMessage'];
                            $multiple_recipients = array(
                                get_bloginfo('admin_email'),
                                'elisheva.g@simplyct.co.il'
                            );
                            $subj = 'Error sync user details with extended details in check_user_in_piority function: '.$user_id;
                            wp_mail( $multiple_recipients, $subj, json_encode($data).'</br>'.$message );
                        }
                    }
                
                }
            }
        }
    }


    function get_user_details_after_registration() {
        //$prev_url = $_SERVER['HTTP_REFERER'];
        if ( is_user_logged_in()  ) {
            $user_id = get_current_user_id();
            //check that first time 
            if(empty(get_user_meta($user_id, 'priority_customer_number', true)) && !current_user_can( 'manage_options' )){
                $meta = get_user_meta($user_id);
                $account_id = get_user_meta($user_id, 'account_id', true);
                $birthdate = get_user_meta($user_id, 'reg_birthday', true);
                $birthdate = str_replace('/', '-', $birthdate);
                $birthdate = date('Y-m-d', strtotime($birthdate));
                // $birthdate = DateTime::createFromFormat('d/m/Y', $birthdate);
                // if($birthdate)
                //     $birthdate =  $birthdate->format('Y-m-d');
                $user_login = $meta['nickname'][0];
                $fname = $meta['first_name'][0];
                $lname = $meta['last_name'][0];
                $phone = $meta['nickname'][0];
                $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
                $billing_address_2 = get_user_meta($user_id, 'billing_address_2', true);
                $billing_city = get_user_meta($user_id, 'billing_city', true);
                $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
                $billing_country = get_user_meta($user_id, 'billing_country', true);
                $user_info = get_userdata($user_id);
                $billing_email = $user_info->user_email;
                //$billing_email = get_user_meta($user_id, 'email', true);
                $accept_newsletter = get_user_meta($user_id, 'agree_business_owner', true);


                if ($accept_newsletter == 'off'){
                    $allow_email = false;
                }
                elseif($accept_newsletter == 'on'){
                    $allow_email = true;
               
                }
               
                $result =  $this->check_user_by_mobile_phone($user_login);
                //print_r($result); 
                $error_code = $result["ErrorCode"];
                if ($error_code === 0) {
                    $PosCustomersResult = $result["POSCustomersMembershipDetails"][0];
                    //if not exist in priority, create user
                    if(empty($PosCustomersResult)){
                        $result =  $this->check_user_by_phone($user_login);
                        $error_code = $result["ErrorCode"];
                        if ($error_code === 0) {
                            $PosCustomersResult = $result["POSCustomersMembershipDetails"][0];
                            if(empty($PosCustomersResult)){
                                $result =  $this->check_user_by_id_num($account_id);
                                $error_code = $result["ErrorCode"];
                                if ($error_code === 0) {
                                    $PosCustomersResult = $result["POSCustomersMembershipDetails"][0];
                                    if(empty($PosCustomersResult)){
                                        //echo 'current user:'.get_current_user_id();
                                        // sync cust to priorirty
                                        $site_priority_customer_number = 'WEB-'.get_current_user_id();
                                        $data = [
                                            "CreateCustomer" => false,
                                        ];
                                        $data["POSCustomerDetails"] = [
                                            "POSCustomerNumber" => $site_priority_customer_number,
                                            "City" => !empty($billing_city) ? $billing_city : '',
                                            "StreetAddress" => !empty($billing_address_1) ? $billing_address_1 : '',
                                            "HouseNumber" => !empty($billing_address_2) ? (int)$billing_address_2 : 0,
                                            "ApartmentNumber" => 0,
                                            "ZipCode" => !empty($billing_postcode) ? $billing_postcode : '',
                                            "FirstName" => !empty($fname) ? $fname : '',
                                            "LastName" => !empty($lname) ? $lname : '',
                                            "FullName" => $lname.' '.$fname,
                                            "PhoneNumber" => '',
                                            "MobileNumber" => !empty($phone) ? $phone : '',
                                            "BirthID" => !empty($account_id) ? $account_id : '',
                                            "BirthDate" => !empty($birthdate) ? $birthdate : '',
                                            "Gender" => "ז",
                                            "Email" => !empty($billing_email) ? $billing_email : '',
                                            "IsActive" => true,
                                            "AllowSMS" => $allow_email,
                                            "AllowEmail" => $allow_email,
                                            "AllowMail" => $allow_email,
                                            "DefaultClubCode" => "",
                                            "Address2" => "string",
                                            "Address3" => "string",
                                            "WantsToJoinMobileClub" => true,
                                            "Entrance" => "string"
                                        ];
                                        $raw_option = $this->option('setting-config');
                                        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
                                        $config = json_decode(stripslashes($raw_option));
                                        $branch_num = $config->BranchNumber;
                                        $unique_id = $config->UniqueIdentifier;
                                        $ChannelCode = $config->ChannelCode;
                                        $pos_num = $config->POSNumber;
                                    
                                        $data['UniquePOSIdentifier'] = [
                                            "BranchNumber" => $branch_num,
                                            "POSNumber" => $pos_num,
                                            "UniqueIdentifier" => $unique_id,
                                            "ChannelCode" => $ChannelCode,
                                            "VendorCode" => "",
                                            "ExternalAccountID" => ""
                                        ];

                                        $form_name =  'PosCustomers';

                                        $form_action = 'CreateOrUpdatePOSCustomer';
    
                                        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action, ['body' => json_encode($data)], true);
                                        $error_code = $body_array["ErrorCode"];
                                        if($error_code === 0){
                                            update_user_meta($user_id, 'priority_customer_number', $site_priority_customer_number, true);
                                            update_user_meta($user_id, 'has_to_edit_details', 0);
                                        }
                                        else{
                                            $message = $body_array['EdeaError']['DisplayErrorMessage'];
                                            $error_msg = $body_array['EdeaError']['ErrorMessage'];
                                            $multiple_recipients = array(
                                                get_bloginfo('admin_email'),
                                                'elisheva.g@simplyct.co.il'
                                            );
                                            $subj = 'Error sync user in priority: '.$user_login.' message:'.$message;
                                            wp_mail( $multiple_recipients, $subj, $error_msg.' data:'.json_encode($data) );
                                        }
                                        //print_r($body_array);
    
                                        
                                    }
                                    else{
                                        //update priority number to user
                                        $priority_customer_number = $PosCustomersResult["POSCustomerBasicDetails"]["POSCustomerNumber"];
                                        update_user_meta($user_id, 'priority_customer_number', $priority_customer_number);
                                        //$is_club = $PosCustomersResult["ChannelName"];
                                        $is_club = $PosCustomersResult["ClubsMemberships"];

                                        //we need here to update back user details from site to priority
                                        $data = [
                                            "CreateCustomer" => false,
                                        ];
                                        $data["POSCustomerDetails"] = [
                                            "POSCustomerNumber" => $priority_customer_number,
                                            "FirstName" => !empty($fname) ? $fname : '',
                                            "LastName" => !empty($lname) ? $lname : '',
                                            "FullName" => $lname.' '.$fname,
                                            "BirthDate" => !empty($birthdate) ? $birthdate : '',
                                            "Email" => !empty($billing_email) ? $billing_email : '',
                                            "IsActive" => true,
                                            "AllowSMS" => $allow_email,
                                            "AllowEmail" => $allow_email,
                                            "AllowMail" => $allow_email,
                                        ];
                                        $raw_option = $this->option('setting-config');
                                        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
                                        $config = json_decode(stripslashes($raw_option));
                                        $branch_num = $config->BranchNumber;
                                        $unique_id = $config->UniqueIdentifier;
                                        $ChannelCode = $config->ChannelCode;
                                        $pos_num = $config->POSNumber;
                                    
                                        $data['UniquePOSIdentifier'] = [
                                            "BranchNumber" => $branch_num,
                                            "POSNumber" => $pos_num,
                                            "UniqueIdentifier" => $unique_id,
                                            "ChannelCode" => $ChannelCode,
                                            "VendorCode" => "",
                                            "ExternalAccountID" => ""
                                        ];

                                        $form_name =  'PosCustomers';

                                        $form_action = 'CreateOrUpdatePOSCustomer';

                                        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action, ['body' => json_encode($data)], true);
                                        $error_code = $body_array["ErrorCode"];
                                        if($error_code === 0){
                                        }
                                        else{
                                            $message = $body_array['EdeaError']['DisplayErrorMessage'];
                                            $error_msg = $body_array['EdeaError']['ErrorMessage'];
                                            $multiple_recipients = array(
                                                get_bloginfo('admin_email'),
                                                'elisheva.g@simplyct.co.il'
                                            );
                                            $subj = 'Error sync user detail after registration from site to priority '.$user_id.' message:'.$message;
                                            wp_mail( $multiple_recipients, $subj, $error_msg.' data:'.json_encode($data) );
                                        }
                     
                                        if(!empty($is_club)){
                                            update_user_meta($user_id, 'is_club', 1);
                                            update_user_meta($user_id, 'club_fee_paid', 1);

                                            //check if user has point and save them in user
                                            $raw_option = $this->option('setting-config');
                                            $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
                                            $config = json_decode(stripslashes($raw_option));
                                            $branch_num = $config->BranchNumber;
                                            $unique_id = $config->UniqueIdentifier;
                                            $ChannelCode = $config->ChannelCode;
                                            $pos_num = $config->POSNumber;

                                            $data["POSCustomerNumber"] = $priority_customer_number;
                                            $data["ClubCode"] = "01";
                                            $data['UniquePOSIdentifier'] = [
                                                "BranchNumber" => $branch_num,
                                                "POSNumber" => $pos_num,
                                                "UniqueIdentifier" => $unique_id,
                                                "ChannelCode" => $ChannelCode,
                                                "VendorCode" => "",
                                                "ExternalAccountID" => ""
                                            ];
                            
                                            $form_name =  'PosCustomers';
                            
                                            $form_action = 'GetPOSCustomerWithExtendedDetails';
                            
                                            $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action, ['body' => json_encode($data)], true);
                                            $error_code = $body_array["ErrorCode"];
                                            if($error_code === 0){
                                                if(!empty($body_array["POSCustomerExtendedDetails"]["PointsPerPointsTypeDetails"])){
                                                    $points = $body_array["POSCustomerExtendedDetails"]["PointsPerPointsTypeDetails"][0]["TotalPoints"];
                                                    update_user_meta( $user_id, 'points_club', $points);
                                                } 
                                                if(!empty($body_array["POSCustomerExtendedDetails"]["CouponEligibilities"])){
                                                    $coupon = $body_array["POSCustomerExtendedDetails"]["CouponEligibilities"][0]["OriginalCouponDescription"];
                                                    $coupon_code = $body_array["POSCustomerExtendedDetails"]["CouponEligibilities"][0]["CouponCode"];
                                                    if($coupon == "יומהולדת"){
                                                        update_user_meta( $user_id, 'birthday_coupon', $coupon_code);
                                                    }
                                                }
                                            }
                                            else{
                                                $message = $body_array['EdeaError']['DisplayErrorMessage'];
                                                $error_msg = $body_array['EdeaError']['ErrorMessage'];
                                                $multiple_recipients = array(
                                                    get_bloginfo('admin_email'),
                                                    'elisheva.g@simplyct.co.il'
                                                );
                                                $subj = 'Error sync user club points after registration when exist with id number'.$user_id.' message:'.$message;
                                                wp_mail( $multiple_recipients, $subj, $error_msg.' data:'.json_encode($data) );
                                            }
                                        }
                                        
                                    }
                                }
                                else{
                                    $message = $result['EdeaError']['DisplayErrorMessage'];
                                    $multiple_recipients = array(
                                        get_bloginfo('admin_email'),
                                        'elisheva.g@simplyct.co.il'
                                    );
                                    $subj = 'Error check user exist with id number in priority';
                                    wp_mail( $multiple_recipients, $subj, $message );
                                }
    
                            }
                            else{
                                //update priority number to user
                                $priority_customer_number = $PosCustomersResult["POSCustomerBasicDetails"]["POSCustomerNumber"];
                                update_user_meta($user_id, 'priority_customer_number', $priority_customer_number);
                                //$is_club = $PosCustomersResult["ChannelName"];
                                $is_club = $PosCustomersResult["ClubsMemberships"];

                                //we need here to update back user details from site to priority
                                $data = [
                                    "CreateCustomer" => false,
                                ];
                                $data["POSCustomerDetails"] = [
                                    "POSCustomerNumber" => $priority_customer_number,
                                    "FirstName" => !empty($fname) ? $fname : '',
                                    "LastName" => !empty($lname) ? $lname : '',
                                    "FullName" => $lname.' '.$fname,
                                    "BirthDate" => !empty($birthdate) ? $birthdate : '',
                                    "Email" => !empty($billing_email) ? $billing_email : '',
                                    "IsActive" => true,
                                    "AllowSMS" => $allow_email,
                                    "AllowEmail" => $allow_email,
                                    "AllowMail" => $allow_email,
                                ];
                                $raw_option = $this->option('setting-config');
                                $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
                                $config = json_decode(stripslashes($raw_option));
                                $branch_num = $config->BranchNumber;
                                $unique_id = $config->UniqueIdentifier;
                                $ChannelCode = $config->ChannelCode;
                                $pos_num = $config->POSNumber;
                            
                                $data['UniquePOSIdentifier'] = [
                                    "BranchNumber" => $branch_num,
                                    "POSNumber" => $pos_num,
                                    "UniqueIdentifier" => $unique_id,
                                    "ChannelCode" => $ChannelCode,
                                    "VendorCode" => "",
                                    "ExternalAccountID" => ""
                                ];

                                $form_name =  'PosCustomers';

                                $form_action = 'CreateOrUpdatePOSCustomer';

                                $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action, ['body' => json_encode($data)], true);
                                $error_code = $body_array["ErrorCode"];
                                if($error_code === 0){
                                }
                                else{
                                    $message = $body_array['EdeaError']['DisplayErrorMessage'];
                                    $error_msg = $body_array['EdeaError']['ErrorMessage'];
                                    $multiple_recipients = array(
                                        get_bloginfo('admin_email'),
                                        'elisheva.g@simplyct.co.il'
                                    );
                                    $subj = 'Error sync user detail after registration from site to priority '.$user_id.' message:'.$message;
                                    wp_mail( $multiple_recipients, $subj, $error_msg.' data:'.json_encode($data) );
                                }


                                if(!empty($is_club)){
                                    update_user_meta($user_id, 'is_club', 1);
                                    update_user_meta($user_id, 'club_fee_paid', 1);

                                    //check if user has point and save them in user
                                    $raw_option = $this->option('setting-config');
                                    $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
                                    $config = json_decode(stripslashes($raw_option));
                                    $branch_num = $config->BranchNumber;
                                    $unique_id = $config->UniqueIdentifier;
                                    $ChannelCode = $config->ChannelCode;
                                    $pos_num = $config->POSNumber;

                                    $data["POSCustomerNumber"] = $priority_customer_number;
                                    $data["ClubCode"] = "01";
                                    $data['UniquePOSIdentifier'] = [
                                        "BranchNumber" => $branch_num,
                                        "POSNumber" => $pos_num,
                                        "UniqueIdentifier" => $unique_id,
                                        "ChannelCode" => $ChannelCode,
                                        "VendorCode" => "",
                                        "ExternalAccountID" => ""
                                    ];
                    
                                    $form_name =  'PosCustomers';
                    
                                    $form_action = 'GetPOSCustomerWithExtendedDetails';
                    
                                    $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action, ['body' => json_encode($data)], true);
                                    $error_code = $body_array["ErrorCode"];
                                    if($error_code === 0){
                                        if(!empty($body_array["POSCustomerExtendedDetails"]["PointsPerPointsTypeDetails"])){
                                            $points = $body_array["POSCustomerExtendedDetails"]["PointsPerPointsTypeDetails"][0]["TotalPoints"];
                                            update_user_meta( $user_id, 'points_club', $points);
                                        } 
                                        if(!empty($body_array["POSCustomerExtendedDetails"]["CouponEligibilities"])){
                                            $coupon = $body_array["POSCustomerExtendedDetails"]["CouponEligibilities"][0]["OriginalCouponDescription"];
                                            $coupon_code = $body_array["POSCustomerExtendedDetails"]["CouponEligibilities"][0]["CouponCode"];
                                            if($coupon == "יומהולדת"){
                                                update_user_meta( $user_id, 'birthday_coupon', $coupon_code);
                                            }
                                        }
                                    }
                                    else{
                                        $message = $body_array['EdeaError']['DisplayErrorMessage'];
                                        $error_msg = $body_array['EdeaError']['ErrorMessage'];
                                        $multiple_recipients = array(
                                            get_bloginfo('admin_email'),
                                            'elisheva.g@simplyct.co.il'
                                        );
                                        $subj = 'Error sync user club points '.$user_id.' message:'.$message;
                                        wp_mail( $multiple_recipients, $subj, $error_msg.' data:'.json_encode($data) );
                                    }
                                }
                                
                            }
                        }
                        else{
                            $message = $result['EdeaError']['DisplayErrorMessage'];
                            $multiple_recipients = array(
                                get_bloginfo('admin_email'),
                                'elisheva.g@simplyct.co.il'
                            );
                            $subj = 'Error check user exist with phone in priority';
                            wp_mail( $multiple_recipients, $subj, $message );
                        }
                        
                    }
                    else{
                        //if exist in priority, update user priority number
                        //update priority number to user
                        $priority_customer_number = $PosCustomersResult["POSCustomerBasicDetails"]["POSCustomerNumber"];
                        update_user_meta($user_id, 'priority_customer_number', $priority_customer_number);
                       
                        $is_club = $PosCustomersResult["ClubsMemberships"];
                        
                        
                        //we need here to update back user details from site to priority
                        $data = [
                            "CreateCustomer" => false,
                        ];
                        $data["POSCustomerDetails"] = [
                            "POSCustomerNumber" => $priority_customer_number,
                            "FirstName" => !empty($fname) ? $fname : '',
                            "LastName" => !empty($lname) ? $lname : '',
                            "FullName" => $lname.' '.$fname,
                            "BirthDate" => !empty($birthdate) ? $birthdate : '',
                            "Email" => !empty($billing_email) ? $billing_email : '',
                            "IsActive" => true,
                            "AllowSMS" => $allow_email,
                            "AllowEmail" => $allow_email,
                            "AllowMail" => $allow_email,
                        ];
                        $raw_option = $this->option('setting-config');
                        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
                        $config = json_decode(stripslashes($raw_option));
                        $branch_num = $config->BranchNumber;
                        $unique_id = $config->UniqueIdentifier;
                        $ChannelCode = $config->ChannelCode;
                        $pos_num = $config->POSNumber;
                    
                        $data['UniquePOSIdentifier'] = [
                            "BranchNumber" => $branch_num,
                            "POSNumber" => $pos_num,
                            "UniqueIdentifier" => $unique_id,
                            "ChannelCode" => $ChannelCode,
                            "VendorCode" => "",
                            "ExternalAccountID" => ""
                        ];

                        $form_name =  'PosCustomers';

                        $form_action = 'CreateOrUpdatePOSCustomer';

                        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action, ['body' => json_encode($data)], true);
                        $error_code = $body_array["ErrorCode"];
                        if($error_code === 0){
                        }
                        else{
                            $message = $body_array['EdeaError']['DisplayErrorMessage'];
                            $error_msg = $body_array['EdeaError']['ErrorMessage'];
                            $multiple_recipients = array(
                                get_bloginfo('admin_email'),
                                'elisheva.g@simplyct.co.il'
                            );
                            $subj = 'Error sync user detail after registration from site to priority '.$user_id.' message:'.$message;
                            wp_mail( $multiple_recipients, $subj, $error_msg.' data:'.json_encode($data) );
                        }

                        // check if club and update user meta
                        if(!empty($is_club)){
                            update_user_meta($user_id, 'is_club', 1);
                            update_user_meta($user_id, 'club_fee_paid', 1);
                            

                            //check if user has point and save them in user
                            $raw_option = $this->option('setting-config');
                            $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
                            $config = json_decode(stripslashes($raw_option));
                            $branch_num = $config->BranchNumber;
                            $unique_id = $config->UniqueIdentifier;
                            $ChannelCode = $config->ChannelCode;
                            $pos_num = $config->POSNumber;

                            $data["POSCustomerNumber"] = $priority_customer_number;
                            $data["ClubCode"] = "01";
                            $data['UniquePOSIdentifier'] = [
                                "BranchNumber" => $branch_num,
                                "POSNumber" => $pos_num,
                                "UniqueIdentifier" => $unique_id,
                                "ChannelCode" => $ChannelCode,
                                "VendorCode" => "",
                                "ExternalAccountID" => ""
                            ];
            
                            $form_name =  'PosCustomers';
            
                            $form_action = 'GetPOSCustomerWithExtendedDetails';
            
                            $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action, ['body' => json_encode($data)], true);
                            $error_code = $body_array["ErrorCode"];
                            if($error_code === 0){
                                if(!empty($body_array["POSCustomerExtendedDetails"]["PointsPerPointsTypeDetails"])){
                                    $points = $body_array["POSCustomerExtendedDetails"]["PointsPerPointsTypeDetails"][0]["TotalPoints"];
                                    update_user_meta( $user_id, 'points_club', $points);
                                } 
                                if(!empty($body_array["POSCustomerExtendedDetails"]["CouponEligibilities"])){
                                    $coupon = $body_array["POSCustomerExtendedDetails"]["CouponEligibilities"][0]["OriginalCouponDescription"];
                                    $coupon_code = $body_array["POSCustomerExtendedDetails"]["CouponEligibilities"][0]["CouponCode"];
                                    if($coupon == "יומהולדת"){
                                        update_user_meta( $user_id, 'birthday_coupon', $coupon_code);
                                    }
                                }
                            }
                            else{
                                $message = $body_array['EdeaError']['DisplayErrorMessage'];
                                $error_msg = $body_array['EdeaError']['ErrorMessage'];
                                $multiple_recipients = array(
                                    get_bloginfo('admin_email'),
                                    'elisheva.g@simplyct.co.il'
                                );
                                $subj = 'Error sync user club points '.$user_id.' message:'.$message;
                                wp_mail( $multiple_recipients, $subj, $error_msg.' data:'.json_encode($data) );
                            }
                        }

                    }
                }
                else{
                    $message = $result['EdeaError']['DisplayErrorMessage'];
                    $multiple_recipients = array(
                        get_bloginfo('admin_email'),
                        'elisheva.g@simplyct.co.il'
                    );
                    $subj = 'Error check user exist with mobile phone in priority';
                    wp_mail( $multiple_recipients, $subj, $message );
                }


                global $wpdb;
                //save user details of all user that accept to receive  newsletter to new table 
                $table = $wpdb->prefix . 'list_user_subscribe_newsletter';
                $table_club = $wpdb->prefix . 'list_user_club';
                
                $accept_newsletter = get_user_meta($user_id, 'agree_business_owner', true);
                $is_club = get_user_meta($user_id, 'is_club', true);
                $priority_customer_number = get_user_meta( $user_id, 'priority_customer_number', true );
                $id_number = get_user_meta( $user_id, 'account_id', true );
                // $user_data = get_userdata($user_id);
                // $user_phone =  $user_data->user_login;
                $current_user = wp_get_current_user();
                if ($current_user->exists()) {
                    $user_phone = $current_user->user_login;
                    // do something with the username, such as display it on the page
                }
                
                // Get user agent data
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $user_agent = $_SERVER['HTTP_USER_AGENT'];

               $flashy = flashy();
               $list_id = get_option('flashy_list_id');
                if($accept_newsletter == 'on'){
                    $result = $wpdb->insert($table, [
                        'priority_customer_number' => $priority_customer_number,
                        'id_number' => $id_number,
                        'customer_phone' =>  $user_phone,
                        'ip_address' => $ip_address,
                        'user_agent' => $user_agent
                    ]);
                    $customer = [
                        "email" => $billing_email,
                        "phone" => $phone,
                        "birthday" => strtotime($birthdate),
                        "lists" => [ $list_id => true ], //true or false depending on accept marketing checkbox
                     ];
                    
                    if ( $result === false ) {
                        wp_mail( 'elisheva.g@simplyct.co.il', 'insert into list error', $wpdb->last_error );
                    }
                }
                else{
                    $customer = [
                        "email" => $billing_email,
                        "phone" => $phone,
                        "birthday" => strtotime($birthdate),
                        //"lists" => [ $list_id => false ], //true or false depending on accept marketing checkbox
                     ];
                }
                $flashy->api->contacts->create($customer, 'email', false,true);
                if($is_club == 1){
                    $result = $wpdb->insert($table_club, [
                        'priority_customer_number' => $priority_customer_number,
                        'id_number' => $id_number,
                        'customer_phone' =>  $user_phone,
                        'ip_address' => $ip_address,
                        'user_agent' => $user_agent
                    ]);
                    //update flashy club list
                    $flashy = flashy();
       
                    //get into the after user register action and try to connect to flashy to update two fields
                    //one for accept marketing and the other for loyalty membership
                    //main contacts list
                    $list_id = get_option('flashy_list_id');
                
    
                    $customer = [
                        "email" => $current_user->user_email,
                        "loyalty_membership" => true 
                         //true or false depending on accept marketing checkbox
                    ];  
                    
                    $flashy->api->contacts->create($customer, 'email', false,true);
                    if ( $result === false ) {
                        wp_mail( 'elisheva.g@simplyct.co.il', 'insert into list error', $wpdb->last_error );
                    }
                }

            }
        }
    }

 
    function create_product_variables($data){

        $postname = sanitize_title($data['title']);
        $author = empty($data['author']) ? '1' : $data['author'];

        $post_data = array(
            'post_author' => $author,
            'post_name' => $postname,
            'post_title' => !empty($data['title']) ? $data['title'] : '',
            'post_status' => $data['status'],
            'ping_status' => 'closed',
            'post_type' => 'product',
            'guid' => home_url('/product/' . $postname . '/'),
        );

        $product_id = wc_get_product_id_by_sku($data['sku']);

        if (!empty($data['sku']) && $product_id) {
            $post_data['ID'] = $product_id;

            // Update the product (post data)
            $product_id = wp_update_post($post_data);
        } else {
            // Creating the product (post data)
            $product_id = wp_insert_post($post_data);
        }

        // Get an instance of the WC_Product_Variable object and save it
        $product = new WC_Product_Variable($product_id);
        global $wpdb;
        // @codingStandardsIgnoreStart
        $wpdb->query(
            $wpdb->prepare(
                "
                UPDATE $wpdb->posts
                SET post_title = '%s'
                WHERE ID = '%s'
                ",
                $data['title'],
                $product_id
            )
        );
        $product->save();

        // MAIN IMAGE
        if (!empty($data['image_id']))
            $product->set_image_id($data['image_id']);

        // IMAGES GALLERY
        if (!empty($data['gallery_ids']) && count($data['gallery_ids']) > 0)
            $product->set_gallery_image_ids($data['gallery_ids']);

        // SKU
        if (!empty($data['sku']))
            $product->set_sku($data['sku']);

        // STOCK (stock will be managed in variations)
        //$product->set_stock_quantity( $data['stock'] ); // Set a minimal stock quantity
        $product->set_manage_stock(false);
        $product->set_stock_status($data['stock']);

        // Tax class
        if (empty($data['tax_class']))
            $product->set_tax_class($data['tax_class']);

        // WEIGHT
        if (!empty($data['weight']))
            $product->set_weight(''); // weight (reseting)
        else
            $product->set_weight($data['weight']);

        $product->validate_props(); // Check validation

        ## ---------------------- VARIATION CATEGORIES ---------------------- ##


        // if ($data['categories-slug'] && is_array($data['categories-slug'])) {
            //foreach ($data['categories-slug'] as $parent_cat => $category) {


                $parent_name = $data['parent_category'];
                if (term_exists($parent_name)) {
                    $parent_term = term_exists( $parent_name, 'product_cat' ); // array is returned if taxonomy is given
                    $parent_term_id = $parent_term['term_id'];
                } else {
                    if (!empty($parent_name)) {
                    $parent_cat =  wp_insert_term(
                        // the name of the category
                            $parent_name,
                            // the taxonomy 'category' (don't change)
                            'product_cat',
                            array(
                                // what to use in the url for term archive
                                'slug' => $parent_name
                            )
                        );
                        $parent_term_id = $parent_cat['term_id'];
                    }
                }

                
        
                if (!empty($data['categories-slug'][0])) {
                    $terms_id = wp_set_object_terms($product_id,  array($data['categories-slug'][0],$parent_name), 'product_cat', true);
                    // update the name of the category
                    wp_update_term($terms_id[0],'product_cat',array(
                        'name'=> $data['categories'][0],
                        'slug' => $data['categories-slug'][0],
                        'parent' => $parent_term_id
                    ));
                    /*
                    if ( ! is_wp_error( $terms_id ) ) {
                        $parent_id = get_term_by( 'name', $parent_cat )->term_id;
                        if ( !$parent_id ) {
                                $parent_id = wp_create_term( $parent_cat, 'product_cat' )['term_id'];
                        }
                        foreach ( $terms_id as $term_id ) {
                            wp_update_term( $term_id, 'product_cat', [
                                'parent' => $parent_id
                            ] );
                        }
                    }
                    */
                }
                

            
            //}
        //}

        ## ---------------------- VARIATION TAGS ---------------------- ##

        if (isset($data['tags'])) {
            if ($data['tags'] && is_array($data['tags']))
                wp_set_object_terms($product_id, $data['tags'], 'product_tag', true);
        }

        ## ---------------------- VARIATION ATTRIBUTES ---------------------- ##

        $product_attributes = [];

        if (is_array($data['attributes'])) {
            foreach ($data['attributes'] as $key => $terms) {

                $taxonomy_id = wc_attribute_taxonomy_id_by_name($key);
                $taxonomy_name = wc_attribute_taxonomy_name($key);

                if (!$taxonomy_id) {
                    wc_create_attribute([
                        'name' => $key,
                    ]);
                    register_taxonomy(
                        $taxonomy_name,
                        apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product')),
                        apply_filters('woocommerce_taxonomy_args_' . $taxonomy_name, array(
                            'labels' => array(
                                'name' => wc_sanitize_taxonomy_name($key),
                            ),
                            'hierarchical' => true,
                            'show_ui' => false,
                            'query_var' => true,
                            'rewrite' => false,
                        ))
                    );
                }

                $product_attributes[$taxonomy_name] = array(
                    'name' => $taxonomy_name,
                    'value' => '',
                    'position' => '',
                    'is_visible' => 0,
                    'is_variation' => 1,
                    'is_taxonomy' => 1
                );

                foreach ($terms as $value) {
                    $term_name = ucfirst($value);
                    $term_slug = sanitize_title($value);

                    // Check if the Term name exist and if not we create it.
                    if (!term_exists($value, $taxonomy_name))
                        wp_insert_term($term_name, $taxonomy_name, array('slug' => $term_slug)); // Create the term

                    // Set attribute values
                    wp_set_object_terms($product_id, $term_name, $taxonomy_name, true);
                }
            }

            //$product_attributes = array_reverse($product_attributes, 1);

            /**
             * t205
             */
            $product_attributes_old = get_post_meta($product_id, '_product_attributes', true);
            $product_attributes = array_merge($product_attributes, is_array($product_attributes_old) ? $product_attributes_old : []);
            /**
             * end t205
             */

            update_post_meta($product_id, '_product_attributes', $product_attributes);
        }
        $product->save(); // Save the data

        return $product_id;
    }

    /**
     * Create a product variation for a defined variable product ID.
     *
     * @param int $product_id | Post ID of the product parent variable product.
     * @param array $variation_data | The data to insert in the product.
     * @since 3.0.0
     */

    function create_product_variations($product_id, $variation_data)
    {
        // Get the Variable product object (parent)
        $product = wc_get_product($product_id);
    
        $variation_post = array(
            'post_title' => $product->get_title(),
            'post_name' => 'product-' . $product_id . '-variation',
            'post_status' => 'publish',
            'post_parent' => $product_id,
            'post_type' => 'product_variation',
            'guid' => $product->get_permalink()
        );
    
        $variation_id = wc_get_product_id_by_sku($variation_data['sku']);
    
        if (!empty($variation_data['sku']) && $variation_id) {
            $variation_post['ID'] = $variation_id;
            // Update the product variation
            $variation_id = wp_update_post($variation_post);
        } else {
            // Creating the product variation
            $variation_id = wp_insert_post($variation_post);
        }
    
        // Get an instance of the WC_Product_Variation object
        $variation = new WC_Product_Variation($variation_id);
    
        if (!empty($variation_data['sku']))
            $variation->set_sku($variation_data['sku']);
    
        // Iterating through the variations attributes
        foreach ($variation_data['attributes'] as $attribute => $term_name) {
            $taxonomy = 'pa_' . sanitize_title($attribute); // The attribute taxonomy
            $taxonomy = 'pa_' . $attribute; // The attribute taxonomy
            // Check if the Term name exist and if not we create it.
            if (!term_exists($term_name, $taxonomy))
                $response = wp_insert_term($term_name, $taxonomy); // Create the term
            $term_slug = get_term_by('name', $term_name, $taxonomy)->slug; // Get the term slug
    
            // Get the post Terms names from the parent variable product.
            $post_term_names = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'names'));
    
            // Check if the post term exist and if not we set it in the parent variable product.
            if (is_array($post_term_names) && !in_array($term_name, $post_term_names))
                $foo = wp_set_post_terms($product_id, $term_name, $taxonomy, true);
            // Set/save the attribute data in the product variation
            update_post_meta($variation_id, 'attribute_' . $taxonomy, $term_slug);
    
            if (!empty($variation_data['desc_attribute'])){
                $term_id = get_term_by('name', $term_name, $taxonomy)->term_id;
                $args = array(
                    'description' => $variation_data['desc_attribute']
                );
                wp_update_term($term_id, $taxonomy, $args);
                //wp_update_term_count($term_id, $taxonomy);
            }
        }


    
        ## Set/save all other data
    
        // Prices
        if (empty($variation_data['sale_price'])) {
            $variation->set_price($variation_data['regular_price']);
        } else {
            $variation->set_price($variation_data['sale_price']);
            $variation->set_sale_price($variation_data['sale_price']);
        }
        $variation->set_regular_price($variation_data['regular_price']);
    
        // Stock
    
        if (empty($variation_data['stock'])) {
            $variation->set_stock_status('outofstock');
        } else {
            $variation->set_stock_status($variation_data['stock']);
        }
    
        // if (!empty($variation_data['stock_qty'])) {
        //     $variation->set_stock_quantity($variation_data['stock_qty']);
        //     $variation->set_manage_stock(true);
        //     $variation->set_stock_status('');
        // } else {
        //     $variation->set_manage_stock(false);
        // }
    
        update_post_meta($variation_id, 'product_code', $variation_data['product_code']);
    
        $variation->set_weight(''); // weight (reseting)
    
        $variation->save(); // Save the data
    
        return $variation_id;
    }
    

    /**
    * sync items with variation from priority and not from edea
    */
    function syncItemsPriorityPos()
    {
        $priority_version = WooAPI::instance()->option('priority-version');
        // config
        $res =  WooAPI::instance()->option('sync_items_priority_pos_config');
        $res = str_replace(array('.', "\n", "\t", "\r"), '', $res);
        $config_v = json_decode(stripslashes($res));
        $show_in_web = (!empty($config->show_in_web) ? $config->show_in_web : 'SHOWINWEB');
        $show_front = !empty($config_v->show_front) ? $config_v->show_front : null;
        $daysback = !empty((int)$config_v->days_back) ? $config_v->days_back : (!empty((int)$config->days_back) ? $config->days_back : 1);
        $stamp = mktime(0 - $daysback * 24, 0, 0);
        $bod = date(DATE_ATOM, $stamp);
        $url_addition = 'UDATE ge ' . $bod;
        $search_field = 'PARTNAME';
        $select = 'PARTNAME,PARTDES,MPARTNAME,BARCODE,VATPRICE,FAMILYDES,ROYY_EFAMILYDES,SPEC2,EDE_SPECDES2,ROYY_SPECEDES2,SPEC3,EDE_SPECDES3,EDE_SPECDES4,SPEC5,SPEC6,EDE_SPECDES9,SPEC10,ROYY_SPECEDES10,ROYY_SPECEDES16,ROYY_SPECEDES18,ROYY_SPECEDES11,SPEC12,EDE_SPECDES12,ROYY_SPECEDES12,SPEC14,EDE_SPECDES14,EDE_SPECDES16,EDE_SPECDES18,EDE_FABRICONRENT';
        $expand = '$expand=POS_INTERNETPARTSPEC_SUBFORM($select=SPEC1;),POS_PARTWEBDES_SUBFORM($select=PARTDES1;)';
        $url_addition_config = (!empty($config_v->additional_url) ? $config_v->additional_url : '');
        $filter = urlencode($url_addition) . ' ' . $url_addition_config;
        //$filter = 'PARTNAME eq \'4100132971L\' or PARTNAME eq \'4100132971M\'';
        $index = 0;
        $step = 50;
        $data = [0];    

        while(sizeof($data) > 0){

            $response = WooAPI::instance()->makeRequest('GET','LOGPART?$select=' . $select.' &$skip='.$index * $step .'&$top='.$step.'&$filter=' . $filter . '&' . $expand . '',
                    [], WooAPI::instance()->option('log_items_priority_variation_pos', true));
            //$subj = 'check sync item work';
            //wp_mail( 'elisheva.g@simplyct.co.il', $subj, implode(" ",$response) );
            // check response status
            if ($response['status']) {
                //$response_data = json_decode($response['body_raw'], true);
                $data = json_decode($response['body_raw'], true)['value'];
                //print_r($data); die;
                //print_r(sizeof($data['value']));die;
                $parents = [];
                $childrens = [];
                //if ($response_data['value'][0] > 0) {
                foreach ($data as $item) {
                    $variation_field = $item['MPARTNAME'].'-'.$item['SPEC2']; //2201-5
                    
                    if ($variation_field !== '-') {
                        $search_by_value = (string)$item[$search_field];  //220152XL
                        //write_custom_log('sync product: '.$variation_field);
                        $attributes = [];
                        //add size attribute
                        $attributes['size'] = $item['SPEC3']; //2XL
                        
                        $item['attributes'] = $attributes;
                        
                        if ($attributes) {
                            $price = $item['VATPRICE'];
                            $parents[$variation_field] = [
                                'sku' => $variation_field,
                                'title' => (!empty($item['POS_INTERNETPARTSPEC_SUBFORM']['SPEC1'])) ? $item['POS_INTERNETPARTSPEC_SUBFORM']['SPEC1'] : $item['PARTDES'] ,
                                'stock' => 'Y',
                                'variation' => [],
                                'regular_price' => $price,
                                'parent_category' => $item['ROYY_EFAMILYDES'], //גברים
                                'categories' => [ // i need to have sub category in hebrew
                                    $item['ROYY_SPECEDES11']
                                ],
                                'categories-slug' => [
                                    $item['ROYY_SPECEDES11'] .'-'.$item['ROYY_EFAMILYDES']
                                ],
                                //'excerpt' => $item['ROYY_SPECEDES10'].'</br> '.$item['ROYY_SPECEDES16'].'</br> '.$item['ROYY_SPECEDES18']
                            ];
                            if (!empty($show_in_web)) {
                                $parents[$variation_field][$show_in_web] = $item[$show_in_web];
                            }
                            $childrens[$variation_field][$search_by_value] = [
                                'sku' => $search_by_value,
                                'regular_price' => $price,
                                'stock' => 'Y',
                                'parent_title' => $item['POS_INTERNETPARTSPEC_SUBFORM']['SPEC1'],
                                'title' => $item['POS_INTERNETPARTSPEC_SUBFORM']['SPEC1'],
                                'stock' => 'outofstock',
                                'attributes' => $attributes,
                                'barcode' => $item['BARCODE'],
                                'model' =>  $item['MPARTNAME'],
                                'color' =>  $item['EDE_SPECDES2'],
                                'grouped_color' =>  $item['ROYY_SPECEDES2'],
                                'color_code' => $item['SPEC2'],
                                'measure_bar_code' =>  $item['SIZEBARCODE'], 
                                'brand_desc' =>  $item['EDE_SPECDES4'],
                                'year' => $item['SPEC5'],
                                'season' => $item['SPEC6'],
                                'concept' => $item['EDE_SPECDES9'],
                                'cut' => $item['SPEC10'],
                                'sub_cat' => $item['ROYY_SPECEDES11'], //missing it's parameter 11- i need it in hebrew
                                'cat' => $item['ROYY_EFAMILYDES'], //גברים
                                //'fabric' => $item['SPEC12'],
                                //'fabric_desc' => $item['EDE_SPECDES12'],
                                'made_in' => $item['EDE_SPECDES14'],
                                'sleeve_type' => $item['EDE_SPECDES18'],
                                'sub_group_jersey' => $item['EDE_SPECDES16'],
                                'fabric_content' => $item['EDE_FABRICONRENT'],
                                'child_size' => $item['EDE_SPECDES3'],
                                //'excerpt' => $item['ROYY_SPECEDES10'].'</br>'.$item['ROYY_SPECEDES16'].'</br>'.$item['ROYY_SPECEDES18']
                                


                            ];
                        }
                    }
                }
                foreach ($parents as $partname => $value) {
                    if (count($childrens[$partname])) {
                        $parents[$partname]['variation'] = $childrens[$partname];
                        $parents[$partname]['title'] = $parents[$partname]['title'];
                        // $parents[$partname]['post_content'] = $parents[$partname]['post_content'];
                        foreach ($childrens[$partname] as $children) {
                            foreach ($children['attributes'] as $attribute => $attribute_value) {
                                if ($attributes) {
                                    if (!empty($parents[$partname]['attributes'][$attribute])) {
                                        if (!in_array($attribute_value, $parents[$partname]['attributes'][$attribute]))
                                            $parents[$partname]['attributes'][$attribute][] = $attribute_value;
                                    } else {
                                        $parents[$partname]['attributes'][$attribute][] = $attribute_value;
                                    }
                                }
                            }
                        }
                    } else {
                        unset($parents[$partname]);
                    }
                }
                if ($parents) {
                    foreach ($parents as $sku_parent => $parent) {

                        $id = $this->create_product_variables(array(
                            'author' => '', // optional
                            'title' => $parent['title'],
                            'content' => '',
                            'excerpt' => '',
                            'regular_price' => '', // product regular price
                            'sale_price' => '', // product sale price (optional)
                            'stock' => $parent['stock'], // Set a minimal stock quantity

                            'sku' => $sku_parent, // optional
                            'tax_class' => '', // optional
                            'weight' => '', // optional
                            // For NEW attributes/values use NAMES (not slugs)
                            'parent_category'  => $parent['parent_category'],
                            'attributes' => $parent['attributes'],
                            'categories' => $parent['categories'],
                            'categories-slug' => $parent['categories-slug'],
                            'status' => 'publish'
                        ));
                        write_custom_log('sync product: '.$id );
                        $parents[$sku_parent]['product_id'] = $id;
                        foreach ($parent['variation'] as $sku_children => $children) {
                            // The variation data
                            $variation_data = array(
                                'attributes' => $children['attributes'],
                                'sku' => $sku_children,
                                'regular_price' => !empty($children['regular_price']) ? ($children['regular_price']) : $parent[$sku_children]['regular_price'],
                                'product_code' => $children['sku'],
                                'sale_price' => '',
                                'stock' => $children['stock'],
                                'show_front' => $children['show_front'],
                                'desc_attribute' => $children['child_size']
                            );
                            // The function to be run
                            $variation_id = $this->create_product_variations($id, $variation_data);
                            // update ACFs
                            //update_post_meta($id, '_excerpt', $children['excerpt']);
                            update_field('barcode', $children['barcode'], $id);
                            update_field('model', $children['model'], $id);
                            update_field('grouped_color', $children['grouped_color'], $id);
                            update_field('color', $children['color'], $id);
                            update_field('color_code', $children['color_code'], $id);
                            update_field('measure_bar_code', $children['measure_bar_code'], $id);
                            update_field('brand_desc', $children['brand_desc'], $id);
                            update_field('year', $children['year'], $id);
                            update_field('season', $children['season'], $id);
                            update_field('concept', $children['concept'], $id);
                            update_field('cut', $children['cut'], $id);
                            update_field('sub_cat', $children['sub_cat'], $id);
                            update_field('fabric', $children['fabric'], $id);
                            update_field('fabric_desc', $children['fabric_desc'], $id);
                            update_field('fabric_content', $children['fabric_content'], $id);
                            update_field('sleeve_type', $children['sleeve_type'], $id);
                            update_field('sub_group_jersey', $children['sub_group_jersey'], $id);
                            update_field('made_in', $children['made_in'], $id);

                        }
                        unset($parents[$sku_parent]['variation']);

                        if ( ! has_post_thumbnail( $id ) ) {
                            $product_data = array(
                                'ID' => $product_id,
                                'post_status' => 'draft'
                            );
                            wp_update_post( $product_data );
                        }

                    }

                }
                $index++;
                // add timestamp
                WooAPI::instance()->updateOption('items_priority_pos_update', time());
                $subj = 'check sync item1';
                //wp_mail( 'elisheva.g@simplyct.co.il', $subj, implode(" ",$response) );
                //}
            
            } else {
                $subj = 'check sync item not working';
                //wp_mail( 'elisheva.g@simplyct.co.il', $subj, $response);
            }

        }
        
    }

    function syncInventoryPriorityPos(){
        $inventory_option = $this->option('sync_inventory_pos_config');
        $inventory_option = str_replace(array("\n", "\t", "\r"), '', $inventory_option);
        $inventory_config = json_decode(stripslashes($inventory_option));

        $daysback = (!empty((int)$inventory_config->days_back) ? $inventory_config->days_back : 1);
        $stamp = mktime(0 - ($daysback * 24), 0, 0);
        $from_date = date(DATE_ATOM, $stamp);
        $data = [
            "FromDateTime" => $from_date, 
        ];
    
        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'OnlineItemStock';
    
        $form_action = 'GetUpdatedItemStock';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        if ($error_code === 0) {
            $response_data = $result['ItemStock'];
            $count = 0;
            $product_parent_ids = array();
            foreach($response_data as $item){
                $count++;
                if ($count % 50 == 0) {
                    // Pause for 1 second (1,000,000 microseconds)
                    usleep(1000000);
                }
                $sku = $item['ItemCode'];
                //write_custom_log('sync product inventory: '.$sku );
                $stock = $item['ActiveItemQuantityInWebWarehouses'];
                write_custom_log('sync product inventory: '.$sku.' with stock: '.$stock );
                $args = array(
                    'post_type' => array('product', 'product_variation'),
                    'post_status' => 'publish',
                    'meta_query' => array(
                        array(
                            'key' => '_sku',
                            'value' => $sku
                        )
                    )
                );
                $my_query = new \WP_Query($args);
                if ($my_query->have_posts()) {
                    while ($my_query->have_posts()) {
                        $my_query->the_post();
                        $product_id = get_the_ID();
                    }
                } else {
                    $product_id = 0;
                }
                if (!$product_id == 0) {
                    
                    $product = wc_get_product($product_id);
                    if ($product->post_type == 'product_variation') {
    
                        $var = wc_get_product($product_id);
                        $var->set_manage_stock(true);
                        $var->set_stock_quantity( $stock );
                        $var->save();
                        //get product parent
                        $product = wc_get_product( $var->get_parent_id() );
                        $product_parent_ids[] = $product->get_id();
                        $product->set_manage_stock(true);
                        // $variations = $product->get_available_variations();
    
                        // $all_out_of_stock = true;
                        // $var_stock = 0;
                        // foreach ($variations as $variation) {
                        //     $variation_obj = wc_get_product($variation['variation_id']);
                        //     $var_stock += $variation_obj->get_stock_quantity();
                        //     if ($variation_obj->is_in_stock()) {
                        //         $all_out_of_stock = false;
                        //         break;
                        //     }
                        // }
                        // $has_stock = ($all_out_of_stock ? 'outofstock' : 'instock');
                        // //$product->set_stock_status($has_stock);
                        // //update_post_meta($product->get_id(), '_stock_status', $has_stock);
                        // $stock_status = $product->get_stock_status();
                        // write_custom_log('parent product : '.$product->get_id().' with status: '.$stock_status );
                        //$product->set_stock_quantity($var_stock);
                        $product->save();   
                    }
                    if ($product->post_type == 'product') {
                        $product->set_manage_stock(true);
                        $product->set_stock_quantity( $stock );
                        $product->save();
                    }

                }

                
            }
            $product_parent_ids = array_unique($product_parent_ids);
            write_custom_log('parent id to update stock status: '.json_encode($product_parent_ids) );
            foreach($product_parent_ids as $parent_product_id){
                //update product status
                $parent_pdt_obj = wc_get_product($parent_product_id);
                $variations = $parent_pdt_obj->get_available_variations();
                $parentstock = 0;
                foreach ($variations as $variation) {
                    $variation_id = $variation['variation_id'];
                    $variation_product = wc_get_product($variation_id);
                    $parentstock += $variation_product->get_stock_quantity();
                }
                if (intval($parentstock) > 0) {
                    //$product->set_stock_status('instock');
                    update_post_meta($parent_product_id, '_stock_status', 'instock');
                    
                } else {
                    //$product->set_stock_status('outofstock');
                    update_post_meta($parent_product_id, '_stock_status', 'outofstock');
                }
                $parent_pdt_obj->set_stock_quantity($parentstock);
                //echo $product->get_stock_status();
                $parent_pdt_obj->save();  
            }
            // add timestamp
            WooAPI::instance()->updateOption('inventory_priority_update_pos', time());
    
        }
        else{
            $message = $result['EdeaError']['DisplayErrorMessage'];
            $multiple_recipients = array(
                get_bloginfo('admin_email'),
                'elisheva.g@simplyct.co.il'
            );
            $subj = 'Error get stock from priority';
            wp_mail( $multiple_recipients, $subj, json_encode($data).'</br>'.$message );
        }
    }

    function syncInventoryYearBackPriorityPos(){
        $daysback = 365;
        $stamp = mktime(0 - ($daysback * 24), 0, 0);
        $from_date = date(DATE_ATOM, $stamp);
        $data = [
            "FromDateTime" => $from_date, 
        ];
    
        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $pos_num = $config->POSNumber;
        $ChannelCode = $config->ChannelCode;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'OnlineItemStock';
    
        $form_action = 'GetUpdatedItemStock';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        if ($error_code === 0) {
            write_custom_log('sync product inventory one year back response' );
            $response_data = $result['ItemStock'];
            $count = 0;
            $product_parent_ids = array();
            foreach($response_data as $item){
                $count++;
                if ($count % 50 == 0) {
                    // Pause for 1 second (1,000,000 microseconds)
                    usleep(1000000);
                }
                $sku = $item['ItemCode'];
                //write_custom_log('sync product inventory: '.$sku );
                $stock = $item['ActiveItemQuantityInWebWarehouses'];
                write_custom_log('sync product inventory: '.$sku.' with stock: '.$stock );
                $args = array(
                    'post_type' => array('product', 'product_variation'),
                    'post_status' => 'publish',
                    'meta_query' => array(
                        array(
                            'key' => '_sku',
                            'value' => $sku
                        )
                    )
                );
                $my_query = new \WP_Query($args);
                if ($my_query->have_posts()) {
                    while ($my_query->have_posts()) {
                        $my_query->the_post();
                        $product_id = get_the_ID();
                    }
                } else {
                    $product_id = 0;
                }
                if (!$product_id == 0) {
                    $product = wc_get_product($product_id);
                    if ($product->post_type == 'product_variation') {
                        $var = new \WC_Product_Variation($product_id);
                        $var->set_manage_stock(true);
                        $var->set_stock_quantity( $stock );
                        $var->save();
                        $product = wc_get_product( $var->get_parent_id() );
                        $product_parent_ids[] = $product->get_id();
                        $product->set_manage_stock(true);
                    }
                    if ($product->post_type == 'product') {
                        $product->set_manage_stock(true);
                        $product->set_stock_quantity( $stock );
                    }
                    $product->save();
                }

            }
            $product_parent_ids = array_unique($product_parent_ids);
            write_custom_log('parent id to update stock for sync year back: '.json_encode($product_parent_ids) );
            $parent_count = 0;
            foreach($product_parent_ids as $parent_product_id){
                //update product status
                $parent_count++;
                if ($parent_count % 50 == 0) {
                    // Pause for 1 second (1,000,000 microseconds)
                    usleep(1000000);
                }
                $parent_pdt_obj = wc_get_product($parent_product_id);
                $variations = $parent_pdt_obj->get_available_variations();
                $parentstock = 0;
                foreach ($variations as $variation) {
                    $variation_id = $variation['variation_id'];
                    $variation_product = wc_get_product($variation_id);
                    $parentstock += $variation_product->get_stock_quantity();
                }
                if (intval($parentstock) > 0) {
                    //$product->set_stock_status('instock');
                    update_post_meta($parent_product_id, '_stock_status', 'instock');
                    write_custom_log('update stock status is stock for pdt: '.$parent_product_id );
                    
                } else {
                    //$product->set_stock_status('outofstock');
                    update_post_meta($parent_product_id, '_stock_status', 'outofstock');
                    write_custom_log('update stock status ot of stock for pdt: '.$parent_product_id );
                }
                
                $parent_pdt_obj->set_stock_quantity($parentstock);
                //echo $product->get_stock_status();
                $parent_pdt_obj->save();  
            }
            // add timestamp
            WooAPI::instance()->updateOption('inventory_priority_update_pos', time());
    
        }
        else{
            $message = $result['EdeaError']['DisplayErrorMessage'];
            $multiple_recipients = array(
                get_bloginfo('admin_email')
            );
            $subj = 'Error get stock one year back from priority ';
            wp_mail( $multiple_recipients, $subj, json_encode($data).'</br>'.$message );
        }
    }


    function syncPricePriorityPos(){
        $price_option = $this->option('sync_price_pos_config');
        $price_option = str_replace(array("\n", "\t", "\r"), '', $price_option);
        $price_config = json_decode(stripslashes($price_option));

        $daysback = (!empty((int)$price_config->days_back) ? $price_config->days_back : 1);
        $stamp = mktime(0 - ($daysback * 24), 0, 0);
        $from_date = date(DATE_ATOM, $stamp);
        $data = [
            "FromDateTime" => $from_date, 
        ];
    
        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        $form_name = 'ItemPrice';
    
        $form_action = 'GetUpdatedItemPrice';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        if ($error_code === 0) {
            $response_data = $result['ItemPrice'];
            $count = 0;
            foreach($response_data as $item){
                $count++;
                if ($count % 50 == 0) {
                    // Pause for 1 second (1,000,000 microseconds)
                    usleep(1000000);
                }
                
                $sku = $item['ItemCode'];
                $price = $item['PriceAfterVAT'];

                $args = array(
                    'post_type' => array('product', 'product_variation'),
                    'post_status' => 'publish',
                    'meta_query' => array(
                        array(
                            'key' => '_sku',
                            'value' => $sku
                        )
                    )
                );
                $my_query = new \WP_Query($args);
                if ($my_query->have_posts()) {
                    while ($my_query->have_posts()) {
                        $my_query->the_post();
                        $product_id = get_the_ID();
                    }
                } else {
                    $product_id = 0;
                }
                if (!$product_id == 0) {
                    
                    update_post_meta($product_id, '_regular_price', $price);
                    
                }
            }

            // add timestamp
            WooAPI::instance()->updateOption('price_priority_update_pos', time());
        }
        else{
            $message = $result['EdeaError']['DisplayErrorMessage'];
            $multiple_recipients = array(
                get_bloginfo('admin_email'),
                'elisheva.g@simplyct.co.il'
            );
            $subj = 'Error get item from priority';
            wp_mail( $multiple_recipients, $subj, $message );
        }
        
    }

    function syncSalePricePriorityPos(){
        write_custom_log('enter sale price sync' );
        $price_option = $this->option('sync_sale_price_pos_config');
        $price_option = str_replace(array("\n", "\t", "\r"), '', $price_option);
        $price_config = json_decode(stripslashes($price_option));

        $daysback = (!empty((int)$price_config->days_back) ? $price_config->days_back : 1);
        $stamp = mktime(0 - ($daysback * 24), 0, 0);
        $from_date = date(DATE_ATOM, $stamp);
        $chunknumber = 1;
        $is_last_chunk = false;
        $data = [
            "FromDateTime" => $from_date, 
            //"ChunkNumber" => 1,
            "ChunkSize" => (!empty((int)$price_config->chunk_size) ? $price_config->chunk_size : 50)
        ];
    
        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        $form_name = 'ItemSpecialPrice';
    
        $form_action = 'GetUpdatedItemSpecialPriceChunk';
        $response_data = [0];
        while(sizeof($response_data) > 0){
            $data['ChunkNumber'] = $chunknumber;
            $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
            $error_code = $result["ErrorCode"];
            if ($error_code === 0) {
                $is_last_chunk = $result["IsLastChunk"];
                $response_data = $result['ItemsSpecialPriceDetails'];
                if(!empty($response_data)){
                    $api_salecode = [];
                    $web_salecode = [];
    
                    //get all products id with salecode to check if still in sale
                    $args = array(
                        'post_type' => 'product_variation',
                        'posts_per_page' => -1,
                        'fields' => 'ids',
                        'meta_query' => array(
                            array(
                                'key' => 'sale_code_field',
                                'value' => '',
                                'compare' => '!='
                            )
                        )
                    );
                    $query = new WP_Query($args);
                    $product_ids = $query->posts;
                    write_custom_log('all pdts id with sale code:' );
                    foreach ($product_ids as $product_id) {
                        write_custom_log($product_id);
                        $web_salecode[$product_id] =  get_post_meta( $product_id, 'sale_code_field', true );
                    }
    
    
                    foreach($response_data as $item){
                        $best_price = $item["IsBestPriceForDigital"];
                        $sale_code = $item["SaleCode"]; 
                        $sku = $item['ItemCode']; //22011103XL
                        $variation_id = wc_get_product_id_by_sku($sku); //625
                        $discount_percent = $item['DiscountPercent']; //25
                        write_custom_log('check sale for sku: '.$sku );
                        write_custom_log('best price: '.$best_price );
                        // check if need to remove the sale
                        if (array_key_exists($variation_id, $web_salecode)) {
                            $wb_id = $web_salecode[$variation_id];
                            if( $sale_code == $web_salecode[$variation_id] ){
                                write_custom_log('sync sale for id : '.$variation_id );
                                write_custom_log('best price : '.$best_price );
                                if($best_price == false){
                                    write_custom_log('sync remove sale for sku: '.$sku );
                                    //update_field( $field_key, '', $post_id );
                                    delete_post_meta($variation_id, 'sale_code_field');
                                    $product = wc_get_product($variation_id);
                                    if ($product) {
                                        $product->set_sale_price('');
                                        $product->save();
                                        wc_delete_product_transients( $variation_id );
                                    }
                                }
                            }
                        }
                        if($best_price == true){
                            write_custom_log('sync add sale for sku: '.$sku );
                        
                            $product = wc_get_product($variation_id);
                            if ($product) {
                                $original_price = $product->get_regular_price(); //355
                                $discount_amount = $original_price * ($discount_percent/100); //88.75
                                $sale_price = $original_price - $discount_amount; //266.25
                                $product->set_sale_price($sale_price);
                                update_post_meta( $variation_id, 'sale_code_field', $sale_code);
                                $product->save();
                            }
                        }
                    }
                    $chunknumber++;
                    // add timestamp
                    WooAPI::instance()->updateOption('sale_price_priority_update_pos', time());
                }
            }
            else{
                $message = $result['EdeaError']['DisplayErrorMessage'];
                $multiple_recipients = array(
                    get_bloginfo('admin_email'),
                    'elisheva.g@simplyct.co.il'
                );
                $subj = 'Error get item in sale from priority';
                wp_mail( $multiple_recipients, $subj, json_encode($data).'</br>'.$message );
            }
        }
        
    }

    function syncPriorityOrderStatusPos(){
        $order_status_option = $this->option('sync_orders_status_pos_config');
        $order_status_option = str_replace(array("\n", "\t", "\r"), '', $order_status_option);
        $order_status_config = json_decode(stripslashes($order_status_option));

        $daysback = (!empty((int)$order_status_config->days_back) ? $order_status_config->days_back : 1);
        $stamp = mktime(0 - ($daysback * 24), 0, 0);
        $from_date = date(DATE_ATOM, $stamp);
        $page_number = 1;
        $page_size = 50;
        $is_last_page = false;


        $raw_option = $this->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;


        $data = [
            "FromStatusDate" => $from_date, 
            "PageSize" => $page_size,
            //"PageNumber" => $page_number,
            "Branches" => [$branch_num]
        ];
    
        
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
        $form_name = 'OnlinePriorityOrderStatus';
    
        $form_action = 'GetOrdersStatus';
        $ordersStatus = [0];
        while(sizeof($ordersStatus) > 0){
            $data['PageNumber'] = $page_number;
            $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
            //write_custom_log('sync status order page'.$page_number.': '.json_encode($result) );
            $error_code = $result["ErrorCode"];
            if ($error_code === 0) {
                $is_last_page = $result["IsLastPage"];
                $ordersStatus = $result["OrdersStatus"];
                if(!empty($ordersStatus)){
                    foreach($ordersStatus as $order){
                        //$order_id = $order["OrderNumber"];
                        $book_num = $order["BookNumber"];
                        if($book_num != ''){
                            write_custom_log('sync status order for book number: '.$book_num );
                            $status = $order["Status"];
        
                            $args = array(
                                'limit' => 1,
                                'return' => 'ids',
                                'meta_query' => array(
                                    array(
                                        'key' => 'priority_pos_cart_number',
                                        'value' => $book_num,
                                        'compare' => 'LIKE',
                                    ),
                                ),
                            );
                            
                            $orders = wc_get_orders( array(
                                'meta_key'   => 'priority_pos_cart_number',
                                'meta_value' => $book_num,
                            ) );
    
                            foreach( $orders as $site_orders ) {
                                $site_order_id = $site_orders->get_id();
                                $site_order = wc_get_order($site_order_id);
                        
                                if (!empty($site_order)) {
                                    write_custom_log('update status:'.$status.' to order: '.$site_order_id );
                                    if($status == "מבוטלת")
                                        $site_order->update_status('cancelled');
                                    if($status == "בוצעה")
                                        $site_order->update_status('completed');
                                    if($status == "שודר")
                                        $site_order->update_status('processing');
                                    if($status == "POS_ORDER")
                                        $site_order->update_status('processing');
                                }
                            }
                        }       
                    }
                }
                $page_number++;
            }
            else{
                $message = $result['EdeaError']['DisplayErrorMessage'];
                $multiple_recipients = array(
                    get_bloginfo('admin_email'),
                    'elisheva.g@simplyct.co.il'
                );
                $subj = 'Errorsync order status from priority to site';
                wp_mail( $multiple_recipients, $subj, $message );
            }
        }
 

       
    }
    
    function syncItemsWebPos(){

        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'Items';
    
        $form_action = 'GetItemsForWebCodes';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        $web_variation_sku = [];
        if ($error_code === 0) {

            $web_sku = $result['ItemCodes'];
            $product_web_ids = array();
            foreach($web_sku as $sku){
                $variation_id = wc_get_product_id_by_sku($sku);
                if($variation_id != null){
                    $product_id = wp_get_post_parent_id($variation_id);
                    $product_web_ids[] = $product_id;
                    $web_variation_sku[] = $sku;
                }
            }
            //remove duplicate from array because all variation have same product id
            $product_web_ids = array_unique($product_web_ids);
            
            //get product id from site and check if product id in array of web product id
            //if not set the post draft



            global $wpdb;
            $all_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" );
            
            //$ids_to_remove = array_diff($product_web_ids, $all_ids);  
            $ids_to_remove = array_udiff($all_ids, $product_web_ids, function ($a, $b) { return (int)$a <=> (int)$b; }); 

            $product_ids_string = implode( ',', $ids_to_remove );

            $update_query = $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_status = 'draft' WHERE post_type = 'product' AND ID IN ({$product_ids_string})" );

            $wpdb->query( $update_query );

            // add timestamp
            //$this->updateOption('items_web_update_pos', time());
            WooAPI::instance()->updateOption('items_web_update_pos', time());

            //get all variation sku from site, to compare with variation from priority 
            //and remove stock for variation that not more supposed tobe in the site.
            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            );
            
            $products = get_posts($args);
            
            $variation_skus = array();
            
            foreach ($products as $product) {
                $product_obj = wc_get_product($product->ID);
            
                if ($product_obj->is_type('variable')) {
                    $variations = $product_obj->get_available_variations();
            
                    foreach ($variations as $variation) {
                        $variation_skus[] = $variation['sku'];
                    }
                }
            }

            $sku_to_remove = array_diff($variation_skus, $web_variation_sku); 
            foreach($sku_to_remove as $my_sku){
                $variation_id = wc_get_product_id_by_sku($my_sku);
                write_custom_log('remove product variation stock for sku not in web anymore: '.$my_sku);
                update_post_meta($variation_id, '_stock', 0);
                update_post_meta($variation_id, '_stock_status', 'outofstock');
            }
        }
        else {
            $response = $body_array['EdeaError']['DisplayErrorMessage'];
            $multiple_recipients = array(
                get_bloginfo('admin_email')
            );
            $subj = 'Error get item for web from priority';
            wp_mail( $multiple_recipients, $subj, $message );
        }
    }

    function syncColorDetails(){
        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'ItemColors';
    
        $form_action = 'GetColors';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        if ($error_code === 0) {

            $response_data = $result['ColorDetails'];
            foreach($response_data as $item){
                $color_code = $item['ColorCode'];
                $color_name = $item['ForeignLanguageColorDescription'];
            
                $args = array(
                    'posts_per_page'	=> -1,
                    'post_type' => array('product', 'product_variation'),
                    'post_status' => 'publish',
                    'meta_query' => array(
                        array(
                            'key' => 'color_code',
                            'value' => $color_code
                        )
                    )
                );
                $posts = get_posts( $args );
                // echo '<pre>';
                // print_r($posts);
                // echo '</pre>';die;
                foreach( $posts as $post ) : 
                    setup_postdata( $post );
                    $pdt_id = $post->ID;
                    update_post_meta( $pdt_id, "color",  $color_name );
                    //update_field( 'related_care_sku',$value, $pdt_id );
                    wp_reset_postdata(); 
                endforeach;
            }

            // add timestamp
            //$this->updateOption('items_web_update_pos', time());
           
      
        }
        else {
            $response = $body_array['EdeaError']['DisplayErrorMessage'];
            $multiple_recipients = array(
                get_bloginfo('admin_email'),
                'elisheva.g@simplyct.co.il'
            );
            $subj = 'Error get color details from priority';
            wp_mail( $multiple_recipients, $subj, $message );
        }

    }

    
    public function openOrUpdateTransaction($product_id,$quantity, $variation_id){
        
        $items_in_bag = [];
        if (  WC()->cart->get_cart_contents_count() == 0 ) {
            $temporarytransactionnumber = '';
        }
        else{
            $i = 0;
            foreach (WC()->cart->get_cart_contents() as $cart_item ) {
                if($i == 0){
                    $temporarytransactionnumber = $cart_item['temporary_transaction_num'];
                    $i ++;
                }
                $pdt = $cart_item['data'];
                $pdt_id = $cart_item['product_id'];
                $vtion_id = $cart_item['variation_id'];
                $quantity = $cart_item['quantity'];
                //$price = WC()->cart->get_product_price( $product );
                //$price = get_post_meta($cart_item['product_id'] , '_price', true);
                $price = $cart_item['data']->get_regular_price();
                $sku = $pdt->get_sku();
                
                $items_in_bag [] = [
                    'OrderItemBasicInputDetails' => [
                        "ItemCode" => $sku,
                        "Barcode" => "",
                        "ItemQuantity" => $quantity,
                        "PricePerItem" => $price,
                        "CalculatePrice" => false,
                        "IsManualPrice" => false,
                        "IsManualDiscount" => false,
                        "VATPercent" => 17,
                        "ClubCode" => ""
                    ],
                    "PointsPerType" => []
                ];
            }
        }

         //product info
         if($product_id){
            if($variation_id){
                $product = wc_get_product($variation_id);
            }
            else{
                $product = wc_get_product( $product_id );
            }
            $pdt_sku = $product->get_sku();
            $pdt_price = get_post_meta($product_id , '_price', true);
            $pdt_qtty = $quantity;
            $items_in_bag [] = [
                'OrderItemBasicInputDetails' => [
                    "ItemCode" => $pdt_sku,
                    "Barcode" => "",
                    "ItemQuantity" => $pdt_qtty,
                    "PricePerItem" => $pdt_price,
                    "CalculatePrice" => false,
                    "IsManualPrice" => false,
                    "IsManualDiscount" => false,
                    "VATPercent" => 17,
                    "ClubCode" => ""
                ],
                "PointsPerType" => []
            ];
         }
         
        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;

        if (is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $user_id = $current_user->ID;
            $cust_priority_number = get_user_meta($user_id, 'priority_customer_number', true);
            $cust_name = $current_user->user_firstname.' '.$current_user->user_lastname;
            $cust_name = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $cust_name);
            $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
            $billing_address_1 = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $billing_address_1);
            $billing_city = get_user_meta($user_id, 'billing_city', true);
            $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
            $cust_phone = get_user_meta($user_id, 'billing_phone', true);
            $cust_phone = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $cust_phone);
            $cust_id_number = get_user_meta($user_id, 'account_id', true);
            $is_club = get_user_meta($user_id, 'is_club', true);
            $want_club = get_user_meta($user_id, 'want_club_registration', true);
            $has_paid_club = get_user_meta( $user_id, 'club_fee_paid', true );
        }
        else{
            $cust_priority_number = '';
            $cust_name = '';
            $billing_address_1 = '';
            $billing_city = '';
            $billing_postcode = '';
            $cust_phone = '';
            $cust_id_number = '';
            $is_club = '';
            $has_paid_club = 1;
            $want_club = 0;
        }

        if(!empty($is_club)){
            $club_code = '01';
        }
        else{
            $club_code = '';
        }

        $data['Transaction']['TransactionBasicDetails'] = [
            "TemporaryTransactionNumber" => $temporarytransactionnumber,
            "TransactionDateTime" => current_time('mysql'),
            "IsOrder" => true,
            "IsCancelTransaction" => false,
            "POSCustomerNumber" => $cust_priority_number,
            "ClubCode" => $club_code,
            "IsManualDiscount" => false,
            "SupplyBranch" => ""
        ];
        $data['Transaction']['TransactionItems'] = [];

        $data['Transaction']['ShippingDetails'] = [
            "City" => "",
            "ForeignLanguageCity" => "",
            "Address" => "",
            "ForeignLanguageAddress" => "",
            "HouseNumber" => 0,
            "ApartmentNumber" => 0,
            "ZipCode" => "",
            "ContactPersonName" => "",
            "ForeignLanguageContactPersonName" => "",
            "Mail" => "",
            "Fax" => "",
            "SupplyDate" => "2022-11-06T10:12:54.619Z",
            "FromSupplyHour" => "2022-11-06T10:12:54.619Z",
            "ToSupplyHour" => "2022-11-06T10:12:54.619Z",
            "Remark" => "",
            "ForeignLanguageRemark" => "",
            "FirstPhoneNumber" => "",
            "SecondPhoneNumber" => "",
            "ShipMethod" => "",
            "Address2" => "",
            "Address3" => "",
            "Email" => ""
        ];

        $data['Transaction']['OrderItems'] = $items_in_bag;

        $default_club = '777';
        $default_club = $config->CLUB_DEFAULT_PARTNAME ?? $default_club;
        $fee_amount  = get_field('club_cost','option');
        if($has_paid_club == 0 && $want_club == 1){
            $data['Transaction']['OrderItems'][] = [
                'OrderItemBasicInputDetails' => [
                    "ItemCode" => $default_club,
                    "Barcode" => "",
                    "ItemQuantity" => 1,
                    "PricePerItem" => $fee_amount,
                    "CalculatePrice" => false,
                    "IsManualPrice" => false,
                    "IsManualDiscount" => false,
                    "VATPercent" => 17,
                    "ClubCode" => "01"
                ],
                "PointsPerType" => []
            ];
        }

        $data['Transaction']['Remark'] = [
            "CustomerName" => $cust_name,
            "CustomerIDNumber" => $cust_id_number,
            "CustomerPhone" => $cust_phone,
            "CustomerAddress" => $billing_address_1,
            "CustomerCity" => $billing_city,
            "CustomerZipCode" => $billing_postcode
        ];

        $data['temporaryTransactionNumber'] = $temporarytransactionnumber;

        $data['TransactionProcessingSettings'] = [
            "CalculateSales" => true,
            "ContainExternalMetaData" => false,
            "RegisterByGeneralPosCustomer" => !empty($cust_priority_number) ? false : true,
            "RetrieveItemPictureFilename" => false,
            "CalculateTax" => 0
        ];

        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        // $form_name = 'Transactions';

        // if($temporarytransactionnumber == null){
        //     $form_action = 'OpenTransaction';
        // }
        // else{
        //     $form_action = 'UpdateTransaction';
        // }

        //$data = json_encode($data);
        //$result = CardPOS::instance()->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
        return $data;
    }

    public function getCoupons($coupon_code){
        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            $temporarytransactionnumber = '';
        }
        else{
            foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
                $temporarytransactionnumber = $cart_item['temporary_transaction_num'];
            }
        }

        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;

        $pos = strpos($coupon_code, '-');
        if($pos !== false){
            $pos = explode("-", $coupon_code );
            $code = $pos[0];
            $unique_num = $pos[1];
        }
        else{
            $code = $coupon_code;
            $unique_num = "";
        }

        $data['Coupons']['CouponsToAdd'][] = [
            "CouponCode" => $code,
            "UniqueNumber" => $unique_num,
            "NumberOfScans" => 1,
            "ExternalCoupon" => true
        ];
        $data['temporaryTransactionNumber'] = $temporarytransactionnumber;
        
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        $form_name = 'Transactions';
        $form_action = 'SetAssets';

        $data = json_encode($data);
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);

        return $result;
    }

    
    public function removeCoupons($coupon_code){
        
        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            $temporarytransactionnumber = '';
        }
        else{
            foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
                $temporarytransactionnumber = $cart_item['temporary_transaction_num'];
            }
        }

        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;

        $pos = strpos($coupon_code, '-');
        
        if($pos !== false){
            $pos = explode("-", $coupon_code );
            $code = $pos[0];
            $unique_num = $pos[1];
        }
        else{
            $code = $coupon_code;
            $unique_num = "";
        }

        $data['Coupons']['CouponsToRemove'][] = [
            "CouponCode" => $code,
            "UniqueNumber" => $unique_num,
            "NumberOfScans" => 1,
            "ExternalCoupon" => true
        ];
        $data['temporaryTransactionNumber'] = $temporarytransactionnumber;
        
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        $form_name = 'Transactions';
        $form_action = 'SetAssets';

        $data = json_encode($data);
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);

        return $result;
    }

    function update_transaction_before_payment($order_id){
        include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
        include_once WC_ABSPATH . 'includes/class-wc-cart.php';
        $order = wc_get_order( $order_id ); 
        // global $woocommerce;
        // if ( is_null($woocommerce->cart) ) {
        //     wc_load_cart();
        // }
        //retrieve data from last update

        $form_name = 'Transactions';
        $form_action = 'UpdateTransaction';
        $data = $this->openOrUpdateTransaction(0,0,0);
        $data = json_encode($data);
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
        $error_code = $result["ErrorCode"];
        //$error_src = $result['EdeaError']['ErrorSource'];
        if ($error_code != 0) {
            // the order is locked so cancel
            if($error_code == 59){
                $this->cancel_transaction();
                
            }
        }
        $data = $this->openOrUpdateTransaction(0,0,0);
        if ( isset($_POST['storelist']) && ! empty($_POST['storelist']) ) {
            $store_address = preg_split ("/\,/", $_POST['storelist']); 
            $address = "חנות GANT".' '.$store_address[0];
            $address = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $address);
            $city = $store_address[1];
            $city = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $city);
            $contact_person = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $contact_person = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $contact_person);
            $phone = $order->get_billing_phone();
        }
        else{
            if ($_POST['ship_to_different_address'] == 1 && !empty($order->get_shipping_address_1())) {
                $address = $order->get_shipping_address_1();
                $address = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $address);
            } else {
                $address = $order->get_billing_address_1();
                $address = str_replace( array( '\'',':', '.','"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $address);
            }
    
    
            if ($_POST['ship_to_different_address'] == 1 &&  !empty($order->get_shipping_city())) {
                $city = $order->get_shipping_city();
                $city = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $city);
            } else {
                $city = $order->get_billing_city();
                $city = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $city);
            }

    
            if ($_POST['ship_to_different_address'] == 1 &&  !empty($order->get_shipping_address_1())) {
                $contact_person = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
                $contact_person = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $contact_person);
            } else {
                $contact_person = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $contact_person = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $contact_person);
            }
    
            if ($_POST['ship_to_different_address'] == 1 && !empty(get_post_meta($order->get_id(), '_shipping_phone', true))) {
                $phone = get_post_meta($order->get_id(), '_shipping_phone', true);
                $phone = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $phone);
            } else {
                $phone = $order->get_billing_phone();
                $phone = str_replace( array( '\'',':', '.','"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $phone);
            }
        }
        $order_notes = $order->get_customer_note();
        if(!empty($order_notes)){
            $order_notes = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $order_notes);
        }
        //add shipping data 
        $data['Transaction']['ShippingDetails'] = [
            "City" => $city,
            "ForeignLanguageCity" => "",
            "Address" => $address,
            "ForeignLanguageAddress" => "",
            "HouseNumber" => 0,
            "ApartmentNumber" => 0,
            "ZipCode" => "",
            "ContactPersonName" => $contact_person,
            "ForeignLanguageContactPersonName" => "",
            "Mail" => "",
            "Fax" => "",
            "SupplyDate" => "2022-04-19T06:56:24.279Z",
            "FromSupplyHour" => "2022-04-19T06:56:24.279Z",
            "ToSupplyHour" => "2022-04-19T06:56:24.279Z",
            "Remark" => !empty($order_notes) ? $order_notes : '',
            "ForeignLanguageRemark" => "",
            "FirstPhoneNumber" => $phone,
            "SecondPhoneNumber" => "",
            "ShipMethod" => $order->get_shipping_method(),
            "Address2" => (!empty($order->get_shipping_address_2())) ? $order->get_shipping_address_2() : '',
            "Address3" => "",
            "Email" => $order->get_billing_email(),
            "CountryCode" => "",
            "StateCode" => ""
        ];

        $data['Transaction']['Remark'] = [
            "CustomerName" => "",
            "CustomerIDNumber" => "",
            "CustomerPhone" => $phone,
            "CustomerAddress" => "",
            "CustomerCity" => "",
            "CustomerZipCode" => ""
        ];

        $raw_option = WooAPI::instance()->option('setting-config');


        $default_product = '000';
        $default_product = $config->SHIPPING_DEFAULT_PARTNAME ?? $default_product;
        $shipping_method = $order->get_shipping_methods();
        $shipping_method = array_shift($shipping_method);
        if (isset($shipping_method)) {
            $shipping_data = $shipping_method->get_data();
            $method_title = $shipping_data['method_title'];
            $method_id = $shipping_data['method_id'];
            $instance_id = $shipping_data['instance_id'];
            $shipping_price = $shipping_data['total'] + $shipping_data['total_tax'];
            if( $shipping_price > 0){
                $data['Transaction']['OrderItems'][] = [
                    'OrderItemBasicInputDetails' => [
                        "ItemCode" => $default_product,
                        "Barcode" => "",
                        "ItemQuantity" => 1,
                        "PricePerItem" => $shipping_price,
                        "CalculatePrice" => false,
                        "IsManualPrice" => false,
                        "IsManualDiscount" => false,
                        "VATPercent" => 17,
                        "ClubCode" => ""
                    ],
                    "PointsPerType" => []
                ];
            }


        }


        $order_item = $data['Transaction']['OrderItems'];
    
        $form_name = 'Transactions';
        $form_action = 'UpdateTransaction';

        update_post_meta($order_id, 'request_transaction_update', $data);
        $data = json_encode($data);
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
        // update transaction array in cart item to approve result to send it to close transaction after payment
        $error_code = $result["ErrorCode"];
        if ($error_code === 0) {
            $result_order_items = $result["Transaction"]["OrderItems"];
            $first = 0;
            update_post_meta($order_id, 'response_transaction_update', $result);
        }
        else{
            $error_src = $result['EdeaError']['ErrorSource'];
            $error_msg = $result['EdeaError']['ErrorMessage'];
            wc_add_notice( 'error updating bag: '.$error_msg, 'error' );
            update_post_meta($order_id, 'response_transaction_update_error', $data);
            $order->update_meta_data('priority_pos_cart_status', $error_msg);
            $order->save();
            exit;
            // $multiple_recipients = array(
            //     get_bloginfo('admin_email')
            // );
            // $subj = 'Error get item for web from priority';
            // wp_mail( $multiple_recipients, $subj, $error_msg );
            // wp_die();
            

        }

        return $result;
    }

    function cancel_transaction(){
        $i = 0;
        if ( WC()->cart->get_cart_contents_count() > 0 ) {
            foreach ( WC()->cart->get_cart_contents() as $key =>$cart_item ) {
                //iterate only once, to get transaction array
                if($i!=0)  break;
                $raw_option = WooAPI::instance()->option('setting-config');
                $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
                $config = json_decode(stripslashes($raw_option));
                $branch_num = $config->BranchNumber;
                $unique_id = $config->UniqueIdentifier;
                $ChannelCode = $config->ChannelCode;
                $pos_num = $config->POSNumber;

                //$temporarytransactionnumber = $cart_item['temporary_transaction_num'];
                //retrieve approve result from cart to send it to cancel to allow adding product to bag after the transaction was locked
                $data = $cart_item['lastapprove_transaction'];
                //$data['temporaryTransactionNumber'] = $temporarytransactionnumber ;
                //$data['Transaction']['TemporaryTransactionNumber'] = $temporarytransactionnumber;
                $data['UniquePOSIdentifier'] = [
                    "BranchNumber" => $branch_num,
                    "POSNumber" => $pos_num,
                    "UniqueIdentifier" => $unique_id,
                    "ChannelCode" => $ChannelCode,
                    "VendorCode" => "",
                    "ExternalAccountID" => ""
                ];

                $form_name = 'Transactions';
                $form_action = 'CancelTransactionApproval';


                $data = json_encode($data);
                $result_cancel = CardPOS::instance()->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
                $error_code = $result_cancel["ErrorCode"];
                if ($error_code != 0) {
                    $error_msg = $result_cancel['EdeaError']['ErrorMessage'];
                    wc_add_notice( 'error in cancel transaction: '.$error_msg, 'error' );
                    //if cancel give error, empty cart to start new transaction
                    $cart = WC()->cart;
                    $cart->empty_cart();
                    exit;
                }
                else{
                    $cart_item['lastapprove_transaction'] =  $result_cancel; 
                    $cart_item['temporaryTransactionNumber'] =  $result_cancel["Transaction"]["TemporaryTransactionNumber"];    
                    WC()->cart->cart_contents[$key] = $cart_item;
                }
                $i++;
            }
        
            WC()->cart->set_session();
        }
    }

    function approve_transaction($order_id){
        $this->update_transaction_before_payment($order_id);
        //$order_id = $order->get_id();
        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $ChannelCode = $config->ChannelCode;
        $pos_num = $config->POSNumber;

        //$data = $this->update_transaction_before_payment($order_id);
        $data = get_post_meta($order_id, 'response_transaction_update', true);
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        $form_name = 'Transactions';
        $form_action = 'ApproveTransaction';


        $data = json_encode($data);
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
        // update transaction array in cart item to approve result to send it to close transaction after payment
        $error_code = $result["ErrorCode"];
        if ($error_code === 0) {
            update_post_meta($order_id, 'response_transaction_approve', $result);
            $i = 0;
            
            foreach ( WC()->cart->get_cart_contents() as $key => $cart_item ) {
                //iterate only once, to get transaction array
                if($i!=0)  break;
                //save also approve result to cart
                $cart_item['lastapprove_transaction'] =  $result; 
                //wp_mail( 'elisheva.g@simplyct.co.il', 'data cart approve', $data );
                //$cart_item['cart_locked'] =  'true';    
                WC()->cart->cart_contents[$key] = $cart_item;
                $i++;
            }
            WC()->cart->set_session();
        }
        else{
            $message = $result['EdeaError']['ErrorMessage'];
            wc_add_notice( 'error in approve transaction: '.$message, 'error' );
            //update_post_meta($order_id, 'response_transaction_approve', $result);
            update_post_meta($order_id, 'response_transaction_approve_error_msg', $message);
            $order->update_meta_data('priority_pos_cart_status', $message);
            $order->save();
            exit;
        }

        return $resut;

     
    }

    public function close_transaction($order_id){
        $data = get_post_meta($order_id, 'response_transaction_approve', true);
        if(!empty($data)){
            include_once WC_ABSPATH . 'includes/wc-cart-functions.php';
            include_once WC_ABSPATH . 'includes/class-wc-cart.php';
            global $woocommerce;
            $order = wc_get_order( $order_id ); 
            // check order status against config
            $config = json_decode(stripslashes($this->option('setting-config')));
            $raw_option = WooAPI::instance()->option('setting-config');
            $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
            $config = json_decode(stripslashes($raw_option));
            $branch_num = $config->BranchNumber;
            $unique_id = $config->UniqueIdentifier;
            $pos_num = $config->POSNumber;
            $ChannelCode = $config->ChannelCode;
            $gateway = $config->gateway ?? 'debug';
            if (!isset($config->order_statuses)) {
                //$is_status = "processing";
                $statuses = ["processing"];
                $is_status = in_array($order->get_status(), $statuses);
            } else {
                $statuses = explode(',', $config->order_statuses);
                $is_status = in_array($order->get_status(), $statuses);
            }
            $payment_method = get_post_meta($order_id, '_payment_method', true);
            //if payment is bacs, its debug
            if (($is_status) || $payment_method == 'bacs') {
                if (($is_status) || $payment_method == 'bacs') { 
                    //update_post_meta($order_id, '_post_pos_done', true);
                    $data['UniquePOSIdentifier'] = [
                        "BranchNumber" => $branch_num,
                        "POSNumber" => $pos_num,
                        "UniqueIdentifier" => $unique_id,
                        "ChannelCode" => $ChannelCode,
                        "VendorCode" => "",
                        "ExternalAccountID" => ""
                    ];

                    $data["ExternalOrderNumber"] = $order->get_order_number();
                    $left_to_pay =  $data['Transaction']['LeftToPay'];
                    $payment_method = get_post_meta($order->get_id(), '_payment_method', true);
                    $gateway = $config->gateway ?? 'debug';
                    //$gateway = get_post_meta( $order_id, '_payment_method', true );
                    //debug
                    if($payment_method == 'bacs'){
                        $data['CreditCardPayments'][] = [
                            "CardNumber" => "12345",
                            "PaymentSum" => $left_to_pay,
                            "AuthorizationNumber" => "12345",
                            "CardIssuerCode" => 1,
                            "CardClearingCode" => 1,
                            "VoucherNumber" => "12345",
                            "NumberOfPayments" => 1,
                            "FirstPaymentSum" => $left_to_pay,
                            "Token" => 12345,
                            "ExpirationDate" => "0126",
                            "CreditType" => 0,
                        ];
                    }
                    else{
                        if ($payment_method == 'wc-pelecard') {
                            $pelecard_transaction_data = get_post_meta( $order_id, '_transaction_data' );
                            foreach ( $pelecard_transaction_data as $order_cc_meta ) {
                                //print_r($order_cc_meta);
                                if($order_cc_meta["StatusCode"] != '000'){
                                    continue;
                                }
                                $paymentcode = !empty($order_cc_meta['CreditCardCompanyClearer']) ? $order_cc_meta['CreditCardCompanyClearer'] : $paymentcode;
                                $payaccount = $order_cc_meta['CreditCardNumber'];
                                $ccuid = $order_cc_meta['Token'];
                                $validmonth = $order_cc_meta['CreditCardExpDate'];
                                $confnum = $order_cc_meta['DebitApproveNumber'];
                                $numpay = $order_cc_meta['TotalPayments'];
                                $paymentsum = $order_cc_meta['DebitTotal'] / 100;
                                $firstpay = $order_cc_meta['FirstPaymentTotal'] / 100;
                                $vouchernumber = str_replace("-", "", $order_cc_meta['VoucherId']);
                                $idnum = $order_cc_meta['CardHolderID'];
                                $credittype = $order_cc_meta['CreditType'];
                            }
                            //echo 'enter pelecard';
                            // $order_cc_meta = $order->get_meta('_transaction_data');
                            // $paymentcode = !empty($order_cc_meta['CreditCardCompanyClearer']) ? $order_cc_meta['CreditCardCompanyClearer'] : $paymentcode;
                
                            // $payaccount = substr($order_cc_meta['CreditCardNumber'], -4);
                            // $ccuid = $order_cc_meta['Token'];
                            // $validmonth = $order_cc_meta['CreditCardExpDate'];
                            // $confnum = $order_cc_meta['DebitApproveNumber'];
                            // $numpay = $order_cc_meta['TotalPayments'];
                            // $paymentsum = $order_cc_meta['DebitTotal'] / 100;
                            // $firstpay = $order_cc_meta['FirstPaymentTotal'] / 100;
                            // $vouchernumber = str_replace("-", "", $order_cc_meta['VoucherId']);
                            // $idnum = $order_cc_meta['CardHolderID'];
                            // $credittype = $order_cc_meta['CreditType'];
                
                            $data['CreditCardPayments'][] = [
                                "CardNumber" => $payaccount,
                                //"PaymentSum" => $paymentsum,
                                "PaymentSum" => $left_to_pay,
                                "AuthorizationNumber" => $confnum,
                                "CardIssuerCode" => 1,
                                "CardClearingCode" => 1,
                                "VoucherNumber" => $vouchernumber,
                                "NumberOfPayments" => $numpay,
                                "FirstPaymentSum" => $firstpay,
                                //"FirstPaymentSum" => number_format(($left_to_pay / $numpay), 2),
                                "Token" => $ccuid,
                                "ExpirationDate" => $validmonth,
                                "CreditType" => $credittype,
                                "IDNumber" => $idnum
                            ];
                            
                        }else{
                            if($payment_method == 'ppcp-gateway'){
                                $data['CreditCardPayments'][] = [
                                    "PaymentSum" => $left_to_pay,
                                    "AuthorizationNumber" => $order->get_transaction_id(),
                                    "PaymentCode" => "500"
                                ];
                            }
                        }  
                    }
                        
                    //echo 'data with card number';
                    //print_r( $data['CreditCardPayments']);
                    update_post_meta($order_id, 'cancel_request', $data);

                    $form_name = 'Transactions';
                    $form_action = 'CloseTransaction';
            
                    $data = json_encode($data);
                    $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
                    $error_code = $result["ErrorCode"];
                    update_post_meta($order_id, 'response_transaction_close', $result);
                    if ($error_code === 0) {
                        $ord_status = $result['EdeaError']['ErrorMessage']; //success
                        $ord_number = $result["TransactionNumber"];
                        $order->update_meta_data('priority_pos_cart_status', $ord_status);
            
                        $order->update_meta_data('priority_pos_cart_number', $ord_number);
                        $order->save();
                    } else {
                        if($error_code == 59){
                            $this->cancel_transaction(); 
                        }
                        else{
                            $message = $result['EdeaError']['ErrorMessage'];
                            $order->update_meta_data('priority_pos_cart_status', $message);
                            $order->save();
                            $multiple_recipients = array(
                                get_bloginfo('admin_email')
    
                            );
                            $subj = 'Error sync order '.$order_id.' to priority';
                            wp_mail( $multiple_recipients, $subj, $message );
                            usleep(1000000);
                            $this->temporary_transaction_for_repost($order_id);
                        }

                    }
                    return $result;
                        
                    
                    //}
                    //order failed or canceled or not paid so cancel transaction
                }
            }
        }
        else{
            $this->temporary_transaction_for_repost($order_id);
        }
    }


    function temporary_transaction_for_repost($order_id){
        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $pos_num = $config->POSNumber;
        $ChannelCode = $config->ChannelCode;


     
        $order = wc_get_order( $order_id ); 
        $user = $order->get_user();
        if ( $user ) {
            $user_id = $user->ID;
            $cust_priority_number = get_user_meta($user_id, 'priority_customer_number', true);
            $cust_name = $user->user_firstname.' '.$user->user_lastname;
            $cust_name = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $cust_name);
            $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
            $billing_address_1 = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $billing_address_1);
            $billing_city = get_user_meta($user_id, 'billing_city', true);
            $billing_city = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $billing_city);
            $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
            $cust_phone = get_user_meta($user_id, 'billing_phone', true);
            $cust_phone = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $cust_phone);
            $cust_id_number = get_user_meta($user_id, 'account_id', true);
            $is_club = get_user_meta($user_id, 'is_club', true);
            $has_paid_club = get_user_meta( $user_id, 'club_fee_paid', true );
        }
        else{
            $cust_priority_number = '';
            $cust_name = '';
            $billing_address_1 = '';
            $billing_city = '';
            $billing_postcode = '';
            $cust_phone = '';
            $cust_id_number = '';
            $is_club = '';
            $has_paid_club = 1;
        }

        if(!empty($is_club)){
            $club_code = '01';
        }
        else{
            $club_code = '';
        }



        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if($product){
                $pdt_id = $item->get_product_id();
                $vtion_id = $item->get_variation_id();
                $quantity = $item->get_quantity();
                $price = $product->get_regular_price();
                $sku = $product->get_sku();
    
                $items_in_bag [] = [
                    'OrderItemBasicInputDetails' => [
                        "ItemCode" => $sku,
                        "Barcode" => "",
                        "ItemQuantity" => $quantity,
                        "PricePerItem" => $price,
                        "CalculatePrice" => false,
                        "IsManualPrice" => false,
                        "IsManualDiscount" => false,
                        "VATPercent" => 17,
                        "ClubCode" => ""
                    ],
                    "PointsPerType" => []
                ];
            }

        }
		
 

        $data['Transaction']['TransactionBasicDetails'] = [
            "TemporaryTransactionNumber" => '',
            "TransactionDateTime" => current_time('mysql'),
            "IsOrder" => true,
            "IsCancelTransaction" => false,
            "POSCustomerNumber" => $cust_priority_number,
            "ClubCode" => $club_code,
            "IsManualDiscount" => false,
            "SupplyBranch" => ""
        ];
        $data['Transaction']['TransactionItems'] = [];

        
        $chosen_store   = $order->get_meta('pickup_store');
        if(!empty($chosen_store)){
            $chosen_store = preg_split ("/\,/",  $chosen_store);
            $address = "חנות GANT".' '.$chosen_store[0];
            $address = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $address);
            $city = $chosen_store[1];
            $city = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $city);
        }
        else{
            $address = $order->get_shipping_address_1();
            $address = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $address);
            $city = $order->get_shipping_city();
            $city = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $city);
        }
 


        $contact_person = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        $contact_person = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $contact_person);
        $phone = get_post_meta($order->get_id(), '_shipping_phone', true);
        $phone = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $phone);
        
        $order_notes = $order->get_customer_note();
        if(!empty($order_notes)){
            $order_notes = str_replace( array( '\'',':','.', '"', ',', ';', '<', '>', '\\','/', '-','_' ), '', $order_notes);
        }
        $data['Transaction']['ShippingDetails'] = [
            "City" => $city,
            "ForeignLanguageCity" => "",
            "Address" => $address,
            "ForeignLanguageAddress" => "",
            "HouseNumber" => 0,
            "ApartmentNumber" => 0,
            "ZipCode" => "",
            "ContactPersonName" => $contact_person,
            "ForeignLanguageContactPersonName" => "",
            "Mail" => "",
            "Fax" => "",
            "SupplyDate" => "2022-04-19T06:56:24.279Z",
            "FromSupplyHour" => "2022-04-19T06:56:24.279Z",
            "ToSupplyHour" => "2022-04-19T06:56:24.279Z",
            "Remark" => (!empty($order_notes) ? $order_notes : ''),
            "ForeignLanguageRemark" => "",
            "FirstPhoneNumber" => $phone,
            "SecondPhoneNumber" => "",
            "ShipMethod" => $order->get_shipping_method(),
            "Address2" => (!empty($order->get_shipping_address_2())) ? $order->get_shipping_address_2() : '',
            "Address3" => "",
            "Email" => $order->get_billing_email(),
            "CountryCode" => "",
            "StateCode" => ""
        ];

        $data['Transaction']['OrderItems'] = $items_in_bag;

        $default_club = '777';
        $default_club = $config->CLUB_DEFAULT_PARTNAME ?? $default_club;
        $fee_amount  = get_field('club_cost','option');
        //check if club registration
        foreach( $order->get_fees() as $fee ){
            // The fee name
            $fee_name = $fee->get_name();
            if($fee_name  == "הצטרפות לחבר מועדון"){
 
                $data['Transaction']['OrderItems'][] = [
                    'OrderItemBasicInputDetails' => [
                        "ItemCode" => $default_club,
                        "Barcode" => "",
                        "ItemQuantity" => 1,
                        "PricePerItem" => $fee_amount,
                        "CalculatePrice" => false,
                        "IsManualPrice" => false,
                        "IsManualDiscount" => false,
                        "VATPercent" => 17,
                        "ClubCode" => "01"
                    ],
                    "PointsPerType" => []
                ];


            }
        }


        $default_product = '000';
        $default_product = $config->SHIPPING_DEFAULT_PARTNAME ?? $default_product;
        $shipping_method = $order->get_shipping_methods();
        $shipping_method = array_shift($shipping_method);
        if (isset($shipping_method)) {
            $shipping_data = $shipping_method->get_data();
            $method_title = $shipping_data['method_title'];
            $method_id = $shipping_data['method_id'];
            $instance_id = $shipping_data['instance_id'];
            $shipping_price = $shipping_data['total'] + $shipping_data['total_tax'];
            if( $shipping_price > 0){
                $data['Transaction']['OrderItems'][] = [
                    'OrderItemBasicInputDetails' => [
                        "ItemCode" => $default_product,
                        "Barcode" => "",
                        "ItemQuantity" => 1,
                        "PricePerItem" => $shipping_price,
                        "CalculatePrice" => false,
                        "IsManualPrice" => false,
                        "IsManualDiscount" => false,
                        "VATPercent" => 17,
                        "ClubCode" => ""
                    ],
                    "PointsPerType" => []
                ];
            }
        }

        $data['Transaction']['Remark'] = [
            "CustomerName" => $cust_name,
            "CustomerIDNumber" => $cust_id_number,
            "CustomerPhone" => $cust_phone,
            "CustomerAddress" => $billing_address_1,
            "CustomerCity" => $billing_city,
            "CustomerZipCode" => $billing_postcode
        ];

        $data['temporaryTransactionNumber'] = '';

        $data['TransactionProcessingSettings'] = [
            "CalculateSales" => true,
            "ContainExternalMetaData" => false,
            "RegisterByGeneralPosCustomer" => !empty($cust_priority_number) ? false : true,
            "RetrieveItemPictureFilename" => false,
            "CalculateTax" => 0
        ];

        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => $ChannelCode,
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        

		$form_name = 'Transactions';
		$form_action = 'OpenTransaction';


        echo "<pre style='direction: ltr;'>";
        echo 'update request';
        //$update_request = get_post_meta($order_id, 'request_transaction_update', true);
        //print_r($update_request);
        print_r(json_encode($data,JSON_PRETTY_PRINT));
        echo "</pre>";

        $data = json_encode($data);
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
        $error_code = $result["ErrorCode"];
        if ($error_code === 0) {
            //update_post_meta($order_id, 'response_transaction_update', $result);
			echo 'update response';
			echo "<pre>";
			print_r($result);
			echo "</pre>";
            $data = $result;
		
            
            $data['UniquePOSIdentifier'] = [
                "BranchNumber" => $branch_num,
                "POSNumber" => $pos_num,
                "UniqueIdentifier" => $unique_id,
                "ChannelCode" => $ChannelCode,
                "VendorCode" => "",
                "ExternalAccountID" => ""
            ];

            $form_name = 'Transactions';
            $form_action = 'ApproveTransaction';

            echo "<pre style='direction: ltr;'>";
            echo 'Approve Request:';
            //print_r($data);
            print_r(json_encode($data,JSON_PRETTY_PRINT));
            echo "</pre>";

            $data = json_encode($data);
            $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
            $error_code = $result["ErrorCode"];
            if ($error_code === 0) {
                update_post_meta($order_id, 'response_transaction_approve', $result);
                $data = $result;
                $gateway = $config->gateway ?? 'debug';
                if (!isset($config->order_statuses)) {
                    //$is_status = "processing";
                    $statuses = ["processing"];
                    $is_status = in_array($order->get_status(), $statuses);
                } else {
                    $statuses = explode(',', $config->order_statuses);
                    $is_status = in_array($order->get_status(), $statuses);
                }
                $payment_method = get_post_meta($order_id, '_payment_method', true);
                $data['UniquePOSIdentifier'] = [
                    "BranchNumber" => $branch_num,
                    "POSNumber" => $pos_num,
                    "UniqueIdentifier" => $unique_id,
                    "ChannelCode" => $ChannelCode,
                    "VendorCode" => "",
                    "ExternalAccountID" => ""
                ];
                $data["ExternalOrderNumber"] = $order->get_order_number();
                $left_to_pay =  $data['Transaction']['LeftToPay'];
                //debug
                if($payment_method == 'bacs'){
                    $data['CreditCardPayments'][] = [
                        "CardNumber" => "12345",
                        "PaymentSum" => $left_to_pay,
                        "AuthorizationNumber" => "12345",
                        "CardIssuerCode" => 1,
                        "CardClearingCode" => 1,
                        "VoucherNumber" => "12345",
                        "NumberOfPayments" => 1,
                        "FirstPaymentSum" => $left_to_pay,
                        "Token" => 12345,
                        "ExpirationDate" => "0126",
                        "CreditType" => 0,
                    ];
                }
                else{
                    if ($payment_method == 'wc-pelecard') {
                        $pelecard_transaction_data = get_post_meta( $order_id, '_transaction_data' );
                        foreach ( $pelecard_transaction_data as $order_cc_meta ) {
                            //print_r($order_cc_meta);
                            if($order_cc_meta["StatusCode"] != '000'){
                                continue;
                            }
                            $paymentcode = !empty($order_cc_meta['CreditCardCompanyClearer']) ? $order_cc_meta['CreditCardCompanyClearer'] : $paymentcode;
                            $payaccount = $order_cc_meta['CreditCardNumber'];
                            $ccuid = $order_cc_meta['Token'];
                            $validmonth = $order_cc_meta['CreditCardExpDate'];
                            $confnum = $order_cc_meta['DebitApproveNumber'];
                            $numpay = $order_cc_meta['TotalPayments'];
                            $paymentsum = $order_cc_meta['DebitTotal'] / 100;
                            $firstpay = $order_cc_meta['FirstPaymentTotal'] / 100;
                            $vouchernumber = str_replace("-", "", $order_cc_meta['VoucherId']);
                            $idnum = $order_cc_meta['CardHolderID'];
                            //$idnum = isset($order_cc_meta['CardHolderID']) ? $order_cc_meta['CardHolderID'] : '';
                            $credittype = $order_cc_meta['CreditType'];
                        }
            
                        $data['CreditCardPayments'][] = [
                            "CardNumber" => $payaccount,
                            //"PaymentSum" => $paymentsum,
                            "PaymentSum" => $left_to_pay,
                            "AuthorizationNumber" => $confnum,
                            "CardIssuerCode" => 1,
                            "CardClearingCode" => 1,
                            "VoucherNumber" => $vouchernumber,
                            "NumberOfPayments" => $numpay,
                            "FirstPaymentSum" => $firstpay,
                            "Token" => $ccuid,
                            "ExpirationDate" => $validmonth,
                            "CreditType" => $credittype,
                            "IDNumber" => $idnum
                        ];


                    }  
                    else{
                        if($payment_method == 'ppcp-gateway'){
                            $data['CreditCardPayments'][] = [
                                "PaymentSum" => $left_to_pay,
                                "AuthorizationNumber" => $order->get_transaction_id(),
                                "PaymentCode" => "500"
                            ];
                        }
                    }
                }

                $form_name = 'Transactions';
                $form_action = 'CloseTransaction';
                echo "<pre style='direction: ltr;'>";
                echo 'Close Request:';
                //print_r($data);
                print_r(json_encode($data,JSON_PRETTY_PRINT));
                echo "</pre>";
                $data = json_encode($data);
                $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
                update_post_meta($order_id, 'response_transaction_close', $result);
                $error_code = $result["ErrorCode"];
                if ($error_code === 0) {
                    $ord_status = $result['EdeaError']['ErrorMessage']; //success
                    $ord_number = $result["TransactionNumber"];
                    $order->update_meta_data('priority_pos_cart_status', $ord_status);
        
                    $order->update_meta_data('priority_pos_cart_number', $ord_number);
                    $order->save();
                }
                else{
                    $error_msg = $result['EdeaError']['DisplayErrorMessage'];
                    $message = $result['EdeaError']['DisplayErrorMessage'];
                    $order->update_meta_data('priority_pos_cart_status', $error_msg);
                    $order->save();
                }

                

            }
            else{
                $error_msg = $result['EdeaError']['ErrorMessage'];
                echo 'error_approve: '.$error_msg;
                exit;
            }
        }
        else{
            $error_msg = $result['EdeaError']['ErrorMessage'];
            echo 'error_update: '.$error_msg;
            exit;
        }
        
        return $result;
    
    }

}

