<?php

namespace Aivec\Plugins\DocParser\Importer;

use phpDocumentor\Reflection\BaseReflector;
use phpDocumentor\Reflection\ClassReflector\MethodReflector;
use phpDocumentor\Reflection\ClassReflector\PropertyReflector;
use phpDocumentor\Reflection\FunctionReflector\ArgumentReflector;
use phpDocumentor\Reflection\ReflectionAbstract;

/**
 * Methods for parsing source code for the importer
 */
class Parser
{
    /**
     * Gets files
     *
     * @param string $root
     * @return array|\WP_Error
     */
    public static function getWpFiles($root) {
        $dirIterator = new \RecursiveDirectoryIterator($root);
        $filterIterator = self::filterWpDirectories($root, $dirIterator);

        if ($filterIterator instanceof \RecursiveCallbackFilterIterator) {
            $iterableFiles = new \RecursiveIteratorIterator($filterIterator);
        } else {
            $iterableFiles = new \RecursiveIteratorIterator($dirIterator);
        }

        $files = [];

        try {
            foreach ($iterableFiles as $file) {
                if ('php' !== $file->getExtension()) {
                    continue;
                }

                /*
                 * Whether to exclude a file for parsing.
                 */
                if (!apply_filters('wp_parser_pre_get_wp_file', true, $file->getPathname(), $root)) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        } catch (\UnexpectedValueException $exc) {
            return new \WP_Error(
                'unexpected_value_exception',
                sprintf('Directory [%s] contained a directory we can not recurse into', $root)
            );
        }

        return $files;
    }

    /**
     * Filter the directories to parse.
     *
     * @param string $root        Root dir.
     * @param object $dirIterator RecursiveDirectoryIterator.
     * @return object|false RecursiveCallbackFilterIterator or false.
     */
    public static function filterWpDirectories($root, $dirIterator) {
        $root = trailingslashit($root);

        /*
         * Filter directories found in the root directory.
         *
         * For example: 'vendor', 'tests', 'specific/directory'.
         *
         * @param unknown $exclude Array with directories to skip parsing. Default empty array().
         * @param unknown $root    Root directory to parse.
         */
        $exclude = apply_filters('wp_parser_exclude_directories', [], $root);

        /*
         * Whether to exlude directories if found in a subdirectory.
         *
         * @param unknown $strict. Exclude subdirectories. Default false.
         * @param unknown $root    Root directory to parse.
         */
        $strict = apply_filters('wp_parser_exclude_directories_strict', false, $root);

        if (!$exclude) {
            return false;
        }

        $filter = new \RecursiveCallbackFilterIterator($dirIterator, function ($current) use ($root, $exclude, $strict) {
            if ($current->isFile() && ('php' !== $current->getExtension())) {
                return false;
            }

            if (!$current->isDir()) {
                return true;
            }

            // Exclude directories strict.
            $dir_name = $current->getFilename();
            if ($strict && in_array($dir_name, $exclude)) {
                return false;
            }

            // Exclude directories in the root directory.
            $current_path = $current->getPathname();
            foreach ($exclude as $dir) {
                if (($root . untrailingslashit($dir)) === $current_path) {
                    return false;
                }
            }

            return true;
        });

        return $filter;
    }

    /**
     * Parses files
     *
     * @param array  $files
     * @param string $root
     * @return array
     */
    public static function parseFiles($files, $root) {
        $output = [];

        foreach ($files as $filename) {
            $file = new FileReflector($filename);

            $path = ltrim(substr($filename, strlen($root)), DIRECTORY_SEPARATOR);
            $file->setFilename($path);

            $file->process();

            // TODO proper exporter
            $out = [
                'file' => self::exportDocblock($file),
                'path' => str_replace(DIRECTORY_SEPARATOR, '/', $file->getFilename()),
                'root' => $root,
            ];

            if (!empty($file->uses)) {
                $out['uses'] = self::exportUses($file->uses);
            }

            foreach ($file->getIncludes() as $include) {
                $out['includes'][] = [
                    'name' => $include->getName(),
                    'line' => $include->getLineNumber(),
                    'type' => $include->getType(),
                ];
            }

            foreach ($file->getConstants() as $constant) {
                $out['constants'][] = [
                    'name' => $constant->getShortName(),
                    'line' => $constant->getLineNumber(),
                    'value' => $constant->getValue(),
                ];
            }

            if (!empty($file->uses['hooks'])) {
                $out['hooks'] = self::exportHooks($file->uses['hooks']);
            }

            foreach ($file->getFunctions() as $function) {
                $func = [
                    'name' => $function->getShortName(),
                    'namespace' => $function->getNamespace(),
                    'aliases' => $function->getNamespaceAliases(),
                    'line' => $function->getLineNumber(),
                    'end_line' => $function->getNode()->getAttribute('endLine'),
                    'arguments' => self::exportArguments($function->getArguments()),
                    'doc' => self::exportDocblock($function),
                    'hooks' => [],
                ];

                if (!empty($function->uses)) {
                    $func['uses'] = self::exportUses($function->uses);

                    if (!empty($function->uses['hooks'])) {
                        $func['hooks'] = self::exportHooks($function->uses['hooks']);
                    }
                }

                $out['functions'][] = $func;
            }

            foreach ($file->getClasses() as $class) {
                $class_data = [
                    'name' => $class->getShortName(),
                    'namespace' => $class->getNamespace(),
                    'line' => $class->getLineNumber(),
                    'end_line' => $class->getNode()->getAttribute('endLine'),
                    'final' => $class->isFinal(),
                    'abstract' => $class->isAbstract(),
                    'extends' => $class->getParentClass(),
                    'implements' => $class->getInterfaces(),
                    'properties' => self::exportProperties($class->getProperties()),
                    'methods' => self::exportMethods($class->getMethods()),
                    'doc' => self::exportDocblock($class),
                ];

                $out['classes'][] = $class_data;
            }

            $output[] = $out;
        }

        return $output;
    }

    /**
     * Fixes newline handling in parsed text.
     *
     * DocBlock lines, particularly for descriptions, generally adhere to a given character width. For sentences and
     * paragraphs that exceed that width, what is intended as a manual soft wrap (via line break) is used to ensure
     * on-screen/in-file legibility of that text. These line breaks are retained by phpDocumentor. However, consumers
     * of this parsed data may believe the line breaks to be intentional and may display the text as such.
     *
     * This function fixes text by merging consecutive lines of text into a single line. A special exception is made
     * for text appearing in `<code>` and `<pre>` tags, as newlines appearing in those tags are always intentional.
     *
     * @param string $text
     * @return string
     */
    public static function fixNewlines($text) {
        // Non-naturally occurring string to use as temporary replacement.
        $replacement_string = '{{{{{}}}}}';

        // Replace newline characters within 'code' and 'pre' tags with replacement string.
        $text = preg_replace_callback(
            '/(?<=<pre><code>)(.+)(?=<\/code><\/pre>)/s',
            function ($matches) use ($replacement_string) {
                return preg_replace('/[\n\r]/', $replacement_string, $matches[1]);
            },
            $text
        );

        // Merge consecutive non-blank lines together by replacing the newlines with a space.
        $text = preg_replace(
            "/[\n\r](?!\s*[\n\r])/m",
            ' ',
            $text
        );

        // Restore newline characters into code blocks.
        $text = str_replace($replacement_string, "\n", $text);

        return $text;
    }

    /**
     * Exports doc block
     *
     * @param BaseReflector|ReflectionAbstract $element
     * @return array
     */
    public static function exportDocblock($element) {
        $docblock = $element->getDocBlock();
        if (!$docblock) {
            return [
                'description' => '',
                'long_description' => '',
                'tags' => [],
            ];
        }

        $output = [
            'description' => preg_replace('/[\n\r]+/', ' ', $docblock->getShortDescription()),
            'long_description' => self::fixNewlines($docblock->getLongDescription()->getFormattedContents()),
            'tags' => [],
        ];

        foreach ($docblock->getTags() as $tag) {
            $tag_data = [
                'name' => $tag->getName(),
                'content' => preg_replace('/[\n\r]+/', ' ', self::formatDescription($tag->getDescription())),
            ];
            if (method_exists($tag, 'getTypes')) {
                $tag_data['types'] = $tag->getTypes();
            }
            if (method_exists($tag, 'getLink')) {
                $tag_data['link'] = $tag->getLink();
            }
            if (method_exists($tag, 'getVariableName')) {
                $tag_data['variable'] = $tag->getVariableName();
            }
            if (method_exists($tag, 'getReference')) {
                $tag_data['refers'] = $tag->getReference();
            }
            if (method_exists($tag, 'getVersion')) {
                // Version string.
                $version = $tag->getVersion();
                if (!empty($version)) {
                    $tag_data['content'] = $version;
                }
                // Description string.
                if (method_exists($tag, 'getDescription')) {
                    $description = preg_replace('/[\n\r]+/', ' ', self::formatDescription($tag->getDescription()));
                    if (!empty($description)) {
                        $tag_data['description'] = $description;
                    }
                }
            }
            $output['tags'][] = $tag_data;
        }

        return $output;
    }

    /**
     * Exports hooks
     *
     * @param HookReflector[] $hooks
     * @return array
     */
    public static function exportHooks(array $hooks) {
        $out = [];

        foreach ($hooks as $hook) {
            $out[] = [
                'name' => $hook->getName(),
                'line' => $hook->getLineNumber(),
                'end_line' => $hook->getNode()->getAttribute('endLine'),
                'type' => $hook->getType(),
                'arguments' => $hook->getArgs(),
                'doc' => self::exportDocblock($hook),
            ];
        }

        return $out;
    }

    /**
     * Exports arguments
     *
     * @param ArgumentReflector[] $arguments
     * @return array
     */
    public static function exportArguments(array $arguments) {
        $output = [];

        foreach ($arguments as $argument) {
            $output[] = [
                'name' => $argument->getName(),
                'default' => $argument->getDefault(),
                'type' => $argument->getType(),
            ];
        }

        return $output;
    }

    /**
     * Exports properties
     *
     * @param PropertyReflector[] $properties
     * @return array
     */
    public static function exportProperties(array $properties) {
        $out = [];

        foreach ($properties as $property) {
            $out[] = [
                'name' => $property->getName(),
                'line' => $property->getLineNumber(),
                'end_line' => $property->getNode()->getAttribute('endLine'),
                'default' => $property->getDefault(),
                // 'final' => $property->isFinal(),
                            'static' => $property->isStatic(),
                'visibility' => $property->getVisibility(),
                'doc' => self::exportDocblock($property),
            ];
        }

        return $out;
    }

    /**
     * Exports methods
     *
     * @param MethodReflector[] $methods
     * @return array
     */
    public static function exportMethods(array $methods) {
        $output = [];

        foreach ($methods as $method) {
            $method_data = [
                'name' => $method->getShortName(),
                'namespace' => $method->getNamespace(),
                'aliases' => $method->getNamespaceAliases(),
                'line' => $method->getLineNumber(),
                'end_line' => $method->getNode()->getAttribute('endLine'),
                'final' => $method->isFinal(),
                'abstract' => $method->isAbstract(),
                'static' => $method->isStatic(),
                'visibility' => $method->getVisibility(),
                'arguments' => self::exportArguments($method->getArguments()),
                'doc' => self::exportDocblock($method),
            ];

            if (!empty($method->uses)) {
                $method_data['uses'] = self::exportUses($method->uses);

                if (!empty($method->uses['hooks'])) {
                    $method_data['hooks'] = self::exportHooks($method->uses['hooks']);
                }
            }

            $output[] = $method_data;
        }

        return $output;
    }

    /**
     * Export the list of elements used by a file or structure.
     *
     * @param array $uses {
     *     @type FunctionCallReflector[] $functions The functions called.
     * }
     * @return array
     */
    public static function exportUses(array $uses) {
        $out = [];

        // Ignore hooks here, they are exported separately.
        unset($uses['hooks']);

        foreach ($uses as $type => $used_elements) {
            /*
             * @var MethodReflector|FunctionReflector $element
             */
            foreach ($used_elements as $element) {
                $name = $element->getName();

                switch ($type) {
                    case 'methods':
                        $out[$type][] = [
                            'name' => $name[1],
                            'class' => $name[0],
                            'static' => $element->isStatic(),
                            'line' => $element->getLineNumber(),
                            'end_line' => $element->getNode()->getAttribute('endLine'),
                        ];
                        break;

                    default:
                    case 'functions':
                        $out[$type][] = [
                            'name' => $name,
                            'line' => $element->getLineNumber(),
                            'end_line' => $element->getNode()->getAttribute('endLine'),
                        ];

                        if (
                            '_deprecated_file' === $name
                            || '_deprecated_function' === $name
                            || '_deprecated_argument' === $name
                            || '_deprecated_hook' === $name
                        ) {
                            $arguments = $element->getNode()->args;

                            $out[$type][0]['deprecation_version'] = $arguments[1]->value->value;
                        }

                        break;
                }
            }
        }

        return $out;
    }

    /**
     * Format the given description with Markdown.
     *
     * @param string $description Description.
     * @return string Description as Markdown if the Parsedown class exists, otherwise return
     *                the given description text.
     */
    public static function formatDescription($description) {
        if (class_exists('Parsedown')) {
            $parsedown = \Parsedown::instance();
            $description = $parsedown->line($description);
        }
        return $description;
    }
}
