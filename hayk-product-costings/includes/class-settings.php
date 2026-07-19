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
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
