# WP Parser

WP-Parser is the parser for creating the new code reference at [developer.wordpress.org](https://developer.wordpress.org/reference). It parses the inline documentation and produces custom post type entries in WordPress.

We are currently looking for contributors to help us complete the work on the parser.

There is a guide to developing for developer.wordpress.org in the [WordPress documentation handbook](https://make.wordpress.org/docs/handbook/projects/devhub/)

## Requirements

* **PHP 8.1+**
* **Node.js 20+** and **npm 9+** (for development environment)
* **Docker** (for wp-env testing environment)
* **Composer**

## Quick Start

Clone the repository into your WordPress plugins directory:

```bash
git clone https://github.com/WordPress/phpdoc-parser.git
cd phpdoc-parser
```

After that install the dependencies:

```bash
npm install
npm run setup
```

## Running

In your site's directory:

    wp parser create /path/to/source/code --user=<id|login>
