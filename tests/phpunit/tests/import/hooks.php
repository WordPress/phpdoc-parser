<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- Named after its hooks.inc fixture, like the other import tests.
/**
 * Test for importing hooks.
 *
 * @package WP_Parser
 */

declare( strict_types = 1 );

namespace WP_Parser\Tests;

/**
 * Test that hooks are imported correctly.
 *
 * @group import
 */
class Hook_Import_Test extends Import_UnitTestCase {

	/**
	 * Test that hooks sharing a slug get their own posts, and keep them on re-import.
	 */
	public function test_hooks_with_the_same_slug_import_as_separate_posts(): void {
		$expected = array(
			'activate_plugin'          => 'activate_plugin',
			'activate_plugin-2'        => 'activate_{$plugin}',
			'manage_screen-id_columns' => 'manage_{$screen-&gt;id}_columns',
		);

		$this->assertSame( $expected, $this->get_hook_titles_by_slug() );

		$this->importer->import( array( $this->export_data ) );

		$this->assertSame( $expected, $this->get_hook_titles_by_slug() );
	}

	/**
	 * Test that the function is connected to each of the hooks sharing a slug.
	 */
	public function test_hooks_with_the_same_slug_are_each_used_by_their_function(): void {
		$function = get_page_by_path( 'wp_parser_activate_plugin', OBJECT, $this->importer->post_type_function );
		$hooks    = p2p_type( 'functions_to_hooks' )->get_connected( $function )->posts;

		$this->assertEqualsCanonicalizing(
			array( 'activate_plugin', 'activate_{$plugin}', 'manage_{$screen-&gt;id}_columns' ),
			wp_list_pluck( $hooks, 'post_title' )
		);
	}

	/**
	 * Get the imported hook names, keyed by post slug.
	 *
	 * @return array<string, string> Post titles keyed by post slug.
	 */
	private function get_hook_titles_by_slug(): array {
		$posts = get_posts(
			array(
				'post_type' => $this->importer->post_type_hook,
				'orderby'   => 'ID',
				'order'     => 'ASC',
			)
		);

		return wp_list_pluck( $posts, 'post_title', 'post_name' );
	}
}
