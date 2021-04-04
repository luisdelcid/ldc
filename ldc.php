<?php
/*
Plugin Name: LDC
Plugin URI: https://github.com/luisdelcid/ldc
Description: A collection of useful functions for your WordPress theme's functions.php.
Version: 0.4.4
Requires at least: 5.6
Requires PHP: 5.6
Author: Luis del Cid
Author URI: https://github.com/luisdelcid
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ldc
Domain Path:
Network: true
*/

if(defined('ABSPATH')){
    require_once(plugin_dir_path(__FILE__) . 'class-ldc.php');
    ldc()->build_update_checker('https://github.com/luisdelcid/ldc', __FILE__, 'ldc');
    ldc()->on('after_setup_theme', function(){
        $file = get_stylesheet_directory() . '/ldc-functions.php';
        if(file_exists($file)){
            require_once($file);
        }
    });
    $ldc_fs = ldc()->filesystem();
    if(is_wp_error($ldc_fs)){
        ldc()->add_admin_notice('<strong>LDC ' . strtolower(__('Error')) . '</strong>: ' . $ldc_fs->get_error_message());
    }
    unset($ldc_fs);
}
