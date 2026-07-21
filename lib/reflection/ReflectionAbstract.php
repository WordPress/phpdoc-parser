<?php
/**
 * phpDocumentor
 *
 * PHP Version 5.3
 *
 * @author    Mike van Riel <mike.vanriel@naenius.com>
 * @copyright 2012 Mike van Riel / Naenius (http://www.naenius.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://phpdoc.org
 */

namespace WP_Parser\Reflection;

/**
 * Provides basic event logging and dispatching for every reflection class.
 *
 * @author  Mike van Riel <mike.vanriel@naenius.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @link    http://phpdoc.org
 */
abstract class ReflectionAbstract
{
    /**
     * The context (namespace, aliases) for the reflection.
     *
     * @var \phpDocumentor\Reflection\DocBlock\Context
     */
    protected $context = null;

    /**
     * Dispatches a logging request.
     *
     * @param string $message  The message to log.
     * @param int    $priority The logging priority, the lower,
     *  the more important. Ranges from 1 to 7
     *
     * @return void
     */
    public function log($message, $priority = 6)
    {
    }

    /**
     * Dispatches a logging request to log a debug message.
     *
     * @param string $message The message to log.
     *
     * @return void
     */
    public function debug($message)
    {
    }

    /**
     * Returns a string representation of a PHP-Parser name-like value.
     *
     * @param mixed $name
     *
     * @return string
     */
    protected function nameToString($name)
    {
        if (null === $name) {
            return '';
        }

        if ($name instanceof \PhpParser\Node\Expr\Variable) {
            return $this->nameToString($name->name);
        }

        if ($name instanceof \PhpParser\Node\Name) {
            return implode('\\', $this->nameParts($name));
        }

        if (is_array($name)) {
            return implode('\\', array_map(array($this, 'nameToString'), $name));
        }

        if (is_object($name) && method_exists($name, '__toString')) {
            return (string) $name;
        }

        if (is_object($name)) {
            return get_class($name);
        }

        return (string) $name;
    }

    /**
     * Returns parts from a PHP-Parser Name across 3.x and 4.x.
     *
     * @param \PhpParser\Node\Name $name
     *
     * @return string[]
     */
    protected function nameParts(\PhpParser\Node\Name $name)
    {
        if (method_exists($name, 'getParts')) {
            return $name->getParts();
        }

        return $name->parts;
    }
}
