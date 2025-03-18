<?php
/**
 * Plugin Name: 			#Triibo_Payments
 * Plugin URI: 				https://triibo.com.br
 * Description: 			Meios de pagamento Triibo para WooCommerce. Dependências: Triibo API Services e WooCommerce.
 * Author: 					Mateus Costa
 * Author URI: 				https://costamateus.com.br/
 * Version: 				1.4.1
 * Text Domain: 			triibo_payments
 * Requires at least: 		6.4
 * Requires PHP: 			7.4
 * WC requires at least: 	8.0
 * WC tested up to: 		8.2
 *
 * @package 	Triibo_Payments
 * @version 	1.4.1
 */
defined( "ABSPATH" ) || exit;

if ( !function_exists( "is_plugin_active" ) ) require_once( ABSPATH . "wp-admin/includes/plugin.php" );

add_action( "plugins_loaded", [ "Triibo_Payments", "get_instance" ], 222 );

class Triibo_Payments
{
	/**
	 * Plugin version.
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	const VERSION = "1.4.1";

	/**
	 * Plugin domain.
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	const DOMAIN  = "triibo_payments";

	/**
	 * Plugin main file.
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	const FILE    = __FILE__;

	/**
	 * Instance of this class.
	 *
	 * @since 	1.0.0
	 * @var 	object
	 */
	protected static $instance = null;

	/**
	 * Construct
	 *
	 * @since 	1.3.0 	Added HPOS compatibility declaration
	 * @since 	1.1.0 	Added plugin change history
	 * @since 	1.0.0
	 */
	private function __construct()
    {
		if ( !is_plugin_active( "woocommerce/woocommerce.php" ) )
		{
			add_action( "admin_notices", [ $this, "woocommerce_missing_notice" ] );
			return;
		}

		if ( !is_plugin_active( "triibo-api-services/triibo-api-services.php" ) )
		{
			add_action( "admin_notices", [ $this, "triibo_api_services_missing_notice" ] );
			return;
		}

		if ( !is_plugin_active( "woocommerce-extra-checkout-fields-for-brazil/woocommerce-extra-checkout-fields-for-brazil.php" ) )
		{
			add_action( "admin_notices", [ $this, "ecfb_missing_notice" ] );
			return;
		}

		require_once ( dirname( self::FILE ) . "/includes/apis/class-triibo-payments-api.php"       );
		require_once ( dirname( self::FILE ) . "/includes/rest-api/class-triibo-payments-rest.php"  );
		require_once ( dirname( self::FILE ) . "/includes/gateways/class-triibo-payment-billet.php" );
		require_once ( dirname( self::FILE ) . "/includes/gateways/class-triibo-payment-card.php"   );
		require_once ( dirname( self::FILE ) . "/includes/gateways/class-triibo-payment-pix.php"    );

		add_filter( "plugin_action_links_" . plugin_basename( self::FILE ), [ $this, "plugin_action_links"      ]        );
		add_filter( "woocommerce_payment_gateways",                         [ $this, "register_gateways"        ], 99, 1 );

		/**
		 * @since 1.1.0
		 */
		add_filter( "plugin_row_meta",                                      [ $this, "plugin_row_meta"          ], 10, 3 );

		/**
		 * @since 1.3.0
		 */
		add_action( "before_woocommerce_init",                              [ $this, "setup_hpos_compatibility" ]        );

		add_action( "admin_menu",                                           [ $this, "add_submenu_page"         ], 11    );
		add_action( "triibo_api_service_add_button",                        [ $this, "add_btn"                  ], 11    );
		add_action( "triibo_api_service_list_plugin_node",                  [ $this, "add_info_dependency"      ],       );

		// Order Box Actions
		add_action( "woocommerce_order_actions",                                         [ $this, "add_order_box_action"   ] );
		add_action( "woocommerce_order_action_wc_triibo_payments_billet_recover_status", [ $this, "process_recover_status" ] );
		add_action( "woocommerce_order_action_wc_triibo_payments_pix_recover_status",    [ $this, "process_recover_status" ] );

		new Triibo_Payments_Rest();
	}

	/**
	 * Return an instance of this class.
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
	 * WooCommerce missing notice.
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public static function woocommerce_missing_notice()
	{
		require_once dirname( self::FILE ) . "/templates/notices/missing-woocommerce.php";
	}

	/**
	 * Triibo-Api-Service missing notice.
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public static function triibo_api_services_missing_notice()
	{
		require_once dirname( self::FILE ) . "/templates/notices/missing-triibo_api_services.php";
	}

	/**
	 * Extra Checkout Fields for Brazil missing notice.
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public static function ecfb_missing_notice()
	{
		require_once dirname( self::FILE ) . "/templates/notices/missing-ecfb.php";
	}

	/**
	 * Action links.
	 *
	 * @since 	1.0.0
	 * @param 	array $links
	 * @return 	array
	 */
	public static function plugin_action_links( $links )
    {
        $url            = esc_url( admin_url( "admin.php?page=wc-settings&tab=checkout" ) );
        $text           = __( "Configurações", self::DOMAIN );
		$plugin_links   = [];
		$plugin_links[] = "<a href='{$url}' >{$text}</a>";

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Get templates path.
	 *
	 * @since 	1.0.0
	 * @return 	string
	 */
	public static function get_templates_path()
    {
		return plugin_dir_path( self::FILE ) . "templates/";
	}

	/**
	 * Register the gateway for use
	 *
	 * @since 	1.0.0
	 * @param 	array $methods
	 * @return 	array
	 */
	public function register_gateways( $methods )
    {
		$methods[] = "Triibo_Payment_Billet";
		$methods[] = "Triibo_Payment_Card";
		$methods[] = "Triibo_Payment_Pix";

		return $methods;
	}

	/**
	 * Add link to changelog modal
	 *
	 * @since 	1.1.0
	 * @param 	array 	$plugin_meta
	 * @param 	string 	$plugin_file
	 * @param 	array 	$plugin_data
	 * @return 	array
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file, $plugin_data )
	{
		$path = path_join( WP_PLUGIN_DIR, $plugin_file );

		if ( DIRECTORY_SEPARATOR == "\\" )
			$path = str_replace( "/", "\\", $path );

        if ( self::FILE === $path )
		{
            $url = plugins_url( "readme.txt", self::FILE );

            $plugin_meta[] = sprintf(
                "<a href='%s' class='thickbox open-plugin-details-modal' aria-label='%s' data-title='%s'>%s</a>",
                add_query_arg( "TB_iframe", "true", $url ),
                esc_attr( sprintf( __( "More information about %s" ), $plugin_data[ "Name" ] ) ),
                esc_attr( $plugin_data[ "Name" ] ),
                __( "Histórico de alterações" )
            );
        }

        return $plugin_meta;
	}

	/**
	 * Setup WooCommerce HPOS compatibility.
	 *
	 * @since 	1.3.0
	 * @return 	void
	 */
	public function setup_hpos_compatibility() : void
	{
		if ( defined( "WC_VERSION" ) && version_compare( WC_VERSION, "7.1", "<" ) )
			return;

		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) )
		{
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				"custom_order_tables",
				__FILE__,
				true
			);
		}
	}

	/**
	 * Add submenu to Triibo menu
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public function add_submenu_page()
	{
		add_submenu_page(
			Triibo_Api_Services::get_name(),
			"Triibo Payments",
			"Payments",
			"manage_options",
			self::DOMAIN . "-settings",
			[ $this, "display_admin_menu_settings" ]
		);
	}

	/**
	 * Display admin menu settings
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public function display_admin_menu_settings()
	{
		require_once ( dirname( self::FILE ) . "/templates/admin/settings.php" );
	}

	/**
	 * Add text to ecosystem description
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public function add_btn()
	{
        $url  = esc_url( admin_url( "admin.php?page=wc-settings&tab=checkout" ) );
		$text = __( "WC Configurações", self::DOMAIN );
		$link = "<a href='{$url}' >{$text}</a>";

		echo "<p>Triibo Payments | {$link}</p>";
	}

	/**
	 * Add text to description which api it depends on
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public function add_info_dependency()
	{
		echo "<p> - Triibo Payments</p>";
	}

	/**
	 * Add custom action to order box.
	 *
	 * @since 	1.4.1 	Validating if the $theorder has the $code.
	 * @since 	1.0.0
	 * @param 	array 	$actions
	 * @return 	array
	 */
	public function add_order_box_action( $actions )
	{
		global $theorder;

		$billet = Triibo_Payment_Billet::get_instance();
		$pix    = Triibo_Payment_Pix::get_instance();

		$method = $theorder->get_payment_method();

		if ( $method != $billet->get_id() && $method != $pix->get_id() )
			return $actions;

		$code   = $theorder->get_meta( "_triibo_payments_code" );

		if ( !$code )
			return $actions;

		if ( !is_array( $code[ "invoice" ] ) && $theorder->has_status( "on-hold" ) )
		{
			if ( $method == $billet->get_id() )
				$actions[ "wc_{$billet->get_id()}_recover_status" ] = __( "Recuperar boleto - {$billet->get_method_title()}", $billet->get_id() );

			if ( $method == $pix->get_id() )
				$actions[ "wc_{$pix->get_id()}_recover_status"    ] = __( "Recuperar pix - {$pix->get_method_title()}", $pix->get_id() );
		}
		elseif ( $theorder->has_status( "on-hold" ) )
		{
			if ( $method == $billet->get_id() )
				$actions[ "wc_{$billet->get_id()}_recover_status" ] = __( "Atualizar status boleto - {$billet->get_method_title()}", $billet->get_id() );

			if ( $method == $pix->get_id() )
				$actions[ "wc_{$pix->get_id()}_recover_status"    ] = __( "Atualizar status pix - {$pix->get_method_title()}", $pix->get_id() );
		}

		return $actions;
	}

	/**
	 * Process custom action order to recover billet data/status.
	 *
	 * @since 	1.4.1 	Validating if the $order has the $code.
	 * @since 	1.0.0
	 * @param 	object 	$order
	 * @return 	void
	 */
	public function process_recover_status( $order )
	{
		$code     = $order->get_meta( "_triibo_payments_code" );

		if ( !$code )
			return;

		$id       = self::DOMAIN . ( ( $code[ "type" ] == "BILLET" ) ? "_billet" : "_pix" );
		$method   = ( $code[ "type" ] == "BILLET" ) ? Triibo_Payment_Billet::get_instance() : Triibo_Payment_Pix::get_instance();
		$api      = $method->get_api();

		$token    = $api->validate_token( $order->user_id );
		$response = $api->payment_status( $token, $code[ "gateway" ], $code[ "paymentId" ] );

		if ( $response[ "success" ] && isset( $response[ "data" ] ) )
		{
			$payment_info = $response[ "data" ][ "paymentStatus" ];

			// se invoice retornou, atualiza metadata
			if ( is_array( $payment_info[ "invoice" ] ) )
			{
				$code[ "invoice" ] = $payment_info[ "invoice" ];
				$order->update_meta_data( "_triibo_payments_code", $code );
			}

			// se teve mudança de status e foi aprovado, muda pra processando
			if ( $payment_info[ "changed" ] && $payment_info[ "approved" ] )
			{
				$order->payment_complete();
				$order->update_status( "processing", __( "Triibo_Payments: Pagamento aprovado", $id ) );
			}

			// se teve mudança de status e não foi aprovado, muda pra falha
			if ( $payment_info[ "changed" ] && !$payment_info[ "approved" ] )
			{
				$order->update_status( "failed", __( "Triibo_Payments: Pagamento malsucedido", $id ) );
			}

			$payment_info[ "orderId" ] = $order->ID;
			unset( $payment_info[ "invoice" ] );

			$method->log( "INFO - payment_status: " . json_encode( $payment_info ) );
		}
		else
		{
			$order->add_order_note( "Falha na consulta do dados/status do pagamento." );
		}

		$order->save();
	}

}