<?php
defined( 'ABSPATH' ) || exit;

// Extract script tags from form HTML to prevent breaking the template script block
$form_html = $args['form'];
$form_scripts = '';

// Extract all script tags (including content)
if ( preg_match_all( '/<script[^>]*>.*?<\/script>/is', $form_html, $matches ) ) {
	$form_scripts = implode( "\n", $matches[0] );
	// Remove script tags from form HTML
	$form_html = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $form_html );
}
?>

<script type="text/template" id="tmpl-rey-request-quote-modal">

	<div class="rey-requestQuote-modal --hidden" data-id="{{data.id}}">

		<?php if( $modal_title = $args['defaults']['title'] ): ?>
			<h3 class="rey-requestQuote-modalTitle"><?php echo $modal_title; ?></h3>
		<?php endif; ?>

		<# if( data.title ){ #>
			<p class="rey-requestQuote-productData">
				<strong class="__title">{{{data.title}}}</strong>
				<# if( data.sku ){ #>
					&nbsp;<strong class="__sku">{{{data.sku}}}</strong>
				<# } #>
			</p>
		<# } #>

		<?php echo $form_html; ?>
	</div>

</script>

<?php if( ! empty( $form_scripts ) ): ?>
	<?php echo $form_scripts; ?>
<?php endif; ?>
