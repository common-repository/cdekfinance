<?php
    if ( ! defined( 'ABSPATH' ) ) exit;
    /**
 * Add new Refund status for woocommerce
 */
add_action( 'init', 'cdek_register_refund_order_status' );

function cdek_register_refund_order_status() {
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

add_filter( 'wc_order_statuses', 'cdek_add_refund_order_status' );

// Register in wc_order_statuses.
function cdek_add_refund_order_status( $order_statuses ) {
    $order_statuses['wc-refund'] = _x( 'Refund', 'Order status', 'cdekpay' );
    return $order_statuses;
}

function cdek_add_bulk_invoice_order_status() {
    global $post_type;

    if ( $post_type == 'shop_order' ) {
	    wp_enqueue_script(
		    'cdekpay-invoice-order-status',
		    plugins_url( '/admin/cdekpay-admin-refund.js', WC_PLUGIN_FILE ),
		    array( 'jquery' ),

	    );

	    wp_localize_script(
		    'cdekpay-invoice-order-status',
		    'cdekpay_invoice_order_status',
		    array(
			    'cdekpay_change_status_to_refund' =>  esc_html_e( 'Change status to refund', 'cdekpay' )
		    )
	    );
        ?>

        <?php
    }
}

add_action( 'admin_footer', 'cdek_add_bulk_invoice_order_status' );