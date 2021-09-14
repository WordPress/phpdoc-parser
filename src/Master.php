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
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('parser', __NAMESPACE__ . '\\CLI\\Commands');
        }

        (new Importer\Relationships())->init();
        (new Registrations())->init();
        (new Explanations\Explanations())->init();
        (new ParsedContent())->init();
        Queries::init();
        Formatting::init();
        if (is_admin()) {
            Admin::init();
        }
    }
}
