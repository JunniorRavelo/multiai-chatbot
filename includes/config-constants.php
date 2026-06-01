<?php
/**
 * wp-config.php constant helpers (with legacy CHATBOT_* fallback).
 *
 * @package Multch_Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string      $name   Preferred constant (e.g. MULTCH_OPENAI_API_KEY).
 * @param string|null $legacy Legacy constant name (e.g. CHATBOT_OPENAI_API_KEY).
 */
function multch_resolve_constant( string $name, ?string $legacy = null ): string {
	if ( defined( $name ) ) {
		$value = constant( $name );
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
	}

	if ( null !== $legacy && defined( $legacy ) ) {
		$value = constant( $legacy );
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}
		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
	}

	return '';
}

/**
 * @param string      $name   Preferred constant.
 * @param string|null $legacy Legacy constant name.
 */
function multch_constant_is_true( string $name, ?string $legacy = null ): bool {
	if ( defined( $name ) ) {
		return (bool) constant( $name );
	}
	if ( null !== $legacy && defined( $legacy ) ) {
		return (bool) constant( $legacy );
	}
	return false;
}
