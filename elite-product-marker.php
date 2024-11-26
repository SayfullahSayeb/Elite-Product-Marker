<?php
/**
 * Plugin Name: Elite Product Marker 
 * Description:  Easily mark and highlight premium products in your store, similar to Daraz, ensuring they stand out with special status and visibility.
 * Version: 1.0
 * Author: Sayfullah Sayeb
 * Author URI: https://github.com/SayfullahSayeb
 */
add_filter('plugin_row_meta', 'elite_product_marker_plugin_row_meta', 10, 2);
function elite_product_marker_plugin_row_meta($links, $file) {
    if ($file == plugin_basename(__FILE__)) {
        $links[] = '<a href="https://github.com/SayfullahSayeb/Elite-Product-Marker" target="_blank">View Details</a>';
    }
    return $links;
}
add_action( 'woocommerce_product_data_panels', 'add_elite_product_field' );
function add_elite_product_field() {
    global $post;
    if ( in_array( $post->post_type, array( 'product', 'product_variation' ) ) ) {
        $is_grouped_product = $post->post_parent && get_post_type( $post->post_parent ) === 'product';
        $field_id = $is_grouped_product ? '_is_elite_product_grouped' : '_is_elite_product';
        $label = __( 'Mark as Elite Product', 'woocommerce' );
        $description = $is_grouped_product ? __( 'Check this box if this grouped product is Elite.', 'woocommerce' ) : __( 'Check this box if this product is Elite.', 'woocommerce' );
        
        woocommerce_wp_checkbox(
            array(
                'id' => $field_id,
                'label' => $label,
                'description' => $description,
                'desc_tip' => true,
                'value' => get_post_meta( $post->ID, $field_id, true )
            )
        );
    }
}
add_action( 'woocommerce_process_product_meta', 'save_elite_product_field' );
add_action( 'woocommerce_process_product_meta_grouped', 'save_elite_product_field' );
function save_elite_product_field( $product_id ) {
    $is_elite_product = isset( $_POST['_is_elite_product'] ) ? 'yes' : 'no';
    $is_elite_product_grouped = isset( $_POST['_is_elite_product_grouped'] ) ? 'yes' : 'no';
    $is_elite = $is_elite_product === 'yes' || $is_elite_product_grouped === 'yes';

    update_post_meta( $product_id, '_is_elite_product', $is_elite ? 'yes' : 'no' );

    $term = get_term_by( 'name', 'Elite', 'product_cat' );
    if ( !$term ) {
        $term = wp_insert_term('Elite', 'product_cat', array(
            'description' => 'Elite products category.',
            'slug' => 'elite',
            'parent' => 0
        ));
        $term_id = !is_wp_error($term) ? $term['term_id'] : null;
    } else {
        $term_id = $term->term_id;
    }
    if ($is_elite) {
        if (!has_term($term_id, 'product_cat', $product_id)) {
            wp_set_object_terms($product_id, $term_id, 'product_cat', true);
        }
    } else {
        if (has_term($term_id, 'product_cat', $product_id)) {
            wp_remove_object_terms($product_id, $term_id, 'product_cat');
        }
    }
    if ($is_elite) {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_key' => '_is_elite_product',
            'meta_value' => 'yes',
            'post__not_in' => array($product_id)
        );

        $elite_products = get_posts($args);
        foreach ($elite_products as $product) {
            if (!has_term($term_id, 'product_cat', $product->ID)) {
                wp_set_object_terms($product->ID, $term_id, 'product_cat', true);
            }
        }
    }
}
add_action( 'delete_term', 'remove_products_from_deleted_elite_category', 10, 3 );
function remove_products_from_deleted_elite_category( $term_id, $tt_id, $taxonomy ) {
    if ('product_cat' === $taxonomy) {
        $term = get_term($term_id, 'product_cat');
        if ($term && $term->name === 'Elite') {
            $products = get_objects_in_term($term_id, 'product_cat');
            foreach ($products as $product_id) {
                wp_remove_object_terms($product_id, $term_id, 'product_cat');
            }
        }
    }
}
add_filter( 'the_title', 'add_elite_mark_to_title', 10, 2 );
function add_elite_mark_to_title( $title, $id ) {
    if ( ! is_admin() ) {
        $is_elite_product = get_post_meta( $id, '_is_elite_product', true );
        if ( $is_elite_product === 'yes' ) {
            $image_url = 'https://static.vecteezy.com/system/resources/thumbnails/008/550/097/small/boy-illustration-sticker-free-png.png'; // Example image URL
            $image_target_url = 'https://noorexpress.shop/elite/';
            $product_permalink = get_permalink( $id );
            $title = '<a href="' . $image_target_url . '"><img src="' . $image_url . '" alt="Elite Product" class="elite-image" /></a><a href="' . $product_permalink . '">' . $title . '</a>';
        }
    }
    return $title;
}
