<?php
/**
 * Triibo Orders Controller class
 *
 * @author      Mateus Costa <mateus@costamateus.com.br>
 * @package 	Triibo_Payments/Rest
 * @version 	1.0.0
 */
defined( "ABSPATH" ) || exit;

class Triibo_Orders_Controller extends WP_REST_Controller
{
	/**
	 * Endpoint namespace.
	 *
	 * @since   1.0.0
	 * @var 	string
	 */
	protected $namespace = "triibo-payments/v1";

	/**
	 * Route base.
	 *
	 * @since   1.0.0
	 * @var 	string
	 */
	protected $rest_base = "orders";

	/**
	 * Stores the request.
	 *
	 * @since   1.0.0
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
	 * @since   1.0.0
	 */
	public function __construct()
    {
        $this->log = ( function_exists( "wc_get_logger" ) ) ? wc_get_logger() : new WC_Logger();
    }

	/**
	 * Register the routes for orders.
	 *
	 * @since   1.0.0
	 * @return 	void
	 */
	public function register_routes()
	{
		register_rest_route(
			$this->namespace,
			"/$this->rest_base/update-payment",
			[
				[
					"methods"             => WP_REST_Server::READABLE, // GET
					"callback"            => [ $this, "update_order" ],
					"permission_callback" => "__return_true",
					"args"                => $this->get_collection_params(),
				],
			]
		);
	}

	/**
	 * Update order status
	 *
	 * @since   1.0.0
	 * @param 	WP_REST_Request $request
	 * @return 	null
	 */
	public function update_order( $request )
	{
        /**
         * Asaas status
         *
         * 'PENDING'                      : 'Aguardando pagamento',
         * 'RECEIVED'                     : 'Recebida',                 // saldo já creditado na conta
         * 'CONFIRMED'                    : 'Pagamento confirmado',     // saldo ainda não creditado
         * 'OVERDUE'                      : 'Vencida',
         * 'REFUNDED'                     : 'Estornada',
         * 'RECEIVED_IN_CASH'             : 'Recebida em dinheiro',     // não gera saldo na conta
         * 'REFUND_REQUESTED'             : 'Estorno Solicitado',
         * 'REFUND_IN_PROGRESS'           : 'Estorno em processamento', // liquidação já está agendada, cobrança será estornada após executar a liquidação
         * 'CHARGEBACK_REQUESTED'         : 'Recebido chargeback',
         * 'CHARGEBACK_DISPUTE'           : 'Em disputa de chargeback', // caso sejam apresentados documentos para contestação
         * 'AWAITING_CHARGEBACK_REVERSAL' : 'Disputa vencida, aguardando repasse da adquirente',
         * 'DUNNING_REQUESTED'            : 'Em processo de negativação',
         * 'DUNNING_RECEIVED'             : 'Recuperada',
         * 'AWAITING_RISK_ANALYSIS'       : 'Pagamento em análise',
         */

		$key = $request->get_header( "x-api-key" );

		if ( $key !== "54723162306C6F6A7052646D4C6170693030" || $key !== "0303960716C4D6462507A6F6C60326132745" )
			return new WP_REST_Response( [ "error" => "Unauthorized" ], 401 );

		$order_id = $request->get_param( "orderId"  );
		$status   = $request->get_param( "status"   );
		$approved = $request->get_param( "approved" );

		$order    = wc_get_order( $order_id );

        if ( $order )
        {
            if ( $approved )
            {
                $this->log->add( Triibo_Payments::DOMAIN, "INFO - finalize_order : ID " . $order_id . " : " . $status );

                $order->payment_complete();
                $order->update_status( "processing" );

                return;
            }

            $this->log->add( Triibo_Payments::DOMAIN, "INFO - order_failed : ID " . $order_id . " : " . $status );

            // TODO confirmar com Pedro, se 'cancelled' ou 'failed'
            $order->update_status( "failed" );

            return;
        }

        return;
	}
}
