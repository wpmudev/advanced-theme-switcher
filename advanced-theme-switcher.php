<?php

/*
Plugin Name: Advanced Theme Switcher
Plugin URI: http://premium.wpmudev.org/project/advanced-theme-switcher
Description: Advanced Theme Switcher allows BuddyPress and Multisite users the chance to switch between different themes, or you the opportunity to profile different theme designs on a BuddyPress or Multisite.
Version: 1.0.4
Author: Ivan Shaovchev, Austin Matzko (Incsub)
Author URI: http://ivan.sh
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

    /** @var string $text_domain The text domain of the plugin */
    var $text_domain = 'advanced_theme_switcher';
    /** @var string $queried_theme The name of the theme queried */
	var $queried_theme = '';

    /**
     * Constructor.
     *
     * @return void
     **/
	function Advanced_Theme_Switcher() {
        register_activation_hook( __FILE__, array( &$this, 'add_rewrite_endpoint' ) );
        add_action( 'setup_theme', array( &$this, 'parse_theme_preview_request' ) );
        add_action( 'init', array( &$this, 'add_rewrite_endpoint' ) );
        add_action( 'init', array( &$this, 'set_cookie' ) );
        add_action( 'init', array( &$this, 'load_plugin_textdomain' ) );
        add_action( 'widgets_init', array( &$this, 'widget_init' ) );
		add_filter( 'stylesheet', array (&$this, 'get_stylesheet' ) );
		add_filter( 'template', array( &$this, 'get_template' ) );
        register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
	}

    /**
     * Set cookie with the theme name queried.
     *
     * @return void
     **/
	function set_cookie() {
		$expire = time() + 30000000;
		if ( !empty( $this->queried_theme ) )
			setcookie( 'wptheme' . COOKIEHASH, stripslashes( $this->queried_theme ), $expire, COOKIEPATH );
	}

    /**
     * Loads the language file from the "languages" directory.
     *
     * @return void
     **/
    function load_plugin_textdomain() {
        $plugin_dir = $this->plugin_dir . 'languages';
        load_plugin_textdomain( $this->text_domain, null, $plugin_dir );
    }

    /**
     * @todo REMOVE. Use global $wp_query
     */
	function parse_theme_preview_request() {
		$theme_preview_query = new WP();
		$theme_preview_query->add_query_var('theme-preview');
		$theme_preview_query->parse_request();
		if ( !empty( $theme_preview_query->query_vars['theme-preview'] ) ) {
			$this->queried_theme = $theme_preview_query->query_vars['theme-preview'];
		}
	}

    /**
     * Add rewrite endpoint and flush relues if necessary.
     *
     * @uses add_rewrite_endpoint();
     * @uses flush_rewrite_rules();
     *
     * @return void
     **/
	function add_rewrite_endpoint() {
        /* Add rewrite point. This will add the "theme-preview" to the root rewrite */
        add_rewrite_endpoint( 'theme-preview', EP_ROOT );
        /* Get available rewrite rules */
        $rewrite_rules = get_option('rewrite_rules');
        /* If our rewrite rule is missing flush the rules so they can be recreated */
        if ( !array_key_exists( 'theme-preview(/(.*))?/?$', $rewrite_rules ) )
            flush_rewrite_rules();
	}

    /**
     * Initiate plugin widget.
     *
     * @return void
     **/
	function widget_init() {
		register_widget('Advanced_Theme_Switcher_Widget');
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
		/* Get theme name from cookie if no var is set */
		if ( !empty( $queried_theme ) ) {
			return $queried_theme;
		} elseif ( !empty( $_COOKIE[ 'wptheme' . COOKIEHASH ] ) ) {
			return $_COOKIE[ 'wptheme' . COOKIEHASH ];
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
		global $wp_rewrite;

        $themes = (array) get_themes();
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
			if ( $wp_rewrite->using_permalinks() ) {
				$theme_data[$theme_name] = get_option('home') . '/theme-preview/' . urlencode( $theme_name ) . '/';
			} else {
				$theme_data[$theme_name] = add_query_arg( 'theme-preview', urlencode( $theme_name ), get_option('home') );
			}
		}
		
		ksort( $theme_data );

		$ts = '<ul id="themeswitcher">'."\n";		

		if ( $style == 'dropdown' )
			$ts .= '<li>'."\n\t" . '<select name="themeswitcher" onChange="location.href=this.options[this.selectedIndex].value;">'."\n";

        $default_theme = get_current_theme();
		$current_theme = $this->get_current_theme_name();
        
		foreach ( $theme_data as $theme_name => $url ) {
			if ( !empty( $current_theme ) && $current_theme == $theme_name || empty( $current_theme ) && ( $theme_name == $default_theme ) ) {
				$pattern = ( 'dropdown' == $style ) ? '<option value="%1$s" selected="selected">%2$s</option>' : '<li>%2$s</li>';
			} else {
				$pattern = ( 'dropdown' == $style ) ? '<option value="%1$s">%2$s</option>' : '<li><a href="%1$s">%2$s</a></li>';
			}				
			$ts .= sprintf( $pattern, esc_attr( $url ), esc_html( $theme_name ) );
		}

		if ( 'dropdown' == $style ) {
			$ts .= '</select>' . "\n" . '</li>' . "\n";
		}
		$ts .= '</ul>';
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
		return $this->WP_Widget( 'theme-switcher-widget', __( 'Theme Switcher Widget', 'theme-switcher' ), array( 'description' => __( 'A widget with options for switching themes.', 'theme-switcher' ) ) );
	}

	function widget( $args, $instance ) {
		global $advanced_theme_switcher;
		echo $args['before_widget'];
		echo $args['before_title'] . __( 'Theme Switcher', 'theme-switcher' ) . $args['after_title'];
		echo $advanced_theme_switcher->theme_switcher_markup( $instance['displaytype'], $instance );
		echo $args['after_widget'];
	}

	function update( $new_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$type = ( isset( $instance['displaytype'] ) ) ? $instance['displaytype'] : NULL; ?>

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

?>