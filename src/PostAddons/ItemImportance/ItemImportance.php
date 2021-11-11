<?php

namespace Aivec\Plugins\DocParser\PostAddons\ItemImportance;

use AVCPDP\Aivec\Core\CSS\Loader;

/**
 * Item importance for `wp-parser-*` post types
 */
class ItemImportance
{
    /**
     * Registers hooks
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public static function init() {
        add_action('pre_get_posts', [get_class(), 'preGetPosts'], 10, 1);
        // Add admin post listing column for item importance indicator.
        add_filter('manage_posts_columns', [get_class(), 'addPostColumns']);
        // Output checkmark in item importance column if post is important.
        add_action('manage_posts_custom_column', [get_class(), 'handleColumnData'], 10, 2);
        foreach (avcpdp_get_parsed_post_types() as $ptype) {
            add_filter("manage_edit-{$ptype}_sortable_columns", [get_class(), 'filterSortableCols'], 10, 1);
        }
        add_action('admin_enqueue_scripts', [get_class(), 'loadAssets'], 10, 1);
    }

    /**
     * Handles various custom query vars
     *
     * @param \WP_Query $query
     * @return void
     */
    public static function preGetPosts($query) {
        if (!is_admin()) {
            return;
        }

        $post_type = isset($_REQUEST['post_type']) ? (string)$_REQUEST['post_type'] : '';
        if (empty($post_type)) {
            return;
        }

        if (!avcpdp_is_parsed_post_type($post_type)) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'];
        $uripath = wp_parse_url($uri, PHP_URL_PATH);
        $plistpath = wp_parse_url(admin_url('edit.php'), PHP_URL_PATH);
        if ($uripath !== $plistpath) {
            // only load on post list page
            return;
        }

        $mquery = $query->get('meta_query', []);
        $mquery[] = ['key' => '_wp-parser_important'];
        $query->set('meta_query', $mquery);
    }

    /**
     * Loads admin post list page assets
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $screenid
     * @return void
     */
    public static function loadAssets($screenid) {
        if ($screenid !== 'edit.php') {
            return;
        }

        $post_type = isset($_REQUEST['post_type']) ? (string)$_REQUEST['post_type'] : '';
        if (empty($post_type)) {
            return;
        }

        if (!avcpdp_is_parsed_post_type($post_type)) {
            return;
        }

        Loader::loadCoreCss();
        wp_enqueue_style(
            'avcpdp-postaddons-item-importance',
            AVCPDP_PLUGIN_URL . '/src/PostAddons/ItemImportance/item-importance.css',
            [],
            AVCPDP_VERSION
        );
    }

    /**
     * Makes custom columns sortable
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $sortable_columns
     * @return array
     */
    public static function filterSortableCols($sortable_columns) {
        $sortable_columns['item_importance'] = 'meta_value';
        return $sortable_columns;
    }

    /**
     * Adds custom columns in the admin listing of posts for parsed post types
     *
     * Custom columns include:
     * - Item Importance
     *
     * Also removes the comment column since comments aren't supported
     *
     * @param array $columns Associative array of post column ids and labels.
     * @return array
     */
    public static function addPostColumns($columns) {
        if (empty($_GET['post_type'])) {
            return $columns;
        }

        if (!avcpdp_is_parsed_post_type($_GET['post_type'])) {
            return $columns;
        }

        $index = array_search('title', array_keys($columns));
        $pos = false === $index ? count($columns) : $index + 1;

        $col_data = [
            'item_importance' => sprintf(
                '<span class="dashicons dashicons-yes" title="%s"></span><span class="screen-reader-text">%s</span>',
                esc_attr__('Important?', 'wp-parser'),
                esc_html__('Important?', 'wp-parser')
            ),
        ];
        $columns = array_merge(array_slice($columns, 0, $pos), $col_data, array_slice($columns, $pos));

        // remove comments col
        unset($columns['comments']);

        return $columns;
    }

    /**
     * Outputs an indicator for the explanations column if post has an explanation.
     *
     * @param string $column_name The name of the column.
     * @param int    $post_id     The ID of the post.
     * @return void
     */
    public static function handleColumnData($column_name, $post_id) {
        if ('item_importance' === $column_name) {
            $importance = (bool)(int)get_post_meta($post_id, '_wp-parser_important', true);
            if ($importance == true) {
                ?>
                <div class="avc-v3 flex row-nowrap ai-center item-importance-container">
                    <span><?php esc_html_e('Important', 'wp-parser'); ?></span>
                    <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e('Important', 'wp-parser'); ?></span>
                </div>
                <?php
            }
        }
    }
}
