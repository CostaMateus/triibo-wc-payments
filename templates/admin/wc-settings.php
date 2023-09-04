<?php
/**
 * Admin options screen
 *
 * @author 	    Mateus Costa <mateus@costamateus.com.br>
 * @package     Triibo_Payments/Admin
 * @version     1.0.0
 */
defined( "ABSPATH" ) || exit;

if ( "yes" == $this->get_option( "enabled" ) )
{
	if ( ! $this->using_supported_currency() &&
			! class_exists( "woocommerce_wpml" ) ) include dirname( __FILE__ ) . "/html-notice-currency-not-supported.php";

	if ( $this->node->empty( "user" ) || $this->node->empty( "pass" ) )
		include_once dirname( Triibo_Payments::FILE ) . "/templates/notices/missing-account-password.php";
}

?>

<h3><?php echo esc_html( $this->method_title ); ?></h3>
<?php echo wpautop( $this->method_description ); ?>

<table class="form-table">
	<?php $this->generate_settings_html(); ?>
</table>
