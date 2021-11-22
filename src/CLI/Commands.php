<?php

namespace Aivec\Plugins\DocParser\CLI;

use Aivec\Plugins\DocParser\Importer\Importer;
use Aivec\Plugins\DocParser\Importer\Parser;
use Aivec\Plugins\DocParser\API\Commands as API;
use WP_CLI;
use WP_CLI_Command;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore

/**
 * Converts PHPDoc markup into a template ready for import to a WordPress blog.
 */
class Commands extends WP_CLI_Command
{
    /**
     * Generate a JSON file containing the PHPDoc markup, and save to filesystem.
     *
     * @synopsis <directory> [<output_file>]
     *
     * @param array $args
     * @return void
     */
    public function export($args) {
        $directory = realpath($args[0]);
        $output_file = empty($args[1]) ? 'phpdoc.json' : $args[1];
        $json = $this->_get_phpdoc_data($directory);
        $result = file_put_contents($output_file, $json);
        WP_CLI::line();

        if (false === $result) {
            WP_CLI::error(sprintf('Problem writing %1$s bytes of data to %2$s', strlen($json), $output_file));
            exit;
        }

        WP_CLI::success(sprintf('Data exported to %1$s', $output_file));
        WP_CLI::line();
    }

    /**
     * Read a JSON file containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
     *
     * @synopsis <file> [--quick] [--import-internal]
     *
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public function import($args, $assoc_args) {
        list( $file ) = $args;
        WP_CLI::line();

        // Get the data from the <file>, and check it's valid.
        $phpdoc = false;

        if (is_readable($file)) {
            $phpdoc = file_get_contents($file);
        }

        if (!$phpdoc) {
            WP_CLI::error(sprintf("Can't read %1\$s. Does the file exist?", $file));
            exit;
        }

        $phpdoc = json_decode($phpdoc, true);
        if (is_null($phpdoc)) {
            WP_CLI::error(sprintf("JSON in %1\$s can't be decoded :(", $file));
            exit;
        }

        // Import data
        $this->_do_import($phpdoc, isset($assoc_args['quick']), isset($assoc_args['import-internal']));
    }

    /**
     * Generate JSON containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
     *
     * @subcommand create
     * @synopsis   <directory> [--quick] [--import-internal] [--user] [--trash-old-refs]
     *
     * @param array $args
     * @param array $assoc_args
     * @return void
     */
    public function create($args, $assoc_args) {
        list( $directory ) = $args;
        $directory = realpath($directory);

        add_action('avcpdp_command_print_line', function ($message) {
            WP_CLI::line($message);
        }, 10, 1);

        $trashOldRefs = isset($assoc_args['trash-old-refs']) && $assoc_args['trash-old-refs'] === true;
        try {
            (new API())->create($directory, $trashOldRefs, $assoc_args['quick'], $assoc_args['import-internal']);
        } catch (CliErrorException $e) {
            WP_CLI::error($e->getMessage());
            exit;
        }
    }

    /**
     * Generate the data from the PHPDoc markup.
     *
     * @param string $path   Directory or file to scan for PHPDoc
     * @param string $format What format the data is returned in: [json|array].
     * @return string|array
     */
    protected function _get_phpdoc_data($path, $format = 'json') {
        WP_CLI::line(sprintf('Extracting PHPDoc from %1$s. This may take a few minutes...', $path));
        $is_file = is_file($path);
        $files = $is_file ? [$path] : Parser::getWpFiles($path);
        $path = $is_file ? dirname($path) : $path;

        if ($files instanceof \WP_Error) {
            WP_CLI::error(sprintf('Problem with %1$s: %2$s', $path, $files->get_error_message()));
            exit;
        }

        $output = Parser::parseFiles($files, $path);

        if ('json' == $format) {
            return json_encode($output, JSON_PRETTY_PRINT);
        }

        return $output;
    }

    /**
     * Import the PHPDoc $data into WordPress posts and taxonomies
     *
     * @param array $data
     * @param bool  $skip_sleep     If true, the sleep() calls are skipped.
     * @param bool  $import_ignored If true, functions marked `@ignore` will be imported.
     * @return void
     */
    protected function _do_import(array $data, $skip_sleep = false, $import_ignored = false) {
        if (!wp_get_current_user()->exists()) {
            WP_CLI::error('Please specify a valid user: --user=<id|login>');
            exit;
        }

        // Run the importer
        $importer = new Importer($data['config'], $data['trash_old_refs']);
        $importer->setLogger(new Logger());

        try {
            $importer->import($data['files'], $skip_sleep, $import_ignored);
        } catch (CliErrorException $e) {
            WP_CLI::error($e->getMessage());
            exit;
        }

        WP_CLI::line();
    }
}
