<?php
/**
 * Plugin Name:     Prompt to Pattern
 * Plugin URI:      https://example.com/prompt-to-pattern
 * Description:     Compose WordPress pages and templates from block patterns using natural-language prompts and AI.
 * Version:         1.0.0
 * Requires at least: 7.0
 * Requires PHP:    8.1
 * Author:          Nextora
 * Author URI:      https://example.com
 * License:         GPL-2.0-or-later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     prompt-to-pattern
 * Domain Path:     /languages
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

const PLUGIN_FILE    = __FILE__;
const PLUGIN_VERSION = '1.0.0';
const MIN_WP_VERSION = '7.0';
const MIN_PHP_VERSION = '8.1';
const TEXT_DOMAIN    = 'prompt-to-pattern';

require_once __DIR__ . '/includes/class-plugin.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\\bootstrap' );

/**
 * Bootstrap the plugin after all plugins have loaded.
 *
 * Verifies minimum WordPress and PHP versions; shows admin notices
 * if requirements are not met; otherwise initialises the plugin.
 */
function bootstrap(): void {
	if ( ! is_wp_version_compatible() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\render_wp_version_notice' );
		return;
	}

	if ( ! is_php_version_compatible() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\render_php_version_notice' );
		return;
	}

	load_plugin_textdomain( TEXT_DOMAIN, false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );

	Plugin::instance()->init();
}

/**
 * Check whether the current WordPress version meets the minimum requirement.
 */
function is_wp_version_compatible(): bool {
	return version_compare( get_bloginfo( 'version' ), MIN_WP_VERSION, '>=' );
}

/**
 * Check whether the current PHP version meets the minimum requirement.
 */
function is_php_version_compatible(): bool {
	return version_compare( PHP_VERSION, MIN_PHP_VERSION, '>=' );
}

/**
 * Render an admin notice when WordPress version is too old.
 */
function render_wp_version_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: minimum WordPress version */
				__( 'Prompt to Pattern requires WordPress %s or later. Please upgrade WordPress to use this plugin.', 'prompt-to-pattern' ),
				MIN_WP_VERSION
			)
		)
	);
}

/**
 * Render an admin notice when PHP version is too old.
 */
function render_php_version_notice(): void {
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %s: minimum PHP version */
				__( 'Prompt to Pattern requires PHP %s or later. Please upgrade PHP to use this plugin.', 'prompt-to-pattern' ),
				MIN_PHP_VERSION
			)
		)
	);
}
