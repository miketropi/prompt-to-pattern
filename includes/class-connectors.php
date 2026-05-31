<?php
/**
 * Connectors — registers optional DeepSeek connector and extensibility filters.
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

use WP_Connector_Registry;

/**
 * Handles connector registration and provider extensibility.
 */
class Connectors {

	/**
	 * Register hooks for connectors and extensibility.
	 */
	public static function register(): void {
		add_action( 'wp_connectors_init', array( static::class, 'register_deepseek_connector' ) );
	}

	/**
	 * Register the DeepSeek connector on the Connectors screen (opt-in).
	 *
	 * This connector only manages the API key credential.
	 * Users must still install the "AI Provider for DeepSeek" plugin
	 * to register DeepSeek as an AI Client provider.
	 *
	 * @param WP_Connector_Registry $registry Connector registry.
	 */
	public static function register_deepseek_connector( WP_Connector_Registry $registry ): void {
		if ( ! self::is_deepseek_connector_enabled() ) {
			return;
		}

		if ( $registry->is_registered( 'deepseek' ) ) {
			return;
		}

		$registry->register(
			'deepseek',
			array(
				'name'           => __( 'DeepSeek', 'prompt-to-pattern' ),
				'description'    => __( 'Text generation with DeepSeek models via an OpenAI-compatible API.', 'prompt-to-pattern' ),
				'type'           => 'ai_provider',
				'plugin'         => array(
					'file'      => 'ai-provider-for-deepseek/ai-provider-for-deepseek.php',
					'is_active' => static function () {
						return has_action( 'init', 'deepseek_register_provider' ) || class_exists( '\DeepSeek\AI_Provider' );
					},
				),
				'authentication' => array(
					'method'          => 'api_key',
					'credentials_url' => 'https://platform.deepseek.com/api_keys',
					'constant_name'   => 'DEEPSEEK_API_KEY',
					'env_var_name'    => 'DEEPSEEK_API_KEY',
				),
			)
		);
	}

	/**
	 * Check if the bundled DeepSeek connector is enabled.
	 *
	 * Site admins can enable/disable this via the WordPress options page
	 * or via the `prompttopattern_enable_deepseek_connector` filter.
	 *
	 * @return bool
	 */
	public static function is_deepseek_connector_enabled(): bool {
		$enabled = (bool) get_option( 'prompttopattern_deepseek_connector', false );

		/**
		 * Filters whether the bundled DeepSeek connector is enabled.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether the bundled connector is enabled. Default false.
		 */
		return (bool) apply_filters( 'prompttopattern_enable_deepseek_connector', $enabled );
	}

}
