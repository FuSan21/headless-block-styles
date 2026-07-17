<?php
/**
 * Block style computation.
 *
 * Computes block styles server-side with the WordPress Style Engine and
 * returns them as React-compatible (camelCase) style objects, so headless
 * frontends don't have to derive styles from raw block attributes in JS.
 *
 * @package HeadlessBlockStyles
 */

namespace HeadlessBlockStyles\Styles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts a kebab-case CSS property to camelCase for React style objects.
 * CSS custom properties (--foo) are preserved as-is.
 *
 * @param string $property CSS property name.
 * @return string
 */
function to_camel_case( $property ) {
	if ( 0 === strpos( $property, '--' ) ) {
		return $property;
	}
	return lcfirst( str_replace( ' ', '', ucwords( str_replace( '-', ' ', $property ) ) ) );
}

/**
 * Decodes an attribute that may arrive as a JSON string (GraphQL-style)
 * or already-parsed array (parse_blocks output).
 *
 * @param mixed $value Attribute value.
 * @return array|null
 */
function to_array( $value ) {
	if ( is_array( $value ) ) {
		return $value;
	}
	if ( is_string( $value ) ) {
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}
	}
	return null;
}

/**
 * Computes the inline styles for a block from its attributes.
 *
 * The `style` attribute is run through the WP Style Engine, and preset
 * attributes are mapped to the --wp--preset--* CSS custom properties
 * defined by the global stylesheet.
 *
 * @param array $attrs Block attributes.
 * @return array React CSSProperties-shaped array (camelCase keys).
 */
function compute_inline_styles( array $attrs ) {
	$styles = array();

	// 1. The `style` attribute via the Style Engine.
	$style_attr = to_array( $attrs['style'] ?? null );
	if ( $style_attr && function_exists( 'wp_style_engine_get_styles' ) ) {
		$engine = wp_style_engine_get_styles( $style_attr );
		if ( ! empty( $engine['declarations'] ) ) {
			foreach ( $engine['declarations'] as $property => $value ) {
				$styles[ to_camel_case( $property ) ] = $value;
			}
		}
	}

	// 2. Preset attributes → CSS custom properties.
	if ( ! empty( $attrs['backgroundColor'] ) && is_string( $attrs['backgroundColor'] ) ) {
		$styles['backgroundColor'] = sprintf( 'var(--wp--preset--color--%s)', $attrs['backgroundColor'] );
	}
	if ( ! empty( $attrs['textColor'] ) && is_string( $attrs['textColor'] ) ) {
		$styles['color'] = sprintf( 'var(--wp--preset--color--%s)', $attrs['textColor'] );
	}
	if ( ! empty( $attrs['gradient'] ) && is_string( $attrs['gradient'] ) ) {
		$styles['background'] = sprintf( 'var(--wp--preset--gradient--%s)', $attrs['gradient'] );
	}
	if ( ! empty( $attrs['borderColor'] ) && is_string( $attrs['borderColor'] ) ) {
		$styles['borderColor'] = sprintf( 'var(--wp--preset--color--%s)', $attrs['borderColor'] );
	}
	if ( ! empty( $attrs['fontSize'] ) && is_string( $attrs['fontSize'] ) ) {
		$styles['fontSize'] = sprintf( 'var(--wp--preset--font-size--%s)', $attrs['fontSize'] );
	}
	if ( ! empty( $attrs['fontFamily'] ) && is_string( $attrs['fontFamily'] ) ) {
		$styles['fontFamily'] = sprintf( 'var(--wp--preset--font-family--%s)', $attrs['fontFamily'] );
	}

	// 3. Layout attributes.
	if ( ! empty( $attrs['width'] ) && is_scalar( $attrs['width'] ) ) {
		$styles['flexBasis'] = (string) $attrs['width'];
	}
	if ( ! empty( $attrs['height'] ) && is_scalar( $attrs['height'] ) ) {
		$styles['height'] = (string) $attrs['height'];
	}

	$layout = to_array( $attrs['layout'] ?? null );
	if ( $layout ) {
		$content_size = ! empty( $layout['contentSize'] ) ? $layout['contentSize'] : null;
		$wide_size    = ! empty( $layout['wideSize'] ) ? $layout['wideSize'] : null;
		$justify      = ! empty( $layout['justifyContent'] ) ? $layout['justifyContent'] : 'center';

		if ( $content_size || $wide_size ) {
			$styles['maxWidth']    = $content_size ? $content_size : $wide_size;
			$styles['marginLeft']  = 'left' === $justify ? '0' : 'auto';
			$styles['marginRight'] = 'right' === $justify ? '0' : 'auto';
		}
	}

	/**
	 * Filters the computed inline styles for a block.
	 *
	 * @param array $styles Computed styles (camelCase keys).
	 * @param array $attrs  Block attributes.
	 */
	return apply_filters( 'headless_block_styles_block_inline_styles', $styles, $attrs );
}

/**
 * Computes the editor-parity class names for a block from its attributes,
 * matching what Gutenberg assigns on the WordPress frontend (has-*-color,
 * has-background, align*, …). These classes are styled by the global
 * stylesheet served at /stylesheet.
 *
 * @param array $attrs Block attributes.
 * @return string[]
 */
function compute_class_names( array $attrs ) {
	$classnames = array();

	if ( ! empty( $attrs['className'] ) && is_string( $attrs['className'] ) ) {
		$classnames = preg_split( '/\s+/', trim( $attrs['className'] ) );
	}

	$style_attr = to_array( $attrs['style'] ?? null );
	if ( $style_attr && function_exists( 'wp_style_engine_get_styles' ) ) {
		$engine = wp_style_engine_get_styles( $style_attr );
		if ( ! empty( $engine['classnames'] ) ) {
			$classnames = array_merge( $classnames, explode( ' ', $engine['classnames'] ) );
		}
	}

	if ( ! empty( $attrs['backgroundColor'] ) && is_string( $attrs['backgroundColor'] ) ) {
		$classnames[] = 'has-background';
		$classnames[] = sprintf( 'has-%s-background-color', $attrs['backgroundColor'] );
	}
	if ( ! empty( $attrs['textColor'] ) && is_string( $attrs['textColor'] ) ) {
		$classnames[] = 'has-text-color';
		$classnames[] = sprintf( 'has-%s-color', $attrs['textColor'] );
	}
	if ( ! empty( $attrs['gradient'] ) && is_string( $attrs['gradient'] ) ) {
		$classnames[] = 'has-background';
		$classnames[] = sprintf( 'has-%s-gradient-background', $attrs['gradient'] );
	}
	if ( ! empty( $attrs['borderColor'] ) && is_string( $attrs['borderColor'] ) ) {
		$classnames[] = 'has-border-color';
		$classnames[] = sprintf( 'has-%s-border-color', $attrs['borderColor'] );
	}
	if ( ! empty( $attrs['fontSize'] ) && is_string( $attrs['fontSize'] ) ) {
		$classnames[] = sprintf( 'has-%s-font-size', $attrs['fontSize'] );
	}
	if ( ! empty( $attrs['fontFamily'] ) && is_string( $attrs['fontFamily'] ) ) {
		$classnames[] = sprintf( 'has-%s-font-family', $attrs['fontFamily'] );
	}
	if ( ! empty( $attrs['align'] ) && is_string( $attrs['align'] ) ) {
		$classnames[] = sprintf( 'align%s', $attrs['align'] );
	}

	/**
	 * Filters the computed class names for a block.
	 *
	 * @param string[] $classnames Computed class names.
	 * @param array    $attrs      Block attributes.
	 */
	return apply_filters( 'headless_block_styles_block_class_names', array_values( array_unique( array_filter( $classnames ) ) ), $attrs );
}

/**
 * Prepares a parsed block (and its inner blocks) for the REST response.
 *
 * @param array $block A single block from parse_blocks().
 * @return array|null Null for whitespace-only filler blocks.
 */
function prepare_block( array $block ) {
	if ( empty( $block['blockName'] ) && '' === trim( $block['innerHTML'] ?? '' ) ) {
		return null;
	}

	$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();

	return array(
		'name'         => $block['blockName'],
		'attributes'   => (object) $attrs,
		'inlineStyles' => (object) compute_inline_styles( $attrs ),
		'classNames'   => compute_class_names( $attrs ),
		'innerHTML'    => $block['innerHTML'] ?? '',
		'innerBlocks'  => prepare_blocks( $block['innerBlocks'] ?? array() ),
	);
}

/**
 * Prepares a list of parsed blocks for the REST response.
 *
 * @param array $blocks Blocks from parse_blocks().
 * @return array
 */
function prepare_blocks( array $blocks ) {
	$prepared = array();
	foreach ( $blocks as $block ) {
		$prepared_block = prepare_block( $block );
		if ( null !== $prepared_block ) {
			$prepared[] = $prepared_block;
		}
	}
	return $prepared;
}
