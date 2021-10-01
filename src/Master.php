<?php

namespace Aivec\Plugins\DocParser;

/**
 * Top-level class
 */
class Master
{
    const REACT_DOM_NODE = 'avcpdp_react';

    /**
     * Error store object
     *
     * @var ErrorStore
     */
    public $estore;

    /**
     * Routes object
     *
     * @var Routes
     */
    public $routes;

    /**
     * Initializes plugin
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function init() {
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('parser', __NAMESPACE__ . '\\CLI\\Commands');
        }

        $this->estore = new ErrorStore();
        $this->estore->populate();
        add_action('init', function () {
            $this->routes = new Routes($this);
            $this->routes->dispatcher->listen();
        }, 12);

        (new Views\ImporterPage\ImporterPage($this))->init();
        (new Importer\Relationships())->init();
        (new Registrations())->init();
        (new Explanations\Explanations())->init();
        (new ParsedContent())->init();
        Queries::init();
        Formatting::init();
        SourceTypeTerm::init();
        PostsPluginFilter::init();
        if (is_admin()) {
            Admin::init();
        }
    }

    /**
     * Returns variables to be injected into JS scripts
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function getScriptInjectionVariables() {
        return array_merge(
            ['reactDomNode' => self::REACT_DOM_NODE],
            $this->estore->getScriptInjectionVariables(),
            $this->routes->getScriptInjectionVariables()
        );
    }
}
