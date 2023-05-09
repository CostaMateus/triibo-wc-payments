<?php
/**
 * Admin options screen
 *
 * @author 	    Mateus Costa <mateus@costamateus.com.br>
 * @package     Triibo_Payments/Admin
 * @version     1.0.0
 */
defined( "ABSPATH" ) || exit;
?>

<h3><?php echo esc_html( $this->method_title ); ?></h3>
<?php echo wpautop( $this->method_description ); ?>

<table class="form-table">
	<?php $this->generate_settings_html(); ?>
</table>
