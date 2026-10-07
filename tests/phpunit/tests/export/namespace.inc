<?php

namespace Awesome\Space;

use Other\Place\Thing as Alias;

function ohai() {}

/**
 * Resolves class names against the namespace and its aliases.
 *
 * @param Local|list<Alias>|array<string, \Absolute\Name|Alias\Child> $value Types.
 */
function resolve_types( $value ) {}
