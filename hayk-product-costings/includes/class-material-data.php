<?php
/**
 * Central accessor for Materials CPT meta data.
 *
 * The Materials CPT stores "bulk pricing" as one or more quantity breaks.
 * Each break is a purchase quantity (the MOQ, in the material's own unit —
 * e.g. piece/s, m², pair/s) and the total cost to buy that quantity (the
 * "Cost per MOQ"). The per-unit rate for a break is cost ÷ qty.
 *
 * All reads of material fields go through here so the meta-key handling
 * lives in one place.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HPC_Material_Data {

    const META_TIERS = '_hpc_price_tiers';
    const META_UNIT  = '_hpc_unit';

    /**
     * The purchase unit for a material (piece/s, m², pair/s …).
     *
     * @param int $post_id Material post ID.
     * @return string Defaults to 'piece/s' when not set.
     */
    public static function get_unit( $post_id ) {
        $unit = get_post_meta( $post_id, self::META_UNIT, true );
        $unit = is_string( $unit ) ? trim( $unit ) : '';
        return '' !== $unit ? $unit : 'piece/s';
    }

    /**
     * Raw bulk pricing tiers exactly as entered (for the editor).
     *
     * Each tier: array( 'qty' => float, 'cost' => float ).
     * Rows with a non-positive qty or cost are dropped.
     *
     * @param int $post_id Material post ID.
     * @return array[]
     */
    public static function get_price_tiers_raw( $post_id ) {
        $rows = get_post_meta( $post_id, self::META_TIERS, true );
        if ( ! is_array( $rows ) ) {
            return array();
        }

        $clean = array();
        foreach ( $rows as $row ) {
            $qty  = isset( $row['qty'] ) ? floatval( $row['qty'] ) : 0;
            $cost = isset( $row['cost'] ) ? floatval( $row['cost'] ) : 0;
            if ( $qty > 0 && $cost > 0 ) {
                $clean[] = array( 'qty' => $qty, 'cost' => $cost );
            }
        }
        return $clean;
    }

    /**
     * Bulk pricing tiers sorted ascending by quantity (smallest MOQ first).
     *
     * @param int $post_id Material post ID.
     * @return array<int,array{qty:float,cost:float}>
     */
    public static function get_price_tiers( $post_id ) {
        $tiers = self::get_price_tiers_raw( $post_id );
        usort( $tiers, function ( $a, $b ) {
            if ( $a['qty'] == $b['qty'] ) {
                return 0;
            }
            return ( $a['qty'] < $b['qty'] ) ? -1 : 1;
        } );
        return $tiers;
    }

    /**
     * The smallest quantity break — the effective minimum order quantity.
     *
     * @param int $post_id Material post ID.
     * @return float|null Null when the material has no bulk pricing.
     */
    public static function get_effective_moq( $post_id ) {
        $tiers = self::get_price_tiers( $post_id );
        return ! empty( $tiers ) ? $tiers[0]['qty'] : null;
    }

    /**
     * The "Cost per MOQ" — the total cost of the smallest quantity break.
     *
     * @param int $post_id Material post ID.
     * @return float|null Null when the material has no bulk pricing.
     */
    public static function get_base_cost( $post_id ) {
        $tiers = self::get_price_tiers( $post_id );
        return ! empty( $tiers ) ? $tiers[0]['cost'] : null;
    }

    /**
     * The base per-unit rate: Cost per MOQ ÷ MOQ of the smallest break.
     *
     * @param int $post_id Material post ID.
     * @return float|null Null when the material has no bulk pricing.
     */
    public static function get_base_rate( $post_id ) {
        $tiers = self::get_price_tiers( $post_id );
        if ( empty( $tiers ) || $tiers[0]['qty'] <= 0 ) {
            return null;
        }
        return $tiers[0]['cost'] / $tiers[0]['qty'];
    }

    /**
     * The bulk pricing tier that applies when purchasing $qty_needed units.
     *
     * Picks the largest quantity break whose MOQ is at or below the quantity
     * needed (the best price break actually reached). When the need is below
     * the smallest break — you must still buy at least the MOQ — the smallest
     * break applies. This is the shoe equivalent of the cosmetic plugin's
     * quantity-break rate selection; with a single tier it always returns
     * that tier.
     *
     * @param int   $post_id     Material post ID.
     * @param float $qty_needed  Units required (units × pairs).
     * @return array{qty:float,cost:float,rate:float}|null
     */
    public static function get_applicable_tier( $post_id, $qty_needed ) {
        $tiers = self::get_price_tiers( $post_id );
        if ( empty( $tiers ) ) {
            return null;
        }

        $chosen = $tiers[0]; // Smallest MOQ (the floor).
        foreach ( $tiers as $tier ) {
            if ( $tier['qty'] <= $qty_needed + 1e-9 ) {
                $chosen = $tier;
            }
        }

        return array(
            'qty'  => $chosen['qty'],
            'cost' => $chosen['cost'],
            'rate' => $chosen['qty'] > 0 ? $chosen['cost'] / $chosen['qty'] : 0,
        );
    }

    /**
     * The material's featured image, at the given size.
     *
     * @param int    $post_id Material post ID.
     * @param string $size    Image size (default 'thumbnail').
     * @return string URL or '' when none.
     */
    public static function get_image_url( $post_id, $size = 'thumbnail' ) {
        $url = get_the_post_thumbnail_url( $post_id, $size );
        return $url ? $url : '';
    }

    /**
     * Products whose materials table uses a given material.
     *
     * @param int $material_id Material post ID.
     * @return array[] Array of array( 'product_id', 'qty_per_pair' ).
     */
    public static function get_products_using( $material_id ) {
        global $wpdb;

        // _hpc_material_rows is a serialized array; match the serialized int fragment.
        $fragment = 's:11:"material_id";i:' . absint( $material_id ) . ';';
        $like     = '%' . $wpdb->esc_like( $fragment ) . '%';

        $post_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_hpc_material_rows'
               AND pm.meta_value LIKE %s
               AND p.post_status NOT IN ( 'trash', 'auto-draft' )",
            $like
        ) );

        $usages = array();
        foreach ( $post_ids as $product_id ) {
            $rows = get_post_meta( $product_id, '_hpc_material_rows', true );
            if ( ! is_array( $rows ) ) {
                continue;
            }
            foreach ( $rows as $row ) {
                if ( absint( $row['material_id'] ?? 0 ) !== absint( $material_id ) ) {
                    continue;
                }
                $usages[] = array(
                    'product_id'   => (int) $product_id,
                    'qty_per_pair' => floatval( $row['qty_per_pair'] ?? 0 ),
                );
            }
        }
        return $usages;
    }
}
