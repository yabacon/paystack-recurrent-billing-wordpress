<?php
/*
	Plugin Name: Paystack Recurrent Billing
	Plugin URI: http://www.paystack.com
	Version: 1.1.0
	Author: Paystack Support
	Author URI: https://paystack.com/
	Description: Allows merchants include a Paystack Inline popup that allows subscription
	to a plan created on their dashboard via shortcode on their website. The subscription is active
	until an optional target is paid or - if no target is set - the user/merchant cancels the subscription.
 */

defined('ABSPATH') or die('<!-- Silence of the tigers-->');
require_once(__DIR__ . '/library/common.php');
register_activation_hook( __FILE__, 'paystack_recurrent_billing_install' );

add_shortcode('paystackrecurrentbilling', 'paystack_recurrent_billing_form');

add_action( 'plugins_loaded', 'paystack_recurrent_billing_update_db_check' );
add_action('admin_menu', 'paystack_recurrent_billing_add_admin_menu');
add_action('admin_init', 'paystack_recurrent_billing_settings_init');
add_action('init', 'paystack_recurrent_billing_start_session', 1);

/* Add filter for action links */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'paystack_recurrent_billing_action_links');
