<?php

namespace Aivec\Plugins\DocParser;

use AVCPDP\Aivec\WordPress\Routing\Router;
use AVCPDP\Aivec\WordPress\Routing\WordPressRouteCollector;

/**
 * Declares all REST routes
 */
class Routes extends Router
{
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
        parent::__construct('/avcpdp', 'avcpdp_nonce_key', 'avcpdp_nonce_name');
    }

    /**
     * Declares routes
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param WordPressRouteCollector $r
     * @return void
     */
    public function declareRoutes(WordPressRouteCollector $r) {
        $r->addGroup('/v1', function (WordPressRouteCollector $r) {
            // REST handlers
            $settings = new REST\Settings($this->master);
            $import = new REST\Import($this->master);

            // REST routes
            $r->addAdministratorRoute('POST', '/updateSourceFoldersAbspath', [$settings, 'updateSourceFoldersAbspath']);
            $r->addAdministratorRoute('POST', '/parser/create/{fname}', [$import, 'create']);
        });
    }
}
