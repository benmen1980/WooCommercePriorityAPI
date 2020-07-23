<?php
add_action( 'woocommerce_product_options_general_product_data', 'woo_add_custom_general_fields' );

function woo_add_custom_general_fields() {
	// Define your fields here.
	// You can create text, textarea, select, checkbox and custom fields

	global $woocommerce, $post;

	echo '<div class="options_group">';

	// Custom fields will be created here...
	?>
	<p class="form-field custom_field_type">
		<label for="custom_field_type"><?php echo __( 'Packs', 'p18a' ); ?></label>
		<span class="wrap">
		<?php
		$packs = get_post_meta( $post->ID, 'packs', true );
		if(!empty($packs)){
			foreach($packs as $pack){
				echo $pack['PACKNAME'].' '.$pack['PACKQUANT'].'<br>';
			}
		}else{
			_e('There are no packs for this product','p18a');
		}


		?>
	</span>

	</p>
	<?php

	echo '</div>';

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
	$packs = get_post_meta( $id, 'packs', true );
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

// add the action
add_action( 'woocommerce_before_add_to_cart_form', 'action_woocommerce_before_add_to_cart_button', 10, 0 );

add_action( 'wp_enqueue_scripts', 'my_theme_scripts' );
function my_theme_scripts(){
	if( is_product() ) {
		wp_enqueue_script('crm_js', get_stylesheet_directory_uri().'/assets/js/crm.js',
			array('jquery'),filemtime(get_stylesheet_directory() . '/assets/js/crm.js'),true);
	}
}