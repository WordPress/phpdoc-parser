<?php

namespace Aivec\Plugins\DocParser\REST;

use Aivec\Plugins\DocParser\API\Commands as API;
use Aivec\Plugins\DocParser\ErrorStore;
use Aivec\Plugins\DocParser\Master;
use Aivec\Plugins\DocParser\Views\ImporterPage\ImporterPage;
use AVCPDP\Aivec\ResponseHandler\GenericError;
use Exception;
use WP_CLI\Loggers\Execution;
use WP_CLI\Loggers\Regular;

/**
 * REST handler for importing source code
 */
class Import
{
    const STATUS_TRACKER = 'avcpdp_importer_status';

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
     * Imports source code
     *
     * Mirror of the `create` command for `wp parser`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $args
     * @param array $payload
     * @return GenericError|string
     */
    public function create(array $args, array $payload) {
        $fpath = ImporterPage::getSettings()['sourceFoldersAbspath'];
        $fname = $args['fname'];
        $fullpath = trailingslashit($fpath) . trim($fname, '/');
        if (!is_dir($fullpath)) {
            return $this->master->estore->getErrorResponse(
                ErrorStore::SOURCE_NOT_FOUND,
                [$fullpath],
                [$fullpath]
            );
        }

        $trashOldRefs = isset($assoc_args['trashOldRefs']) && $assoc_args['trashOldRefs'] === true;

        $settings = ImporterPage::getSettings();
        try {
            $execution_logger = new Execution(true);
            $execution_logger->ob_start();
            \WP_CLI::set_logger($execution_logger);
            (new API())->create($fullpath, $trashOldRefs);
            $execution_logger->ob_end();
            $stdout = $execution_logger->stdout;
            $stderr = $execution_logger->stderr;
            $settings['importOutput'] = $stdout;
        } catch (Exception $e) {
            ob_clean();
            return $this->master->estore->getErrorResponse(
                ErrorStore::IMPORT_ERROR,
                [$e->getMessage()],
                [$e->getMessage()]
            );
        }

        return 'success';
    }

    public function status(array $args) {
        $status = get_option(self::STATUS_TRACKER, [
            'status' => '',
            'fulloutput' => '',
        ]);

        return $status;
    }
}
