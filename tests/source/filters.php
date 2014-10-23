<?php
/**
 * This is a well documented filter.
 *
 * Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
 * Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
 *
 * @since 3.9.0
 *
 * @param array $mce_translation Key/value pairs of strings.
 * @param string $mce_locale Locale.
 */
$mce_translation = apply_filters('good_static_filter', $mce_translation, $mce_locale);

/**
 * This is a well documented dynamic filter.
 *
 * Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
 * Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
 *
 * @since 2.6.0
 *
 * @param mixed $value The new, unserialized option value.
 * @param mixed $old_value The old option value.
 */
$value = apply_filters('good_dynamic_filter_' . $option, $value, $old_value);

/**
 * This is a well documented dynamic filter.
 *
 * Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
 * Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
 *
 * @since 2.6.0
 *
 * @param mixed $value The new, unserialized option value.
 * @param mixed $old_value The old option value.
 */
$value = apply_filters("good_double_quotes_dynamic_filter_$option", $value, $old_value);

/**
 * This is a filter missing the "since" line.
 *
 * Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
 * Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
 *
 * @param mixed $value The new, unserialized option value.
 * @param string $mce_locale Locale.
 */
$mce_translation = apply_filters('missing_since_static_filter', $mce_translation, $mce_locale);

/**
 * This is a dynamic filter missing the "since" line.
 *
 * Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
 * Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
 *
 * @param mixed $value The new, unserialized option value.
 * @param mixed $old_value The old option value.
 */
$value = apply_filters('missing_since_dynamic_filter_' . $option, $value, $old_value);

/**
 * This is a dynamic filter missing the "since" line.
 *
 * Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
 * Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
 *
 * @param mixed $value The new, unserialized option value.
 * @param mixed $old_value The old option value.
 */
$value = apply_filters("missing_since_double_quotes_dynamic_filter_$option", $value, $old_value);

/**
 * This is a filter missing one "param" line.
 *
 * Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
 * Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
 *
 * @since 2.6.0
 *
 * @param string $mce_locale Locale.
 */
$mce_translation = apply_filters('missing_param_static_filter', $mce_translation, $mce_locale);

/**
 * This is a dynamic filter missing one "param" line.
 *
 * Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
 * Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
 *
 * @since 2.6.0
 *
 * @param string $mce_locale Locale.
 */
$value = apply_filters('missing_param_dynamic_filter_' . $option, $value, $old_value);

/**
 * This is a dynamic filter missing one "param" line.
 *
 * Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
 * Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
 *
 * @since 2.6.0
 *
 * @param string $mce_locale Locale.
 */
$value = apply_filters("missing_param_double_quotes_dynamic_filter_$option", $value, $old_value);

/**
 * This is a filter with multiple since tags
 *
 * @since 1.0
 * @since 1.9 Added a new parameter to the filter
 * More description
 *
 * @param string $first_parameter
 * @param string $second_parameter
 */
$value = apply_filters( 'multiple_since_tags', $first_parameter, $second_parameter );

$mce_translation = apply_filters('no_doc_static_filter', $mce_translation, $mce_locale);

$value = apply_filters('no_doc_dynamic_filter_' . $option, $value, $old_value);

$value = apply_filters("no_doc_double_quotes_dynamic_filter_$option", $value, $old_value);
