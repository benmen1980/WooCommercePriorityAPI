<?php

/**
 * Create a new variable product (with new attributes if they are).
 * (Needed functions:
 *
 * @param array $data | The data to insert in the product.
 * @since 3.0.0
 */



function create_product_variable($data)
{

    $postname = sanitize_title($data['title']);
    $author = empty($data['author']) ? '1' : $data['author'];

    $post_data = array(
        'post_author' => $author,
        'post_name' => $postname,
        'post_title' => !empty($data['title']) ? $data['title'] : '',
        'post_status' => $data['status'],
        'ping_status' => 'closed',
        'post_type' => 'product',
        'guid' => home_url('/product/' . $postname . '/'),
    );

    $product_id = wc_get_product_id_by_sku($data['sku']);

    if (!empty($data['sku']) && $product_id) {
        if($data['is_update_products'] == true){
            $post_data['ID'] = $product_id;
            $_product = wc_get_product( $product_id );

            // check if the item flagged as show in web, if not skip the item
            $show_in_web = $data['show_in_web'];
            if ( isset( $show_in_web ) ) {
                if ( $product_id == 0 &&  $show_in_web != 'Y' ) {
                    return $product_id;
                }
                if ( $product_id != 0 && $show_in_web != 'Y' ) {
                    $_product->set_status( 'draft' );
                    $_product->save();
                    return $product_id;
                }
            }

            // Update the product (post data)
            $product_id = wp_update_post($post_data);
            // $_product->set_status($this->option('item_status'));
            // $_product->save();
        }
        else{
            return $product_id;
        }
    } else {
        // Creating the product (post data)
        $product_id = wp_insert_post($post_data);
    }

    // Get an instance of the WC_Product_Variable object and save it
    $product = new WC_Product_Variable($product_id);
    global $wpdb;
    // @codingStandardsIgnoreStart
    $wpdb->query(
        $wpdb->prepare(
            "
			UPDATE $wpdb->posts
			SET post_title = '%s'
			WHERE ID = '%s'
			",
            $data['title'],
            $product_id
        )
    );
    $product->save();
    //Description
    if (!empty($data['content'])) {
        $product->set_description($data['content']);
    }
    // MAIN IMAGE
    if (!empty($data['image_id']) && !empty($data['image_file'])) {
        $file = $data['image_file'];
        $attach_id = $data['image_id'];
        include $file;
        require_once( ABSPATH . '/wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        set_post_thumbnail($product_id, $attach_id);
    }

    // IMAGES GALLERY
    if (!empty($data['gallery_ids']) && count($data['gallery_ids']) > 0)
        $product->set_gallery_image_ids($data['gallery_ids']);

    // SKU
    if (!empty($data['sku']))
        $product->set_sku($data['sku']);

    // STOCK (stock will be managed in variations)
    //$product->set_stock_quantity( $data['stock'] ); // Set a minimal stock quantity
    $product->set_manage_stock(false);
    $product->set_stock_status($data['stock']);

    //shipping method
    if (!empty($data['shipping'])) {
        $shipping_classes = get_terms(array('taxonomy' => 'product_shipping_class', 'hide_empty' => false));
        foreach ($shipping_classes as $shipping_class) {
            if ($data['shipping'] == $shipping_class->name) {
                // assign class to product
                $product->set_shipping_class_id($shipping_class->term_id); // Set the shipping class ID
            }
        }
    }

    // Tax class
    if (empty($data['tax_class']))
        $product->set_tax_class($data['tax_class']);

    // WEIGHT
    if (!empty($data['weight']))
        $product->set_weight(''); // weight (reseting)
    else
        $product->set_weight($data['weight']);

    $product->validate_props(); // Check validation

    ## ---------------------- VARIATION CATEGORIES ---------------------- ##


    if ($data['categories'] && is_array($data['categories'])) {
        foreach ($data['categories'] as $parent_cat => $category) {
            if (term_exists($category)) {
                wp_set_object_terms($product_id, $category, 'product_cat', true);
            } else {
                if ($category !== null) {
                    $terms_id = wp_set_object_terms($product_id, $category, 'product_cat', true);
                    if (!is_wp_error($terms_id)) {
                        $parent_id = get_term_by('name', $parent_cat)->term_id;
                        if (!$parent_id) {
                            $parent_id = wp_create_term($parent_cat, 'product_cat')->term_id;
                        }
                        foreach ($terms_id as $term_id) {
                            wp_update_term($term_id, 'product_cat', [
                                'parent' => $parent_id
                            ]);
                        }
                    }
                }
            }
        }
    }


    ## ---------------------- VARIATION TAGS ---------------------- ##


    if ($data['tags'] && is_array($data['tags']))
        wp_set_object_terms($product_id, $data['tags'], 'product_tag', true);


    ## ---------------------- VARIATION ATTRIBUTES ---------------------- ##

    $product_attributes = [];

    if (is_array($data['attributes'])) {
        foreach ($data['attributes'] as $key => $terms) {

            $taxonomy_id = wc_attribute_taxonomy_id_by_name($key);
            $taxonomy_name = wc_attribute_taxonomy_name($key);

            if (!$taxonomy_id) {
                wc_create_attribute([
                    'name' => $key,
                ]);
                register_taxonomy(
                    $taxonomy_name,
                    apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product')),
                    apply_filters('woocommerce_taxonomy_args_' . $taxonomy_name, array(
                        'labels' => array(
                            'name' => wc_sanitize_taxonomy_name($key),
                        ),
                        'hierarchical' => true,
                        'show_ui' => false,
                        'query_var' => true,
                        'rewrite' => false,
                    ))
                );
            }
            $is_variation = 1;
            
            $is_variation = apply_filters('simply_select_attr_for_variations', $key);
            

        
            $product_attributes[$taxonomy_name] = array(
                'name' => $taxonomy_name,
                'value' => '',
                'position' => '',
                'is_visible' => 0,
                'is_variation' => $is_variation,
                'is_taxonomy' => 1
            );

            foreach ($terms as $value) {
                $term_name = ucfirst($value);
                $term_slug = sanitize_title($value);

                // Check if the Term name exist and if not we create it.
                if (!term_exists($value, $taxonomy_name))
                    wp_insert_term($term_name, $taxonomy_name, array('slug' => $term_slug)); // Create the term

                // Set attribute values
                wp_set_object_terms($product_id, $term_name, $taxonomy_name, true);
            }
        }

        //$product_attributes = array_reverse($product_attributes, 1);

        /**
         * t205
         */
        $product_attributes_old = get_post_meta($product_id, '_product_attributes', true);
        $product_attributes = array_merge($product_attributes, is_array($product_attributes_old) ? $product_attributes_old : []);
        /**
         * end t205
         */

        update_post_meta($product_id, '_product_attributes', $product_attributes);
    }


    ## ---------------------- VARIATION CUSTOM TAXONOMY ---------------------- ##
    $data['id'] = $product_id;
    do_action( 'simply_update_parent_data', $data );

    $product->save(); // Save the data

    return $product_id;
}

/**
 * Create a product variation for a defined variable product ID.
 *
 * @param int $product_id | Post ID of the product parent variable product.
 * @param array $variation_data | The data to insert in the product.
 * @since 3.0.0
 */

function create_product_variation($product_id, $variation_data)
{
    // Get the Variable product object (parent)
    $product = wc_get_product($product_id);
    $variation_post = array(
        'post_title' => $product->get_title(),
        'post_name' => 'product-' . $product_id . '-variation',
        'post_status' => 'publish',
        'post_parent' => $product_id,
        'post_type' => 'product_variation',
        'guid' => $product->get_permalink()
    );

    $variation_id = wc_get_product_id_by_sku($variation_data['sku']);

    if (!empty($variation_data['sku']) && $variation_id) {
        if($variation_data['is_update_products'] == true){
            $variation_post['ID'] = $variation_id;
            // Update the product variation
            $variation_id = wp_update_post($variation_post);
        }
        else{
            do_action( 'simply_update_variation_price', $variation_data );
            return;
        }
    } else {
        // Creating the product variation
	    if($variation_data['show_in_web'] != 'Y'){
			return;
	    }
        $variation_id = wp_insert_post($variation_post); //67
    }

    // Get an instance of the WC_Product_Variation object
    $variation = new WC_Product_Variation($variation_id);
    if ($variation_data['show_front']) {
        update_post_meta($variation_id, 'show_front', $variation_data['show_front']);
    }
    if (!empty($variation_data['sku']))
        $variation->set_sku($variation_data['sku']);

    // Iterating through the variations attributes
    foreach ($variation_data['attributes'] as $attribute => $term_name) {
        $taxonomy = 'pa_' . sanitize_title($attribute); // The attribute taxonomy
        $taxonomy = 'pa_' . $attribute; // The attribute taxonomy
        // Check if the Term name exist and if not we create it.
        if (!term_exists($term_name, $taxonomy))
            $response = wp_insert_term($term_name, $taxonomy); // Create the term
        $term_slug = get_term_by('slug', $term_name, $taxonomy)->slug; // Get the term slug

        // Get the post Terms names from the parent variable product.
        $post_term_names = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'names'));

        // Check if the post term exist and if not we set it in the parent variable product.
        if (is_array($post_term_names) && !in_array($term_name, $post_term_names))
            $foo = wp_set_post_terms($product_id, $term_name, $taxonomy, true);
        // Set/save the attribute data in the product variation
        update_post_meta($variation_id, 'attribute_' . $taxonomy, $term_slug);


    }

    ## Set/save all other data

    // Prices
    if (empty($variation_data['sale_price'])) {
        $variation->set_price($variation_data['regular_price']);
    } else {
        $variation->set_price($variation_data['sale_price']);
        $variation->set_sale_price($variation_data['sale_price']);
    }
    $variation->set_regular_price($variation_data['regular_price']);
    
    // Stock

    if (empty($variation_data['stock'])) {
        $variation->set_stock_status('outofstock');
    } else {
        $variation->set_stock_status($variation_data['stock']);
    }

    // if not show in web set price 0 and set status outofstock
    if($variation_data['show_in_web'] != 'Y'){
        $variation->set_regular_price(0.0);
        $variation->set_price(0.0);
        $variation->set_sale_price(0.0);

        $variation->set_stock_status('outofstock');
        $variation->set_stock(0);

        // remove a specific variation from all the variations of a variable product      
        // Check if the product is a variable product
        if ($product && $product->is_type('variable')) {
            // Get the variations
            $variations = $product->get_children();
    
            foreach ($variations as $variation) {
                // Check if the current variation is the one to be removed
                if ($variation == $variation_id) {
                    // Remove the variation
                    wp_delete_post($variation_id, true); // Set the second parameter to true to permanently delete the variation
                }
            }
        }
    }

    /*if( ! empty($variation_data['stock_qty']) ){
        $variation->set_stock_quantity( $variation_data['stock_qty'] );
        $variation->set_manage_stock(true);
        $variation->set_stock_status('');
    } else {
        $variation->set_manage_stock(false);
    }*/


    update_post_meta($variation_id, 'product_code', $variation_data['product_code']);
    if (!empty($variation_data['image_id']) && !empty($variation_data['image_file'])) {
        $file = $variation_data['image_file'];
        $attach_id = $variation_data['image_id'];
        include $file;
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        set_post_thumbnail($variation_id, $attach_id);
    }
    $variation->set_weight(''); // weight (reseting)

   

	$variation_data['variation_id'] = $variation_id;
	do_action( 'simply_update_variation_data', $variation_data );

    $variation->save(); // Save the data

    return $variation_id;
}

function filter_woocommerce_get_children($children, $product, $false)
{
    // NOT backend
    if (is_admin()) return $children;

    // Delete by value: Searches the array for a given value and returns the first corresponding key if successful

    foreach ($children as $key => $value) {
        $show = get_post_meta($value, 'show_front', true);
        if ($show == 'N') {
            unset($children[$key]);
        }

    }
    return $children;

}

add_filter('woocommerce_get_children', 'filter_woocommerce_get_children', 10, 3);
