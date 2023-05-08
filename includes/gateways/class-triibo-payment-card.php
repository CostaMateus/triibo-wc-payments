<?php
/**
 * Triibo Payment Card class
 *
 * @author      Mateus Costa <mateus@costamateus.com.br>
 * @package 	Triibo_Payments/Gateways
 * @version 	1.0.0
 */
defined( "ABSPATH" ) || exit;

class Triibo_Payment_Card extends WC_Payment_Gateway_CC
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

        $this->id                 = Triibo_Payments::DOMAIN . "_card";
        $this->icon               = apply_filters( "woocommerce_" . $this->id . "_icon", plugins_url( "assets/images/triibo-25-cor-completo.png", Triibo_Payments::FILE ) );

        $this->method_title       = __( "Triibo Cartão de Crédito",                                  $this->id );
        $this->method_description = __( "Pagamentos com cartão de crédito usando a Triibo Gateway.", $this->id );
        $this->order_button_text  = __( "Prossiga para o pagamento",                                 $this->id );

        $this->supports           = apply_filters( $this->id . "_supports_array", [
            "default_credit_card_form",
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
				"label"       => __( "Ativar Triibo Cartão de Crédito",                           $this->id ),
				"default"     => "no",
			],

			"title"           => [
				"type"        => "text",
				"title"       => __( "Título",                                                    $this->id ),
				"description" => __( "Controla o título que o usuário vê durante o checkout.",    $this->id ),
				"default"     => __( "Triibo Cartão de Crédito",                                  $this->id ),
			],
			"description"     => [
				"type"        => "textarea",
				"title"       => __( "Descrição",                                                 $this->id ),
				"description" => __( "Controla a descrição que o usuário vê durante o checkout.", $this->id ),
				"default"     => __( "Pague com a Triibo Cartão",                                 $this->id ),
			],

			"service"         => [
				"type"        => "select",
				"title"       => __( "Serviço de pagamento",                                      $this->id ),
				"description" => __( "Escolha qual será o serviço de pagamento padrão.",          $this->id ),
				"default"     => "asaas",
				"class"       => "wc-enhanced-select",
				"options"     => [
					"asaas" => __( "Asaas", $this->id ),
					"cielo" => __( "Cielo", $this->id ),
				],
			],

			"debug"           => [
				"type"        => "checkbox",
				"title"       => __( "Debug Log",                                                 $this->id ),
				"label"       => __( "Ativar logger",                                             $this->id ),
				"description" => sprintf( __( "Registrar log de eventos, %s", $this->id ), $this->get_log_view() ),
				"default"     => "yes",
			],

			"configs" => [
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
		add_filter( "woocommerce_credit_card_form_start",                       [ $this, "form_fields"           ] );
	}

	public function checkout_scripts()
	{
		$load_scripts = false;

		if ( is_checkout() )         $load_scripts = true;
		if ( $this->is_available() ) $load_scripts = true;
		if ( false === $load_scripts ) return;

		$suffix       = defined( "SCRIPT_DEBUG" ) && SCRIPT_DEBUG ? "" : ".min";

		$css_path     = "assets/css/frontend/style{$suffix}.css";
		$css_ver      = date( "ymdHis", filemtime( plugin_dir_path( Triibo_Payments::FILE ) . $css_path ) );
		wp_enqueue_style(  "{$this->id}_checkout", plugins_url( $css_path, Triibo_Payments::FILE ), false, $css_ver, "all" );

		$js_path      = "assets/js/frontend/script{$suffix}.js";
		$js_ver       = date( "ymdHis", filemtime( plugin_dir_path( Triibo_Payments::FILE ) . $js_path  ) );
		wp_enqueue_script( "{$this->id}_checkout", plugins_url( $js_path,  Triibo_Payments::FILE ), [ "json2", "jquery", "woocommerce-extra-checkout-fields-for-brazil-front", "jquery-mask" ], $js_ver, true );

		$user         = wp_get_current_user();
		$token        = $this->api->validate_token( $user->ID );

		$url          = get_rest_url( null, "triibo-payments/v1/cards/brands" );

		wp_localize_script(
			"{$this->id}_checkout",
			"{$this->id}_params",
			[
				"user_id"       => $user->ID,
				"base_url"      => $url,
				"invalid_token" => $token ? false : true,
				"card_messages" => [
					"unsupported_card"   => __( "Essa bandeira não é suportada pelo Triibo Cartão de Crédito atualmente.", $this->id ),
					"session_error"      => __( "Ocorreu um error. Por favor, atualize a página.",                         $this->id ),
					"invalid_card"       => __( "O número do cartão de crédito é inválido.",                               $this->id ),
					"invalid_expiry"     => __( "A data de expiração é inválida, por favor, utilize o formato MM / AA.",   $this->id ),
					"invalid_cvv"        => __( "O código do cartão é inválido.",                                          $this->id ),
					"invalid_holder"     => __( "Informe o nome do titular do cartão.",                                    $this->id ),
					"invalid_holder_cpf" => __( "Informe o CPF do titular do cartão.",                                     $this->id ),
					"expired_date"       => __( "Por favor, verifique a data de expiração e utilize o formato MM / AA.",   $this->id ),
					"empty_installments" => __( "Selecione o número de parcelas.",                                         $this->id ),
					"interest_free"      => __( "sem juros",                                                               $this->id ),
					"general_error"      => __( "Não foi possível processar os dados do seu cartão de crédito, por favor, tente novamente ou entre em contato para receber ajuda.", $this->id ),
				]
			]
		);
	}

	/**
	 * Checkout form fields
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	function form_fields( $id )
	{
		if ( $id == $this->id )
		{
			$id = esc_attr( $this->id );

			echo "<p class='form-row form-row-wide' >
				<label for='{$id}-card-holder-name' >" . esc_html__( "Nome do titular", $this->id ) . "&nbsp;<span class='required' >*</span></label>
				<input id='{$id}-card-holder-name' class='input-text' type='text' placeholder='" . esc_attr__( "Informe o nome do titular", $this->id ) . "' " . $this->field_name( "card-holder-name" ) . " />
			</p>
			<p class='form-row form-row-wide' >
				<label for='{$id}-card-holder-cpf' >" . esc_html__( "CPF do titular", $this->id ) . "&nbsp;<span class='required'>*</span></label>
				<input id='{$id}-card-holder-cpf' class='input-text' type='tel' placeholder='" . esc_attr__( "000.000.000-00", $this->id ) . "' " . $this->field_name( "card-holder-cpf" ) . "/>
			</p>
			<p class='form-row form-row-wide' >
				<label for='{$id}-card-holder-phone' >" . esc_html__( "Celular do titular", $this->id ) . "&nbsp;<span class='required'>*</span></label>
				<input id='{$id}-card-holder-phone' class='input-text' type='tel' placeholder='" . esc_attr__( "(00) 00000-0000", $this->id ) . "' " . $this->field_name( "card-holder-phone" ) . "/>
			</p>";
		}
	}

}
