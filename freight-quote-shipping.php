<?php
 
/**
 * Plugin Name: Freight Quote Shipping
 * Plugin URI: http://code.tutsplus.com/tutorials/create-a-custom-shipping-method-for-woocommerce--cms-26098
 * Description: Custom Shipping Method for WooCommerce
 * Version: 1.0.0
 * Author: Igor Benić
 * Author URI: http://www.ibenic.com
 * License: GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Domain Path: /lang
 * Text Domain: freight
 */
 
if ( ! defined( 'WPINC' ) ) {
    die;
}

include ("FreightQuote.php");
/*
 * Check if WooCommerce is active
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
 
    function freight_quote_shipping_method() {
        if ( ! class_exists( 'Freight_Quote_Shipping_Method' ) ) {
            class Freight_Quote_Shipping_Method extends WC_Shipping_Method {
                /**
                 * Constructor for your shipping class
                 *
                 * @access public
                 * @return void
                 */
                public function __construct() {
                    $countries = new WC_Countries();

                    $this->id                 = 'freight'; 
                    $this->method_title       = __( 'Freight Quote Shipping', 'freight' );  
                    $this->method_description = __( 'Custom Shipping Method for Freight Quote', 'freight' ); 
                    $this->init();
                   
                    // Availability & Countries
                    $this->availability = 'including';

                    $this->countries = array_keys($countries->get_shipping_countries());
                   
                    $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
                    $this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Freight Quote Shipping', 'freight' );
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
                
                     
                     'title' => array(
                        'title' => __( 'Title', 'freight' ),
                          'type' => 'text',
                          'description' => __( 'Title to be display on site', 'freight' ),
                          'default' => __( 'Freight Shipping', 'freight' )
                          ),
                     'username' => array(
                          'title' => __( 'Username', 'freight' ),
                          'type' => 'text',
                          'description' => __( 'Username provided by Freightquote', 'freight' ),
                          ),
                     'password' => array(
                          'title' => __( 'Password', 'freight' ),
                          'type' => 'password',
                          'description' => __( 'Password provided by Freightquote', 'freight' ),
                          ),
                     // 'countries' => array(
                     //      'title' => __( 'Country', 'freight' ),
                     //      'type' => 'multiselect',
                     //      'description' => __( 'Sell to specific countries', 'freight' ),
                     //      'class' => array('my-field-class form-row-wide'),
                     //      'label'         => __('Fill in this field'),
                     //      'placeholder'   => __('Enter something'),
                     //      'default' => 'US',
                     //      'options' => (new WC_Countries())->get_countries()
                     //      ),
                      'enabled' => array(
                          'title' => __( 'Enable', 'freight' ),
                          'type' => 'checkbox',
                          'description' => __( 'Enable this shipping.', 'freight' ),
                          'default' => 'no'
                          ),
                      'zipcode_field' => array(
                          'title' => __( 'Custom Zipcode Field Name', 'freight' ),
                          'type' => 'text',
                          'description' => __( '', 'freight' ),
                          ),
                      'zipcode' => array(
                          'title' => __( 'Default Zipcode', 'freight' ),
                          'type' => 'text',
                          'description' => __( '', 'freight' ),
                          ),
 

                     );
 
                }
 
                /**
                 * This function is used to calculate the shipping cost. Within this function we can check for weights, dimensions and other parameters.
                 *
                 * @access public
                 * @param mixed $package
                 * @return void
                 */
                public function calculate_shipping( $package ) {

                    $countries = new WC_Countries();
                    
                    $Freight_Quote_Shipping_Method = new Freight_Quote_Shipping_Method();
                    $settings =  $Freight_Quote_Shipping_Method->settings;

                    if(empty($settings['username']) || empty($settings['password'])){

                        $message = sprintf('Username or Password is missing for Freight Quote.');
                        $messageType = "error";
 
                        if( ! wc_has_notice( $message, $messageType ) ) {
                            wc_add_notice( $message, $messageType );
                        }
                    }
                   
                    $credentails = array(
                      'username' => $settings['username'],
                      'password' => $settings['password']
                    );
                    $cost = 0;
                      
                    if(isset($package["destination"])&&$package["destination"]['postcode']&&$package["destination"]['country']){
                      $freightquote = new Freightquote($credentails);

                      foreach ( $package['contents'] as $item_id => $values ) 
                      { 
                        $_product = $values['data']; 

                        $request = array(
                          'CustomerId'=> $settings['username'],
                          'QuoteType' => 'B2B',
                          'ServiceType' => 'LTL',
                          'QuoteShipment' => array(
                              'IsBlind' => 'false',
                              'ShipmentLocations' => array(
                                  'Location' => array(
                                      array(
                                          'LocationType' => 'Origin',
                                          'LocationAddress' => array(
                                              'PostalCode' => $_product->get_attribute('pa_'.$settings['zipcode_field']) ? $_product->get_attribute('pa_'.$settings['zipcode_field']) : $settings['zipcode'],
                                              'CountryCode' => $countries->get_base_country(),
                                          ),
                                      ),
                                      array(
                                        'LocationType' => 'Destination',
                                        'LocationAddress' => array(
                                            'PostalCode' => $package["destination"]['postcode'],
                                            'CountryCode' => $package["destination"]['country'],
                                        ),
                                        
                                      ),
                                  ),
                              ),
                              'ShipmentProducts' => array(
                                  'Product' => array(
                                    'Weight' => $_product->get_weight(),
                                    'Length' => $_product->get_length(),
                                    'Height' => $_product->get_height(),
                                    'Width' => $_product->get_width(),
                                    'ProductDescription' => $_product->get_name(),
                                    'PackageType' => 'Boxes',
                                    'ContentType' => 'NewCommercialGoods',
                                    'IsHazardousMaterial' => 'false',
                                    'PieceCount' => $values['quantity'],
                                  )
                              ),
                          ),
                        );
                        $response = $freightquote->getQuotes($request);
                        // echo "<pre>";print_r($response);echo "</pre>";die();

                        if($response && isset($response['status']) && $response['status']){
                          $cost  = $response['data']['QuoteCarrierOptions'][0]['CarrierOption'][0]['QuoteAmount'] + $cost;
                        }elseif(isset($response['error'])){
                          $message = sprintf(isset($response['error']['ErrorMessage'])?$response['error']['ErrorMessage']:'Error in FreightQuote Api');
                          $messageType = "error";
   
                          if( ! wc_has_notice( $message, $messageType ) ) {
                              wc_add_notice( $message, $messageType );
                          }
                        }
                      
                      }
                      if($cost){

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
        }
    }

    add_filter( 'woocommerce_cart_ready_to_calc_shipping', 'disable_shipping_on_cart' );
    add_filter( 'woocommerce_cart_needs_shipping', 'disable_shipping_on_cart' );
    function disable_shipping_on_cart( $enabled ){
        return is_checkout() ? true : false;
    }
 
    add_action( 'woocommerce_shipping_init', 'freight_quote_shipping_method' );
    
    function add_freight_quote_shipping_method( $methods ) {
        $methods[] = 'Freight_Quote_Shipping_Method';
        return $methods;
    }
 
    add_filter( 'woocommerce_shipping_methods', 'add_freight_quote_shipping_method' );
 
}