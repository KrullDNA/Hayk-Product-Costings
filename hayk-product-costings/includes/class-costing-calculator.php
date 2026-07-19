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
    // Plugin-managed override key (_hpc_*) is checked first; if empty, the
    // client's own custom fields are used.
    private static $product_field_map = array(
        'production_run'          => array( '_hpc_production_run', 'production_run', '_production_run', 'production-run' ),
        'packaging_cost_per_pair' => array( '_hpc_packaging_cost_per_pair', 'packaging_unit_cost', '_packaging_unit_cost', 'packaging_cost_per_pair', '_packaging_cost_per_pair', 'packaging_cost', '_packaging_cost' ),
        'labour'                  => array( '_hpc_labour', 'labour_costs', '_labour_costs', 'labour', '_labour' ),
        'facility_running_costs'  => array( '_hpc_facility_running_costs', 'facility_running_costs', '_facility_running_costs', 'facility_costs', '_facility_costs' ),
        'miscellaneous_costs'     => array( '_hpc_miscellaneous_costs', 'miscellaneous_cost', '_miscellaneous_cost', 'miscellaneous_costs', '_miscellaneous_costs', 'misc_costs', '_misc_costs' ),
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

        $margin_pct = HPC_Settings::leather_margin_pct();

        $lines = array();
        foreach ( $rows as $row ) {
            $material_id  = absint( $row['material_id'] ?? 0 );
            $qty_per_pair = floatval( $row['qty_per_pair'] ?? 0 );
            $type         = isset( $row['material_type'] ) ? (string) $row['material_type'] : '';

            if ( ! $material_id ) {
                continue;
            }

            $unit         = HPC_Material_Data::get_unit( $material_id );
            $wastage      = HPC_Material_Data::get_wastage( $material_id );
            $m2_factor    = HPC_Material_Data::unit_m2_factor( $unit ); // >0 => area-priced (m²/ft²)
            $area_mode    = ( $m2_factor > 0 );
            $waste_factor = 1 + ( $wastage / 100 );

            if ( $area_mode ) {
                // Supplier prices by area (m²/ft²); leather usage is entered in
                // m². Qty per pair is net m². The supplier-unit rate is
                // converted to a per-m² rate by dividing by the unit's m² factor
                // (ft² factor 0.092903 → per-m² rate = per-ft² rate × 10.7639).
                $gross_m2   = $qty_per_pair * $waste_factor;              // m² per pair incl. wastage
                $m2_run     = $gross_m2 * max( 0, $run );                 // total m² for the run
                $units_run  = ( $m2_factor > 0 ) ? $m2_run / $m2_factor : 0; // in the supplier unit (e.g. ft²)

                // Applicable tier by the quantity needed in the supplier unit.
                $tier = HPC_Material_Data::get_applicable_tier( $material_id, $units_run );

                $cost_per_moq = $tier ? $tier['cost'] : 0;
                $moq          = $tier ? $tier['qty'] : 0;
                $rate_unit    = $tier ? $tier['rate'] : 0;               // cost per supplier unit (per ft²)

                $margin_applied = ( $tier && ! empty( $tier['apply_margin'] ) && $margin_pct > 0 );
                if ( $margin_applied ) {
                    $rate_unit = $rate_unit * ( 1 + $margin_pct / 100 );
                }

                $rate_m2       = $m2_factor > 0 ? $rate_unit / $m2_factor : 0; // cost per m²
                $cost_per_pair = $gross_m2 * $rate_m2;

                $lines[] = array(
                    'material_id'    => $material_id,
                    'material_type'  => $type,
                    'title'          => get_the_title( $material_id ),
                    'area_mode'      => true,
                    'unit'           => $unit,          // supplier area unit (m²/ft²) — MOQ column
                    'qty_unit'       => 'm²',           // usage is always in m²
                    'm2_factor'      => $m2_factor,
                    'rate_m2'        => $rate_m2,
                    'wastage'        => $wastage,
                    'margin_applied' => $margin_applied,
                    'image'          => HPC_Material_Data::get_image_url( $material_id ),
                    'cost_per_moq'   => $cost_per_moq,
                    'moq'            => $moq,
                    'qty_per_pair'   => $qty_per_pair,  // net m²
                    'cost_per_pair'  => $cost_per_pair,
                    'gross_run_area' => $m2_run,        // m² needed for the run
                    'units_per_run'  => $units_run,     // area to buy in the supplier unit
                );
                continue;
            }

            // Direct mode: consumed and priced in the same purchase unit.
            $eff_per_pair = $qty_per_pair * $waste_factor;
            $qty_needed   = $eff_per_pair * max( 0, $run );
            $tier         = HPC_Material_Data::get_applicable_tier( $material_id, $qty_needed );

            $cost_per_moq = $tier ? $tier['cost'] : 0;
            $moq          = $tier ? $tier['qty'] : 0;
            $rate         = $tier ? $tier['rate'] : 0;

            $margin_applied = ( $tier && ! empty( $tier['apply_margin'] ) && $margin_pct > 0 );
            if ( $margin_applied ) {
                $rate = $rate * ( 1 + $margin_pct / 100 );
            }

            $cost_per_pair = $rate * $eff_per_pair;

            $lines[] = array(
                'material_id'    => $material_id,
                'material_type'  => $type,
                'title'          => get_the_title( $material_id ),
                'area_mode'      => false,
                'unit'           => $unit,
                'qty_unit'       => $unit,
                'm2_factor'      => 0,
                'wastage'        => $wastage,
                'margin_applied' => $margin_applied,
                'image'          => HPC_Material_Data::get_image_url( $material_id ),
                'cost_per_moq'   => $cost_per_moq,
                'moq'            => $moq,
                'qty_per_pair'   => $qty_per_pair,
                'cost_per_pair'  => $cost_per_pair,
                'units_per_run'  => 0,
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
        $misc               = self::get_field( $product_id, 'miscellaneous_costs' );

        $prod_run_material_cost = $material_cost_per_pair * $run;
        $packaging_run_total    = $packaging_per_pair * $run;
        $manufacturing_total    = $labour + $facility;
        $full_production_cost   = $prod_run_material_cost + $packaging_run_total + $manufacturing_total + $misc;
        $single_pair_cost       = $run > 0 ? $full_production_cost / $run : 0;

        return array(
            'production_run'          => $run,
            'material_cost_per_pair'  => $material_cost_per_pair,
            'prod_run_material_cost'  => $prod_run_material_cost,
            'packaging_cost_per_pair' => $packaging_per_pair,
            'packaging_run_total'     => $packaging_run_total,
            'labour'                  => $labour,
            'facility_running_costs'  => $facility,
            'miscellaneous_costs'     => $misc,
            'manufacturing_total'     => $manufacturing_total,
            'full_production_cost'    => $full_production_cost,
            'single_pair_cost'        => $single_pair_cost,
        );
    }
}
