<?php
/**
 * Admin settings page — opt-in for bundled DeepSeek connector.
 *
 * @package PromptToPattern
 */

namespace PromptToPattern;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the plugin's admin settings.
 */
class Admin_Settings {

	/**
	 * Option name for the DeepSeek connector toggle.
	 */
	private const OPTION_DEEPSEEK = 'prompttopattern_deepseek_connector';

	/**
	 * Register admin hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( static::class, 'add_settings_page' ) );
		add_action( 'admin_init', array( static::class, 'register_settings' ) );
	}

	/**
	 * Add a settings page under Settings → Prompt to Pattern.
	 */
	public static function add_settings_page(): void {
		add_options_page(
			__( 'Prompt to Pattern', 'prompt-to-pattern' ),
			__( 'Prompt to Pattern', 'prompt-to-pattern' ),
			'manage_options',
			'prompt-to-pattern',
			array( static::class, 'render_page' )
		);
	}

	/**
	 * Register the setting.
	 */
	public static function register_settings(): void {
		register_setting(
			'prompt_to_pattern',
			self::OPTION_DEEPSEEK,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Render the settings page.
	 */
	public static function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Prompt to Pattern', 'prompt-to-pattern' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'prompt_to_pattern' ); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<?php esc_html_e( 'Bundled DeepSeek Connector', 'prompt-to-pattern' ); ?>
						</th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_DEEPSEEK ); ?>"
									value="1"
									<?php checked( get_option( self::OPTION_DEEPSEEK, false ) ); ?>
								/>
								<?php esc_html_e( 'Register a DeepSeek connector on the Connectors screen (Settings → Connectors).', 'prompt-to-pattern' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: 1: Link to AI Provider for DeepSeek plugin. */
									esc_html__( 'The connector only stores your API key. You must also install the %s plugin to register DeepSeek as an AI provider.', 'prompt-to-pattern' ),
									'<a href="https://wordpress.org/plugins/ai-provider-for-deepseek/" target="_blank" rel="noopener noreferrer">AI Provider for DeepSeek</a>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Provider Configuration', 'prompt-to-pattern' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: URL to Connectors screen */
					esc_html__( 'After configuring your AI provider plugin, enter your API key on the %s screen.', 'prompt-to-pattern' ),
					'<a href="' . esc_url( admin_url( 'options-general.php?page=connectors' ) ) . '">' . esc_html__( 'Settings → Connectors', 'prompt-to-pattern' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}
}
