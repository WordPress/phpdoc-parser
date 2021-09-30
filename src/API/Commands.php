<?php

namespace Aivec\Plugins\DocParser\API;

use Aivec\Plugins\DocParser\CLI\CliErrorException;
use Aivec\Plugins\DocParser\CLI\Logger;
use Aivec\Plugins\DocParser\Importer\Importer;
use Aivec\Plugins\DocParser\Importer\Parser;
use Aivec\Plugins\DocParser\Models\ImportConfig;
use Exception;

// phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
// phpcs:disable PSR2.Methods.MethodDeclaration.Underscore

/**
 * Converts PHPDoc markup into a template ready for import to a WordPress blog.
 */
class Commands
{
    /**
     * Generate JSON containing the PHPDoc markup, convert it into WordPress posts, and insert into DB.
     *
     * @param string    $directory
     * @param bool      $trashOldRefs
     * @param null|bool $quick
     * @param null|bool $importInternal
     * @throws CliErrorException Thrown when an error occurs.
     * @return void
     */
    public function create($directory, $trashOldRefs = false, $quick = null, $importInternal = null) {
        if (empty($directory)) {
            throw new CliErrorException(sprintf("Can't read %1\$s. Does the file exist?", $directory), 1);
        }

        do_action('avcpdp_command_print_line', '');

        $parser_meta_filen = 'docparser-meta.json';
        $parser_meta_filep = '';
        if ($directory === '.') {
            $parser_meta_filep = "./{$parser_meta_filen}";
        } else {
            $parser_meta_filep = "{$directory}/{$parser_meta_filen}";
        }

        do_action('avcpdp_command_print_line', sprintf('Getting source meta data from %1$s', $parser_meta_filep));

        if (!file_exists($parser_meta_filep)) {
            throw new CliErrorException(sprintf('Missing required file: %1$s', $parser_meta_filep), 1);
        }

        $metaf = file_get_contents($parser_meta_filep);
        if (empty($metaf)) {
            throw new CliErrorException(sprintf("Can't read %1\$s. Possible permissions error.", $parser_meta_filep), 1);
        }

        $parser_meta = json_decode($metaf, true);
        if ($parser_meta === null) {
            throw new CliErrorException(sprintf(
                '%1$s is malformed. Make sure the file is in proper JSON format.',
                $parser_meta_filen
            ), 1);
        }

        $types = ['plugin', 'theme', 'composer-packages'];
        $validtypesm = 'Valid types are "plugin", "theme", and "composer-package"';
        if (empty($parser_meta['type'])) {
            throw new CliErrorException("The \"type\" key is missing.\r\n{$validtypesm}", 1);
        }

        if (!in_array($parser_meta['type'], $types, true)) {
            throw new CliErrorException($validtypesm, 1);
        }

        if (empty($parser_meta['name'])) {
            throw new CliErrorException('The "name" key is missing or contains an empty value.', 1);
        }

        if (!is_string($parser_meta['name'])) {
            throw new CliErrorException('"name" must be a string.', 1);
        }

        if (isset($parser_meta['exclude'])) {
            if (!is_array($parser_meta['exclude'])) {
                throw new CliErrorException('"exclude" must be an array of strings.', 1);
            }

            foreach ($parser_meta['exclude'] as $target) {
                if (!is_string($target)) {
                    throw new CliErrorException('"exclude" must be an array of strings.', 1);
                }
            }
        }

        if (isset($parser_meta['excludeStrict'])) {
            if (!is_bool($parser_meta['excludeStrict'])) {
                throw new CliErrorException('"excludeStrict" must be a boolean.', 1);
            }
        }

        if (isset($parser_meta['version'])) {
            if (!is_string($parser_meta['version'])) {
                throw new CliErrorException('"version" must be a string.', 1);
            }
        }

        // handle file/folder exclusions
        $exclude = !empty($parser_meta['exclude']) ? $parser_meta['exclude'] : [];
        add_filter('wp_parser_exclude_directories', function () use ($exclude) {
            return $exclude;
        });

        $exclude_strict = isset($parser_meta['excludeStrict']) ? (bool)$parser_meta['excludeStrict'] : false;
        add_filter('wp_parser_exclude_directories_strict', function () use ($exclude_strict) {
            return $exclude_strict;
        });

        $version = isset($parser_meta['version']) ? $parser_meta['version'] : null;

        $data = $this->_get_phpdoc_data($directory, 'array');
        $data = [
            'config' => new ImportConfig(
                $parser_meta['type'],
                $parser_meta['name'],
                $version,
                $exclude,
                $exclude_strict
            ),
            'trash_old_refs' => $trashOldRefs,
            'files' => $data,
        ];

        // Import data
        $this->_do_import($data, isset($quick), isset($importInternal));
    }

    /**
     * Generate the data from the PHPDoc markup.
     *
     * @param string $path   Directory or file to scan for PHPDoc
     * @param string $format What format the data is returned in: [json|array].
     * @throws CliErrorException Thrown when an error occurs.
     * @return string|array
     */
    protected function _get_phpdoc_data($path, $format = 'json') {
        do_action('avcpdp_command_print_line', sprintf('Extracting PHPDoc from %1$s. This may take a few minutes...', $path));
        $is_file = is_file($path);
        $files = $is_file ? [$path] : Parser::getWpFiles($path);
        $path = $is_file ? dirname($path) : $path;

        if ($files instanceof \WP_Error) {
            throw new CliErrorException(sprintf('Problem with %1$s: %2$s', $path, $files->get_error_message()), 1);
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
     * @throws CliErrorException Thrown when an error occurs.
     * @return void
     */
    protected function _do_import(array $data, $skip_sleep = false, $import_ignored = false) {
        if (!wp_get_current_user()->exists()) {
            throw new CliErrorException('Please specify a valid user: --user=<id|login>', 1);
        }

        // Run the importer
        $importer = new Importer($data['config'], $data['trash_old_refs']);
        $importer->setLogger(new Logger());
        $importer->import($data['files'], $skip_sleep, $import_ignored);

        do_action('avcpdp_command_print_line', '');
    }
}
