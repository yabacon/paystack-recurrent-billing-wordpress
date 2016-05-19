<?php
/*
  Plugin Name: Paystack Subscribe Pay Plugin
  Plugin URI: http://www.paystack.com
  Version: 1.0.0
  Author: Ibrahim Lawal
  Author URI: https://paystack.com/
  Description: Allows merchants include a Paystack Inline popup that allows subscription
  to a plan created on their dashboard. Until a set amount is paid.
  via shortcode on their website
 */

defined('ABSPATH') or die('<!-- Silence of the tigers-->');
require_once(__DIR__ . '/library/common.php');
register_activation_hook( __FILE__, 'paystack_subscribe_pay_install' );

add_shortcode('paystacksubscribepay', 'paystack_subscribe_pay_form');

add_action( 'plugins_loaded', 'paystack_subscribe_pay_update_db_check' );
add_action('admin_menu', 'paystack_subscribe_pay_add_admin_menu');
add_action('admin_init', 'paystack_subscribe_pay_settings_init');
add_action('init', 'paystack_subscribe_pay_start_session', 1);

/* Add filter for action links */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'paystack_subscribe_pay_action_links');
