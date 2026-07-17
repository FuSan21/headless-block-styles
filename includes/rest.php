<?php
/**
 * REST API surface.
 *
 * Registers routes for the global stylesheet and theme settings, plus a
 * `block_data` field on post responses carrying per-block computed
 * inline styles.
 *
 * @package HeadlessBlockStyles
 */

namespace HeadlessBlockStyles\Rest;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

use function HeadlessBlockStyles\Styles\prepare_blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const STYLESHEET_TYPES = array( 'variables', 'presets', 'styles', 'base-layout-styles' );

add_action( 'rest_api_init', __NAMESPACE__ . '\\register_routes' );
/**
 * Registers the REST routes.
 *
 * @return void
 */
function register_routes() {
	register_rest_route(
		HBS_REST_NAMESPACE,
		'/stylesheet',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_stylesheet',
			'permission_callback' => '__return_true',
			'args'                => array(
				'types'  => array(
					'description' => __( 'Which parts of the global stylesheet to include.', 'headless-block-styles' ),
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
						'enum' => STYLESHEET_TYPES,
					),
					'required'    => false,
				),
				'format' => array(
					'description' => __( 'Response format: json (default) or raw css.', 'headless-block-styles' ),
					'type'        => 'string',
					'enum'        => array( 'json', 'css' ),
					'default'     => 'json',
				),
			),
		)
	);

	register_rest_route(
		HBS_REST_NAMESPACE,
		'/theme',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => __NAMESPACE__ . '\\get_theme_settings',
			'permission_callback' => '__return_true',
		)
	);

	register_block_data_field();
}

/**
 * Returns the global stylesheet (merged core, theme, and user data)
 * as produced by wp_get_global_stylesheet().
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function get_stylesheet( WP_REST_Request $request ) {
	$types = $request->get_param( 'types' );
	$css   = wp_get_global_stylesheet( ! empty( $types ) ? $types : array() );

	$response = new WP_REST_Response(
		array(
			'css'   => $css,
			'types' => ! empty( $types ) ? $types : STYLESHEET_TYPES,
		)
	);

	if ( 'css' === $request->get_param( 'format' ) ) {
		// Flag for rest_pre_serve_request below to emit raw CSS.
		$response->header( 'Content-Type', 'text/css; charset=' . get_option( 'blog_charset' ) );
	}

	return $response;
}

add_filter( 'rest_pre_serve_request', __NAMESPACE__ . '\\serve_raw_css', 10, 3 );
/**
 * Serves the /stylesheet response as raw CSS when format=css was requested,
 * so the URL can be used directly in a <link rel="stylesheet"> tag.
 *
 * @param bool             $served  Whether the request has been served.
 * @param WP_REST_Response $result  Result to send.
 * @param WP_REST_Request  $request Request used to generate the response.
 * @return bool
 */
function serve_raw_css( $served, $result, $request ) {
	if ( $served || '/' . HBS_REST_NAMESPACE . '/stylesheet' !== $request->get_route() ) {
		return $served;
	}

	$headers = $result->get_headers();
	if ( empty( $headers['Content-Type'] ) || 0 !== strpos( $headers['Content-Type'], 'text/css' ) ) {
		return $served;
	}

	$data = $result->get_data();
	if ( isset( $data['css'] ) ) {
		echo $data['css']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS payload, not HTML.
		return true;
	}

	return $served;
}

/**
 * Returns the merged theme.json settings (palette, font sizes, layout, …).
 *
 * @return WP_REST_Response
 */
function get_theme_settings() {
	return new WP_REST_Response(
		array(
			'settings' => wp_get_global_settings(),
		)
	);
}

/**
 * Registers the `block_data` field on all REST-enabled post types. The field
 * contains the parsed block tree where every block carries `inlineStyles`
 * (a React CSSProperties-shaped object) and `classNames`.
 *
 * @return void
 */
function register_block_data_field() {
	$post_types = get_post_types( array( 'show_in_rest' => true ), 'names' );

	/**
	 * Filters the post types that expose the block_data REST field.
	 *
	 * @param string[] $post_types Post type names.
	 */
	$post_types = apply_filters( 'hbs_post_types', array_values( $post_types ) );

	register_rest_field(
		$post_types,
		'block_data',
		array(
			'get_callback' => __NAMESPACE__ . '\\get_block_data',
			'schema'       => array(
				'description' => __( 'Parsed blocks with computed inline styles and class names.', 'headless-block-styles' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
			),
		)
	);
}

/**
 * REST field callback: parses the post content into a block tree with
 * computed styles.
 *
 * @param array $post_data Prepared post data.
 * @return array
 */
function get_block_data( array $post_data ) {
	$post = get_post( $post_data['id'] ?? 0 );

	if ( ! $post || post_password_required( $post ) ) {
		return array();
	}

	return prepare_blocks( parse_blocks( $post->post_content ) );
}
