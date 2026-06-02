<?php
/**
 * Order mapping and payment completion for InfinitePay.
 *
 * @package InfinitePay
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds API payloads from WC orders and completes payment idempotently.
 */
class InfinitePay_Order {

	const META_SLUG               = '_infinitepay_slug';
	const META_TRANSACTION_NSU    = '_infinitepay_transaction_nsu';
	const META_CAPTURE_METHOD     = '_infinitepay_capture_method';
	const META_RECEIPT_URL        = '_infinitepay_receipt_url';
	const META_PAID_AMOUNT        = '_infinitepay_paid_amount';
	const META_PAYMENT_CONFIRMED  = '_infinitepay_payment_confirmed';
	const META_CHECKOUT_URL       = '_infinitepay_checkout_url';

	/**
	 * Build full /links payload from order.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $redirect_url Return URL.
	 * @param string   $webhook_url Webhook URL.
	 * @return array
	 */
	public static function build_link_payload( WC_Order $order, $redirect_url, $webhook_url ) {
		$payload = array(
			'order_nsu'    => (string) $order->get_id(),
			'items'        => self::build_items( $order ),
			'redirect_url' => $redirect_url,
			'webhook_url'  => $webhook_url,
		);

		$customer = self::build_customer( $order );
		if ( ! empty( $customer ) ) {
			$payload['customer'] = $customer;
		}

		$address = self::build_address( $order );
		if ( ! empty( $address ) ) {
			$payload['address'] = $address;
		}

		return $payload;
	}

	/**
	 * Convert order line items to API items (cents).
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function build_items( WC_Order $order ) {
		$lines = array();

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$total = (float) $item->get_total();
			if ( $total < 0 ) {
				continue;
			}

			$lines[] = array(
				'quantity'    => max( 1, (int) $item->get_quantity() ),
				'price'       => self::unit_price_cents( $total, (int) $item->get_quantity() ),
				'description' => self::item_description( $item->get_name() ),
			);
		}

		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Shipping ) {
				continue;
			}

			$total = (float) $item->get_total();
			if ( $total <= 0 ) {
				continue;
			}

			$lines[] = array(
				'quantity'    => 1,
				'price'       => self::to_cents( $total ),
				'description' => self::item_description(
					$item->get_name() ? $item->get_name() : __( 'Frete', 'infinitepay' )
				),
			);
		}

		foreach ( $order->get_items( 'fee' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Fee ) {
				continue;
			}

			$total = (float) $item->get_total();
			if ( 0.0 === $total ) {
				continue;
			}

			$lines[] = array(
				'quantity'    => 1,
				'price'       => self::to_cents( $total ),
				'description' => self::item_description(
					$item->get_name() ? $item->get_name() : __( 'Taxa', 'infinitepay' )
				),
			);
		}

		$discount = (float) $order->get_discount_total();
		if ( $discount > 0 ) {
			$lines[] = array(
				'quantity'    => 1,
				'price'       => -1 * self::to_cents( $discount ),
				'description' => self::item_description( __( 'Desconto', 'infinitepay' ) ),
			);
		}

		if ( empty( $lines ) ) {
			$lines[] = array(
				'quantity'    => 1,
				'price'       => self::to_cents( (float) $order->get_total() ),
				'description' => self::item_description(
					sprintf(
						/* translators: %s: order number */
						__( 'Pedido #%s', 'infinitepay' ),
						$order->get_order_number()
					)
				),
			);
		}

		self::balance_items_to_order_total( $lines, $order );

		return $lines;
	}

	/**
	 * Adjust last line so item sum matches order total in cents.
	 *
	 * @param array    $lines Line items (by reference).
	 * @param WC_Order $order Order.
	 */
	private static function balance_items_to_order_total( array &$lines, WC_Order $order ) {
		$target = self::to_cents( (float) $order->get_total() );
		$sum    = 0;

		foreach ( $lines as $line ) {
			$sum += (int) $line['price'] * (int) $line['quantity'];
		}

		$diff = $target - $sum;
		if ( 0 === $diff || empty( $lines ) ) {
			return;
		}

		$last = count( $lines ) - 1;
		$lines[ $last ]['price'] = (int) $lines[ $last ]['price'] + $diff;
	}

	/**
	 * Unit price in cents for a line total and quantity.
	 *
	 * @param float $line_total Line total.
	 * @param int   $quantity   Quantity.
	 * @return int
	 */
	private static function unit_price_cents( $line_total, $quantity ) {
		$quantity = max( 1, $quantity );
		return (int) round( self::to_cents( $line_total ) / $quantity );
	}

	/**
	 * Convert currency amount to cents.
	 *
	 * @param float $amount Amount.
	 * @return int
	 */
	public static function to_cents( $amount ) {
		return (int) round( (float) $amount * 100 );
	}

	/**
	 * Sanitize item description for API.
	 *
	 * @param string $name Description.
	 * @return string
	 */
	private static function item_description( $name ) {
		$name = wp_strip_all_tags( (string) $name );
		return mb_substr( $name, 0, 200 );
	}

	/**
	 * Build customer object.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function build_customer( WC_Order $order ) {
		$name  = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$email = $order->get_billing_email();
		$phone = self::format_phone( $order->get_billing_phone() );

		$customer = array();

		if ( $name ) {
			$customer['name'] = $name;
		}
		if ( $email && is_email( $email ) ) {
			$customer['email'] = $email;
		}
		if ( $phone ) {
			$customer['phone_number'] = $phone;
		}

		return $customer;
	}

	/**
	 * Build address object from shipping or billing.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function build_address( WC_Order $order ) {
		$use_shipping = $order->has_shipping_address();

		$cep          = $use_shipping ? $order->get_shipping_postcode() : $order->get_billing_postcode();
		$street       = $use_shipping ? $order->get_shipping_address_1() : $order->get_billing_address_1();
		$neighborhood = '';
		$number       = $use_shipping ? $order->get_shipping_address_2() : $order->get_billing_address_2();
		$complement   = '';

		$city = $use_shipping ? $order->get_shipping_city() : $order->get_billing_city();

		if ( ! $cep && ! $street ) {
			return array();
		}

		$address = array();

		if ( $cep ) {
			$address['cep'] = preg_replace( '/\D/', '', $cep );
		}
		if ( $street ) {
			$address['street'] = $street;
		}
		if ( $neighborhood ) {
			$address['neighborhood'] = $neighborhood;
		}
		if ( $number ) {
			$address['number'] = $number;
		} elseif ( $city ) {
			$address['number'] = $city;
		}
		if ( $complement ) {
			$address['complement'] = $complement;
		}

		return $address;
	}

	/**
	 * Normalize Brazilian phone to E.164 when possible.
	 *
	 * @param string $phone Raw phone.
	 * @return string
	 */
	public static function format_phone( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );
		if ( empty( $digits ) ) {
			return '';
		}

		if ( strlen( $digits ) >= 12 && '55' === substr( $digits, 0, 2 ) ) {
			return '+' . $digits;
		}

		if ( strlen( $digits ) >= 10 && strlen( $digits ) <= 11 ) {
			return '+55' . $digits;
		}

		return '+' . $digits;
	}

	/**
	 * Extract payment URL from API response.
	 *
	 * @param array $response API body.
	 * @return string
	 */
	public static function get_checkout_url_from_response( array $response ) {
		foreach ( array( 'url', 'link', 'checkout_url', 'payment_url' ) as $key ) {
			if ( ! empty( $response[ $key ] ) && is_string( $response[ $key ] ) ) {
				return $response[ $key ];
			}
		}
		return '';
	}

	/**
	 * Extract slug from API response.
	 *
	 * @param array $response API body.
	 * @return string
	 */
	public static function get_slug_from_response( array $response ) {
		if ( ! empty( $response['slug'] ) ) {
			return (string) $response['slug'];
		}
		if ( ! empty( $response['invoice_slug'] ) ) {
			return (string) $response['invoice_slug'];
		}
		return '';
	}

	/**
	 * Confirm payment via API and complete order (idempotent).
	 *
	 * @param WC_Order        $order Order.
	 * @param InfinitePay_API $api   API client.
	 * @param array           $hints order_nsu, transaction_nsu, slug keys.
	 * @return bool True if order is paid/confirmed.
	 */
	public static function confirm_payment( WC_Order $order, InfinitePay_API $api, array $hints ) {
		if ( 'yes' === $order->get_meta( self::META_PAYMENT_CONFIRMED ) ) {
			return true;
		}

		$order_nsu = isset( $hints['order_nsu'] ) ? (string) $hints['order_nsu'] : (string) $order->get_id();

		if ( (string) $order->get_id() !== $order_nsu ) {
			return false;
		}

		$transaction_nsu = '';
		if ( ! empty( $hints['transaction_nsu'] ) ) {
			$transaction_nsu = (string) $hints['transaction_nsu'];
		} else {
			$transaction_nsu = (string) $order->get_meta( self::META_TRANSACTION_NSU );
		}

		$slug = '';
		if ( ! empty( $hints['slug'] ) ) {
			$slug = (string) $hints['slug'];
		} elseif ( ! empty( $hints['invoice_slug'] ) ) {
			$slug = (string) $hints['invoice_slug'];
		} else {
			$slug = (string) $order->get_meta( self::META_SLUG );
		}

		if ( ! $transaction_nsu || ! $slug ) {
			return false;
		}

		$check = $api->payment_check(
			array(
				'order_nsu'       => $order_nsu,
				'transaction_nsu' => $transaction_nsu,
				'slug'            => $slug,
			)
		);

		if ( is_wp_error( $check ) ) {
			return false;
		}

		if ( empty( $check['paid'] ) ) {
			return false;
		}

		self::complete_order( $order, $check, $hints );

		return true;
	}

	/**
	 * Mark order paid and store metadata.
	 *
	 * @param WC_Order $order  Order.
	 * @param array    $check  payment_check response.
	 * @param array    $hints  Extra fields from webhook/redirect.
	 */
	public static function complete_order( WC_Order $order, array $check, array $hints ) {
		if ( 'yes' === $order->get_meta( self::META_PAYMENT_CONFIRMED ) ) {
			return;
		}

		$transaction_nsu = ! empty( $hints['transaction_nsu'] ) ? (string) $hints['transaction_nsu'] : '';
		$slug            = ! empty( $hints['slug'] ) ? (string) $hints['slug'] : '';
		if ( ! $slug && ! empty( $hints['invoice_slug'] ) ) {
			$slug = (string) $hints['invoice_slug'];
		}

		if ( $transaction_nsu ) {
			$order->update_meta_data( self::META_TRANSACTION_NSU, $transaction_nsu );
		}
		if ( $slug ) {
			$order->update_meta_data( self::META_SLUG, $slug );
		}

		if ( ! empty( $hints['capture_method'] ) ) {
			$order->update_meta_data( self::META_CAPTURE_METHOD, sanitize_text_field( $hints['capture_method'] ) );
		} elseif ( ! empty( $check['capture_method'] ) ) {
			$order->update_meta_data( self::META_CAPTURE_METHOD, sanitize_text_field( $check['capture_method'] ) );
		}

		if ( ! empty( $hints['receipt_url'] ) ) {
			$order->update_meta_data( self::META_RECEIPT_URL, esc_url_raw( $hints['receipt_url'] ) );
		}

		if ( isset( $check['paid_amount'] ) ) {
			$order->update_meta_data( self::META_PAID_AMOUNT, (string) (int) $check['paid_amount'] );
		} elseif ( ! empty( $hints['paid_amount'] ) ) {
			$order->update_meta_data( self::META_PAID_AMOUNT, (string) (int) $hints['paid_amount'] );
		}

		$order->update_meta_data( self::META_PAYMENT_CONFIRMED, 'yes' );
		$order->save();

		$transaction_id = $transaction_nsu ? $transaction_nsu : $slug;
		$order->payment_complete( $transaction_id );

		$note = sprintf(
			/* translators: 1: capture method 2: transaction nsu */
			__( 'Pagamento InfinitePay confirmado. Método: %1$s. Transação: %2$s', 'infinitepay' ),
			$order->get_meta( self::META_CAPTURE_METHOD ),
			$transaction_nsu
		);
		$order->add_order_note( $note );
	}
}
