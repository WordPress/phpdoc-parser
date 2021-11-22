<?php

/**
 * A parent test case class for the data export tests.
 */

namespace WP_Parser\Tests;

/**
 * Parent test case for data export tests.
 */
class Import_UnitTestCase extends Export_UnitTestCase
{
    /**
     * The importer instace used in the tests.
     *
     * @var \WP_Parser\Importer
     */
    protected $importer;

    /**
     * Set up before the tests.
     */
    public function setUp() {
        parent::setUp();

        $this->importer = new \WP_Parser\Importer();
        $this->importer->import([$this->export_data]);
    }
}
