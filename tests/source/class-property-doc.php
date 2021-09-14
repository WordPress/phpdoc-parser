<?php

/**
 * WordPress Error class.
 *
 * Container for checking for WordPress errors and error messages. Return
 * WP_Error and use {@link is_wp_error()} to check if this class is returned.
 * Many core WordPress functions pass this class in the event of an error and
 * if not handled properly will result in code errors.
 *
 * @package WordPress
 * @since 2.1.0
 */
class Bad_Property_Doc
{
    /**
     * Stores the list of errors.
     *
     * @since 2.1.0
     * @var array
     * @access private
     */
    private $private_good_doc_property = 'foo';

    /**
     * @since 2.1.0
     * @var array
     * @access private
     */
    private $private_missing_description_property = 'string';

    /**
     * Stores the list of errors.
     *
     * @var array
     * @access private
     */
    private $private_missing_since_property = 'foo';

    /**
     * Stores the list of errors.
     *
     * @since 2.1.0
     * @access private
     */
    private $private_missing_var_property = 'foo';

    /**
     * @since 2.1.0
     * @var array
     */
    private $private_missing_access_property = 'string';

    /**
     * Stores the list of errors.
     *
     * @since 2.1.0
     * @var array
     * @access public
     */
    public $public_good_doc_property = 'foo';

    /**
     * @since 2.1.0
     * @var array
     * @access public
     */
    public $public_missing_description_property = 'string';

    /**
     * Stores the list of errors.
     *
     * @var array
     * @access public
     */
    public $public_missing_since_property = 'foo';

    /**
     * Stores the list of errors.
     *
     * @since 2.1.0
     * @access public
     */
    public $public_missing_var_property = 'foo';

    /**
     * @since 2.1.0
     * @var array
     */
    public $public_missing_access_property = 'string';
}
