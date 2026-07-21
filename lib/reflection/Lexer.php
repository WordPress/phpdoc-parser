<?php
/**
 * phpDocumentor
 *
 * PHP Version 5.3
 *
 * @author    Mike van Riel <mike.vanriel@naenius.com>
 * @copyright 2010-2011 Mike van Riel / Naenius (http://www.naenius.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://phpdoc.org
 */

namespace WP_Parser\Reflection;

use PhpParser\Lexer as BaseLexer;

/**
 * PHP-Parser lexer wrapper kept for consumers that reference this class.
 *
 * PHP-Parser 5 provides scalar raw values natively, so phpDocumentor no longer
 * needs to inject custom token attributes.
 *
 * @author  Mike van Riel <mike.vanriel@naenius.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @link    http://phpdoc.org
 */
class Lexer extends BaseLexer
{
}
