<?php

function awpcp_multiple_region_selector( $regions, $options ) {
    return awpcp_multiple_region_selector_with_template( $regions, $options, 'default' );
}

function awpcp_multiple_region_selector_with_template( $regions, $options, $template_name ) {
    if ( $template_name == 'form-table' ) {
        $template = AWPCP_DIR . '/templates/admin/profile/contact-information-region-selector.tpl.php';
    } else {
        $template = AWPCP_DIR . '/frontend/templates/html-widget-multiple-region-selector.tpl.php';
    }

    $selector = new AWPCP_MultipleRegionSelector( $regions, $options );
    $selector->set_template( $template );

    return $selector;
}

class AWPCP_MultipleRegionSelector {

    private $template = '';

    public $options = array();
    public $regions = array();

    public function __construct( $regions, $options ) {

        // we need at least one region, even if its empty
        if ( empty( $regions ) ) {
            $this->regions = array( array(
                'country' => '',
                'county' => '',
                'state' => '',
                'city' => ''
            ) );
        } else {
            $this->regions = $regions;
        }

        $this->options = wp_parse_args( $options, array(
            'maxRegions' => 1,
            'showTextField' => false,
            'showExistingRegionsOnly' => get_awpcp_option( 'buildsearchdropdownlists' ),
            'hierarchy' => array( 'country', 'county', 'state', 'city' ),
            // List of Enabled Fields
            //
            // Possible value is an array with country, state, city or county as keys. Set to true to
            // enable that field or false to disable it. All keys must be provided.
            'enabled_fields' => awpcp_get_enabled_region_fields(),
        ) );

        $this->options['maxRegions'] = max( $this->options['maxRegions'], count( $regions ) );
    }

    public function set_template( $template ) {
        $this->template = $template;
    }

    private function get_region_fields( $context ) {
        return awpcp_region_fields( $context, $this->options['enabled_fields'] );
    }

    private function get_region_field_options( $context, $type, $selected, $hierarchy ) {
        $options = apply_filters( 'awpcp-region-field-options', false, $context, $type, $selected, $hierarchy );

        if ( false !== $options ) {
            return $options;
        }

        if ( $context === 'search' && $this->options['showExistingRegionsOnly'] ) {
            $options = $this->get_existing_regions_of_type($type, $hierarchy);
        } else {
            $options = array();
        }

        $filtered_options = array();

        foreach ( $options as $key => $option ) {
            if ( strlen( $option ) > 0 ) {
                $filtered_options[] = array( 'id' => $option, 'name' => $option );
            }
        }

        return $filtered_options;
    }

    private function get_existing_regions_of_type($type, $hierarchy) {
        $parent_type = $this->get_parent_region_type( $type );
        $parent = awpcp_array_data( $parent_type, null, $hierarchy );

        $api = awpcp_basic_regions_api();

        if ( ! is_null( $parent ) ) {
            $regions = $api->find_by_parent_name( $parent, $parent_type, $type );
        } else {
            $regions = $api->find_by_type( $type );
        }

        return $regions;
    }

    private function get_parent_region_type( $type ) {
        $parent_types = array(
            'country' => null,
            'state' => 'country',
            'city' => 'state',
            'county' => 'city',
        );

        return awpcp_array_data( $type, null, $parent_types );
    }

    public function render($context, $translations=array(), $errors=array()) {
        $fields = $this->get_region_fields( $context );

        if ( empty( $fields ) ) {
            return '';
        }

        wp_enqueue_script( 'awpcp-multiple-region-selector' );

        awpcp()->js->localize( 'multiple-region-selector', array(
            'select-placeholder' => _x( 'Select %s', 'Select <Region Type> in Multiple Region Selector', 'another-wordpress-classifieds-plugin' ),
            'duplicated-region' => __( 'This particular region is already selected in another field. Please choose one or more sub-regions, to make the selection more specific, or change the selected region.', 'another-wordpress-classifieds-plugin' ),
            'missing-country' => __( 'You did not enter your country. Your country is required.', 'another-wordpress-classifieds-plugin' ),
            'missing-state' => __( 'You did not enter your state. Your state is required.', 'another-wordpress-classifieds-plugin' ),
            'missing-county' => __( 'You did not enter your county/village. Your county/village is required.', 'another-wordpress-classifieds-plugin' ),
            'missing-city' => __( 'You did not enter your city. Your city is required.', 'another-wordpress-classifieds-plugin' ),
            'add-region' => ($context == "search") ? __( 'Add Search Region', 'another-wordpress-classifieds-plugin' ) : __( 'Add Region', 'another-wordpress-classifieds-plugin' ),
            'remove-region' => ($context == "search") ? __( 'Delete Search Region', 'another-wordpress-classifieds-plugin' ) : __( 'Remove Region', 'another-wordpress-classifieds-plugin' )
        ) );

        $regions = array();
        foreach ( $this->regions as $i => $region ) {
            $hierarchy = array();
            foreach ( $fields as $type => $field ) {
                $selected = awpcp_array_data( $type, null, $region );

                $regions[$i][$type] = $field;
                $regions[$i][$type]['options'] = $this->get_region_field_options( $context, $type, $selected, $hierarchy );
                $regions[$i][$type]['selected'] = awpcp_array_data( $type, null, $region );
                $regions[$i][$type]['required'] = ( 'search' == $context ) ? false : $field['required'];

                if ( isset( $translations[ $type ] ) ) {
                    $regions[$i][$type]['param'] = $translations[ $type ];
                } else {
                    $regions[$i][$type]['param'] = $type;
                }

                // make values selected in parent fields available to child
                // fields when computing the field options.
                $hierarchy[$type] = $regions[$i][$type]['selected'];
            }
        }

        // use first region as template for additional regions
        $this->options['template'] = $regions[0];

        $options = apply_filters( 'awpcp-multiple-region-selector-configuration', $this->options, $context, $fields );

        $uuid = uniqid();
        awpcp()->js->set( "multiple-region-selector-$uuid", array(
            'options' => array_merge( $options, array(
                'fields' => array_keys( $fields ),
                'context' => $context,
            ) ),
            'regions' => $regions,
        ) );

        ob_start();
        include( $this->template );
        $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }
}
