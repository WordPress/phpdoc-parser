<?php

namespace Aivec\Plugins\DocParser\Importer;

use WP_CLI;

/**
 * Registers and implements relationships with Posts 2 Posts.
 */
class Relationships
{
    /**
     * Post types we're setting relationships between
     *
     * @var array
     */
    public $post_types;

    /**
     * Map of post slugs to post ids.
     *
     * @var array
     */
    public $slugs_to_ids = [];

    /**
     * Map of how post IDs relate to one another.
     *
     * phpcs:ignore
     * array(
     *   $from_type => array(
     *     $from_id => array(
     *       $to_type => array(
     *         $to_slug => $to_id
     *       )
     *     )
     *   )
     * )
     *
     * @var array
     */
    public $relationships = [];

    /**
     * Adds the actions.
     */
    public function init() {
        add_action('plugins_loaded', [$this, 'require_posts_to_posts']);
        add_action('wp_loaded', [$this, 'register_post_relationships']);

        add_action('wp_parser_import_item', [$this, 'import_item'], 10, 3);
        add_action('wp_parser_starting_import', [$this, 'wp_parser_starting_import']);
        add_action('wp_parser_ending_import', [$this, 'wp_parser_ending_import'], 10, 1);
    }

    /**
     * Load the posts2posts from the composer package if it is not loaded already.
     *
     * @return void
     */
    public function require_posts_to_posts() {
        // Initializes the database tables
        \P2P_Storage::init();

        // Initializes the query mechanism
        \P2P_Query_Post::init();
    }

    /**
     * Set up relationships using Posts to Posts plugin.
     *
     * Default settings for p2p_register_connection_type:
     *   'cardinality' => 'many-to-many'
     *   'reciprocal' => false
     *
     * @link  https://github.com/scribu/wp-posts-to-posts/wiki/p2p_register_connection_type
     */
    public function register_post_relationships() {
        /*
         * Functions to functions, methods and hooks
         */
        p2p_register_connection_type([
            'name' => 'functions_to_functions',
            'from' => 'wp-parser-function',
            'to' => 'wp-parser-function',
            'can_create_post' => false,
            'self_connections' => true,
            'from_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'to_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'title' => [
                'from' => __('Uses Functions', 'wp-parser'),
                'to' => __('Used by Functions', 'wp-parser'),
            ],
        ]);

        p2p_register_connection_type([
            'name' => 'functions_to_methods',
            'from' => 'wp-parser-function',
            'to' => 'wp-parser-method',
            'can_create_post' => false,
            'from_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'to_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'title' => [
                'from' => __('Uses Methods', 'wp-parser'),
                'to' => __('Used by Functions', 'wp-parser'),
            ],
        ]);

        p2p_register_connection_type([
            'name' => 'functions_to_hooks',
            'from' => 'wp-parser-function',
            'to' => 'wp-parser-hook',
            'can_create_post' => false,
            'from_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'to_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'title' => [
                'from' => __('Uses Hooks', 'wp-parser'),
                'to' => __('Used by Functions', 'wp-parser'),
            ],
        ]);

        /*
         * Methods to functions, methods and hooks
         */
        p2p_register_connection_type([
            'name' => 'methods_to_functions',
            'from' => 'wp-parser-method',
            'to' => 'wp-parser-function',
            'can_create_post' => false,
            'from_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'to_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'title' => [
                'from' => __('Uses Functions', 'wp-parser'),
                'to' => __('Used by Methods', 'wp-parser'),
            ],
        ]);

        p2p_register_connection_type([
            'name' => 'methods_to_methods',
            'from' => 'wp-parser-method',
            'to' => 'wp-parser-method',
            'can_create_post' => false,
            'self_connections' => true,
            'from_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'to_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'title' => [
                'from' => __('Uses Methods', 'wp-parser'),
                'to' => __('Used by Methods', 'wp-parser'),
            ],
        ]);

        p2p_register_connection_type([
            'name' => 'methods_to_hooks',
            'from' => 'wp-parser-method',
            'to' => 'wp-parser-hook',
            'can_create_post' => false,
            'from_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'to_query_vars' => [
                'orderby' => 'post_title',
                'order' => 'ASC',
            ],
            'title' => [
                'from' => __('Used by Methods', 'wp-parser'),
                'to' => __('Uses Hooks', 'wp-parser'),
            ],
        ]);
    }

    /**
     * Bring Importer post types into this class.
     * Runs at import start.
     */
    public function wp_parser_starting_import() {
        if (!$this->p2p_tables_exist()) {
            \P2P_Storage::init();
            \P2P_Storage::install();
        }

        $this->post_types = [
            'hook' => Importer::PROPERTY_MAP['post_type_hook'],
            'method' => Importer::PROPERTY_MAP['post_type_method'],
            'function' => Importer::PROPERTY_MAP['post_type_function'],
        ];
    }

    /**
     * Checks to see if the posts to posts tables exist and returns if they do
     *
     * @return bool Whether or not the posts 2 posts tables exist.
     */
    public function p2p_tables_exist() {
        global $wpdb;

        $tables = $wpdb->get_col('SHOW TABLES');

        // There is no way to get the name out of P2P so we hard code it here.
        return in_array($wpdb->prefix . 'p2p', $tables);
    }

    /**
     * As each item imports, build an array mapping it's post_type->slug to it's post ID.
     * These will be used to associate post IDs to each other without doing an additional
     * database query to map each post's slug to its ID.
     *
     * @param int   $post_id   Post ID of item just imported.
     * @param array $data      Parser data
     * @param array $post_data Post data
     */
    public function import_item($post_id, $data, $post_data) {
        $from_type = $post_data['post_type'];
        $slug = $post_data['post_name'];

        $this->slugs_to_ids[$from_type][$slug] = $post_id;

        // Build Relationships: Functions
        if ($this->post_types['function'] == $from_type) {
            // Functions to Functions
            $to_type = $this->post_types['function'];
            foreach ((array)@$data['uses']['functions'] as $to_function) {
                $to_function_slug = $this->names_to_slugs($to_function['name'], $data['namespace']);

                $this->relationships[$from_type][$post_id][$to_type][] = $to_function_slug;
            }

            // Functions to Methods
            $to_type = $this->post_types['method'];
            foreach ((array)@$data['uses']['methods'] as $to_method) {
                if ($to_method['static'] || !empty($to_method['class'])) {
                    $to_method_slug = $to_method['class'] . '-' . $to_method['name'];
                } else {
                    $to_method_slug = $to_method['name'];
                }
                $to_method_slug = $this->names_to_slugs($to_method_slug, $data['namespace']);

                $this->relationships[$from_type][$post_id][$to_type][] = $to_method_slug;
            }

            // Functions to Hooks
            $to_type = $this->post_types['hook'];
            foreach ((array)@$data['hooks'] as $to_hook) {
                // Never a namespace on a hook so don't send one.
                $to_hook_slug = $this->names_to_slugs($to_hook['name']);

                $this->relationships[$from_type][$post_id][$to_type][] = $to_hook_slug;
            }
        }

        if ($this->post_types['method'] === $from_type) {
            // Methods to Functions
            $to_type = $this->post_types['function'];
            foreach ((array)@$data['uses']['functions'] as $to_function) {
                $to_function_slug = $this->names_to_slugs($to_function['name'], $data['namespace']);

                $this->relationships[$from_type][$post_id][$to_type][] = $to_function_slug;
            }

            // Methods to Methods
            $to_type = $this->post_types['method'];
            foreach ((array)@$data['uses']['methods'] as $to_method) {
                if (!is_string($to_method['name'])) { // might contain variable node for dynamic method calls
                    continue;
                }

                if ($to_method['static'] || !empty($to_method['class'])) {
                    $to_method_slug = $to_method['class'] . '-' . $to_method['name'];
                } else {
                    $to_method_slug = $to_method['name'];
                }
                $to_method_slug = $this->names_to_slugs($to_method_slug, $data['namespace']);

                $this->relationships[$from_type][$post_id][$to_type][] = $to_method_slug;
            }

            // Methods to Hooks
            $to_type = $this->post_types['hook'];
            foreach ((array)@$data['hooks'] as $to_hook) {
                $to_hook_slug = $this->names_to_slugs($to_hook['name']);

                $this->relationships[$from_type][$post_id][$to_type][] = $to_hook_slug;
            }
        }
    }

    /**
     * After import has run, go back and connect all the posts.
     */
    public function wp_parser_ending_import(Importer $importer) {
        global $wpdb;

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::log('Removing current relationships...');
        }

        /*
         * The following lines delete connections for ALL sources which
         * is why we delete with a custom query below.
         */
        // p2p_delete_connections('functions_to_functions');
        // p2p_delete_connections('functions_to_methods');
        // p2p_delete_connections('functions_to_hooks');
        // p2p_delete_connections('methods_to_functions');
        // p2p_delete_connections('methods_to_methods');
        // p2p_delete_connections('methods_to_hooks');

        $wtr = $wpdb->prefix . 'term_relationships';
        $wtt = $wpdb->prefix . 'term_taxonomy';
        $wt = $wpdb->prefix . 'terms';
        $p2p = $wpdb->prefix . 'p2p';
        $p2p_meta = $wpdb->prefix . 'p2pmeta';
        foreach (
            [
                'functions_to_functions',
                'functions_to_methods',
                'functions_to_hooks',
                'methods_to_functions',
                'methods_to_methods',
                'methods_to_hooks',
            ] as $p2p_type
        ) {
            /*
             * This query deletes only associations for the current plugin/theme/composer-package
             * being imported.
             */
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE p2p, p2p_meta FROM {$p2p} p2p
                    INNER JOIN {$wtr} wtr ON wtr.object_id = p2p.p2p_from
                    INNER JOIN {$wtt} wtt ON wtt.term_taxonomy_id = wtr.term_taxonomy_id
                    INNER JOIN {$wt} wt ON wt.term_id = wtt.term_id
                    LEFT JOIN {$p2p_meta} p2p_meta ON p2p_meta.p2p_id = p2p.p2p_id
                    WHERE wt.slug = %s
                    AND wtt.parent = %d
                    AND p2p.p2p_type = %s",
                    $importer->source_type_meta['name'],
                    $importer->source_type_meta['type_parent_term_id'],
                    $p2p_type
                )
            );
        }

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::log('Setting up relationships...');
        }

        // Iterate over post types being related FROM: functions, methods, and hooks
        foreach ($this->post_types as $from_type) {
            // Iterate over relationships for each post type
            foreach ((array)@$this->relationships[$from_type] as $from_id => $to_types) {
                // Iterate over slugs for each post type being related TO
                foreach ($to_types as $to_type => $to_slugs) {
                    // Convert slugs to IDs.
                    if (empty($this->slugs_to_ids[$to_type])) { // TODO why might this be empty? test class-IXR.php
                        continue;
                    }

                    $this->relationships[$from_type][$from_id][$to_type] = $this->get_ids_for_slugs($to_slugs, $this->slugs_to_ids[$to_type]);
                }
            }
        }

        // Repeat loop over post_types and relationships now that all slugs have been mapped to IDs
        foreach ($this->post_types as $from_type) {
            foreach ((array)@$this->relationships[$from_type] as $from_id => $to_types) {
                // Connect Functions
                if ($from_type == $this->post_types['function']) {
                    foreach ($to_types as $to_type => $to_slugs) {
                        // ...to Functions
                        if ($this->post_types['function'] == $to_type) {
                            foreach ($to_slugs as $to_slug => $to_id) {
                                $to_id = intval($to_id, 10);
                                if (0 != $to_id) {
                                    p2p_type('functions_to_functions')->connect($from_id, $to_id, ['date' => current_time('mysql')]);
                                }
                            }
                        }
                        // ...to Methods
                        if ($this->post_types['method'] == $to_type) {
                            foreach ($to_slugs as $to_slug => $to_id) {
                                $to_id = intval($to_id, 10);
                                if (0 != $to_id) {
                                    p2p_type('functions_to_methods')->connect($from_id, $to_id, ['date' => current_time('mysql')]);
                                }
                            }
                        }
                        // ...to Hooks
                        if ($this->post_types['hook'] == $to_type) {
                            foreach ($to_slugs as $to_slug => $to_id) {
                                $to_id = intval($to_id, 10);
                                if (0 != $to_id) {
                                    p2p_type('functions_to_hooks')->connect($from_id, $to_id, ['date' => current_time('mysql')]);
                                }
                            }
                        }
                    }
                }

                // Connect Methods
                if ($from_type === $this->post_types['method']) {
                    foreach ($to_types as $to_type => $to_slugs) {
                        // ...to Functions
                        if ($this->post_types['function'] === $to_type) {
                            foreach ($to_slugs as $to_slug => $to_id) {
                                $to_id = intval($to_id, 10);
                                if (0 != $to_id) {
                                    p2p_type('methods_to_functions')->connect($from_id, $to_id, ['data' => current_time('mysql')]);
                                }
                            }
                        }

                        // ...to Methods
                        if ($this->post_types['method'] === $to_type) {
                            foreach ($to_slugs as $to_slug => $to_id) {
                                $to_id = intval($to_id, 10);
                                if (0 != $to_id) {
                                    p2p_type('methods_to_methods')->connect($from_id, $to_id, ['data' => current_time('mysql')]);
                                }
                            }
                        }

                        // ...to Hooks
                        if ($this->post_types['hook'] === $to_type) {
                            foreach ($to_slugs as $to_slug => $to_id) {
                                $to_id = intval($to_id, 10);
                                if (0 != $to_id) {
                                    p2p_type('methods_to_hooks')->connect($from_id, $to_id, ['data' => current_time('mysql')]);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Map a name to slug, taking into account namespace context.
     *
     * When a function is called within a namespace, the function is first looked
     * for in the current namespace. If it exists, the namespaced version is used.
     * If the function does not exist in the current namespace, PHP tries to find
     * the function in the global scope.
     *
     * Unless the call has been prefixed with '\' indicating it is fully qualified
     * we need to check first in the current namespace and then in the global
     * scope.
     *
     * This also catches the case where relative namespaces are used. You can
     * create a file in namespace `\Foo` and then call a funtion called `baz` in
     * namespace `\Foo\Bar\` by just calling `Bar\baz()`. PHP will first look
     * for `\Foo\Bar\baz()` and if it can't find it fall back to `\Bar\baz()`.
     *
     * @see    Importer::import_item()
     * @param  string $name      The name of the item a slug is needed for.
     * @param  string $namespace The namespace the item is in when for context.
     * @return array             An array of slugs, starting with the context of the
     *                           namespace, and falling back to the global namespace.
     */
    public function names_to_slugs($name, $namespace = null) {
        $fully_qualified = (0 === strpos('\\', $name));
        $name = ltrim($name, '\\');
        $names = [];

        if ($namespace && !$fully_qualified) {
            $names[] = $this->name_to_slug($namespace . '\\' . $name);
        }
        $names[] = $this->name_to_slug($name);

        return $names;
    }

    /**
     * Simple conversion of a method, function, or hook name to a post slug.
     *
     * Replaces '::' and '\' to dashes and then runs the name through `sanitize_title()`.
     *
     * @param  string $name Method, function, or hook name
     * @return string       The post slug for the passed name.
     */
    public function name_to_slug($name) {
        return sanitize_title(str_replace('\\', '-', str_replace('::', '-', $name)));
    }

    /**
     * Convert a post slug to an array( 'slug' => id )
     * Ignores slugs that are not found in $slugs_to_ids
     *
     * @param  array $slugs         Array of post slugs.
     * @param  array $slugs_to_ids  Map of slugs to IDs.
     * @return array
     */
    public function get_ids_for_slugs(array $slugs, array $slugs_to_ids) {
        $slugs_with_ids = [];

        foreach ($slugs as $index => $scoped_slugs) {
            // Find the first matching scope the ID exists for.
            foreach ($scoped_slugs as $slug) {
                if (array_key_exists($slug, $slugs_to_ids)) {
                    $slugs_with_ids[$slug] = $slugs_to_ids[$slug];
                    // if we found it in this scope, stop searching the chain.
                    continue;
                }
            }
        }

        return $slugs_with_ids;
    }
}
