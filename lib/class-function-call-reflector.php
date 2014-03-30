<?php

/**
 * A reflection class for a function call.
 */

namespace WP_Parser;

use phpDocumentor\Reflection\BaseReflector;
use phpDocumentor\Reflection\DocBlock\Context;

/**
 * A reflection of a function call expression.
 */
class Function_Call_Reflector extends BaseReflector {

	/**
	 * Returns the name for this Reflector instance.
	 *
	 * @return string
	 */
	public function getName() {
		if (isset($this->node->namespacedName)) {
			return '\\'.implode('\\', $this->node->namespacedName->parts);
		}

		return (string) $this->getShortName();
	}
}
