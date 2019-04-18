<?php namespace WP_Parser\Values;

/**
 * Class DocBlock
 * @package WP_Parser\Values
 */
class DocBlock {

	/**
	 * @var string
	 */
	private $description;
	/**
	 * @var string
	 */
	private $long_description;
	/**
	 * @var array
	 */
	private $tags;

	public function __construct( string $description, string $long_description = '' ) {
		$this->description = $description;
		$this->long_description = $long_description;
	}


	public static function from_element( BaseReflector $element ) {

	}
}
