<?php
/**
 * WordPress admin UI for Joshternet.
 *
 * @package Joshternet
 * @author  Joshua Morris
 * @copyright 2026 Joshua Morris
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings UI for Joshternet.
 */
final class Joshternet_Admin {
	/**
	 * Register settings and menus.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_privacy_policy_content' ) );
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
	}

	/**
	 * Contribute suggested Privacy Policy Guide text.
	 *
	 * This is suggested language for the site's own privacy policy. It is not
	 * legal advice and does not fetch Joshternet policy pages at runtime.
	 *
	 * @return void
	 */
	public static function register_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$privacy_url = 'https://joshternet.org/privacy/';

		$content  = '<p class="privacy-policy-tutorial">' . esc_html__( 'The following is suggested text you may include in your site Privacy Policy. It is not legal advice.', 'joshternet' ) . '</p>';
		$content .= '<p><strong class="privacy-policy-tutorial">' . esc_html__( 'Suggested text:', 'joshternet' ) . '</strong></p>';
		$content .= '<p>' . esc_html__( 'The Joshternet declaration at /.well-known/josh does not send visitor data to Joshternet.', 'joshternet' ) . '</p>';
		$content .= '<p>' . esc_html__( 'If this site uses the optional [joshternet_button] shortcode, the site\'s WordPress server contacts the Joshternet Button State service to determine which official Joshternet button applies to this origin. That request includes this site\'s normalized public origin. A successful result may be cached locally in WordPress for up to 6 hours. A failed lookup may be cached for 15 minutes.', 'joshternet' ) . '</p>';
		$content .= '<p>' . esc_html__( 'The shortcode uses local button artwork bundled with the plugin. Ordinary visitors do not load remote Joshternet scripts or button images through the shortcode.', 'joshternet' ) . '</p>';
		$content .= '<p>' . sprintf(
			/* translators: %1$s: Joshternet privacy policy URL. */
			__( 'The Joshternet Privacy Policy is available at <a href="%1$s">%1$s</a>.', 'joshternet' ),
			esc_url( $privacy_url )
		) . '</p>';

		wp_add_privacy_policy_content(
			__( 'Joshternet', 'joshternet' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	/**
	 * Register site-local settings.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'joshternet',
			Joshternet_Endpoint::OPTION_ENABLED,
			array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);

		register_setting(
			'joshternet',
			Joshternet_Endpoint::OPTION_IDENTITY,
			array(
				'type'              => 'string',
				'default'           => 'undeclared',
				'sanitize_callback' => array( __CLASS__, 'sanitize_identity' ),
			)
		);
	}

	/**
	 * Add Settings > Joshternet.
	 *
	 * @return void
	 */
	public static function add_settings_page() {
		add_options_page(
			__( 'Joshternet', 'joshternet' ),
			__( 'Joshternet', 'joshternet' ),
			'manage_options',
			'joshternet',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Sanitize the Josh identity setting.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_identity( $value ) {
		$value   = is_string( $value ) ? $value : '';
		$allowed = array( 'undeclared', 'affirmed', 'declined' );

		return in_array( $value, $allowed, true ) ? $value : 'undeclared';
	}

	/**
	 * Render the plugin settings page.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$shared_origin = Joshternet_Origin::shares_origin_with_main_site();
		$enabled       = Joshternet_Endpoint::is_enabled();
		$identity      = (string) get_option( Joshternet_Endpoint::OPTION_IDENTITY, 'undeclared' );
		$endpoint      = Joshternet_Origin::endpoint_url();
		$declaration   = wp_json_encode( Joshternet_Endpoint::declaration(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Joshternet', 'joshternet' ); ?></h1>

			<p>
				<?php echo esc_html__( 'This plugin gives this WordPress site one small way to speak for itself on the web. When enabled, it publishes the Joshternet declaration for this origin at /.well-known/josh.', 'joshternet' ); ?>
			</p>
			<p>
				<?php echo esc_html__( 'Participation and Josh identity are separate. Joining the network does not automatically make anyone a Josh.', 'joshternet' ); ?>
			</p>

			<?php if ( $shared_origin ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php echo esc_html__( 'This subsite shares an origin with the multisite main site. The Joshternet declaration belongs to the origin, so configure it from the main site instead of creating a second declaration for this path.', 'joshternet' ); ?>
					</p>
				</div>
			<?php else : ?>

				<?php if ( Joshternet_Origin::home_is_subdirectory() ) : ?>
					<div class="notice notice-warning inline">
						<p><?php echo esc_html__( 'This site is published below the origin root. The Joshternet endpoint still belongs at /.well-known/josh, so verify that your host routes that exact root request to WordPress before relying on this declaration.', 'joshternet' ); ?></p>
					</div>
				<?php endif; ?>

				<form action="options.php" method="post">
					<?php settings_fields( 'joshternet' ); ?>

					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Joshternet participation', 'joshternet' ); ?></th>
							<td>
								<label>
									<input type="hidden" name="<?php echo esc_attr( Joshternet_Endpoint::OPTION_ENABLED ); ?>" value="0" />
									<input type="checkbox" name="<?php echo esc_attr( Joshternet_Endpoint::OPTION_ENABLED ); ?>" value="1" <?php checked( $enabled ); ?> />
									<?php echo esc_html__( 'Publish the Joshternet declaration for this origin.', 'joshternet' ); ?>
								</label>
								<p class="description"><?php echo esc_html__( 'A valid declaration means this origin is participating in the Joshternet. Turn this off and the plugin stops publishing it.', 'joshternet' ); ?></p>
							</td>
						</tr>

						<tr>
							<th scope="row"><label for="joshternet_identity"><?php echo esc_html__( 'Josh identity', 'joshternet' ); ?></label></th>
							<td>
								<select id="joshternet_identity" name="<?php echo esc_attr( Joshternet_Endpoint::OPTION_IDENTITY ); ?>">
									<option value="undeclared" <?php selected( $identity, 'undeclared' ); ?>><?php echo esc_html__( 'Do not declare Josh identity', 'joshternet' ); ?></option>
									<option value="affirmed" <?php selected( $identity, 'affirmed' ); ?>><?php echo esc_html__( 'Affirm Josh identity', 'joshternet' ); ?></option>
									<option value="declined" <?php selected( $identity, 'declined' ); ?>><?php echo esc_html__( 'Decline Josh identity', 'joshternet' ); ?></option>
								</select>
								<p class="description"><?php echo esc_html__( 'The Joshternet does not infer Joshness from a name, a website, or participation. Joshness is declared, never derived.', 'joshternet' ); ?></p>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Joshternet settings', 'joshternet' ) ); ?>
				</form>

				<hr />

				<h2><?php echo esc_html__( 'What WordPress will publish', 'joshternet' ); ?></h2>
				<p>
					<strong><?php echo esc_html__( 'Endpoint:', 'joshternet' ); ?></strong>
					<?php if ( $endpoint ) : ?>
						<a href="<?php echo esc_url( $endpoint ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( $endpoint ); ?></code></a>
					<?php endif; ?>
				</p>
				<pre><?php echo esc_html( $declaration ); ?></pre>
				<p class="description">
					<?php echo esc_html__( 'The endpoint is virtual. This plugin does not write files into the web root or change Apache or Nginx configuration.', 'joshternet' ); ?>
				</p>

				<hr />

				<h2><?php echo esc_html__( 'Button shortcode', 'joshternet' ); ?></h2>
				<p>
					<?php echo esc_html__( 'The [joshternet_button] shortcode is optional. Place it in a post or page only if you want to show the official Joshternet button.', 'joshternet' ); ?>
				</p>
				<p>
					<?php echo esc_html__( 'When that shortcode needs a button state and there is no usable local cache, WordPress contacts the Joshternet Button State service and sends this site\'s normalized website origin. A successful result is cached locally for up to 6 hours. A failed lookup is cached for 15 minutes. The image is bundled with the plugin; the shortcode does not load a remote image or remote script. Publishing /.well-known/josh does not make this request.', 'joshternet' ); ?>
				</p>
				<p>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: 1: Joshternet privacy policy URL, 2: Joshternet terms URL. */
							__( '<a href="%1$s">Joshternet Privacy Policy</a> and <a href="%2$s">Joshternet Terms of Service</a>.', 'joshternet' ),
							esc_url( 'https://joshternet.org/privacy/' ),
							esc_url( 'https://joshternet.org/terms/' )
						),
						array(
							'a' => array(
								'href' => true,
							),
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
