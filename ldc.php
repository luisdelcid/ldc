<?php
/**
 * Author: Luis del Cid
 * Author URI: https://luisdelcid.com
 * Description: A collection of useful functions for your WordPress theme's functions.php.
 * Domain Path:
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network:
 * Plugin Name: LDC
 * Plugin URI: https://github.com/luisdelcid/ldc
 * Text Domain: ldc
 * Version: 0.3.2
 *
 */ // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    define('LDC_VERSION', '0.3.2');

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    defined('ABSPATH') or die('No script kiddies please!');
    if(!class_exists('LDC', false)){
        require_once(plugin_dir_path(__FILE__) . 'class-ldc.php');
    }
    LDC::init(__FILE__);

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
