<?php

/**
 * This is the file-level docblock summary.
 *
 * This is the file-level docblock description, which may span multiple lines. In
 * fact, this one does. It spans more than two full lines, continuing on to the
 * third line.
 *
 * ```setup-blueprint file-greeting
 * {
 *   "steps": [
 *     {
 *       "step": "writeFile",
 *       "path": "/wordpress/wp-content/mu-plugins/file-greeting.php",
 *       "data": "<?php\nfunction docs_file_greeting() {\n\treturn 'Hello from the file setup';\n}\n"
 *     }
 *   ]
 * }
 * ```
 *
 * @since 1.5.0
 */

use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;

/**
 * This is a function docblock.
 *
 * This function is just a test, but we've added this description anyway.
 *
 * @since 2.6.0
 *
 * @param string $var A string value.
 * @param int    $num A number.
 *
 * @return bool Whether the function was called correctly.
 */
function test_func( $var, $num ) {

	// In real life, there *might* be more stuff in here.
	return true;
}

/**
 * Localizes a script.
 *
 * Copied from WordPress core's wp_localize_script().
 *
 * @param string               $handle      Script handle the data will be attached to.
 * @param string               $object_name Name for the JavaScript object. Passed directly, so it should be qualified JS variable.
 *                                          Example: '/[a-zA-Z0-9_]+/'.
 * @param array<string, mixed> $l10n        The data itself. The data can be either a single or multi-dimensional array.
 * @return bool True if the script was successfully localized, false otherwise.
 */
function docs_wp_localize_script( $handle, $object_name, $l10n ) {
}

/**
 * Creates a new AI prompt builder using the default provider registry.
 *
 * Copied from WordPress core's wp_ai_client_prompt().
 *
 * @param string|MessagePart|Message|array|list<string|MessagePart|array>|list<Message>|null $prompt Optional. Initial prompt content.
 *                                                                                                   A string for simple text prompts,
 *                                                                                                   a MessagePart or Message object for
 *                                                                                                   structured content, an array for a
 *                                                                                                   message array shape, or a list of
 *                                                                                                   parts or messages for multi-turn
 *                                                                                                   conversations. Default null.
 * @return WP_AI_Client_Prompt_Builder The prompt builder instance.
 */
function docs_wp_ai_client_prompt( $prompt = null ) {
}

/**
 * Registers a new ability using the Abilities API.
 *
 * Copied from WordPress core's wp_register_ability().
 *
 * @param string               $name The name of the ability. Must be a namespaced string containing
 *                                   a prefix, e.g., `my-plugin/my-ability`. Can only contain lowercase
 *                                   alphanumeric characters, dashes, and forward slashes.
 * @param array<string, mixed> $args {
 *     An associative array of arguments for configuring the ability.
 *
 *     @type string               $label               Required. The human-readable label for the ability.
 *     @type string               $description         Required. A detailed description of what the ability does
 *                                                     and when it should be used.
 *     @type string               $category            Required. The ability category slug this ability belongs to.
 *                                                     The ability category must be registered via `wp_register_ability_category()`
 *                                                     before registering the ability.
 *     @type callable             $execute_callback    Required. A callback function to execute when the ability is invoked.
 *                                                     Receives optional mixed input data and must return either a result
 *                                                     value (any type) or a `WP_Error` object on failure.
 *     @type callable             $permission_callback Required. A callback function to check permissions before execution.
 *                                                     Receives optional mixed input data (same as `execute_callback`) and
 *                                                     must return `true`/`false` for simple checks, or `WP_Error` for
 *                                                     detailed error responses.
 *     @type array<string, mixed> $input_schema        Optional. JSON Schema definition for validating the ability's input.
 *                                                     Must be a valid JSON Schema object defining the structure and
 *                                                     constraints for input data. Used for automatic validation and
 *                                                     API documentation.
 *     @type array<string, mixed> $output_schema       Optional. JSON Schema definition for the ability's output.
 *                                                     Describes the structure of successful return values from
 *                                                     `execute_callback`. Used for documentation and validation.
 *     @type array<string, mixed> $meta                {
 *         Optional. Additional metadata for the ability.
 *
 *         @type array<string, bool|null> $annotations  {
 *             Optional. Semantic annotations describing the ability's behavioral characteristics.
 *             These annotations are hints for tooling and documentation.
 *
 *             @type bool|null $readonly    Optional. If true, the ability does not modify its environment.
 *             @type bool|null $destructive Optional. If true, the ability may perform destructive updates to its environment.
 *                                          If false, the ability performs only additive updates.
 *             @type bool|null $idempotent  Optional. If true, calling the ability repeatedly with the same arguments
 *                                          will have no additional effect on its environment.
 *         }
 *         @type bool                     $public       Optional. Whether the ability is meant to be available to
 *                                                      clients such as the REST API, MCP, or AI agents. Seeds
 *                                                      the default for per-channel flags like `$show_in_rest`.
 *                                                      Defaults to false.
 *         @type bool                     $show_in_rest Optional. Whether to expose this ability in the REST API.
 *                                                      When true, the ability can be invoked via HTTP requests.
 *                                                      Default is the value of `$public` when set, false otherwise.
 *     }
 *     @type string               $ability_class       Optional. Fully-qualified custom class name to instantiate
 *                                                     instead of the default `WP_Ability` class. The custom class
 *                                                     must extend `WP_Ability`. Useful for advanced customization
 *                                                     of ability behavior.
 * }
 * @return WP_Ability|null The registered ability instance on success, `null` on failure.
 */
function docs_wp_register_ability( $name, $args ) {
}

/**
 * Tests type expressions PHPStan's grammar reads and phpDocumentor's does not.
 *
 * @template T
 *
 * @param array{label: string, count?: int} $shape     An array shape.
 * @param ?WP_Post                          $post      A nullable class.
 * @param MessagePart&Countable             $both      An intersection.
 * @param iterable<int, list<MessagePart>>  $parts     Class names resolved inside generics.
 * @param callable(string $a): bool         $callback  A callable with a signature.
 * @param non-empty-string|int<0, max>      $pseudo    PHPStan pseudo-types.
 * @param class-string<T>                   $class     A template type.
 * @param MessagePart::TYPE_*               $constant  A class constant pattern.
 * @param array<int, string>                &$by_ref   A reference.
 * @param                                   $untyped   No type.
 * @return array<string, string> Labels keyed by name.
 */
function test_phpstan_types( $shape, $post, $both, $parts, $callback, $pseudo, $class, $constant, &$by_ref, $untyped ) {
}

/**
 * Tests a multi-line array shape return type.
 *
 * @return array{
 *     label: string,
 *     badge: array{
 *         color: string,
 *     },
 * }|false
 */
function test_multiline_shape_return_type() {
}

/**
 * Tests types PHPStan's parser rejects, which keep phpDocumentor's reading.
 *
 * @param array<int $x The arrow->prop blah
 * @return Generator<int A generator of things>
 */
function test_invalid_type_fallback( $x ) {
}

/**
 * Tests hash notation shapes from WordPress core.
 *
 * @param array $args {
 *     Optional. Arguments.
 *
 *     @type string                  $label Label.
 *     @type array<int               $broken A type PHPStan's parser rejects.
 *     @type array<string, int|null> ...$0 {
 *         Each entry, wrapped onto
 *         a continuation line.
 *
 *         @type string $0 The first element.
 *         @type int    $1 The second element.
 *     }
 *     @type array {
 *         An entry with no name.
 *
 *         @type bool $flag A flag.
 *     },
 * }
 * @return array|false $results {
 *     The results, or false.
 *
 *     @type int $ID Post ID.
 * }
 */
function test_hash_notation( $args ) {
}

/**
 * Tests hash notation whose braces do not balance.
 *
 * @param array $args {
 *     Arguments.
 *
 *     @type string $label Label.
 */
function test_unbalanced_hash_notation( $args ) {
}

/**
 * This is a class docblock.
 *
 * This is the more wordy description: This is a comment with two *'s at the start,
 * which means that it is a doc comment. Docblock comments are comment blocks used
 * to document code. This one documents the Test_Class class.
 *
 * @since 3.5.2
 */
class Test_Class {

	/**
	 * This is a docblock for a class property.
	 *
	 * ```php interactive setup-blueprint=file-greeting
	 * <?php
	 * // This property snippet line ends in a period.
	 * @unlink( '/tmp/phpdoc-parser-property' );
	 * require '/wordpress/wp-load.php';
	 * echo docs_file_greeting();
	 * // Outputs: Hello from the file setup
	 * ```
	 *
	 * @since 3.0.0
	 *
	 * @var string
	 */
	public $a_string;

	/**
	 * This is a method docblock.
	 *
	 * @since 4.5.0
	 *
	 * @param mixed $var A parameter.
	 * @param array $arr Another parameter.
	 *
	 * @return mixed The first param.
	 */
	public function test_method( $var, $arr ) {
		return $var;
	}

	/**
	 * This is a method docblock with a code snippet.
	 *
	 * Use this example:
	 *
	 * ```setup-blueprint
	 * {
	 *   "steps": [
	 *     {
	 *       "step": "writeFile",
	 *       "path": "/wordpress/wp-content/mu-plugins/docs-fixture.php",
	 *       "data": "<?php\nfunction docs_fixture_greeting() {\n\treturn 'Hello from a method';\n}\n"
	 *     }
	 *   ]
	 * }
	 * ```
	 *
	 * ```php interactive
	 * <?php
	 * require '/wordpress/wp-load.php';
	 * echo docs_fixture_greeting();
	 * // Outputs:
	 * // Hello from a method
	 * ```
	 *
	 * @since 6.9.0
	 */
	public function test_method_with_code_snippet() {
	}

	/**
	 * This is a method docblock with a reused setup Blueprint.
	 *
	 * ```setup-blueprint shared-greeting
	 * {
	 *   "steps": [
	 *     {
	 *       "step": "writeFile",
	 *       "path": "/wordpress/wp-content/mu-plugins/shared-greeting.php",
	 *       "data": "<?php\nfunction docs_shared_greeting( $name ) {\n\treturn \"Hello, $name\";\n}\n"
	 *     }
	 *   ]
	 * }
	 * ```
	 *
	 * ```php interactive setup-blueprint=shared-greeting
	 * <?php
	 * require '/wordpress/wp-load.php';
	 * echo docs_shared_greeting( 'first' );
	 * // Outputs: Hello, first
	 * ```
	 *
	 * ```php interactive setup-blueprint=shared-greeting
	 * <?php
	 * require '/wordpress/wp-load.php';
	 * echo docs_shared_greeting( 'second' );
	 * // Outputs: Hello, second
	 * ```
	 *
	 * @since 6.9.0
	 */
	public function test_method_with_reused_setup_blueprint() {
	}

	/**
	 * This is a method docblock with a file-level setup Blueprint.
	 *
	 * ```php interactive setup-blueprint=file-greeting
	 * <?php
	 * require '/wordpress/wp-load.php';
	 * echo docs_file_greeting();
	 * // Outputs: Hello from the file setup
	 * ```
	 *
	 * @since 6.9.0
	 */
	public function test_method_with_file_setup_blueprint() {
	}
}

/**
 * A filter.
 */
$var = apply_filters( 'test_filter', $var );

/**
 * A reference array filter.
 */
$var = apply_filters_ref_array( 'test_ref_array_filter', array( &$var ) );

/**
 * A test action.
 *
 * ```php interactive setup-blueprint=file-greeting
 * <?php
 * require '/wordpress/wp-load.php';
 * echo docs_file_greeting();
 * // Outputs: Hello from the file setup
 * ```
 *
 * @since 3.7.0
 *
 * @param WP_Post $post Post object.
 */
do_action( 'test_action', $post );

do_action( 'undocumented_hook' );

/**
 * A reference array action.
 */
do_action_ref_array( 'test_ref_array_action', array( &$var ) );
