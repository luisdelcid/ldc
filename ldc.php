<?php
/*
 * Plugin Name: LDC
 * Plugin URI: https://github.com/luisdelcid/ldc
 * Description: A collection of methods for WordPress.
 * Version: 26.4.2.1
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

// Load PHP methods.
require_once plugin_dir_path(__FILE__) . 'loader/load.php';

// Load JavaScript methods.
add_action('admin_enqueue_scripts', ['ldc', '_enqueue_scripts']);
add_action('login_enqueue_scripts', ['ldc', '_enqueue_scripts']);
add_action('wp_enqueue_scripts', ['ldc', '_enqueue_scripts']);

// Wait for the `plugins_loaded` action hook.
add_action('plugins_loaded', function(){

    // Check for updates as soon as possible.
    $update_checker = ldc::plugin_update_checker();
    if(is_wp_error($update_checker)){
        ldc::add_admin_notice($update_checker->get_error_message(), 'error');
    }

    // Fires after the plugin is loaded.
    do_action('ldc_loaded');

});

// Wait for the `after_setup_theme` action hook.
add_action('after_setup_theme', function(){

    // Load the functions for the active theme, for both parent and child theme if applicable.
    foreach(wp_get_active_and_valid_themes() as $theme){
        if(file_exists($theme . '/ldc-functions.php')){
            require_once $theme . '/ldc-functions.php';
        }
    }

    // Fires after the theme is loaded.
    do_action('after_setup_ldc');

});
