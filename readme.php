=== LDC ===
Contributors: luisdelcid
Donate link: https://github.com/luisdelcid
Tags: ldc
Tested up to: 7.1.4
Requires at least: 6.4
Requires PHP: 8.3
Stable tag: 26.10.16
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A personal collection of useful methods for WordPress developers.

== Description ==

A personal collection of useful methods for WordPress developers.

= For themes =

Create a new file called `ldc-methods.php` in the root directory of your theme, if it doesn't already exist. Then add your custom theme functions there.

If you are unable to create the file, add the following to your `functions.php` file:

`if(did_action('ldc')){`
`  // Add your custom theme functions here.`
`}`

= For plugins =

Add the following to your main plugin file:

`if(did_action('ldc')){`
`  // Add your custom plugin functions here.`
`}`

== Changelog ==

To see what’s changed, visit the [GitHub repository](https://github.com/luisdelcid/ldc).
