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

/**
 * Write a message to STDERR (CLI dev script only; excluded from production ZIP).
 *
 * @param string $message Message to write (include trailing newline if needed).
 */
function maicb_cli_stderr( string $message ): void {
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI dev tooling; STDERR is the standard error channel for compile-languages.
	fwrite( STDERR, $message );
}

$root     = dirname( __DIR__ );
$lang_dir = $root . '/languages';

require_once $root . '/scripts/lib/pomo/po.php';
require_once $root . '/scripts/lib/pomo/mo.php';

$files = glob( $lang_dir . '/multiai-chatbot-*.po' );
if ( ! $files ) {
	maicb_cli_stderr( "No .po files found in languages/\n" );
	exit( 1 );
}

foreach ( $files as $po_path ) {
	$mo_path = preg_replace( '/\.po$/', '.mo', $po_path );
	$po      = new PO();
	if ( ! $po->import_from_file( $po_path ) ) {
		maicb_cli_stderr( "Failed to import: {$po_path}\n" );
		exit( 1 );
	}
	$mo = new MO();
	$mo->entries      = $po->entries;
	$mo->headers      = $po->headers;
	$mo->set_header( 'Project-Id-Version', 'MultiAI ChatBot' );
	if ( ! $mo->export_to_file( $mo_path ) ) {
		maicb_cli_stderr( "Failed to write: {$mo_path}\n" );
		exit( 1 );
	}
	echo 'compiled: ' . esc_html( basename( $mo_path ) ) . PHP_EOL;
}
