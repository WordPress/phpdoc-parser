<?php

/**
 * File summary.
 *
 * ```setup-blueprint global-setup
 * {
 *   "steps": [
 *     {
 *       "step": "writeFile",
 *       "path": "/wordpress/wp-content/mu-plugins/global-setup.php",
 *       "data": "<?php\n\\Docs_Global_Registry::register( '\\Docs_Global' );\n"
 *     }
 *   ]
 * }
 * ```
 *
 * @since 1.0.0
 */

/**
 * This is a function docblock.
 *
 * ```php interactive setup-blueprint=global-setup
 * <?php
 * require '/wordpress/wp-load.php';
 * echo \Docs_Global::greet();
 * ```
 *
 * ```expected-output
 * Hello from \Docs_Global
 * ```
 *
 * @since 1.0.0
 */
function snippet_verbatim_func() {}
