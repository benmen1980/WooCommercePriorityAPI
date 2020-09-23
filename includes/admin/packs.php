<?php
add_action( 'woocommerce_product_options_general_product_data', 'woo_add_custom_general_fields' );

function woo_add_custom_general_fields() {
	// Define your fields here.
	// You can create text, textarea, select, checkbox and custom fields

	global $woocommerce, $post;
    // Custom fields will be created here...
	?>
	<div class="options_group">
	<p class="form-field custom_field_type">
		<label for="custom_field_packs"><?php echo __( 'Packs', 'p18a' ); ?></label>
		<span class="wrap">
		<?php
		$packs = get_post_meta( $post->ID, 'pri_packs', true );
		if(!empty($packs)){
			foreach($packs as $pack){
				echo $pack['PACKNAME'].' '.$pack['PACKQUANT'].'<br>';
			}
		}else{
			_e('There are no packs for this product','p18a');
		}
		?>
        </span>
        <br>
        <label for="custom_field_barcode"><?php echo __( 'Barcode', 'p18a' ); ?></label>
		<span class="wrap">
        <?php  echo(get_post_meta($post->ID,'simply_barcode',true)) ?>
            (The meatadata name is 'simply_barcode')
	    </span>

	</p>
	</div>
    <?php
}

add_filter( 'woocommerce_quantity_input_args', 'bloomer_woocommerce_quantity_changes', 10, 2 );

function bloomer_woocommerce_quantity_changes( $args, $product ) {

	if ( ! is_cart() ) {
		global $product;
		$id    = $product->get_id();
		$packs = get_post_meta( $id, 'packs', true );
		if ( !empty( $packs ) ) {
			$args['input_value'] = $packs[0]['PACKQUANT']; // Start from this value (default = 1)
			$args['max_value']   = 1000; // Max quantity (default = -1)
			$args['min_value']   = $packs[0]['PACKQUANT']; // Min quantity (default = 0)
			$args['step']        = $packs[0]['PACKQUANT']; // Increment/decrement by this value (default = 1)
		}

	} else {

		// Cart's "min_value" is already 0 and we don't need "input_value"
		$args['max_value'] = 10; // Max quantity (default = -1)
		$args['step'] = 2; // Increment/decrement by this value (default = 0)
		// COMMENT OUT FOLLOWING IF STEP < MIN_VALUE
		// $args['min_value'] = 4; // Min quantity (default = 0)

	}

	return $args;

}

// define the woocommerce_before_add_to_cart_button callback
function action_woocommerce_before_add_to_cart_button(  ) {
	global $product;
	$id = $product->get_id();
	$packs = get_post_meta( $id, 'pri_packs', true );
	if(!empty($packs)) {
		?>
		<label for="cars"><?php _e( 'Choose a pack:', 'storefront' ); ?></label>

		<select name="packs" id="pri-packs">
			<?php
			foreach ( $packs as $pack ) {
				echo $pack['PACKNAME'] . ' ' . $pack['PACKQUANT'] . '<br>';
				echo ' <option value="' . $pack['PACKQUANT'] . '">' . $pack['PACKNAME'] . '</option>';
			}
			?>
		</select>
		<?php
	}
};

// add the action product page
add_action( 'woocommerce_before_add_to_cart_form', 'action_woocommerce_before_add_to_cart_button', 10, 0 );
//  add the action shop page
//add_action( 'woocommerce_shop_loop_item_title', 'action_woocommerce_before_add_to_cart_button' );

add_action( 'wp_enqueue_scripts', 'my_theme_scripts' );
function my_theme_scripts(){
	if( is_product() ) {
		//wp_enqueue_script('packs_js', P18AW_ASSET_URL.'packs.js',array('jquery'),true);
	}
}
add_filter( 'woocommerce_quantity_input_args', 'jk_woocommerce_quantity_input_args', 10, 2 ); // Simple products
function jk_woocommerce_quantity_input_args( $args, $product ) {
    if ( ! is_cart() ) {
        $args['input_value']    = 0;
        $args['min_value']    = 0;
        $packs = get_post_meta( $product->get_id(), 'pri_packs', true );
        $args['step']    = $packs[0]['PACKQUANT'];  // need to get the steps of the first pack
    }
    return $args;
}

