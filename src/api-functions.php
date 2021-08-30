<?php

/**
 * Returns the slug for the source type taxonomy
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return string
 */
function avcpdp_get_source_type_taxonomy_slug() {
    return WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG;
}

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
 * Checks if given post type is the code reference landing page post type.
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int|null $post_id Optional. The post ID.
 * @return bool True if post has a parsed post type
 */
function avcpdp_is_reference_landing_page_post_type($post_id = null) {
    $post_type = get_post_type($post_id);

    return $post_type === WP_Parser\Plugin::CODE_REFERENCE_POST_TYPE;
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
 * Returns source type taxonomy "plugin" term
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return WP_Term|false
 */
function avcpdp_get_source_type_plugin_term() {
    return get_term_by('slug', 'plugin', WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG);
}

/**
 * Returns list of child terms for the source type taxonomy "plugin" term
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return array
 */
function avcpdp_get_source_type_plugin_terms() {
    $term = avcpdp_get_source_type_plugin_term();
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
 * Returns list of reference post type posts for a given role slug
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param string $role
 * @param int    $posts_per_page
 * @return int[]
 */
function avcpdp_get_reference_post_list_by_role($role, $posts_per_page = 20) {
    return get_posts([
        'fields' => 'ids',
        'post_type' => avcpdp_get_parsed_post_types(),
        'posts_per_page' => $posts_per_page,
        'tax_query' => [
            [
                'taxonomy' => WP_Parser\Plugin::ROLE_TAX_SLUG,
                'field' => 'slug',
                'terms' => $role,
            ],
        ],
    ]);
}

/**
 * Returns a role term given a role slug
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param string $slug
 * @return WP_Term|false
 */
function avcpdp_get_role_term_by_slug($slug) {
    return get_term_by('slug', $slug, WP_Parser\Plugin::ROLE_TAX_SLUG);
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
 * Retrieve name of source file
 *
 * @param int $post_id
 * @return string
 */
function avcpdp_get_source_file($post_id = null) {
    $source_file_object = wp_get_post_terms(empty($post_id) ? get_the_ID() : $post_id, 'wp-parser-source-file', ['fields' => 'names']);

    return empty($source_file_object) ? '' : esc_html($source_file_object[0]);
}

/**
 * Retrieve URL to source file archive.
 *
 * @param string $name
 * @param int    $post_id Optional. The post ID.
 * @return string
 */
function avcpdp_get_source_file_archive_link($name = null, $post_id = null) {
    $source_file_object = get_term_by('name', empty($name) ? avcpdp_get_source_file($post_id) : $name, 'wp-parser-source-file');

    return empty($source_file_object) ? '' : esc_url(get_term_link($source_file_object));
}

/**
 * Retrieve either the starting or ending line number.
 *
 * @param int    $post_id Optional. The post ID.
 * @param string $type    Optional. Either 'start' for starting line number, or 'end' for ending line number.
 * @return int
 */
function avcpdp_get_line_number($post_id = null, $type = 'start') {
    $post_id = empty($post_id) ? get_the_ID() : $post_id;
    $meta_key = ('end' == $type) ? '_wp-parser_end_line_num' : '_wp-parser_line_num';

    return (int)get_post_meta($post_id, $meta_key, true);
}

/**
 * Returns `true` if the function/method/hook has usage info, `false` otherwise
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int $post_id Optional. The post ID.
 * @return bool
 */
function avcpdp_show_usage_info($post_id = null) {
    $p2p_enabled = function_exists('p2p_register_connection_type');

    return $p2p_enabled && avcpdp_post_type_has_usage_info(get_post_type($post_id));
}

/**
 * Does the post type support usage information?
 *
 * @param string $post_type Optional. The post type name. If blank, assumes current post type.
 * @param int    $post_id Optional. The post ID.
 * @return boolean
 */
function avcpdp_post_type_has_usage_info($post_type = null, $post_id = null) {
    $post_type = $post_type ? $post_type : get_post_type($post_id);
    $post_types_with_usage = ['wp-parser-function', 'wp-parser-method', 'wp-parser-hook', 'wp-parser-class'];

    return in_array($post_type, $post_types_with_usage);
}

/**
 * Does the post type support uses information?
 *
 * @param string $post_type Optional. The post type name. If blank, assumes current post type.
 * @param int    $post_id Optional. The post ID.
 * @return boolean
 */
function avcpdp_post_type_has_uses_info($post_type = null, $post_id = null) {
    $post_type = $post_type ? $post_type : get_post_type($post_id);
    $post_types_with_uses = ['wp-parser-function', 'wp-parser-method', 'wp-parser-class'];

    return in_array($post_type, $post_types_with_uses);
}

/**
 * Retrieves a WP_Query object for the posts that use the specified post.
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is global $post.
 * @return WP_Query|null   The WP_Query if the post's post type supports 'used by', null otherwise.
 */
function avcpdp_get_used_by($post = null) {
    switch (get_post_type($post)) {
        case 'wp-parser-function':
            $connection_types = ['functions_to_functions', 'methods_to_functions'];
            break;

        case 'wp-parser-method':
            $connection_types = ['functions_to_methods', 'methods_to_methods'];
            break;

        case 'wp-parser-hook':
            $connection_types = ['functions_to_hooks', 'methods_to_hooks'];
            break;

        case 'wp-parser-class':
            $connected = new WP_Query([
                'post_type' => ['wp-parser-class'],
                'meta_key' => '_wp-parser_extends',
                'meta_value' => get_post_field('post_name', $post),
            ]);
            return $connected;
            break;

        default:
            return;
    }

    $connected = new WP_Query([
        'post_type' => ['wp-parser-function', 'wp-parser-method'],
        'connected_type' => $connection_types,
        'connected_direction' => ['to', 'to'],
        'connected_items' => get_post_field('ID', $post),
        'nopaging' => true,
    ]);

    return $connected;
}

/**
 * Retrieves a WP_Query object for the posts that the current post uses.
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is global $post.
 * @return WP_Query|null   The WP_Query if the post's post type supports 'uses', null otherwise.
 */
function avcpdp_get_uses($post = null) {
    $post_id = get_post_field('ID', $post);
    $post_type = get_post_type($post);

    if ('wp-parser-class' === $post_type) {
        $extends = get_post_meta($post_id, '_wp-parser_extends', true);
        if (!$extends) {
            return null;
        }
        $connected = new WP_Query([
            'post_type' => ['wp-parser-class'],
            'name' => $extends,
        ]);
        return $connected;
    } elseif ('wp-parser-function' === $post_type) {
        $connection_types = ['functions_to_functions', 'functions_to_methods', 'functions_to_hooks'];
    } else {
        $connection_types = ['methods_to_functions', 'methods_to_methods', 'methods_to_hooks'];
    }

    $connected = new WP_Query([
        'post_type' => ['wp-parser-function', 'wp-parser-method', 'wp-parser-hook'],
        'connected_type' => $connection_types,
        'connected_direction' => ['from', 'from', 'from'],
        'connected_items' => $post_id,
        'nopaging' => true,
    ]);

    return $connected;
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
