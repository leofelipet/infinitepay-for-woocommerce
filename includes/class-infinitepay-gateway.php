<?php
/**
 * WooCommerce payment gateway for InfinitePay.
 *
 * @package InfinitePay
 */

defined( 'ABSPATH' ) || exit;

/**
 * InfinitePay checkout redirect gateway.
 */
class InfinitePay_Gateway extends WC_Payment_Gateway {

	/**
	 * InfiniteTag handle.
	 *
	 * @var string
	 */
	protected $handle = '';

	/**
	 * Debug logging.
	 *
	 * @var string yes|no
	 */
	protected $debug = 'no';

	/**
	 * Register gateway instance hooks.
	 */
	public static function init() {
		add_filter( 'plugin_action_links_' . plugin_basename( INFINITEPAY_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
	}

	/**
	 * Settings link on plugins list.
	 *
	 * @param array $links Plugin links.
	 * @return array
	 */
	public static function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=infinitepay' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Configurações', 'infinitepay' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'infinitepay';
		$this->icon               = '';
		$this->has_fields         = false;
		$this->method_title       = __( 'InfinitePay', 'infinitepay' );
		$this->method_description = __( 'Aceite pagamentos via Checkout Integrado InfinitePay (PIX e cartão). O cliente é redirecionado para finalizar o pagamento.', 'infinitepay' );
		$this->supports           = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', __( 'InfinitePay', 'infinitepay' ) );
		$this->description = $this->get_option( 'description', __( 'Pague com PIX ou cartão na InfinitePay.', 'infinitepay' ) );
		$this->enabled     = $this->get_option( 'enabled', 'no' );
		$this->handle      = $this->get_option( 'handle', '' );
		$this->debug       = $this->get_option( 'debug', 'no' );

		add_action(
			'woocommerce_update_options_payment_gateways_' . $this->id,
			array( $this, 'process_admin_options' )
		);
	}

	/**
	 * Admin settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Ativar/Desativar', 'infinitepay' ),
				'type'    => 'checkbox',
				'label'   => __( 'Ativar InfinitePay', 'infinitepay' ),
				'default' => 'no',
			),
			'title'       => array(
				'title'       => __( 'Título', 'infinitepay' ),
				'type'        => 'text',
				'description' => __( 'Nome exibido ao cliente no checkout.', 'infinitepay' ),
				'default'     => __( 'InfinitePay', 'infinitepay' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Descrição', 'infinitepay' ),
				'type'        => 'textarea',
				'description' => __( 'Descrição exibida ao cliente no checkout.', 'infinitepay' ),
				'default'     => __( 'Você será redirecionado para pagar com PIX ou cartão na InfinitePay.', 'infinitepay' ),
				'desc_tip'    => true,
			),
			'handle'      => array(
				'title'       => __( 'Handle (InfiniteTag)', 'infinitepay' ),
				'type'        => 'text',
				'description' => __( 'Seu nome de usuário no app InfinitePay, sem o símbolo $.', 'infinitepay' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'urls_section' => array(
				'title'       => __( 'URLs de integração', 'infinitepay' ),
				'type'        => 'title',
				'description' => __( 'A URL de webhook é fixa. A URL de redirect é montada automaticamente para cada pedido no checkout.', 'infinitepay' ),
			),
			'webhook_url'  => array(
				'title'       => __( 'URL do webhook', 'infinitepay' ),
				'type'        => 'infinitepay_readonly_url',
				'description' => __( 'Enviada à InfinitePay em cada pedido. Deve ser acessível publicamente (HTTPS).', 'infinitepay' ),
				'url'         => self::get_webhook_url(),
			),
			'redirect_url' => array(
				'title'       => __( 'URL de redirect', 'infinitepay' ),
				'type'        => 'infinitepay_readonly_url',
				'description' => __( 'Página de pedido recebido (thank you). O WooCommerce substitui {order_id} e adiciona a chave do pedido em cada compra.', 'infinitepay' ),
				'url'         => self::get_redirect_url_pattern(),
			),
			'debug'       => array(
				'title'       => __( 'Log de depuração', 'infinitepay' ),
				'type'        => 'checkbox',
				'label'       => __( 'Registrar requisições no log do WooCommerce (source: infinitepay)', 'infinitepay' ),
				'default'     => 'no',
				'description' => __( 'WooCommerce → Status → Logs', 'infinitepay' ),
			),
		);
	}

	/**
	 * Webhook endpoint URL for InfinitePay callbacks.
	 *
	 * @return string
	 */
	public static function get_webhook_url() {
		if ( function_exists( 'WC' ) && WC() && is_callable( array( WC(), 'api_request_url' ) ) ) {
			return WC()->api_request_url( 'infinitepay' );
		}

		return add_query_arg( 'wc-api', 'infinitepay', home_url( '/', 'https' ) );
	}

	/**
	 * Order-received URL pattern sent as redirect_url (order_id placeholder).
	 *
	 * @return string
	 */
	public static function get_redirect_url_pattern() {
		if ( ! function_exists( 'wc_get_checkout_url' ) || ! function_exists( 'wc_get_endpoint_url' ) ) {
			return home_url( '/' );
		}

		$example = wc_get_endpoint_url( 'order-received', '000', wc_get_checkout_url() );

		return str_replace( '000', '{order_id}', $example );
	}

	/**
	 * Render read-only URL field in gateway settings.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field config.
	 * @return string
	 */
	public function generate_infinitepay_readonly_url_html( $key, $data ) {
		$defaults = array(
			'title'       => '',
			'description' => '',
			'url'         => '',
		);
		$data = wp_parse_args( $data, $defaults );
		$url  = (string) $data['url'];

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $data['title'] ); ?></label>
			</th>
			<td class="forminp">
				<input
					type="text"
					class="large-text code"
					readonly="readonly"
					onclick="this.select();"
					value="<?php echo esc_attr( $url ); ?>"
				/>
				<?php if ( ! empty( $data['description'] ) ) : ?>
					<p class="description"><?php echo esc_html( $data['description'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Validate handle is set when enabling.
	 *
	 * @return bool
	 */
	public function process_admin_options() {
		$saved = parent::process_admin_options();

		if ( 'yes' === $this->get_option( 'enabled' ) && ! $this->get_handle() ) {
			WC_Admin_Settings::add_error( __( 'Informe o Handle (InfiniteTag) para ativar o InfinitePay.', 'infinitepay' ) );
			$this->update_option( 'enabled', 'no' );
		}

		return $saved;
	}

	/**
	 * Merchant handle without $.
	 *
	 * @return string
	 */
	public function get_handle() {
		return ltrim( (string) $this->handle, '$' );
	}

	/**
	 * Log helper for webhook handler.
	 *
	 * @param string $message Message.
	 * @param mixed  $context Context.
	 */
	public function log( $message, $context = null ) {
		if ( 'yes' !== $this->debug ) {
			return;
		}
		$api = new InfinitePay_API( $this->get_handle(), true );
		$api->log( $message, $context );
	}

	/**
	 * Process payment: create link and redirect.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Pedido inválido.', 'infinitepay' ), 'error' );
			return array( 'result' => 'fail' );
		}

		if ( ! $this->get_handle() ) {
			wc_add_notice( __( 'InfinitePay não está configurado. Contate a loja.', 'infinitepay' ), 'error' );
			return array( 'result' => 'fail' );
		}

		$redirect_url = $order->get_checkout_order_received_url();
		$webhook_url  = self::get_webhook_url();

		$payload = InfinitePay_Order::build_link_payload( $order, $redirect_url, $webhook_url );

		$api      = new InfinitePay_API( $this->get_handle(), 'yes' === $this->debug );
		$response = $api->create_link( $payload );

		if ( is_wp_error( $response ) ) {
			wc_add_notice(
				$response->get_error_message(),
				'error'
			);
			return array( 'result' => 'fail' );
		}

		$checkout_url = InfinitePay_Order::get_checkout_url_from_response( $response );
		$slug         = InfinitePay_Order::get_slug_from_response( $response );

		if ( ! $checkout_url ) {
			wc_add_notice( __( 'InfinitePay não retornou o link de pagamento.', 'infinitepay' ), 'error' );
			$this->log( 'Missing checkout URL in response', $response );
			return array( 'result' => 'fail' );
		}

		$order->update_meta_data( InfinitePay_Order::META_CHECKOUT_URL, esc_url_raw( $checkout_url ) );
		if ( $slug ) {
			$order->update_meta_data( InfinitePay_Order::META_SLUG, $slug );
		}
		$order->save();

		$order->update_status(
			'pending',
			__( 'Aguardando pagamento na InfinitePay.', 'infinitepay' )
		);

		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $checkout_url,
		);
	}
}
