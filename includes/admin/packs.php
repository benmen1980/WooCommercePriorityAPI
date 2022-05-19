<?php
add_action('woocommerce_product_options_general_product_data', 'woo_add_custom_general_fields');
function woo_add_custom_general_fields()
{
    // Define your fields here.
    // You can create text, textarea, select, checkbox and custom fields
    global $woocommerce, $post;
    // Custom fields will be created here...
    ?>
    <div class="options_group">
        <p class="form-field custom_field_type">
            <label for="custom_field_packs"><?php echo __('Packs', 'p18a'); ?></label>
            <span class="wrap">
		<?php
        $packs = get_post_meta($post->ID, 'pri_packs', false);
        if (!empty($packs)) {
            foreach ($packs[0] as $pack) {
                echo $pack['PACKNAME'] . ' ' . $pack['PACKQUANT'] . '<br>';
            }
        } else {
            _e('There are no packs for this product', 'p18a');
        }
        ?>

        </p>
    </div>
    <?php
}

// create the packs drop down
function create_pack_drop_down($product)
{
    $id = $product->get_id();

    $packs = get_post_meta($id, 'pri_packs', false);
    //if ( is_page( 'cart' ) || is_cart() || is_product() ) {
    if (!empty(WC()->cart)) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            if ($product_id == $id) {
                if (isset($cart_item['pack_step'])) {
                    $step = $cart_item['pack_step'];
                }

            }
        }
        //echo 'step:'.$step;
        //}
    }
    if (!empty($packs)) {
        ?>
        <label for="cars"><?php _e('Choose a pack:', 'storefront'); ?></label>

        <select name="packs" class="pri-packs">
            <?php
            foreach ($packs[0] as $pack) {
                echo $pack['PACKNAME'] . ' ' . $pack['PACKQUANT'] . '<br>';
                echo ' <option value="' . $pack['PACKQUANT'] . '">' . $pack['PACKNAME'] . ' | ' . $pack['PACKQUANT'] . '</option>';
            }
            ?>
        </select>
        <br>
        <label><?php _e('Num a pack:', 'storefront'); ?> </label>
        <span id="num-packs">0</span>
        <?php
    }
}

// define the woocommerce_before_add_to_cart_button callback
add_action('show_packs_on_cart_page', 'add_pack_cart_item', 10, 2);
function add_pack_cart_item($product)
{
    create_pack_drop_down($product);
}

function action_woocommerce_before_add_to_cart_button()
{
    global $product;
    create_pack_drop_down($product);
}

;

// add the action product page
add_action('woocommerce_before_add_to_cart_form', 'action_woocommerce_before_add_to_cart_button', 10, 0);
//  add the action shop page
//add_action( 'woocommerce_shop_loop_item_title', 'action_woocommerce_before_add_to_cart_button' );

add_action('wp_enqueue_scripts', 'my_theme_scripts');
function my_theme_scripts()
{
    if (is_product() || is_shop() || is_cart() || is_product_category()) {
        wp_enqueue_script('packs_js', P18AW_ASSET_URL . 'packs.js', array('jquery'), false);
    }
}

add_filter('woocommerce_quantity_input_args', 'jk_woocommerce_quantity_input_args', 10, 2); // Simple products
function jk_woocommerce_quantity_input_args($args, $product)
{
    $args['min_value'] = 0;
    $args['max_value'] = 1000000; // Max quantity (default = -1)
    $packs = get_post_meta($product->get_id(), 'pri_packs', false);
    //if( is_cart() || is_product()) {

    //if ( sizeof( WC()->cart->get_cart() ) > 0  ) {
    if (WC()->cart && !WC()->cart->is_empty()) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product_id = $cart_item['product_id'];
            if ($product_id == $product->get_id()) {
                if (isset($cart_item['pack_step'])) {
                    $step = $cart_item['pack_step'];
                } else {
                    $step = $packs[0][0]['PACKQUANT'];
                    $quantity = 0;
                    break;

                }

                $quantity = $cart_item['quantity'];
                break;
            } else {
                $step = $packs[0][0]['PACKQUANT'];
                $quantity = 0;
            }

        }
    } else {
        $step = $packs[0][0]['PACKQUANT'];
        $quantity = 0;
    }
    $args['step'] = $step;
    $args['input_value'] = $quantity;
    //echo $step;
    //}
    // else{
    //     $args['input_value']    = 0;
    //     $args['step']    = $packs[0]['PACKQUANT'];  // need to get the steps of the first pack
    // }

    return $args;
}
