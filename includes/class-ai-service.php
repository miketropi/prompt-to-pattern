<?php
/**
 * AI Service — wraps the core AI Client to build and execute compose prompts.
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

use WP_Error;

/**
 * Builds prompts from the catalog + user input, sends them to the AI provider,
 * and returns structured compose results.
 */
class AI_Service {

	/**
	 * Compose a page layout from patterns based on a user prompt.
	 *
	 * @param string $user_prompt      Natural-language description of the desired page.
	 * @param array  $available_patterns Array of pattern objects { slug, title, categories, description, block_types }.
	 * @param array  $design_tokens     Design token slugs from theme.json.
	 * @param string $target            'page_content' or 'fse_template'.
	 * @param bool   $allow_generation  Whether Mode B (AI-generated markup) is allowed.
	 * @return array|WP_Error Structured compose result or error.
	 */
	public function compose(
		string $user_prompt,
		array $available_patterns,
		array $design_tokens,
		string $target = 'page_content',
		bool $allow_generation = false
	): array|WP_Error {
		if ( ! wp_supports_ai() ) {
			return new WP_Error(
				'ai_disabled',
				__( 'AI features are not available on this site.', 'prompt-to-pattern' ),
				array( 'status' => 503 )
			);
		}

		$system_instruction = $this->get_skill_instructions();
		$prompt_text        = $this->build_compose_prompt(
			$user_prompt,
			$available_patterns,
			$design_tokens,
			$target,
			$allow_generation
		);

		$prompt_text = $this->truncate_text_if_oversized( $prompt_text, 64000 );

		$result = wp_ai_client_prompt()
			->using_system_instruction( $system_instruction )
			->with_text( $prompt_text )
			->as_json_response()
			->using_temperature( 0.4 )
			->using_max_tokens( 4096 )
			->generate_text();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->parse_response( $result, $available_patterns );
	}

	/**
	 * Read the SKILL.md compose instructions, filtered for extensibility.
	 */
	private function get_skill_instructions(): string {
		$skill_path = plugin_dir_path( PLUGIN_FILE ) . 'skills/compose/SKILL.md';

		if ( $skill_path && file_exists( $skill_path ) ) {
			$instruction = file_get_contents( $skill_path );
		} else {
			$instruction = $this->get_fallback_skill_instructions();
		}

		/**
		 * Filters the system instructions sent to the AI model.
		 *
		 * @since 1.0.0
		 *
		 * @param string $instruction The system instruction text.
		 * @return string
		 */
		return apply_filters( 'prompttopattern_system_instruction', $instruction );
	}

	/**
	 * Fallback skill instructions if the SKILL.md file is not available.
	 */
	private function get_fallback_skill_instructions(): string {
		return <<<'EOS'
You are a layout composer for WordPress. Your job is to select and arrange
existing block patterns to fulfill a page request. You do NOT invent patterns
unless explicitly told to. You return only JSON, no prose.

Output format:
{
  "sections": [
    { "slug": "<pattern slug string>", "content_overrides": {} }
  ],
  "notes": "<short reasoning>",
  "unmet": ["<section you could not satisfy>"]
}

Rules:
- Use only slug strings from the provided pattern list.
- Copy the slug EXACTLY as shown (e.g., "core/paragraph", "theme/hero").
- Order sections top-to-bottom for the page.
- Prefer the smallest set of patterns that fulfills the request.
- Do not use emojis.
- Return ONLY valid JSON, no surrounding text.
EOS;
	}

	/**
	 * Build the full prompt text sent to the AI model.
	 */
	private function build_compose_prompt(
		string $user_prompt,
		array $available_patterns,
		array $design_tokens,
		string $target,
		bool $allow_generation
	): string {
		$sections = array();

		$sections[] = '## User Request';
		$sections[] = $user_prompt;

		$lines = array();
		foreach ( $available_patterns as $p ) {
			$slug       = $p['slug'] ?? '';
			$title      = $p['title'] ?? '';
			$desc       = ! empty( $p['description'] ) ? wp_strip_all_tags( $p['description'] ) : '';
			$categories = ! empty( $p['categories'] ) ? implode( ', ', $p['categories'] ) : '';

			$line = '- ' . $slug . ' — ' . $title;
			if ( $desc ) {
				$line .= ' [' . $desc . ']';
			}
			if ( $categories ) {
				$line .= ' {' . $categories . '}';
			}

			$lines[] = $line;
		}

		$sections[] = '## Available Pattern Slugs';
		$sections[] = 'Below is a list of ALL available pattern slugs. For each pattern you select, use its slug EXACTLY as shown. Example slugs from this list: ' . implode( ', ', array_slice( array_column( $available_patterns, 'slug' ), 0, 5 ) ) . '. Do NOT invent slugs or use numbers.';
		$sections[] = implode( "\n", $lines );

		if ( ! empty( $design_tokens ) ) {
			$sections[] = '## Design Tokens (use only these slugs)';
			$sections[] = wp_json_encode( $design_tokens, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		$sections[] = '## Target';
		$sections[] = $target;

		$sections[] = '## Generation Mode';
		$sections[] = $allow_generation ? 'allowed' : 'not allowed';

		$sections[] = '## Task';
		$sections[] = 'Select patterns from the list above that best satisfy the user request. Use the slug strings exactly as they appear in the list above. Return ONLY a JSON object with keys: sections, notes, unmet.';

		return implode( "\n\n", $sections );
	}

	/**
	 * Truncate the prompt text if it exceeds the character budget.
	 */
	private function truncate_text_if_oversized( string $prompt_text, int $max_chars ): string {
		$len = mb_strlen( $prompt_text );

		if ( $len <= $max_chars ) {
			return $prompt_text;
		}

		return mb_substr( $prompt_text, 0, $max_chars ) . "\n\n[Prompt truncated for length.]";
	}

	/**
	 * Parse the AI response into a structured array.
	 *
	 * @param string $response           Raw text response from the AI.
	 * @param array  $available_patterns Pattern catalog for slug validation.
	 * @return array|WP_Error Parsed compose result or error.
	 */
	public function parse_response( string $response, array $available_patterns ): array|WP_Error {
		$response = trim( $response );

		if ( str_starts_with( $response, '```' ) ) {
			$response = preg_replace( '/^```(?:json)?\s*\n?/', '', $response );
			$response = preg_replace( '/\n?```\s*$/', '', $response );
			$response = trim( $response );
		}

		$data = json_decode( $response, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'invalid_json',
				sprintf(
					/* translators: %s: JSON parse error message */
					__( 'AI response was not valid JSON: %s', 'prompt-to-pattern' ),
					json_last_error_msg()
				),
				array( 'status' => 500 )
			);
		}

		if ( ! isset( $data['sections'] ) || ! is_array( $data['sections'] ) ) {
			return new WP_Error(
				'invalid_response',
				__( 'AI response did not include the required "sections" key.', 'prompt-to-pattern' ),
				array( 'status' => 500 )
			);
		}

		$resolved = array();
		$unmet    = $data['unmet'] ?? array();

		foreach ( $data['sections'] as $section ) {
			$raw_slug = $section['slug'] ?? '';

			if ( '' === (string) $raw_slug ) {
				continue;
			}

			$resolved_slug = $this->resolve_slug( (string) $raw_slug, $available_patterns );

			if ( null === $resolved_slug ) {
				$unmet[] = (string) $raw_slug;
				continue;
			}

			$section['slug'] = $resolved_slug;
			$resolved[]      = $section;
		}

		return array(
			'sections' => $resolved,
			'notes'    => $data['notes'] ?? '',
			'unmet'    => $unmet,
		);
	}

	/**
	 * Resolve a slug from the AI response to an actual registered pattern slug.
	 *
	 * @param string $raw_slug          The slug from the AI response.
	 * @param array  $available_patterns Pattern catalog for direct lookup.
	 * @return string|null Resolved slug or null.
	 */
	private function resolve_slug( string $raw_slug, array $available_patterns ): ?string {
		$slugs = array_column( $available_patterns, 'slug' );

		if ( in_array( $raw_slug, $slugs, true ) ) {
			return $raw_slug;
		}

		if ( ctype_digit( $raw_slug ) ) {
			$index = (int) $raw_slug;

			if ( isset( $slugs[ $index ] ) ) {
				return $slugs[ $index ];
			}

			if ( $index > 0 && isset( $slugs[ $index - 1 ] ) ) {
				return $slugs[ $index - 1 ];
			}
		}

		$normalized = strtolower( trim( $raw_slug ) );
		foreach ( $available_patterns as $p ) {
			$candidate = strtolower( trim( $p['slug'] ?? '' ) );
			if ( $candidate && $normalized === $candidate ) {
				return $p['slug'];
			}
		}

		return null;
	}
}
