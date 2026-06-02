<?php
/**
 * Plugin Name:       InfinitePay para WooCommerce
 * Plugin URI:        https://www.infinitepay.io/checkout-documentacao
 * Description:       Aceite PIX e cartão no WooCommerce com o Checkout Integrado InfinitePay. Redirect seguro, webhook e compatível com checkout em blocos e HPOS.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Léo Felipe
 * Author URI:        https://wa.me/5514981453663
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       infinitepay
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:    9.0
 *
 * InfinitePay para WooCommerce is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * @package InfinitePay
 */

defined( 'ABSPATH' ) || exit;

define( 'INFINITEPAY_VERSION', '1.0.0' );
define( 'INFINITEPAY_PLUGIN_FILE', __FILE__ );
define( 'INFINITEPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INFINITEPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				INFINITEPAY_PLUGIN_FILE,
				true
			);
		}
	}
);

/**
 * Load plugin textdomain.
 */
add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'infinitepay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

/**
 * Bootstrap after WooCommerce is fully loaded (load order safe).
 */
add_action( 'woocommerce_loaded', 'infinitepay_init' );

/**
 * Admin notice when WooCommerce is missing.
 */
add_action(
	'admin_notices',
	static function () {
		if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'InfinitePay para WooCommerce requer o WooCommerce ativo.', 'infinitepay' );
		echo '</p></div>';
	}
);

/**
 * Initialize plugin classes and gateway registration.
 */
function infinitepay_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	require_once INFINITEPAY_PLUGIN_DIR . 'includes/class-infinitepay-api.php';
	require_once INFINITEPAY_PLUGIN_DIR . 'includes/class-infinitepay-order.php';
	require_once INFINITEPAY_PLUGIN_DIR . 'includes/class-infinitepay-webhook.php';
	require_once INFINITEPAY_PLUGIN_DIR . 'includes/class-infinitepay-gateway.php';
	require_once INFINITEPAY_PLUGIN_DIR . 'includes/class-infinitepay-blocks-support.php';

	add_filter( 'woocommerce_payment_gateways', 'infinitepay_register_gateway' );

	InfinitePay_Webhook::init();
	InfinitePay_Gateway::init();
	infinitepay_register_blocks_support();
}

/**
 * Register payment method with WooCommerce Blocks checkout.
 */
function infinitepay_register_blocks_support() {
	if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		return;
	}

	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		static function ( $payment_method_registry ) {
			$payment_method_registry->register( new InfinitePay_Blocks_Support() );
		}
	);
}

/**
 * Register gateway with WooCommerce.
 *
 * @param array $methods Payment gateway class names.
 * @return array
 */
function infinitepay_register_gateway( $methods ) {
	$methods[] = 'InfinitePay_Gateway';
	return $methods;
}
