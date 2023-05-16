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

	<?php if ( $type == "BILLET" ) : ?>
		<p style="margin:.5em 0 .25em 0;" >
			Ou se preferir, você pode acessar o seu boleto
			<a class="button" style="margin:1em auto;" href="<?php echo $link; ?>" target="_blank" >clicando aqui</a>.
			Ou ainda, você pode copiar o código de barras abaixo:
		</p>
		<p style="margin:.25em 0 .5em 0; font-size:14px; word-break:break-all;" ><?php echo $code; ?></p>
	<?php else : ?>
		<p style="margin:.5em 0 .25em 0;" >QR Code para pagamento do seu pedido</p>
		<img style="margin:.25em auto .5em auto; width:200px; height:200px" src="data:image/gif;base64,<?php echo $link; ?>" />

		<p style="margin:.5em 0 .25em 0;" >Ou se você preferir pode copiar o código PIX abaixo</p>
		<p style="margin:.25em 0 .5em 0; font-size:14px; word-break:break-all;" ><?php echo $code; ?></p>
	<?php endif;?>
</div>
