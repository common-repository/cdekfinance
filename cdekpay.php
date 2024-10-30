<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://cdekpay.ru
 * @since             1.0.0
 * @package           Cdekpay
 *
 * @wordpress-plugin
 * Plugin Name:       CDEK PAY
 * Plugin URI:        https://cdekpay.ru/cms/wordpress
 * Description:       CDEK PAY payment gateway for WooCommerce
 * Version:           1.0.45
 * Author:            CdekPay
 * Author URI:        https://cdekpay.ru
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       cdekpay
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'CDEKPAY_VERSION', '1.0.45' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-cdekpay-activator.php
 */
function cdek_activate_cdekpay($network_wide) {
    // Check, that Woocommerce is installed and active
    if (!cdek_check_woocommerce_status_cdekpay()) {
        cdek_stop_activation_cdekpay();
    }

	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cdekpay-activator.php';

    if( is_multisite() && $network_wide ) {
        global $wpdb;

        $blogs = $wpdb->get_col( $wpdb->prepare("SELECT blog_id FROM %s",$wpdb->blogs));
        foreach( $blogs as $blog_id ) {
            switch_to_blog($blog_id);

            Cdekpay_Activator::activate();

            restore_current_blog();
        }
    } else {
        Cdekpay_Activator::activate();
    }
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-cdekpay-deactivator.php
 */
function cdek_deactivate_cdekpay() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-cdekpay-deactivator.php';
	Cdekpay_Deactivator::deactivate();
}

function cdek_stop_activation_cdekpay() {
    cdek_deactivate_cdekpay();

    wp_die(esc_html(__("It is obligatory to install plugin ", 'cdekpay')."<a href=\"https://wordpress.org/extend/plugins/woocommerce/\" target=\"_blank\">WooCommerce</a>. "."<a href=\"/wp-admin/plugins.php\">".__("Go back", 'cdekpay')."</a>"));
}

function cdek_check_woocommerce_status_cdekpay() {
    $woocommerce_plugin = 'woocommerce/woocommerce.php';
    if (in_array($woocommerce_plugin, get_option('active_plugins'))) {
        return true;
    }

    if (!is_multisite()) {
        return false;
    }

    $plugins = get_site_option('active_sitewide_plugins');

    return isset($plugins[$woocommerce_plugin]);
}

register_activation_hook( __FILE__, 'cdek_activate_cdekpay' );
register_deactivation_hook( __FILE__, 'cdek_deactivate_cdekpay' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-cdekpay.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function cdek_run_cdekpay() {

	$plugin = new Cdekpay();
	$plugin->run();

}

add_filter("plugin_action_links", 'cdek_add_link', 10, 2 );

function cdek_add_link($links, $plugin_file) {
    if( false === strpos( $plugin_file, basename(__FILE__) ) ){
        return $links;
    }

    $settings_link = '<a href="'.esc_url("admin.php?page=wc-settings&tab=checkout&section=cdekpay",'cdekpay').'">'.esc_html(__("Settings"),'cdekpay').'</a>';
    $help_link     = '<a href="'.esc_url("admin.php?page=cdekpay",'cdekpay').'">'.esc_html(__("Reference",'cdekpay')).'</a>';

    array_unshift($links, $settings_link);
    array_unshift($links, $help_link);

    return $links;
}

cdek_run_cdekpay();
