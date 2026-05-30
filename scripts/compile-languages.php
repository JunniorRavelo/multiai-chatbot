<?php
/**
 * Compile languages/*.po to .mo (WordPress POMO).
 *
 * Usage: php scripts/compile-languages.php
 */

$root     = dirname( __DIR__ );
$lang_dir = $root . '/languages';

require_once $root . '/scripts/lib/pomo/po.php';
require_once $root . '/scripts/lib/pomo/mo.php';

$files = glob( $lang_dir . '/chatbot-plugin-wp-*.po' );
if ( ! $files ) {
	fwrite( STDERR, "No .po files found in languages/\n" );
	exit( 1 );
}

foreach ( $files as $po_path ) {
	$mo_path = preg_replace( '/\.po$/', '.mo', $po_path );
	$po      = new PO();
	if ( ! $po->import_from_file( $po_path ) ) {
		fwrite( STDERR, "Failed to import: {$po_path}\n" );
		exit( 1 );
	}
	$mo = new MO();
	$mo->entries      = $po->entries;
	$mo->headers      = $po->headers;
	$mo->set_header( 'Project-Id-Version', 'MultiAI ChatBot' );
	if ( ! $mo->export_to_file( $mo_path ) ) {
		fwrite( STDERR, "Failed to write: {$mo_path}\n" );
		exit( 1 );
	}
	echo 'compiled: ' . esc_html( basename( $mo_path ) ) . PHP_EOL;
}
