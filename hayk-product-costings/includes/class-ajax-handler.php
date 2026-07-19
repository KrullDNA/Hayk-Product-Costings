<?php
/**
 * AJAX handlers for the product Materials repeater:
 * - Searching Materials (autocomplete)
 * - Fetching a Material's bulk pricing (unit, tiers, base MOQ + cost, image)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HPC_Ajax_Handler {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_hpc_search_materials', array( $this, 'search_materials' ) );
        add_action( 'wp_ajax_hpc_get_material_meta', array( $this, 'get_material_meta' ) );
    }

    /**
     * Search the Materials CPT for the autocomplete dropdown.
     */
    public function search_materials() {
        check_ajax_referer( 'hpc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

        global $wpdb;

        $args = array(
            'post_type'      => HPC_MATERIALS_CPT,
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( $search ) {
            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $args['hpc_title_like'] = $like;
            add_filter( 'posts_where', array( $this, 'filter_title_only' ), 10, 2 );
        }

        $query = new WP_Query( $args );
        remove_filter( 'posts_where', array( $this, 'filter_title_only' ), 10 );

        $results = array();
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $results[] = array(
                    'id'   => get_the_ID(),
                    'text' => get_the_title(),
                );
            }
            wp_reset_postdata();
        }

        wp_send_json_success( $results );
    }

    /**
     * Filter WP_Query WHERE clause to only search post_title.
     */
    public function filter_title_only( $where, $query ) {
        global $wpdb;
        $like = $query->get( 'hpc_title_like' );
        if ( $like ) {
            $where .= $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $like );
        }
        return $where;
    }

    /**
     * Return the bulk pricing data for a Material post.
     */
    public function get_material_meta() {
        check_ajax_referer( 'hpc_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;

        if ( ! $post_id || HPC_MATERIALS_CPT !== get_post_type( $post_id ) ) {
            wp_send_json_error( 'Invalid material.' );
        }

        $base_moq  = HPC_Material_Data::get_effective_moq( $post_id );
        $base_cost = HPC_Material_Data::get_base_cost( $post_id );

        wp_send_json_success( array(
            'title'        => get_the_title( $post_id ),
            'unit'         => HPC_Material_Data::get_unit( $post_id ),
            'wastage'      => HPC_Material_Data::get_wastage( $post_id ),
            'tiers'        => HPC_Material_Data::get_price_tiers( $post_id ),
            'base_moq'     => ( null !== $base_moq ) ? $base_moq : '',
            'base_cost'    => ( null !== $base_cost ) ? $base_cost : '',
            'image'        => HPC_Material_Data::get_image_url( $post_id ),
        ) );
    }
}
