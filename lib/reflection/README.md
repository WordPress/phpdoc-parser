# WP_Parser\Reflection

Static reflection layer for PHP source files, built on `nikic/php-parser`.

This code originates from [`phpDocumentor/Reflection`](https://github.com/phpDocumentor/Reflection)
3.x (MIT licensed), plus the PHP-Parser 4/5 compatibility updates from the
[`dmsnell/Reflection`](https://github.com/dmsnell/Reflection) fork
(commit `5a70d28ca62c0fd1911c96e721494e8821313f7b`). Upstream declined to
maintain the 3.x series ([phpDocumentor/Reflection#721](https://github.com/phpDocumentor/Reflection/pull/721)),
so the library is maintained here as part of phpdoc-parser rather than as a
forked Composer dependency.

Differences from the fork:

- Classes live in the `WP_Parser\Reflection` namespace instead of
  `phpDocumentor\Reflection`.
- The optional `phpDocumentor\Event` dispatching (dead code in this plugin —
  the event package was never installed) has been removed.

DocBlock parsing still uses the mainline
[`phpdocumentor/reflection-docblock`](https://github.com/phpDocumentor/ReflectionDocBlock) 2.x
package (`phpDocumentor\Reflection\DocBlock`).

The original per-file MIT license and author attribution headers are preserved.
