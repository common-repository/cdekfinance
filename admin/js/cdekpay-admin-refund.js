(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 *
	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.
	 *
	 * This enables you to define handlers, for when the DOM is ready:
	 *
	 * $(function() {
	 *
	 * });
	 *
	 * When the window is loaded:
	 *
	 * $( window ).load(function() {
	 *
	 * });
	 *
	 * ...and/or other possibilities.
	 *
	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.
	 */
	var CdekpayAdminRefundForm = function( $target ) {
		this.$target   = $target;

		// Params.
		this.params = $.extend( {}, {
			'cdekpay_change_status_to_refund': 'Change status to refund'
		}, cdekpay_invoice_order_status );

		this.onDisplay             = this.onDisplay.bind( this );

		this.onDisplay();

	};

	CdekpayAdminRefundForm.prototype.onDisplay = function(){
		$('<option>').val('mark_refund').text(this.params.cdekpay_change_status_to_refund).appendTo("select[name='action']");
		$('<option>').val('mark_refund').text(this.params.cdekpay_change_status_to_refund).appendTo("select[name='action2']");
	}

})( jQuery );
