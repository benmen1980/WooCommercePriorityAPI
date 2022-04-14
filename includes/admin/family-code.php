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
            <label for="custom_field_family_code"><?php echo __('Family Code', 'p18a'); ?></label>
            <span class="wrap">
		<?php
        $family_code = get_post_meta($post->ID, 'family_code', true);

                echo $family_code;

        ?>

        </p>
    </div>
    <?php
}

