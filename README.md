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

Unit tests do not cover every shape of real-world documentation, so changes to the parser are also checked against a corpus of WordPress core source. The same corpus is parsed with the parser at two refs, the base branch and the pull request merged into it, and the two JSON exports are diffed. Everything in that diff is a behavior change the pull request makes: every hunk must be either intended and explained, or it is a regression.

`.github/workflows/corpus-diff.yml` runs this on every pull request. The corpus is the PHP of a whole WordPress release at a pinned tag (`.github/corpus-version`): wp-admin, wp-includes, the root files, and the bundled themes and plugins. Pinning keeps diffs reproducible. The job is non-blocking: it uploads the diff as a `corpus.diff` artifact, reports it in the job summary, and posts one comment on the pull request, updated on every push, with the hunk and line counts and the diff itself when it fits in a comment (`tools/corpus-diff-comment.sh` renders it). Pull requests from forks and from Dependabot run with a read-only token, so they get the summary and the artifact only. The head checkout's `tools/export-corpus.php` drives both sides, so tooling changes never masquerade as parser changes. The exports are diffed as emitted: both sides share the corpus, the PHP binary, and the exporter, so the output is already deterministic. `prep-diff.php` is for comparing exports from different environments; its line-number and namespace-prefix erasure and its collection sorting would hide or scatter real changes here.

To run it locally, get the pinned corpus:

```bash
tag="$(cat .github/corpus-version)"
curl -sSfL -o wordpress.zip "https://github.com/WordPress/WordPress/archive/refs/tags/${tag}.zip"
unzip -q wordpress.zip
```

Check out the other side of the comparison and install its dependencies. The exporter runs under plain PHP, without loading WordPress, but it needs a Composer autoloader in each parser root:

```bash
git fetch origin
git worktree add base "$(git merge-base origin/master HEAD)"
composer --working-dir=base install
composer install
```

On a branch this compares against the commit the branch left `master` at. CI runs on the pull request merged into `master`, so it compares against the `master` tip; merge `master` into the branch first to get the same comparison.

Export both sides over the same corpus and diff. `export-corpus.php` takes the parser root and the corpus directory, and writes JSON to stdout:

```bash
export LC_ALL=C
php -d memory_limit=4G tools/export-corpus.php base "WordPress-${tag}" > base.json
php -d memory_limit=4G tools/export-corpus.php . "WordPress-${tag}" > head.json
diff -u base.json head.json > corpus.diff
```

An empty `corpus.diff` means the change has no effect on parser output.

Clean up afterwards:

```bash
git worktree remove base
rm -rf wordpress.zip "WordPress-${tag}" base.json head.json corpus.diff
```
