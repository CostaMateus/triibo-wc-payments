<?php
/**
 * Triibo Payments Rest class
 *
 * @author      Mateus Costa <mateus@costamateus.com.br>
 * @package 	Triibo_Payments/Rest
 * @version 	1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Triibo_Payments_Rest
{
	/**
	 * Initialize the plugin public actions.
     *
	 * @since   1.0.0
	 */
    public function __construct()
	{
		require_once dirname( __FILE__ ) . "/controllers/v1/class-triibo-cards-controller.php";
		require_once dirname( __FILE__ ) . "/controllers/v1/class-triibo-orders-controller.php";

        add_action( "rest_api_init", [ $this, "register_rest_routes" ] );
    }

    /**
     * Register REST API routes.
     *
	 * @since   1.0.0
	 * @return  void
     */
    public function register_rest_routes()
    {
        foreach ( self::get_rest_namespaces() as $controllers )
        {
            foreach ( $controllers as $controller_class )
            {
                $controller = new $controller_class();
                $controller->register_routes();
            }
        }
    }

    /**
     * Get API namespaces - new namespaces should be registered here.
     *
	 * @since   1.0.0
     * @return  array List of Namespaces and Main controller classes.
     */
    private static function get_rest_namespaces()
    {
        return apply_filters(
            "triibo_payments_rest_namespaces",
            [
                "triibo-payments/v1" => [
                    "cards"  => "Triibo_Cards_Controller",
                    "orders" => "Triibo_Orders_Controller",
                ]
            ]
        );
    }
}
