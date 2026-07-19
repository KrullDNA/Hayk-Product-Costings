<?php
/**
 * Metaboxes on the Materials CPT:
 * - Bulk Pricing: purchase unit + quantity-break table (MOQ + Cost per MOQ).
 * - Where Used: reverse lookup of products using this material.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HPC_Material_Fields {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
        add_action( 'save_post_' . HPC_MATERIALS_CPT, array( $this, 'save_meta' ), 10, 2 );
    }

    public function register_metaboxes() {
        add_meta_box(
            'hpc_material_pricing',
            __( 'Bulk Pricing', 'hayk-product-costings' ),
            array( $this, 'render_pricing_metabox' ),
            HPC_MATERIALS_CPT,
            'normal',
            'high'
        );

        add_meta_box(
            'hpc_material_where_used',
            __( 'Where Used', 'hayk-product-costings' ),
            array( $this, 'render_where_used_metabox' ),
            HPC_MATERIALS_CPT,
            'normal',
            'default'
        );
    }

    /* ───────────────────────────────────────────────
     * Bulk Pricing (quantity breaks)
     * ─────────────────────────────────────────────── */

    public function render_pricing_metabox( $post ) {
        wp_nonce_field( 'hpc_save_material_fields', 'hpc_material_fields_nonce' );

        $tiers    = HPC_Material_Data::get_price_tiers_raw( $post->ID );
        $unit     = HPC_Material_Data::get_unit( $post->ID );
        $wastage  = get_post_meta( $post->ID, HPC_Material_Data::META_WASTAGE, true );
        $currency = HPC_Settings::currency();
        $margin   = HPC_Settings::leather_margin_pct();
        $units    = HPC_Material_Data::unit_options();
        ?>
        <p class="description">
            <?php esc_html_e( 'Supplier pricing for this material. Choose the purchase Unit (how the material is bought and measured — skins, pairs, pieces, packs, m², etc.) then add one row per quantity break. Each row is a Minimum Order Quantity (MOQ) and the total Cost to buy that quantity. The per-unit rate (Cost ÷ MOQ) is shown live and is what the product Materials table uses to work out cost per pair. Add more rows for volume discounts — the smallest MOQ is the base rate; a larger row is used automatically once a production run needs at least that many units.', 'hayk-product-costings' ); ?>
        </p>
        <p>
            <label>
                <strong><?php esc_html_e( 'Purchase Unit', 'hayk-product-costings' ); ?></strong><br>
                <select id="hpc-unit" name="hpc_unit" style="width:160px;">
                    <?php
                    $matched = false;
                    foreach ( $units as $u ) {
                        $sel = selected( $unit, $u, false );
                        if ( '' !== $sel ) {
                            $matched = true;
                        }
                        echo '<option value="' . esc_attr( $u ) . '" ' . $sel . '>' . esc_html( $u ) . '</option>';
                    }
                    // Preserve a previously saved unit no longer in the managed list.
                    if ( ! $matched && '' !== $unit ) {
                        echo '<option value="' . esc_attr( $unit ) . '" selected>' . esc_html( $unit ) . '</option>';
                    }
                    ?>
                </select>
            </label>
            <span class="description"><?php esc_html_e( 'Shown next to MOQ and Qty per pair on the product Materials table. Manage the list under Products → Costings Settings.', 'hayk-product-costings' ); ?></span>
        </p>
        <p>
            <label>
                <strong><?php esc_html_e( 'Wastage %', 'hayk-product-costings' ); ?></strong>
                <input type="number" step="any" min="0" id="hpc-wastage" name="hpc_wastage_pct" value="<?php echo esc_attr( $wastage ); ?>" style="width:90px;" placeholder="0">%
            </label>
            <span class="description"><?php esc_html_e( 'Cutting / consumption allowance for irregular skins (e.g. 20 for 20%). The cost per pair is worked out on the net Qty per pair × (1 + wastage %). Leave 0 for non-leather materials.', 'hayk-product-costings' ); ?></span>
        </p>

        <table class="widefat striped" id="hpc-price-tier-table" style="max-width:760px;">
            <thead>
                <tr>
                    <th style="width:24px;">&nbsp;</th>
                    <th style="width:140px;"><?php esc_html_e( 'MOQ (qty)', 'hayk-product-costings' ); ?></th>
                    <th style="width:140px;"><?php esc_html_e( 'Cost per MOQ', 'hayk-product-costings' ); ?></th>
                    <th style="width:90px;" title="<?php echo esc_attr( sprintf( __( 'Uplift this row by the global Leather Margin (%s%%)', 'hayk-product-costings' ), $margin ) ); ?>"><?php esc_html_e( 'Apply margin', 'hayk-product-costings' ); ?></th>
                    <th style="width:180px;"><?php esc_html_e( '≈ Cost per unit', 'hayk-product-costings' ); ?></th>
                    <th style="width:40px;">&nbsp;</th>
                </tr>
            </thead>
            <tbody id="hpc-price-tier-body">
                <?php
                if ( empty( $tiers ) ) {
                    $tiers = array( array( 'qty' => '', 'cost' => '', 'apply_margin' => false ) );
                }
                foreach ( $tiers as $i => $tier ) :
                    ?>
                    <tr>
                        <td class="hpc-tier-drag" title="<?php esc_attr_e( 'Drag to reorder', 'hayk-product-costings' ); ?>" style="cursor:move;text-align:center;color:#888;">&#9776;</td>
                        <td><input type="number" step="any" min="0" name="hpc_price_tiers[<?php echo (int) $i; ?>][qty]" value="<?php echo esc_attr( $tier['qty'] ); ?>" class="widefat hpc-tier-qty" placeholder="1000"></td>
                        <td><input type="number" step="any" min="0" name="hpc_price_tiers[<?php echo (int) $i; ?>][cost]" value="<?php echo esc_attr( $tier['cost'] ); ?>" class="widefat hpc-tier-cost" placeholder="2450"></td>
                        <td style="text-align:center;"><input type="checkbox" name="hpc_price_tiers[<?php echo (int) $i; ?>][apply_margin]" value="1" class="hpc-tier-margin" <?php checked( ! empty( $tier['apply_margin'] ) ); ?>></td>
                        <td class="hpc-tier-rate">&mdash;</td>
                        <td><button type="button" class="button hpc-tier-remove">&times;</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p><button type="button" class="button" id="hpc-tier-add"><?php esc_html_e( '+ Add Price Break', 'hayk-product-costings' ); ?></button></p>

        <script>
        jQuery(function ($) {
            var currency = <?php echo wp_json_encode( $currency ); ?>;
            var margin   = <?php echo wp_json_encode( $margin ); ?>;

            function rowMarkup(idx) {
                return '<tr>' +
                    '<td class="hpc-tier-drag" title="<?php echo esc_js( __( 'Drag to reorder', 'hayk-product-costings' ) ); ?>" style="cursor:move;text-align:center;color:#888;">&#9776;</td>' +
                    '<td><input type="number" step="any" min="0" name="hpc_price_tiers[' + idx + '][qty]" class="widefat hpc-tier-qty" placeholder="1000"></td>' +
                    '<td><input type="number" step="any" min="0" name="hpc_price_tiers[' + idx + '][cost]" class="widefat hpc-tier-cost" placeholder="2450"></td>' +
                    '<td style="text-align:center;"><input type="checkbox" name="hpc_price_tiers[' + idx + '][apply_margin]" value="1" class="hpc-tier-margin"></td>' +
                    '<td class="hpc-tier-rate">&mdash;</td>' +
                    '<td><button type="button" class="button hpc-tier-remove">&times;</button></td>' +
                    '</tr>';
            }

            function refresh() {
                var unit = $('#hpc-unit').val() || '';
                var perUnit = unit ? unit.replace(/s$/, '') : '';
                $('#hpc-price-tier-body tr').each(function () {
                    var qty  = parseFloat($(this).find('.hpc-tier-qty').val()) || 0;
                    var cost = parseFloat($(this).find('.hpc-tier-cost').val()) || 0;
                    var mrg  = $(this).find('.hpc-tier-margin').is(':checked');
                    var $cell = $(this).find('.hpc-tier-rate');
                    if (qty > 0 && cost > 0) {
                        var rate = cost / qty;
                        var txt = currency + rate.toFixed(4) + (perUnit ? ' / ' + perUnit : '');
                        if (mrg && margin > 0) {
                            txt += ' → ' + currency + (rate * (1 + margin / 100)).toFixed(4) + ' (+' + margin + '%)';
                        }
                        $cell.text(txt);
                    } else {
                        $cell.text('—');
                    }
                });
            }

            $('#hpc-tier-add').on('click', function () {
                $('#hpc-price-tier-body').append(rowMarkup($('#hpc-price-tier-body tr').length));
                refresh();
            });
            $('#hpc-price-tier-table').on('click', '.hpc-tier-remove', function () {
                $(this).closest('tr').remove();
                refresh();
            });
            $('#hpc-price-tier-table').on('input change', '.hpc-tier-qty, .hpc-tier-cost, .hpc-tier-margin', refresh);
            $('#hpc-unit').on('input change', refresh);

            if ($.fn.sortable) {
                $('#hpc-price-tier-body').sortable({ handle: '.hpc-tier-drag', axis: 'y', opacity: 0.7 });
            }

            refresh();
        });
        </script>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Where Used
     * ─────────────────────────────────────────────── */

    public function render_where_used_metabox( $post ) {
        $usages = HPC_Material_Data::get_products_using( $post->ID );

        if ( empty( $usages ) ) {
            echo '<p>' . esc_html__( 'This material is not used in any product yet.', 'hayk-product-costings' ) . '</p>';
            return;
        }

        $unit = HPC_Material_Data::get_unit( $post->ID );
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Product', 'hayk-product-costings' ); ?></th>
                    <th><?php esc_html_e( 'Qty per pair', 'hayk-product-costings' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $usages as $u ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( get_edit_post_link( $u['product_id'] ) ); ?>">
                                <?php echo esc_html( get_the_title( $u['product_id'] ) ); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html( HPC_Material_Data::format_qty_unit( $u['qty_per_pair'], $unit ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <?php esc_html_e( 'Use this before discontinuing or re-sourcing a material — every product listed here will need reviewing.', 'hayk-product-costings' ); ?>
        </p>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Save
     * ─────────────────────────────────────────────── */

    public function save_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! isset( $_POST['hpc_material_fields_nonce'] ) ) {
            return;
        }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hpc_material_fields_nonce'] ) ), 'hpc_save_material_fields' ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Purchase unit.
        $unit = isset( $_POST['hpc_unit'] ) ? sanitize_text_field( wp_unslash( $_POST['hpc_unit'] ) ) : '';
        if ( '' === $unit ) {
            delete_post_meta( $post_id, HPC_Material_Data::META_UNIT );
        } else {
            update_post_meta( $post_id, HPC_Material_Data::META_UNIT, $unit );
        }

        // Wastage % (cutting / consumption allowance).
        $wastage = isset( $_POST['hpc_wastage_pct'] ) ? floatval( wp_unslash( $_POST['hpc_wastage_pct'] ) ) : 0;
        if ( $wastage > 0 ) {
            update_post_meta( $post_id, HPC_Material_Data::META_WASTAGE, $wastage );
        } else {
            delete_post_meta( $post_id, HPC_Material_Data::META_WASTAGE );
        }

        // Bulk pricing tiers (MOQ qty + total cost per MOQ + margin flag).
        $raw_tiers   = isset( $_POST['hpc_price_tiers'] ) && is_array( $_POST['hpc_price_tiers'] ) ? wp_unslash( $_POST['hpc_price_tiers'] ) : array();
        $clean_tiers = array();
        foreach ( $raw_tiers as $tier ) {
            $qty  = floatval( $tier['qty'] ?? 0 );
            $cost = floatval( $tier['cost'] ?? 0 );
            if ( $qty <= 0 || $cost <= 0 ) {
                continue;
            }
            $clean_tiers[] = array(
                'qty'          => $qty,
                'cost'         => $cost,
                'apply_margin' => ! empty( $tier['apply_margin'] ),
            );
        }
        if ( empty( $clean_tiers ) ) {
            delete_post_meta( $post_id, HPC_Material_Data::META_TIERS );
        } else {
            update_post_meta( $post_id, HPC_Material_Data::META_TIERS, $clean_tiers );
        }
    }
}
