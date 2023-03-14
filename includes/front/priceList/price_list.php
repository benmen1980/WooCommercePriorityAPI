<?php

add_action('wp_enqueue_scripts', 'my_theme_scripts_price_list');
function my_theme_scripts_price_list()
{
    if (is_product() || is_shop() || is_cart() || is_product_category()) {
	    $product = wc_get_product();
	    if ( is_product() && $product->is_type( 'variable' ) ) {
	    }else{
		    wp_enqueue_script('price_list', P18AW_FRONT_URL . 'priceList/price_list.js', array('jquery'), true);
		    wp_enqueue_style('priceList-css', P18AW_FRONT_URL . 'priceList/price_list.css');
	    }
    }
}

// Add data to cart item
//add_filter('woocommerce_add_cart_item_data', 'simply_add_cart_item_data', 25, 2);
function simply_add_cart_item_data($cart_item_data, $product_id)
{
    $key = 'realprice';
    if (isset($_POST[$key]))
        $cart_item_data['custom_data'][$key] = $_POST[$key];
    return $cart_item_data;
}

// Displaying the checkboxes
add_action('woocommerce_before_add_to_cart_button', 'simply_add_fields_before_add_to_cart');
function simply_add_fields_before_add_to_cart()
{
    ?>
    <input type="hidden" name="realprice" id="realprice">
    <?php
}


add_action( 'woocommerce_before_calculate_totals', 'modify_cart_item_price', 11, 1 );
function modify_cart_item_price( $cart ) {
	if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
		return;
	}
// Loop through the cart items
	foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
// Get the product object
		$product = $cart_item['data'];
// Get the quantity of the cart item
		$quantity  = $cart_item['quantity'];
		$new_price = $product->get_regular_price();
		$id        = get_current_user_id();
		if ( ! empty( get_user_meta( $id, 'custpricelists', true ) ) ) {
			$price_list = get_user_meta( $id, 'custpricelists', true );
		} else {
			$locale        = get_locale();
			$price_list[0] = [ 'PLNAME' => $locale == 'he_IL' ? 'בסיס' : 'Base' ];
		};

		if ( ! empty( $price_list ) ) {
			$price_list = $price_list[0]["PLNAME"];
			$sql        = '
                    SELECT  *
                    FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                    WHERE product_sku = \'' . $product->get_sku() . '\' and price_list_code=\'' . $price_list . '\' 
                    ORDER BY price_list_quant ASC';
			$data       = $GLOBALS['wpdb']->get_results( $sql,
				ARRAY_A
			);
			foreach ( $data as $item ) {
				if ( $quantity >= $item['price_list_quant'] ) {
					$new_price = (float)$item['price_list_price'];
				}
			}
		}

// Set the new price for the cart item
		if ( method_exists( $cart_item['data'], 'set_price' ) ) {
			// Set the new price for the cart item
			$cart_item['data']->set_price( $new_price );
		} else {
			// Output an error message
			echo 'Unable to set price for cart item ' . $cart_item_key . '<br>';
		}
	}
}

//add_filter( 'woocommerce_add_to_cart_validation', 'simply_modify_product_price', 10, 5 );
function simply_modify_product_price( $passed, $product_id, $quantity, $variation_id = '', $variations = array() ) {
	// Check if the product is eligible for the price modification


		// Set the new price for the product
		$product = wc_get_product( $product_id );
        $new_price = $product->get_regular_price();
        $id = get_current_user_id();
        if(!empty(get_user_meta($id, 'custpricelists', true))){
	        $price_list =   get_user_meta($id, 'custpricelists', true);
        }else{
	    $locale = get_locale();
	    $price_list[0] = ['PLNAME' => $locale == 'he_IL' ? 'בסיס' : 'Base'];
        };

        if (!empty($price_list)) {
	        $price_list = $price_list[0]["PLNAME"];
	        $sql        = '
                    SELECT  *
                    FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
                    WHERE product_sku = \'' . $product->get_sku() . '\' and price_list_code=\'' . $price_list . '\' 
                    ORDER BY price_list_quant ASC';
	        $data       = $GLOBALS['wpdb']->get_results( $sql,
		        ARRAY_A
	        );
            foreach ($data as $item){
                if($quantity >= $item['price_list_quant']){
                    $new_price = $item['price_list_price'];
                }
            }
        }
		$product->set_price( $new_price );

	return $passed;
}

add_filter('woocommerce_after_add_to_cart_form', 'simply_pricelist_qty_table');
function simply_pricelist_qty_table()
{
    global $product;
    $price = $product->get_regular_price();
    $id = get_current_user_id();
    if(!empty(get_user_meta($id, 'custpricelists', true))){
	    $price_list =   get_user_meta($id, 'custpricelists', true);
        }else{
	    $locale = get_locale();
        $price_list[0] = ['PLNAME' => $locale == 'he_IL' ? 'בסיס' : 'Base'];
            };

    if (!empty($price_list)) {
        $price_list = $price_list[0]["PLNAME"];
        $sql = '
            SELECT  *
            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
            WHERE product_sku = \'' . $product->get_sku() . '\' and price_list_code=\'' . $price_list . '\' 
            ORDER BY price_list_quant ASC';
        $data = $GLOBALS['wpdb']->get_results($sql,
            ARRAY_A
        );
        if ($data && (count($data) > 1 || (count($data) == 1 && $data[0]['price_list_quant'] > 1))) {
            // $product->set_regular_price($data[0]['price_list_price'])
            ?>
            <input type="hidden" name="price_regular" id="price_regular" value="<?= $price ?>">
            <table style="width:100%" class="simply-tire-price-grid">
                <thead>
                <tr class="price_list_tr">
                    <th class="price_list_td"><?php _e('Quantity','woocommerce');?></th>
                    <th class="price_list_td"><?php _e('Price','woocommerce');?></th>
                </tr>
                </thead>
                <tbody id="simply-tire-price-grid-rows">
                <?php
                foreach ($data as $item) {
                    $price = $item["price_list_price"];
                    $float_price = $price;
                    $quantity = $item["price_list_quant"];
	                $arr = apply_filters('simply_tire_pricing_filter_price_and_quantity', ['price'=>$price,'quantity' => $quantity]);
                    $price = $arr['price'];
                    $quantity = $arr['quantity'];
                    ?>
                    <tr class="price_list_tr">
                        <td class="simply-tire-quantity"><?= $quantity ?></td>
                        <td class="simply-tire-price"><span> <?= $price ?></span><span hidden><?= $float_price ?></span></td>

                    </tr>
                    <?php
                }
                ?></tbody>
            </table>
            <?php

        }
    }
}

?>
