<?php

/**
 * Test file for @type tag handling.
 *
 * @since 1.0.0
 */

/**
 * Function with hash notation in @param.
 *
 * @since 1.0.0
 *
 * @param array $args {
 *     Optional. Array of arguments.
 *
 *     @type bool   $enabled   Whether the feature is enabled. Default false.
 *     @type string $label     The label to display.
 *     @type int    $max_items Maximum number of items. Default 10.
 * }
 * @return bool True on success.
 */
function function_with_hash_notation( $args ) {
	return true;
}

/**
 * Function with multiple hash notation params.
 *
 * @since 2.0.0
 *
 * @param array $options {
 *     Configuration options.
 *
 *     @type string $name   The name.
 *     @type int    $count  The count.
 * }
 * @param array $settings {
 *     Additional settings.
 *
 *     @type bool $active Whether active.
 * }
 * @return void
 */
function function_with_multiple_hash_params( $options, $settings ) {
}

/**
 * Function with simple params (no hash notation).
 *
 * @since 1.0.0
 *
 * @param string $name  The name.
 * @param int    $count The count.
 * @return bool Success.
 */
function function_with_simple_params( $name, $count ) {
	return true;
}

/**
 * Function with hash notation in @return.
 *
 * @since 1.0.0
 *
 * @return array {
 *     Result data.
 *
 *     @type int[]    $updated An array of updated IDs.
 *     @type int[]    $skipped An array of skipped IDs.
 *     @type string[] $errors  An array of error messages.
 * }
 */
function function_with_return_hash() {
	return array();
}

/**
 * Function with hash notation in both @param and @return.
 *
 * @since 1.0.0
 *
 * @param array $options {
 *     Input options.
 *
 *     @type string $mode   The processing mode.
 *     @type bool   $force  Whether to force the operation.
 * }
 * @return array {
 *     Output data.
 *
 *     @type bool   $success Whether the operation succeeded.
 *     @type string $message A status message.
 * }
 */
function function_with_param_and_return_hash( $options ) {
	return array();
}

/**
 * Function with mixed params: some with hash, some without.
 *
 * @since 1.0.0
 *
 * @param string $name Simple string param.
 * @param array  $config {
 *     Configuration array.
 *
 *     @type int  $timeout Timeout in seconds.
 *     @type bool $retry   Whether to retry on failure.
 * }
 * @param int    $limit Simple int param.
 * @return bool Success.
 */
function function_with_mixed_params( $name, $config, $limit ) {
	return true;
}
