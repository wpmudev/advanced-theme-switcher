# WordPress Theme Switcher Plugin - WPMU DEV

**INACTIVE NOTICE: This plugin is unsupported by WPMUDEV, we've published it here for those technical types who might want to fork and maintain it for their needs.**

# Theme Switcher

Let users switch between themes from the front end of a site – in a post, a page, a widget or right from the WordPress toolbar.


Let users switch between themes from the front end of a site.

* Switch themes from front end 
* Adds switcher to WordPress toolbar 
* Preview theme design from nice URLs 
* Great for Multisite and BuddyPress 
* Parent and child theme selector 
* List or drop-down selector 

## Theme Switcher gives your users the ability to switch between themes from the front end of your site.

It also provides you with the opportunity to preview different theme designs quickly and easily. Plus, it works perfectly for any WordPress, Multisite or BuddyPress site.

### Switch From Anywhere

The plugin comes complete with a handy widget that allows you to offer either a list or drop-down choice of themes.

![Enable your users to easily switch themes with a simple widget.][35]

Enable users to easily switch themes with a simple widget

You can also have a theme switcher right in your WordPress toolbar.

![Activate the theme switcher right in your WP toolbar!][36]

Add theme switcher to your toolbar

One of the coolest things about this plugin, is that it allows previews of different theme designs via nice URLs.

### Give Your Clients Options

This a must have for anyone wanting to offer their users maximum flexibility of personalization, or to demonstrate to clients different layout options on a WordPress, Multisite or BuddyPress network.

You won’t find a better theme switcher available for WordPress, Multisite or BuddyPress on the web.

### To Use

There are 3 ways the Advanced Theme Switcher plugin can be used:

* As a widget in any sidebar.
* As a shortcode anywhere you want it.
* As a menu item in your WordPress toolbar.

Let’s take a look at each method in detail, shall we?

### Using the Widget

The Theme Switcher widget enables you to simply drop a widget in any sidebar, and set the options with a few clicks.

You can set it up to display your theme selection either as a dropdown or a list, and you have full control over how the themes should be organized.

![advanced-theme-switcher-1090-widget][35]

### Using the Shortcode

The Advanced Theme Switcher now supports use of a shortcode. So you can place the themes listing in a post or page instead of being limited to the display with the built-in widget. As you are probably aware, widgets do not transfer between themes. So when the theme is switched you lose the widget (unless you add it to every theme you have and save them).

The basic shortcode using all default options is simply:  
[`adv_theme_switcher]`

There are many optional parameters you can add to the shortcode to customize the output of the themes list. They are the same options as you see in the widget:

_display_type_ – Possible values: no/false, dropdown, list. As the values imply, you can show the themes in dropdown/select or an unordered list. For example:  
[`adv_theme_switcher display_type='list']`

_show_theme_parent_ – Possible values: yes (default) or no. This controls display of the parent theme when it also has children. If set to ‘yes’ the parent and child will be displayed. If set to ‘no’ only child themes will be displayed. Also included in the display will be base themes which do not have child themes associated. For example:  
[`adv_theme_switcher show_theme_parent='yes']`

_show_theme_groups_ – Possible values: yes (default) or no. This option is only used if the ‘show_theme_parent’ option is set as ‘yes’. If set to ‘yes’ this option will display the parent/child themes represented in a hierarchy output. Meaning the child theme will be shown indented beneath the parent theme. If set to ‘no’ the parent and child themes will be displayed in a normal output. For example:  
[`adv_theme_switcher show_theme_parent='yes' show_theme_groups='yes']`

_show_theme_parent_folder_ – Possible values: yes (default) or no. This option depends on how your themes are organized within the /wp-content/themes/ directory. WordPress allows you to nest your themes into sub-directories. So for example you may group your themes into sub-directories like /wp-content/themes/Free, /wp-content/themes/Premium. WordPress only supports one level of sub-directory. If you have your themes organized within sub-directories and this option is set to ‘yes’ the plugin will display the output with an outer level of the hierarchy. The sub-directory name will be used as the label for this outer level. For example:  
[`adv_theme_switcher show_theme_parent_folder='yes']`

_show_theme_version_ – Possible values: yes (default) or no. When set to ‘yes’ this will include the theme version as part of the theme name. If set to ‘no’ the version will not be shown. For example:  
[`adv_theme_switcher show_theme_version='no']`

_show_theme_parent_filter_ – Value: theme directory text. Using this option you can limit the output of the themes to a partial listing. For example if your themes are organized into sub-directories (see notes on ‘show_theme_parent_folder’ option) and one of the sub-directories is named ‘Free’ then setting this option to ‘Free’ will show only themes within that sub-directory. Also, you can set this option to be a specific parent theme. As a second example assume you have a number of child themes based on the WordPress TwentyThirteen theme. You can set this option to ‘twentythirteen’ and the parent and any child themes only will be displayed. (This option is not available in the widget.) For example:  
[`adv_theme_switcher show_theme_parent_filter='twentythirteen']`

### Theme switcher in the WordPress toolbar

Yep, you can have a theme switcher right in your WordPress toolbar too!

![advanced-theme-switcher-1090-toolbar][36]

To get the theme switcher in your WordPress toolbar, you just need to add a simple define to your _wp-config.php_ file.  
`define('ADV_THEME_SWITCHER_TOOLBAR');`

The define as shown above will output the theme switcher with the default settings. But you can use the same shortcode options detailed above to customize the option=value pairs and control how the Advanced Theme Switcher outputs the information.

Note that when using the theme switcher in the WordPress toolbar, an additional value can be specified for the _display_type_ option: _menu_. And an additional option can be defined:

_display_type_sub_ – Possible values: dropdown (default), menu. For example, let’s show the sub-menu items in a dropdown instead of sub-menu items.  
`define('ADV_THEME_SWITCHER_TOOLBAR', 'display_type=menu&display_type_sub=dropdown');`

Note the difference in how the optional parameters are added to the define in wp-config.php. Each option should be separated by an ampersand (&), and should **not **be enclosed in quote marks.

### Setting custom default options

You can also set your own default options in your wp-config.php by adding this define, and adjusting the values for each option:  
`define('ADV_THEME_SWITCHER_DEFAULTS', 'display_type=dropdown&show_theme_parent=yes&show_theme_version=yes&show_theme_groups=yes&show_theme_parent_folder=yes');`

This means for widgets and shortcode this will be the default option set used. You can then override these default options via the local shortcode or widget options.


[35]: https://premium.wpmudev.org/wp-content/uploads/2009/12/advanced-theme-switcher-1090-widget1.png
[36]: https://premium.wpmudev.org/wp-content/uploads/2009/12/advanced-theme-switcher-1090-toolbar1.png

