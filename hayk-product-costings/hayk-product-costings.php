<?php
/**
 * Plugin Name: Hayk Product Costings
 * Description: Shoe product costing tool. Adds a Bulk Pricing metabox to the Materials CPT and a dynamic Materials table to the Products CPT that pulls cost/MOQ data from Materials, then calculates per-pair and full production-run costs. Front-end display via Elementor widgets.
 * Version: 1.2.1
 * Author: KrullDNA
 * Text Domain: hayk-product-costings
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HPC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HPC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HPC_VERSION', '1.2.1' );

/**
 * Post type slugs. Filterable so the plugin can be pointed at differently
 * named CPTs without touching the code.
 */
if ( ! defined( 'HPC_MATERIALS_CPT' ) ) {
    define( 'HPC_MATERIALS_CPT', apply_filters( 'hpc_materials_cpt', 'materials' ) );
}
if ( ! defined( 'HPC_PRODUCTS_CPT' ) ) {
    define( 'HPC_PRODUCTS_CPT', apply_filters( 'hpc_products_cpt', 'products' ) );
}

require_once HPC_PLUGIN_DIR . 'includes/class-material-data.php';
require_once HPC_PLUGIN_DIR . 'includes/class-costing-calculator.php';
require_once HPC_PLUGIN_DIR . 'includes/class-material-fields.php';
require_once HPC_PLUGIN_DIR . 'includes/class-product-metaboxes.php';
require_once HPC_PLUGIN_DIR . 'includes/class-ajax-handler.php';
require_once HPC_PLUGIN_DIR . 'includes/class-settings.php';
require_once HPC_PLUGIN_DIR . 'includes/class-elementor-widget.php';

/**
 * Main plugin class.
 */
final class Hayk_Product_Costings {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'register_front_assets' ) );

        HPC_Material_Fields::instance();
        HPC_Product_Metaboxes::instance();
        HPC_Ajax_Handler::instance();
        HPC_Settings::instance();
    }

    /**
     * Register front-end assets (loaded on demand by the Elementor widgets).
     */
    public function register_front_assets() {
        wp_register_style( 'hpc-materials-table-front', HPC_PLUGIN_URL . 'assets/css/materials-table.css', array(), HPC_VERSION );
        wp_register_style( 'hpc-cost-metrics-front', HPC_PLUGIN_URL . 'assets/css/cost-metrics.css', array(), HPC_VERSION );
    }

    /**
     * Enqueue admin scripts and styles on the Products / Materials edit screens
     * and our settings page.
     */
    public function enqueue_admin_assets( $hook ) {
        global $post_type;

        $is_product_screen  = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && HPC_PRODUCTS_CPT === $post_type;
        $is_material_screen = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && HPC_MATERIALS_CPT === $post_type;
        $is_settings_page   = ( false !== strpos( (string) $hook, 'hpc-settings' ) );

        if ( ! $is_product_screen && ! $is_material_screen && ! $is_settings_page ) {
            return;
        }

        wp_enqueue_style( 'hpc-admin', HPC_PLUGIN_URL . 'assets/css/admin.css', array(), HPC_VERSION );

        if ( $is_material_screen || $is_settings_page ) {
            // Drag-to-reorder: Bulk Pricing tiers / Settings unit rows.
            wp_enqueue_script( 'jquery-ui-sortable' );
        }

        if ( $is_product_screen ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script( 'hpc-admin', HPC_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-util' ), HPC_VERSION, true );

            // Current product's effective cost fields (plugin override, else
            // the client's own custom fields), as a baseline for the live Cost
            // Summary when their inputs aren't being edited on the page.
            $post_id = 0;
            if ( isset( $_GET['post'] ) ) {
                $post_id = absint( $_GET['post'] );
            } elseif ( isset( $GLOBALS['post']->ID ) ) {
                $post_id = (int) $GLOBALS['post']->ID;
            }

            $fields = array( 'production_run' => 0, 'packaging_cost_per_pair' => 0, 'labour' => 0, 'facility_running_costs' => 0, 'miscellaneous_costs' => 0 );
            if ( $post_id ) {
                foreach ( array_keys( $fields ) as $f ) {
                    $fields[ $f ] = HPC_Costing_Calculator::get_field( $post_id, $f );
                }
            }

            // Unit definitions keyed by plural label, for live singular/plural
            // and "units per" display in the Materials repeater.
            $units = array();
            foreach ( HPC_Material_Data::unit_defs() as $d ) {
                $units[ $d['plural'] ] = $d;
                $units[ $d['singular'] ] = $d;
            }

            wp_localize_script( 'hpc-admin', 'hpcData', array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'hpc_nonce' ),
                'currency'     => HPC_Settings::currency(),
                'leatherMargin' => HPC_Settings::leather_margin_pct(),
                'fields'       => $fields,
                'units'        => $units,
            ) );
        }
    }
}

add_action( 'plugins_loaded', array( 'Hayk_Product_Costings', 'instance' ) );
