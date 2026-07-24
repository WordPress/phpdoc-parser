# WP Parser

WP-Parser is the parser for creating the new code reference at [developer.wordpress.org](https://developer.wordpress.org/reference). It parses the inline documentation and produces custom post type entries in WordPress.

We are currently looking for contributors to help us complete the work on the parser.

There is a guide to developing for developer.wordpress.org in the [WordPress documentation handbook](https://make.wordpress.org/docs/handbook/projects/devhub/).

## Requirements
* PHP 7.4+
* [Composer](https://getcomposer.org/)
* [WP CLI](https://wp-cli.org/)

## Development

Clone the repository and set up the development environment:

```bash
git clone https://github.com/WordPress/phpdoc-parser.git
cd phpdoc-parser
npm ci
npm run setup
```

WordPress is available at <http://localhost:8888>.

## Tests

```bash
npm run test:phpunit:setup
npm test
```

If the development environment was set up with `npm run setup`, only `npm test` is needed.

## Running the parser

Activate the plugin first:

```bash
wp plugin activate phpdoc-parser
```

In your site's directory:

```bash
wp parser create /path/to/source/code --user=<id|login>
```
