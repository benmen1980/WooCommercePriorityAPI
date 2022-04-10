<?php
add_action('woocommerce_product_options_general_product_data', 'woo_add_custom_general_fields');

function woo_add_custom_general_fields()
{
    // Define your fields here.
    // You can create text, textarea, select, checkbox and custom fields

    global $woocommerce, $post;
    $packs = get_post_meta($post->ID, 'pri_packs',false);
    if (!empty($packs)) {
        echo '<div class="options_group">';

        // Custom fields will be created here...
        ?>
        <p class="form-field custom_field_type">
            <label for="custom_field_type"><?php echo __('Packs', 'p18a'); ?></label>
            <span class="wrap">
        <?php

        foreach ($packs[0] as $pack) {
            echo $pack['PACKNAME'] . ' ' . $pack['PACKQUANT'] . '<br>';
        }

        ?>
    </span>

        </p>
        <?php

        echo '</div>';
    }

}


// define the woocommerce_before_add_to_cart_button callback
function action_woocommerce_before_add_to_cart_button()
{
    global $product;
    $id = $product->get_id();
    $packs = get_post_meta($id, 'pri_packs', false);
    if (!empty($packs)) {
        ?>
        <label for="cars"><?php _e('Choose a pack:', 'storefront'); ?></label>

        <select name="packs" id="pri-packs">
            <?php
            foreach ($packs[0] as $pack) {
                echo $pack['PACKNAME'] . ' ' . $pack['PACKQUANT'] . '<br>';
                echo ' <option  value="' . $pack['PACKQUANT'] . '" pack-code="'.$pack['PACKCODE'].'">' . $pack['PACKNAME'].' | '.$pack['PACKQUANT'] . '</option>';
            }
            ?>
        </select>
        <?php
    }
}

;

// add the action product page
add_action('woocommerce_before_add_to_cart_form', 'action_woocommerce_before_add_to_cart_button', 10, 0);
//  add the action shop page
add_action( 'woocommerce_shop_loop_item_title', 'action_woocommerce_before_add_to_cart_button' );

add_action('wp_enqueue_scripts', 'my_theme_scripts');
function my_theme_scripts()
{
    if (is_product()) {
        //wp_enqueue_script('packs_js', P18AW_ASSET_URL.'packs.js',array('jquery'),true);
    }
}

add_filter('woocommerce_quantity_input_args', 'jk_woocommerce_quantity_input_args', 10, 2); // Simple products
function jk_woocommerce_quantity_input_args($args, $product)
{
    $packs = get_post_meta($product->get_id(), 'pri_packs', false);
    if (empty($packs)) {
        return $args;
    }
    if (!is_cart()) {
        $args['input_value'] = 0;
        $args['min_value'] = 0;
        $args['step'] = $packs[0]['PACKQUANT'];  // need to get the steps of the first pack
    }
    return $args;
}


