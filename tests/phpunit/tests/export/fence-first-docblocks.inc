<?php

/**
 * ```setup-blueprint shared
 * {"steps":[]}
 * ```
 *
 * ```php interactive setup-blueprint=shared
 * <?php
 * @unlink( '/tmp/phpdoc-parser-file-review' );
 * @! file_exists( '/tmp/phpdoc-parser-file-review' );
 * echo 'file fence';
 * ```
 */

/**
 * ```php interactive setup-blueprint=shared
 * <?php
 *
 * echo 'fence first';
 * ```
 */
function fence_first_docblock_example() {
}

/**
 *     ```php interactive
 *     <?php
 *     // This source line ends in a period.
 *     echo 'period split';
 *     ```
 */
function fence_first_period_example() {
}

/**
 * A snippet containing PHP's error-suppression operator.
 *
 * ```php interactive
 * <?php
 * @_before( 'not parsed before a letter-named tag' );
 * @unlink( '/tmp/phpdoc-parser-review' );
 * @! file_exists( '/tmp/phpdoc-parser-review' );
 * @$suppressed_value;
 * @( file_exists( '/tmp/phpdoc-parser-review' ) );
 * @_same( 'parsed after the tag block starts' );
 * @2inside( 'numeric tag name parsed after the tag block starts' );
 *   @author( 'indentation makes this part of the preceding tag' );
 * @since( 'inside-fence' );
 * echo 'at sign';
 * // Outputs: at sign
 * ```
 *
 * @since 1.0.0
 * @_before A real custom tag.
 * @_same Another real custom tag.
 * @author Jane Doe
 */
function docblock_at_sign_example() {
}
