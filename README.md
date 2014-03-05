# WP Parser

WP-Parser is the parser for creating the new code reference at [developer.wordpress.org](http://developer.wordpress.org/reference). It parses the inline documentation and produces custom post type entries in WordPress.

We are currently looking for contributors to help us complete the work on the parser.

There is a guide to developing for developer.wordpress.org in the [WordPress documentation handbook](http://make.wordpress.org/docs/handbook/projects/devhub/)

## Requirements
* PHP 5.3+
* [Composer](https://getcomposer.org/)
* [WP CLI](http://wp-cli.org/)

After cloning from Git set up dependencies via:

    composer install --no-dev

## Running
Note: ensure the plugin is enabled first.

In your site's directory:

	$ wp funcref generate-and-import /path/to/source/code --user=<id|login>

## Displaying in your theme
By default, your theme will use the built-in content. This content is generated
on the fly by the `expand_content` function.

To use your own theming instead, simply add the following to
your `functions.php`:

	remove_filter( 'the_content', 'WP_Parser\\expand_content' );
