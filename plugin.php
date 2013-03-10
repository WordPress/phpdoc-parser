<?php
/**
 * Plugin Name: WordPress API Documentor
 * Description: Generates API documentation for WordPress and imports it into WP.
 * Author: Ryan McCue and Jon Cave
 * Version: 0.1
 */

namespace WPAPIDocumentor;

const TAXONOMY_FILE = 'wpapi-source-file';
const TAXONOMY_PACKAGE = 'wpapi-package';

const POSTTYPE_FUNCTION = 'wpapi-function';
const POSTTYPE_CLASS = 'wpapi-class';
const POSTTYPE_HOOK = 'wpapi-hook';

bootstrap();
register_activation_hook( __FILE__, '\\WPAPIDocumentor\\activate' );
register_deactivation_hook( __FILE__, '\\WPAPIDocumentor\\deactivate' );

function bootstrap() {
	add_action( 'init', '\\WPAPIDocumentor\\register_types' );
	add_action( 'admin_init', '\\WPAPIDocumentor\\run_import' );
}

function activate() {
	wp_schedule_event( time(), 'hourly', 'wpapi_import' );
}

function deactivate() {
	wp_clear_scheduled_hook( 'wpapi_import' );
}

function run_import() {
	if (strpos($_SERVER['REQUEST_URI'], 'tools.php') === false)
		return;

	set_time_limit(0);
	header('Content-Type: text/plain');
	$importer = new Importer;
	$importer->parseFile( __DIR__ . '/output.json' );
	die();
}

function register_types() {
	register_taxonomy( TAXONOMY_FILE, null, array(
		'label' => __( 'Files', 'wpapi' ),
		'public' => true,
		#'show_ui' => false,
		'hierarchical' => true,
		'rewrite' => array(
			'slug' => 'file',
		),
		'sort' => false,
	) );
	register_taxonomy( TAXONOMY_PACKAGE, null, array(
		'label' => __( 'Packages', 'wpapi' ),
		'public' => true,
		'show_ui' => false,
		'hierarchical' => true,
		'rewrite' => array(
			'slug' => 'package',
		),
		'sort' => false,
	) );
	register_post_type( POSTTYPE_FUNCTION, array(
		'label' => __( 'Functions', 'wpapi' ),
		'public' => true,
		#'show_ui' => false,
		'rewrite' => array(
			'slug' => 'function'
		),
		'taxonomies' => array(
			TAXONOMY_FILE,
			TAXONOMY_PACKAGE,
		),
		'hierarchical' => true,
		'supports' => array( 'title', 'editor', 'excerpt', 'comments', 'custom-fields', 'page-attributes' )
	) );
	register_post_type( POSTTYPE_CLASS, array(
		'label' => __( 'Classes', 'wpapi' ),
		'public' => true,
		#'show_ui' => false,
		'rewrite' => array(
			'slug' => 'class'
		),
		'taxonomies' => array(
			TAXONOMY_FILE,
			TAXONOMY_PACKAGE,
		),
		'hierarchical' => true,
		'supports' => array( 'title', 'editor', 'excerpt', 'comments', 'custom-fields', 'page-attributes' )
	) );
	register_post_type( POSTTYPE_HOOK, array(
		'label' => __( 'Hooks', 'wpapi' ),
		'public' => true,
		#'show_ui' => false,
		'rewrite' => array(
			'slug' => 'hook'
		),
		'taxonomies' => array(
			TAXONOMY_FILE,
			TAXONOMY_PACKAGE,
		),
	) );
}

class Importer {
	public static function getFileTerm( $file, $create = true ) {
		$slug = sanitize_title( str_replace( '/', '_', $file ) );
		$term = get_term_by( 'slug', $slug, TAXONOMY_FILE );
		
		if ( ! empty( $term ) )
			$term = (int) $term->term_id;

		if ( empty( $term ) && $create ) {
			$data = wp_insert_term( add_magic_quotes( $file ), TAXONOMY_FILE, array( 'slug' => $slug ) );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			$term = (int) $data['term_id'];
		}

		return apply_filters( 'wpapi_file_term', $term, $file, $create, $slug );
	}

	public static function getHookDescription( $hook ) {
		return apply_filters( 'wpapi_hook_description', $hook->name . ' is a ' . $hook->type, $hook );
	}

	public function parseFile( $path ) {
		$contents = file_get_contents( $path );
		$data = json_decode($contents);
		return $this->parse( $data );
	}

	public function parse( $data ) {
		wp_defer_term_counting( true );
		wp_defer_comment_counting( true );

		foreach ( $data as $file ) {
			$this->handleFile( $file );
		}

		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
		var_dump($this->log);
	}

	protected function handleFile( $file ) {
		$this->file = self::getFileTerm( $file->path );
		if ( is_wp_error( $this->file) ) {
			var_dump( $this->file );
			return;
		}

		#foreach ( $file->constants as $constant ) {
		#	$this->handleConstant( $constant );
		#}

		if ( ! empty( $file->hooks ) ) {
			foreach ( $file->hooks as $hook ) {
				$this->handleHook( $hook );
			}
		}

		if ( ! empty( $file->functions ) ) {
			foreach ( $file->functions as $function ) {
				$this->handleFunction( $function );
			}
		}

		if ( ! empty( $file->classes ) ) {
			foreach ( $file->classes as $class ) {
				$this->handleClass( $class );
			}
		}

		unset($this->file);
	}

	/**
	 * Handle hook data
	 *
	 * @param stdClass $hook Hook data
	 * @param int|null $function Associated function
	 */
	public function handleHook( $hook, $function = null ) {
		$ID = $this->insertHook( $hook, $function );

		if ( is_wp_error( $ID ) ) {
			$this->log[] = $ID;
			return;
		}

		wp_set_object_terms( $ID, $this->file, TAXONOMY_FILE );

		update_post_meta( $ID, '_wpapi_args', $hook->arguments );
		update_post_meta( $ID, '_wpapi_line_num', $hook->line );
		update_post_meta( $ID, '_wpapi_type', $hook->type );
	}

	/**
	 * Insert a hook into the database
	 *
	 * @param stdClass $hook Hook data
	 * @param int|null $function Associated function
	 * @return int|WP_Error
	 */
	protected function insertHook( $data, $function = null ) {
		$slug = sanitize_title( $data->name );

		$post_data = array(
			'post_type' => POSTTYPE_HOOK,
			'post_parent' => $function,
			'post_title' => $data->name,
			'post_name' => $slug,
			'post_status' => 'publish',
			'post_content' => self::getHookDescription( $data ),
		);

		$search = array(
			'name' => $slug,
			'post_type' => POSTTYPE_HOOK,
			'post_parent' => $function,
		);
		$existing = get_posts($search);

		if ( !empty( $existing ) ) {
			$post_data['ID'] = $existing[0]->ID;
			$ID = wp_update_post( $post_data, true );
		}
		else {
			$ID = wp_insert_post( $post_data, true );
		}

		return $ID;
	}

	public function handleFunction( $function, $class = null ) {
		$ID = $this->insertFunction( $function, $class );

		if ( is_wp_error( $ID ) ) {
			$this->log[] = $ID;
			return;
		}

		wp_set_object_terms( $ID, $this->file, TAXONOMY_FILE );

		update_post_meta( $ID, '_wpapi_args', $function->arguments );
		update_post_meta( $ID, '_wpapi_line_num', $function->line );
		update_post_meta( $ID, '_wpapi_tags', $function->doc->tags );

		if ( null !== $class ) {
			update_post_meta( $ID, '_wpapi_final', (bool) $function->final );
			update_post_meta( $ID, '_wpapi_abstract', (bool) $function->abstract );
			update_post_meta( $ID, '_wpapi_static', (bool) $function->static );
			update_post_meta( $ID, '_wpapi_visibility', $function->visibility );
		}

		if ( ! empty( $function->hooks ) ) {
			foreach ( $function->hooks as $hook ) {
				$this->handleHook( $hook, $ID );
			}
		}
	}

	/**
	 * Insert a function into the database
	 *
	 * @param stdClass $data Function data
	 * @return int|WP_Error
	 */
	protected function insertFunction( $data, $class = 0 ) {
		$slug = sanitize_title( $data->name );

		$post_data = array(
			'post_name' => $slug,
			'post_type' => POSTTYPE_FUNCTION,
			'post_parent' => $class,
			'post_title' => $data->name,
			'post_status' => 'publish',
			'post_content' => '<p>' . $data->doc->description . '</p>' . "\n\n" . $data->doc->long_description,
			'post_excerpt' => $data->doc->description,
		);

		$search = array(
			'name' => $slug,
			'post_type' => POSTTYPE_FUNCTION,
			'post_parent' => $class,
		);
		$existing = get_posts($search);

		if ( !empty( $existing ) ) {
			$post_data['ID'] = $existing[0]->ID;
			$ID = wp_update_post( $post_data, true );
		}
		else {
			$ID = wp_insert_post( $post_data, true );
		}

		return $ID;
	}

	protected function handleClass( $class ) {
		$ID = self::insertClass( $class );

		if ( is_wp_error( $ID ) ) {
			$this->log[] = $ID;
			return;
		}

		wp_set_object_terms( $ID, $this->file, TAXONOMY_FILE );

		update_post_meta( $ID, '_wpapi_line_num', $class->line );
		update_post_meta( $ID, '_wpapi_properties', $class->properties );

		foreach ( $class->methods as $method ) {
			$this->handleFunction( $method, $ID );
		}
	}

	/**
	 * Insert a class into the database
	 *
	 * @param stdClass $data class data
	 * @return int|WP_Error
	 */
	protected function insertClass( $data ) {
		$slug = sanitize_title( $data->name );

		$post_data = array(
			'name' => $slug,
			'post_type' => POSTTYPE_CLASS,
			'post_title' => $data->name,
			'post_status' => 'publish',
			'post_content' => '<p>' . $data->doc->description . '</p>' . "\n\n" . $data->doc->long_description,
			'post_excerpt' => $data->doc->description,
		);

		$search = array(
			'name' => $slug,
			'post_type' => POSTTYPE_CLASS,
		);
		$existing = get_posts($search);

		if ( !empty( $existing ) ) {
			$post_data['ID'] = $existing[0]->ID;
			$ID = wp_update_post( $post_data, true );
		}
		else {
			$ID = wp_insert_post( $post_data, true );
		}

		return $ID;
	}
}