<?php

if(!class_exists('ldc')){
	final class ldc {

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Hardcoded
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public static $branding = 'LDC';

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return void
         */
        public static function _enqueue_scripts($hook_suffix = ''){
    		if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $file = plugin_dir_path(dirname(__FILE__)) . 'js/ldc.js';
    		if(file_exists($file)){
                $src = self::path_to_url($file);
        		$ver = filemtime($file);
        		wp_enqueue_script('stackframe', 'https://cdn.jsdelivr.net/npm/stackframe@1.3.4/stackframe.min.js', [], '1.3.4');
        		wp_enqueue_script('error-stack-parser', 'https://cdn.jsdelivr.net/npm/error-stack-parser@2.1.4/error-stack-parser.min.js', ['stackframe'], '2.1.4');
        		wp_enqueue_script('ldc', $src, ['error-stack-parser', 'jquery', 'underscore', 'utils', 'wp-api', 'wp-hooks'], $ver, true);
        		wp_localize_script('ldc', 'ldc', [
        			'home_url' => home_url(),
					'locale' => get_locale(),
        			'mu_plugins_url' => WPMU_PLUGIN_URL,
        			'plugins_url' => WP_PLUGIN_URL,
        			'site_url' => site_url(),
        		]);
    		}
            $file = plugin_dir_path(dirname(__FILE__)) . 'css/ldc.css';
            if(file_exists($file)){
                $src = self::path_to_url($file);
                $ver = filemtime($file);
                wp_enqueue_style('ldc', $src, [], $ver);
            }
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * This function MUST be called inside the 'tgmpa_register' action hook.
         *
    	 * @return void
    	 */
        public static function _maybe_tgmpa_register(){
            if(!doing_action('tgmpa_register')){
    			return; // Too early or too late.
    		}
            $group = 'tgmpa';
            $tgmpa = self::cache_all($group);
    		foreach($tgmpa as $args){
    			self::tgmpa($args['plugins'], $args['config']);
    		}
    		$group = 'tgmpa_plugins';
            $plugins = self::cache_all($group);
    		if(!$plugins){
    			return;
    		}
    		self::tgmpa($plugins, [
    			'id' => 'ldc-plugins',
    			'menu' => 'ldc-plugin-install',
    			'parent_slug' => 'plugins.php',
    		    'strings' => [
    				'menu_title' => 'LDC',
    				'page_title' => __('Add Plugins'),
    		    ],
    		]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * This function MUST be called inside the 'after_setup_theme' action hook.
         *
         * @return void
         */
        public static function _setup_theme(){
    		if(!doing_action('after_setup_theme')){
                return; // Too early or too late.
            }
            foreach(wp_get_active_and_valid_themes() as $theme){
                if(file_exists($theme . '/ldc-functions.php')){
                    require_once $theme . '/ldc-functions.php'; // Load the functions for the active theme, for both parent and child theme if applicable.
                }
            }
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function add_hiding_rule($args = []){
            if(is_multisite()){
        		return; // The rewrite rules are not for WordPress MU networks.
        	}
        	$pairs = [
                'capability' => '',
                'exclude_other_media' => [],
                'exclude_public_media' => false,
                'file' => '',
        		'subdir' => '',
        	];
            $args = shortcode_atts($pairs, $args);
        	$md5 = self::md5($args);
        	$uploads_use_yearmonth_folders = false;
            $subdir = self::unslashit($args['subdir']);
        	if($subdir){
        		$subdir = '/(' . $subdir . ')';
        	} else {
        		if(get_option('uploads_use_yearmonth_folders')){
        			$subdir = '/(\d{4})/(\d{2})';
        			$uploads_use_yearmonth_folders = true;
        		}
        	}
        	$upload_dir = wp_get_upload_dir();
        	if($upload_dir['error']){
        		return;
        	}
            $atts = [];
            $path = self::get_shortinit_dir() . 'readfile.php';
            if(!file_exists($path)){
                return;
            }
        	$tmp = str_replace(wp_normalize_path(ABSPATH), '', wp_normalize_path($path));
        	$parts = explode('/', $tmp);
        	$levels = count($parts);
        	$query = self::path_to_url($path);
        	$regex = $upload_dir['baseurl'] . $subdir. '/(.+)';
        	if($uploads_use_yearmonth_folders){
        		$atts['yyyy'] = '$1';
        		$atts['mm'] = '$2';
        		$atts['file'] = '$3';
        	} else {
        		$atts['subdir'] = '$1';
        		$atts['file'] = '$2';
        	}
        	$atts['levels'] = $levels;
            $atts['md5'] = $md5;
            $value = [
                'capability' => $args['capability'],
                'exclude_other_media' => $args['exclude_other_media'],
                'exclude_public_media' => $args['exclude_public_media'],
            ];
            $option = 'ldc_hiding_rule_' . $md5;
            update_option($option, $value, 'no');
        	$query = add_query_arg($atts, $query);
        	self::add_external_rule($regex, $query, $args['file']);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function add_inline_script($data = '', $position = 'after'){
            if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            return wp_add_inline_script('ldc', $data, $position);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function add_inline_style($data = ''){
    		if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            return wp_add_inline_style('ldc', $data);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function context_enqueue($context = '', $handle = '', $src = '', $deps = [], $ver = false, $args_media = true, $l10n = []){
            if(doing_action($context . '_enqueue_scripts')){
                return self::enqueue_dependency($handle, $src, $deps, $ver, $args_media, $l10n); // Just in time.
            }
            if(did_action($context . '_enqueue_scripts')){
    			return self::doing_it_wrong(__FUNCTION__); // Too late.
            }
    		if(!in_array($context, ['admin', 'login', 'wp'])){
    			return self::doing_it_wrong(__FUNCTION__);
    		}
            if(!$handle){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'handle');
                return self::error($error_msg);
            }
            $dependency = [
                'args_media' => $args_media,
                'deps' => $deps,
                'handle' => $handle,
    			'l10n' => $l10n,
                'src' => $src,
                'ver' => $ver,
            ];
            $md5 = self::md5($dependency);
    		self::add_action_once($context . '_enqueue_scripts', [__CLASS__, 'maybe_enqueue_' . $context . '_dependencies']);
            self::cache_set($context . '_dependencies', $md5, $dependency);
            return $handle;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function enqueue_script($handle = '', $src = '', $deps = [], $ver = false, $args = [], $l10n = []){
    		if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
    		$handle = sanitize_title($handle);
            if(!$handle){
                return;
            }
            if(!wp_http_validate_url($src)){
                if(!is_file($src)){
                    return;
                }
                if(!$ver){
                    $ver = filemtime($src);
                }
                $src = self::path_to_url($src);
            }
            $filename = self::basename($src);
            $mimes = [
                'js' => 'application/javascript',
            ];
            $filetype = wp_check_filetype($filename, $mimes);
            if(!$filetype['ext']){
                return;
            }
            if(!in_array('ldc', $deps)){
                $deps[] = 'ldc';
            }
            wp_enqueue_script($handle, $src, $deps, $ver, $args);
            if($l10n){
                self::localize_script($handle, $l10n);
            }
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function enqueue_style($handle = '', $src = '', $deps = [], $ver = false, $media = 'all'){
    		if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
    		$handle = sanitize_title($handle);
            if(!$handle){
                return;
            }
            if(!wp_http_validate_url($src)){
                if(!is_file($src)){
                    return;
                }
                if(!$ver){
                    $ver = filemtime($src);
                }
                $src = self::path_to_url($src);
            }
            $filename = self::basename($src);
            $mimes = [
                'css' => 'text/css',
            ];
            $filetype = wp_check_filetype($filename, $mimes);
            if(!$filetype['ext']){
                return;
            }
            if(!in_array('ldc', $deps)){
                $deps[] = 'ldc';
            }
            wp_enqueue_style($handle, $src, $deps, $ver, $media);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return WP_Error
         */
        public static function error($message = '', $data = ''){
    		if(is_wp_error($message)){
    			return $message;
    		}
    		if(!$message){
    			$message = __('An error occurred.'); // Something went wrong.
    		}
    		if(class_exists('ldc\error')){
    			return new \ldc\error('ldc_error', $message, $data);
    		}
    		return new \WP_Error('ldc_error', $message, $data); // Backcompat.
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object|WP_Error
         */
        public static function get_instance($class = ''){
    		if(!class_exists($class)){
    			return self::missing_params($class);
    		}
            $parent = 'ldc\singleton';
            if(!class_exists($parent)){
    			return self::missing_params($parent);
    		}
    		if(!is_subclass_of($class, $parent)){
    			return self::invalid_params($class);
    		}
    		return call_user_func([$class, 'get_instance']);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function get_shortinit_dir(){
    		return plugin_dir_path(__FILE__) . 'shortinit';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function get_upload_dir($subdir = ''){
    		$upload_dir = wp_get_upload_dir();
    		if($upload_dir['error']){
    			return self::error($upload_dir['error']);
    		}
            $basedir = self::path_join($upload_dir['basedir'], 'ldc');
            $target = self::path_join($basedir, $subdir);
    		return self::mkdir_p($target);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return string
         */
        public static function global_cache_key(){
    		static $global = '';
    		if($global){
    			return $global;
    		}
    		$global = 'ldc_cache';
    		if(!isset($GLOBALS[$global])){
    			$GLOBALS[$global] = []; // Set.
    		}
    		if(!is_array($GLOBALS[$global])){
    			$GLOBALS[$global] = []; // Reset.
    		}
    		return $global;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function str_prefix($str = '', $prefix = ''){
			$str = sanitize_text_field($str);
    		if(!$str){
    			return '';
    		}
    		$prefix = self::canonicalize($prefix);
			if(!$prefix){
    			return $str;
    		}
    		if(self::str_starts_with($str, $prefix)){
    			return $str; // Text is already prefixed.
    		}
    		return $prefix . '_' . $str;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function str_slug($str = '', $slug = ''){
			$str = sanitize_text_field($str);
    		if(!$str){
    			return '';
    		}
    		$slug = sanitize_title($slug);
			if(!$slug){
    			return $str;
    		}
    		if(self::str_starts_with($str, $slug)){
    			return $str; // Text is already slugged.
    		}
    		return $slug . '-' . $str;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Ace (Ajax.org Cloud9 Editor)
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function ace_ver($set = ''){
            static $ver = '1.43.6';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function enqueue_ace($deps = []){
    		if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = self::use_ace();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $src = self::path_to_url($dir) . '/src-min';
            $ver = self::ace_ver();
            self::enqueue_dependency('ace', $src . '/ace.js', $deps, $ver);
            self::enqueue_dependency('ace-language-tools', $src . '/ext-language_tools.js', ['ace'], $ver);
            $data = "_.isUndefined(ace)||(ace.config.set('basePath','$src'),ace.require('ace/ext/language_tools'))";
            wp_add_inline_script('ace-language-tools', $data);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function use_ace(){
            $ver = self::ace_ver();
            $url = 'https://github.com/ajaxorg/ace-builds/archive/refs/tags/v' . $ver . '.zip';
            return self::use([
                'expected_dir' => 'ace-builds-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Admin notices
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'admin_notices' action hook.
    	 *
    	 * @return void
    	 */
        public static function _maybe_add_admin_notices(){
    		if(!doing_action('admin_notices')){
    	        return; // Too early or too late.
    	    }
            $admin_notices = self::cache_all('admin_notices');
    		foreach($admin_notices as $admin_notice){
    			self::admin_notice($admin_notice['message'], $admin_notice['type'], $admin_notice['is_dismissible']);
    		}
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function add_admin_notice($message = '', $type = '', $is_dismissible = false){
    		if(doing_action('admin_notices')){
    	        self::admin_notice($message, $type, $is_dismissible);
    			return; // Just in time.
    	    }
    		if(did_action('admin_notices')){
    			return; // Too late.
    		}
    		$admin_notice = [
    			'is_dismissible' => $is_dismissible,
    			'message' => $message,
                'type' => $type,
    		];
            $group = 'admin_notices';
            $key = self::md5($admin_notice);
    		self::add_action_once('admin_notices', [__CLASS__, '_maybe_add_admin_notices']);
            self::cache_set($key, $admin_notice, $group);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * This function MUST be called inside the 'admin_notices' action hook.
         *
         * @return void
         */
        public static function admin_notice($message = '', $type = '', $is_dismissible = false){
            if(!doing_action('admin_notices')){
                return; // Too early or too late.
            }
            echo self::get_admin_notice($message, $type, $is_dismissible);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_admin_notice($message = '', $type = '', $is_dismissible = false){
            if(!in_array($type, ['error', 'info', 'success', 'warning'])){
                $type = '';
            }
            if(function_exists('wp_get_admin_notice')){
                $args = [
                    'dismissible' => $is_dismissible,
                    'type' => $type,
                ];
                return wp_get_admin_notice($message, $args); // @since 6.4.0
            }
            if(empty($type)){
                $type = 'warning';
            }
            if($is_dismissible){
                $type .= ' is-dismissible';
            }
            return '<div class="notice notice-' . $type . '"><p>' . $message . '</p></div>'; // Backcompat.
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Animate.css
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function animate_css_ver($set = ''){
            static $ver = '4.1.1';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function enqueue_animate_css($deps = []){
    		if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = self::use_animate_css();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $src = self::path_to_url($dir) . '/animate.min.css';
            $ver = self::animate_css_ver();
            self::enqueue_dependency('animate-css', $src, $deps, $ver);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function use_animate_css(){
            $ver = self::animate_css_ver();
            $url = 'https://github.com/animate-css/animate.css/archive/refs/tags/v' . $ver . '.zip';
            return self::use([
                'expected_dir' => 'animate.css-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Arrays
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return array
         */
        public static function array_a($input_list = [], $index_key = '', $field = ''){
            if(empty($index_key)){
                return $input_list;
            }
            if(!empty($field)){
                return wp_list_pluck($input_list, $field, $index_key); // Same as wp_list_pluck().
            }
            $newlist = [];
            foreach($input_list as $value){
                if(is_object($value)){
                    if(isset($value->$index_key)){
                        $newlist[$value->$index_key] = $value;
                    } else {
                        $newlist[] = $value;
                    }
                } else {
                    if(isset($value[$index_key])){
                        $newlist[$value[$index_key]] = $value;
                    } else {
                        $newlist[] = $value;
                    }
                }
            }
            return $newlist;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Checks if the given keys exist in the array.
    	 *
         * @return bool
         */
        public static function array_keys_exist($keys = [], $array = []){
            if(!is_array($keys) || !is_array($array)){
                return false;
            }
            $result = true;
            foreach($keys as $key){
                if(array_key_exists($key, $array)){
                    continue;
                }
                $result = false;
                break;
            }
            return $result;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Determines if the variable is an associative array.
    	 *
         * @return bool
         */
        public static function is_associative_array($array = []){
            if(!is_array($array)){
                return false;
            }
            return !self::is_numeric_array($array);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Determines if the variable is a numeric-indexed array.
    	 *
         * @return bool
         */
        public static function is_numeric_array($array = []){
            if(!is_array($array)){
                return false;
            }
            if(!$array){
                return true; // Empty.
            }
            return array_keys($array) === range(0, count($array) - 1);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return array
         */
        public static function recursive_ksort($array = []){
            if(!is_array($array)){
                return $array;
            }
            foreach($array as $key => $value){
                $array[$key] = self::recursive_ksort($value); // Recursive.
            }
    		if(!self::is_associative_array($array)){
                return $array;
            }
    		ksort($array);
            return $array;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Asynchronous HTTP requests
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'http_request_args' filter hook.
    	 *
    	 * @return array
    	 */
        public static function _async_http_request_args($parsed_args, $url){
    		if(!doing_filter('http_request_args')){
    	        return $parsed_args; // Too early or too late.
    	    }
    		$parsed_args['blocking'] = false;
    		$parsed_args['timeout'] = 0.01;
    		return $parsed_args;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'http_api_transports' filter hook.
    	 *
    	 * @return array
    	 */
        public static function _fsockopen_first($transports, $args, $url){
    		if(!doing_filter('http_api_transports')){
    	        return $transports; // Too early or too late.
    	    }
    		$index = array_search('streams', $transports);
    	    if($index === false){
    	        return $transports; // fsockopen is not supported.
    	    }
    	    if(count($transports) === 1){
    	        return $transports; // Only fsockopen is supported.
    	    }
    	    if($index === 0){
    	        return $transports; // fsockopen is the first one.
    	    }
    	    unset($transports[$index]);
    	    $transports = array_merge(['streams'], $transports);
    	    return $transports;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function async_request($method = '', $url = '', $args = []){
    		add_filter('http_api_transports', [__CLASS__, '_fsockopen_first'], 10, 3);
    		add_filter('http_request_args', [__CLASS__, '_async_http_request_args'], 10, 2);
    		$response = self::remote_request($method, $url, $args);
    	    remove_filter('http_request_args', [__CLASS__, '_async_http_request_args']);
    		remove_filter('http_api_transports', [__CLASS__, '_fsockopen_first']);
    		return $response;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Attachments
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int
         */
        public static function attachment_url_to_postid($url = ''){
            $post_id = attachment_url_to_postid($url);
            if($post_id){
                return $post_id;
            }
            $post_id = self::guid_to_postid($url);
            if($post_id){
                return $post_id;
            }
            preg_match('/^(.+)(\-\d+x\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
            if($matches){
                $url = $matches[1];
                if(isset($matches[3])){
                    $url .= $matches[3];
                }
                $post_id = self::guid_to_postid($url);
                if($post_id){
                    return $post_id; // Resized.
                }
            }
            preg_match('/^(.+)(\-scaled)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
            if($matches){
                $url = $matches[1];
                if(isset($matches[3])){
                    $url .= $matches[3];
                }
                $post_id = self::guid_to_postid($url);
                if($post_id){
                    return $post_id; // Scaled.
                }
            }
            preg_match('/^(.+)(\-e\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
            if($matches){
                $url = $matches[1];
                if(isset($matches[3])){
                    $url .= $matches[3];
                }
                $post_id = self::guid_to_postid($url);
                if($post_id){
                    return $post_id; // Edited.
                }
            }
            return 0;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int
         */
        public static function guid_to_postid($guid = ''){
            global $wpdb;
            $query = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid = %s", $guid);
            $post_id = $wpdb->get_var($query);
            if(is_null($post_id)){
                return 0;
            }
            return (int) $post_id;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function maybe_generate_attachment_metadata($attachment_id = 0){
            $attachment = get_post($attachment_id);
            if(is_null($attachment)){
                return;
            }
            if($attachment->post_type !== 'attachment'){
                return;
            }
            if(!function_exists('wp_generate_attachment_metadata')){
                require_once ABSPATH . 'wp-admin/includes/image.php';
            }
            wp_maybe_generate_attachment_metadata($attachment);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int|WP_Error
         */
        public static function sideload($file = '', $post_id = 0, $generate_attachment_metadata = true){
            if(!file_exists($file)){
                return self::missing_file($file);
            }
            $filename = self::test_type($file, wp_basename($file));
            if(is_wp_error($filename)){
                return $filename;
            }
            $filetype_and_ext = wp_check_filetype($filename);
            $attachment_id = wp_insert_attachment([
                'guid' => self::path_to_url($file),
                'post_mime_type' => $filetype_and_ext['type'],
                'post_status' => 'inherit',
                'post_title' => preg_replace('/\.[^.]+$/', '', $filename), // Use the original filename. Remove the file extension (after the last `.`)
            ], $file, $post_id, true);
            if(is_wp_error($attachment_id)){
                return $attachment_id;
            }
            if(!$generate_attachment_metadata){
                return $attachment_id;
            }
            self::maybe_generate_attachment_metadata($attachment_id);
            return $attachment_id;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Authentication
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * This function MUST be called inside the 'authenticate' filter hook.
         *
         * @return WP_User|WP_Error|false
         */
        public static function _maybe_authenticate_without_password($user = null, $username_or_email = '', $password = ''){
            if(!doing_filter('authenticate')){
                return $user; // Too early or too late.
            }
            if(!is_null($user)){
                return $user; // Avoid conflicts with other handlers.
            }
            $user = false; // Returning a non-null value will effectively short-circuit the user authentication process.
            if(username_exists($username_or_email)){
                $user = get_user_by('login', $username_or_email);
            } elseif(is_email($username_or_email) && email_exists($username_or_email)){
                $user = get_user_by('email', $username_or_email);
            }
            return $user;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: Use this function at your own risk!
         *
    	 * Authenticates and logs a user in with ‘remember’ capability, bypassing the Wordfence's CAPTCHA.
    	 *
         * @return WP_User|WP_Error
         */
        public static function signon($username_or_email = '', $password = '', $remember = false){
            if(is_user_logged_in()){
                $error_msg = __('You are logged in already. No need to register again!');
                $error_msg = self::first_p($error_msg);
                return self::error($error_msg);
            }
            $disable_captcha = !has_filter('wordfence_ls_require_captcha', '__return_false');
            if($disable_captcha){
                add_filter('wordfence_ls_require_captcha', '__return_false'); // Don't filter twice.
            }
            $user = wp_signon([
                'remember' => $remember,
                'user_login' => $username_or_email,
                'user_password' => $password,
            ]);
            if($disable_captcha){
                remove_filter('wordfence_ls_require_captcha', '__return_false');
            }
            if(is_wp_error($user)){
                return $user;
            }
            return wp_set_current_user($user->ID);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: Use this function at your own risk!
         *
    	 * Authenticates and logs a user in with ‘remember’ capability, without passing a password, bypassing the Wordfence's CAPTCHA.
    	 *
         * @return WP_Error|WP_User
         */
        public static function signon_without_password($username_or_email = '', $remember = false){
            if(is_user_logged_in()){
                $error_msg = __('You are logged in already. No need to register again!');
                $error_msg = self::first_p($error_msg);
                return self::error($error_msg);
            }
            add_filter('authenticate', [__CLASS__, '_maybe_authenticate_without_password'], 10, 3);
            $disable_captcha = !has_filter('wordfence_ls_require_captcha', '__return_false');
            if($disable_captcha){
                add_filter('wordfence_ls_require_captcha', '__return_false'); // Don't filter twice.
            }
            $user = wp_signon([
                'remember' => $remember,
                'user_login' => $username_or_email,
                'user_password' => '',
            ]);
            if($disable_captcha){
                remove_filter('wordfence_ls_require_captcha', '__return_false');
            }
            remove_filter('authenticate', [__CLASS__, '_maybe_authenticate_without_password']);
            if(is_wp_error($user)){
                return $user;
            }
            return wp_set_current_user($user->ID);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Beaver Builder
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * This function MUST be called inside the 'fl_builder_font_families_system' or 'fl_theme_system_fonts' filter hooks.
    	 *
         * @return array
         */
        public static function _bb_add_system_fonts($fonts = []){
            if(!doing_filter('fl_builder_font_families_system') && !doing_filter('fl_theme_system_fonts')){
                return $fonts; // Too early or too late.
            }
            $system_fonts = self::cache_all('bb_system_fonts');
            foreach($system_fonts as $key => $value){
                $fonts[$key] = $value;
            }
            return $fonts;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * @return array
    	 */
        public static function _bb_maybe_sort_fl_builder_photo_sizes_select($sizes){
            if(!self::cache_exists('bb_sort_photo_sizes_select')){
        		return $sizes;
        	}
    		uasort($sizes, [__CLASS__, '_bb_width_height_ascending_sort']);
    		return $sizes;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return int
         */
        public static function _bb_width_height_ascending_sort($a = 0, $b = 0){
            if($a['width'] === $b['width']){
    			if($a['height'] === $b['height']){
    				return 0;
    			}
    			if($a['height'] < $b['height']){
    				return -1;
    			}
    			return 1;
    		}
    		if($a['width'] < $b['width']){
    			return -1;
    		}
    		return 1;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function bb_add_system_font($key = '', $weights = [], $fallback = ''){
            if(!$fallback){
                $fallback = 'Arial, Verdana, sans-serif';
            }
            $value = [
                'fallback' => $fallback,
                'weights' => self::bb_sanitize_font_weights($weights),
            ];
            self::add_filter_once('fl_builder_font_families_system', [__CLASS__, '_bb_add_system_fonts']);
            self::add_filter_once('fl_theme_system_fonts', [__CLASS__, '_bb_add_system_fonts']);
            self::cache_set($key, $value, 'bb_system_fonts');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array
    	 */
        public static function bb_get_font_weights(){
            return [
                '100' => __('Thin', 'fl-automator'),
    			'100italic' => __('Thin Italic', 'fl-automator'),
    			'200' => __('Extra-Light', 'fl-automator'),
    			'200italic' => __('Extra-Light Italic', 'fl-automator'),
    			'300' => __('Light', 'fl-automator'),
    			'300italic' => __('Light Italic', 'fl-automator'),
    			'400' => __('Normal', 'fl-automator'),
    			'500' => __('Medium', 'fl-automator'),
    			'500italic' => __('Medium Italic', 'fl-automator'),
    			'600' => __('Semi-Bold', 'fl-automator'),
    			'600italic' => __('Semi-Bold Italic', 'fl-automator'),
    			'700' => __('Bold', 'fl-automator'),
    			'700italic' => __('Bold Italic', 'fl-automator'),
    			'800' => __('Extra-Bold', 'fl-automator'),
    			'800italic' => __('Extra-Bold Italic', 'fl-automator'),
    			'900' => __('Ultra-Bold', 'fl-automator'),
    			'900italic' => __('Ultra-Bold Italic', 'fl-automator'),
            ];
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function bb_is_b4_enabled(){
    		if(!self::bb_is_theme_enabled()){
    			return false;
    		}
        	return get_theme_mod('fl-framework', 'none') === 'bootstrap-4';
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function bb_is_fa5_enabled(){
    		if(!self::bb_is_theme_enabled()){
    			return false;
    		}
        	return get_theme_mod('fl-awesome', 'none') === 'fa5';
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function bb_is_theme_enabled(){
    		return (self::theme_is('Beaver Builder Theme') || self::theme_is_child_of('bb-theme'));
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array
    	 */
        public static function bb_sanitize_font_weights($weights = []){
            $font_weights = self::bb_get_font_weights();
            foreach($weights as $index => $weight){
                $weight = (string) $weight;
                if(!isset($font_weights[$weight])){
                    unset($weights[$index]);
                }
        	}
            return array_values($weights);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function bb_sort_photo_sizes_select(){
            self::add_filter_once('fl_builder_photo_sizes_select', [__CLASS__, '_bb_maybe_sort_fl_builder_photo_sizes_select'], 11);
            self::cache_set('bb_sort_photo_sizes_select', true);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Bootstrap 4
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function alert_danger($message = '', $is_dismissible = false){
    		return self::get_alert($message, 'danger', $is_dismissible);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function alert_dark($message = '', $is_dismissible = false){
    		return self::get_alert($message, 'dark', $is_dismissible);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function alert_info($message = '', $is_dismissible = false){
    		return self::get_alert($message, 'info', $is_dismissible);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function alert_light($message = '', $is_dismissible = false){
    		return self::get_alert($message, 'light', $is_dismissible);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function alert_primary($message = '', $is_dismissible = false){
    		return self::get_alert($message, 'primary', $is_dismissible);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function alert_secondary($message = '', $is_dismissible = false){
    		return self::get_alert($message, 'secondary', $is_dismissible);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function alert_success($message = '', $is_dismissible = false){
    		return self::get_alert($message, 'success', $is_dismissible);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function alert_warning($message = '', $is_dismissible = false){
    		return self::get_alert($message, 'warning', $is_dismissible);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_alert($message = '', $class = '', $is_dismissible = false){
    		if(!in_array($class, ['danger', 'dark', 'info', 'light', 'primary', 'secondary', 'success', 'warning'])){
    			$class = 'warning';
    		}
    		if($is_dismissible){
    			$class .= ' alert-dismissible fade show';
    		}
    		if($is_dismissible){
    			$message .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
    		}
    		return '<div class="alert alert-' . $class . '">' . $message . '</div>';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function has_btn_class($class = ''){
    	    $class = self::remove_whitespaces($class);
    	    preg_match_all('/btn-[A-Za-z][-A-Za-z0-9_:.]*/', $class, $matches);
    		$matches = array_filter($matches[0], function($match){
    			return !in_array($match, ['btn-block', 'btn-lg', 'btn-sm']);
    		});
    		return $matches ? true : false;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Branding
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_branding($context = ''){
    	    $branding = self::$branding;
			if($context === 'prefix'){
				return self::canonicalize($branding);
			}
			if($context === 'slug'){
				return self::slugify($branding);
			}
			return $branding;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function set_branding($branding = ''){
			$branding = trim($branding);
			if(preg_match('/^[A-Za-z0-9].*[A-Za-z0-9]$/', $branding) === 1){
				self::$branding = $branding;
				return true;
			}
			return false;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Cache
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return string
         */
        public static function _sanitize_cache_group($group = ''){
    		$group = sanitize_text_field($group);
    		if($group){
    			$group = strtolower($group);
    		} else {
    			$group = 'default';
    		}
    		$global = self::global_cache_key();
    		if(!isset($GLOBALS[$global][$group])){
    			$GLOBALS[$global][$group] = []; // Set.
    		}
    		if(!is_array($GLOBALS[$global][$group])){
    			$GLOBALS[$global][$group] = []; // Reset.
    		}
    		return $group;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return string
         */
        public static function _sanitize_cache_key($key = ''){
    		$key = sanitize_text_field($key);
    	    if(!$key){
    	        return '';
    	    }
    		$key = strtolower($key);
    		return $key;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Adds data to the cache if it doesn't already exist.
    	 *
    	 * @return bool
    	 */
        public static function cache_add($key = '', $data = null, $group = ''){
    	    $key = self::_sanitize_cache_key($key);
    	    if(!$key){
    	        return false;
    	    }
    		if(is_null($data)){
    	        return false;
    	    }
    		$global = self::global_cache_key();
    		$group = self::_sanitize_cache_group($group);
    		if(isset($GLOBALS[$global][$group][$key])){
    			return false;
    		}
    		$GLOBALS[$global][$group][$key] = $data;
    	    return true;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Retrieves the cache contents.
    	 *
    	 * @return array
    	 */
        public static function cache_all($group = ''){
    		$global = self::global_cache_key();
    		$group = self::_sanitize_cache_group($group);
    	    return $GLOBALS[$global][$group];
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Removes the contents of the cache key in the group.
    	 *
    	 * @return bool
    	 */
        public static function cache_delete($key = '', $group = ''){
    	    $key = self::_sanitize_cache_key($key);
    	    if(!$key){
    	        return false;
    	    }
    		$global = self::global_cache_key();
    		$group = self::_sanitize_cache_group($group);
    		if(!isset($GLOBALS[$global][$group][$key])){
    			return false;
    		}
    		unset($GLOBALS[$global][$group][$key]);
    	    return true;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Serves as a utility function to determine whether a key exists in the cache.
    	 *
    	 * @return bool
    	 */
        public static function cache_exists($key = '', $group = ''){
    	    $key = self::_sanitize_cache_key($key);
    	    if(!$key){
    	        return false;
    	    }
    		$global = self::global_cache_key();
    		$group = self::_sanitize_cache_group($group);
    	    return isset($GLOBALS[$global][$group][$key]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Retrieves the cache contents, if it exists.
    	 *
    	 * @return bool
    	 */
        public static function cache_get($key = '', $group = ''){
    		$default_value = null;
    	    $key = self::_sanitize_cache_key($key);
    	    if(!$key){
    	        return $default_value;
    	    }
    		$global = self::global_cache_key();
    		$group = self::_sanitize_cache_group($group);
    		if(!isset($GLOBALS[$global][$group][$key])){
    			return $default_value;
    		}
    	    return $GLOBALS[$global][$group][$key];
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Retrieves the cache contents as an array.
    	 *
    	 * @return bool
    	 */
        public static function cache_get_array($key = '', $group = ''){
    		$default_value = [];
    		$value = self::cache_get($key, $group);
    		if(is_null($value)){
    			return $default_value;
    		}
    		if(is_array($value)){
    			return $value;
    		}
    		if(is_object($value)){
    			return get_object_vars($value);
    		}
    		return $default_value;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Retrieves the cache contents as an integer.
    	 *
    	 * @return bool
    	 */
        public static function cache_get_int($key = '', $group = ''){
    		$default_value = 0;
    		$value = self::cache_get($key, $group);
    		if(is_null($value)){
    			return $default_value;
    		}
    		if(is_int($value)){
    			return $value;
    		}
    		if(is_numeric($value)){
    			return (int) $value;
    		}
    		return $default_value;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Retrieves the cache contents as an object.
    	 *
    	 * @return bool
    	 */
        public static function cache_get_object($key = '', $group = ''){
    		$default_value = new stdClass();
    		$value = self::cache_get($key, $group);
    		if(is_null($value)){
    			return $default_value;
    		}
    		if(is_object($value)){
    			return $value;
    		}
    		if(is_array($value)){
    			return (object) $value;
    		}
    		return $default_value;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Retrieves the cache contents as a string.
    	 *
    	 * @return bool
    	 */
        public static function cache_get_string($key = '', $group = ''){
    		$default_value = '';
    		$value = self::cache_get($key, $group);
    		if(is_null($value)){
    			return $default_value;
    		}
    		if(is_string($value)){
    			return $value;
    		}
    		if(is_scalar($value)){
    			return (string) $value;
    		}
    		return $default_value;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Replaces the contents in the cache, if contents already exist.
    	 *
    	 * @return bool
    	 */
        public static function cache_replace($key = '', $data = null, $group = ''){
    		$key = self::_sanitize_cache_key($key);
    	    if(!$key){
    	        return false;
    	    }
    		if(is_null($data)){
    	        return false;
    	    }
    		$global = self::global_cache_key();
    		$group = self::_sanitize_cache_group($group);
    		if(!isset($GLOBALS[$global][$group][$key])){
    			return false;
    		}
    		$old_value = $GLOBALS[$global][$group][$key];
    		if($data === $old_value){
    			return false;
    		}
    		$GLOBALS[$global][$group][$key] = $data;
    	    return true;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Sets the data contents into the cache.
    	 *
    	 * @return bool
    	 */
        public static function cache_set($key = '', $data = null, $group = ''){
    		$key = self::_sanitize_cache_key($key);
    	    if(!$key){
    	        return false;
    	    }
    		if(is_null($data)){
    	        return false;
    	    }
    		$global = self::global_cache_key();
    		$group = self::_sanitize_cache_group($group);
    		if(isset($GLOBALS[$global][$group][$key])){
    			$old_value = $GLOBALS[$global][$group][$key];
    		} else {
    			$old_value = null;
    		}
    		if($data === $old_value){
    			return false;
    		}
    		$GLOBALS[$global][$group][$key] = $data;
    	    return true;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Cloudflare
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_cf_country(){
            return isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : '';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_cf_ip(){
    		return isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : '';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function is_cf_enabled(){
    		return isset($_SERVER['CF-ray']);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Code
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
     	 * WARNING: This function’s access is marked private.
     	 *
    	 * @return string
    	 */
        public static function _replace_ie_filters($matches = []){
    		return empty($matches[1]) ? $matches[0] : 'filter: ~"' . $matches[1] . '";';
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function compress_css($css = ''){
    		return self::remove_whitespaces(preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css));
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function minify_css($css = ''){
            $css = self::normalize_ie_filters($css);
            $less = self::parse_less($css, [
                'compress' => true,
            ]);
            if(!is_wp_error($less)){
                $css = $less;
            }
            return self::compress_css($css);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function normalize_ie_filters($css = ''){
    		return preg_replace_callback('(filter\s?:\s?(.*);)', [__CLASS__, '_replace_ie_filters'], $css); // Fix issue with IE filters.
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Conditional tags
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_auto_draft($post = null){
    		return get_post_status($post) === 'auto-draft';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_debug_enabled(){
            return defined('WP_DEBUG') && WP_DEBUG;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_doing_heartbeat(){
    		return wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] === 'heartbeat';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Like is_email(), but for domains.
    	 *
    	 * @return string|false
    	 */
        public static function is_domain($domain = ''){
            if(strlen($domain) < 4){
                return false; // Test for the minimum length the domain can be.
            }
            if(strpos($domain, '@') !== false){
                return false; // Test for an @ character.
            }
            if(preg_match('/\.{2,}/', $domain)){
                return false; // Test for sequences of periods.
            }
            if(trim($domain, " \t\n\r\0\x0B.") !== $domain){
                return false; // Test for leading and trailing periods and whitespace.
            }
            $subs = explode('.', $domain); // Split the domain into subs.
            if(2 > count($subs)){
                return false; // Assume the domain will have at least two subs.
            }
            // Loop through each sub.
            foreach($subs as $sub){
                if(trim($sub, " \t\n\r\0\x0B-") !== $sub){
                    return false; // Test for leading and trailing hyphens and whitespace.
                }
                if(!preg_match('/^[a-z0-9-]+$/i', $sub)){
                    return false;
                } // Test for invalid characters.
            }
            return $domain; // Congratulations, your domain made it!
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_extension_allowed($extension = ''){
			$extension = ltrim($extension, '.');
            $is_extension_allowed = false;
    		foreach(wp_get_mime_types() as $exts => $mime){
                if(!preg_match('!^(' . $exts . ')$!i', $extension)){
                    continue;
                }
                $is_extension_allowed = true;
                break;
    		}
    		return $is_extension_allowed;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_false($data = ''){
    		return in_array((string) $data, ['0', 'false', 'off'], true);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_front(){
            global $wp_query;
            if(is_admin()){
                return false; // The current request is for an administrative interface page.
            }
            if(wp_doing_ajax()){
                return false; // The current request is a WordPress Ajax request.
            }
            if(wp_is_serving_rest_request()){
                return false; // WordPress is currently serving a REST API request.
            }
            if(wp_is_json_request()){
                return false; // The current request is a JSON request, or is expecting a JSON response.
            }
            if(wp_is_jsonp_request()){
                return false; // The current request is a JSONP request, or is expecting a JSONP response.
            }
            if(defined('XMLRPC_REQUEST') && XMLRPC_REQUEST){
                return false; // The current request is a WordPress XML-RPC request.
            }
            if(wp_is_xml_request() || (isset($wp_query) && (is_feed() || is_comment_feed() || is_trackback()))){
                return false; // The current request is an XML request, or is expecting an XML response.
            }
            return true;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_mysql_date($subject = ''){
    		return preg_match('/^\d{4}\-(0[1-9]|1[0-2])\-(0[1-9]|[12]\d|3[01]) ([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/', $subject);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * A valid name starts with a letter or underscore, followed by any number of letters, numbers, or underscores.
         *
         * @link https://www.php.net/manual/en/functions.user-defined.php
         *
    	 * @return bool
    	 */
        public static function is_name($name = ''){
    		return preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function is_path_in_dir($path = '', $dir = ''){
            $haystack = wp_normalize_path($path);
    		$needle = wp_normalize_path($dir);
    		return self::str_starts_with($haystack, $needle);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_path_in_mu_plugins_dir($path = ''){
    		return self::is_path_in_dir($path, WPMU_PLUGIN_DIR);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_path_in_plugins_dir($path = ''){
    		return self::is_path_in_dir($path, WP_PLUGIN_DIR);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_path_in_themes_dir($path = ''){
    		return self::is_path_in_dir($path, WP_CONTENT_DIR . '/themes');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_path_in_uploads_dir($path = ''){
            $upload_dir = wp_get_upload_dir();
    		if($upload_dir['error']){
    			$upload_dir = WP_CONTENT_DIR . '/uploads';
    		} else {
                $upload_dir = $upload_dir['basedir'];
            }
    		return self::is_path_in_dir($path, $upload_dir);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return int
         */
        public static function is_post_edit($post_type = ''){
            global $hook_suffix;
            if(!is_admin()){
                return 0;
            }
            if($hook_suffix !== 'post.php'){
                return 0;
            }
            if(!isset($_GET['action'], $_GET['post'])){
    			return 0;
    		}
            if($_GET['action'] !== 'edit'){
    			return 0;
    		}
            $post_id = (int) $_GET['post'];
            if(!$post_type){
                return $post_id;
            }
            if(get_post_type($post_id) !== $post_type){
    			return 0;
    		}
            return $post_id;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function is_post_list($post_type = ''){
            global $hook_suffix;
            if(!is_admin()){
                return false;
            }
            if($hook_suffix !== 'edit.php'){
                return false;
            }
            if(!$post_type){
                return true;
            }
            return $post_type === (isset($_GET['post_type']) ? $_GET['post_type'] : 'post');
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function is_post_new($post_type = ''){
            global $hook_suffix;
            if(!is_admin()){
                return false;
            }
            if($hook_suffix !== 'post-new.php'){
                return false;
            }
            if(!$post_type){
                return true;
            }
            return $post_type === (isset($_GET['post_type']) ? $_GET['post_type'] : 'post');
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_revision_or_auto_draft($post = null){
    		return (wp_is_post_revision($post) || self::is_auto_draft($post));
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_true($data = ''){
    		return in_array((string) $data, ['1', 'on', 'true'], true);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_valid_filename($filename = ''){
            $filename = wp_basename($filename);
            $filetype = wp_check_filetype($filename);
            return $filetype['ext'] ? true : false;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Content-Type
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function convert_exts_to_mimes($exts = []){
            if(!$exts){
    	        $exts = array_merge(wp_get_audio_extensions(), wp_get_video_extensions(), self::get_image_extensions());
    	    }
    	    $mimes = wp_get_mime_types();
    	    $ext_mimes = [];
    	    foreach($exts as $ext){
    	        foreach($mimes as $ext_preg => $mime_match){
    	            if(preg_match('#' . $ext . '#i', $ext_preg)){
    	                $ext_mimes[$ext] = $mime_match;
    	                break;
    	            }
    	        }
    	    }
    	    return $ext_mimes;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Alias for WP_REST_Request::get_content_type().
    	 *
    	 * Retrieves the Content-Type of the remote request or response.
    	 *
    	 * @return array
    	 */
        public static function get_content_type($value = []){
            if(self::is_content_type($value)){
                return $value;
            }
            if(self::is_remote_request($value) || self::is_remote_response($value)){
                $values = (array) wp_remote_retrieve_header($value, 'Content-Type');
        		if(!$values){
        			return [];
        		}
                $value = $values[0];
            }
    		if(!is_string($value)){
                return [];
            }
    		$parameters = '';
    		if(strpos($value, ';')){
    			list($value, $parameters) = explode(';', $value, 2);
    		}
    		$value = strtolower($value);
    		if(!str_contains($value, '/')){
    			return [];
    		}
    		list($type, $subtype) = explode('/', $value, 2); // Parse type and subtype out.
    		$data = compact('value', 'type', 'subtype', 'parameters');
    		return array_map('trim', $data);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array
    	 */
        public static function get_image_extensions(){
    	    return ['jpg', 'jpeg', 'jpe', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'ico', 'heic'];
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_content_type($content_type = []){
            $keys = ['parameters', 'subtype', 'type', 'value'];
    		return self::array_keys_exist($keys, $content_type);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function mime_content_type($filename = '', $mimes = null){
            $mime = wp_check_filetype($filename, $mimes);
            if($mime['type'] === false && function_exists('mime_content_type')){
                $mime['type'] = mime_content_type($filename);
            }
            return $mime['type'] === false ? '' : $mime['type'];
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Cookies
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function cookie_get($name = ''){
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : '';
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return array
         */
        public static function cookie_get_hash($name = ''){
            wp_parse_str(self::cookie_get($name), $values);
            return $values;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function cookie_remove($name = ''){
            if(!isset($_COOKIE[$name])){
                return;
            }
            setcookie($name, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function cookie_set($name = '', $value = '', $expires = 0){
            setcookie($name, $value, $expires, COOKIEPATH, COOKIE_DOMAIN, wp_is_home_url_using_https());
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function cookie_set_hash($name = '', $values_obj = [], $expires = 0){
            if(!is_array($values_obj)){
                if(!is_object($values_obj)){
                    return;
                }
                $values_obj = self::object_to_array($values_obj);
                if(is_wp_error($values_obj)){
                    return;
                }
            }
            self::cookie_set($name, build_query($values_obj), $expires);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // DateTime
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see current_time()
         *
    	 * @return string
    	 */
        public static function current_time($type = 'U', $offset_or_tz = ''){
    		if($type === 'timestamp'){
    			$type = 'U';
    		} elseif($type === 'mysql'){
    			$type = 'Y-m-d H:i:s';
    		}
    		$timezone = $offset_or_tz ? self::timezone($offset_or_tz) : wp_timezone();
    		$datetime = new \DateTime('now', $timezone);
    		return $datetime->format($type);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function date_convert($string = '', $fromtz = '', $totz = '', $format = 'Y-m-d H:i:s'){
    		$datetime = date_create($string, self::timezone($fromtz));
    		if($datetime === false){
    			return gmdate($format, 0);
    		}
            $timezone = self::timezone($totz);
    		return $datetime->setTimezone($timezone)->format($format);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see populate_options()
         * @see wp-admin/options.php
         *
    	 * @return array
    	 */
        public static function offset_or_tz($offset_or_tz = ''){
            if(!$offset_or_tz){
                $offset_or_tz = _x('0', 'default GMT offset or timezone string'); // Default GMT offset or timezone string. Must be either a valid offset (-12 to 14) or a valid timezone string.
            }
    		if(preg_match('/^(-1[0-2]|-?[0-9]|1[0-4])$/', $offset_or_tz)){
    			return [
    				'gmt_offset' => $offset_or_tz,
    				'timezone_string' => '',
    			];
    		}
    		if(preg_match('/^UTC[+-]/', $offset_or_tz)){
    			return [
    				'gmt_offset' => preg_replace('/UTC\+?/', '', $offset_or_tz),
    				'timezone_string' => '',
    			]; // Map UTC+- timezones to gmt_offsets and set timezone_string to empty.
    		}
    		if(in_array($offset_or_tz, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC), true)){
    			return [
    				'gmt_offset' => 0,
    				'timezone_string' => $offset_or_tz,
    			];
    		}
    		return [
    			'gmt_offset' => 0,
    			'timezone_string' => 'UTC',
    		];
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see wp_timezone()
         *
    	 * @return DateTimeZone
    	 */
        public static function timezone($offset_or_tz = ''){
            $timezone = self::timezone_string($offset_or_tz);
    		return new \DateTimeZone($timezone);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see wp_timezone_string()
         *
    	 * @return string
    	 */
        public static function timezone_string($offset_or_tz = ''){
    		$offset_or_tz = self::offset_or_tz($offset_or_tz);
    		$timezone_string = $offset_or_tz['timezone_string'];
    		if($timezone_string){
    			return $timezone_string;
    		}
    		$offset = (float) $offset_or_tz['gmt_offset'];
    		$hours = (int) $offset;
    		$minutes = $offset - $hours;
    		$sign = $offset < 0 ? '-' : '+';
    		$abs_hour = abs($hours);
    		$abs_mins = abs($minutes * 60);
    		return sprintf('%s%02d:%02d', $sign, $abs_hour, $abs_mins);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Dependencies
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return string|WP_Error
		 */
		public static function admin_enqueue($handle = '', $src = '', $deps = [], $ver = false, $args_media = null, $l10n = []){
			return self::context_enqueue('admin', $handle, $src, $deps, $ver, $args_media, $l10n);
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return string|WP_Error
		 */
		public static function enqueue($handle = '', $src = '', $deps = [], $ver = false, $args_media = null, $l10n = []){
			return self::context_enqueue('wp', $handle, $src, $deps, $ver, $args_media, $l10n);
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return void
		 */
		public static function enqueue_dependency($handle = '', $src = '', $deps = [], $ver = false, $args_media = null, $l10n = []){
			if(self::scripts_maybe_doing_it_wrong()){
				return; // Too early.
			}
			if(!wp_http_validate_url($src)){
				if(!is_file($src)){
					return; // Dependency src must be a valid URL or file reference.
				}
				if(!$ver){
					$ver = filemtime($src);
				}
				$src = self::path_to_url($src);
			}
			$filename = self::basename($src);
			$mimes = [
				'css' => 'text/css',
				'js' => 'application/javascript',
			];
			$filetype = wp_check_filetype($filename, $mimes);
			if(!$filetype['ext']){
				return;
			}
			if(!is_array($deps)){
				$deps = []; // Perhaps it was called directly?
			}
			switch($filetype['ext']){
				case 'css':
					self::enqueue_style($handle, $src, $deps, $ver, $args_media);
					break;
				case 'js':
					self::enqueue_script($handle, $src, $deps, $ver, $args_media, $l10n);
					break;
			}
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return bool
		 */
		public static function localize_script($handle = '', $l10n = []){
			return wp_localize_script($handle, self::canonicalize($handle) . '_object', $l10n);
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return string|WP_Error
		 */
		public static function login_enqueue($handle = '', $src = '', $deps = [], $ver = false, $args_media = null, $l10n = []){
			return self::context_enqueue('login', $handle, $src, $deps, $ver, $args_media, $l10n);
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * This function MUST be called inside the 'admin_enqueue_scripts' action hook.
		 *
		 * @return void
		 */
		public static function maybe_enqueue_admin_assets(){
			if(!doing_action('admin_enqueue_scripts')){
				return; // Too early or too late.
			}
			$key = 'admin_dependencies';
			if(!self::cache_exists($key)){
				return;
			}
			$dependencies = self::cache_get($key);
			foreach($dependencies as $dependency){
				self::enqueue_dependency($dependency['handle'], $dependency['src'], $dependency['deps'], $dependency['ver'], $dependency['args_media'], $dependency['l10n']);
			}
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * This function MUST be called inside the 'wp_enqueue_scripts' action hook.
		 *
		 * @return void
		 */
		public static function maybe_enqueue_wp_assets(){
			if(!doing_action('wp_enqueue_scripts')){
				return; // Too early or too late.
			}
			$key = 'wp_dependencies';
			if(!self::cache_exists($key)){
				return;
			}
			$dependencies = self::cache_get($key);
			foreach($dependencies as $dependency){
				self::enqueue_dependency($dependency['handle'], $dependency['src'], $dependency['deps'], $dependency['ver'], $dependency['args_media'], $dependency['l10n']);
			}
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * This function MUST be called inside the 'wp_enqueue_scripts' action hook.
		 *
		 * @return void
		 */
		public static function maybe_enqueue_login_assets(){
			if(!doing_action('login_enqueue_scripts')){
				return; // Too early or too late.
			}
			$key = 'login_dependencies';
			if(!self::cache_exists($key)){
				return;
			}
			$dependencies = self::cache_get($key);
			foreach($dependencies as $dependency){
				self::enqueue_dependency($dependency['handle'], $dependency['src'], $dependency['deps'], $dependency['ver'], $dependency['args_media'], $dependency['l10n']);
			}
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return string|WP_Error
		 */
		public static function omni_enqueue($handle = '', $src = '', $deps = [], $ver = false, $args_media = true, $l10n = []){
			if(!doing_action('admin_enqueue_scripts') && !doing_action('login_enqueue_scripts') && !doing_action('wp_enqueue_scripts')){
				if(did_action('admin_enqueue_scripts') || did_action('login_enqueue_scripts') || did_action('wp_enqueue_scripts')){
					return self::doing_it_wrong(__FUNCTION__); // Too late.
				}
			}
			self::context_enqueue('admin', $handle, $src, $deps, $ver, $args_media, $l10n);
			self::context_enqueue('login', $handle, $src, $deps, $ver, $args_media, $l10n);
			self::context_enqueue('wp', $handle, $src, $deps, $ver, $args_media, $l10n);
			return $handle;
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return void
		 */
		public static function scripts_maybe_doing_it_wrong(){
			return (!did_action('admin_enqueue_scripts') && !did_action('init') && !did_action('login_enqueue_scripts') && !did_action('wp_enqueue_scripts'));
		}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Error handling
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return string|WP_Error
		 */
		public static function caller_file($index = 0){
			$index = self::absint($index);
            $limit = $index + 1;
			$options = 2; // options: 0: provide args and ignore object, 1: provide args and object, 2: ignore args and object, 3: ignore args and provide object.
            $debug_backtrace = debug_backtrace($options, $limit);
            if(!isset($debug_backtrace[$index])){
                return self::error();
            }
			if(!isset($debug_backtrace[$index]['file'])){
                return self::error();
            }
			return $debug_backtrace[$index]['file'];
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array|WP_error
    	 */
        public static function class_context($class = ''){
            if(!$class){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'class');
                return self::error($error_msg);
            }
            if(!class_exists($class)){
                return self::invalid_params('class');
            }
            $reflector = new \ReflectionClass($class);
            return self::reflector_context($reflector);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_error
    	 */
        public static function doing_it_wrong($function = '', $arguments = []){
            $error_msg = __('Function %1$s was called <strong>incorrectly</strong>. %2$s %3$s');
            $error_msg = sprintf($error_msg, $function, '', '');
    		if($function){
    			$error_msg = preg_replace('/\s+/', ' ', $error_msg);
    		}
    		$error_msg = trim($error_msg);
    		return self::error($error_msg, $arguments);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function exit_with_error($message = '', $title = '', $args = []){
            if(is_wp_error($message)){
    			$message = $message->get_error_message();
    			if($title && !$args){
    				$args = $title;
    				$title = '';
    			}
    		}
    		if(!$message){
    			$message = __('Error');
    		}
            if(is_int($args)){
                $args = [
                    'response' => $args,
                ];
            }
            if(is_int($title)){
                if(!isset($args['response'])){
                    $args['response'] = $title;
                }
                $title = get_status_header_desc($title);
            }
    		if(!$title){
    			$title = __('An error occurred.'); // Something went wrong.
    		}
            $html = '<h1>' . $title . '</h1>';
            $html .= '<p>' . $message . '</p>';
            $referer = wp_get_referer();
            if($referer){
                $html .= '<p>' . sprintf('<a href="%s">%s</a>', esc_url($referer), __('Go back')) . '</p>';
            }
            wp_die($html, $title, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array|WP_Error
    	 */
        public static function function_context($function = ''){
            if(empty($function)){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'function');
                return self::error($error_msg);
            }
            if(!function_exists($function)){
                return self::invalid_params('function');
            }
            $reflector = new \ReflectionFunction($function);
            return self::reflector_context($reflector);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_Error
    	 */
        public static function invalid_params($params = []){
            $error_msg = __('Invalid parameter(s): %s');
    		if($params){
                $error_msg = sprintf($error_msg, self::implode_and((array) $params));
    			return self::error($error_msg);
    		}
            $error_msg = sprintf($error_msg, '');
            $error_msg = trim($error_msg);
            $error_msg = str_replace(':', '.', $error_msg);
    		return self::error($error_msg);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_Error
    	 */
        public static function missing_file($file = ''){
            if($file){
                $error_msg = __('File &#8220;%s&#8221; does not exist?');
                $error_msg = sprintf($error_msg, wp_basename($file));
    			return self::error($error_msg, $file);
    		}
            $error_msg = __('File does not exist?');
    		return self::error($error_msg);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_Error
    	 */
        public static function missing_params($params = []){
            $error_msg = __('Missing parameter(s): %s');
    		if($params){
                $error_msg = sprintf($error_msg, self::implode_and((array) $params));
    			return self::error($error_msg);
    		}
            $error_msg = sprintf($error_msg, '');
            $error_msg = trim($error_msg);
            $error_msg = str_replace(':', '.', $error_msg);
    		return self::error($error_msg);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array|WP_Error
    	 */
        public static function reflector_context($reflector = null){
            if(!$reflector instanceof \Reflector){
                $error_msg = __('Invalid object type.');
                return self::error($error_msg);
            }
            return [
                'file' => $reflector->getFileName(),
                'name' => $reflector->getName(),
                'namespace_name' => $reflector->getNamespaceName(),
                'reflector' => $reflector,
                'short_name' => $reflector->getShortName(),
            ];
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return WP_Error
         */
        public static function unexpected_error($data = ''){
            $error_msg = self::unexpected_error_string();
    		return self::error($error_msg, $data);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return WP_Error
         */
        public static function unexpected_error_string(){
    		$error_msg = __('An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="%s">support forums</a>.');
    		$error_msg = sprintf($error_msg, '#');
    		return self::first_p($error_msg);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Files
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function basename($path = '', $suffix = ''){
            $path = self::remove_query($path);
    		return wp_basename($path, $suffix);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function check_dir($dir = ''){
            if(!$dir){
                return self::error();
            }
            if(!@is_dir($dir)){
                $error_msg = __('Destination directory for file streaming does not exist or is not writable.');
                $error = self::error($error_msg);
                return $error;
            }
            if(!wp_is_writable($dir)){
                $error_msg = __('The %s directory exists but is not writable. This directory is used for plugin and theme updates. Please make sure the server has write permissions to this directory.');
                $error_msg = sprintf($error_msg, self::relative_path($dir));
                $error_msg = self::first_p($error_msg);
                $error = self::error($error_msg, $target);
                return $error;
            }
    		return $dir;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @see WP_REST_Attachments_Controller::check_upload_size()
    	 *
    	 * @return bool|WP_Error
    	 */
        public static function check_upload_size($file_size = 0){
    		if(!is_multisite()){
    			return true;
    		}
    		if(get_site_option('upload_space_check_disabled')){
    			return true;
    		}
    		$space_left = get_upload_space_available();
    		if($space_left < $file_size){
                $error_msg = __('Not enough space to upload. %s KB needed.');
                $error_msg = sprintf($error_msg, number_format(($file_size - $space_left) / KB_IN_BYTES));
    			return self::error($error_msg);
    		}
    		if($file_size > (KB_IN_BYTES * get_site_option('fileupload_maxk', 1500))){
                $error_msg = __('This file is too big. Files must be less than %s KB in size.');
                $error_msg = sprintf($error_msg, get_site_option('fileupload_maxk', 1500));
    			return self::error($error_msg);
    		}
    		if(!function_exists('upload_is_user_over_quota')){
    			require_once ABSPATH . 'wp-admin/includes/ms.php'; // Include multisite admin functions to get access to upload_is_user_over_quota().
    		}
    		if(upload_is_user_over_quota(false)){
                $error_msg = __('You have used your space quota. Please delete files before uploading.');
    			return self::error($error_msg);
    		}
    		return true;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_error
    	 */
        public static function fs_copy($source = '', $destination = '', $overwrite = false, $mode = false){
    		$fs = self::get_filesystem();
            if(is_wp_error($fs)){
                return $fs;
            }
    		if(!$fs->is_file($source)){
                return self::missing_file($source);
            }
            if($fs->is_dir($destination)){
                $filename = wp_basename($source);
                if(!$overwrite){
                    $filename = wp_unique_filename($destination, $filename);
                }
                $destination = path_join($destination, $filename);
            }
    		if(!$fs->copy($source, $destination, $overwrite, $mode)){
                $error_msg = __('Could not copy file.');
                $error = self::error($error_msg, $destination);
                return $error;
            }
            return $destination;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool|WP_error
    	 */
        public static function fs_delete($file = ''){
            $fs = self::get_filesystem();
            if(is_wp_error($fs)){
                return $fs;
            }
            if(!$fs->is_file($file)){
                return self::missing_file($file);
            }
            return $fs->delete($file, false, 'f');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @see WP_Filesystem_Direct::dirlist()
    	 *
    	 * @return array|WP_error
    	 */
        public static function fs_dirlist($path = '', $include_hidden = true, $recursive = false){
            $fs = self::get_filesystem();
            if(is_wp_error($fs)){
                return $fs;
            }
            $ret = $fs->dirlist($path, $include_hidden, $recursive);
            if($ret === false){
                return self::error(__('Directory listing failed.'), $path);
            }
            return $ret;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array|WP_Error
    	 */
        public static function fs_list_files($path = '', $include_hidden = true){
            $ret = self::fs_dirlist($path, $include_hidden);
    		if(is_wp_error($ret)){
    			return $ret;
    		}
            return array_filter($ret, function($ret){
                return $ret['type'] === 'f';
            });
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function fs_move($source = '', $destination = '', $overwrite = false){
            $fs = self::get_filesystem();
            if(is_wp_error($fs)){
                return $fs;
            }
    		if(!$fs->is_file($source)){
                return self::missing_file($source);
            }
            if($fs->is_dir($destination)){
                $filename = wp_basename($source);
                if(!$overwrite){
                    $filename = wp_unique_filename($destination, $filename);
                }
                $destination = path_join($destination, $filename);
            }
            if(!$fs->move($source, $destination, $overwrite)){
                $error_msg = __('Could not move the old version to the %s directory.');
                $error_msg = sprintf($error_msg, self::relative_path(dirname($destination)));
                $error = self::error($error_msg, $destination);
                return $error;
            }
            return $destination;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool|WP_error
    	 */
        public static function fs_rmdir($directory = ''){
            $fs = self::get_filesystem();
            if(is_wp_error($fs)){
                return $fs;
            }
            if(!$fs->is_dir($directory)){
                return self::invalid_params(['directory']);
            }
            return $fs->delete($file, true, 'd');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see download_url()
         *
    	 * @return string
    	 */
        public static function get_file_sample($tmpfname = ''){
    		if(!is_file($tmpfname)){
    			return '';
    		}
    		$tmpf = fopen($tmpfname, 'rb'); // Retrieve a sample of the response body for debugging purposes.
    		if(!$tmpf){
    			return '';
    		}
    		$response_size = apply_filters('download_url_error_max_body_size', KB_IN_BYTES); // Filters the maximum error response body size. Default 1 KB.
    		$sample = fread($tmpf, $response_size);
    		fclose($tmpf);
    		return $sample;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_Filesystem_Base|WP_Error
    	 */
        public static function get_filesystem(){
            global $wp_filesystem;
    		if($wp_filesystem instanceof \WP_Filesystem_Base && (is_null($wp_filesystem->errors) || (is_wp_error($wp_filesystem->errors) && !$wp_filesystem->errors->has_errors()))){
                return $wp_filesystem;
            }
            if(!function_exists('request_filesystem_credentials')){
    			require_once ABSPATH . 'wp-admin/includes/file.php';
    		}
            ob_start();
            $credentials = request_filesystem_credentials(self_admin_url()); // Check filesystem credentials.
            ob_end_clean();
    		if(!$credentials){
    			return self::get_filesystem_error();
    		}
            if(!WP_Filesystem($credentials)){
                return self::get_filesystem_error();
            }
            return $wp_filesystem;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_Error
    	 */
        public static function get_filesystem_error(){
            global $wp_filesystem;
            if($wp_filesystem instanceof \WP_Filesystem_Base && is_wp_error($wp_filesystem->errors) && $wp_filesystem->errors->has_errors()){
                return $wp_filesystem->errors;
            }
            $error_msg = __('Could not access filesystem.');
            return self::error($error_msg);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return int
    	 */
        public static function get_memory_size(){
    		$command = 'free -b';
    		$output = self::exec($command);
    		if(is_wp_error($output)){
    			$current_limit = ini_get('memory_limit');
    			$current_limit_int = wp_convert_hr_to_bytes($current_limit);
    			return $current_limit_int;
    		}
    		$output = sanitize_text_field($output[1]);
    		$output = explode(' ', $output);
    		return (int) $output[1];
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @see wp_mkdir_p().
    	 *
    	 * @return string|WP_Error
    	 */
        public static function mkdir_p($target = ''){
            if(!$target){
                return '';
            }
            $group = 'mkdir_p';
    		$key = self::md5($target);
    		if(self::cache_exists($key, $group)){
    			return self::cache_get($key, $group);
    		}
    		if(!wp_mkdir_p($target)){
                $error_msg = __('Could not create directory.');
                $error = self::error($error_msg, $target);
                self::cache_set($key, $error, $group);
    			return $error;
    		}
            self::cache_set($key, $target, $group);
    		return $target;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see path_join()
         *
    	 * @return string
    	 */
        public static function path_join($base = '', $path = ''){
            if(path_is_absolute($path)){
        		return $path;
        	}
            $base = untrailingslashit($base);
            $path = self::unslashit($path);
    		if($base && !$path){
    			return $base;
    		} elseif($path && !$base){
    			return $path;
    		} elseif(!$base && !$path){
    			return '';
    		} else {
    			return $base . '/' . $path; // Without trailing slash.
    		}
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function path_to_url($path = ''){
            $search = wp_normalize_path(ABSPATH);
            $replace = site_url('/');
            $subject = wp_normalize_path($path);
    		return str_replace($search, $replace, $subject);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function read_file_chunk($handle = null, $chunk_size = 0, $chunk_lenght = 0){
    		$giant_chunk = '';
    		if(is_resource($handle) && $chunk_size){
    			$byte_count = 0;
    			if(!$chunk_lenght){
    				$chunk_lenght = 8 * KB_IN_BYTES;
    			}
    			while(!feof($handle)){
    				$chunk = fread($handle, $chunk_lenght);
    				$byte_count += strlen($chunk);
    				$giant_chunk .= $chunk;
    				if($byte_count >= $chunk_size){
    					return $giant_chunk;
    				}
    			}
    		}
    		return $giant_chunk;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function relative_path($path = ''){
            $abspath = wp_normalize_path(ABSPATH);
            $path = wp_normalize_path($path);
            if(!self::str_starts_with($path, $abspath)){
                return wp_basename($path);
            }
    		return str_replace(ABSPATH, '', $path);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function remove_query($path = ''){
            return preg_replace('/\?.*/', '', $path); // Fix file filename for query strings.
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see _wp_handle_upload()
         *
    	 * @return bool|WP_Error
    	 */
        public static function test_file_error($error = 0){
    		$upload_error_strings = [
    			false,
    			sprintf(__('The uploaded file exceeds the %1$s directive in %2$s.'), 'upload_max_filesize', 'php.ini'),
    			sprintf(__('The uploaded file exceeds the %s directive that was specified in the HTML form.'), 'MAX_FILE_SIZE'),
    			__('The uploaded file was only partially uploaded.'),
    			__('No file was uploaded.'),
    			'',
    			__('Missing a temporary folder.'),
    			__('Failed to write file to disk.'),
    			__('File upload stopped by extension.'),
    		]; // Courtesy of php.net, the strings that describe the error indicated in $_FILES[{form field}]['error'].
            $error_msg = isset($upload_error_strings[$error]) ? $upload_error_strings[$error] : self::unexpected_error_string();
    		return $error > 0 ? self::error($error_msg) : true; // A successful upload will pass this test.
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see _wp_handle_upload()
         *
    	 * @return bool|WP_Error
    	 */
        public static function test_file_size($file_size = 0){ // A non-empty file will pass this test.
            $error_msg = is_multisite() ? __('File is empty. Please upload something more substantial.') : sprintf(__('File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your %1$s file or by %2$s being defined as smaller than %3$s in %1$s.'), 'php.ini', 'post_max_size', 'upload_max_filesize');
    		return $file_size === 0 ? self::error($error_msg) : true;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see _wp_handle_upload()
         *
    	 * @return string|WP_Error
    	 */
        public static function test_file_type($tmp_name = '', $name = '', $mimes = null){
    		$wp_filetype = wp_check_filetype_and_ext($tmp_name, $name, $mimes);
    		$ext = empty($wp_filetype['ext']) ? '' : $wp_filetype['ext'];
    		$type = empty($wp_filetype['type']) ? '' : $wp_filetype['type'];
    		$proper_filename = empty($wp_filetype['proper_filename']) ? '' : $wp_filetype['proper_filename']; // Check to see if wp_check_filetype_and_ext() determined the filename was incorrect.
    		if($proper_filename){
    			$name = $proper_filename;
    		}
    		if((!$type || !$ext) && !current_user_can('unfiltered_upload')){
                $error_msg = __('Sorry, you are not allowed to upload this file type.');
    			return self::error($error_msg);
    		}
    		return $name; // A correct MIME type will pass this test.
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see _wp_handle_upload()
         *
    	 * @return bool|WP_Error
    	 */
        public static function test_uploaded_file($tmp_name = ''){
            $error_msg = __('Specified file failed upload test.');
    		return is_uploaded_file($tmp_name) ? true : self::error($error_msg); // A properly uploaded file will pass this test.
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function unique_filename($dir = '', $filename = ''){
            $filename = wp_basename($filename);
            $path = wp_unique_filename($dir, $filename);
            return path_join($dir, $path);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function url_to_path($url = ''){
    	    $site_url = site_url('/');
    		return self::str_starts_with($url, $site_url) ? str_replace($site_url, wp_normalize_path(ABSPATH), $url) : '';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Font Awesome
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function enqueue_font_awesome($deps = []){
    		if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = self::use_font_awesome();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $src = self::path_to_url($dir) . '/css/all.min.css';
            $ver = self::fa_ver();
            self::enqueue_dependency('font-awesome', $src, $deps, $ver);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function fa_file_type($post = null, $ver = 7){
            if('attachment' !== get_post_type($post)){
                return 'file';
            }
            if(wp_attachment_is('audio', $post)){
                return 'file-audio';
            }
            if(wp_attachment_is('image', $post)){
                return 'file-image';
            }
            if(wp_attachment_is('video', $post)){
                return 'file-video';
            }
            $type = get_post_mime_type($post);
            switch($type){
                case 'application/vnd.ms-excel':
                case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
                    $result = 'file-excel';
                    break;
                case 'application/pdf':
                    $result = 'file-pdf';
                    break;
                case 'application/vnd.ms-powerpoint':
                case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
                    $result = 'file-powerpoint';
                    break;
                case 'application/msword':
                case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
                    $result = 'file-word';
                    break;
    			case 'application/zip':
    				$result = $ver >= 6 ? 'file-zip' : 'file-archive';
    				break;
    			case 'application/x-rar-compressed':
                case 'application/x-7z-compressed':
                case 'application/x-tar':
                    $result = $ver >= 6 ? 'file-zipper' : 'file-archive';
                    break;
                default:
                    $result = 'file';
            }
            return $result;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function fa_ver($set = ''){
            static $ver = '7.2.0';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function use_font_awesome(){
            $ver = self::fa_ver();
            $url = 'https://github.com/FortAwesome/Font-Awesome/archive/refs/tags/' . $ver . '.zip';
            return self::use([
                'expected_dir' => 'Font-Awesome-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // GitHub
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'http_request_args' filter hook.
    	 *
    	 * @return array
    	 */
        public static function _maybe_add_github_api_token($parsed_args, $url){
    		if(!doing_filter('http_request_args')){
    	        return $parsed_args; // Too early or too late.
    	    }
            $key = 'github_api_token';
            if(!self::cache_exists($key)){
                return $parsed_args;
            }
            if(!self::str_starts_with($url, 'https://api.github.com/')){
                return $parsed_args;
            }
            if(!isset($parsed_args['headers'])){
                $parsed_args['headers'] = [];
            }
            $parsed_args['headers']['Authorization'] = 'token ' . self::cache_get($key);
    		return $parsed_args;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function github_api_token($token = ''){
            if(!$token){
                return;
            }
            $key = 'github_api_token';
            self::add_filter_once('http_request_args', [__CLASS__, '_maybe_add_github_api_token'], 10, 2);
            self::cache_set($key, $token);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Google
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function google_api_php_client_ver($set = ''){
            static $ver = '';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function hide_recaptcha_badge(){
            if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            self::add_inline_style('.grecaptcha-badge{visibility:hidden!important;}');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool|string
    	 */
        public static function is_google_workspace($domain = ''){
            if(!self::is_domain($domain)){
                if(!is_email($domain)){
        			return false;
        		}
                list($local, $domain) = explode('@', $domain, 2);
            }
    		if(strtolower($domain) === 'gmail.com'){
    			return 'gmail.com';
    		}
    		if(!getmxrr($domain, $mxhosts)){
    			return false;
    		}
    		if(!in_array('aspmx.l.google.com', $mxhosts)){
    			return false;
    		}
    		return strtolower($domain);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_recaptcha_branding(){
    		return 'This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" target="_blank">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank">Terms of Service</a> apply.';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function use_google_api_php_client(){
            if($ver){
                $php = '';
                $url = 'https://github.com/googleapis/google-api-php-client/archive/refs/tags/v' . $ver . '.zip';
    		} else {
                if(is_php_version_compatible('8.3')){
                    $php = '8.3';
                    $ver = '2.19.0';
                    $url = 'https://github.com/googleapis/google-api-php-client/releases/download/v' . $ver . '/google-api-php-client--PHP' . $php . '.zip';
                } elseif(is_php_version_compatible('8.1')){
                    $php = '8.1';
                    $ver = '2.19.0';
                    $url = 'https://github.com/googleapis/google-api-php-client/releases/download/v' . $ver . '/google-api-php-client--PHP' . $php . '.zip';
                } elseif(is_php_version_compatible('8.0')){
                    $php = '8.0';
                    $ver = '2.16.0'; // Avoid 2.17.* and 2.18.*.
                    $url = 'https://github.com/googleapis/google-api-php-client/releases/download/v' . $ver . '/google-api-php-client--PHP' . $php . '.zip';
                } elseif(is_php_version_compatible('7.4')){
                    $php = '7.4';
                    $ver = '2.16.0';
                    $url = 'https://github.com/googleapis/google-api-php-client/releases/download/v' . $ver . '/google-api-php-client--PHP' . $php . '.zip';
                } elseif(is_php_version_compatible('7.0')){
                    $php = '7.0';
                    $ver = '2.14.0';
                    $url = 'https://github.com/googleapis/google-api-php-client/releases/download/v' . $ver . '/google-api-php-client--PHP' . $php . '.zip';
                } else {
                    $php = '5.6';
                    $ver = '2.14.0';
                    $url = 'https://github.com/googleapis/google-api-php-client/releases/download/v' . $ver . '/google-api-php-client--PHP' . $php . '.zip';
                }
            }
            return self::use([
                'autoload' => 'vendor/autoload.php',
                'requires_wp' => $php,
                'validation_class' => 'Google\Client',
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Hooks
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function _build_unique_id($callback = null){
            return _wp_filter_build_unique_id('', $callback, 0);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see add_action().
         *
         * @return string
         */
        public static function add_action($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            return self::on($hook_name, $callback, $priority, $accepted_args);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function add_action_once($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            return self::one($hook_name, $callback, $priority, $accepted_args);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see add_filter().
         *
         * @return string
         */
        public static function add_filter($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            return self::on($hook_name, $callback, $priority, $accepted_args);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function add_filter_once($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            return self::one($hook_name, $callback, $priority, $accepted_args);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function callback_md5($callback = null){
            $unique_id = self::_build_unique_id($callback);
            $md5 = md5($unique_id);
            if(!self::is_closure($callback)){
                return $md5;
            }
            $md5_closure = self::closure_to_md5($callback);
            return is_wp_error($md5_closure) ? $md5 : $md5_closure;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see remove_action().
         *
         * @return bool
         */
        public static function remove_action($hook_name = '', $callback = null, $priority = 10){
            return self::off($hook_name, $callback, $priority);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see remove_filter().
         *
         * @return bool
         */
        public static function remove_filter($hook_name = '', $callback = null, $priority = 10){
            return self::off($hook_name, $callback, $priority);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see remove_action() and remove_filter().
         *
         * @return bool
         */
        public static function off($hook_name = '', $callback = null, $priority = 10){
            if(!is_null($callback)){
                $group = 'hooks';
                $key = self::uuid($hook_name . '-' . self::callback_md5($callback));
                self::cache_delete($key, $group);
            }
            return remove_filter($hook_name, $callback, $priority);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function on($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            if(is_null($callback)){
                return '';
            }
            add_filter($hook_name, $callback, $priority, $accepted_args);
            return self::_build_unique_id($callback);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function one($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            if(is_null($callback)){
                return '';
            }
            $group = 'hooks';
            $key = self::uuid($hook_name . '-' . self::callback_md5($callback));
            if(self::cache_exists($key, $group)){
                return self::cache_get($key, $group);
            }
            $idx = self::on($hook_name, $callback, $priority, $accepted_args);
            self::cache_set($key, $idx, $group);
            return $idx;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Image sizes
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'image_size_names_choose' filter hook.
    	 *
    	 * @return array
    	 */
        public static function _maybe_add_image_size_names($sizes){
    		if(!doing_filter('image_size_names_choose')){
    	        return $sizes; // Too early or too late.
    	    }
    		$image_sizes = self::cache_all('image_sizes');
    		foreach($image_sizes as $size => $name){
    			$sizes[$size] = $name;
    		}
    		return $sizes;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function add_4k_image_size(){
    		self::add_image_size('4K', 3840, 3840);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function add_full_hd_image_size(){
    		self::add_image_size('Full HD', 1920, 1920);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function add_hd_image_size(){
    		self::add_image_size('HD', 1280, 1280);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function add_image_size($name = '', $width = 0, $height = 0, $crop = false){
            $default_sizes = ['thumbnail', 'medium', 'medium_large', 'large'];
    		$size = self::canonicalize($name);
    		if(in_array($size, $default_sizes)){
    			return; // Does not overwrite the default image sizes.
    		}
    		add_image_size($size, $width, $height, $crop);
            self::add_filter_once('image_size_names_choose', [__CLASS__, '_maybe_add_image_size_names']);
    		self::cache_set($size, $name, 'image_sizes');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function add_larger_image_sizes(){
    		self::add_hd_image_size();
    		self::add_full_hd_image_size();
    		self::add_4k_image_size();
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Inputmask
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function inputmask_ver($set = ''){
            static $ver = '5.0.9';
            if(!$set){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function enqueue_inputmask($deps = []){
    		if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = self::use_inputmask();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $src = self::path_to_url($dir) . '/dist/jquery.inputmask.min.js';
            $ver = self::inputmask_ver();
            self::enqueue_dependency('inputmask', $src, $deps, $ver);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function use_inputmask(){
            $ver = self::inputmask_ver();
            $url = 'https://github.com/RobinHerbots/Inputmask/archive/refs/tags/' . $ver . '.zip';
            return self::use([
                'expected_dir' => 'Inputmask-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // jQuery Scrollspy
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function enqueue_jquery_scrollspy($deps = []){
            if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = self::use_jquery_scrollspy();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $base_path = self::path_to_url($dir);
            $ver = self::jquery_scrollspy_ver();
            self::enqueue_dependency('jquery-scrollspy', $base_path . '/scrollspy.js', $deps, $ver);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function jquery_scrollspy_ver($set = ''){
            static $ver = '0.1.3';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function use_jquery_scrollspy(){
            $ver = self::jquery_scrollspy_ver();
            $url = 'https://github.com/thesmart/jquery-scrollspy/archive/refs/tags/' . $ver . '.zip';
            return self::use([
    			'expected_dir' => 'jquery-scrollspy-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // JShrink
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function jshrink_ver($set = ''){
            static $ver = '1.8.1';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function minify_js($js = '', $options = []){
            $dir = self::use_jshrink();
        	if(is_wp_error($dir)){
        		return $js;
        	}
            return \JShrink\Minifier::minify($js, $options);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function use_jshrink(){
            $ver = self::jshrink_ver();
            $url = 'https://github.com/tedious/JShrink/archive/refs/tags/v' . $ver . '.zip';
            return self::use([
                'autoload' => 'src/JShrink/Minifier.php',
    			'expected_dir' => 'JShrink-' . $ver,
                'validation_class' => 'JShrink\Minifier',
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // JSON
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Checks if the remote request or response has specified a JSON Content-Type.
    	 *
    	 * @return bool
    	 */
        public static function is_json_content_type($content_type = []){
    		if(!$content_type){
    			return wp_is_json_request(); // Checks whether current request is a JSON request, or is expecting a JSON response.
    		}
    		if(!self::is_content_type($content_type)){
    			$content_type = self::get_content_type($content_type);
    			if(!$content_type){
    				return false;
    			}
    		}
    		return wp_is_json_media_type($content_type['value']);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool|WP_error
    	 */
        public static function is_json_wp_die_handler($data = []){
    		if(!self::array_keys_exist(['additional_errors', 'code', 'data', 'message'], $data)){
                return false;
            }
            $error = new \WP_Error($data['code'], $data['message'], $data['data']);
            foreach($data['additional_errors'] as $additional_error){
                if(!self::array_keys_exist(['code', 'data', 'message'], $additional_error)){
                    continue;
                }
                $error->add($additional_error['code'], $additional_error['message'], $additional_error['data']);
            }
            return $error;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @see json_decode().
    	 *
    	 * Retrieves the parameters from a JSON-formatted body.
    	 *
    	 * @return array|stdClass|WP_Error
    	 */
        public static function json_decode($json = '', $associative = null, $depth = 512, $flags = 0){
            $empty = ($associative || ($flags & JSON_OBJECT_AS_ARRAY)) ? [] : new \stdClass;
    		if(!$json){
    			return $empty;
    		}
    		$params = json_decode($json, $associative, $depth, $flags); // Parses the JSON parameters.
    		if(is_null($params) && JSON_ERROR_NONE !== json_last_error()){ // Check for a parsing error.
                $error_msg = __('Invalid JSON body passed.');
                return self::error($error_msg, [
    				'json_error_code' => json_last_error(),
    				'json_error_message' => json_last_error_msg(),
    				'status' => 400, // Bad request.
    			]);
    		}
    		return $params;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function json_encode_for_js($data = []){
            if(is_string($data)){
                $data = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
            } else {
                foreach((array) $data as $key => $value){
                    if(!is_scalar($value)){
                        continue;
                    }
                    $data[$key] = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
                }
            }
            return wp_json_encode($data);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Less.php
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function less_php_ver($set = ''){
            static $ver = '';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function parse_less($css = '', $options = []){
            $dir = self::use_less_php();
        	if(is_wp_error($dir)){
        		return $dir;
        	}
        	\Less_Autoloader::register();
            $parser = new \Less_Parser($options);
            try {
                $parser->parse($css);
                $result = $parser->getCss();
            } catch(\Throwable $t){
                $error_msg = str_replace(' in file anonymous-file-0.less in anonymous-file-0.less', '.', $t->getMessage());
                $result = self::error($error_msg);
            } catch(\Exception $e){
                $error_msg = str_replace(' in file anonymous-file-0.less in anonymous-file-0.less', '.', $e->getMessage());
                $result = self::error($error_msg);
            }
            return $result;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function use_less_php(){
    		$ver = self::less_php_ver();
    		if($ver){
                $url = 'https://github.com/wikimedia/less.php/archive/refs/tags/v' . $ver . '.zip';
    		} else {
    			if(is_php_version_compatible('8.1')){
    	            $ver = '5.5.1';
    	            $url = 'https://github.com/wikimedia/less.php/archive/refs/tags/v' . $ver . '.zip';
    	        } elseif(is_php_version_compatible('7.4')){
    	            $ver = '5.3.1';
    	            $url = 'https://github.com/wikimedia/less.php/archive/refs/tags/v' . $ver . '.zip';
    	        } else {
    	            $ver = '3.2.1';
    	            $url = 'https://github.com/wikimedia/less.php/archive/refs/tags/v' . $ver . '.zip';
    	        }
    		}
            return self::use([
                'autoload' => 'lib/Less/Autoloader.php',
    			'expected_dir' => 'less.php-' . $ver,
                'validation_class' => 'Less_Autoloader',
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // MD5
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_md5($string = ''){
            return (is_string($string) && preg_match('/^[a-f0-9]{32}$/i', $string) === 1);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function md5($data = ''){
            if(is_scalar($data)){
    			return self::is_md5($data) ? $data : md5($data);
    		}
            if(self::is_closure($data)){
    			$md5 = self::closure_to_md5($data);
    			if(!is_wp_error($md5)){
    				return $md5;
    			}
                $data = $md5;
            }
            if(is_array($data)){
    			$data = self::recursive_ksort($data);
    		}
    		return md5(serialize($data));
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function md5_to_uuid($md5 = ''){
            if(!self::is_md5($md5)){
                return '';
            }
    		$time_low = substr($md5, 0, 8);
    	    $time_mid = substr($md5, 8, 4);
    		$time_hi_and_version = sprintf('%04x', (hexdec(substr($md5, 12, 4)) & 0x0fff) | 0x3000); // Version 3 UUID.
    		$clock_seq = sprintf('%04x', (hexdec(substr($md5, 16, 4)) & 0x3fff) | 0x8000); // Variant RFC 4122.
    		$node = substr($md5, 20, 12);
    		return sprintf('%s-%s-%s-%s-%s', $time_low, $time_mid, $time_hi_and_version, $clock_seq, $node);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Miscellaneous
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'login_enqueue_scripts' action hook.
    	 *
         * @return void
         */
        public static function _maybe_replace_login_logo(){
            if(!doing_action('login_enqueue_scripts')){
    	        return; // Too early or too late.
    	    }
    		$key = 'custom_login_logo';
    		if(!self::cache_exists($key)){
    			return; // Nothing to do.
    		}
    		$value = self::cache_get($key);
    		self::add_inline_style("#login h1 a,.login h1 a{background-image:url('$value[0]');background-size:$value[1]px $value[2]px;height:$value[2]px;width:$value[1]px;}");
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return int
    	 */
        public static function absint($maybeint = 0){
    		return is_numeric($maybeint) ? absint($maybeint) : 0; // Make sure the value is numeric to avoid casting objects, for example, to int 1.
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function breadcrumbs($breadcrumbs = [], $separator = ''){
    	    $elements = [];
    	    foreach($breadcrumbs as $breadcrumb){
    			$breadcrumb = wp_parse_args($breadcrumb, [
    				'link' => '',
    				'target' => '_self',
    				'text' => '',
    			]);
    	        if(!$breadcrumb['text']){
    	            continue;
    	        }
    	        $elements[] = $breadcrumb['link'] ? sprintf('<a href="%1$s" target="%2$s">%3$s</a>', esc_url($breadcrumb['link']), esc_attr($breadcrumb['target']), esc_html($breadcrumb['text'])) : sprintf('<span>%1$s</span>', esc_html($breadcrumb['text']));
    	    }
            $separator = trim($separator);
            if(empty($separator)){
                $separator = DIRECTORY_SEPARATOR;
            }
    		return implode(' ' . trim($separator) . ' ', $elements);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_Role|WP_Error
    	 */
        public static function clone_role($source = '', $destination = '', $display_name = ''){
    		$role = get_role($source);
    		if(is_null($role)){
                $error_msg = __('Invalid role.');
    			return self::error($error_msg, $role);
    		}
            $role_name = self::canonicalize($destination);
    		return add_role($role_name, $display_name, $role->capabilities);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function current_screen_in($ids = []){
    		global $current_screen;
    		if(!is_array($ids)){
    			return false;
    		}
    		if(!isset($current_screen)){
    			return false;
    		}
    		return in_array($current_screen->id, $ids);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function current_screen_is($id = ''){
    		global $current_screen;
    		if(!is_string($id)){
    			return false;
    		}
    		if(!isset($current_screen)){
    			return false;
    		}
    		return $current_screen->id === $id;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function custom_login_logo($attachment_id = 0, $half = true){
            if(!wp_attachment_is_image($attachment_id)){
                return;
            }
            $custom_logo = wp_get_attachment_image_src($attachment_id, 'medium');
            $height = $custom_logo[2];
            $width = $custom_logo[1];
            if($width > 300){ // Fix for SVG.
                $r = 300 / $width;
    			$height *= $r;
                $width = 300;
            }
            if($half){
                $height = $height / 2;
                $width = $width / 2;
            }
    		self::add_action_once('login_enqueue_scripts', [__CLASS__, '_maybe_replace_login_logo']);
    		self::cache_set('custom_login_logo', [$custom_logo[0], $width, $height]);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function delete_options($prefix = ''){
    		global $wpdb;
            if(!$prefix){
                return; // Nothing to do.
            }
            $options = $wpdb->get_col("SELECT option_name FROM $wpdb->options WHERE option_name LIKE '{$prefix}%'");
            foreach($options as $option){
                delete_option($option);
            }
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return array|WP_Error
         */
        public static function exec($command = ''){
            $output = [];
    		if(!function_exists('exec')){
                $error_msg = __('Function %s used incorrectly in PHP.');
                $error_msg = sprintf($error_msg, 'exec');
    			return self::error($error_msg, $command);
    		}
            try {
                $result = exec($command, $output);
            } catch(\Throwable $t){
                $result = self::error($t->getMessage());
            } catch(\Exception $e){
                $result = self::error($e->getMessage());
            }
            return is_wp_error($result) ? $result : $output;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see Contact Form 7 wpcf7_format_atts().
    	 *
         * @return string
         */
        public static function format_atts($atts = [], $tag = ''){
            $atts_filtered = [];
            foreach($atts as $name => $value){
                $name = strtolower(trim($name));
                if(!preg_match('/^[a-z_:][a-z_:.0-9-]*$/', $name)){
                    continue;
                }
                static $boolean_attributes = ['checked', 'disabled', 'inert', 'multiple', 'readonly', 'required', 'selected'];
                if(in_array($name, $boolean_attributes) && $value === ''){
                    $value = false;
                }
                if(is_numeric($value)){
                    $value = (string) $value;
                }
                if(is_null($value) || $value === false){
                    unset($atts_filtered[$name]);
                } elseif($value === true){
                    $atts_filtered[$name] = $name; // Boolean attribute.
                } elseif(is_string($value)){
                    $atts_filtered[$name] = trim($value);
                }
            }
            $output = '';
            foreach($atts_filtered as $name => $value){
                $output .= sprintf(' %1$s="%2$s"', $name, esc_attr($value));
            }
            $output = trim($output);
            $tag = trim($tag);
            if(!$tag){
                return $output;
            }
            if(!$output){
                return $tag;
            }
            return $tag . ' ' . $output;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function format_function($function_name = '', $args = []){
    		$str = '<span style="color: #24831d; font-family: monospace; font-weight: 400;">' . $function_name . '(';
    		$function_args = [];
    		foreach($args as $arg){
    			$arg = shortcode_atts([
    				'default' => 'null',
    				'name' => '',
    				'type' => '',
    			], $arg);
    			if($arg['default'] && $arg['name'] && $arg['type']){
    				$function_args[] = '<span style="color: #cd2f23; font-family: monospace; font-style: italic; font-weight: 400;">' . $arg['type'] . '</span> <span style="color: #0f55c8; font-family: monospace; font-weight: 400;">$' . $arg['name'] . '</span> = <span style="color: #000; font-family: monospace; font-weight: 400;">' . $arg['default'] . '</span>';
    			}
    		}
    		if($function_args){
    			$str .= ' ' . implode(', ', $function_args) . ' ';
    		}
    		$str .= ')</span>';
    		return $str;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_redirect_to($fallback = ''){
    		$redirect_to = isset($_REQUEST['redirect_to']) ? wp_http_validate_url($_REQUEST['redirect_to']) : false;
    		if(!$redirect_to && $fallback){
    			$redirect_to = wp_http_validate_url($fallback);
    		}
    		return $redirect_to ? $redirect_to : '';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return int
    	 */
        public static function get_the_id_early(){
            if(!self::is_front()){
                return 0;
            }
            if(!isset($_SERVER['HTTP_HOST'])){
                return 0;
            }
            $requested_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']; // Build the URL in the address bar.
            if(isset($_SERVER['REQUEST_URI'])){
                $requested_url .= $_SERVER['REQUEST_URI'];
            }
            return url_to_postid($requested_url);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function go_to($str = ''){
    		return trim(str_replace('&larr;', '', sprintf(_x('&larr; Go to %s', 'site'), $str)));
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array
    	 */
        public static function has_shortcode($content = '', $tag = ''){
    	    if(strpos($content, '[') === false){
    	        return [];
    	    }
    	    if(!shortcode_exists($tag)){
    	        return [];
    	    }
    	    preg_match_all('/' . get_shortcode_regex() . '/', $content, $matches, PREG_SET_ORDER);
    	    if(!$matches){
    	        return [];
    	    }
    	    foreach($matches as $shortcode){
    	        if($shortcode[2] === $tag){
    	            return shortcode_parse_atts($shortcode[3]);
    	        }
    	        if(!$shortcode[5]){
    	            continue;
    	        }
    	        $attr = self::has_shortcode($shortcode[5], $tag); // Recursive.
    	        if(!$attr){
    	            continue;
    	        }
    	        return $attr;
    	    }
    	    return [];
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function host_url($url = ''){
            $host = wp_parse_url(sanitize_url($url), PHP_URL_HOST);
            if(is_null($host)){
                return '';
            }
            return substr($url, 0, (strpos($url, $host) + strlen($host)));
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function load_admin_textdomain($domain = 'default'){
            if(is_admin()){
                return false;
            }
            $locale = determine_locale();
            $mofile = WP_LANG_DIR . '/admin-' . $locale . '.mo';
            return file_exists($mofile) ? load_textdomain($domain, $mofile, $locale) : false;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Useful for returning whether a variable is not empty to filters easily.
    	 *
         * @return bool
         */
        public static function not_empty($var = null){
            return !empty($var);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array
    	 */
        public static function post_type_labels($singular = '', $plural = '', $all = true){
    		if(!$singular){
    			return [];
    		}
    		if(!$plural){
    			$plural = $singular;
    		}
    		$page = _x('Page', 'post type singular name');
            $pages = _x('Pages', 'post type general name');
            $labels = [
                'name' => _x('Pages', 'post type general name'),
    			'singular_name' => _x('Page', 'post type singular name'),
    			'add_new' => __('Add'),
    			'add_new_item' => __('Add Page'),
    			'edit_item' => __('Edit Page'),
    			'new_item' => __('New Page'),
    			'view_item' => __('View Page'),
    			'view_items' => __('View Pages'),
    			'search_items' => __('Search Pages'),
    			'not_found' => __('No pages found.'),
    			'not_found_in_trash' => __('No pages found in Trash.'),
    			'parent_item_colon' => __('Parent Page:'),
    			'all_items' => $all ? __('All Pages') : $pages,
    			'archives' => __('Page Archives'),
    			'attributes' => __('Page Attributes'),
    			'insert_into_item' => __('Insert into page'),
    			'uploaded_to_this_item' => __('Uploaded to this page'),
    			'featured_image' => _x('Featured image', 'page'),
    			'set_featured_image' => _x('Set featured image', 'page'),
    			'remove_featured_image' => _x('Remove featured image', 'page'),
    			'use_featured_image' => _x('Use as featured image', 'page'),
    			'filter_items_list' => __('Filter pages list'),
    			'filter_by_date' => __('Filter by date'),
    			'items_list_navigation' => __('Pages list navigation'),
    			'items_list' => __('Pages list'),
    			'item_published' => __('Page published.'),
    			'item_published_privately' => __('Page published privately.'),
    			'item_reverted_to_draft' => __('Page reverted to draft.'),
    			'item_trashed' => __('Page trashed.'),
    			'item_scheduled' => __('Page scheduled.'),
    			'item_updated' => __('Page updated.'),
    			'item_link' => _x('Page Link', 'navigation link block title'),
    			'item_link_description' => _x('A link to a page.', 'navigation link block description'),
            ];
            foreach($labels as $key => $value){
                $labels[$key] = str_replace([$page, $pages, lcfirst($page), lcfirst($pages)], [$singular, $plural, lcfirst($singular), lcfirst($plural)], $value);
            }
            return $labels;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function table($data = [], $headers = [], $args = []){
            $data = array_values($data);
            if(!$data){
                return __('No posts found.');
            }
            $defaults = [
                'bordered' => false,
                'borderless' => false,
                'hover' => false,
                'responsive' => false,
                'sm' => false,
                'striped' => false,
            ];
            $args = wp_parse_args($args, $defaults);
            $classes = ['table'];
            $html = '';
            $responsive = '';
            if($args['bordered']){
                $classes[] = 'table-bordered';
            }
            if($args['borderless']){
                $classes[] = 'table-borderless';
            }
            if($args['hover']){
                $classes[] = 'table-hover';
            }
            if($args['responsive']){
                if(in_array($args['responsive'], ['sm', 'md', 'lg', 'xl'])){
                    $responsive = 'table-responsive-' . $args['responsive'];
                } else {
                    $responsive = 'table-responsive';
                }
            }
            if($args['sm']){
                $classes[] = 'table-sm';
            }
            if($args['striped']){
                $classes[] = 'table-striped';
            }
            $max_cols = 0;
            if($headers){
                $max_cols = count($headers);
            } else {
                $max_cols = count($data[0]);
            }
            if($responsive){
                $html .= '<div class="' . $responsive . '">';
            }
            $atts = [
                'class' => implode(' ', array_unique(array_map('trim', $classes))),
            ];
            $html .= '<' . self::format_atts($atts, 'table') . '>';
            if($headers){
                $html .= '<thead>';
                $html .= '<tr>';
                foreach($headers as $header){
                    $html .= '<th>' . $header . '</th>';
                }
                $html .= '</tr>';
                $html .= '</thead>';
            }
            $html .= '<tbody>';
            foreach($data as $row){
                $row = array_values($row);
                $html .= '<tr>';
                foreach($row as $index => $column){
                    if($index === $max_cols){
                        break;
                    }
                    $html .= '<td>' . $column . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody>';
            $html .= '</table>';
            if($responsive){
                $html .= '</div>';
            }
            return $html;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function test(){
            $error_msg = __('%1$s is proudly powered by %2$s');
            $error_msg = sprintf($error_msg, get_bloginfo('name'), '<a href="https://wordpress.org/">WordPress</a>');
            self::exit_with_error($error_msg, __('Hello world!'), 200);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function use($atts = []){
            $pairs = [
                'autoload' => '',
                'expected_dir' => '',
                'requires_php' => '',
                'requires_wp' => '',
                'strict_validation' => false,
                'validation_class' => '',
                'validation_file' => '',
                'validation_function' => '',
                'zipball_url' => '',
            ];
            $atts = shortcode_atts($pairs, $atts);
            $group = 'use';
            $md5 = self::md5($atts);
            $key = self::md5_to_uuid($md5);
            if(self::cache_exists($key, $group)){
                return self::cache_get($key, $group);
            }
            if(!$atts['zipball_url']){
                $error_msg = __('No URL Provided.');
                $error = self::error($error_msg);
                self::cache_set($key, $error, $group);
                return $error;
        	}
            if(!wp_http_validate_url($atts['zipball_url'])){
                $error_msg = __('A valid URL was not provided.');
                $error = self::error($error_msg, $atts['zipball_url']);
                self::cache_set($key, $error, $group);
                return $error;
            }
            if($atts['requires_php']){
                if(!is_php_version_compatible($atts['requires_php'])){
                    $error_msg = __('This update does not work with your version of PHP.');
                    $error = self::error($error_msg, PHP_VERSION);
                    self::cache_set($key, $error, $group);
                    return $error;
                }
            }
            if($atts['requires_wp']){
                if(!is_wp_version_compatible($atts['requires_wp'])){
                    $error_msg = __('This update does not work with your version of WordPress.');
                    $error = self::error($error_msg, wp_get_wp_version());
                    self::cache_set($key, $error, $group);
                    return $error;
                }
            }
            if($atts['strict_validation']){
                if($atts['validation_class']){
                    $invalid = [];
                    $classes = (array) $atts['validation_class'];
                    foreach($classes as $class){
                        if(class_exists($class)){
                            $invalid[] = $class;
                        }
                    }
                    if($invalid){
                        $error = self::invalid_params($invalid);
                        self::cache_set($key, $error, $group);
                        return $error;
                    }
                }
                if($atts['validation_function']){
                    $invalid = [];
                    $functions = (array) $atts['validation_function'];
                    foreach($functions as $function){
                        if(function_exists($function)){
                            $invalid[] = $function;
                        }
                    }
                    if($invalid){
                        $error = self::invalid_params($invalid);
                        self::cache_set($key, $error, $group);
                        return $error;
                    }
                }
            }
            $dir = self::remote_package($atts['zipball_url']);
            if(is_wp_error($dir)){
                return $dir;
            }
            $expected_dir = $dir;
            if($atts['expected_dir']){
                $expected_dir = self::path_join($dir, $atts['expected_dir']);
            }
            if($atts['validation_class']){
                $valid = true;
                $classes = (array) $atts['validation_class'];
                foreach($classes as $class){
                    if(class_exists($class)){
                        $valid = false;
                        break;
                    }
                }
                if(!$valid){
                    return $expected_dir;
                }
            }
            if($atts['validation_function']){
                $valid = true;
                $functions = (array) $atts['validation_function'];
                foreach($functions as $function){
                    if(function_exists($function)){
                        $valid = false;
                        break;
                    }
                }
                if(!$valid){
                    return $expected_dir;
                }
            }
            if($atts['validation_file']){
                $missing = [];
                $files = (array) $atts['validation_file'];
                foreach($files as $path){
                    $file = self::path_join($expected_dir, $path);
                    if(!file_exists($file)){
                        $missing[] = $path;
                    }
                }
                if($missing){
                    $error = self::missing_params($missing);
                    self::cache_set($key, $error, $group);
                    return $error;
                }
            }
            if($atts['autoload']){
                $missing = [];
                $autoload = (array) $atts['autoload'];
                foreach($autoload as $path){
                    $file = self::path_join($expected_dir, $path);
                    if(file_exists($file)){
                        require_once $file;
                    } else {
                        $missing[] = $path;
                    }
                }
                if($missing){
                    $error = self::missing_params($missing);
                    self::cache_set($key, $error, $group);
                    return $error;
                }
            }
            if($atts['validation_class']){
                $missing = [];
                $classes = (array) $atts['validation_class'];
                foreach($classes as $class){
                    if(!class_exists($class)){
                        $missing[] = $class;
                    }
                }
                if($missing){
                    $error = self::missing_params($missing);
                    self::cache_set($key, $error, $group);
                    return $error;
                }
            }
            if($atts['validation_function']){
                $missing = [];
                $functions = (array) $atts['validation_function'];
                foreach($functions as $function){
                    if(!function_exists($function)){
                        $missing[] = $function;
                    }
                }
                if($missing){
                    $error = self::missing_params($missing);
                    self::cache_set($key, $error, $group);
                    return $error;
                }
            }
            self::cache_set($key, $expected_dir, $group);
            return $expected_dir;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Nonces
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function create_nonce_guest($action = -1){
            $i = wp_nonce_tick($action);
            $token = self::get_session_token_guest();
            $uid = 0;
            return substr(wp_hash($i . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_session_token_guest($action = -1){
            return self::base64_urlencode(hash('sha256', $action, true)); // 43 chars just like wp_get_session_token().
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function nonce_url($actionurl = '', $action = -1, $name = '_wpnonce'){
            //$actionurl = str_replace('&amp;', '&', $actionurl);
            return esc_html(add_query_arg($name, self::create_nonce_guest($action), $actionurl));
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function nonce_url_guest($actionurl = '', $action = -1, $name = '_wpnonce'){
            //$actionurl = str_replace('&amp;', '&', $actionurl);
            return esc_html(add_query_arg($name, self::create_nonce_guest($action), $actionurl));
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function verify_nonce_guest($nonce = '', $action = -1){
            $nonce = (string) $nonce;
        	if(!$nonce){
        		return false;
        	}
            $i = wp_nonce_tick($action);
            $token = self::get_session_token_guest();
        	$uid = 0;
        	$expected = substr(wp_hash($i . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
        	if(hash_equals($expected, $nonce)){
        		return 1; // Nonce generated 0-12 hours ago.
        	}
        	$expected = substr(wp_hash(($i - 1) . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
        	if(hash_equals($expected, $nonce)){
        		return 2; // Nonce generated 12-24 hours ago.
        	}
        	return false; // Invalid nonce.
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Objects
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function object_properties_exist($properties = [], $object_or_class = null){
    		if(!is_array($properties)){
    			return false;
    		}
    		$valid_class = is_string($object_or_class) && class_exists($object_or_class);
    		$valid_object = is_object($object_or_class);
    		if(!$valid_class && !$valid_object){
    			return false;
    		}
    		foreach($properties as $property){
    			if(!property_exists($object_or_class, $property)){
    				return false;
    			}
    		}
    		return true;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array|WP_Error
    	 */
        public static function object_to_array($data = null){
    		if(!is_object($data)){
                $error_msg = __('Invalid data provided.');
    			return self::error($error_msg, $data);
    		}
            $object_vars = get_object_vars($data);
    		return self::json_decode(wp_json_encode($object_vars), true);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Opis Closure
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function closure_to_md5($data = null, $spl_object_hash = false){
            if(!self::is_closure($data)){
                $error_msg = __('Invalid object type.');
    			return self::error($error_msg, $data);
    		}
    		$serialized_data = self::serialize_closure($data);
    		if(is_wp_error($serialized_data)){
    			return $serialized_data;
    		}
    		return $spl_object_hash ? md5($serialized_data) : md5(str_replace(spl_object_hash($data), '__SPL_OBJECT_HASH__', $serialized_data));
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_closure($thing = null){
            return $thing instanceof \Closure;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function opis_closure_ver($set = ''){
            static $ver = '4.5.0';
            if(!$set){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function serialize_closure($data = null, $security = null){
    		if(!self::is_closure($data)){
                $error_msg = __('Invalid object type.');
    			return self::error($error_msg, $data);
    		}
            $dir = self::use_closure();
    		return is_wp_error($dir) ? $dir : \Opis\Closure\serialize($data, $security);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return mixed|WP_Error
    	 */
        public static function unserialize_closure($data = '', $security = null, $options = null){
            if(!is_string($data)){
                $error_msg = __('Invalid data provided.');
    			return self::error($error_msg, $data);
    		}
    		$dir = self::use_closure();
            return is_wp_error($dir) ? $dir : \Opis\Closure\unserialize($data, $security, $options);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function use_closure(){
            $ver = self::opis_closure_ver();
            $url = 'https://github.com/opis/closure/archive/refs/tags/' . $ver . '.zip';
            return self::use([
                'autoload' => 'autoload.php',
                'expected_dir' => 'closure-' . $ver,
                'validation_class' => 'Opis\Closure\Serializer',
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Plugin Update Checker
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'puc_request_info_query_args-SLUG' filter hook.
    	 *
    	 * @return array
    	 */
        public static function _maybe_set_update_license($queryArgs){
    		$current_filter = current_filter();
    		if(!self::str_starts_with($current_filter, 'puc_request_info_query_args-')){
    	        return; // Too early or too late.
    	    }
    		$slug = str_replace('puc_request_info_query_args-', '', $current_filter);
    		if(!self::cache_exists($slug, 'puc_licenses')){
    			return $queryArgs;
    		}
    		$queryArgs['license'] = self::cache_get($slug, 'puc_licenses');
    		return $queryArgs;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return YahnisElsts\PluginUpdateChecker\v5p6\Vcs\BaseChecker|WP_Error
    	 */
        public static function build_update_checker(...$args){
            $group = 'plugin_update_checker';
            $key = self::md5($args);
            if(self::cache_exists($key, $group)){
                return self::cache_get($key, $group);
            }
    		$dir = self::use_plugin_update_checker();
    		if(is_wp_error($dir)){
    			return $dir;
    		}
            $class = self::plugin_update_checker_class();
            $update_checker = call_user_func_array([$class, 'buildUpdateChecker'], $args);
    		self::cache_set($key, $update_checker, $group);
            return $update_checker;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function plugin_update_checker_class(){
            $ver = self::plugin_update_checker_ver();
            $ver = explode('.', $ver);
            $puc = 'v' . $ver[0];
            if(isset($ver[1])){
                $puc .= 'p' . $ver[1];
            }
            return 'YahnisElsts\PluginUpdateChecker\\' . $puc . '\PucFactory';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function plugin_update_checker_ver($set = ''){
            static $ver = '5.6';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function set_plugin_update_license($slug = '', $license = ''){
    		if(!$slug || !$license){
    			return;
    		}
            self::add_filter_once('puc_request_info_query_args-' . $slug, [__CLASS__, '_maybe_set_update_license']);
            self::cache_set($slug, $license, 'puc_licenses');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function use_plugin_update_checker(){
            $ver = self::plugin_update_checker_ver();
            $url = 'https://github.com/YahnisElsts/plugin-update-checker/archive/refs/tags/v' . $ver . '.zip';
            return self::use([
                'autoload' => 'plugin-update-checker.php',
    			'expected_dir' => 'plugin-update-checker-' . $ver,
                'validation_class' => self::plugin_update_checker_class(),
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Plugins
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function are_plugins_active($plugins = []){
            if(!is_array($plugins)){
                return false;
            }
            $result = true;
            foreach($plugins as $plugin){
                if(self::is_plugin_active($plugin)){
                    continue;
                }
                $result = false;
                break;
            }
            return $result;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function is_plugin_active($plugin = ''){
            $group = 'active_plugins';
            if(self::cache_exists($plugin, $group)){
                return self::cache_get($plugin, $group);
            }
            if(!function_exists('is_plugin_active')){
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $status = is_plugin_active($plugin);
            self::cache_set($plugin, $status, $group);
            return $status;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function is_plugin_deactivating($file = ''){
            global $pagenow;
            if(!$file){
                $file = self::caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return $file;
                }
            }
            $plugin_file = self::plugin_file($file);
            if(is_wp_error($plugin_file)){
                return false; // File is not a plugin.
            }
            return (is_admin() && $pagenow === 'plugins.php' && isset($_GET['action'], $_GET['plugin']) && $_GET['action'] === 'deactivate' && $_GET['plugin'] === plugin_basename($plugin_file));
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return string|WP_Error
         */
        public static function main_plugin_file($plugin = '', $mu_plugin = false){
            $dir = wp_normalize_path($mu_plugin ? WPMU_PLUGIN_DIR : WP_PLUGIN_DIR);
            if(!$mu_plugin && self::is_plugin_active($plugin)){
                return $dir . '/' . $plugin; // Plugin is the main plugin file.
            }
            $parts = explode('/', $plugin);
            if(count($parts) < 2){ // The entire plugin consists of just a single PHP file, like Hello Dolly.
                if($mu_plugin){
                    return $dir . '/' . $plugin; // Plugin is a must-use plugin.
                }
                $error_msg = __('Plugin not found.');
                return self::error($error_msg); // Plugin is inactive.
            }
            if($mu_plugin){
                $error_msg = __('Invalid plugin path.');
                return self::error($error_msg);
            }
            // wp_get_mu_plugins(); ?
            $active_plugins = (array) get_option('active_plugins', []);
            $plugin_dir = trailingslashit($parts[0]); // The plugin directory name (with trailing slash).
            $plugin_file = '';
            foreach($active_plugins as $active_plugin){
                if(!self::str_starts_with($active_plugin, $plugin_dir)){
                    continue;
                }
                $plugin_file = $dir . '/' . $active_plugin;
                break;
            }
            if($plugin_file){
                return $plugin_file; // Plugin is active.
            }
            $active_sitewide_plugins = (array) get_site_option('active_sitewide_plugins', []);
            $active_sitewide_plugins = array_keys($active_sitewide_plugins);
            foreach($active_sitewide_plugins as $active_sitewide_plugin){
                if(!self::str_starts_with($active_sitewide_plugin, $plugin_dir)){
                    continue;
                }
                $plugin_file = $dir . '/' . $active_sitewide_plugin;
            }
            if($plugin_file){
                return $plugin_file; // Plugin is active for the entire network.
            }
            $error_msg = __('Plugin not found.');
            return self::error($error_msg); // Plugin is inactive.
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return string|WP_Error
         */
        public static function plugin_basename($file = '', $mu_plugin = false){
            global $wp_plugin_paths;
        	$file = wp_normalize_path($file); // $wp_plugin_paths contains normalized paths.
        	arsort($wp_plugin_paths);
        	foreach($wp_plugin_paths as $dir => $realdir){
        		if(self::str_starts_with($file, $realdir)){
        			$file = $dir . substr($file, strlen($realdir));
        		}
        	}
            $dir = wp_normalize_path($mu_plugin ? WPMU_PLUGIN_DIR : WP_PLUGIN_DIR);
            $pattern = '#^' . preg_quote($dir, '#') . '/#';
            if(!preg_match($pattern, $file)){
                $error_msg = __('Plugin not found.');
                return self::error($error_msg);
            }
            $file = preg_replace($pattern, '', $file);
        	$file = trim($file, '/');
        	return $file; // Get relative path from plugins directory.
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return array|WP_Error
         */
        public static function plugin_data($file = '', $markup = true, $translate = true){
            if(!$file){
                $file = self::caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return $file;
                }
            }
            $plugin_file = self::plugin_file($file);
            if(is_wp_error($plugin_file)){
                return $plugin_file;
            }
            $data = [
                'markup' => $markup,
                'plugin_file' => $plugin_file,
                'translate' => $translate,
            ];
            $group = 'plugin_data';
            $md5 = self::md5($data);
            if(self::cache_exists($md5, $group)){
                return self::cache_get($md5, $group);
            }
            if(!function_exists('get_plugin_data')){
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $data = get_plugin_data($plugin_file, $markup, $translate);
            self::cache_set($md5, $data, $group);
            return $data;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function plugin_file($file = ''){
            global $wp_plugin_paths;
            if(!$file){
                $file = self::caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return $file;
                }
            }
            if(!file_exists($file)){
                $error_msg = __('File does not exist! Please double check the name and try again.');
                return self::error($error_msg);
            }
            $group = 'plugin_files';
            $key = md5($file);
            if(self::cache_exists($key, $group)){
                return self::cache_get($key, $group);
            }
            if(self::is_path_in_plugins_dir($file)){
                $mu_plugin = false;
                $plugin = self::plugin_basename($file, $mu_plugin);
            } elseif(self::is_path_in_mu_plugins_dir($file)){
                $mu_plugin = true;
                $plugin = self::plugin_basename($file, $mu_plugin);
            } else {
                $error_msg = __('Invalid plugin path.');
                $plugin = self::error($error_msg);
            }
            if(is_wp_error($plugin)){
                self::cache_set($key, $plugin, $group);
                return $plugin;
            }
            $plugin_file = self::main_plugin_file($plugin, $mu_plugin);
            self::cache_set($key, $plugin_file, $group);
            return $plugin_file;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function plugin_id($file = ''){
            if(!$file){
                $file = self::caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return $file;
                }
            }
            if(!file_exists($file)){
                return self::missing_file($file);
            }
            $group = 'plugin_ids';
            $key = md5($file);
            if(self::cache_exists($key, $group)){
                return self::cache_get($key, $group);
            }
            if(self::is_path_in_plugins_dir($file)){
                $plugin = self::plugin_basename($file);
            } elseif(self::is_path_in_mu_plugins_dir($file)){
                $plugin = self::plugin_basename($file, true);
            } else {
                $error_msg = __('Invalid plugin path.');
                $plugin = self::error($error_msg);
            }
            if(is_wp_error($plugin)){
                self::cache_set($key, $plugin, $group);
                return $plugin;
            }
            $parts = explode('/', $plugin);
            $plugin_id = wp_basename($parts[0], '.php');
            self::cache_set($key, $plugin_id, $group);
            return $plugin_id;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string|WP_Error
         */
        public static function plugin_meta($key = '', $file = '', $markup = false, $translate = false){
            if(!$file){
                $file = self::caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return $file;
                }
            }
            $data = self::plugin_data($file, $markup, $translate);
            if(is_wp_error($data)){
                return $data;
            }
            if(isset($data[$key])){
                $arr = $data;
            } elseif(isset($data['sections'], $data['sections'][$key])){
                $arr = $data['sections'];
            } else {
                $error_msg = '"' . $key . '" ' . __('(not found)');
                return self::error($error_msg);
            }
            return $arr[$key];
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function plugin_prefix($str = '', $file = ''){
            if(!$file){
                $file = self::caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return self::str_prefix($str);
                }
            }
            $plugin_id = self::plugin_id($file);
            if(is_wp_error($plugin_id)){
                return self::str_prefix($str);
            }
            return self::str_prefix($str, $plugin_id);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function plugin_slug($str = '', $file = ''){
            if(!$file){
                $file = self::caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return self::str_slug($str);
                }
            }
            $plugin_id = self::plugin_id($file);
            if(is_wp_error($plugin_id)){
                return self::str_slug($str);
            }
            return self::str_slug($str, $plugin_id);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function plugin_slug_to_basename($slug = ''){
            $basename = '';
            $plugins = get_plugins();
            foreach($plugins as $name => $plugin){
                if(!self::str_starts_with($name, $slug . '/')){
                    continue;
                }
                $basename = $name;
                break;
            }
            if(!$basename){
                $error_msg = __('Plugin not found.');
                return self::error($error_msg);
            }
            return $basename;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return Plugin\UpdateChecker|Theme\UpdateChecker|Vcs\BaseChecker|WP_Error
         */
        public static function plugin_update_checker($file = ''){
            if(!$file){
                $file = self::caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return $file;
                }
            }
            $plugin_file = self::plugin_file($file);
            if(is_wp_error($plugin_file)){
                return $plugin_file;
            }
            $metadata_url = self::plugin_meta('UpdateURI', $plugin_file);
            if(is_wp_error($metadata_url)){
                return $metadata_url;
            }
            if(!wp_http_validate_url($metadata_url)){
                $error_msg = __('A valid URL was not provided.');
                return self::error($error_msg);
            }
            $slug = self::plugin_slug($plugin_file);
            if(is_wp_error($slug)){
                return $slug;
            }
            $update_checker = self::build_update_checker($metadata_url, $plugin_file, $slug);
            $constant = strtoupper(self::str_prefix('license', str_replace('-', '_', $slug)));
            if(defined($constant)){
                self::set_plugin_update_license($slug, constant($constant));
            }
            return $update_checker;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_Post|array|null
    	 */
        public static function get_post($post = null, $output = OBJECT, $filter = 'raw'){
            if(!is_array($post)){
                return get_post($post, $output, $filter);
            }
            $post['fields'] = 'ids';
            $post['posts_per_page'] = 1;
            $post_ids = get_posts($post);
            if(!$post_ids){
                return null;
            }
            return get_post($post_ids[0], $output, $filter);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_Query
    	 */
        public static function get_posts_args($args = []){
    		$defaults = [
    			'category' => 0,
    			'exclude' => [],
    			'include' => [],
    			'meta_key' => '',
    			'meta_value' => '',
    			'numberposts' => 5,
    			'order' => 'DESC',
    			'orderby' => 'date',
    			'post_type' => 'post',
    			'suppress_filters' => true,
    		];
    		$parsed_args = wp_parse_args($args, $defaults);
    		if(empty($parsed_args['post_status'])){
    			$parsed_args['post_status'] = $parsed_args['post_type'] === 'attachment' ? 'inherit' : 'publish';
    		}
    		if(!empty($parsed_args['numberposts']) && empty($parsed_args['posts_per_page'])){
    			$parsed_args['posts_per_page'] = $parsed_args['numberposts'];
    		}
    		if(!empty($parsed_args['category'])){
    			$parsed_args['cat'] = $parsed_args['category'];
    		}
    		if(!empty($parsed_args['include'])){
    			$incposts = wp_parse_id_list($parsed_args['include']);
    			$parsed_args['posts_per_page'] = count($incposts);  // Only the number of posts included.
    			$parsed_args['post__in'] = $incposts;
    		} elseif(!empty($parsed_args['exclude'])){
    			$parsed_args['post__not_in'] = wp_parse_id_list($parsed_args['exclude']);
    		}
    		$parsed_args['ignore_sticky_posts'] = true;
    		$parsed_args['no_found_rows'] = true;
    		return $parsed_args;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_Query
    	 */
        public static function get_posts_query($args = []){
    		$query = new \WP_Query;
    		$query->query($args);
    		return $query;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return int
    	 */
        public static function get_the_id(){
            if(!did_action('parse_query')){
                return self::get_the_id_early();
            }
            if(in_the_loop()){
                return get_the_ID();
            }
    		$object = get_queried_object();
    		if(is_null($object)){
    			return 0;
    		}
    		if(!$object instanceof \WP_Post){
    			return 0;
    		}
            return get_queried_object_id();
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * Alias for wp_get_current_user(), get_user_by() or get_userdata().
    	 *
    	 * @return bool|WP_User
    	 */
        public static function get_user($user = null){
    	    if(is_null($user)){
    	        return is_user_logged_in() ? wp_get_current_user() : false;
    		}
    	    if($user instanceof \WP_User){
    	        return $user->exists() ? $user : false;
    	    }
    	    if(is_numeric($user)){
    	        return get_userdata($user);
    	    }
    	    if(!is_string($user)){
    	        return false;
    	    }
    	    if(username_exists($user)){
    	        return get_user_by('login', $user); // 1.
    	    }
    	    if(!is_email($user)){
    	        return false;
    	    }
    	    return get_user_by('email', $email); // 2.
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return WP_User_Query
    	 */
        public static function get_users_query($args = []){
            $args = wp_parse_args($args, [
    	        'count_total' => false,
    	    ]);
    	    return new \WP_User_Query($args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Remote
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_remote_country($default = ''){
            $cloudflare_country = self::get_cf_country();
            $wordfence_country = self::get_wf_country();
    		switch(true){
    			case !empty($cloudflare_country):
    				$country = $cloudflare_country;
    				break;
                case !empty($wordfence_country):
    				$country = $wordfence_country;
    				break;
    			default:
    				$country = $default;
    		}
    		return preg_match('/^[a-zA-Z]{2}$/', $country) ? strtoupper($country) : ''; // ISO 3166-1 alpha-2.
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_remote_ip($default = ''){
            $cloudflare_ip = self::get_cf_ip();
            $wordfence_ip = self::get_wf_ip();
    		switch(true){
    			case !empty($cloudflare_ip):
    				$ip = $cloudflare_ip;
    				break;
    			case !empty($wordfence_ip):
    				$ip = $wordfence_ip;
    				break;
    			case !empty($_SERVER['HTTP_X_FORWARDED_FOR']):
    				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    				break;
    			case !empty($_SERVER['HTTP_X_REAL_IP']):
    				$ip = $_SERVER['HTTP_X_REAL_IP'];
    				break;
    			case !empty($_SERVER['REMOTE_ADDR']):
    				$ip = $_SERVER['REMOTE_ADDR'];
    				break;
    			default:
    				$ip = $default;
    		}
    		return \WP_Http::is_ip_address($ip) ? $ip : '';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function get_status_message($code = 0){
    		if(!$code || !is_numeric($code)){
                $message = __('An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="%s">support forums</a>.');
        		$message = self::first_p($message);
    			return $message;
    		}
    		$message = get_status_header_desc($code);
    		if($message){
    			return $message;
    		}
            return self::is_success($code) ? __('OK') : __('Error');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_parsed_remote_response($response = null){
            return self::object_properties_exist(['body', 'code', 'cookies', 'download', 'filename', 'headers', 'json', 'json_params', 'message', 'response', 'status', 'tmpf', 'wp_error'], $response);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_remote_request($args = []){
    		if(!$args){
    			return false;
    		}
            if(!is_array($args)){
                $args = wp_parse_args($args);
            }
            $is_request = true;
    		$request_args = ['body', 'blocking', 'compress', 'cookies', 'decompress', 'filename', 'headers', 'httpversion', 'limit_response_size', 'method', 'redirection', 'reject_unsafe_urls', 'sslcertificates', 'sslverify', 'stream', 'timeout', 'user-agent']; // https://developer.wordpress.org/reference/classes/wp_http/request/#parameters
    		foreach(array_keys($args) as $arg){
    			if(!in_array($arg, $request_args)){
    				$is_request = false;
    				break;
    			}
    		}
            return $is_request;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_remote_response($response = []){
            return self::array_keys_exist(['body', 'cookies', 'filename', 'headers', 'http_response', 'response'], $response); // https://developer.wordpress.org/reference/classes/wp_http/request/#return
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @see is_success().
    	 *
    	 * @return bool
    	 */
        public static function is_success($sc = 0){
            if(!is_numeric($sc)){
                if(self::is_parsed_remote_response($sc)){
                    $sc = $sc->code;
                } elseif(self::is_remote_response($sc)){
                    $sc = (int) $sc['response']['code'];
                } else {
                    return false;
                }
            }
            $sc = self::absint($sc);
    		return ($sc >= 200 && $sc < 300);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function remote_delete($url = '', $args = []){
    		return self::remote_request('delete', $url, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function remote_download($url = '', $args = []){
            if(!$url){
                $error_msg = __('No URL Provided.');
                return self::error($error_msg);
        	}
            $group = 'remote_downloads';
            $key = self::uuid($url . '-' . self::md5($args));
            if(self::cache_exists($key, $group)){
                return self::cache_get($key, $group);
            }
            $dir = self::get_upload_dir('downloads/' . $key);
            if(is_wp_error($dir)){
                self::cache_set($key, $dir, $group);
                return $dir;
            }
            $ret = self::fs_list_files($dir, false);
    		if(is_wp_error($ret)){
                self::cache_set($key, $ret, $group);
                return $ret;
            }
            if($ret){
                $filename = array_key_first($ret);
                $file = path_join($dir, $filename);
                self::cache_set($key, $file, $group);
                return $file;
            }
            $args = self::sanitize_remote_request_args($args, $url);
            $new_filename = '';
            if(isset($args['filename'])){
                $new_filename = wp_basename($args['filename']);
                if(!self::is_valid_filename($new_filename)){
                    $new_filename = '';
                }
                unset($args['filename']);
            }
            $url_filename = self::basename($url);
            if(!function_exists('wp_tempnam')){
    			require_once(ABSPATH . 'wp-admin/includes/file.php');
    		}
            $tmpfname = wp_tempnam($url_filename, $dir);
        	if(!$tmpfname){
                $error_msg = __('Could not create temporary file.');
                $error = self::error($error_msg);
                self::cache_set($key, $error, $group);
        		return $error;
        	}
            $args['filename'] = $tmpfname;
            $args['stream'] = true;
            $response = self::remote_get($url, $args);
            if(!$response->status){
                self::cache_set($key, $response->wp_error, $group);
                self::fs_delete($tmpfname);
                return $response->wp_error;
            }
            if(isset($response->headers['Content-Disposition'])){
                $content_disposition = $response->headers['Content-Disposition'];
                $content_disposition = strtolower($content_disposition);
                if(self::str_starts_with($content_disposition, 'attachment; filename=')){
        			$tmpfname_disposition = sanitize_file_name(substr($content_disposition, 21));
        		} else {
        			$tmpfname_disposition = '';
        		}
                // Potential file name must be valid string.
        		if($tmpfname_disposition && is_string($tmpfname_disposition) && validate_file($tmpfname_disposition) === 0){
        			$tmpfname_disposition = dirname($tmpfname) . '/' . $tmpfname_disposition;
                    if(self::fs_move($tmpfname, $tmpfname_disposition)){
                        $tmpfname = $tmpfname_disposition;
                    }
        		}
            }
            // Allow uploading images from URLs without extensions.
            if(isset($response->headers['content-type'])){
                $mime_type = $response->headers['content-type'];
                if($mime_type && pathinfo($tmpfname, PATHINFO_EXTENSION) === 'tmp'){
            		$valid_mime_types = array_flip(get_allowed_mime_types());
            		if(!empty($valid_mime_types[$mime_type])){
            			$extensions = explode('|', $valid_mime_types[$mime_type]);
            			$new_image_name = substr($tmpfname, 0, -4) . ".{$extensions[0]}";
            			if(validate_file($new_image_name) === 0){
                            if(self::fs_move($tmpfname, $new_image_name)){
                                $tmpfname = $new_image_name;
                            }
            			}
            		}
            	}
            }
            if(isset($response->headers['Content-MD5'])){
                $content_md5 = $response->headers['Content-MD5'];
                $md5_check = verify_file_md5($tmpfname, $content_md5);
        		if(is_wp_error($md5_check)){
                    self::cache_set($key, $md5_check, $group);
                    self::fs_delete($tmpfname);
        			return $md5_check;
        		}
            }
            if($new_filename){
                $new_filename = self::unique_filename($dir, $new_filename);
                if(self::fs_move($tmpfname, $new_filename)){
                    $tmpfname = $new_filename;
                }
                self::cache_set($key, $tmpfname, $group);
                return $tmpfname;
            }
            if(self::is_valid_filename($url_filename)){
                $new_filename = self::unique_filename($dir, $url_filename);
                if(self::fs_move($tmpfname, $new_filename)){
                    $tmpfname = $new_filename;
                }
                self::cache_set($key, $tmpfname, $group);
                return $tmpfname;
            }
            $filetype = wp_check_filetype($tmpfname);
            if($filetype['ext']){
                self::cache_set($key, $tmpfname, $group);
                return $tmpfname;
            }
            $filetype = wp_check_filetype_and_ext($tmpfname, $url_filename);
            if($filetype['proper_filename']){
                $new_filename = self::unique_filename($dir, $filetype['proper_filename']);
                if(self::fs_move($tmpfname, $new_filename)){
                    $tmpfname = $new_filename;
                }
                self::cache_set($key, $tmpfname, $group);
                return $tmpfname;
            }
            $error_msg = __('Sorry, you are not allowed to upload this file type.');
            $error = self::error($error_msg);
            self::cache_set($key, $error, $group);
            self::fs_delete($tmpfname);
            return $error;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function remote_get($url = '', $args = []){
    		return self::remote_request('get', $url, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function remote_head($url = '', $args = []){
    		return self::remote_request('head', $url, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function remote_options($url = '', $args = []){
    		return self::remote_request('options', $url, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function remote_package($url = '', $args = []){
            if(!$url){
                $error_msg = __('No URL Provided.');
                return self::error($error_msg);
        	}
            $group = 'remote_packages';
    		$key = self::uuid($url . '-' . self::md5($args));
            if(self::cache_exists($key, $group)){
                return self::cache_get($key, $group);
            }
            $dir = self::get_upload_dir('packages/' . $key);
            if(is_wp_error($dir)){
                self::cache_set($key, $dir, $group);
                return $dir;
            }
            $ret = self::fs_dirlist($dir, false);
    		if(is_wp_error($ret)){
                self::cache_set($key, $ret, $group);
                return $ret;
            }
            if($ret){
                self::cache_set($key, $dir, $group);
                return $dir;
            }
            $file = self::remote_download($url, $args);
            if(is_wp_error($file)){
                self::cache_set($key, $file, $group);
                return $file;
            }
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if($extension !== 'zip'){
                $error_msg = __('Only .zip archives may be uploaded.');
                $error = self::error($error_msg);
                self::cache_set($key, $error, $group);
                return $error;
            }
            $result = unzip_file($file, $dir);
    		if(is_wp_error($result)){
                self::cache_set($key, $result, $group);
    			return $result;
    		}
            $ret = self::fs_dirlist($dir, false);
    		if(is_wp_error($ret)){
                self::cache_set($key, $ret, $group);
                return $ret;
            }
            if($ret){
                self::cache_set($key, $dir, $group);
                return $dir;
            }
            $error_msg = __('Empty archive.');
            $error = self::error($error_msg);
            self::cache_set($key, $error, $group);
            return $error;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function remote_patch($url = '', $args = []){
    		return self::remote_request('patch', $url, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function remote_post($url = '', $args = []){
    		return self::remote_request('post', $url, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function remote_put($url = '', $args = []){
    		return self::remote_request('put', $url, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function remote_request($method = '', $url = '', $args = []){
    		$args = self::sanitize_remote_request_args($args, $url);
            if(!isset($args['method'])){
                $args['method'] = self::sanitize_remote_request_method($method);
            }
    		$response = wp_remote_request($url, $args);
    		return self::parse_remote_response($response);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function remote_trace($url = '', $args = []){
    		return self::remote_request('trace', $url, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function parse_remote_response($raw_response = []){
            if(self::is_parsed_remote_response($raw_response)){
                return $raw_response;
            }
            $response = new \stdClass;
            $response->body = '';
            $response->code = 0;
            $response->cookies = [];
            $response->download = false;
            $response->filename = '';
            $response->headers = [];
            $response->json = false;
            $response->json_params = [];
            $response->message = '';
    		$response->raw = $raw_response;
            $response->response = [];
            $response->status = false; // Backward compatibility.
    		$response->success = false;
            $response->tmpf = '';
            $response->wp_error = null;
            if(is_wp_error($raw_response)){
                $response->message = $raw_response->get_error_message();
                $response->wp_error = $raw_response;
            } elseif(self::is_remote_response($raw_response)){
                $array = $raw_response['http_response']->to_array();
                $response->body = trim($array['body']);
                $response->code = (int) $array['response']['code'];
                $response->cookies = $array['cookies'];
                $response->filename = $array['filename'];
                $response->headers = $array['headers'];
                $response->json = self::is_json_content_type($raw_response);
                $response->message = trim($array['response']['message']);
                $response->response = $raw_response;
                $response->success = self::is_success($response->code);
    			$response->status = $response->success; // Backward compatibility.
                if($response->filename){
                    $response->download = true;
                }
                if(!$response->message){
                    $response->message = self::get_status_message($response->code);
                }
                if(!$response->success){
                    if($response->download){
                        $tmpf = fopen($response->filename, 'rb'); // Retrieve a sample of the response body for debugging purposes.
                        if($tmpf){
                            $response_size = apply_filters('download_url_error_max_body_size', KB_IN_BYTES); // Filters the maximum error response body size in `download_url()`.
                            $response->tmpf = fread($tmpf, $response_size);
                            fclose($tmpf);
                        }
                        unlink($response->filename);
                    }
                    $response->wp_error = self::error($response->message, $raw_response);
                }
                if($response->json){
                    $json_params = self::json_decode($response->body, true);
                    if(is_wp_error($json_params)){
                        $response->message = $json_params->get_error_message();
                        if($response->success){
    						$response->success = false;
                            $response->status = $response->success; // Backward compatibility.
                            $response->wp_error = $json_params;
                        } else {
                            $response->wp_error->merge_from($json_params);
                        }
                    } else {
                        $response->json_params = $json_params;
                        $maybe_error = self::is_json_wp_die_handler($json_params);
                        if(is_wp_error($maybe_error)){
                            $response->message = $maybe_error->get_error_message();
                            if($response->success){
    							$response->success = false;
    	                        $response->status = $response->success; // Backward compatibility.
                                $response->wp_error = $maybe_error;
                            } else {
                                $response->wp_error->merge_from($maybe_error);
                            }
                        }
                    }
                }
            } else {
                $response->message = __('Invalid data provided.');
                $response->wp_error = self::errorerror($response->message, $raw_response);
            }
            return $response;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function sanitize_remote_request_method($method = ''){
            $method = strtoupper($method);
            if(!in_array($method, ['DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'])){
                $method = 'GET'; // Default.
            }
            return $method;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return int
    	 */
        public static function sanitize_remote_request_timeout($timeout = 0){
    		$timeout = (int) $timeout;
    		if($timeout < 0){
    			$timeout = 0; // Timeout cannot be negative.
    		}
            if(function_exists('ini_get')){ // Some hosts do not allow you to read configuration values.
                $max_execution_time = (int) ini_get('max_execution_time');
                if($max_execution_time > 0){ // The max_execution_time defaults to 0 when PHP runs from cli.
                    $max_execution_time -= 2; // Reduce it a bit to prevent edge-case timeouts that may happen after the remote request has finished running.
                    if($timeout === 0 || $timeout > $max_execution_time){
                        $timeout = $max_execution_time;
                    }
                }
            }
    		if(self::is_cf_enabled()){ // The Cloudflare’s proxy read timeout is 100 seconds. TODO: Check for Cloudflare Enterprise. See: https://developers.cloudflare.com/support/troubleshooting/http-status-codes/cloudflare-5xx-errors/error-524/
                $max_execution_time = 98; // Reduce it a bit to prevent edge-case timeouts that may happen after the remote request has finished running.
    			if($timeout === 0 || $timeout > $max_execution_time){
    				$timeout = $max_execution_time;
    			}
    		}
    		return $timeout;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array
    	 */
        public static function sanitize_remote_request_args($args = [], $url = ''){
            if(!is_array($args)){
                $args = wp_parse_args($args);
            }
            if(!$args){
                return [];
            }
    		if(!self::is_remote_request($args)){
    			return [
    				'body' => $args,
    			];
    		}
            if(isset($args['method'])){
                $args['method'] = self::sanitize_remote_request_method($args['method']);
            }
            if(isset($args['timeout'])){
    			$args['timeout'] = self::sanitize_remote_request_timeout($args['timeout']);
    		}
    		if(!isset($args['cookies']) && wp_validate_redirect($url)){
                $args['cookies'] = $_COOKIE;
    		}
    		if(!isset($args['user-agent'])){
                $args['user-agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36'; // https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/User-Agent#chrome_ua_string
    		}
    		if(isset($args['body']) && self::is_json_content_type($args) && !is_scalar($args['body'])){
    			$args['body'] = wp_json_encode($args['body']);
    		}
    		return $args;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Rewrite
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'generate_rewrite_rules' action hook.
    	 *
    	 * @return void
    	 */
        public static function _maybe_add_external_rule($rule = []){
    		global $wp_rewrite;
    		if(!doing_action('generate_rewrite_rules')){
    	        return; // Too early or too late.
    	    }
    		if(!self::is_external_rule($rule)){
    			return;
    		}
    		if($rule['plugin_file'] && self::is_plugin_deactivating($rule['plugin_file'])){
    			return;
    		}
    		$wp_rewrite->add_external_rule($rule['regex'], $rule['query']);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'generate_rewrite_rules' action hook.
    	 *
    	 * @return void
    	 */
        public static function _maybe_add_external_rules($wp_rewrite){
    		if(!doing_action('generate_rewrite_rules')){
    	        return; // Too early or too late.
    	    }
            $external_rules = self::cache_all('external_rules');
            if(!$external_rules){
                return;
            }
    	    foreach($external_rules as $external_rule){
    			self::maybe_add_external_rule($external_rule);
    	    }
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * This function MUST be called inside the 'admin_notices' action hook.
    	 *
    	 * @return void
    	 */
        public static function _maybe_add_external_rules_notice(){
    		if(!doing_action('admin_notices')){
    	        return; // Too early or too late.
    	    }
    		if(!current_user_can('manage_options')){
    			return;
    		}
            $htaccess = get_home_path() . '.htaccess';
            if(!file_exists($htaccess)){
                $error = self::missing_file($htaccess);
                self::add_admin_notice($error->get_error_message());
                return;
            }
            $external_rules = self::cache_all('external_rules');
            if(!$external_rules){
                return;
            }
            $add_admin_notice = false;
    		foreach($external_rules as $external_rule){
    			if(!self::external_rule_exists($external_rule['regex'], $external_rule['query'])){
    				$add_admin_notice = true;
    				break;
    			}
    		}
    		if(!$add_admin_notice){
    	        return;
    		}
            if(!apache_mod_loaded('mod_rewrite')){
                self::add_admin_notice(sprintf(__('It looks like the Apache %s module is not installed.'), '<code>mod_rewrite</code>'));
                return;
            }
    	    self::add_admin_notice(sprintf(__('You should update your %s file now.'), '<code>.htaccess</code>') . ' ' . sprintf('<a href="%s">%s</a>', esc_url(admin_url('options-permalink.php')), __('Flush permalinks')) . '.');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function add_external_rule($regex = '', $query = '', $plugin_file = ''){
    		$rule = [
    			'plugin_file' => $plugin_file,
    			'query' => str_replace(site_url('/'), '', $query),
    			'regex' => str_replace(site_url('/'), '', $regex),
    		];
    		if(doing_action('generate_rewrite_rules')){
    			self::add_action_once('admin_notices', [__CLASS__, '_maybe_add_external_rules_notice']);
                self::maybe_add_external_rule($rule);
    			return; // Just in time.
    		}
    		if(did_action('generate_rewrite_rules')){
    			return; // Too late.
    		}
            $md5 = self::md5($rule);
            self::add_action_once('admin_notices', [__CLASS__, '_maybe_add_external_rules_notice']);
            self::add_action_once('generate_rewrite_rules', [__CLASS__, '_maybe_add_external_rules']);
            self::cache_set($md5, $rule, 'external_rules');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function external_rule_exists($regex = '', $query = ''){
    		$regex = str_replace('.+?', '.+', $regex); // Apache 1.3 does not support the reluctant (non-greedy) modifier.
    		$rewrite_rules = self::get_rewrite_rules();
    		$rule = 'RewriteRule ^' . $regex . ' ' . self::home_root() . $query . ' [QSA,L]';
    		return in_array($rule, $rewrite_rules);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return array
    	 */
        public static function get_rewrite_rules(){
            $key = 'rewrite_rules';
            if(self::cache_exists($key)){
                return self::cache_get($key);
            }
    		$rewrite_rules = array_filter(extract_from_markers(get_home_path() . '.htaccess', 'WordPress'));
    		self::cache_set($key, $rewrite_rules);
    		return $rewrite_rules;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function home_root(){
    		$home_root = parse_url(home_url());
    		if(isset($home_root['path'])){
    			$home_root = trailingslashit($home_root['path']);
    		} else {
    			$home_root = '/';
    		}
    		return $home_root;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function is_apache(){
            global $is_apache;
            return $is_apache;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_external_rule($rule = []){
    	    return self::array_keys_exist(['plugin_file', 'query', 'regex'], $rule);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // PHP Simple HTML DOM Parser
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return simple_html_dom|WP_Error
    	 */
        public static function file_get_html(...$args){
    		$dir = self::use_simple_html_dom();
    		if(is_wp_error($dir)){
    			return $dir;
    		}
    		return file_get_html(...$args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function simple_html_dom_ver($set = ''){
            static $ver = '1.9.1';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return simple_html_dom|WP_Error
    	 */
        public static function str_get_html(...$args){
    		$dir = self::use_simple_html_dom();
    		if(is_wp_error($dir)){
    			return $dir;
    		}
    		return str_get_html(...$args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool|WP_Error
    	 */
        public static function use_simple_html_dom(){
            $ver = self::simple_html_dom_ver();
            $url = 'https://github.com/simplehtmldom/simplehtmldom/archive/refs/tags/' . $ver . '.zip';
            return self::use([
                'autoload' => 'simple_html_dom.php',
    			'expected_dir' => 'simplehtmldom-' . $ver,
                'validation_class' => 'simple_html_dom',
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Slick
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function enqueue_slick($deps = []){
            if(self::scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = self::use_slick();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $base_path = self::path_to_url($dir) . '/slick';
            $ver = self::slick_ver();
            self::enqueue_dependency('slick', $base_path . '/slick.css', $deps, $ver);
            self::enqueue_dependency('slick-theme', $base_path . '/slick-theme.css', ['slick'], $ver);
            self::enqueue_dependency('slick', $base_path . '/slick.min.js', $deps, $ver);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return void
         */
        public static function slick_ver($set = ''){
            static $ver = '1.8.1';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function use_slick(){
            $ver = self::slick_ver();
            $url = 'https://github.com/kenwheeler/slick/archive/refs/tags/v' . $ver . '.zip';
            return self::use([
    			'expected_dir' => 'slick-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Strings
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function base64_urldecode($data = '', $strict = false){
    		return base64_decode(strtr($data, '-_', '+/'), $strict);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function base64_urlencode($data = ''){
    		return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function canonicalize($str = ''){
			$str = str_replace('\\', '_', $str); // Fix namespaces.
			$str = sanitize_title($str);
			$str = str_replace('-', '_', $str); // Fix slugified.
			return trim($str, '_');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function first_p($text = '', $dot = true){
    		return self::one_p($text, $dot, 'first');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function implode($array = [], $separator = ''){
            if(!$separator){
                $separator = wp_get_list_item_separator();
            }
    		return implode($separator, $array);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function implode_and($array = [], $last = ''){
            if(!$last){
                $last = trim(sprintf(__('%1$s and %2$s'), '', ''));
            }
    		return self::implode_last($array, $last);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function implode_last($array = [], $last = '', $separator = ''){
    		if(!$array || !is_array($array)){
    			return '';
    		}
            if(count($array) === 1){
    			return $array[0];
    		}
            if(!$last){
                return self::implode($array, $separator);
            }
    		$last_item = array_pop($array);
    		return self::implode($array, $separator) . ' ' . $last . ' ' . $last_item;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function implode_or($array = [], $last = ''){
            if(!$last){
                $last = trim(sprintf(__('%1$s or %2$s'), '', ''));
            }
    		return self::implode_last($array, $last);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function last_p($text = '', $dot = true){
    		return self::one_p($text, $dot, 'last');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function one_p($text = '', $dot = true, $p = 'first'){
            $text = sanitize_text_field($text);
            $matches = preg_split('/[\.\?!]+/', $text, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_OFFSET_CAPTURE);
            switch($p){
                case 'first':
                    $match = array_shift($matches);
                    break;
                case 'last':
                    $match = array_pop($matches);
                    break;
                default:
                    $p = absint($p);
                    if(count($matches) >= $p){
                        $p --;
                        $match = $matches[$p];
                    } else {
                        $error_msg = __('Error');
                        if($dot){
                            $error_msg .= '.';
                        }
                        return $error_msg;
                    }
            }
            $one = trim($match[0]);
            if(!$dot){
                return $one;
            }
            $dot_chr = substr($text, strlen($match[0]) + $match[1], 1);
            if(!$dot_chr){
                $dot_chr = '.';
            }
            $one .= $dot_chr;
            return $one;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function prepare($str = '', ...$args){
    		global $wpdb;
    		if(!$args){
    			return $str;
    		}
    		if(strpos($str, '%') === false){
    			return $str;
    		}
    		return str_replace("'", '', $wpdb->remove_placeholder_escape($wpdb->prepare($str, ...$args)));
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function remove_whitespaces($str = ''){
    		return trim(preg_replace('/[\r\n\t\s]+/', ' ', $str));
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function slugify($str = ''){
			$str = str_replace('\\', '-', $str); // Fix namespaces.
			$str = sanitize_title($str);
			$str = str_replace('_', '-', $str); // Fix canonicalized.
			return trim($str, '-');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * Polyfill for `str_ends_with()` function added in PHP 8.0 and WordPress 5.9.
         *
    	 * @return bool
    	 */
        public static function str_ends_with($haystack = '', $needle = ''){
            if(function_exists('str_ends_with')){
                return str_ends_with($haystack, $needle);
            }
    		if($haystack === ''){
    			return $needle === '';
    		}
            $len = strlen($needle);
            return substr($haystack, -$len, $len) === $needle;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function str_split($str = '', $line_length = 55){
    		$str = sanitize_text_field($str);
    		$lines = ceil(strlen($str) / $line_length);
    		$words = explode(' ', $str);
    		if(count($words) <= $lines){
    			return $words;
    		}
    		$length = 0;
    		$index = 0;
    		$oputput = [];
    		foreach($words as $word){
    			$word_length = strlen($word);
    			if((($length + $word_length) <= $line_length) || empty($oputput[$index])){
    				$oputput[$index][] = $word;
    				$length += ($word_length + 1);
    			} else {
    				if($index < ($lines - 1)){
    					$index ++;
    				}
    				$length = $word_length;
    				$oputput[$index][] = $word;
    			}
    		}
    		foreach($oputput as $index => $words){
    			$oputput[$index] = implode(' ', $words);
    		}
    		return $oputput;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function str_split_lines($str = '', $lines = 2){
    		$str = sanitize_text_field($str);
    		$words = explode(' ', $str);
    		if(count($words) <= $lines){
    			return $words;
    		}
    		$line_length = ceil(strlen($str) / $lines);
    		$length = 0;
    		$index = 0;
    		$oputput = [];
    		foreach($words as $word){
    			$word_length = strlen($word);
    			if((($length + $word_length) <= $line_length) || empty($oputput[$index])){
    				$oputput[$index][] = $word;
    				$length += ($word_length + 1);
    			} else {
    				if($index < ($lines - 1)){
    					$index ++;
    				}
    				$length = $word_length;
    				$oputput[$index][] = $word;
    			}
    		}
    		foreach($oputput as $index => $words){
    			$oputput[$index] = implode(' ', $words);
    		}
    		return $oputput;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * Polyfill for `str_starts_with()` function added in PHP 8.0 and WordPress 5.9.
         *
    	 * @return bool
    	 */
        public static function str_starts_with($haystack = '', $needle = ''){
            if(function_exists('str_starts_with')){
                return str_starts_with($haystack, $needle);
            }
    		if($needle === ''){
    			return true;
    		}
    		return strpos($haystack, $needle) === 0;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function trailingdotit($text = ''){
            $text = sanitize_textarea_field($text);
    		return self::str_ends_with($text, '.') ? $text : $text . '.';
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function unslashit($value = ''){
            return trim($value, '/\\');
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // TGM Plugin Activation
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
    	 * @return bool|WP_Error
    	 */
        public static function _use_tgm_plugin_activation(){
            $ver = self::tgmpa_ver();
            $url = 'https://github.com/TGMPA/TGM-Plugin-Activation/archive/refs/tags/' . $ver . '.zip';
            return self::use([
                'autoload' => 'class-tgm-plugin-activation.php',
    			'expected_dir' => 'TGM-Plugin-Activation-' . $ver,
                'validation_function' => 'tgmpa',
                'zipball_url' => $url,
            ]);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * This function MUST be called inside the 'tgmpa_register' action hook.
         *
    	 * @return void
    	 */
        public static function tgmpa($plugins = [], $config = []){
    		if(!doing_action('tgmpa_register')){
    			return; // Too early or too late.
    		}
            $dir = self::use_tgm_plugin_activation();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
    		tgmpa($plugins, $config);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function tgmpa_register($plugins = [], $config = []){
            $dir = self::use_tgm_plugin_activation();
    		if(is_wp_error($dir)){
    			return; // Silence is golden.
    		}
    		if(doing_action('tgmpa_register')){
    			self::tgmpa($plugins, $config);
    			return; // Just in time.
    		}
    		if(did_action('tgmpa_register')){
    			return; // Too late.
    		}
    		$tgmpa = [
    			'config' => $config,
    			'plugins' => $plugins,
    		];
            $group = 'tgmpa';
    		$md5 = self::md5($tgmpa);
    		self::add_action_once('tgmpa_register', [__CLASS__, '_maybe_tgmpa_register']);
    		self::cache_set($md5, $tgmpa, $group);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return void
    	 */
        public static function tgmpa_register_plugin($plugin = []){
    		$dir = self::use_tgm_plugin_activation();
    		if(is_wp_error($dir)){
    			return; // Silence is golden.
    		}
    		$group = 'tgmpa_plugins';
    		$md5 = self::md5($plugin);
    		self::add_action_once('tgmpa_register', [__CLASS__, '_maybe_tgmpa_register']);
    		self::cache_set($md5, $plugin, $group);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function tgmpa_ver($set = ''){
            static $ver = '2.6.1';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Themes
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function theme_is($name = ''){
            $group = 'theme_is';
            if(self::cache_exists($name, $group)){
                return self::cache_get($name, $group);
            }
        	$current_theme = wp_get_theme();
    		$theme_is = $name === $current_theme->get('Name');
    		self::cache_set($name, $theme_is, $group);
        	return $theme_is;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function theme_is_child_of($template = ''){
            $group = 'theme_is_child_of';
            if(self::cache_exists($template, $group)){
                return self::cache_get($template, $group);
            }
    		$current_theme = wp_get_theme();
    		$theme_is_child_of = $template === $current_theme->get('Template');
            self::cache_set($template, $theme_is_child_of, $group);
        	return $theme_is_child_of;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Uploads
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * This function works only if the file was uploaded via HTTP POST.
    	 *
    	 * @return array|WP_Error
    	 */
        public static function handle_file($file = [], $dir = '', $mimes = null){
    		if(!$file){
                $error_msg = __('No data supplied.');
    			return self::error($error_msg);
    		}
    		if(!is_array($file)){
    			if(!is_scalar($file)){
                    $error_msg = __('Invalid data provided.');
    				return self::error($error_msg);
    			}
    			if(!isset($_FILES[$file])){
    				return self::missing_file();
    			}
    			$file = $_FILES[$file];
    		}
    		$keys = ['error', 'name', 'size', 'tmp_name', 'type'];
    		foreach($keys as $key){
    			$file[$key] = isset($file[$key]) ? (array) $file[$key] : [];
    		}
    		$count = count($file['tmp_name']);
    		$files = [];
    		for($i = 0; $i < $count; $i ++){
    			$files[$i] = [];
    			foreach($keys as $key){
    				if(isset($file[$key][$i])){
    					$files[$i][$key] = $file[$key][$i];
    				}
    			}
    		}
    		$uploaded_files = [];
    		foreach($files as $index => $file){
    			$uploaded_files[$index] = self::handle_upload($file, $dir, $mimes);
    		}
    		return $uploaded_files;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * This function works only if the files were uploaded via HTTP POST.
    	 *
    	 * @return array|WP_Error
    	 */
        public static function handle_files($files = [], $dir = '', $mimes = null){
    		if(!$files){
    			if(!$_FILES){
                    $error_msg = __('No data supplied.');
    				return self::error($error_msg);
    			}
    			$files = $_FILES;
    		}
    		$uploaded_files = [];
    		foreach($files as $key => $file){
    			$uploaded_files[$key] = self::handle_file($file, $dir, $mimes);
    		}
    		return $uploaded_files;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string|WP_Error
    	 */
        public static function handle_upload($file = [], $dir = '', $mimes = null){
    	    $dir = self::check_dir($dir);
    	    if(is_wp_error($dir)){
    	        return $dir;
    	    }
    		if(!$file){
                $error_msg = __('No data supplied.');
    			return self::error($error_msg);
    		}
    		$file = shortcode_atts([
    			'error' => 0,
    			'name' => '',
    			'size' => 0,
    			'tmp_name' => '',
    			'type' => '',
    		], $file);
    		$uploaded_file = self::test_uploaded_file($file['tmp_name']);
    		if(is_wp_error($uploaded_file)){
    			return $uploaded_file;
    		}
    		$error = self::test_error($file['error']);
    		if(is_wp_error($error)){
    			return $error;
    		}
    		$size = self::test_size($file['size']);
    		if(is_wp_error($size)){
    			return $size;
    		}
    		$filename = self::test_type($file['tmp_name'], $file['name'], $mimes);
    		if(is_wp_error($filename)){
    			return $filename;
    		}
    		$size_check = self::check_upload_size($file['size']);
    		if(is_wp_error($size_check)){
    			return $size_check;
    		}
            if($dir){
                if(!self::is_path_in_uploads_dir($dir)){
                    $error_msg = __('Unable to locate needed folder (%s).');
                    $error_msg = sprintf($error_msg, __('The uploads directory'));
                    return self::error($error_msg);
                }
            } else {
                $upload_dir = wp_upload_dir();
    			if($upload_dir['error']){
    				return self::error($upload_dir['error']);
    			}
    			$dir = $upload_dir['path'];
            }
    		$filename = wp_unique_filename($dir, $filename);
    		$new_file = path_join($dir, $filename);
    		$move_new_file = @move_uploaded_file($file['tmp_name'], $new_file);
    		if($move_new_file === false){
                $error_msg = __('The uploaded file could not be moved to %s.');
                $error_msg = sprintf($error_msg, str_replace(ABSPATH, '', $dir));
    			return self::errorerror($error_msg);
    		}
    		$stat = stat(dirname($new_file));
    		$perms = $stat['mode'] & 0000666;
    		chmod($new_file, $perms); // Set correct file permissions.
    		if(is_multisite()){
    			clean_dirsize_cache($new_file);
    		}
    		return $new_file;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // UUID
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return bool
    	 */
        public static function is_uuid($string = ''){
            return is_string($string) && preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $string) === 1;
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return string
    	 */
        public static function uuid($data = ''){
    		return self::is_uuid($data) ? $data : self::md5_to_uuid(self::md5($data));
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Wordfence
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return array
         */
        public static function get_wf_code_execution_protection_rules(){
            $upload_dir = wp_get_upload_dir();
    		if($upload_dir['error']){
    			return [];
    		}
            $basedir = $upload_dir['basedir'];
            $htaccess = path_join($basedir . '.htaccess');
            if(!function_exists('extract_from_markers')){
                require_once ABSPATH . 'wp-admin/includes/misc.php';
            }
            $result = extract_from_markers($htaccess, 'Wordfence code execution protection');
            return array_values(array_filter($result));
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return array
         */
        public static function get_wf_countries($preferred_countries = []){
            $wf_countries = self::wf_bulk_countries();
            if(!$preferred_countries){
                return $wf_countries;
            }
            $countries = [];
            foreach($preferred_countries as $iso2){
                if(!isset($wf_countries[$iso2])){
                    continue;
                }
                $countries[$iso2] = $wf_countries[$iso2];
                unset($wf_countries[$iso2]);
            }
            return array_merge($countries, $wf_countries);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function get_wf_country($ip = ''){
            if($ip && !\WP_Http::is_ip_address($ip)){
                return '';
            }
            $ip = $ip ? $ip : self::get_remote_ip();
            return is_callable(['wfUtils', 'IP2Country']) ? \wfUtils::IP2Country($ip) : '';
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function get_wf_ip(){
            return is_callable(['wfUtils', 'getIP']) ? \wfUtils::getIP() : '';
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return bool
         */
        public static function is_wf_code_execution_protection_enabled(){
            $disable_code_execution_uploads = is_callable(['wfConfig', 'get']) ? \wfConfig::get('disableCodeExecutionUploads') : false;
            if(!$disableCodeExecutionUploads){
                return false;
            }
            $rules = self::get_wf_code_execution_protection_rules();
            return $rules ? true : false;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return array
         */
        public static function wf_bulk_countries(){
            if(!defined('WORDFENCE_PATH')){
                return [];
            }
            $file = WORDFENCE_PATH . 'lib/wfBulkCountries.php';
            if(!file_exists($file)){
                return [];
            }
            include $file; /** @var array $wfBulkCountries */
            if(!isset($wfBulkCountries) || !is_array($wfBulkCountries)){
                return [];
            }
            asort($wfBulkCountries);
            return $wfBulkCountries;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // Zoom
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return string|WP_Error
         */
        public static function _zoom_access_token(){
            $key = 'zoom_access_token';
            if(self::cache_exists($key)){
                return self::cache_get($key);
            }
            $app_credentials = self::zoom_app_credentials();
            if(is_wp_error($app_credentials)){
                return $app_credentials;
            }
            $authorization = base64_encode($app_credentials['client_id'] . ':' . $app_credentials['client_secret']);
            $url = 'https://zoom.us/oauth/token';
            $args = [
                'body' => [
                    'account_id' => $app_credentials['account_id'],
                    'grant_type' => 'account_credentials',
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . $authorization,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'timeout' => 10,
            ];
            $response = self::remote_post($url, $args);
            if(!$response->success){
                return $response->wp_error;
            }
            if(!isset($response->json_params['access_token'])){
                $error = self::missing_params(['access_token']);
                self::cache_set($key, $error);
                return $error;
            }
            $access_token = $response->json_params['access_token'];
            if(!$access_token){
                $error = self::invalid_params(['access_token']);
                self::cache_set($key, $error);
                return $error;
            }
            self::cache_set($key, $access_token);
            return $access_token;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * WARNING: This function’s access is marked private.
         *
         * @return string|WP_Error
         */
        public static function _zoom_oauth_token(){
            $transient = 'zoom_oauth_token';
            $oauth_token = get_transient($transient);
            if($oauth_token){
                return $oauth_token;
            }
            $oauth_token = self::_zoom_access_token();
            if(is_wp_error($oauth_token)){
                return $oauth_token;
            }
            $expiration = 59 * MINUTE_IN_SECONDS; // The token’s time to live is 1 hour. Reduce it a bit to prevent edge-case timeouts that may happen before the page is fully loaded. See: https://developers.zoom.us/docs/internal-apps/s2s-oauth/
            set_transient($transient, $oauth_token, $expiration);
            return $oauth_token;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
    	 * @return stdClass
    	 */
        public static function zoom_async_request($method = '', $endpoint = '', $body = []){
            $oauth_token = self::_zoom_oauth_token();
            if(is_wp_error($oauth_token)){
                return $oauth_token;
            }
            $url = self::zoom_api_url($endpoint);
            if(!is_array($body)){
                $body = wp_parse_args($body);
            }
            $args = [
                'body' => $body,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $oauth_token,
                    'Content-Type' => 'application/json',
                ],
            ];
            return self::async_request($method, $url, $args);
    	}

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return string
         */
        public static function zoom_api_url($endpoint = ''){
            $base = 'https://api.zoom.us/v2';
            if(self::str_starts_with($endpoint, $base)){
                $endpoint = str_replace($base, '', $endpoint);
            }
            return self::path_join($base, $endpoint);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return array|WP_Error
         */
        public static function zoom_app_credentials($app_credentials = []){
            $key = 'zoom_app_credentials';
            if(self::cache_exists($key)){
                return self::cache_get($key);
            }
            $app_credentials = shortcode_atts([
                'account_id' => '',
                'client_id' => '',
                'client_secret' => '',
            ], $app_credentials);
            $missing = [];
            if(!$app_credentials['account_id']){
                $missing[] = 'Account ID';
            }
            if(!$app_credentials['client_id']){
                $missing[] = 'Client ID';
            }
            if(!$app_credentials['client_secret']){
                $missing[] = 'Client Secret';
            }
            if($missing){
                $error = self::missing_params($missing);
                self::cache_set($key, $error);
                return $error;
            }
            self::cache_set($key, $app_credentials);
            return $app_credentials;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object
         */
        public static function zoom_delete($endpoint = '', $body = [], $timeout = 10){
            return self::zoom_request('delete', $endpoint, $body, $timeout);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object
         */
        public static function zoom_get($endpoint = '', $body = [], $timeout = 10){
            return self::zoom_request('get', $endpoint, $body, $timeout);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object
         */
        public static function zoom_patch($endpoint = '', $body = [], $timeout = 10){
            return self::zoom_request('patch', $endpoint, $body, $timeout);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object
         */
        public static function zoom_post($endpoint = '', $body = [], $timeout = 10){
            return self::zoom_request('post', $endpoint, $body, $timeout);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object
         */
        public static function zoom_put($endpoint = '', $body = [], $timeout = 10){
            return self::zoom_request('put', $endpoint, $body, $timeout);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        /**
         * @return object
         */
        public static function zoom_request($method = '', $endpoint = '', $body = [], $timeout = 10){
            $oauth_token = self::_zoom_oauth_token();
            if(is_wp_error($oauth_token)){
                return $oauth_token;
            }
            $url = self::zoom_api_url($endpoint);
            if(!is_array($body)){
                $body = wp_parse_args($body);
            }
            $args = [
                'body' => $body,
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $oauth_token,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => $timeout,
            ];
            return self::remote_request($method, $url, $args);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	}
}
