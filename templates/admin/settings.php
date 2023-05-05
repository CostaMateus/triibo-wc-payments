<?php
/**
 * Admin area settings view
 *
 * @author 	    Mateus Costa <mateus@costamateus.com.br>
 * @package     Triibo_Payments/Admin
 * @version     1.0.0
 */
defined( "ABSPATH" ) || exit;
?>

<div class="wrap">
	<h2>Triibo Payments</h2>
    <?php
        $url  = esc_url( admin_url( "admin.php?page=wc-settings&tab=checkout" ) );
        $text = __( "WC Configurações", Triibo_Payments::DOMAIN );
        $link = "<a href='{$url}' >{$text}</a>";

        echo "<p>{$link}</p>";
    ?>
</div>