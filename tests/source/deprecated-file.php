<?php

_deprecated_file('deprecated-file.php', '1.0');

/**
* This class should be marked as deprecated since 1.0
*/
class Should_Be_Deprectated
{
    /**
     * This method should be marked as deprecated since 1.0
     */
    public function should_be_deprecated() {
    }
}

/**
* This function should be marked as deprecated since 1.0
*/
function should_be_deprecated() {
}

/**
* This filter should be marked as deprecated since 1.0
*/
$var = apply_filters('deprecated_filter', $var);

/**
* This action should be marked as deprecated since 1.0
*/
do_action('deprecated_action');
