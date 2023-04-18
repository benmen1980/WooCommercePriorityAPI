<?php
class Priority_sdk_accounts extends \PriorityAPI\API{
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
		add_action('init', function() {
			add_rewrite_endpoint('priority-sdk-account', EP_ROOT | EP_PAGES);
		});
		function my_custom_flush_rewrite_rules_priority_sdk_account() {
			add_rewrite_endpoint( 'priority-sdk-account', EP_ROOT | EP_PAGES );
			flush_rewrite_rules();
		}
		register_activation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priority_sdk_account' );
		register_deactivation_hook( __FILE__, 'my_custom_flush_rewrite_rules_priority_sdk_account' );
		add_filter('woocommerce_account_menu_items', function($items) {
			$items['priority-sdk-account'] = __('Account report', 'p18w');
			return $items;
		});
		add_action('woocommerce_account_priority-sdk-account_endpoint', function() {
			?>
			<div class="woocommerce-MyAccount-content-priority-sdk-accounts">

				<p><?php _e('Accounts','p18w'); ?></p>
				<?php
			    $res = json_decode($this->create_hub2sdk_request());
                $url = $res->report_url;
              //  $url = 'https://prioritydev.simplyct.co.il/netfiles/1e6022cCC42FCFE0C734670A328FA4685048CE4.htm';
                ?>
				<a href="<?php echo $url ?>" target="_blank"><?php _e('Click on the link to open the report', 'p18w') ?></a>
			</div>

			<?php

		});
	}
    function create_hub2sdk_request(){
	    $current_user             = wp_get_current_user();
	    $priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );
	    $username = $this->option('username');
	    $password = $this->option('password');
	    $url = 'https://'.$this->option('url');
	    $tabulaini = $this->option('application');
	    $language = '1';
	    $company = $this->option('environment');
	    $devicename = 'devicename';
	    $appid = $this->option('X-App-Id');
	    $appkey = $this->option('X-App-Key');
	    $array['CUSTNAME'] = $priority_customer_number;
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
		    CURLOPT_URL            => 'prinodehub1-env.eba-gdu3xtku.us-west-2.elasticbeanstalk.com/accounts',
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
	function request_front_priority_sdk_accounts() {

		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );

		echo('here will show the url');
	}
	function my_action_exporttoexcel_delivery() {
		$current_user             = wp_get_current_user();
		$priority_customer_number = get_user_meta( $current_user->ID, 'priority_customer_number', true );

		echo('here will show the url');

		wp_die(); // this is required to terminate immediately and return a proper response
	}
}