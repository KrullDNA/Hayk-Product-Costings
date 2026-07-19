<?php
/**
 * Simple settings: currency symbol, exposed under the Products menu.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HPC_Settings {

    private static $instance = null;

    const OPTION_CURRENCY = 'hpc_currency_symbol';
    const OPTION_MARGIN   = 'hpc_leather_margin_pct';
    const OPTION_UNITS    = 'hpc_units';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * The configured currency symbol (defaults to $).
     */
    public static function currency() {
        $c = get_option( self::OPTION_CURRENCY, '$' );
        return ( '' !== $c && null !== $c ) ? $c : '$';
    }

    /**
     * The global leather margin, as a percentage (e.g. 15 for 15%).
     * Applied as a cost uplift to any bulk-pricing row flagged "apply margin".
     */
    public static function leather_margin_pct() {
        $m = get_option( self::OPTION_MARGIN, 0 );
        return max( 0, floatval( $m ) );
    }

    public function add_menu() {
        add_submenu_page(
            'edit.php?post_type=' . HPC_PRODUCTS_CPT,
            __( 'Costings Settings', 'hayk-product-costings' ),
            __( 'Costings Settings', 'hayk-product-costings' ),
            'manage_options',
            'hpc-settings',
            array( $this, 'render_page' )
        );
    }

    public function register_settings() {
        register_setting( 'hpc_settings', self::OPTION_CURRENCY, array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '$',
        ) );

        register_setting( 'hpc_settings', self::OPTION_MARGIN, array(
            'type'              => 'number',
            'sanitize_callback' => array( __CLASS__, 'sanitize_margin' ),
            'default'           => 0,
        ) );

        register_setting( 'hpc_settings', self::OPTION_UNITS, array(
            'type'              => 'array',
            'sanitize_callback' => array( __CLASS__, 'sanitize_units' ),
        ) );
    }

    public static function sanitize_margin( $value ) {
        return max( 0, floatval( $value ) );
    }

    /**
     * Sanitise the units repeater into structured definitions.
     *
     * Accepts the repeater's array of rows ( each with singular / plural /
     * units_per keys ). A row with an empty singular is dropped; plural
     * defaults to the singular; units_per defaults to 1.
     */
    public static function sanitize_units( $value ) {
        if ( ! is_array( $value ) ) {
            return HPC_Material_Data::default_unit_defs();
        }

        $defs = array();
        $seen = array();
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $singular = isset( $row['singular'] ) ? trim( sanitize_text_field( $row['singular'] ) ) : '';
            if ( '' === $singular ) {
                continue;
            }
            $plural = isset( $row['plural'] ) && '' !== trim( $row['plural'] ) ? trim( sanitize_text_field( $row['plural'] ) ) : $singular;
            $units  = isset( $row['units_per'] ) && '' !== $row['units_per'] ? max( 1, floatval( $row['units_per'] ) ) : 1;

            $key = strtolower( $plural );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;
            $defs[] = array( 'singular' => $singular, 'plural' => $plural, 'units_per' => $units );
        }

        return ! empty( $defs ) ? $defs : HPC_Material_Data::default_unit_defs();
    }

    public function render_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Costings Settings', 'hayk-product-costings' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'hpc_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="hpc-currency"><?php esc_html_e( 'Currency Symbol', 'hayk-product-costings' ); ?></label></th>
                        <td>
                            <input type="text" id="hpc-currency" name="<?php echo esc_attr( self::OPTION_CURRENCY ); ?>" value="<?php echo esc_attr( self::currency() ); ?>" class="small-text">
                            <p class="description"><?php esc_html_e( 'Used across the admin cost summary and the front-end widgets.', 'hayk-product-costings' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hpc-margin"><?php esc_html_e( 'Leather Margin %', 'hayk-product-costings' ); ?></label></th>
                        <td>
                            <input type="number" step="any" min="0" id="hpc-margin" name="<?php echo esc_attr( self::OPTION_MARGIN ); ?>" value="<?php echo esc_attr( self::leather_margin_pct() ); ?>" class="small-text">%
                            <p class="description"><?php esc_html_e( 'Cost uplift applied to any bulk-pricing row with its "Apply margin" box ticked. e.g. 15 turns a $4.90 leather cost into $5.64. Leave at 0 for no uplift.', 'hayk-product-costings' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Purchase Units', 'hayk-product-costings' ); ?></th>
                        <td>
                            <p class="description" style="margin-bottom:8px;">
                                <?php esc_html_e( 'These populate the Unit dropdown in each material\'s Bulk Pricing box. "Units per" is how many individual units make up one purchase unit — e.g. a pair = 2 units (shown next to quantities).', 'hayk-product-costings' ); ?>
                            </p>
                            <table class="widefat striped" id="hpc-units-table" style="max-width:560px;">
                                <thead>
                                    <tr>
                                        <th style="width:24px;">&nbsp;</th>
                                        <th><?php esc_html_e( 'Singular', 'hayk-product-costings' ); ?></th>
                                        <th><?php esc_html_e( 'Plural', 'hayk-product-costings' ); ?></th>
                                        <th style="width:100px;"><?php esc_html_e( 'Units per', 'hayk-product-costings' ); ?></th>
                                        <th style="width:40px;">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody id="hpc-units-body">
                                    <?php foreach ( HPC_Material_Data::unit_defs() as $i => $d ) : ?>
                                        <tr>
                                            <td class="hpc-unit-drag" style="cursor:move;text-align:center;color:#888;">&#9776;</td>
                                            <td><input type="text" name="<?php echo esc_attr( self::OPTION_UNITS ); ?>[<?php echo (int) $i; ?>][singular]" value="<?php echo esc_attr( $d['singular'] ); ?>" class="widefat" placeholder="skin"></td>
                                            <td><input type="text" name="<?php echo esc_attr( self::OPTION_UNITS ); ?>[<?php echo (int) $i; ?>][plural]" value="<?php echo esc_attr( $d['plural'] ); ?>" class="widefat" placeholder="skins"></td>
                                            <td><input type="number" step="any" min="1" name="<?php echo esc_attr( self::OPTION_UNITS ); ?>[<?php echo (int) $i; ?>][units_per]" value="<?php echo esc_attr( rtrim( rtrim( number_format( $d['units_per'], 4 ), '0' ), '.' ) ); ?>" class="widefat" placeholder="1"></td>
                                            <td><button type="button" class="button hpc-unit-remove">&times;</button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p><button type="button" class="button" id="hpc-unit-add"><?php esc_html_e( '+ Add Unit', 'hayk-product-costings' ); ?></button></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        jQuery(function ($) {
            var optName = <?php echo wp_json_encode( self::OPTION_UNITS ); ?>;
            function rowMarkup(i) {
                return '<tr>' +
                    '<td class="hpc-unit-drag" style="cursor:move;text-align:center;color:#888;">&#9776;</td>' +
                    '<td><input type="text" name="' + optName + '[' + i + '][singular]" class="widefat" placeholder="skin"></td>' +
                    '<td><input type="text" name="' + optName + '[' + i + '][plural]" class="widefat" placeholder="skins"></td>' +
                    '<td><input type="number" step="any" min="1" name="' + optName + '[' + i + '][units_per]" class="widefat" placeholder="1"></td>' +
                    '<td><button type="button" class="button hpc-unit-remove">&times;</button></td>' +
                    '</tr>';
            }
            $('#hpc-unit-add').on('click', function () {
                $('#hpc-units-body').append(rowMarkup($('#hpc-units-body tr').length));
            });
            $('#hpc-units-table').on('click', '.hpc-unit-remove', function () {
                $(this).closest('tr').remove();
            });
            if ($.fn.sortable) {
                $('#hpc-units-body').sortable({ handle: '.hpc-unit-drag', axis: 'y', opacity: 0.7 });
            }
        });
        </script>
        <?php
    }
}
