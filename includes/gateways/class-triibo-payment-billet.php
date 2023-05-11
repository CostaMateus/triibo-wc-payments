<?php
/**
 * Triibo Payment Billet class
 *
 * @author      Mateus Costa <mateus@costamateus.com.br>
 * @package 	Triibo_Payments/Gateways
 * @version 	1.0.0
 */
defined( "ABSPATH" ) || exit;

class Triibo_Payment_Billet extends WC_Payment_Gateway
{
	/**
	 * Instance of this class.
	 *
	 * @since 	1.0.0
	 * @var 	object
	 */
	protected static $instance = null;

	/**
	 * Payment gateway (ex Cielo).
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	protected $service         = null;

	/**
	 * Instance of Api Node
	 *
	 * @since 	1.0.0
	 * @var 	Triibo_Api_Node
	 */
	protected $node            = null;

	/**
	 * Instance of Triibo_Payment_Api
	 *
	 * @since 	1.0.0
	 * @var 	Triibo_Payment_Api
	 */
	protected $api             = null;

	/**
	 * Debug
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	protected $debug           = null;

	/**
	 * Logger
	 *
	 * @since 	1.0.0
	 * @var 	wc_get_logger|WC_Logger|null
	 */
	protected $log             = null;

	/**
	 * Constructor
	 *
	 * @since 	1.0.0
	 */
	public function __construct()
	{
		self::$instance = $this;

        $this->id                 = Triibo_Payments::DOMAIN . "_billet";
        $this->icon               = apply_filters( "woocommerce_" . $this->id . "_icon", plugins_url( "assets/images/triibo-25-cor-completo.png", Triibo_Payments::FILE ) );

        $this->method_title       = __( "Triibo Boleto",                                  $this->id );
        $this->method_description = __( "Pagamentos com boleto usando a Triibo Gateway.", $this->id );
        $this->order_button_text  = __( "Prossiga para o pagamento",                      $this->id );

        $this->supports           = apply_filters( $this->id . "_supports_array", [
            "products",
        ] );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables.
        $this->title              = $this->get_option( "title"       );
        $this->description        = $this->get_option( "description" );
        $this->service            = $this->get_option( "service"     );
        $this->debug              = $this->get_option( "debug"       );

        // Active logs.
        if ( $this->debug == "yes" ) $this->log = ( function_exists( "wc_get_logger" ) ) ? wc_get_logger() : new WC_Logger();

        $this->node = new Triibo_Api_Node();
        $this->api  = new Triibo_Payments_Api( $this, $this->node );

        $this->init_actions_filters();
	}

	/**
	 * Get id
	 *
	 * @since 	1.0.0
	 * @return 	string
	 */
	public function get_id()
	{
		return $this->id;
	}

	/**
	 * Get service
	 *
	 * @since 	1.0.0
	 * @return 	string
	 */
	public function get_service()
	{
		return $this->service;
	}

	/**
	 * Get node
	 *
	 * @since 	1.0.0
	 * @return 	string
	 */
	public function get_node()
	{
		return $this->node;
	}

	/**
	 * Get api
	 *
	 * @since 	1.0.0
	 * @return 	string
	 */
	public function get_api()
	{
		return $this->api;
	}

	/**
	 * Get debug
	 *
	 * @since 	1.0.0
	 * @return 	string
	 */
	public function get_debug()
	{
		return $this->debug;
	}

	/**
	 * Get log
	 *
	 * @since 	1.0.0
	 * @return 	wc_get_logger|WC_Logger
	 */
	public function get_log()
	{
		return $this->log;
	}

	/**
	 * Return an instance of this class
	 *
	 * @since 	1.0.0
	 * @return 	object A single instance of this class.
	 */
	public static function get_instance()
	{
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) self::$instance = new self;

		return self::$instance;
	}

	/**
	 * Get log.
	 *
	 * @since 	1.0.0
	 * @return 	string
	 */
	protected function get_log_view()
	{
		$date      = date( "Y-m-d" );
		$esc_attr  = esc_attr( $this->id );
		$sanitize  = sanitize_file_name( wp_hash( $this->id ) );
		$admin_url = "admin.php?page=wc-status&tab=logs&log_file=$esc_attr-$date-$sanitize.log";
		$link      = esc_url( admin_url( $admin_url ) );
		$text      = __( "System Status &gt; Logs", $this->id );

		return "<a href='$link' target='_blank' >$text</a>";
	}

	/**
	 * Get log.
	 *
	 * @since 	1.0.0
	 * @return 	string
	 */
	protected function get_global_config()
	{
		$triibo = Triibo_Api_Services::get_name( true );
        $url    = esc_url( admin_url( "admin.php?page={$triibo}" ) );
		$text   = __( "clicando aqui", $this->id );

		return "<a href='{$url}' target='_blank' >{$text}</a>";
	}

	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @since 	1.0.0
	 * @return 	bool
	 */
	public function using_supported_currency()
	{
		return "BRL" === get_woocommerce_currency();
	}

	/**
	 * Returns a value indicating the the Gateway is available or not.
	 * It's called automatically by WooCommerce before allowing
	 * customers to use the gateway for payment.
	 *
	 * @return bool
	 */
	public function is_available()
	{
		// TODO remover futuramente
		return false;

		$available = $this->get_option( "enabled" ) === "yes" &&
					 $this->node->status() && $this->using_supported_currency();

		if ( !class_exists( "Extra_Checkout_Fields_For_Brazil" ) ) $available = false;

		return $available;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public function init_form_fields()
	{
		$this->form_fields = [

			// ativar / desativar meio de pagamento
			"enabled"         => [
				"type"        => "checkbox",
				"title"       => __( "Ativar / Desativar",                                        $this->id ),
				"label"       => __( "Ativar Triibo Boleto",                                      $this->id ),
				"default"     => "no",
			],

			"title"           => [
				"type"        => "text",
				"title"       => __( "Título",                                                    $this->id ),
				"description" => __( "Controla o título que o usuário vê durante o checkout.",    $this->id ),
				"default"     => __( "Triibo Boleto",                                             $this->id ),
			],
			"description"     => [
				"type"        => "textarea",
				"title"       => __( "Descrição",                                                 $this->id ),
				"description" => __( "Controla a descrição que o usuário vê durante o checkout.", $this->id ),
				"default"     => __( "Pague com a Triibo Boleto",                                 $this->id ),
			],

			"service"         => [
				"type"        => "select",
				"title"       => __( "Serviço de pagamento",                                      $this->id ),
				"description" => __( "Escolha qual será o serviço de pagamento padrão.",          $this->id ),
				"default"     => "asaas",
				"class"       => "wc-enhanced-select",
				"options"     => [
					"asaas" => __( "Asaas", $this->id ),
				],
			],

			"debug"           => [
				"type"        => "checkbox",
				"title"       => __( "Debug Log",                                                 $this->id ),
				"label"       => __( "Ativar logger",                                             $this->id ),
				"description" => sprintf( __( "Registrar log de eventos, %s", $this->id ), $this->get_log_view() ),
				"default"     => "yes",
			],

			"configs"         => [
				"type"        => "title",
				"title"       => __( "Chaves de API",                                             $this->id ),
				"description" => sprintf( __( "Acessar configurações globais %s.", $this->id ), $this->get_global_config() ),
			],
		];
	}

	/**
	 * Initialise Gateway Actions and Filters.
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public function init_actions_filters()
	{
		add_action( "wp_enqueue_scripts",                                       [ $this, "checkout_scripts"      ] );
		add_action( "woocommerce_update_options_payment_gateways_" . $this->id, [ $this, "process_admin_options" ] );
	}

	/**
	 * Admin page.
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public function admin_options()
	{
		$suffix  = defined( "SCRIPT_DEBUG" ) && SCRIPT_DEBUG ? "" : ".min";
		$js_path = "assets/js/admin/script{$suffix}.js";
		$js_ver  = date( "ymdHis", filemtime( plugin_dir_path( Triibo_Payments::FILE ) . $js_path  ) );
		wp_enqueue_script( "{$this->id}_admin", plugins_url( $js_path, Triibo_Payments::FILE ), [ "json2", "jquery" ], $js_ver, true );

		require_once ( dirname( Triibo_Payments::FILE ) . "/templates/admin/wc-settings.php" );
	}

	/**
	 * Checkout scripts.
	 *
	 * @return void
	 */
	public function checkout_scripts()
	{
		$load_scripts = false;

		if ( is_checkout() )         $load_scripts = true;
		if ( $this->is_available() ) $load_scripts = true;
		if ( false === $load_scripts ) return;

		$suffix       = defined( "SCRIPT_DEBUG" ) && SCRIPT_DEBUG ? "" : ".min";

		// $css_path     = "assets/css/frontend/style{$suffix}.css";
		// $css_ver      = date( "ymdHis", filemtime( plugin_dir_path( Triibo_Payments::FILE ) . $css_path ) );
		// wp_enqueue_style(  "{$this->id}_checkout", plugins_url( $css_path, Triibo_Payments::FILE ), false, $css_ver, "all" );

		$js_path      = "assets/js/frontend/billet.script{$suffix}.js";
		$js_ver       = date( "ymdHis", filemtime( plugin_dir_path( Triibo_Payments::FILE ) . $js_path  ) );
		wp_enqueue_script( "{$this->id}_checkout", plugins_url( $js_path,  Triibo_Payments::FILE ), [ "json2", "jquery", "woocommerce-extra-checkout-fields-for-brazil-front", "jquery-mask" ], $js_ver, true );

		$user         = wp_get_current_user();
		$token        = $this->api->validate_token( $user->ID );

		$url          = get_rest_url( null, "triibo-payments/v1/cards/brand" );

		wp_localize_script(
			"{$this->id}_checkout",
			"{$this->id}_params",
			[
				"id"            => $this->id,
				"user_id"       => $user->ID,
				"rest_url"      => $url,
				"invalid_token" => $token ? false : true,
				"messages"      => [
					"session_error" => __( "Ocorreu um error. Por favor, atualize a página.",            $this->id ),
					"invalid_token" => __( "Por favor, faça login pela Triibo para finalizar a compra.", $this->id ),
				],
			]
		);
	}

	/**
	 * Payment Fields Hook.
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public function payment_fields()
	{
		if (
			( class_exists( "WC_Subscriptions_Cart"                   ) && WC_Subscriptions_Cart::cart_contains_subscription() )
			||
			( class_exists( "WC_Subscriptions_Change_Payment_Gateway" ) && WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment )
			||
			( function_exists( "wcs_cart_contains_resubscribe" ) && wcs_cart_contains_resubscribe() )
			||
			( function_exists( "wcs_cart_contains_renewal"     ) && wcs_cart_contains_renewal() )
		)
		{
			$this->availability = false;
		}
		else
		{
			$description = ( $this->node->env() ) ? $this->get_description() . __( " - SANDBOX ativado.", $this->id ) : $this->get_description();

			if ( $description ) echo wpautop( wptexturize( trim( $description ) ) );

			echo "<p>" . _e( "Ao finalizar a compra, você terá acesso ao código de barras do boleto bancário que poderá pagar no seu internet banking ou em uma lotérica.", $this->id ) . "</p>";
			echo "<p>" . _e( "* O pedido será confirmado somente após a confirmação do pagamento.", $this->id ) . "</p>";
		}
	}

	/**
	 * Get User IP
	 *
	 * @return string
	 */
	public function get_user_ip()
	{
		if ( !empty( $_SERVER[ "HTTP_CLIENT_IP" ] ) )
		{
			$ip = $_SERVER[ "HTTP_CLIENT_IP" ];
		}
		elseif ( !empty( $_SERVER[ "HTTP_X_FORWARDED_FOR" ] ) )
		{
			$ip = $_SERVER[ "HTTP_X_FORWARDED_FOR" ];
		}
		else
		{
			$ip = $_SERVER[ "REMOTE_ADDR" ];
		}

		return $ip;
	}

	/**
	 * Process the payment
	 * Function called by WooCommerce to process the payment
	 *
	 * @since 	1.0.0
	 * @param  	int $order_id
	 * @return 	array
	 */
	public function process_payment( $order_id )
	{
		$notice        = "<b>" . $this->title . "</b>:";
		wc_add_notice( "{$notice} método não finalizado!", "error" );
		return [
			"result"   => "fail",
			"redirect" => ""
		];

		$order         = wc_get_order( $order_id );
        $user          = wp_get_current_user();
		$user_ip       = $this->get_user_ip();
		$triibo_id     = get_user_meta( $user->ID, "triiboId_phone", true );
		$token         = $this->api->validate_token( $user->ID );

		$cpf           = WC()->checkout()->get_value( $this->id . "-card-holder-cpf" );
		$email         = WC()->checkout()->get_value( "billing_email"    );
		$zipcode       = WC()->checkout()->get_value( "billing_postcode" );
		$addrNumber    = WC()->checkout()->get_value( "billing_number"   );

		$holder_name   = WC()->checkout()->get_value( $this->id . "-card-holder-name"  );
		$holder_cpf    = preg_replace( "/[^0-9]/", "", $cpf );
		$holder_phone  = preg_replace( "/[^0-9]/", "", WC()->checkout()->get_value( $this->id . "-card-holder-phone" ) );

		$card_number   = WC()->checkout()->get_value( $this->id . "-card-number" );
		$card_exp      = preg_replace( "/[^0-9]/", "", WC()->checkout()->get_value( $this->id . "-card-expiry"       ) );
		$card_cvc      = preg_replace( "/[^0-9]/", "", WC()->checkout()->get_value( $this->id . "-card-cvc"          ) );
		$card_brand    = WC()->checkout()->get_value( $this->id . "-card-brand"        );
		$installments  = WC()->checkout()->get_value( $this->id . "-card-installment"  );

		$card_exp      = substr( $card_exp, 0, 2 ) . "/20" . substr( $card_exp, 2, 2 );

		$tokenInfo     = [
			"token"       => $token,
			"card_number" => str_replace( " ", "-", $card_number ),
			"brand"       => $card_brand,

			"holder"      => $holder_name,
			"month"       => substr( $card_exp, 0, 2 ),
			"year"        => substr( $card_exp, 3    ),
			"cvv"         => $card_cvc,
		];

		if ( $this->service == "asaas" )
		{
			$tokenInfo[ "infoAsaas" ] = [
				"email"         => $email,
				"document"      => $cpf,
				"zipCode"       => $zipcode,
				"addressNumber" => $addrNumber,
				"cellPhone"     => $holder_phone,
				"userIp"        => $user_ip,
				"userId"        => $user->ID
			];
		}

		/**
		 * check if card hsa token
		 */
		$resp_card_tkn = $this->api->status_card( $tokenInfo );

		if ( !$resp_card_tkn[ "success" ] )
		{
			$this->log( json_encode( $resp_card_tkn ) );

			wc_add_notice( "{$notice} " . $resp_card_tkn[ "error" ], "error" );

			return [
				"result"   => "fail",
				"redirect" => ""
			];
		}

		$this->log( json_encode( $resp_card_tkn ) );

		$tokenIndex    = $resp_card_tkn[ "tokenIndex" ];

		$paymentInfo   = [
			"orderId"     => $order_id,
			"totalAmount" => ( int ) str_replace( ".", "", $order->get_total() ),
			"description" => "Marketplace Triibo: #{$order_id}",
			"tokenIndex"  => $tokenIndex,
			"name"        => $holder_name,
			"document"    => $holder_cpf,
			"phone"       => $holder_phone,
		];

		if ( $this->service == "asaas" )
		{
			$paymentInfo[ "infoAsaas" ] = [
				"email"        => $email,
				"userId"       => $user->ID,
				"userIp"       => $user_ip,
				"type"         => "CREDIT_CARD",
				"installments" => ( int ) $installments,
				"dueDate"      => date( "Y-m-d" )
			];
		}

		/**
		 * make the payment
		 */
		$resp_pay      = $this->api->do_payment( "card", $token, $paymentInfo );

		$this->log( json_encode( $resp_pay ) );

		if ( !$resp_pay[ "success" ] )
		{
			wc_add_notice( "{$notice} " . $resp_pay[ "error" ], "error" );

			return [
				"result"   => "fail",
				"redirect" => ""
			];
		}

		$order->update_meta_data( "_triibo_payments_code", [
			"type"      => "CREDIT_CARD",
			"gateway"   => $this->service,
			"paymentId" => $resp_pay[ "data" ][ "paymentInfo" ][ "id" ],
		] );

		$note  = "Payment ID: {$resp_pay[ "data" ][ "paymentInfo" ][ "id" ]}\n";
		$note .= "Gateway: " . $this->service;
		$order->add_order_note( $note );
		$order->payment_complete();
		$order->update_status( "processing", __( "Triibo_Payments: Pagamento aprovado", $this->id ) );
		$order->save();

		// Remove cart.
		WC()->cart->empty_cart();

		// Reduce stock for billets.
		if ( function_exists( "wc_reduce_stock_levels" ) ) wc_reduce_stock_levels( $order_id );

		return [
			"result"   => "success",
			"redirect" => $this->get_return_url( $order ),
		];
	}

	/**
	 * Register log
	 *
	 * @since 	1.0.0
	 * @param 	string 	$message
	 * @param 	boolean $is_error
	 * @return 	void
	 */
	public function log( $message, $is_error = false )
	{
		if ( $this->debug == "yes" )
		{
			$this->log->add( $this->id, $message );
		}
		else if ( $is_error )
		{
			$this->log->add( $this->id, $message );
		}
	}

}
