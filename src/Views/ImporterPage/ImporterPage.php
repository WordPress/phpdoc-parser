<?php

namespace Aivec\Plugins\DocParser\Views\ImporterPage;

use Aivec\Plugins\DocParser\Master;
use AVCPDP\Aivec\Core\CSS\PluginLoader;

/**
 * Importer page for importing source code from the admin console
 */
class ImporterPage
{
    const PAGE = 'avcpdp_importer';
    const OPTIONS_KEY = 'avcpdp_settings';

    /**
     * Master object
     *
     * @var Master
     */
    private $master;

    /**
     * Injects `Master`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param Master $master
     * @return void
     */
    public function __construct(Master $master) {
        $this->master = $master;
    }

    /**
     * Registers hooks
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function init() {
        add_action('admin_menu', [get_class(), 'registerSettingsPage']);
        add_action('admin_init', [get_class(), 'registerSetting']);
        add_action('admin_enqueue_scripts', [$this, 'load'], 10, 1);
    }

    /**
     * Loads settings page assets
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $hook_suffix
     * @return void
     */
    public function load($hook_suffix) {
        if ($hook_suffix !== 'settings_page_' . self::PAGE) {
            return;
        }

        $assetsmap = include(AVCPDP_DIST_DIR . '/js/Views/ImporterPage/App.asset.php');
        wp_enqueue_script(
            self::PAGE,
            AVCPDP_DIST_URL . '/js/Views/ImporterPage/App.js',
            $assetsmap['dependencies'],
            $assetsmap['version'],
            true
        );

        PluginLoader::loadCoreCss();
        wp_enqueue_style(
            'avcpdp-importer-page',
            AVCPDP_PLUGIN_URL . '/src/Views/ImporterPage/importer-page.css',
            [],
            AVCPDP_VERSION
        );

        wp_set_script_translations(self::PAGE, 'wp-parser', AVCPDP_LANG_DIR);
        wp_localize_script(
            self::PAGE,
            'avcpdp',
            array_merge(
                self::getSettings(),
                $this->master->getScriptInjectionVariables()
            )
        );
    }

    /**
     * Returns settings array
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public static function getSettings() {
        $settings = get_option(self::OPTIONS_KEY, [
            'sourceFoldersAbspath' => ABSPATH,
            'importOutput' => '',
        ]);
        return $settings;
    }

    /**
     * Registers `avcpdp_settings` option name
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public static function registerSetting() {
        register_setting(self::PAGE, self::OPTIONS_KEY);
    }

    /**
     * Adds settings page
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public static function registerSettingsPage() {
        add_options_page(
            'AVC WP Parser Importer',
            'AVC WP Parser Importer',
            'manage_options',
            self::PAGE,
            [get_class(), 'addSettingsPage']
        );
    }

    /**
     * Adds `AVC WP Parser Importer` page
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public static function addSettingsPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('AVC WP Parser Importer', 'cptmp') ?></h1>
            <div
                id="<?php echo esc_attr(Master::REACT_DOM_NODE); ?>"
                class="avc-v2 flex column-nowrap"
            ></div>
        </div>
        <?php
    }
}
