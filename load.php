<?php

// Don't load directly.
defined('ABSPATH') || die('-1');

// Stores the location of the directory of classes and core content.
$_ldc_inc = plugin_dir_path(__FILE__) . 'includes';

// Load the PHP base classes first, in this order, the remaining ones may extend them: LDC_Remote extends LDC_Multiton, and LDC_Async and LDC_Zoom extend LDC_Remote.
foreach(['multiton', 'singleton', 'remote'] as $_ldc_inc_class){
    include_once $_ldc_inc . '/classes/' . $_ldc_inc_class . '.php';
}
unset($_ldc_inc_class);

// Load the remaining PHP classes, LDC among them: every function of the plugin is one of its methods.
foreach(glob($_ldc_inc . '/classes/*.php') as $_ldc_inc_file){
    include_once $_ldc_inc_file;
}
unset($_ldc_inc_file);

// The master function: returns the one LDC instance, from which every method is reached. E.g. ldc()->download_url($url);
if(!function_exists('ldc')){
    function ldc(){
        static $ldc = null;
        if(is_null($ldc)){
            $ldc = LDC::get_instance(); // Kept, because every method call goes through here — the cache's, for one — and LDC_Singleton::get_instance() hashes the class name each time it is asked.
        }
        return $ldc;
    }
}

// Everything below runs through these names, and any of them may already belong to another plugin or theme: the guards above would then have kept that one, and the plugin would be running someone else's code. Stop here and say which, instead of failing far from the cause.
$_ldc_classes = wp_normalize_path(realpath($_ldc_inc . '/classes'));
$_ldc_taken = [];
foreach(['LDC', 'LDC_Async', 'LDC_CL_Image', 'LDC_Cache', 'LDC_Error', 'LDC_Multiton', 'LDC_Plugin', 'LDC_Remote', 'LDC_Singleton', 'LDC_Tool', 'LDC_Zoom'] as $_ldc_class){ // Hardcoded. Every class in includes/classes.
    if(!class_exists($_ldc_class, false) || wp_normalize_path(realpath(dirname((new ReflectionClass($_ldc_class))->getFileName()))) !== $_ldc_classes){
        $_ldc_taken[] = $_ldc_class;
    }
}
if(wp_normalize_path(realpath((new ReflectionFunction('ldc'))->getFileName())) !== wp_normalize_path(realpath(__FILE__))){
    $_ldc_taken[] = 'ldc()';
}
if($_ldc_taken){
    add_action('admin_notices', function() use ($_ldc_taken){
        echo '<div class="notice notice-error"><p>' . esc_html(sprintf('LDC is not running: %s already declared by another plugin or theme.', implode(', ', $_ldc_taken))) . '</p></div>';
    });
    unset($_ldc_inc, $_ldc_classes, $_ldc_taken, $_ldc_class);
    return;
}
unset($_ldc_classes, $_ldc_taken, $_ldc_class);

// Attach the default filters.
include_once $_ldc_inc . '/default-filters.php';
unset($_ldc_inc);

// Load JavaScript classes and functions.
add_action('admin_enqueue_scripts', [ldc(), '_load_dependencies'], 5);
add_action('login_enqueue_scripts', [ldc(), '_load_dependencies'], 5);
add_action('wp_enqueue_scripts', [ldc(), '_load_dependencies'], 5);

// Load theme functions.
add_action('after_setup_theme', [ldc(), '_load_theme_functions'], 5);

// Fires after the must-use plugin is loaded.
do_action('ldc');
