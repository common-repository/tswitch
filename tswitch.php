<?php
/**
 * Plugin Name: tSwitch
 * Plugin URI: http://wordpress.org/extend/plugins/tswitch/
 * Description: Lets administrator users to quickly switch theme directly from the toolbar.
 * Version: 1.1
 * Author: Luigi Cavalieri
 * Author URI: http://profiles.wordpress.org/_luigi
 * License: GPLv2 or later
 * License URI: license.txt
 * 
 * 
 * @package tSwitch
 * @version 1.1
 * @author Luigi Cavalieri
 * @license http://opensource.org/licenses/GPL-2.0 GPLv2.0 Public license
 * 
 * 
 * Copyright (c) 2012 Luigi Cavalieri (email: luigi.wpdev@gmail.com)
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 * 
 * ---------------------------------------------------------------------------------------- */



/**
 * Plugin class
 *
 * @since 1.0
 */
class tSwitch {
	const VERSION = '1.1';
	
	/**
	 * @since 1.0
	 * @var array
	 */
	private $options;
	
	/**
	 * @since 1.0
	 * @var array
	 */
	private $themes;
	
	/**
	 * @since 1.0
	 * @var array
	 */
	private $active_theme_data;
	
	/**
	 * Instantiates the plugin class.
	 *
	 * @since 1.1
	 * @return object
	 */
	public static function load() {
		return new tSwitch();
	}
	
	/**
	 * The constructor method: registers the plugin activation and deactivation
	 * hooks and hooks the @see init() method to the setup_theme action hook.
	 *
	 * @uses register_activation_hook()
	 * @uses register_deactivation_hook()
	 * @uses add_action()
	 *
	 * @since 1.0
	 */
	private function __construct() {
		if (version_compare(get_bloginfo('version'), '3.4', '<')) return;
		
		add_action('setup_theme', array(&$this, 'init'));
		if (is_admin()) register_deactivation_hook(__FILE__, array(&$this, 'uninstall'));
	}
	
	/**
	 * On deactivation, removes the plugin options from the database.
	 *
	 * This method is hooked into the plugin deactivation hook.
	 *
	 * @uses delete_option()
	 *
	 * @since 1.0
	 */
	public function uninstall() {
		delete_option('tswitch');
	}
	
	/**
	 * If the current user is not an administrator, prevents the
	 * execution of the plugin.
	 * Initialise the $options property and load up the plugin business 
	 * by the call of actions and filters hooks.
	 * 
	 * This method is hooked into the setup_theme action hook.
	 *
	 * @see __construct()
	 * @uses is_super_admin()
	 * @uses get_option()
	 * @uses add_filter()
	 * @uses add_action()
	 *
	 * @since 1.0
	 */
	public function init() {
		if (! is_super_admin()) return;
		
		$this->init_options();
		
		if (! $this->options['disable']) {
			$this->load_themes();
			
			add_filter('template', array(&$this, 'active_theme_template'));
			add_filter('stylesheet', array(&$this, 'active_theme_stylesheet'));
		}
		
		add_action('init', array(&$this, 'process_action'));
		add_action('admin_bar_menu', array(&$this, 'add_menu'), 100);
	}
	
	/**
	 * Initialises plugin options.
	 *
	 * @see init()
	 *
	 * @since 1.1
	 */
	public function init_options() {
		if ($this->options = get_option('tswitch')) return;
		
		$current_theme = &wp_get_theme();
		$this->options = array(
			'version'	 => self::VERSION,
			'disable'	 => false,
			'theme_id'	 => $current_theme->get_stylesheet(),
			'theme_name' => $current_theme->get('Name'),
		);
		
		add_option('tswitch', $this->options);
	}
	
	/**
	 * Intercept and process the actions triggers.
	 *
	 * This method is hooked into the init action hook.
	 *
	 * @see init()
	 * @uses wp_redirect()
	 * @uses wp_get_referer()
	 * @uses update_option()
	 * @uses is_admin()
	 *
	 * @since 1.0
	 */
	public function process_action() {
		if(empty($_GET) || !isset($_GET['tswitch_action'])) return;
		
		switch ($_GET['tswitch_action']) {
			case 'switch':
				if (! isset($_GET['theme'])) return;
				
				if ($this->is_theme_broken($_GET['theme'])) {
					wp_redirect(wp_get_referer());
					exit;
				}
				
				$theme = &wp_get_theme($_GET['theme']);
				$this->options['theme_name'] = $theme->get('Name');
				$this->options['theme_id'] = $_GET['theme'];
			break;
			
			case 'toggle_disable':
				$this->options['disable'] = (! $this->options['disable']);
			break;
			
			default:
			return;
		}
		
		$referer = wp_get_referer();
		update_option('tswitch', $this->options);
		
		if (is_admin()) {
			$matches = array();
			
			// This prevents the occurance of a WordPress notice if the switch action
			// is triggered while in a theme settings page
			if (preg_match('/(admin|themes)\.php\?page(.)*/', $referer, &$matches))
				$referer = str_replace($matches[0], '', $referer);
		}
		
		wp_redirect($referer);
		exit;
	}
	
	/**
	 * Adds the menu to the admin toolbar.
	 *
	 * This method is hooked into the admin_bar_menu action hook.
	 *
	 * @see init()
	 * @uses get_option()
	 * @uses esc_html()
	 * @uses site_url()
	 * @uses add_query_arg()
	 * @uses add_menu() For backward compatibility with Wordpress 3.2
	 *
	 * @since 1.0
	 */
	public function add_menu() {
		global $wp_admin_bar;
		
		if ($this->options['disable']) {
			$root_item_title = wp_get_theme()->get('Name');
			$toggle_title = 'Enable';
		}
		else {
			$root_item_title = esc_attr($this->options['theme_name']);
			$toggle_title = 'Disable';
		}
		
		// First we add the root item
		$wp_admin_bar->add_menu(array(
		    'id'	=> 'tswitch-menu',
		    'title'	=> $root_item_title,
		    'href'	=> site_url('/')
		));
		
		// Then the toggle-disable item
		$wp_admin_bar->add_menu(array(
		    'id'	 => 'toggle-disable',
		    'parent' => 'tswitch-menu',
		    'title'	 => '<strong>--- ' . $toggle_title . ' ---</strong>',
		    'href'	 => add_query_arg('tswitch_action', 'toggle_disable')
		));
		
		if ($this->options['disable']) return;
		
		// Finally we add the list of themes if the disable action
		// has not been triggered
		foreach ($this->themes as &$theme) {
			$wp_admin_bar->add_menu(array(
			    'id'	 => $theme['stylesheet'],
			    'parent' => $theme['parent'],
			    'title'	 => $theme['name'],
			    'href'	 => $theme['url']
			));
		}
	}
	
	/**
	 * Returns the template name of the active theme.
	 *
	 * This method is hooked into the template filter hook.
	 *
	 * @see init()
	 * @since 1.0
	 *
	 * @return string Template name of the active theme
	 */
	public function active_theme_template() {
		return $this->active_theme_data['template'];
	}
	
	/**
	 * Returns the stylesheet name of the active theme.
	 *
	 * This method is hooked into the stylesheet filter hook.
	 *
	 * @see init()
	 * @since 1.0
	 *
	 * @return string Stylesheet name of the active theme
	 */
	public function active_theme_stylesheet() {
		return $this->active_theme_data['stylesheet'];
	}
	
	/**
	 * Checks whether or not a theme is broken.
	 *
	 * @since 1.0
	 *
	 * @param string $name Theme name
	 * @return bool true if the theme is broken, false otherwise.
	 */
	private function is_theme_broken($id) {
		return !(isset($this->themes[$id]) && ($this->themes[$id]['stylesheet'] == $id));
	}
	
	/**
	 * Creates a list of currently available themes.
	 * Initializes the properties $themes and $active_theme_data.
	 * Removes the active theme from the list if it is a parent or resets its 'url'
	 * element otherwise.
	 *
	 * This method is called within the @see init() method.
	 *
	 * @uses wp_get_themes()
	 * @uses add_query_arg()
	 *
	 * @since 1.0
	 */
	private function load_themes() {
		$themes = &wp_get_themes(array('allowed' => true));
		
		foreach ($themes as $id => &$theme) {
			$item = array();
			$item['name']		= $theme->get('Name');
			$item['stylesheet']	= $theme->get_stylesheet();
			$item['template']	= $theme->get_template();
			$item['url']			= add_query_arg(array(
				'tswitch_action' => 'switch',
		    	'theme'	 => urlencode($item['stylesheet'])
		    ));
			
			if ($parent = &$theme->parent()) {
				$item['parent'] = $parent->get_stylesheet();
			}
			else { $item['parent'] = 'tswitch-menu'; }
			
			$this->themes[$item['stylesheet']] = $item;
		}
		
		$this->active_theme_data['template'] = $this->themes[$this->options['theme_id']]['template'];
		$this->active_theme_data['stylesheet'] = $this->themes[$this->options['theme_id']]['stylesheet'];
		
		if ($this->themes[$this->options['theme_id']]['parent'] != 'tswitch-menu') {
			$this->themes[$this->options['theme_id']]['url'] = null;
		}
		else { unset($this->themes[$this->options['theme_id']]); }
	}
}


tSwitch::load();
?>