<?php
/**
 * Plugin Name:          #Triibo_WooCommerce_Payments
 * Description:          Meios de pagamento Triibo para WooCommerce. Dependências: Triibo API Services e WooCommerce.
 * Author:               Mateus Costa
 * Author URI:           https://costamateus.com.br/
 * Version:              1.0.0
 * Text Domain:          triibo_wc_payments
 * RequiresPHP:          7.4
 * RequiresWP:           5.9
 * WC requires at least: 5.8
 * WC tested up to:      6.3
 *
 * @package 			 Triibo_WC_Payments
 * @version 			 1.0.0
 */
defined( "ABSPATH" ) || exit;

if ( !function_exists( "is_plugin_active") ) include_once( ABSPATH . "wp-admin/includes/plugin.php" );

add_action( "plugins_loaded", [ "Triibo_WC_Payments", "get_instance" ], 222 );

class Triibo_WC_Payments
{
	/**
	 * Plugin version.
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	const VERSION = "1.0.0";

	/**
	 * Plugin domain.
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	const DOMAIN  = "triibo_wc_payments";

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

		include_once ( dirname( self::FILE ) . "/includes/gateways/class-triibo-payment-billet.php" );
		include_once ( dirname( self::FILE ) . "/includes/gateways/class-triibo-payment-card.php"   );
		include_once ( dirname( self::FILE ) . "/includes/gateways/class-triibo-payment-pix.php"    );

		add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), [ $this, "plugin_action_links" ]        );
		add_filter( "woocommerce_payment_gateways",                       [ $this, "register_gateways"   ], 99, 1 );

		// include_once dirname( __FILE__ ) . "/includes/class-triibo-assinatura-api.php";
		// require_once dirname( __FILE__ ) . "/includes/rest-api/class-triibo-assinatura-rest.php";

		// add_action( "admin_menu",                          [ $this, "add_submenu_page"    ], 11 );
		// add_action( "triibo_api_service_add_button",       [ $this, "add_btn"             ], 11 );
		// add_action( "triibo_api_service_list_plugin_node", [ $this, "add_info_dependency" ],    );
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
		include_once dirname( __FILE__ ) . "/templates/notices/missing-woocommerce.php";
	}

	/**
	 * Triibo-Api-Service missing notice.
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public static function triibo_api_services_missing_notice()
	{
		include_once dirname( __FILE__ ) . "/templates/notices/missing-triibo_api_services.php";
	}

	/**
	 * Extra Checkout Fields for Brazil missing notice.
	 *
	 * @since 	1.0.0
	 * @return 	void
	 */
	public static function ecfb_missing_notice()
	{
		include_once dirname( __FILE__ ) . "/templates/notices/missing-ecfb.php";
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

}