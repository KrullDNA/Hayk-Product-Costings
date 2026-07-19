<?php
/**
 * Central accessor for Materials CPT meta data.
 *
 * The Materials CPT stores "bulk pricing" as one or more quantity breaks.
 * Each break is a purchase quantity (the MOQ, in the material's own unit —
 * e.g. skins, pairs, pieces, packs, m²) and the total cost to buy that
 * quantity (the "Cost per MOQ"). The per-unit rate for a break is cost ÷ qty.
 *
 * Leather-style materials can also carry a wastage % (a cutting/consumption
 * allowance for irregular skins) and, per break, an "apply margin" flag that
 * upflifts that break's cost by the global leather margin %.
 *
 * All reads of material fields go through here so the meta-key handling
 * lives in one place.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HPC_Material_Data {

    const META_TIERS   = '_hpc_price_tiers';
    const META_UNIT    = '_hpc_unit';
    const META_WASTAGE = '_hpc_wastage_pct';

    /**
     * The built-in default purchase-unit definitions.
     *
     * Each definition has a singular and plural label and a "units per"
     * count — how many individual units make up one of this purchase unit
     * (e.g. a "pair" is 2 units). units_per is informational (shown next to
     * quantities); it does not alter the money maths, since MOQ, Cost per MOQ
     * and Qty per pair are all expressed in the same chosen unit.
     *
     * @return array<int,array{singular:string,plural:string,units_per:float}>
     */
    public static function default_unit_defs() {
        return array(
            array( 'singular' => 'skin',   'plural' => 'skins',   'units_per' => 1 ),
            array( 'singular' => 'pair',   'plural' => 'pairs',   'units_per' => 2 ),
            array( 'singular' => 'piece',  'plural' => 'pieces',  'units_per' => 1 ),
            array( 'singular' => 'pack',   'plural' => 'packs',   'units_per' => 1 ),
            array( 'singular' => 'm²',     'plural' => 'm²',      'units_per' => 1 ),
            array( 'singular' => 'sq ft',  'plural' => 'sq ft',   'units_per' => 1 ),
            array( 'singular' => 'metre',  'plural' => 'metres',  'units_per' => 1 ),
            array( 'singular' => 'roll',   'plural' => 'rolls',   'units_per' => 1 ),
            array( 'singular' => 'sheet',  'plural' => 'sheets',  'units_per' => 1 ),
            array( 'singular' => 'kg',     'plural' => 'kg',      'units_per' => 1 ),
            array( 'singular' => 'litre',  'plural' => 'litres',  'units_per' => 1 ),
        );
    }

    /**
     * All purchase-unit definitions, read from Costings Settings with a
     * fallback to the defaults. Filterable via 'hpc_unit_defs'.
     *
     * @return array<int,array{singular:string,plural:string,units_per:float}>
     */
    public static function unit_defs() {
        $saved = get_option( 'hpc_units', null );
        $defs  = array();

        if ( is_array( $saved ) && ! empty( $saved ) ) {
            foreach ( $saved as $row ) {
                if ( is_array( $row ) ) {
                    $plural = isset( $row['plural'] ) ? trim( $row['plural'] ) : '';
                    if ( '' === $plural ) {
                        continue;
                    }
                    $defs[] = array(
                        'singular'  => isset( $row['singular'] ) && '' !== trim( $row['singular'] ) ? trim( $row['singular'] ) : $plural,
                        'plural'    => $plural,
                        'units_per' => isset( $row['units_per'] ) ? max( 1, floatval( $row['units_per'] ) ) : 1,
                    );
                } elseif ( is_string( $row ) && '' !== trim( $row ) ) {
                    // Backward-compat: a plain string list.
                    $defs[] = array( 'singular' => trim( $row ), 'plural' => trim( $row ), 'units_per' => 1 );
                }
            }
        }

        if ( empty( $defs ) ) {
            $defs = self::default_unit_defs();
        }

        return apply_filters( 'hpc_unit_defs', $defs );
    }

    /**
     * The plural unit labels, for the bulk pricing dropdown.
     *
     * @return string[]
     */
    public static function unit_options() {
        return array_map( function ( $d ) { return $d['plural']; }, self::unit_defs() );
    }

    /**
     * Look up a unit definition by its stored (plural) label, matching the
     * singular form too. Returns a sensible default when unknown.
     *
     * @param string $unit Stored unit label.
     * @return array{singular:string,plural:string,units_per:float}
     */
    public static function unit_info( $unit ) {
        $unit = trim( (string) $unit );
        foreach ( self::unit_defs() as $d ) {
            if ( strcasecmp( $d['plural'], $unit ) === 0 || strcasecmp( $d['singular'], $unit ) === 0 ) {
                return $d;
            }
        }
        return array( 'singular' => $unit, 'plural' => $unit, 'units_per' => 1 );
    }

    /**
     * Format a quantity with its unit, choosing singular/plural by amount and
     * optionally appending the equivalent number of individual units.
     *
     * @param float  $qty        The quantity.
     * @param string $unit       Stored unit label.
     * @param bool   $show_units Append "(N units)" when units_per > 1.
     * @return string e.g. "1 pair (2 units)", "5 skins".
     */
    public static function format_qty_unit( $qty, $unit, $show_units = true ) {
        $qty  = floatval( $qty );
        $info = self::unit_info( $unit );
        $label = ( abs( $qty - 1 ) < 1e-9 ) ? $info['singular'] : $info['plural'];

        $num = ( floor( $qty ) == $qty ) ? number_format( $qty, 0 ) : rtrim( rtrim( number_format( $qty, 4 ), '0' ), '.' );
        $out = $num . ' ' . $label;

        if ( $show_units && $info['units_per'] > 1 && $qty > 0 ) {
            $total = $qty * $info['units_per'];
            $tnum  = ( floor( $total ) == $total ) ? number_format( $total, 0 ) : rtrim( rtrim( number_format( $total, 4 ), '0' ), '.' );
            $out  .= ' (' . $tnum . ' units)';
        }
        return $out;
    }

    /**
     * The purchase unit for a material (skins, pairs, pieces, packs …).
     *
     * @param int $post_id Material post ID.
     * @return string Defaults to 'pieces' when not set.
     */
    public static function get_unit( $post_id ) {
        $unit = get_post_meta( $post_id, self::META_UNIT, true );
        $unit = is_string( $unit ) ? trim( $unit ) : '';
        return '' !== $unit ? $unit : 'pieces';
    }

    /**
     * The wastage / cutting-allowance percentage for a material.
     *
     * @param int $post_id Material post ID.
     * @return float 0 when not set.
     */
    public static function get_wastage( $post_id ) {
        $w = get_post_meta( $post_id, self::META_WASTAGE, true );
        return ( '' !== $w && null !== $w ) ? max( 0, floatval( $w ) ) : 0;
    }

    /**
     * Raw bulk pricing tiers exactly as entered (for the editor).
     *
     * Each tier: array( 'qty' => float, 'cost' => float, 'apply_margin' => bool ).
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
                $clean[] = array(
                    'qty'          => $qty,
                    'cost'         => $cost,
                    'apply_margin' => ! empty( $row['apply_margin'] ),
                );
            }
        }
        return $clean;
    }

    /**
     * Bulk pricing tiers sorted ascending by quantity (smallest MOQ first).
     *
     * @param int $post_id Material post ID.
     * @return array<int,array{qty:float,cost:float,apply_margin:bool}>
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
     * break applies. With a single tier it always returns that tier.
     *
     * @param int   $post_id     Material post ID.
     * @param float $qty_needed  Units required (units × pairs, incl. wastage).
     * @return array{qty:float,cost:float,rate:float,apply_margin:bool}|null
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
            'qty'          => $chosen['qty'],
            'cost'         => $chosen['cost'],
            'rate'         => $chosen['qty'] > 0 ? $chosen['cost'] / $chosen['qty'] : 0,
            'apply_margin' => ! empty( $chosen['apply_margin'] ),
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
