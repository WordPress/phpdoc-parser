# Contributing

Thank you for your interest in contributing to WP Parser!

## Setup

This project uses wp-env to provide a containerised test environment. You should manage the environement and run the tests using the npm commands.

```bash
# Prerequisites: Node.js 20+, Docker Desktop
git clone https://github.com/your-username/phpdoc-parser.git
cd phpdoc-parser
npm install
npm run setup  # First time only
npm run test   # Run tests
```

To run the full export to JSON using the WordPress core files in the wp-env container:

```
npm run export
```

## Stop containers when done

```
npm run wp-env stop
```

## Architecture

```
lib/
├── class-file-reflector.php  # Main AST parser
├── class-hook-reflector.php  # WordPress hooks
├── runner.php                # API compatibility
└── class-importer.php        # WordPress posts
```

WP Parser uses PHPParser for AST traversal and phpstan/phpdoc-parser for PHPDoc.
