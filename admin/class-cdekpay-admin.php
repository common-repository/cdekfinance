<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://cdekpay.ru
 * @since      1.0.0
 *
 * @package    Cdekpay
 * @subpackage Cdekpay/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Cdekpay
 * @subpackage Cdekpay/admin
 * @author     CdekPay <coder.dlc@yandex.ru>
 */
class Cdekpay_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
     * Register the route webhook for processing status of the payment.
	 * http://domain.loc/wp-json/cdekpay/v1/webhook/
	 */
    public function cdek_add_rest_route_webhook() {
        register_rest_route(
            'cdekpay/v1',
            '/webhook/',
            array(
                'methods'  => 'POST',
                'callback' => array($this, 'process_webhook'),
                'args'     => array(
                    'payment' => array(
                        'type'     => 'json', // значение параметра должно быть строкой
                        'required' => true,   // параметр обязательный
                    ),
                    'signature' => array(
                        'type'     => 'string',
                        'required' => true,
                    ),
                ),
                'permission_callback' => '__return_true'
            )
        );

        register_rest_route(
            'cdekpay/v1',
            '/payment/(?P<key>.+)/(?P<wc_order_id>\d+)',
            array(
                'methods'  => 'GET',
                'callback' => array($this, 'payment_status'),
                'args'     => array(),
                'permission_callback' => '__return_true'
            )
        );
    }

    public function payment_status( WP_REST_Request $request ) {

        $key = $request->get_param( 'key' );
        $wc_order_id = (int)$request->get_param( 'wc_order_id' );

        if (!$key || !$wc_order_id) {
            Cdekpay_Logger::error("rest_route payment: !key || !wc_order_id");

            return new WP_Error( 'invalid_key', 'Invalid key', array( 'status' => 404 ) );
        }

        $order = wc_get_order( $wc_order_id );

        if (!$order instanceof WC_Order) {
            Cdekpay_Logger::error("rest_route payment: order not instanceof WC_Order");

            return new WP_Error( 'invalid_key', 'Invalid key', array( 'status' => 404 ) );
        }

        $order_key = $order->get_order_key();

        if ($order_key !== $key) {
            Cdekpay_Logger::error("rest_route payment: order_key !== key");

            return new WP_Error( 'invalid_key', 'Invalid key', array( 'status' => 404 ) );
        }

        $received_url = $order->get_checkout_order_received_url();

        wp_redirect( $received_url );
        exit;
    }

    /* request
    *  {"payment":{"pay_amount":1000,"order_id":346501994,"id":1377503,"currency":"RUR"},"signature":"9F3266...."}
    *  {"payment":{"refund_amount":1000,"order_id":307824513,"id":1377681,"currency":"RUR"},"signature":"323E...."}
    */
    public function process_webhook( WP_REST_Request $request ) {
        $data = $request->get_params();
        Cdekpay_Logger::info('POST webhook: '. wp_json_encode($data));

        if (!isset($data['payment']) || !isset($data['payment']['order_id']) || !isset($data['signature'])) {
            $msg = __("No payment or order_id or signature in webhook data", 'cdekpay');
            Cdekpay_Logger::error("POST webhook: $msg | ". wp_json_encode($data));

            return rest_ensure_response('OK');
        }

        $cdekpay_order_id = $data['payment']['access_key'];
        global $wpdb;
	    $cdekpay_order = wp_cache_get( 'get_order_'.$cdekpay_order_id, 'cdekpay', false, $found );

	    if ( ! $found) {
		    $cdekpay_order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cdekpay_orders WHERE cdekpay_order_id = %s",$cdekpay_order_id ));
		    wp_cache_set( 'get_order_'.$cdekpay_order_id, $cdekpay_order, 'cdekpay' );
	    }

        if (!$cdekpay_order) {
            $msg = __("Order Cdekpay not found in DB", 'cdekpay');
            Cdekpay_Logger::error("POST webhook: $msg | ". wp_json_encode($data));

            return rest_ensure_response('OK');
        }

        $wc_order = wc_get_order($cdekpay_order->order_id);
        if (!$wc_order) {
            $msg = __("Order WooCommerce not found in DB", 'cdekpay');
            Cdekpay_Logger::error("POST webhook: $msg | ". wp_json_encode($data));

            return rest_ensure_response('OK');
        }

        $payment_gateway = wc_get_payment_gateway_by_order( $wc_order );

        $isAcceptedSignature = $payment_gateway->isAcceptedSignature($data);
        if (!$isAcceptedSignature) {
            $msg = __("Wrong signature", 'cdekpay');
            Cdekpay_Logger::error("POST webhook: $msg | " . wp_json_encode($data));

            return rest_ensure_response('OK');
        }

        $statuses_list = $payment_gateway->sdk->getPaymentStatuses();
        if (isset($data['payment']['pay_amount']) && isset($statuses_list['undefined']) && $cdekpay_order->status == $statuses_list['undefined']) {
            $data['payment']['status'] = 'success';

            $msg = "payment status: ".$data['payment']['status'];
            Cdekpay_Logger::info("POST webhook: $msg | ". wp_json_encode($data));

            $payment_gateway->update_order_statuses($cdekpay_order, $data['payment']);
        }

        if (isset($data['payment']['refund_amount']) && $cdekpay_order->total_refund === null) {
            $data['payment']['status'] = 'cancelled';
            $data['payment']['pay_amount'] = $data['payment']['refund_amount'];

            $msg = "payment status: ".$data['payment']['status'];
            Cdekpay_Logger::info("POST webhook: $msg | ". wp_json_encode($data));

            $payment_gateway->update_order_statuses($cdekpay_order, $data['payment']);
        }

        return rest_ensure_response('OK');
    }

    public function register_order_status_refund()
    {
        register_post_status( 'wc-refund', array(
            'label'                     => _x( 'Refund', 'Order status', 'cdekpay' ),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
	        /* translators: %s is replaced with "number" */
            'label_count'               => _n_noop( 'Refund (%s)', 'Refunds (%s)', 'cdekpay' )
        ) );
    }

    // Register in wc_order_statuses.
    public function add_order_status_refund( $order_statuses ) {
        $order_statuses['wc-refund'] = _x( 'Refund', 'Order status', 'cdekpay' );

        return $order_statuses;
    }

    public function voided_order_cdekpay( $order_id )
    {
        try {
            global $wpdb;

            $wpdb->update( $wpdb->prefix.'cdekpay_orders',
                [ 'status' => 5 ], // voided
                [ 'order_id' => $order_id ]
            );
        } catch (Exception $exception) {
            Cdekpay_Logger::error("voided_order_cdekpay:  ". $exception->getMessage());
        }
    }

    public function delete_order_cdekpay( $order_id )
    {
        try {
            global $wpdb;

            $wpdb->delete( $wpdb->prefix.'cdekpay_orders',
                [ 'order_id' => $order_id ]
            );
        } catch (Exception $exception) {
            Cdekpay_Logger::error("delete_order_cdekpay:  ". $exception->getMessage());
        }
    }

    public function cdek_add_schedule( $schedules ) {
        $schedules['schedule_cdekpay'] = array(
            'interval' => 10 * 60,
            'display' => __('Every 10 minutes', 'cdekpay')
        );
        return $schedules;
    }

    public function activation_cron_cdekpay() {
        if ( ! wp_next_scheduled( 'event_cron_cdekpay' ) ) {
            wp_schedule_event( time(), 'schedule_cdekpay', 'event_cron_cdekpay');
        }
    }

    public function process_cron_cdekpay() {

        if( class_exists( 'WC_Payment_Gateway' ) ) {
            Cdekpay_Logger::info("CRON: run ");

            require_once plugin_dir_path(dirname(__FILE__)) . '/includes/class-wc-cdekpay-gateway.php';

            try {
                $payment_gateway = new Cdekpay_Gateway();
                $payment_gateway->check_payment_status();
            } catch (Exception $exception) {
                Cdekpay_Logger::error("CRON: Exception - " . $exception->getMessage());
            }
        }
    }

    public function cdek_add_wc_cdekpay_gateway($gateways) {
        if( class_exists( 'WC_Payment_Gateway' ) ) {
            require_once plugin_dir_path(dirname(__FILE__)) . '/includes/class-wc-cdekpay-gateway.php';

            $gateways[] = 'Cdekpay_Gateway';
        }

        return $gateways;
    }

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cdekpay_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cdekpay_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cdekpay-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Cdekpay_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Cdekpay_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cdekpay-admin.js',
			array( 'jquery' ),
			$this->version,
			false );

	}

    public function cdek_add_admin_menu_cdekpay() {
        //add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $function, $icon_url, $position );
        add_menu_page(
            $this->plugin_name,
            wc_strtoupper($this->plugin_name),
            'administrator',
            $this->plugin_name,
            array($this, 'display_admin_reference_page'),
            'dashicons-money-alt',
            '55.5'
        );

        //add_submenu_page( '$parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
        add_submenu_page(
            $this->plugin_name,
            $this->plugin_name . ' Reference',
            esc_html(__( 'Reference', 'cdekpay' )),
            'administrator',
            $this->plugin_name,
            array($this, 'display_admin_reference_page')
        );

        add_submenu_page(
            $this->plugin_name,
            $this->plugin_name . ' Settings',
            esc_html(__( 'Settings', 'cdekpay' )),
            'administrator',
            'wc-settings&tab=checkout&section=cdekpay',
            array($this, 'display_admin_settings_page')
        );
    }

    public function display_admin_settings_page() {
        require_once 'partials/'.$this->plugin_name.'-admin-display-settings.php';
    }

    public function display_admin_reference_page() {
        require_once 'partials/'.$this->plugin_name.'-admin-display-reference.php';
    }
}
