<?php
/**
 * Triibo Payment Pix class
 *
 * @author      Mateus Costa <mateus@costamateus.com.br>
 * @package 	Triibo_Payments/Gateways
 * @version 	1.0.0
 */
defined( "ABSPATH" ) || exit;

class Triibo_Payment_Pix extends WC_Payment_Gateway
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
	 * Message of checkout page
	 *
	 * @var 	string
	 * @since 	1.0.0
	 */
	protected $message_pre     = "";

	/**
	 * Message of thank_you and user order page
	 *
	 * @var 	string
	 * @since 	1.0.0
	 */
	protected $message_pos     = "";

	/**
	 * Billet expiration date
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	protected $due_date        = null;

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

        $this->id                 = Triibo_Payments::DOMAIN . "_pix";
        $this->icon               = apply_filters( "woocommerce_" . $this->id . "_icon", plugins_url( "assets/images/triibo-25-cor-completo.png", Triibo_Payments::FILE ) );

        $this->method_title       = __( "Triibo PIX",                                  $this->id );
        $this->method_description = __( "Pagamentos com PIX usando a Triibo Gateway.", $this->id );
        $this->order_button_text  = __( "Efetuar pagamento",                           $this->id );

        $this->supports           = apply_filters( $this->id . "_supports_array", [
            "products",
        ] );

        // Load the form fields.
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();

        // Define user set variables.
        $this->title              = $this->get_option( "title"         );
        $this->description        = $this->get_option( "description"   );
		$this->due_date           = $this->get_option( "due_date", "2" );
		$this->message_pre        = $this->get_option( "message_pre",  );
		$this->message_pos        = $this->get_option( "message_pos",  );
        $this->service            = $this->get_option( "service"       );
        $this->debug              = $this->get_option( "debug"         );

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
		$available = $this->get_option( "enabled" ) === "yes" &&
					 $this->node->status() && $this->using_supported_currency();

		if ( !is_plugin_active( "woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php" ) )
			$available = false;

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
			"enabled"     => [
				"type"        => "checkbox",
				"title"       => __( "Ativar / Desativar",                                        $this->id ),
				"label"       => __( "Ativar Triibo PIX",                                         $this->id ),
				"default"     => "no",
			],

			"title"       => [
				"type"        => "text",
				"title"       => __( "Título",                                                    $this->id ),
				"description" => __( "Controla o título que o usuário vê durante o checkout.",    $this->id ),
				"default"     => __( "Triibo PIX",                                                $this->id ),
			],
			"description" => [
				"type"        => "textarea",
				"title"       => __( "Descrição",                                                 $this->id ),
				"description" => __( "Controla a descrição que o usuário vê durante o checkout.", $this->id ),
				"default"     => __( "Pague com a Triibo PIX",                                    $this->id ),
			],
			"due_date"    => [
				"type"        => "number",
				"title"       => __( "Validade do PIX",                                           $this->id ),
				"description" => __( "Informe qual o prazo de validade do PIX, em dias.<br>
									  Mínimo 1.",                                                 $this->id ),
				"default"     => 2,
				"custom_attributes" => [
					"maxlength" => 2,
					"min"       => 1,
				],
            ],
			"message_pre"     => [
				"type"        => "textarea",
				"title"       => __( "Mensagem de instrução 1",                                   $this->id ),
				"description" => __( "Controla a mensagem de instrução que o usuário
									  vê na tela de finalização do pedido.",                      $this->id ),
				"default"     => __( "Ao finalizar a compra, você terá acesso ao QR Code e poderá pagar no seu aplicativo favorito do PIX.<br><br> * O pedido será confirmado somente após o pagamento ser confirmado.", $this->id ),
			],
			"message_pos"     => [
				"type"        => "textarea",
				"title"       => __( "Mensagem de instrução 2",                                   $this->id ),
				"description" => __( "Controla a mensagem de instrução que o usuário
									  vê na tela de obrigado e na tela de meus pedidos.",         $this->id ),
				"default"     => __( "Utilize o seu aplicativo favorito do PIX para ler o QR Code ou copie o código abaixo para efetuar o pagamento.", $this->id ),
			],

			"service"     => [
				"type"        => "select",
				"title"       => __( "Serviço de pagamento",                                      $this->id ),
				"description" => __( "Escolha qual será o serviço de pagamento padrão.",          $this->id ),
				"default"     => "asaas",
				"class"       => "wc-enhanced-select",
				"options"     => [
					"asaas" => __( "Asaas", $this->id ),
				],
			],

			"debug"       => [
				"type"        => "checkbox",
				"title"       => __( "Debug Log",                                                 $this->id ),
				"label"       => __( "Ativar logger",                                             $this->id ),
				"description" => sprintf( __( "Registrar log de eventos, %s", $this->id ), $this->get_log_view() ),
				"default"     => "yes",
			],

			"configs"     => [
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
		add_action( "woocommerce_update_options_payment_gateways_" . $this->id, [ $this, "process_admin_options" ] );
		add_action( "woocommerce_thankyou_"                        . $this->id, [ $this, "thankyou_page"         ] );
		add_action( "woocommerce_email_after_order_table",                      [ $this, "email_instructions"    ] );

		if ( is_account_page() )
			add_action( "woocommerce_order_details_after_order_table", [ $this, "order_page" ] );
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

			echo "<p>{$this->message_pre}</p>";
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
		$notice      = "<b>" . $this->title . "</b>:";

		$order       = wc_get_order( $order_id );
        $user        = wp_get_current_user();
		$user_ip     = $this->get_user_ip();
		$token       = $this->api->validate_token( $user->ID );

		$f_name      = WC()->checkout()->get_value( "billing_first_name" );
		$l_name      = WC()->checkout()->get_value( "billing_last_name"  );
		$cpf         = WC()->checkout()->get_value( "billing_cpf"        );
		$email       = WC()->checkout()->get_value( "billing_email"      );
		$phone       = WC()->checkout()->get_value( "billing_phone"      );

		$paymentInfo = [
			"orderId"     => $order_id,
			"totalAmount" => ( int ) str_replace( ".", "", $order->get_total() ),
			"description" => "Marketplace Triibo: #{$order_id}",
			"name"        => "{$f_name} {$l_name}",
			"document"    => preg_replace( "/[^0-9]/", "", $cpf   ),
			"phone"       => preg_replace( "/[^0-9]/", "", $phone ),
		];

		if ( $this->service == "asaas" )
		{
			$due_date = date( "Y-m-d" ) . " +{$this->due_date} days";
			$due_date = date( "Y-m-d", strtotime( $due_date ) );

			$paymentInfo[ "infoAsaas" ] = [
				"email"        => $email,
				"userId"       => $user->ID,
				"userIp"       => $user_ip,
				"type"         => "PIX",
				"installments" => 1,
				"dueDate"      => $due_date
			];
		}

		$resp_pay      = $this->api->do_payment( "pix", $token, $paymentInfo );

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
			"type"      => "PIX",
			"gateway"   => $this->service,
			"paymentId" => $resp_pay[ "data" ][ "paymentInfo" ][ "id"      ],
			"invoice"   => $resp_pay[ "data" ][ "paymentInfo" ][ "invoice" ]
		] );

		$note  = "Payment ID: {$resp_pay[ "data" ][ "paymentInfo" ][ "id" ]}\n";
		$note .= "Gateway: " . $this->service;
		$order->add_order_note( $note );
		$order->update_status( "on-hold", __( "Triibo_Payments: Aguardando pagamento", $this->id ) );
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
	 * Add content to the WC emails.
	 *
	 * @since 	1.0.0
	 * @param 	object 	$order
	 * @return 	void
	 */
	public function email_instructions( $order )
	{
		if ( $order->get_payment_method() != $this->id ) return;

		$code    = $order->get_meta( "_triibo_payments_code" );
		$invoice = $code[ "invoice" ];

		if ( !empty( $invoice ) )
		{
			$data    = [
				"type" => $code[ "type" ],
				"link" => $invoice[ "qrCode"  ],
				"code" => $invoice[ "pixCode" ],
			];

			wc_get_template(
				"on-hold.php",
				$data,
				"",
				Triibo_Payments::get_templates_path() . "emails/"
			);
		}
	}

	/**
	 * Thank You page message.
	 *
	 * @since 	1.0.0
	 * @param 	int 	$order_id
	 * @return 	void
	 */
	public function thankyou_page( $order_id )
	{
		$order = wc_get_order( $order_id );

		if ( $order->get_payment_method() != $this->id ) return;

		$code  = $order->get_meta( "_triibo_payments_code" );

		$this->render_code( $code[ "invoice" ] );
	}

	/**
	 * Order Page message
	 *
	 * @since 	1.0.0
	 * @param 	object 	$order
	 * @return 	void
	 */
	public function order_page( $order )
	{
		if ( $order->get_payment_method() != $this->id ) return;

		$code = $order->get_meta( "_triibo_payments_code" );

		$this->render_code( $code[ "invoice" ] );
	}

	/**
	 * Render pix code on thnak_you/order page
	 *
	 * @since 	1.0.0
	 * @param 	array 	$invoice
	 * @return 	void
	 */
	private function render_code( $invoice )
	{
		if ( !empty( $invoice ) )
		{
			$link = $invoice[ "qrCode"  ];
			$code = $invoice[ "pixCode" ];

			?>
			<div class="tpb-pix-container" style="text-align:center; margin:20px 0;" >
				<div class="tpb-pix-instructions" >
					<?php echo $this->message_pos; ?>
				</div>
				<input type="hidden" value="<?php echo $code; ?>" id="copiar" >

				<img style="max-width:350px; width:50%; min-width:200px; max-height:350px; height:50%; min-height:200px;"
					src="data:image/gif;base64,<?php echo $link; ?>" />

				<p class="tpb-pix-p" style="font-size:14px; margin:.5em 0; word-break:break-all;" >Código PIX</p>
				<p class="tpb-pix-p" style="font-size:14px; margin:.5em 0; word-break:break-all;" ><?php echo $code; ?></p>

				<button class="button tpb-pix-button-copy-code" style="margin:1em auto;" onclick="copyCode()" >
					<?php echo __( "Copiar o código acima", $this->id ); ?>
				</button>
				<div class="tpb-pix-response-output inactive" style="margin:1em .5em; padding:.2em 1em; border:2px solid #46b450; display:none;" aria-hidden="true" >
					<?php echo __( "Código copiado para a área de transferência.", $this->id ); ?>
				</div>
			</div>
			<script>
				function copyCode()
				{
					let copyText = document.getElementById( "copiar" );
					copyText.type = "text";
					copyText.select();
					copyText.setSelectionRange( 0, 99999 );
					document.execCommand( "copy" );
					copyText.type = "hidden";

					if ( jQuery( "div.tpb-pix-response-output" ) )
						jQuery( "div.tpb-pix-response-output" ).show();
					else
						alert( "Código copiado para a área de transferência." );

					return false;
				}
			</script>
			<?php
		}
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
