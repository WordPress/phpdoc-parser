<?php

namespace Aivec\Plugins\DocParser\CLI;

use Exception;

/**
 * WP-CLI error exception that holds paramaters for `WP_CLI::error()`
 */
class CliErrorException extends Exception
{
    /**
     * Whether to exit or not
     *
     * @var true
     */
    private $exit = true;

    /**
     * Constructs a WP-CLI error exception
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $message
     * @param int    $code
     * @param bool   $exit
     * @return void
     */
    public function __construct($message = '', $code = 0, $exit = true) {
        $this->exit = $exit;
        parent::__construct($message, $code);
    }

    /**
     * Getter for `$this->exit`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return true
     */
    public function getExit() {
        return $this->exit;
    }
}
