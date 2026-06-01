<?php
/**
 * Widget namespace helpers (IDs, class prefix filters).
 *
 * @package Multch_Plugin_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default DOM id for the first floating widget root.
 */
function multch_plugin_default_root_id(): string {
	return (string) apply_filters( 'multch_plugin_root_id', 'multch-plugin-root' );
}

/**
 * CSS class prefix for the public widget (maicb).
 */
function multch_plugin_widget_class_prefix(): string {
	$prefix = (string) apply_filters( 'multch_widget_class_prefix', 'maicb' );
	$prefix = preg_replace( '/[^a-z0-9_-]/i', '', $prefix );
	return '' !== $prefix ? $prefix : 'maicb';
}

/**
 * Unique root element id for each widget instance.
 */
function multch_plugin_allocate_root_id( string $mode ): string {
	static $instance = 0;
	++$instance;

	if ( 1 === $instance && 'floating' === $mode ) {
		return multch_plugin_default_root_id();
	}

	return 'multch-plugin-root-' . $instance;
}
