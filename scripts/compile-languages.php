<?php
/**
 * Compile languages/*.po to .mo (WordPress POMO).
 *
 * Usage: php scripts/compile-languages.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	if ( 'cli' === php_sapi_name() ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	} else {
		exit;
	}
}

$root     = dirname( __DIR__ );
$lang_dir = $root . '/languages';

require_once $root . '/scripts/lib/pomo/po.php';
require_once $root . '/scripts/lib/pomo/mo.php';

$files = glob( $lang_dir . '/multiai-chatbot-*.po' );
if ( ! $files ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI dev script; static message only.
	echo "Error: No .po files found in languages/\n";
	exit( 1 );
}

foreach ( $files as $po_path ) {
	$mo_path = preg_replace( '/\.po$/', '.mo', $po_path );
	$po      = new PO();
	if ( ! $po->import_from_file( $po_path ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI dev script; path escaped below.
		echo 'Error: Failed to import: ' . esc_html( $po_path ) . PHP_EOL;
		exit( 1 );
	}
	$mo = new MO();
	$mo->entries      = $po->entries;
	$mo->headers      = $po->headers;
	$mo->set_header( 'Project-Id-Version', 'MultiAI ChatBot' );
	if ( ! $mo->export_to_file( $mo_path ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI dev script; path escaped below.
		echo 'Error: Failed to write: ' . esc_html( $mo_path ) . PHP_EOL;
		exit( 1 );
	}
	echo 'compiled: ' . esc_html( basename( $mo_path ) ) . PHP_EOL;
}
