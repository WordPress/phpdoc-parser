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
 * Checks if given post type is one of the parsed post types.
 *
 * @param null|string $post_type Optional. The post type. Default null.
 * @return bool True if post has a parsed post type
 */
function avcpdp_is_parsed_post_type($post_type = null) {
    $post_type = $post_type ? $post_type : get_post_type();

    return in_array($post_type, avcpdp_get_parsed_post_types());
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
 * Returns list of child terms for the source type taxonomy "plugin" term
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return array
 */
function avcpdp_get_source_type_plugin_terms() {
    $term = get_term_by('slug', 'plugin', WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG);
    if (empty($term)) {
        return [];
    }

    $pterms = get_terms([
        'parent' => $term->term_id,
        'taxonomy' => WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG,
    ]);
    if (empty($pterms) || $pterms instanceof WP_Error) {
        return [];
    }

    return $pterms;
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

/**
 * Retrieve an explanation for the given post.
 *
 * @param int|WP_Post $post      Post ID or WP_Post object.
 * @param bool        $published Optional. Whether to only retrieve the explanation if it's published.
 *                               Default false.
 * @return WP_Post|null WP_Post object for the Explanation, null otherwise.
 */
function avcpdp_get_explanation($post, $published = false) {
    if (!$post = get_post($post)) {
        return null;
    }

    $args = [
        'post_type' => 'wporg_explanations',
        'post_parent' => $post->ID,
        'no_found_rows' => true,
        'posts_per_page' => 1,
    ];

    if (true === $published) {
        $args['post_status'] = 'publish';
    }

    $explanation = get_children($args, OBJECT);

    if (empty($explanation)) {
        return null;
    }

    $explanation = reset($explanation);

    if (!$explanation) {
        return null;
    }
    return $explanation;
}

/**
 * Retrieve data from an explanation post field.
 *
 * Works only for published explanations.
 *
 * @see get_post_field()
 *
 * @param string      $field   Post field name.
 * @param int|WP_Post $post    Post ID or object for the function, hook, class, or method post
 *                             to retrieve an explanation field for.
 * @param string      $context Optional. How to filter the field. Accepts 'raw', 'edit', 'db',
 *                             or 'display'. Default 'display'.
 * @return string The value of the post field on success, empty string on failure.
 */
function avcpdp_get_explanation_field($field, $post, $context = 'display') {
    if (!$explanation = avcpdp_get_explanation($post, $published = true)) {
        return '';
    }

    return get_post_field($field, $explanation, $context);
}

/**
 * Retrieve the post content from an explanation post.
 *
 * @param int|WP_Post $_post Post ID or object for the function, hook, class, or method post
 *                           to retrieve an explanation field for.
 * @return string The post content of the explanation.
 */
function avcpdp_get_explanation_content($_post) {
    global $post;

    // Temporarily remove filter.
    remove_filter('the_content', ['DevHub_Formatting', 'fix_unintended_markdown'], 1);

    // Store original global post.
    $orig = $post;

    // Set global post to the explanation post.
    $post = avcpdp_get_explanation($_post);

    // Get explanation's raw post content.
    $content = '';
    if (
        !empty($_GET['wporg_explanations_preview_nonce'])
        &&
        false !== wp_verify_nonce($_GET['wporg_explanations_preview_nonce'], 'post_preview_' . $post->ID)
    ) {
        $preview = wp_get_post_autosave($post->ID);

        if (is_object($preview)) {
            $post = $preview;
            $content = get_post_field('post_content', $preview, 'display');
        }
    } else {
        $content = avcpdp_get_explanation_field('post_content', $_post);
    }

    // Pass the content through expected content filters.
    $content = apply_filters('the_content', apply_filters('get_the_content', $content));

    // Restore original global post.
    $post = $orig;

    // Restore filter.
    add_filter('the_content', ['DevHub_Formatting', 'fix_unintended_markdown'], 1);

    return $content;
}
