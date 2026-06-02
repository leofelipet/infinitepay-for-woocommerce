<?php
/**
 * Plugin Name: InfinitePay para WooCommerce
 * Plugin URI: https://www.infinitepay.io/checkout-documentacao
 * Description: Gateway de pagamento WooCommerce para o Checkout Integrado InfinitePay.
 * Version: 1.0.0
 * Author: EJABR
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 * Text Domain: infinitepay
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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
 * Bootstrap gateway when WooCommerce is available.
 */
add_action(
	'plugins_loaded',
	'infinitepay_init',
	11
);

/**
 * Initialize plugin classes and gateway registration.
 */
function infinitepay_init() {
	if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action(
			'admin_notices',
			static function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'InfinitePay para WooCommerce requer o WooCommerce ativo.', 'infinitepay' );
				echo '</p></div>';
			}
		);
		return;
	}

	require_once INFINITEPAY_PLUGIN_DIR . 'includes/class-infinitepay-api.php';
	require_once INFINITEPAY_PLUGIN_DIR . 'includes/class-infinitepay-order.php';
	require_once INFINITEPAY_PLUGIN_DIR . 'includes/class-infinitepay-webhook.php';
	require_once INFINITEPAY_PLUGIN_DIR . 'includes/class-infinitepay-gateway.php';

	InfinitePay_Webhook::init();
	InfinitePay_Gateway::init();
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

add_filter( 'woocommerce_payment_gateways', 'infinitepay_register_gateway' );
