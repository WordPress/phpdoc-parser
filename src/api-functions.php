<?php

use Aivec\Plugins\DocParser\Formatting;
use Aivec\Plugins\DocParser\Registrations;

/**
 * Returns the slug for the source type taxonomy
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return string
 */
function avcpdp_get_source_type_taxonomy_slug() {
    return Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG;
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
 * If no post type is provided, this function will use the post type of
 * the current post, or the queried post type if archive or search
 *
 * If archive or search, `false` will be returned if any of the queried
 * post types are not a `wp-parser-*` post type
 *
 * @param null|string $post_type Optional. The post type. Default null.
 * @return bool
 */
function avcpdp_is_parsed_post_type($post_type = null) {
    if (!empty($post_type)) {
        return in_array($post_type, avcpdp_get_parsed_post_types(), true);
    }

    if (is_single()) {
        return in_array(get_post_type(), avcpdp_get_parsed_post_types(), true);
    }

    $ptypes = get_query_var('post_type');
    $ptypes = !empty($ptypes) ? $ptypes : '';
    if (is_array($ptypes)) {
        foreach ($ptypes as $ptype) {
            if (!in_array($ptype, avcpdp_get_parsed_post_types(), true)) {
                return false;
            }
        }
    } else {
        if (!in_array($ptypes, avcpdp_get_parsed_post_types(), true)) {
            return false;
        }
    }

    return true;
}

/**
 * Gets the current parsed post type
 *
 * This function will use the post type of the current post, or the
 * queried post type if archive or search
 *
 * If archive or search, `null` will be returned if any of the queried
 * post types are not a `wp-parser-*` post type
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return string|string[]|null
 */
function avcpdp_get_parsed_post_type() {
    if (is_single()) {
        $sptype = get_post_type();
        if (!in_array($sptype, avcpdp_get_parsed_post_types(), true)) {
            return null;
        }

        return $sptype;
    }

    $ptypes = get_query_var('post_type');
    $ptypes = !empty($ptypes) ? $ptypes : null;
    if ($ptypes === null) {
        return null;
    }

    if (is_array($ptypes)) {
        foreach ($ptypes as $ptype) {
            if (!in_array($ptype, avcpdp_get_parsed_post_types(), true)) {
                return null;
            }
        }
    } else {
        if (!in_array($ptypes, avcpdp_get_parsed_post_types(), true)) {
            return null;
        }
    }

    return $ptypes;
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
 * Returns the SVG logo term meta for the current reference
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return array {
 *     The SVG logo term meta data
 *
 *     @type string $file Image path
 *     @type string $url  Image URL
 *     @type string $type File extension
 * }
 */
function avcpdp_get_reference_logo() {
    $stterms = avcpdp_get_source_type_terms();
    if (empty($stterms)) {
        return null;
    }

    $svglogo = get_term_meta($stterms['name']->term_id, 'item_image', true);
    if (empty($svglogo)) {
        return null;
    }

    return $svglogo;
}

/**
 * Returns search page link
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param string $s
 * @param array  $args
 * @param array  $post_types
 * @return string
 */
function avcpdp_get_search_link($s = '', $args = [], $post_types = []) {
    $args = avcpdp_get_search_args($s, $args, $post_types);
    foreach ($args as $argk => $argsv) {
        if (is_string($argsv)) {
            $args[$argk] = rawurlencode($argsv);
        }
    }
    return add_query_arg($args, home_url('/'));
}

/**
 * Returns search arguments
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param string $s
 * @param array  $args
 * @param array  $post_types
 * @return (string|array)[]
 */
function avcpdp_get_search_args($s = '', $args = [], $post_types = []) {
    $sttstring = '';
    $stterms = avcpdp_get_source_type_terms();
    if (!empty($stterms)) {
        $sttstring = $stterms['type']->slug . ',' . $stterms['name']->slug;
    }

    return array_merge(
        [
            's' => $s,
            'post_type' => $post_types,
            'avcpdp_search' => 1,
            'taxonomy' => Registrations::SOURCE_TYPE_TAX_SLUG,
            Registrations::SOURCE_TYPE_TAX_SLUG => $sttstring,
        ],
        $args
    );
}

/**
 * Returns `true` if the current query is the main query and a search query
 * for at least one of the 4 `wp-parser-*` reference post types
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return bool
 */
function avcpdp_is_reference_search() {
    // only process main query
    if (!is_main_query()) {
        return false;
    }
    // check if search query
    if (!is_search()) {
        return false;
    }

    return avcpdp_is_parsed_post_type();
}

/**
 * Returns source type "type" and "name" terms for the current page
 *
 * If the current page is an archive or search page and at least one
 * of the queried post types is a `wp-parser-*` post type, this function
 * will attempt to extract the source type terms from the taxonomy query.
 *
 * If the current page is a single page, source type terms associated with
 * the post ID will be returned.
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return array {
 *     A key-value map of source type terms. Empty array if the source type terms could
 *     not be determined
 *
 *     @type \WP_Term $type The type of source (plugin, theme, or composer-package)
 *     @type \WP_Term $name The unique name for the source (eg: my-plugin)
 * }
 */
function avcpdp_get_source_type_terms() {
    if (is_archive() || is_search()) {
        return avcpdp_get_reference_archive_source_type_terms();
    }

    return avcpdp_get_post_source_type_terms();
}

/**
 * Returns source type "type" and "name" terms for the current post
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param int|null $post_id
 * @return array
 */
function avcpdp_get_post_source_type_terms($post_id = null) {
    if ($post_id === null) {
        $post_id = get_the_ID();
    }

    if (empty($post_id)) {
        return [];
    }

    $terms = wp_get_post_terms($post_id, Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG);
    if (empty($terms)) {
        return [];
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
        return [];
    }

    return $res;
}

/**
 * Returns the source type terms for the `wp-parser-*` post type currently being queried
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return WP_Term[]
 */
function avcpdp_get_reference_archive_source_type_terms() {
    if (!is_archive() && !is_search()) {
        // not a code reference archive, cannot get source type terms
        return [];
    }
    // only get source type terms from `wp-parser-*` post types
    $ptypes = get_query_var('post_type');
    $ptypes = !empty($ptypes) ? $ptypes : '';
    if (is_array($ptypes)) {
        foreach ($ptypes as $ptype) {
            if (!in_array($ptype, avcpdp_get_parsed_post_types(), true)) {
                return false;
            }
        }
    } else {
        if (!in_array($ptypes, avcpdp_get_parsed_post_types(), true)) {
            return false;
        }
    }

    $stype = get_query_var(\Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG);
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
 * Returns the base URL for the `wp-parser-*` post type of the current post/query
 *
 * This function will return an empty string if the current main query is not related
 * to a reference post type or if the current page is a singular page but the post
 * type is not a reference post type
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return string Example: `/reference/plugin/my-plugin/functions`
 */
function avcpdp_get_reference_base_url() {
    $baseurl = avcpdp_get_reference_type_base_url();
    if (empty($baseurl)) {
        return '';
    }
    $ptype = avcpdp_get_parsed_post_type();
    if (!is_string($ptype)) {
        return '';
    }
    $parsertype = avcpdp_get_reference_post_type_url_slug($ptype);
    $baseurl .= "/{$parsertype}";

    return $baseurl;
}

/**
 * Returns URL portion for a `wp-parser-*` post type
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param string $ptype
 * @return string
 */
function avcpdp_get_reference_post_type_url_slug($ptype) {
    if (!avcpdp_is_parsed_post_type($ptype)) {
        return '';
    }

    return \Aivec\Plugins\DocParser\Registrations::WP_PARSER_PT_MAP[$ptype]['urlpiece'];
}

/**
 * Returns the base URL for the source type terms of the current post/query
 *
 * This function will return an empty string if the current main query does not
 * contain a source type taxonomy search or if the current page is a singular page
 * but the post does not have source type terms applied to it.
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return string Example: `/reference/plugin/my-plugin`
 */
function avcpdp_get_reference_type_base_url() {
    $stterms = avcpdp_get_source_type_terms();
    if (empty($stterms)) {
        return '';
    }
    $type = $stterms['type']->slug;
    $name = $stterms['name']->slug;
    $baseurl = home_url("/reference/{$type}/{$name}");

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
 * Returns hierarchical descending array of reference landing page posts tied to the current source types terms
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @return array
 */
function avcpdp_get_reference_landing_page_posts_from_source_type_terms() {
    $stterms = avcpdp_get_source_type_terms();
    if (empty($stterms)) {
        return [];
    }
    $trail = [];
    $stypelanding = get_posts([
        'order' => 'ASC',
        'orderby' => 'parent',
        'post_status' => ['publish', 'private'],
        'post_type' => Aivec\Plugins\DocParser\Registrations::CODE_REFERENCE_POST_TYPE,
        'tax_query' => [
            [
                'taxonomy' => Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG,
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
            'post_type' => Aivec\Plugins\DocParser\Registrations::CODE_REFERENCE_POST_TYPE,
            'tax_query' => [
                [
                    'taxonomy' => Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG,
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

    $terms = wp_get_post_terms($post_id, Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG);

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
        $term = get_term_by('slug', $termslug, Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG);
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
    return get_term_by('slug', 'plugin', Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG);
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
        'taxonomy' => Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG,
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
 * @param WP_Term[]    $stterms
 * @param string       $role
 * @param array|string $post_type
 * @param int          $posts_per_page
 * @return WP_Query
 */
function avcpdp_get_reference_post_list_by_role($stterms, $role, $post_type, $posts_per_page = 5) {
    if (empty($post_type)) {
        $post_type = avcpdp_get_parsed_post_types();
    }
    $q = new WP_Query([
        'fields' => 'ids',
        'post_type' => $post_type,
        'posts_per_page' => $posts_per_page,
        'tax_query' => [
            'relation' => 'AND',
            [
                'taxonomy' => Aivec\Plugins\DocParser\Registrations::ROLE_TAX_SLUG,
                'field' => 'slug',
                'terms' => $role,
                'include_children' => true,
            ],
            [
                'taxonomy' => Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG,
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
 * Returns list of tags that have at least one association with a `wp-parser-*` post
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param array  $stterms Source type terms
 * @param string $fields
 * @param bool   $hide_empty
 * @return WP_Term[]
 */
function avcpdp_get_associated_tags($stterms, $fields = 'all', $hide_empty = true) {
    $terms = get_tags([
        'hide_empty' => $hide_empty,
        'fields' => $fields,
    ]);
    if ($terms instanceof WP_Error) {
        return [];
    }

    $tagswithposts = [];
    foreach ($terms as $tag) {
        $q = new WP_Query([
            'fields' => 'ids',
            'post_type' => avcpdp_get_parsed_post_types(),
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'post_tag',
                    'field' => 'slug',
                    'terms' => $tag->slug,
                    'include_children' => true,
                ],
                [
                    'taxonomy' => Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG,
                    'field' => 'slug',
                    'terms' => [$stterms['type']->slug, $stterms['name']->slug],
                    'include_children' => false,
                    'operator' => 'AND',
                ],
            ],
        ]);
        if ($q->post_count > 0) {
            $tagswithposts[] = $tag;
        }
    }

    return $tagswithposts;
}

/**
 * Returns list of role terms for a source
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param array        $stterms Source type terms
 * @param array|string $post_type
 * @param string       $fields
 * @param bool         $hide_empty
 * @return WP_Term[]
 */
function avcpdp_get_role_terms($stterms, $post_type, $fields = 'all', $hide_empty = true) {
    $terms = get_terms([
        'taxonomy' => Aivec\Plugins\DocParser\Registrations::ROLE_TAX_SLUG,
        'hide_empty' => $hide_empty,
        'fields' => $fields,
    ]);
    if ($terms instanceof WP_Error) {
        return [];
    }

    if (empty($post_type)) {
        $post_type = avcpdp_get_parsed_post_types();
    }

    $roleswithposts = [];
    foreach ($terms as $role) {
        $q = new WP_Query([
            'fields' => 'ids',
            'post_type' => $post_type,
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => Aivec\Plugins\DocParser\Registrations::ROLE_TAX_SLUG,
                    'field' => 'slug',
                    'terms' => $role->slug,
                    'include_children' => true,
                ],
                [
                    'taxonomy' => Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG,
                    'field' => 'slug',
                    'terms' => [$stterms['type']->slug, $stterms['name']->slug],
                    'include_children' => false,
                    'operator' => 'AND',
                ],
            ],
        ]);
        if ($q->post_count > 0) {
            $roleswithposts[] = $role;
        }
    }

    return $roleswithposts;
}

/**
 * Returns list of hook reference post IDs
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param WP_Term[] $stterms
 * @param string    $hook_type
 * @param int       $posts_per_page
 * @return WP_Query
 */
function avcpdp_get_hook_reference_posts($stterms, $hook_type = 'all', $posts_per_page = 20) {
    $params = [
        'fields' => 'ids',
        'post_type' => 'wp-parser-hook',
        'posts_per_page' => $posts_per_page,
        'tax_query' => [
            'relation' => 'AND',
            [
                'taxonomy' => Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG,
                'field' => 'slug',
                'terms' => [$stterms['type']->slug, $stterms['name']->slug],
                'include_children' => false,
                'operator' => 'AND',
            ],
        ],
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

    $terms = wp_get_post_terms($post_id, Aivec\Plugins\DocParser\Registrations::ROLE_TAX_SLUG);
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
    return get_term_by('slug', $slug, Aivec\Plugins\DocParser\Registrations::ROLE_TAX_SLUG);
}

/**
 * Returns version number of the imported source.
 *
 * The the post ID must have a source type term associated with it for this
 * function to return a value other than `null`
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param null|int $post_id
 * @return null|string
 */
function avcpdp_get_source_imported_version($post_id = null) {
    if (empty($post_id)) {
        $post_id = get_the_ID();
    }

    if (empty($post_id)) {
        return null;
    }

    $stterms = avcpdp_get_post_source_type_terms($post_id);
    if (empty($stterms)) {
        return null;
    }

    return (string)get_term_meta($stterms['name']->term_id, 'wp_parser_imported_version', true);
}

/**
 * Retrieve the root directory of the parsed source code.
 *
 * If the source type term meta 'wp_parser_root_import_dir' (as set by the parser) is not
 * set, then assume ABSPATH.
 *
 * @param int|null $post_id
 * @return string
 */
function avcpdp_get_source_code_root_dir($post_id = null) {
    $root_dir = ABSPATH;
    if (empty($post_id)) {
        $post_id = get_the_ID();
    }

    if (empty($post_id)) {
        return $root_dir;
    }

    $sourceterms = avcpdp_get_post_source_type_terms($post_id);
    if (!empty($sourceterms['name'])) {
        $dir = get_term_meta(
            $sourceterms['name']->term_id,
            'wp_parser_root_import_dir',
            true
        );
        $root_dir = !empty($dir) ? $dir : $root_dir;
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
    // phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
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
    // phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

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

/**
 * Returns all wp-parser-* posts associated with a given source type and name pair
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param string $source_type
 * @param string $source_name
 * @return WP_Query
 */
function avcpdp_get_all_parser_posts_for_source($source_type, $source_name) {
    $q = new WP_Query([
        'fields' => 'ids',
        'post_status' => ['any'],
        'post_type' => avcpdp_get_parsed_post_types(),
        'posts_per_page' => -1,
        'tax_query' => [
            [
                'taxonomy' => Aivec\Plugins\DocParser\Registrations::SOURCE_TYPE_TAX_SLUG,
                'field' => 'slug',
                'terms' => [$source_type, $source_name],
                'include_children' => false,
                'operator' => 'AND',
            ],
        ],
    ]);

    return $q;
}

/**
 * Returns since tags data.
 *
 * @param int|null $post_id Post ID, defaults to the ID of the global $post.
 * @return array
 */
function avcpdp_get_sinces($post_id = null) {
    $post_id = empty($post_id) ? get_the_ID() : $post_id;

    // Since terms assigned to the post.
    $since_terms = wp_get_post_terms($post_id, 'wp-parser-since');

    // Since data stored in meta.
    $since_meta = get_post_meta($post_id, '_wp-parser_tags', true);

    $since_tags = wp_filter_object_list($since_meta, ['name' => 'since']);
    $translated_sinces = (array)get_post_meta($post_id, 'translated_sinces', true);

    $data = [];

    // Pair the term data with meta data.
    foreach ($since_terms as $since_term) {
        foreach ($since_tags as $meta) {
            if (is_array($meta) && $since_term->name == $meta['content']) {
                $raw_description = !empty($meta['description']) ? $meta['description'] : '';
                $translated_description = !empty($translated_sinces[$since_term->name]) ? $translated_sinces[$since_term->name] : '';
                $description = !empty($translated_description) ? $translated_description : $raw_description;
                $data[$since_term->name] = [
                    'version' => $since_term->name,
                    'since_url' => get_term_link($since_term),
                    'since_term' => $since_term,
                    'description' => $description,
                    'formatted_description' => Formatting::formatParamDescription($description),
                    'raw_description' => $raw_description,
                    'translated_raw_description' => $translated_description,
                ];
            }
        }
    }

    return $data;
}

/**
 * Returns deprecated tag data.
 *
 * @param int|null $post_id Optional. Post ID. Default is the ID of the global `$post`.
 * @return null|array
 */
function avcpdp_get_deprecated($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }

    $types = explode('-', get_post_type($post_id));
    $type = array_pop($types);
    $tags = get_post_meta($post_id, '_wp-parser_tags', true);
    $deprecated = wp_filter_object_list($tags, ['name' => 'deprecated']);
    $deprecated = array_shift($deprecated);

    if (!$deprecated) {
        return null;
    }

    $translated_deprecated = (string)get_post_meta($post_id, 'translated_deprecated', true);

    $raw_description = !empty($deprecated['description']) ? $deprecated['description'] : '';
    $translated_raw_description = !empty($translated_deprecated) ? $translated_deprecated : '';
    $description = !empty($translated_raw_description) ? sanitize_text_field($translated_raw_description) : sanitize_text_field($raw_description);

    $refers = null;
    $referral_link = null;
    $referral = wp_filter_object_list($tags, ['name' => 'see']);
    $referral = array_shift($referral);

    // Construct message pointing visitor to preferred alternative, as provided
    // via @see, if present.
    if (!empty($referral['refers'])) {
        $refers = sanitize_text_field($referral['refers']);

        if ($refers) {
            // For some reason, the parser may have dropped the parentheses, so add them.
            if (in_array($type, ['function', 'method']) && false === strpos($refers, '()')) {
                $refers .= '()';
            }

            $referral_link = Formatting::linkInternalElement($refers);
        }
    }

    $since_url = '';
    $version = $deprecated['content'];
    $since_term = get_term_by('name', $version, 'wp-parser-since');
    if ($since_term) {
        $since_url = get_term_link($since_term);
    }

    return [
        'version' => $deprecated['content'],
        'since_url' => $since_url,
        'since_term' => $since_term || null,
        'description' => $description,
        'formatted_description' => Formatting::formatParamDescription($description),
        'raw_description' => $raw_description,
        'translated_raw_description' => $translated_raw_description,
        'refers' => $refers,
        'referral_link' => $referral_link,
    ];
}

/**
 * Retrieve parameters as an array
 *
 * @param int|null $post_id
 * @return array
 */
function avcpdp_get_params($post_id = null) {
    if (empty($post_id)) {
        $post_id = get_the_ID();
    }
    $params = [];
    $args = get_post_meta($post_id, '_wp-parser_args', true);
    $tags = get_post_meta($post_id, '_wp-parser_tags', true);
    $tparams = (array)get_post_meta($post_id, 'translated_params', true);

    if ($tags) {
        $encountered_optional = false;
        foreach ($tags as $tag) {
            if (!empty($tag['name']) && 'param' === strtolower($tag['name'])) {
                $key = $tag['variable'];
                $param = [];

                $param['variable'] = $key;
                $param['ishash'] = false;
                $param['hierarchical'] = null;
                $param = array_merge($param, avcpdp_build_param_types($tag['types']));
                $param['raw_content'] = $tag['content'];
                $param['raw_translated_content'] = '';
                if (isset($tparams[$key])) {
                    $param['raw_translated_content'] = $tparams[$key];
                }

                // Normalize spacing at beginning of hash notation params.
                if ($tag['content'] && '{' == $tag['content'][0]) {
                    $tag['content'] = '{ ' . trim(substr($tag['content'], 1));
                    $param['ishash'] = true;
                }

                if (strtolower(substr($tag['content'], 0, 8)) == 'optional') {
                    $param['required'] = false;
                    $param['content'] = substr($tag['content'], 9);
                    $encountered_optional = true;
                } elseif (strtolower(substr($tag['content'], 2, 9)) == 'optional.') { // Hash notation param
                    $param['required'] = false;
                    $param['content'] = '{ ' . substr($tag['content'], 12);
                    $encountered_optional = true;
                } elseif ($encountered_optional) {
                    $param['required'] = false;
                } else {
                    $param['required'] = true;
                }

                $param['raw_content_formatted'] = Formatting::formatParamDescription(
                    $tag['content']
                );
                $param['raw_translated_content_formatted'] = Formatting::formatParamDescription(
                    $param['ishash'] ? '' : $param['raw_translated_content']
                );
                if ($param['ishash'] === true) {
                    $param['hierarchical'] = Formatting::getParamHashMapRecursive(
                        $param['raw_content'],
                        isset($tparams[$key]) ? (array)$tparams[$key] : []
                    );
                }

                $param['content'] = $param['raw_content_formatted'];
                if (!empty($param['raw_translated_content_formatted'])) {
                    $param['content'] = $param['raw_translated_content_formatted'];
                }

                $params[$key] = $param;
            }
        }
    }

    if ($args) {
        foreach ($args as $arg) {
            if (!empty($arg['name']) && !empty($params[$arg['name']])) {
                $params[$arg['name']]['raw_default'] = $arg['default'];

                // If a default value was supplied
                if (!empty($arg['default'])) {
                    // Ensure the parameter was marked as optional (sometimes they aren't
                    // properly and explicitly documented as such)
                    $params[$arg['name']]['required'] = false;

                    // If a known default is stated in the parameter's description, try to remove it
                    // since the actual default value is displayed immediately following description.
                    $default = htmlentities($arg['default']);
                    $params[$arg['name']]['default'] = $default;
                    if ($params[$arg['name']]['ishash'] === true) {
                        // skip hash parameters
                        continue;
                    }

                    $params[$arg['name']]['content'] = str_replace("default is {$default}.", '', $params[$arg['name']]['content']);
                    $params[$arg['name']]['content'] = str_replace("Default {$default}.", '', $params[$arg['name']]['content']);

                    // When the default is '', docs sometimes say "Default empty." or similar.
                    if ("''" == $arg['default']) {
                        $params[$arg['name']]['content'] = str_replace('Default empty.', '', $params[$arg['name']]['content']);
                        $params[$arg['name']]['content'] = str_replace('Default empty string.', '', $params[$arg['name']]['content']);

                        // Only a few cases of this. Remove once core is fixed.
                        $params[$arg['name']]['content'] = str_replace('default is empty string.', '', $params[$arg['name']]['content']);
                    // When the default is array(), docs sometimes say "Default empty array." or similar.
                    } elseif ('array()' == $arg['default']) {
                        $params[$arg['name']]['content'] = str_replace('Default empty array.', '', $params[$arg['name']]['content']);
                        // Not as common.
                        $params[$arg['name']]['content'] = str_replace('Default empty.', '', $params[$arg['name']]['content']);
                    }
                }
            }
        }
    }

    return $params;
}

/**
 * Retrieves return type and description if available.
 *
 * If there is no explicit return value, or it is explicitly "void", then
 * an empty string is returned. This rules out display of return type for
 * classes, hooks, and non-returning functions.
 *
 * @param int $post_id
 * @return string|array
 */
function avcpdp_get_return($post_id = null) {
    if (empty($post_id)) {
        $post_id = get_the_ID();
    }

    $tags = get_post_meta($post_id, '_wp-parser_tags', true);
    $return = wp_filter_object_list($tags, ['name' => 'return']);
    $translated_return = get_post_meta($post_id, 'translated_return', true);

    // If there is no explicit or non-"void" return value, don't display one.
    if (empty($return)) {
        return '';
    }

    $return = array_shift($return);
    $data = [];
    $data['ishash'] = false;
    $data['hierarchical'] = null;
    $data = array_merge($data, avcpdp_build_param_types($return['types']));
    $data['raw_content'] = $return['content'];
    $data['raw_translated_content'] = '';
    if (!empty($translated_return)) {
        $data['raw_translated_content'] = $translated_return;
    }

    // Normalize spacing at beginning of hash notation params.
    if ($return['content'] && '{' == $return['content'][0]) {
        $return['content'] = '{ ' . trim(substr($return['content'], 1));
        $data['ishash'] = true;
    }

    $data['raw_content_formatted'] = Formatting::formatParamDescription(
        $return['content']
    );
    $data['raw_translated_content_formatted'] = Formatting::formatParamDescription(
        $data['ishash'] ? '' : $data['raw_translated_content']
    );
    if ($data['ishash'] === true) {
        $data['hierarchical'] = Formatting::getParamHashMapRecursive(
            $data['raw_content'],
            !empty($translated_return) ? (array)$translated_return : []
        );
    }

    $data['content'] = $data['raw_content_formatted'];
    if (!empty($data['raw_translated_content_formatted'])) {
        $data['content'] = $data['raw_translated_content_formatted'];
    }

    return $data;
}

/**
 * Builds types meta data for an array of param types
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 * @param array $types
 * @return array
 */
function avcpdp_build_param_types($types) {
    $types = !empty($types) ? (array)$types : [];
    $data = [];
    $data['type'] = implode('|', $types);
    $data['types'] = $types;
    $data['types_formatted'] = $types;
    $data['types_meta'] = [];
    foreach ((array)$types as $i => $typeval) {
        if (strpos($typeval, '\\') !== false) {
            $typeval = ltrim($typeval, '\\');
            $data['types_meta'][] = ['isclass' => true];
        } else {
            $data['types_meta'][] = ['isclass' => false];
        }
        $data['types_formatted'][$i] = Formatting::autolinkReferences($typeval);
    }

    return $data;
}
