<?php
/*
Plugin Name: WooCommerce Paysolution Gateway
Plugin URI: http://www.thaiepay.com/
Description: Extends WooCommerce with a Paysolution gateway.
Version: 1.0
Author: Paysolution
Author URI: http://www.thaiepay.com/


*/
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	
	add_action('plugins_loaded', 'woocommerce_paysolution_init', 0);
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_paysolution_gateway' );
	load_plugin_textdomain('wc-paysolution', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
}

function woocommerce_paysolution_init() {
	
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	
	class WC_Gateway_Paysolution extends WC_Payment_Gateway {
		
		var $notify_url;
		
		public function __construct() {
			global $woocommerce;
		
        $this->id			= 'paysolution';
		$this->icon 		= WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/image/paysolution.png';
        $this->has_fields 	= false;
		$this->liveurl 		= 'https://www.thaiepay.com/epaylink/payment.aspx';
        $this->method_title = __( 'Paysolution', 'woocommerce' );
		$this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_Paysolution', home_url( '/' ) ) );
		
		// Load the form fields.
		$this->init_form_fields();
		
		// Load the settings.
		$this->init_settings();
		
		// Define user set variables
			
		$this->merchantid 			= $this->settings['merchantid'];
		$this->title 			= $this->settings['title'];
		$this->description 		= $this->settings['description'];
		$this->email 			= $this->settings['email'];
		
		// Actions
		add_action('woocommerce_receipt_paysolution', array(&$this, 'receipt_page'));
		add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		//API hook
		add_action( 'woocommerce_api_wc_gateway_paysolution', array( $this, 'paysolution_response' ) );
		
		
		}//end __construct
		
		public function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable Paysolution', 'woocommerce' ),
							'default' => 'yes'
						),
								
		
					'merchantid' => array(
							'title' => __( 'Merchantid', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'This controls the merchantid which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'thaiepay', 'woocommerce' ) 
						),
							
				'title' => array(
							'title' => __( 'Title', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'Paysolution', 'woocommerce' ) 
						),

				'description' => array(
							'title' => __( 'Description', 'woocommerce' ),
							'type' => 'textarea',
							'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
							'default' => __("You can pay with Paysolution; You must be Paysolution account.", 'woocommerce')
						),
				'email' => array(
							'title' => __( 'Paysolution Email', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'Please enter your Paysolution email address; this is needed in order to take payment.', 'woocommerce' ),
							'default' => ''
						)
			);					   
		}//end init_form_fields
		
		public function admin_options() {

			echo '<h3>' . _e('Paysolution','woocommerce') . '</h3>';
    		echo '<p>' . _e('Make it easier!', 'woocommerce' ) . '</p>';
    		echo '<table class="form-table">';
        
    		$this->generate_settings_html(); 
			echo '</table>';
		}//end admin_options
		
		function get_paysolution_args( $order ) {
			global $woocommerce;
		
		$order_id = $order->id;
		
		$item_names = array();

		if (sizeof($order->get_items())>0) : foreach ($order->get_items() as $item) :
		if ($item['qty']) $item_names[] = $item['name'] . ' x ' . $item['qty'];
			endforeach; endif;
			
		$paysolution_args['item_name'] 	= sprintf( __('Order %s' , 'woocommerce'), $order->get_order_number() ) . " - " . implode(', ', $item_names);
		
		
		
		$paysolution_args = array(
				  'paysolution'            => "paysolution",
				 'customeremail' 		=> $this->email,
				  'merchantid' 			=> $this->merchantid,
				  'refno'				=> $order_id,
				  'productdetail'     => $paysolution_args['item_name'],
				  'total'				=> $order->get_total(),
				  'postURL'				=> $this->get_return_url($order),
				  'opt_fix_redirect'	=> "1",
				  'reqURL'         		=> $this->notify_url
				  
	     );
		
		$paysolution_args = apply_filters( 'woocommerce_paysolution_args', $paysolution_args );

		return $paysolution_args;
		}//end paysolution_args
		
		function generate_paysolution_form( $order_id ) {
			global $woocommerce;

			$order = new WC_Order( $order_id );
		
			$paysolution_adr = $this->liveurl . '?';

			$paysolution_args = $this->get_paysolution_args( $order );

			$paysolution_args_array = array();

			foreach ($paysolution_args as $key => $value) {
				$paysolution_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
			}

			$woocommerce->add_inline_js('
				jQuery("body").block({
						message: "<img src=\"' . esc_url( apply_filters( 'woocommerce_ajax_loader_url', WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/image/ajax-loader.gif' ) ) . '\" alt=\"Redirecting&hellip;\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Paysolution to make payment.', 'woocommerce').'",
					overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
							css: {
					        padding:        20,
					        textAlign:      "center",
					        color:          "#555",
					        border:         "3px solid #aaa",
					        backgroundColor:"#fff",
					        cursor:         "wait",
					        lineHeight:		"32px"
					    }
					});
				jQuery("#submit_paysolution_payment_form").click();
			');

			return '<form action="'.esc_url( $paysolution_adr ).'" method="post" id="paysolution_payment_form" target="_top">
					' . implode('', $paysolution_args_array) . '
					<input type="submit" class="button-alt" id="submit_paysolution_payment_form" value="'.__('Pay via Paysolution', 'woocommerce').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order &amp; restore cart', 'woocommerce').'</a>
				</form>';

		}//end generate_paysbuy_form
		
		function receipt_page( $order ) {
			
			echo '<p>'.__('Thank you for your order, please click the button below to pay with Paysolution.', 'woocommerce').'</p>';

			echo $this->generate_paysolution_form( $order );
			
		}//end receipt_page
		
		function paysolution_response() {
			global $woocommerce;
				if(isset($_REQUEST['result']) && isset($_REQUEST['apCode']) && isset($_REQUEST['total'])){
				$order_id = trim(substr($_POST["result"],2));
				$order = new WC_Order( $order_id );
				
					$result = $_POST["result"];
					$result = substr($result, 0, 2);
					$apCode = $_POST["apCode"];
					$total = $_POST["total"];
					$fee = $_POST["fee"];
					$method = $_POST["method"];
					
					if($result == '00'){
						$order->payment_complete();
						$woocommerce->cart->empty_cart();
					}
					else if ($result == '99'){
						$order->update_status('failed', __('Payment Failed', 'woothemes'));
						$woocommerce->cart->empty_cart();
					}
					else if ($result == '02'){
						$order->update_status('on-hold', __('Awaiting Counter Service payment', 'woothemes'));
						$woocommerce->cart->empty_cart();
					}
				}
	
		}//end paysolution_response
		
		function process_payment( $order_id ) {
    		global $woocommerce;
    	
			$order = new WC_Order( $order_id );
		
			return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
				);
		}//end process_payment
	
	}//end class WC_Paysbuy	

	
	function woocommerce_add_paysolution_gateway($methods) {
		$methods[] = 'WC_Gateway_Paysolution';
		return $methods;
	}//end woocommerce_add_paysolution_gateway
}//end
?>