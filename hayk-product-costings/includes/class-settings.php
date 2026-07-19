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
     * Parse the units textarea into structured definitions.
     *
     * One unit per line, format:  singular | plural | units_per
     * (plural and units_per optional; units_per defaults to 1).
     */
    public static function sanitize_units( $value ) {
        if ( is_string( $value ) ) {
            $value = preg_split( '/\r\n|\r|\n/', $value );
        }
        if ( ! is_array( $value ) ) {
            return HPC_Material_Data::default_unit_defs();
        }

        $defs  = array();
        $seen  = array();
        foreach ( $value as $line ) {
            $line = trim( sanitize_text_field( $line ) );
            if ( '' === $line ) {
                continue;
            }
            $parts    = array_map( 'trim', explode( '|', $line ) );
            $singular = $parts[0];
            $plural   = ( isset( $parts[1] ) && '' !== $parts[1] ) ? $parts[1] : $singular;
            $units    = ( isset( $parts[2] ) && '' !== $parts[2] ) ? max( 1, floatval( $parts[2] ) ) : 1;

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
                        <th scope="row"><label for="hpc-units"><?php esc_html_e( 'Purchase Units', 'hayk-product-costings' ); ?></label></th>
                        <td>
                            <?php
                            $lines = array();
                            foreach ( HPC_Material_Data::unit_defs() as $d ) {
                                $lines[] = $d['singular'] . ' | ' . $d['plural'] . ' | ' . rtrim( rtrim( number_format( $d['units_per'], 4 ), '0' ), '.' );
                            }
                            ?>
                            <textarea id="hpc-units" name="<?php echo esc_attr( self::OPTION_UNITS ); ?>" rows="10" class="large-text code"><?php echo esc_textarea( implode( "\n", $lines ) ); ?></textarea>
                            <p class="description">
                                <?php esc_html_e( 'One unit per line, in the format:  singular | plural | units per', 'hayk-product-costings' ); ?><br>
                                <?php esc_html_e( 'These populate the Unit dropdown in each material\'s Bulk Pricing box. "units per" is how many individual units make up one purchase unit — e.g. "pair | pairs | 2" means 1 pair = 2 units (shown next to quantities). Plural and units per are optional (default plural = singular, units per = 1). Add or remove lines to manage the list.', 'hayk-product-costings' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
