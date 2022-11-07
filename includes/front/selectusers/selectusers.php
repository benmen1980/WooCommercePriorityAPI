<?php

add_shortcode('select_users','simply_populate_users');


//this is for populate users in front
function simply_populate_users($atts)
{
    $add_agent_to_drop_down = $atts['add_agent_to_drop_down'] == "true" ;
    $current_user_id = get_current_user_id();
    $current_first_name = get_user_meta( $current_user_id, 'first_name', true );
    $current_last_name = get_user_meta( $current_user_id, 'last_name', true );
    if(empty($_SESSION['related_users']) && empty(get_user_meta( $current_user_id, 'select_users', true))){
        return null;
    }
    if(!empty(get_user_meta( $current_user_id, 'select_users', true))) {
        $selected_users = get_user_meta( $current_user_id, 'select_users', true);
        $_SESSION['agent_id'] = $current_user_id;
        if(true == $add_agent_to_drop_down){
            $selected_users[] = $current_user_id;
        };

    }
    else{
        $selected_users = $_SESSION['related_users'];
    }
    $_SESSION['related_users'] = $selected_users;
    if(empty($selected_users)){
        //return __('No Sites','p18a');
        return __('','p18a');
    }
    $select_options = '<select name="users" class="change_user">';
    $select_options .= ' <option disabled selected value>'.__('-- select an option --','p18w').'</option>';
    /*
    if(!in_array($current_user_id,$selected_users) && $removeInitailUser){
        $select_options .= '<option value="' . $current_user_id . '" '. $selected .' >' . $current_first_name.' '.$current_last_name . '</option>';
    }
    */
    foreach ($selected_users as $userid) {
        $first_name = get_user_meta( $userid, 'first_name', true );
        $last_name = get_user_meta( $userid, 'last_name', true );

        if($userid ==  $current_user_id){
            $selected = ' selected';
        }
        $select_options .= '<option value="' . $userid . '" '. $selected .' >' . $first_name.' '.$last_name . '</option>';
        $selected = '';
    }
    $select_options .= '</select>';
    return $select_options;
}

add_action( 'wp_footer', 'simply_ajax_change_user' );
/* handle session on frontend */
function simply_ajax_change_user() { ?>
    <script type="text/javascript" >
        jQuery(".change_user").change(function($) {
            ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ) ?>'; // get ajaxurl
            var data = {
                'action': 'change_user', // your action name
                'userid': this.value,
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

add_action("wp_ajax_change_user" , "change_user");
add_action("wp_ajax_nopriv_change_user" , "change_user");
function change_user(){
    $user_id = $_POST['userid'];
    $user = get_user_by('id', $user_id);
    if ($user) {
        if (is_user_logged_in()) {
            wp_logout();
            global $woocommerce;
            $woocommerce->cart->empty_cart();
        }
        wp_set_current_user( $user_id, $user->user_login );
        wp_set_auth_cookie( $user_id );
        do_action( 'wp_login', $user->user_login,$user );
    }
    wp_die();
}


