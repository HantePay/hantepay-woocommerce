<?php
/**
 * Plugin Name: HantePay Gateway for WooCommerce
 * Plugin Name:
 * Description: Allows you to use AliPay,Unionpay and WechatPay through HantePay Gateway
 * Version: 2.0.1
 * Author: hantepay
 * Author URI: http://www.hante.com
 *
 * @package HantePay Gateway for WooCommerce
 * @author hantepay
 */
require_once 'HantepayApi.php';

$hantepay = new Hantepay();

add_action('plugins_loaded', 'init_woocommerce_hantepay', 0);

function init_woocommerce_hantepay() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	class woocommerce_hantepay extends WC_Payment_Gateway{

		public function __construct() {


			global $woocommerce;

			$plugin_dir = plugin_dir_url(__FILE__);

	        $this->id               = 'hantepay';
			$this->wechatpay_icon   = apply_filters( 'woocommerce_hantepay_wechatpay_icon', ''.$plugin_dir.'/wechatpay_logo.png' );
	        $this->alipay_icon     	= apply_filters( 'woocommerce_hantepay_alipay_icon', ''.$plugin_dir.'/alipay_logo.png' );
			$this->unionpay_icon    = apply_filters( 'woocommerce_hantepay_unionpay_icon', ''.$plugin_dir.'/unionpay_logo.png' );
	        $this->has_fields       = true;

	        $this->init_form_fields();
	        $this->init_settings();

	        // variables
	        $this->title            = $this->settings['title'];
			$this->key			    = $this->settings['key'];
			$this->mode             = $this->settings['mode'];
			$this->currency         = $this->settings['currency'];
            $this->merchantNo		= $this->settings['merchantNo'];
            $this->storeNo			= $this->settings['storeNo'];
            $this->notify_url   	= add_query_arg('wc-api', 'wc_hantepay', home_url('/'));
			if( $this->mode == 'live' ){
				$this->gateway_url = 'https://gateway.hantepay.com/v2/gateway/woopay';
			}
			$this->weChatPayShow = $this->settings['weChatPayShow'];
            $this->aliPayShow = $this->settings['aliPayShow'];
            $this->unionPayShow = $this->settings['unionPayShow'];
            $this->creditCardShow = $this->settings['creditCardShow'];


	        // actions
			add_action( 'woocommerce_receipt_hantepay', array( $this, 'receipt_page' ) );
	        add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_wc_hantepay', array( $this, 'check_ipn_response' ) );

			if ( !$this->is_valid_for_use() ) {
				$this->enabled = false;
			}
		}

		/**
		 * get_icon function.
		 *
		 * @access public
		 * @return string
		 */
		function get_icon() {
			global $woocommerce;
			$icon = '';
			if ( $this->wechatpay_icon &&  $this->weChatPayShow == 'yes') {
				$icon.= '<img src="' . $this->force_ssl( $this->wechatpay_icon ) . '" alt="' . $this->title . '" width="29" height="26" />';
			}
			if ( $this->alipay_icon &&  $this->aliPayShow == 'yes') {
				$icon.= '<img src="' . $this->force_ssl( $this->alipay_icon ) . '" alt="' . $this->title . '" width="26" height="26" />';
			}
			if ( $this->unionpay_icon &&  $this->unionPayShow == 'yes') {
				$icon.= '<img src="' . $this->force_ssl( $this->unionpay_icon ) . '" alt="' . $this->title . '" width="26" height="26" />';
			}
			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

	     /**
	     * Check if this gateway is enabled and available in the user's country
	     */
	    function is_valid_for_use() {
	        if (!in_array(get_option('woocommerce_currency'), array('USD','CNY')))
	        	return false;

	        return true;
	    }

	    /**
	    * Admin Panel Options
	    **/
	    public function admin_options()
	    {
			?>
	        <h3><?php _e('Hantepay', 'woocommerce'); ?></h3>
	        <p><?php _e('HantePay Gateway supports AliPay,Unionpay,CreditCard and WeChatPay.', 'woocommerce'); ?></p>
			<table class="form-table">
	        <?php
	    		if ( $this->is_valid_for_use() ) :

	    			// Generate the HTML For the settings form.
	    			$this->generate_settings_html();

	    		else :

	    			?>
	            		<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong>: <?php _e( 'HantePay does not support your store currency.', 'woothemes' ); ?></p></div>
	        		<?php

	    		endif;
	        ?>
	        </table><!--/.form-table-->
	        <?php
		}

	    /**
	    * Initialise HantePay Settings Form Fields
	    */
	    public function init_form_fields() {

			//  array to generate admin form
	        $this->form_fields = array(
	        	'enabled' => array(
	            				'title' => __( 'Enable/Disable', 'woocommerce' ),
			                    'type' => 'checkbox',
			                    'label' => __( 'Enable HantePay', 'woocommerce' ),
			                    'default' => 'yes'
							),
				'title' => array(
			                    'title' => __( 'Title', 'woocommerce' ),
			                    'type' => 'text',
			                    'description' => __('This is the title displayed to the user during checkout.', 'woocommerce' ),
			                    'default' => __( 'HantePay', 'patsatech-woo-hantepay-server' )
			                ),
				'key' => array(
								'title' => __( '商户密钥', 'woocommerce' ),
								'type' => 'text',
								'description' => __( '商户密钥', 'woocommerce' ),
								'default' => ''
				),
                'storeNo' => array(
                    'title' => __( '门店编号', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( '门店编号', 'woocommerce' ),
                    'default' => ''
                ),
                'merchantNo' => array(
                    'title' => __( '商户号', 'woocommerce' ),
                    'type' => 'text',
                    'description' => __( '商户号', 'woocommerce' ),
                    'default' => ''
                ),
				'currency' => array(
								'title' => __( '结算币种', 'woocommerce' ),
								'type' => 'select',
								'options' => array(
													'USD' => 'USD',
													),
								'description' => __( 'HantePay结算币种', 'woocommerce' ),
								'default' => 'USD'
				),
				'mode' => array(
								'title' => __('模式', 'woocommerce'),
			                    'type' => 'select',
			                    'options' => array(
													'live' => 'Live'
													),
			                    'default' => 'live',
								'description' => __( '模式', 'woocommerce' )
							),
                'weChatPayShow' => array(
                                    'title' => __( '微信支付', 'woocommerce' ),
                                    'type' => 'checkbox',
                                    'label' => __( '启用微信支付', 'woocommerce' ),
                                    'default' => 'no'
                                ),
                 'aliPayShow' => array(
                                    'title' => __( '支付宝支付', 'woocommerce' ),
                                    'type' => 'checkbox',
                                    'label' => __( '启用支付宝支付', 'woocommerce' ),
                                    'default' => 'no'
                                ),
                'unionPayShow' => array(
                                    'title' => __( '银联支付', 'woocommerce' ),
                                    'type' => 'checkbox',
                                    'label' => __( '启用银联支付', 'woocommerce' ),
                                    'default' => 'no'
                                ),
                'creditCardShow' => array(
                                                    'title' => __( '信用卡支付', 'woocommerce' ),
                                                    'type' => 'checkbox',
                                                    'label' => __( '启用信用卡支付', 'woocommerce' ),
                                                    'default' => 'no'
                                                ),

				);
		}

		/**
		 * Generate the hantepayserver button link
		 **/
	    public function generate_hantepay_form( $order_id ) {
			global $woocommerce;
	        $order = new WC_Order( $order_id );

			wc_enqueue_js('
					jQuery("body").block({
							message: "<img src=\"'.esc_url( $woocommerce->plugin_url() ).'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to verify your card.', 'woothemes').'",
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
					jQuery("#submit_hantepay_payment_form").click();
				');

				return '<form action="'.esc_url( get_transient('hantepay_next_url') ).'" method="post" id="hantepay_payment_form">
						<input type="submit" class="button alt" id="submit_hantepay_payment_form" value="'.__('Submit', 'woothemes').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order', 'woothemes').'</a>
					</form>';

		}

		/**
		*
	    * process payment
	    *
	    */
	    function process_payment( $order_id ) {
			global $woocommerce;
            global $hantepay;
	        $order = new WC_Order( $order_id );

	        $time_stamp = date("YmdHis");
	        $out_trade_no = $time_stamp . "-" . $order_id;
			
			//woocommerce设置货币单位
			$mark_currency = get_option('woocommerce_currency');
			//订单金额
			$oder_total =  ( WC()->version < '2.7.0' ) ? $order->order_total : $order->get_total();

	        $paymentRequest[]=array();
	        $paymentRequest['merchant_no'] = $this->merchantNo;
            $paymentRequest['store_no'] = $this->storeNo;
            $paymentRequest['nonce_str'] = $hantepay->getNonceStr();
            $paymentRequest['time'] = $hantepay->getMillisecond();
			$paymentRequest['out_trade_no'] = $out_trade_no;
			if($mark_currency =='CNY'){
	        	$paymentRequest['rmb_amount']=$oder_total * 100;
	        }else{
	        	if($mark_currency != 'JPY') {
					$paymentRequest['amount']=$oder_total * 100;
				} else {
					$paymentRequest['amount']=$oder_total;
				}
	        }
	        $paymentRequest['currency']=$this->currency;
			$paymentRequest['payment_method']=$_POST['vendor'];
			if( $hantepay->isMobile()){
				$paymentRequest['terminal']='WAP';
			}else{
				$paymentRequest['terminal']='ONLINE';
			}
	        $paymentRequest['notify_url'] = $this->notify_url;
	        $paymentRequest['callback_url']=$order->get_checkout_order_received_url();
	        $paymentRequest['body'] = "woocommerce";
	        $paymentRequest['note']=$order_id;
			$paymentRequest['signature'] = $hantepay->generateSign($paymentRequest,$this->key);
			$paymentRequest['sign_type'] = "MD5";


	        $post_values = "";
	        foreach( $paymentRequest as $key => $value ) {
	            $post_values .= "$key=" . $value . "&";
	        }
	        $post_values = rtrim( $post_values, "& " );

	        $response = wp_remote_post($this->gateway_url, array(
											'body' => $post_values,
											'method' => 'POST',
	                						'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded', 'Authorization' => 'Bearer '.$this->token ),
											'sslverify' => FALSE
											));
			if (!is_wp_error($response)) {
	        	$resp=$response['body'];

                $result =  json_decode($resp, false);
                $redirect = "";
                if($result->return_code == "ok" && $result->result_code == "SUCCESS"){
                    $redirect = $this->force_ssl($result->data->pay_url);
                }else{
                    $redirect = $this->force_ssl( WP_PLUGIN_URL ."/" . plugin_basename( dirname(__FILE__) ) . '/redirect.php').'?res='. base64_encode($resp);
                }
	        	return array(
					'result' 	=> 'success',
					'redirect'	=> $redirect
				);
	        }else{
	        	$woocommerce->add_error( __('Gateway Error.', 'woocommerce') );
	        }
		}
		/**
		 * Payment form on checkout page
		 */
		function payment_fields() {
				global $woocommerce;
				?>
				<?php if ($this->description) : ?><p><?php echo $this->description; ?></p><?php endif; ?>
				<fieldset>
				<legend><label>Method of payment<span class="required">*</span></label></legend>
				<ul class="wc_payment_methods payment_methods methods">
				    <?php if ($this->aliPayShow == 'yes') : ?>
                        <li class="wc_payment_method">
                            <input id="hantepay_pay_method_alipay" class="input-radio" name="vendor" checked="checked" value="alipay" data-order_button_text="" type="radio" required>
                            <label for="hantepay_pay_method_alipay"> AliPay </label>
                        </li>
					<?php endif; ?>
                    <?php if ($this->weChatPayShow == 'yes') : ?>
                        <li class="wc_payment_method">
                            <input id="hantepay_pay_method_wechatpay" class="input-radio" name="vendor" value="wechatpay" data-order_button_text="" type="radio" required>
                            <label for="hantepay_pay_method_wechatpay"> WechatPay </label>
                        </li>
					<?php endif; ?>
					<?php if ($this->unionPayShow == 'yes') : ?>
                        <li class="wc_payment_method">
                            <input id="hantepay_pay_method_unionpay" class="input-radio" name="vendor" value="unionpay" data-order_button_text="" type="radio" required>
                            <label for="hantepay_pay_method_unionpay"> Unionpay </label>
                        </li>
					<?php endif; ?>
                    <?php if ($this->creditCardShow == 'yes') : ?>
                        <li class="wc_payment_method">
                            <input id="hantepay_pay_method_credit_card" class="input-radio" name="vendor" value="creditcard" data-order_button_text="" type="radio" required>
                            <label for="hantepay_pay_method_credit_card"> Credit Card Pay </label>
                        </li>
					<?php endif; ?>
				</ul>
				<div class="clear"></div>
				</fieldset>
				<?php
		 }
		/**
		 * receipt_page
		 **/
		function receipt_page( $order ) {
			global $woocommerce;
			echo '<p>'.__('Thank you for your order.', 'woothemes').'</p>';

			echo $this->generate_hantepay_form( $order );

		}

		private function force_ssl($url){
			if ( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$url = str_replace( 'http:', 'https:', $url );
			}
			return $url;
		}

        function check_ipn_response() {
            global $woocommerce;
            global $hantepay;
            @ob_clean();
            $note = $_REQUEST['note'];
            $status=$_REQUEST['trade_status'];
            $signature = $_REQUEST['signature'];
            $data = $_REQUEST;
            $wc_order   = new WC_Order( absint( $note ) );
            if(!$signature == $hantepay ->generateSign($data,key)){
                wp_die( "Payment failed. Please try again." );
            }
            if($status == 'success'){
                $wc_order->payment_complete();
                $woocommerce->cart->empty_cart();
                wp_redirect( $this->get_return_url( $wc_order ) );
                echo 'SUCCESS';
                exit;
            }else{
                wp_die( "Payment failed. Please try again." );
            }
        }
	}

	/**
	 * Add the gateway to WooCommerce
	 **/
	function add_hantepay_gateway( $methods )
	{
	    $methods[] = 'woocommerce_hantepay';
	    return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_hantepay_gateway' );
}
