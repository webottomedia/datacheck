<?php
/**
 * Plugin Name:       DataCheck for CF7
 * Plugin URI:        https://github.com/webottomedia/datacheck
 * Description:       Controllo anti-spam per Contact Form 7. Aggiungi il tag [datacheck] al form per abilitarlo.
 * Version:           1.2.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Alberto Cattani
 * Update URI:        https://github.com/webottomedia/datacheck
 * Requires Plugins:  contact-form-7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Tempo minimo (secondi) tra caricamento pagina e invio
if ( ! defined( 'DATACHECK_MIN_SECONDS' ) ) {
	define( 'DATACHECK_MIN_SECONDS', 3 );
}

// Aggiornamenti dalle release GitHub
require __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
\YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/webottomedia/datacheck/',
	__FILE__,
	'datacheck'
)->getVcsApi()->enableReleaseAssets();

// Tag [datacheck]: campo esca + campo tempo
add_action( 'wpcf7_init', function () {
	wpcf7_add_form_tag( 'datacheck', function () {
		return '<span class="dc-field" aria-hidden="true">'
			. '<input type="text" name="dc-hp" value="" size="40" tabindex="-1" autocomplete="off">'
			. '<input type="hidden" name="dc-t" value="">'
			. '</span>';
	} );
} );

// Pulsante "datacheck" nell'editor del form (CF7 6.0+)
add_action( 'wpcf7_admin_init', function () {
	if ( ! class_exists( 'WPCF7_TagGeneratorGenerator' ) ) {
		return;
	}

	WPCF7_TagGenerator::get_instance()->add( 'datacheck', 'datacheck', function ( $contact_form, $options ) {
		$tgg = new WPCF7_TagGeneratorGenerator( $options['content'] );
		?>
		<header class="description-box">
			<h3>DataCheck anti-spam</h3>
			<p>Aggiunge al form un campo esca nascosto e un controllo sul tempo di compilazione. Gli invii sospetti vengono marcati come spam. Basta un tag per form, in qualsiasi punto.</p>
		</header>
		<div class="control-box">
			<input type="hidden" data-tag-part="basetype" value="datacheck">
		</div>
		<footer class="insert-box">
			<?php $tgg->print( 'insert_box_content' ); ?>
		</footer>
		<?php
	}, array( 'version' => '2' ) );
}, 60, 0 );

// CSS e JS inline
add_action( 'wp_enqueue_scripts', function () {
	wp_register_style( 'datacheck', false );
	wp_enqueue_style( 'datacheck' );
	wp_add_inline_style( 'datacheck', '.dc-field{position:absolute!important;left:-9999px!important;top:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important}' );

	// Secondi trascorsi dal caricamento, calcolati lato client: funziona anche con la cache di pagina
	wp_register_script( 'datacheck', false, array(), false, true );
	wp_enqueue_script( 'datacheck' );
	wp_add_inline_script( 'datacheck', 'document.addEventListener("submit",function(e){var f=e.target.querySelector&&e.target.querySelector("input[name=dc-t]");if(f){f.value=Math.floor(performance.now()/1000);}},true);' );
} );

// Controllo spam (solo per i form che contengono [datacheck])
add_filter( 'wpcf7_spam', function ( $spam, $submission ) {
	if ( $spam ) {
		return $spam;
	}

	$form = $submission->get_contact_form();
	if ( ! $form || ! $form->scan_form_tags( array( 'type' => 'datacheck' ) ) ) {
		return $spam;
	}

	// Campo esca compilato
	if ( ! empty( $_POST['dc-hp'] ) ) {
		$submission->add_spam_log( array(
			'agent'  => 'datacheck',
			'reason' => 'Campo esca compilato',
		) );
		return true;
	}

	// Invio troppo rapido (se JS è disattivato il campo resta vuoto e il controllo viene saltato)
	if ( isset( $_POST['dc-t'] ) && '' !== $_POST['dc-t'] && (int) $_POST['dc-t'] < DATACHECK_MIN_SECONDS ) {
		$submission->add_spam_log( array(
			'agent'  => 'datacheck',
			'reason' => sprintf( 'Invio dopo %d secondi (minimo %d)', (int) $_POST['dc-t'], DATACHECK_MIN_SECONDS ),
		) );
		return true;
	}

	return $spam;
}, 10, 2 );
