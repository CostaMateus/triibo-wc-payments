<?php
/**
 * Triibo FS Data Delete Controller class
 *
 * @author      Mateus Costa <mateus@costamateus.com.br>
 * @package 	Triibo/Rest
 * @version 	1.4.0
 */
defined( "ABSPATH" ) || exit;

class Triibo_FS_Data_Delete_Controller extends WP_REST_Controller
{
	/**
	 * Endpoint namespace.
	 *
	 * @since 	1.4.0
	 * @var 	string
	 */
	protected $namespace = "triibo/v1";

	/**
	 * Route base.
	 *
	 * @since 	1.4.0
	 * @var 	string
	 */
	protected $rest_base = "delete";

	/**
	 * Stores the request.
	 *
	 * @since 	1.4.0
	 * @var 	array
	 */
	protected $request   = [];

	/**
	 * Construct
	 *
	 * @since 	1.4.0
	 */
	public function __construct() {}

	/**
	 * Register the routes for orders.
	 *
	 * @since 	1.4.0
	 * @return 	void
	 */
	public function register_routes()
	{
		register_rest_route(
			$this->namespace,
			"/{$this->rest_base}/fs-data",
			[
				[
					"methods"             => WP_REST_Server::CREATABLE, // POST
					"callback"            => [ $this, "delete_images"     ],
					"permission_callback" => [ $this, "permissions_check" ],
					"args"                => [
						"posts" => [
							"type"        => "array",
							"required"    => true,
							"description" => "Lista de post_id a serem excluídos",
							"items"       => [ "type" => "string" ]
						],
						"type" => [
							"type"        => "string",
							"required"    => true,
							"enum"        => [ "attachment", "product" ],
							"description" => "Tipo do post (attachment ou product) que será excluído"
						]
					]
				]
			]
		);
	}

	/**
	 *
	 * @param 	WP_REST_Request 	$request
	 * @return 	bool
	 */
	public function permissions_check( WP_REST_Request $request )
	{
		return in_array(
			$request->get_header( "x-api-key" ),
			[
				"54723162306C6F6A7052646D4C6170693030",
				"0303960716C4D6462507A6F6C60326132745"
			]
		);
	}

	/**
	 *
	 * @param 	WP_REST_Request 	$request
	 * @return 	WP_REST_Response|WP_Error
	 */
    public function delete_images( WP_REST_Request $request )
	{
        $posts   = $request->get_param( "posts" );
        $type    = $request->get_param( "type"  );
        $results = [];

        foreach ( $posts as $post_id )
		{
			$response = false;

			if ( $type === "product" )
				$response = wp_delete_post( $post_id, true );

			if ( $type === "attachment" )
				$response = wp_delete_attachment( $post_id, true );

			if ( !$response )
			{
				$results[] = [
					"post_id" => $post_id,
					"status"  => "error",
				];
			}
			else
			{
				$results[] = [
					"post_id" => $post_id,
					"status"  => "success",
				];
			}
        }

        return rest_ensure_response( $results );
    }
}
