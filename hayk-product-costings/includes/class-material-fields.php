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
        $unit     = get_post_meta( $post->ID, HPC_Material_Data::META_UNIT, true );
        $unit     = is_string( $unit ) ? $unit : '';
        $currency = HPC_Settings::currency();
        $suggested = array( 'piece/s', 'm²', 'pair/s', 'metre/s', 'roll/s', 'sheet/s', 'kg', 'litre/s' );
        ?>
        <p class="description">
            <?php esc_html_e( 'Supplier pricing for this material. Set the purchase Unit (how the material is bought and measured — piece/s, m², pair/s, etc.) then add one row per quantity break. Each row is a Minimum Order Quantity (MOQ) and the total Cost to buy that quantity. The per-unit rate (Cost ÷ MOQ) is shown live and is what the product Materials table uses to work out cost per pair. Add more rows for volume discounts — the smallest MOQ is the base rate; a larger row is used automatically once a production run needs at least that many units.', 'hayk-product-costings' ); ?>
        </p>
        <p>
            <label>
                <strong><?php esc_html_e( 'Purchase Unit', 'hayk-product-costings' ); ?></strong><br>
                <input type="text" id="hpc-unit" name="hpc_unit" value="<?php echo esc_attr( $unit ); ?>" list="hpc-unit-list" style="width:160px;" placeholder="piece/s">
                <datalist id="hpc-unit-list">
                    <?php foreach ( $suggested as $s ) : ?>
                        <option value="<?php echo esc_attr( $s ); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>
            <span class="description"><?php esc_html_e( 'Shown next to MOQ and Qty per pair on the product Materials table.', 'hayk-product-costings' ); ?></span>
        </p>

        <table class="widefat striped" id="hpc-price-tier-table" style="max-width:640px;">
            <thead>
                <tr>
                    <th style="width:24px;">&nbsp;</th>
                    <th style="width:150px;"><?php esc_html_e( 'MOQ (qty)', 'hayk-product-costings' ); ?></th>
                    <th style="width:150px;"><?php esc_html_e( 'Cost per MOQ', 'hayk-product-costings' ); ?></th>
                    <th style="width:150px;"><?php esc_html_e( '≈ Cost per unit', 'hayk-product-costings' ); ?></th>
                    <th style="width:40px;">&nbsp;</th>
                </tr>
            </thead>
            <tbody id="hpc-price-tier-body">
                <?php
                if ( empty( $tiers ) ) {
                    $tiers = array( array( 'qty' => '', 'cost' => '' ) );
                }
                foreach ( $tiers as $i => $tier ) :
                    ?>
                    <tr>
                        <td class="hpc-tier-drag" title="<?php esc_attr_e( 'Drag to reorder', 'hayk-product-costings' ); ?>" style="cursor:move;text-align:center;color:#888;">&#9776;</td>
                        <td><input type="number" step="any" min="0" name="hpc_price_tiers[<?php echo (int) $i; ?>][qty]" value="<?php echo esc_attr( $tier['qty'] ); ?>" class="widefat hpc-tier-qty" placeholder="1000"></td>
                        <td><input type="number" step="any" min="0" name="hpc_price_tiers[<?php echo (int) $i; ?>][cost]" value="<?php echo esc_attr( $tier['cost'] ); ?>" class="widefat hpc-tier-cost" placeholder="2450"></td>
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

            function rowMarkup(idx) {
                return '<tr>' +
                    '<td class="hpc-tier-drag" title="<?php echo esc_js( __( 'Drag to reorder', 'hayk-product-costings' ) ); ?>" style="cursor:move;text-align:center;color:#888;">&#9776;</td>' +
                    '<td><input type="number" step="any" min="0" name="hpc_price_tiers[' + idx + '][qty]" class="widefat hpc-tier-qty" placeholder="1000"></td>' +
                    '<td><input type="number" step="any" min="0" name="hpc_price_tiers[' + idx + '][cost]" class="widefat hpc-tier-cost" placeholder="2450"></td>' +
                    '<td class="hpc-tier-rate">&mdash;</td>' +
                    '<td><button type="button" class="button hpc-tier-remove">&times;</button></td>' +
                    '</tr>';
            }

            function refresh() {
                var unit = $('#hpc-unit').val() || '';
                $('#hpc-price-tier-body tr').each(function () {
                    var qty  = parseFloat($(this).find('.hpc-tier-qty').val()) || 0;
                    var cost = parseFloat($(this).find('.hpc-tier-cost').val()) || 0;
                    var $cell = $(this).find('.hpc-tier-rate');
                    if (qty > 0 && cost > 0) {
                        $cell.text(currency + (cost / qty).toFixed(4) + (unit ? ' / ' + unit.replace(/\/s$/, '') : ''));
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
            $('#hpc-price-tier-table').on('input change', '.hpc-tier-qty, .hpc-tier-cost', refresh);
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
                        <td><?php echo esc_html( rtrim( rtrim( number_format( $u['qty_per_pair'], 4 ), '0' ), '.' ) . ' ' . $unit ); ?></td>
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

        // Bulk pricing tiers (MOQ qty + total cost per MOQ).
        $raw_tiers   = isset( $_POST['hpc_price_tiers'] ) && is_array( $_POST['hpc_price_tiers'] ) ? wp_unslash( $_POST['hpc_price_tiers'] ) : array();
        $clean_tiers = array();
        foreach ( $raw_tiers as $tier ) {
            $qty  = floatval( $tier['qty'] ?? 0 );
            $cost = floatval( $tier['cost'] ?? 0 );
            if ( $qty <= 0 || $cost <= 0 ) {
                continue;
            }
            $clean_tiers[] = array( 'qty' => $qty, 'cost' => $cost );
        }
        if ( empty( $clean_tiers ) ) {
            delete_post_meta( $post_id, HPC_Material_Data::META_TIERS );
        } else {
            update_post_meta( $post_id, HPC_Material_Data::META_TIERS, $clean_tiers );
        }
    }
}
