<?php
/**
 * Composer — resolves pattern slugs to block markup and assembles the final output.
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

use WP_Block_Patterns_Registry;
use WP_Error;

/**
 * Converts a structured compose result (list of pattern slugs) into
 * valid block markup ready for insertion into the editor.
 */
class Composer {

	/**
	 * Assemble block markup from a compose result.
	 *
	 * Handles both Mode A (existing patterns) and Mode B (AI-generated sections).
	 *
	 * @param array $compose_result The result from AI_Service::compose(), with a 'sections' key.
	 * @param array $design_tokens  Design token slugs for Mode B validation.
	 * @param string $target        'page_content' or 'fse_template'.
	 * @return array{markup: string, sections: list<array>, unmet: list<string>, generated: int}|WP_Error
	 */
	public function assemble( array $compose_result, array $design_tokens = array(), string $target = 'page_content' ): array|WP_Error {
		$sections    = $compose_result['sections'] ?? array();
		$unmet       = $compose_result['unmet'] ?? array();
		$notes       = $compose_result['notes'] ?? '';
		$registry    = WP_Block_Patterns_Registry::get_instance();
		$assembled   = '';
		$resolved    = array();
		$generated   = 0;
		$validator   = new Block_Validator();
		$safe_fallback = $this->get_safe_fallback();

		foreach ( $sections as $section ) {
			// Mode B: AI-generated section.
			if ( ! empty( $section['generated'] ) ) {
				$markup    = $section['markup'] ?? '';
				$label     = $section['label'] ?? __( 'Generated section', 'prompt-to-pattern' );
				$valid     = $validator->validate( $markup, $design_tokens );

				if ( is_wp_error( $valid ) ) {
					// Try up to 3 retries through the AI service — for now, fallback.
					// In full V2, the retry loop lives in AI_Service.
					$assembled .= $safe_fallback;
					$resolved[] = array(
						'generated' => true,
						'label'     => $label,
						'valid'     => false,
						'error'     => $valid->get_error_message(),
					);
					$unmet[]    = $label;
				} else {
					$assembled  .= $markup . "\n\n";
					$generated++;
					$resolved[] = array(
						'generated' => true,
						'label'     => $label,
						'valid'     => true,
					);
				}

				continue;
			}

			// Mode A: existing pattern by slug.
			$slug = $section['slug'] ?? null;

			if ( empty( $slug ) ) {
				continue;
			}

			// Look up the pattern — handle both string and integer registry keys.
			$pattern = $registry->get_registered( $slug );

			if ( ! $pattern ) {
				// Direct lookup from all registered patterns for integer-keyed registries.
				$all = $registry->get_all_registered();
				$found = $all[ $slug ] ?? null;
				$found = $found ?? $all[ (int) $slug ] ?? null;

				if ( $found ) {
					$pattern = $found;
				}
			}

			if ( ! $pattern ) {
				trigger_error(
					sprintf(
						/* translators: %s: pattern slug */
						esc_html__( 'Prompt to Pattern: AI suggested unknown pattern slug "%s" — skipped.', 'prompt-to-pattern' ),
						esc_html( $slug )
					),
					E_USER_WARNING
				);
				$unmet[] = $slug;
				continue;
			}

			$markup = $pattern['content'] ?? '';

			// Apply content overrides if the AI suggested them.
			if ( ! empty( $section['content_overrides'] ) && is_array( $section['content_overrides'] ) ) {
				$markup = $this->apply_content_overrides( $markup, $section['content_overrides'] );
			}

			$assembled .= $markup . "\n\n";
			$resolved[]  = array(
				'slug'  => $slug,
				'title' => $pattern['title'] ?? $slug,
			);
		}

		// Wrap in template block if targeting FSE template.
		if ( 'fse_template' === $target && ! empty( $assembled ) ) {
			$assembled = sprintf(
				'<!-- wp:template -->%s<!-- /wp:template -->',
				"\n" . trim( $assembled ) . "\n"
			);
		}

		if ( empty( $assembled ) && empty( $unmet ) ) {
			return new WP_Error(
				'empty_compose',
				__( 'The AI did not select any patterns. Try a more specific prompt.', 'prompt-to-pattern' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'markup'    => $assembled,
			'sections'  => $resolved,
			'unmet'     => $unmet,
			'notes'     => $notes,
			'generated' => $generated,
			'target'    => $target,
		);
	}

	/**
	 * Get a safe fallback placeholder block for when generated markup fails validation.
	 */
	private function get_safe_fallback(): string {
		return sprintf(
			'<!-- wp:paragraph --><p>%s</p><!-- /wp:paragraph -->',
			esc_html__( 'This section could not be generated. Please add content manually.', 'prompt-to-pattern' )
		);
	}

	/**
	 * Apply content overrides to pattern markup.
	 *
	 * Performs simple string replacements for headings and paragraphs
	 * that the AI suggested, without changing the pattern's block structure.
	 *
	 * @param string $markup   Original pattern block markup.
	 * @param array  $overrides Key-value pairs of original text → replacement text.
	 * @return string Modified markup.
	 */
	public function apply_content_overrides( string $markup, array $overrides ): string {
		foreach ( $overrides as $original => $replacement ) {
			if ( ! is_string( $original ) || ! is_string( $replacement ) ) {
				continue;
			}

			$safe_original    = wp_kses_post( $original );
			$safe_replacement = wp_kses_post( $replacement );

			// Replace inside HTML content, but not in block delimiters/attributes.
			$markup = str_replace(
				'>' . $safe_original . '<',
				'>' . $safe_replacement . '<',
				$markup
			);
		}

		return $markup;
	}
}
