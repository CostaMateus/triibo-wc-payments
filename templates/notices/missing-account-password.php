<?php
/**
 * Admin View: Notice - Email missing
 *
 * @author 		Mateus Costa <mateus@costamateus.com.br>
 * @package 	WooCommerce_Triibo_Payments/Admin/Notices
 * @version 	1.0.0
 */
defined( "ABSPATH" ) || exit;

?>

<div class="error inline" >
	<p>
		<strong>
			<?php _e( $this->method_title . " - desativado", "triibo_payments" ); ?>
		</strong>:
		<?php _e( "Você deve informar os dados de acesso da API. Atualize as credenciais {$this->get_global_config()}.", "triibo_payments" ); ?>
	</p>
</div>
