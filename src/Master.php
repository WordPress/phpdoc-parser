<?php

namespace Aivec\Plugins\DocParser;

/**
 * Top-level class
 */
class Master
{
    /**
     * Initializes plugin
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public static function init() {
        add_action('init', function () {
            add_action('pre_get_posts', [get_class(), 'preGetPosts'], 10, 1);
        });

        (new Explanations())->init();
        (new ParsedContent())->init();
        if (is_admin()) {
            Admin::init();
        }
    }

    /**
     * @param \WP_Query $query
     */
    public static function preGetPosts($query) {
        if ($query->is_main_query() && $query->is_post_type_archive()) {
            $query->set('orderby', 'title');
            $query->set('order', 'ASC');
        }

        if ($query->is_main_query() && $query->is_tax() && $query->get('wp-parser-source-file')) {
            $query->set('wp-parser-source-file', str_replace(['.php', '/'], ['-php', '_'], $query->query['wp-parser-source-file']));
        }

        // For search query modifications see DevHub_Search.
    }
}
