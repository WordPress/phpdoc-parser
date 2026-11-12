<?php
namespace WP_Parser;

/**
 * Main plugin's class. Registers things and adds WP CLI command.
 */
class Plugin {

	/**
	 * @var \WP_Parser\Relationships
	 */
	public $relationships;

	public function on_load() {

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'parser', __NAMESPACE__ . '\\Command' );
		}

		$this->relationships = new Relationships;

		add_action( 'init', array( $this, 'register_post_types' ), 11 );
		add_action( 'init', array( $this, 'register_taxonomies' ), 11 );
		add_filter( 'wp_parser_get_arguments', array( $this, 'make_args_safe' ) );
		add_filter( 'wp_parser_return_type', array( $this, 'humanize_separator' ) );

		add_filter( 'post_type_link', array( $this, 'method_permalink' ), 10, 2 );
	}

	/**
	 * Register the function and class post types
	 */
	public function register_post_types() {

		$supports = array(
			'comments',
			'custom-fields',
			'editor',
			'excerpt',
			'revisions',
			'title',
		);

		if ( ! post_type_exists( 'wp-parser-function' ) ) {

			register_post_type(
				'wp-parser-function',
				array(
					'has_archive' => 'functions',
					'label'       => __( 'Functions', 'wp-parser' ),
					'public'      => true,
					'rewrite'     => array(
						'feeds'      => false,
						'slug'       => 'function',
						'with_front' => false,
					),
					'supports'    => $supports,
				)
			);
		}


		if ( ! post_type_exists( 'wp-parser-method' ) ) {

			add_rewrite_rule( 'method/([^/]+)/([^/]+)/?$', 'index.php?post_type=wp-parser-method&name=$matches[1]-$matches[2]', 'top' );

			register_post_type(
				'wp-parser-method',
				array(
					'has_archive' => 'methods',
					'label'       => __( 'Methods', 'wp-parser' ),
					'public'      => true,
					'rewrite'     => array(
						'feeds'      => false,
						'slug'       => 'method',
						'with_front' => false,
					),
					'supports'    => $supports,
				)
			);
		}


		if ( ! post_type_exists( 'wp-parser-class' ) ) {

			register_post_type(
				'wp-parser-class',
				array(
					'has_archive' => 'classes',
					'label'       => __( 'Classes', 'wp-parser' ),
					'public'      => true,
					'rewrite'     => array(
						'feeds'      => false,
						'slug'       => 'class',
						'with_front' => false,
					),
					'supports'    => $supports,
				)
			);
		}

		if ( ! post_type_exists( 'wp-parser-hook' ) ) {

			register_post_type(
				'wp-parser-hook',
				array(
					'has_archive' => 'hooks',
					'label'       => __( 'Hooks', 'wp-parser' ),
					'public'      => true,
					'rewrite'     => array(
						'feeds'      => false,
						'slug'       => 'hook',
						'with_front' => false,
					),
					'supports'    => $supports,
				)
			);
		}
	}

	/**
	 * Register the file and @since taxonomies
	 */
	public function register_taxonomies() {

		$object_types = array( 'wp-parser-class', 'wp-parser-method', 'wp-parser-function', 'wp-parser-hook' );

		if ( ! taxonomy_exists( 'wp-parser-source-file' ) ) {

			register_taxonomy(
				'wp-parser-source-file',
				$object_types,
				array(
					'label'                 => __( 'Files', 'wp-parser' ),
					'public'                => true,
					'rewrite'               => array( 'slug' => 'files' ),
					'sort'                  => false,
					'update_count_callback' => '_update_post_term_count',
				)
			);
		}

		if ( ! taxonomy_exists( 'wp-parser-package' ) ) {

			register_taxonomy(
				'wp-parser-package',
				$object_types,
				array(
					'hierarchical'          => true,
					'label'                 => '@package',
					'public'                => true,
					'rewrite'               => array( 'slug' => 'package' ),
					'sort'                  => false,
					'update_count_callback' => '_update_post_term_count',
				)
			);
		}

		if ( ! taxonomy_exists( 'wp-parser-since' ) ) {

			register_taxonomy(
				'wp-parser-since',
				$object_types,
				array(
					'hierarchical'          => true,
					'label'                 => __( '@since', 'wp-parser' ),
					'public'                => true,
					'rewrite'               => array( 'slug' => 'since' ),
					'sort'                  => false,
					'update_count_callback' => '_update_post_term_count',
				)
			);
		}

		if ( ! taxonomy_exists( 'wp-parser-namespace' ) ) {

			register_taxonomy(
				'wp-parser-namespace',
				$object_types,
				array(
					'hierarchical'          => true,
					'label'                 => __( 'Namespaces', 'wp-parser' ),
					'public'                => true,
					'rewrite'               => array( 'slug' => 'namespace' ),
					'sort'                  => false,
					'update_count_callback' => '_update_post_term_count',
				)
			);
		}
	}

	/**
	 * @param string   $link
	 * @param \WP_Post $post
	 *
	 * @return string|void
	 */
	public function method_permalink( $link, $post ) {

		if ( 'wp-parser-method' !== $post->post_type || 0 == $post->post_parent ) {
			return $link;
		}

		list( $class, $method ) = explode( '-', $post->post_name );
		$link = home_url( user_trailingslashit( "method/$class/$method" ) );

		return $link;
	}

	/**
	 * Raw phpDoc could potentially introduce unsafe markup into the HTML, so we sanitise it here.
	 *
	 * @param array $args Parameter arguments to make safe
	 *
	 * @return array
	 */
	public function make_args_safe( $args ) {

		if ( is_array( $args ) ) {
			$args = $this->sanitize_arguments( $args );
		}

		return apply_filters( 'wp_parser_make_args_safe', $args );
	}

	/**
	 * Sanitizes every field of an argument list according to what the field holds.
	 *
	 * Only the types of an argument are a type expression. Its name, its default
	 * value and its description are prose, and are printed without escaping, so
	 * they go through the content filters whatever they happen to look like.
	 *
	 * @param array $args Arguments to make safe.
	 *
	 * @return array The arguments, made safe.
	 */
	protected function sanitize_arguments( array $args ) {

		foreach ( $args as $key => $value ) {
			if ( 'type' === $key || 'types' === $key ) {
				$args[ $key ] = $this->sanitize_type( $value );
			} elseif ( is_array( $value ) ) {
				$args[ $key ] = $this->sanitize_arguments( $value );
			} else {
				$args[ $key ] = $this->sanitize_argument( $value );
			}
		}

		return $args;
	}

	/**
	 * Sanitizes a type, or a list of them, without destroying the type expression.
	 *
	 * The content filters are written for prose, and a type expression isn't
	 * prose: a fully qualified class name is all namespace separators, which
	 * `stripslashes_deep()` eats, and a generic type is wrapped in what
	 * `wp_filter_kses()` reads as an HTML tag and throws away. A type which is
	 * displayed as written is escaped where it's printed instead.
	 *
	 * @param mixed $type A type expression, or a list of them.
	 *
	 * @return mixed The type, made safe.
	 */
	protected function sanitize_type( $type ) {

		if ( is_array( $type ) ) {
			return array_map( array( $this, 'sanitize_type' ), $type );
		}

		if ( is_string( $type ) && $this->is_type_expression_safe( $type ) ) {
			return $type;
		}

		return $this->sanitize_argument( $type );
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public function sanitize_argument( &$value ) {

		static $filters = array(
			'wp_filter_kses',
			'make_clickable',
			'force_balance_tags',
			'wptexturize',
			'convert_smilies',
			'convert_chars',
			'stripslashes_deep',
		);

		foreach ( $filters as $filter ) {
			$value = call_user_func( $filter, $value );
		}

		return $value;
	}

	/**
	 * Reports whether a value is a type expression which is safe to display as written.
	 *
	 * @param string $value Value to check.
	 *
	 * @return bool
	 */
	protected function is_type_expression_safe( $value ) {

		if ( ! is_string( $value ) ) {
			return false;
		}

		/*
		 * Only the characters a DocBlock type expression is written with are
		 * allowed. That leaves out `/` and `=` entirely, so neither a closing
		 * tag nor an attribute can be written at all, which is what every markup
		 * injection needs.
		 */
		if ( 1 !== preg_match( '~^[A-Za-z0-9_\\\\|&,\'"()\[\]{}<>?:.$\s-]++$~', $value ) ) {
			return false;
		}

		/*
		 * A bracket at the very start qualifies nothing, so `<b>hello` is markup
		 * in front of prose rather than a generic type. A group is the exception:
		 * a nested expression is written in front of an array suffix, as in
		 * `(int|string)[]`.
		 */
		if ( false !== strpos( '<[{', $value[0] ) ) {
			return false;
		}

		/*
		 * Whether the whole value is a type expression is decided by the same
		 * scanner which decides where a tag's type expression ends, so a type
		 * the exporter preserves can't be one this destroys. A bracket which is
		 * never closed reads as a start tag which swallows everything after it
		 * up to the next `>`, wherever that turns out to be, and whitespace
		 * anywhere but where a type expression breaks means it's prose.
		 */
		$scan = scan_docblock_tag_content( $value );
		if ( ! $scan['scannable'] || ! $scan['balanced'] || $value !== $scan['type'] ) {
			return false;
		}

		/*
		 * An element whose content isn't parsed as markup can execute or
		 * swallow everything after it even with no attributes and no closing
		 * tag, so a type which reads as one of those is never displayed as
		 * written. A class actually named `Script` is the price of that.
		 *
		 * `object` and `embed` are deliberately not on this list: `array<object>`
		 * is an everyday type, and an attribute-less `<object>` or `<embed>` has
		 * nothing to load, since the `=` and `/` their exploits need are already
		 * rejected above.
		 */
		return 1 !== preg_match(
			'~<\s*(?:script|style|iframe|xmp|textarea|title|svg|math|template'
				. '|plaintext|noembed|noframes|noscript|listing|select)\b~i',
			$value
		);
	}

	/**
	 * Replace separators with a more readable version
	 *
	 * Only the separators between the top-level members of a union are replaced.
	 * A union nested inside brackets, as in `list<string|\WP_Post>`, is part of a
	 * single type and is left untouched.
	 *
	 * This is the point at which a type expression becomes markup, so everything
	 * in it but the separator this adds is escaped: the angle brackets of a
	 * generic type read as a start tag otherwise, and a browser swallows the type
	 * along with them. Escaping here rather than where the type is printed keeps
	 * the separator markup intact, since it's added after the escaping.
	 *
	 * @param string|string[] $type Variable type, or the list of a tag's types.
	 *
	 * @return string|string[] The type as display-ready HTML.
	 */
	public function humanize_separator( $type ) {

		/*
		 * The `wp_parser_return_type` filter is passed the whole list of a
		 * return tag's types rather than a single type expression, so every
		 * one of them is humanized on its own.
		 */
		if ( is_array( $type ) ) {
			return array_map( array( $this, 'humanize_separator' ), $type );
		}

		if ( ! is_string( $type ) ) {
			return $type;
		}

		$separator = '<span class="wp-parser-item-type-or">' . _x( ' or ', 'separator', 'wp-parser' ) . '</span>';
		$scan      = scan_docblock_type_syntax( $type );

		/*
		 * A bracket which is never closed isn't a type expression, so there is
		 * no telling which separator is nested inside a single type and which
		 * one separates two of them. Every separator is replaced in that case,
		 * which is what this did before it knew about brackets at all. The
		 * escaped expression can't contain a bracket for a separator to hide
		 * inside of, so replacing them all is safe here.
		 */
		if ( ! $scan['balanced'] ) {
			return str_replace( '|', $separator, esc_html( $type ) );
		}

		return implode(
			$separator
			, array_map( 'esc_html', split_docblock_type_expression( $type, '|' ) )
		);
	}
}
