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

use PhpParser\Node\Scalar\String_;

/**
 * Custom PrettyPrinter for phpDocumentor.
 *
 * phpDocumentor has a custom PrettyPrinter for PHP-Parser because it needs the
 * raw value for scalar variables instead of an interpreted version.
 *
 * If the interpreted version was to be used then the XML interpretation would
 * fail because of special characters.
 *
 * @author  Mike van Riel <mike.vanriel@naenius.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 * @link    http://phpdoc.org
 */
class PrettyPrinter extends \PhpParser\PrettyPrinter\Standard
{
    /**
     * Converts the string into it's original representation without converting
     * the special character combinations.
     *
     * This method is overridden from the original Zend Pretty Printer because
     * the original returns the strings as interpreted by PHP-Parser.
     * Since we do not want such conversions we take the raw value that is
     * provided by PHP-Parser.
     *
     * @param String_ $node The node to return a string
     *     representation of.
     *
     * @return string
     */
    public function pScalar_String(String_ $node): string
    {
        $original = $node->getAttribute('rawValue');
        if (null === $original) {
            return parent::pScalar_String($node);
        }

        return $original;
    }

}
