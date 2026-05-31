<?php
/**
 * REST API controller — exposes /patterns and /compose endpoints.
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST controller for Prompt to Pattern endpoints.
 */
class Rest_Controller extends WP_REST_Controller {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'prompt-to-pattern/v1';

	/**
	 * Resource base for pattern endpoints.
	 *
	 * @var string
	 */
	protected $rest_base = 'patterns';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->namespace = 'prompt-to-pattern/v1';
		$this->rest_base = 'patterns';
	}

	/**
	 * Register routes on rest_api_init.
	 */
	public function register_routes(): void {
		// GET  /wp-json/prompt-to-pattern/v1/patterns
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_patterns' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
			)
		);

		// POST /wp-json/prompt-to-pattern/v1/compose
		register_rest_route(
			$this->namespace,
			'/compose',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'compose' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'args'                => $this->get_compose_args(),
			)
		);
	}

	/**
	 * Permission check: user must be able to edit posts.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error True if allowed, WP_Error otherwise.
	 */
	public function check_edit_permission( WP_REST_Request $request ): true|WP_Error {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'You do not have permission to use Prompt to Pattern.', 'prompt-to-pattern' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * GET /patterns — return the full pattern catalog.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_patterns( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$context = new Pattern_Context();
		$catalog = $context->get_catalog();

		return new WP_REST_Response( $catalog, 200 );
	}

	/**
	 * POST /compose — accept a prompt and return assembled block markup.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function compose( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$prompt           = $request->get_param( 'prompt' );
		$target           = $request->get_param( 'target' ) ?? 'page_content';
		$allow_generation = (bool) ( $request->get_param( 'allow_generation' ) ?? false );

		if ( empty( trim( $prompt ) ) ) {
			return new WP_Error(
				'empty_prompt',
				__( 'A prompt is required.', 'prompt-to-pattern' ),
				array( 'status' => 400 )
			);
		}

		$ai       = new AI_Service();
		$context  = new Pattern_Context();
		$composer = new Composer();
		$catalog  = $context->get_catalog();

		if ( empty( $catalog['patterns'] ) ) {
			return new WP_Error(
				'no_patterns',
				__( 'No block patterns are available on this site. Activate a block theme to enable pattern-based composition.', 'prompt-to-pattern' ),
				array( 'status' => 400 )
			);
		}

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

		$assembled = $composer->assemble( $result, $catalog['design_tokens'], $target );

		if ( is_wp_error( $assembled ) ) {
			return $assembled;
		}

		return new WP_REST_Response( $assembled, 200 );
	}

	/**
	 * Validation schema for the /compose POST endpoint.
	 *
	 * @return array<string, array> Argument schema.
	 */
	private function get_compose_args(): array {
		return array(
			'prompt'           => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_textarea_field',
				'description'       => __( 'Natural-language prompt describing the desired page.', 'prompt-to-pattern' ),
			),
			'target'           => array(
				'required'          => false,
				'type'              => 'string',
				'default'           => 'page_content',
				'enum'              => array( 'page_content', 'fse_template' ),
				'sanitize_callback' => 'sanitize_text_field',
				'description'       => __( 'Output target: page content or FSE template.', 'prompt-to-pattern' ),
			),
			'allow_generation' => array(
				'required'          => false,
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'description'       => __( 'Whether to allow AI-generated markup (Mode B).', 'prompt-to-pattern' ),
			),
		);
	}
}
