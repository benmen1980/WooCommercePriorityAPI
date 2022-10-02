<?php
function wc_remove_checkout_fields($fields)
{
    unset($fields);
    if (!isset($fields['billing']['change_user'])) {
        $current_user_id = get_current_user_id();
        $selected_users = get_user_meta($current_user_id, 'select_users', true);
        $select_options = array();
        $i = 0;
        if (!empty($selected_users)) {
            foreach ($selected_users as $userid) {
                $first_name = get_user_meta($userid, 'first_name', true);
                $last_name = get_user_meta($userid, 'last_name', true);
                $select_options[$userid] = $first_name . ' ' . $last_name;
            }
        }
        unset($select_options[$current_user_id]);
        $a = $select_options;

        if (!empty($a)) {
            $fields['billing']['change_user']['label'] = "סניף";
            $fields['billing']['change_user']['type'] = 'select';
            $fields['billing']['change_user']['class'] = array('change_user');
            $fields['billing']['change_user']['options'] = $a;
            $fields['billing']['change_user']['required'] = true;
        }
    }
    return $fields;
}
add_filter('woocommerce_checkout_fields', 'wc_remove_checkout_fields');
function my_custom_checkout_field_update_order_meta($order_id)
{
    if ($_POST['change_user']) {
        $userid = $_POST['change_user'];
        update_post_meta($order_id, '_customer_user', $userid);
    }
}
add_action('woocommerce_checkout_update_order_meta', 'my_custom_checkout_field_update_order_meta');
