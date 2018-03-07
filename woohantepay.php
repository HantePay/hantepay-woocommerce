<?php
/**
 * Plugin Name: HantePay Gateway for WooCommerce
 * Plugin Name: 
 * Description: Allows you to use UnionPay, AliPay and WechatPay through HantePay Gateway
 * Version: 1.0.0
 * Author: hantepay
 * Author URI: https://www.hantepay.com
 *
 * @package HantePay Gateway for WooCommerce
 * @author hantepay
 */

add_action('plugins_loaded', 'init_woocommerce_hantepay', 0);

function init_woocommerce_hantepay() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	class woocommerce_hantepay extends WC_Payment_Gateway{
		
		public function __construct() {

			global $woocommerce;
			
			$plugin_dir = plugin_dir_url(__FILE__);
	        
	        $this->id               = 'hantepay';
	        $this->icon     		= apply_filters( 'woocommerce_hantepay_icon', ''.$plugin_dir.'/hantepay_methods.png' );
	        $this->has_fields       = true;

	        $this->init_form_fields();
	        $this->init_settings();

	        // variables            
	        $this->title            = $this->settings['title'];
			$this->token			= $this->settings['token'];
			$this->mode             = $this->settings['mode'];
			$this->currency         = $this->settings['currency'];
	        $this->notify_url   	= str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_hantepay', home_url( '/' ) ) );

			
						
			
			if( $this->mode == 'test' ){
				$this->gateway_url = 'http://xiayue2008.imwork.net:8005/route/v1.3/transactions/securepay';
			}else if( $this->mode == 'live' ){
				$this->gateway_url = 'https://api.hantepay.cn/v1.3/transactions/securepay';
			}
	        
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
			if ( $this->icon ) {
				$icon = '<img src="' . $this->force_ssl( $this->icon ) . '" alt="' . $this->title . '" />';
			} 
			return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
		}

	     /**
	     * Check if this gateway is enabled and available in the user's country
	     */
	    function is_valid_for_use() {
	        if (!in_array(get_option('woocommerce_currency'), array('USD','GBP','HKD','JPY','EUR','CAD','CNY'))) 
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
	        <p><?php _e('Hantepay Gateway supports AliPay, WeChatPay and UnionPay.', 'woocommerce'); ?></p>
			<table class="form-table">
	        <?php           
	    		if ( $this->is_valid_for_use() ) :

	    			// Generate the HTML For the settings form.
	    			$this->generate_settings_html();

	    		else :

	    			?>
	            		<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong>: <?php _e( 'Hantepay does not support your store currency.', 'woothemes' ); ?></p></div>
	        		<?php

	    		endif;
	        ?>
	        </table><!--/.form-table-->
	        <?php
		}

	    /**
	    * Initialise Hantepay Settings Form Fields
	    */
	    public function init_form_fields() {
	            
			//  array to generate admin form
	        $this->form_fields = array(
	        	'enabled' => array(
	            				'title' => __( 'Enable/Disable', 'woocommerce' ), 
			                    'type' => 'checkbox', 
			                    'label' => __( 'Enable Hantepay', 'woocommerce' ), 
			                    'default' => 'yes'
							),
				'title' => array(
			                    'title' => __( 'Title', 'woocommerce' ), 
			                    'type' => 'text', 
			                    'description' => __('This is the title displayed to the user during checkout.', 'woocommerce' ), 
			                    'default' => __( 'Hantepay', 'patsatech-woo-hantepay-server' )
			                ),
				'token' => array(
								'title' => __( 'API Token', 'woocommerce' ), 
								'type' => 'text', 
								'description' => __( 'API Token', 'woocommerce' ),
								'default' => ''
				),
				'currency' => array(
								'title' => __( 'Settle Currency', 'woocommerce' ), 
								'type' => 'select', 
								'options' => array( 
													'USD' => 'USD'
													),
								'description' => __( 'Settlement Currency from Hantepay', 'woocommerce' ),
								'default' => 'USD'
				),
				'mode' => array(
								'title' => __('Mode', 'woocommerce'),
			                    'type' => 'select',
			                    'options' => array( 
													'test' => 'Test',
													'live' => 'Live'
													),
			                    'default' => 'live',
								'description' => __( 'Test or Live', 'woocommerce' )
							)
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
			
	        $order = new WC_Order( $order_id );
	        
	        $time_stamp = date("YmdHis");
	        $orderid = $time_stamp . "-" . $order_id;

	        $nhp_arg=array();
			
			$mark_currency = get_option('woocommerce_currency');
	        
	        $nhp_arg['currency']=$this->currency;
	        
	        if($mark_currency =='CNY'){
	        	$nhp_arg['rmb_amount']=$order->order_total * 100;
	        }else{
	        	if($mark_currency != 'JPY') {
					$nhp_arg['amount']=$order->order_total * 100;
				} else {
					$nhp_arg['amount']=$order->order_total;
				}
	        }
			
	        $nhp_arg['ipn_url']=$this->notify_url;
	        $nhp_arg['callback_url']=$order->get_checkout_order_received_url();
	        $nhp_arg['show_url']=$order->get_cancel_order_url();
	        $nhp_arg['reference']=$orderid;
	        $nhp_arg['vendor']=$_POST['vendor'];
			if(isMobile()){
				$nhp_arg['terminal']='WAP';
			}else{
				$nhp_arg['terminal']='ONLINE';
			}
	        //$nhp_arg['terminal']=$this->terminal;
	        $nhp_arg['note']=$order_id;

			
	        $post_values = "";
	        foreach( $nhp_arg as $key => $value ) {
	            $post_values .= "$key=" . $value . "&";
	        }
	        $post_values = rtrim( $post_values, "& " );
			
			$file  = 'log.txt';
			$post_body = json_encode( $nhp_arg);			
			if($f  = file_put_contents($file, $post_body ,FILE_APPEND)){
			   echo "写入成功。<br />";
			}
	        
	        $response = wp_remote_post($this->gateway_url, array( 
											'body' => $post_body,
											'method' => 'POST',
	                						'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer '.$this->token ),
											'sslverify' => FALSE
											));

			if (!is_wp_error($response)) { 
	        	$resp=$response['body'];
			$res=gzcompress(base64_encode(esc_attr($resp)));	
	        	$redirect = $this->force_ssl( WP_PLUGIN_URL ."/" . plugin_basename( dirname(__FILE__) ) . '/redirect.php').'?res='. urlencode($res);			
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
					<li class="wc_payment_method">
						<input id="hantepay_pay_method_alipay" class="input-radio" name="vendor" checked="checked" value="alipay" data-order_button_text="" type="radio" required>
						<label for="hantepay_pay_method_alipay"> AliPay </label>
					</li>
					<li class="wc_payment_method">
						<input id="hantepay_pay_method_wechatpay" class="input-radio" name="vendor" value="wechatpay" data-order_button_text="" type="radio" required>
						<label for="hantepay_pay_method_wechatpay"> WechatPay </label>
					</li>
					<li class="wc_payment_method">
						<input id="hantepay_pay_method_unionpay" class="input-radio" name="vendor" value="unionpay" data-order_button_text="" type="radio" required>
						<label for="hantepay_pay_method_unionpay"> UnionPay </label>
					</li>
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
            @ob_clean();
            $note = $_REQUEST['note'];
            $status=$_REQUEST['status'];

            $wc_order   = new WC_Order( absint( $note ) );

            if($status == 'success'){
            	$wc_order->payment_complete();
            	$woocommerce->cart->empty_cart();
            	wp_redirect( $this->get_return_url( $wc_order ) );
                exit;
            }else{
            	wp_die( "Payment failed. Please try again." );
            }
        }
		
		
	} 

	function isMobile()
		{ 
			// 如果有HTTP_X_WAP_PROFILE则一定是移动设备
			if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))
			{
				return true;
			} 
			// 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
			if (isset ($_SERVER['HTTP_VIA']))
			{ 
				// 找不到为flase,否则为true
				return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
			} 
			// 脑残法，判断手机发送的客户端标志,兼容性有待提高
			if (isset ($_SERVER['HTTP_USER_AGENT']))
			{
				$clientkeywords = array ('nokia',
					'sony',
					'ericsson',
					'mot',
					'samsung',
					'htc',
					'sgh',
					'lg',
					'sharp',
					'sie-',
					'philips',
					'panasonic',
					'alcatel',
					'lenovo',
					'iphone',
					'ipod',
					'blackberry',
					'meizu',
					'android',
					'netfront',
					'symbian',
					'ucweb',
					'windowsce',
					'palm',
					'operamini',
					'operamobi',
					'openwave',
					'nexusone',
					'cldc',
					'midp',
					'wap',
					'mobile'
					); 
				// 从HTTP_USER_AGENT中查找手机浏览器的关键字
				if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT'])))
				{
					return true;
				} 
			} 
			// 协议法，因为有可能不准确，放到最后判断
			if (isset ($_SERVER['HTTP_ACCEPT']))
			{ 
				// 如果只支持wml并且不支持html那一定是移动设备
				// 如果支持wml和html但是wml在html之前则是移动设备
				if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html'))))
				{
					return true;
				} 
			} 
			return false;
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
