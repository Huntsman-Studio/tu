<?php

if (!defined('ABSPATH')) die('No direct access.');

/**
 * This class contains code for integrating within the WP admin dashboard
 */

class Simba_TFA_Admin_Dashboard_Integration {

	// Main class
	private $tfa;

	/**
	 * Class constructor
	 */
	public function __construct($tfa) {
		$this->tfa = $tfa;
		
		if (!is_admin()) return;
		
		// Add to Settings menu on sites
		add_action('admin_menu', array($this, 'menu_entry_for_admin'));
		
		// Add settings link in plugin list
		$plugin = plugin_basename(SIMBA_TFA_PLUGIN_FILE); 
		add_filter("plugin_action_links_".$plugin, array($this, 'add_plugin_settings_link'));
		add_filter('network_admin_plugin_action_links_'.$plugin, array($this, 'add_plugin_settings_link'));

		// Entry that everybody gets
		add_action('network_admin_menu', array($this, 'admin_menu'));
		add_action('admin_menu', array($this, 'admin_menu'));

		// Add TFA column on users list
		add_action('manage_users_columns', array($this, 'manage_users_columns_tfa'));
		add_action('wpmu_users_columns', array($this, 'manage_users_columns_tfa'));
		add_action('manage_users_custom_column', array($this, 'manage_users_custom_column_tfa'), 10, 3);

		// Needed users.php CSS.
		add_action('admin_print_styles-users.php', array($this, 'load_users_css'), 10, 0);
	}

	/**
	 * Enqueue CSS styling on the users page
	 */
	public function load_users_css() {
		wp_enqueue_style(
			'tfa-users-css',
			SIMBA_TFA_PLUGIN_URL.'/css/users.css',
			array(),
			$this->tfa->version,
			'screen'
		);
	}
	
	/**
	 * Runs upon the WP filters plugin_action_links_(plugin) and network_plugin_action_links_(plugin)
	 *
	 * @param Array $links
	 *
	 * @return Array
	 */
	public function add_plugin_settings_link($links) {
		if (!is_network_admin()) {
			$link = '<a href="options-general.php?page=two-factor-auth">'.__('Plugin settings', 'two-factor-authentication').'</a>';
			array_unshift($links, $link);
		} else {
			switch_to_blog(1);
			$link = '<a href="'.admin_url('options-general.php').'?page=two-factor-auth">'.__('Plugin settings', 'two-factor-authentication').'</a>';
			restore_current_blog();
			array_unshift($links, $link);
		}

		$link2 = '<a href="admin.php?page=two-factor-auth-user">'.__('User settings', 'two-factor-authentication').'</a>';
		array_unshift($links, $link2);

		return $links;
	}
	
	/**
	 * Runs upon the WP action admin_menu
	 */
	public function admin_menu()  {
		$tfa = $this->tfa->getTFA();
		
		$tfa->potentially_port_private_keys();
		
		global $current_user;
		
		if(!$tfa->isActivatedForUser($current_user->ID)) return;
		add_menu_page(__('Two Factor Authentication', 'two-factor-authentication'), __('Two Factor Auth', 'two-factor-authentication'), 'read', 'two-factor-auth-user', array($this, 'show_user_settings_page'), SIMBA_TFA_PLUGIN_URL.'/img/tfa_admin_icon_16x16.png', 72);
	}
	
	/**
	 * Include the user settings page code
	 */
	public function show_user_settings_page() {
		$tfa = $this->tfa->getTFA();
		include SIMBA_TFA_PLUGIN_DIR.'/includes/user_settings.php';
	}
	
	/**
	 * Runs upon the WP action admin_menu
	 */
	public function menu_entry_for_admin() {

		if (is_multisite() && (!is_super_admin() || !is_main_site())) return;

		add_action('admin_init', array($this, 'register_two_factor_auth_settings'));

		add_options_page(
			__('Two Factor Authentication', 'two-factor-authentication'),
			__('Two Factor Authentication', 'two-factor-authentication'),
			$this->tfa->get_management_capability(),
			'two-factor-auth',
			array($this, 'show_admin_settings_page')
		);
	}
	
	/**
	 * Include the admin settings page code
	 */
	public function show_admin_settings_page() {
		$simba_two_factor_authentication = $this->tfa;
		$tfa = $this->tfa->getTFA();
		require_once(SIMBA_TFA_PLUGIN_DIR.'/includes/admin_settings.php');
	}
	
	/**
	 * Add the 2FA label to the users list table header.
	 *
	 * @param Array $columns Table columns.
	 *
	 * @return Array
	 */
	public function manage_users_columns_tfa($columns = array()) {
		$columns['tfa-status'] = __('2FA', 'two-factor-authentication');
		return $columns;
	}

	/**
	 * Add status into TFA column.
	 *
	 * @param  String  $value       String.
	 * @param  String  $column_name Column name.
	 * @param  Integer $user_id     User ID.
	 *
	 * @return String
	 */
	public function manage_users_custom_column_tfa($value = '', $column_name = '', $user_id = 0) {

		// Only for this column name.
		if ('tfa-status' === $column_name) {

			// Get TFA info.
			$tfa = $this->tfa->getTFA();

			if (!$tfa->isActivatedForUser($user_id)) {
				$value = '&#8212;';
			} elseif ($tfa->isActivatedByUser($user_id)) {
				// Use value.
				$value = '<span title="' . __( 'Enabled', 'two-factor-authentication' ) . '" class="dashicons dashicons-yes"></span>';
			} else {
				// No group.
				$value = '<span title="' . __( 'Disabled', 'two-factor-authentication' ) . '" class="dashicons dashicons-no"></span>';
			}
		}

		return $value;
	}
	
	/**
	 * Runs upon the WP action admin_init
	 */
	public function register_two_factor_auth_settings() {
		global $wp_roles;
		if (!isset($wp_roles)) $wp_roles = new WP_Roles();
		
		foreach ($wp_roles->role_names as $id => $name) {
			register_setting('tfa_user_roles_group', 'tfa_'.$id);
			register_setting('tfa_user_roles_trusted_group', 'tfa_trusted_'.$id);
			register_setting('tfa_user_roles_required_group', 'tfa_required_'.$id);
		}
		
		if (is_multisite()) {
			register_setting('tfa_user_roles_group', 'tfa__super_admin');
			register_setting('tfa_user_roles_trusted_group', 'tfa_trusted__super_admin');
			register_setting('tfa_user_roles_required_group', 'tfa_required__super_admin');
		}
		
		register_setting('tfa_user_roles_required_group', 'tfa_requireafter');
		register_setting('tfa_user_roles_required_group', 'tfa_if_required_redirect_to');
		register_setting('tfa_user_roles_required_group', 'tfa_hide_turn_off');
		register_setting('tfa_user_roles_trusted_group', 'tfa_trusted_for');
		register_setting('simba_tfa_woocommerce_group', 'tfa_wc_add_section');
		register_setting('simba_tfa_default_hmac_group', 'tfa_default_hmac');
		register_setting('tfa_xmlrpc_status_group', 'tfa_xmlrpc_on');
	}
	
}
