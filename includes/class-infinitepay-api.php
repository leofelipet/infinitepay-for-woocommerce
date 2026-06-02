<?php
/**
 * InfinitePay Checkout API client.
 *
 * @package InfinitePay
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTTP client for InfinitePay checkout endpoints.
 */
class InfinitePay_API {

	const API_BASE = 'https://api.checkout.infinitepay.io';

	/**
	 * @var string
	 */
	private $handle;

	/**
	 * @var bool
	 */
	private $debug;

	/**
	 * @param string $handle  Merchant InfiniteTag (without $).
	 * @param bool   $debug   Enable WC logging.
	 */
	public function __construct( $handle, $debug = false ) {
		$this->handle = ltrim( (string) $handle, '$' );
		$this->debug  = (bool) $debug;
	}

	/**
	 * Create checkout payment link.
	 *
	 * @param array $payload Request body without handle (handle added here).
	 * @return array|WP_Error Decoded JSON body or error.
	 */
	public function create_link( array $payload ) {
		$payload['handle'] = $this->handle;
		return $this->post( '/links', $payload, true );
	}

	/**
	 * Verify payment status.
	 *
	 * @param array $data order_nsu, transaction_nsu, slug.
	 * @return array|WP_Error
	 */
	public function payment_check( array $data ) {
		$body = array_merge(
			array( 'handle' => $this->handle ),
			$data
		);
		return $this->post( '/payment_check', $body, false );
	}

	/**
	 * POST JSON to API.
	 *
	 * @param string $path            Endpoint path.
	 * @param array  $payload         Body.
	 * @param bool   $try_items_fallback Retry with `itens` if `items` fails.
	 * @return array|WP_Error
	 */
	private function post( $path, array $payload, $try_items_fallback ) {
		$response = $this->request( $path, $payload );

		if ( $try_items_fallback && is_wp_error( $response ) && isset( $payload['items'] ) && ! isset( $payload['itens'] ) ) {
			$fallback = $payload;
			$fallback['itens'] = $fallback['items'];
			unset( $fallback['items'] );
			$this->log( 'Retrying with itens key instead of items.' );
			$response = $this->request( $path, $fallback );
		}

		return $response;
	}

	/**
	 * Execute HTTP request.
	 *
	 * @param string $path    Path.
	 * @param array  $payload Body.
	 * @return array|WP_Error
	 */
	private function request( $path, array $payload ) {
		$url = self::API_BASE . $path;

		$this->log( 'POST ' . $url, $payload );

		$http = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $http ) ) {
			$this->log( 'HTTP error: ' . $http->get_error_message() );
			return $http;
		}

		$code = wp_remote_retrieve_response_code( $http );
		$raw  = wp_remote_retrieve_body( $http );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$this->log( 'Response ' . $code, $data );

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['message'] ) ? $data['message'] : $raw;
			if ( empty( $message ) ) {
				$message = sprintf(
					/* translators: %d: HTTP status code */
					__( 'InfinitePay retornou erro HTTP %d.', 'infinitepay' ),
					$code
				);
			}
			return new WP_Error( 'infinitepay_api_error', $message, array( 'status' => $code, 'body' => $data ) );
		}

		return $data;
	}

	/**
	 * Write to WooCommerce logger when debug enabled.
	 *
	 * @param string $message Log message.
	 * @param mixed  $context Optional context.
	 */
	public function log( $message, $context = null ) {
		if ( ! $this->debug || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$text   = $message;

		if ( null !== $context ) {
			$text .= ' ' . wp_json_encode( $context );
		}

		$logger->debug( $text, array( 'source' => 'infinitepay' ) );
	}
}
