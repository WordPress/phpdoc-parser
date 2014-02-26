# WP Function Reference
## Requirements
* PHP 5.3+
* [WP CLI](http://wp-cli.org/)

## Running
Note: ensure the plugin is enabled first.

In your site's directory:

	$ wp funcref generate-and-import /path/to/source/code --user=<id|login>

## Displaying in your theme
By default, your theme will use the built-in content. This content is generated
on the fly by the `expand_content` function.

To use your own theming instead, simply add the following to
your `functions.php`:

	remove_filter( 'the_content', 'WPFuncRef\\expand_content' );
