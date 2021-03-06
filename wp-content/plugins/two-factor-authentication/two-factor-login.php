<?php
/*
Plugin Name: Two Factor Authentication
Plugin URI: https://www.simbahosting.co.uk/s3/product/two-factor-authentication/
Description: Secure your WordPress login forms with two factor authentication - including WooCommerce login forms
Author: David Anderson, original plugin by Oskar Hane and enhanced by Dee Nutbourne
Author URI: https://www.simbahosting.co.uk
Version: 1.10.4
Text Domain: two-factor-authentication
Domain Path: /languages
License: GPLv2 or later
*/

if (defined('SIMBA_TFA_PLUGIN_DIR') && file_exists(dirname(__FILE__).'/premium.php')) {
	throw new Exception('To activate Two Factor Authentication Premium, first de-activate the free version (only one can be active at once).');
}

define('SIMBA_TFA_PLUGIN_DIR', dirname(__FILE__));
define('SIMBA_TFA_PLUGIN_FILE', __FILE__);
define('SIMBA_TFA_PLUGIN_URL', plugins_url('', __FILE__));

class Simba_Two_Factor_Authentication {

	public $version = '1.10.4';

	private $php_required = '5.6';

	private $frontend;
	
	private $tfa_controller;

	/**
	 * Constructor, run upon plugin initiation
	 */
	public function __construct() {

		if (version_compare(PHP_VERSION, $this->php_required, '<' )) {
			add_action('all_admin_notices', array($this, 'admin_notice_insufficient_php'));
			$abort = true;
		}

		if (!function_exists('mcrypt_get_iv_size') && !function_exists('openssl_cipher_iv_length')) {
			add_action('all_admin_notices', array($this, 'admin_notice_missing_mcrypt_and_openssl'));
			$abort = true;
		}

		if (!empty($abort)) return;

		if (file_exists(SIMBA_TFA_PLUGIN_DIR.'/premium.php')) include_once(SIMBA_TFA_PLUGIN_DIR.'/premium.php');

		require_once(SIMBA_TFA_PLUGIN_DIR.'/providers/totp-hotp.php');
		$this->tfa_controller = new Simba_TFA_Provider_TOTP();
		
		// Process login form AJAX events
		add_action('wp_ajax_nopriv_simbatfa-init-otp', array($this, 'tfaInitLogin'));
		add_action('wp_ajax_simbatfa-init-otp', array($this, 'tfaInitLogin'));

		add_action('wp_ajax_simbatfa_shared_ajax', array($this, 'shared_ajax'));

		require_once(SIMBA_TFA_PLUGIN_DIR.'/includes/login-form-integrations.php');
		
		new Simba_TFA_Login_Form_Integrations($this);
		
		require_once(SIMBA_TFA_PLUGIN_DIR.'/includes/admin-dashboard-integration.php');
		
		new Simba_TFA_Admin_Dashboard_Integration($this);
		
		if (is_admin()) {
			// Save settings
			add_action('admin_init', array($this, 'check_possible_reset'));
		} else {
			add_action('init', array($this, 'check_possible_reset'));
		}

		add_action('plugins_loaded', array($this, 'plugins_loaded'));
		
		add_action('init', array($this, 'init'));

		// Show off-sync message for hotp
		add_action('admin_notices', array($this, 'tfa_show_hotp_off_sync_message'));
		
		if (!defined('TWO_FACTOR_DISABLE') || !TWO_FACTOR_DISABLE) {
			add_filter('authenticate', array($this, 'tfaVerifyCodeAndUser'), 99999999999, 3);
		}

		if (file_exists(SIMBA_TFA_PLUGIN_DIR.'/updater.php')) include_once(SIMBA_TFA_PLUGIN_DIR.'/updater.php');

		if (defined('DOING_AJAX') && DOING_AJAX && defined('WP_ADMIN') && WP_ADMIN && !empty($_REQUEST['action']) && 'simbatfa-init-otp' == $_REQUEST['action']) {
			// Try to prevent PHP notices breaking the AJAX conversation
			$this->output_buffering = true;
			$this->logged = array();
			set_error_handler(array($this, 'get_php_errors'), E_ALL & ~E_STRICT);
			ob_start();
		}

	}

	/**
	 * Get the user capability needed for managing TFA users.
	 * You'll want to think carefully about changing this to a non-admin, as it can give the ability to lock admins out (though, if you have FTP/files access, you can always disable TFA or any plugin)
	 *
	 * @return String
	 */
	public function get_management_capability() {
		return apply_filters('simba_tfa_management_capability', 'manage_options');
	}
	
	/**
	 * Used with set_error_handler()
	 *
	 * @param Integer $errno
	 * @param String  $errstr
	 * @param String  $errfile
	 * @param Integer $errline
	 *
	 * @return Boolean
	 */
	public function get_php_errors($errno, $errstr, $errfile, $errline) {
		if (0 == error_reporting()) return true;
		$logline = $this->php_error_to_logline($errno, $errstr, $errfile, $errline);
		$this->logged[] = $logline;
		# Don't pass it up the chain (since it's going to be output to the user always)
		return true;
	}

	public function php_error_to_logline($errno, $errstr, $errfile, $errline) {
		switch ($errno) {
			case 1:		$e_type = 'E_ERROR'; break;
			case 2:		$e_type = 'E_WARNING'; break;
			case 4:		$e_type = 'E_PARSE'; break;
			case 8:		$e_type = 'E_NOTICE'; break;
			case 16:	$e_type = 'E_CORE_ERROR'; break;
			case 32:	$e_type = 'E_CORE_WARNING'; break;
			case 64:	$e_type = 'E_COMPILE_ERROR'; break;
			case 128:	$e_type = 'E_COMPILE_WARNING'; break;
			case 256:	$e_type = 'E_USER_ERROR'; break;
			case 512:	$e_type = 'E_USER_WARNING'; break;
			case 1024:	$e_type = 'E_USER_NOTICE'; break;
			case 2048:	$e_type = 'E_STRICT'; break;
			case 4096:	$e_type = 'E_RECOVERABLE_ERROR'; break;
			case 8192:	$e_type = 'E_DEPRECATED'; break;
			case 16384:	$e_type = 'E_USER_DEPRECATED'; break;
			case 30719:	$e_type = 'E_ALL'; break;
			default:	$e_type = "E_UNKNOWN ($errno)"; break;
		}

		if (!is_string($errstr)) $errstr = serialize($errstr);

		if (0 === strpos($errfile, ABSPATH)) $errfile = substr($errfile, strlen(ABSPATH));

		return "PHP event: code $e_type: $errstr (line $errline, $errfile)";

	}

	/**
	 * Runs upon the WordPress 'init' action
	 */
	public function init() {
		if ((!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) && is_user_logged_in() && file_exists(SIMBA_TFA_PLUGIN_DIR.'/includes/tfa_frontend.php')) {
			$this->load_frontend();
		} else {
			add_shortcode('twofactor_user_settings', array($this, 'shortcode_when_not_logged_in'));
		}
	}

	/**
	 * Runs conditionally on the WP action all_admin_notices
	 */
	public function admin_notice_insufficient_php() {
		$this->show_admin_warning('<strong>'.__('Higher PHP version required', 'two-factor-authentication').'</strong><br> '.sprintf(__('The Two Factor Authentication plugin requires PHP version %s or higher - your current version is only %s.', 'two-factor-authentication'), $this->php_required, PHP_VERSION), 'error');
	}

	/**
	 * Runs conditionally on the WP action all_admin_notices
	 */
	public function admin_notice_missing_mcrypt_and_openssl() {
		$this->show_admin_warning('<strong>'.__('PHP OpenSSL or mcrypt module required', 'two-factor-authentication').'</strong><br> '.__('The Two Factor Authentication plugin requires either the PHP openssl (preferred) or mcrypt module to be installed. Please ask your web hosting company to install one of them.', 'two-factor-authentication'), 'error');
	}

	/**
	 * Paint out an admin notice
	 *
	 * @param String $message - the caller should already have taken care of escaping
	 * @param String $class
	 */
	public function show_admin_warning($message, $class = 'updated') {
		echo '<div class="tfamessage '.$class.'">'."<p>$message</p></div>";
	}

	/**
	 * Return a new Simba_TFA object. Legacy method.
	 *
	 * @returns Simba_TFA
	 */
	public function getTFA() {
		return $this->tfa_controller->get_simba_tfa();
	}

	// "Shared" - i.e. could be called from either front-end or back-end
	public function shared_ajax() {
		if (empty($_POST['subaction']) || empty($_POST['nonce']) || !is_user_logged_in() || !wp_verify_nonce($_POST['nonce'], 'tfa_shared_nonce')) die('Security check (3).');

		global $current_user;

		if ('refreshotp' == $_POST['subaction']) {

			$code = $this->tfa_controller->get_current_code($current_user->ID);
		
			if (false === $code) die(json_encode(array('code' => '')));

			die(json_encode(array('code' => $code)));
			
		} elseif ('untrust_device' == $_POST['subaction'] && isset($_POST['device_id'])) {
			do_action('simba_tfa_untrust_device', stripslashes($_POST['device_id']));
		}
		
		exit;

	}

	/**
	 * Called upon the AJAX action simbatfa-init-otp . Will die.
	 *
	 * Uses these keys from $_POST: user
	 */
	public function tfaInitLogin() {

		if (empty($_POST['user'])) die('Security check (2).');

		$tfa = $this->getTFA();
		
		if (defined('TWO_FACTOR_DISABLE') && TWO_FACTOR_DISABLE) {
			$res = array('result' => false, 'user_can_trust' => false);
		} else {
		
			if (!function_exists('sanitize_user')) require_once ABSPATH.WPINC.'/formatting.php';
		
			// WP's password-checking sanitizes the supplied user, so we must do the same to check if TFA is enabled for them
			$auth_info = array('log' => sanitize_user(stripslashes((string)$_POST['user'])));
		
			if (!empty($_COOKIE['simbatfa_trust_token'])) $auth_info['trust_token'] = (string) $_COOKIE['simbatfa_trust_token'];
		
			$res = $tfa->preAuth($auth_info, 'array');
		}

		$results = array(
			'jsonstarter' => 'justhere',
			'status' => $res['result'],
		);
		
		if (!empty($res['user_can_trust'])) {
			$results['user_can_trust'] = 1;
			if (!empty($res['user_already_trusted'])) $results['user_already_trusted'] = 1;
		}


		if (!empty($this->output_buffering)) {
			if (!empty($this->logged)) {
				$results['php_output'] = $this->logged;
			}
			restore_error_handler();
			$buffered = ob_get_clean();
			if ($buffered) $results['extra_output'] = $buffered;
		}

		$results = apply_filters('simbatfa_check_tfa_requirements_ajax_response', $results);
		
		echo json_encode($results);
		
		exit;
	}
	
	/**
	 * Here's where the login action happens. Called on the WP 'authenticate' action.
	 *
	 * @param WP_Error|WP_User $user
	 * @param String		   $username - this is not necessarily the WP username; it is whatever was typed in the form, so can be an email address
	 * @param String		   $password
	 *
	 * @return WP_Error|WP_User
	 */
	public function tfaVerifyCodeAndUser($user, $username, $password) {

		$original_user = $user;
		$params = stripslashes_deep($_POST);

		// If (only) the error was a wrong password, but it looks like the user appended a TFA code to their password, then have another go
		if (is_wp_error($user) && array('incorrect_password') == $user->get_error_codes() && !isset($params['two_factor_code']) && false !== ($from_password = apply_filters('simba_tfa_tfa_from_password', false, $password))) {
			// This forces a new password authentication below
			$user = false;
		}
	
		if (is_wp_error($user)) {
			$ret = $user;
		} else {

			$tfa = $this->getTFA();
			
			if (is_object($user) && isset($user->ID) && isset($user->user_login)) {
				$params['log'] = $user->user_login;
				// Confirm that this is definitely a username regardless of its format
				$may_be_email = false;
			} else {
				$params['log'] = $username;
				$may_be_email = true;
			}
			
			$params['caller'] = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['REQUEST_URI'];
			if (!empty($_COOKIE['simbatfa_trust_token'])) $params['trust_token'] = (string) $_COOKIE['simbatfa_trust_token'];

			if (isset($from_password) && false !== $from_password) {
				// Support login forms that can't be hooked via appending to the password
				$speculatively_try_appendage = true;
				$params['two_factor_code'] = $from_password['tfa_code'];
			}

			$code_ok = $tfa->authUserFromLogin($params, $may_be_email);

			if (is_wp_error($code_ok)) {
				$ret = $code_ok;
			} elseif (!$code_ok) {
				$ret =  new WP_Error('authentication_failed', '<strong>'.__('Error:', 'two-factor-authentication').'</strong> '.__('The one-time password (TFA code) you entered was incorrect.', 'two-factor-authentication'));
			} elseif ($user) {
				$ret = $user;
			} else {
			
				if (!empty($speculatively_try_appendage) && true === $code_ok) {
					$password = $from_password['password'];
				}

				$username_is_email = false;
				
				if (function_exists('wp_authenticate_username_password') && $may_be_email && filter_var($username, FILTER_VALIDATE_EMAIL)) {
					global $wpdb;
					// This has to match Simba_TFA::authUserFromLogin()
					$response = $wpdb->get_row($wpdb->prepare("SELECT ID, user_registered from ".$wpdb->users." WHERE user_email=%s", $username));
					if (is_object($response)) $username_is_email = true;
				}

				$ret = $username_is_email ? wp_authenticate_email_password(null, $username, $password) : wp_authenticate_username_password(null, $username, $password);
			}
			
		}
		
		$ret = apply_filters('simbatfa_verify_code_and_user_result', $ret, $original_user, $username, $password);

		// If the TFA code was actually validated (not just not required, for example), then $code_ok is (boolean)true
		if (isset($code_ok) && true === $code_ok && is_a($ret, 'WP_User')) {
			if (!empty($params['simba_tfa_mark_as_trusted']) && $tfa->user_can_trust($ret->ID) && (is_ssl() || (!empty($_SERVER['SERVER_NAME']) && ('localhost' == $_SERVER['SERVER_NAME'] ||'127.0.0.1' == $_SERVER['SERVER_NAME'])))) {
		
				$trusted_for = $this->get_option('tfa_trusted_for');
				$trusted_for = (false === $trusted_for) ? 30 : (string) absint($trusted_for);
			
				$tfa->trust_device($ret->ID, $trusted_for);
			}
		}
		
		return $ret;
	}
	
	/**
	 * Print the radio buttons for enabling/disabling TFA
	 *
	 * @param Integer $user_id	  - the WordPress user ID
	 * @param Boolean $long_label - whether to use a long label rather than a short one
	 * @param String  $style	  - valid values are "show_current" and "require_current"
	 */ 
	public function tfaListEnableRadios($user_id, $long_label = false, $style = 'show_current') {

		if (!$user_id) return;
			
		$setting = get_user_meta($user_id, 'tfa_enable_tfa', true);
		$setting = $setting ? $setting : false;

		if ('require_current' != $style) $style = 'show_current';
		
		$tfa = $this->getTFA();

		$is_required = $tfa->isRequiredForUser($user_id);
		
		if ($is_required) {
			$requireafter = absint($this->get_option('tfa_requireafter'));
			echo '<p class="tfa_required_warning" style="font-weight:bold; font-style:italic;">'.sprintf(__('N.B. This site is configured to forbid you to log in if you disable two-factor authentication after your account is %d days old', 'two-factor-authentication'), $requireafter).'</p>';
		}

		$tfa_enabled_label = $long_label ? __('Enable two-factor authentication', 'two-factor-authentication') : __('Enabled', 'two-factor-authentication');
		
		if ('show_current' == $style) {
			$tfa_enabled_label .= ' '.sprintf(__('(Current code: %s)', 'two-factor-authentication'), $this->current_otp_code($tfa, $user_id));
		} elseif ('require_current' == $style) {
			$tfa_enabled_label .= ' '.sprintf(__('(you must enter the current code: %s)', 'two-factor-authentication'), '<input type="text" class="tfa_enable_current" name="tfa_enable_current" size="6" style="height">');
		}
		
		$show_disable = ((is_multisite() && is_super_admin()) || (!is_multisite() && current_user_can($this->get_management_capability())) || false == $setting || !$is_required || !$this->get_option('tfa_hide_turn_off')) ? true : false;
		
		$tfa_disabled_label = $long_label ? __('Disable two-factor authentication', 'two-factor-authentication') : __('Disabled', 'two-factor-authentication');

		if ('require_current' == $style) echo '<input type="hidden" name="require_current" value="1">'."\n";
		
		echo '<input type="radio" class="tfa_enable_radio" id="tfa_enable_tfa_true" name="tfa_enable_tfa" value="true" '.(true == $setting ? 'checked="checked"' : '').'> <label class="tfa_enable_radio_label" for="tfa_enable_tfa_true">'.apply_filters('simbatfa_radiolabel_enabled', $tfa_enabled_label, $long_label).'</label> <br>';

		// Show the 'disabled' option if the user is an admin, or if it is currently set, or if TFA is not compulsory, or if the site owner doesn't require it to be hidden
		// Note that this just hides the option in the UI. The user could POST to turn off TFA, but, since it's required, they won't be able to log in.
		if ($show_disable) {
			echo '<input type="radio" class="tfa_enable_radio" id="tfa_enable_tfa_false" name="tfa_enable_tfa" value="false" '.(false == $setting ? 'checked="checked"' :'').'> <label class="tfa_enable_radio_label" for="tfa_enable_tfa_false">'.apply_filters('simbatfa_radiolabel_disabled', $tfa_disabled_label, $long_label).'</label> <br>';
		}
	}

	public function tfaListAlgorithmRadios($user_id) {
		if (!$user_id) return;
				
		$types = array('totp' => __('TOTP (time based - most common algorithm; used by Google Authenticator)', 'two-factor-authentication'), 'hotp' => __('HOTP (event based)', 'two-factor-authentication')); 
		
		$setting = get_user_meta($user_id, 'tfa_algorithm_type', true);
		$setting = $setting === false || !$setting ? 'totp' : $setting;

		foreach($types as $id => $name) {
			print '<input type="radio" id="tfa_algorithm_type_'.esc_attr($id).'" name="tfa_algorithm_type" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> <label for="tfa_algorithm_type_'.esc_attr($id).'">'.$name."</label><br>\n";
		}
	}

	/**
	 * Retrieve a saved option
	 *
	 * @param String $key - option key
	 *
	 * @return Mixed
	 */
	public function get_option($key) {
		if (!is_multisite()) return get_option($key);
		switch_to_blog(1);
		$v = get_option($key);
		restore_current_blog();
		return $v;
	}

	/**
	 * Paint a list of checkboxes, one for each role
	 *
	 * @param String  $prefix
	 * @param Integer $default - default value (0 or 1)
	 */
	public function list_user_roles_checkboxes($prefix = '', $default = 1) {

		if (is_multisite()) {
			// Not a real WP role; needs separate handling
			$id = '_super_admin';
			$name = __('Multisite Super Admin', 'two-factor-authentication');
			$setting = $this->get_option('tfa_'.$prefix.$id);
			$setting = ($setting === false) ? $default : ($setting ? 1 : 0);
			
			echo '<input type="checkbox" id="tfa_'.$prefix.$id.'" name="tfa_'.$prefix.$id.'" value="1" '.($setting ? 'checked="checked"' :'').'> <label for="tfa_'.$prefix.$id.'">'.htmlspecialchars($name)."</label><br>\n";
		}

		global $wp_roles;
		if (!isset($wp_roles)) $wp_roles = new WP_Roles();
		
		foreach ($wp_roles->role_names as $id => $name) {
			$setting = $this->get_option('tfa_'.$prefix.$id);
			$setting = ($setting === false) ? $default : ($setting ? 1 : 0);
			
			echo '<input type="checkbox" id="tfa_'.$prefix.$id.'" name="tfa_'.$prefix.$id.'" value="1" '.($setting ? 'checked="checked"' :'').'> <label for="tfa_'.$prefix.$id.'">'.htmlspecialchars($name)."</label><br>\n";
		}
		
	}

	public function tfaListDefaultHMACRadios() {
		$tfa = $this->getTFA();
		$setting = $this->get_option('tfa_default_hmac');
		$setting = $setting === false || !$setting ? $tfa->default_hmac : $setting;
		
		$types = array('totp' => __('TOTP (time based - most common algorithm; used by Google Authenticator)', 'two-factor-authentication'), 'hotp' => __('HOTP (event based)', 'two-factor-authentication'));
		
		foreach($types as $id => $name)
			print '<input type="radio" id="tfa_default_hmac_'.esc_attr($id).'" name="tfa_default_hmac" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> '.'<label for="tfa_default_hmac_'.esc_attr($id).'">'."$name</label><br>\n";
	}

	public function tfaListXMLRPCStatusRadios() {
		$tfa = $this->getTFA();
		$setting = $this->get_option('tfa_xmlrpc_on');
		$setting = $setting === false || !$setting ? 0 : 1;
		
		$types = array(
			'0' => __('Do not require 2FA over XMLRPC (best option if you must use XMLRPC and your client does not support 2FA)', 'two-factor-authentication'),
			'1' => __('Do require 2FA over XMLRPC (best option if you do not use XMLRPC or are unsure)', 'two-factor-authentication')
		);
		
		foreach($types as $id => $name)
			print '<input type="radio" name="tfa_xmlrpc_on" id="tfa_xmlrpc_on_'.$id.'" value="'.$id.'" '.($setting == $id ? 'checked="checked"' :'').'> <label for="tfa_xmlrpc_on_'.$id.'">'.$name."</label><br>\n";
	}

	/**
	 * Runs upon the WP 'init' action
	 */
	public function check_possible_reset() {
		if(!empty($_GET['simbatfa_priv_key_reset']) && !empty($_REQUEST['nonce']) && wp_verify_nonce($_REQUEST['nonce'], 'simbatfa_reset_private_key')) {
			$this->reset_private_key_and_emergency_codes();
			exit;
		}
	}

	/**
	 * Remove private key and emergency codes for the specified (or logged-in) user
	 *
	 * @param Boolean|Integer $user_id	- WP user ID, or false for the currently logged-in user
	 * @param Boolean|Null	  $redirect - if this is not false, then a redirection will occur - where to depends upon the value of $_REQUEST['noredirect']
	 */
	public function reset_private_key_and_emergency_codes($user_id = false, $redirect = null) {
	
		if (!$user_id) {
			global $current_user;
			$user_id = $current_user->ID;
		}
		
		delete_user_meta($user_id, 'tfa_priv_key_64');
		delete_user_meta($user_id, 'simba_tfa_emergency_codes_64');
		
		if (false === $redirect) return;
		
		if (empty($_REQUEST['noredirect'])) {
			wp_safe_redirect(admin_url('admin.php').'?page=two-factor-auth-user&settings-updated=1');
		} else {
			$url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . remove_query_arg(array('simbatfa_priv_key_reset', 'noredirect', 'nonce'));
			wp_redirect(esc_url_raw($url));
		}
	}

	public function reset_link($admin = true) {

		$url_base = $admin ? admin_url('admin.php').'?page=two-factor-auth-user&settings-updated=1' : (( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST']);

		$add_query_args = array('simbatfa_priv_key_reset' => 1);
		
		if (!$admin) $add_query_args['noredirect'] = 1;

		$url = $url_base.add_query_arg($add_query_args);

		$url = wp_nonce_url($url, 'simbatfa_reset_private_key', 'nonce');

		return '<a href="javascript:if(confirm(\''.__('Warning: if you reset this key you will have to update your apps with the new one. Are you sure you want this?', 'two-factor-authentication').'\')) { window.location = \''.esc_js($url).'\'; }">'.__('Reset private key', 'two-factor-authentication').'</a>';

	}

	/**
	 * Runs upon the WP actions wp_footer and admin_footer
	 */
	public function footer() {
		$ajax_url = admin_url('admin-ajax.php');
		// It's possible that FORCE_ADMIN_SSL will make that SSL, whilst the user is on the front-end having logged in over non-SSL - and as a result, their login cookies won't get sent, and they're not registered as logged in.
		if (!is_admin() && substr(strtolower($ajax_url), 0, 6) == 'https:' && !is_ssl()) {
			$also_try = 'http:'.substr($ajax_url, 6);
		}
		?>
		<script>
			jQuery(function($) {
			
				// Render any QR codes
				$('.simbaotp_qr_container').qrcode({
					'render': 'image',
					'text': $('.simbaotp_qr_container:first').data('qrcode'),
				});
				
				function update_otp_code() {
					$('.simba_current_otp').html('<em><?php echo esc_attr(__('Updating...', 'two-factor-authentication'));?></em>');
					
					$.post('<?php echo esc_js($ajax_url);?>', {
						action: 'simbatfa_shared_ajax',
						subaction: 'refreshotp',
						nonce: '<?php echo esc_js(wp_create_nonce('tfa_shared_nonce'));?>'
					}, function(response) {
						var got_code = '';
						try {
							var resp = JSON.parse(response);
							got_code = resp.code;
						} catch(err) {
							<?php if (!isset($also_try)) { ?>
							alert("<?php echo esc_js(__('Response:', 'two-factor-authentication')); ?> "+response);
							<?php } ?>
							console.log(response);
							console.log(err);
						}
						<?php
							if (isset($also_try)) {
							?>
							$.post('<?php echo esc_js($also_try);?>', {
								action: "simbatfa_shared_ajax",
								subaction: "refreshotp",
								nonce: "<?php echo esc_js(wp_create_nonce("tfa_shared_nonce"));?>"
							}, function(response) {
								try {
									var resp = JSON.parse(response);
									if (resp.code) {
										$('.simba_current_otp').html(resp.code);
									} else {
										console.log(response);
										console.log("TFA: no code found");
									}
								} catch(err) {
									alert("<?php echo esc_js(__('Response:', 'two-factor-authentication')); ?> "+response);
									console.log(response);
									console.log(err);
								}
							});
							<?php } else { ?>
						if ('' != got_code) {
							$('.simba_current_otp').html(got_code);
						} else {
							console.log("TFA: no code found");
						}
						<?php } ?>
					});
				}
				
				var min_refresh_after = 30;
				
				if (0 == $('body.settings_page_two-factor-auth').length) {
					$('.simba_current_otp').each(function(ind, obj) {
						var refresh_after = $(obj).data('refresh_after');
						if (refresh_after > 0 && refresh_after < min_refresh_after) {
							min_refresh_after = refresh_after;
						}
					});
				
					// Update after the given seconds, and then every 30 seconds
					setTimeout(function() {
						setInterval(update_otp_code, 30000)
						update_otp_code();
					}, min_refresh_after * 1000);
				}
					
				// Handle clicks on the 'refresh' link
				$('.simbaotp_refresh').on('click', function(e) {
					e.preventDefault();
					update_otp_code();
				});
				
				$('#tfa_trusted_devices_box').on('click', '.simbatfa-trust-remove', function(e) {
					e.preventDefault();
					var device_id = $(this).data('trusted-device-id');
					$(this).parents('.simbatfa_trusted_device').css('opacity', '0.5');
					if ('undefined' !== typeof device_id) {
						$.post('<?php echo esc_js($ajax_url);?>', {
							action: 'simbatfa_shared_ajax',
							subaction: 'untrust_device',
							nonce: '<?php echo esc_js(wp_create_nonce('tfa_shared_nonce'));?>',
							device_id: device_id
						}, function(response) {
							var resp = JSON.parse(response);
							if (resp.hasOwnProperty('trusted_list')) {
								$('#tfa_trusted_devices_box_inner').html(resp.trusted_list);
							}
						});
					}
				});
			});
		</script>
		<?php
	}

	public function print_private_keys($admin, $type = 'full', $user_id = false) {

		$tfa = $this->getTFA();
		global $current_user;

		if ($user_id == false) $user_id = $current_user->ID;

		$tfa_priv_key_64 = get_user_meta($user_id, 'tfa_priv_key_64', true);
		if (!$tfa_priv_key_64) $tfa_priv_key_64 = $tfa->addPrivateKey($user_id);

		$tfa_priv_key = trim($tfa->getPrivateKeyPlain($tfa_priv_key_64, $user_id), "\x00..\x1F");

		$tfa_priv_key_32 = Base32::encode($tfa_priv_key);

		if ('full' == $type) {
			?>
			<strong><?php echo __('Private key (base 32 - used by Google Authenticator and Authy):', 'two-factor-authentication');?></strong>
			<?php echo htmlspecialchars($tfa_priv_key_32); ?><br>

			<strong><?php echo __('Private key:', 'two-factor-authentication');?></strong>
			<?php echo htmlspecialchars($tfa_priv_key); ?><br>
			<?php
		} elseif ('plain' == $type) {
			echo htmlspecialchars($tfa_priv_key);
		} elseif ('base32' == $type) {
			echo htmlspecialchars($tfa_priv_key_32);
		} elseif ('base64' == $type) {
			echo htmlspecialchars($tfa_priv_key_64);
		}
	}

	/**
	 * Return an HTML snippet for the current OTP code
	 *
	 * @param Simba_TFA		  $tfa
	 * @param Integer|Boolean $user_id
	 *
	 * @return String
	 */
	public function current_otp_code($tfa, $user_id = false) {
		global $current_user;
		if (false == $user_id) $user_id = $current_user->ID;
		$tfa_priv_key_64 = get_user_meta($user_id, 'tfa_priv_key_64', true);
		if (!$tfa_priv_key_64) $tfa_priv_key_64 = $tfa->addPrivateKey($user_id);
		$time_now = time();
		$refresh_after = 30 - ($time_now % 30);
		return '<span class="simba_current_otp" data-refresh_after="'.$refresh_after.'">'.$tfa->generateOTP($user_id, $tfa_priv_key_64).'</span>';
	}

	public function add_footer($admin) {
		static $added_footer;
		if (empty($added_footer)) {
			$added_footer = true;
			$script_ver = (defined('WP_DEBUG') && WP_DEBUG) ? time() : $this->version;
			$script_file = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? 'jquery-qrcode.js' : 'jquery-qrcode.min.js';
			wp_enqueue_script('jquery-qrcode', SIMBA_TFA_PLUGIN_URL.'/includes/jquery-qrcode/'.$script_file, array('jquery'), $script_ver);
			add_action($admin ? 'admin_footer' : 'wp_footer', array($this, 'footer'));
		}
	}

	public function current_codes_box($admin = true, $user_id = false) {

		global $current_user;

		if (false == $user_id) $user_id = $current_user->ID;

		$tfa = $this->getTFA();

		$this->add_footer($admin);

		$url = preg_replace('/^https?:\/\//', '', site_url());
		
		$tfa_priv_key_64 = get_user_meta($user_id, 'tfa_priv_key_64', true);
		
		if (!$tfa_priv_key_64) $tfa_priv_key_64 = $tfa->addPrivateKey($user_id);

		$tfa_priv_key = trim($tfa->getPrivateKeyPlain($tfa_priv_key_64, $user_id), "\x00..\x1F");

		$tfa_priv_key_32 = Base32::encode($tfa_priv_key);

		$algorithm_type = $tfa->getUserAlgorithm($user_id);

		if ($admin) {
			if ($current_user->ID == $user_id) {
				if (!is_admin()) echo '<h2>'.__('Current codes', 'two-factor-authentication').'</h2>';
			} else {
				$user = get_user_by('id', $user_id);
				$user_descrip = htmlspecialchars($user->user_nicename.' - '.$user->user_email);
				echo '<h2>'.sprintf(__('Current codes (login: %s)', 'two-factor-authentication'), $user_descrip).'</h2>';
			}
		} else {
// 			echo '<h2>'.__('Current one-time password', 'two-factor-authentication').' '.$this->reset_current_otp_link().'</h2>';
		}

		?>
		<div class="postbox" style="clear:both;">

			<?php if ($admin) { ?>
				<h3 style="padding: 10px 6px 0px; margin:4px 0 0; cursor: default;">
					<span style="cursor: default;"><?php echo __('Current one-time password', 'two-factor-authentication').' ';
					if ($current_user->ID == $user_id) { echo $this->reset_current_otp_link(); } ?>
					</span>
					<div class="inside">
						<p><strong style="font-size: 3em;"><?php echo $this->current_otp_code($tfa, $user_id); ?></strong></p>
					</div>
				</h3>
			<?php } else {
				?>
				<div class="inside">
					<p class="simbatfa-frontend-current-otp" style="font-size: 1.5em; margin-top:6px;">
					<strong>
						<?php echo __('Current one-time password', 'two-factor-authentication').' '.$this->reset_current_otp_link(); ?>
					</strong> :

					<?php
					$time_now = time();
					$refresh_after = 30 - ($time_now % 30);
					
					?><span class="simba_current_otp" data-refresh_after="<?php echo $refresh_after; ?>"><?php print $tfa->generateOTP($user_id, $tfa_priv_key_64); ?></span>
			
					</p>
				</div>

			<?php } ?>

			<?php if ($admin) { ?>
				<h3 style="padding-left: 10px; cursor: default;">
					<span style="cursor: default;"><?php _e('Setting up - either scan the code, or type in the private key', 'two-factor-authentication'); ?></span>
				</h3>
			<?php } else {
				echo '<h2>'.__('Setting up', 'two-factor-authentication').'</h2>';
			} ?>
			<div class="inside">
					<p>
					<?php
						_e('For OTP apps that support using a camera to scan a setup code (below), that is the quickest way to set the app up (e.g. with Duo Mobile, Google Authenticator).', 'two-factor-authentication');
						echo ' ';
						_e('Otherwise, you can type the textual private key (shown below) into your app. Always keep private keys secret.', 'two-factor-authentication');
					?>
					
					<?php printf(__('You are currently using %s, %s', 'two-factor-authentication'),  strtoupper($algorithm_type), ($algorithm_type == 'totp') ? __('a time based algorithm', 'two-factor-authentication') : __('an event based algorithm', 'two-factor-authentication')); ?>.
					</p>
<!-- 					<p title="<?php echo sprintf(__("Private key: %s (base 32: %s)", 'two-factor-authentication'), $tfa_priv_key, $tfa_priv_key_32);?>"> -->
					<?php $qr_url = $this->tfa_qr_code_url($algorithm_type, $url, $tfa_priv_key, $user_id) ?>
					<div style="float: left; padding-right: 20px;" class="simbaotp_qr_container" data-qrcode="<?php echo esc_attr($qr_url); ?>"></div>
					
				<p>
					<?php
						$this->print_private_keys($admin, 'full', $user_id);
						if ($current_user->ID == $user_id) {
							echo $this->reset_link($admin);
						} else {
							echo '<a id="tfa-reset-privkey-for-user" data-user_id="'.$user_id.'" href="#">'.__('Reset private key', 'two-factor-authentication').'</a>';
						}
					?>
				</p>

			<?php
				if ($admin || apply_filters('simba_tfa_emergency_codes_user_settings', false, $user_id) !== false) {
			?>
			

				<div style="min-height: 100px;">
					<h3 class="normal" style="cursor: default"><?php _e('Emergency codes', 'two-factor-authentication'); ?></h3>
					<?php
							$default_text = '<a href="https://www.simbahosting.co.uk/s3/product/two-factor-authentication/">'.__('One-time emergency codes are a feature of the Premium version of this plugin.', 'two-factor-authentication').'</a>';
							echo apply_filters('simba_tfa_emergency_codes_user_settings', $default_text, $user_id);
						?>
				</div>

			<?php } ?>
			
			</div>

		</div>
		<?php
	}

	public function reset_current_otp_link($admin = true) {
		return '<a href="#" class="simbaotp_refresh">'.__('(update)', 'two-factor-authentication').'</a>';
	}

	/**
	 * Print out the advanced settings box
	 *
	 * @param Boolean|Callable $submit_button_callback - if not a callback, then <form> tags will be added
	 */
	public function advanced_settings_box($submit_button_callback = false) {
		$tfa = $this->getTFA();

		global $current_user;
		$algorithm_type = $tfa->getUserAlgorithm($current_user->ID);

		?>
		<h2 id="tfa_advanced_heading" style="clear:both;"><?php _e('Advanced settings', 'two-factor-authentication'); ?></h2>

		<div id="tfa_advanced_box" class="tfa_settings_form" style="margin-top: 20px;">

			<?php if (false === $submit_button_callback) { ?>
				<form method="post" action="<?php print esc_url(add_query_arg('settings-updated', 'true', $_SERVER['REQUEST_URI'])); ?>">
				<?php wp_nonce_field('tfa_algorithm', '_tfa_algorithm_nonce', false, true); ?>
			<?php } ?>

			<?php _e('Choose which algorithm for One Time Passwords you want to use.', 'two-factor-authentication'); ?>
			<p>
			<?php
				$this->tfaListAlgorithmRadios($current_user->ID);
				if ('hotp' == $algorithm_type) {
					$counter = $tfa->getUserCounter($current_user->ID);
					print '<br>'.__('Your counter on the server is currently on', 'two-factor-authentication').': '.$counter;
				}
			?>
			
			</p>
			<?php if (false === $submit_button_callback) { submit_button(); echo '</form>'; } else { call_user_func($submit_button_callback); } ?>

		</div>
		<?php
	}

	/**
	 * This deals with the issue that wp-login.php does not redirect to a canonical URL. As a result, if a website is available under more than one host, then admin_url('admin-ajax.php') might return a different one than the visitor is using, resulting in AJAX failing due to CORS errors.
	 *
	 * @return String
	 */
	private function get_ajax_url() {
		$ajax_url = admin_url('admin-ajax.php');
		$parsed_url = parse_url($ajax_url);
		if (strtolower($parsed_url['host']) !== strtolower($_SERVER['HTTP_HOST']) && !empty($parsed_url['path'])) {
			// Mismatch - return the relative URL only
			$ajax_url = $parsed_url['path'];
		}
		return $ajax_url;
	}
	
	/**
	 * Called not only upon the WP action login_enqueue_scripts, but potentially upon the action 'init' and various others from other plugins too. It can handle being called multiple times.
	 */
	public function login_enqueue_scripts() {

		if (isset($_GET['action']) && 'logout ' != $_GET['action'] && 'login' != $_GET['action']) return;

		static $already_done = false;
		if ($already_done) return;
		$already_done = true;

		// Prevent cacheing when in debug mode
		$script_ver = (defined('WP_DEBUG') && WP_DEBUG) ? time() : $this->version;

		wp_enqueue_script('tfa-ajax-request', SIMBA_TFA_PLUGIN_URL.'/includes/tfa.js', array('jquery'), $script_ver);
		
		$trusted_for = $this->get_option('tfa_trusted_for');
		$trusted_for = (false === $trusted_for) ? 30 : (string) absint($trusted_for);
		
		$localize = array(
			'ajaxurl' => $this->get_ajax_url(),
			'click_to_enter_otp' => __("Click to enter One Time Password", 'two-factor-authentication'),
			'enter_username_first' => __('You have to enter a username first.', 'two-factor-authentication'),
			'otp' => __('One Time Password (i.e. 2FA)', 'two-factor-authentication'),
			'otp_login_help' => __('(check your OTP app to get this password)', 'two-factor-authentication'),
			'mark_as_trusted' => sprintf(_n('Trust this device (allow login without TFA for %d day)', 'Trust this device (allow login without TFA for %d days)', $trusted_for, 'two-factor-authentication'), $trusted_for),
			'is_trusted' => __('(Trusted device)', 'two-factor-authentication'),
			'nonce' => wp_create_nonce('simba_tfa_loginform_nonce'),
			'login_form_selectors' => '',
			'login_form_off_selectors' => '',
		);
		
		// Spinner exists since WC 3.8. Use the proper functions to avoid SSL warnings.
		if (file_exists(ABSPATH.'wp-admin/images/spinner-2x.gif')) {
			$localize['spinnerimg'] = admin_url('images/spinner-2x.gif');
		} elseif (file_exists(ABSPATH.WPINC.'/images/spinner-2x.gif')) {
			$localize['spinnerimg'] = includes_url('images/spinner-2x.gif');
		}
		
		$localize = apply_filters('simba_tfa_login_enqueue_localize', $localize);
		
		wp_localize_script('tfa-ajax-request', 'simba_tfasettings', $localize);
		
	}

	/**
	 * See if HOTP is off sync, and if show, print out a message
	 */
	public function tfa_show_hotp_off_sync_message() {
		global $current_user;
		$is_off_sync = get_user_meta($current_user->ID, 'tfa_hotp_off_sync', true);
		if(!$is_off_sync)
			return;
		
		?>
		<div class="error">
			<h3><?php _e('Two Factor Authentication re-sync needed', 'two-factor-authentication');?></h3>
			<p>
				<?php _e('You need to resync your device for Two Factor Authentication since the OTP you last used is many steps ahead of the server.', 'two-factor-authentication'); ?>
				<br>
				<?php _e('Please re-sync or you might not be able to log in if you generate more OTPs without logging in.', 'two-factor-authentication');?>
				<br><br>
				<a href="<?php echo wp_nonce_url('admin.php?page=two-factor-auth-user&warning_button_clicked=1', 'tfaresync', 'resyncnonce'); ?>" class="button"><?php _e('Click here and re-scan the QR-Code', 'two-factor-authentication');?></a>
			</p>
		</div>
		
		<?php
		
	}

	// QR code image
	public function tfa_qr_code_url($algorithm_type, $url, $tfa_priv_key, $user_id = false) {
		global $current_user;
		
		$user = (false == $user_id) ? $current_user : get_user_by('id', $user_id);
		
		$tfa = $this->getTFA();
		
		// Old way was to get it via an image from https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl='.$encode.'"> . But of course, that's a slight privacy leak (Google could get your private key from their logs).

		$encode = 'otpauth://'.$algorithm_type.'/'.$url.':'.rawurlencode($user->user_login).'?secret='.Base32::encode($tfa_priv_key).'&issuer='.$url.'&counter='.$tfa->getUserCounter($user->ID);

		return $encode;
	}

	public function settings_intro_notices() {
		?>
		<p class="simba_tfa_personal_settings_notice simba_tfa_intro_notice">
			<?php
			
				echo __('These are your personal settings.', 'two-factor-authentication').' '.__('Nothing you change here will have any effect on other users.', 'two-factor-authentication');
			
				if (is_multisite()) {
					if (is_super_admin()) {
						// Since WP 4.9
						$main_site_id = function_exists('get_main_site_id') ? get_main_site_id() : 1;
						$switched = switch_to_blog($main_site_id);
						echo ' <a href="'.admin_url('options-general.php?page=two-factor-auth').'">'.__('The site-wide administration options are here.', 'two-factor-authentication').'</a>';
						if ($switched) restore_current_blog();
					}
				} elseif (current_user_can($this->get_management_capability())) { 
					echo ' <a href="'.admin_url('options-general.php?page=two-factor-auth').'">'.__('The site-wide administration options are here.', 'two-factor-authentication').'</a>';
				}
			
			?>
		</p>
		<p class="simba_tfa_verify_tfa_notice simba_tfa_intro_notice"><strong>
			<?php echo apply_filters('simbatfa_message_you_should_verify', __('If you activate two-factor authentication, then verify that your two-factor application and this page show the same One-Time Password (within a minute of each other) before you log out.', 'two-factor-authentication')); ?></strong> <?php if (current_user_can($this->get_management_capability())) { ?><a href="https://wordpress.org/plugins/two-factor-authentication/faq/"><?php _e('You should also bookmark the FAQs, which explain how to de-activate the plugin even if you cannot log in.', 'two-factor-authentication');?></a><?php } ?>
		</p>
		<?php
	}

	/**
	 * Run upon the WP plugins_loaded action
	 */
	public function plugins_loaded() {
		load_plugin_textdomain(
			'two-factor-authentication',
			false,
			dirname(plugin_basename(__FILE__)).'/languages/'
		);
	}

	/**
	 * Make sure that self::$frontend is the instance of TFA_Frontend, and return it
	 *
	 * @return TFA_Frontend
	 */
	public function load_frontend() {
		if (!class_exists('TFA_Frontend')) require_once(SIMBA_TFA_PLUGIN_DIR.'/includes/tfa_frontend.php');
		if (empty($this->frontend)) $this->frontend = new TFA_Frontend($this);
		return $this->frontend;
	}

	// __return_empty_string() does not exist until WP 3.7
	public function shortcode_when_not_logged_in() {
		return '';
	}
	
}

$GLOBALS['simba_two_factor_authentication'] = new Simba_Two_Factor_Authentication();
