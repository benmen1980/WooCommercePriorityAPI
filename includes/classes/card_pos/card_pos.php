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
            //'sync_items_priority_pos' => 'syncItemsPriorityPos',
            'sync_items_web_pos' => 'syncItemsWebPos',
            'sync_inventory_priority_pos' => 'syncInventoryPriorityPos',
            'sync_price_priority_pos' => 'syncPricePriorityPos',
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
        add_action('woocommerce_payment_complete', [$this, 'close_transaction'], 9999);
        add_action('woocommerce_order_status_changed', [$this, 'close_transaction'], 99999);
        add_action('wp_authenticate', [$this,'check_user_in_priority'], 9999, 2);
        add_action( 'template_redirect', [$this,'get_user_details_after_registration']);

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

                $this->updateOption('sync_items_priority_pos', $this->post('sync_items_priority_pos'));
                $this->updateOption('auto_sync_items_priority_pos', $this->post('auto_sync_items_priority_pos'));

                $this->updateOption('sync_inventory_priority_pos', $this->post('sync_inventory_priority_pos'));
                $this->updateOption('auto_sync_inventory_priority_pos', $this->post('auto_sync_inventory_priority_pos'));

                $this->updateOption('sync_price_priority_pos', $this->post('sync_price_priority_pos'));
                $this->updateOption('auto_sync_price_priority_pos', $this->post('auto_sync_price_priority_pos'));

                $this->updateOption('sync_color_details', $this->post('sync_color_details'));
                $this->updateOption('auto_sync_color_details', $this->post('auto_sync_color_details'));

                $this->updateOption('sync_items_web_pos', $this->post('sync_items_web_pos'));
                $this->updateOption('auto_sync_items_web_pos', $this->post('auto_sync_items_web_pos'));
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
    
        $url = sprintf('http://%s/PrioriPOSTestAPI/api/%s/%s',
            $ip,
            is_null($form_name) ? '' : stripslashes($form_name),
            is_null($form_action) ? '' : stripslashes($form_action),
        );
    
        $response = wp_remote_request($url, $args);
    
        $body_array = json_decode($response["body"], true);
        // echo '<pre>';
        // print_r($body_array);
        // echo '</pre>';
    
    
        return $body_array;
    
    
    
    }

    function check_user_by_mobile_phone($mobile_phone){
        $data = [
            "MobilePhone" => $mobile_phone, 
        ];
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => "77",
            "POSNumber" => "1",
            "UniqueIdentifier" => "PRODGANT77",
            "ChannelCode" => "",
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'PosCustomers';

        $form_action = 'GetPOSCustomer';
    
        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
    
        return $body_array;
    
        
    }
    
    function check_user_by_phone($phone){
        $data = [
            "PhoneNumber" => $phone, 
        ];
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => "77",
            "POSNumber" => "1",
            "UniqueIdentifier" => "PRODGANT77",
            "ChannelCode" => "",
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'PosCustomers';

        $form_action = 'GetPOSCustomer';
    
        $body_array = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
    
        return $body_array;
    }
    
    function check_user_by_id_num($id_num){
        $data = [
            "IDNumber" => $id_num, 
        ];
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => "77",
            "POSNumber" => "1",
            "UniqueIdentifier" => "PRODGANT77",
            "ChannelCode" => "",
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
                if ($error_code == 0) {
                    $PosCustomersResult = $body_array["POSCustomersMembershipDetails"][0];
                    //if exist in priority, create user
                    if(!empty($PosCustomersResult)){
                        $priority_customer_number = $PosCustomersResult["POSCustomerBasicDetails"]["POSCustomerNumber"];
                        $username = $PosCustomersResult["POSCustomerBasicDetails"]["MobileNumber"];
                        $email = $PosCustomersResult["POSCustomerBasicDetails"]["Email"];
                        $fname = $PosCustomersResult["POSCustomerBasicDetails"]["FirstName"];
                        $lname = $PosCustomersResult["POSCustomerBasicDetails"]["LastName"];
                        $fullname = $PosCustomersResult["POSCustomerBasicDetails"]["FullName"];
                        $displayname = $PosCustomersResult["POSCustomerBasicDetails"]["FirstName"];
                        $user_city = $PosCustomersResult["POSCustomerBasicDetails"]["City"];
                        $user_address_1 = $PosCustomersResult["POSCustomerBasicDetails"]["Address"];
                        $user_address_2 = $PosCustomersResult["POSCustomerBasicDetails"]["Address2"];
                        $user_city = $PosCustomersResult["POSCustomerBasicDetails"]["City"];
                        $user_zipcode = $PosCustomersResult["POSCustomerBasicDetails"]["ZipCode"];
                        $user_birthId = $PosCustomersResult["POSCustomerBasicDetails"]["BirthID"];
            
                        //check if user exist by user login or email
                        $user_obj = get_user_by('login', $username);
                        if(empty($user_obj)){
                            $user_obj = get_user_by('email',$email);
                        }
            
                        $user_id = wp_insert_user(array(
                            'ID' => isset($user_obj->ID) ? $user_obj->ID : null,
                            'user_login'  =>  $username,
                            'user_email'  =>  (!empty($email)) ? $email : $username.'@gmail.com',
                            'first_name'  =>  $fname,
                            'last_name'  =>  $lname,
                            'role' => 'customer',
                            'user_nicename' => $fullname,
                            'display_name'  => $fullname,
                        ));
                        if (is_wp_error($user_id)) {
                            $multiple_recipients = array(
                                get_bloginfo('admin_email')
                            );
                            $subj = 'Error creating user from priority';
                            $body = $user_id->get_error_message().'</br>';
                            $body.= 'username:'.$username.', first name:'.$fname.',last_name: '.$lname;
                            wp_mail( $multiple_recipients, $subj, $body );
                        }
                        
                        update_user_meta($user_id, 'priority_customer_number', $priority_customer_number);
                        update_user_meta($user_id, 'billing_address_1', $user_address_1);
                        update_user_meta($user_id, 'billing_address_2', $user_address_2);
                        update_user_meta($user_id, 'billing_city', $user_city);
                        update_user_meta($user_id, 'billing_phone', $username);
                        update_user_meta($user_id, 'billing_postcode', $user_zipcode);
                        update_user_meta($user_id, 'account_id', $user_birthId);

                        //update user club
                        if(!empty($PosCustomersResult["ClubsMemberships"])){
                            if($PosCustomersResult["ClubsMemberships"]["ClubCode"] == "01")
                                update_user_meta($user_id, 'is_club', 1);
                        }
                    }
                }
                else {
                        $message = $body_array['EdeaError']['DisplayErrorMessage'];
                        $multiple_recipients = array(
                            get_bloginfo('admin_email')
                        );
                        $subj = 'Error check user exist with mobile phone in priority';
                        wp_mail( $multiple_recipients, $subj, $message );
                }
            }
        }
    }

    function get_user_details_after_registration() {
        $prev_url = $_SERVER['HTTP_REFERER'];
        if ( (is_page_template( 'page-templates/overview.php' ) && ($_SERVER['HTTP_REFERER'] == get_site_url().'/register/')) || ( is_front_page() && strpos($prev_url, 'branch') == true )){
            if ( is_user_logged_in() ) {
                $user_id = get_current_user_id();
                $meta = get_user_meta($user_id);
                $account_id = get_user_meta($user_id, 'account_id', true);
                $user_login = $meta['nickname'][0];
                $fname = $meta['first_name'][0];
                $lname = $meta['last_name'][0];
                $phone = $meta['nickname'][0];
                $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
                $billing_address_2 = get_user_meta($user_id, 'billing_address_2', true);
                $billing_city = get_user_meta($user_id, 'billing_city', true);
                $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
                $billing_country = get_user_meta($user_id, 'billing_country', true);
                $billing_email = get_user_meta($user_id, 'billing_email', true);
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
                if ($error_code == 0) {
                    $PosCustomersResult = $result["POSCustomersMembershipDetails"][0];
                    //if not exist in priority, create user
                    if(empty($PosCustomersResult)){
                        $result =  $this->check_user_by_phone($user_login);
                        $error_code = $result["ErrorCode"];
                        if ($error_code == 0) {
                            $PosCustomersResult = $result["POSCustomersMembershipDetails"][0];
                            if(empty($PosCustomersResult)){
                                $result =  $this->check_user_by_id_num($account_id);
                                $error_code = $result["ErrorCode"];
                                if ($error_code == 0) {
                                    $PosCustomersResult = $result["POSCustomersMembershipDetails"][0];
                                    if(empty($PosCustomersResult)){
                                        //echo 'current user:'.get_current_user_id();
                                        // sync cust to priorirty
                                        $site_priority_customer_number = 'WEB-'.get_current_user_id();
                                        $data = [
                                            "CreateCustomer" => true,
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
                                            "BirthDate" => "2022-05-26T21:02:27.413Z",
                                            "Gender" => "ז",
                                            "Email" => !empty($billing_email) ? $billing_email : '',
                                            "IsActive" => true,
                                            "AllowSMS" => true,
                                            "AllowEmail" => $allow_email,
                                            "AllowMail" => true,
                                            "DefaultClubCode" => "01",
                                            "Address2" => "string",
                                            "Address3" => "string",
                                            "WantsToJoinMobileClub" => true,
                                            "Entrance" => "string"
                                        ];
                                        $data['UniquePOSIdentifier'] = [
                                            "BranchNumber" => "77",
                                            "POSNumber" => "1",
                                            "UniqueIdentifier" => "PRODGANT77",
                                            "ChannelCode" => "",
                                            "VendorCode" => "",
                                            "ExternalAccountID" => ""
                                        ];

                                        $form_name =  'PosCustomers';

                                        $form_action = 'CreateOrUpdatePOSCustomer';
    
                                        $body_array = makeRequestCardPos('POST', $form_name , $form_action, ['body' => json_encode($data)], true);
                                        $error_code = $body_array["ErrorCode"];
                                        if($error_code == 0){
                                            update_user_meta($user_id, 'priority_customer_number', $site_priority_customer_number, true);
                                        }
                                        else{
                                            $message = $body_array['EdeaError']['DisplayErrorMessage'];
                                            $multiple_recipients = array(
                                                get_bloginfo('admin_email')
                                            );
                                            $subj = 'Error sync user in priority';
                                            wp_mail( $multiple_recipients, $subj, $message );
                                        }
                                        //print_r($body_array);
    
                                        
                                    }
                                    else{
                                        //update priority number to user
                                        $priority_customer_number = $PosCustomersResult["POSCustomerBasicDetails"]["POSCustomerNumber"];
                                        update_user_meta($user_id, 'priority_customer_number', $priority_customer_number);
                                        //$is_club = $PosCustomersResult["ChannelName"];
                                        $is_club = $PosCustomersResult["ClubsMemberships"];
                                        if(!empty($is_club)){
                                            update_user_meta($user_id, 'is_club', 1);
                                        }
                                    }
                                }
                                else{
                                    $message = $result['EdeaError']['DisplayErrorMessage'];
                                    $multiple_recipients = array(
                                        get_bloginfo('admin_email')
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
                                if(!empty($is_club)){
                                    update_user_meta($user_id, 'is_club', 1);
                                }
                            }
                        }
                        else{
                            $message = $result['EdeaError']['DisplayErrorMessage'];
                            $multiple_recipients = array(
                                get_bloginfo('admin_email')
                            );
                            $subj = 'Error check user exist with phone in priority';
                            wp_mail( $multiple_recipients, $subj, $message );
                        }
                        
                    }
                    else{
                        //update priority number to user
                        $priority_customer_number = $PosCustomersResult["POSCustomerNumber"];
                        update_user_meta($user_id, 'priority_customer_number', $priority_customer_number);
                        //$is_club = $PosCustomersResult["ChannelName"];
                        $is_club = $PosCustomersResult["ClubsMemberships"];
                        // check if club and update user meta
                        if(!empty($is_club)){
                            update_user_meta($user_id, 'is_club', 1);
                        }
                    }
                }
                else{
                    $message = $result['EdeaError']['DisplayErrorMessage'];
                    $multiple_recipients = array(
                        get_bloginfo('admin_email')
                    );
                    $subj = 'Error check user exist with mobile phone in priority';
                    wp_mail( $multiple_recipients, $subj, $message );
                }
    
            }
        }
    }


    function syncItemsPriorityPos(){
        $item_option = $this->option('sync_items_priority_pos_config');
        $item_option = str_replace(array("\n", "\t", "\r"), '', $item_option);
        $item_config = json_decode(stripslashes($item_option));
        $daysback = (!empty((int)$item_config->days_back) ? $item_config->days_back : 1);

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
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => "",
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'Items';
    
        $form_action = 'GetUpdatedItemDetails';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        if ($error_code == 0) {
            $response_data = $result['ItemDetails'];
            if(!empty($response_data)){
                foreach ($response_data as $item) {
    
                    $sku = $item['ItemCode'];  //220152xl
                    $barcode = $item['Barcode']; 
                    $style = $item['ModelCode'];  //2201
                    $title = $item['Description'];
                    // $color = $item[27];  //param 2:black
                    $size = $item['Size'];
                    // $price = $item[23];
                    $style_color = $style . '-' . $item['ColorCode']; //2201-5
        
                    if (empty($sku) || $sku == '') {
                        continue;
                    }
                    if ($style == $sku) {
                        continue;
                    }
                    // push products here...
                    $attributes['size'] = $size;
                    //$price = $price;
                    $parents[$style_color] = [
                        'sku' => '',
                        'title' => $title,
                        'stock' => 'Y',
                        'variation' => [],
                        //'regular_price' => $price,
                        //'parent_category' => $data[15], //גברים
                        // 'categories' => [  //חולצת פולו
                        //     $data[19]
                        // ],
                        // 'categories-slug' => [   חולצת-פולו-גברים
                        //     $data[19] .'-'.$data[15]
                        // ]
                    ];
        
                    $childrens[$style_color][$sku] = [
                        'sku' => $sku,  //220152xl
                        'regular_price' => $price,
                        'stock' => 'Y',
                        'parent_title' => $title,
                        'title' => $title,
                        'stock' => 'outofstock',
                        'attributes' => $attributes,
                        'barcode' => $barcode,
                        'model' => $style, //2001
                        //'color' => $color,
                        //'grouped_color' => $data[6],
                        'color_code' => $item['ColorCode'],
                        // 'measure_bar_code' => $data[8],
                        // 'brand_desc' => $data[11],
                        // 'year' => $data[12],
                        // 'season' => $data[13],
                        // 'concept' => $data[16],
                        // 'cut' => $data[17],
                        // 'sub_cat' => $data[19],
                        // 'cat' => $data[15],
                        // 'fabric' => $data[20],
                        // 'fabric_desc' => $data[21],
                        // 'made_in' => $data[22],
                        // 'sleeve_type' => $data[24]
                    ];
                }
            }
            
    
            // foreach ($parents as $partname => $value) {
            //     if (count($childrens[$partname])) {
            //         $parents[$partname]['variation'] = $childrens[$partname];
            //         $parents[$partname]['title'] = $parents[$partname]['title'];
            //         foreach ($childrens[$partname] as $children) {
            //             foreach ($children['attributes'] as $attribute => $attribute_value) {
            //                 if ($attributes) {
            //                     if (!empty($parents[$partname]['attributes'][$attribute])) {
            //                         if (!in_array($attribute_value, $parents[$partname]['attributes'][$attribute]))
            //                             $parents[$partname]['attributes'][$attribute][] = $attribute_value;
            //                     } else {
            //                         $parents[$partname]['attributes'][$attribute][] = $attribute_value;
            //                     }
            //                 }
            //             }
            //         }
            //         //  $product_cross_sells[$value['cross_sells']][] = $partname;
            //     } else {
            //         unset($parents[$partname]);
            //     }
            // }

            // add timestamp
            $this->updateOption('inventory_pritems_priority_pos_updateiority_update_pos', time());
            
        }
        else {
            $message = $$result['EdeaError']['DisplayErrorMessage'];
            $multiple_recipients = array(
                get_bloginfo('admin_email')
            );
            $subj = 'Error get item from priority';
            wp_mail( $multiple_recipients, $subj, $message );
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
        $pos_num = $config->POSNumber;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => "",
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'OnlineItemStock';
    
        $form_action = 'GetUpdatedItemStock';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        if ($error_code == 0) {
            $response_data = $result['ItemStock'];
            foreach($response_data as $item){
                $sku = $item['ItemCode'];
                $stock = $item['ActiveItemQuantityInWebWarehouses'];
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
                    
                    update_post_meta($product_id, '_stock', $stock);
                    // set stock status
                    if (intval($stock) > 0) {
                        update_post_meta($product_id, '_stock_status', 'instock');
                        $stock_status = 'instock';
                    } else {
                        update_post_meta($product_id, '_stock_status', 'outofstock');
                        $stock_status = 'outofstock';
                    }
                }
                $product = wc_get_product($product_id);
                if ($product->post_type == 'product_variation') {
                    $var = new \WC_Product_Variation($product_id);
                    $var->set_manage_stock(true);
                    $var->save();
                }
                if ($product->post_type == 'product') {
                    $product->set_manage_stock(true);
                    $product->save();
                }
            }

            // add timestamp
            $this->updateOption('inventory_priority_update_pos', time());
    
        }
        else{
            $message = $result['EdeaError']['DisplayErrorMessage'];
            $multiple_recipients = array(
                get_bloginfo('admin_email')
            );
            $subj = 'Error get item from priority';
            wp_mail( $multiple_recipients, $subj, $message );
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
        $pos_num = $config->POSNumber;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => "",
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        $form_name = 'ItemPrice';
    
        $form_action = 'GetUpdatedItemPrice';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        if ($error_code == 0) {
            $response_data = $result['ItemPrice'];
            foreach($response_data as $item){
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
            $this->updateOption('price_priority_update_pos', time());
        }
        else{
            $message = $result['EdeaError']['DisplayErrorMessage'];
            $multiple_recipients = array(
                get_bloginfo('admin_email')
            );
            $subj = 'Error get item from priority';
            wp_mail( $multiple_recipients, $subj, $message );
        }
        
    }

    function syncItemsWebPos(){

        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $pos_num = $config->POSNumber;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => "",
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'Items';
    
        $form_action = 'GetItemsForWebCodes';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        if ($error_code == 0) {

            $web_sku = $result['ItemCodes'];
            $product_web_ids = array();
            foreach($web_sku as $sku){
                $variation_id = wc_get_product_id_by_sku($sku);
                if($variation_id != '')
                    $product_id = wp_get_post_parent_id($variation_id);
                    $product_web_ids[] = $product_id;
            }
            //remove duplicate from array because all variation have same product id
            $product_web_ids = array_unique($product_web_ids);
            
            //get product id from site and check if product id in array of web product id
            //if not set the post draft

            $all_ids = get_posts( array(
                array('product', 'product_variation'),
                'numberposts' => -1,
                'post_status' => 'publish',
                'fields' => 'ids',
            ) );
            foreach ( $all_ids as $id ) {
                if(!in_array($id,$product_web_ids)){
                    wp_update_post( array(
                        'ID' => $id,
                        'post_status' => 'draft',
                    ) );
                }
            }

            // add timestamp
            $this->updateOption('items_web_update_pos', time());
           
      
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
        $pos_num = $config->POSNumber;
    
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => "",
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];
    
        $form_name = 'ItemColors';
    
        $form_action = 'GetColors';
    
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => json_encode($data)], true); 
        $error_code = $result["ErrorCode"];
        if ($error_code == 0) {

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
                get_bloginfo('admin_email')
            );
            $subj = 'Error get color details from priority';
            wp_mail( $multiple_recipients, $subj, $message );
        }

    }

    
 

    public function openOrUpdateTransaction($product_id,$quantity, $variation_id){
        $items_in_bag = [];
        if ( WC()->cart->get_cart_contents_count() == 0 ) {
            $temporarytransactionnumber = '';
        }
        else{
            $i = 0;
            foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
                if($i == 0){
                    $temporarytransactionnumber = $cart_item['temporary_transaction_num'];
                    $i ++;
                }
                $pdt = $cart_item['data'];
                $pdt_id = $cart_item['product_id'];
                $vtion_id = $cart_item['variation_id'];
                $quantity = $cart_item['quantity'];
                //$price = WC()->cart->get_product_price( $product );
                $price = get_post_meta($cart_item['product_id'] , '_price', true);
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
        $pos_num = $config->POSNumber;

        if (is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $user_id = $current_user->ID;
            $cust_priority_number = get_user_meta($user_id, 'priority_customer_number', true);
            $cust_name = $current_user->user_firstname.' '.$current_user->user_lastname;
            $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
            $billing_city = get_user_meta($user_id, 'billing_city', true);
            $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
            $cust_phone = get_user_meta($user_id, 'billing_phone', true);
            $cust_id_number = get_user_meta($user_id, 'account_id', true);
            $is_club = get_user_meta($user_id, 'is_club', true);
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
        }

        $data['Transaction']['TransactionBasicDetails'] = [
            "TemporaryTransactionNumber" => $temporarytransactionnumber,
            "TransactionDateTime" => "2022-11-06T10:12:54.619Z",
            "IsOrder" => true,
            "IsCancelTransaction" => false,
            "POSCustomerNumber" => $cust_priority_number,
            "ClubCode" => "",
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
            "RegisterByGeneralPosCustomer" => is_user_logged_in() ? false : true,
            "RetrieveItemPictureFilename" => false,
            "CalculateTax" => 0
        ];

        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => "",
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
        $pos_num = $config->POSNumber;

        $pos = strpos($coupon_code, '-');
        if($pos !== false){
            $pos = explode("-", $pos );
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
            "ChannelCode" => "",
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
        $pos_num = $config->POSNumber;

        $data['Coupons']['CouponsToRemove'][] = [
            "CouponCode" => $coupon_code,
            "UniqueNumber" => "string",
            "NumberOfScans" => 1,
            "ExternalCoupon" => true
        ];
        $data['temporaryTransactionNumber'] = $temporarytransactionnumber;
        
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => "",
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
        if ($_POST['ship_to_different_address'] == 1 && !empty($order->get_shipping_address_1())) {
            $address = $order->get_shipping_address_1();
        } else {
            $address = $order->get_billing_address_1();
        }


        if ($_POST['ship_to_different_address'] == 1 &&  !empty($order->get_shipping_city())) {
            $city = $order->get_shipping_city();
        } else {
            $city = $order->get_billing_city();
        }

        if ($_POST['ship_to_different_address'] == 1 &&  !empty($order->get_shipping_address_1())) {
            $contact_person = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
        } else {
            $contact_person = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        }

        if ($_POST['ship_to_different_address'] == 1 && !empty(get_post_meta($order->get_id(), '_shipping_phone', true))) {
            $phone = get_post_meta($order->get_id(), '_shipping_phone', true);
        } else {
            $phone = $order->get_billing_phone();
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
            "Remark" => "",
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
        else{

        }

        $default_club = '000';
        $default_club = $config->CLUB_DEFAULT_PARTNAME ?? $default_club;

        //check if club registration
        foreach( $order->get_items('fee') as $item_id => $item_fee ){
            //if fee is negative , it's coupon
            if($item_fee->total < 0){

            }

            // The fee name
            $fee_name = $item_fee->get_name();
            if($fee_name  == "הצטרפות לחבר מועדון"){
                // The fee total amount
                $fee_total = $item_fee->get_total();

                $data['Transaction']['OrderItems'][] = [
                    'OrderItemBasicInputDetails' => [
                        "ItemCode" => $default_club,
                        "Barcode" => "",
                        "ItemQuantity" => 1,
                        "PricePerItem" => $fee_total,
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


        $data = json_encode($data);
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
        // update transaction array in cart item to approve result to send it to close transaction after payment
        $error_code = $result["ErrorCode"];
        if ($error_code == 0) {
            $result_order_items = $result["Transaction"]["OrderItems"];
            $first = 0;
            update_post_meta($order_id, 'response_transaction_update', $result);
        }
        else{
            $error_src = $result['EdeaError']['ErrorSource'];
            // the order is locked so cancel
            $error_msg = $result['EdeaError']['ErrorMessage'];
            wc_add_notice( 'error updating bag: '.$error_msg, 'error' );
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
        foreach ( WC()->cart->get_cart_contents() as $key =>$cart_item ) {
            //iterate only once, to get transaction array
            if($i!=0)  break;
            $raw_option = WooAPI::instance()->option('setting-config');
            $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
            $config = json_decode(stripslashes($raw_option));
            $branch_num = $config->BranchNumber;
            $unique_id = $config->UniqueIdentifier;
            $pos_num = $config->POSNumber;
            //retrieve approve result from cart to send it to cancel to allow adding product to bag after that te btransaction was locked
            $data = $cart_item['lastapprove_transaction'];
            $data['UniquePOSIdentifier'] = [
                "BranchNumber" => $branch_num,
                "POSNumber" => $pos_num,
                "UniqueIdentifier" => $unique_id,
                "ChannelCode" => "",
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

    function approve_transaction($order_id){
        $this->update_transaction_before_payment($order_id);
        //$order_id = $order->get_id();
        $raw_option = WooAPI::instance()->option('setting-config');
        $raw_option = str_replace(array("\n", "\t", "\r"), '', $raw_option);
        $config = json_decode(stripslashes($raw_option));
        $branch_num = $config->BranchNumber;
        $unique_id = $config->UniqueIdentifier;
        $pos_num = $config->POSNumber;

        //$data = $this->update_transaction_before_payment($order_id);
        $data = get_post_meta($order_id, 'response_transaction_update', true);
        $data['UniquePOSIdentifier'] = [
            "BranchNumber" => $branch_num,
            "POSNumber" => $pos_num,
            "UniqueIdentifier" => $unique_id,
            "ChannelCode" => "",
            "VendorCode" => "",
            "ExternalAccountID" => ""
        ];

        $form_name = 'Transactions';
        $form_action = 'ApproveTransaction';


        $data = json_encode($data);
        $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
        // update transaction array in cart item to approve result to send it to close transaction after payment
        $error_code = $result["ErrorCode"];
        if ($error_code == 0) {
            update_post_meta($order_id, 'response_transaction_approve', $result);
            $i = 0;
            foreach ( WC()->cart->get_cart_contents() as $key => $cart_item ) {
                //iterate only once, to get transaction array
                if($i!=0)  break;
                //save also approve result to cart
                $cart_item['lastapprove_transaction'] =  $result; 
                //$cart_item['cart_locked'] =  'true';    
                WC()->cart->cart_contents[$key] = $cart_item;
                $i++;
            }
            WC()->cart->set_session();
        }
        else{
            $error_msg = $result_cancel['EdeaError']['ErrorMessage'];
            wc_add_notice( 'error in approve transaction: '.$error_msg, 'error' );
            exit;
        }

        return $resut;

     
    }

    public function close_transaction($order_id){

        $data = get_post_meta($order_id, 'response_transaction_approve', true);

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
        $gateway = $config->gateway ?? 'debug';
        if (!isset($config->order_statuses)) {
            //$is_status = "processing";
            $statuses = ["processing"];
            $is_status = in_array($order->get_status(), $statuses);
        } else {
            $statuses = explode(',', $config->order_statuses);
            $is_status = in_array($order->get_status(), $statuses);
        }
        if (empty(get_post_meta($order_id, '_post_done', true))) {
            if($is_status || $gateway == 'debug' ){
                //update_post_meta($order_id, '_post_done', true);
                $data['UniquePOSIdentifier'] = [
                    "BranchNumber" => $branch_num,
                    "POSNumber" => $pos_num,
                    "UniqueIdentifier" => $unique_id,
                    "ChannelCode" => "",
                    "VendorCode" => "",
                    "ExternalAccountID" => ""
                ];

                $data["ExternalOrderNumber"] = $order->get_order_number();
                $left_to_pay =  $data['Transaction']['LeftToPay'];
                $payment_method = get_post_meta($order->get_id(), '_payment_method', true);
                $gateway = $config->gateway ?? 'debug';
                if ($gateway == 'pelecard') {
                    $order_cc_meta = $order->get_meta('_transaction_data');
                    $paymentcode = !empty($order_cc_meta['CreditCardCompanyClearer']) ? $order_cc_meta['CreditCardCompanyClearer'] : $paymentcode;
        
                    $payaccount = $order_cc_meta['CreditCardNumber'];
                    $ccuid = $order_cc_meta['Token'];
                    $validmonth = $order_cc_meta['CreditCardExpDate'];
                    $confnum = $order_cc_meta['ConfirmationKey'];
                    $numpay = $order_cc_meta['TotalPayments'];
                    $firstpay = $order_cc_meta['FirstPaymentTotal'] / 100;
                    $vouchernumber = str_replace("-", "", $order_cc_meta['VoucherId']);
                    $idnum = $order_cc_meta['CardHolderID'];
        
                    $data['CreditCardPayments'][] = [
                        "CardNumber" => $payaccount,
                        "PaymentSum" => floatval($order->get_total()),
                        "AuthorizationNumber" => $confnum,
                        "CardIssuerCode" => 1,
                        "CardClearingCode" => 1,
                        "VoucherNumber" => $vouchernumber,
                        "NumberOfPayments" => $numpay,
                        "FirstPaymentSum" => $firstpay,
                        "Token" => $ccuid,
                        "ExpirationDate" => $validmonth,
                        "CreditType" => 0,
                        "IDNumber" => $idnum
                    ];
                } else {
                    //debug
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
                update_post_meta($order_id, 'cancel_request', $data);

                $form_name = 'Transactions';
                $form_action = 'CloseTransaction';
        
                $data = json_encode($data);
                $result = $this->makeRequestCardPos('POST', $form_name , $form_action,  ['body' => $data], true);
                $error_code = $result["ErrorCode"];
                if ($error_code == 0) {
                    $ord_status = $result['EdeaError']['ErrorMessage']; //success
                    $ord_number = $result["TransactionNumber"];
                    $order->update_meta_data('priority_pos_cart_status', $ord_status);
        
                    $order->update_meta_data('priority_pos_cart_number', $ord_number);
                    $order->save();
                } else {
                    $message = $result['EdeaError']['DisplayErrorMessage'];
                    $order->update_meta_data('priority_pos_cart_status', $message);
                    $order->save();
                }
                return $result;
                
                
  
            }
            //order failed or canceled or not paid so cancel transaction
            else{
                $this->cancel_transaction();
            }
            
        }
    }


    function add_custom_data_to_order_item_meta($item_id, $values ) {

        global $woocommerce,$wpdb;

        if(!empty($values['lastupdate_transaction'])){
            $transaction_data = $values['lastupdate_transaction'];
            wc_add_order_item_meta($item_id, 'transaction_data', serialize($transaction_data));
        }

	}



    function add_transaction_num_to_woocommerce_session($cart_item_data, $product_id){
        global $woocommerce;
        session_start();
    
        if(empty($_SESSION['temporary_transaction_num']))
            return $cart_item_data;
        else { 
            $options = array('temporary_transaction_num' => $_SESSION['temporary_transaction_num']);
    
            //Unset our custom session variable
            unset($_SESSION['temporary_transaction_num']);
    
            if(empty($cart_item_data))
                return $options;
            else
                return array_merge($cart_item_data, $options);
        }
        

    }
    //Add the custom data to WooCommerce cart object
    function get_user_transaction_num_session($item, $values, $key ){

        //Check if the key exist and add it to item variable.
        if (array_key_exists( 'temporary_transaction_num', $values ) )
        {
            $item['temporary_transaction_num'] = $values['temporary_transaction_num'];
        }
        return $item;
    }




}
