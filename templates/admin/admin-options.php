<?php
/** @var WC_Payment_Gateway $gateway */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
?>

<h2><?php esc_html( $gateway->get_method_title() ); ?></h2>
<?php wp_kses_post( wpautop( $gateway->get_method_description() ) ); ?>
<?php $gateway->checks(); ?>
<table class="form-table">
	<?php $gateway->generate_settings_html( $gateway->get_form_fields(), true ); ?>
</table>
