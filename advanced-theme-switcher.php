<?php

/*
Plugin Name: Advanced Theme Switcher
Plugin URI: http://incsub.com
Description: Allow your users to switch themes, and view each theme on its own custom page.
Version: 1.0.2
Author: Austin Matzko (IncSub) 
Author URI: http://incsub.com

Adapted from Ryan Boren's theme switcher, which was based on Alex King's style switcher.
http://www.alexking.org/software/wordpress/

*/ 

/* 
Copyright 2007-2009 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


class ThemeSwitcherWidget extends WP_Widget {
	function ThemeSwitcherWidget()
	{
		return $this->WP_Widget('theme-switcher-widget', __('Theme Switcher Widget', 'theme-switcher'), array('description' => __('A widget with options for switching themes.', 'theme-switcher')));
	}

	function widget($args, $instance)
	{
		global $theme_switcher;
		echo $args['before_widget'];
		echo $args['before_title'] . __('Theme Switcher', 'theme-switcher') . $args['after_title'];
		echo $theme_switcher->theme_switcher_markup($instance['displaytype'], $instance);
		echo $args['after_widget'];
	}

	function update($new_instance) 
	{
		return $new_instance;
	}

	function form($instance) 
	{
		$type = $instance['displaytype'];
		?>
		<p><label for="<?php echo $this->get_field_id('displaytype'); ?>"><?php _e('Display themes as:', 'theme-switcher'); ?></label></p>
		<p>
			<span><input type="radio" name="<?php echo $this->get_field_name('displaytype'); ?>" value="list" <?php
				if ( 'list' == $type ) {
					echo ' checked="checked"';
				}
			?> /> <?php _e('List', 'theme-switcher'); ?></span>
			<span><input type="radio" name="<?php echo $this->get_field_name('displaytype'); ?>" value="dropdown" <?php 
				if ( 'dropdown' == $type ) {
					echo ' checked="checked"';
				}
			?>/> <?php _e('Dropdown', 'theme-switcher'); ?></span>
		</p>
		<?php
	}
}

class ThemeSwitcher {

	var $_queried_theme = ''; // the name of the theme queried

	function ThemeSwitcher()
	{
		add_action('update_site_option_allowedthemes', array(&$this, 'flush_rewrite_rules'));
		add_action('admin_init', array(&$this, 'setup_rewrite_rules'));
		add_action('init', array(&$this, 'event_init'));
		add_action('setup_theme', array(&$this, 'setup_rewrite_rules'), 1);
		add_action('setup_theme', array(&$this, 'event_setup_theme'));
		add_action('widgets_init', array(&$this, 'event_widgets_init'));
		
		add_filter('stylesheet', array(&$this, 'get_stylesheet'));
		add_filter('template', array(&$this, 'get_template'));
		
		register_activation_hook(__FILE__, array(&$this, 'flush_rewrite_rules'));
	}

	function event_init() {
		load_plugin_textdomain('theme-switcher');
		$expire = time() + 30000000;
		if ( ! empty($this->_queried_theme) ) {
			setcookie(
				"wptheme" . COOKIEHASH,
				stripslashes($this->_queried_theme),
				$expire,
				COOKIEPATH
			);
		}
	}
	
	function event_setup_theme()
	{
		// we have to query at this point to test whether the query is for a theme preview
		$test_query = new WP();
		$test_query->add_query_var('theme-preview');
		$test_query->parse_request();
		if ( isset( $test_query->query_vars ) && ! empty( $test_query->query_vars['theme-preview'] ) ) {
			$this->_queried_theme = $test_query->query_vars['theme-preview'];
		}
	}

	function event_widgets_init()
	{
		register_widget('ThemeSwitcherWidget');
	}
	
	function get_stylesheet($stylesheet = '') {
		$theme = $this->get_current_theme();

		if (empty($theme)) {
			return $stylesheet;
		}

		$theme = get_theme($theme);

		// Don't let people peek at unpublished themes.
		if (isset($theme['Status']) && $theme['Status'] != 'publish')
			return $template;		
		
		if (empty($theme)) {
			return $stylesheet;
		}

		return $theme['Stylesheet'];
	}

	function get_template($template) {
		$theme = $this->get_current_theme();

		if (empty($theme)) {
			return $template;
		}

		$theme = get_theme($theme);
		
		if (empty($theme)) {
			return $template;
		}

		// Don't let people peek at unpublished themes.
		if (isset($theme['Status']) && $theme['Status'] != 'publish')
			return $template;		

		return $theme['Template'];
	}

	function get_current_theme() {
		$query_theme = '';

		if ( ! empty( $this->_queried_theme ) ) {
			$query_theme = $this->_queried_theme;
		} else {
			$query_theme = get_query_var('theme-preview');
		}
		
		if ( ! empty( $query_theme ) ) {
			return $query_theme;
		} elseif ( ! empty($_COOKIE["wptheme" . COOKIEHASH] ) ) {
			return $_COOKIE["wptheme" . COOKIEHASH];
		} else {
			return '';
		}
	}

	/**
	 * Flush the saved WordPress rewrite rules and rebuild them.
	 */
	function flush_rewrite_rules()
	{
		global $wp_rewrite;
		$this->setup_rewrite_rules();
		$wp_rewrite->flush_rules();
	}

	function setup_rewrite_rules()
	{
		// rewrite rules setup
		add_rewrite_endpoint('theme-preview', EP_ALL);
	}

	function theme_switcher_markup($style = "text", $instance = array()) {
		global $wp_rewrite;
		if ( ! $themes = wp_cache_get('all-themes', 'theme-switcher') ) {
			$themes = (array) get_themes();
			if ( function_exists('is_site_admin') ) {
				$allowed_themes = (array) get_site_option( 'allowedthemes' );
				foreach( $themes as $key => $theme ) {
				    if( isset( $allowed_themes[ wp_specialchars( $theme[ 'Stylesheet' ] ) ] ) == false ) {
						unset( $themes[ $key ] );
				    }
				}
			}

			wp_cache_set('all-themes', $themes, 'theme-switcher');
		}

		$default_theme = get_current_theme();


		$theme_data = array();
		$theme_names = array_keys((array) $themes);
		foreach ($theme_names as $theme_name) {
			// Skip unpublished themes.
			if (empty($theme_name) || isset($themes[$theme_name]['Status']) && $themes[$theme_name]['Status'] != 'publish')
				continue;
			// $theme_data[$theme_name] = add_query_arg('wptheme', $theme_name, get_option('home'));
			if ( $wp_rewrite->using_permalinks() ) {
				$theme_data[$theme_name] = get_option('home') . '/theme-preview/' . urlencode($theme_name) . '/';
			} else {
				$theme_data[$theme_name] = add_query_arg('theme-preview', urlencode($theme_name), get_option('home'));;
			}
		}
		
		ksort($theme_data);

		$ts = '<ul id="themeswitcher">'."\n";		

		if ( $style == 'dropdown' ) {
			$ts .= '<li>'."\n\t" . '<select name="themeswitcher" onchange="location.href=\'this.options[this.selectedIndex].value;">'."\n";
		}

		$current_theme = $this->get_current_theme();
		foreach ($theme_data as $theme_name => $url) {
			if (
				! empty($current_theme) && $current_theme == $theme_name ||
				empty($current_theme) && ($theme_name == $default_theme)
			) {
				$pattern = 'dropdown' == $style ? '<option value="%1$s" selected="selected">%2$s</option>' : '<li>%2$s</li>';
			} else {
				$pattern = 'dropdown' == $style ? '<option value="%1$s">%2$s</option>' : '<li><a href="%1$s">%2$s</a></li>';
			}				
			$ts .= sprintf($pattern,
				esc_attr($url),
				esc_html($theme_name)
			);

		}

		if ( 'dropdown' == $style ) {
			$ts .= '</select>' . "\n" . '</li>' . "\n";
		}
		$ts .= '</ul>';
		return $ts;
	}
}

$theme_switcher = new ThemeSwitcher();
