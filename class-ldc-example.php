<?php

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    defined('ABSPATH') or die('No script kiddies please!');

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    if(!class_exists('LDC', false)){
        class LDC extends LDC_Plugin_Base {

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function init($file = ''){
        parent::init($file);
        self::add_setting(array(
            'id' => 'ldc_search_functions',
			'name' => '',
			'type' => 'text',
			'placeholder' => __('Search'),
        ));
        self::require_meta_box_extension(array(
            'name' => 'MB Settings Page',
            'slug' => 'mb-settings-page',
			'url' => 'https://metabox.io/plugins/mb-settings-page/',
        ));
        self::require_meta_box_extension(array(
            'name' => 'Meta Box Columns',
            'slug' => 'meta-box-columns',
			'url' => 'https://metabox.io/plugins/meta-box-columns/',
        ));
        self::require_plugin(array(
            'basename' => 'meta-box/meta-box.php',
            'name' => 'Meta Box',
			'url' => 'https://wordpress.org/plugins/meta-box/',
        ));
        self::require_plugin(array(
            'basename' => 'meta-box-aio/meta-box-aio.php',
            'name' => 'Meta Box AIO',
			'url' => 'https://metabox.io/my-account/',
        ));
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        }
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
