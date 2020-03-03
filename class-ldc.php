<?php

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    defined('ABSPATH') or die('No script kiddies please!');

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    if(!class_exists('LDC', false)){
        class LDC {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    private static $file = '', $meta_boxes = array(), $settings_pages = array();

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function add_setting($settings_page = '', $meta_box_tab = '', $setting = array()){
		if($settings_page and $meta_box_tab and $setting){
			if($settings_page == 'LDC'){
				$icon_url = 'data:image/svg+xml;base64,PHN2ZyBpZD0iTGF5ZXJfMSIgZGF0YS1uYW1lPSJMYXllciAxIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA3OTQuMjIgNDQ4LjExIj48ZGVmcz48c3R5bGU+LmNscy0xe2ZpbGw6I2ZmZjt9PC9zdHlsZT48L2RlZnM+PHRpdGxlPmxkYy00czwvdGl0bGU+PHBhdGggY2xhc3M9ImNscy0xIiBkPSJNOTA3LjE3LDU0NC4xMUE5Niw5NiwwLDEsMSw5NzQuOCwzODBsNDUuMjYtNDUuMjZhMTYwLDE2MCwwLDEsMCwuNSwyMjYuMjdMOTc1LjMsNTE1Ljc0QTk1LjczLDk1LjczLDAsMCwxLDkwNy4xNyw1NDQuMTFaIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMjM1IC0xNjApIi8+PHBvbHlnb24gY2xhc3M9ImNscy0xIiBwb2ludHM9Ijc3OC40OSA0MTcuNzIgNzc4LjQ4IDQxNy43MyA3NzguNDkgNDE3LjcyIDc3OC40OSA0MTcuNzIiLz48Y2lyY2xlIGNsYXNzPSJjbHMtMSIgY3g9Ijc2Mi4yMiIgY3k9IjE5Ny44MSIgcj0iMzIiLz48Y2lyY2xlIGNsYXNzPSJjbHMtMSIgY3g9Ijc2Mi4yMiIgY3k9IjM3OC44MyIgcj0iMzIiLz48cmVjdCBjbGFzcz0iY2xzLTEiIHdpZHRoPSI2NCIgaGVpZ2h0PSI0NDgiIHJ4PSIzMiIvPjxwYXRoIGNsYXNzPSJjbHMtMSIgZD0iTTUyMywyODcuNzVhMTYwLDE2MCwwLDEsMCwxNjAsMTYwQTE2MCwxNjAsMCwwLDAsNTIzLDI4Ny43NVptMCwyNTZhOTYsOTYsMCwxLDEsOTYtOTZBOTYsOTYsMCwwLDEsNTIzLDU0My43NVoiIHRyYW5zZm9ybT0idHJhbnNsYXRlKC0yMzUgLTE2MCkiLz48cmVjdCBjbGFzcz0iY2xzLTEiIHg9IjM4NCIgd2lkdGg9IjY0IiBoZWlnaHQ9IjQ0OCIgcng9IjMyIi8+PC9zdmc+';
				$parent = '';
				$settings_page_id = 'ldc';
				$submenu_title = 'General';
			} else {
				$icon_url = '';
				$parent = 'ldc';
				$settings_page_id = 'ldc-' . sanitize_title(wp_strip_all_tags($settings_page));
				$submenu_title = '';
			}
			$meta_box_tab_id = $settings_page_id . '-' . sanitize_title(wp_strip_all_tags($meta_box_tab));
			$option_name = str_replace('-', '_', $settings_page_id);
			if(empty(self::$settings_pages[$settings_page_id])){
				self::$settings_pages[$settings_page_id] = array(
					'columns' => 1,
					'icon_url' => $icon_url,
					'id' => $settings_page_id,
					'menu_title' => $settings_page,
					'option_name' => $option_name,
					'page_title' => $settings_page . ' &#8212; ' . __('Settings'),
					'parent' => $parent,
					'style' => 'no-boxes',
					'tabs' => array(),
					'tab_style' => 'left',
				);
			}
			if(!isset(self::$settings_pages[$settings_page_id]['tabs'][$meta_box_tab_id])){
				self::$settings_pages[$settings_page_id]['tabs'][$meta_box_tab_id] = $meta_box_tab;
			}
			if(!isset(self::$meta_boxes[$meta_box_tab_id])){
				self::$meta_boxes[$meta_box_tab_id] = array(
					'fields' => array(),
					'id' => $meta_box_tab_id,
					'settings_pages' => $settings_page_id,
					'tab' => $meta_box_tab_id,
					'title' => $meta_box_tab . ' &#8212; ' . __('Settings'),
				);
			}
			if(empty($setting['columns'])){
				$setting['columns'] = 12;
			}
			self::$meta_boxes[$meta_box_tab_id]['fields'][] = $setting;
		}
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function admin_enqueue_scripts(){
        $screen = get_current_screen();
		if($screen->id != 'toplevel_page_ldc' and !array_key_exists(str_replace('ldc_page_', '', $screen->id), self::$settings_pages)){
			return;
		}
        if(wp_script_is('gist-embed', 'enqueued')){
            return;
        }
        if(!wp_script_is('gist-embed', 'registered')){
            wp_register_script('gist-embed', 'https://cdnjs.cloudflare.com/ajax/libs/gist-embed/2.7.1/gist-embed.min.js', array('jquery'), '2.7.1', true);
        }
        wp_enqueue_script('gist-embed');
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function admin_footer(){
		$screen = get_current_screen();
		if($screen->id == 'toplevel_page_ldc' or array_key_exists(str_replace('ldc_page_', '', $screen->id), self::$settings_pages)){ ?>
            <script>
                jQuery(function($){
                    $('#ldc_search_functions').on('input propertychange', function(){
                        var value = $(this).val().toLowerCase();
                        var values = value.split(' ');
                        $('.rwmb-meta-box').each(function(){
                            $(this).find('.rwmb-row').not(':first').filter(function(){
                                var row_value = $(this).find('.rwmb-input').text().toLowerCase();
                                var result_count = 0;
                                $.each(values, function(index, tmp_value){
                                    if(row_value.indexOf(tmp_value) > -1){
                                        result_count ++;
                                    }
                                });
                                if(values.length == result_count){
                                    $(this).show();
                                } else {
                                    $(this).hide();
                                }
                            });
                        });
                    });
                });
            </script><?php
        }
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function admin_head(){
		$screen = get_current_screen();
        if($screen->id == 'toplevel_page_ldc' or array_key_exists(str_replace('ldc_page_', '', $screen->id), self::$settings_pages)){ ?>
            <style>
                .form-wrap p,
                p.description,
                p.help,
                span.description {
                    font-style: normal;
                }
                code[data-gist-id] {
                    background: transparent;
                    border: 0;
                    margin: 0;
                    padding: 0;
                }
            </style><?php
        }
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function after_setup_theme(){
		global $wpdb;
		$empty_string = $wpdb->prepare('%s', '');
        self::add_setting('LDC', 'General', array(
			'name' => sprintf(__('%1$s is proudly powered by %2$s'), 'LDC', '<a href="https://luisdelcid.com" target="_blank">Luis del Cid</a>'),
			'std' => '<a class="button" href="https://luisdelcid.com" target="_blank">luisdelcid.com</a>',
			'type' => 'custom_html',
		));
		self::add_setting('LDC', 'Functions', array(
			'id' => 'ldc_search_functions',
			'name' => '',
			'type' => 'text',
			'placeholder' => __('Search'),
		));
		self::add_setting('LDC', 'Functions', array(
			'name' => '',
			'std' => ldc_format_function('ldc_attachment_url_to_postid', array(
				array(
					'type' => 'string',
					'name' => 'url',
					'default' => $empty_string,
				),
			)),
			'type' => 'custom_html',
		));
		self::add_setting('LDC', 'Functions', array(
			'name' => '',
			'std' => ldc_format_function('ldc_base64_urldecode', array(
				array(
					'type' => 'string',
					'name' => 'data',
					'default' => $empty_string,
				),
				array(
					'type' => 'bool',
					'name' => 'strict',
					'default' => 'false',
				),
			)),
			'type' => 'custom_html',
		));
		self::add_setting('LDC', 'Functions', array(
			'name' => '',
			'std' => ldc_format_function('ldc_base64_urlencode', array(
				array(
					'type' => 'string',
					'name' => 'data',
					'default' => $empty_string,
				),
			)),
			'type' => 'custom_html',
		));
		self::add_setting('LDC', 'Functions', array(
			'name' => '',
			'std' => ldc_format_function('ldc_format_function', array(
				array(
					'type' => 'string',
					'name' => 'function_name',
					'default' => $empty_string,
				),
				array(
					'type' => 'array',
					'name' => 'args',
					'default' => 'array()',
				),
			)),
			'type' => 'custom_html',
		));
		self::add_setting('LDC', 'Functions', array(
			'name' => '',
			'std' => ldc_format_function('ldc_guid_to_postid', array(
				array(
					'type' => 'string',
					'name' => 'guid',
					'default' => $empty_string,
				),
			)),
			'type' => 'custom_html',
		));
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function build_update_checker($url = '', $path = '', $slug = ''){
        if(!class_exists('Puc_v4_Factory', false)){
            require_once(plugin_dir_path(self::$file) . 'includes/plugin-update-checker-4.9/plugin-update-checker.php');
        }
        if($url and is_file($path) and $slug){
            Puc_v4_Factory::buildUpdateChecker($url, $path, $slug);
        }
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function init($file = ''){
        if(is_file($file)){
            self::$file = $file;
            self::build_update_checker('https://github.com/luisdelcid/ldc', self::$file, 'ldc');
            add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));
    		add_action('admin_footer', array(__CLASS__, 'admin_footer'));
    		add_action('admin_head', array(__CLASS__, 'admin_head'));
			add_action('after_setup_theme', array(__CLASS__, 'after_setup_theme'));
    		add_filter('mb_settings_pages', array(__CLASS__, 'mb_settings_pages'));
    		add_filter('rwmb_meta_boxes', array(__CLASS__, 'rwmb_meta_boxes'));
        }
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function mb_settings_pages($settings_pages){
		if(self::$settings_pages){
			foreach(self::$settings_pages as $index => $settings_page){
				asort(self::$settings_pages[$index]['tabs']);
			}
			$settings_pages = array_merge(array_values(self::$settings_pages), $settings_pages);
		}
		return $settings_pages;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    static public function rwmb_meta_boxes($meta_boxes){
		if(is_admin()){
			$meta_boxes = array_merge(array_values(self::$meta_boxes), $meta_boxes);
		}
		return $meta_boxes;
	}

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        }
    }

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
