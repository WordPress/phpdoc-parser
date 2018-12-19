<?php namespace WP_Parser;

use Symfony\Component\Yaml\Yaml;

class PluginFinder {

	// Keep track of the plugin found in the directory, including its files.

	private $directory;
	private $plugin = array();

	/**
	 * @var array
	 */
	private $exclude_files;

	private $valid_plugins = array();

	public function __construct( $directory, $exclude_files = array() ) {
		$this->directory 	 = $directory;
		$this->exclude_files = $exclude_files;
		$this->valid_plugins = $this->collect_valid_plugins();
	}

	public function find() {
		$files = Utils::get_files( $this->directory, $this->exclude_files );

		foreach ( $files as $file ) {
			$plugin_data = $this->get_plugin_data( $file );

			if ( $plugin_data === array() ) {
				continue;
			}

			$this->plugin = $plugin_data;
			break;
		}
	}

	public function has_plugin() {
		return $this->plugin !== array();
	}

	public function get_plugin() {
		return $this->plugin;
	}

	public function is_valid_plugin() {
		return array_search( $this->plugin['Name'], array_column( $this->valid_plugins, 'name' ) ) !== false;
	}

	public function collect_valid_plugins() {
		return Yaml::parseFile( dirname( __DIR__ ) . '/plugins.yml' );
	}

	private function get_plugin_data( $file ) {
		$plugin_data = get_plugin_data( $file, false, false );

		if ( ! empty( $plugin_data['Name'] ) ) {
			return $plugin_data;
		}

		return array();
	}

}
