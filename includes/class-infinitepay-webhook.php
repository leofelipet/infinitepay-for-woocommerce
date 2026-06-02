<?php
/**
 * Webhook and redirect payment confirmation handlers.
 *
 * @package InfinitePay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles InfinitePay webhook (wc-api) and thank-you redirect fallback.
 */
class InfinitePay_Webhook {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_api_infinitepay', array( __CLASS__, 'handle_webhook' ) );
		add_action( 'woocommerce_thankyou_infinitepay', array( __CLASS__, 'handle_thankyou_redirect' ), 10, 1 );
	}

	/**
	 * Webhook endpoint: POST JSON from InfinitePay.
	 */
	public static function handle_webhook() {
		$raw = file_get_contents( 'php://input' );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$gateway = self::get_gateway();
		if ( ! $gateway ) {
			status_header( 400 );
			exit;
		}

		$gateway->log( 'Webhook received', $data );

		$order = self::resolve_order( $data );
		if ( ! $order ) {
			$gateway->log( 'Webhook: order not found', $data );
			status_header( 400 );
			exit;
		}

		$api = new InfinitePay_API( $gateway->get_handle(), 'yes' === $gateway->get_option( 'debug' ) );
		InfinitePay_Order::confirm_payment( $order, $api, $data );

		status_header( 200 );
		exit;
	}

	/**
	 * Thank-you page: confirm payment from redirect query params.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function handle_thankyou_redirect( $order_id ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['transaction_nsu'] ) && empty( $_GET['slug'] ) && empty( $_GET['order_nsu'] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_payment_method() !== 'infinitepay' ) {
			return;
		}

		$gateway = self::get_gateway();
		if ( ! $gateway ) {
			return;
		}

		$hints = array(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order_nsu'       => isset( $_GET['order_nsu'] ) ? sanitize_text_field( wp_unslash( $_GET['order_nsu'] ) ) : (string) $order_id,
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'transaction_nsu' => isset( $_GET['transaction_nsu'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction_nsu'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'slug'            => isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'capture_method'  => isset( $_GET['capture_method'] ) ? sanitize_text_field( wp_unslash( $_GET['capture_method'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'receipt_url'     => isset( $_GET['receipt_url'] ) ? esc_url_raw( wp_unslash( $_GET['receipt_url'] ) ) : '',
		);

		$api = new InfinitePay_API( $gateway->get_handle(), 'yes' === $gateway->get_option( 'debug' ) );
		InfinitePay_Order::confirm_payment( $order, $api, $hints );
	}

	/**
	 * Resolve order from webhook/redirect payload.
	 *
	 * @param array $data Payload.
	 * @return WC_Order|false
	 */
	private static function resolve_order( array $data ) {
		$order_nsu = '';
		if ( ! empty( $data['order_nsu'] ) ) {
			$order_nsu = (string) $data['order_nsu'];
		}

		if ( ! $order_nsu ) {
			return false;
		}

		$order = wc_get_order( absint( $order_nsu ) );
		if ( ! $order || $order->get_payment_method() !== 'infinitepay' ) {
			return false;
		}

		return $order;
	}

	/**
	 * @return InfinitePay_Gateway|false
	 */
	private static function get_gateway() {
		$gateways = WC()->payment_gateways()->payment_gateways();
		if ( empty( $gateways['infinitepay'] ) || ! $gateways['infinitepay'] instanceof InfinitePay_Gateway ) {
			return false;
		}
		return $gateways['infinitepay'];
	}
}
