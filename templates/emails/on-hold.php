<?php
/**
 * HTML email instructions.
 *
 * @author 		Mateus Costa <mateus@costamateus.com.br>
 * @package 	Triibo_Payments/Emails
 * @version 	1.0.0
 */
defined( "ABSPATH" ) || exit;

?>

<div style="margin: 36px auto;">
	<p>
		Caso tenha perdido o link para pagamento ou fechado antes da conclusão, você pode encontrá-lo na sua conta,
		<a href="<?php echo esc_url( get_permalink( get_option( "woocommerce_myaccount_page_id" ) ) ); ?>" title="Minha conta" >clicando aqui</a>.
	</p>
	<p style="margin:.5em 0 .25em 0;" >
		Ou se preferir, você pode acessar o seu boleto
		<a class="button" style="margin:1em auto;" href="<?php echo $link; ?>" target="_blank" >clicando aqui</a>.
		Ou ainda, você pode copiar o código de barras abaixo:
	</p>
	<p style="margin:.25em 0 .5em 0; font-size:14px; word-break:break-all;" ><?php echo $code; ?></p>
	<p><?php echo $message; ?></p>
</div>
