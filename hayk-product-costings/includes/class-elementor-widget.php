<?php
/**
 * Register the front-end Elementor widgets once Elementor is ready.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function hpc_register_elementor_widgets( $widgets_manager ) {
    require_once __DIR__ . '/widget-materials-table.php';
    require_once __DIR__ . '/widget-cost-metrics.php';
    $widgets_manager->register( new \HPC_Widget_Materials_Table() );
    $widgets_manager->register( new \HPC_Widget_Cost_Metrics() );
}
add_action( 'elementor/widgets/register', 'hpc_register_elementor_widgets' );
