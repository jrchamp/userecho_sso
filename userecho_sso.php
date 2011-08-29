<?php
/*
Plugin Name: UserEcho SSO Plugin
Version: 0.2
Plugin URI: https://github.com/jrchamp/userecho_sso
Author: Jonathan Champ
Author URI: https://github.com/jrchamp
Description: Allows users to automatically sign in to the associated UserEcho account.
TODO:
 * Feedback link
 * Widget for feedback link?
*/

define( 'UE_SSO_URL', plugins_url() . '/userecho_sso' );

class UserEcho_SSO {
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ), 20 );
	}

	public function add_menu() {
		$options = $this->get_options();
		add_menu_page( 'UserEcho SSO ' . __( 'Options' ), 'UserEcho SSO', 'manage_options', 'userecho_sso', array( $this, 'set_options' ), UE_SSO_URL . '/icon.png' );
	}

	public function set_options() {
		if ( !current_user_can( 'manage_options' ) ) {
			return false;
		}

		$_REQUEST += array( 'ue_action' => '' );
		$action = $_REQUEST['ue_action'];

		if ( $action === 'delete' && check_admin_referer( 'ue-delete-options' ) ) {
			delete_option( 'UserEcho_SSO_options' );
		}

		$options = $this->get_options();

		if ( $action === 'edit' && check_admin_referer( 'userecho_sso' ) ) {
			$orig_options = $options;

			if ( !empty( $_POST['api_key'] ) ) { $options['api_key'] = $_POST['api_key']; }
			if ( !empty( $_POST['project_key'] ) ) { $options['project_key'] = $_POST['project_key']; }
			if ( !empty( $_POST['domain'] ) ) { $options['domain'] = $_POST['domain']; }
			if ( !empty( $_POST['locale'] ) ) { $options['locale'] = $_POST['locale']; }

			if ( $orig_options !== $options ) {
				update_option( 'UserEcho_SSO_options', $options );
			}
		}

		$locale_options = array(
			'nl' => __('Dutch'),
			'en' => __('English'),
			'de' => __('German'),
			'ru' => __('Russian'),
		);
		if ( !empty( $options['locale'] ) ) {
			$locale_options += array( $options['locale'] => 'Custom (' + $options['locale'] + ')' );
		}
		?>

		<div id="icon-options-general" class="icon32"><br /></div>
		<h2>UserEcho SSO: <?php _e('Options'); ?></h2>

		<form method="post" action="">
			<?php wp_nonce_field( 'userecho_sso' ); ?>
			<input type="hidden" name="ue_action" value="edit" />
			<div class='clear metabox-holder'>
			<div class='postbox-container' style='width:49%;'>
			<div id='normal-sortables' class='meta-box-sortables ui-sortable'>
				<div class="postbox">
					<h3><?php _e( 'Configuration', 'UserEchoSSO' ); ?></h3>
					<div class="inside">
						<p class="sub"><?php _e( 'Set the options for your UserEcho account to allow for Single Sign On capabilities.', 'UserEchoSSO' ); ?></p>
						<div class="table">
						<table class="form-table">
						<tbody>
							<tr valign="top">
								<td width="100">
									<label for="api_key"><?php _e( 'API Key', 'UserEchoSSO' ); ?></label>
								</td>
								<td>
									<input id="api_key" name="api_key" type="text" size="50" value="<?php echo esc_attr( $options['api_key'] ); ?>" />
								</td>
							</tr>
							<tr valign="top">
								<td>
									<label for="project_key"><?php _e( 'Project Key', 'UserEchoSSO' ); ?></label>
								</td>
								<td>
									<input id="project_key" name="project_key" type="text" size="50" value="<?php echo esc_attr( $options['project_key'] ); ?>" />
								</td>
							</tr>
							<tr valign="top">
								<td>
									<label for="domain"><?php _e( 'Domain', 'UserEchoSSO' ); ?></label>
								</td>
								<td>
									<input id="domain" name="domain" type="text" size="50" value="<?php echo esc_attr( $options['domain'] ); ?>" />
								</td>
							</tr>
							<tr valign="top">
								<td>
									<label for="locale"><?php _e( 'Locale', 'UserEchoSSO' ); ?></label>
								</td>
								<td>
									<select id="locale" name="locale">
									<?php foreach ( $locale_options as $locale => $label ) {
										echo '<option value="' . esc_attr( $locale ) . '"' . selected( $locale, $options['locale'], false ) . '>' . esc_attr( $label ) . '</option>';
									} ?>
									</select>
								</td>
							</tr>
						</tbody>
						</table>
						</div>
						<p class="submit"><input type="submit" class="button-primary" value="Save Options" /></p>
						<div class="clear"></div>
					</div>
				</div>
				<div class="postbox">
					<h3><?php _e( 'Reset Configuration', 'UserEchoSSO' ); ?></h3>
					<div class="inside">
						<p class="sub"><?php _e( 'Deletes the UserEcho SSO configuration details.', 'UserEchoSSO' ); ?></p>
						<p><a onclick="return confirm('<?php _e( 'Are you sure you want to reset all data?'); ?>')" href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=userecho_sso&ue_action=delete' ), 'ue-delete-options' ); ?>" ><?php _e( 'Delete UserEcho SSO configuration.'); ?></a></p>
					</div>
				</div>
			</div>
			</div>
			</div>
		</form>
		<?php
	}

	// This function returns the current options
	public function get_options() {
		static $default = null;
		if ( !isset( $default ) ) {
			$default = array(
				'api_key' => '',
				'project_key' => 'PROJECT_KEY',
				'domain' => 'PROJECT_KEY.userecho.com',
				'locale' => 'en',
			);
		}

		$saved = get_option( 'UserEcho_SSO_options' );

		$options = array();
		if ( !empty( $saved ) ) {
			$options = $saved;
		}

		$options += $default;

		if ( $saved != $options ) {
			update_option( 'UserEcho_SSO_options', $options );
		}

		return $options;
	}

	private function get_sso_token( $params ) {
		$options = $this->get_options();
		$api_key = $options['api_key']; // Your project personal api key
		$project_key = $options['project_key']; // Your project alias

		$message = $params + array(
			'expires_date' => gmdate( 'Y-m-d H:i:s', time() + 36000 ), // sso_token expiration date in format 'Y-m-d H:i:s'. Recommend set date now() + 10 hours
		);

		// random bytes value, length = 16
		// Recommend use random to generate $iv
		$iv = substr( md5( rand() ), 0, 16 );

		// key hash, length = 16
		$key_hash = substr( hash( 'sha1', $api_key . $project_key, true ), 0, 16 );

		$message_json = json_encode( $message );

		// double XOR first block message_json
		for ( $i = 0; $i < 16; $i++ ) {
			$message_json[$i] = $message_json[$i] ^ $iv[$i];
		}

		// fill tail of message_json by bytes equaled count empty bytes (to 16)
		$pad = 16 - ( strlen( $message_json ) % 16 );
		$message_json = $message_json . str_repeat( chr( $pad ), $pad );

		// encode json
		$cipher = mcrypt_module_open( MCRYPT_RIJNDAEL_128, '', 'cbc', '' );
		mcrypt_generic_init( $cipher, $key_hash, $iv );
		$encrypted_bytes = mcrypt_generic( $cipher, $message_json );
		mcrypt_generic_deinit( $cipher );

		// encode bytes to url safe string
		return urlencode( base64_encode( $encrypted_bytes ) );
	}

	public function sso_login() {
		global $current_user;
		$options = $this->get_options();

		$params = array(
			'guid' => $current_user->user_login, // User ID in your system - used to identify user (first time auto-registration)
			'display_name' => $current_user->display_name, // User display name in your system
			'email' => $current_user->user_email, // User email - used for notification about changes on feedback
			'locale' => $options['locale'], // (Optional) User language override
		);

		$base_url = 'http://' . $options['domain'] . '/';
		if ( isset( $_GET['return'] ) && strpos( $_GET['return'], $base_url ) === 0 ) {
			$base_url = $_GET['return'];
		}

		$redirect = $base_url . '?sso_token=' . $this->get_sso_token( $params );
		header( 'Location: ' . $redirect );
		die();
	}
}

$ue_sso = new UserEcho_SSO();
