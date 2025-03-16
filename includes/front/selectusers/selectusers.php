<?php
if ( ! defined( 'SIMPLY_CHANGE_USER_TRANSIENT_PREFIX' ) ) {
    define( 'SIMPLY_CHANGE_USER_TRANSIENT_PREFIX', 'cFDt4d' );
}
add_shortcode('select_users','simply_populate_users');
//this is for populate users in front
function simply_populate_users($atts)
{
    if(!is_user_logged_in()){
        return null;
    }
    $add_agent_to_drop_down = $atts['add_agent_to_drop_down'] == "true" ;
    $current_user_id = get_current_user_id();


    $transient_valid = false;
    $transient_name = SimplyCookieHandler::get( SIMPLY_CHANGE_USER_TRANSIENT_PREFIX );
    if( ! empty( $transient_name ) && ( $transient_data = get_transient( $transient_name ) ) ) {
        $transient_valid = true;
    }

    if( ! $transient_valid && empty(get_user_meta( $current_user_id, 'select_users', true))){
        return null;
    }
    if(!empty(get_user_meta( $current_user_id, 'select_users', true))) {
        $selected_users = get_user_meta( $current_user_id, 'select_users', true);
        if(true == $add_agent_to_drop_down){
            $selected_users[] = $current_user_id;
        };
    }
    else{
        $selected_users = $transient_data[ 'related_users' ] ?? null;
        if( ! is_array( $selected_users ) ) {
            $selected_users  = [];
        }
        if(true == $add_agent_to_drop_down){
            $agent_id = $transient_data[ 'agent_id' ] ?? null;
            if( !empty( $agent_id ) ) {
                $selected_users[] = $agent_id;
            }
        }
    }
    if(empty($selected_users)){
        //return __('No Sites','p18a');
        return __('','p18a');
    }
    $select_options = '<select name="users" class="simply-agents-list">';
    $select_options .= ' <option disabled selected value>'.__('-- select an option --','p18w').'</option>';
    
    foreach ($selected_users as $userid) {
        $first_name = get_user_meta( $userid, 'first_name', true );
        $last_name = get_user_meta( $userid, 'last_name', true );

        $selected = '';
        if($userid ==  $current_user_id){
            $selected = ' selected';
        }
        $select_options .= '<option value="' . $userid . '" '. $selected .' >' . $first_name.' '.$last_name . '</option>';
    }
    $select_options .= '</select>';
    return $select_options;
}
add_action( 'wp_footer', 'simply_ajax_change_user' );
/* handle session on frontend */
function simply_ajax_change_user() { 
    if( ! is_user_logged_in() ) {
        // Don't output the change users JS for non logged-in users
        return;
    }
    ?>
    <script type="text/javascript" >
        jQuery(".simply-agents-list").change(function($) {
            ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ) ?>'; // get ajaxurl
            var data = {
                'action': 'change_user', // your action name
                'userid': this.value,
                'nonce': '<?php echo wp_create_nonce( 'simply-change-user' ); ?>',
            }
            jQuery.ajax({
                url: ajaxurl, // this will point to admin-ajax.php
                type: 'POST',
                data: data,
                success: function (response) {
                    console.log(response);
                    window.location.reload();
                }
            });
        });
    </script>
    <?php
}

add_action("wp_ajax_change_user" , "change_user"); // Only logged-in users should be able to change users (NEVER add "wp_ajax_nopriv_change_user" action!)
function change_user(){
    if( ! is_user_logged_in() ) {
        // Just in case - Only logged-in users should be able to change users
        wp_die();
    }
    if( empty( $_POST['userid'] ) || ! is_numeric( $_POST['userid'] ) || 
        empty($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], 'simply-change-user' ) 
    ) {
        // Basic validation - Check POST fields and verify nonce
        wp_die();
    }

    if( ! simply_is_valid_user_change( get_current_user_id() ) ) {
        // Make sure that the current logged-in user ID exists in the transient (agent_id or one of the related_users).
        // Otherwise - It might be an attempt to hack
        wp_die();
    }

    $user_id = intval( $_POST[ 'userid' ] );
    if( ! simply_is_valid_user_change( $user_id ) ) {
        // Make sure the requested 'userid' change is valid (It should either match the agent_id or one of the related_users)
        wp_die();
    }

    $user = get_user_by('id', $user_id);
    if( is_a( $user, 'WP_User' ) ) {
        wp_cache_set( 'dont_delete_change_user_cookie', true );
        wp_logout();
        wp_cache_set( 'dont_delete_change_user_cookie', false );
        global $woocommerce;
        $woocommerce->cart->empty_cart();

        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
        do_action( 'wp_login', $user->user_login,$user );
    }
    wp_die();
}

add_action( 'wp_logout', function( $user_id ) {
    if ( true === wp_cache_get( 'dont_delete_change_user_cookie' ) ) {
        // We're currently changing users - Ignore this logout action
        return;
    }
    // Clear transient and cookie data on logout
    $transient_name = SIMPLY_CHANGE_USER_TRANSIENT_PREFIX . $user_id;
    delete_transient( $transient_name );
    SimplyCookieHandler::delete( SIMPLY_CHANGE_USER_TRANSIENT_PREFIX );
});

add_action('init', function() {
    $current_user_id = get_current_user_id();
    if( empty( $current_user_id ) ) {
        return;
    }

    if(!empty(get_user_meta( $current_user_id, 'select_users', true))) {
        $selected_users = get_user_meta( $current_user_id, 'select_users', true);
        $transient_data = [
            'agent_id'      => $current_user_id,
            'related_users' => $selected_users,
        ];

        // Create a transient name, based on the current logged-in user ID
        $transient_name = SIMPLY_CHANGE_USER_TRANSIENT_PREFIX . $current_user_id;
        // Save the actual transient details in the DB 
        set_transient( $transient_name, $transient_data, DAY_IN_SECONDS );
        // Store ONLY the transient name in a cookie for later use (Users can manipulate the data, we must not trust them)
        SimplyCookieHandler::set( SIMPLY_CHANGE_USER_TRANSIENT_PREFIX, $transient_name );
    }
});


function simply_is_valid_user_change( int $user_id ) {
    $transient_name = SimplyCookieHandler::get( SIMPLY_CHANGE_USER_TRANSIENT_PREFIX );
    if( empty( $transient_name ) ) {
        // INVALID - Nothing found in the cookie
        return false;
    }

    if ( false === ( $transient_data = get_transient( $transient_name ) ) ) {
        // INVALID - No valid transient was found
        return false;
    }

    if( ! empty( $transient_data[ 'agent_id' ] ) && intval( $transient_data[ 'agent_id' ] ) === $user_id ) {
        // VALID - Agent ID is equal to user_id 
        return true;
    }

    if( empty( $transient_data[ 'related_users' ] ) || ! is_array( $transient_data[ 'related_users' ] ) ) {
        // INVALID - related_users were not defined or not an array
        return false;
    }

    foreach( $transient_data[ 'related_users' ] as $related_user ) {
        if( is_numeric( $related_user ) && intval( $related_user ) === $user_id ) {
            // VALID - user_id match one of the related users
            return true;
        }
    }

    // INVALID - return false
    return false;
}

class SimplyCookieHandler {
    /**
     * Set a cookie.
     *
     * @param string $name The name of the cookie.
     * @param string $value The value of the cookie.
     * @param int $expiry Expiry time in seconds (default: 1 day).
     * @return bool True if the cookie was set successfully, false otherwise.
     */
    public static function set($name, $value, $expiry = DAY_IN_SECONDS) {
        $expireTime = time() + $expiry;
        return setcookie($name, $value, $expireTime, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    }

    /**
     * Get a cookie value.
     *
     * @param string $name The name of the cookie.
     * @return string|null The cookie value or null if it doesn't exist.
     */
    public static function get($name) {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
    }

    /**
     * Check if a cookie exists.
     *
     * @param string $name The name of the cookie.
     * @return bool True if the cookie exists, false otherwise.
     */
    public static function exists($name) {
        return isset($_COOKIE[$name]);
    }

    /**
     * Delete a cookie.
     *
     * @param string $name The name of the cookie.
     * @return bool True if the cookie was deleted successfully, false otherwise.
     */
    public static function delete($name) {
        if (self::exists($name)) {
            setcookie($name, "", time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
            unset($_COOKIE[$name]);
            return true;
        }
        return false;
    }
}
