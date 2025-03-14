<?php
/**
 * Triibo Cards Controller class
 *
 * @author      Mateus Costa <mateus@costamateus.com.br>
 * @package 	Triibo_Payments/Rest
 * @version 	1.0.0
 */
defined( "ABSPATH" ) || exit;

class Triibo_Cards_Controller extends WP_REST_Controller
{
	/**
	 * Endpoint namespace.
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	protected $namespace = "triibo-payments/v1";

	/**
	 * Route base.
	 *
	 * @since 	1.0.0
	 * @var 	string
	 */
	protected $rest_base = "cards";

	/**
	 * Stores the request.
	 *
	 * @since 	1.0.0
	 * @var 	array
	 */
	protected $request   = [];

	/**
	 * Logger
	 *
	 * @since 	1.0.0
	 * @var 	wc_get_logger|WC_Logger|null
	 */
	protected $log       = null;

	/**
	 * Construct
	 *
	 * @since 	1.0.0
	 */
	public function __construct()
    {
        $this->log = ( function_exists( "wc_get_logger" ) ) ? wc_get_logger() : new WC_Logger();
    }

	/**
	 * Register the routes for users.
	 */
	public function register_routes()
	{
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/brand",
			[
				[
					"methods"             => WP_REST_Server::READABLE, // GET
                    "callback"            => [ $this, "get_brand" ],
                    "permission_callback" => "__return_true",
					"args"                => $this->get_collection_params(),
                ],
			]
		);
	}

	/**
	 * Validate the credit card brand
	 *
	 * @since 	1.0.0
	 * @param 	WP_REST_Request $request
	 * @return 	string
	 */
	public function get_brand( $request )
	{
		$tpc     = Triibo_Payment_Card::get_instance();
		$service = $tpc->get_service();

		$bin     = $request->get_param( "bin" );
		$user_id = $request->get_param( "user_id" );

		// ASAAS
		if ( $service == "asaas" )
		{
			$bin  = str_replace( "-", "", $bin );

			$arr  = [
				"Visa"       => "/^4\d{12}(\d{3})?$/",
				"Mastercard" => "/^(5[1-5]\d{14}|2(22[1-9]\d{12}|2[3-9]\d{13}|[3-6]\d{14}|7[0-1]\d{13}|720\d{12})|6(0[0,3]\d{13}|3[7,9]\d{13}|7\d\d{13})|975230\d{10})$/",
				"Amex"       => "/^3[47]\d{13}$/",
				"Diners"     => "/^3(0[0-5]|[68]\d)\d{11}$/",
				"Elo"        => "/^((((438935)|(431274)|(457393)|(504175)|(451416)|(627780)|(636297)|(636368))\d{0,10})|((506[6,7])|(4576)|(4011)|(509\d)|(650\d)|(6516[5-7])|(65500[0-2,4-9])|(6550[1-2,4-9])|(65503[1-9]))\d{0,12})$/",
				"Discover"   => "/^6(?:011|5[0-9]{2})\d{12}$/",
				"Hipercard"  => "/^(606282\d{10}(\d{3})?)|(3841\d{15})$/",
			];

			$data = [
				"success"   => false,
				"brandInfo" => [
					"supported" => false,
					"brand"     => null
				],
			];

			foreach ( $arr as $key => $regex )
			{
				if ( preg_match_all( $regex, $bin, $matches ) )
				{
					$data[ "success"   ] = true;
					$data[ "brandInfo" ] = [
						"supported" => true,
						"brand"     => $key
					];

					return json_encode( $data );
				}
			}

			$tpc->log( json_encode( [ $user_id, $bin, $data ] ) );

			return json_encode( $data );
		}

		// CIELO
		if ( $service == "cielo" )
		{
			$api      = $tpc->get_api();
			$node     = $tpc->get_node();
			$token    = $api->validate_token( $user_id );

			$params   = [
				"payload" => [
					"gateway"    => $service,
					"cardNumber" => $bin,
				]
			];

			$response = $node->brand( $token, $params );

			if ( !$response[ "success" ] )
				$tpc->log( json_encode( [ $user_id, $params, $response ] ) );

			return json_encode( $response[ "data" ] );
		}
	}

}
