#!/usr/bin/env bash
#
# Renders the corpus diff as GitHub-flavored Markdown for a pull request
# comment or a job summary.
#
# Usage:
#
#     WP_CORPUS_TAG=7.0.4 BASE_SHA=... HEAD_SHA=... tools/corpus-diff-comment.sh corpus.diff > comment.md
#
# Optional: ARTIFACT_URL (download link for corpus.diff), RUN_URL (fallback
# link), MAX_BYTES (largest body that inlines the diff; GitHub rejects
# comments over 65536 characters, default 60000).
#
# The first line is an HTML comment that marks the output as this tool's, so
# the workflow can find and update its own comment instead of adding one per
# run.

set -euo pipefail

diff_file=$1
max_bytes=${MAX_BYTES:-60000}
link=${ARTIFACT_URL:-${RUN_URL:-}}

marker='<!-- corpus-diff -->'
heading='### Corpus diff'
compared="\`wp-includes@${WP_CORPUS_TAG}\`, parser at \`${BASE_SHA:0:7}\` (base) vs \`${HEAD_SHA:0:7}\` (this PR merged)"

if [ ! -s "$diff_file" ]; then
	printf '%s\n%s\n\n**0 hunks.** No behavior change over %s.\n' "$marker" "$heading" "$compared"
	exit 0
fi

hunks=$(grep -c '^@@' "$diff_file" || true)
added=$(grep -c '^+[^+]' "$diff_file" || true)
removed=$(grep -c '^-[^-]' "$diff_file" || true)
lines=$(wc -l < "$diff_file" | tr -d ' ')
bytes=$(wc -c < "$diff_file" | tr -d ' ')

stat="**${hunks} hunks**, \`+${added}\` \`-${removed}\` lines, over ${compared}. Every hunk must be intended and explained in the PR."
download=''
if [ -n "$link" ]; then
	download=" [Download corpus.diff](${link})."
fi

# A fence must be longer than any backtick run inside the diff.
ticks=$( { grep -o '`\{3,\}' "$diff_file" || true; } | awk '{ if ( length( $0 ) > m ) m = length( $0 ) } END { print m + 0 }')
fence=$(printf '%*s' $(( ticks > 2 ? ticks + 1 : 3 )) '' | tr ' ' '`')

# Everything except the diff itself, to decide whether the diff fits.
frame=$(printf '%s\n%s\n\n%s%s\n\n<details>\n<summary>corpus.diff (%s lines)</summary>\n\n%sdiff\n%s\n</details>\n' \
	"$marker" "$heading" "$stat" "$download" "$lines" "$fence" "$fence")

if [ $(( ${#frame} + bytes )) -gt "$max_bytes" ]; then
	printf '%s\n%s\n\n%s The diff (%s lines, %s bytes) is too large for a comment.%s\n' \
		"$marker" "$heading" "$stat" "$lines" "$bytes" "$download"
	exit 0
fi

printf '%s\n%s\n\n%s%s\n\n<details>\n<summary>corpus.diff (%s lines)</summary>\n\n%sdiff\n' \
	"$marker" "$heading" "$stat" "$download" "$lines" "$fence"
cat "$diff_file"
printf '%s\n</details>\n' "$fence"
