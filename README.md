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
composer install
npm run setup
```

WordPress is available at <http://localhost:8888>.

## Tests

For a test-only setup:

```bash
composer install
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

## Corpus diff

Unit tests do not cover every shape of real-world documentation, so changes to the parser are also checked against a corpus of WordPress core source. The same corpus is parsed with the parser at two refs — the merge base of a pull request and its head — both JSON outputs are normalized with `prep-diff.php`, and the two are diffed. Everything in that diff is a behavior change the pull request makes: every hunk must be either intended and explained, or it is a regression.

`.github/workflows/corpus-diff.yml` runs this on every pull request. The corpus is `wp-includes` from a pinned WordPress tag (`WP_CORPUS_TAG` in the workflow), so diffs are reproducible. The job is non-blocking: it uploads the diff as a `corpus.diff` artifact and reports the hunk count in the job summary. The head checkout's `tools/export-corpus.php` and `prep-diff.php` drive both sides, so tooling changes never masquerade as parser changes; when `prep-diff.php` itself changes, its effect on normalization shows up in the diff and is reviewed like any other change.

To run it locally, get the pinned corpus:

```bash
curl -sSfL -o wordpress.zip https://github.com/WordPress/WordPress/archive/refs/tags/7.0.4.zip
unzip -q wordpress.zip 'WordPress-7.0.4/wp-includes/*'
```

Check out the other side of the comparison and install its dependencies. The exporter runs under plain PHP — it does not load WordPress — but it needs a Composer autoloader in each parser root:

```bash
git worktree add base "$(git merge-base origin/master HEAD)"
composer --working-dir=base install
composer install
```

Export both sides over the same corpus, normalize, and diff. `export-corpus.php` takes the parser root and the corpus directory, and writes JSON to stdout:

```bash
export LC_ALL=C
php -d memory_limit=4G tools/export-corpus.php base WordPress-7.0.4/wp-includes > base.json
php -d memory_limit=4G tools/export-corpus.php . WordPress-7.0.4/wp-includes > head.json
php -d memory_limit=4G prep-diff.php < base.json > base.norm.json
php -d memory_limit=4G prep-diff.php < head.json > head.norm.json
diff -u base.norm.json head.norm.json > corpus.diff
```

An empty `corpus.diff` means the change has no effect on parser output.
