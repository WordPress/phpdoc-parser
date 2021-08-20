<?php

namespace WP_Parser;

/**
 * Main plugin's class. Registers things and adds WP CLI command.
 */
class Plugin
{
    const SOURCE_TYPE_TAX_SLUG = 'wp-parser-source-type';

    /**
     * @var \WP_Parser\Relationships
     */
    public $relationships;

    public function on_load() {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('parser', __NAMESPACE__ . '\\Command');
        }

        $this->relationships = new Relationships();

        add_action('init', [$this, 'register_post_types'], 11);
        add_action('init', [$this, 'register_taxonomies'], 11);
        add_filter('wp_parser_get_arguments', [$this, 'make_args_safe']);
        add_filter('wp_parser_return_type', [$this, 'humanize_separator']);

        add_filter('post_type_link', [$this, 'method_permalink'], 10, 2);
        add_filter('term_link', [$this, 'taxonomy_permalink'], 10, 3);
    }

    /**
     * Register the function and class post types
     */
    public function register_post_types() {
        $supports = [
            'comments',
            'custom-fields',
            'editor',
            'excerpt',
            'revisions',
            'title',
        ];

        // Functions
        if (!post_type_exists('wp-parser-function')) {
            register_post_type('wp-parser-function', [
                'has_archive' => 'reference/functions',
                'label' => __('Functions', 'wporg'),
                'labels' => [
                    'name' => __('Functions', 'wporg'),
                    'singular_name' => __('Function', 'wporg'),
                    'all_items' => __('Functions', 'wporg'),
                    'new_item' => __('New Function', 'wporg'),
                    'add_new' => __('Add New', 'wporg'),
                    'add_new_item' => __('Add New Function', 'wporg'),
                    'edit_item' => __('Edit Function', 'wporg'),
                    'view_item' => __('View Function', 'wporg'),
                    'search_items' => __('Search Functions', 'wporg'),
                    'not_found' => __('No Functions found', 'wporg'),
                    'not_found_in_trash' => __('No Functions found in trash', 'wporg'),
                    'parent_item_colon' => __('Parent Function', 'wporg'),
                    'menu_name' => __('Functions', 'wporg'),
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
        }

        // Methods
        if (!post_type_exists('wp-parser-method')) {
            add_rewrite_rule('reference/classes/page/([0-9]{1,})/?$', 'index.php?post_type=wp-parser-class&paged=$matches[1]', 'top');
            add_rewrite_rule('reference/classes/([^/]+)/([^/]+)/?$', 'index.php?post_type=wp-parser-method&name=$matches[1]-$matches[2]', 'top');
            register_post_type('wp-parser-method', [
                'has_archive' => 'reference/methods',
                'label' => __('Methods', 'wporg'),
                'labels' => [
                    'name' => __('Methods', 'wporg'),
                    'singular_name' => __('Method', 'wporg'),
                    'all_items' => __('Methods', 'wporg'),
                    'new_item' => __('New Method', 'wporg'),
                    'add_new' => __('Add New', 'wporg'),
                    'add_new_item' => __('Add New Method', 'wporg'),
                    'edit_item' => __('Edit Method', 'wporg'),
                    'view_item' => __('View Method', 'wporg'),
                    'search_items' => __('Search Methods', 'wporg'),
                    'not_found' => __('No Methods found', 'wporg'),
                    'not_found_in_trash' => __('No Methods found in trash', 'wporg'),
                    'parent_item_colon' => __('Parent Method', 'wporg'),
                    'menu_name' => __('Methods', 'wporg'),
                ],
                'menu_icon' => 'dashicons-editor-code',
                'public' => true,
                'rewrite' => [
                    'feeds' => false,
                    'slug' => 'classes',
                    'with_front' => false,
                ],
                'supports' => $supports,
                'show_in_rest' => true,
            ]);
        }

        // Classes
        if (!post_type_exists('wp-parser-class')) {
            register_post_type('wp-parser-class', [
                'has_archive' => 'reference/classes',
                'label' => __('Classes', 'wporg'),
                'labels' => [
                    'name' => __('Classes', 'wporg'),
                    'singular_name' => __('Class', 'wporg'),
                    'all_items' => __('Classes', 'wporg'),
                    'new_item' => __('New Class', 'wporg'),
                    'add_new' => __('Add New', 'wporg'),
                    'add_new_item' => __('Add New Class', 'wporg'),
                    'edit_item' => __('Edit Class', 'wporg'),
                    'view_item' => __('View Class', 'wporg'),
                    'search_items' => __('Search Classes', 'wporg'),
                    'not_found' => __('No Classes found', 'wporg'),
                    'not_found_in_trash' => __('No Classes found in trash', 'wporg'),
                    'parent_item_colon' => __('Parent Class', 'wporg'),
                    'menu_name' => __('Classes', 'wporg'),
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
        }

        // Hooks
        if (!post_type_exists('wp-parser-hook')) {
            register_post_type('wp-parser-hook', [
                'has_archive' => 'reference/hooks',
                'label' => __('Hooks', 'wporg'),
                'labels' => [
                    'name' => __('Hooks', 'wporg'),
                    'singular_name' => __('Hook', 'wporg'),
                    'all_items' => __('Hooks', 'wporg'),
                    'new_item' => __('New Hook', 'wporg'),
                    'add_new' => __('Add New', 'wporg'),
                    'add_new_item' => __('Add New Hook', 'wporg'),
                    'edit_item' => __('Edit Hook', 'wporg'),
                    'view_item' => __('View Hook', 'wporg'),
                    'search_items' => __('Search Hooks', 'wporg'),
                    'not_found' => __('No Hooks found', 'wporg'),
                    'not_found_in_trash' => __('No Hooks found in trash', 'wporg'),
                    'parent_item_colon' => __('Parent Hook', 'wporg'),
                    'menu_name' => __('Hooks', 'wporg'),
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
        }
    }

    /**
     * Register the file and @since taxonomies
     */
    public function register_taxonomies() {
        $object_types = avcpdp_get_parsed_post_types();

        // Files
        if (!taxonomy_exists('wp-parser-source-file')) {
            register_taxonomy(
                'wp-parser-source-file',
                $object_types,
                [
                    'label' => __('Files', 'wporg'),
                    'labels' => [
                        'name' => __('Files', 'wporg'),
                        'singular_name' => _x('File', 'taxonomy general name', 'wporg'),
                        'search_items' => __('Search Files', 'wporg'),
                        'popular_items' => null,
                        'all_items' => __('All Files', 'wporg'),
                        'parent_item' => __('Parent File', 'wporg'),
                        'parent_item_colon' => __('Parent File:', 'wporg'),
                        'edit_item' => __('Edit File', 'wporg'),
                        'update_item' => __('Update File', 'wporg'),
                        'add_new_item' => __('New File', 'wporg'),
                        'new_item_name' => __('New File', 'wporg'),
                        'separate_items_with_commas' => __('Files separated by comma', 'wporg'),
                        'add_or_remove_items' => __('Add or remove Files', 'wporg'),
                        'choose_from_most_used' => __('Choose from the most used Files', 'wporg'),
                        'menu_name' => __('Files', 'wporg'),
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
        }

        // Package
        if (!taxonomy_exists('wp-parser-package')) {
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
        }

        // @since
        if (!taxonomy_exists('wp-parser-since')) {
            register_taxonomy(
                'wp-parser-since',
                $object_types,
                [
                    'hierarchical' => true,
                    'label' => __('@since', 'wporg'),
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
        }

        // Namespaces
        if (!taxonomy_exists('wp-parser-namespace')) {
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
        }

        if (!taxonomy_exists(self::SOURCE_TYPE_TAX_SLUG)) {
            register_taxonomy(
                self::SOURCE_TYPE_TAX_SLUG,
                $object_types,
                [
                    'hierarchical' => true,
                    'label' => __('Source Type', 'wporg'),
                    'public' => true,
                    'rewrite' => [
                        'with_front' => false,
                        'slug' => 'reference/source-type',
                    ],
                    'sort' => false,
                    'update_count_callback' => '_update_post_term_count',
                    'show_in_rest' => true,
                ]
            );
        }

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
    }

    /**
     * Filters the permalink for a wp-parser-method post.
     *
     * @param string   $link The post's permalink.
     * @param \WP_Post $post The post in question.
     * @return string
     */
    public function method_permalink($link, $post) {
        global $wp_rewrite;

        if (!$wp_rewrite->using_permalinks() || ('wp-parser-method' !== $post->post_type)) {
            return $link;
        }

        $parts = explode('-', $post->post_name);
        $method = array_pop($parts);
        $class = implode('-', $parts);

        return home_url(user_trailingslashit("reference/classes/$class/$method"));
    }

    public function taxonomy_permalink($link, $term, $taxonomy) {
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
     *
     * @return array
     */
    public function make_args_safe($args) {
        array_walk_recursive($args, [$this, 'sanitize_argument']);

        return apply_filters('wp_parser_make_args_safe', $args);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function sanitize_argument(&$value) {
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
     *
     * @return string
     */
    public function humanize_separator($type) {
        return str_replace('|', '<span class="wp-parser-item-type-or">' . _x(' or ', 'separator', 'wp-parser') . '</span>', $type);
    }
}
