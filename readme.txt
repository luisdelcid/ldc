=== LDC ===
Contributors: luisdelcid
Donate link: https://github.com/luisdelcid
Tags: ldc
Tested up to: 6.9.4
Requires at least: 5.6
Requires PHP: 5.6
Stable tag: 26.4.2.1
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A collection of methods for WordPress.

== Description ==

A collection of methods for WordPress.

= For themes =

Create a new file called `ldc-functions.php` in the root directory of your theme, if it doesn't already exist. Then add your custom theme functions there.

If you are unable to create the file, add the following to your `functions.php` file:

`add_action('after_setup_ldc', function(){`
`  // Add your custom theme functions here.`
`});`

Note that `after_setup_ldc` is the **first action hook available to themes**, instead of `after_setup_theme`.

= For plugins =

Add the following to your main plugin file:

`add_action('ldc_loaded', function(){`
`  // Add your custom plugin functions here.`
`});`

Note that `ldc_loaded` is the **first action hook available to plugins**, instead of `plugins_loaded`.

== Changelog ==

To see what’s changed, visit the [Plugin Homepage](https://github.com/luisdelcid/ldc).
