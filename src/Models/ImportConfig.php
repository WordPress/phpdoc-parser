<?php

namespace Aivec\Plugins\DocParser\Models;

use JsonSerializable;

/**
 * Represents an import configuration
 */
class ImportConfig implements JsonSerializable
{
    /**
     * The sources type (`plugin`, `theme`, or `composer-package`)
     *
     * @var string
     */
    private $source_type;

    /**
     * The plugin/theme/composer-package name
     *
     * @var string
     */
    private $name;

    /**
     * Array of files/folders to exclude from import
     *
     * @var array
     */
    private $exclude;

    /**
     * Whether to exclude a file/folder in subdirectories as well
     *
     * @var bool
     */
    private $exclude_strict;

    /**
     * Initializes an import config
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $source_type
     * @param string $name
     * @param array  $exclude
     * @param bool   $exclude_strict
     * @return void
     */
    public function __construct($source_type, $name, $exclude = [], $exclude_strict = false) {
        $this->source_type = $source_type;
        $this->name = $name;
        $this->exclude = $exclude;
        $this->exclude_strict = $exclude_strict;
    }

    /**
     * Serializes instance data for `json_encode`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function jsonSerialize() {
        return [
            'type' => $this->source_type,
            'name' => $this->name,
            'exclude' => $this->exclude,
            'excludeStrict' => $this->exclude_strict,
        ];
    }

    /**
     * Getter for `$this->source_type`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public function getSourceType() {
        return $this->source_type;
    }

    /**
     * Getter for `$this->name`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Getter for `$this->exclude`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function getExclude() {
        return $this->exclude;
    }

    /**
     * Getter for `$this->exclude_strict`
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function getExcludeStrict() {
        return $this->exclude_strict;
    }
}
