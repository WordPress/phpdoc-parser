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
        add_action('parse_tax_query', [get_class(), 'taxQueryNoChildren'], 10, 1);
        add_filter('pre_handle_404', [get_class(), 'force404onWrongSourceType'], 10, 2);
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

    public static function taxQueryNoChildren(\WP_Query $query) {
        if (!$query->tax_query) {
            return;
        }
        if (empty($query->query['taxonomy']) || $query->query['taxonomy'] !== \WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG) {
            return;
        }
        if (empty($query->tax_query->queries)) {
            return;
        }
        if ($query->tax_query->queries[0]['taxonomy'] !== \WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG) {
            return;
        }

        $query->tax_query->queries[0]['operator'] = 'AND';
        $query->tax_query->queries[0]['include_children'] = false;
    }

    /**
     * 404s for `wp-parser-{function|class|method|hook}` post types for single pages
     * if the source-type taxonomy term slug and it's child term slug are not set for the post.
     *
     * For example, given this URL: `/reference/plugin/my-plugin/functions/my-function`,
     * a 404 will be forced if the `my-function` post, which is of the `wp-parser-function`
     * post type, is not associated with the `plugin` and `my-plugin` terms, which both belong
     * to the `wp-parser-source-type` taxonomy, where `plugin` is one of the three default terms
     * and `my-plugin` is a child term of `plugin`.
     *
     * This hook is used because WordPress' `get_posts` function does not process taxonomy queries
     * for singular pages.
     *
     * Note that WordPress will try to guess the URL and redirect to the correct permalink for
     * the post even if we return a 404. Whether the guess succeeds depends partially on the
     * URL rewrite rules. Our rewrite rules cause the redirect to succeed as long as the source
     * type provided is one of the three defaults (plugin, theme, composer-package). The source
     * type child term slug can be **anything**, however.
     *
     * It should be investigated whether it's worth short-circuiting guess redirect behavior or not.
     *
     * @see wp-includes/canonical.php redirect_canonical()
     * @todo Investigate if there is a more elegant way of accomplishing this. Investigate
     *       whether guess redirects are more or less SEO compliant
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param bool      $bool
     * @param \WP_Query $query
     * @return bool
     */
    public static function force404onWrongSourceType($bool, $query) {
        if (!$query->is_main_query()) {
            return $bool;
        }
        if (!$query->is_singular) {
            return $bool;
        }
        if ($query->post_count < 1) {
            return $bool;
        }
        if (!in_array($query->post->post_type, avcpdp_get_parsed_post_types(), true)) {
            return $bool;
        }
        $taxslug = \WP_Parser\Plugin::SOURCE_TYPE_TAX_SLUG;
        if (empty($query->query['taxonomy']) || empty($query->query[$taxslug])) {
            return $bool;
        }
        $qtax = $query->query['taxonomy'];
        if ($qtax !== $taxslug) {
            return $bool;
        }
        $terms = explode(',', $query->query[$taxslug]);
        if (count($terms) < 2) {
            return $bool;
        }
        $stterms = avcpdp_get_post_source_type_terms($query->post->ID);
        if ($terms[0] === $stterms['type']->slug && $terms[1] === $stterms['name']->slug) {
            return $bool;
        }

        $query->set_404();
        status_header(404);
        nocache_headers();

        return true;
    }
}
