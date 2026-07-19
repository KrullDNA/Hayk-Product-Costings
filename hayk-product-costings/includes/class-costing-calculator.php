<?php
/**
 * Shared costing calculator for shoe products.
 *
 * Single source of truth for all costing maths, used by the admin Cost
 * Summary metabox and the front-end Elementor widgets so every surface
 * reports identical figures.
 *
 * Costing model (per the client's front-end spec):
 *   For each material row:
 *     unit rate      = Cost per MOQ ÷ MOQ   (from the material's bulk pricing)
 *     cost per pair  = unit rate × Qty per pair
 *   Material cost per pair  = Σ cost per pair
 *   Prod. run material cost = Material cost per pair × Production run
 *   Packaging run total     = Packaging cost per pair × Production run
 *   Full production cost    = Prod. run material cost + Packaging run total
 *                             + Labour + Facility running costs
 *   Single pair cost        = Full production cost ÷ Production run
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HPC_Costing_Calculator {

    /**
     * Plugin-managed product meta keys, mapped to the external key patterns
     * checked as a fallback when the managed field is empty (so existing
     * JetEngine / ACF fields still feed the calculation).
     */
    private static $product_field_map = array(
        'production_run'         => array( '_hpc_production_run', 'production_run', '_production_run', 'production-run' ),
        'packaging_cost_per_pair' => array( '_hpc_packaging_cost_per_pair', 'packaging_cost_per_pair', '_packaging_cost_per_pair', 'packaging_cost', '_packaging_cost' ),
        'labour'                 => array( '_hpc_labour', 'labour', '_labour', 'labour_costs', '_labour_costs' ),
        'facility_running_costs' => array( '_hpc_facility_running_costs', 'facility_running_costs', '_facility_running_costs', 'facility_costs', '_facility_costs' ),
    );

    /**
     * Read a numeric product field, trying the plugin-managed key first, then
     * external key variants, then ACF.
     *
     * @param int    $product_id Product post ID.
     * @param string $field      Logical field name (key of $product_field_map).
     * @return float 0 when unset.
     */
    public static function get_field( $product_id, $field ) {
        if ( ! isset( self::$product_field_map[ $field ] ) ) {
            return 0;
        }
        foreach ( self::$product_field_map[ $field ] as $key ) {
            $val = get_post_meta( $product_id, $key, true );
            if ( '' !== $val && null !== $val && false !== $val ) {
                return floatval( $val );
            }
        }
        if ( function_exists( 'get_field' ) ) {
            $val = get_field( $field, $product_id );
            if ( '' !== $val && null !== $val && false !== $val ) {
                return floatval( $val );
            }
        }
        return 0;
    }

    /**
     * Compute the per-material line data for a product.
     *
     * @param int        $product_id Product post ID.
     * @param float|null $run        Production run override (null = product field).
     * @param array|null $rows       Material rows override (null = saved rows).
     * @return array[] One entry per row with all display + cost values.
     */
    public static function material_lines( $product_id, $run = null, $rows = null ) {
        if ( null === $run ) {
            $run = self::get_field( $product_id, 'production_run' );
        }
        if ( null === $rows ) {
            $rows = get_post_meta( $product_id, '_hpc_material_rows', true );
        }
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        $lines = array();
        foreach ( $rows as $row ) {
            $material_id  = absint( $row['material_id'] ?? 0 );
            $qty_per_pair = floatval( $row['qty_per_pair'] ?? 0 );
            $type         = isset( $row['material_type'] ) ? (string) $row['material_type'] : '';

            if ( ! $material_id ) {
                continue;
            }

            $unit        = HPC_Material_Data::get_unit( $material_id );
            $qty_needed  = $qty_per_pair * max( 0, $run );
            $tier        = HPC_Material_Data::get_applicable_tier( $material_id, $qty_needed );

            $cost_per_moq = $tier ? $tier['cost'] : 0;
            $moq          = $tier ? $tier['qty'] : 0;
            $rate         = $tier ? $tier['rate'] : 0;
            $cost_per_pair = $rate * $qty_per_pair;

            $lines[] = array(
                'material_id'   => $material_id,
                'material_type' => $type,
                'title'         => get_the_title( $material_id ),
                'unit'          => $unit,
                'image'         => HPC_Material_Data::get_image_url( $material_id ),
                'cost_per_moq'  => $cost_per_moq,
                'moq'           => $moq,
                'qty_per_pair'  => $qty_per_pair,
                'cost_per_pair' => $cost_per_pair,
            );
        }
        return $lines;
    }

    /**
     * Run all costing calculations for a product.
     *
     * @param int        $product_id Product post ID.
     * @param float|null $run        Production run override (null = product field).
     * @param array|null $rows       Material rows override (null = saved rows).
     * @return array Metric key => value.
     */
    public static function metrics( $product_id, $run = null, $rows = null ) {
        if ( null === $run ) {
            $run = self::get_field( $product_id, 'production_run' );
        }
        $run = max( 0, floatval( $run ) );

        $lines = self::material_lines( $product_id, $run, $rows );

        $material_cost_per_pair = 0;
        foreach ( $lines as $line ) {
            $material_cost_per_pair += $line['cost_per_pair'];
        }

        $packaging_per_pair = self::get_field( $product_id, 'packaging_cost_per_pair' );
        $labour             = self::get_field( $product_id, 'labour' );
        $facility           = self::get_field( $product_id, 'facility_running_costs' );

        $prod_run_material_cost = $material_cost_per_pair * $run;
        $packaging_run_total    = $packaging_per_pair * $run;
        $manufacturing_total    = $labour + $facility;
        $full_production_cost   = $prod_run_material_cost + $packaging_run_total + $manufacturing_total;
        $single_pair_cost       = $run > 0 ? $full_production_cost / $run : 0;

        return array(
            'production_run'          => $run,
            'material_cost_per_pair'  => $material_cost_per_pair,
            'prod_run_material_cost'  => $prod_run_material_cost,
            'packaging_cost_per_pair' => $packaging_per_pair,
            'packaging_run_total'     => $packaging_run_total,
            'labour'                  => $labour,
            'facility_running_costs'  => $facility,
            'manufacturing_total'     => $manufacturing_total,
            'full_production_cost'    => $full_production_cost,
            'single_pair_cost'        => $single_pair_cost,
        );
    }
}
