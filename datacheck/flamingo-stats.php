<?php
/**
 * Statistiche anti-spam nella cartella Spam di Flamingo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function datacheck_is_flamingo_spam_view() {
	return isset( $_GET['page'], $_GET['post_status'] )
		&& 'flamingo_inbound' === $_GET['page']
		&& 'spam' === $_GET['post_status']
		&& empty( $_GET['action'] );
}

// Chi ha bloccato il messaggio, dal primo elemento del log di spam
function datacheck_spam_source( $log ) {
	if ( empty( $log ) || ! is_array( $log ) ) {
		return array( 'other', 'Sconosciuto' );
	}

	$entry  = reset( $log );
	$agent  = isset( $entry['agent'] ) ? (string) $entry['agent'] : '';
	$reason = isset( $entry['reason'] ) ? (string) $entry['reason'] : '';

	if ( 'datacheck' === $agent ) {
		if ( 0 === strpos( $reason, 'Campo esca' ) ) {
			return array( 'dc-hp', 'DataCheck · campo esca' );
		}
		if ( preg_match( '/(\d+) secondi/', $reason, $m ) ) {
			return array( 'dc-time', 'DataCheck · inviato in ' . $m[1] . ' s' );
		}
		return array( 'dc-time', 'DataCheck · troppo veloce' );
	}

	$labels = array(
		'akismet'         => 'Akismet',
		'flamingo'        => 'Segnato a mano',
		'disallowed_list' => 'Parole vietate',
		'recaptcha'       => 'reCAPTCHA',
		'turnstile'       => 'Turnstile',
	);

	return isset( $labels[ $agent ] )
		? array( $agent, $labels[ $agent ] )
		: array( 'other', '' !== $agent ? $agent : 'Sconosciuto' );
}

// Colonna "Bloccato da"
add_filter( 'manage_flamingo_inbound_posts_columns', function ( $columns ) {
	if ( ! datacheck_is_flamingo_spam_view() ) {
		return $columns;
	}

	$new = array();
	foreach ( $columns as $key => $label ) {
		if ( 'date' === $key ) {
			$new['datacheck'] = 'Bloccato da';
		}
		$new[ $key ] = $label;
	}
	return $new;
} );

add_action( 'manage_flamingo_inbound_posts_custom_column', function ( $column, $post_id ) {
	if ( 'datacheck' === $column ) {
		list( , $label ) = datacheck_spam_source( get_post_meta( $post_id, '_spam_log', true ) );
		echo esc_html( $label );
	}
}, 10, 2 );

// Riepilogo sopra la tabella
add_action( 'flamingo_admin_updated_message', function () {
	if ( ! datacheck_is_flamingo_spam_view() ) {
		return;
	}

	global $wpdb;

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT p.post_date_gmt, pm.meta_value
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_spam_log'
		WHERE p.post_type = %s AND p.post_status = %s",
		'flamingo_inbound',
		'flamingo-spam'
	) );

	if ( ! $rows ) {
		return;
	}

	$counts = array();
	$day    = 0;
	$since  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );

	foreach ( $rows as $row ) {
		list( $key, $label ) = datacheck_spam_source( maybe_unserialize( $row->meta_value ) );
		if ( 0 === strpos( $key, 'dc-' ) ) {
			$label = $key;
		}
		$counts[ $label ] = ( $counts[ $label ] ?? 0 ) + 1;

		if ( $row->post_date_gmt >= $since ) {
			$day++;
		}
	}

	$hp   = $counts['dc-hp'] ?? 0;
	$time = $counts['dc-time'] ?? 0;
	unset( $counts['dc-hp'], $counts['dc-time'] );
	arsort( $counts );

	$parts = array();
	if ( $hp + $time ) {
		$parts[] = sprintf( '<strong>DataCheck %d</strong> (campo esca %d, troppo veloce %d)', $hp + $time, $hp, $time );
	}
	foreach ( $counts as $label => $n ) {
		$parts[] = sprintf( '%s %d', esc_html( $label ), $n );
	}

	$days   = defined( 'FLAMINGO_MOVE_TRASH_DAYS' ) ? (int) FLAMINGO_MOVE_TRASH_DAYS : 0;
	$period = $days > 0 ? sprintf( 'ultimi %d giorni', $days ) : 'tutta la cartella';

	printf(
		'<div class="notice notice-info inline"><p>Spam bloccato (%s): <strong>%d</strong>, di cui %d nelle ultime 24 ore — %s</p></div>',
		esc_html( $period ),
		count( $rows ),
		$day,
		implode( ' · ', $parts )
	);
} );
