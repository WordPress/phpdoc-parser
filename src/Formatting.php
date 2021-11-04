<?php

namespace Aivec\Plugins\DocParser;

/**
 * Utility methods for handling formatting of parsed content and
 * auto-adding link references
 */
class Formatting
{
    /**
     * Initializes class
     *
     * @return void
     */
    public static function init() {
        add_action('init', [get_class(), 'doInit']);
    }

    /**
     * Handles adding/removing hooks to perform formatting as needed.
     *
     * @return void
     */
    public static function doInit() {
        // NOTE: This filtering is temporarily disabled and then restored in
        // reference/template-explanation.php
        add_filter('the_content', [get_class(), 'fixUnintendedMarkdown'], 1);
        add_filter('the_content', [get_class(), 'makeDoclinkClickable'], 10, 5);

        add_filter('the_excerpt', [get_class(), 'removeInlineInternal']);
        add_filter('the_content', [get_class(), 'removeInlineInternal']);

        add_filter('the_excerpt', [get_class(), 'autolinkReferences'], 11);
        add_filter('the_content', [get_class(), 'autolinkReferences'], 11);

        add_filter('avcapps-parameter-type', [get_class(), 'autolinkReferences']);

        add_filter('avcpdp-format-description', [get_class(), 'autolinkReferences']);
        add_filter('avcpdp-format-description', [get_class(), 'fixParamHashFormatting'], 9);
        add_filter('avcpdp-format-description', [get_class(), 'fixParamDescriptionHtmlAsCode']);
        add_filter('avcpdp-format-description', [get_class(), 'convertListsToMarkup']);

        add_filter('avcpdp-format-hash-param-description', [get_class(), 'autolinkReferences']);
        add_filter('avcpdp-format-hash-param-description', [get_class(), 'fixParamDescriptionParsedownBug']);

        add_filter('avcapps-function-return-type', [get_class(), 'autolinkReferences'], 10, 2);

        add_filter('syntaxhighlighter_htmlresult', [get_class(), 'fixCodeEntityEncoding'], 20);
    }

    /**
     * Fixes bug in (or at least in using) SyntaxHighlighter code shortcodes that
     * causes double-encoding of `>` character.
     *
     * @param string $content The text being handled as code.
     * @return string
     */
    public static function fixCodeEntityEncoding($content) {
        return str_replace('&amp;gt;', '&gt;', $content);
    }

    /**
     * Prevents display of the inline use of {@internal}} as it is not meant to be shown.
     *
     * @param string      $content   The post content.
     * @param null|string $post_type Optional. The post type. Default null.
     * @return string
     */
    public static function removeInlineInternal($content, $post_type = null) {
        // Only attempt a change for a parsed post type with an @internal reference in the text.
        if (avcpdp_is_parsed_post_type($post_type) && false !== strpos($content, '{@internal ')) {
            $content = preg_replace('/\{@internal (.+)\}\}/', '', $content);
        }

        return $content;
    }

    /**
     * Makes phpDoc @see and @link references clickable.
     *
     * Handles these six different types of links:
     *
     * - {@link https://en.wikipedia.org/wiki/ISO_8601}
     * - {@see WP_Rewrite::$index}
     * - {@see WP_Query::query()}
     * - {@see esc_attr()}
     * - {@see 'pre_get_search_form'}
     * - {@link https://codex.wordpress.org/The_Loop Use new WordPress Loop}
     *
     * Note: Though @see and @link are semantically different in meaning, that isn't always
     * the case with use so this function handles them identically.
     *
     * @param string $content The content.
     * @return string
     */
    public static function makeDoclinkClickable($content) {
        // Nothing to change unless a @link or @see reference is in the text.
        if (false === strpos($content, '{@link ') && false === strpos($content, '{@see ')) {
            return $content;
        }

        return preg_replace_callback(
            '/\{@(?:link|see) ([^\}]+)\}/',
            function ($matches) {
                $link = $matches[1];

                // We may have encoded a link, so unencode if so.
                // (This would never occur natually.)
                if (0 === strpos($link, '&lt;a ')) {
                    $link = html_entity_decode($link);
                }

                // Undo links made clickable during initial parsing
                if (0 === strpos($link, '<a ')) {
                    if (preg_match('/^<a .*href=[\'\"]([^\'\"]+)[\'\"]>(.*)<\/a>(.*)$/', $link, $parts)) {
                        $link = $parts[1];
                        if ($parts[3]) {
                            $link .= ' ' . $parts[3];
                        }
                    }
                }

                // Link to an external resource.
                if (0 === strpos($link, 'http')) {
                    $parts = explode(' ', $link, 2);

                    // Link without linked text: {@link https://en.wikipedia.org/wiki/ISO_8601}
                    if (1 === count($parts)) {
                        $url = $text = $link;
                    } else {
                        // Link with linked text: {@link https://codex.wordpress.org/The_Loop Use new WordPress Loop}
                        $url = $parts[0];
                        $text = $parts[1];
                    }

                    $link = self::generateLink($url, $text);
                } else {
                    // Link to an internal resource.
                    $link = self::linkInternalElement($link);
                }

                return $link;
            },
            $content
        );
    }

    /**
     * Parses and links an internal element if a valid element is found.
     *
     * @param string $link Element string.
     * @return string HTML link markup if a valid element was found.
     */
    public static function linkInternalElement($link) {
        $url = '';

        // Exceptions for externally-linked elements.
        $exceptions = [
            'error_log()' => 'https://secure.php.net/manual/en/function.error-log.php',
        ];

        // Link exceptions that should actually point to external resources.
        if (!empty($exceptions[$link])) {
            $url = $exceptions[$link];
        // Link to class variable: {@see WP_Rewrite::$index}
        } elseif (false !== strpos($link, '::$')) {
            // Nothing to link to currently.
        } elseif (false !== strpos($link, '::')) {
            // Link to class method: {@see \Namespace\Classname::someMethod()}
            $post = self::getPostFromReference($link, 'wp-parser-method');
            if ($post !== null) {
                $url = get_permalink($post->ID);
            }
        } elseif (1 === preg_match('/^(?:\'|(?:&#8216;))([\$\w\-&;]+)(?:\'|(?:&#8217;))$/', $link, $hook)) {
            // Link to hook: {@see 'pre_get_search_form'}
            if (!empty($hook[1])) {
                $post = self::getPostFromReference($hook[1], 'wp-parser-hook');
                if ($post !== null) {
                    $url = get_permalink($post->ID);
                }
            }
        } elseif (1 === preg_match('/\\\?(?:[A-Z]+[A-Za-z]*)+(?:\\\{1}[A-Z]+[A-Za-z]*)+/', $link)) {
            // Link to a PSR-4 class: {@see \Namespace\Classname}
            $post = self::getPostFromReference($link, 'wp-parser-class');
            if ($post !== null) {
                $url = get_permalink($post->ID);
            }
        } else {
            // Link to function: {@see esc_attr()}
            $post = self::getPostFromReference($link, 'wp-parser-function');
            if ($post !== null) {
                $url = get_permalink($post->ID);
            }
        }

        if ($url) {
            $link = self::generateLink($url, $link);
        }
        return $link;
    }

    /**
     * Generates a link given a URL and text.
     *
     * @param string $url  The URL, for the link's href attribute.
     * @param string $text The text content of the link.
     * @return string The HTML for the link.
     */
    public static function generateLink($url, $text) {
        /*
         * Filters the HTML attributes applied to a link's anchor element.
         *
         * @param array  $attrs The HTML attributes applied to the link's anchor element.
         * @param string $url   The URL for the link.
         */
        $attrs = (array)apply_filters('avcpdp-format-link-attributes', ['href' => $url], $url);

        // Make sure the filter didn't completely remove the href attribute.
        if (empty($attrs['href'])) {
            $attrs['href'] = $url;
        }

        $attributes = '';
        foreach ($attrs as $name => $value) {
            $value = 'href' === $name ? esc_url($value) : esc_attr($value);
            $attributes .= sprintf(' %s="%s"', esc_attr($name), $value);
        }

        return sprintf('<a%s>%s</a>', $attributes, esc_html($text));
    }

    /**
     * Fixes unintended markup generated by Markdown during parsing.
     *
     * The parser interprets underscores surrounding text as Markdown indicating
     * italics. That is never the intention, so undo it.
     *
     * @param string      $content   The post content.
     * @param null|string $post_type Optional. The post type. Default null.
     * @return string
     */
    public static function fixUnintendedMarkdown($content, $post_type = null) {
        // Only apply to parsed content that have the em tag.
        if (avcpdp_is_parsed_post_type($post_type) && false !== strpos($content, '<em>')) {
            $content = preg_replace_callback(
                '/([^\s])<em>(.+)<\/em>/U',
                function ($matches) {
                    return $matches[1] . '_' . $matches[2] . '_';
                },
                $content
            );
        }

        return $content;
    }

    /**
     * Handles formatting of the parameter description.
     *
     * @param string $text The parameter description.
     * @return string
     */
    public static function formatParamDescription($text) {
        // Undo parser's Markdown conversion of '*' to `<em>` and `</em>`.
        // In pretty much all cases, the docs mean literal '*' and never emphasis.
        // ....... The above is from wporg-developer ..........
        // Replace em tags with the italics i tag
        $text = str_replace('<em>', '<i>', $text);
        $text = str_replace('</em>', '</i>', $text);

        // Undo parser's Markdown conversion of '__' to `<strong>` and `</strong>`.
        // $text = str_replace( array( '<strong>', '</strong>' ), '__', $text );
        // Encode all htmlentities (but don't double-encode).
        $text = htmlentities($text, ENT_COMPAT | ENT_HTML401, 'UTF-8', false);

        // Simple allowable tags that should get unencoded.
        // Note: This precludes them from being able to be used in an encoded fashion
        // within a parameter description.
        $allowable_tags = ['code', 'em', 'strong', 'i'];
        foreach ($allowable_tags as $tag) {
            $text = str_replace(["&lt;{$tag}&gt;", "&lt;/{$tag}&gt;"], ["<{$tag}>", "</{$tag}>"], $text);
        }

        // Convert any @link or @see to actual link.
        $text = self::makeDoclinkClickable($text);
        $text = self::autolinkReferences($text);
        // $text = self::fixParamHashFormatting($text);
        $text = self::fixParamDescriptionHtmlAsCode($text);
        $text = self::convertListsToMarkup($text);

        return $text;
    }

    /**
     * Returns the post given a function/method/class/hook raw reference string
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $ref
     * @param string $parser_type
     * @return \WP_Post|null
     */
    public static function getPostFromReference($ref, $parser_type) {
        $postname = sanitize_title(str_replace('\\', '-', str_replace('::', '-', $ref)));
        $posts = get_posts([
            'name' => $postname,
            'post_type' => $parser_type,
        ]);
        if (empty($posts)) {
            return null;
        }

        return $posts[0];
    }

    /**
     * Strips the namespace from a function/method/class reference
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $ref
     * @return string
     */
    public static function stripNamespaceFromReference($ref) {
        $parts = explode('\\', $ref);

        return $parts[count($parts) - 1];
    }

    /**
     * Automatically detects inline references to parsed resources and links to them.
     *
     * Examples:
     * - Functions: get_item()
     * - Classes:   My\PSR\Four\Class, \TopLevelClass
     * - Methods:   My\PSR\Four\Class::isSingle(), \My\PSR\Four\Class::isSingle()
     *
     * Note: currently there is not a reliable way to infer references to hooks. Recommend
     * using the {@}see 'hook_name'} notation as used in the inline docs.
     *
     * @param string $text The text.
     * @param bool   $strip_namespaces Whether to strip PSR-4 namespaces from the link text
     * @return string
     */
    public static function autolinkReferences($text, $strip_namespaces = true) {
        // Temporary: Don't do anything if the text is a hash notation string.
        if ($text && '{' === $text[0]) {
            return $text;
        }

        $r = '';
        $textarr = preg_split('/(<[^<>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE); // split out HTML tags
        $nested_code_pre = 0; // Keep track of how many levels link is nested inside <pre> or <code>
        foreach ($textarr as $piece) {
            if (preg_match('|^<code[\s>]|i', $piece) || preg_match('|^<pre[\s>]|i', $piece) || preg_match('|^<script[\s>]|i', $piece) || preg_match('|^<style[\s>]|i', $piece)) {
                $nested_code_pre++;
            } elseif ($nested_code_pre && ('</code>' === strtolower($piece) || '</pre>' === strtolower($piece) || '</script>' === strtolower($piece) || '</style>' === strtolower($piece))) {
                $nested_code_pre--;
            }

            if ($nested_code_pre || empty($piece) || ($piece[0] === '<' && !preg_match('|^<\s*[\w]{1,20}+://|', $piece))) {
                $r .= $piece;
                continue;
            }

            // Long strings might contain expensive edge cases ...
            if (10000 < strlen($piece)) {
                // ... break it up
                foreach (_split_str_by_whitespace($piece, 2100) as $chunk) { // 2100: Extra room for scheme and leading and trailing paretheses
                    if (2101 < strlen($chunk)) {
                        $r .= $chunk; // Too big, no whitespace: bail.
                    } else {
                        $r .= make_clickable($chunk);
                    }
                }
            } else {
                /*
                 * Everthing outside of this conditional block was copied from core's
                 *`make_clickable()`.
                 */

                $content = " $piece "; // Pad with whitespace to simplify the regexes

                // Only if the text contains something that might be a function.
                if (false !== strpos($content, '()')) {
                    // Detect references to class methods, e.g. MyNamespace\MyClass::query()
                    // or functions, e.g. get_item().
                    $content = preg_replace_callback(
                        '~
							(?!<.*?)       # Non-capturing check to ensure not matching what looks like the inside of an HTML tag.
							(              # 1: The full method or function name.
                                (([a-zA-Z0-9_\\\]+)::)? # 2: The PSR-4 class prefix, if a method reference.
                                ([a-zA-Z0-9_\\\]+)      # 3: The method or function name.
							)
							\(\)           # The () that signifies either a method or function.
							(?![^<>]*?>)   # Non-capturing check to ensure not matching what looks like the inside of an HTML tag.
						~x',
                        function ($matches) use ($strip_namespaces) {
                            // Reference to a class method.
                            if ($matches[2]) {
                                // Only link actually parsed methods.
                                $post = self::getPostFromReference($matches[1], 'wp-parser-method');
                                if ($post !== null) {
                                    return sprintf(
                                        '<a href="%s">%s</a>',
                                        get_permalink($post->ID),
                                        $strip_namespaces ? self::stripNamespaceFromReference($matches[0]) : $matches[0]
                                    );
                                }
                            // Reference to a function.
                            } else {
                                // Only link actually parsed functions.
                                $post = self::getPostFromReference($matches[1], 'wp-parser-function');
                                if ($post !== null) {
                                    return sprintf(
                                        '<a href="%s">%s</a>',
                                        get_permalink($post->ID),
                                        $strip_namespaces ? self::stripNamespaceFromReference($matches[0]) : $matches[0]
                                    );
                                }
                            }

                            // It's not a reference to an actual thing, so restore original text.
                            return $matches[0];
                        },
                        $content
                    );
                }

                // Detect references to classes
                $content = preg_replace_callback(
                    // Resolves PSR-4 class names
                    // If referencing a top level class (ie: MyClass), the class name MUST be prefixed with
                    // a backslash (ie: \MyClass).
                    // For all other non top level classes (ie: \MyNamespace\MyClass), the leading backslash
                    // is optional (ie: MyNamespace\MyClass).
                    //
                    // Note that WordPress style class names, such as WP_Post, are not resolved. Class names
                    // MUST be PSR-4 compliant.
                    '~'
                        . '(?<!/)'
                        . '('                 // Primary match grouping
                            . '\\\?(?:[A-Z]+[A-Za-z]*)+(?:\\\{1}[A-Z]+[A-Za-z]*)+'  // Resolves PSR-4 namespaces.
                        . ')'                 // End primary match grouping
                        . '\b'                // Word boundary
                        . '(?!([<:]|"|\'>))'  // Does not appear within a tag
                    . '~',
                    function ($matches) use ($strip_namespaces) {
                        // Only link actually parsed classes.
                        $post = self::getPostFromReference($matches[0], 'wp-parser-class');
                        if ($post !== null) {
                            return sprintf(
                                '<a href="%s">%s</a>',
                                get_permalink($post->ID),
                                $strip_namespaces ? self::stripNamespaceFromReference($matches[0]) : $matches[0]
                            );
                        }

                        // Not a class reference, so put the original reference back in.
                        return $matches[0];
                    },
                    $content
                );

                // Maybelater: Detect references to hooks, Currently not deemed reliably possible.
                $content = substr($content, 1, -1); // Remove our whitespace padding.
                $r .= $content;
            } // end else
        } // end foreach

        // Cleanup of accidental links within links
        return preg_replace('#(<a([ \r\n\t]+[^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i', '$1$3</a>', $r);
    }

    /**
     * Converts simple Markdown-like lists into list markup.
     *
     * Necessary in cases like hash param descriptions which don't see Markdown
     * list processing during parsing.
     *
     * Recognizes lists where list items are denoted with an asterisk or dash.
     *
     * Does not handle nesting of lists.
     *
     * @param string $text The text to process for lists.
     * @return string
     */
    public static function convertListsToMarkup($text) {
        $inline_list = false;
        $li = '<br /> * ';

        // Convert asterisks to a list.
        // Example: https://developer.wordpress.org/reference/functions/add_menu_page/
        if (false !== strpos($text, ' * ')) {
            // Display as simple plaintext list.
            $text = str_replace(' * ', "\n" . $li, $text);
            $inline_list = true;
        }

        // Convert dashes to a list.
        // Example: https://developer.wordpress.org/reference/classes/wp_term_query/__construct/
        // Example: https://developer.wordpress.org/reference/hooks/password_change_email/
        if (false !== strpos($text, ' - ')) {
            // Display as simple plaintext list.
            $text = str_replace(' - ', "\n" . $li, $text);
            $inline_list = true;
        }

        // If list detected.
        if ($inline_list) {
            // Replace first item, ensuring the opening 'ul' tag is prepended.
            $text = preg_replace('~^' . preg_quote($li) . '(.+)$~mU', "<ul><li>\$1</li>\n", $text, 1);
            // Wrap subsequent list items in 'li' tags.
            $text = preg_replace('~^' . preg_quote($li) . '(.+)$~mU', "<li>\$1</li>\n", $text);
            $text = trim($text);

            // Close the list if it hasn't been closed before start of next hash parameter.
            // $text = preg_replace( '~(</li>)(\s+</li>)~smU', '$1</ul>$2', $text );
            $text = preg_replace('~(</li>)(\s*</li>)~smU', '$1</ul>$2', $text);

            // Close the list if it hasn't been closed and it's the end of the description.
            if ('</li>' === substr(trim($text), -5)) {
                $text .= '</ul>';
            }
        }

        return $text;
    }

    /**
     * Returns hierarchical array for a param hash array type
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $text
     * @param array  $pieces
     * @param string $key
     * @return array
     */
    public static function getParamHashMapRecursive($text, $pieces = [], $key = '') {
        if (!$text || '{' != $text[0]) {
            return [];
        }

        $index = 0;
        $noprocessrange = 0;
        $text = trim(substr($text, 1, -1));
        $text = str_replace('@type', "\n@type", $text);
        $parts = explode("\n", $text);

        foreach ($parts as $part) {
            if ($index < $noprocessrange) {
                $index++;
                continue;
            }

            $part = preg_replace('/\s+/', ' ', $part);
            // extra spaces ensure we'll always have 4 items.
            list($wordtype, $type, $name, $rawdescription) = explode(' ', $part . '    ', 4);
            $description = trim($rawdescription);

            if ('@type' != $wordtype) {
                $pieces['description'] = [
                    'name' => $key,
                    'type' => 'array',
                    'wordtype' => null,
                    'value' => $part,
                ];
            } else {
                $pieces[$name] = [
                    'name' => $name,
                    'type' => $type,
                    'wordtype' => $wordtype,
                    'value' => $description,
                ];
            }

            $islinkbrace = false;
            preg_match('/({.*(?:@see|@link).+?})(.*)/', $description, $matches);
            if (!empty($matches)) {
                if (trim($matches[count($matches) - 1]) === '') {
                    // If there are no braces after the @see|@link closing brace,
                    // the closing brace is a link brace, not a hash param closing brace
                    $islinkbrace = true;
                }
            }

            // Handle nested hashes.
            if (($description && '{' === $description[0]) || '{' === $name) {
                $deschashpieces = explode('{', $rawdescription, 2);
                $nestedtext = join('    ', array_slice($parts, $index + 1));
                $nestedtext = '{' . $deschashpieces[1] . '    ' . $nestedtext;
                $pieces[$name] = self::getParamHashMapRecursive($nestedtext, [], $name);
                $numprocessed = self::countNestedHashParamLeafNodes($pieces[$name]);
                $noprocessrange = $index + $numprocessed;
            // Sometimes nested hashes contain links (eg. {@see 'hook_name'}) so we
            // need to make sure that if the last character is a closing brace it
            // isn't for a link
            } elseif ('}' === substr($description, -1) && !$islinkbrace) {
                $pieces[$name]['value'] = trim(substr($description, 0, -1));
                return $pieces;
            }

            $index++;
        }

        return $pieces;
    }

    /**
     * Recursively counts all leaf nodes of a nested hash param
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param array $na
     * @return int
     */
    public static function countNestedHashParamLeafNodes($na) {
        $num = 0;
        foreach ($na as $el) {
            if (!isset($el['value']) || is_array($el['value'])) {
                $num += self::countNestedHashParamLeafNodes($el);
            } else {
                $num++;
            }
        }

        return $num;
    }

    /**
     * Formats the output of params defined using hash notation.
     *
     * This is a temporary measure until the parser parses the hash notation
     * into component elements that the theme could then handle and style
     * properly.
     *
     * Also, as a stopgap this is going to begin as a barebones hack to simply
     * keep the text looking like one big jumble.
     *
     * @param  string $text The content for the param.
     * @return string
     */
    public static function fixParamHashFormatting($text) {
        // Don't do anything if this isn't a hash notation string.
        if (!$text || '{' != $text[0]) {
            return $text;
        }

        $new_text = '';
        $text = trim(substr($text, 1, -1));
        $text = str_replace('@type', "\n@type", $text);

        $in_list = false;
        $parts = explode("\n", $text);
        foreach ($parts as $part) {
            $part = preg_replace('/\s+/', ' ', $part);
            list( $wordtype, $type, $name, $description ) = explode(' ', $part . '    ', 4); // extra spaces ensure we'll always have 4 items.
            $description = trim($description);

            $tclass = 'ref-arg-type';
            $type = apply_filters('avcpdp_filter_param_hash_type', $type, $text);
            if (strpos($type, '\\') !== false) {
                $type = ltrim($type, '\\');
                $tclass .= ' ref-arg-type--class';
            }

            $description = apply_filters('avcpdp-format-hash-param-description', $description);

            $skip_closing_li = false;

            // Handle nested hashes.
            if (($description && '{' === $description[0]) || '{' === $name) {
                $description = ltrim($description, '{') . '<ul class="ref-params ref-param-hash">';
                $skip_closing_li = true;
            } elseif ('}' === substr($description, -1)) {
                $description = substr($description, 0, -1) . "</li></ul>\n";
            }

            if ('@type' != $wordtype) {
                if ($in_list) {
                    $in_list = false;
                    $new_text .= "</li></ul>\n";
                }

                $new_text .= $part;
            } else {
                if ($in_list) {
                    $new_text .= '<li>';
                } else {
                    $new_text .= '<ul class="ref-params ref-param-hash"><li>';
                    $in_list = true;
                }

                // Normalize argument name.
                if ($name === '{') {
                    // No name is specified, generally indicating an array of arrays.
                    $name = '';
                } else {
                    // The name is defined as a variable, so remove the leading '$'.
                    $name = ltrim($name, '$');
                }
                if ($name) {
                    $new_text .= "<b>'{$name}'</b><br />";
                }
                $new_text .= "<i><span class='{$tclass}'>({$type})</span></i><span class='ref-params__description'>{$description}</span>";
                if (!$skip_closing_li) {
                    $new_text .= '</li>';
                }
                $new_text .= "\n";
            }
        }

        if ($in_list) {
            $new_text .= "</li></ul>\n";
        }

        return $new_text;
    }

    /**
     * Fix Parsedown bug that introduces unbalanced 'code' tags.
     *
     * Under very specific criteria, a bug in the Parsedown package used by the
     * parser causes backtick-to-code-tag conversions to get mishandled, skipping
     * conversion of a backtick and causing subsequent backticks to be converted
     * incorrectly as an open or close 'code' tag (opposite of what it should've
     * been). See referenced tickets for more details.
     *
     * Intended to be a temporary fix until/unless Parsedown is fixed.
     *
     * @see https://meta.trac.wordpress.org/ticket/2900
     * @see https://github.com/erusev/parsedown/pull/515
     * @param string $text
     * @return string
     */
    public static function fixParamDescriptionParsedownBug($text) {
        $fixes = [
            '/`(.+)<code>/' => '<code>$1</code>',
            '/<\/code>(.+)`/' => ' <code>$1</code>',
        ];

        // Determine if code tags look inverted.
        $first_start = strpos($text, '<code>');
        $first_end = strpos($text, '</code>');
        if (false !== $first_start && false !== $first_end && $first_end < $first_start) {
            $fixes['~</code>(.+)<code>~U'] = ' <code>$1</code>';
        }

        $matched = true;

        foreach ($fixes as $regex => $replace) {
            $text = preg_replace($regex, $replace, $text);
        }

        return $text;
    }

    /**
     * Wraps single-quoted HTML within 'code' tags.
     *
     * The HTML should have been denoted with backticks in the original source, in
     * which case it would have been parsed properly, but committers aren't
     * always sticklers for documentation formatting.
     *
     * @param string $text
     * @return string
     */
    public static function fixParamDescriptionHtmlAsCode($text) {
        if (false !== strpos($text, "'&lt;")) {
            $text = preg_replace('/\'(&lt;[^\']+&gt;)\'/', '<code>$1</code>', $text);
        }

        return $text;
    }
}
