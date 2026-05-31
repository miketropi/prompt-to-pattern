<?php
/**
 * Pattern context provider — reads registered patterns and theme.json tokens.
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

use WP_Block_Patterns_Registry;

/**
 * Gathers available block patterns and design tokens from the active theme
 * and WordPress core, then returns a sanitised catalog for the AI agent.
 */
class Pattern_Context {

	/**
	 * Get the full catalog: patterns + design tokens.
	 *
	 * @return array{patterns: list<array>, design_tokens: array}
	 */
	public function get_catalog(): array {
		return array(
			'patterns'       => $this->get_patterns(),
			'design_tokens'  => $this->get_design_tokens(),
		);
	}

	/**
	 * Enumerate all registered block patterns (core + theme).
	 *
	 * Each entry: slug, title, categories, description, block_types.
	 *
	 * @return list<array> Sanitised pattern entries.
	 */
	public function get_patterns(): array {
		$registry   = WP_Block_Patterns_Registry::get_instance();
		$patterns   = $registry->get_all_registered();
		$catalog    = array();

		foreach ( $patterns as $slug => $pattern ) {
			if ( empty( $pattern['title'] ) ) {
				continue;
			}

			$catalog[] = array(
				'slug'        => is_string( $slug ) ? sanitize_text_field( $slug ) : (string) $slug,
				'title'       => sanitize_text_field( $pattern['title'] ),
				'categories'  => $this->sanitize_categories( $pattern['categories'] ?? array() ),
				'description' => ! empty( $pattern['description'] )
					? wp_kses_post( $pattern['description'] )
					: '',
				'block_types' => $this->sanitize_block_types( $pattern['blockTypes'] ?? array() ),
			);
		}

		return $catalog;
	}

	/**
	 * Extract a condensed set of design tokens from theme.json via
	 * wp_get_global_settings().
	 *
	 * Returns only the token slugs, never raw values, so the AI agent
	 * references tokens instead of inventing colors/spacing.
	 *
	 * @return array{
	 *     colors: list<string>,
	 *     gradients: list<string>,
	 *     font_sizes: list<string>,
	 *     spacing_sizes: list<string>,
	 *     font_families: list<string>,
	 * }
	 */
	public function get_design_tokens(): array {
		$settings = wp_get_global_settings();
		$tokens   = array(
			'colors'         => array(),
			'gradients'      => array(),
			'font_sizes'     => array(),
			'spacing_sizes'  => array(),
			'font_families'  => array(),
		);

		// Colour palette.
		if ( ! empty( $settings['color']['palette']['theme'] ) ) {
			foreach ( $settings['color']['palette']['theme'] as $color ) {
				$tokens['colors'][] = sanitize_text_field( $color['slug'] ?? '' );
			}
		}
		if ( ! empty( $settings['color']['palette']['default'] ) ) {
			foreach ( $settings['color']['palette']['default'] as $color ) {
				$tokens['colors'][] = sanitize_text_field( $color['slug'] ?? '' );
			}
		}

		// Gradients.
		if ( ! empty( $settings['color']['gradients']['theme'] ) ) {
			foreach ( $settings['color']['gradients']['theme'] as $gradient ) {
				$tokens['gradients'][] = sanitize_text_field( $gradient['slug'] ?? '' );
			}
		}
		if ( ! empty( $settings['color']['gradients']['default'] ) ) {
			foreach ( $settings['color']['gradients']['default'] as $gradient ) {
				$tokens['gradients'][] = sanitize_text_field( $gradient['slug'] ?? '' );
			}
		}

		// Font sizes.
		if ( ! empty( $settings['typography']['fontSizes']['theme'] ) ) {
			foreach ( $settings['typography']['fontSizes']['theme'] as $size ) {
				$tokens['font_sizes'][] = sanitize_text_field( $size['slug'] ?? '' );
			}
		}
		if ( ! empty( $settings['typography']['fontSizes']['default'] ) ) {
			foreach ( $settings['typography']['fontSizes']['default'] as $size ) {
				$tokens['font_sizes'][] = sanitize_text_field( $size['slug'] ?? '' );
			}
		}

		// Spacing sizes.
		if ( ! empty( $settings['spacing']['spacingSizes']['theme'] ) ) {
			foreach ( $settings['spacing']['spacingSizes']['theme'] as $spacing ) {
				$tokens['spacing_sizes'][] = sanitize_text_field( $spacing['slug'] ?? '' );
			}
		}
		if ( ! empty( $settings['spacing']['spacingSizes']['default'] ) ) {
			foreach ( $settings['spacing']['spacingSizes']['default'] as $spacing ) {
				$tokens['spacing_sizes'][] = sanitize_text_field( $spacing['slug'] ?? '' );
			}
		}

		// Font families.
		if ( ! empty( $settings['typography']['fontFamilies']['theme'] ) ) {
			foreach ( $settings['typography']['fontFamilies']['theme'] as $font ) {
				$tokens['font_families'][] = sanitize_text_field( $font['slug'] ?? '' );
			}
		}

		// Remove empty token groups.
		return array_filter(
			$tokens,
			static fn( $group ) => ! empty( $group )
		);
	}

	/**
	 * Sanitise an array of pattern category slugs.
	 *
	 * @param string[] $categories
	 * @return string[]
	 */
	private function sanitize_categories( array $categories ): array {
		return array_map(
			static fn( $cat ) => sanitize_text_field( $cat ),
			$categories
		);
	}

	/**
	 * Sanitise an array of block type strings.
	 *
	 * @param string[] $block_types
	 * @return string[]
	 */
	private function sanitize_block_types( array $block_types ): array {
		return array_map(
			static fn( $type ) => sanitize_text_field( $type ),
			$block_types
		);
	}
}
