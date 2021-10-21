<?php

namespace Aivec\Plugins\DocParser;

/**
 * Class to handle admin area customization and tools.
 */
class Admin
{
    /**
     * Initializes class
     *
     * @return void
     */
    public static function init() {
        add_action('admin_init', [get_class(), 'doInit']);
    }

    /**
     * Handles adding/removing hooks.
     *
     * @return void
     */
    public static function doInit() {
        add_action('admin_enqueue_scripts', [get_class(), 'adminEnqueueScripts']);
    }

    /**
     * Returns array of screen IDs for parsed post types.
     *
     * @return array
     */
    public static function getParsedPostTypesScreenIds() {
        $screen_ids = [];
        foreach (avcpdp_get_parsed_post_types() as $parsed_post_type) {
            $screen_ids[] = $parsed_post_type;
            $screen_ids[] = "edit-{$parsed_post_type}";
        }

        return $screen_ids;
    }

    /**
     * Enqueue JS and CSS on the edit screens for all parsed post types.
     *
     * @return void
     */
    public static function adminEnqueueScripts() {
        // By default, only enqueue on parsed post type screens.
        $screen_ids = self::getParsedPostTypesScreenIds();

        /*
         * Filters whether or not admin.css should be enqueued.
         *
         * @param bool True if admin.css should be enqueued, false otherwise.
         */
        if ((bool)apply_filters('avcpdp_admin_enqueue_scripts', in_array(get_current_screen()->id, $screen_ids))) {
            wp_enqueue_style(
                'avcpdp-admin',
                AVCPDP_PLUGIN_URL . '/src/styles/admin.css',
                [],
                AVCPDP_VERSION
            );
        }
    }
}
