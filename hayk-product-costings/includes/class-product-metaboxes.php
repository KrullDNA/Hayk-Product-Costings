<?php
/**
 * Metaboxes on the Products CPT edit screen:
 * - Materials repeater (dynamically pulls cost/MOQ data from the Materials CPT)
 * - Production & Costs (production run, packaging, labour, facility)
 * - Cost Summary (live figures matching the front-end widgets)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HPC_Product_Metaboxes {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
        add_action( 'save_post_' . HPC_PRODUCTS_CPT, array( $this, 'save_meta' ), 10, 2 );
    }

    public function register_metaboxes() {
        add_meta_box(
            'hpc_materials',
            __( 'Materials', 'hayk-product-costings' ),
            array( $this, 'render_materials_metabox' ),
            HPC_PRODUCTS_CPT,
            'normal',
            'high'
        );

        add_meta_box(
            'hpc_production_costs',
            __( 'Production & Costs', 'hayk-product-costings' ),
            array( $this, 'render_production_metabox' ),
            HPC_PRODUCTS_CPT,
            'normal',
            'default'
        );

        add_meta_box(
            'hpc_cost_summary',
            __( 'Cost Summary', 'hayk-product-costings' ),
            array( $this, 'render_cost_summary_metabox' ),
            HPC_PRODUCTS_CPT,
            'normal',
            'default'
        );
    }

    /* ───────────────────────────────────────────────
     * Materials Repeater
     * ─────────────────────────────────────────────── */

    public function render_materials_metabox( $post ) {
        wp_nonce_field( 'hpc_save_materials', 'hpc_materials_nonce' );

        $rows = get_post_meta( $post->ID, '_hpc_material_rows', true );
        if ( ! is_array( $rows ) ) {
            $rows = array();
        }
        ?>
        <div id="hpc-materials-wrap">
            <table id="hpc-materials-table" class="widefat hpc-materials-table">
                <thead>
                    <tr>
                        <th class="hpc-col-sort">&nbsp;</th>
                        <th class="hpc-col-type"><?php esc_html_e( 'Material Type', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-col-material"><?php esc_html_e( 'Material', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-col-costmoq"><?php esc_html_e( 'Cost per MOQ', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-col-moq"><?php esc_html_e( 'MOQ', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-col-qty"><?php esc_html_e( 'Qty per pair', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-col-costpair"><?php esc_html_e( 'Cost per pair', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-col-actions">&nbsp;</th>
                    </tr>
                </thead>
                <tbody id="hpc-materials-body">
                    <?php
                    if ( ! empty( $rows ) ) {
                        foreach ( $rows as $i => $row ) {
                            $this->render_row( $i, $row );
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="6" class="hpc-total-label"><strong><?php esc_html_e( 'Material cost per pair:', 'hayk-product-costings' ); ?></strong></td>
                        <td id="hpc-total-costpair"><strong>&mdash;</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <p style="margin-top:12px;">
                <button type="button" id="hpc-add-row" class="button button-primary">
                    <?php esc_html_e( '+ Add Material', 'hayk-product-costings' ); ?>
                </button>
                <button type="button" id="hpc-refresh-meta" class="button">
                    <?php esc_html_e( '↻ Refresh Material Data', 'hayk-product-costings' ); ?>
                </button>
                <span id="hpc-refresh-status"></span>
            </p>
            <p class="description">
                <?php esc_html_e( 'Material Type is a free label for how the material is used in this product (e.g. Leather, Lining, Sole). Cost per MOQ and MOQ are pulled live from the selected material\'s Bulk Pricing; enter the Qty per pair to get the cost per pair.', 'hayk-product-costings' ); ?>
            </p>
        </div>

        <!-- Row template (hidden) -->
        <script type="text/html" id="tmpl-hpc-row">
            <tr class="hpc-row" data-index="{{data.i}}" data-unit="" data-tiers="[]">
                <td class="hpc-col-sort hpc-drag-handle">&#9776;</td>
                <td class="hpc-col-type">
                    <input type="text" name="hpc_rows[{{data.i}}][material_type]" value="" class="hpc-field-type" placeholder="<?php esc_attr_e( 'e.g. Leather', 'hayk-product-costings' ); ?>">
                </td>
                <td class="hpc-col-material">
                    <select name="hpc_rows[{{data.i}}][material_id]" class="hpc-field-material">
                        <option value=""><?php esc_html_e( '— Select —', 'hayk-product-costings' ); ?></option>
                    </select>
                </td>
                <td class="hpc-col-costmoq">
                    <input type="text" name="hpc_rows[{{data.i}}][cost_per_moq]" value="" class="hpc-field-costmoq" readonly>
                </td>
                <td class="hpc-col-moq">
                    <input type="text" name="hpc_rows[{{data.i}}][moq]" value="" class="hpc-field-moq" readonly>
                </td>
                <td class="hpc-col-qty">
                    <input type="number" step="any" min="0" name="hpc_rows[{{data.i}}][qty_per_pair]" value="" class="hpc-field-qty" placeholder="0.00">
                </td>
                <td class="hpc-col-costpair hpc-cell-costpair">&mdash;</td>
                <td class="hpc-col-actions">
                    <button type="button" class="button hpc-duplicate-row" title="<?php esc_attr_e( 'Duplicate', 'hayk-product-costings' ); ?>">&#x2398;</button>
                    <button type="button" class="button hpc-remove-row" title="<?php esc_attr_e( 'Remove', 'hayk-product-costings' ); ?>">&#x1F5D1;</button>
                </td>
            </tr>
        </script>
        <?php
    }

    /**
     * Render a single saved repeater row (with live-pulled material data).
     */
    private function render_row( $i, $row ) {
        $type        = isset( $row['material_type'] ) ? $row['material_type'] : '';
        $material_id = isset( $row['material_id'] ) ? (int) $row['material_id'] : 0;
        $qty         = isset( $row['qty_per_pair'] ) ? $row['qty_per_pair'] : '';

        $unit         = '';
        $tiers        = array();
        $cost_per_moq = '';
        $moq          = '';
        if ( $material_id ) {
            $unit         = HPC_Material_Data::get_unit( $material_id );
            $tiers        = HPC_Material_Data::get_price_tiers( $material_id );
            $base_moq     = HPC_Material_Data::get_effective_moq( $material_id );
            $base_cost    = HPC_Material_Data::get_base_cost( $material_id );
            $moq          = ( null !== $base_moq ) ? $base_moq : '';
            $cost_per_moq = ( null !== $base_cost ) ? $base_cost : '';
        }

        $currency    = HPC_Settings::currency();
        $moq_display = ( '' !== $moq ) ? self::fmt_qty( $moq ) . ' ' . $unit : '';
        $cost_display = ( '' !== $cost_per_moq ) ? $currency . number_format( floatval( $cost_per_moq ), 2 ) : '';
        ?>
        <tr class="hpc-row" data-index="<?php echo (int) $i; ?>" data-unit="<?php echo esc_attr( $unit ); ?>" data-tiers="<?php echo esc_attr( wp_json_encode( $tiers ) ); ?>">
            <td class="hpc-col-sort hpc-drag-handle">&#9776;</td>
            <td class="hpc-col-type">
                <input type="text" name="hpc_rows[<?php echo (int) $i; ?>][material_type]" value="<?php echo esc_attr( $type ); ?>" class="hpc-field-type" placeholder="<?php esc_attr_e( 'e.g. Leather', 'hayk-product-costings' ); ?>">
            </td>
            <td class="hpc-col-material">
                <select name="hpc_rows[<?php echo (int) $i; ?>][material_id]" class="hpc-field-material">
                    <option value=""><?php esc_html_e( '— Select —', 'hayk-product-costings' ); ?></option>
                    <?php if ( $material_id ) : ?>
                        <option value="<?php echo $material_id; ?>" selected><?php echo esc_html( get_the_title( $material_id ) ); ?></option>
                    <?php endif; ?>
                </select>
            </td>
            <td class="hpc-col-costmoq">
                <input type="text" name="hpc_rows[<?php echo (int) $i; ?>][cost_per_moq]" value="<?php echo esc_attr( $cost_display ); ?>" class="hpc-field-costmoq" readonly>
            </td>
            <td class="hpc-col-moq">
                <input type="text" name="hpc_rows[<?php echo (int) $i; ?>][moq]" value="<?php echo esc_attr( $moq_display ); ?>" class="hpc-field-moq" readonly>
            </td>
            <td class="hpc-col-qty">
                <input type="number" step="any" min="0" name="hpc_rows[<?php echo (int) $i; ?>][qty_per_pair]" value="<?php echo esc_attr( $qty ); ?>" class="hpc-field-qty" placeholder="0.00">
            </td>
            <td class="hpc-col-costpair hpc-cell-costpair">&mdash;</td>
            <td class="hpc-col-actions">
                <button type="button" class="button hpc-duplicate-row" title="<?php esc_attr_e( 'Duplicate', 'hayk-product-costings' ); ?>">&#x2398;</button>
                <button type="button" class="button hpc-remove-row" title="<?php esc_attr_e( 'Remove', 'hayk-product-costings' ); ?>">&#x1F5D1;</button>
            </td>
        </tr>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Production & Costs
     * ─────────────────────────────────────────────── */

    public function render_production_metabox( $post ) {
        $run       = get_post_meta( $post->ID, '_hpc_production_run', true );
        $packaging = get_post_meta( $post->ID, '_hpc_packaging_cost_per_pair', true );
        $labour    = get_post_meta( $post->ID, '_hpc_labour', true );
        $facility  = get_post_meta( $post->ID, '_hpc_facility_running_costs', true );
        $currency  = HPC_Settings::currency();
        ?>
        <p class="description">
            <?php esc_html_e( 'These drive the production-run figures. Production run is the number of pairs made. Packaging is a per-pair cost; Labour and Facility running costs are fixed totals for the whole run.', 'hayk-product-costings' ); ?>
        </p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="hpc-production-run"><?php esc_html_e( 'Production run (pairs)', 'hayk-product-costings' ); ?></label></th>
                <td><input type="number" step="any" min="0" id="hpc-production-run" name="hpc_production_run" value="<?php echo esc_attr( $run ); ?>" class="regular-text" placeholder="300"></td>
            </tr>
            <tr>
                <th scope="row"><label for="hpc-packaging"><?php esc_html_e( 'Packaging cost per pair', 'hayk-product-costings' ); ?></label></th>
                <td>
                    <?php echo esc_html( $currency ); ?><input type="number" step="any" min="0" id="hpc-packaging" name="hpc_packaging_cost_per_pair" value="<?php echo esc_attr( $packaging ); ?>" class="regular-text" placeholder="0.65">
                    <span class="description" id="hpc-packaging-total"></span>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="hpc-labour"><?php esc_html_e( 'Labour costs (run total)', 'hayk-product-costings' ); ?></label></th>
                <td><?php echo esc_html( $currency ); ?><input type="number" step="any" min="0" id="hpc-labour" name="hpc_labour" value="<?php echo esc_attr( $labour ); ?>" class="regular-text" placeholder="1000"></td>
            </tr>
            <tr>
                <th scope="row"><label for="hpc-facility"><?php esc_html_e( 'Facility running costs (run total)', 'hayk-product-costings' ); ?></label></th>
                <td><?php echo esc_html( $currency ); ?><input type="number" step="any" min="0" id="hpc-facility" name="hpc_facility_running_costs" value="<?php echo esc_attr( $facility ); ?>" class="regular-text" placeholder="1200"></td>
            </tr>
        </table>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Cost Summary (live)
     * ─────────────────────────────────────────────── */

    public function render_cost_summary_metabox( $post ) {
        ?>
        <div id="hpc-cost-summary" class="hpc-cost-summary">
            <p class="description"><?php esc_html_e( 'Calculated automatically from the Materials table and the Production & Costs fields above — matching the front-end widgets.', 'hayk-product-costings' ); ?></p>
            <table class="widefat striped">
                <tr>
                    <th><?php esc_html_e( 'Material cost per pair', 'hayk-product-costings' ); ?></th>
                    <td id="hpc-sum-matpair">&mdash;</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Prod. run material cost', 'hayk-product-costings' ); ?></th>
                    <td id="hpc-sum-matrun">&mdash;</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Packaging (run total)', 'hayk-product-costings' ); ?></th>
                    <td id="hpc-sum-pkg">&mdash;</td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Manufacturing (labour + facility)', 'hayk-product-costings' ); ?></th>
                    <td id="hpc-sum-mfg">&mdash;</td>
                </tr>
                <tr>
                    <th><strong><?php esc_html_e( 'Full production cost', 'hayk-product-costings' ); ?></strong></th>
                    <td id="hpc-sum-full"><strong>&mdash;</strong></td>
                </tr>
                <tr>
                    <th><strong><?php esc_html_e( 'Single pair cost', 'hayk-product-costings' ); ?></strong></th>
                    <td id="hpc-sum-pair"><strong>&mdash;</strong></td>
                </tr>
            </table>
        </div>
        <?php
    }

    /* ───────────────────────────────────────────────
     * Save
     * ─────────────────────────────────────────────── */

    public function save_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( ! isset( $_POST['hpc_materials_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hpc_materials_nonce'] ) ), 'hpc_save_materials' ) ) {
            return;
        }

        // --- Material rows ---
        $raw_rows = isset( $_POST['hpc_rows'] ) && is_array( $_POST['hpc_rows'] ) ? wp_unslash( $_POST['hpc_rows'] ) : array();
        $clean    = array();
        foreach ( $raw_rows as $row ) {
            $material_id = absint( $row['material_id'] ?? 0 );
            $type        = sanitize_text_field( $row['material_type'] ?? '' );
            $qty         = isset( $row['qty_per_pair'] ) && '' !== $row['qty_per_pair'] ? floatval( $row['qty_per_pair'] ) : 0;

            // Drop entirely empty rows.
            if ( ! $material_id && '' === $type && $qty <= 0 ) {
                continue;
            }
            $clean[] = array(
                'material_type' => $type,
                'material_id'   => $material_id,
                'qty_per_pair'  => $qty,
            );
        }
        update_post_meta( $post_id, '_hpc_material_rows', $clean );

        // --- Production & Costs ---
        $fields = array(
            'hpc_production_run'           => '_hpc_production_run',
            'hpc_packaging_cost_per_pair'  => '_hpc_packaging_cost_per_pair',
            'hpc_labour'                   => '_hpc_labour',
            'hpc_facility_running_costs'   => '_hpc_facility_running_costs',
        );
        foreach ( $fields as $field => $meta_key ) {
            $val = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
            if ( '' === $val ) {
                delete_post_meta( $post_id, $meta_key );
            } else {
                update_post_meta( $post_id, $meta_key, floatval( $val ) );
            }
        }
    }

    /**
     * Format a quantity: up to 4 dp, trailing zeros stripped.
     */
    public static function fmt_qty( $v ) {
        $v = floatval( $v );
        if ( floor( $v ) == $v ) {
            return number_format( $v, 0 );
        }
        return rtrim( rtrim( number_format( $v, 4 ), '0' ), '.' );
    }
}
