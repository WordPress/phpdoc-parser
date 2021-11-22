<?php

namespace Aivec\Plugins\DocParser\CLI;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use WP_CLI\Loggers\Execution;

/**
 * PSR-3 logger for WP CLI.
 */
class Logger extends AbstractLogger
{
    /**
     * Logs messages
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function log($level, $message, array $context = []) {
        switch ($level) {
            case LogLevel::WARNING:
                \WP_CLI::warning($message);
                break;

            case LogLevel::ERROR:
            case LogLevel::ALERT:
            case LogLevel::EMERGENCY:
            case LogLevel::CRITICAL:
                \WP_CLI::error($message, false);
                break;

            default:
                \WP_CLI::log($message);
        }
    }
}
