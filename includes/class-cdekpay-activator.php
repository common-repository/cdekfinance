<?php

/**
 * Fired during plugin activation
 *
 * @link       https://cdekpay.ru
 * @since      1.0.0
 *
 * @package    Cdekpay
 * @subpackage Cdekpay/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Cdekpay
 * @subpackage Cdekpay/includes
 * @author     CdekPay <coder.dlc@yandex.ru>
 */
class Cdekpay_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cdekpay-logger.php';

        try {
            self::create_table_cdekpay_orders();
        } catch (Exception $e) {
            Cdekpay_Logger::error('Cdekpay_Activator Exception: '.$e->getMessage());
        }
	}

    public static function create_table_cdekpay_orders() {
        global $wpdb;

        $wpdb->hide_errors();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $wpdb->get_blog_prefix() . 'cdekpay_orders';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL auto_increment,
        cdekpay_order_id varchar(32) NOT NULL,
        order_id bigint(20) NOT NULL,
        order_number varchar(12) NULL,
        total int(12) NOT NULL,
        total_refund int(12) NULL,
        currency_code varchar(6) NOT NULL,
        status int(2) NOT NULL default '0',
        created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id),
        KEY order_id (order_id)
        )
        {$charset_collate};";

        return dbDelta($sql);
    }
}
