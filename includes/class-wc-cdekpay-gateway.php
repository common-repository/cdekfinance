<?php

/**
 * Cdekpay Payment Gateway.
 *
 * @class       Gateway_Cdekpay
 * @extends     WC_Payment_Gateway
 * @version     2.1.0
 * @package     WooCommerce/Classes/Payment
 */
class Cdekpay_Gateway extends WC_Payment_Gateway {

    public $sdk;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
        $this->supports = array(
            'products',
            'refunds',
        );

		// Setup general properties.
		$this->setup_properties();

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Get settings.
        $settings = $this->get_settings();
        $order_statuses = [
            'pending'        => 'wc-pending',
            'paid'           => 'wc-processing',
            'refund'         => 'wc-refund',
            'refunded'       => 'wc-refunded',
            'void'           => 'wc-canceled',
        ];

        $this->initSDK($settings, $order_statuses);

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

        // Customer Emails.
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
	}

    protected function get_settings()
    {
        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );

        $this->enable_for_methods = $this->get_option( 'enable_for_methods', array() );
        $this->enable_for_virtual = $this->get_option( 'enable_for_virtual', 'yes' ) === 'yes';

        return [
            'merchant_login'        => $this->get_option( 'merchant_login' ),
            'secret_key'            => $this->get_option( 'secret_key' ),
            'secret_key_test'       => $this->get_option( 'secret_key_test' ),
            'accounts_test'         => $this->get_option( 'accounts_test' ),
            'mode_test'             => ( $this->get_option( 'mode_test' ) === 'yes'),
        ];
    }

    protected function initSDK($settings, $order_statuses)
    {

        if (!class_exists('CdekpaySDK')) {
            require_once plugin_dir_path(dirname(__FILE__)) . '/includes/CdekpaySDK.php';
        }

        $this->sdk = new CdekpaySDK($settings, $order_statuses);
    }

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties()
    {
		$this->id                 = 'cdekpay';
		$this->method_title       = esc_html(__( 'CDEK PAY', 'cdekpay' ));
        $this->method_description = sprintf(
            esc_html__( 'Payment by card or QR-code in the system of quick payments', 'cdekpay' ),
            '<a href="'.esc_url("admin.php?page=cdekpay",'cdekpay').'">' . esc_html__( 'Reference', 'cdekpay' ) . '</a>'
        );
        $this->has_fields         = false;
	}

    /**
     * Can the order be refunded via Cdekpay?
     *
     * @param  WC_Order $order Order object.
     * @return bool
     */
    public function can_refund_order( $order )
    {
        if ( $this->get_option( 'mode_test' ) === 'yes' ) {
            $has_api_credentials = $this->get_option( 'merchant_login' ) && $this->get_option( 'secret_key_test' );
        } else {
            $has_api_credentials = $this->get_option( 'merchant_login' ) && $this->get_option( 'secret_key' );
        }

        return $order && $order->get_transaction_id() && $has_api_credentials;
    }

    public function process_refund( $order_id, $amount = null, $reason = '' )
    {
        $order          = wc_get_order($order_id);
        $transaction_id = $order->get_transaction_id();
        $refund_amount  = $this->sdk::convertRublesInPennies($amount);

        if ($refund_amount === 0) {
            $msg = sprintf("process_refund (refund_amount = 0) - wc_order_id: %d | transaction_id: %s | amount: %s", $order_id, $transaction_id, $amount);
            Cdekpay_Logger::error( $msg );

            return false;
        }
        $refundItems = [];
		if(!wp_verify_nonce(wc_get_post_data_by_key('security'),'order-item' ) > 0){
			Cdekpay_Logger::error('Nonce not valid');

			return false;
		}

	    $items =  json_decode(wc_get_post_data_by_key('line_item_totals',[]),true);
	    $itemsqnty =  json_decode(wc_get_post_data_by_key('line_item_qtys',[]),true);

        $orderItems = $order->get_items();
        foreach($items as $k=>$item){
            if((int)$item > 0){
                if(isset($orderItems[$k])){
                    $product    = $orderItems[$k]->get_product();
                    $tags = $product->tag_ids;
                    $paymentObject = 1;
                    foreach($tags as $tag) {
                        if (preg_match('/услуг/', strtolower(get_term($tag)->name))) {
                            $paymentObject = 4;
                        }
                    }
                    $qnt = isset($itemsqnty[$k])? $itemsqnty[$k]: 1;

	                $regPrice = $product->get_regular_price();
	                if($regPrice*$qnt !=$item){
		                Cdekpay_Logger::error('Product price or quantity is not correct');
		                throw new Exception( sprintf(__( 'Product price(%s) or quantity(%s) is not correct.', 'cdekpay' ), $regPrice, $qnt ) );
		                return false;
	                }
                    $refundItems[] = [
                        'name' =>$product->get_name(),
                        'price' =>$this->sdk::convertRublesInPennies($item/$qnt),
                        'quantity' =>$qnt,
                        'sum' =>$this->sdk::convertRublesInPennies($item),
                        'payment_object' =>$paymentObject
                    ];
                }
            }
        }

        $cdekPayResponse = $this->sdk->initRefundPayment($order_id,$transaction_id, $refund_amount,$refundItems);

        if (isset($cdekPayResponse['error'])) {
            $msg = sprintf("process_refund (sdk->initRefundPayment) - wc_order_id: %d | transaction_id: %s | amount: %s | response error : %s", $order_id, $transaction_id, $amount, $cdekPayResponse['error']);
            Cdekpay_Logger::error($msg);
	        throw new Exception( $cdekPayResponse['error']);
            return false;
        }

        $this->check_payment_status($order_id);
	    $msg = sprintf( esc_html(__( 'Refund requested for sum %s', 'cdekpay' )), $amount);

	    $order->add_order_note($msg);
        return true;
    }

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'            => array(
                'title'       => esc_html(__( 'On/Off', 'cdekpay' )),
                'label'       => esc_html(__( 'Turn on payment CDEKPAY', 'cdekpay' )),
                'type'        => 'checkbox',
                'description' => esc_html(__('Set checkbox for payment method activation', 'cdekpay' )),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'title'              => array(
                'title'       => esc_html(__( 'Title', 'cdekpay' )),
                'type'        => 'text',
                'description' => esc_html(__( 'The title of the payment method that the buyer will see when placing an order.', 'cdekpay' )),
                'default'     => esc_html(__( 'CDEK PAY', 'cdekpay' )),
                'desc_tip'    => true,
            ),
            'description'        => array(
                'title'       => esc_html(__( 'Description', 'cdekpay' )),
                'type'        => 'textarea',
                'description' => esc_html(__( 'The description of the payment method that the buyer will see', 'cdekpay' )),
                'default'     => esc_html(__('Payment by bank card or via QR code in the Fast Payments System.', 'cdekpay' )),
                'desc_tip'    => true,
            ),
            'merchant_login'  => array(
                'title'       => esc_html(__( 'Login', 'cdekpay' )),
                'type'        => 'text',
                'description' => esc_html(__( 'Private  https://secure.cdekfin.ru -> Settings -> Edit Shop -> Field', 'cdekpay' )),
                'desc_tip'    => true,
            ),
            'secret_key'      => array(
                'title'       => esc_html(__( 'Secret Key', 'cdekpay' )),
                'type'        => 'text',
                'description' => esc_html(__( 'Private https://secure.cdekfin.ru -> Integration -> Settings API -> Field Secret Key', 'cdekpay' )),
                'desc_tip'    => true,
            ),
            'secret_key_test' => array(
                'title'       => esc_html(__( 'Test Secret Key', 'cdekpay' )),
                'type'        => 'text',
                'description' => esc_html(__( 'Private https://secure.cdekfin.ru -> Integration -> Settings API -> Field Test Secret Key', 'cdekpay' )),
                'desc_tip'    => true,
            ),
            'accounts_test'   => array(
                'title'       => esc_html(__( 'Test payment account', 'cdekpay' )),
                'type'        => 'text',
                'description' => esc_html(__( 'authorized user email', 'cdekpay' )),
                'desc_tip'    => true,
            ),
            'mode_test'       => array(
                'title'       => esc_html(__( 'Test mode', 'cdekpay' )),
                'label'       => esc_html(__( 'Turn on Test mode', 'cdekpay' )),
                'type'        => 'checkbox',
                'description' => esc_html(__( 'For Test mode', 'cdekpay' )),
                'default'     => 'no',
                'desc_tip'    => true,
            ),
            'webhook'   => array(
                'title'       => esc_html(__( 'URL for webhook', 'cdekpay' )),
                'type'        => 'text',
                'desc_tip'    => true,
                'description'        => esc_html(__('Private https://secure.cdekfin.ru -> Integration -> Settings API', 'cdekpay' )),
                'default'     => esc_url(get_rest_url().'cdekpay/v1/webhook/','cdekpay'),
                'custom_attributes' => array(
                    'readonly' => 'readonly',
                ),
            ),
		);
	}

	/**
	 * Check If The Gateway Is Available For Use.
	 *
	 * @return bool
	 */
	public function is_available()
    {
		$order          = null;
		$needs_shipping = false;

		// Test if shipping is needed first.
		if ( WC()->cart && WC()->cart->needs_shipping() ) {
			$needs_shipping = true;
		} elseif ( is_page( wc_get_page_id( 'checkout' ) ) && 0 < get_query_var( 'order-pay' ) ) {
			$order_id = absint( get_query_var( 'order-pay' ) );
			$order    = wc_get_order( $order_id );

			// Test if order needs shipping.
			if ( 0 < count( $order->get_items() ) ) {
				foreach ( $order->get_items() as $item ) {
					$_product = $item->get_product();
					if ( $_product && $_product->needs_shipping() ) {
						$needs_shipping = true;
						break;
					}
				}
			}
		}

		$needs_shipping = apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );

		// Virtual order, with virtual disabled.
		if ( ! $this->enable_for_virtual && ! $needs_shipping ) {
			return false;
		}

		// Only apply if all packages are being shipped via chosen method, or order is virtual.
		if ( ! empty( $this->enable_for_methods ) && $needs_shipping ) {
			$order_shipping_items            = is_object( $order ) ? $order->get_shipping_methods() : false;
			$chosen_shipping_methods_session = WC()->session->get( 'chosen_shipping_methods' );

			if ( $order_shipping_items ) {
				$canonical_rate_ids = $this->get_canonical_order_shipping_item_rate_ids( $order_shipping_items );
			} else {
				$canonical_rate_ids = $this->get_canonical_package_rate_ids( $chosen_shipping_methods_session );
			}

			if ( ! count( $this->get_matching_rates( $canonical_rate_ids ) ) ) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * Checks to see whether or not the admin settings are being accessed by the current request.
	 *
	 * @return bool
	 */
	private function is_accessing_settings()
    {
		if ( is_admin() ) {
			// phpcs:disable WordPress.Security.NonceVerification
			if ( ! isset( $_REQUEST['page'] ) || 'wc-settings' !== $_REQUEST['page'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['tab'] ) || 'checkout' !== $_REQUEST['tab'] ) {
				return false;
			}
			if ( ! isset( $_REQUEST['section'] ) || 'cdekpay' !== $_REQUEST['section'] ) {
				return false;
			}
			// phpcs:enable WordPress.Security.NonceVerification

			return true;
		}

		return false;
	}

	/**
	 * Loads all of the shipping method options for the enable_for_methods field.
	 *
	 * @return array
	 */
	private function load_shipping_method_options()
    {
		// Since this is expensive, we only want to do it if we're actually on the settings page.
		if ( ! $this->is_accessing_settings() ) {
			return array();
		}

		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();

		foreach ( $raw_zones as $raw_zone ) {
			$zones[] = new WC_Shipping_Zone( $raw_zone );
		}

		$zones[] = new WC_Shipping_Zone( 0 );

		$options = array();
		foreach ( WC()->shipping()->load_shipping_methods() as $method ) {

			$options[ $method->get_method_title() ] = array();

			// Translators: %1$s shipping method name.
			$options[ $method->get_method_title() ][ $method->id ] = sprintf( esc_html(__( 'Any &quot;%1$s&quot; method', 'cdekpay' )), $method->get_method_title() );

			foreach ( $zones as $zone ) {

				$shipping_method_instances = $zone->get_shipping_methods();

				foreach ( $shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance ) {

					if ( $shipping_method_instance->id !== $method->id ) {
						continue;
					}

					$option_id = $shipping_method_instance->get_rate_id();

					// Translators: %1$s shipping method title, %2$s shipping method id.
					$option_instance_title = sprintf( esc_html(__( '%1$s (#%2$s)', 'cdekpay' )), $shipping_method_instance->get_title(), $shipping_method_instance_id );

					// Translators: %1$s zone name, %2$s shipping method instance name.
					$option_title = sprintf( esc_html(__( '%1$s &ndash; %2$s', 'cdekpay' )), $zone->get_id() ? $zone->get_zone_name() : __( 'Other locations', 'cdekpay' ), $option_instance_title );

					$options[ $method->get_method_title() ][ $option_id ] = $option_title;
				}
			}
		}

		return $options;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $order_shipping_items  Array of WC_Order_Item_Shipping objects.
	 * @return array $canonical_rate_ids    Rate IDs in a canonical format.
	 */
	private function get_canonical_order_shipping_item_rate_ids( $order_shipping_items )
    {

		$canonical_rate_ids = array();

		foreach ( $order_shipping_items as $order_shipping_item ) {
			$canonical_rate_ids[] = $order_shipping_item->get_method_id() . ':' . $order_shipping_item->get_instance_id();
		}

		return $canonical_rate_ids;
	}

	/**
	 * Converts the chosen rate IDs generated by Shipping Methods to a canonical 'method_id:instance_id' format.
	 *
	 * @since  3.4.0
	 *
	 * @param  array $chosen_package_rate_ids Rate IDs as generated by shipping methods. Can be anything if a shipping method doesn't honor WC conventions.
	 * @return array $canonical_rate_ids  Rate IDs in a canonical format.
	 */
	private function get_canonical_package_rate_ids( $chosen_package_rate_ids )
    {

		$shipping_packages  = WC()->shipping()->get_packages();
		$canonical_rate_ids = array();

		if ( ! empty( $chosen_package_rate_ids ) && is_array( $chosen_package_rate_ids ) ) {
			foreach ( $chosen_package_rate_ids as $package_key => $chosen_package_rate_id ) {
				if ( ! empty( $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ] ) ) {
					$chosen_rate          = $shipping_packages[ $package_key ]['rates'][ $chosen_package_rate_id ];
					$canonical_rate_ids[] = $chosen_rate->get_method_id() . ':' . $chosen_rate->get_instance_id();
				}
			}
		}

		return $canonical_rate_ids;
	}

	/**
	 * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
	 *
	 * @since  3.4.0
	 *
	 * @param array $rate_ids Rate ids to check.
	 * @return boolean
	 */
	private function get_matching_rates( $rate_ids )
    {
		// First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
		return array_unique( array_merge( array_intersect( $this->enable_for_methods, $rate_ids ), array_intersect( $this->enable_for_methods, array_unique( array_map( 'wc_get_string_before_colon', $rate_ids ) ) ) ) );
	}

    function check_email($order)
    {
        $customer_email = $order->get_billing_email() ? sanitize_email($order->get_billing_email()): null;

        if( !$this->sdk->getModeTest() || $this->sdk->isAllowedAccount($customer_email) ) {
            return true;
        }

        $msg = esc_html(__('Test emails for testing', 'cdekpay' ));

        $order->update_status( 'wc-pending', $customer_email . $msg );
        $order->add_order_note($msg);
        wc_add_notice( $msg, 'error' );

        return false;
    }

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id )
    {
        $order = wc_get_order( $order_id );

        if ( ! $this->check_email($order) ) {
            return array(
                'result'   => 'error',
            );
        }

        $total = $order->get_total();

        $responseUrlSuccess =  esc_url( get_home_url() . '/wp-json/cdekpay/v1/payment/' . $order->get_order_key() . '/' . $order_id ,'cdekpay');
        $responseUrlFail = esc_url( $this->get_return_url( $order ) . '&fail=true' ,'cdekpay');
	    $orderItems =   array_map( function($item){ return $item->get_data(); }, $order->get_items());
	    $paymentItems = [];
	    foreach($orderItems as $k=>$item){
		            $product = wc_get_product($item['product_id'] );

				    $tags = $product->get_tag_ids();
				    $paymentObject = 1;
				    foreach($tags as $tag) {

					    if (preg_match('/услуг/', strtolower(get_term($tag)->name))) {
						    $paymentObject = 4;
					    }
				    }
		            $qnt = $item['quantity'];
				    $paymentItems[] = [
					    'name' =>$item['name'],
					    'price' =>$this->sdk::convertRublesInPennies($item['total']/$qnt),
					    'quantity' =>$qnt,
					    'sum' =>$this->sdk::convertRublesInPennies($item['total']),
					    'payment_object' =>$paymentObject
				    ];
	    }
	    $orderData = $order->get_data();
		if($orderData['shipping_total'] > 0){
			$paymentItems[] = [
				'name' =>__('Delivery', 'cdekpay' ),
				'price' =>$this->sdk::convertRublesInPennies($orderData['shipping_total']),
				'quantity' =>1,
				'sum' =>$this->sdk::convertRublesInPennies($orderData['shipping_total']),
				'payment_object' =>4
			];
		}

        $response = $this->sdk->initPayment(
            $total,
            $order_id,
            $order->get_billing_phone(),
            $order->get_billing_email(),
            $responseUrlSuccess,
            $responseUrlFail,
	        $paymentItems
        );

        if (isset($response['error'])) {
            Cdekpay_Logger::info('Error process_payment(): '.$response['error']);

            wc_add_notice( $response['error'], 'error' );

            return array(
                'result'   => 'error',
            );
        }
        // create order_payment_info
        $this->create_cdekpay_order($response['access_key'], $order_id, $total);

        // add in order history payment link
        $order->add_order_note(__('Payment link', 'cdekpay').$response['link']);

        // Remove cart.
        WC()->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => $response['link'],
        );
	}

    public function create_cdekpay_order($cdekpay_order_id, $order_id, $total)
    {
        $date = gmdate('Y-m-d H:i:s');

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'cdekpay_orders',
            array(
                'cdekpay_order_id' => $cdekpay_order_id,
                'order_id' => $order_id,
                'order_number' => (string)$order_id,
                'total' => $this->sdk::convertRublesInPennies($total),
                'currency_code' => $this->sdk->getCurrency(),
                'created_at' => $date,
                'updated_at' => $date
            ),
            array(
                '%s', // %d - значит число
                '%d',
                '%s', // %s - значит строка
                '%d',
                '%s',
                '%s',
                '%s',
            )
        );
    }

    public function isAcceptedSignature($data)
    {
        return $this->sdk->isAcceptedSignature($data);
    }

    public function update_order_statuses($cdekpay_order, $payment)
    {
        global $wpdb;

        $payment_status = $payment['status'];
        $cdekpay_order_status = $this->sdk->getPaymentStatuses()[$payment_status];

        $wpdb->update( $wpdb->prefix.'cdekpay_orders',
            [ 'status' => $cdekpay_order_status ],
            [ 'cdekpay_order_id' => $cdekpay_order->cdekpay_order_id ]
        );

        $wc_order = wc_get_order($cdekpay_order->order_id);

        switch ($payment_status) {
            case 'success':
                $transaction_id = $payment['id'];
                if (!$wc_order->get_transaction_id()) {
                    $wc_order->set_transaction_id($transaction_id);
                    $wc_order->save();
                }

                $wc_order_status = $this->sdk->getOrderStatuses()->paid;
                $wc_order->add_order_note(__('Got payment', 'cdekpay').$transaction_id);
                $wc_order->update_status($wc_order_status);
                break;
            case 'cancellation_requested':
                $wc_order_status = $this->sdk->getOrderStatuses()->refund;
                $wc_order->add_order_note(__('Initiated refund of payment CDEK PAY.', 'cdekpay').$payment['id']);
                $wc_order->update_status($wc_order_status);
                break;
            case 'success_cancellation':
                $wc_order_status = $this->sdk->getOrderStatuses()->refund;
                $wc_order->add_order_note(__('Refund successfully completed', 'cdekpay').$payment['id']);
                $wc_order->update_status($wc_order_status);
                break;
            case 'cancelled':
//                $wc_order_status = $this->sdk->getOrderStatuses()->refunded;
//                $wc_order->update_status($wc_order_status);

                $wpdb->update( $wpdb->prefix.'cdekpay_orders',
                    [ 'total_refund' => abs($payment['pay_amount']) ],
                    [ 'cdekpay_order_id' => $cdekpay_order->cdekpay_order_id ]
                );
                break;
        }
    }

    public function check_payment_status(int $order_id = null)
    {
        global $wpdb;
        $statuses_list = $this->sdk->getPaymentStatuses();

        if ($order_id) {
            $cdekpay_order = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}cdekpay_orders WHERE order_id = %s",$order_id ));
            if (!$cdekpay_order) {
                return;
            }

            $cdekpay_orders = [$cdekpay_order];
        } else {
            $status_undefined = $statuses_list['undefined'];
            $status_cancellation = $statuses_list['cancellation_requested'];

            $cdekpay_orders = $wpdb->get_results(
                $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cdekpay_orders WHERE status IN (%s, %s)",$status_undefined,$status_cancellation),
            );

            Cdekpay_Logger::info("check_payment_status count orders: " . count($cdekpay_orders));

            if (!$cdekpay_orders) {
                return;
            }
        }

        foreach ($cdekpay_orders as $cdekpay_order) {
            $cdekPayResponse = $this->sdk->getPayments($cdekpay_order->cdekpay_order_id);

            if (isset($cdekPayResponse['error'])) {
                Cdekpay_Logger::error('order_number: '.$cdekpay_order->order_number." checkStatus response error :" . $cdekPayResponse['error']);
                continue;
            }

            foreach ($cdekPayResponse['payments'] as $payment) {
                if ($cdekpay_order->status !== $statuses_list[$payment['status']] && $cdekpay_order->total_refund === null) {
                    $this->update_order_statuses($cdekpay_order, $payment);
                }
            }
        }
    }

	/**
	 * Output for the order received page.
	 */
	public function thankyou_page()
    {
	    if(isset($_GET['_wpnonce']) && !wp_verify_nonce(sanitize_text_field( wp_unslash ($_GET['_wpnonce'])), 'cdekpay'  )){
			return;
	    }
        $key = isset($_GET[ 'key' ]) ? sanitize_text_field($_GET[ 'key' ]) : false;
        $fail = isset($_GET[ 'fail' ]) ? sanitize_text_field($_GET[ 'fail' ]) : false;

        if (!$key) {
            return;
        }

        if ($fail) {
            // FAIL Payment
            global $wpdb;

            $order_id = wc_get_order_id_by_order_key( $key );

            $cdekpay_order = $wpdb->get_row($wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cdekpay_orders WHERE order_id = %s",$order_id ));
            if (!$cdekpay_order) {

                echo wp_kses_post( wpautop( wptexturize( __('Error Ex01! Please refer to shop admin!', 'cdekpay') ) ) );
                echo wp_kses_post( wpautop( wptexturize( __('Status not paid', 'cdekpay') ) ) );

                return;
            }

            $url = esc_url($this->sdk->getPaymentUrlByOrderId($cdekpay_order->cdekpay_order_id));

            $msg = __('Payment completed with errors. please repeat', 'cdekpay');
            echo wp_kses_post( wpautop( wptexturize( $msg ) ) );
            echo wp_kses_post( wpautop( wptexturize( __("Payment link", 'cdekpay')."<a href=\"$url\">".$url."</a>" ) ) );
            echo wp_kses_post( wpautop( wptexturize( __('Status not paid', 'cdekpay') ) ) );

            return;
        }

        // SUCCESS Payment
        $order_id = wc_get_order_id_by_order_key( $key );
        $wc_order = wc_get_order( $order_id );

        if (!$wc_order->is_paid() && $wc_order->get_status() === 'pending') {
            $this->check_payment_status($order_id);

            $url = home_url(sanitize_text_field($_SERVER['REQUEST_URI']));
            echo "<a href='".esc_url($url)."'>".esc_html(__("Check status of payment", 'cdekpay'))."</a>";
        }

        $payment_status_description = $wc_order->is_paid() ? __('Paid', 'cdekpay') : __('not Paid', 'cdekpay');

        echo wp_kses_post( wpautop( wptexturize( __('pay status', 'cdekpay').$payment_status_description ) ) );
	}

	/**
	 * Change payment complete order status to completed for cdekpay orders.
	 *
	 * @since  3.1.0
	 * @param  string         $status Current order status.
	 * @param  int            $order_id Order ID.
	 * @param  WC_Order|false $order Order object.
	 * @return string
	 */
	public function change_payment_complete_order_status( $status, $order_id = 0, $order = false )
    {
        Cdekpay_Logger::info('change_payment_complete_order_status()');
		if ( $order && 'cdekpay' === $order->get_payment_method() ) {
			$status = 'completed';
		}
		return $status;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false )
    {
		if ( isset($this->instructions) && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
			echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
		}
	}
}