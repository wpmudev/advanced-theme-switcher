<?php
/*
Plugin Name: Advanced Theme Switcher
Plugin URI: http://premium.wpmudev.org/project/advanced-theme-switcher
Description: Advanced Theme Switcher allows BuddyPress and Multisite users the chance to switch between different themes, or you the opportunity to profile different theme designs on a BuddyPress or Multisite.
Version: 1.0.8.1
Author: Paul Menard (Incsub)
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


// Support for WPMU DEV Dashboard plugin
include_once( dirname(__FILE__) . '/lib/dash-notices/wpmudev-dash-notification.php');

/**
 * Advanced Theme Switcher Class
 **/

if ( !class_exists('Advanced_Theme_Switcher') ) {
	class Advanced_Theme_Switcher {

		var $plugin_version = "1.0.8.1";
	
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


			add_filter( 'stylesheet', 				array (&$this, 'get_stylesheet' ) );
			add_filter( 'template', 				array( &$this, 'get_template' ) );
		
			add_action( 'admin_bar_menu', 			array( &$this, 'add_nodes_and_groups_to_toolbar'), 999 );
			add_action( 'wp_print_styles', 			array( &$this, 'load_css'));
			
			register_activation_hook( __FILE__, array( &$this, 'plugin_activation_proc' ) );
	        
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
	    
			$instance = array();
			$instance['displaytype'] = "dropdown";
			$instance['show-theme-parent'] = "yes";
			$instance['show-theme-version'] = "yes";
			$instance['show-theme-groups'] = "yes";
	
			$wp_admin_bar->add_menu ( 
				array( 
					'parent' 	=> 	'advanced-theme-switcher-menu', 
					'id'		=>	'advanced-theme-switcher-menu-sub',
					'title' 	=> 	'</a>'. $this->theme_switcher_markup($instance). '<a href="#">', 
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
			$theme = $this->get_preview_theme_name();
			if ( empty( $theme ) )
				return $stylesheet;
			
	        /* Get theme by name */
			$theme = wp_get_theme( $theme );
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
			$theme = $this->get_preview_theme_name();
			if ( empty( $theme ) )
				return $template;
			
	        /* Get theme by name */
			$theme = wp_get_theme( $theme );
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
		function get_preview_theme_name() {

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

		function plugin_activation_proc() {
			delete_transient( 'wpmudev-advanced-theme-'. $this->plugin_version );			
		}

	    /**
	     * Widget output.
	     *
	     * @global <type> $wp_rewrite
	     * @param <type> $style
	     * @param <type> $instance
	     * @return <type>
	     */
		function theme_switcher_markup( $instance = array() ) {
			global $wpdb;

//			echo "instance<pre>"; print_r($instance); echo "</pre>";
						
//			$themes_data = get_transient( 'wpmudev-advanced-theme-'. $this->plugin_version );
//			if ((!$themes_data) || (!is_array($themes_data))) {
		
				$themes = wp_get_themes( array( 'allowed' => true, 'blog_id' => $wpdb->blogid ) );			
				if (!$themes) return;
		
				$themes_data = array();
				foreach($themes as $theme_slug => $theme) {
				
					$theme_info = array();
					$theme_info['title'] 		= $theme->display('Name');
					$theme_info['version'] 		= $theme->display('Version');
					$theme_info['template'] 	= $theme->get_template();
					$theme_info['stylesheet'] 	= $theme->get_stylesheet();
					$theme_info['url'] 			= add_query_arg( 'theme-preview', urlencode( $theme_slug ), get_option('home') );

					//if (($instance['show-theme-parent'] == "no") && ($theme_info['template'] !== $theme_info['stylesheet']))
					//	continue;
					
					$themes_data[$theme_slug] = $theme_info;
				}
//				set_transient( 'wpmudev-advanced-theme-'. $this->plugin_version, $themes_data, 60 );
//			}
		
//			echo "themes_data<pre>"; print_r($themes_data); echo "</pre>";
			if ((!empty($themes_data)) && (is_array($themes_data))) {

				$current_active_theme_slug = wp_get_theme()->get_stylesheet();			
				$preview_theme_name = $this->get_preview_theme_name();

				$themes_data_parents = array();
				
				// Step 1: Create a nested array ot Parent > Child groups
				foreach ( $themes_data as $theme_slug => $theme_info ) {
					if ($theme_info['template'] === $theme_info['stylesheet']) {
						$themes_data_parents[$theme_slug] = $theme_info;
						unset($themes_data[$theme_slug]);
					}
				}

				// Step 2: Then loop through the theme parents then the remaining themes_data elements looking
				// for the parent's children. 
				if (count($themes_data_parents)) {
					
					foreach($themes_data_parents as $theme_slug_parent => $theme_info_parent) {
						$theme_info_children = array();

						foreach ( $themes_data as $theme_slug => $theme_info ) {
							if ($theme_info['template'] === $theme_info_parent['stylesheet']) {
								$theme_info_children[$theme_slug] = $theme_info;
								unset($themes_data[$theme_slug]);
							}
						}
						
						if (count($theme_info_children)) {
							$themes_data_parents[$theme_slug_parent]['children'] = array();
							$themes_data_parents[$theme_slug_parent]['children'] = $theme_info_children;
						}
					}
					$themes_data = array_merge($themes_data, $themes_data_parents);
				}
				
				// Step 3: If the widget does not want to show parent then only for those who have children remove. 
				// Move the parent's children back to top level.
				if ($instance['show-theme-parent'] == "no") {
					$child_themes = array();
					foreach ( $themes_data as $theme_slug => $theme_info ) {
						if (isset($theme_info['children'])) {
							$child_themes = array_merge($child_themes, $theme_info['children']);
							unset($themes_data[$theme_slug]);
						}
					}
					if (count($child_themes)) {
						$themes_data = array_merge($themes_data, $child_themes);
					}
				}
				
				// Step 4: If NOT grouping by Parent. Move all remaining children to top level
				if ($instance['show-theme-groups'] == "no") {
					$child_themes = array();
					foreach ( $themes_data as $theme_slug => $theme_info ) {
						if (isset($theme_info['children'])) {
							$child_themes = array_merge($child_themes, $theme_info['children']);
							unset($themes_data[$theme_slug]['children']);
						}
					}
					if (count($child_themes)) {
						$themes_data = array_merge($themes_data, $child_themes);
					}
				}
				
				//echo "themes_data<pre>"; print_r($themes_data); echo "</pre>";
								
				$ts = '<ul class="advanced-theme-switcher-container">'."\n";

				if ( $instance['displaytype'] == 'dropdown' ) {
					$ts .= '<li>'."\n\t" . '<select class="advanced-theme-switcher-themes"
					 	onchange="location.href=this.options[this.selectedIndex].value;">'."\n";
				}
			
				$preview_theme_name = $this->get_preview_theme_name();
				foreach ( $themes_data as $theme_slug => $theme_info ) {

					$theme_title = esc_html( $theme_info['title']);
					if ((isset($instance['show-theme-version'])) && ($instance['show-theme-version'] == "yes")) {
						$theme_title .= " (". esc_html( $theme_info['version']).")";
					}

					
					if ($theme_slug == $preview_theme_name) {
						$pattern = ( 'dropdown' == $instance['displaytype'] ) ? '<option selected="selected" value="%1$s">%2$s</option>' : '<li><a href="%1$s">%2$s</a>';						
					} else {
						$pattern = ( 'dropdown' == $instance['displaytype'] ) ? '<option value="%1$s">%2$s</option>' : '<li><a href="%1$s">%2$s</a>';
					}
					$ts .= sprintf( $pattern, esc_attr( $theme_info['url'] ), $theme_title );
					
					if ( (isset($theme_info['children'])) && (count($theme_info['children'])) ) {
						$ts .= ( 'dropdown' == $instance['displaytype'] ) ? '' : '<ul class="theme-child-container">';
						
						foreach($theme_info['children'] as $theme_slug_child => $theme_info_child) {

							$theme_title_child = esc_html( $theme_info_child['title']);
							if ((isset($instance['show-theme-version'])) && ($instance['show-theme-version'] == "yes")) {
								$theme_title_child .= " (". esc_html( $theme_info_child['version']).")";
							}

							if ($theme_slug == $preview_theme_name) {
								$pattern = ( 'dropdown' == $instance['displaytype'] ) ? '<option selected="selected" class="theme-child" value="%1$s">%2$s</option>' : '<li class="theme-child"><a href="%1$s">%2$s</a></li>';
							} else {
								$pattern = ( 'dropdown' == $instance['displaytype'] ) ? '<option class="theme-child" value="%1$s">%2$s</option>' : '<li class="theme-child"><a href="%1$s">%2$s</a></li>';
							}
							$ts .= sprintf( $pattern, esc_attr( $theme_info_child['url'] ), $theme_title_child );
						}
						
						$ts .= ( 'dropdown' == $instance['displaytype'] ) ? '' : '</ul>';
					}
					
					$ts .= ( 'dropdown' == $instance['displaytype'] ) ? '' : '</li>';
					
				}

				if ( 'dropdown' == $instance['displaytype'] ) {
					$ts .= '</select>' . "\n" . '</li>' . "\n";
				}
				$ts .= '</ul>';

				return $ts;
			}
		}
	}
	$advanced_theme_switcher = new Advanced_Theme_Switcher();
}

/**
 * Widget for Advanced Theme Switcher
 **/
if ( !class_exists('Advanced_Theme_Switcher_Widget') ) {
	class Advanced_Theme_Switcher_Widget extends WP_Widget {

		function Advanced_Theme_Switcher_Widget() {
			return $this->WP_Widget( 'advanced-theme-switcher-widget', __( 'Advanced Theme Switcher Widget', 'advanced-theme-switcher' ), array( 'description' => __( 'A widget with options for switching themes.', 'advanced-theme-switcher' ) ) );
		}

		function widget( $args, $instance ) {
			global $advanced_theme_switcher;
			echo $args['before_widget'];
			echo $args['before_title'] . __( 'Theme Switcher', 'advanced-theme-switcher' ) . $args['after_title'];
			echo $advanced_theme_switcher->theme_switcher_markup( $instance );
			echo $args['after_widget'];
		}

		function update( $new_instance, $instance ) {
			global $advanced_theme_switcher;
			
			delete_transient( 'wpmudev-advanced-theme-'. $advanced_theme_switcher->plugin_version );
			
			if (isset($new_instance['displaytype']))
				$instance['displaytype'] = strip_tags($new_instance['displaytype']);
			else
				$instance['displaytype'] = 'list';

			if (isset($new_instance['show-theme-groups']))
				$instance['show-theme-groups'] = strip_tags($new_instance['show-theme-groups']);
			else
				$instance['show-theme-parent'] = 'no';

			if (isset($new_instance['show-theme-parent']))
				$instance['show-theme-parent'] = strip_tags($new_instance['show-theme-parent']);
			else
				$instance['show-theme-parent'] = 'yes';

			if (isset($new_instance['show-theme-version']))
				$instance['show-theme-version'] = strip_tags($new_instance['show-theme-version']);
			else
				$instance['show-theme-version'] = 'yes';
		
			if ($instance['show-theme-parent'] == "no")
				$instance['show-theme-groups'] = "no";
				
			return $instance;
		}

		function form( $instance ) {
			$type = ( isset( $instance['displaytype'] ) ) ? $instance['displaytype'] : 'list'; 
			$show_theme_groups = ( isset( $instance['show-theme-groups'] ) ) ? $instance['show-theme-groups'] : 'yes';
			$show_theme_parent = ( isset( $instance['show-theme-parent'] ) ) ? $instance['show-theme-parent'] : 'yes';
			$show_theme_version = ( isset( $instance['show-theme-version'] ) ) ? $instance['show-theme-version'] : 'yes';
			?>
		
			<p><label for="<?php echo $this->get_field_id('displaytype'); ?>"><?php 
				_e( 'Display themes as:', 'advanced-theme-switcher' ); ?></label><br />
				<span><input type="radio" name="<?php echo $this->get_field_name('displaytype'); ?>" value="list" <?php
					if ( 'list' == $type ) { echo ' checked="checked"'; }
				?> /> <?php _e( 'List', 'advanced-theme-switcher' ); ?></span>
				<span><input type="radio" name="<?php echo $this->get_field_name('displaytype'); ?>" value="dropdown" <?php
					if ( 'dropdown' == $type ) { echo ' checked="checked"'; }
				?>/> <?php _e( 'Dropdown', 'advanced-theme-switcher' ); ?></span>
			</p>
			
			<p><label for="<?php echo $this->get_field_id('show-theme-parent'); ?>"><?php 
				_e( 'Show Theme Parent:', 'advanced-theme-switcher' ); ?></label><br />
				<span><input type="radio" name="<?php echo $this->get_field_name('show-theme-parent'); ?>" value="yes" <?php
					if ( 'yes' == $show_theme_parent ) { echo ' checked="checked"'; }
				?> /> <?php _e( 'Yes', 'advanced-theme-switcher' ); ?></span>
				<span><input type="radio" name="<?php echo $this->get_field_name('show-theme-parent'); ?>" value="no" <?php
					if ( 'no' == $show_theme_parent ) { echo ' checked="checked"'; }
				?>/> <?php _e( 'No', 'advanced-theme-switcher' ); ?></span>
			</p>

			<p><label for="<?php echo $this->get_field_id('show-theme-groups'); ?>"><?php 
				_e( 'Show Themes Grouped By Parent: ', 'advanced-theme-switcher' ); ?></label><br />
				<span><input type="radio" name="<?php echo $this->get_field_name('show-theme-groups'); ?>" value="yes" <?php
					if ( 'yes' == $show_theme_groups ) { echo ' checked="checked"'; }
				?> /> <?php _e( 'Yes', 'advanced-theme-switcher' ); ?></span>
				<span><input type="radio" name="<?php echo $this->get_field_name('show-theme-groups'); ?>" value="no" <?php
					if ( 'no' == $show_theme_groups ) { echo ' checked="checked"'; }
				?>/> <?php _e( 'No', 'advanced-theme-switcher' ); ?></span>
			</p>

			<p><label for="<?php echo $this->get_field_id('show-theme-version'); ?>"><?php 
				_e( 'Show Theme Version as part of Title:', 'advanced-theme-switcher' ); ?></label><br />
				<span><input type="radio" name="<?php echo $this->get_field_name('show-theme-version'); ?>" value="yes" <?php
					if ( 'yes' == $show_theme_version ) { echo ' checked="checked"'; }
				?> /> <?php _e( 'Yes', 'advanced-theme-switcher' ); ?></span>
				<span><input type="radio" name="<?php echo $this->get_field_name('show-theme-version'); ?>" value="no" <?php
					if ( 'no' == $show_theme_version ) { echo ' checked="checked"'; }
				?>/> <?php _e( 'No', 'advanced-theme-switcher' ); ?></span>
			</p>

			<?php
		}
	}

	add_action( 'widgets_init', 'widget_init_advanced_theme_switcher_widget' );
	function widget_init_advanced_theme_switcher_widget() {
		register_widget('Advanced_Theme_Switcher_Widget');
	}
}
// End of plugin code