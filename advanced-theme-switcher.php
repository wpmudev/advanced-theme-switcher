<?php

/*
Plugin Name: Advanced Theme Switcher
Plugin URI: http://premium.wpmudev.org/project/advanced-theme-switcher
Description: Advanced Theme Switcher allows BuddyPress and Multisite users the chance to switch between different themes, or you the opportunity to profile different theme designs on a BuddyPress or Multisite.
Version: 1.0.7
Author: Ivan Shaovchev, Austin Matzko, Andrey Shipilov, Paul Menard (Incsub)
Author URI: http://premium.wpmudev.org/
WDP ID: 112
License: GNU General Public License (Version 2 - GPLv2)
*/

/*
Copyright 2007-2011 Incsub (http://incsub.com)

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

/**
 * Advanced Theme Switcher Class
 **/

if ( !class_exists('Advanced_Theme_Switcher') ):
class Advanced_Theme_Switcher {

    /** @var string $queried_theme The name of the theme queried */
	var $queried_theme = '';

    /**
     * Constructor.
     *
     * @return void
     **/
	function Advanced_Theme_Switcher() {

        add_action( 'setup_theme', 				array( &$this, 'parse_theme_preview_request' ) );
        add_action( 'init', 					array( &$this, 'set_cookie' ) );
        add_action( 'init', 					array( &$this, 'load_plugin_textdomain' ) );
        add_action( 'widgets_init', 			array( &$this, 'widget_init' ) );

		add_filter( 'stylesheet', 				array (&$this, 'get_stylesheet' ) );
		add_filter( 'template', 				array( &$this, 'get_template' ) );
		
		add_action( 'admin_bar_menu', 			array( &$this, 'add_nodes_and_groups_to_toolbar'), 999 );
		add_action( 'wp_print_styles', 			array( &$this, 'load_css'));
	}

    /**
     * Initiate plugin widget.
     *
     * @return void
     **/
	function widget_init() {
		register_widget('Advanced_Theme_Switcher_Widget');
	}



	function load_css() {
		if ( !is_admin_bar_showing() )
	        return;

		if (is_admin())
			return;

		wp_register_style("advanced-theme-switcher-style", plugins_url('/css/advanced-theme-switcher.css', __FILE__));
		wp_enqueue_style("advanced-theme-switcher-style", plugins_url('/css/advanced-theme-switcher.css', __FILE__));
	}

	function add_nodes_and_groups_to_toolbar( $wp_admin_bar ) {

	    global $wp_admin_bar, $wpdb;

		if ( !is_admin_bar_showing() )
	        return;

		if (is_admin())
			return;

	    /* Add the main siteadmin menu item */
	    $wp_admin_bar->add_menu( 
			array( 
				'id' 		=> 'advanced-theme-switcher-menu', 
				'title' 	=> __('Themes', 'advanced-theme-switcher'), 
				'href' 		=> '#', 
				'meta' 		=> array ( 'class' => 'advanced-theme-switcher-menu-main' ) 
			) 
		);
	    
		$wp_admin_bar->add_menu( 
			array( 
				'parent' 	=> 	'advanced-theme-switcher-menu', 
				'id'		=>	'advanced-theme-switcher-menu-sub',
				'title' 	=> 	'</a>'. $this->theme_switcher_markup('dropdown'). '<a href="#">', 
				'href' 		=> 	'#', 
				'meta' 		=> 	array ( 'class' => 'advanced-theme-switcher-menu-sub' ) 
			) 
		);
	}


    /**
     * Set cookie with the theme name queried.
     *
     * @return void
     **/
	function set_cookie() {
		$expire = time() + 30000000;
		if ( !empty( $this->queried_theme ) )
			setcookie( 'advanced-theme-switcher-' . COOKIEHASH, stripslashes( $this->queried_theme ), $expire, COOKIEPATH );			
	}

    /**
     * Loads the language file from the "languages" directory.
     *
     * @return void
     **/
    function load_plugin_textdomain() {
	
        load_plugin_textdomain( 'advanced-theme-switcher', null, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
    }

    /**
     * Parse the queried theme var.
     *
     * @return void
     **/
	function parse_theme_preview_request() {
		if ( !isset($_GET['theme-preview']))
			return;
		
		$this->queried_theme = esc_attr($_GET['theme-preview']);
	}

    /**
     *  Filter stylesheet path
     *
     * @param <type> $stylesheet
     * @return <type>
     */
	function get_stylesheet( $stylesheet ) {

        /* Get theme name */
		$theme = $this->get_current_theme_name();
		if ( empty( $theme ) )
			return $stylesheet;
			
        /* Get theme by name */
		$theme = get_theme( $theme );
        if ( empty( $theme ) )
			return $stylesheet;
			
		/* Don't let people peek at unpublished themes. */
		if ( isset( $theme['Status'] ) && $theme['Status'] != 'publish' )
			return $stylesheet;

		return $theme['Stylesheet'];
	}

    /**
     * Filter template path
     *
     * @param <type> $template
     * @return <type>
     */
	function get_template( $template ) {

        /* Get theme name */
		$theme = $this->get_current_theme_name();
		if ( empty( $theme ) )
			return $template;
			
        /* Get theme by name */
		$theme = get_theme( $theme );
		if ( empty( $theme ) )
			return $template;

		/* Don't let people peek at unpublished themes. */
		if ( isset($theme['Status'] ) && $theme['Status'] != 'publish' )
			return $template;

		return $theme['Template'];
	}

    /**
     * Get selected theme name.
     *
     * @return string|NULL Selected theme name.
     **/
	function get_current_theme_name() {

        /* Get theme name from var */
		if ( !empty( $this->queried_theme ) ) {
			$queried_theme = $this->queried_theme;
		} else {
			$queried_theme = get_query_var('theme-preview');
		}

        $queried_theme =  urldecode( $queried_theme );

		/* Get theme name from cookie if no var is set */
		if ( !empty( $queried_theme ) ) {
			return $queried_theme;
		} elseif ( !empty( $_COOKIE[ 'advanced-theme-switcher-' . COOKIEHASH ] ) ) {
			return $_COOKIE[ 'advanced-theme-switcher-' . COOKIEHASH ];
		} else {
			return;
		}
	}

    /**
     * Widget output.
     *
     * @global <type> $wp_rewrite
     * @param <type> $style
     * @param <type> $instance
     * @return <type>
     */
	function theme_switcher_markup( $style = 'text', $instance = array() ) {

		if ( ! $theme_data = wp_cache_get('themes-data', 'advanced-theme-switcher') ) {

        	$themes = (array) get_themes();
			if (!$themes) return;
		
        	if ( is_multisite() ) {
            	$allowed_themes = (array) get_site_option( 'allowedthemes' );
            	foreach( $themes as $name => $theme ) {
                	if ( !isset( $allowed_themes[ esc_html( $theme['Stylesheet'] ) ] ) ) {
                    	unset( $themes[$name] );
                	}
            	}
        	}

			$theme_names = array_keys( (array) $themes );

			foreach ( $theme_names as $theme_name ) {

				/* Skip unpublished themes. */
				if ( empty( $theme_name ) || isset( $themes[$theme_name]['Status'] ) && $themes[$theme_name]['Status'] != 'publish' )
					continue;

				$theme_data[$theme_name] = add_query_arg( 'theme-preview', urlencode( $theme_name ), get_option('home') );
			}
			wp_cache_set('themes-data', $theme_data, 'advanced-theme-switcher');
		}
		
		ksort( $theme_data );

		$ts = '<ul class="advanced-theme-switcher-container">'."\n";

		if ( $style == 'dropdown' )
			$ts .= '<li>'."\n\t" . '<select class="advanced-theme-switcher-themes" onchange="location.href=this.options[this.selectedIndex].value;">'."\n";

        $default_theme = get_current_theme();
		$current_theme = $this->get_current_theme_name();

		foreach ( $theme_data as $theme_name => $url ) {
			if ( !empty( $current_theme ) && $current_theme == $theme_name || empty( $current_theme ) && ( $theme_name == $default_theme ) ) {
				if ($default_theme == $theme_name)
					$pattern = ( 'dropdown' == $style ) ? '<option value="%1$s" selected="selected">%2$s *</option>' : '<li>%2$s *</li>';
				else
					$pattern = ( 'dropdown' == $style ) ? '<option value="%1$s" selected="selected">%2$s</option>' : '<li>%2$s</li>';
			} else if ($default_theme == $theme_name) {
				$pattern = ( 'dropdown' == $style ) ? '<option value="%1$s">%2$s *</option>' : '<li><a href="%1$s">%2$s</a> *</li>';
			} else {
				$pattern = ( 'dropdown' == $style ) ? '<option value="%1$s">%2$s</option>' : '<li><a href="%1$s">%2$s</a></li>';
			}
			$ts .= sprintf( $pattern, esc_attr( $url ), esc_html( $theme_name ) );
		}

		if ( 'dropdown' == $style ) {
			$ts .= '</select>' . "\n" . '</li>' . "\n";
		}
		$ts .= '</ul>';
		//$ts .= "<p>* = ". __('Current theme', 'advanced-theme-switcher') ."</p>";
		return $ts;
	}
}
endif;

/**
 * Widget for Advanced Theme Switcher
 **/
if ( !class_exists('Advanced_Theme_Switcher_Widget') ):
class Advanced_Theme_Switcher_Widget extends WP_Widget {

	function Advanced_Theme_Switcher_Widget() {
		return $this->WP_Widget( 'advanced-theme-switcher-widget', __( 'Advanced Theme Switcher Widget', 'advanced-theme-switcher' ), array( 'description' => __( 'A widget with options for switching themes.', 'advanced-theme-switcher' ) ) );
	}

	function widget( $args, $instance ) {
		global $advanced_theme_switcher;
		echo $args['before_widget'];
		echo $args['before_title'] . __( 'Theme Switcher', 'advanced-theme-switcher' ) . $args['after_title'];
		echo $advanced_theme_switcher->theme_switcher_markup( $instance['displaytype'], $instance );
		echo $args['after_widget'];
	}

	function update( $new_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$type = ( isset( $instance['displaytype'] ) ) ? $instance['displaytype'] : NULL; ?>

		<p><label for="<?php echo $this->get_field_id('displaytype'); ?>"><?php _e( 'Display themes as:', 'advanced-theme-switcher' ); ?></label></p>
		<p>
			<span><input type="radio" name="<?php echo $this->get_field_name('displaytype'); ?>" value="list" <?php
				if ( 'list' == $type ) {
					echo ' checked="checked"';
				}
			?> /> <?php _e( 'List', 'advanced-theme-switcher' ); ?></span>
			<span><input type="radio" name="<?php echo $this->get_field_name('displaytype'); ?>" value="dropdown" <?php
				if ( 'dropdown' == $type ) {
					echo ' checked="checked"';
				}
			?>/> <?php _e( 'Dropdown', 'advanced-theme-switcher' ); ?></span>
		</p>
		<?php
	}
}
endif;

/* Initiate plugin */
if ( class_exists('Advanced_Theme_Switcher') )
    $advanced_theme_switcher = new Advanced_Theme_Switcher();

/* Update Notifications Notice */
if ( !function_exists('wdp_un_check') ) {
    function wdp_un_check() {
        if ( !class_exists('WPMUDEV_Update_Notifications') && current_user_can('edit_users') )
            echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
    }
    add_action( 'network_admin_notices', 'wdp_un_check', 5 );
    add_action( 'admin_notices', 'wdp_un_check', 5 );
}
