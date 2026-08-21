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
# link), MAX_BYTES (largest body to emit; GitHub rejects comments over 65536
# characters, default 60000). A diff that does not fit is cut to the leading
# whole hunks that do, and the comment says so.
#
# The first line is an HTML comment that marks the output as this tool's, so
# the workflow can find and update its own comment instead of adding one per
# run.

set -euo pipefail

# Count bytes, not characters: GitHub's limit is characters, so bytes are safe.
export LC_ALL=C

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

hunk_word=hunks
if [ "$hunks" -eq 1 ]; then
	hunk_word=hunk
fi
stat="**${hunks} ${hunk_word}**, \`+${added}\` \`-${removed}\` lines, over ${compared}. Every hunk must be intended and explained in the PR."
download=''
if [ -n "$link" ]; then
	download=" [Download corpus.diff](${link})."
fi

# A fence must be longer than any backtick run inside the diff.
ticks=$( { grep -o '`\{3,\}' "$diff_file" || true; } | awk '{ if ( length( $0 ) > m ) m = length( $0 ) } END { print m + 0 }')
fence=$(printf '%*s' $(( ticks > 2 ? ticks + 1 : 3 )) '' | tr ' ' '`')

# Everything except the diff itself, sized with the longer, truncated wording,
# decides how many bytes of diff fit.
note=" Only the first ${hunks} of ${hunks} hunks are shown below; the download has them all."
frame=$(printf '%s\n%s\n\n%s%s%s\n\n<details>\n<summary>corpus.diff (first %s of %s hunks)</summary>\n\n%sdiff\n%s\n</details>\n' \
	"$marker" "$heading" "$stat" "$note" "$download" "$hunks" "$hunks" "$fence" "$fence")
budget=$(( max_bytes - ${#frame} ))

# Last line and count of the leading whole hunks that fit the budget.
read -r keep_line keep_hunks < <(awk -v budget="$budget" '
	/^@@/ { if ( total <= budget ) { keep_line = NR - 1; keep_hunks = seen } seen++ }
	{ total += length( $0 ) + 1 }
	END { if ( total <= budget ) { keep_line = NR; keep_hunks = seen } print keep_line + 0, keep_hunks + 0 }
' "$diff_file")

if [ "$keep_hunks" -eq 0 ]; then
	printf '%s\n%s\n\n%s No hunk fits in a comment (%s lines, %s bytes).%s\n' \
		"$marker" "$heading" "$stat" "$lines" "$bytes" "$download"
	exit 0
fi

if [ "$keep_hunks" -eq "$hunks" ]; then
	note=''
	summary="corpus.diff (${lines} lines)"
else
	note=" Only the first ${keep_hunks} of ${hunks} hunks are shown below; the download has them all."
	summary="corpus.diff (first ${keep_hunks} of ${hunks} hunks)"
fi

printf '%s\n%s\n\n%s%s%s\n\n<details>\n<summary>%s</summary>\n\n%sdiff\n' \
	"$marker" "$heading" "$stat" "$note" "$download" "$summary" "$fence"
head -n "$keep_line" "$diff_file"
printf '%s\n</details>\n' "$fence"
