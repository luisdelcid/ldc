<?php

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    defined('ABSPATH') or die('No script kiddies please!');
    if(!class_exists('LDC_Base', false)){
        class LDC_Base {

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    private static $file = '';

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function add_setting($setting = array(), $tab = '', $page = ''){
        if(!$tab){
            $tab = __('General');
        }
        if(!$page){
            $page = self::get_name();
        }
		LDC_Plugin_Helper::add_setting($page, $tab, $setting);
	}

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function get_basename(){
        return plugin_basename(self::$file);
	}

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function get_github_url(){
        return 'https://github.com/luisdelcid/' . self::get_slug();
	}

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function get_name(){
        return str_replace('_', ' ', str_replace('LDC_', '', __CLASS__));
	}

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function get_slug(){
        return str_replace('_', '-', strtolower(__CLASS__));
	}

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function init($file = ''){
        LDC_Plugin_Helper::build_update_checker(self::get_github_url(), $file, self::get_slug());
        self::$file = $file;
	}

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function require_meta_box_extension($extension = array()){
        LDC_Plugin_Helper::require_meta_box_extension(__CLASS__, $extension);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function require_plugin($plugin = array()){
        LDC_Plugin_Helper::require_plugin(__CLASS__, $plugin);
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        }
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
