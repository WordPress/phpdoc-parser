# WP Parser

WP-Parser is the parser for creating the new code reference at [developer.wordpress.org](https://developer.wordpress.org/reference). It parses the inline documentation and produces custom post type entries in WordPress.

We are currently looking for contributors to help us complete the work on the parser.

There is a guide to developing for developer.wordpress.org in the [WordPress documentation handbook](https://make.wordpress.org/docs/handbook/projects/devhub/)

## Requirements
* PHP 5.4+
* [Composer](https://getcomposer.org/)
* [WP CLI](https://wp-cli.org/)

Clone the repository into your WordPress plugins directory:

```bash
git clone https://github.com/WordPress/phpdoc-parser.git
```

After that install the dependencies using composer in the parser directory:

```bash
composer install
```

## Running
Activate the plugin first:

    wp plugin activate phpdoc-parser

In your site's directory:

    wp parser create /path/to/source/code --user=<id|login>

## Known Parser Issues
- The parser will crash if it encounters an invokation of an anonymous function returned by a call to a method/function all on the same line. (ie: $this->getAndExecuteFunc()())
