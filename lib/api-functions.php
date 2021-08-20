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

/**
 * Retrieve the root directory of the parsed WP code.
 *
 * If the option 'wp_parser_root_import_dir' (as set by the parser) is not
 * set, then assume ABSPATH.
 *
 * @param int|null $post_id
 * @return string
 */
function avcpdp_get_source_code_root_dir($post_id = null) {
    $root_dir = get_option('wp_parser_root_import_dir');
    if (empty($post_id)) {
        $post_id = get_the_ID();
        if (!empty($post_id)) {
            $sourceterms = avcpdp_get_post_source_type_terms($post_id);
            if (!empty($sourceterms['name'])) {
                $dir = get_term_meta(
                    $sourceterms['name']->term_id,
                    'wp_parser_root_import_dir',
                    true
                );
                $root_dir = !empty($dir) ? $dir : $root_dir;
            }
        }
    }
    if (isset($_ENV['AVC_NODE_ENV']) && $_ENV['AVC_NODE_ENV'] === 'development') {
        $root_dir = str_replace('/app/', '/var/www/html/', $root_dir);
    }

    return $root_dir ? trailingslashit($root_dir) : ABSPATH;
}
