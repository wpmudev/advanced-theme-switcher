<?php
/*
Plugin Name: Advanced Theme Switcher
Plugin URI: http://premium.wpmudev.org/project/advanced-theme-switcher
Description: Advanced Theme Switcher allows BuddyPress and Multisite users the chance to switch between different themes, or you the opportunity to profile different theme designs on a BuddyPress or Multisite.
Version: 1.0.9
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

		var $plugin_version = "1.0.9";
	
		var $current_themes;
		
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
			
			add_shortcode( 'adv_theme_switcher', 	array(&$this, 'process_shortcode') );
			
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
			
			$instance_defaults 				= 	array(
				'display_type' 				=> 	"menu",
				'display_type_sub'			=>	"menu",
				//'displaycontainer' 			=> 	"menu",
				'show_theme_parent' 		=> 	"yes",
				'show_theme_version' 		=> 	"yes",
				'show_theme_groups' 		=> 	"yes",
				'show_theme_parent_folder'	=>	"yes"
			);
			
			if (defined('ADV_THEME_SWITCHER_TOOLBAR')) {
				$instance = wp_parse_args( ADV_THEME_SWITCHER_TOOLBAR, $instance_defaults );
			} else {
				$instance = $instance_defaults;
			}
			
			// Override any user defined. From here MUST always be 'menu
			$instance['display_type'] = 'menu';
			
			// If admin wants to hide the toolba menu
			if ((empty($instance['display_type'])) || ($instance['display_type'] == "false") || ($instance['display_type'] == "no")) 
				return;
			
			if (empty($this->current_themes))
				$this->current_themes = wp_get_themes( array( 'allowed' => true, 'blog_id' => $wpdb->blogid ) );			

			// IF no themes the don't add new menu node
			if (!$this->current_themes) return;

		    /* Add the main siteadmin menu item */
			$instance['parent_menu_id'] = 'advanced-theme-switcher-menu';
		    $wp_admin_bar->add_menu( 
				array( 
					'id' 		=> $instance['parent_menu_id'], 
					'title' 	=> __('Themes', 'advanced-theme-switcher'), 
					'href' 		=> false, 
					'meta' 		=> array ( 'class' => 'advanced-theme-switcher-menu-main' ) 
				) 
			);
	    	$this->theme_switcher_markup($instance);
	
			if (wp_script_is('jquery')) {
				?>
				<script type="text/javascript">
					jQuery(document).ready(function(){
					    var ab_item_color 				= jQuery('#wpadminbar .ab-submenu .ab-empty-item').css('color');
						console.log('ab_item_color['+ab_item_color+']');

					    var ab_item_background_color 	= jQuery('#wpadminbar .ab-sub-wrapper').css('background-color');
						console.log('ab_item_background_color['+ab_item_background_color+']');
						
						if (jQuery(jQuery('#wpadminbar .advanced-theme-switcher-menu-sub select.advanced-theme-switcher-themes').length)) {
							console.log('#wpadminbar found');
							jQuery('#wpadminbar .advanced-theme-switcher-menu-sub select').css('color', ab_item_color);
							jQuery('#wpadminbar .advanced-theme-switcher-menu-sub select option').css('color', ab_item_color);
							jQuery('#wpadminbar .advanced-theme-switcher-menu-sub select').css('background-color', ab_item_background_color);
							jQuery('#wpadminbar .advanced-theme-switcher-menu-sub select option').css('background-color', ab_item_background_color);
						} else {
							console.log('#wpadminbar not present');
						}
					});
				</script>
				<?php
			}
	
		}


		function process_shortcode($atts) {
			
			if (defined('ADV_THEME_SWITCHER_DEFAULTS')) {
				$instance_defaults = wp_parse_args( ADV_THEME_SWITCHER_DEFAULTS, array() );
			} else {
				$instance_defaults = array(
					'display_type'				=> 	"list",
					'show_theme_parent'			=> 	"yes",
					'show_theme_version'		=> 	"yes",
					'show_theme_groups' 		=> 	"yes",
					'show_theme_parent_folder' 	=> 	"yes",
				);
			}
			
			$instance = wp_parse_args($atts, $instance_defaults);

			if (($instance['display_type'] != "list") && ($instance['display_type'] != "dropdown"))
				$instance['display_type'] = "dropdown";

			//echo "instance<pre>"; print_r($instance); echo "</pre>";
			return $this->theme_switcher_markup($instance);
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
			
			if (empty($this->current_themes))
				$this->current_themes = wp_get_themes( array( 'allowed' => true, 'blog_id' => $wpdb->blogid ) );			
				
			if (!$this->current_themes) return;
		
			//echo "themes<pre>"; print_r($themes); echo "</pre>";

			if (!isset($instance['display_type'])) {
				if (isset($instance['displaytype'])) {
					$instance['display_type'] = $instance['displaytype'];
					unset($instance['displaytype']);
				}
			}

			if ($instance['display_type'] == "menu") {
				if (!isset($instance['display_type_sub'])) {
					$instance['display_type_sub'] = "dropdown";
				} else if ( ($instance['display_type_sub'] != "menu") && ($instance['display_type_sub'] != "dropdown") ) {
					$instance['display_type_sub'] = "dropdown";
				}
			} else {
				//if (!isset($instance['display_type_sub'])) {
					$instance['display_type_sub'] = $instance['display_type'];
				//}
			}
			//echo "instance<pre>"; print_r($instance); echo "</pre>";

			$themes_data = array();
			foreach($this->current_themes as $theme_slug => $theme) {
				$slug = str_replace('\\', '/', $theme_slug);
				$slug = str_replace('/', '-', $slug);
				$slug = sanitize_title_with_dashes($slug);
				
				$theme_info = array();
				$theme_info['title'] 		= $theme->display('Name');
				$theme_info['version'] 		= $theme->display('Version');
				$theme_info['template'] 	= $theme->get_template();
				$theme_info['stylesheet'] 	= $theme->get_stylesheet();
				$theme_info['url'] 			= add_query_arg( 'theme-preview', urlencode( $theme_slug ), get_option('home') );
				$theme_info['slug']			= $slug;
				$theme_info['is_group']		= false;
				
				$themes_data[$theme_slug] = $theme_info;
			}
	
			if ((!empty($themes_data)) && (is_array($themes_data))) {

				//$current_active_theme_slug = wp_get_theme()->get_stylesheet();			
				//echo "current_active_theme_slug=[". $current_active_theme_slug ."]<br />";
				
				//$preview_theme_name = $this->get_preview_theme_name();
				//echo "preview_theme_name=[". $preview_theme_name ."]<br />";
				
				$themes_data_parents = array();
				
				// IF the instance wants to filter by the parent folder. 
				if ((isset($instance['show_theme_parent_filter'])) && (!empty($instance['show_theme_parent_filter']))) {
					//echo "show_theme_parent_filter[". $instance['show_theme_parent_filter'] ."]<br />";
					$instance['show_theme_parent_filter'] = str_replace('\\', '/', $instance['show_theme_parent_filter']);
					foreach ( $themes_data as $theme_slug => $theme_info ) {
						$theme_slug = str_replace('\\', '/', $theme_slug);
						if (strncasecmp( $theme_slug, $instance['show_theme_parent_filter'], strlen($instance['show_theme_parent_filter'])) != 0) {
							unset($themes_data[$theme_slug]);
						}
					}
				}
				
				// Step 1: Create a nested array ot Parent > Child groups
				if ($instance['show_theme_groups'] == "yes") {
					foreach ( $themes_data as $theme_slug => $theme_info ) {
						if (strtolower($theme_info['template']) === strtolower($theme_info['stylesheet'])) {
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
					if ($instance['show_theme_parent'] == "no") {
						$child_themes = array();
						
						foreach ( $themes_data as $theme_slug => $theme_info ) {
							if ((isset($theme_info['children'])) && (is_array($theme_info['children'])) && (count($theme_info['children']))) {
								if ((isset($theme_info['children']['children'])) && (is_array($theme_info['children']['children'])) && (count($theme_info['children']['children']))) {
									$child_themes = array_merge($child_themes, $theme_info['children']['children']);
								}
								$child_themes = array_merge($child_themes, $theme_info['children']);
								unset($themes_data[$theme_slug]);
							}
						}
						if (count($child_themes)) {
							$themes_data = array_merge($themes_data, $child_themes);
						}
					}
					
					foreach ( $themes_data as $theme_slug => $theme_info ) {

						//First process the child themes. Replace the details array index with the title so things will sort properly
						if ((isset($theme_info['children'])) && (is_array($theme_info['children'])) && (count($theme_info['children']))) {
							foreach($theme_info['children'] as $theme_slug_child => $theme_info_child) {
								unset($theme_info['children'][$theme_slug_child]);
								$theme_slug_child_new = strtolower(esc_attr($theme_info_child['title']));
								$theme_info['children'][$theme_slug_child_new] = $theme_info_child;
							}
							ksort($theme_info['children']);
						}

						$theme_slug = str_replace('\\', '/', $theme_slug);
						$theme_group_parts = explode('/', $theme_slug);
						if (count($theme_group_parts) > 1) {
							if ($instance['show_theme_parent_folder'] == "yes") {
								$theme_group_slug = sanitize_title_with_dashes($theme_group_parts[0]);
								if (!isset($themes_data[$theme_group_slug])) {
									$theme_group_info 				= array();
									$theme_group_info['title']		= $theme_group_parts[0];
									$theme_group_info['is_group'] 	= true;
									$theme_group_info['slug'] 		= $theme_group_slug;
									$theme_group_info['children'] 	= array();
							
									$themes_data[$theme_group_slug] = $theme_group_info;
								}
								unset($themes_data[$theme_slug]);
								$theme_slug_new = strtolower(esc_attr($theme_info['title']));
								$themes_data[$theme_group_slug]['children'][$theme_slug_new] = $theme_info;
							} else {
								unset($themes_data[$theme_slug]);
								$theme_slug_new = strtolower(esc_attr($theme_info['title']));
								$themes_data[$theme_slug_new] = $theme_info;
							}
						} 
					}
					
				} else {
					//echo "themes_data<pre>"; print_r($themes_data); echo "</pre>";

					if ($instance['show_theme_parent'] == "no") {
						$child_themes = array();
						
						foreach ( $themes_data as $theme_slug => $theme_info ) {
							
							if ($theme_info['template'] === $theme_info['stylesheet']) {
								
								$HAS_CHILD = false;
								foreach ( $themes_data as $theme_slug2 => $theme_info2 ) {
									if ($theme_slug === $theme_slug2) continue;
									
									if ($theme_info['template'] == $theme_info2['template']) {
										$HAS_CHILD = true;
										break;
									}
								}
								if ($HAS_CHILD == true)
									unset($themes_data[$theme_slug]);
							}
						}
					}
					
					if ($instance['show_theme_groups'] == "no") {
						$child_themes = array();
						foreach ( $themes_data as $theme_slug => $theme_info ) {
							if ((isset($theme_info['children'])) && (is_array($theme_info['children'])) && (count($theme_info['children']))) {
								$child_themes = array_merge($child_themes, $theme_info['children']);
								unset($themes_data[$theme_slug]['children']);
							}
						}
						if (count($child_themes)) {
							$themes_data = array_merge($themes_data, $child_themes);
						}
					}
					
					// This next step is to provide better sorting. In some cases the slug is way different than the Title. 
					// So the sort if off. 
					foreach ( $themes_data as $theme_slug => $theme_info ) {						
						unset($themes_data[$theme_slug]);
						$theme_slug_new = strtolower($theme_info['title']);
						$themes_data[$theme_slug_new] = $theme_info;
					}
				} 
				
				ksort($themes_data, SORT_STRING);
				
				//if ($instance['display_type'] == "menu") {
				//echo "themes_data<pre>"; print_r($themes_data); echo "</pre>";
				//}

				//if ((!isset($instance['displaycontainer'])) || ($instance['displaycontainer'] != 'menu')) {
				if ( $instance['display_type'] == 'menu' ) {
					global $wp_admin_bar;
					$ts = '';
					
				} else if ( $instance['display_type'] == 'list' ) {
					$ts = '<ul class="advanced-theme-switcher-container">';

				} else if ( $instance['display_type'] == 'dropdown' ) {
					$ts = '<ul class="advanced-theme-switcher-container"><li><select class="advanced-theme-switcher-themes" onchange="location.href=this.options[this.selectedIndex].value;">';
					
					//if ((isset($instance['show_theme_parent_filter'])) && (!empty($instance['show_theme_parent_filter']))) {
					//	$ts .= '<option value="">'. $instance['show_theme_parent_filter'] .'</option>';
					//}
				}
			
				//$current_active_theme_slug = wp_get_theme()->get_stylesheet();			
				//echo "current_active_theme_slug=[". $current_active_theme_slug ."]<br />";
				
				$instance['current_theme_name'] = $this->get_preview_theme_name();
				//echo "current_theme_name=[". $instance['current_theme_name'] ."]<br />";
				
				$instance['sub_menu_id'] = '';
				foreach ( $themes_data as $theme_slug => $theme_info ) {

					if ($theme_info['is_group'] == true) {
						if ($instance['display_type'] == "menu") {
							$instance['sub_menu_id'] = 'advanced-theme-switcher-menu-sub-'. $theme_info['slug'];
							$wp_admin_bar->add_menu ( 
								array( 
									'parent' 	=> 	$instance['parent_menu_id'], 
									'id'		=>	$instance['sub_menu_id'],
									'title' 	=> 	$theme_info['title'], 
									'href' 		=> 	false, 
									'meta' 		=> 	array ( 'class' => 'advanced-theme-switcher-menu-sub' ) 
								) 
							);
						}
					}


					if ($theme_info['is_group'] == false) {
						$theme_title = esc_html( $theme_info['title']);
						if ((isset($instance['show_theme_version'])) && ($instance['show_theme_version'] == "yes")) {
							$theme_title .= " (". esc_html( $theme_info['version']).")";
						}
					
						if ( $instance['display_type'] == 'dropdown' ) {
							if ($theme_info['stylesheet'] == $instance['current_theme_name'])
								$selected = ' selected="selected" ';
							else
								$selected = '';
								
							$pattern = '<option '. $selected .' value="%1$s">%2$s</option>';
							$ts .= sprintf( $pattern, esc_attr( $theme_info['url'] ), $theme_title );
								
						} else if ( $instance['display_type'] == 'list' ) {
							$pattern = '<li><a href="%1$s">%2$s</a>';						
							$ts .= sprintf( $pattern, esc_attr( $theme_info['url'] ), $theme_title );

						} else if ($instance['display_type'] == "menu") {
							$instance['sub_menu_id'] = 'advanced-theme-switcher-menu-sub-'. $theme_info['slug'];

							$item_class = 'advanced-theme-switcher-menu-sub';
							if ($theme_info['stylesheet'] == $instance['current_theme_name']) {
								//echo "match current_theme_name[". $instance['current_theme_name'] ."] theme_info<pre>"; print_r($theme_info); echo "</pre>";
								$item_class .= " current_theme";
							}

							$wp_admin_bar->add_menu ( 
								array( 
									'parent' 	=> 	$instance['parent_menu_id'], 
									'id'		=>	$instance['sub_menu_id'],
									'title' 	=> 	$theme_info['title'], 
									'href' 		=> 	$theme_info['url'], 
									'meta' 		=> 	array ( 'class' => $item_class ) 
								) 
							);
						}
					}
					
					if ( (isset($theme_info['children'])) && (is_array($theme_info['children'])) && (count($theme_info['children'])) ) {

						if ($instance['display_type'] == "dropdown") {
							if ($theme_info['is_group'] == true) {
								$ts .=	'<optgroup label="'. $theme_info['title'] .'">';
							}
							
						} else if ($instance['display_type'] == "list") {
							if ($theme_info['is_group'] == true)
								$ts .=	'<li>'. $theme_info['title'];

							$ts .= '<ul class="theme-child-container">';
						}
						
						$ts .= $this->theme_switcher_markup_children($instance, $theme_info['children']);
						
						if ($instance['display_type'] == 'dropdown') {
							if ($theme_info['is_group'] == true)
								$ts .=	'</optgroup>';
						} else if ($instance['display_type'] == 'list') {
							$ts .= '</ul>';
						} else if ($instance['display_type'] == "menu") {
							if ($instance['display_type_sub'] == "dropdown") {

								$ts = '<option value="">'. __('Select Theme', 'advanced-theme-switcher') .'</option>'. $ts;
								$ts = '<select class="advanced-theme-switcher-themes"
								 	onchange="location.href=this.options[this.selectedIndex].value;">'. $ts .'</select>';

								$item_class = 'advanced-theme-switcher-menu-sub';
								if ($theme_info['stylesheet'] == $instance['current_theme_name']) {
									//echo "match current_theme_name[". $instance['current_theme_name'] ."] theme_info<pre>"; print_r($theme_info); echo "</pre>";
									$item_class .= " current_theme";
								}


								$wp_admin_bar->add_menu ( 
									array( 
										'parent' 	=> 	$instance['sub_menu_id'], 
										'id'		=>	$instance['sub_menu_id'] .'-sub',
										'title' 	=> 	$ts, 
										'href' 		=> 	false, 
										'meta' 		=> 	array ( 'class' =>  $item_class) 
									) 
								);
							}
							$ts = '';
						}
					} 
					
					if ($instance['display_type'] == 'list') {
						$ts .= '</li>';
					}
					
				}

				if ( $instance['display_type'] == 'menu' ) {
				} else if ( 'dropdown' == $instance['display_type'] ) {
					$ts .= '</select></li></ul>';
				} else if ( 'list' == $instance['display_type'] ) {
					$ts .= '</ul>';
				}

				return $ts;
			}
		}
		
		function theme_switcher_markup_children($instance, $theme_children = array(), $level = 1) {
			$ts_child = '';
			
			if (!count($theme_children)) return;
			
			ksort($theme_children, SORT_STRING);

			//echo "instance<pre>"; print_r($instance); echo "</pre>";
			//echo "level[". $level ."] theme_children<pre>"; print_r($theme_children); echo "</pre>";

			$item_class = "theme". str_repeat ( '-child', $level );
			foreach($theme_children as $theme_slug_child => $theme_info_child) {

				$theme_title_child = esc_html( $theme_info_child['title'] );
				if ((isset($instance['show_theme_version'])) && ($instance['show_theme_version'] == "yes")) {
					$theme_title_child .= " (". esc_html( $theme_info_child['version']).")";
				}

				if ($instance['display_type_sub'] == "dropdown") {
					if ($theme_info_child['stylesheet'] == $instance['current_theme_name']) {
						//echo "match current_theme_name[". $instance['current_theme_name'] ."] theme_info_child<pre>"; print_r($theme_info_child); echo "</pre>";
						$selected = ' selected="selected" ';
					} else {
						$selected = '';
					}
					$pattern = '<option '. $selected .' class="'. $item_class .'" value="%1$s">%2$s</option>';
					$ts_child .= sprintf( $pattern, esc_attr( $theme_info_child['url'] ), $theme_title_child );
					
				} else if ($instance['display_type_sub'] == "list") {
					$pattern = '<li class="'. $item_class .'"><a href="%1$s">%2$s</a>';
					$ts_child .= sprintf( $pattern, esc_attr( $theme_info_child['url'] ), $theme_title_child );
					
				} else if ($instance['display_type_sub'] == "menu") {
					global $wp_admin_bar;
					
					if ($level > 1) {
						$theme_title = str_repeat ( '-', 1 ). "&nbsp;". $theme_title_child;
					} else {
						$theme_title = $theme_title_child;
					}
					
					if ($theme_info_child['stylesheet'] == $instance['current_theme_name']) {
						//echo "match current_theme_name[". $instance['current_theme_name'] ."] theme_info_child<pre>"; print_r($theme_info_child); echo "</pre>";
						$item_class_selected .= $item_class ." current_theme";
					} else {
						$item_class_selected = $item_class;
					}
					$wp_admin_bar->add_menu ( 
						array( 
							'parent' 	=> 	$instance['sub_menu_id'], 
							'id'		=>	$instance['sub_menu_id'] .'-children-'. $theme_info_child['slug'] ,
							'title' 	=> 	$theme_title, 
							'href' 		=> 	$theme_info_child['url'], 
							'meta' 		=> 	array ( 'class' => $item_class_selected ) 
						) 
					);
					
				}
				
				if ( (isset($theme_info_child['children'])) && (is_array($theme_info_child['children'])) && (count($theme_info_child['children'])) ) {

					$ts_child_child .= $this->theme_switcher_markup_children($instance, $theme_info_child['children'], $level+1);

					if ($instance['display_type_sub'] == "dropdown") {
						$ts_child .= $ts_child_child;
					} else if ($instance['display_type_sub'] == "list") {
						$ts_child .= '<ul class="'. $item_class .'">'. $ts_child_child . '</ul>';
					}
				} 
				if ($instance['display_type_sub'] == "list") {
					$ts_child .= '</li>';
				}
			}
			//echo "ts_child<pre>"; print_r($ts_child); echo "</pre>";
			return $ts_child;
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
			
	 		/* Widget settings. */
	 		$widget_ops = array( 
				//'classname' 	=> 'advanced-theme-switcher-widget', 
				'description'	=> __( 'A widget with options for switching themes.', 'advanced-theme-switcher')
			);

	 		/* Widget control settings. */
	 		$control_ops = array( 
				'width' 	=> 280, 
				'height' 	=> 350, 
				//'id_base' => 'advanced-theme-switcher' 
			);

	 		/* Create the widget. */
	 		$this->WP_Widget( 'advanced-theme-switcher-widget', __( 'Advanced Theme Switcher Widget', 'advanced-theme-switcher' ), $widget_ops, $control_ops );
		}

		function update_instance_keys($instance) {
			foreach($instance as $idx => $val) {
				if ($idx == "displaytype") {
					unset($instance[$idx]);
					if (!isset($instance['display_type']))
						$instance['display_type'] = $val;
				} else {
					$idx_new = str_replace('-', '_', $idx);
					if ($idx !== $idx_new) {
						unset($instance[$idx]);
						if (!isset($instance[$idx_new]))
							$instance[$idx_new] = $val;
					}
				}
			}
			return $instance;
		}
		
		function widget( $args, $instance ) {
			global $advanced_theme_switcher;

			// Convert old-style keys from using dash to underscore
			$instance = $this->update_instance_keys($instance);
			//echo "instance<pre>"; print_r($instance); echo "</pre>";
			
			echo $args['before_widget'];
			echo $args['before_title'] . __( 'Theme Switcher', 'advanced-theme-switcher' ) . $args['after_title'];
			echo $advanced_theme_switcher->theme_switcher_markup( $instance );
			echo $args['after_widget'];
		}

		function update( $new_instance, $instance ) {
			
			if (isset($new_instance['display_type']))
				$instance['display_type'] = strip_tags($new_instance['display_type']);
			else
				$instance['display_type'] = 'list';

			if (isset($new_instance['show_theme_groups']))
				$instance['show_theme_groups'] = strip_tags($new_instance['show_theme_groups']);
			else
				$instance['show_theme_groups'] = 'no';

			if (isset($new_instance['show_theme_parent']))
				$instance['show_theme_parent'] = strip_tags($new_instance['show_theme_parent']);
			else
				$instance['show_theme_parent'] = 'yes';

			if (isset($new_instance['show_theme_parent_folder']))
				$instance['show_theme_parent_folder'] = strip_tags($new_instance['show_theme_parent_folder']);
			else
				$instance['show_theme_parent_folder'] = 'yes';

			if (isset($new_instance['show_theme_version']))
				$instance['show_theme_version'] = strip_tags($new_instance['show_theme_version']);
			else
				$instance['show_theme_version'] = 'yes';

			// A little safety. If we aren't showing parent (child theme only) we can't do the group
			if ($instance['show_theme_parent'] == "no")
				$instance['show_theme_groups'] = 'no';
				
			return $instance;
		}

		function form( $instance ) {

			$instance = $this->update_instance_keys($instance);
			
			$display_type 				= ( isset( $instance['display_type'] ) ) 				? $instance['display_type'] 				: 	'list'; 
			$show_theme_groups 			= ( isset( $instance['show_theme_groups'] ) ) 			? $instance['show_theme_groups'] 			:	'yes';
			$show_theme_parent 			= ( isset( $instance['show_theme_parent'] ) ) 			? $instance['show_theme_parent'] 			: 	'yes';
			$show_theme_parent_folder 	= ( isset( $instance['show_theme_parent_folder'] ) ) 	? $instance['show_theme_parent_folder'] 	: 	'yes';
			$show_theme_version 		= ( isset( $instance['show_theme_version'] ) ) 			? $instance['show_theme_version'] 			:	'yes';

			?>		
			<p><label for="<?php echo $this->get_field_id('display_type'); ?>"><?php 
				_e( 'Display themes as:', 'advanced-theme-switcher' ); ?></label><br />
				<span><input type="radio" name="<?php echo $this->get_field_name('display_type'); ?>" value="list" <?php
					if ( 'list' == $display_type ) { echo ' checked="checked"'; }
				?> /> <?php _e( 'List', 'advanced-theme-switcher' ); ?></span>
				<span><input type="radio" name="<?php echo $this->get_field_name('display_type'); ?>" value="dropdown" <?php
					if ( 'dropdown' == $display_type ) { echo ' checked="checked"'; }
				?>/> <?php _e( 'Dropdown', 'advanced-theme-switcher' ); ?></span>
			</p>
			
			<p><label for="<?php echo $this->get_field_id('show_theme_parent'); ?>"><?php 
				_e( 'Show Theme Parent:', 'advanced-theme-switcher' ); ?></label><br />
				<span><input type="radio" name="<?php echo $this->get_field_name('show_theme_parent'); ?>" value="yes" <?php
					if ( 'yes' == $show_theme_parent ) { echo ' checked="checked"'; }
				?> /> <?php _e( 'Parent &amp; Child', 'advanced-theme-switcher' ); ?></span>
				<span><input type="radio" name="<?php echo $this->get_field_name('show_theme_parent'); ?>" value="no" <?php
					if ( 'no' == $show_theme_parent ) { echo ' checked="checked"'; }
				?>/> <?php _e( 'Child only', 'advanced-theme-switcher' ); ?></span>
			</p>

			<p><label for="<?php echo $this->get_field_id('show_theme_groups'); ?>"><?php 
				_e( 'Show Themes Grouped By Parent/Child Hierarchy: ', 'advanced-theme-switcher' ); ?></label><br />
				<span><input type="radio" name="<?php echo $this->get_field_name('show_theme_groups'); ?>" value="yes" <?php
					if ( 'yes' == $show_theme_groups ) { echo ' checked="checked"'; }
				?> /> <?php _e( 'Yes', 'advanced-theme-switcher' ); ?></span>
				<span><input type="radio" name="<?php echo $this->get_field_name('show_theme_groups'); ?>" value="no" <?php
					if ( 'no' == $show_theme_groups ) { echo ' checked="checked"'; }
				?>/> <?php _e( 'No', 'advanced-theme-switcher' ); ?></span>
			</p>

			<p><label for="<?php echo $this->get_field_id('show_theme_parent_folder'); ?>"><?php 
				_e( 'Show Theme Parent Directory:', 'advanced-theme-switcher' ); ?></label><br />
				<span><input type="radio" name="<?php echo $this->get_field_name('show_theme_parent_folder'); ?>" value="yes" <?php
					if ( 'yes' == $show_theme_parent_folder ) { echo ' checked="checked"'; }
				?> /> <?php _e( 'Yes', 'advanced-theme-switcher' ); ?></span>
				<span><input type="radio" name="<?php echo $this->get_field_name('show_theme_parent_folder'); ?>" value="no" <?php
					if ( 'no' == $show_theme_parent_folder ) { echo ' checked="checked"'; }
				?>/> <?php _e( 'No', 'advanced-theme-switcher' ); ?></span>
			</p>

			<p><label for="<?php echo $this->get_field_id('show_theme_version'); ?>"><?php 
				_e( 'Show Theme Version as part of Title:', 'advanced-theme-switcher' ); ?></label><br />
				<span><input type="radio" name="<?php echo $this->get_field_name('show_theme_version'); ?>" value="yes" <?php
					if ( 'yes' == $show_theme_version ) { echo ' checked="checked"'; }
				?> /> <?php _e( 'Yes', 'advanced-theme-switcher' ); ?></span>
				<span><input type="radio" name="<?php echo $this->get_field_name('show_theme_version'); ?>" value="no" <?php
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