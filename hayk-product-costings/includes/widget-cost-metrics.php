<?php
/**
 * Elementor Widget – Cost Metrics.
 *
 * Displays selectable calculated costing figures for a product (production
 * run, full production cost, single pair cost, and the material / packaging /
 * manufacturing breakdown), each with an optional label override.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HPC_Widget_Cost_Metrics extends \Elementor\Widget_Base {

    public function get_name() {
        return 'hpc_cost_metrics';
    }

    public function get_title() {
        return esc_html__( 'Cost Metrics', 'hayk-product-costings' );
    }

    public function get_icon() {
        return 'eicon-price-list';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array( 'cost', 'costing', 'price', 'production', 'pair', 'shoe' );
    }

    public function get_style_depends() {
        return array( 'hpc-cost-metrics-front' );
    }

    /**
     * Available metric definitions (key => label).
     */
    private function get_metric_options() {
        return array(
            'production_run'          => 'Production run',
            'full_production_cost'    => 'Full production cost',
            'single_pair_cost'        => 'Single pair cost',
            'material_cost_per_pair'  => 'Material cost per pair',
            'prod_run_material_cost'  => 'Prod. run material cost',
            'packaging_run_total'     => 'Packaging production run total',
            'packaging_cost_per_pair' => 'Packaging cost per pair',
            'labour'                  => 'Labour costs',
            'facility_running_costs'  => 'Facility running costs',
            'miscellaneous_costs'     => 'Miscellaneous cost',
            'manufacturing_total'     => 'Manufacturing costs (total)',
        );
    }

    /**
     * Metrics that are plain numbers (not currency).
     */
    private function non_currency() {
        return array( 'production_run' );
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

        $this->add_control( 'metrics', array(
            'label'       => esc_html__( 'Metrics to Display', 'hayk-product-costings' ),
            'type'        => \Elementor\Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $this->get_metric_options(),
            'default'     => array( 'full_production_cost', 'single_pair_cost' ),
            'description' => esc_html__( 'Select which costing figures to show.', 'hayk-product-costings' ),
        ) );

        $this->add_control( 'layout', array(
            'label'   => esc_html__( 'Layout', 'hayk-product-costings' ),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'stack',
            'options' => array(
                'stack'  => esc_html__( 'Stacked rows', 'hayk-product-costings' ),
                'inline' => esc_html__( 'Inline pills', 'hayk-product-costings' ),
            ),
        ) );

        $this->add_control( 'currency_symbol', array(
            'label'   => esc_html__( 'Currency Symbol', 'hayk-product-costings' ),
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => HPC_Settings::currency(),
        ) );

        $this->add_control( 'prefix_text', array(
            'label'       => esc_html__( 'Value Prefix Text', 'hayk-product-costings' ),
            'type'        => \Elementor\Controls_Manager::TEXT,
            'description' => esc_html__( 'Text before the figure (e.g. "AUD" or "Approx.").', 'hayk-product-costings' ),
            'default'     => '',
        ) );

        $this->add_control( 'label_overrides_heading', array(
            'label'     => esc_html__( 'Label Overrides', 'hayk-product-costings' ),
            'type'      => \Elementor\Controls_Manager::HEADING,
            'separator' => 'before',
        ) );

        foreach ( $this->get_metric_options() as $key => $default_label ) {
            $this->add_control( 'label_' . $key, array(
                'label'       => $default_label,
                'type'        => \Elementor\Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => $default_label,
            ) );
        }

        $this->end_controls_section();

        /* ── Style: Layout ── */
        $this->start_controls_section( 'section_style_layout', array(
            'label' => esc_html__( 'Layout', 'hayk-product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'item_bg_color', array(
            'label'     => esc_html__( 'Item Background', 'hayk-product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#000000',
            'selectors' => array( '{{WRAPPER}} .hpc-cm-item' => 'background-color: {{VALUE}};' ),
        ) );

        $this->add_responsive_control( 'item_padding', array(
            'label'      => esc_html__( 'Item Padding', 'hayk-product-costings' ),
            'type'       => \Elementor\Controls_Manager::DIMENSIONS,
            'size_units' => array( 'px', 'em' ),
            'default'    => array( 'top' => '14', 'right' => '22', 'bottom' => '14', 'left' => '22', 'unit' => 'px' ),
            'selectors'  => array( '{{WRAPPER}} .hpc-cm-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' ),
        ) );

        $this->add_control( 'item_radius', array(
            'label'      => esc_html__( 'Border Radius', 'hayk-product-costings' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 60 ) ),
            'default'    => array( 'unit' => 'px', 'size' => 30 ),
            'selectors'  => array( '{{WRAPPER}} .hpc-cm-item' => 'border-radius: {{SIZE}}{{UNIT}};' ),
        ) );

        $this->add_responsive_control( 'item_gap', array(
            'label'      => esc_html__( 'Gap Between Items', 'hayk-product-costings' ),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
            'default'    => array( 'unit' => 'px', 'size' => 12 ),
            'selectors'  => array( '{{WRAPPER}} .hpc-cm' => 'gap: {{SIZE}}{{UNIT}};' ),
        ) );

        $this->end_controls_section();

        /* ── Style: Label ── */
        $this->start_controls_section( 'section_style_label', array(
            'label' => esc_html__( 'Label', 'hayk-product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'label_color', array(
            'label'     => esc_html__( 'Color', 'hayk-product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .hpc-cm-label' => 'color: {{VALUE}};' ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'label_typography',
            'label'    => esc_html__( 'Typography', 'hayk-product-costings' ),
            'selector' => '{{WRAPPER}} .hpc-cm-label',
        ) );

        $this->end_controls_section();

        /* ── Style: Value ── */
        $this->start_controls_section( 'section_style_value', array(
            'label' => esc_html__( 'Value', 'hayk-product-costings' ),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ) );

        $this->add_control( 'value_color', array(
            'label'     => esc_html__( 'Color', 'hayk-product-costings' ),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => array( '{{WRAPPER}} .hpc-cm-value' => 'color: {{VALUE}};' ),
        ) );

        $this->add_group_control( \Elementor\Group_Control_Typography::get_type(), array(
            'name'     => 'value_typography',
            'label'    => esc_html__( 'Typography', 'hayk-product-costings' ),
            'selector' => '{{WRAPPER}} .hpc-cm-value',
        ) );

        $this->end_controls_section();
    }

    protected function render() {
        $settings   = $this->get_settings_for_display();
        $product_id = ! empty( $settings['product_id'] ) ? absint( $settings['product_id'] ) : get_the_ID();

        if ( ! $product_id || HPC_PRODUCTS_CPT !== get_post_type( $product_id ) ) {
            if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
                echo '<p style="padding:20px;text-align:center;color:#999;">' . esc_html__( 'Cost Metrics — please view on a Products post or enter a Product ID.', 'hayk-product-costings' ) . '</p>';
            }
            return;
        }

        $selected = ! empty( $settings['metrics'] ) ? $settings['metrics'] : array();
        if ( empty( $selected ) ) {
            return;
        }

        $currency     = $settings['currency_symbol'];
        $prefix       = $settings['prefix_text'];
        $layout       = ! empty( $settings['layout'] ) ? $settings['layout'] : 'stack';
        $values       = HPC_Costing_Calculator::metrics( $product_id );
        $labels       = $this->get_metric_options();
        $non_currency = $this->non_currency();

        echo '<div class="hpc-cm hpc-cm-' . esc_attr( $layout ) . '">';

        foreach ( $selected as $key ) {
            if ( ! isset( $values[ $key ] ) || ! isset( $labels[ $key ] ) ) {
                continue;
            }

            $raw      = $values[ $key ];
            $override = ! empty( $settings[ 'label_' . $key ] ) ? $settings[ 'label_' . $key ] : '';
            $label    = '' !== $override ? $override : $labels[ $key ];

            if ( in_array( $key, $non_currency, true ) ) {
                $formatted = number_format( $raw, 0 );
            } else {
                $formatted = $currency . number_format( $raw, 2 );
            }

            echo '<div class="hpc-cm-item">';
            echo '<span class="hpc-cm-label">' . esc_html( $label ) . ':</span> ';
            echo '<span class="hpc-cm-value">';
            if ( '' !== $prefix ) {
                echo '<span class="hpc-cm-prefix">' . esc_html( $prefix ) . ' </span>';
            }
            echo esc_html( $formatted );
            echo '</span>';
            echo '</div>';
        }

        echo '</div>';
    }
}
