<?php
add_action('wp_enqueue_scripts', 'my_theme_scripts_price_list_variation',999);
function my_theme_scripts_price_list_variation()
{
	if (is_product() || is_shop() || is_cart() || is_product_category()) {
		$product = wc_get_product();
		if ( is_product() && $product->is_type( 'variable' ) ) {
			wp_enqueue_script('price_list', P18AW_FRONT_URL . 'price_list_variation/price_list_variation.js', array('jquery'), true);
			wp_enqueue_style('priceList-css', P18AW_FRONT_URL . 'priceList/price_list.css');
		}
	}
}

add_filter('woocommerce_after_add_to_cart_form', 'simply_pricelist_variation_qty_table');
function simply_pricelist_variation_qty_table()
{
	global $product;
	if (!$product->is_type('variable')){return;}
	if (!empty($price_list)){return;}
	$id = get_current_user_id();
	if(!empty(get_user_meta($id, 'custpricelists', true))){
		$price_list =   get_user_meta($id, 'custpricelists', true);
	}else{
		$locale 		= get_locale();
		$basePriceCode 	= apply_filters( 'simply_modify_basePriceCode', $locale == 'he_IL' ? 'בסיס' : 'Base' );
		$price_list[0] 	= ['PLNAME' => $basePriceCode];
	};
	$price_list = $price_list[0]["PLNAME"];
	$variations = $product->get_children();
	foreach ($variations as $variation_id) {
		$variation = wc_get_product($variation_id);
		$sku = $variation->get_sku();
			$sql = '
            SELECT  *
            FROM ' . $GLOBALS['wpdb']->prefix . 'p18a_pricelists
            WHERE product_sku = \'' . $sku . '\' and price_list_code=\'' . $price_list . '\' 
            ORDER BY price_list_quant ASC';
			$data = $GLOBALS['wpdb']->get_results($sql,
				ARRAY_A
			);
           // echo ('variation table here...<br>');
			if ($data && (count($data) > 1 || (count($data) == 1 && $data[0]['price_list_quant'] > 1))) {
				// $product->set_regular_price($data[0]['price_list_price'])
				ob_start();
				?>
				<input type="hidden" name="price_regular" id="price_regular" value="">
				<table style="width:100%" class="simply-tire-price-grid hidden-table" data-variation-id="<?php echo $variation_id ?>">
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
				$output = ob_get_clean();
                echo $output;
			}
	}

}