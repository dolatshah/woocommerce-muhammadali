<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

define( 'WC_MUHAMMADALI_VERSION', '1.0.0' );

define( 'WC_MUHAMMADALI_PLUGIN_FILE', __FILE__ );
define('WC_MUHAMMADALI_DIR',plugin_dir_path(__FILE__));
define('WC_MUHAMMADALI_REQUIRED_VERSION','4.9.5');

if ( ! class_exists( 'WC_woocommerce_muhammadali' ) ) {
	include_once WC_MUHAMMADALI_DIR . '/includes/class-wc-muhammadali.php';	
}

register_activation_hook(__FILE__,'wc_muhammadali_activation');

function wc_muhammadali_activation(){

	global $wp_version;

	if (version_compare($wp_version,WC_MUHAMMADALI_REQUIRED_VERSION,'<')){
		wp_die("This plugin requires at least the version " . WC_MUHAMMADALI_REQUIRED_VERSION . " of Wordpress");
	}

	if (!function_exists('curl_version')){
		wp_die("To use this plugin, it is mandatory to enable the CURL extension of PHP");
	}
}
