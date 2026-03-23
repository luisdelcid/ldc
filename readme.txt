=== LDC ===
Contributors: luisdelcid
Donate link: https://profiles.wordpress.org/luisdelcid
Tags: ldc
Tested up to: 6.9.4
Requires at least: 5.6
Requires PHP: 5.6
Stable tag: 26.3.22.1
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A personal collection of methods and tools for plugin and theme developers.

== Description ==

A personal collection of methods and tools for plugin and theme developers.

= For themes =

Create a new file called `ldc-functions.php` in the root directory of your theme, if it doesn't already exist. Then add your custom functions there.

If you are unable to create the file, add the following to your `functions.php` file:

`add_action('after_setup_ldc', function(){`
`  // Add your custom functions here.`
`});`

Note that `after_setup_ldc` is the **first action hook available to themes**, instead of `after_setup_theme`.

= For plugins =

Add the following to your main plugin file:

`add_action('ldc_loaded', function(){`
`  // Add your custom functions here.`
`});`

Note that `ldc_loaded` is the **first action hook available to plugins**, instead of `plugins_loaded`.

== Changelog ==

To see what’s changed, visit the [GitHub repository](https://github.com/luisdelcid/ldc).
