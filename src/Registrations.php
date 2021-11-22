<?php

namespace Aivec\Plugins\DocParser;

/**
 * Registers posts, post types, terms, and meta data
 */
class Registrations
{
    const ROLE_TAX_SLUG = 'wp-parser-role';
    const CODE_REFERENCE_POST_TYPE = 'code-reference';
    const SOURCE_TYPE_TAX_SLUG = 'wp-parser-source-type';
    const SOURCE_TYPE_TERM_SLUGS = ['composer-package', 'plugin', 'theme'];
    const WP_PARSER_PT_MAP = [
        'wp-parser-method' => [
            'urlpiece' => 'methods',
            'post_type' => 'wp-parser-method',
        ],
        'wp-parser-function' => [
            'urlpiece' => 'functions',
            'post_type' => 'wp-parser-function',
        ],
        'wp-parser-class' => [
            'urlpiece' => 'classes',
            'post_type' => 'wp-parser-class',
        ],
        'wp-parser-hook' => [
            'urlpiece' => 'hooks',
            'post_type' => 'wp-parser-hook',
        ],
    ];

    /**
     * Registers hooks
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function init() {
        // add_filter('rewrite_rules_array', [$this, 'removeDefaultParserPostTypeRewriteRules'], 10, 1);
        add_action('init', [$this, 'registerPostTypes'], 11);
        add_action('init', [$this, 'registerTaxonomies'], 11);
        add_filter('wp_parser_get_arguments', [$this, 'makeArgsSafe']);
        add_filter('wp_parser_return_type', [$this, 'humanizeSeparator']);

        add_filter('post_type_link', [$this, 'postPermalink'], 10, 2);
        add_filter('term_link', [$this, 'taxonomyPermalink'], 10, 3);
    }

    /**
     * Adds rewrite rules for the `wp-parser-(function|method|class|hook)` post
     * types.
     *
     * Rewrites URLs to be unique on a per source-type basis.
     *
     * For example, given a source type of `plugin`, a `plugin` child term slug of `my-plugin`,
     * and a function named `my-function`, the URL would be `/reference/plugin/my-plugin/functions/my-function`.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public static function addRewriteRules() {
        $sttax = self::SOURCE_TYPE_TAX_SLUG;
        $stterms = implode('|', self::SOURCE_TYPE_TERM_SLUGS);
        $stterms = str_replace('-', '\-', $stterms);

        // Add rewrite rules for Methods
        add_rewrite_rule(
            "reference/($stterms)/([a-z_\-]{1,32})/methods/page/([0-9]{1,})/?",
            "index.php?post_type=wp-parser-method&taxonomy={$sttax}&wp-parser-source-type=\$matches[1],\$matches[2]&paged=\$matches[3]",
            'top'
        );

        // Add rewrite rules for Functions, Classes, and Hooks
        foreach (self::WP_PARSER_PT_MAP as $key => $info) {
            if ($key === 'wp-parser-method') {
                continue;
            }
            $urlpiece = $info['urlpiece'];
            $ptype = $info['post_type'];
            add_rewrite_rule(
                "reference/($stterms)/([a-z_\-]{1,32})/$urlpiece/page/([0-9]{1,})/?",
                "index.php?post_type={$ptype}&taxonomy={$sttax}&wp-parser-source-type=\$matches[1],\$matches[2]&paged=\$matches[3]",
                'top'
            );
            add_rewrite_rule(
                "reference/($stterms)/([a-z_\-]{1,32})/$urlpiece/([^/]+)/?\$",
                "index.php?post_type={$ptype}&taxonomy={$sttax}&wp-parser-source-type=\$matches[1],\$matches[2]&name=\$matches[3]",
                'top'
            );
            add_rewrite_rule(
                "reference/($stterms)/([a-z_\-]{1,32})/$urlpiece/?\$",
                "index.php?post_type={$ptype}&taxonomy={$sttax}&wp-parser-source-type=\$matches[1],\$matches[2]",
                'top'
            );
        }

        add_rewrite_rule(
            "reference/($stterms)/([a-z_\-]{1,32})/classes/([^/]+)/([^/]+)/?\$",
            "index.php?post_type=wp-parser-method&taxonomy={$sttax}&wp-parser-source-type=\$matches[1],\$matches[2]&name=\$matches[3]-\$matches[4]",
            'top'
        );
        add_rewrite_rule(
            "reference/($stterms)/([a-z_\-]{1,32})/methods/?\$",
            "index.php?post_type=wp-parser-method&taxonomy={$sttax}&wp-parser-source-type=\$matches[1],\$matches[2]",
            'top'
        );
    }

    /**
     * Register the function and class post types
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function registerPostTypes() {
        $supports = [
            'comments',
            'custom-fields',
            'editor',
            'excerpt',
            'revisions',
            'title',
        ];

        self::addRewriteRules();

        // Functions
        register_post_type('wp-parser-function', [
            'has_archive' => 'reference/functions',
            'label' => __('Functions', 'wp-parser'),
            'labels' => [
                'name' => __('Functions', 'wp-parser'),
                'singular_name' => __('Function', 'wp-parser'),
                'all_items' => __('Functions', 'wp-parser'),
                'new_item' => __('New Function', 'wp-parser'),
                'add_new' => __('Add New', 'wp-parser'),
                'add_new_item' => __('Add New Function', 'wp-parser'),
                'edit_item' => __('Edit Function', 'wp-parser'),
                'view_item' => __('View Function', 'wp-parser'),
                'search_items' => __('Search Functions', 'wp-parser'),
                'not_found' => __('No Functions found', 'wp-parser'),
                'not_found_in_trash' => __('No Functions found in trash', 'wp-parser'),
                'parent_item_colon' => __('Parent Function', 'wp-parser'),
                'menu_name' => __('Functions', 'wp-parser'),
            ],
            'menu_icon' => 'dashicons-editor-code',
            'public' => true,
            'rewrite' => [
                'feeds' => false,
                'slug' => 'reference/functions',
                'with_front' => false,
            ],
            'supports' => $supports,
            'show_in_rest' => true,
        ]);

        // Methods
        register_post_type('wp-parser-method', [
            'has_archive' => 'reference/methods',
            'label' => __('Methods', 'wp-parser'),
            'labels' => [
                'name' => __('Methods', 'wp-parser'),
                'singular_name' => __('Method', 'wp-parser'),
                'all_items' => __('Methods', 'wp-parser'),
                'new_item' => __('New Method', 'wp-parser'),
                'add_new' => __('Add New', 'wp-parser'),
                'add_new_item' => __('Add New Method', 'wp-parser'),
                'edit_item' => __('Edit Method', 'wp-parser'),
                'view_item' => __('View Method', 'wp-parser'),
                'search_items' => __('Search Methods', 'wp-parser'),
                'not_found' => __('No Methods found', 'wp-parser'),
                'not_found_in_trash' => __('No Methods found in trash', 'wp-parser'),
                'parent_item_colon' => __('Parent Method', 'wp-parser'),
                'menu_name' => __('Methods', 'wp-parser'),
            ],
            'menu_icon' => 'dashicons-editor-code',
            'public' => true,
            'rewrite' => [
                'feeds' => false,
                'slug' => 'reference/methods',
                'with_front' => false,
            ],
            'supports' => $supports,
            'show_in_rest' => true,
        ]);

        // Classes
        register_post_type('wp-parser-class', [
            'has_archive' => 'reference/classes',
            'label' => __('Classes', 'wp-parser'),
            'labels' => [
                'name' => __('Classes', 'wp-parser'),
                'singular_name' => __('Class', 'wp-parser'),
                'all_items' => __('Classes', 'wp-parser'),
                'new_item' => __('New Class', 'wp-parser'),
                'add_new' => __('Add New', 'wp-parser'),
                'add_new_item' => __('Add New Class', 'wp-parser'),
                'edit_item' => __('Edit Class', 'wp-parser'),
                'view_item' => __('View Class', 'wp-parser'),
                'search_items' => __('Search Classes', 'wp-parser'),
                'not_found' => __('No Classes found', 'wp-parser'),
                'not_found_in_trash' => __('No Classes found in trash', 'wp-parser'),
                'parent_item_colon' => __('Parent Class', 'wp-parser'),
                'menu_name' => __('Classes', 'wp-parser'),
            ],
            'menu_icon' => 'dashicons-editor-code',
            'public' => true,
            'rewrite' => [
                'feeds' => false,
                'slug' => 'reference/classes',
                'with_front' => false,
            ],
            'supports' => $supports,
            'show_in_rest' => true,
        ]);

        // Hooks
        register_post_type('wp-parser-hook', [
            'has_archive' => 'reference/hooks',
            'label' => __('Hooks', 'wp-parser'),
            'labels' => [
                'name' => __('Hooks', 'wp-parser'),
                'singular_name' => __('Hook', 'wp-parser'),
                'all_items' => __('Hooks', 'wp-parser'),
                'new_item' => __('New Hook', 'wp-parser'),
                'add_new' => __('Add New', 'wp-parser'),
                'add_new_item' => __('Add New Hook', 'wp-parser'),
                'edit_item' => __('Edit Hook', 'wp-parser'),
                'view_item' => __('View Hook', 'wp-parser'),
                'search_items' => __('Search Hooks', 'wp-parser'),
                'not_found' => __('No Hooks found', 'wp-parser'),
                'not_found_in_trash' => __('No Hooks found in trash', 'wp-parser'),
                'parent_item_colon' => __('Parent Hook', 'wp-parser'),
                'menu_name' => __('Hooks', 'wp-parser'),
            ],
            'menu_icon' => 'dashicons-editor-code',
            'public' => true,
            'rewrite' => [
                'feeds' => false,
                'slug' => 'reference/hooks',
                'with_front' => false,
            ],
            'supports' => $supports,
            'show_in_rest' => true,
        ]);

        // Reference Landing Pages
        register_post_type('code-reference', [
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => true,
            'hierarchical' => true,
            'label' => __('Reference Landing Pages', 'wp-parser'),
            'labels' => [
                'name' => __('Reference Landing Pages', 'wp-parser'),
                'singular_name' => __('Reference Landing Page', 'wp-parser'),
                'all_items' => __('Reference Landing Pages', 'wp-parser'),
                'new_item' => __('New Reference Landing Page', 'wp-parser'),
                'add_new' => __('Add New', 'wp-parser'),
                'add_new_item' => __('Add New Reference Landing Page', 'wp-parser'),
                'edit_item' => __('Edit Reference Landing Page', 'wp-parser'),
                'view_item' => __('View Reference Landing Page', 'wp-parser'),
                'search_items' => __('Search Reference Landing Pages', 'wp-parser'),
                'not_found' => __('No Pages found', 'wp-parser'),
                'not_found_in_trash' => __('No Pages found in trash', 'wp-parser'),
                'menu_name' => __('Reference Landing Pages', 'wp-parser'),
            ],
            'menu_icon' => 'dashicons-admin-page',
            'menu_position' => 20,
            'public' => true,
            'rewrite' => [
                'slug' => 'reference',
                'with_front' => false,
                'pages' => false,
            ],
            'supports' => [
                'page-attributes',
                'custom-fields',
                'editor',
                'excerpt',
                'revisions',
                'title',
            ],
            'show_in_rest' => false,
        ]);
    }

    /**
     * Register the file and `@since` taxonomies
     *
     * @return void
     */
    public function registerTaxonomies() {
        $object_types = avcpdp_get_parsed_post_types();

        // Files
        register_taxonomy(
            'wp-parser-source-file',
            $object_types,
            [
                'label' => __('Files', 'wp-parser'),
                'labels' => [
                    'name' => __('Files', 'wp-parser'),
                    'singular_name' => _x('File', 'taxonomy general name', 'wp-parser'),
                    'search_items' => __('Search Files', 'wp-parser'),
                    'popular_items' => null,
                    'all_items' => __('All Files', 'wp-parser'),
                    'parent_item' => __('Parent File', 'wp-parser'),
                    'parent_item_colon' => __('Parent File:', 'wp-parser'),
                    'edit_item' => __('Edit File', 'wp-parser'),
                    'update_item' => __('Update File', 'wp-parser'),
                    'add_new_item' => __('New File', 'wp-parser'),
                    'new_item_name' => __('New File', 'wp-parser'),
                    'separate_items_with_commas' => __('Files separated by comma', 'wp-parser'),
                    'add_or_remove_items' => __('Add or remove Files', 'wp-parser'),
                    'choose_from_most_used' => __('Choose from the most used Files', 'wp-parser'),
                    'menu_name' => __('Files', 'wp-parser'),
                ],
                'public' => true,
                // Hierarchical x 2 to enable (.+) rather than ([^/]+) for rewrites.
                'hierarchical' => true,
                'rewrite' => [
                    'with_front' => false,
                    'slug' => 'reference/files',
                    'hierarchical' => true,
                ],
                'sort' => false,
                'update_count_callback' => '_update_post_term_count',
                'show_in_rest' => true,
            ]
        );

        // Package
        register_taxonomy(
            'wp-parser-package',
            $object_types,
            [
                'hierarchical' => true,
                'label' => '@package',
                'public' => true,
                'rewrite' => [
                    'with_front' => false,
                    'slug' => 'reference/package',
                ],
                'sort' => false,
                'update_count_callback' => '_update_post_term_count',
                'show_in_rest' => true,
            ]
        );

        // @since
        register_taxonomy(
            'wp-parser-since',
            $object_types,
            [
                'hierarchical' => true,
                'label' => __('@since', 'wp-parser'),
                'public' => true,
                'rewrite' => [
                    'with_front' => false,
                    'slug' => 'reference/since',
                ],
                'sort' => false,
                'update_count_callback' => '_update_post_term_count',
                'show_in_rest' => true,
            ]
        );

        // Namespaces
        register_taxonomy(
            'wp-parser-namespace',
            $object_types,
            [
                'hierarchical' => true,
                'label' => __('Namespaces', 'wp-parser'),
                'public' => true,
                'rewrite' => ['slug' => 'namespace'],
                'sort' => false,
                'update_count_callback' => '_update_post_term_count',
            ]
        );

        // Source Type
        register_taxonomy(
            self::SOURCE_TYPE_TAX_SLUG,
            array_merge($object_types, ['code-reference']),
            [
                'hierarchical' => true,
                'label' => __('Source Type', 'wp-parser'),
                'public' => true,
                'rewrite' => [
                    'with_front' => false,
                    'slug' => 'reference/source-type',
                    'hierarchical' => false,
                ],
                'sort' => false,
                'update_count_callback' => '_update_post_term_count',
                'show_in_rest' => true,
            ]
        );

        // Add default source-type terms
        if (!term_exists('plugin', self::SOURCE_TYPE_TAX_SLUG)) {
            wp_insert_term(
                __('Plugin', 'wp-parser'),
                self::SOURCE_TYPE_TAX_SLUG,
                ['slug' => 'plugin']
            );
        }

        if (!term_exists('theme', self::SOURCE_TYPE_TAX_SLUG)) {
            wp_insert_term(
                __('Theme', 'wp-parser'),
                self::SOURCE_TYPE_TAX_SLUG,
                ['slug' => 'theme']
            );
        }

        if (!term_exists('composer-package', self::SOURCE_TYPE_TAX_SLUG)) {
            wp_insert_term(
                __('Composer Package', 'wp-parser'),
                self::SOURCE_TYPE_TAX_SLUG,
                ['slug' => 'composer-package']
            );
        }

        // Role
        register_taxonomy(
            self::ROLE_TAX_SLUG,
            ['wp-parser-function', 'wp-parser-method'],
            [
                'hierarchical' => true,
                'label' => __('Role', 'wp-parser'),
                'public' => true,
                'rewrite' => [
                    'with_front' => false,
                    'slug' => 'reference/role',
                ],
                'sort' => false,
                'update_count_callback' => '_update_post_term_count',
                'show_in_rest' => true,
            ]
        );

        // Add default role terms
        $roles = [
            'display' => __('Display', 'wp-parser'),
            'condition' => __('Condition', 'wp-parser'),
            'utility' => __('Utility', 'wp-parser'),
            'setter' => __('Setter', 'wp-parser'),
            'getter' => __('Getter', 'wp-parser'),
        ];
        foreach ($roles as $slug => $label) {
            if (!term_exists($slug, self::ROLE_TAX_SLUG)) {
                wp_insert_term($label, self::ROLE_TAX_SLUG, ['slug' => $slug]);
            }
        }

        $parentpostmap = self::getCodeReferenceSourceTypePostMap();
        foreach ($parentpostmap as $slug => $info) {
            if (empty($parentpostmap[$slug]['post_id'])) {
                continue;
            }

            $parent_term = get_terms([
                'fields' => 'ids',
                'parent' => 0,
                'hide_empty' => false,
                'slug' => $slug,
                'taxonomy' => self::SOURCE_TYPE_TAX_SLUG,
            ]);
            if (empty($parent_term) || ($parent_term instanceof \WP_Error)) {
                continue;
            }
            $parent_term_id = $parent_term[0];
            // Assign `wp-parser-source-type` term
            wp_set_object_terms(
                $parentpostmap[$slug]['post_id'],
                [$parent_term_id],
                self::SOURCE_TYPE_TAX_SLUG
            );
        }

        // Link tags to reference post types
        foreach (avcpdp_get_parsed_post_types() as $post_type) {
            register_taxonomy_for_object_type('post_tag', $post_type);
        }
    }

    /**
     * Returns key-value array of source type slugs to their corresponding
     * landing page posts
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return (string|null)[][]|((string|null)[]|int[])[]
     */
    public static function getCodeReferenceSourceTypePostMap() {
        $parentpostmap = [
            'plugin' => [
                'title' => __('Plugin', 'wp-parser'),
                'post_id' => null,
            ],
            'theme' => [
                'title' => __('Theme', 'wp-parser'),
                'post_id' => null,
            ],
            'composer-package' => [
                'title' => __('Composer Package', 'wp-parser'),
                'post_id' => null,
            ],
        ];
        foreach ($parentpostmap as $slug => $info) {
            $posts = get_posts([
                'name' => $slug,
                'post_status' => 'any',
                'post_type' => self::CODE_REFERENCE_POST_TYPE,
            ]);
            if (!empty($posts)) {
                $parentpostmap[$slug]['post_id'] = $posts[0]->ID;
            }
        }

        return $parentpostmap;
    }

    /**
     * Filters the permalink for a wp-parser-* post.
     *
     * @param string   $link The post's permalink.
     * @param \WP_Post $post The post in question.
     * @return string
     */
    public function postPermalink($link, $post) {
        global $wp_rewrite;

        if (!$wp_rewrite->using_permalinks()) {
            return $link;
        }

        $post_types = ['wp-parser-function', 'wp-parser-hook', 'wp-parser-class', 'wp-parser-method'];

        $stterm = null;
        $stchildterm = null;
        if (in_array($post->post_type, $post_types, true)) {
            $stterms = wp_get_post_terms($post->ID, self::SOURCE_TYPE_TAX_SLUG);
            foreach ($stterms as $t) {
                if (
                    $t->parent === 0 &&
                    in_array($t->slug, self::SOURCE_TYPE_TERM_SLUGS, true)
                ) {
                    $stterm = $t;
                } else {
                    $stchildterm = $t;
                }
            }
        }

        if ($stterm === null || $stchildterm === null) {
            return $link;
        }

        if ('wp-parser-method' === $post->post_type) {
            $parts = explode('-', $post->post_name);
            $method = array_pop($parts);
            $class = implode('-', $parts);
            return home_url(user_trailingslashit(
                "reference/{$stterm->slug}/{$stchildterm->slug}/classes/{$class}/{$method}"
            ));
        }

        array_pop($post_types);
        if (in_array($post->post_type, $post_types, true)) {
            $urlpiece = self::WP_PARSER_PT_MAP[$post->post_type]['urlpiece'];
            return home_url(user_trailingslashit(
                "reference/{$stterm->slug}/{$stchildterm->slug}/{$urlpiece}/{$post->post_name}"
            ));
        }

        return $link;
    }

    /**
     * Filters the taxonomy permalink for `wp-parser-source-file` and
     * `wp-parser-since`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @global \WP_Rewrite $wp_rewrite
     * @param string   $link
     * @param \WP_Term $term
     * @param string   $taxonomy
     * @return string
     */
    public function taxonomyPermalink($link, $term, $taxonomy) {
        global $wp_rewrite;

        if (!$wp_rewrite->using_permalinks()) {
            return $link;
        }

        if ($taxonomy === 'wp-parser-source-file') {
            $slug = $term->slug;
            if (substr($slug, -4) === '-php') {
                $slug = substr($slug, 0, -4) . '.php';
                $slug = str_replace('_', '/', $slug);
            }
            $link = home_url(user_trailingslashit("reference/files/$slug"));
        } elseif ($taxonomy === 'wp-parser-since') {
            $link = str_replace($term->slug, str_replace('-', '.', $term->slug), $link);
        }

        return $link;
    }

    /**
     * Raw phpDoc could potentially introduce unsafe markup into the HTML, so we sanitise it here.
     *
     * @param array $args Parameter arguments to make safe
     * @return array
     */
    public function makeArgsSafe($args) {
        array_walk_recursive($args, [$this, 'sanitizeArgument']);

        return apply_filters('wp_parser_make_args_safe', $args);
    }

    /**
     * Sanitizes argument
     *
     * @param mixed $value
     * @return mixed
     */
    public function sanitizeArgument(&$value) {
        static $filters = [
            'wp_filter_kses',
            'make_clickable',
            'force_balance_tags',
            'wptexturize',
            'convert_smilies',
            'convert_chars',
            'stripslashes_deep',
        ];

        foreach ($filters as $filter) {
            $value = call_user_func($filter, $value);
        }

        return $value;
    }

    /**
     * Replace separators with a more readable version
     *
     * @param string $type Variable type
     * @return string
     */
    public function humanizeSeparator($type) {
        return str_replace('|', '<span class="wp-parser-item-type-or">' . _x(' or ', 'separator', 'wp-parser') . '</span>', $type);
    }
}
