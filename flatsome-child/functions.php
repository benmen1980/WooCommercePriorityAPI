<?php
// Add custom Theme Functions here

function isMobileDevice() { 
    return preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo 
|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i" 
, $_SERVER["HTTP_USER_AGENT"]); 
} 
/**
 * Enqueue our JS file
 */
function winn_enqueue_scripts() {
	wp_register_script( 'cart-script', get_stylesheet_directory_uri() . '/js/update-cart-item-ajax.js', array( 'jquery' ), time(), true );
	wp_localize_script('cart-script','admin_vars',array('ajaxurl' => admin_url( 'admin-ajax.php' )));
	 wp_enqueue_script( 'cart-script' );
}
add_action( 'wp_enqueue_scripts', 'winn_enqueue_scripts' );

/**
 * Adds a quantity before cartesian.
 */
function add_quantity_before_cart() {
	$product = wc_get_product(get_the_ID());
	$id = get_the_ID();
	$packs = get_post_meta($id, 'packs', true);
	?>
	<div class="pack-wrapper">
	<?php
	if (!empty($packs)) {
		?>
		<label for="pri-packs"><?php _e('Choose a pack:', 'storefront');?></label>

		<select name="packs" id="pri-packs">

			<?php
		foreach ($packs as $pack) {
			echo $pack['PACKNAME'] . ' ' . $pack['PACKQUANT'] . '<br>';
			echo ' <option value="' . $pack['PACKQUANT'] . '">' . $pack['PACKNAME'] . '</option>';
		}
		?>
		</select>
		<?php
	}

	if (!$product->is_sold_individually() && $product->is_purchasable()) {
		woocommerce_quantity_input(array('input_value' => 0, 'min_value' => 0)); /*, 'max_value' => $product->get_stock_quantity()*/
	}
	?>
	</div>
	
	<?php

}

//add_action('woocommerce_after_shop_loop_item', 'add_quantity_before_cart',10);


/**
 * Shows the stock. on single product page
 */
add_action('woocommerce_after_shop_loop_item', 'show_stock',50);
add_action('woocommerce_after_add_to_cart_button', 'show_stock');
function show_stock(){
	global $product, $woocommerce_loop;
	$stock = number_format($product->get_stock_quantity(), 0, '', '');
	if($stock <= 0) {
		$cls = "red";
	}
	else {
		$cls = "gray";
	}
	?>
	<div class="totalamt-wrapper <?php echo $cls; ?>">
		<?php
		if(is_product() && !$woocommerce_loop['name'] == 'related'){
			_e("<p> מלאי: יש במלאי  </p>");
		}
		?>
		<label>
			<?php 
				_e('סה”כ כמות  :');
				echo $stock;
			?>
		</label>	
	</div>
	<?php
}

/**
 * add class to product loop on shop and category page
 */
add_filter('post_class', function ($classes, $class, $product_id) {
	if (is_product_category() || is_shop()) {
		//only add these classes if we're on a product category page.
		$classes = array_merge(['theme-products'], $classes);
	}
	return $classes;
}, 10, 3);


/**
 * Add product SKU on single product page after product title
 */
add_action('woocommerce_single_product_summary', 'replace_product_title_by_product_sku', 5);
function replace_product_title_by_product_sku() {
	global $product;

	if ($product->get_sku()) {
		//remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);
		echo '<p>' . esc_html($product->get_sku()) . __(': SKU') . '</p>';
	}
}

/**
 * remove excerpt and add description tabs there - beside product image on single product page 
 
 */
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20);
add_action('woocommerce_single_product_summary', 'woocommerce_output_product_data_tabs', 6);
remove_action('woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10);

/**
 * footer script for header menu hover
 */
add_action('wp_footer', 'custom_footer_script', 10);
function custom_footer_script() {
	$path = get_stylesheet_directory_uri();
	?>
	<script>
		jQuery(document).ready(function(){



			jQuery('.header-nav-main li').hover(function(){

					jQuery('.header-main').addClass('z-indexup');

			},function(){
					jQuery('.header-main').removeClass('z-indexup');

			}
			);

			jQuery('.single-product .cart .quantity.buttons_added').find('.minus').val('').css('background','url("<?php echo $path; ?>/images/down-arrow.png")');
			jQuery('.single-product .cart .quantity.buttons_added').find('.plus').val('').css('background','url("<?php echo $path; ?>/images/up-arrow.png")');

			jQuery('.woocommerce-cart .cart_item .quantity.buttons_added').find('.minus').val('').css('background','url("<?php echo $path; ?>/images/cart-down-arrow.png")');
			jQuery('.woocommerce-cart .cart_item .quantity.buttons_added').find('.plus').val('').css('background','url("<?php echo $path; ?>/images/up-arrow.png")');
			/*jQuery('.header-nav-main li .nav-dropdown li').hover(function(){
					jQuery('.header-main').addClass('z-indexup');

			}, function(){

					jQuery('.header-main').removeClass('z-indexup');

			});*/
			
		});
	</script>
	<?php
}
// Disable product review (tab)
function woo_remove_product_tabs($tabs) {
	unset($tabs['reviews']); 					// Remove Reviews tab

	return $tabs;
}

add_filter( 'woocommerce_product_tabs', 'woo_remove_product_tabs', 98 );


/*add_action('woocommerce_before_single_product', 'backto_category', 5, 0);
function backto_category() {
	global $post;
	$terms = get_the_terms($post->ID, 'product_cat');

	foreach ($terms as $term) {

		$product_cat_id = $term->term_id;

	}
	_e('<p class="backtocat"><a href="#">Back to </a></p>');

}*/

/**
 * Update cart item notes
 */
function winn_update_cart_notes() {
 // Do a nonce check
	if( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'woocommerce-cart' ) ) {
		wp_send_json( array( 'nonce_fail' => 1 ) );
	 	exit;
	}
 // Save the notes to the cart meta
 	$cart = WC()->cart->cart_contents;
 	$cart_id = $_POST['cart_id'];
 	$notes = $_POST['notes'];
 	$cart_item = $cart[$cart_id];
 	$cart_item['notes'] = $notes;
 	echo $notes;
 	WC()->cart->cart_contents[$cart_id] = $cart_item;
 	WC()->cart->set_session();
 	wp_send_json( array( 'success' => 1 ) );
 	exit;
}
add_action( 'wp_ajax_winn_update_cart_notes', 'winn_update_cart_notes' );

function winn_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
 	foreach( $item as $cart_item_key=>$cart_item ) {
 		if( isset( $cart_item['notes'] ) ) {
 			$item->add_meta_data( 'notes', $cart_item['notes'], true );
 		}
 	}
}
add_action( 'woocommerce_checkout_create_order_line_item', 'winn_checkout_create_order_line_item', 10, 4 );


 
 
// select
add_action( 'woocommerce_after_order_notes', 'winn_date_field' );
function winn_date_field( $checkout ){
 
	// you can also add some custom HTML here
 
	woocommerce_form_field( 'shippingduedate', array(
		'type'          => 'date', // text, textarea, select, radio, checkbox, password, about custom validation a little later
		'required'	=> true, // actually this parameter just adds "*" to the field
		'class'         => array('shipping-due-field', 'form-row-wide'), // array only, read more about classes and styling in the previous step
		'label'         => 'Due Date',
		'label_class'   => 'shipping-due-label',
		'validate' => array('validate-shippingduedate'), // sometimes you need to customize labels, both string and arrays are supported
		
		), $checkout->get_value( 'shippingduedate' ) );
 
	// you can also add some custom HTML here
 
}

//add_filter( 'woocommerce_ship_to_different_address_checked', '__return_true' );

// save fields to order meta
add_action( 'woocommerce_checkout_update_order_meta', 'winn_save_what_we_added' );
 
// save field values
function winn_save_what_we_added( $order_id ){
 
	if( !empty( $_POST['shippingduedate'] ) )
		update_post_meta( $order_id, 'shippingduedate', sanitize_text_field( $_POST['shippingduedate'] ) );
 
}
add_filter( 'woocommerce_checkout_fields' , 'winn_not_required_fields', 9999 );
 
function winn_not_required_fields( $f ) {
 	
	unset( $f['billing']); // that's it
	return $f;
}

/**
 * 
 * Shows the packs on cartesian page. Add action hook for displaying packs on cart page.
 */
function show_packs_on_cart_page() {
	do_action('show_packs_on_cart_page');
}
