<?php
/**
 * Abilities API integration — registers the "compose page from patterns" ability.
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

/**
 * Handles registration of the compose ability via the Abilities API.
 */
class Ability {

	/**
	 * Register the ability category and ability on the appropriate hooks.
	 */
	public static function register(): void {
		add_action( 'wp_abilities_api_categories_init', array( static::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( static::class, 'register_ability' ) );
	}

	/**
	 * Register the ability category.
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			'content-composition',
			array(
				'label'       => __( 'Content Composition', 'prompt-to-pattern' ),
				'description' => __( 'Abilities for composing and generating content using AI.', 'prompt-to-pattern' ),
			)
		);
	}

	/**
	 * Register the compose ability for Prompt to Pattern.
	 */
	public static function register_ability(): void {
		wp_register_ability(
			'prompt-to-pattern/compose-page',
			array(
				'label'               => __( 'Compose Page from Patterns', 'prompt-to-pattern' ),
				'description'         => __( 'Given a natural-language description of a desired page, selects and arranges existing block patterns into a complete page layout.', 'prompt-to-pattern' ),
				'category'            => 'content-composition',
				'execute_callback'    => array( static::class, 'execute' ),
				'permission_callback' => array( static::class, 'check_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'prompt'           => array(
							'type'        => 'string',
							'description' => __( 'Natural-language description of the desired page.', 'prompt-to-pattern' ),
						),
						'target'           => array(
							'type'        => 'string',
							'enum'        => array( 'page_content', 'fse_template' ),
							'description' => __( 'Output target: page content or FSE template.', 'prompt-to-pattern' ),
						),
						'allow_generation' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether AI-generated markup (Mode B) is allowed.', 'prompt-to-pattern' ),
						),
					),
					'required'   => array( 'prompt' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'markup'   => array(
							'type'        => 'string',
							'description' => __( 'Assembled block markup.', 'prompt-to-pattern' ),
						),
						'sections' => array(
							'type'        => 'array',
							'description' => __( 'Resolved pattern sections.', 'prompt-to-pattern' ),
						),
						'unmet'    => array(
							'type'        => 'array',
							'description' => __( 'Unmet section requests.', 'prompt-to-pattern' ),
						),
						'notes'    => array(
							'type'        => 'string',
							'description' => __( 'Layout reasoning notes from the AI.', 'prompt-to-pattern' ),
						),
					),
				),
				'meta'                => array(
					'annotations'   => array(
						'readonly' => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Execute callback — composes a page from a prompt.
	 *
	 * @param array|null $input Input data with 'prompt', 'target', 'allow_generation'.
	 * @return array|\WP_Error Compose result or error.
	 */
	public static function execute( ?array $input = null ): array|\WP_Error {
		$prompt           = $input['prompt'] ?? '';
		$target           = $input['target'] ?? 'page_content';
		$allow_generation = (bool) ( $input['allow_generation'] ?? false );

		if ( empty( trim( $prompt ) ) ) {
			return new \WP_Error(
				'empty_prompt',
				__( 'A prompt is required to compose a page.', 'prompt-to-pattern' ),
				array( 'status' => 400 )
			);
		}

		$context  = new Pattern_Context();
		$catalog  = $context->get_catalog();
		$ai       = new AI_Service();

		$result = $ai->compose(
			$prompt,
			$catalog['patterns'],
			$catalog['design_tokens'],
			$target,
			$allow_generation
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$composer = new Composer();
		return $composer->assemble( $result, $catalog['design_tokens'], $target );
	}

	/**
	 * Permission callback — user must be able to edit posts.
	 *
	 * @param array|null $input Input data (unused for permission).
	 * @return bool|\WP_Error
	 */
	public static function check_permission( ?array $input = null ): bool|\WP_Error {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		return new \WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to compose pages.', 'prompt-to-pattern' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}
}
