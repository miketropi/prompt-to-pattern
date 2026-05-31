<?php
/**
 * Block Markup Validator — validates AI-generated block markup for Mode B.
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Parses and validates AI-generated block markup to ensure it uses only
 * allowed block types and design-token slugs — never hardcoded values.
 */
class Block_Validator {

	/**
	 * Core block prefix.
	 */
	private const CORE_PREFIX = 'core/';

	/**
	 * Validate that generated markup uses only allowed block types
	 * and references only token slugs for styling.
	 *
	 * @param string $markup      The block markup to validate.
	 * @param array  $design_tokens Design token slugs from theme.json.
	 * @return true|WP_Error True if valid, WP_Error with problem description otherwise.
	 */
	public function validate( string $markup, array $design_tokens ): true|WP_Error {
		if ( empty( trim( $markup ) ) ) {
			return new WP_Error(
				'empty_markup',
				__( 'Generated markup is empty.', 'prompt-to-pattern' ),
				array( 'status' => 400 )
			);
		}

		$blocks = parse_blocks( $markup );

		if ( empty( $blocks ) ) {
			return new WP_Error(
				'no_blocks',
				__( 'Generated markup contains no valid blocks.', 'prompt-to-pattern' ),
				array( 'status' => 400 )
			);
		}

		foreach ( $blocks as $block ) {
			$result = $this->validate_block( $block, $design_tokens );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Validate a single block and its inner blocks recursively.
	 *
	 * @param array $block         Parsed block array.
	 * @param array $design_tokens Design token slugs.
	 * @return true|WP_Error
	 */
	private function validate_block( array $block, array $design_tokens ): true|WP_Error {
		if ( empty( $block['blockName'] ) ) {
			return true; // Free-form HTML — allowed in limited cases.
		}

		$block_name = $block['blockName'];

		if ( ! $this->is_allowed_block( $block_name ) ) {
			return new WP_Error(
				'invalid_block',
				sprintf(
					/* translators: %s: block name */
					__( 'Block type "%s" is not allowed.', 'prompt-to-pattern' ),
					$block_name
				),
				array( 'status' => 400 )
			);
		}

		// Validate style attributes against design tokens.
		$style_check = $this->validate_styles( $block['attrs'] ?? array(), $design_tokens );
		if ( is_wp_error( $style_check ) ) {
			return $style_check;
		}

		// Recurse into inner blocks.
		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner ) {
				$result = $this->validate_block( $inner, $design_tokens );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}

		return true;
	}

	/**
	 * Check whether a block type is on the allowlist.
	 *
	 * Allows all core blocks and theme-registered blocks.
	 *
	 * @param string $block_name Full block name (e.g. 'core/paragraph').
	 * @return bool
	 */
	private function is_allowed_block( string $block_name ): bool {
		// Always allow core blocks.
		if ( str_starts_with( $block_name, self::CORE_PREFIX ) ) {
			return true;
		}

		// Check if it's a registered block type.
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );
		return null !== $block_type;
	}

	/**
	 * Validate that block attributes don't contain hardcoded style values
	 * when design tokens have been provided.
	 *
	 * @param array $attrs         Block attributes.
	 * @param array $design_tokens Design token slugs.
	 * @return true|WP_Error
	 */
	private function validate_styles( array $attrs, array $design_tokens ): true|WP_Error {
		if ( empty( $design_tokens ) || empty( $attrs ) ) {
			return true;
		}

		$all_tokens = $this->collect_all_tokens( $design_tokens );

		// Check backgroundColor / textColor — must be a token slug if present.
		if ( isset( $attrs['backgroundColor'] ) && ! in_array( $attrs['backgroundColor'], $all_tokens, true ) ) {
			return new WP_Error(
				'invalid_color_token',
				sprintf(
					/* translators: %s: color value */
					__( 'Hardcoded color "%s" detected; use a design-token slug instead.', 'prompt-to-pattern' ),
					esc_html( $attrs['backgroundColor'] )
				),
				array( 'status' => 400 )
			);
		}

		if ( isset( $attrs['textColor'] ) && ! in_array( $attrs['textColor'], $all_tokens, true ) ) {
			return new WP_Error(
				'invalid_color_token',
				sprintf(
					/* translators: %s: color value */
					__( 'Hardcoded color "%s" detected; use a design-token slug instead.', 'prompt-to-pattern' ),
					esc_html( $attrs['textColor'] )
				),
				array( 'status' => 400 )
			);
		}

		// Check fontSize — must be a token slug.
		if ( isset( $attrs['fontSize'] ) && ! in_array( $attrs['fontSize'], $all_tokens, true ) ) {
			return new WP_Error(
				'invalid_font_token',
				sprintf(
					/* translators: %s: font size value */
					__( 'Hardcoded font size "%s" detected; use a design-token slug instead.', 'prompt-to-pattern' ),
					esc_html( $attrs['fontSize'] )
				),
				array( 'status' => 400 )
			);
		}

		// Check style object for hardcoded CSS (color, spacing).
		if ( isset( $attrs['style'] ) && is_array( $attrs['style'] ) ) {
			// Reject hardcoded color values (hex, rgb, named colors).
			if ( isset( $attrs['style']['color'] ) && is_array( $attrs['style']['color'] ) ) {
				$color_style = wp_json_encode( $attrs['style']['color'] );
				if ( $color_style && $this->has_hardcoded_color( $color_style ) ) {
					return new WP_Error(
						'hardcoded_color',
						__( 'Hardcoded color values detected in style attributes; use design-token slugs only.', 'prompt-to-pattern' ),
						array( 'status' => 400 )
					);
				}
			}

			// Reject hardcoded spacing values (px, em, rem with raw numbers).
			if ( isset( $attrs['style']['spacing'] ) && is_array( $attrs['style']['spacing'] ) ) {
				$spacing_style = wp_json_encode( $attrs['style']['spacing'] );
				if ( $spacing_style && $this->has_hardcoded_spacing( $spacing_style ) ) {
					return new WP_Error(
						'hardcoded_spacing',
						__( 'Hardcoded spacing/pixel values detected in style attributes; use design-token slugs only.', 'prompt-to-pattern' ),
						array( 'status' => 400 )
					);
				}
			}
		}

		return true;
	}

	/**
	 * Collect all token slugs from all design token groups.
	 *
	 * @param array $design_tokens Groups of token slugs.
	 * @return string[] Flat array of all slugs.
	 */
	private function collect_all_tokens( array $design_tokens ): array {
		$all = array();
		foreach ( $design_tokens as $group ) {
			if ( is_array( $group ) ) {
				$all = array_merge( $all, $group );
			}
		}
		return array_unique( array_filter( $all, 'is_string' ) );
	}

	/**
	 * Check a JSON string for hardcoded color values (hex, rgb, hsl, named).
	 *
	 * @param string $json JSON-encoded style object.
	 * @return bool
	 */
	private function has_hardcoded_color( string $json ): bool {
		// Match hex colors, rgb(), rgba(), hsl(), hsla().
		$patterns = array(
			'/#[0-9a-fA-F]{3,8}/',
			'/rgba?\s*\(/',
			'/hsla?\s*\(/',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $json ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check a JSON string for hardcoded pixel/unit values.
	 *
	 * @param string $json JSON-encoded style object.
	 * @return bool
	 */
	private function has_hardcoded_spacing( string $json ): bool {
		// Match values like "10px", "2rem", "3em" etc.
		return (bool) preg_match( '/"\d+(?:\.\d+)?\s*(?:px|em|rem|vh|vw|%|pt|cm|mm)/', $json );
	}
}
