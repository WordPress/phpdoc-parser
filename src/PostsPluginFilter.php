<?php

namespace Aivec\Plugins\DocParser;

/**
 * Added plugin filter to posts
 */
class PostsPluginFilter
{
    /**
     * Initialize
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @return void
     */
    public static function init() {
        add_action('restrict_manage_posts', [get_class(), 'addSourceTypePluginFilter'], 10, 1);
        add_filter('query_vars', [get_class(), 'addSourceTypePlugin'], 10, 1);
        add_filter('posts_where', [get_class(), 'postsWhereSourceTypePlugin'], 10, 1);
    }

    /**
     * Adds form attribute to term edit page
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param string $post_type Post type to get the templates for. Default 'page'.
     * @return void
     */
    public static function addSourceTypePluginFilter($post_type) {
        $post_types = avcpdp_get_parsed_post_types();
        if (!in_array($post_type, $post_types, true)) {
            return;
        }

        $plugin_term = avcpdp_get_source_type_plugin_term();
        if (!$plugin_term) {
            return;
        }

        $show_option_all = __('All Plugins', 'wp-parser');
        $plugin_term_id = get_query_var('avcpdp_plugin');
        $args = [
            'show_option_all' => $show_option_all,
            'selected' => $plugin_term_id,
            'hide_empty' => 0,
            'child_of' => $plugin_term->term_id,
            'name' => 'avcpdp_plugin',
            'taxonomy' => $plugin_term->taxonomy,
        ];

        wp_dropdown_categories($args);
    }

    /**
     * Added search condition value "plugin"
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param array $vars
     * @return array $vars
     */
    public static function addSourceTypePlugin($vars) {
        $vars[] = 'avcpdp_plugin';
        return $vars;
    }

    /**
     * Add plugin to search criteria
     *
     * @author Seiyu Inoue <s.inoue@aivec.co.jp>
     * @param string $where The WHERE clause of the query.
     * @return string $where
     */
    public static function postsWhereSourceTypePlugin($where) {
        global $wpdb;
        if (is_admin()) {
            $value = get_query_var('avcpdp_plugin');
            if (!empty($value)) {
                $where .= $wpdb->prepare(
                    " AND EXISTS ( SELECT 
                        1
                    FROM {$wpdb->term_relationships} AS m
				    WHERE m.object_id = {$wpdb->posts}.ID 
                    AND m.term_taxonomy_id = %d )",
                    (int)$value
                );
            }
        }
        return $where;
    }
}
