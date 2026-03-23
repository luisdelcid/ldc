<?php
/*
 * Plugin Name: LDC
 * Plugin URI: https://github.com/luisdelcid/ldc
 * Description: A personal collection of methods and tools for plugin and theme developers.
 * Version: 26.3.22.1
 * Requires at least: 5.6
 * Requires PHP: 5.6
 * Author: Luis del Cid
 * Author URI: https://github.com/luisdelcid
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ldc
 * Update URI: https://github.com/luisdelcid/ldc
 */

/*
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// Don't load directly.
if(!defined('ABSPATH')){
    die('-1');
}

// Load PHP classes.
foreach(glob(plugin_dir_path(__FILE__) . 'includes/classes/*.php') as $_ldc_class_file){
    require_once $_ldc_class_file;
}
unset($_ldc_class_file);

// Check for updates as soon as possible.
$_ldc_update_checker = ldc::plugin_update_checker();
if(is_wp_error($_ldc_update_checker)){
    ldc::add_admin_notice($_ldc_update_checker->get_error_message(), [
        'type' => 'error',
    ]);
}
unset($_ldc_update_checker);

// Load JavaScript classes.
if(!has_action('admin_enqueue_scripts', ['ldc', '_enqueue_scripts'])){
    add_action('admin_enqueue_scripts', ['ldc', '_enqueue_scripts']);
}
if(!has_action('login_enqueue_scripts', ['ldc', '_enqueue_scripts'])){
    add_action('login_enqueue_scripts', ['ldc', '_enqueue_scripts']);
}
if(!has_action('wp_enqueue_scripts', ['ldc', '_enqueue_scripts'])){
    add_action('wp_enqueue_scripts', ['ldc', '_enqueue_scripts']);
}

// Load theme functions.
if(!has_action('after_setup_theme', ['ldc', '_setup_theme'])){
    add_action('after_setup_theme', ['ldc', '_setup_theme']);
}

// Wait for the `plugins_loaded` action hook.
add_action('plugins_loaded', function(){

    // Fires after the plugin is loaded.
    do_action('ldc_loaded');

});

// Wait for the `after_setup_theme` action hook.
add_action('after_setup_theme', function(){

    // Fires after the theme is loaded.
    do_action('after_setup_ldc');

});
