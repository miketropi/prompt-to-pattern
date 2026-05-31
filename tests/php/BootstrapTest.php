<?php
/**
 * Sample test case.
 *
 * @package PromptToPattern
 * @since   1.0.0
 */

namespace PromptToPattern\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Test that the plugin bootstrap loads without fatal errors.
 */
class BootstrapTest extends TestCase {

	/**
	 * Ensure the main plugin file defines the required constants.
	 */
	public function test_constants_defined(): void {
		$this->assertTrue( defined( 'PromptToPattern\\PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'PromptToPattern\\MIN_WP_VERSION' ) );
		$this->assertTrue( defined( 'PromptToPattern\\MIN_PHP_VERSION' ) );
	}
}
