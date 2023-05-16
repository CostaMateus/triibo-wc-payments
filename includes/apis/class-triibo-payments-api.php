<?php
/**
 * Triibo Payments Api class
 *
 * @author      Mateus Costa <mateus@costamateus.com.br>
 * @package 	Triibo_Payments/Api
 * @version 	1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Triibo_Payments_Api
{
	/**
	 * Gateway class.
	 *
	 * @since 	1.0.0
	 * @var 	Triibo_Payment_Billet|Triibo_Payment_Card|Triibo_Payment_Pix
	 */
	protected $gateway;

	/**
	 * API class.
	 *
	 * @since 	1.0.0
	 * @var 	Triibo_Api_Node
	 */
	protected $node;

	/**
	 * Construct
	 *
	 * @since 	1.0.0
	 */
	public function __construct( $gateway = null, $node = null )
	{
		$this->node    = $node;
		$this->gateway = $gateway;
	}

	/**
	 * Generate token for use in API calls
	 *
	 * @since 	1.0.0
     * @param   string  $uid
     * @param   integer $user_id
	 * @return 	string
	 */
	public function generate_token( $uid, $user_id )
	{
		if ( $this->node->status() )
		{
			$resp = $this->node->auth( "uid: {$uid}" );

			if ( $resp[ "success" ] )
			{
				$token    = $resp[ "data"  ][ "token" ];
				$validity = ( new DateTime() )->format( "Y-m-d H:i:s" );

				update_user_meta( $user_id, "_triibo_auth_token",    $token    );
				update_user_meta( $user_id, "_triibo_auth_validity", $validity );

				return $token;
			}
			else
			{
				$this->log( "INFO - generate_token : " . json_encode( [
					"code"    => $resp[ "code"    ],
					"message" => $resp[ "message" ],
					"error"   => $resp[ "error"   ]
				] ) );

				return $this->generate_token( $uid, $user_id );
			}
		}
	}

	/**
	 * Checks token validity, if expired, generates a new one
	 *
	 * @since 	1.0.0
     * @param   integer $user_id
	 * @return 	string
	 */
	public function validate_token( $user_id )
	{
		$uid      = get_user_meta( $user_id, "_triibo_id",            true );
		$token    = get_user_meta( $user_id, "_triibo_auth_token",    true );
		$validity = get_user_meta( $user_id, "_triibo_auth_validity", true );

        if ( !$uid   ) return null;

		if ( !$token )
		{
			$this->log( "INFO - validate_token : user without token $user_id" );
			return $this->generate_token( $uid, $user_id );
		}
		else
		{
			$dtval = DateTime::createFromFormat( "Y-m-d H:i:s", $validity );
			$now   = new DateTime();
			$diff  = $now->diff( $dtval );

			if ( $diff->d > 0 || $diff->h > 10 )
			{
				$this->log( "INFO - validate_token : experied token $user_id" );
				return $this->generate_token( $uid, $user_id );
			}

			return $token;
		}
	}

	/**
	 * Checks if card has already been tokenized
	 *
	 * @since 	1.0.0
	 * @param 	string $token
	 * @param 	string $number
	 * @param 	string $brand
	 * @return 	array
	 */
	private function check_card_token( $token, $number, $brand )
	{
		$params   = [
			"payload" => [
				"gateway"  => $this->gateway->get_service(),
				"cardInfo" => [
					"number" => $number,
					"brand"  => $brand,
				]
			]
		];

		$response = $this->node->check_token( $token, $params );

		$this->log( "INFO - check_card_token : " . json_encode( [ $params, $response ] ) );

		return $response;
	}

	/**
	 * Tokeniza um cartão
	 *
	 * @since 	1.0.0
	 * @param 	string  $token
	 * @param 	array   $data
	 * @return 	array
	 */
	private function create_card_token( $token, $data )
	{
		$params   = [
			"payload" => [
				"gateway"  => $this->gateway->get_service(),
				"cardInfo" => [
					"holder"         => $data[ "holder"         ],
					"cardNumber"     => $data[ "cardNumber"     ],
					"expirationDate" => $data[ "expirationDate" ],
					"securityCode"   => $data[ "securityCode"   ],
					"brand"          => $data[ "brand"          ]
				],
			]
		];

        if ( $this->gateway->get_service() == "asaas" )
            $params[ "payload" ][ "cardAsaas" ] = $data[ "infoAsaas" ];

		$response = $this->node->create_token( $token, $params );

		$this->log( "INFO - create_card_token : " . json_encode( $response ) );

		return $response;
	}

	/**
	 * Deletes a tokenized card
	 *
	 * @since 	1.0.0
	 * @param 	string $token
	 * @param 	string $cardIndex
	 * @return 	array
	 */
	private function delete_card_token( $token, $cardIndex )
	{
		$params = [
			"payload" => [
				"gateway"    => $this->gateway->get_service(),
				"tokenIndex" => $cardIndex
			]
		];

		$response = $this->node->delete_token( $token, $params );

		$this->log( "INFO - delete_card_token : " . json_encode( [ $params, $response ] ) );

		return $response;
	}

	/**
	 * Returns the card's token, if it doesn't have it, tokenizes it.
	 *
	 * @since 	1.0.0
	 * @param 	array $posted
	 * @return 	array
	 */
	public function status_card( $posted )
	{
		$number   = substr( $posted[ "card_number" ], -4 );
		$response = $this->check_card_token( $posted[ "token" ], $number, $posted[ "brand" ] );

		// CARD NOT TOKENIZED
		if ( !$response[ "success" ] )
		{
			$data     = [
				"holder"         => $posted[ "holder"      ],
				"cardNumber"     => $posted[ "card_number" ],
				"expirationDate" => $posted[ "month"       ] . "/" . $posted[ "year" ],
				"securityCode"   => $posted[ "cvv"         ],
				"brand"          => $posted[ "brand"       ],
			];

            if ( $this->gateway->get_service() == "asaas" )
                $data[ "infoAsaas" ] = $posted[ "infoAsaas" ];

			// CREATING CARD TOKEN
			$response = $this->create_card_token( $posted[ "token" ], $data );

			// CARD TOKEN CREATION FAILURE
			if ( !$response[ "success" ] )
			{
				$error = "Falha ao consultar token.";
				$this->log( "ERROR - create_card_token : " . json_encode( [ $error, $response ] ), true );

				return [
					"success" => false,
					"error"   => "$error (cod EP2)"
				];
			}

			$created    = true;
			$tokenIndex = $response[ "data" ][ "tokenInfo" ][ "index" ];
		}
		// CARD ALREADY TOKENIZED
		else
		{
			$created    = false;
			$tokenIndex = $response[ "data" ][ "tokenInfo" ][ "index" ];
		}

		return [
			"success"    => true,
			"cretead"    => $created,
			"tokenIndex" => $tokenIndex,
		];
	}

	/**
	 * Process payment
	 *
	 * @since 	1.0.0
	 * @param 	string 	$method
	 * @param 	string 	$token
	 * @param 	array 	$data
	 * @return 	array
	 */
	public function do_payment( $method, $token, $data )
	{
		$descrip  = $this->treatDescription( $data[ "description" ] );

		$params   = [
			"payload" => [
				"gateway" => $this->gateway->get_service(),
				"order"   => [
					"orderId"     => $data[ "orderId"     ],
					"totalAmount" => $data[ "totalAmount" ], // em centavos
					"description" => $descrip,
					// "tokenIndex"  => $data[ "tokenIndex"  ],
					"name"        => $data[ "name"        ],
					"document"    => $data[ "document"    ],
					"cellPhone"   => $data[ "phone"       ],
				],
			]
		];

		if ( $method == "card" )
			$params[ "payload" ][ "order" ][ "tokenIndex" ] = $data[ "tokenIndex" ];

        if ( $this->gateway->get_service() == "asaas" )
            $params[ "payload" ][ "orderAsaas" ] = $data[ "infoAsaas" ];

		$response = $this->node->payment( $token, $params );

		$this->log( "INFO - do_payment: " . json_encode( [ $params, $response ] ) );

		return $response;
	}

	/**
	 * Check payment status
	 *
	 * @since 	1.0.0
	 * @param 	string 	$token
	 * @param 	string 	$gateway
	 * @param 	string 	$payment_id
	 * @return 	array
	 */
	public function payment_status( $token, $gateway, $payment_id )
	{
		$params   = [
			"payload" => [
				"gateway"     => $gateway,
				"paymentInfo" => [
					"id" => $payment_id,
				],
			]
		];

		$response = $this->node->payment_status( $token, $params );

		$this->log( "INFO - payment_status: " . json_encode( [ $params, $response ] ) );

		return $response;
	}

	/**
	 * Treat description
	 *
	 * @since 	1.0.0
	 * @param 	string $text
	 * @return 	string
	 */
	private function treatDescription( $text )
	{
		$divider = "-";

        // replace non letter or digits by divider
        $text    = preg_replace( "~[^\pL\d]+~u", $divider, $text );

        // transliterate
        $text    = iconv( "utf-8", "us-ascii//TRANSLIT", $text );

        // remove unwanted characters
        $text    = preg_replace( "~[^-\w]+~", "", $text );

        // trim
        $text    = trim( $text, $divider );

        // remove duplicate divider
        $text    = preg_replace( "~-+~", $divider, $text );

		$text    = str_replace( $divider, " ", $text );

		return $text;
	}

	/**
	 * Register log
	 *
	 * @since 	1.0.0
	 * @param 	string 	$message
	 * @param 	boolean $is_error
	 * @return 	void
	 */
	private function log( $message, $is_error = false )
	{
		$id  = $this->gateway->get_id();
		$log = $this->gateway->get_log();

		if ( $this->gateway->get_debug() == "yes" )
		{
			$log->add( $id, $message );
		}
		else if ( $is_error )
		{
			$log->add( $id, $message );
		}
	}
}
