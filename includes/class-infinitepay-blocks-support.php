<?php
/**
 * WooCommerce Blocks checkout integration for InfinitePay.
 *
 * @package InfinitePay
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Registers InfinitePay with the Blocks payment method registry.
 */
class InfinitePay_Blocks_Support extends AbstractPaymentMethodType {

	/**
	 * Payment method ID (must match gateway id).
	 *
	 * @var string
	 */
	protected $name = 'infinitepay';

	/**
	 * Load settings from the database.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_infinitepay_settings', array() );
	}

	/**
	 * @return bool
	 */
	public function is_active() {
		if ( empty( $this->settings['enabled'] ) || 'yes' !== $this->settings['enabled'] ) {
			return false;
		}

		$handle = isset( $this->settings['handle'] ) ? ltrim( (string) $this->settings['handle'], '$' ) : '';

		return '' !== $handle;
	}

	/**
	 * @return string[]
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'wc-payment-method-infinitepay',
			INFINITEPAY_PLUGIN_URL . 'assets/js/infinitepay-blocks.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
				'wc-sanitize',
			),
			INFINITEPAY_VERSION,
			true
		);

		return array( 'wc-payment-method-infinitepay' );
	}

	/**
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title', __( 'InfinitePay', 'infinitepay' ) ),
			'description' => $this->get_setting( 'description', '' ),
			'supports'    => $this->get_supported_features(),
		);
	}
}
