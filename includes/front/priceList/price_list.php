<?php
add_action('wp_enqueue_scripts', 'my_theme_scripts');
function my_theme_scripts()
{
    if (is_product() || is_shop() || is_cart() || is_product_category()) {
        wp_enqueue_script('price_list', P18AW_FRONT_URL . 'priceList/price_list.js', array('jquery'), true);
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
    $id = get_current_user_id();
    $price_list = get_user_meta($id, 'custpricelists', true);
    if (!empty($price_list)) {
        $price_list = $price_list[0]["PLNAME"];

        $data = $GLOBALS['wpdb']->get_results('
            SELECT  *
            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
            WHERE product_sku = ' . $product->get_sku() . ' and price_list_code=' . $price_list . ' 
            ORDER BY price_list_quant ASC'
            ,
            ARRAY_A
        );
        if ($data && count($data) > 1) {
            $product->set_regular_price($data[0]['price_list_price'])
            ?>
            <table style="width:100%">
                <thead>
                <tr>
                    <th>כמות</th>
                    <th>מחיר</th>
                </tr>
                </thead>
                <tbody id="price_list">
                <?php
                foreach ($data as $item) {
                    $price = $item["price_list_price"];
                    $quant = $item["price_list_quant"];
                    ?>
                    <tr>
                        <td><?= $price ?></td>
                        <td><?= $quant ?></td>
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
