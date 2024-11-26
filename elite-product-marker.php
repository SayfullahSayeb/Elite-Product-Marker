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

// Add a menu item for the settings page
add_action('admin_menu', 'elite_product_marker_menu');
function elite_product_marker_menu() {
    add_menu_page('Elite Product Settings', 'Elite Product Settings', 'manage_options', 'elite-product-settings', 'elite_product_marker_settings_page');
}

// Display the settings page
function elite_product_marker_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            // Output nonce, action, and settings fields
            settings_fields('elite_product_marker_options_group');
            do_settings_sections('elite-product-settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php _e('Elite Product Image URL', 'woocommerce'); ?></th>
                    <td><input type="text" name="elite_product_image_url" value="<?php echo esc_attr( get_option('elite_product_image_url') ); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php _e('Elite Product Target URL', 'woocommerce'); ?></th>
                    <td><input type="text" name="elite_product_target_url" value="<?php echo esc_attr( get_option('elite_product_target_url') ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// Register the settings
add_action('admin_init', 'elite_product_marker_settings_init');
function elite_product_marker_settings_init() {
    register_setting('elite_product_marker_options_group', 'elite_product_image_url');
    register_setting('elite_product_marker_options_group', 'elite_product_target_url');
}


add_filter('the_title', 'add_elite_mark_to_title', 10, 2);
function add_elite_mark_to_title($title, $id) {
    if (!is_admin()) {
        $is_elite_product = get_post_meta($id, '_is_elite_product', true);
        if ($is_elite_product === 'yes') {
            $image_url = get_option('elite_product_image_url', 'default-image-url'); // Get URL from settings
            $image_target_url = get_option('elite_product_target_url', 'default-target-url'); // Get target URL from settings
            $product_permalink = get_permalink($id);
            $title = '<a href="' . esc_url($image_target_url) . '"><img src="' . esc_url($image_url) . '" alt="Elite Product" class="elite-image" /></a><a href="' . esc_url($product_permalink) . '">' . $title . '</a>';
        }
    }
    return $title;
}
