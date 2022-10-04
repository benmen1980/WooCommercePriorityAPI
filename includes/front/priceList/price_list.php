<?php
add_action('wp_enqueue_scripts', 'my_theme_scripts_price_list');
function my_theme_scripts_price_list()
{
    if (is_product() || is_shop() || is_cart() || is_product_category()) {
        wp_enqueue_script('price_list', P18AW_FRONT_URL . 'priceList/price_list.js', array('jquery'), true);
        wp_enqueue_style('priceList-css', P18AW_FRONT_URL . 'priceList/price_list.css');

    }
}

// Add data to cart item
add_filter('woocommerce_add_cart_item_data', 'simply_add_cart_item_data', 25, 2);
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

add_filter('woocommerce_after_add_to_cart_form', 'simply_pricelist_qty_table');
function simply_pricelist_qty_table()
{
    global $product;
    $price = $product->get_regular_price();
    $id = get_current_user_id();
    $price_list = get_user_meta($id, 'custpricelists', true);
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
            <table style="width:100%" class="price_list_table">
                <thead>
                <tr class="price_list_tr">
                    <th class="price_list_td"><?php _e('Quantity','woocommerce');?></th>
                    <th class="price_list_td"><?php _e('Price','woocommerce');?></th>
                </tr>
                </thead>
                <tbody id="price_list">
                <?php
                foreach ($data as $item) {
                    $price = $item["price_list_price"];
                    $quant = $item["price_list_quant"];
                    ?>
                    <tr class="price_list_tr">
                        <td class="price_list_td"><?= $quant ?></td>
                        <td class="price_list_td"> <?= $price ?></td>
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
