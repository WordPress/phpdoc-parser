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

        if (!post_type_exists('wp-parser-function')) {
            register_post_type(
                'wp-parser-function',
                [
                    'has_archive' => 'functions',
                    'label' => __('Functions', 'wp-parser'),
                    'public' => true,
                    'rewrite' => [
                        'feeds' => false,
                        'slug' => 'function',
                        'with_front' => false,
                    ],
                    'supports' => $supports,
                ]
            );
        }

        if (!post_type_exists('wp-parser-method')) {
            add_rewrite_rule('method/([^/]+)/([^/]+)/?$', 'index.php?post_type=wp-parser-method&name=$matches[1]-$matches[2]', 'top');

            register_post_type(
                'wp-parser-method',
                [
                    'has_archive' => 'methods',
                    'label' => __('Methods', 'wp-parser'),
                    'public' => true,
                    'rewrite' => [
                        'feeds' => false,
                        'slug' => 'method',
                        'with_front' => false,
                    ],
                    'supports' => $supports,
                ]
            );
        }

        if (!post_type_exists('wp-parser-class')) {
            register_post_type(
                'wp-parser-class',
                [
                    'has_archive' => 'classes',
                    'label' => __('Classes', 'wp-parser'),
                    'public' => true,
                    'rewrite' => [
                        'feeds' => false,
                        'slug' => 'class',
                        'with_front' => false,
                    ],
                    'supports' => $supports,
                ]
            );
        }

        if (!post_type_exists('wp-parser-hook')) {
            register_post_type(
                'wp-parser-hook',
                [
                    'has_archive' => 'hooks',
                    'label' => __('Hooks', 'wp-parser'),
                    'public' => true,
                    'rewrite' => [
                        'feeds' => false,
                        'slug' => 'hook',
                        'with_front' => false,
                    ],
                    'supports' => $supports,
                ]
            );
        }
    }

    /**
     * Register the file and @since taxonomies
     */
    public function register_taxonomies() {
        $object_types = [
            'wp-parser-class',
            'wp-parser-method',
            'wp-parser-function',
            'wp-parser-hook',
        ];

        if (!taxonomy_exists('wp-parser-source-file')) {
            register_taxonomy(
                'wp-parser-source-file',
                $object_types,
                [
                    'label' => __('Files', 'wp-parser'),
                    'public' => true,
                    'rewrite' => ['slug' => 'files'],
                    'sort' => false,
                    'update_count_callback' => '_update_post_term_count',
                ]
            );
        }

        if (!taxonomy_exists('wp-parser-package')) {
            register_taxonomy(
                'wp-parser-package',
                $object_types,
                [
                    'hierarchical' => true,
                    'label' => '@package',
                    'public' => true,
                    'rewrite' => ['slug' => 'package'],
                    'sort' => false,
                    'update_count_callback' => '_update_post_term_count',
                ]
            );
        }

        if (!taxonomy_exists('wp-parser-since')) {
            register_taxonomy(
                'wp-parser-since',
                $object_types,
                [
                    'hierarchical' => true,
                    'label' => __('@since', 'wp-parser'),
                    'public' => true,
                    'rewrite' => ['slug' => 'since'],
                    'sort' => false,
                    'update_count_callback' => '_update_post_term_count',
                ]
            );
        }

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
                    'label' => __('Source Type', 'wp-parser'),
                    'public' => true,
                    'rewrite' => ['slug' => 'source-type'],
                    'sort' => false,
                    'update_count_callback' => '_update_post_term_count',
                ]
            );
        }

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
     * @param string   $link
     * @param \WP_Post $post
     *
     * @return string|void
     */
    public function method_permalink($link, $post) {
        if ('wp-parser-method' !== $post->post_type || 0 == $post->post_parent) {
            return $link;
        }

        list( $class, $method ) = explode('-', $post->post_name);
        $link = home_url(user_trailingslashit("method/$class/$method"));

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
