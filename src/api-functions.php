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
 * Get the specific type of hook.
 *
 * @param int|WP_Post|null $post Optional. Post ID or post object. Default is global $post.
 * @return string          Either 'action', 'filter', or an empty string if not a hook post type.
 */
function avcpdp_get_hook_type($post = null) {
    $hook = '';

    if ('wp-parser-hook' === get_post_type($post)) {
        $hook = get_post_meta(get_post_field('ID', $post), '_wp-parser_hook_type', true);
    }

    return $hook;
}

/**
 * Returns the array of post types that have source code.
 *
 * @return array
 */
function avcpdp_get_post_types_with_source_code() {
    return ['wp-parser-class', 'wp-parser-method', 'wp-parser-function'];
}

/**
 * Does the post type have source code?
 *
 * @param  null|string $post_type Optional. The post type name. If null, assumes current post type. Default null.
 * @return bool
 */
function avcpdp_post_type_has_source_code($post_type = null) {
    $post_type = $post_type ? $post_type : get_post_type();

    return in_array($post_type, avcpdp_get_post_types_with_source_code());
}

/**
 * Returns the source type terms for the `wp-parser-*` post type currently being queried
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return WP_Term[]
 */
function avcpdp_get_reference_archive_source_type_terms() {
    if (!is_archive()) {
        // not a code reference archive, cannot get source type terms
        return [];
    }
    $ptype = get_query_var('post_type');
    if (!in_array($ptype, avcpdp_get_parsed_post_types(), true)) {
        // only get source type terms from `wp-parser-*` post types
        return [];
    }
    $stype = get_query_var(\WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG);
    if (empty($stype)) {
        // source type not queried for, cannot determine URL
        return [];
    }
    $stypepieces = explode(',', $stype);
    if (!avcpdp_source_type_term_slugs_are_valid($stypepieces)) {
        // the combination of source type terms are not valid
        return [];
    }

    $terms = avcpdp_get_source_type_terms_from_slug_pair($stypepieces);

    return $terms;
}

/**
 * Returns the base URL for the `wp-parser-*` post type currently being queried
 *
 * This function will return an empty string if the current main query is not related
 * to a reference post type
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return string
 */
function avcpdp_get_reference_archive_base_url() {
    if (!is_archive()) {
        // not a code reference archive, cannot determine URL
        return '';
    }
    $ptype = get_query_var('post_type');
    if (!in_array($ptype, avcpdp_get_parsed_post_types(), true)) {
        // only show filter by category section for `wp-parser-*` post types
        return '';
    }
    $stype = get_query_var(\WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG);
    if (empty($stype)) {
        // source type not queried for, cannot determine URL
        return '';
    }
    $stypepieces = explode(',', $stype);
    if (!avcpdp_source_type_term_slugs_are_valid($stypepieces)) {
        // the combination of source type terms are not valid
        return '';
    }

    $type = $stypepieces[0];
    $name = $stypepieces[1];
    $parsertype = \WP_Parser\Plugin::WP_PARSER_PT_MAP[$ptype]['urlpiece'];
    $baseurl = home_url("/reference/{$type}/{$name}/{$parsertype}");

    return $baseurl;
}

/**
 * Returns the base URL for the current reference single post.
 *
 * The URL is constructed from the source type terms assigned to the post. The
 * `wp-parser-*` post type URL piece is **not appended**.
 *
 * Example: `/reference/plugin/my-plugin`
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int|null $pid Optional. Post ID. Defaults to current post
 * @return string Returns an empty string if the URL cannot be determined
 */
function avcpdp_get_reference_single_base_url($pid = null) {
    if (!is_single($pid)) {
        return '';
    }
    if (empty($pid)) {
        $pid = get_the_ID();
    }
    if (!avcpdp_source_type_terms_are_valid_for_post($pid)) {
        return '';
    }
    $stterms = avcpdp_get_post_source_type_terms($pid);
    $type = $stterms['type']->slug;
    $name = $stterms['name']->slug;
    $baseurl = home_url("/reference/{$type}/{$name}");

    return $baseurl;
}

/**
 * Returns hierarchical descending array of reference landing page posts tied to the current reference
 * single post via the source type taxonomy
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int|null $pid Optional. Post ID. Defaults to current post
 * @return array
 */
function avcpdp_get_reference_landing_page_posts_from_reference_single_post($pid = null) {
    if (empty($pid)) {
        $pid = get_the_ID();
    }
    if (empty($pid)) {
        return [];
    }
    if (!is_single($pid)) {
        return [];
    }
    if (!avcpdp_is_parsed_post_type()) {
        return [];
    }
    if (!avcpdp_source_type_terms_are_valid_for_post($pid)) {
        return [];
    }

    $stterms = avcpdp_get_post_source_type_terms($pid);

    return avcpdp_get_reference_landing_page_posts_from_source_type_terms($stterms);
}

/**
 * Returns hierarchical descending array of reference landing page posts tied to the source types terms
 * of the current `wp-parser-*` post type currently being queired
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return array
 */
function avcpdp_get_reference_landing_page_posts_from_archive() {
    $stterms = avcpdp_get_reference_archive_source_type_terms();
    if (empty($stterms)) {
        return [];
    }

    return avcpdp_get_reference_landing_page_posts_from_source_type_terms($stterms);
}

/**
 * Returns hierarchical descending array of reference landing page posts tied to the given source types terms
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param array $stterms
 * @return array
 */
function avcpdp_get_reference_landing_page_posts_from_source_type_terms($stterms) {
    $trail = [];
    $stypelanding = get_posts([
        'order' => 'ASC',
        'orderby' => 'parent',
        'post_status' => ['publish', 'private'],
        'post_type' => WP_Parser\Plugin::CODE_REFERENCE_POST_TYPE,
        'tax_query' => [
            [
                'taxonomy' => WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG,
                'field' => 'term_id',
                'terms' => $stterms['type']->term_id,
                'include_children' => false,
            ],
        ],
    ]);
    if (!empty($stypelanding)) {
        if ($stypelanding[0]->post_status === 'publish') {
            $trail[] = $stypelanding[0];
        }

        $sourcelanding = get_posts([
            'post_parent' => $stypelanding[0]->ID,
            'post_status' => 'publish',
            'post_type' => WP_Parser\Plugin::CODE_REFERENCE_POST_TYPE,
            'tax_query' => [
                [
                    'taxonomy' => WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG,
                    'field' => 'term_id',
                    'terms' => $stterms['name']->term_id,
                    'include_children' => false,
                ],
            ],
        ]);
        if (!empty($sourcelanding)) {
            $trail[] = $sourcelanding[0];
        }
    }

    return $trail;
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
 * Checks whether the source type terms are valid for a post
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int|null $post_id
 * @return bool
 */
function avcpdp_source_type_terms_are_valid_for_post($post_id = null) {
    if ($post_id === null) {
        $post_id = get_the_ID();
    }

    if (empty($post_id)) {
        return false;
    }

    $terms = wp_get_post_terms($post_id, WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG);

    return avcpdp_source_type_terms_are_valid($terms);
}

/**
 * Returns an array of source type terms given an array of source type slugs
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param array $termslugs
 * @return WP_Term[]
 */
function avcpdp_get_source_type_terms_from_slug_pair($termslugs) {
    $terms = [];
    foreach ($termslugs as $termslug) {
        $term = get_term_by('slug', $termslug, WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG);
        if (empty($term)) {
            return false;
        }
        if ($term->parent === 0) {
            $terms['type'] = $term;
        } else {
            $terms['name'] = $term;
        }
    }

    return $terms;
}

/**
 * Checks whether an array of source type term slugs is a valid combination
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param array $termslugs
 * @return bool
 */
function avcpdp_source_type_term_slugs_are_valid($termslugs) {
    if (empty($termslugs)) {
        return false;
    }

    if (count($termslugs) > 2) {
        // a post can only have one source type and one source type name
        return false;
    }
    $terms = avcpdp_get_source_type_terms_from_slug_pair($termslugs);

    return avcpdp_source_type_terms_are_valid($terms);
}

/**
 * Checks whether an array of source types is a valid combination
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param array $terms
 * @return bool
 */
function avcpdp_source_type_terms_are_valid($terms) {
    if (empty($terms)) {
        return false;
    }

    if (count($terms) > 2) {
        // a post can only have one source type and one source type name
        return false;
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
        // source type and source type name are required
        return false;
    }

    if ($res['name']->parent !== $res['type']->term_id) {
        // source type name must be a child of source type
        return false;
    }

    return true;
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
 * @param WP_Term[] $stterms
 * @param string    $role
 * @param int       $posts_per_page
 * @return WP_Query
 */
function avcpdp_get_reference_post_list_by_role($stterms, $role, $posts_per_page = 10) {
    $q = new WP_Query([
        'fields' => 'ids',
        'post_type' => avcpdp_get_parsed_post_types(),
        'posts_per_page' => $posts_per_page,
        'tax_query' => [
            'relation' => 'AND',
            [
                'taxonomy' => WP_Parser\Plugin::ROLE_TAX_SLUG,
                'field' => 'slug',
                'terms' => $role,
                'include_children' => true,
            ],
            [
                'taxonomy' => WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG,
                'field' => 'slug',
                'terms' => [$stterms['type']->slug, $stterms['name']->slug],
                'include_children' => false,
                'operator' => 'AND',
            ],
        ],
    ]);

    return $q;
}

/**
 * Returns list of role terms
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param string $fields
 * @param bool   $hide_empty
 * @return WP_Term[]
 */
function avcpdp_get_role_terms($fields = 'all', $hide_empty = true) {
    $terms = get_terms([
        'taxonomy' => WP_Parser\Plugin::ROLE_TAX_SLUG,
        'hide_empty' => $hide_empty,
        'fields' => $fields,
    ]);
    if ($terms instanceof WP_Error) {
        return [];
    }

    return $terms;
}

/**
 * Returns list of reference post type posts that have at least one role assigned to them
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int $posts_per_page
 * @return int[]
 */
function avcpdp_get_reference_post_list_having_roles($posts_per_page = 50) {
    return get_posts([
        'fields' => 'ids',
        'post_type' => avcpdp_get_parsed_post_types(),
        'posts_per_page' => $posts_per_page,
        'tax_query' => [
            [
                'taxonomy' => WP_Parser\Plugin::ROLE_TAX_SLUG,
                'field' => 'slug',
                'terms' => avcpdp_get_role_terms('slugs'),
            ],
        ],
    ]);
}

/**
 * Returns list of hook reference post IDs
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param string $hook_type
 * @param int    $posts_per_page
 * @return WP_Query
 */
function avcpdp_get_hook_reference_posts($hook_type = 'all', $posts_per_page = 20) {
    $params = [
        'fields' => 'ids',
        'post_type' => 'wp-parser-hook',
        'posts_per_page' => $posts_per_page,
    ];
    if ($hook_type === 'filter' || $hook_type === 'action') {
        $params['meta_key'] = '_wp-parser_hook_type';
        $params['meta_value'] = $hook_type;
    }

    return new WP_Query($params);
}

/**
 * Returns list of reference post type posts that have at least one role assigned to them
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int|null $post_id
 * @return WP_Term[]
 */
function avcpdp_get_reference_post_roles($post_id = null) {
    if (empty($post_id)) {
        $post_id = get_the_ID();
    }
    if (empty($post_id)) {
        return [];
    }

    $terms = wp_get_post_terms($post_id, WP_Parser\Plugin::ROLE_TAX_SLUG);
    if ($terms instanceof WP_Error) {
        return [];
    }

    return $terms;
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
 * Retrieve source code for a function or method.
 *
 * @param int  $post_id     Optional. The post ID.
 * @param bool $force_parse Optional. Ignore potential value in post meta and reparse source file for source code?
 *
 * @return string The source code.
 */
function avcpdp_get_source_code($post_id = null, $force_parse = false) {
    if (empty($post_id)) {
        $post_id = get_the_ID();
    }

    // Get the source code stored in post meta.
    $meta_key = '_wp-parser_source_code';
    if (!$force_parse && $source_code = get_post_meta($post_id, $meta_key, true)) {
        return $source_code;
    }

    /* Source code hasn't been stored in post meta, so parse source file to get it. */

    // Get the name of the source file.
    $source_file = avcpdp_get_source_file($post_id);

    // Get the start and end lines.
    $start_line = intval(get_post_meta($post_id, '_wp-parser_line_num', true)) - 1;
    $end_line = intval(get_post_meta($post_id, '_wp-parser_end_line_num', true));

    // Sanity check to ensure proper conditions exist for parsing
    if (!$source_file || !$start_line || !$end_line || ($start_line > $end_line)) {
        return '';
    }

    // Find just the relevant source code
    $source_code = '';
    $handle = @fopen(avcpdp_get_source_code_root_dir() . $source_file, 'r');
    if ($handle) {
        $line = -1;
        while (!feof($handle)) {
            $line++;
            $source_line = fgets($handle);

            // Stop reading file once end_line is reached.
            if ($line >= $end_line) {
                break;
            }

            // Skip lines until start_line is reached.
            if ($line < $start_line) {
                continue;
            }

            $source_code .= $source_line;
        }
        fclose($handle);
    }

    update_post_meta($post_id, $meta_key, addslashes($source_code));

    return $source_code;
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
    remove_filter('the_content', ['Aivec\Plugins\DocParser\Formatting', 'fixUnintendedMarkdown'], 1);

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
    add_filter('the_content', ['Aivec\Plugins\DocParser\Formatting', 'fixUnintendedMarkdown'], 1);

    return $content;
}

/**
 * Returns the translated content for a parsed parameter
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int    $post_id
 * @param string $key
 * @return string
 */
function avcpdp_get_param_translated_content($post_id, $key) {
    $clean_name = str_replace('$', '', $key);
    $translated_key = "translated_{$clean_name}";
    $translated_key_val = (string)get_post_meta($post_id, $translated_key, true);

    return $translated_key_val;
}
