<?php
/**
 * Plugin bootstrap class.
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class — singleton that wires up all subsystems.
 */
class Plugin {

	/**
	 * Singleton instance.
	 */
	private static ?self $instance = null;

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Get the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialise all plugin subsystems.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );

		$this->load_includes();

		if ( class_exists( Ability::class ) ) {
			Ability::register();
		}

		if ( class_exists( Connectors::class ) ) {
			Connectors::register();
		}

		if ( class_exists( Admin_Settings::class ) ) {
			Admin_Settings::register();
		}
	}

	/**
	 * Require all PHP class files.
	 */
	private function load_includes(): void {
		$files = array(
			'class-rest-controller.php',
			'class-pattern-context.php',
			'class-ai-service.php',
			'class-composer.php',
			'class-block-validator.php',
			'class-ability.php',
			'class-connectors.php',
			'class-admin-settings.php',
		);

		foreach ( $files as $file ) {
			$path = __DIR__ . '/' . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Register block editor assets for wp-scripts build output.
	 */
	public function register_assets(): void {
		$plugin_dir = plugin_dir_path( PLUGIN_FILE );
		$asset_file = $plugin_dir . 'build/index.asset.php';

		$deps        = array(
			'wp-plugins',
			'wp-components',
			'wp-element',
			'wp-data',
			'wp-api-fetch',
			'wp-i18n',
			'wp-block-editor',
			'wp-editor',
		);
		$asset_ver   = PLUGIN_VERSION;

		if ( file_exists( $asset_file ) ) {
			$assets    = require $asset_file;
			$deps      = array_merge( $deps, $assets['dependencies'] ?? array() );
			$asset_ver = $assets['version'] ?? $asset_ver;
		}

		wp_register_script(
			'prompt-to-pattern-editor',
			plugins_url( 'build/index.js', PLUGIN_FILE ),
			$deps,
			$asset_ver,
			true
		);

		wp_set_script_translations( 'prompt-to-pattern-editor', TEXT_DOMAIN, $plugin_dir . 'languages' );

		if ( file_exists( $plugin_dir . 'build/index.css' ) ) {
			wp_register_style(
				'prompt-to-pattern-editor',
				plugins_url( 'build/index.css', PLUGIN_FILE ),
				array( 'wp-components' ),
				$asset_ver
			);
		}
	}

	/**
	 * Enqueue editor assets.
	 */
	public function enqueue_editor_assets(): void {
		wp_enqueue_script( 'prompt-to-pattern-editor' );
		wp_enqueue_style( 'prompt-to-pattern-editor' );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		if ( class_exists( REST_Controller::class ) ) {
			$controller = new REST_Controller();
			$controller->register_routes();
		}
	}
}
