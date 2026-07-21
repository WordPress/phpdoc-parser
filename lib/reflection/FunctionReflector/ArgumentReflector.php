<?php
/**
 * phpDocumentor
 *
 * PHP Version 5.3
 *
 * @author    Mike van Riel <mike.vanriel@naenius.com>
 * @copyright 2010-2012 Mike van Riel / Naenius (http://www.naenius.com)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://phpdoc.org
 */

namespace WP_Parser\Reflection\FunctionReflector;

use WP_Parser\Reflection\BaseReflector;
use PhpParser\Node\Param;

class ArgumentReflector extends BaseReflector
{
    /** @var Param */
    protected $node;

    /**
     * Checks whether the argument is passed by reference.
     *
     * @return bool TRUE if the argument is by reference, FALSE otherwise.
     */
    public function isByRef()
    {
        return $this->node->byRef;
    }

    /**
     * Returns the default value or null is none is set.
     *
     * @return string|null
     */
    public function getDefault()
    {
        $result = null;
        if ($this->node->default) {
            $result = $this->getRepresentationOfValue($this->node->default);
        }

        return $result;
    }

    /**
     * Returns the typehint, or null if none is set.
     *
     * @return string|null
     */
    public function getType()
    {
        return $this->typeToString($this->node->type);
    }

    public function getName()
    {
        return '$'.parent::getName();
    }
}
