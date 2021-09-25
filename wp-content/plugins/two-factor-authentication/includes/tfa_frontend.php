<?php
if (!defined('ABSPATH')) die('Access denied.');

class TFA_Frontend {

	private $mother;

	/**
	 * Class constructor
	 *
	 * @param Object $mother
	 */
	public function __construct($mother) {

		$this->mother = $mother;
		add_action('wp_ajax_tfa_frontend', array($this, 'ajax'));
		add_shortcode('twofactor_user_settings', array($this, 'tfa_user_settings_front'));
	}
	
	/**
	 * Runs upon the WP action wp_ajax_tfa_frontend
	 *
	 * @uses die()
	 */
	public function ajax() {
		$tfa = $this->mother->getTFA();
		global $current_user;
		
		$return_array = array();
		
		if (empty($_POST) || empty($_POST['subaction']) || !isset($_POST['nonce']) || !is_user_logged_in() || !wp_verify_nonce($_POST['nonce'], 'tfa_frontend_nonce')) die('Security check');
		
		if ('savesettings' == $_POST['subaction']) {
			if (empty($_POST['settings']) || !is_string($_POST['settings'])) die;
			
			parse_str(stripslashes($_POST['settings']), $posted_settings);
			
			if (isset($posted_settings['tfa_algorithm_type'])) {
				$old_algorithm = $tfa->getUserAlgorithm($current_user->ID);
		
				if ($old_algorithm != $posted_settings['tfa_algorithm_type'])
					$tfa->changeUserAlgorithmTo($current_user->ID, $posted_settings['tfa_algorithm_type']);
				
				//Re-fetch the algorithm type, url and private string
				$variables = $this->tfa_fetch_assort_vars();
				
				$return_array['qr'] = $this->mother->tfa_qr_code_url($variables['algorithm_type'], $variables['url'], $variables['tfa_priv_key']);
				$return_array['al_type_disp'] = $this->tfa_algorithm_info($variables['algorithm_type']);
			}
			
			if (isset($posted_settings['tfa_enable_tfa'])) {
			
				$allow_enable_or_disable = false;
			
				if (empty($posted_settings['require_current']) || !$posted_settings['tfa_enable_tfa']) {
					$allow_enable_or_disable = true;
				} else {
				
					if (!isset($posted_settings['tfa_enable_current']) || '' == $posted_settings['tfa_enable_current']) {
						$return_array['message'] = __('To enable TFA, you must enter the current code.', 'two-factor-authentication');
						$return_array['error'] = 'code_absent';
					} else {
						// Third parameter: don't allow emergency codes
						if ($tfa->check_code_for_user($current_user->ID, $posted_settings['tfa_enable_current'], false)) {
							$allow_enable_or_disable = true;
						} else {
							$return_array['error'] = 'code_wrong';
							$return_array['message'] = __('The TFA code you entered was incorrect.', 'two-factor-authentication');
						}
					}
				
				}
				
				if ($allow_enable_or_disable) $tfa->changeEnableTFA($current_user->ID, $posted_settings['tfa_enable_tfa']);
			}
			
			$return_array['result'] = 'saved';
			
			echo json_encode($return_array);
		}
		
		die;
	}
	
	/**
	 * Make the algorithm information string easier to update
	 *
	 * @param String $algorithm_type - totp|hotp
	 */
	public function tfa_algorithm_info($algorithm_type) {
		$al_type_disp = strtoupper($algorithm_type);
		$al_type_desc = ($algorithm_type == 'totp' ? __('a time based', 'two-factor-authentication') : __('an event based', 'two-factor-authentication'));
		
		return array('disp' => $al_type_disp, 'desc' => $al_type_desc);
	}
	
	/**
	 * Make the assorted required variables more accessible for ajax
	 *
	 * Returns: Site URL, private key, emergency codes, algorithm type
	 *
	 * @return Array
	 */
	public function tfa_fetch_assort_vars() {
		global $current_user;
		$tfa = $this->mother->getTFA();
		
		$url = preg_replace('/^https?:\/\//i', '', site_url());
				
		$tfa_priv_key_64 = get_user_meta($current_user->ID, 'tfa_priv_key_64', true);
		
		if (!$tfa_priv_key_64) $tfa_priv_key_64 = $tfa->addPrivateKey($current_user->ID);

		$tfa_priv_key = trim($tfa->getPrivateKeyPlain($tfa_priv_key_64, $current_user->ID));
			
		$algorithm_type = $tfa->getUserAlgorithm($current_user->ID);
		
		return apply_filters('simba_tfa_fetch_assort_vars', array(
			'url' => $url,
			'tfa_priv_key_64' => $tfa_priv_key_64,
			'tfa_priv_key' => $tfa_priv_key,
			'emergency_str' => '<em>'.__('No emergency codes left. Sorry.', 'two-factor-authentication').'</em>',
			'algorithm_type' => $algorithm_type
		), $tfa, $current_user);
	}
	
	/**
	 * Paints out the 'save settings' button
	 */
	public function save_settings_button() {
		echo '<button style="margin-left: 4px;margin-bottom: 10px" class="simbatfa_settings_save button button-primary">'.__('Save Settings', 'two-factor-authentication').'</button>';
	}

	/**
	 * Populate self::$tfa
	 */
	private function get_tfa() {
		if (empty($this->tfa)) $this->tfa = $this->mother->getTFA();
	}

	/**
	 * Paint output for the TFA on/off radio
	 *
	 * @param String $style - valid values are 'show_current' and 'require_current'
	 */
	public function settings_enable_or_disable_output($style = 'show_current') {
		$this->save_settings_javascript_output();
		global $current_user;
		?>
			<div class="simbatfa_frontend_settings_box tfa_settings_form">
				<p><?php $this->mother->tfaListEnableRadios($current_user->ID, true, $style); ?></p>
				<button style="margin-left: 4px; margin-bottom: 10px;" class="button button-primary simbatfa_settings_save"><?php _e('Save Settings', 'two-factor-authentication'); ?></button>
			</div>
		<?php
	}

	/**
	 * Enqueue scripts
	 */
	public function save_settings_javascript_output() {
	
		static $is_already_added = false;
		if ($is_already_added) return;
		$is_already_added = true;
		
		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
		wp_register_script('jquery-blockui', SIMBA_TFA_PLUGIN_URL . '/includes/jquery.blockUI' . $suffix . '.js', array('jquery'), '2.60');
		
		$script_ver = (defined('WP_DEBUG') && WP_DEBUG) ? time() : $this->mother->version;
		
		wp_enqueue_script('simba-tfa-frontend-settings', SIMBA_TFA_PLUGIN_URL.'/includes/frontend-settings.js', array('jquery-blockui'), $script_ver);
		
		$ajax_url = admin_url('admin-ajax.php');
		// It's possible that FORCE_ADMIN_SSL will make that SSL, whilst the user is on the front-end having logged in over non-SSL - and as a result, their login cookies won't get sent, and they're not registered as logged in.
		if (!is_admin() && substr(strtolower($ajax_url), 0, 6) == 'https:' && !is_ssl()) {
			$also_try = 'http:'.substr($ajax_url, 6);
		} else {
			$also_try = '';
		}
		
		$localize = array(
			'ask' => __('You have unsaved settings.', 'two-factor-authentication'),
			'saving' => __('Saving...', 'two-factor-authentication'),
			'ajax_url' => $ajax_url,
			'also_try' => $also_try,
			'nonce' => wp_create_nonce('tfa_frontend_nonce'),
			'response' => __('Response:', 'two-factor-authentication'),
		);
		
		wp_localize_script('simba-tfa-frontend-settings', 'simba_tfa_frontend', $localize);
		
	}

	/**
	 * Shortcode function for twofactor_user_settings
	 *
	 * @param Array $atts
	 * @param Null|String $content
	 *
	 * @return String
	 */
	public function tfa_user_settings_front($atts, $content = null) {

		if (!is_user_logged_in()) return '';

		global $current_user;
		
		// We want to print to buffer, since the shortcode API wants the value returned, not echoed
		ob_start();

		$this->get_tfa();

		if (!$this->tfa->isActivatedForUser($current_user->ID)) {
			_e('Two factor authentication is not available for your user.', 'two-factor-authentication');
		} else {

			?>

			<div class="wrap" style="padding-bottom:10px">
				
				<?php $this->mother->settings_intro_notices(); ?>
				
				<?php $this->settings_enable_or_disable_output(); ?>

				<?php $this->mother->current_codes_box(false); ?>

				<?php $this->mother->advanced_settings_box(array($this, 'save_settings_button')); ?>
				
			</div>
			
			<?php $this->save_settings_javascript_output(); ?>

			<?php
		}

		return ob_get_clean();

	}
}
