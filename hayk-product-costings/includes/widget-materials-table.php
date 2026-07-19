<?php
/**
 * Elementor Widget – Materials Table.
 *
 * Renders the materials saved on a Products CPT post as the styled front-end
 * table from the client spec: Material Type, Image, Materials, Cost per MOQ,
 * MOQ, Qty per pair, Cost per pair.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HPC_Widget_Materials_Table extends \Elementor\Widget_Base {

    public function get_name() {
        return 'hpc_materials_table';
    }

    public function get_title() {
        return esc_html__( 'Materials Table', 'hayk-product-costings' );
    }

    public function get_icon() {
        return 'eicon-table';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array( 'materials', 'table', 'product', 'costing', 'shoe' );
    }

    public function get_style_depends() {
        return array( 'hpc-materials-table-front' );
    }

    /**
     * Column keys → labels, for the per-column style controls and headings.
     */
    private function get_column_defs() {
        return array(
            'type'    => array( 'label' => 'Material Type', 'class' => 'hpc-mt-type' ),
            'image'   => array( 'label' => 'Image',         'class' => 'hpc-mt-image' ),
            'material' => array( 'label' => 'Materials',    'class' => 'hpc-mt-material' ),
            'costmoq' => array( 'label' => 'Cost per MOQ',  'class' => 'hpc-mt-costmoq' ),
            'moq'     => array( 'label' => 'MOQ',           'class' => 'hpc-mt-moq' ),
            'qty'     => array( 'label' => 'Qty per pair',  'class' => 'hpc-mt-qty' ),
            'costpair' => array( 'label' => 'Cost per pair', 'class' => 'hpc-mt-costpair' ),
        );
    }

    protected function register_controls() {

        /* ── Content ── */
        $this->start_controls_section( 'section_content', array(
            'label' => esc_html__( 'Content', 'hayk-product-costings' ),
        ) );

        $this->add_control( 'product_id', array(
            'label'       => esc_html__( 'Product', 'hayk-product-costings' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'description' => esc_html__( 'Leave blank to use the current product. Or enter a Product post ID.', 'hayk-product-costings' ),
            'default'     => '',
        ) );

        $this->add_control( 'currency_symbol', array(
            'label'   => esc_html__( 'Currency Symbol', 'hayk-product-costings' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => HPC_Settings::currency(),
        ) );

        $this->add_control( 'show_image', array(
            'label'        => esc_html__( 'Show Image Column', 'hayk-product-costings' ),
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
        ) );

        $this->add_control( 'empty_message', array(
            'label'   => esc_html__( 'Empty Table Message', 'hayk-product-costings' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'No materials have been added yet.',
        ) );

        $this->end_controls_section();

        /* ── Header Style ── */
        $this->start_controls_section( 'section_style_header', array(
            'label' => esc_html__( 'Header', 'hayk-product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'header_bg_color', array(
            'label'     => esc_html__( 'Background Color', 'hayk-product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#000000',
            'selectors' => array( '{{WRAPPER}} .hpc-mt thead th' => 'background-color: {{VALUE}};' ),
        ) );

        $this->add_control( 'header_text_color', array(
            'label'     => esc_html__( 'Text Color', 'hayk-product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .hpc-mt thead th' => 'color: {{VALUE}};' ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'header_typography',
            'label'    => esc_html__( 'Typography', 'hayk-product-costings' ),
            'selector' => '{{WRAPPER}} .hpc-mt thead th',
        ) );

        $this->add_responsive_control( 'header_padding', array(
            'label'      => esc_html__( 'Padding', 'hayk-product-costings' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'default'    => array( 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px' ),
            'selectors'  => array( '{{WRAPPER}} .hpc-mt thead th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->end_controls_section();

        /* ── Body Style ── */
        $this->start_controls_section( 'section_style_body', array(
            'label' => esc_html__( 'Body Rows', 'hayk-product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'row_border_color', array(
            'label'     => esc_html__( 'Row Border Color', 'hayk-product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#e5e5e5',
            'selectors' => array( '{{WRAPPER}} .hpc-mt tbody td' => 'border-color: {{VALUE}};' ),
        ) );

        $this->add_control( 'body_text_color', array(
            'label'     => esc_html__( 'Text Color', 'hayk-product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => array( '{{WRAPPER}} .hpc-mt tbody td' => 'color: {{VALUE}};' ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'body_typography',
            'label'    => esc_html__( 'Typography', 'hayk-product-costings' ),
            'selector' => '{{WRAPPER}} .hpc-mt tbody td',
        ) );

        $this->add_responsive_control( 'body_padding', array(
            'label'      => esc_html__( 'Cell Padding', 'hayk-product-costings' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'default'    => array( 'top' => '18', 'right' => '16', 'bottom' => '18', 'left' => '16', 'unit' => 'px' ),
            'selectors'  => array( '{{WRAPPER}} .hpc-mt tbody td' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->add_control( 'image_size', array(
            'label'      => esc_html__( 'Image Size (px)', 'hayk-product-costings' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 30, 'max' => 200 ) ),
            'default'    => array( 'unit' => 'px', 'size' => 90 ),
            'selectors'  => array( '{{WRAPPER}} .hpc-mt-image img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; object-fit: cover;' ),
        ) );

        $this->end_controls_section();

        /* ── Table Style ── */
        $this->start_controls_section( 'section_style_table', array(
            'label' => esc_html__( 'Table', 'hayk-product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_group_control( \Elementor\Group_Control_Border::get_type(), array(
            'name'     => 'table_border',
            'label'    => esc_html__( 'Table Border', 'hayk-product-costings' ),
            'selector' => '{{WRAPPER}} .hpc-mt',
        ) );

        $this->add_control( 'table_border_radius', array(
            'label'      => esc_html__( 'Border Radius', 'hayk-product-costings' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px' ),
            'default'    => array( 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px' ),
            'selectors'  => array( '{{WRAPPER}} .hpc-mt-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;' ),
        ) );

        $this->end_controls_section();
    }

    protected function render() {
        $settings   = $this->get_settings_for_display();
        $product_id = ! empty( $settings['product_id'] ) ? absint( $settings['product_id'] ) : get_the_ID();

        if ( ! $product_id || HPC_PRODUCTS_CPT !== get_post_type( $product_id ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<p style="padding:20px;text-align:center;color:#999;">' . esc_html__( 'Materials Table — please view on a Products post or enter a Product ID.', 'hayk-product-costings' ) . '</p>';
            }
            return;
        }

        $lines = HPC_Costing_Calculator::material_lines( $product_id );

        if ( empty( $lines ) ) {
            if ( $settings['empty_message'] ) {
                echo '<p class="hpc-mt-empty">' . esc_html( $settings['empty_message'] ) . '</p>';
            }
            return;
        }

        $currency   = $settings['currency_symbol'];
        $show_image = ( 'yes' === $settings['show_image'] );
        ?>
        <div class="hpc-mt-wrapper">
            <table class="hpc-mt">
                <thead>
                    <tr>
                        <th class="hpc-mt-col-type"><?php esc_html_e( 'Material Type', 'hayk-product-costings' ); ?></th>
                        <?php if ( $show_image ) : ?>
                            <th class="hpc-mt-col-image"><?php esc_html_e( 'Image', 'hayk-product-costings' ); ?></th>
                        <?php endif; ?>
                        <th class="hpc-mt-col-material"><?php esc_html_e( 'Materials', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-mt-col-costmoq"><?php esc_html_e( 'Cost per MOQ', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-mt-col-moq"><?php esc_html_e( 'MOQ', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-mt-col-qty"><?php esc_html_e( 'Qty per pair', 'hayk-product-costings' ); ?></th>
                        <th class="hpc-mt-col-costpair"><?php esc_html_e( 'Cost per pair', 'hayk-product-costings' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $lines as $line ) : ?>
                        <?php
                        $unit         = $line['unit'];
                        $moq_display  = HPC_Product_Metaboxes::fmt_qty( $line['moq'] ) . ' ' . $unit;
                        $qty_display  = HPC_Product_Metaboxes::fmt_qty( $line['qty_per_pair'] ) . ' ' . $unit;
                        $mat_link     = get_permalink( $line['material_id'] );
                        ?>
                        <tr>
                            <td class="hpc-mt-type"><?php echo esc_html( $line['material_type'] ); ?></td>
                            <?php if ( $show_image ) : ?>
                                <td class="hpc-mt-image">
                                    <?php if ( $line['image'] ) : ?>
                                        <img src="<?php echo esc_url( $line['image'] ); ?>" alt="<?php echo esc_attr( $line['title'] ); ?>">
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="hpc-mt-material">
                                <?php
                                if ( $mat_link ) {
                                    printf( '<a href="%s" target="_blank" rel="noopener noreferrer"><strong>%s</strong></a>', esc_url( $mat_link ), esc_html( $line['title'] ) );
                                } else {
                                    echo '<strong>' . esc_html( $line['title'] ) . '</strong>';
                                }
                                ?>
                            </td>
                            <td class="hpc-mt-costmoq"><strong><?php echo esc_html( $currency . number_format( $line['cost_per_moq'], 2 ) ); ?></strong></td>
                            <td class="hpc-mt-moq"><?php echo esc_html( $moq_display ); ?></td>
                            <td class="hpc-mt-qty"><?php echo esc_html( $qty_display ); ?></td>
                            <td class="hpc-mt-costpair"><strong><?php echo esc_html( $currency . number_format( $line['cost_per_pair'], 2 ) ); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
