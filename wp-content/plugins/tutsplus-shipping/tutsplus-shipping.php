<?php

/**
 * Plugin Name: TutsPlus Shipping
 * Plugin URI: http://code.tutsplus.com/tutorials/create-a-custom-shipping-method-for-woocommerce--cms-26098
 * Description: Custom Shipping Method for WooCommerce
 * Version: 1.0.0
 * Author: Igor Benić
 * Author URI: http://www.ibenic.com
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /lang
 * Text Domain: tutsplus
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

use Shuchkin\SimpleXLSX;
require_once "simplexlsx-master/src/SimpleXLSX.php";
/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    function tutsplus_shipping_method() {
        if ( ! class_exists( 'TutsPlus_Shipping_Method' ) ) {
            class TutsPlus_Shipping_Method extends WC_Shipping_Method {

                public $file;
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct() {
                    $this->id                 = 'tutsplus';
                    $this->method_title       = __( 'TutsPlus Shipping', 'tutsplus' );
                    $this->method_description = __( 'Custom Shipping Method for TutsPlus', 'tutsplus' );

                    $this->init();

                    $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
                    $this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'TutsPlus Shipping', 'tutsplus' );
                }

                /**
                 * Init your settings
                 *
                 * @access public
                 * @return void
                 */
                function init() {
                    // Load the settings API
                    $this->init_form_fields();
                    $this->init_settings();

                    // Save settings in admin if you have any defined
                    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
                }

                /**
                 * Define settings field for this shipping
                 * @return void
                 */
                function init_form_fields() {

                    $this->form_fields = array(
                        'enabled' => array(
                            'title' => __( 'Enable', 'tutsplus' ),
                            'type' => 'checkbox',
                            'description' => __( 'Enable this shipping.', 'tutsplus' ),
                            'default' => 'yes'
                        ),
                        'title' => array(
                            'title' => __( 'Title', 'tutsplus' ),
                            'type' => 'text',
                            'description' => __( 'Title to be display on site', 'tutsplus' ),
                            'default' => __( 'TutsPlus Shipping', 'tutsplus' )
                        ),
                        'key' => array(
                            'title' => __( 'API key', 'tutsplus' ),
                            'type' => 'text',
                            'description' => __( 'Google API', 'tutsplus' ),
                        ),
                        'source' => array(
                            'title' => __( 'File', 'tutsplus' ),
                            'type' => 'file',
                            'description' => __( 'File price', 'tutsplus' ),
                        )
                    );

                }

                function process_admin_options(): bool
                {
                    $this->upload_key_files();

                    $saved = parent::process_admin_options();

                    return $saved;
                }

                private function upload_key_files() {

                    if ( ! function_exists( 'wp_handle_upload' ) ) {
                        require_once( ABSPATH . 'wp-admin/includes/file.php' );
                    }
                    $uploadedfile = &$_FILES['woocommerce_tutsplus_source'];
                    $overrides = [ 'test_form' => false ];
                    $movefile = wp_handle_upload( $uploadedfile, $overrides );
                    $this->settings['source'] = $movefile['file'];

                    $file = $movefile['file'];
                    $filename = basename($file);
                    $upload_file = wp_upload_bits($filename, null, file_get_contents($file));
                    $wp_filetype = wp_check_filetype($filename, null );
                    $attachment = array(
                        'post_mime_type' => $wp_filetype['type'],
                        'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
                        'post_content' => '',
                        'post_status' => 'inherit'
                    );
                    $attachment_id = wp_insert_attachment( $attachment, $upload_file['file'] );

                    $my_price = get_posts( array(
                        'numberposts' => 5,
                        'category'    => 0,
                        'orderby'     => 'date',
                        'order'       => 'DESC',
                        'include'     => array(),
                        'exclude'     => array(),
                        'meta_key'    => '',
                        'meta_value'  =>'',
                        'post_type'   => 'file_price',
                        'suppress_filters' => true,
                    ) );
                    if (!$my_price) {
                        wp_insert_post(wp_slash(array(
                            'post_title'    => 'Price',
                            'post_content'  => $attachment_id,
                            'post_status'   => 'publish',
                            'post_author'   => 1,
                            'post_category' => 0,
                            'post_type'     => 'file_price'
                        )));
                    } else {
                        $my_post = [
                            'ID' => $my_price[0]->ID,
                            'post_content' => $attachment_id,
                        ];
                        wp_update_post( wp_slash( $my_post ) );
                    }
                }

                /**
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping( $package = array() ) {
//                    $addressFrom = 'Ahornallee 4, 14050 Berlin, Германия';
//                    $addressTo   = 'Naumannstraße 36, 10829 Berlin, Германия';

                    $user_id = $package['user']['ID'];
                    $billing_postcode = get_user_meta( $user_id, 'billing_postcode', true);
                    $billing_address_1 = get_user_meta( $user_id, 'billing_address_1', true );
                    $billing_city = get_user_meta( $user_id, 'billing_city', true );

                    $shipping_postcode = get_user_meta( $user_id, 'shipping_postcode', true);
                    $shipping_address_1 = get_user_meta( $user_id, 'shipping_address_1', true );
                    $shipping_city = get_user_meta( $user_id, 'shipping_city', true );

                    if ($shipping_postcode) {
                        $user_address = $shipping_address_1 ." ". $shipping_postcode ." ".
                            $shipping_city;
                    } else {
                        $user_address = $billing_address_1 ." ". $billing_postcode ." ".
                            $billing_city;
                    }

                    $my_price = get_posts( array(
                        'numberposts' => 5,
                        'category'    => 0,
                        'orderby'     => 'date',
                        'order'       => 'DESC',
                        'include'     => array(),
                        'exclude'     => array(),
                        'meta_key'    => '',
                        'meta_value'  =>'',
                        'post_type'   => 'file_price',
                        'suppress_filters' => true,
                    ) );
                    $source = $my_price[0]->post_content;

                    if ($source === '') {
                        return;
                    }

                    $file_id = (int)($source);
                    if (!$file_id) {
                        return;
                    }
                    $matrix = processFile($file_id);

                    $product_count = 0;
                    $store_address     = get_option( 'woocommerce_store_address' );
                    $store_city        = get_option( 'woocommerce_store_city' );
                    $store_postcode    = get_option( 'woocommerce_store_postcode' );
                    $store_address_full = $store_address . " " . $store_postcode . " " . $store_city;

                    foreach ($package['contents'] as $product) {
                        $product_count = $product_count + $product['quantity'];
                    }

                    $distance = getDistance($store_address_full, $user_address);
                    if (!$distance) {
                        return;
                    }
                    $distance = (int)str_replace('km', '', $distance);

                    $cost = findCurrentCoast($distance, $matrix, $product_count);

                    $rate = array(
                        'id' => $this->id,
                        'label' => $this->title,
                        'cost' => $cost
                    );

                    $this->add_rate( $rate );
                }
            }
        }
    }

    function processFile($file_id) {
        $file = get_attached_file($file_id);
        if ( $xlsx = SimpleXLSX::parseFile($file) ) {
            $dim = $xlsx->dimension();
            $num_cols = $dim[0];
            $num_rows = $dim[1];

            foreach ($xlsx->rows() as $index => $r) {
                for ($i = 0; $i < $num_cols; $i ++) {
                    if( !empty($r[ $i ])) {
                        $matrix[$index][$i] = $r[ $i ];
                    };
                }
            }
        } else {
            echo SimpleXLSX::parseError();
        }
        return $matrix;
    }

    function findCurrentCoast($distance, $matrix, $product_count) {
        foreach ($matrix as $index => $row) {
            if (intval($row[0]) && $distance < $row[0]) {
                $current_row = $row;
                break;
            } elseif(intval($row[0])) {
                $current_row = $row;
            }
        }

        if ($product_count < count($current_row)) {
            return $current_row[$product_count];
        } else {
            return end($current_row);
        }

    }

    function getDistance($addressFrom, $addressTo){
        // Google API key
//        $apiKey = 'AIzaSyBVzTVCNStKmPn47mh-e9xxmT2PamV0ebc'; // MY key
//        $apiKey = 'AIzaSyB1iXrYerWvtX-DX1hQgy4g-WJHK6eAfFo';

        $TutsPlus_Shipping_Method = new TutsPlus_Shipping_Method();
        $apiKey = (string) $TutsPlus_Shipping_Method->settings['key'];

        // Change address format
        $formattedAddrFrom    = str_replace(' ', '+', $addressFrom);
        $formattedAddrTo     = str_replace(' ', '+', $addressTo);

        $geocodeFrom = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$formattedAddrFrom.'&sensor=false&key='.$apiKey);
        $outputFrom = json_decode($geocodeFrom);
        if(!empty($outputFrom->error_message)){
            return $outputFrom->error_message;
        }

        $geocodeTo = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.$formattedAddrTo.'&sensor=false&key='.$apiKey);
        $outputTo = json_decode($geocodeTo);
        if(!empty($outputTo->error_message)){
            return $outputTo->error_message;
        }

        $result = file_get_contents('https://maps.googleapis.com/maps/api/distancematrix/json?origins=place_id:'. $outputFrom->results[0]->place_id .'&destinations=place_id:'.$outputTo->results[0]->place_id.'&mode=driving&key='.$apiKey);
        $result = json_decode($result);
        return $result->rows[0]->elements[0]->distance->text; //value
    }

    add_action( 'woocommerce_shipping_init', 'tutsplus_shipping_method' );

    function add_tutsplus_shipping_method( $methods ) {
        $methods[] = 'TutsPlus_Shipping_Method';
        return $methods;
    }


    add_filter( 'woocommerce_shipping_methods', 'add_tutsplus_shipping_method' );
    add_action( 'woocommerce_after_checkout_validation', 'tutsplus_validate_order' , 10 );

    add_action('init', 'my_custom_post');
    function my_custom_init(){
        register_post_type('file_price', array(
            'labels'             => array(
                'name'               => 'file_price',
                'singular_name'      => 'file_price',
                'add_new'            => 'add',
                'add_new_item'       => 'add_1',
                'edit_item'          => 'edit',
                'new_item'           => 'new',
                'view_item'          => 'view_item',
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => true,
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array('title','editor')
        ) );
    }
}