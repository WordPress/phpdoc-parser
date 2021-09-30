<?php

namespace Aivec\Plugins\DocParser\REST;

use Aivec\Plugins\DocParser\ErrorStore;
use Aivec\Plugins\DocParser\Master;
use Aivec\Plugins\DocParser\Views\ImporterPage\ImporterPage;
use AVCPDP\Aivec\ResponseHandler\GenericError;

/**
 * REST handlers for updating settings
 */
class Settings
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
    }

    /**
     * Updates the absolute path to the folder containing source folders to be imported
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $args
     * @param array $payload
     * @return GenericError|string `success` on success
     */
    public function updateSourceFoldersAbspath(array $args, array $payload) {
        $path = !empty($payload['path']) ? (string)$payload['path'] : '';
        if (empty($path)) {
            return $this->master->estore->getErrorResponse(
                ErrorStore::REQUIRED_FIELDS_MISSING,
                ['path'],
                [__('Path cannot be empty.', 'cptmp')]
            );
        }

        $settings = ImporterPage::getSettings();
        $settings['sourceFoldersAbspath'] = $path;
        $res = update_option(ImporterPage::OPTIONS_KEY, $settings);
        if ($res === false) {
            return $this->master->estore->getErrorResponse(ErrorStore::INTERNAL_SERVER_ERROR);
        }

        return 'success';
    }
}
