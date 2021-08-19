<?php

/**
 * Get an array of all parsed post types.
 *
 * @param string $labels If set to 'labels' post types with their labels are returned.
 * @return array
 */
function avcpdp_get_parsed_post_types($labels = '') {
    $post_types = [
        'wp-parser-class' => __('Classes', 'wp-parser'),
        'wp-parser-function' => __('Functions', 'wp-parser'),
        'wp-parser-hook' => __('Hooks', 'wp-parser'),
        'wp-parser-method' => __('Methods', 'wp-parser'),
    ];

    if ('labels' !== $labels) {
        return array_keys($post_types);
    }

    return $post_types;
}

/**
 * Returns source type "type" and "name" terms for the current post
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int|null $post_id
 * @return array|null
 */
function avcpdp_get_post_source_type_terms($post_id = null) {
    if ($post_id === null) {
        $post_id = get_the_ID();
    }

    if (empty($post_id)) {
        return null;
    }

    $terms = wp_get_post_terms($post_id, WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG);
    if (empty($terms)) {
        return null;
    }

    $res = [];
    foreach ($terms as $term) {
        if ($term->parent === 0) {
            $res['type'] = $term;
        } else {
            $res['name'] = $term;
        }
    }

    if (empty($res['type']) || empty($res['name'])) {
        return null;
    }

    return $res;
}
