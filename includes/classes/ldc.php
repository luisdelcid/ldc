<?php

if(!class_exists('LDC') && class_exists('LDC_Singleton')){
	class LDC extends LDC_Singleton {

		// ---------------------------------------------------------------------
        //
		// Protected
        //
		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        protected function __construct(){
			// Silence is golden.
		}

		// ---------------------------------------------------------------------
        //
		// Public static
        //
		// ---------------------------------------------------------------------

        /**
		 * Overrides the parent method. Called with no class, it returns the one
		 * LDC instance, the same one ldc() returns. Called with a class, it returns
		 * that class' instance: an LDC_Multiton subclass gets the arguments that
		 * follow, an LDC_Singleton subclass none.
		 * E.g. ldc()->get_instance('LDC_Cache', $group);
		 *
		 * Both jobs share this method because LDC_Singleton declares get_instance()
		 * static, and PHP does not let a subclass turn it into an instance method.
		 * Keeping them together is also what makes ldc()->get_instance($class)
		 * do what its name says, instead of quietly returning LDC itself.
		 *
		 * NOTE: The signature must stay variadic to remain compatible with
		 * LDC_Singleton::get_instance().
		 *
		 * @return LDC|LDC_Multiton|LDC_Singleton|WP_Error
		 */
        public static function get_instance(...$args){
            if(!$args){
                return parent::get_instance(); // This class.
            }
            $ldc = parent::get_instance();
            $class = array_shift($args);
		    if(!$class){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'class');
                return $ldc->error($error_msg);
		    }
            if(!class_exists($class)){
                return $ldc->missing_params($class);
            }
            if(is_subclass_of($class, 'LDC_Multiton')){ // Hardcoded.
                return call_user_func_array([$class, 'get_instance'], $args);
            }
            if(is_subclass_of($class, 'LDC_Singleton')){ // Hardcoded.
                return call_user_func([$class, 'get_instance']);
            }
            return $ldc->invalid_params($class);
        }

		// ---------------------------------------------------------------------
        //
		// Ace (Ajax.org Cloud9 Editor)
        //
		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function ace_version($set = ''){
            static $ver = '1.43.6';
            if($set === ''){
                return $ver; // Get.
            }
            $ver = $set;
            return $ver; // Set.
        }

		// ---------------------------------------------------------------------

        /**
         * This method MUST be called inside or after the 'admin_enqueue_scripts', 'login_enqueue_scripts' or 'wp_enqueue_scripts' action hooks.
         *
         * @return string|WP_Error
         */
        public function enqueue_ace($deps = []){
            $ver = $this->ace_version();
            $src = 'https://cdn.jsdelivr.net/npm/ace-builds@' . $ver . '/css/ace.min.css';
            $handle = $this->enqueue('ace', $src, [], $ver);
            if(is_wp_error($handle)){
                return $handle;
            }
            $base_path = 'https://cdn.jsdelivr.net/npm/ace-builds@' . $ver . '/src-min-noconflict';
            $src = $base_path . '/ace.min.js';
            $handle = $this->enqueue($handle, $src, $deps, $ver);
            if(is_wp_error($handle)){
                return $handle;
            }
            $src = $base_path . '/ext-language_tools.js';
            $handle = $this->enqueue('ace-language-tools', $src, [$handle], $ver);
            if(is_wp_error($handle)){
                return $handle;
            }
            $data = "_.isUndefined(ace)||(ace.config.set('basePath','$base_path'),ace.require('ace/ext/language_tools'))";
            wp_add_inline_script($handle, $data);
            return $handle;
        }

		// ---------------------------------------------------------------------

        /**
         * This method MUST be called inside or after the 'admin_enqueue_scripts', 'login_enqueue_scripts' or 'wp_enqueue_scripts' action hooks.
         *
         * @return string|WP_Error
         */
        public function load_ace($deps = []){
            $dir = $this->use_ace();
            if(is_wp_error($dir)){
                return $dir;
            }
            $path = $this->path_join($dir , 'css');
            $src = $this->path_to_url($path);
            $ver = $this->ace_version();
            $handle = $this->enqueue('ace', $src . '/ace.css', [], $ver);
            if(is_wp_error($handle)){
                return $handle;
            }
            $path = $this->path_join($dir , 'src-min-noconflict');
            $src = $this->path_to_url($path);
            $handle = $this->enqueue('ace', $src . '/ace.js', $deps, $ver);
            if(is_wp_error($handle)){
                return $handle;
            }
            $handle = $this->enqueue('ace-language-tools', $src . '/ext-language_tools.js', [$handle], $ver);
            if(is_wp_error($handle)){
                return $handle;
            }
            $data = "_.isUndefined(ace)||(ace.config.set('basePath','$src'),ace.require('ace/ext/language_tools'))";
            wp_add_inline_script($handle, $data);
            return $handle;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function use_ace(){
            $ver = $this->ace_version();
            $url = 'https://github.com/ajaxorg/ace-builds/archive/refs/tags/v' . $ver . '.zip';
            return $this->use([
                'expected_dir' => 'ace-builds-' . $ver,
                'zipball_url' => $url,
            ]);
        }

		// ---------------------------------------------------------------------
        //
		// Admin notices
        //
		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function add_admin_notice($message = '', $type = '', $is_dismissible = false){
        	if(doing_action('admin_notices')){
                $this->admin_notice($message, $type, $is_dismissible);
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
        	$this->add_action_once('admin_notices', [$this, '_maybe_add_admin_notices']); // Priority will always be 10.
            $group = 'admin_notices';
            $key = $this->md5($admin_notice);
            $this->cache($group)->set($key, $admin_notice);
        }

		// ---------------------------------------------------------------------

        /**
         * This method MUST be called inside the 'admin_notices' action hook.
         *
         * @return void
         */
        public function admin_notice($message = '', $type = '', $is_dismissible = false){
            if(!doing_action('admin_notices')){
                return; // Too early or too late.
            }
            echo $this->get_admin_notice($message, $type, $is_dismissible);
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function get_admin_notice($message = '', $type = '', $is_dismissible = false){
            if(is_wp_error($message)){
                $message = $message->get_error_message();
                if($type === ''){
                    $type = 'error';
                }
            }
            if(!in_array($type, ['error', 'info', 'success', 'warning'])){
                $type = '';
            }
            if(is_wp_version_compatible('6.4')){
                $args = [
                    'dismissible' => $is_dismissible,
                    'type' => $type,
                ];
                return wp_get_admin_notice($message, $args);
            }
            if($type === ''){
                $type = 'warning'; // Backward compatibility.
            }
            if($is_dismissible){
                $type .= ' is-dismissible';
            }
            return '<div class="notice notice-' . $type . '"><p>' . $message . '</p></div>';
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * This method MUST be called inside the 'admin_notices' action hook.
         *
         * @return void
         */
        public function _maybe_add_admin_notices(){
        	if(!doing_action('admin_notices')){
                return; // Too early or too late.
            }
            $group = 'admin_notices';
            $admin_notices = $this->cache($group)->get_group();
        	foreach($admin_notices as $admin_notice){
        		$this->admin_notice($admin_notice['message'], $admin_notice['type'], $admin_notice['is_dismissible']);
        	}
        }

		// ---------------------------------------------------------------------
        //
		// Animate.css
        //
		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function animate_css_version($set = ''){
            static $ver = '4.1.1';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
        }

		// ---------------------------------------------------------------------

        /**
         * This method MUST be called inside or after the 'admin_enqueue_scripts', 'login_enqueue_scripts' or 'wp_enqueue_scripts' action hooks.
         *
         * @return string|WP_Error
         */
        public function enqueue_animate_css($deps = []){
            $ver = $this->animate_css_version();
            $src = 'https://cdn.jsdelivr.net/npm/animate.css@' . $ver . '/animate.min.css';
            return $this->enqueue('animate.css', $src, $deps, $ver);
        }

		// ---------------------------------------------------------------------

        /**
         * This method MUST be called inside or after the 'admin_enqueue_scripts', 'login_enqueue_scripts' or 'wp_enqueue_scripts' action hooks.
         *
         * @return string|WP_Error
         */
        public function load_animate_css($deps = []){
            $dir = $this->use_animate_css();
            if(is_wp_error($dir)){
                return $dir;
            }
            $path = $this->path_join($dir , 'animate.min.css');
            $src = $this->path_to_url($path);
            $ver = $this->animate_css_version();
            return $this->enqueue('animate-css', $src, $deps, $ver);
        }

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function use_animate_css(){
            $ver = $this->animate_css_version();
            $url = 'https://github.com/animate-css/animate.css/archive/refs/tags/v' . $ver . '.zip';
            return $this->use([
                'expected_dir' => 'animate.css-' . $ver,
                'zipball_url' => $url,
            ]);
        }

		// ---------------------------------------------------------------------
        //
		// Arrays
        //
		// ---------------------------------------------------------------------

        /**
    	 * Determines if the variable is an associative array.
    	 *
         * @return bool
         */
        public function array_is_assoc($array = []){
            if(!is_array($array) || !$array){
                return false;
            }
            if(function_exists('array_is_list')){
                return !array_is_list($array);
            }
            return array_keys($array) !== range(0, count($array) - 1);
        }

		// ---------------------------------------------------------------------

        /**
    	 * Determines if the variable is a numeric-indexed array.
    	 *
         * @return bool
         */
        public function array_is_list($array = []){
            if(!is_array($array) || !$array){
                return false;
            }
            if(function_exists('array_is_list')){
                return array_is_list($array);
            }
            return array_keys($array) === range(0, count($array) - 1);
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function array_keys_exist($keys = [], $array = []){
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

		// ---------------------------------------------------------------------

        /**
         * @return array
         */
        public function array_ksort_recursive($array = []){
            if(!is_array($array) || !$array){
                return [];
            }
            if(!$this->array_is_assoc($array)){
                return $array;
            }
            foreach($array as $key => $value){
                if(!$this->array_is_assoc($value)){
                    continue;
                }
                $array[$key] = $this->array_ksort_recursive($value); // Recursive.
            }
        	ksort($array);
            return $array;
        }

		// ---------------------------------------------------------------------

        /**
         * @return array
         */
        public function array_make_assoc($input_list = [], $index_key = null, $field = null){
            if(!is_array($input_list) || !$input_list){
                return false;
            }
            if(is_null($index_key)){
                return is_null($field) ? $input_list : wp_list_pluck($input_list, $field);
            }
            if(!is_null($field)){
                return wp_list_pluck($input_list, $field, $index_key);
            }
            $newlist = [];
            foreach($input_list as $value){
                if(is_object($value)){
                    if(!isset($value->$index_key)){
                        continue;
                    }
                    $newlist[$value->$index_key] = $value;
                    continue;
                }
                if(is_array($value)){
                    if(!isset($value[$index_key])){
                        continue;
                    }
                    $newlist[$value[$index_key]] = $value;
                    continue;
                }
            }
            return $newlist;
        }

		// ---------------------------------------------------------------------
        //
		// Asynchronous HTTP requests
        //
		// ---------------------------------------------------------------------

        /**
         * Returns the LDC_Async instance for the given arguments, from which the
         * request is sent without waiting for the response. It works the way
         * ldc()->remote() does, except that a request returns true once it is
         * sent, or a WP_Error when it could not be. E.g. ldc()->async($url, $args)->post($body);
         *
    	 * @return LDC_Async
    	 */
        public function async($url = '', $args = []){
            return LDC_Async::get_instance($url, $args);
        }

		// ---------------------------------------------------------------------

        /**
         * @return true|WP_Error
         */
        public function do_async_request($url = '', $args = []){
            return $this->async($url, $args)->request();
        }

		// ---------------------------------------------------------------------
        //
		// Attachments
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return int
    	 */
    	public function attachment_url_to_postid($url = ''){
            $post_id = attachment_url_to_postid($url);
    		if($post_id){
    			return $post_id;
    		}
    		$post_id = $this->guid_to_postid($url);
    		if($post_id){
    			return $post_id;
    		}
    		preg_match('/^(.+)(\-\d+x\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
    		if($matches){
    			$url = $matches[1];
    			if(isset($matches[3])){
    				$url .= $matches[3];
    			}
    			$post_id = $this->guid_to_postid($url);
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
    			$post_id = $this->guid_to_postid($url);
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
    			$post_id = $this->guid_to_postid($url);
    			if($post_id){
    				return $post_id; // Edited.
    			}
    		}
    		return 0;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function convert_exts_to_mimes($exts = []){
    		if(!$exts){
    	        $exts = array_merge(wp_get_audio_extensions(), wp_get_video_extensions(), $this->get_image_extensions());
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

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function get_attached_image_base64($attachment_id = 0, $size = ''){
            $file = $this->get_attached_image_file($attachment_id, $size);
            if(is_wp_error($file)){
                return $file;
            }
            $mime = wp_get_image_mime($file);
            if(!$mime){
                $mime = mime_content_type($file);
            }
            $contents = file_get_contents($file);
            if($contents === false){
                $error_msg = __('This file is empty. Please try another.');
                $error_msg = $this->first_p($error_msg);
                return $this->error($error_msg);
            }
            return sprintf('data:%s;base64,%s', $mime, base64_encode($contents));
        }

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function get_attached_image_file($attachment_id = 0, $size = ''){
            if(!wp_attachment_is_image($attachment_id)){
                $error_msg = __('This file is not an image. Please try another.');
                $error_msg = $this->first_p($error_msg);
                return $this->error($error_msg);
            }
            $image_file = get_attached_file($attachment_id);
            if(!file_exists($image_file)){
                $error_msg = __('The attached file cannot be found.');
                return $this->error($error_msg);
            }
            $type = wp_check_filetype($image_file);
            if(!$type['ext'] || !$type['type']){
                $error_msg = __('The uploaded file is not a valid image. Please try again.');
                $error_msg = $this->first_p($error_msg);
                return $this->error($error_msg);
            }
            $image_meta = wp_get_attachment_metadata($attachment_id);
            if($size === '' || !$image_meta){
                return $image_file;
            }
            if($size === 'original' && isset($image_meta['original_image'])){
                $original_image = path_join(dirname($image_file), $image_meta['original_image']); // Alias for wp_get_original_image_path().
                if(!file_exists($original_image)){
                    $error_msg = __('The attached file cannot be found.');
                    return $this->error($error_msg);
                }
                return $original_image;
            }
            $image = image_downsize($attachment_id, $size);
            if(!$image){
                $error_msg = __('The attached file cannot be found.');
                return $this->error($error_msg);
            }
            $path = $this->url_to_path($image[0]);
            if($path === ''){
                $error_msg = __('The attached file cannot be found.');
                return $this->error($error_msg);
            }
            return $path;
        }

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function get_image_extensions(){
    		return ['jpg', 'jpeg', 'jpe', 'png', 'gif', 'bmp', 'tiff', 'tif', 'webp', 'ico', 'heic'];
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return int
    	 */
    	public function guid_to_postid($guid = '', $check_rewrite_rules = false){
    		global $wpdb;
    		$query = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid = %s", $guid);
    		$post_id = $wpdb->get_var($query);
    		if(!is_null($post_id)){
    			return intval($post_id);
    		}
    		if($check_rewrite_rules){
    			return url_to_postid($guid);
    		}
    		return 0;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return void
    	 */
    	public function maybe_generate_attachment_metadata($attachment_id = 0){
    		$attachment = get_post($attachment_id);
    		if(is_null($attachment)){
    			return;
    		}
    		if($attachment->post_type !== 'attachment'){
    			return;
    		}
    		if(!function_exists('wp_generate_attachment_metadata')){
    			require_once(ABSPATH . 'wp-admin/includes/image.php');
    		}
    		wp_maybe_generate_attachment_metadata($attachment);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return int|WP_Error
    	 */
    	public function sideload($file = '', $post_id = 0, $generate_attachment_metadata = true){
    		if(!file_exists($file)){
    			return $this->missing_file($file);
    		}
    	    $filename = wp_basename($file);
    	    $filename = $this->test_file_type($file, $filename);
    		if(is_wp_error($filename)){
    			return $filename;
    		}
    	    $filetype_and_ext = wp_check_filetype($filename);
    	    $attachment_id = wp_insert_attachment([
    	        'guid' => $this->path_to_url($file),
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
            $this->maybe_generate_attachment_metadata($attachment_id);
    	    return $attachment_id;
    	}

		// ---------------------------------------------------------------------
        //
		// Authentication
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: Use this method at your own risk!
         *
         * Authenticates and logs a user in with ‘remember’ capability, suppressing filters.
         *
         * @return WP_User|WP_Error
         */
    	public function signon($username_or_email = '', $password = '', $remember = false){
    		global $wpdb;
            if(is_user_logged_in()){
                $error_msg = __('You are logged in already. No need to register again!');
                $error_msg = $this->first_p($error_msg);
                return $this->error($error_msg);
            }
    		$user = false;
    		if(username_exists($username_or_email)){
    			$user = get_user_by('login', $username_or_email);
    		} elseif(is_email($username_or_email) && email_exists($username_or_email)){
    			$user = get_user_by('email', $username_or_email);
    		}
    		if($user === false){
    			$error_msg = __('<strong>Error:</strong> Invalid username, email address or incorrect password.');
    			$error_msg = ucfirst(trim(str_replace(__('Error:'), '', wp_strip_all_tags($error_msg))));
                return $this->error($error_msg);
    		}
    		if($password === '' || !wp_check_password($password, $user->user_pass, $user->ID)){
    			$error_msg = __('<strong>Error:</strong> Invalid username, email address or incorrect password.');
    			$error_msg = ucfirst(trim(str_replace(__('Error:'), '', wp_strip_all_tags($error_msg))));
                return $this->error($error_msg);
    		}
    		wp_clear_auth_cookie();
    		wp_set_auth_cookie($user->ID, $remember);
    		if($user->user_activation_key !== ''){
    			$wpdb->update($wpdb->users, [
    				'user_activation_key' => '',
    			], [
    				'ID' => $user->ID,
    			]); // Clear `user_activation_key` after a successful login.
    			$user->user_activation_key = '';
    		}
    		do_action('wp_login', $user->user_login, $user); // Fires after the user has successfully logged in.
    	    return $user;
    	}

		// ---------------------------------------------------------------------

        /**
         * WARNING: Use this method at your own risk!
         *
         * Authenticates and logs a user in with ‘remember’ capability, without passing a password, suppressing filters.
         *
         * @return WP_Error|WP_User
         */
    	public function signon_without_password($username_or_email = '', $remember = false){
    		global $wpdb;
            if(is_user_logged_in()){
                $error_msg = __('You are logged in already. No need to register again!');
                $error_msg = $this->first_p($error_msg);
                return $this->error($error_msg);
            }
    		$user = false;
    		if(username_exists($username_or_email)){
    			$user = get_user_by('login', $username_or_email);
    		} elseif(is_email($username_or_email) && email_exists($username_or_email)){
    			$user = get_user_by('email', $username_or_email);
    		}
    		if($user === false){
    			$error_msg = __('<strong>Error:</strong> Invalid username, email address or incorrect password.');
    			$error_msg = ucfirst(trim(str_replace(__('Error:'), '', wp_strip_all_tags($error_msg))));
                return $this->error($error_msg);
    		}
    		wp_clear_auth_cookie();
    		wp_set_auth_cookie($user->ID, $remember);
    		if($user->user_activation_key !== ''){
    			$wpdb->update($wpdb->users, [
    				'user_activation_key' => '',
    			], [
    				'ID' => $user->ID,
    			]); // Clear `user_activation_key` after a successful login.
    			$user->user_activation_key = '';
    		}
    		do_action('wp_login', $user->user_login, $user); // Fires after the user has successfully logged in.
    	    return $user;
    	}

		// ---------------------------------------------------------------------
        //
		// Beaver Builder - Plugin, Theme and Theme Builder
        //
		// ---------------------------------------------------------------------

        /**
         * Adds a font that is already on the visitor's system — nothing is loaded —
         * to Beaver Builder's font picker, in both the builder and the theme.
         *
         * Beaver Builder uses the family name as it is: in the picker and in the
         * `font-family` it prints. So it is passed along untouched, never the
         * cache key it is stored under.
         * E.g. ldc()->bb_add_system_font('Helvetica Neue', [400, 700]);
         *
         * @return void
         */
        public function bb_add_system_font($family = '', $weights = [], $fallback = ''){
            if(!is_string($family) || $family === ''){
                return;
            }
            if(!$fallback){
                $fallback = 'Arial, Verdana, sans-serif';
            }
            $this->add_filter_once('fl_builder_font_families_system', [$this, '_bb_maybe_add_system_fonts']);
            $this->add_filter_once('fl_theme_system_fonts', [$this, '_bb_maybe_add_system_fonts']);
            $group = 'bb_system_fonts';
            $this->cache($group)->set($family, [
                'fallback' => $fallback,
                'family' => $family, // Carried in the value, so nothing depends on how the cache shapes its keys.
                'weights' => $this->bb_sanitize_font_weights($weights),
            ]);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function bb_asort_image_sizes(){
            $this->add_filter_once('fl_builder_photo_sizes_select', [$this, '_bb_maybe_asort_image_sizes'], 11);
            $this->cache()->set('bb_asort_image_sizes', true);
        }

		// ---------------------------------------------------------------------

        /**
         * @return array
         */
        public function bb_get_font_weights(){
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

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function bb_is_b4_active(){
            $key = 'bb_is_b4';
            if($this->cache()->exists($key)){
                return $this->cache()->get_bool($key);
            }
            if(!$this->bb_is_theme_active()){
                return false;
            }
        	$value = get_theme_mod('fl-framework', 'none') === 'bootstrap-4';
            $this->cache()->set($key, $value);
        	return $value;
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function bb_is_builder_active(){
            $key = 'bb_is_builder_active';
            if($this->cache()->exists($key)){
                return $this->cache()->get_bool($key);
            }
            if(!class_exists('FLBuilderModel')){
    			return false;
    		}
            $value = FLBuilderModel::is_builder_active();
            $this->cache()->set($key, $value);
        	return $value;
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function bb_is_fa5_active(){
            $key = 'bb_is_fa5';
            if($this->cache()->exists($key)){
                return $this->cache()->get_bool($key);
            }
            if(!$this->bb_is_theme_active()){
                return false;
            }
            $value = get_theme_mod('fl-awesome', 'none') === 'fa5';
            $this->cache()->set($key, $value);
        	return $value;
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function bb_is_theme_active(){
            $key = 'bb_is_theme';
            if($this->cache()->exists($key)){
                return $this->cache()->get_bool($key);
            }
            $value = $this->theme_is('Beaver Builder Theme') || $this->theme_is_child_of('bb-theme');
            $this->cache()->set($key, $value);
            return $value;
        }

		// ---------------------------------------------------------------------

        /**
         * Keeps the weights Beaver Builder knows, as strings: its builder compares
         * them strictly against the saved weight ('400' === 400 is false), so an
         * integer would never show as the one selected.
         *
    	 * @return array
    	 */
        public function bb_sanitize_font_weights($weights = []){
            $font_weights = $this->bb_get_font_weights();
            $sanitized = [];
            foreach((array) $weights as $weight){
                if(!is_scalar($weight)){
                    continue;
                }
                $weight = (string) $weight;
                if(isset($font_weights[$weight])){
                    $sanitized[] = $weight;
                }
        	}
            return $sanitized;
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * This method MUST be called inside the 'fl_builder_font_families_system' or 'fl_theme_system_fonts' filter hooks.
    	 *
         * @return array
         */
        public function _bb_maybe_add_system_fonts($fonts = []){
            if(!doing_filter('fl_builder_font_families_system') && !doing_filter('fl_theme_system_fonts')){
                return $fonts; // Too early or too late.
            }
            $group = 'bb_system_fonts';
            foreach($this->cache($group)->get_group() as $font){
                $fonts[$font['family']] = [
                    'fallback' => $font['fallback'],
                    'weights' => $font['weights'],
                ];
            }
            return $fonts;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * This method MUST be called inside the 'fl_builder_photo_sizes_select' filter hook.
         *
    	 * @return array
    	 */
        public function _bb_maybe_asort_image_sizes($sizes){
            if(!doing_filter('fl_builder_photo_sizes_select')){
        		return $sizes; // Too early or too late.
        	}
            $key = 'bb_asort_image_sizes';
            if(!$this->cache()->exists($key) || !$this->cache()->get_bool($key)){
                return $sizes;
            }
    		uasort($sizes, [$this, '_bb_width_height_asort']);
    		return $sizes;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * @return array
    	 */
        public function _bb_width_height_asort($a = 0, $b = 0){
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

		// ---------------------------------------------------------------------
        //
		// Bootstrap
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function alert_danger($message = '', $is_dismissible = false){
    		return $this->get_alert($message, 'danger', $is_dismissible);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function alert_dark($message = '', $is_dismissible = false){
    		return $this->get_alert($message, 'dark', $is_dismissible);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function alert_info($message = '', $is_dismissible = false){
    		return $this->get_alert($message, 'info', $is_dismissible);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function alert_light($message = '', $is_dismissible = false){
    		return $this->get_alert($message, 'light', $is_dismissible);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function alert_primary($message = '', $is_dismissible = false){
    		return $this->get_alert($message, 'primary', $is_dismissible);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function alert_secondary($message = '', $is_dismissible = false){
    		return $this->get_alert($message, 'secondary', $is_dismissible);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function alert_success($message = '', $is_dismissible = false){
    		return $this->get_alert($message, 'success', $is_dismissible);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function alert_warning($message = '', $is_dismissible = false){
    		return $this->get_alert($message, 'warning', $is_dismissible);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function bs_custom_file_input_version($set = ''){
            static $ver = '1.3.4';
            if($set === ''){
                return $ver; // Get.
            }
            $ver = $set;
            return $ver; // Set.
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function enqueue_bs_custom_file_input($deps = []){
    		if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $ver = $this->bs_custom_file_input_version();
            $src = 'https://cdn.jsdelivr.net/npm/bs-custom-file-input@' . $ver . '/dist/bs-custom-file-input.min.js';
            $handle = $this->enqueue('bs-custom-file-input', $src, $deps, $ver);
            if(is_wp_error($handle)){
                return;
            }
            $data = "jQuery(function(){window.bsCustomFileInput&&window.bsCustomFileInput.init();});";
            wp_add_inline_script($handle, $data);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function get_alert($message = '', $class = '', $is_dismissible = false){
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

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function has_btn_class($class = ''){
    	    $class = $this->remove_whitespaces($class);
    	    preg_match_all('/btn-[A-Za-z][-A-Za-z0-9_:.]*/', $class, $matches);
    		$matches = array_filter($matches[0], function($match){
    			return !in_array($match, ['btn-block', 'btn-lg', 'btn-sm']);
    		});
    		return $matches ? true : false;
    	}

		// ---------------------------------------------------------------------
        //
		// Cache
        //
		// ---------------------------------------------------------------------

        /**
         * Returns the LDC_Cache instance for the given group, from which the cache
         * is read and written. E.g. ldc()->cache($group)->set($key, $value);
         *
    	 * @return LDC_Cache
    	 */
        public function cache($group = ''){
            return LDC_Cache::get_instance($group);
        }

		// ---------------------------------------------------------------------

        /**
         * Serves as a utility function to determine whether a cache key is valid.
         *
    	 * @return bool
    	 */
        public function cache_is_valid_key($key = ''){
            return $this->is_slugified($key);
        }

		// ---------------------------------------------------------------------

        /**
         * Sanitizes cache group.
         *
    	 * @return string
    	 */
        public function cache_sanitize_group($group = ''){
            $group = $this->cache_sanitize_key($group);
            if($group === ''){
                $group = 'default';
            }
            if(is_multisite()){
                $group .= '_' . get_current_blog_id();
            }
            return $group;
        }

		// ---------------------------------------------------------------------

        /**
         * Sanitizes cache key, the way WordPress sanitizes a slug: a key that is
         * already one comes back as it is, and 'size-512' and 'size_512' stay two
         * different keys.
         *
    	 * @return string
    	 */
        public function cache_sanitize_key($key = ''){
            return is_scalar($key) ? sanitize_title($key) : '';
        }

		// ---------------------------------------------------------------------
        //
		// Contact Form 7 - Additional settings
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_additional_setting_exists($name = '', $contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return false;
    		}
    		$pref = $contact_form->pref($name);
    		return !is_null($pref);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * Opposite of WPCF7_ContactForm::is_true().
    	 *
    	 * @return bool
    	 */
    	public function cf7_additional_setting_is_false($name = '', $contact_form = null){
    		$pref = $this->cf7_get_additional_setting($name, $contact_form);
    	    return $this->is_false($pref);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * Alias for WPCF7_ContactForm::is_true().
    	 *
    	 * @return bool
    	 */
    	public function cf7_additional_setting_is_true($name = '', $contact_form = null){
    		$pref = $this->cf7_get_additional_setting($name, $contact_form);
    	    return $this->is_true($pref);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @see WPCF7_ContactForm::pref().
    	 *
    	 * Differs from WPCF7_ContactForm::pref() in that it will always return a string.
    	 *
    	 * @return string
    	 */
    	public function cf7_get_additional_setting($name = '', $contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return '';
    		}
            $pref = $contact_form->pref($name);
    		if(is_null($pref)){
                return '';
            }
            return $pref;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * Alias for WPCF7_ContactForm::additional_setting(@param $max = false).
    	 *
    	 * Differs from WPCF7_ContactForm::additional_setting() in that it will always return an array.
    	 *
    	 * @return array
    	 */
    	public function cf7_get_additional_settings($name = '', $contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return [];
    		}
    		return $contact_form->additional_setting($name, false);
    	}

		// ---------------------------------------------------------------------
        //
		// Contact Form 7
        //
		// ---------------------------------------------------------------------

    	/**
    	 * This method MUST be called inside the 'wpcf7_before_send_mail' action hook.
         *
         * NOTE: ldc()->cf7_abort() does not end the execution of the caller automatically, and should almost always be followed by a call to `return;`.
    	 *
    	 * @return void
    	 */
    	public function cf7_abort(&$abort, $message = '', $submission = null){
    		if(!doing_action('wpcf7_before_send_mail')){
    	        return; // Too early or too late.
    	    }
    		if($abort){
    			return; // Already aborted.
    		}
    	    $submission = $this->cf7_get_submission($submission);
    	    if(is_null($submission)){
    	        return; // Silence is golden.
    	    }
    		if(!$submission->is('init')){
    			return; // Avoid conflicts with other statuses.
    		}
    	    $abort = true; // Avoid 'mail_sent' and 'mail_failed' action hooks.
    		$message = $this->cf7_filter_message($message, 'aborted');
    	    $submission->set_response($message);
    	    $submission->set_status('aborted');
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function cf7_alert($message = '', $type = '', $is_dismissible = false){
            if(is_wp_error($message)){
                $message = $message->get_error_message();
    			if($type === ''){
    				$type = 'danger';
    			}
            }
    		if($message === ''){
    			$message = __('An error occurred.'); // Something went wrong.
                $type = 'danger';
    		}
            return $this->get_alert($message, $type, $is_dismissible);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return int
    	 */
    	public function cf7_contact_form_get_object_number($contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return 0;
    		}
    		$unit_tag = $contact_form->unit_tag();
    		if(preg_match_all('/^wpcf7-f(\d+)-p(\d+)-o(\d+)$/', $unit_tag, $matches)){
    			return (int) $matches[3][0];
    		}
            if(preg_match_all('/^wpcf7-f(\d+)-o(\d+)$/', $unit_tag, $matches)){
                return (int) $matches[2][0];
            }
    		return 0;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * Skips or sends emails based on user input values and contact form email templates avoiding 'mail_sent' and 'mail_failed' action hooks.
    	 *
    	 * This method MUST be called inside the 'wpcf7_before_send_mail' action hook.
    	 *
    	 * @return bool
    	 */
    	public function cf7_fake_mail($submission = null){
    		if(!doing_action('wpcf7_before_send_mail')){
    	        return false; // Too early or too late.
    	    }
            $submission = $this->cf7_get_submission($submission);
    		if(is_null($submission)){
    			return false;
    		}
    		if(!$submission->is('init')){
    			return false; // Avoid conflicts with other statuses.
    		}
            $contact_form = $submission->get_contact_form();
    		if(is_null($contact_form)){
    			return false;
    		}
    		if($this->cf7_skip_mail($contact_form) || $this->cf7_mail($contact_form)){
    			$status = 'mail_sent';
    			$message = $this->cf7_filter_message('', $status);
    			$submission->set_response($message);
    			$submission->set_status($status);
    			return true;
    		}
    		$status = 'mail_failed';
    		$message = $this->cf7_filter_message('', $status);
    		$submission->set_response($message);
    		$submission->set_status($status);
    		return false;
    	}

		// ---------------------------------------------------------------------

        /**
         * @see WPCF7_ContactForm::filter_message().
         *
    	 * @return string
    	 */
    	public function cf7_filter_message($message = '', $status = ''){
    		$message = wpcf7_mail_replace_tags($message);
    		$message = apply_filters('wpcf7_display_message', $message, $status);
    		$message = wp_strip_all_tags($message);
    		if(!$message){
    			$messages = wpcf7_messages();
    			switch($status){
    				case 'aborted':
    					$message = __('Sending mail has been aborted.', 'contact-form-7');
    					break;
    				case 'acceptance_missing':
    					$message = $messages['accept_terms']['default'];
    					break;
    				case 'mail_failed':
    					$message = $messages['mail_sent_ng']['default'];
    					break;
    				case 'mail_sent':
    					$message = $messages['mail_sent_ok']['default'];
    					break;
    				case 'spam':
    					$message = $messages['spam']['default'];
    					break;
    				case 'validation_failed':
    					$message = $messages['validation_error']['default'];
    					break;
    				default:
                        $message = isset($messages[$status]) ? $messages[$status]['default'] : __('Unknown action.');
    			}
    		}
    		return $message;
    	}

		// ---------------------------------------------------------------------

        /**
         * @see WPCF7_ContactForm::find().
    	 *
    	 * Differs from WPCF7_ContactForm::find() in that it will always return an array of post IDs instead of an array of WPCF7_ContactForm objects.
    	 *
    	 * @return int[]
    	 */
    	public function cf7_find($args = []){
    		$defaults = [
                'post_status' => 'any',
    			'posts_per_page' => -1,
    			'offset' => 0,
                'order' => 'ASC',
    			'orderby' => 'ID',
            ];
    		$args = wp_parse_args($args, $defaults);
    		$args['fields'] = 'ids';
            $args['post_type'] = WPCF7_ContactForm::post_type;
    		return get_posts($args);
    	}

		// ---------------------------------------------------------------------

        /**
         * @see WPCF7_Submission::get_contact_form(), wpcf7_get_current_contact_form(), wpcf7_contact_form(), wpcf7_get_contact_form_by_hash() and wpcf7_get_contact_form_by_title().
    	 *
    	 * Returns the current contact form if the specified setting has a falsey value and restores the current contact form.
    	 *
    	 * @return WPCF7_ContactForm|null
    	 */
    	public function cf7_get_contact_form($contact_form = null){
            if($this->cf7_is_contact_form($contact_form)){
    			return $contact_form;
    		}
            if($this->cf7_is_submission($contact_form)){
                return $contact_form->get_contact_form();
            }
    		$current_contact_form = wpcf7_get_current_contact_form();
    		if(is_null($contact_form)){
    			return $current_contact_form;
    		}
    		$post_id = $this->cf7_hash_exists($contact_form); // Hash-based contact form identification.
    		if($post_id){
    			$contact_form = wpcf7_contact_form($post_id); // Avoid wpcf7_get_contact_form_by_hash().
    		} elseif(is_numeric($contact_form)){
    			$contact_form = $this->cf7_get_contact_form_by('id', $contact_form);
    		} elseif(is_string($contact_form)){
    	        $contact_form = $this->cf7_get_contact_form_by('title', $contact_form);
    	    } elseif($contact_form instanceof WP_Post){
    			$contact_form = wpcf7_contact_form($contact_form);
    		} else {
    			$contact_form = null;
    		}
    		if($this->cf7_is_contact_form($current_contact_form)){
    			wpcf7_contact_form($current_contact_form); // Restores the current contact form.
    		}
    		return $contact_form;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return WPCF7_ContactForm|null
    	 */
    	public function cf7_get_contact_form_by($field = '', $value = null){
    	    if('hash' === $field && !$this->cf7_is_version_compatible('5.8')){
    			return null; // @see https://contactform7.com/2023/08/06/contact-form-7-58/#hash-based-contact-form-identification
    		}
    		if('id' === $field){
    			$value = $this->positive_int($value);
    			if(!$value){
    				return null;
    			}
    		}
    		if(in_array($field, ['hash', 'title'])){
    			if(!is_string($value)){
    				return null;
    			}
    			$value = trim($value);
    			if(!$value){
    				return null;
    			}
    		}
    		switch($field){
    			case 'hash':
    				$contact_form = wpcf7_get_contact_form_by_hash($value);
    				break;
    			case 'id':
    				$contact_form = wpcf7_contact_form($value);
    				break;
    			case 'title':
    				$contact_form = wpcf7_get_contact_form_by_title($value);
    				break;
    			default:
    				return null;
    		}
    		return $contact_form;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function cf7_get_metadata($submission = null){
            $metadata = [
    	        'contact_form_id' => 0,
    	        'container_post_id' => 0,
    	        'current_user_id' => 0,
    	        'remote_ip' => '',
    	        'remote_port' => '',
    	        'response' => '',
    	        'status' => '',
    	        'timestamp' => 0,
    	        'unit_tag' => '',
    	        'url' => '',
    	        'user_agent' => '',
    	    ];
    		$submission = $this->cf7_get_submission($submission);
    		if(is_null($submission)){
    			return $metadata;
    		}
            $contact_form = $submission->get_contact_form();
    		if(is_null($contact_form)){
    			return $metadata;
    		}
            $metadata = [
    	        'contact_form_id' => $contact_form->id(),
    	        'container_post_id' => $submission->get_meta('container_post_id'),
    	        'current_user_id' => $submission->get_meta('current_user_id'),
    	        'remote_ip' => $submission->get_meta('remote_ip'),
    	        'remote_port' => $submission->get_meta('remote_port'),
    	        'response' => $submission->get_response(),
    	        'status' => $submission->get_status(),
    	        'timestamp' => $submission->get_meta('timestamp'),
    	        'unit_tag' => $submission->get_meta('unit_tag'),
    	        'url' => $submission->get_meta('url'),
    	        'user_agent' => $submission->get_meta('user_agent'),
    	    ];
            return $metadata;
    	}

		// ---------------------------------------------------------------------

        /**
         * @see wpcf7_get_contact_form_by_hash().
    	 *
    	 * Differs from wpcf7_get_contact_form_by_hash() in that it will always return an integer.
    	 *
    	 * @return int
    	 */
    	public function cf7_hash_exists($hash = ''){
            if(!preg_match('/^[0-9a-f]{7,}$/', $hash)){
                return 0;
            }
            $ids = $this->cf7_find([
                'meta_query' => [
                    [
                        'compare' => 'REGEXP',
                        'key' => '_hash',
                        'value' => '^' . $hash,
                    ],
                ],
                'posts_per_page' => 1,
            ]);
            return $ids ? $ids[0] : 0;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function cf7_invalid_fields($fields = [], $contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return [];
    		}
    		if(!$this->array_is_assoc($fields)){
    			return [];
    		}
    		$invalid = [];
    		$tags = wp_list_pluck($contact_form->scan_form_tags('feature=name-attr'), 'type', 'name');
    		foreach($fields as $name => $types){
    	        if(!isset($tags[$name])){
    	            continue;
    	        }
    	        if(!in_array($tags[$name], (array) $types)){
    	            $invalid[] = $name;
    	        }
    		}
    		return $invalid;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_is_contact_form($thing = null){
            if(is_null($thing)){
                return false;
            }
    		return $thing instanceof WPCF7_ContactForm;
    	}

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function cf7_is_version_compatible($required = ''){
            if(!defined('WPCF7_VERSION')){
                return false;
            }
            if($required === ''){
                return true;
            }
            return version_compare(WPCF7_VERSION, $required, '>=');
        }

		// ---------------------------------------------------------------------

    	/**
    	 * Alias for WPCF7_Submission::mail().
    	 *
    	 * @return bool
    	 */
    	public function cf7_mail($contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return false;
    		}
    		$skip_mail = $this->cf7_skip_mail($contact_form);
    		if($skip_mail){
    			return true;
    		}
    		$result = WPCF7_Mail::send($contact_form->prop('mail'), 'mail');
    		if(!$result){
    			return false;
    		}
    		$additional_mail = [];
    		if($mail_2 = $contact_form->prop('mail_2') and $mail_2['active']){
    			$additional_mail['mail_2'] = $mail_2;
    		}
    		$additional_mail = apply_filters('wpcf7_additional_mail', $additional_mail, $contact_form);
    		foreach($additional_mail as $name => $template){
    			WPCF7_Mail::send($template, $name);
    		}
    		return true;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function cf7_missing_fields($names = [], $contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return [];
    		}
    	    if(!is_array($names)){
    			return [];
    		}
    		if($this->array_is_assoc($names)){
    			$names = array_keys($names);
    		}
    		$missing = [];
    		$tags = wp_list_pluck($contact_form->scan_form_tags('feature=name-attr'), 'name');
    		foreach($names as $name){
    			if(in_array($name, $tags)){
    				continue;
    			}
    			$missing[] = $name;
    		}
    		return $missing;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_skip_mail($contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return false;
    		}
    		$skip_mail = $contact_form->in_demo_mode() || $contact_form->is_true('skip_mail') || !empty($contact_form->skip_mail);
    		$skip_mail = apply_filters('wpcf7_skip_mail', $skip_mail, $contact_form);
    		return $skip_mail;
    	}

		// ---------------------------------------------------------------------
        //
		// Contact Form 7 - Form-tags
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function cf7_form_tag_get_content($tag = null, $remove_whitespaces = false){
            if(!$this->cf7_is_form_tag($tag)){
    	        return '';
    	    }
    		return $remove_whitespaces ? $this->remove_whitespaces($tag->content) : trim($tag->content);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function cf7_form_tag_get_fa($tag = null){
            $class = $this->cf7_form_tag_get_fa_class($tag);
    		return $class === '' ? '' : '<i class="' . $class . '"></i>';
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function cf7_form_tag_get_fa_class($tag = null){
            $classes = $this->cf7_form_tag_get_fa_classes($tag);
    		return $classes ? implode(' ', $classes) : '';
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function cf7_form_tag_get_fa_classes($tag = null){
            $classes = [];
            if(!$this->cf7_is_form_tag($tag)){
    	        return $classes;
    	    }
    		if(!$tag->has_option('fa')){
    			return $classes;
    		}
    		switch(true){
    		    case $tag->has_option('fab'):
    		        $classes[] = 'fab';
    		        break;
    		    case $tag->has_option('fad'):
    		        $classes[] = 'fad';
    		        break;
    		    case $tag->has_option('fal'):
    		        $classes[] = 'fal';
    		        break;
    		    case $tag->has_option('far'):
    		        $classes[] = 'far';
    		        break;
    		    case $tag->has_option('fas'):
    		        $classes[] = 'fas';
    		        break;
    		}
            if(!$classes){
                return $classes;
            }
    		$fa = $tag->get_option('fa', 'class', true);
    		if(0 !== strpos($fa, 'fa-')){
    			$fa = 'fa-' . $fa;
    		}
    		$classes[] = $fa;
    		if($tag->has_option('fw')){
    		    $classes[] = 'fa-fw';
    		}
    	    $rotate = $tag->get_option('rotate', 'int', true);
    	    if(in_array($rotate, [90, 180, 270])){
    	        $classes[] = 'fa-rotate-' . $rotate;
    	    }
    	    $flip = $tag->get_option('flip', '', true);
    	    if(in_array($flip, ['horizontal', 'vertical', 'both'])){
    	        $classes[] = 'fa-flip-' . $flip;
    	    }
    	    $animate = $tag->get_option('animate', '', true);
    	    if(in_array($animate, ['beat', 'fade', 'beat-fade', 'bounce', 'flip', 'shake', 'spin'])){
    	        $classes[] = 'fa-' . $animate;
    	    }
    		return $classes;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function cf7_form_tag_get_floating_label($tag = null){
    		if(!$this->cf7_is_form_tag($tag)){
    	        return '';
    	    }
    		$content = $this->cf7_form_tag_get_content($tag, true);
    		$placeholder = $this->cf7_form_tag_get_placeholder($tag);
    		if($content === '' && $placeholder === ''){
    			return '';
    		}
    		if(!in_array($tag->basetype, ['date', 'email', 'file', 'number', 'password', 'select', 'tel', 'text', 'textarea', 'url'])){
    	        return '';
    	    }
    		$text = $placeholder ? $placeholder : wp_strip_all_tags($content);
    		$text = apply_filters('ldc_cf7_floating_label_text', $text, $tag);
    		return $text;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * WARNING: Avoid WPCF7_FormTag::get_id_option().
    	 *
    	 * Differs from WPCF7_FormTag::get_id_option() in that it will always return a string, even if it is already used.
    	 *
    	 * @return string
    	 */
    	public function cf7_form_tag_get_id($tag = null){
    	    if(!$this->cf7_is_form_tag($tag)){
    	        return '';
    	    }
            $option = $tag->get_option('id', 'id', true);
    		return $option === false ? '' : $option;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function cf7_form_tag_get_idx($tag = null){
    	    if(!$this->cf7_is_form_tag($tag)){
    	        return '';
    	    }
            $id = $this->cf7_form_tag_get_id($tag);
            if($id === ''){
                $id = $tag->name === '' ? $tag->basetype : $tag->name;
            }
            $id .= '-';
            $id .= (wp_doing_ajax() || wp_is_serving_rest_request()) ? uniqid() : $this->unique_id($id);
            return $id;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function cf7_form_tag_get_label($tag = null){
            if(!$this->cf7_is_form_tag($tag)){
    	        return '';
    	    }
            $basetypes = ['checkbox', 'date', 'email', 'file', 'number', 'password', 'radio', 'range', 'select', 'tel', 'text', 'textarea', 'url'];
    		if(!in_array($tag->basetype, $basetypes)){
    	        return '';
    	    }
    		if($tag->basetype === 'textarea' && $tag->has_option('content')){
    			return '';
    		}
            $content = $this->cf7_form_tag_get_content($tag, true);
    		return $content;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see WPCF7_FormTag::get_option(@param $single = true).
    	 *
    	 * Differs from WPCF7_FormTag::get_option() in that it will always return a string.
    	 *
    	 * @return string
    	 */
    	public function cf7_form_tag_get_option($tag = null, $name = '', $pattern = ''){
            if(!$this->cf7_is_form_tag($tag)){
    	        return '';
    	    }
            if(!$tag->has_option($name)){
    	        return '';
    	    }
            $value = $tag->get_option($name, $pattern, true);
            if($value === false){
                return '';
            }
            return $value;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @see WPCF7_FormTag::get_option(@param $single = false).
    	 *
    	 * Differs from WPCF7_FormTag::get_option() in that it will always return an array.
    	 *
    	 * @return array
    	 */
    	public function cf7_form_tag_get_options($tag = null, $name = '', $pattern = ''){
            if(!$this->cf7_is_form_tag($tag)){
    	        return [];
    	    }
            if(!$tag->has_option($name)){
    	        return [];
    	    }
            $value = $tag->get_option($name, $pattern, false);
            if($value === false){
                return [];
            }
            return $value;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function cf7_form_tag_get_placeholder($tag = null){
            if(!$this->cf7_is_form_tag($tag)){
    	        return '';
    	    }
            $basetypes = ['date', 'email', 'file', 'number', 'password', 'select', 'tel', 'text', 'textarea', 'url'];
    		if(!in_array($tag->basetype, $basetypes)){
    	        return '';
    	    }
            if($tag->basetype === 'select'){
    			if($tag->has_option('include_blank') || !$tag->values){
                    return $this->cf7_is_version_compatible('5.7') ? __('&#8212;Please choose an option&#8212;', 'contact-form-7') : '---'; // Drop-down menu: Uses more friendly label text. https://contactform7.com/2022/12/10/contact-form-7-57/
    			}
                if($tag->has_option('first_as_label') && $tag->values){
    				return $tag->values[0];
    			}
                return '';
    		}
            if(($tag->has_option('placeholder') || $tag->has_option('watermark')) && $tag->values){
                return $tag->values[0];
            }
            return '';
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function cf7_form_tag_has_class($tag = null, $default_classes = ''){
            if(!$this->cf7_is_form_tag($tag)){
    	        return false;
    	    }
            $class = $tag->get_class_option();
            if($class === false){
                return false;
            }
            if(is_string($default_classes)){
    			$default_classes = explode(' ', $default_classes);
    		}
            $default_classes = array_map('sanitize_html_class', $default_classes);
    		$default_classes = array_unique($default_classes);
            $default_classes = array_filter($default_classes);
            if(empty($default_classes)){
    			return false;
    		}
            $class = $this->remove_whitespaces($class);
            $classes = explode(' ', $class);
            $has_class = true;
            foreach($default_classes as $default_class){
                if(in_array($default_class, $classes, true)){
                    continue;
                }
                $has_class = false;
                break;
            }
            return $has_class;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function cf7_form_tag_has_content($tag = null){
            $content = $this->cf7_form_tag_get_content($tag, true);
    		return $content !== '';
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_form_tag_has_data_option($tag = null){
    	    if(!$this->cf7_is_form_tag($tag)){
    	        return false;
    	    }
            $data_option = $tag->get_data_option();
            if(is_null($data_option)){
                return false;
            }
    		return $data_option ? true : false;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function cf7_form_tag_has_free_text($tag = null){
    		return $this->cf7_form_tag_has_option($tag, 'free_text');
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @see WPCF7_FormTag::has_option().
    	 *
    	 * @return bool
    	 */
    	public function cf7_form_tag_has_option($tag = null, $name = ''){
    	    if(!$this->cf7_is_form_tag($tag)){
    	        return false;
    	    }
    		return $tag->has_option($name);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_form_tag_has_pipes($tag = null){
    		if(!$this->cf7_is_form_tag($tag)){
    	        return false;
    	    }
    	    if(!WPCF7_USE_PIPE || !$tag->pipes instanceof WPCF7_Pipes || $tag->pipes->zero()){
    	        return false;
    	    }
            $has_pipes = false;
            $pipes = $tag->pipes->to_array();
    	    foreach($pipes as $pipe){
                if($pipe[0] === $pipe[1]){
                    continue;
                }
                $has_pipes = true;
                break;
    	    }
    		return $has_pipes;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_is_form_tag($thing = null){
            if(is_null($thing)){
                return false;
            }
    		return $thing instanceof WPCF7_FormTag;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_form_tag_is_false($tag = null, $name = ''){
    		if(!$this->cf7_is_form_tag($tag)){
    	        return false;
    	    }
    		$value = $tag->get_option($name, '', true);
    		return $value === false ? false : $this->is_false($value);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @see WPCF7_ContactForm::is_true().
    	 *
    	 * @return bool
    	 */
    	public function cf7_form_tag_is_true($tag = null, $name = ''){
    		if(!$this->cf7_is_form_tag($tag)){
    	        return false;
    	    }
    		$value = $tag->get_option($name, '', true);
    		return $value === false ? false : $this->is_true($value);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_form_tag_label_equals($tag = null){
    		$content = $this->cf7_form_tag_get_content($tag, true);
    		$placeholder = $this->cf7_form_tag_get_placeholder($tag);
    		return $content === $placeholder;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function cf7_get_invalid_form_tags($fields = [], $contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return [];
    		}
    		if(!$this->array_is_assoc($fields)){
    			return [];
    		}
    		$invalid = [];
    		$tags = wp_list_pluck($contact_form->scan_form_tags('feature=name-attr'), 'type', 'name');
    		foreach($fields as $name => $types){
    	        if(!isset($tags[$name])){
    	            continue;
    	        }
    	        if(!in_array($tags[$name], (array) $types)){
    	            $invalid[] = $name;
    	        }
    		}
    		return $invalid;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function cf7_get_missing_form_tags($names = [], $contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return [];
    		}
    	    if(!is_array($names)){
    			return [];
    		}
    		if($this->array_is_assoc($names)){
    			$names = array_keys($names);
    		}
    		$missing = [];
    		$tags = wp_list_pluck($contact_form->scan_form_tags('feature=name-attr'), 'name');
    		foreach($names as $name){
    			if(in_array($name, $tags)){
    				continue;
    			}
    			$missing[] = $name;
    		}
    		return $missing;
    	}

		// ---------------------------------------------------------------------
        //
		// Contact Form 7 - Actions and filters
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function cf7_filter_form_tag_output($callback = null, $priority = 10){
    		$accepted_args = 3;
    		if(!$this->is_callable($callback, $accepted_args)){
    	        return false;
    	    }
    		return $this->add_filter_once('ldc_cf7_form_tag_output', $callback, $priority, $accepted_args);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function cf7_filter_shortcode_tag_errors($callback = null, $priority = 10){
    		$accepted_args = 3;
    		if(!$this->is_callable($callback, $accepted_args)){
    	        return false;
    	    }
    		return $this->add_filter_once('ldc_cf7_shortcode_tag_errors', $callback, $priority, $accepted_args);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function cf7_filter_shortcode_tag_output($callback = null, $priority = 10){
    		$accepted_args = 3;
    		if(!$this->is_callable($callback, $accepted_args)){
    	        return false;
    	    }
    		return $this->add_filter_once('ldc_cf7_shortcode_tag_output', $callback, $priority, $accepted_args);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function cf7_filter_submit_output($callback = null, $priority = 10){
    		$accepted_args = 3;
    		if(!$this->is_callable($callback, $accepted_args)){
    	        return false;
    	    }
    		return add_filter('ldc_cf7_submit_output', $callback, $priority, $accepted_args);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function cf7_hide_shortcode_tag_on_builder_active(){
    		return $this->add_filter_once('ldc_cf7_hide_shortcode_tag_on_builder_active', '__return_true');
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function cf7_remove_shortcode_tag_comments(){
    		return $this->add_filter_once('ldc_cf7_remove_shortcode_tag_comments', '__return_true');
    	}

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

    	/**
    	 * WARNING: This method’s access is marked private.
    	 *
    	 * This method MUST be called inside the 'ldc_cf7_shortcode_tag_output' filter hook.
    	 *
    	 * @return string
    	 */
    	public function _cf7_do_form_tags($output, $contact_form, $attr){
            if(!doing_filter('ldc_cf7_shortcode_tag_output')){
    	        return $output; // Too early or too late.
    	    }
    		$html = $this->str_get_html($output);
    		$tags = $contact_form->scan_form_tags('feature=name-attr');
    		foreach($tags as $tag){
    			$wrapper = $html->find('.wpcf7-form-control-wrap[data-name="' . $tag->name . '"]', 0);
    			if(is_null($wrapper)){
    				continue;
    			}
    			$outertext = $wrapper->outertext;
    			$output = apply_filters('ldc_cf7_form_tag_output', $outertext, $tag, $contact_form);
    			$wrapper->outertext = $output;
    		}
    		$tags = $contact_form->scan_form_tags('type=submit');
    		foreach($tags as $idx => $tag){
    			$submit = $html->find('.wpcf7-form-control[type="submit"]', $idx);
    			if(is_null($submit)){
    				continue;
    			}
    			$outertext = $submit->outertext;
    			$output = apply_filters('ldc_cf7_submit_output', $outertext, $tag, $contact_form);
    			$submit->outertext = $output;
    		}
    		$output = $html->save();
    		return $output;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * WARNING: This method’s access is marked private.
    	 *
    	 * This method MUST be called inside the 'do_shortcode_tag' filter hook.
    	 *
    	 * @return void
    	 */
    	public function _cf7_do_shortcode_tag($output, $tag, $attr){
    		if(!doing_filter('do_shortcode_tag')){
    	        return $output; // Too early or too late.
    	    }
    		if($tag !== 'contact-form-7'){
    			return $output; // Bail if the current shortcode is not contact-form-7.
    		}
    		$contact_form = $this->cf7_get_contact_form();
    		if(is_null($contact_form)){
    	        return $output; // Bail if the contact form do not exist.
    		}
    	    $html = $this->str_get_html($output); // Test for Simple HTML DOM.
    		if(is_wp_error($html)){
    			return $output; // Silence is golden.
    		}
    		$output = apply_filters('ldc_cf7_shortcode_tag_output', $output, $contact_form, $attr);
    		return $output;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * WARNING: This method’s access is marked private.
    	 *
    	 * This method MUST be called inside the 'ldc_cf7_shortcode_tag_output' filter hook.
    	 *
    	 * @return string
    	 */
    	public function _cf7_maybe_add_shortcode_tag_errors($output, $contact_form, $attr){
            if(!doing_filter('ldc_cf7_shortcode_tag_output')){
    	        return $output; // Too early or too late.
    	    }
    		$errors = new WP_Error();
    	    $errors = apply_filters('ldc_cf7_shortcode_tag_errors', $errors, $output, $contact_form, $attr);
    	    if(!is_wp_error($errors)){
    	        return $this->cf7_alert();
    	    }
    	    if($errors->has_errors()){
    			return $this->cf7_alert($errors);
    	    }
    		return $output;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * WARNING: This method’s access is marked private.
    	 *
    	 * This method MUST be called inside the 'ldc_cf7_shortcode_tag_output' filter hook.
    	 *
    	 * @return string
    	 */
    	public function _cf7_maybe_hide_on_builder_active($output, $contact_form, $attr){
            if(!doing_filter('ldc_cf7_shortcode_tag_output')){
    	        return $output; // Too early or too late.
    	    }
    	    $hide_on_builder_active = apply_filters('ldc_cf7_hide_shortcode_tag_on_builder_active', false, $contact_form, $attr);
    	    if(!$hide_on_builder_active){
    			return $output;
    		}
            if(!$this->bb_is_builder_active()){
                return $output; // Bail if the builder isn't active.
            }
            $branding = FLBuilderModel::get_branding();
            $post_type = get_post_type();
            if($post_type){
                $object = get_post_type_object($post_type);
                $singular_name = $object->labels->singular_name;
            } else {
                $singular_name = __('Content');
            }
            $message = _x('%1$s is currently active for this %2$s.', '%1$s branded builder name. %2$s post type name.', 'fl-builder');
            $message = sprintf($message, $branding, $singular_name);
            return $this->cf7_alert($message);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * WARNING: This method’s access is marked private.
    	 *
    	 * This method MUST be called inside the 'ldc_cf7_shortcode_tag_output' filter hook.
    	 *
    	 * @return string
    	 */
    	public function _cf7_maybe_remove_shortcode_tag_comments($output, $contact_form, $attr){
            if(!doing_filter('ldc_cf7_shortcode_tag_output')){
    	        return $output; // Too early or too late.
    	    }
    	    $remove_comments = apply_filters('ldc_cf7_remove_shortcode_tag_comments', false, $contact_form, $attr);
    	    if(!$remove_comments){
    			return $output;
    		}
    		$html = $this->str_get_html($output);
    		$comments = $html->find('comment');
    		foreach($comments as $comment){
    			$comment->remove();
    		}
    		$output = $html->save();
    		return $output;
    	}

		// ---------------------------------------------------------------------
        //
		// Contact Form 7 - Shortcode attributes
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @see WPCF7_ContactForm::shortcode_attr().
    	 *
    	 * Differs from WPCF7_ContactForm::shortcode_attr() in that it will always return a string, even if the attribute does not exist.
    	 *
    	 * @return string
    	 */
    	public function cf7_get_shortcode_attr($name = '', $contact_form = null){
    		$contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return '';
    		}
            $value = $contact_form->shortcode_attr($name);
    		return is_null($value) ? '' : $value;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return void
    	 */
    	public function cf7_shortcode_attr_exists($name = '', $contact_form = null){
            $contact_form = $this->cf7_get_contact_form($contact_form);
    		if(is_null($contact_form)){
    			return '';
    		}
            $value = $contact_form->shortcode_attr($name);
    		return !is_null($value);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return void
    	 */
    	public function cf7_shortcode_attr_is_false($name = '', $contact_form = null){
            $value = $this->cf7_get_shortcode_attr($name, $contact_form);
    		return $this->is_false($value);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return void
    	 */
    	public function cf7_shortcode_attr_is_true($name = '', $contact_form = null){
            $value = $this->cf7_get_shortcode_attr($name, $contact_form);
    		return $this->is_true($value);
    	}

		// ---------------------------------------------------------------------
        //
		// Contact Form 7 - Submissions
        //
		// ---------------------------------------------------------------------

        /**
    	 * @see WPCF7_Submission::get_meta().
         *
    	 * Differs from WPCF7_Submission::get_meta() in that it will return an array if the meta key has a falsey value.
    	 *
    	 * @return array|int|string
    	 */
    	public function cf7_get_meta($key = '', $submission = null){
    		$submission = $this->cf7_get_submission($submission);
    		if(is_null($submission)){
    			return empty($key) ? [] : '';
    		}
            if(!empty($key)){
                $value = $submission->get_meta($key);
                return is_null($value) ? '' : $value;
            }
            $metadata = [
    	        'container_post_id' => $submission->get_meta('container_post_id'),
    	        'current_user_id' => $submission->get_meta('current_user_id'),
                'do_not_store' => $submission->get_meta('do_not_store'),
    	        'remote_ip' => $submission->get_meta('remote_ip'),
    	        'remote_port' => $submission->get_meta('remote_port'),
    	        'timestamp' => $submission->get_meta('timestamp'),
    	        'unit_tag' => $submission->get_meta('unit_tag'),
    	        'url' => $submission->get_meta('url'),
    	        'user_agent' => $submission->get_meta('user_agent'),
    	    ];
            return $metadata;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function cf7_get_posted_array($key = ''){
    		if($key === ''){
    			return [];
    		}
            $data = $this->cf7_get_posted_data();
            if(!isset($data[$key])){
                return [];
            }
            $value = $data[$key];
            $value = wpcf7_array_flatten($value);
    		return $value;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @see WPCF7_Submission::get_posted_data().
    	 *
    	 * Differs from WPCF7_Submission::get_posted_data() in that it will avoid filters.
    	 *
    	 * @return array
    	 */
    	public function cf7_get_posted_data(){
            $key = 'cf7_submission_posted_data';
            if($this->cache()->exists($key)){
                $data = $this->cache()->get_array($key);
                return $data;
            }
            $data = wp_unslash($_POST);
            $data = $this->cf7_sanitize_posted_data($data);
            $this->cache()->set($key, $data);
            return $data;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see WPCF7_Submission::get_posted_string().
    	 *
    	 * Differs from WPCF7_Submission::get_posted_string() in that it will avoid filters and returns all values in a comma separated string instead of just the first array item.
    	 *
    	 * @return string
    	 */
    	public function cf7_get_posted_string($key = ''){
            $data = $this->cf7_get_posted_array($key);
            $data = implode(', ', $data);
    		return $data;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @see WPCF7_Submission::get_instance().
    	 *
    	 * Returns the current submission if the specified setting has a falsey value.
    	 *
    	 * @return WPCF7_Submission|null
    	 */
    	public function cf7_get_submission($submission = null){
    		$current_submission = WPCF7_Submission::get_instance();
    		if(is_null($submission)){
    			return $current_submission;
    		}
    		if($this->cf7_is_submission($submission)){
    			return $submission;
    		}
    		return null;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_has_posted_data($key = ''){
    		if($key === ''){
    			return false;
    		}
            $data = $this->cf7_get_posted_data();
            return isset($data[$key]);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function cf7_is_submission($thing = null){
            if(is_null($thing)){
                return false;
            }
    		return $thing instanceof WPCF7_Submission;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @see WPCF7_Submission::sanitize_posted_data().
    	 *
    	 * @return array|string
    	 */
    	public function cf7_sanitize_posted_data($value = []){
            return map_deep($value, [$this, 'cf7_sanitize_posted_string']);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function cf7_sanitize_posted_string($value = ''){
            $value = wp_check_invalid_utf8($value);
            $value = wp_kses_no_null($value);
            if(function_exists('wpcf7_strip_whitespaces')){
                $value = wpcf7_strip_whitespaces($value); // @see https://contactform7.com/2024/07/13/consistent-handling-policy-of-surrounding-whitespaces/
            }
    		return $value;
    	}

		// ---------------------------------------------------------------------
        //
		// Closures
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return string|WP_Error
    	 */
    	public function closure_serialize($data = null, $security = null){
    		if(!$this->is_closure($data)){
                $error_msg = __('Invalid object type.');
    			return $this->error($error_msg, $data);
    		}
            $dir = $this->use_closure();
    		if(is_wp_error($dir)){
    			return $dir;
    		}
    		$serialized = LDC\Deps\Opis\Closure\serialize($data, $security);
    		return $serialized;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return object|WP_Error
    	 */
    	public function closure_unserialize($data = null, $security = null, $options = null){
    		if(!is_string($data)){
                $error_msg = __('Invalid data provided.');
    			return $this->error($error_msg, $data);
    		}
    		$dir = $this->use_closure();
    		if(is_wp_error($dir)){
    			return $dir;
    		}
    		$unserialized = LDC\Deps\Opis\Closure\unserialize($data, $security, $options);
            return $unserialized;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function closure_ver($set = ''){
    		static $ver = '4.5.0';
            if(!$set){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_closure($thing = null){
            if(is_null($thing)){
                return false;
            }
    		return $thing instanceof Closure;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return true|WP_Error
    	 */
    	public function use_closure(){
    		return $this->use_dep('LDC\Deps\Opis\Closure\Serializer');
    	}

		// ---------------------------------------------------------------------
        //
		// Cloudflare
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function cf_get_country(){
            return isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : '';
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function cf_get_ip(){
            return isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : '';
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function cf_is_active(){
            return isset($_SERVER['CF-ray']);
    	}

		// ---------------------------------------------------------------------
        //
		// Cloudinary
        //
		// ---------------------------------------------------------------------

        /**
         * Adds a transformation registered with ldc()->cl_register_image_transformation()
         * as a WordPress image size, by its slug.
         *
         * Unlike ldc()->cl_image_transformation_to_bb(), this is site-wide:
         * image_downsize() is filtered, so any image asked for in this size is
         * transformed.
         * E.g. ldc()->cl_add_image_size('size-512');
         *
         * The transformation is looked up when the size is first used, not now, so
         * it may be registered before or after this call.
         *
         * @return void
         */
        public function cl_add_image_size($slug = ''){
            $default_sizes = ['thumbnail', 'medium', 'medium_large', 'large'];
            if(!$this->_cl_is_image_transformation_slug($slug)){
                return;
            }
        	if(in_array($slug, $default_sizes)){
        		return; // Do not overwrite default sizes.
        	}
            $this->add_filter_once('fl_builder_photo_sizes_select', [$this, '_cl_fl_builder_photo_sizes_select']);
            $this->add_filter_once('image_downsize', [$this, '_cl_image_downsize'], 10, 3);
            $this->add_filter_once('image_size_names_choose', [$this, '_cl_image_size_names_choose']);
            $group = 'cl_image_sizes';
            $this->cache($group)->set($slug, $slug); // Carried in the value, so nothing depends on how the cache shapes its keys.
            add_image_size($slug); // Fake size.
        }

		// ---------------------------------------------------------------------

        /**
         * Reads the access keys from the environment first and from constants after
         * that, so they never have to be written into a function call.
         *
         * Either `LDC_CL_URL`, holding the whole `cloudinary://` string, or
         * `LDC_CL_API_KEY`, `LDC_CL_API_SECRET` and `LDC_CL_CLOUD_NAME` separately.
         * The URL wins when both are set, and a malformed URL is an error rather
         * than a silent fallback.
         *
         * @return array|WP_Error
         */
        public function cl_config(){
            $cache_key = 'cl_config';
            if($this->cache()->exists($cache_key)){
                return $this->cache()->get_array($cache_key);
            }
            $url = $this->_cl_credential('LDC_CL_URL');
            if($url !== ''){
                if(!preg_match('/^(?:CLOUDINARY_URL=)?(?:cloudinary:\/\/)(\d+):([^:@]+)@([^@]+)$/', $url, $matches)){
                    $error_msg = __('Invalid parameter(s): %s');
                    $error_msg = sprintf($error_msg, 'Access Keys') . '.';
                    return $this->error($error_msg);
                }
                $credentials = [
                    'api_key' => $matches[1],
                    'api_secret' => $matches[2],
                    'cloud_name' => $matches[3],
                ];
            } else {
                $credentials = [
                    'api_key' => $this->_cl_credential('LDC_CL_API_KEY'),
                    'api_secret' => $this->_cl_credential('LDC_CL_API_SECRET'),
                    'cloud_name' => $this->_cl_credential('LDC_CL_CLOUD_NAME'),
                ];
                foreach($credentials as $credential){
                    if($credential !== ''){
                        continue;
                    }
                    $error_msg = __('Missing parameter(s): %s');
                    $error_msg = sprintf($error_msg, 'Access Keys') . '.';
                    return $this->error($error_msg);
                }
            }
            $lib = $this->cl_use();
            if(is_wp_error($lib)){
                return $lib;
            }
            LDC\Deps\Cloudinary\Configuration\Configuration::instance([
                'cloud' => $credentials,
            ]);
            $this->cache()->set($cache_key, $credentials); // Cache what was built, so the shape — and the hash the meta keys are namespaced with — never changes.
            return $credentials;
        }

		// ---------------------------------------------------------------------

        /**
         * Returns the LDC_CL_Image instance for the given attachment, from which the
         * image is read on Cloudinary. Every method takes the transformation: the
         * options array, the slug of one registered with
         * ldc()->cl_register_image_transformation(), or nothing at all to address the
         * original.
         * E.g. ldc()->cl_image($post_id)->file_info('size-512');
         *
    	 * @return LDC_CL_Image
    	 */
        public function cl_image($post_id = 0){
            return LDC_CL_Image::get_instance($post_id);
        }

		// ---------------------------------------------------------------------

        /**
         * Lists a transformation registered with ldc()->cl_register_image_transformation()
         * under "Transform", in a "Cloudinary" section at the end of the Beaver
         * Builder photo module's Style tab, by its slug. The module shows its
         * display name.
         *
         * No image size is added and image_downsize() is never filtered, so nothing
         * is sent to Cloudinary until a photo module actually selects it.
         * E.g. ldc()->cl_image_transformation_to_bb('size-512');
         *
         * The transformation wins over the module's own "Crop" setting, which
         * Beaver Builder applies before this runs.
         *
         * @return void
         */
        public function cl_image_transformation_to_bb($slug = ''){
            if(!$this->_cl_is_image_transformation_slug($slug)){
                return;
            }
            $group = 'cl_bb_image_transformations';
            $this->cache($group)->set($slug, $slug); // Carried in the value, so nothing depends on how the cache shapes its keys.
            $this->add_filter_once('fl_builder_photo_module_url', [$this, '_cl_bb_maybe_transform_photo_url'], 10, 2);
            $this->_cl_bb_add_image_transformation_field();
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function cl_is_error($thing = null){
            return $thing instanceof LDC\Deps\Cloudinary\Api\Exception\ApiError;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function cl_meta_key($context = ''){
            if(!$context){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'context');
                return $this->error($error_msg);
            }
            $config = $this->cl_config();
            if(is_wp_error($config)){
                return $config;
            }
            $config_hash = $this->md5($config);
            $meta_key = '_ldc_cl_' . $config_hash . '_' . sanitize_title($context);
            return $meta_key;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function cl_meta_key_with_options($context = '', $options = []){
            $meta_key = $this->cl_meta_key($context);
            if(is_wp_error($meta_key)){
                return $meta_key;
            }
            if(!$options){
                $error_msg = __('The %s argument must be an array.');
                $error_msg = sprintf($error_msg, 'options');
                return $this->error($error_msg);
            }
            $options_hash = $this->md5($options);
            $meta_key .= '_' . $options_hash;
            return $meta_key;
        }

		// ---------------------------------------------------------------------

        /**
         * Registers a Cloudinary transformation, and nothing else: no image size,
         * no filter, no field. Everything that applies one refers to it by its
         * slug — ldc()->cl_add_image_size(),
         * ldc()->cl_image_transformation_to_bb() and every LDC_CL_Image
         * method — so its options are defined in exactly one place.
         *
         * Modeled on add_role(): the slug is what code uses, the display name is
         * what people see, and one never stands in for the other. The slug has to
         * be what sanitize_title() returns, e.g. 'size-512', or the transformation
         * is refused.
         *
         * Also like add_role(), the first registration of a slug stays and later
         * ones are ignored. A child theme's ldc-methods.php loads before its
         * parent's, so that is what lets the child override the parent.
         * E.g. ldc()->cl_register_image_transformation('size-512', 'Size 512', $options);
         *
         * @return void
         */
        public function cl_register_image_transformation($slug = '', $display_name = '', $options = []){
            if(!$this->_cl_is_image_transformation_slug($slug)){
                return;
            }
            if(!is_string($display_name) || $display_name === ''){
                return;
            }
            if(!is_array($options)){
                return;
            }
            $group = 'cl_image_transformations';
            $this->cache($group)->add($slug, [ // Only if not registered yet: the first one stays.
                'display_name' => $display_name,
                'options' => $options,
                'slug' => $slug, // Carried in the value, so nothing depends on how the cache shapes its keys.
            ]);
        }

		// ---------------------------------------------------------------------

        /**
         * @return true|WP_Error
         */
        public function cl_use(){
            return $this->use_dep('LDC\Deps\Cloudinary\Configuration\Configuration');
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function cl_version($set = ''){
            static $ver = '3.1.3';
            if($set === ''){
                return $ver; // Get.
            }
            $ver = $set;
            return $ver; // Set.
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Puts the "Cloudinary" section, with its "Transform" field, into the photo
         * module's settings form.
         *
         * Beaver Builder registers its modules on 'init' at priority 2. Before
         * that the filter below is the only way in; after it the stored form is,
         * and it stays readable because the builder does not hand its config over
         * to the UI until much later. Registering a transformation from either
         * side of that line has to work, so both are covered.
         *
    	 * @return void
    	 */
        public function _cl_bb_add_image_transformation_field(){
            $this->add_filter_once('fl_builder_register_settings_form', [$this, '_cl_bb_maybe_add_image_transformation_field'], 10, 2);
            if(!class_exists('FLBuilderModel')){
                return;
            }
            $slug = 'photo'; // Hardcoded.
            if(!isset(FLBuilderModel::$modules[$slug])){
                return; // Not registered yet, the filter above catches it.
            }
            $module = FLBuilderModel::$modules[$slug];
            if(!is_object($module) || !isset($module->form)){
                return;
            }
            $module->form = $this->_cl_bb_image_transformation_form($module->form);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Adds a "Cloudinary" section, holding the "Transform" field, at the end of
         * the photo module's Style tab.
         *
    	 * @return array
    	 */
        public function _cl_bb_image_transformation_form($form = []){
            if(!isset($form['style']['sections']) || !is_array($form['style']['sections'])){
                return $form; // Not the form this section belongs to.
            }
            $options = [
                '' => _x('None', 'Photo Crop.', 'fl-builder'),
            ];
            $group = 'cl_bb_image_transformations';
            foreach($this->cache($group)->get_group() as $slug){
                $transformation = $this->_cl_image_transformation($slug);
                if(!$transformation){
                    continue; // Listed, but never registered.
                }
                $options[$slug] = $transformation['display_name'];
            }
            $key = 'ldc_cloudinary'; // Hardcoded.
            unset($form['style']['sections'][$key]); // Listing another transformation rebuilds it, last again.
            $form['style']['sections'][$key] = [
                'title' => 'Cloudinary', // A name, so it is not translated.
                'fields' => [
                    'ldc_transformation' => [ // Hardcoded. Modules store their choice under this key, whatever section shows it.
                        'type' => 'select',
                        'label' => __('Transform', 'fl-builder'),
                        'default' => '',
                        'options' => $options,
                    ],
                ],
            ];
            return $form;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'fl_builder_register_settings_form' filter hook.
    	 *
    	 * @return array
    	 */
        public function _cl_bb_maybe_add_image_transformation_field($form = [], $slug = ''){
            if(!doing_filter('fl_builder_register_settings_form')){
                return $form; // Too early or too late.
            }
            if($slug !== 'photo'){ // Hardcoded.
                return $form;
            }
            return $this->_cl_bb_image_transformation_form($form);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'fl_builder_photo_module_url' filter hook.
    	 *
         * This is the only place a transformation is generated: a module that
         * selected none is left with the URL Beaver Builder built, and an error
         * along the way — a transformation listed but never registered, too —
         * leaves it there as well, so the photo always renders.
         *
    	 * @return string
    	 */
        public function _cl_bb_maybe_transform_photo_url($url = '', $module = null){
            if(!doing_filter('fl_builder_photo_module_url')){
                return $url; // Too early or too late.
            }
            if(!is_object($module) || !isset($module->settings)){
                return $url;
            }
            $settings = $module->settings;
            if(!isset($settings->ldc_transformation)){
                return $url;
            }
            $transformation = $settings->ldc_transformation;
            if(!is_scalar($transformation) || !$transformation){
                return $url; // Nothing selected, so nothing is transformed.
            }
            $group = 'cl_bb_image_transformations';
            if(!$this->cache($group)->exists($transformation)){
                return $url; // Selected once, no longer listed.
            }
            $id = $this->_cl_bb_photo_module_id($module);
            if(!$id){
                return $url;
            }
            $transformed_url = $this->cl_image($id)->url($this->cache($group)->get_string($transformation));
            if(is_wp_error($transformed_url)){
                return $url;
            }
            return $transformed_url;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Gets the attachment a photo module is rendering.
         *
         * The module keeps the attachment ID in `photo`, except when the field is
         * connected to a field of its own, where it holds the whole attachment
         * object. A module sourced from a URL carries no ID at all, so the URL is
         * looked up — an image outside the media library has none and is skipped.
         *
    	 * @return int
    	 */
        public function _cl_bb_photo_module_id($module = null){
            if(!is_object($module) || !isset($module->settings)){
                return 0;
            }
            $settings = $module->settings;
            $photo = isset($settings->photo) ? $settings->photo : 0;
            if(is_object($photo)){
                $photo = isset($photo->id) ? $photo->id : 0;
            }
            $id = $this->positive_int($photo); // Discards non-numerics.
            if($id){
                return $id;
            }
            foreach(['photo_src', 'photo_url'] as $key){
                if(!isset($settings->$key) || !is_string($settings->$key) || !$settings->$key){
                    continue;
                }
                $id = $this->attachment_url_to_postid($settings->$key);
                if($id){
                    return $id;
                }
            }
            return 0;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Reads a credential from the environment first, then from a constant, the
         * way it would be defined in wp-config.php.
         *
    	 * @return string
    	 */
        public function _cl_credential($name = ''){
            if(!$name){
                return '';
            }
            $value = getenv($name);
            if(is_string($value) && trim($value) !== ''){
                return trim($value);
            }
            if(defined($name)){
                $value = constant($name);
                if(is_scalar($value) && trim((string) $value) !== ''){
                    return trim((string) $value);
                }
            }
            return '';
        }

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function _cl_fl_builder_photo_sizes_select($sizes){
            $group = 'cl_image_sizes';
            $cl_image_sizes = $this->cache($group)->get_group();
            if(!$cl_image_sizes){
                return $sizes;
            }
    		if(!isset($sizes['full'])){
    			return $sizes;
    		}
    		$id = $this->attachment_url_to_postid($sizes['full']['url']);
    		if(!$id){
    			return $sizes;
    		}
    		foreach($cl_image_sizes as $size){
    			if(isset($sizes[$size])){
    				continue;
    			}
                $file_info = $this->cl_image($id)->file_info($size);
                if(is_wp_error($file_info)){
                    continue;
                }
    			$sizes[$size] = [
    				'filename' => $file_info['filename'],
    				'height' => $file_info['height'],
    				'url' => $file_info['url'],
    				'width' => $file_info['width'],
    			];
    		}
    		return $sizes;
    	}

		// ---------------------------------------------------------------------

        /**
         * @return array|false
         */
        public function _cl_image_downsize($out, $id, $size){
        	if($out){
        		return $out; // A truthy value from the filter will effectively short-circuit down-sizing the image, returning that value instead.
        	}
            if(!wp_attachment_is_image($id)){
        		return $out;
        	}
            if(!is_scalar($size)){
                return $out; // Discard non-scalars.
            }
            $group = 'cl_image_sizes';
            if(!$this->cache($group)->exists($size)){
                return $out;
            }
            $file_info = $this->cl_image($id)->file_info($this->cache($group)->get_string($size));
            if(is_wp_error($file_info)){
                return $out;
            }
            return [$file_info['url'], $file_info['width'], $file_info['height'], true];
        }

		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function _cl_image_size_names_choose($sizes){
            $group = 'cl_image_sizes';
            $cl_image_sizes = $this->cache($group)->get_group();
            if(!$cl_image_sizes){
                return $sizes;
            }
    		foreach($cl_image_sizes as $size){
    			$transformation = $this->_cl_image_transformation($size);
    			if(!$transformation){
    				continue; // Added, but never registered.
    			}
    			$sizes[$size] = $transformation['display_name'];
    		}
    		return $sizes;
    	}

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Gets a transformation registered with ldc()->cl_register_image_transformation()
         * by its slug — its display name, options and slug — or an empty array
         * when there is none.
         *
    	 * @return array
    	 */
        public function _cl_image_transformation($slug = ''){
            if(!$this->_cl_is_image_transformation_slug($slug)){
                return []; // Not even a slug, so certainly not a registered one.
            }
            $group = 'cl_image_transformations';
            if(!$this->cache($group)->exists($slug)){
                return [];
            }
            return $this->cache($group)->get_array($slug);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Checks that a transformation is referred to by a slug: a string that
         * sanitize_title() returns unchanged, like 'size-512'. Its display name
         * never stands in for it, the same way a role's display name never stands
         * in for the role.
         *
    	 * @return bool
    	 */
        public function _cl_is_image_transformation_slug($slug = ''){
            return is_string($slug) && $slug !== '' && sanitize_title($slug) === $slug;
        }

		// ---------------------------------------------------------------------
        //
		// Code - CSS, JavaScript and Less
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function compress_css($css = ''){
    		return $this->remove_whitespaces(preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css));
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function minify_css($css = ''){
            $css = $this->normalize_ie_filters($css);
            $less = $this->parse_less($css, [
                'compress' => true,
            ]);
            if(!is_wp_error($less)){
                $css = $less;
            }
            return $this->compress_css($css);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function normalize_ie_filters($css = ''){
    		return preg_replace_callback('(filter\s?:\s?(.*);)', [$this, '_replace_ie_filters'], $css); // Fix issue with IE filters.
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
     	 * WARNING: This method’s access is marked private.
     	 *
    	 * @return string
    	 */
        public function _replace_ie_filters($matches = []){
    		return empty($matches[1]) ? $matches[0] : 'filter: ~"' . $matches[1] . '";';
        }

		// ---------------------------------------------------------------------
        //
		// Colors
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function get_readable_text_color($hex = ''){
            $rgb = $this->hex_to_rgb($hex);
            if(is_wp_error($rgb)){
                return $rgb;
            }
            [$r, $g, $b] = $rgb;
            $brightness = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
            return $brightness > 0.62 ? 'dark' : 'light';
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function get_readable_text_color_wcag($hex = ''){
            $rgb = $this->hex_to_rgb($hex);
            if(is_wp_error($rgb)){
                return $rgb;
            }
            [$r, $g, $b] = $rgb;
            $channels = [$r / 255, $g / 255, $b / 255];
            foreach($channels as &$channel){
                $channel = $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
            }
            unset($channel);
            list($r, $g, $b) = $channels;
            $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
            $dark_contrast = ($luminance + 0.05) / 0.05;
            $light_contrast = 1.05 / ($luminance + 0.05);
            return $dark_contrast >= $light_contrast ? 'dark' : 'light';
        }

		// ---------------------------------------------------------------------

        /**
         * Converts a hex value into the rgb equivalent.
         *
    	 * @return array|WP_Error
    	 */
        public function hex_to_rgb($hex = ''){
            $hex = trim($hex);
            if(isset($hex[0]) && $hex[0] === '#'){
                $hex = substr($hex, 1);
            }
            if(strlen($hex) === 3){
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if(strlen($hex) !== 6 || !ctype_xdigit($hex)){
                return $this->invalid_params(['hex']);
            }
            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }

		// ---------------------------------------------------------------------

        /**
         * Converts a hex value into the rgba equivalent.
         *
    	 * @return string|WP_Error
    	 */
        public function hex_to_rgba($hex = '', $alpha = 1){
            if(!is_numeric($alpha)){
               return $this->invalid_params(['alpha']);
            }
            $rgb = $this->hex_to_rgb($hex);
            if(is_wp_error($rgb)){
                return $rgb;
            }
            [$r, $g, $b] = $rgb;
            $alpha = max(0, min(1, $alpha));
            return "rgba($r, $g, $b, $alpha)";
        }

		// ---------------------------------------------------------------------

        /**
         * Converts a hsl value into the rgb equivalent.
         *
    	 * @return array|WP_Error
    	 */
        public function hsl_to_rgb($h = 0, $s = 0, $l = 0){
            if(!is_numeric($h) || !is_numeric($s) || !is_numeric($l)){
                return $this->invalid_params(['h', 's', 'l']);
            }
            $h = fmod(fmod($h, 360) + 360, 360) / 360;
            $s = max(0, min(1, $s));
            $l = max(0, min(1, $l));
            if($s == 0){
                $v = (int) round($l * 255);
                return [$v, $v, $v];
            }
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;
            $channels = [$h + 1 / 3, $h, $h - 1 / 3];
            $rgb = [];
            foreach($channels as $channel){
                if($channel < 0){
                    $channel += 1;
                } elseif($channel > 1){
                    $channel -= 1;
                }
                if($channel < 1 / 6){
                    $value = $p + ($q - $p) * 6 * $channel;
                } elseif($channel < 1 / 2){
                    $value = $q;
                } elseif($channel < 2 / 3){
                    $value = $p + ($q - $p) * (2 / 3 - $channel) * 6;
                } else {
                    $value = $p;
                }
                $rgb[] = (int) round($value * 255);
            }
            return $rgb;
        }

		// ---------------------------------------------------------------------

        /**
         * Mixes two hex colors.
         *
    	 * @return string|WP_Error
    	 */
        public function mix_hex($hex_a = '', $hex_b = '', $weight_b = 0.5){
            if(!is_numeric($weight_b)){
                return $this->invalid_params(['weight_b']);
            }
            $rgb_a = $this->hex_to_rgb($hex_a);
            if(is_wp_error($rgb_a)){
                return $rgb_a;
            }
            $rgb_b = $this->hex_to_rgb($hex_b);
            if(is_wp_error($rgb_b)){
                return $rgb_b;
            }
            [$ar, $ag, $ab] = $rgb_a;
            list($br, $bg, $bb) = $rgb_b;
            $weight_b = max(0, min(1, $weight_b));
            $weight_a = 1 - $weight_b;
            $r = (int) round($ar * $weight_a + $br * $weight_b);
            $g = (int) round($ag * $weight_a + $bg * $weight_b);
            $b = (int) round($ab * $weight_a + $bb * $weight_b);
            return $this->rgb_to_hex($r, $g, $b);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function normalize_hex($hex = ''){
            $hex = trim($hex);
            if(isset($hex[0]) && $hex[0] === '#'){
                $hex = substr($hex, 1);
            }
            if(strlen($hex) === 3){
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            if(strlen($hex) !== 6 || !ctype_xdigit($hex)){
                return $this->invalid_params(['hex']);
            }
            return '#' . strtolower($hex);
        }

		// ---------------------------------------------------------------------

        /**
         * Converts a rgb value into the hex equivalent.
         *
    	 * @return string|WP_Error
    	 */
        public function rgb_to_hex($r = 0, $g = 0, $b = 0){
            if(!is_numeric($r) || !is_numeric($g) || !is_numeric($b)){
                return $this->invalid_params(['r', 'g', 'b']);
            }
            $r = (int) round(max(0, min(255, $r)));
            $g = (int) round(max(0, min(255, $g)));
            $b = (int) round(max(0, min(255, $b)));
            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

		// ---------------------------------------------------------------------

        /**
         * Converts a rgb value into the hsl equivalent.
         *
    	 * @return array|WP_Error
    	 */
        public function rgb_to_hsl($r = 0, $g = 0, $b = 0){
            if(!is_numeric($r) || !is_numeric($g) || !is_numeric($b)){
                return $this->invalid_params(['r', 'g', 'b']);
            }
            $r = max(0, min(255, $r)) / 255;
            $g = max(0, min(255, $g)) / 255;
            $b = max(0, min(255, $b)) / 255;
            $max = max($r, $g, $b);
            $min = min($r, $g, $b);
            $h = 0;
            $s = 0;
            $l = ($max + $min) / 2;
            if($max !== $min){
                $d = $max - $min;
                $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
                if($max === $r){
                    $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                } elseif($max === $g){
                    $h = ($b - $r) / $d + 2;
                } else {
                    $h = ($r - $g) / $d + 4;
                }
                $h *= 60;
            }
            return [$h, $s, $l];
        }

		// ---------------------------------------------------------------------

        /**
         * Shifts the hsl lightness of a hex color.
         *
    	 * @return string|WP_Error
    	 */
        public function shift_hex_lightness($hex = '', $delta = 0){
            if(!is_numeric($delta)){
                return $this->invalid_params(['delta']);
            }
            $rgb = $this->hex_to_rgb($hex);
            if(is_wp_error($rgb)){
                return $rgb;
            }
            [$r, $g, $b] = $rgb;
            list($h, $s, $l) = $this->rgb_to_hsl($r, $g, $b);
            $l = max(0, min(1, $l + $delta));
            list($r, $g, $b) = $this->hsl_to_rgb($h, $s, $l);
            return $this->rgb_to_hex($r, $g, $b);
        }

		// ---------------------------------------------------------------------
        //
		// Conditional Tags
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is($suffix = ''){
    		$name = 'is_' . $suffix;
    		if(!isset($GLOBALS[$name])){
    			return false;
    		}
    		return (bool) $GLOBALS[$name];
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_auto_draft($post = null){
    		return get_post_status($post) === 'auto-draft';
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_debug_enabled(){
    		return defined('WP_DEBUG') && WP_DEBUG;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_doing_heartbeat(){
    		return wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] === 'heartbeat';
    	}

		// ---------------------------------------------------------------------

    	/**
         * Like is_email(), but for domains.
         *
    	 * @return bool
    	 */
    	public function is_domain($domain = ''){
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
            if(count($subs) < 2){
                return false; // A domain needs at least two labels, like example.com.
            }
            foreach($subs as $sub){
                if(trim($sub, " \t\n\r\0\x0B-") !== $sub){
                    return false; // Test for leading and trailing hyphens and whitespace.
                }
                if(!preg_match('/^[a-z0-9-]+$/i', $sub)){
                    return false;
                } // Test for invalid characters.
            } // Loop through each sub.
            return true; // Congratulations, your domain made it!
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_extension_allowed($extension = ''){
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

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_false($data = ''){
    		return in_array((string) $data, ['0', 'false', 'off'], true);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_front(){
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
            if($this->is_xmlrpc()){
                return false; // The current request is a WordPress XML-RPC request.
            }
            if(wp_is_xml_request() || (isset($wp_query) && (is_feed() || is_comment_feed() || is_trackback()))){
                return false; // The current request is an XML request, or is expecting an XML response.
            }
            return true;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_mysql_date($subject = ''){
    		return preg_match('/^\d{4}\-(0[1-9]|1[0-2])\-(0[1-9]|[12]\d|3[01]) ([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/', $subject);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return int
         */
        public function is_post_edit($post_type = ''){
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

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function is_post_list($post_type = ''){
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

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function is_post_new($post_type = ''){
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

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_revision_or_auto_draft($post = null){
    		return wp_is_post_revision($post) || $this->is_auto_draft($post);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_slugified($str = ''){
    		return is_string($str) && preg_match('/^[a-z0-9_-]+$/', $str) === 1;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_true($data = ''){
    		return in_array((string) $data, ['1', 'on', 'true'], true);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * Like wp_http_validate_url(), but only for the string: an http or https
    	 * URL, or a protocol-relative one, with a host and no credentials.
    	 *
    	 * Nothing is looked up. wp_http_validate_url() resolves every host that
    	 * is not the site's own, to refuse private addresses before a request
    	 * is made; this never does, so a host that does not exist, or one on
    	 * the local network, still passes. Use it to tell a URL from something
    	 * else, and wp_http_validate_url() before fetching one.
    	 *
    	 * @return bool
    	 */
    	public function is_url($url = ''){
            if(!is_string($url) || $url === '' || is_numeric($url)){
                return false;
            }
            if(preg_match('/[\x00-\x20\x7f]/', $url)){
                return false; // Whitespace or control characters.
            }
            $parsed_url = wp_parse_url($url);
            if(!$parsed_url || empty($parsed_url['host'])){
                return false;
            }
            if(isset($parsed_url['scheme']) ? !in_array(strtolower($parsed_url['scheme']), ['http', 'https'], true) : strpos($url, '//') !== 0){
                return false; // Only http and https, or protocol-relative.
            }
            if(isset($parsed_url['user']) || isset($parsed_url['pass'])){
                return false;
            }
            $label = '[\p{L}\p{N}_](?:[\p{L}\p{N}_-]*[\p{L}\p{N}_])?'; // One host label: starts and ends with a letter, digit or underscore, hyphens allowed inside.
            return preg_match('/^' . $label . '(?:\.' . $label . ')*\.?$/u', $parsed_url['host']) === 1; // Dot-separated labels, with an optional trailing dot (a root FQDN); no brackets, so no IPv6, as in wp_http_validate_url().
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_valid_filename($filename = '', $mimes = null){
            $filename = $this->basename($filename);
            $filetype = wp_check_filetype($filename, $mimes);
            return $filetype['ext'] ? true : false;
    	}

		// ---------------------------------------------------------------------

    	/**
         * A valid name starts with a letter or underscore, followed by any number of letters, numbers, or underscores.
         *
         * @link https://www.php.net/manual/en/functions.user-defined.php
         *
    	 * @return bool
    	 */
    	public function is_valid_function_name($name = ''){
    		return preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_xmlrpc(){
    		return defined('XMLRPC_REQUEST') && XMLRPC_REQUEST;
    	}

		// ---------------------------------------------------------------------
        //
		// Cookies
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function cookie_get($name = ''){
            $value = isset($_COOKIE[$name]) ? wp_unslash($_COOKIE[$name]) : '';
            return is_string($value) ? $value : '';
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return array
    	 */
        public function cookie_get_hash($name = ''){
            $value = $this->cookie_get($name);
            wp_parse_str($value, $values);
            return $values;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function cookie_remove($name = ''){
            if(!isset($_COOKIE[$name])){
                return false;
            }
            return setcookie($name, ' ', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function cookie_set($name = '', $value = '', $expires = 0){
            return setcookie($name, $value, $expires, COOKIEPATH, COOKIE_DOMAIN, wp_is_home_url_using_https());
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function cookie_set_hash($name = '', $values_obj = [], $expires = 0){
            if(!is_array($values_obj)){
                if(!is_object($values_obj)){
                    return false;
                }
                $values_obj = $this->object_to_array($values_obj);
                if(is_wp_error($values_obj)){
                    return false;
                }
            }
            //return ldc()->cookie_set($name, build_query($values_obj), $expires);
            return $this->cookie_set($name, http_build_query($values_obj), $expires);
        }

		// ---------------------------------------------------------------------
        //
		// Custom pages
        //
		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function custom_interim_login_page($post_id = 0){
            $post = get_post($post_id);
            if(is_null($post) || $post->post_type !== 'page' || $post->post_status !== 'publish'){
                return;
            }
            $this->add_action_once('login_form_login', [$this, '_maybe_redirect_login_form_login']);
            $this->cache('login_forms')->set('interim_login', $post->ID);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function custom_login_page($post_id = 0, $interim_login = 0){
            $post = get_post($post_id);
            if(is_null($post) || $post->post_type !== 'page' || $post->post_status !== 'publish'){
                return;
            }
            $this->add_action_once('login_form_login', [$this, '_maybe_redirect_login_form_login']);
            $this->cache('login_forms')->set('login', $post->ID);
            if(!$interim_login){
                return;
            }
            $this->custom_interim_login_page($post->ID);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function custom_lostpassword_page($post_id = 0){
            $post = get_post($post_id);
            if(is_null($post) || $post->post_type !== 'page' || $post->post_status !== 'publish'){
                return;
            }
            $this->add_action_once('login_form_lostpassword', [$this, '_maybe_redirect_login_form_lostpassword']);
            $this->add_action_once('login_form_retrievepassword', [$this, '_maybe_redirect_login_form_lostpassword']);
            $this->cache('login_forms')->set('lostpassword', $post->ID);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function custom_register_page($post_id = 0){
            $post = get_post($post_id);
            if(is_null($post) || $post->post_type !== 'page' || $post->post_status !== 'publish'){
                return;
            }
            $this->add_action_once('login_form_register', [$this, '_maybe_redirect_login_form_register']);
            $this->cache('login_forms')->set('register', $post->ID);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function custom_resetpass_page($post_id = 0){
            $post = get_post($post_id);
            if(is_null($post) || $post->post_type !== 'page' || $post->post_status !== 'publish'){
                return;
            }
            $this->add_action_once('login_form_resetpass', [$this, '_maybe_redirect_login_form_resetpass']);
            $this->add_action_once('login_form_rp', [$this, '_maybe_redirect_login_form_resetpass']);
            $this->cache('login_forms')->set('resetpass', $post->ID);
        }

		// ---------------------------------------------------------------------

        /**
    	 * Alias for ldc()->custom_lostpassword_page.
    	 *
         * @return void
         */
        public function custom_retrievepassword_page($post_id = 0){
            $this->custom_lostpassword_page($post_id);
        }

		// ---------------------------------------------------------------------

        /**
    	 * Alias for ldc()->custom_resetpass_page.
    	 *
         * @return void
         */
        public function custom_rp_page($post_id = 0){
            $this->custom_resetpass_page($post_id);
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'login_form_login' action hook.
    	 *
         * @return void
         */
        public function _maybe_redirect_login_form_login(){
            if(!doing_action('login_form_login')){
    	        return; // Too early or too late.
    	    }
            $action = isset($_REQUEST['interim-login']) ? 'interim_login' : 'login';
            if(!$this->cache('login_forms')->exists($action)){
                return;
            }
            $post_id = $this->cache('login_forms')->get_int($action);
            $url = get_permalink($post_id);
            if($_GET){
                $_GET = urlencode_deep($_GET); // This re-URL-encodes things that were already in the query string.
                $url = add_query_arg($_GET, $url);
            }
            wp_safe_redirect($url);
            exit;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'login_form_lostpassword' or 'login_form_retrievepassword' action hooks.
    	 *
         * @return void
         */
        public function _maybe_redirect_login_form_lostpassword(){
            if(!doing_action('login_form_lostpassword') && !doing_action('login_form_retrievepassword')){
    	        return; // Too early or too late.
    	    }
            if(!$this->cache('login_forms')->exists('lostpassword')){
                return;
            }
            $post_id = $this->cache('login_forms')->get_int('lostpassword');
            $url = get_permalink($post_id);
            if($_GET){
                $_GET = urlencode_deep($_GET); // This re-URL-encodes things that were already in the query string.
                $url = add_query_arg($_GET, $url);
            }
            wp_safe_redirect($url);
            exit;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'login_form_register' action hook.
    	 *
         * @return void
         */
        public function _maybe_redirect_login_form_register(){
            if(!doing_action('login_form_register')){
    	        return; // Too early or too late.
    	    }
            if(!$this->cache('login_forms')->exists('register')){
                return;
            }
            $post_id = $this->cache('login_forms')->get_int('register');
            $url = get_permalink($post_id);
            if($_GET){
                $_GET = urlencode_deep($_GET); // This re-URL-encodes things that were already in the query string.
                $url = add_query_arg($_GET, $url);
            }
            wp_safe_redirect($url);
            exit;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'login_form_resetpass' or 'login_form_rp' action hooks.
    	 *
         * @return void
         */
        public function _maybe_redirect_login_form_resetpass(){
            if(!doing_action('login_form_resetpass') && !doing_action('login_form_rp')){
    	        return; // Too early or too late.
    	    }
            if(!$this->cache('login_forms')->exists('resetpass')){
                return;
            }
            $post_id = $this->cache('login_forms')->get_int('resetpass');
            $url = get_permalink($post_id);
            if($_GET){
                $_GET = urlencode_deep($_GET); // This re-URL-encodes things that were already in the query string.
                $url = add_query_arg($_GET, $url);
            }
            wp_safe_redirect($url);
            exit;
        }

		// ---------------------------------------------------------------------
        //
		// DataTables
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return array
    	 */
    	public function dtsb_group_to_wpmq_group($group = []){
            if(!$this->is_dtsb_group($group)){
                if(!$this->is_dtsb_node($group)){
                    return [];
                }
                return $this->dtsb_node_to_wpmq_node($group);
            }
            if(isset($group['logic'])){
                $logic = strtoupper($group['logic']);
            } else {
                $logic = 'AND';
            }
            $parts = [
                'relation' => $logic,
            ];
            foreach($group['criteria'] as $criteria){
                if($this->is_dtsb_group($criteria)){
                    $node = $this->dtsb_group_to_wpmq_group($criteria); // Recursive.
                    if($node){
                        $parts[] = $node;
                    }
                } elseif($this->is_dtsb_node($criteria)){
                    $node = $this->dtsb_node_to_wpmq_node($criteria);
                    if($node){
                        $parts[] = $node;
                    }
                }
            }
            return count($parts) > 1 ? $parts : [];
    	}

		// ---------------------------------------------------------------------

        /**
         * @see https://datatables.net/reference/type/SearchBuilder.Criteria
         *
         * @return array
         */
    	public function dtsb_node_to_wpmq_node($node = []){
            if(!$this->is_dtsb_node($node)){
                return [];
            }
            $dt_condition = $node['condition'];
            $dt_data = $node['data'];
            $dt_data_orig = isset($node['dataOrig']) ? $node['dataOrig'] : $dt_data;
            $dt_type = isset($node['type']) ? $node['type'] : 'string';
            $dt_value = isset($node['value']) ? $node['value'] : [];
            $dt_value1 = isset($node['value1']) ? $node['value1'] : (isset($dt_value[0]) ? $dt_value[0] : '');
            $dt_value2 = isset($node['value2']) ? $node['value2'] : (isset($dt_value[1]) ? $dt_value[1] : '');
            $wp_condition = [];
            $wp_key = $dt_data_orig;
            if(in_array($dt_type, ['html-num', 'html-num-fmt', 'num', 'num-fmt'])){
                $wp_type = 'NUMERIC';
            } elseif(in_array($dt_type, ['date', 'luxon', 'moment'])){
                $wp_type = 'DATE';
            } else {
                $wp_type = 'CHAR';
            }
            $wp_value1 = $dt_value1;
            $wp_value2 = $dt_value2;
            if(in_array($dt_type, ['html', 'html-num', 'html-num-fmt'])){
                $wp_value1 = wp_strip_all_tags($wp_value1);
                $wp_value2 = wp_strip_all_tags($wp_value2);
            }
            if($dt_type === 'array'){
                switch($dt_condition){
                    case 'contains':
                        $wp_condition = [
                            'compare' => 'IN',
                            'key' => $wp_key,
                            'value' => [$wp_value1],
                        ];
                        break;
                    case '!contains':
                        $wp_condition = [
                            'compare' => 'NOT IN',
                            'key' => $wp_key,
                            'value' => [$wp_value1],
                        ];
                        break;
                }
            }
            if($wp_condition){
                return $wp_condition;
            }
            switch($dt_condition){
                case 'null':
                case 'empty':
                    $wp_condition = [
                        'relation' => 'OR',
                        [
                            'compare' => 'NOT EXISTS',
                            'key' => $wp_key,
                        ],
                        [
                            'compare' => '=',
                            'key' => $wp_key,
                            'value' => '',
                        ],
                    ];
                    break;
                case '!null':
                case '!empty':
                    $wp_condition = [
                        'compare' => '!=',
                        'key' => $wp_key,
                        'value' => '',
                    ];
                    break;
                case '=':
                case 'equals':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => '=',
                            'key' => $wp_key,
                            'value' => $wp_value1,
                        ];
                    }
                    break;
                case '!=':
                case '!equals':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => '!=',
                            'key' => $wp_key,
                            'value' => $wp_value1,
                        ];
                    }
                    break;
                case 'contains':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => 'LIKE',
                            'key' => $wp_key,
                            'value' => $wp_value1,
                        ];
                    }
                    break;
                case '!contains':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => 'NOT LIKE',
                            'key' => $wp_key,
                            'value' => $wp_value1,
                        ];
                    }
                    break;
                case 'starts':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => 'REGEXP',
                            'key' => $wp_key,
                            'value' => '^' . $wp_value1,
                        ];
                    }
                    break;
                case '!starts':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => 'NOT REGEXP',
                            'key' => $wp_key,
                            'value' => '^' . $wp_value1,
                        ];
                    }
                    break;
                case 'ends':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => 'REGEXP',
                            'key' => $wp_key,
                            'value' => $wp_value1 . '$',
                        ];
                    }
                    break;
                case '!ends':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => 'NOT REGEXP',
                            'key' => $wp_key,
                            'value' => $wp_value1 . '$',
                        ];
                    }
                    break;
                case '>':
                case 'gt':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => '>',
                            'key' => $wp_key,
                            'type' => $wp_type,
                            'value' => $wp_value1,
                        ];
                    }
                    break;
                case '<':
                case 'lt':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => '<',
                            'key' => $wp_key,
                            'type' => $wp_type,
                            'value' => $wp_value1,
                        ];
                    }
                    break;
                case '>=':
                case 'gte':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => '>=',
                            'key' => $wp_key,
                            'type' => $wp_type,
                            'value' => $wp_value1,
                        ];
                    }
                    break;
                case '<=':
                case 'lte':
                    if($wp_value1){
                        $wp_condition = [
                            'compare' => '<=',
                            'key' => $wp_key,
                            'type' => $wp_type,
                            'value' => $wp_value1,
                        ];
                    }
                    break;
                case 'between':
                    if($wp_value1 && $wp_value2){
                        $wp_condition = [
                            'compare' => 'BETWEEN',
                            'key' => $wp_key,
                            'type' => $wp_type,
                            'value' => [$wp_value1, $wp_value2],
                        ];
                    }
                    break;
                case '!between':
                    if($wp_value1 && $wp_value2){
                        $wp_condition = [
                            'compare' => 'NOT BETWEEN',
                            'key' => $wp_key,
                            'type' => $wp_type,
                            'value' => [$wp_value1, $wp_value2],
                        ];
                    }
                    break;
            }
            return $wp_condition;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function is_dtsb_group($thing = []){
            return is_array($thing) && isset($thing['criteria']);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function is_dtsb_node($thing = []){
            return is_array($thing) && isset($thing['condition'], $thing['data']);
        }

		// ---------------------------------------------------------------------
        //
		// DateTime
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @see current_time()
    	 *
    	 * Differs from current_time() in that it will always return a string.
    	 *
    	 * If 'offset_or_tz' parameter is an empty string, the output is adjusted with the GMT offset in the WordPress option.
    	 *
    	 * @return string
    	 */
    	public function current_time($type = 'U', $offset_or_tz = ''){
            if($type === 'timestamp'){
    			$type = 'U';
    		} elseif($type === 'mysql'){
    			$type = 'Y-m-d H:i:s';
    		}
    		$timezone = $offset_or_tz ? $this->timezone($offset_or_tz) : wp_timezone();
    		$datetime = new DateTime('now', $timezone);
    		return $datetime->format($type);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function date_convert($string = '', $fromtz = '', $totz = '', $format = 'Y-m-d H:i:s'){
    		$datetime = date_create($string, $this->timezone($fromtz));
    		if($datetime === false){
    			return gmdate($format, 0);
    		}
            $timezone = $this->timezone($totz);
    		return $datetime->setTimezone($timezone)->format($format);
    	}

		// ---------------------------------------------------------------------

        /**
         * @see populate_options()
         * @see wp-admin/options.php
         *
    	 * @return array
    	 */
    	public function offset_or_tz($offset_or_tz = ''){
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
            if(in_array($offset_or_tz, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC), true)){
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

		// ---------------------------------------------------------------------

        /**
         * @see wp_timezone()
         *
    	 * @return DateTimeZone
    	 */
    	public function timezone($offset_or_tz = ''){
            $timezone = $this->timezone_string($offset_or_tz);
    		return new DateTimeZone($timezone);
    	}

		// ---------------------------------------------------------------------

        /**
         * @see wp_timezone_string()
         *
    	 * @return string
    	 */
    	public function timezone_string($offset_or_tz = ''){
            $offset_or_tz = $this->offset_or_tz($offset_or_tz);
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

		// ---------------------------------------------------------------------
        //
		// Debugging
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
    	public function caller_file($index = 0){
    		$index = $this->positive_int($index);
            $limit = $index + 1;
    		$options = 2; // options: 0: provide args and ignore object, 1: provide args and object, 2: ignore args and object, 3: ignore args and provide object.
            $debug_backtrace = debug_backtrace($options, $limit);
            if(!isset($debug_backtrace[$index])){
                return $this->missing_params(['index ' . $index]);
            }
    		if(!isset($debug_backtrace[$index]['file'])){
                return $this->invalid_params(['index ' . $index]);
            }
    		return $debug_backtrace[$index]['file'];
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return array|WP_error
    	 */
        public function class_context($class = ''){
            if($class === ''){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'class');
                return $this->error($error_msg);
            }
            if(!class_exists($class)){
                return $this->invalid_params('class');
            }
            $reflector = new ReflectionClass($class);
            return $this->reflector_context($reflector);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return array|WP_Error
    	 */
        public function function_context($function = ''){
            if(empty($function)){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'function');
                return $this->error($error_msg);
            }
            if(!function_exists($function)){
                return $this->invalid_params('function');
            }
            $reflector = new ReflectionFunction($function);
            return $this->reflector_context($reflector);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * Polyfill for `get_debug_type()` function added in PHP 8.0.
    	 *
    	 * The type of a value, named the way PHP names types in code: null, bool,
    	 * int, float, string, array, the class of an object, or resource (type).
    	 *
         * @return string
         */
        public function get_debug_type($value = null){
            if(function_exists('get_debug_type')){
                return get_debug_type($value);
            }
            if(is_null($value)){
                return 'null';
            }
            if(is_bool($value)){
                return 'bool';
            }
            if(is_string($value)){
                return 'string';
            }
            if(is_array($value)){
                return 'array';
            }
            if(is_int($value)){
                return 'int';
            }
            if(is_float($value)){
                return 'float';
            }
            if(is_object($value)){
                $class = get_class($value);
                if(strpos($class, '@') === false){
                    return $class;
                }
                $parent = get_parent_class($value); // An anonymous class: named after what it extends or implements.
                if($parent){
                    return $parent . '@anonymous';
                }
                $interfaces = class_implements($value);
                return ($interfaces ? key($interfaces) : 'class') . '@anonymous';
            }
            $type = @get_resource_type($value);
            if(!is_string($type)){
                return 'unknown';
            }
            return 'resource (' . ($type === 'Unknown' ? 'closed' : $type) . ')';
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return array|WP_Error
    	 */
        public function reflector_context($reflector = null){
            if(!$reflector instanceof Reflector){
                $error_msg = __('Invalid object type.');
                return $this->error($error_msg);
            }
            return [
                'file' => $reflector->getFileName(),
                'name' => $reflector->getName(),
                'namespace_name' => $reflector->getNamespaceName(),
                'reflector' => $reflector,
                'short_name' => $reflector->getShortName(),
            ];
    	}

		// ---------------------------------------------------------------------
        //
		// Dependencies
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
    	public function admin_enqueue($handle = '', $src = '', $deps = [], $ver = false, $args_media = null, $l10n = []){
    		return $this->context_enqueue('admin', $handle, $src, $deps, $ver, $args_media, $l10n);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function context_enqueue($context = '', $handle = '', $src = '', $deps = [], $ver = false, $args_media = true, $l10n = []){
            $hook_name = $context . '_enqueue_scripts';
            if(doing_action($hook_name)){
                $this->enqueue_dependency($handle, $src, $deps, $ver, $args_media, $l10n); // Just in time.
                return $handle;
            }
            if(did_action($hook_name)){
    			return $this->doing_it_wrong(__METHOD__); // Too late.
            }
    		if(!in_array($context, ['admin', 'login', 'wp'])){
    			return $this->doing_it_wrong(__METHOD__);
    		}
            if(!$handle){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'handle');
                return $this->error($error_msg);
            }
            $dependency = [
                'args_media' => $args_media,
                'deps' => $deps,
                'handle' => $handle,
    			'l10n' => $l10n,
                'src' => $src,
                'ver' => $ver,
            ];
            $group = $context . '_dependencies';
            $key = $this->md5($dependency);
    		$this->add_action_once($hook_name, [$this, '_maybe_enqueue_' . $context . '_dependencies']);
            $this->cache($group)->set($key, $dependency);
            return $handle;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
    	public function enqueue($handle = '', $src = '', $deps = [], $ver = false, $args_media = null, $l10n = []){
    		return $this->context_enqueue('wp', $handle, $src, $deps, $ver, $args_media, $l10n);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
    	public function enqueue_dependency($handle = '', $src = '', $deps = [], $ver = false, $args_media = null, $l10n = []){
    		if($this->scripts_maybe_doing_it_wrong()){
    			return; // Too early.
    		}
    		if(!$this->is_url($src)){
    			if(!is_file($src)){
    				return; // Dependency src must be a valid URL or file reference.
    			}
    			if(!$ver){
    				$ver = filemtime($src);
    			}
    			$src = $this->path_to_url($src);
    		}
    		$filename = $this->basename($src);
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
                    if(is_null($args_media)){
                        $args_media = 'all';
                    }
    				$this->enqueue_style($handle, $src, $deps, $ver, $args_media);
    				break;
    			case 'js':
                    if(is_null($args_media)){
                        $args_media = [];
                    }
    				$this->enqueue_script($handle, $src, $deps, $ver, $args_media, $l10n);
    				break;
    		}
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
    	public function enqueue_everywhere($handle = '', $src = '', $deps = [], $ver = false, $args_media = true, $l10n = []){
    		if(!doing_action('admin_enqueue_scripts') && !doing_action('login_enqueue_scripts') && !doing_action('wp_enqueue_scripts')){
    			if(did_action('admin_enqueue_scripts') || did_action('login_enqueue_scripts') || did_action('wp_enqueue_scripts')){
    				return $this->doing_it_wrong(__METHOD__); // Too late.
    			}
    		}
    		$this->context_enqueue('admin', $handle, $src, $deps, $ver, $args_media, $l10n);
    		$this->context_enqueue('login', $handle, $src, $deps, $ver, $args_media, $l10n);
    		$this->context_enqueue('wp', $handle, $src, $deps, $ver, $args_media, $l10n);
    		return $handle;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function localize_script($handle = '', $l10n = []){
    		$object_name = $this->str_prefix('l10n', $handle);
    		$result = wp_localize_script($handle, $object_name, $l10n);
            return $result;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
    	public function login_enqueue($handle = '', $src = '', $deps = [], $ver = false, $args_media = null, $l10n = []){
    		return $this->context_enqueue('login', $handle, $src, $deps, $ver, $args_media, $l10n);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
    	public function scripts_maybe_doing_it_wrong(){
            return !did_action('admin_enqueue_scripts') && !did_action('login_enqueue_scripts') && !did_action('wp_enqueue_scripts');
    	}

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'admin_enqueue_scripts' action hook.
    	 *
    	 * @return void
    	 */
    	public function _maybe_enqueue_admin_dependencies(){
    		if(!doing_action('admin_enqueue_scripts')){
    			return; // Too early or too late.
    		}
            $group = 'admin_dependencies';
    		$dependencies = $this->cache($group)->get_group();
    		foreach($dependencies as $dependency){
    			$this->enqueue_dependency($dependency['handle'], $dependency['src'], $dependency['deps'], $dependency['ver'], $dependency['args_media'], $dependency['l10n']);
    		}
    	}

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'wp_enqueue_scripts' action hook.
    	 *
    	 * @return void
    	 */
    	public function _maybe_enqueue_wp_dependencies(){
    		if(!doing_action('wp_enqueue_scripts')){
    			return; // Too early or too late.
    		}
            $group = 'wp_dependencies';
    		$dependencies = $this->cache($group)->get_group();
    		foreach($dependencies as $dependency){
    			$this->enqueue_dependency($dependency['handle'], $dependency['src'], $dependency['deps'], $dependency['ver'], $dependency['args_media'], $dependency['l10n']);
    		}
    	}

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'wp_enqueue_scripts' action hook.
    	 *
    	 * @return void
    	 */
    	public function _maybe_enqueue_login_dependencies(){
    		if(!doing_action('login_enqueue_scripts')){
    			return; // Too early or too late.
    		}
            $group = 'login_dependencies';
    		$dependencies = $this->cache($group)->get_group();
    		foreach($dependencies as $dependency){
    			$this->enqueue_dependency($dependency['handle'], $dependency['src'], $dependency['deps'], $dependency['ver'], $dependency['args_media'], $dependency['l10n']);
    		}
    	}

		// ---------------------------------------------------------------------
        //
		// Errors
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return WP_error
    	 */
        public function doing_it_wrong($function = '', $arguments = []){
            $error_msg = __('Function %1$s was called <strong>incorrectly</strong>. %2$s %3$s');
            $error_msg = sprintf($error_msg, $function, '', '');
    		if(!$function){
    			$error_msg = preg_replace('/\s+/', ' ', $error_msg);
    		}
            $error_msg = wp_strip_all_tags($error_msg);
    		return $this->error($error_msg, $arguments);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return WP_Error
    	 */
        public function empty_param($param = ''){
    		$error_msg = __('The "%s" argument must be a non-empty string.');
    		$error_msg = sprintf($error_msg, $param);
    		if($param){
    			return $this->error($error_msg);
    		}
    		$error_msg = str_replace('""', '', $error_msg);
    		$error_msg = $this->remove_whitespaces($error_msg);
    		return $this->error($error_msg);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return WP_Error
    	 */
        public function invalid_params($params = [], $sort = true){
            $error_msg = __('Invalid parameter(s): %s');
    		if($params){
                $params = (array) $params;
                if($sort){
                    sort($params);
                }
                $error_msg = sprintf($error_msg, $this->implode_and($params));
                $error_msg .= '.';
    			return $this->error($error_msg);
    		}
            $error_msg = sprintf($error_msg, '');
            $error_msg = trim($error_msg);
            $error_msg = str_replace(':', '.', $error_msg);
    		return $this->error($error_msg);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return WP_Error
    	 */
        public function missing_file($file = ''){
            if($file){
                $error_msg = __('File &#8220;%s&#8221; does not exist?');
                $error_msg = sprintf($error_msg, wp_basename($file));
    			return $this->error($error_msg, $file);
    		}
            $error_msg = __('File does not exist?');
    		return $this->error($error_msg);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return WP_Error
    	 */
        public function missing_params($params = [], $sort = true){
            $error_msg = __('Missing parameter(s): %s');
    		if($params){
                $params = (array) $params;
                if($sort){
                    sort($params);
                }
                $error_msg = sprintf($error_msg, $this->implode_and($params));
                $error_msg .= '.';
    			return $this->error($error_msg);
    		}
            $error_msg = sprintf($error_msg, '');
            $error_msg = trim($error_msg);
            $error_msg = str_replace(':', '.', $error_msg);
    		return $this->error($error_msg);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return WP_Error
         */
        public function unexpected_error($data = ''){
            $error_msg = $this->unexpected_error_string();
    		return $this->error($error_msg, $data);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function unexpected_error_string(){
    		$error_msg = __('An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="%s">support forums</a>.');
    		$error_msg = sprintf($error_msg, '#');
    		return $this->first_p($error_msg);
    	}

		// ---------------------------------------------------------------------
        //
		// Files
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function basename($path = '', $suffix = ''){
            $path = $this->remove_query($path);
    		return wp_basename($path, $suffix);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function check_dir($dir = ''){
            if(!$dir){
                $message = __('The "%s" argument must be a non-empty string.');
                $message = sprintf($message, 'dir');
                return $this->error($message);
            }
            if(!@is_dir($dir)){
                $error_msg = __('Destination directory for file streaming does not exist or is not writable.');
                $error = $this->error($error_msg);
                return $error;
            }
            if(!wp_is_writable($dir)){
                $error_msg = __('The %s directory exists but is not writable. This directory is used for plugin and theme updates. Please make sure the server has write permissions to this directory.');
                $error_msg = sprintf($error_msg, $this->relative_path($dir));
                $error_msg = $this->first_p($error_msg);
                return $this->error($error_msg, $dir);
            }
    		return $dir;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see WP_REST_Attachments_Controller::check_upload_size()
    	 *
    	 * @return bool|WP_Error
    	 */
        public function check_upload_size($file_size = 0){
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
    			return $this->error($error_msg);
    		}
    		if($file_size > (KB_IN_BYTES * get_site_option('fileupload_maxk', 1500))){
                $error_msg = __('This file is too big. Files must be less than %s KB in size.');
                $error_msg = sprintf($error_msg, get_site_option('fileupload_maxk', 1500));
    			return $this->error($error_msg);
    		}
    		if(!function_exists('upload_is_user_over_quota')){
    			require_once ABSPATH . 'wp-admin/includes/ms.php'; // Include multisite admin functions to get access to upload_is_user_over_quota().
    		}
    		if(upload_is_user_over_quota(false)){
                $error_msg = __('You have used your space quota. Please delete files before uploading.');
    			return $this->error($error_msg);
    		}
    		return true;
    	}

		// ---------------------------------------------------------------------

        /**
         * @see download_url()
         *
    	 * @return string|WP_Error
    	 */
        public function get_file_sample($file = ''){
            if(!$file){
                $message = __('The "%s" argument must be a non-empty string.');
                $message = sprintf($message, 'file');
                return $this->error($message);
            }
            if(!file_exists($file)){
                return $this->missing_file($file);
            }
            if(!is_file($file)){
    			return $this->invalid_params(['file']);
    		}
    		$tmpf = fopen($file, 'rb'); // Retrieve a sample of the response body for debugging purposes.
    		if(!$tmpf){
    			return '';
    		}
    		$response_size = apply_filters('download_url_error_max_body_size', KB_IN_BYTES); // Filters the maximum error response body size. Default 1 KB.
    		$sample = fread($tmpf, $response_size);
    		fclose($tmpf);
    		return $sample;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function read_file_chunk($handle = null, $chunk_size = 0, $chunk_lenght = 0){
    		$giant_chunk = '';
            if(!is_resource($handle) || !$chunk_size){
                return $giant_chunk;
            }
            $byte_count = 0;
            if(!$chunk_lenght){
                $chunk_lenght = 8 * KB_IN_BYTES;
            }
            while(!feof($handle)){
                $chunk = fread($handle, $chunk_lenght);
                $byte_count += strlen($chunk);
                $giant_chunk .= $chunk;
                if($byte_count >= $chunk_size){
                    break;
                }
            }
    		return $giant_chunk;
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function remove_query($path = ''){
            return preg_replace('/\?.*/', '', $path); // Fix file filename for query strings.
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp_tempnam().
         *
         * Differs from wp_tempnam() in that it uses wp_rand() instead of wp_generate_password(). wp_generate_password() is a pluggable function so is not available prior to the `plugins_loaded` hook firing. This is to ensure against fatal errors when the function is not available. It also preserves the original file extension when possible.
         *
         * @return string
         */
        public function tempnam($filename = '', $dir = ''){
        	if(empty($dir)){
        		$dir = get_temp_dir();
        	}
        	if(empty($filename) || in_array($filename, array('.', '/', '\\'), true)){
        		$filename = uniqid();
        	}
        	$temp_filename = basename($filename); // Use the basename of the given file without the extension as the name for the temporary directory.
        	$temp_filename = preg_replace('|\.[^.]*$|', '', $temp_filename);
        	if(!$temp_filename){
        		return $this->tempnam(dirname($filename), $dir); // If the folder is falsey, use its parent directory name instead.
        	}
        	$temp_filename .= '-' . wp_rand(100000, 999999); // Suffix some random data to avoid filename conflicts.
        	$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); // File extension fix.
        	$temp_filename .= $extension ? ".$extension" : '.tmp'; // Preserve the original file extension when possible.
        	$temp_filename  = wp_unique_filename($dir, $temp_filename);
        	$characters_over_limit = strlen($temp_filename) - 252; // Filesystems typically have a limit of 255 characters for a filename. If the generated unique filename exceeds this, truncate the initial filename and try again. As it's possible that the truncated filename may exist, producing a suffix of "-1" or "-10" which could exceed the limit again, truncate it to 252 instead.
        	if($characters_over_limit > 0){
        		$filename = substr($filename, 0, -$characters_over_limit);
        		return $this->tempnam($filename, $dir);
        	}
        	$temp_filename = $dir . $temp_filename;
        	$fp = @fopen($temp_filename, 'x');
        	if(!$fp && is_writable($dir) && file_exists($temp_filename)){
        		return $this->tempnam($filename, $dir);
        	}
        	if($fp){
        		fclose($fp);
        	}
        	return $temp_filename;
        }

		// ---------------------------------------------------------------------

        /**
         * @see _wp_handle_upload()
         *
    	 * @return bool|WP_Error
    	 */
        public function test_file_error($error = 0){
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
            $error_msg = isset($upload_error_strings[$error]) ? $upload_error_strings[$error] : $this->unexpected_error_string();
    		return $error > 0 ? $this->error($error_msg) : true; // A successful upload will pass this test.
    	}

		// ---------------------------------------------------------------------

        /**
         * @see _wp_handle_upload()
         *
    	 * @return bool|WP_Error
    	 */
        public function test_file_size($file_size = 0){
            $error_msg = is_multisite() ? __('File is empty. Please upload something more substantial.') : sprintf(__('File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your %1$s file or by %2$s being defined as smaller than %3$s in %1$s.'), 'php.ini', 'post_max_size', 'upload_max_filesize');
    		return $file_size === 0 ? $this->error($error_msg) : true; // A non-empty file will pass this test.
    	}

		// ---------------------------------------------------------------------

        /**
         * @see _wp_handle_upload()
         *
    	 * @return string|WP_Error
    	 */
        public function test_file_type($tmp_name = '', $name = '', $mimes = null){
    		$wp_filetype = wp_check_filetype_and_ext($tmp_name, $name, $mimes);
    		$ext = empty($wp_filetype['ext']) ? '' : $wp_filetype['ext'];
    		$type = empty($wp_filetype['type']) ? '' : $wp_filetype['type'];
    		$proper_filename = empty($wp_filetype['proper_filename']) ? '' : $wp_filetype['proper_filename']; // Check to see if wp_check_filetype_and_ext() determined the filename was incorrect.
    		if($proper_filename){
    			$name = $proper_filename;
    		}
    		if((!$type || !$ext) && !current_user_can('unfiltered_upload')){
                $error_msg = __('Sorry, you are not allowed to upload this file type.');
    			return $this->error($error_msg);
    		}
    		return $name; // A correct MIME type will pass this test.
    	}

		// ---------------------------------------------------------------------

        /**
         * @see _wp_handle_upload()
         *
    	 * @return bool|WP_Error
    	 */
        public function test_uploaded_file($tmp_name = ''){
            $error_msg = __('Specified file failed upload test.');
    		return is_uploaded_file($tmp_name) ? true : $this->error($error_msg); // A properly uploaded file will pass this test.
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function unique_filename($dir = '', $filename = ''){
            $filename = wp_basename($filename);
            $path = wp_unique_filename($dir, $filename);
            return path_join($dir, $path);
    	}

		// ---------------------------------------------------------------------
        //
		// Filesystem
        //
		// ---------------------------------------------------------------------

        /**
    	 * @see WP_Filesystem_Direct::copy()
    	 *
    	 * @return string|WP_error
    	 */
        public function copy_file($file = '', $destination = '', $overwrite = false, $mode = false){
    		$fs = $this->get_filesystem_direct();
            if(!$fs->exists($file)){
                return $this->missing_file($file);
            }
    		if(!$fs->is_file($file)){
                return $this->invalid_params(['file']);
            }
            if($fs->is_dir($destination)){
                $filename = wp_basename($file);
                if(!$overwrite){
                    $filename = wp_unique_filename($destination, $filename);
                }
                $destination = path_join($destination, $filename);
            }
            if($fs->copy($file, $destination, $overwrite, $mode)){
                return $destination;
            }
            $error_msg = __('Could not copy file.');
            $error = $this->error($error_msg, $destination);
            return $error;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see WP_Filesystem_Direct::delete()
    	 *
    	 * @return bool|WP_error
    	 */
        public function delete_file($file = ''){
            $fs = $this->get_filesystem_direct();
            if(!$fs->exists($file)){
                return $this->missing_file($file);
            }
            if(!$fs->is_file($file)){
                return $this->invalid_params(['file']);
            }
            if($fs->delete($file, false, 'f')){
                return true;
            }
            $error_msg = __('An error occurred while processing your request. Please try again later.');
            $error_msg = $this->first_p($error_msg);
            $error = $this->error($error_msg, $file);
            return $error;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see WP_Filesystem_Direct::dirlist()
    	 *
    	 * @return array|WP_error
    	 */
        public function dirlist($directory = '', $include_hidden = true, $recursive = false){
            $fs = $this->get_filesystem_direct();
            if(!$fs->is_dir($directory)){
                return $this->invalid_params(['directory']);
            }
            $ret = $fs->dirlist($directory, $include_hidden, $recursive);
            if($ret !== false){
                return $ret;
            }
            $error_msg = __('Directory listing failed.');
            return $this->error($error_msg, $directory);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return array|WP_Error
    	 */
        public function dirlist_files($path = '', $include_hidden = true){
            $ret = $this->dirlist($path, $include_hidden);
    		if(is_wp_error($ret)){
    			return $ret;
    		}
            return array_filter($ret, function($ret){
                return $ret['type'] === 'f';
            });
        }

		// ---------------------------------------------------------------------

        /**
    	 * @see WP_Filesystem_Direct::exists()
    	 *
    	 * @return bool
    	 */
        public function file_exists($file = ''){
            $fs = $this->get_filesystem_direct();
            return $fs->exists($file);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return WP_Filesystem_Direct
    	 */
        public function get_filesystem_direct(){
            static $fs = null;
            if(!is_null($fs)){
                return $fs;
            }
            if(!class_exists('WP_Filesystem_Base')){
                require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
            }
            if(!class_exists('WP_Filesystem_Direct')){
                require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
            }
            $fs = new WP_Filesystem_Direct(false);
            return $fs;
    	}

		// ---------------------------------------------------------------------

        /**
         * Whether the path climbs out of where it points, with `..` as one of its
         * segments: in the middle (`a/../b`), at the end (`a/..`) or on its own.
         * wp_mkdir_p() only looks for `../`, so it lets a trailing `..` through,
         * and it takes a folder named `foo..` for one. Both separators count, the
         * way wp_normalize_path() reads them.
         *
    	 * @see wp_mkdir_p()
    	 *
		 * @return bool
    	 */
		 public function has_path_traversal($target = ''){
			 if(!is_string($target)){
				 return false;
			 }
			 return in_array('..', explode('/', str_replace('\\', '/', $target)), true);
		 }

		// ---------------------------------------------------------------------

        /**
    	 * @see WP_Filesystem_Direct::is_dir()
    	 *
    	 * @return bool
    	 */
        public function is_dir($file = ''){
            $fs = $this->get_filesystem_direct();
            return $fs->is_dir($file);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see WP_Filesystem_Direct::is_file()
    	 *
    	 * @return bool
    	 */
        public function is_file($file = ''){
            $fs = $this->get_filesystem_direct();
            return $fs->is_file($file);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see wp_mkdir_p()
    	 *
    	 * @return string|WP_Error
    	 */
        public function mkdir_p($target = ''){
			$target = is_string($target) ? trim($target) : ''; // Anything else is no target, without trim()'s deprecation notice for null.
            if($target === ''){
                $message = __('The "%s" argument must be a non-empty string.');
                $message = sprintf($message, 'target');
                return $this->error($message);
            }
			$sanitized_target = $this->sanitize_target($target);
			if($this->has_path_traversal($sanitized_target)){
				return $this->invalid_params(['target']); // Do not allow path traversals.
			}
            $group = 'mkdir_p';
    		$key = md5($sanitized_target);
    		if($this->cache($group)->exists($key)){
    			return $this->cache($group)->get($key);
    		}
    		if(!wp_mkdir_p($target)){
                $error_msg = __('Could not create directory.');
                $error = $this->error($error_msg, $target);
                $this->cache($group)->set($key, $error);
    			return $error;
    		}
            $this->cache($group)->set($key, $sanitized_target);
    		return $sanitized_target;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see WP_Filesystem_Direct::move()
    	 *
    	 * @return string|WP_Error
    	 */
        public function move_file($file = '', $destination = '', $overwrite = false){
            $fs = $this->get_filesystem_direct();
            if(!$fs->exists($file)){
                return $this->missing_file($file);
            }
    		if(!$fs->is_file($file)){
                return $this->invalid_params(['file']);
            }
            if($fs->is_dir($destination)){
                $filename = wp_basename($file);
                if(!$overwrite){
                    $filename = wp_unique_filename($destination, $filename);
                }
                $destination = path_join($destination, $filename);
            }
            if($fs->move($file, $destination, $overwrite)){
                return $destination;
            }
            $error_msg = __('Could not move the old version to the %s directory.');
            $error_msg = sprintf($error_msg, $this->relative_path(dirname($destination)));
            $error = $this->error($error_msg, $destination);
            return $error;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see WP_Filesystem_Direct::rmdir()
    	 *
    	 * @return bool|WP_error
    	 */
        public function rmdir($path = '', $recursive = false){
            $fs = $this->get_filesystem_direct();
            if(!$fs->is_dir($path)){
                return $this->invalid_params(['directory']);
            }
            if($fs->delete($path, $recursive, 'd')){
                return true;
            }
            $error_msg = __('The destination directory already exists and could not be removed.');
            $error = $this->error($error_msg, $path);
            return $error;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @see wp_mkdir_p()
    	 *
		 * @return string
    	 */
		 public function sanitize_target($target = ''){
			 $target = is_string($target) ? trim($target) : '';
			 $wrapper = null;
			 if(wp_is_stream($target)){
				 list($wrapper, $target) = explode('://', $target, 2); // Strip the protocol.
			 }
			 $target = str_replace('//', '/', $target); // From php.net/mkdir user contributed notes.
			 if($wrapper !== null){
				 $target = $wrapper . '://' . $target; // Put the wrapper back on the target.
			 }
			 $target = rtrim($target, '/'); // Safe mode fails with a trailing slash under certain PHP versions. Use rtrim() instead of untrailingslashit to avoid formatting.php dependency.
			 if($target === ''){
				 $target = '/';
			 }
			 return $target;
		 }

		// ---------------------------------------------------------------------
        //
		// Font Awesome
        //
		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function enqueue_fontawesome($deps = []){
    		if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $ver = $this->fa_ver();
            $src = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@' . $ver . '/css/all.min.css';
            $this->enqueue('font-awesome', $src, $deps, $ver);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function fa_file_type($post = null, $ver = 7){
            if(get_post_type($post) !== 'attachment'){
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

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function fa_ver($set = ''){
            static $ver = '7.3.0';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function load_fontawesome($deps = []){
    		if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = $this->use_fontawesome();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $src = $this->path_to_url($dir) . '/css/all.min.css';
            $ver = $this->fa_ver();
            $this->enqueue('font-awesome', $src, $deps, $ver);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function use_fontawesome(){
            $ver = $this->fa_ver();
            $url = 'https://github.com/FortAwesome/Font-Awesome/archive/refs/tags/' . $ver . '.zip';
            return $this->use([
                'expected_dir' => 'Font-Awesome-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

		// ---------------------------------------------------------------------
        //
		// GitHub
        //
		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function set_github_api_token($token = ''){
            if(!$token){
                return;
            }
            $key = 'github_api_token';
            $this->add_filter_once('http_request_args', [$this, '_maybe_add_github_api_token'], 10, 2);
            $this->cache()->set($key, $token);
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'http_request_args' filter hook.
    	 *
    	 * @return array
    	 */
        public function _maybe_add_github_api_token($parsed_args, $url){
    		if(!doing_filter('http_request_args')){
    	        return $parsed_args; // Too early or too late.
    	    }
            $key = 'github_api_token';
            if(!$this->cache()->exists($key)){
                return $parsed_args;
            }
            if(!$this->str_starts_with($url, 'https://api.github.com/')){
                return $parsed_args;
            }
            if(!isset($parsed_args['headers'])){
                $parsed_args['headers'] = [];
            }
            $parsed_args['headers']['Authorization'] = 'token ' . $this->cache()->get_string($key);
    		return $parsed_args;
    	}

		// ---------------------------------------------------------------------
        //
		// Google
        //
		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function google_api_php_client_ver($set = ''){
            static $ver = '';
            if($set === ''){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function hide_recaptcha_badge(){
            if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $this->add_inline_style('.grecaptcha-badge{visibility:hidden!important;}');
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool|string
    	 */
        public function is_google_workspace($domain = ''){
            if(!$this->is_domain($domain)){
                if(!is_email($domain)){
        			return false;
        		}
                $domain = $this->get_email_domain($domain);
            }
            $domain = strtolower($domain);
    		if($domain === 'gmail.com'){
    			return 'gmail.com';
    		}
    		if(!getmxrr($domain, $mxhosts)){
    			return false;
    		}
    		if(!in_array('aspmx.l.google.com', $mxhosts)){
    			return false;
    		}
    		return $domain;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function get_recaptcha_branding(){
    		return 'This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" target="_blank">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank">Terms of Service</a> apply.';
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function use_google_api_php_client($ver = ''){
            if($ver){
                $php = '';
                $url = 'https://github.com/googleapis/google-api-php-client/archive/refs/tags/v' . $ver . '.zip';
    		} else {
                if(is_php_version_compatible('8.3')){
                    $php = '8.3';
                    $ver = '2.19.4';
                    $url = 'https://github.com/googleapis/google-api-php-client/releases/download/v' . $ver . '/google-api-php-client--PHP' . $php . '.zip';
                } elseif(is_php_version_compatible('8.1')){
                    $php = '8.1';
                    $ver = '2.19.4';
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
            return $this->use([
                'autoload' => 'vendor/autoload.php',
                'offline' => true,
                'requires_wp' => $php,
                'validation_class' => 'Google\Client',
                'zipball_url' => $url,
            ]);
    	}

		// ---------------------------------------------------------------------
        //
		// Hardcoded
        //
		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function add_hiding_rule($args = []){
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
        	$md5 = $this->md5($args);
        	$uploads_use_yearmonth_folders = false;
            $subdir = $this->unslashit($args['subdir']);
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
            $path = $this->get_shortinit_dir('readfile.php');
            if(!file_exists($path)){
                return;
            }
        	$tmp = str_replace(wp_normalize_path(ABSPATH), '', wp_normalize_path($path));
        	$parts = explode('/', $tmp);
        	$levels = count($parts);
        	$query = $this->path_to_url($path);
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
            $option_name = 'ldc_hiding_rule_' . $md5; // Hardcoded. Must be equal to `$option_name` in `shortinit/readfile.php`.
            update_option($option_name, $value, 'no');
        	$query = add_query_arg($atts, $query);
        	$this->add_external_rule($regex, $query, $args['file']);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function add_inline_script($data = '', $position = 'after'){
            if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $handle = 'ldc'; // Hardcoded.
            return wp_add_inline_script($handle, $data, $position);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function add_inline_style($data = ''){
    		if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
    		$handle = 'ldc'; // Hardcoded.
            return wp_add_inline_style($handle, $data);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function enqueue_script($handle = '', $src = '', $deps = [], $ver = false, $args = [], $l10n = []){
    		if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            if(!is_string($handle)){
                return;
            }
    		$handle = sanitize_title($handle);
            if(!$handle){
                return;
            }
            if(!$this->is_url($src)){
                if(!is_file($src)){
                    return;
                }
                if(!$ver){
                    $ver = filemtime($src);
                }
                $src = $this->path_to_url($src);
            }
            $mimes = [
                'js' => 'application/javascript',
            ];
            if(!$this->is_valid_filename($src, $mimes)){
                return;
            }
            $dep = 'ldc'; // Hardcoded.
            if(!in_array($dep, $deps)){
                $deps[] = $dep;
            }
            wp_enqueue_script($handle, $src, $deps, $ver, $args);
            if($l10n){
                $this->localize_script($handle, $l10n);
            }
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function enqueue_style($handle = '', $src = '', $deps = [], $ver = false, $media = 'all'){
    		if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            if(!is_string($handle)){
                return;
            }
    		$handle = sanitize_title($handle);
            if(!$handle){
                return;
            }
            if(!$this->is_url($src)){
                if(!is_file($src)){
                    return;
                }
                if(!$ver){
                    $ver = filemtime($src);
                }
                $src = $this->path_to_url($src);
            }
            $mimes = [
                'css' => 'text/css',
            ];
            if(!$this->is_valid_filename($src, $mimes)){
                return;
            }
    		$dep = 'ldc'; // Hardcoded.
            if(!in_array($dep, $deps)){
                $deps[] = $dep;
            }
            wp_enqueue_style($handle, $src, $deps, $ver, $media);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return WP_Error
         */
        public function error($message = '', $data = ''){
            if(is_wp_error($message)){
    			return $message;
    		}
    		if($message === ''){
    			$message = __('An error occurred.'); // Something went wrong.
    		}
            return new LDC_Error('ldc_error', $message, $data); // Hardcoded.
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return LDC_Multiton|WP_Error
    	 */
    	public function get_multiton($class = '', ...$args){
    	    if(!$class){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'class');
                return $this->error($error_msg);
    	    }
            if(!class_exists($class)){
                return $this->missing_params($class);
            }
            if(is_subclass_of($class, 'LDC_Multiton')){ // Hardcoded.
                return call_user_func_array([$class, 'get_instance'], $args);
            }
            return $this->invalid_params($class);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function get_root_dir($subdir = ''){
            $target = plugin_dir_path(ABSPATH);
            if(isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== ''){
                $target = plugin_dir_path($_SERVER['DOCUMENT_ROOT']);
            }
            $dir = 'ldc'; // Hardcoded.
            $target = $this->path_join($target, $dir);
            $target = $this->path_join($target, $subdir);
            return $this->mkdir_p($target);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function get_shortinit_dir($subdir = ''){
            $dir = plugin_dir_path(dirname(__FILE__)) . 'shortinit'; // Hardcoded.
            return $this->path_join($dir, $subdir);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return LDC_Singleton|WP_Error
    	 */
    	public function get_singleton($class = ''){
    	    if(!$class){
                $error_msg = __('The "%s" argument must be a non-empty string.');
                $error_msg = sprintf($error_msg, 'class');
                return $this->error($error_msg);
    	    }
            if(!class_exists($class)){
                return $this->missing_params($class);
            }
            if(is_subclass_of($class, 'LDC_Singleton')){ // Hardcoded.
                return call_user_func([$class, 'get_instance']);
            }
            return $this->invalid_params($class);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function get_upload_dir($subdir = ''){
            $upload_dir = wp_get_upload_dir();
    		if($upload_dir['error']){
    			return $this->error($upload_dir['error']);
    		}
            $dir = 'ldc'; // Hardcoded.
            $target = $this->path_join($upload_dir['basedir'], $dir);
            $target = $this->path_join($target, $subdir);
            return $this->mkdir_p($target);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return LDC_Multiton|LDC_Singleton|WP_Error
         */
        public function load_plugin($file = ''){
            if(!$file){
                $file = $this->caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return $file;
                }
            }
            $plugin = $this->plugin($file);
            $file = $plugin->file();
            if($file === ''){
                $error_msg = __('Invalid plugin path.');
                $file = $this->error($error_msg);
                return $file;
            }
            $id = $plugin->id();
            $file = plugin_dir_path($file) . 'load.php'; // Hardcoded.
            if(!file_exists($file)){
                $file = $this->missing_file($file);
                return $file;
            }
            include_once $file; // Load the plugin's main PHP class.
            $class = $this->canonicalize($id);
            $instance = $this->get_singleton($class);
            if(is_wp_error($instance)){
                return $instance;
            }
            return $instance;
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool|WP_Error
         */
        public function load_plugin_tools($file = ''){
            if(!$file){
                $file = $this->caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return $file;
                }
            }
            $plugin = $this->plugin($file);
            $file = $plugin->file();
            if($file === ''){
                $error_msg = __('Invalid plugin path.');
                $file = $this->error($error_msg);
                return $file;
            }
            $id = $plugin->id();
            $invalid = [];
            $missing = [];
            $tools_dir = plugin_dir_path($file) . 'tools'; // Hardcoded.
            foreach(glob($tools_dir . '/*', GLOB_ONLYDIR) as $dir){
                $basename = wp_basename($dir);
                $file = path_join($dir, $basename . '.php');
                if(!file_exists($file)){
                    $missing[] = $basename;
                    continue;
                }
                include_once $file; // Load the tools's main PHP file.
                $class = $this->canonicalize($id . '_' . $basename);
                $instance = $this->get_singleton($class); // Instantiate the tools's main PHP class.
                if(is_wp_error($instance)){
                    $invalid[] = $basename;
                }
            }
            if($missing){
                return $this->missing_params($missing);
            }
            if($invalid){
                return $this->invalid_params($invalid);
            }
            return true;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return string|WP_Error
         */
        public function zoom_get_access_token(){
            $transient = 'ldc_zoom_oauth_token'; // Hardcoded.
            $access_token = get_transient($transient);
            if($access_token){
                return $access_token;
            }
            $access_token = $this->zoom_request_access_token();
            if(is_wp_error($access_token)){
                return $access_token;
            }
            $expiration = 59 * MINUTE_IN_SECONDS; // The token’s time to live is 1 hour. Reduce it a bit to prevent edge-case timeouts that may happen before the page is fully loaded. See: https://developers.zoom.us/docs/internal-apps/s2s-oauth/
            set_transient($transient, $access_token, $expiration);
            return $access_token;
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return void
         */
        public function _load_dependencies($hook_suffix = ''){
    		if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $file = plugin_dir_path(dirname(__FILE__)) . 'js/ldc.js';
    		$handle = 'ldc'; // Hardcoded.
    		if(file_exists($file)){
                $src = $this->path_to_url($file);
        		$ver = filemtime($file);
        		wp_enqueue_script('stackframe', 'https://cdn.jsdelivr.net/npm/stackframe@1.3.4/stackframe.min.js', [], '1.3.4');
        		wp_enqueue_script('error-stack-parser', 'https://cdn.jsdelivr.net/npm/error-stack-parser@2.1.4/error-stack-parser.min.js', ['stackframe'], '2.1.4');
        		wp_enqueue_script($handle, $src, ['error-stack-parser', 'jquery', 'underscore', 'utils', 'wp-api', 'wp-hooks'], $ver, true);
    			$l10n = [
        			'home_url' => home_url(),
    				'locale' => get_locale(),
        			'mu_plugins_url' => WPMU_PLUGIN_URL,
        			'plugins_url' => WP_PLUGIN_URL,
        			'site_url' => site_url(),
        		];
    			$this->localize_script($handle, $l10n);
    		}
            $file = plugin_dir_path(dirname(__FILE__)) . 'css/ldc.css';
            if(file_exists($file)){
                $src = $this->path_to_url($file);
                $ver = filemtime($file);
                wp_enqueue_style($handle, $src, [], $ver);
            }
    	}

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * This method MUST be called inside the 'after_setup_theme' action hook.
         *
         * @return void
         */
        public function _load_theme_functions(){
            if(!doing_action('after_setup_theme')){
                return; // Too early or too late.
            }
            foreach(wp_get_active_and_valid_themes() as $theme){
				$filename = $theme . '/ldc-methods.php'; // Hardcoded.
                if(file_exists($filename)){
                    require_once($filename); // Load the functions for the active theme, for both parent and child theme if applicable.
                }
            }
        }

		// ---------------------------------------------------------------------
        //
		// Hide
        //
		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function hide_others_media($capability = 'edit_others_posts'){
            $this->cache()->set('hide_others_media', [
                'capability' => $capability,
            ]);
            $this->add_filter_once('ajax_query_attachments_args', [$this, '_maybe_hide_others_media']);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function hide_others_posts($capability = 'edit_others_posts'){
            $this->cache()->set('hide_others_posts', [
                'capability' => $capability,
            ]);
            $this->add_action_once('current_screen', [$this, '_maybe_hide_others_posts_count']);
            $this->add_action_once('pre_get_posts', [$this, '_maybe_hide_others_posts_query_args']);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function hide_the_dashboard($capability = 'edit_posts', $location = ''){
            $this->cache()->set('hide_the_dashboard', [
                'capability' => $capability,
        		'location' => $location,
            ]);
            $this->add_action_once('admin_init', [$this, '_maybe_hide_the_dashboard']);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function hide_the_frontend($capability = 'read', $exclude_special_pages = [], $exclude_other_pages = []){
            $this->cache()->set('hide_the_frontend', [
                'capability' => $capability,
                'exclude_other_pages' => $exclude_other_pages,
        		'exclude_special_pages' => $exclude_special_pages,
            ]);
            $this->add_action_once('template_redirect', [$this, '_maybe_hide_the_frontend']);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function hide_the_rest_api($capability = 'read'){
            $this->cache()->set('hide_the_rest_api', [
                'capability' => $capability,
            ]);
            $this->add_filter_once('rest_authentication_errors', [$this, '_maybe_hide_the_rest_api']);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function hide_the_toolbar($capability = 'edit_posts'){
            $this->cache()->set('hide_the_toolbar', [
                'capability' => $capability,
            ]);
            $this->add_filter_once('show_admin_bar', [$this, '_maybe_hide_the_toolbar']);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function hide_uploads_subdir($subdir = '', $capability = 'edit_others_posts', $exclude_public_media = false, $exclude_other_media = [], $file = ''){
            $args = [
                'capability' => $capability,
                'exclude_other_media' => $exclude_other_media,
                'exclude_public_media' => $exclude_public_media,
                'file' => '',
                'subdir' => $subdir,
            ];
            if($file){
                if(file_exists($file)){
                    $args['file'] = $file;
                }
            } else {
                $file = $this->caller_file(1); // One level above.
                if(!is_wp_error($file)){
                    $args['file'] = $file;
                }
        	}
            return $this->add_hiding_rule($args);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function hide_wp(){
            $this->local_login_header();
            $this->cache()->set('hide_wp', true);
        	$this->add_action_once('admin_init', [$this, '_maybe_hide_wp_from_admin']);
            $this->add_action_once('wp_before_admin_bar_render', [$this, '_maybe_hide_wp_from_admin_bar']);
        }

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function local_login_header(){
            $this->cache()->set('local_login_header', true);
            $this->add_filter_once('login_headertext', [$this, '_maybe_local_login_headertext']);
            $this->add_filter_once('login_headerurl', [$this, '_maybe_local_login_headerurl']);
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'ajax_query_attachments_args' filter hook.
    	 *
         * @return array
         */
        public function _maybe_hide_others_media($query){
            if(!doing_filter('ajax_query_attachments_args')){
    	        return $query; // Too early or too late.
    	    }
            $key = 'hide_others_media';
            if(!$this->cache()->exists($key)){
                return $query;
            }
            $hide_others_media = $this->cache()->get_array($key);
            if(current_user_can($hide_others_media['capability'])){
                return $query;
            }
            $query['author'] = get_current_user_id();
            return $query;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'current_screen' action hook.
    	 *
         * @return void
         */
        public function _maybe_hide_others_posts_count(){
            global $current_screen, $pagenow;
            if(!doing_action('current_screen')){
    	        return; // Too early or too late.
    	    }
            $key = 'hide_others_posts';
            if(!$this->cache()->exists($key)){
                return;
            }
            $hide_others_posts = $this->cache()->get_array($key);
            if($pagenow !== 'edit.php'){
                return;
            }
            if(current_user_can($hide_others_posts['capability'])){
        		return;
        	}
            $this->add_filter_once('views_' . $current_screen->id, [$this, '_maybe_hide_others_posts_count_from_views']);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'views_SCREEN_ID' filter hook.
    	 *
         * @return array
         */
        public function _maybe_hide_others_posts_count_from_views($views){
            $current_filter = current_filter();
            if(!$this->str_starts_with($current_filter, 'views_')){
                return $views; // Too early or too late.
            }
            foreach($views as $index => $view){
                $views[$index] = preg_replace('/ <span class="count">\([0-9]+\)<\/span>/', '', $view);
            }
        	return $views;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'pre_get_posts' action hook.
    	 *
         * @return array
         */
        public function _maybe_hide_others_posts_query_args($query){
            global $pagenow;
            if(!doing_action('pre_get_posts')){
    	        return; // Too early or too late.
    	    }
            $key = 'hide_others_posts';
            if(!$this->cache()->exists($key)){
                return;
            }
            $hide_others_posts = $this->cache()->get_array($key);
            if($pagenow !== 'edit.php'){
                return;
            }
            if(current_user_can($hide_others_posts['capability'])){
        		return;
        	}
            $query->set('author', get_current_user_id());
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'admin_init' action hook.
    	 *
         * @return void
         */
        public function _maybe_hide_the_dashboard(){
            if(!doing_action('admin_init')){
    	        return; // Too early or too late.
    	    }
            if(wp_doing_ajax()){
                return;
            }
            $key = 'hide_the_dashboard';
            if(!$this->cache()->exists($key)){
                return;
            }
            $hide_the_dashboard = $this->cache()->get_array($key);
            if(current_user_can($hide_the_dashboard['capability'])){
                return;
            }
            $location = $this->get_redirect_to($hide_the_dashboard['location']);
            if($location !== false){
                wp_safe_redirect($location);
            	exit;
            }
            $message = __('Sorry, you are not allowed to access this page.');
            $title = __('You need a higher level of permission.');
        	wp_die($message, $title, 403);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'template_redirect' action hook.
    	 *
         * @return void
         */
        public function _maybe_hide_the_frontend(){
            if(!doing_action('template_redirect')){
    	        return; // Too early or too late.
    	    }
            $key = 'hide_the_frontend';
            if(!$this->cache()->exists($key)){
                return;
            }
            $hide_the_frontend = $this->cache()->get_array($key);
            $exclude_other_pages = in_array(get_the_ID(), (array) $hide_the_frontend['exclude_other_pages']);
        	$exclude_special_pages = ((is_front_page() && in_array('front_page', (array) $hide_the_frontend['exclude_special_pages'])) || (is_home() && in_array('home', (array) $hide_the_frontend['exclude_special_pages'])));
            if($exclude_other_pages || $exclude_special_pages){
                return;
            }
            if(!is_user_logged_in()){
                auth_redirect();
            }
            if(current_user_can($hide_the_frontend['capability'])){
                return;
            }
            $message = __('Sorry, you are not allowed to access this page.');
            $title = __('You need a higher level of permission.');
        	wp_die($message, $title, 403);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'rest_authentication_errors' filter hook.
    	 *
         * @return null|WP_Error
         */
        public function _maybe_hide_the_rest_api($errors = null){
            if(!doing_filter('rest_authentication_errors')){
    	        return $errors; // Too early or too late.
    	    }
            if(!is_null($errors)){
        		return $errors; // WP_Error if authentication error or true if authentication succeeded.
        	}
            $key = 'hide_the_rest_api';
            if(!$this->cache()->exists($key)){
                return $errors;
            }
            $hide_the_rest_api = $this->cache()->get_array($key);
            if(current_user_can($hide_the_rest_api['capability'])){
                return $errors;
            }
            $message = __('You need a higher level of permission.');
            return $this->error($message, [
        		'status' => 401,
        	]);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'show_admin_bar' filter hook.
    	 *
         * @return bool
         */
        public function _maybe_hide_the_toolbar($show = false){
            if(!doing_filter('show_admin_bar')){
    	        return $show; // Too early or too late.
    	    }
            $key = 'hide_the_toolbar';
            if(!$this->cache()->exists($key)){
                return $show;
            }
            $hide_the_toolbar = $this->cache()->get_array($key);
            if(current_user_can($hide_the_toolbar['capability'])){
                return $show;
            }
            return false;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'admin_init' action hook.
    	 *
         * @return void
         */
        public function _maybe_hide_wp_from_admin(){
            if(!doing_action('admin_init')){
    	        return; // Too early or too late.
    	    }
            $key = 'hide_wp';
            if(!$this->cache()->exists($key)){
                return;
            }
            $hide_wp = $this->cache()->get_bool($key);
        	if(!$hide_wp){
        		return;
        	}
            remove_action('welcome_panel', 'wp_welcome_panel');
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'wp_before_admin_bar_render' action hook.
    	 *
         * @return void
         */
        public function _maybe_hide_wp_from_admin_bar(){
            global $wp_admin_bar;
            if(!doing_action('wp_before_admin_bar_render')){
    	        return; // Too early or too late.
    	    }
            $key = 'hide_wp';
            if(!$this->cache()->exists($key)){
                return;
            }
            $hide_wp = $this->cache()->get_bool($key);
        	if(!$hide_wp){
        		return;
        	}
        	$wp_admin_bar->remove_node('wp-logo');
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'login_headertext' filter hook.
    	 *
         * @return string
         */
        public function _maybe_local_login_headertext($login_header_text = ''){
            if(!doing_filter('login_headertext')){
    	        return $login_header_text; // Too early or too late.
    	    }
            $key = 'local_login_header';
            if(!$this->cache()->exists($key)){
                return;
            }
            $local_login_header = $this->cache()->get_bool($key);
        	if(!$local_login_header){
                return $login_header_text;
        	}
        	return get_option('blogname');
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'login_headerurl' filter hook.
    	 *
         * @return string
         */
        public function _maybe_local_login_headerurl($login_header_url = ''){
            if(!doing_filter('login_headerurl')){
    	        return $login_header_url; // Too early or too late.
    	    }
            $key = 'local_login_header';
            if(!$this->cache()->exists($key)){
                return;
            }
            $local_login_header = $this->cache()->get_bool($key);
        	if(!$local_login_header){
                return $login_header_url;
        	}
            return home_url();
        }

		// ---------------------------------------------------------------------
        //
		// Actions and filters
        //
		// ---------------------------------------------------------------------

        /**
         * @see add_action().
         *
         * @return bool
         */
        public function add_action($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            return $this->on($hook_name, $callback, $priority, $accepted_args);
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function add_action_once($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            return $this->one($hook_name, $callback, $priority, $accepted_args);
        }

		// ---------------------------------------------------------------------

        /**
         * @see add_filter().
         *
         * @return bool
         */
        public function add_filter($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            return $this->on($hook_name, $callback, $priority, $accepted_args);
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function add_filter_once($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            return $this->one($hook_name, $callback, $priority, $accepted_args);
        }

		// ---------------------------------------------------------------------

        /**
         * @see _wp_filter_build_unique_id().
         *
         * Differs from _wp_filter_build_unique_id() in that it will always return a string.
         *
         * @return string
         */
        public function filter_build_unique_id($callback = null){
            $unique_id = _wp_filter_build_unique_id('', $callback, 0);
            return is_null($unique_id) ? '' : $unique_id;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function filter_md5($callback = null){
            if(!$this->is_closure($callback)){
                $idx = $this->filter_build_unique_id($callback);
                $md5 = md5($idx);
                return $md5;
            }
            $md5 = $this->md5($callback);
            return $md5;
        }

		// ---------------------------------------------------------------------

        /**
         * @see remove_action() and remove_filter().
         *
         * @return bool
         */
        public function off($hook_name = '', $callback = null, $priority = 10){
            if(!is_null($callback)){
                $group = 'hooks';
                $md5 = $this->filter_md5($callback);
                $key = $hook_name . '-' . $md5;
                $this->cache($group)->delete($key);
            }
            return remove_filter($hook_name, $callback, $priority);
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function on($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            return add_filter($hook_name, $callback, $priority, $accepted_args);
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function one($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
            if(is_null($callback)){
                return false;
            }
            $group = 'hooks';
            $md5 = $this->filter_md5($callback);
            $key = $hook_name . '-' . $md5;
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get_bool($key);
            }
            $value = add_filter($hook_name, $callback, $priority, $accepted_args);
            $this->cache($group)->set($key, $value);
            return $value;
        }

		// ---------------------------------------------------------------------

        /**
         * @see remove_action().
         *
         * @return bool
         */
        public function remove_action($hook_name = '', $callback = null, $priority = 10){
            return $this->off($hook_name, $callback, $priority);
        }

		// ---------------------------------------------------------------------

        /**
         * @see remove_filter().
         *
         * @return bool
         */
        public function remove_filter($hook_name = '', $callback = null, $priority = 10){
            return $this->off($hook_name, $callback, $priority);
        }

		// ---------------------------------------------------------------------
        //
		// HTTP
        //
		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/functions.php:get_status_header_desc()
         *
    	 * @return string
    	 */
        public function get_status_header_desc($code = 0, $include_code = false){
            $desc = get_status_header_desc($code);
            if($desc !== ''){
                return $desc;
            }
            if($this->is_success($code)){
                $desc = __('SUCCESS: %s');
            } else {
                $desc = __('FAILED: %s');
            }
            if($include_code){
                $desc = sprintf($desc, $code);
            } else {
                $desc = sprintf($desc, '');
                $desc = trim($desc);
                $desc = str_replace(':', '', $desc);
            }
            $desc = strtolower($desc);
            $desc = ucfirst($desc);
            return $desc;
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/rss.php:is_client_error()
         *
    	 * @return bool
    	 */
        public function is_client_error($sc = 0){
            $sc = $this->positive_int($sc);
            return $sc >= 400 && $sc < 500;
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/rss.php:is_error()
         *
    	 * @return bool
    	 */
        public function is_error($sc = 0){
            $sc = $this->positive_int($sc);
            return $sc >= 400 && $sc < 600;
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/rss.php:is_info()
         *
    	 * @return bool
    	 */
        public function is_info($sc = 0){
            $sc = $this->positive_int($sc);
            return $sc >= 100 && $sc < 200;
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/rss.php:is_redirect()
         *
    	 * @return bool
    	 */
        public function is_redirect($sc = 0){
            $sc = $this->positive_int($sc);
            return $sc >= 300 && $sc < 400;
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/rss.php:is_server_error()
         *
    	 * @return bool
    	 */
        public function is_server_error($sc = 0){
            $sc = $this->positive_int($sc);
            return $sc >= 500 && $sc < 600;
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/rss.php:is_success()
         *
    	 * @return bool
    	 */
        public function is_success($sc = 0){
            $sc = $this->positive_int($sc);
            return $sc >= 200 && $sc < 300;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function is_wp_http_request_array($args = []){
            if(!is_array($args) || !$args){
                return false;
            }
            $found = 0;
            $schema = [
                'blocking' => ['bool'],
                'body' => ['array', 'string'],
                'compress' => ['bool'],
                'cookies' => ['array'],
                'decompress' => ['bool'],
                'filename' => ['null', 'string'],
                'headers' => ['array', 'string'],
                'httpversion' => ['string'],
                'limit_response_size' => ['int'],
                'method' => ['string'],
                'redirection' => ['int'],
                'reject_unsafe_urls' => ['bool'],
                'sslcertificates' => ['string'],
                'sslverify' => ['bool'],
                'stream' => ['bool'],
                'timeout' => ['float', 'int'],
                'user-agent' => ['string'],
            ];
            foreach($schema as $key => $types){
                if(!isset($args[$key])){
                    continue;
                }
                $type = $this->get_debug_type($args[$key]);
                if(!in_array($type, $types, true)){
                    continue;
                }
                $found ++;
            }
            if($found === 0 || count($args) !== $found){
                return false;
            }
            return true;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function is_wp_http_response_array($response = []){
            if(!$this->array_keys_exist(['body', 'cookies', 'filename', 'headers', 'response'], $response)){
                return false;
            }
            if(isset($response['http_response']) && !$response['http_response'] instanceof \WP_HTTP_Requests_Response){
                return false;
            }
            return true;
        }

		// ---------------------------------------------------------------------

        /**
         * Returns the LDC_Remote instance for the given arguments, from which the
         * request is made. E.g. ldc()->remote($url, $args)->post($body);
         *
    	 * @return LDC_Remote
    	 */
        public function remote($url = '', $args = []){
            return LDC_Remote::get_instance($url, $args);
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/rest-api/class-wp-rest-request.php:get_content_type()
         *
    	 * @return string
    	 */
        public function remote_retrieve_content_type($response = []){
            $value = $this->remote_retrieve_header($response, 'Content-Type');
            if($value === ''){
                return '';
            }
            $parameters = '';
            if(strpos($value, ';')){
                list($value, $parameters) = explode(';', $value, 2);
            }
            $value = strtolower($value);
            if(!str_contains($value, '/')){
                return '';
            }
            $value = trim($value);
            return $value;
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/http.php:wp_remote_retrieve_header()
         *
    	 * @return string
    	 */
        public function remote_retrieve_header($response = [], $header = ''){
            $value = wp_remote_retrieve_header($response, $header);
            if(is_string($value)){
                return $value;
            }
            $value = implode(',', $value);
            return $value;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return stdClass
    	 */
        public function remote_retrieve_object($response = []){
            $r = new stdClass();
            $r->body = '';
            $r->code = 0;
            $r->content_type = '';
            $r->cookies = [];
            $r->filename = null;
            $r->headers = [];
            $r->json = false;
            $r->json_params = [];
            $r->message = '';
            $r->raw = $response;
    		$r->success = false;
            $r->wp_error = new WP_Error();
            if(is_wp_error($response)){
                $error_data = $response->get_error_data();
                $r->code = is_array($error_data) && isset($error_data['status']) ? $this->positive_int($error_data['status']) : 500; // WP_Http::INTERNAL_SERVER_ERROR
                $r->message = $response->get_error_message();
                $r->wp_error = $response;
                return $r;
            }
            if(!$this->is_wp_http_response_array($response)){
                $r->code = 400; // WP_Http::BAD_REQUEST
                $r->message = __('Invalid data provided.');
                $r->wp_error = $this->error($r->message, [
                    'status' => $r->code,
                ]);
                return $r;
            }
            $r->body = wp_remote_retrieve_body($response);
            $r->code = $this->remote_retrieve_response_code($response);
            $r->content_type = $this->remote_retrieve_content_type($response);
            $r->cookies = wp_remote_retrieve_cookies($response);
            if(isset($response['filename'])){
                $r->filename = $response['filename'];
            }
            $r->headers = wp_remote_retrieve_headers($response);
            $r->json = wp_is_json_media_type($r->content_type);
            $r->json_params = [];
            $r->message = $this->remote_retrieve_response_message($response);
            $r->success = $this->is_success($r->code);
            if(!$r->success){
                $r->wp_error = $this->error($r->message, [
                    'status' => $r->code,
                ]);
                if(!is_null($r->filename)){
                    unlink($r->filename);
                }
                return $r;
            }
            if(!$r->json){
                return $r;
            }
            $json_params = $this->json_decode($r->body, true);
            if(is_wp_error($json_params)){
                $r->code = 500; // WP_Http::INTERNAL_SERVER_ERROR
                $r->message = $json_params->get_error_message();
                $r->success = false;
                $r->wp_error = $json_params;
                if(!is_null($r->filename)){
                    unlink($r->filename);
                }
                return $r;
            }
            $r->json_params = $json_params;
            if($r->success){
                return $r;
            }
            $error = $this->get_wp_error_from_params($json_params);
            if($error->has_errors()){
                $error_data = $error->get_error_data();
                $r->code = is_array($error_data) && isset($error_data['status']) ? $this->positive_int($error_data['status']) : 500; // WP_Http::INTERNAL_SERVER_ERROR
                $r->message = $error->get_error_message();
                $r->wp_error = $error;
                if(!is_null($r->filename)){
                    unlink($r->filename);
                }
                return $r;
            }
            $message = $this->get_message_from_params($json_params);
            if($message === ''){
                return $r;
            }
            $r->message = $message;
            $r->wp_error = $this->error($message, [
                'status' => $r->code,
            ]);
            if(!is_null($r->filename)){
                unlink($r->filename);
            }
            return $r;
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/http.php:wp_remote_retrieve_response_code()
         *
    	 * @return int
    	 */
        public function remote_retrieve_response_code($response = []){
            $code = wp_remote_retrieve_response_code($response);
            if($code !== ''){
                return $code;
            }
            $code = 500; // WP_Http::INTERNAL_SERVER_ERROR
            return $code;
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/http.php:wp_remote_retrieve_response_message()
         *
    	 * @return string
    	 */
        public function remote_retrieve_response_message($response = []){
            $message = wp_remote_retrieve_response_message($response);
            if($message !== ''){
                return $message;
            }
            $code = $this->remote_retrieve_response_code($response);
            $message = $this->get_status_header_desc($code, true);
            return $message;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return int
    	 */
        public function sanitize_timeout($timeout = 0){
            $max_execution_time = function_exists('ini_get') ? $this->positive_int(ini_get('max_execution_time')) : 0; // Some hosts do not allow you to read configuration values.
            $timeout = $this->positive_int($timeout); // Timeout cannot be negative.
            if($max_execution_time > 0 && ($timeout === 0 || $timeout > $max_execution_time)){ // The max_execution_time defaults to 0 when PHP runs from cli.
                $timeout = $max_execution_time - 2; // Reduce it a bit to prevent edge-case timeouts that may happen after the remote request has finished running.
            }
            if($this->cf_is_active() && ($timeout === 0 || $timeout > 118)){ // The Cloudflare’s proxy read timeout is 120 seconds.
                $timeout = 118; // See: https://developers.cloudflare.com/support/troubleshooting/http-status-codes/cloudflare-5xx-errors/error-524/
            }
            return $timeout;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return array
    	 */
        public function sanitize_wp_http_request_array($args = [], $url = ''){
            if(!is_array($args)){
                $args = wp_parse_args($args);
            }
            if(!$args){
                return [];
            }
            if(!$this->is_wp_http_request_array($args)){
                return [
    				'body' => $args,
    			];
            }
            $content_type = $this->remote_retrieve_content_type($args);
            if(isset($args['body']) && $content_type !== '' && wp_is_json_media_type($content_type) && !is_scalar($args['body'])){
                $args['body'] = wp_json_encode($args['body']);
            }
            $url = wp_http_validate_url($url);
            if(!isset($args['cookies']) && $url && wp_validate_redirect($url)){
                $args['cookies'] = $_COOKIE;
            }
            if(isset($args['method'])){
                $args['method'] = $this->sanitize_wp_http_request_method($args['method']);
                if($args['method'] === ''){
                    unset($args['method']);
                }
            }
            if(isset($args['timeout'])){
                $args['timeout'] = $this->sanitize_timeout($args['timeout']);
            }
            if(!isset($args['user-agent']) && isset($_SERVER['HTTP_USER_AGENT'])){
                $args['user-agent'] = $_SERVER['HTTP_USER_AGENT'];
            }
            return $args;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function sanitize_wp_http_request_method($method = ''){
            $method = strtoupper($method);
            if(!in_array($method, ['DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'])){
                return '';
            }
            return $method;
    	}

		// ---------------------------------------------------------------------
        //
		// Image sizes
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function add_4k_image_size(){
    		$this->add_image_size('4K', 3840, 3840);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function add_full_hd_image_size(){
    		$this->add_image_size('Full HD', 1920, 1920);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function add_hd_image_size(){
    		$this->add_image_size('HD', 1280, 1280);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function add_image_size($name = '', $width = 0, $height = 0, $crop = false){
            $default_sizes = ['thumbnail', 'medium', 'medium_large', 'large'];
            $group = 'additional_image_sizes';
    		$key = $this->canonicalize($name);
    		if(in_array($key, $default_sizes)){
    			return; // Does not overwrite the default image sizes.
    		}
    		add_image_size($key, $width, $height, $crop);
            $this->add_filter_once('image_size_names_choose', [$this, '_maybe_add_image_size_names']);
    		$this->cache($group)->set($key, $name);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function add_larger_image_sizes(){
    		$this->add_hd_image_size();
    		$this->add_full_hd_image_size();
    		$this->add_4k_image_size();
    	}

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'image_size_names_choose' filter hook.
    	 *
    	 * @return array
    	 */
        public function _maybe_add_image_size_names($sizes){
    		if(!doing_filter('image_size_names_choose')){
    	        return $sizes; // Too early or too late.
    	    }
            $group = 'additional_image_sizes';
    		$image_sizes = $this->cache($group)->get_group();
    		foreach($image_sizes as $size => $name){
    			$sizes[$size] = $name;
    		}
    		return $sizes;
    	}

		// ---------------------------------------------------------------------
        //
		// Inputmask
        //
		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function inputmask_ver($set = ''){
            static $ver = '5.0.9';
            if(!$set){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function enqueue_inputmask($deps = []){
    		if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $ver = $this->inputmask_ver();
            $src = 'https://cdn.jsdelivr.net/npm/inputmask@' . $ver . '/dist/jquery.inputmask.min.js';
            $this->enqueue('inputmask', $src, $deps, $ver);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function load_inputmask($deps = []){
    		if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = $this->use_inputmask();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $src = $this->path_to_url($dir) . '/dist/jquery.inputmask.min.js';
            $ver = $this->inputmask_ver();
            $this->enqueue('inputmask', $src, $deps, $ver);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function use_inputmask(){
            $ver = $this->inputmask_ver();
            $url = 'https://github.com/RobinHerbots/Inputmask/archive/refs/tags/' . $ver . '.zip';
            return $this->use([
                'expected_dir' => 'Inputmask-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

		// ---------------------------------------------------------------------
        //
		// jQuery Scrollspy
        //
		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function load_jquery_scrollspy($deps = []){
            if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = $this->use_jquery_scrollspy();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $base_path = $this->path_to_url($dir);
            $ver = $this->jquery_scrollspy_ver();
            $this->enqueue('jquery-scrollspy', $base_path . '/scrollspy.js', $deps, $ver);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function jquery_scrollspy_ver($set = ''){
            static $ver = '0.1.3';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function use_jquery_scrollspy(){
            $ver = $this->jquery_scrollspy_ver();
            $url = 'https://github.com/thesmart/jquery-scrollspy/archive/refs/tags/' . $ver . '.zip';
            return $this->use([
    			'expected_dir' => 'jquery-scrollspy-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

		// ---------------------------------------------------------------------
        //
		// JShrink
        //
		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function jshrink_ver($set = ''){
            static $ver = '1.8.1';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function minify_js($js = '', $options = []){
            $dir = $this->use_jshrink();
        	if(is_wp_error($dir)){
        		return $js;
        	}
            return LDC\Deps\JShrink\Minifier::minify($js, $options);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return true|WP_Error
    	 */
        public function use_jshrink(){
            return $this->use_dep('LDC\Deps\JShrink\Minifier');
    	}

		// ---------------------------------------------------------------------
        //
		// JSON
        //
		// ---------------------------------------------------------------------

        /**
         * @return array|bool|null|stdClass|WP_Error
         */
        public function json_decode($json = '', $associative = null, $depth = 512, $flags = 0){
            if(is_null($associative)){
                $associative = $flags & JSON_OBJECT_AS_ARRAY;
            }
            if(is_bool($json) || is_null($json)){
                return $json;
            }
            if(!is_string($json)){
                $message = __('%s parameter must be a valid JSON string.');
                $message = sprintf($message, 'JSON');
                return $this->error($message);
            }
            if($json === ''){
                return $associative ? [] : new stdClass();
            }
            $decoded = json_decode($json, $associative, $depth, $flags);
            //if(is_null($decoded) && json_last_error() !== JSON_ERROR_NONE){
            if(is_null($decoded)){
                $message = json_last_error_msg();
                return $this->error($message);
            }
            return $decoded;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function json_encode($value = null, $flags = 0, $depth = 512){
            $string = wp_json_encode($value, $flags, $depth);
            //if($string === false && json_last_error() !== JSON_ERROR_NONE){
            if($string === false){
                $message = json_last_error_msg();
                return $this->error($message);
            }
            return $string;
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function json_validate($json = '', $depth = 512, $flags = 0){
            if(function_exists('json_validate')){
                $valid = json_validate($json, $depth, $flags);
                //if($valid === false && json_last_error() !== JSON_ERROR_NONE){
                if($valid === false){
                    $message = json_last_error_msg();
                    $this->error($message); // Silence is golden.
                }
                return $valid;
            }
            $value = $this->json_decode($json, null, $depth, $flags);
            return !is_wp_error($value);
        }

		// ---------------------------------------------------------------------
        //
		// Less.php
        //
		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function less_php_ver($set = ''){
            static $ver = '';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function parse_less($css = '', $options = []){
            $dir = $this->use_less_php();
        	if(is_wp_error($dir)){
        		return $dir;
        	}
            $parser = new LDC\Deps\Less_Parser($options); // Less_Autoloader::register() is not needed, ldc()->_register_less_autoloader() covers the prefixed classes.
            try {
                $parser->parse($css);
                $result = $parser->getCss();
            } catch(\Throwable $t){
                $error_msg = str_replace(' in file anonymous-file-0.less in anonymous-file-0.less', '.', $t->getMessage());
                $result = $this->error($error_msg);
            } catch(\Exception $e){
                $error_msg = str_replace(' in file anonymous-file-0.less in anonymous-file-0.less', '.', $e->getMessage());
                $result = $this->error($error_msg);
            }
            return $result;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return true|WP_Error
    	 */
        public function use_less_php(){
            return $this->use_dep('LDC\Deps\Less_Parser');
    	}

		// ---------------------------------------------------------------------
        //
		// MD5
        //
		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function is_md5($string = ''){
            if(!is_string($string)){
                return false;
            }
            return preg_match('/^[a-f0-9]{32}$/', $string);
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function md5($data = '', $more_entropy = false){
            if(is_scalar($data)){
        		return $this->is_md5($data) ? $data : md5($data);
        	}
            if(is_array($data) && !$more_entropy){
                $data = $this->array_ksort_recursive($data);
            }
            if(!$this->is_closure($data)){
                $serialized = serialize($data);
            	return md5($serialized);
            }
            $idx = $this->filter_build_unique_id($data);
            $serialized = $this->closure_serialize($data);
            if(is_wp_error($serialized)){
                $md5 = md5($idx);
                return $md5;
            }
            if($more_entropy){
                $md5 = md5($serialized);
                return $md5;
            }
            $replaced = str_replace($idx, '__less_entropy__', $serialized);
            $md5 = md5($replaced);
            return $md5;
        }

		// ---------------------------------------------------------------------
        //
		// Miscellaneous
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function breadcrumbs($breadcrumbs = [], $separator = ''){
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

		// ---------------------------------------------------------------------

        /**
    	 * @return WP_Role|WP_Error
    	 */
        public function clone_role($source = '', $destination = '', $display_name = ''){
    		$role = get_role($source);
    		if(is_null($role)){
                $error_msg = __('Invalid role.');
    			return $this->error($error_msg, $role);
    		}
            $role_name = $this->canonicalize($destination);
    		return add_role($role_name, $display_name, $role->capabilities);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function current_screen_in($ids = []){
    		global $current_screen;
    		if(!is_array($ids)){
    			return false;
    		}
    		if(!isset($current_screen)){
    			return false;
    		}
    		return in_array($current_screen->id, $ids);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function current_screen_is($id = ''){
    		global $current_screen;
    		if(!is_string($id)){
    			return false;
    		}
    		if(!isset($current_screen)){
    			return false;
    		}
    		return $current_screen->id === $id;
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function custom_login_logo($attachment_id = 0, $half = true){
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
    		$this->add_action_once('login_enqueue_scripts', [$this, '_maybe_replace_login_logo']);
    		$this->cache()->set('custom_login_logo', [$custom_logo[0], $width, $height]);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function delete_options($prefix = ''){
    		global $wpdb;
            if(!$prefix){
                return false; // Nothing to do.
            }
            $like = $wpdb->esc_like($prefix) . '%'; // Escape the LIKE wildcards: an unescaped _ or % in the prefix would match more options than asked for.
            $options = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name LIKE %s", $like));
            if(!$options){
                return false; // Nothing to do.
            }
            foreach($options as $option){
                delete_option($option);
            }
            return true;
    	}

		// ---------------------------------------------------------------------

        /**
         * Only 'filename' and 'timeout' reach the request: download_url() takes no
         * other request arguments, so headers, cookies and the rest are not sent.
         *
    	 * @return string|WP_Error
    	 */
        public function download_url($url = '', $args = [], $root = false, $subdir = ''){
            if(!wp_http_validate_url($url)){
                $message = __('A valid URL was not provided.');
                return $this->error($message);
            }
            $args = $this->sanitize_wp_http_request_array($args, $url);
            $group = 'downloads';
            $key = $this->emulate_uuid($url . '-' . $this->md5($args));
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get($key);
            }
            $subdir = $subdir === '' ? 'downloads' : $this->unslashit($subdir);
            $dir = path_join($subdir, $key);
            $dir = $root ? $this->get_root_dir($dir) : $this->get_upload_dir($dir);
            if(is_wp_error($dir)){
                $this->cache($group)->set($key, $dir);
                return $dir;
            }
            $ret = $this->dirlist_files($dir, false);
            if(is_wp_error($ret)){
                $this->cache($group)->set($key, $ret);
                return $ret;
            }
            if($ret){
                $filename = array_key_first($ret);
                $file = path_join($dir, $filename);
                $this->cache($group)->set($key, $file);
                return $file;
            }
            $filename = isset($args['filename']) ? $args['filename'] : '';
            $timeout = isset($args['timeout']) ? $args['timeout'] : 300;
            if(!function_exists('wp_generate_password')){
    			require_once ABSPATH . WPINC . '/pluggable.php';
    		}
            if(!function_exists('download_url')){
            	require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $file = download_url($url, $timeout);
            if(is_wp_error($file)){
                $this->cache($group)->set($key, $file);
                return $file;
            }
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            if($extension === 'tmp'){
                $filetype = wp_check_filetype_and_ext($file, wp_basename($file));
                if($filetype['proper_filename']){
                    $new_file = $this->unique_filename($dir, wp_basename($filetype['proper_filename']));
                } elseif($this->is_valid_filename($url)){
                    $new_file = $this->unique_filename($dir, $this->basename($url));
                } else {
                    $error = $this->error(__('Sorry, you are not allowed to upload files.'));
                    $this->cache($group)->set($key, $error);
                    $this->delete_file($file);
                    return $error;
                }
            } else {
                $new_file = $this->unique_filename($dir, $file);
            }
            $result = $this->move_file($file, $new_file);
            if(is_wp_error($result)){
                $this->cache($group)->set($key, $result);
                $this->delete_file($file);
                return $result;
            }
            $this->cache($group)->set($key, $new_file);
            return $new_file;
        }

		// ---------------------------------------------------------------------

        /**
         * @return array|WP_Error
         */
        public function exec($command = ''){
            $output = [];
    		if(!function_exists('exec')){
                $error_msg = __('Function %s used incorrectly in PHP.');
                $error_msg = sprintf($error_msg, 'exec');
    			return $this->error($error_msg, $command);
    		}
            try {
                $result = exec($command, $output);
            } catch(\Throwable $t){
                $result = $this->error($t->getMessage());
            } catch(\Exception $e){
                $result = $this->error($e->getMessage());
            }
            return is_wp_error($result) ? $result : $output;
        }

		// ---------------------------------------------------------------------

        /**
         * @see Contact Form 7 wpcf7_format_atts().
    	 *
         * @return string
         */
        public function format_atts($atts = [], $tag = ''){
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

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function format_function($function_name = '', $args = []){
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

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function get_email_domain($email = ''){
            if(!is_email($email)){
                return '';
            }
            list($local, $domain) = explode('@', $email, 2);
    		return $domain;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return int
    	 */
        public function get_memory_size(){
            $command = 'free -b';
    		$output = $this->exec($command);
    		if(is_wp_error($output)){
    			$current_limit = ini_get('memory_limit');
    			$current_limit_int = wp_convert_hr_to_bytes($current_limit);
    			return $current_limit_int;
    		}
    		$output = sanitize_text_field($output[1]);
    		$output = explode(' ', $output);
    		return (int) $output[1];
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function get_message_from_params($thing = null){
            $message = '';
            if(!is_array($thing) && !$thing instanceof stdClass){
                return $message;
            }
            foreach($thing as $key => $value){
                if(strtolower($key) !== 'message'){
                    continue;
                }
                $message = $value;
                break;
            }
            if(!is_string($message)){
                return ''; // In theory could be anything.
            }
            return $message;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return string|false
    	 */
        public function get_redirect_to($fallback = ''){
            $fallback = $fallback !== '' && $this->is_url($fallback) ? $fallback : false;
    		$redirect_to = isset($_REQUEST['redirect_to']) && $this->is_url($_REQUEST['redirect_to']) ? $_REQUEST['redirect_to'] : false; // Only a URL: wp_safe_redirect() is what keeps it on the site.
    		return $redirect_to ? $redirect_to : $fallback;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function get_remote_country($default = ''){
            $cloudflare_country = $this->cf_get_country();
            $wordfence_country = $this->wf_get_country();
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

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function get_remote_ip($default = ''){
            $cloudflare_ip = $this->cf_get_ip();
            $wordfence_ip = $this->wf_get_ip();
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
    		return WP_Http::is_ip_address($ip) ? $ip : '';
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function get_search_stopwords() {
        	$words = explode(',', _x('about,an,are,as,at,be,by,com,for,from,how,in,is,it,of,on,or,that,the,this,to,was,what,when,where,who,will,with,www', 'Comma-separated list of search stopwords in your language'));
        	$stopwords = [];
        	foreach($words as $word){
        		$word = trim($word, "\r\n\t ");
        		if($word){
        			$stopwords[] = $word;
        		}
        	}
        	return apply_filters('wp_search_stopwords', $stopwords);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return int
    	 */
        public function get_the_id_early(){
            if(!$this->is_front()){
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

		// ---------------------------------------------------------------------

        /**
         * @return WP_Error
         */
        public function get_wp_error_from_params($thing = null){
            if(is_wp_error($thing)){
                return $thing;
            }
            $wp_error = new WP_Error();
            if(!is_array($thing) && !$thing instanceof stdClass){
                return $wp_error;
            }
            if(!$this->array_keys_exist(['code', 'data', 'message'], $thing)){
                return $wp_error;
            }
            $wp_error->add($thing['code'], $thing['message'], $thing['data']);
            return $wp_error;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function go_to($str = '', $url = ''){
            if(!$str){
                return '';
            }
            if($this->is_url($url)){
                $str = sprintf('<a href="%s">%s</a>', esc_url($url), $str);
            }
            return trim(str_replace('&larr;', '', sprintf(_x('&larr; Go to %s', 'site'), $str)));
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return array
    	 */
        public function has_shortcode($content = '', $tag = ''){
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
    	        $attr = $this->has_shortcode($shortcode[5], $tag); // Recursive.
    	        if(!$attr){
    	            continue;
    	        }
    	        return $attr;
    	    }
    	    return [];
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function host_url($url = ''){
            $host = wp_parse_url(sanitize_url($url), PHP_URL_HOST);
            if(is_null($host)){
                return '';
            }
            return substr($url, 0, (strpos($url, $host) + strlen($host)));
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function is_callable($callable = '', $num_args = false){
            if(!is_callable($callable)){
                return false;
            }
            if($num_args === false){
                return true;
            }
            $num_args = $this->positive_int($num_args);
            if(is_array($callable)){
                if(!method_exists($callable[0], $callable[1])){
                    return false; // Callable only through __call(), like ldc()->no_such_method(): there are no parameters to count.
                }
                $reflector = new ReflectionMethod($callable[0], $callable[1]);
            } else {
                $reflector = new ReflectionFunction($callable);
            }
            $accepted_args = $reflector->getNumberOfParameters();
            $required_args = $reflector->getNumberOfRequiredParameters();
            return $accepted_args >= $num_args && $required_args <= $num_args;
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function load_admin_textdomain($domain = 'default'){
            if(is_admin()){
                return false;
            }
            $locale = determine_locale();
            $mofile = WP_LANG_DIR . '/admin-' . $locale . '.mo';
            return file_exists($mofile) ? load_textdomain($domain, $mofile, $locale) : false;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function load_front_textdomain($domain = 'default'){
            if(!is_admin()){
                return false;
            }
            $locale = determine_locale();
            $mofile = WP_LANG_DIR . '/' . $locale . '.mo';
            return file_exists($mofile) ? load_textdomain($domain, $mofile, $locale) : false;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
    	public function menu_page_exists($slug = ''){
    		global $menu;
    		if(is_null($menu)){
    			return false;
    		}
    		foreach($menu as $item){
    	        if(isset($item[2]) && $item[2] === $slug){
    	            return true;
    	        }
    	    }
    		return false;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * Useful for returning whether a variable is not empty to filters easily.
    	 *
         * @return bool
         */
        public function not_empty($var = null){
            return !empty($var);
        }

		// ---------------------------------------------------------------------

        /**
    	 * @return array
    	 */
        public function post_type_labels($singular = '', $plural = '', $all = true){
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

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function table($data = [], $headers = [], $args = []){
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
            $html .= '<' . $this->format_atts($atts, 'table') . '>';
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

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function test(){
            die('Hello, World!');
        }

		// ---------------------------------------------------------------------

        /**
         * @return int
         */
        public function unique_id($group = ''){
            static $id_counters = [];
            $group = $this->cache_sanitize_group($group);
            if(!isset($id_counters[$group])){
                $id_counters[$group] = 0;
            }
            $id_counters[$group] ++;
            return $id_counters[$group];
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'login_enqueue_scripts' action hook.
    	 *
         * @return void
         */
        public function _maybe_replace_login_logo(){
            if(!doing_action('login_enqueue_scripts')){
    	        return; // Too early or too late.
    	    }
    		$key = 'custom_login_logo';
    		if(!$this->cache()->exists($key)){
    			return; // Nothing to do.
    		}
    		$value = $this->cache()->get($key);
    		$this->add_inline_style("#login h1 a,.login h1 a{background-image:url('$value[0]');background-size:$value[1]px $value[2]px;height:$value[2]px;width:$value[1]px;}");
        }

		// ---------------------------------------------------------------------
        //
		// Nonces for guests (non logged-in users)
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function create_nonce_guest($action = -1){
            $i = wp_nonce_tick($action);
            $token = $this->get_session_token_guest();
            $uid = 0;
            return substr(wp_hash($i . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function get_session_token_guest($action = -1){
            return $this->base64_urlencode(hash('sha256', $action, true)); // 43 chars just like wp_get_session_token().
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function nonce_url($actionurl = '', $action = -1, $name = '_wpnonce'){
            //$actionurl = str_replace('&amp;', '&', $actionurl);
            return esc_html(add_query_arg($name, wp_create_nonce($action), $actionurl));
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function nonce_url_guest($actionurl = '', $action = -1, $name = '_wpnonce'){
            //$actionurl = str_replace('&amp;', '&', $actionurl);
            return esc_html(add_query_arg($name, $this->create_nonce_guest($action), $actionurl));
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function verify_nonce_guest($nonce = '', $action = -1){
            $nonce = (string) $nonce;
        	if(!$nonce){
        		return false;
        	}
            $i = wp_nonce_tick($action);
            $token = $this->get_session_token_guest();
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

		// ---------------------------------------------------------------------
        //
		// Numbers
        //
		// ---------------------------------------------------------------------

        /**
         * @return int
         */
        public function absint($maybeint = 0){
            if(is_int($maybeint)){
                return abs($maybeint);
            }
            if(!is_numeric($maybeint)){
                return 0; // Make sure the value is numeric to avoid casting objects, for example, to int 1.
            }
            return absint($maybeint);
        }

		// ---------------------------------------------------------------------

        /**
         * @return int
         */
        public function int($maybeint = 0){
            if(is_int($maybeint)){
                return $maybeint;
            }
            if(!is_numeric($maybeint)){
                return 0; // Make sure the value is numeric to avoid casting objects, for example, to int 1.
            }
            $maybeint = (int) $maybeint;
            return $maybeint;
        }

		// ---------------------------------------------------------------------

        /**
         * @return int
         */
        public function negative_int($maybeint = 0){
            if(is_int($maybeint)){
                return $maybeint < 0 ? $maybeint : 0;
            }
            if(!is_numeric($maybeint)){
                return 0; // Make sure the value is numeric to avoid casting objects, for example, to int 1.
            }
            $maybeint = (int) $maybeint;
            return $maybeint < 0 ? $maybeint : 0;
        }

		// ---------------------------------------------------------------------

        /**
         * @return int
         */
        public function positive_int($maybeint = 0){
            if(is_int($maybeint)){
                return $maybeint > 0 ? $maybeint : 0;
            }
            if(!is_numeric($maybeint)){
                return 0; // Make sure the value is numeric to avoid casting objects, for example, to int 1.
            }
            $maybeint = (int) $maybeint;
            return $maybeint > 0 ? $maybeint : 0;
        }

		// ---------------------------------------------------------------------
        //
		// Objects
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function object_properties_exist($properties = [], $object_or_class = null){
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

		// ---------------------------------------------------------------------

        /**
    	 * @return array|WP_Error
    	 */
        public function object_to_array($data = null){
    		if(!is_object($data)){
                $error_msg = __('Invalid data provided.');
    			return $this->error($error_msg, $data);
    		}
            $object_vars = get_object_vars($data);
    		return $this->json_decode(wp_json_encode($object_vars), true);
    	}

		// ---------------------------------------------------------------------
        //
		// Packages
        //
		// ---------------------------------------------------------------------

        /**
         * Lists the versions of the libraries bundled with the plugin, to tell when
         * one is out of date.
         *
         * The ones under `vendor-prefixed` are the packages Composer installed, at the
         * version it recorded, the way `composer show` prints it. The root package,
         * and the packages only provided by another one, have no version of their
         * own and are left out. The ones under `vendor-local` did not go through
         * Composer, so they are listed by folder, at the latest release in their
         * CHANGELOG.md, or the version in the title of their README.md when they
         * have no changelog, or an empty string when there is none to read.
         * E.g. ['cloudinary/cloudinary_php' => '3.1.3', …, 'simplehtmldom' => '1.9.1'].
         *
         * To see which have a newer release, run `composer outdated` where the
         * libraries are installed and prefixed.
         *
    	 * @return array|WP_Error
    	 */
        public function dep_versions(){
            $loaded = $this->use_dep('LDC\Deps\Composer\InstalledVersions');
            if(is_wp_error($loaded)){
                return $loaded;
            }
            $root = LDC\Deps\Composer\InstalledVersions::getRootPackage();
            $versions = [];
            foreach(LDC\Deps\Composer\InstalledVersions::getInstalledPackages() as $name){
                $version = LDC\Deps\Composer\InstalledVersions::getPrettyVersion($name);
                if($name === $root['name'] || $version === null){
                    continue; // The root package, or one only provided by another package.
                }
                $versions[$name] = $version;
            }
            foreach(glob($this->_local_dir() . '/*', GLOB_ONLYDIR) ?: [] as $dir){
                $versions[wp_basename($dir)] = $this->_local_version($dir);
            }
            return $versions;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function use($atts = []){
            $wp_http_request_array = [];
            $wp_http_request_array_keys = ['blocking', 'body', 'compress', 'cookies', 'decompress', 'filename', 'headers', 'httpversion', 'limit_response_size', 'method', 'redirection', 'reject_unsafe_urls', 'sslcertificates', 'sslverify', 'stream', 'timeout', 'user-agent'];
            foreach($wp_http_request_array_keys as $wp_http_request_array_key){
                if(isset($atts[$wp_http_request_array_key])){
                    $wp_http_request_array[$wp_http_request_array_key] = $atts[$wp_http_request_array_key];
                    unset($atts[$wp_http_request_array_key]);
                }
            }
            if(!$this->is_wp_http_request_array($wp_http_request_array)){
                $wp_http_request_array = [];
            }
            $pairs = [
                'autoload' => '',
                'expected_dir' => '',
                'offline' => false,
                'requires_php' => '',
                'requires_wp' => '',
                'strict_validation' => false,
                'validation_class' => '',
                'validation_file' => '',
                'validation_function' => '',
                'zipball_url' => '',
            ];
            $atts = shortcode_atts($pairs, $atts);
            $group = 'used_packages';
            $key = $this->emulate_uuid($atts);
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get($key);
            }
            if(!$atts['zipball_url']){
                $error_msg = __('No URL Provided.');
                $error = $this->error($error_msg);
                $this->cache($group)->set($key, $error);
                return $error;
        	}
            if(!wp_http_validate_url($atts['zipball_url'])){
                $error_msg = __('A valid URL was not provided.');
                $error = $this->error($error_msg, $atts['zipball_url']);
                $this->cache($group)->set($key, $error);
                return $error;
            }
            if($atts['requires_php']){
                if(!is_php_version_compatible($atts['requires_php'])){
                    $error_msg = __('This update does not work with your version of PHP.');
                    $error = $this->error($error_msg, $atts['requires_php']);
                    $this->cache($group)->set($key, $error);
                    return $error;
                }
            }
            if($atts['requires_wp']){
                if(!is_wp_version_compatible($atts['requires_wp'])){
                    $error_msg = __('<strong>Error:</strong> This plugin <strong>requires a newer version of WordPress</strong>.');
                    $error = $this->error($error_msg, $atts['requires_wp']);
                    $this->cache($group)->set($key, $error);
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
                        $error = $this->invalid_params($invalid);
                        $this->cache($group)->set($key, $error);
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
                        $error = $this->invalid_params($invalid);
                        $this->cache($group)->set($key, $error);
                        return $error;
                    }
                }
            }
            $dir = $this->use_package($atts['zipball_url'], $wp_http_request_array, $atts['offline']);
            if(is_wp_error($dir)){
                return $dir;
            }
            $expected_dir = $dir;
            if($atts['expected_dir']){
                $expected_dir = $this->path_join($dir, $atts['expected_dir']);
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
                    $file = $this->path_join($expected_dir, $path);
                    if(!file_exists($file)){
                        $missing[] = $path;
                    }
                }
                if($missing){
                    $error = $this->missing_params($missing);
                    $this->cache($group)->set($key, $error);
                    return $error;
                }
            }
            if($atts['autoload']){
                $missing = [];
                $autoload = (array) $atts['autoload'];
                foreach($autoload as $path){
                    $file = $this->path_join($expected_dir, $path);
                    if(file_exists($file)){
                        require_once $file;
                    } else {
                        $missing[] = $path;
                    }
                }
                if($missing){
                    $error = $this->missing_params($missing);
                    $this->cache($group)->set($key, $error);
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
                    $error = $this->missing_params($missing);
                    $this->cache($group)->set($key, $error);
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
                    $error = $this->missing_params($missing);
                    $this->cache($group)->set($key, $error);
                    return $error;
                }
            }
            $this->cache($group)->set($key, $expected_dir);
            return $expected_dir;
        }

		// ---------------------------------------------------------------------

        /**
         * Loads the libraries bundled with the plugin.
         *
         * Unlike ldc()->use(), nothing is downloaded or written to disk: the libraries
         * ship inside the plugin, under `vendor-prefixed`, namespaced with
         * the `LDC\Deps\` prefix so they cannot collide with another plugin's copy of
         * the same library. Use ldc()->use_local() for the ones bundled without a
         * prefix, and ldc()->use() for anything that is not bundled.
         *
         * Pass the class, interface or function the caller is about to use and it is
         * verified before returning, e.g. ldc()->use_dep('LDC\Deps\JShrink\Minifier').
         *
    	 * @return true|WP_Error
    	 */
        public function use_dep($validation = ''){
            $group = 'used_dependencies';
            $key = 'autoload';
            if(!$this->cache($group)->exists($key)){
                $this->cache($group)->set($key, $this->_load_deps());
            }
            $loaded = $this->cache($group)->get($key);
            if(is_wp_error($loaded)){
                return $loaded;
            }
            if($validation === ''){
                return true;
            }
            if(class_exists($validation) || interface_exists($validation) || function_exists($validation)){
                return true;
            }
            return $this->missing_params([$validation]);
        }

		// ---------------------------------------------------------------------

        /**
         * Loads a library bundled with the plugin under `vendor-local`: one that
         * did not go through Composer, so it is not prefixed and loads as it
         * ships.
         *
         * Pass the library's folder, the files to load from it — what
         * ldc()->use() takes as 'autoload' — and the class, interface or function
         * the caller is about to use. When that already exists, because another
         * plugin loaded its own copy of the same library, nothing is loaded
         * again: the library is not prefixed, so a second copy would be a fatal
         * redeclaration.
         * E.g. ldc()->use_local('simplehtmldom', 'simple_html_dom.php', 'LDC\Deps\simple_html_dom');
         *
         * Like ldc()->use(), it returns the library's folder, so a CSS or
         * JavaScript library can be enqueued from it too.
         *
		 * @return string|WP_Error
		 */
        public function use_local($dir = '', $autoload = [], $validation = ''){
            if(!is_string($dir) || $dir === ''){
                return $this->invalid_params(['dir']);
            }
            $autoload = (array) $autoload;
            $validation = (array) $validation;
            $group = 'used_local_libraries';
            $key = $this->md5([
                'autoload' => $autoload,
                'dir' => $dir,
                'validation' => $validation,
            ]);
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get($key);
            }
            $path = $this->_local_path($this->_local_dir(), $dir);
            if($path === '' || !is_dir($path)){
                $error = $this->invalid_params(['dir']);
                $this->cache($group)->set($key, $error);
                return $error;
            }
            foreach($validation as $name){
                if(class_exists($name) || interface_exists($name) || function_exists($name)){
                    $this->cache($group)->set($key, $path);
                    return $path; // Already loaded, by this plugin or another one.
                }
            }
            $missing = [];
            foreach($autoload as $relative){
                $file = is_string($relative) ? $this->_local_path($path, $relative) : '';
                if($file === '' || !is_file($file)){
                    $missing[] = is_string($relative) ? $relative : 'autoload';
                    continue;
                }
                require_once $file;
            }
            if($missing){
                $error = $this->missing_params($missing);
                $this->cache($group)->set($key, $error);
                return $error;
            }
            $missing = [];
            foreach($validation as $name){
                if(!class_exists($name) && !interface_exists($name) && !function_exists($name)){
                    $missing[] = $name;
                }
            }
            if($missing){
                $error = $this->missing_params($missing);
                $this->cache($group)->set($key, $error);
                return $error;
            }
            $this->cache($group)->set($key, $path);
            return $path;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function use_package($url = '', $args = [], $root = false){
            if(!wp_http_validate_url($url)){
                $message = __('A valid URL was not provided.');
                return $this->error($message);
            }
            $args = $this->sanitize_wp_http_request_array($args, $url);
            $group = 'packages';
            $key = $this->emulate_uuid($url . '-' . $this->md5($args));
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get($key);
            }
            $dir = path_join('packages', $key);
            $dir = $root ? $this->get_root_dir($dir) : $this->get_upload_dir($dir);
            if(is_wp_error($dir)){
                $this->cache($group)->set($key, $dir);
                return $dir;
            }
            $ret = $this->dirlist($dir, false);
    		if(is_wp_error($ret)){
                $this->cache($group)->set($key, $ret);
                return $ret;
            }
            if($ret){
                $this->cache($group)->set($key, $dir);
                return $dir;
            }
            $file = $this->download_url($url, $args, $root);
            if(is_wp_error($file)){
                $this->cache($group)->set($key, $file);
                return $file;
            }
            if(!function_exists('unzip_file')){
            	require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $result = unzip_file($file, $dir);
    		if(is_wp_error($result)){
                $this->cache($group)->set($key, $result);
    			return $result;
    		}
            $ret = $this->dirlist($dir, false);
    		if(is_wp_error($ret)){
                $this->cache($group)->set($key, $ret);
                return $ret;
            }
            if($ret){
                $this->cache($group)->set($key, $dir);
                return $dir;
            }
            $result = $this->rmdir($dir);
            if(is_wp_error($result)){
                $this->cache($group)->set($key, $result);
    			return $result;
    		}
            $message = __('Empty archive.');
            $error = $this->error($message);
            $this->cache($group)->set($key, $error);
            return $error;
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Boots the Composer autoloader that ships with the plugin.
         *
         * The generated platform check throws when PHP is older than the bundled
         * libraries require, so it is caught and reported as an error instead of
         * taking the whole site down.
         *
    	 * @return true|WP_Error
    	 */
        public function _load_deps(){
            $file = $this->_deps_dir('scoper-autoload.php');
            if(!file_exists($file)){
                return $this->missing_file($file);
            }
            try {
                require_once $file;
            } catch(\Throwable $t){
                return $this->error($t->getMessage());
            }
            $this->_register_less_autoloader();
            $this->_register_plugin_update_checker_autoloader();
            return true;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Gets the directory the bundled libraries live in.
         *
    	 * @return string
    	 */
        public function _deps_dir($subdir = ''){
            $dir = plugin_dir_path(dirname(dirname(__FILE__))) . 'vendor-prefixed'; // Hardcoded.
            return $this->path_join($dir, $subdir);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Gets the directory the local libraries live in.
         *
		 * @return string
		 */
        public function _local_dir($subdir = ''){
            $dir = plugin_dir_path(dirname(dirname(__FILE__))) . 'vendor-local'; // Hardcoded.
            return $this->path_join($dir, $subdir);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Resolves a path given to ldc()->use_local() against the folder it has to
         * stay in, or an empty string when it leaves that folder or does not
         * exist.
         *
		 * @return string
		 */
        public function _local_path($dir = '', $path = ''){
            if(!is_string($path) || $path === '' || path_is_absolute($path)){
                return '';
            }
            $base = realpath($dir);
            $real = realpath($this->path_join($dir, $path));
            if($base === false || $real === false){
                return '';
            }
            $base = trailingslashit(wp_normalize_path($base));
            $real = wp_normalize_path($real);
            if(strpos(trailingslashit($real), $base) !== 0 || trailingslashit($real) === $base){
                return ''; // Outside the folder, or the folder itself.
            }
            return $real;
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Reads the version of a library under `vendor-local` from its CHANGELOG.md:
         * the first release heading, the way Keep a Changelog writes them
         * (`## [1.9.1] - 2019-10-20`), so `## [Unreleased]` is passed over. A library
         * with no changelog is read from the title of its README.md instead, the
         * first `#` heading, when it names one (`# Simple HTML DOM Parser 1.9.1 — …`).
         * An empty string when neither has a version to read.
         *
		 * @return string
		 */
        public function _local_version($dir = ''){
            $file = $this->path_join($dir, 'CHANGELOG.md'); // Hardcoded.
            if(is_file($file)){
                if(!preg_match('/^## +\[?(v?\d[^\]\s]*)/m', (string) file_get_contents($file), $matches)){
                    return '';
                }
                return $matches[1];
            }
            $file = $this->path_join($dir, 'README.md'); // Hardcoded.
            if(!is_file($file)){
                return '';
            }
            if(!preg_match('/^# +(.*)$/m', (string) file_get_contents($file), $title)){
                return '';
            }
            if(!preg_match('/(?<![\w.])(v?\d+(?:\.\d+)+)(?!\w|\.\d)/', $title[1], $matches)){
                return '';
            }
            return $matches[1];
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Autoloads the less.php classes, which Composer cannot reach.
         *
         * less.php declares flat class names (Less_Parser, Less_Tree_Color) that its
         * own PSR-4 entry never matches, so it ships Less_Autoloader for them — and
         * that autoloader filters by a hardcoded 'Less_' string, which stops
         * matching once the classes are prefixed. This does the same job against the
         * prefixed names.
         *
    	 * @return void
    	 */
        public function _register_less_autoloader(){
            $dir = $this->_deps_dir('wikimedia/less.php/lib/Less'); // Hardcoded.
            $prefix = 'LDC\Deps\Less_'; // Hardcoded. Must match the prefix in composer.json.
            spl_autoload_register(function($class) use ($dir, $prefix){
                if(strpos($class, $prefix) !== 0){
                    return;
                }
                $name = substr($class, strlen($prefix));
                $file = $dir . '/' . str_replace('_', '/', $name) . '.php';
                if(!file_exists($file)){
                    return;
                }
                require_once $file;
            });
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * Autoloads Parsedown and PucReadmeParser, the two classes the Plugin
         * Update Checker keeps in its vendor folder, which its own autoloader
         * cannot reach once they are prefixed.
         *
         * Its autoloader looks for them in the global namespace, or in the
         * library's own namespace when the whole library was moved to another
         * one. The scoper does neither: it puts them in LDC\Deps, so they are
         * never loaded, and the changelog of an update from GitHub — "View
         * details" in wp-admin — is a fatal error. This loads them under the
         * names the scoper gave them.
         *
    	 * @return void
    	 */
        public function _register_plugin_update_checker_autoloader(){
            $dir = $this->_deps_dir('yahnis-elsts/plugin-update-checker/vendor'); // Hardcoded.
            $files = [
                'LDC\Deps\Parsedown' => 'Parsedown.php', // Hardcoded. The prefix must match the one in composer.json.
                'LDC\Deps\PucReadmeParser' => 'PucReadmeParser.php', // Hardcoded.
            ];
            spl_autoload_register(function($class) use ($dir, $files){
                if(!isset($files[$class])){
                    return;
                }
                $file = $dir . '/' . $files[$class];
                if(!file_exists($file)){
                    return;
                }
                require_once $file;
            });
        }

		// ---------------------------------------------------------------------
        //
		// Paths
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_path_in_abspath($path = ''){
            return $this->is_path_in_dir($path, ABSPATH);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_path_in_dir($path = '', $dir = ''){
            $haystack = wp_normalize_path($path);
    		$needle = wp_normalize_path($dir);
    		return $this->str_starts_with($haystack, $needle);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_path_in_document_root($path = ''){
    		if(!isset($_SERVER['DOCUMENT_ROOT']) || $_SERVER['DOCUMENT_ROOT'] === ''){
    			return $this->is_path_in_dir($path, ABSPATH);
    		}
            return $this->is_path_in_dir($path, $_SERVER['DOCUMENT_ROOT']);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_path_in_mu_plugins_dir($path = ''){
            return $this->is_path_in_dir($path, WPMU_PLUGIN_DIR);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_path_in_plugins_dir($path = ''){
            return $this->is_path_in_dir($path, WP_PLUGIN_DIR);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_path_in_themes_dir($path = ''){
            return $this->is_path_in_dir($path, WP_CONTENT_DIR . '/themes');
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_path_in_uploads_dir($path = ''){
            $upload_dir = wp_get_upload_dir();
    		if($upload_dir['error']){
    			$upload_dir = WP_CONTENT_DIR . '/uploads';
    		} else {
                $upload_dir = $upload_dir['basedir'];
            }
    		return $this->is_path_in_dir($path, $upload_dir);
    	}

		// ---------------------------------------------------------------------

        /**
         * @see path_join()
         *
    	 * @return string
    	 */
        public function path_join($base = '', $path = ''){
            if(path_is_absolute($path)){
        		return $path;
        	}
            $base = untrailingslashit($base);
            $path = $this->unslashit($path);
            if(!$base && !$path){
    			return '';
    		}
            if($base && !$path){
                return $base;
            }
            if($path && !$base){
    			return $path;
    		}
            return $base . '/' . $path; // Without trailing slash.
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function path_to_url($path = ''){
            $search = wp_normalize_path(ABSPATH);
            $replace = site_url('/');
            $subject = wp_normalize_path($path);
    		return str_replace($search, $replace, $subject);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function relative_path($path = ''){
            $abspath = wp_normalize_path(ABSPATH);
            $path = wp_normalize_path($path);
            if(!$this->str_starts_with($path, $abspath)){
                return wp_basename($path);
            }
    		return str_replace(ABSPATH, '', $path);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function url_to_path($url = ''){
    	    $site_url = site_url('/');
    		return $this->str_starts_with($url, $site_url) ? str_replace($site_url, wp_normalize_path(ABSPATH), $url) : '';
    	}

		// ---------------------------------------------------------------------
        //
		// PHP_XLSXWriter
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return LDC\Deps\XLSXWriter|WP_Error
    	 */
    	public function xlsx_writer(){
    		$lib = $this->use_xlsxwriter();
    		if(is_wp_error($lib)){
    			return $lib;
    		}
    		return new LDC\Deps\XLSXWriter();
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function xlsxwriter_version($set = ''){
            static $ver = '0.39';
            if($set === ''){
                return $ver; // Get.
            }
            $ver = $set;
            return $ver; // Set.
        }

		// ---------------------------------------------------------------------

    	/**
    	 * @return true|WP_Error
    	 */
    	public function use_xlsxwriter(){
            return $this->use_dep('LDC\Deps\XLSXWriter');
    	}

		// ---------------------------------------------------------------------
        //
		// Plugins
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function add_plugin_action($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return $this->on($hook_name, $callback, $priority, $accepted_args);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function add_plugin_action_once($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return $this->one($hook_name, $callback, $priority, $accepted_args);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function add_plugin_filter($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return $this->on($hook_name, $callback, $priority, $accepted_args);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function add_plugin_filter_once($hook_name = '', $callback = null, $priority = 10, $accepted_args = 1){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return $this->one($hook_name, $callback, $priority, $accepted_args);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return mixed
    	 */
        public function apply_plugin_filters($hook_name = '', $value = null, ...$arg){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return apply_filters($hook_name, $value, ...$arg);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function did_plugin_action($hook_name = ''){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return did_action($hook_name);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function did_plugin_filter($hook_name = ''){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return did_filter($hook_name);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function do_plugin_action($hook_name = '', ...$arg){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    do_action($hook_name, ...$arg);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function do_plugin_action_ref_array($hook_name = '', ...$arg){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    do_action_ref_array($hook_name, ...$arg);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function doing_plugin_action($hook_name = ''){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return doing_filter($hook_name);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function doing_plugin_filter($hook_name = ''){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return doing_filter($hook_name);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function has_plugin_action($hook_name = '', $callback = false){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return has_filter($hook_name, $callback);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function has_plugin_filter($hook_name = '', $callback = false){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return has_filter($hook_name, $callback);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function remove_plugin_action($hook_name = '', $callback = false){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return remove_filter($hook_name, $callback);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function remove_plugin_filter($hook_name = '', $callback = false){
    		$hook_name = $this->_plugin_hook_name($hook_name);
    	    return remove_filter($hook_name, $callback);
    	}

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
     	 * WARNING: This method’s access is marked private.
     	 *
    	 * @return string
    	 */
        public function _plugin_hook_name($hook_name = ''){
    		$file = $this->caller_file(2); // Two levels above.
            if(is_wp_error($file)){
                return $hook_name;
            }
    	    $hook_name = $this->plugin($file)->prefix($hook_name);
    	    return $hook_name;
    	}

		// ---------------------------------------------------------------------
        //
		// Plugin Update Checker
        //
		// ---------------------------------------------------------------------

        /**
    	 * This method MUST be called inside or before the 'plugins_loaded' action hook.
    	 *
    	 * @return Plugin\UpdateChecker|Theme\UpdateChecker|Vcs\BaseChecker|WP_Error
    	 */
        public function build_update_checker($metadata_url = '', $full_path = '', $slug = '', $check_period = 12, $option_name = '', $mu_plugin_file = ''){
            if(did_action('plugins_loaded') && !doing_action('plugins_loaded')){
                return $this->doing_it_wrong(__METHOD__); // Too late.
            }
            if($metadata_url === ''){
                $metadata_url = $this->caller_file(1); // One level above.
                if(is_wp_error($metadata_url)){
                    return $metadata_url;
                }
            }
            if(is_file($metadata_url)){
                $plugin = $this->plugin($metadata_url);
                $full_path = $plugin->file();
                if($full_path === ''){
                    $error_msg = __('Invalid plugin path.');
                    return $this->error($error_msg);
                }
                $metadata_url = $plugin->meta('UpdateURI');
                if(is_wp_error($metadata_url)){
                    return $metadata_url;
                }
                if(!$this->is_url($metadata_url)){
                    $error_msg = __('A valid URL was not provided.');
                    return $this->error($error_msg);
                }
                $slug = $plugin->id();
                if($slug === ''){
                    $error_msg = __('Invalid plugin path.');
                    return $this->error($error_msg);
                }
            }
            $args = compact('metadata_url', 'full_path', 'slug', 'check_period', 'option_name', 'mu_plugin_file');
            $group = 'plugin_update_checkers';
            $key = $this->md5($args);
            $values = array_values($args);
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get($key);
            }
    		$dir = $this->use_plugin_update_checker();
    		if(is_wp_error($dir)){
    			return $dir;
    		}
            $class = $this->plugin_update_checker_class();
            $update_checker = call_user_func_array([$class, 'buildUpdateChecker'], $values);
            if(is_wp_error($update_checker)){
                return $update_checker;
            }
            $constant = strtoupper($this->str_prefix('license', $this->canonicalize($slug)));
            if(defined($constant)){
                $this->set_plugin_update_license($slug, constant($constant));
            }
    		$this->cache($group)->set($key, $update_checker);
            return $update_checker;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function plugin_update_checker_class(){
            $ver = $this->plugin_update_checker_ver();
            $ver = explode('.', $ver);
            $ver = implode('p', $ver);
            return 'LDC\Deps\YahnisElsts\PluginUpdateChecker\v' . $ver . '\PucFactory';
    	}

		// ---------------------------------------------------------------------

        /**
         * The version of the Plugin Update Checker in vendor-prefixed, as major
         * and minor, e.g. '6.0': the two its namespace is named after, v6p0.
         * Read from Composer, so it follows the library when vendor-prefixed is
         * rebuilt. A version set with $set takes its place from then on.
         *
         * @return string Empty when the library is not there.
         */
        public function plugin_update_checker_ver($set = ''){
            static $ver = null;
            if(!empty($set)){
                $ver = $set; // Set.
                return $ver;
            }
            if(!is_null($ver)){
                return $ver; // Get.
            }
            $ver = '';
            if(is_wp_error($this->use_dep('LDC\Deps\Composer\InstalledVersions'))){
                return $ver;
            }
            $package = 'yahnis-elsts/plugin-update-checker'; // Hardcoded.
            if(!LDC\Deps\Composer\InstalledVersions::isInstalled($package)){
                return $ver;
            }
            $pretty_version = (string) LDC\Deps\Composer\InstalledVersions::getPrettyVersion($package); // E.g. v6.0
            if(preg_match('/^v?(\d+)\.(\d+)/', $pretty_version, $m)){
                $ver = $m[1] . '.' . $m[2];
            }
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function set_plugin_update_license($slug = '', $license = ''){
    		if(!$slug || !$license){
    			return;
    		}
            $this->add_filter_once('puc_request_info_query_args-' . $slug, [$this, '_maybe_set_update_license']);
            $group = 'plugin_update_checker_licenses';
            $this->cache($group)->set($slug, $license);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return true|WP_Error
    	 */
        public function use_plugin_update_checker(){
            return $this->use_dep($this->plugin_update_checker_class());
    	}

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'puc_request_info_query_args-SLUG' filter hook.
    	 *
    	 * @return array
    	 */
        public function _maybe_set_update_license($queryArgs){
    		$current_filter = current_filter();
    		if(!$this->str_starts_with($current_filter, 'puc_request_info_query_args-')){
    	        return; // Too early or too late.
    	    }
    		$slug = str_replace('puc_request_info_query_args-', '', $current_filter);
            $group = 'plugin_update_checker_licenses';
    		if(!$this->cache($group)->exists($slug)){
    			return $queryArgs;
    		}
    		$queryArgs['license'] = $this->cache($group)->get($slug);
    		return $queryArgs;
    	}

		// ---------------------------------------------------------------------
        //
		// Plugins
        //
		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function are_plugins_active($plugins = []){
            if(!is_array($plugins)){
                return false;
            }
            $result = true;
            foreach($plugins as $plugin){
                if($this->is_plugin_active($plugin)){
                    continue;
                }
                $result = false;
                break;
            }
            return $result;
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function is_plugin_active($plugin = ''){
            $group = 'active_plugins';
            if($this->cache($group)->exists($plugin)){
                return $this->cache($group)->get($plugin);
            }
            if(!function_exists('is_plugin_active')){
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $status = is_plugin_active($plugin);
            $this->cache($group)->set($plugin, $status);
            return $status;
        }

		// ---------------------------------------------------------------------

        /**
         * @see plugin_basename()
    	 *
    	 * Differs from plugin_basename() in that it will return the basename of a must-use plugin.
    	 *
    	 * @return string
    	 */
        public function mu_plugin_basename($file = ''){
            if(!$file){
                $file = $this->caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return '';
                }
            }
            $group = 'mu_plugin_basename';
            $key = md5($file);
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get_string($key);
            }
            $plugin_basename = $this->is_path_in_mu_plugins_dir($file) ? plugin_basename($file) : '';
            $this->cache($group)->set($key, $plugin_basename);
            return $plugin_basename;
        }

		// ---------------------------------------------------------------------

        /**
    	 * Gets the must-use plugin's main file.
    	 *
    	 * @return string
    	 */
        public function mu_plugin_file($file = ''){
            if(!$file){
                $file = $this->caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return '';
                }
            }
            $group = 'mu_plugin_file';
            $key = md5($file);
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get_string($key);
            }
            $plugin_basename = $this->mu_plugin_basename($file);
            if($plugin_basename === ''){
                $this->cache($group)->set($key, '');
                return '';
            }
            if(strpos($plugin_basename, '/') !== false){
                $this->cache($group)->set($key, '');
                return '';
            }
            $dir = wp_normalize_path(WPMU_PLUGIN_DIR);
            $plugin_file = $dir . '/' . $plugin_basename;
            $this->cache($group)->set($key, $plugin_file);
            return $plugin_file;
        }

		// ---------------------------------------------------------------------

        /**
         * Returns the LDC_Plugin instance for the plugin the given value points at,
         * either a path to any file inside the plugin or the plugin's basename, e.g.
         * 'akismet/akismet.php'. When no value is given, the file this is called
         * from is used instead. E.g. ldc()->plugin($file)->basename();
         *
    	 * @return LDC_Plugin
    	 */
        public function plugin($file = ''){
            return LDC_Plugin::get_instance($file);
        }

		// ---------------------------------------------------------------------

        /**
         * @see plugin_basename()
    	 *
    	 * @return string
    	 */
        public function plugin_basename_raw($file = ''){
            if(!$file){
                $file = $this->caller_file(1); // One level above.
                if(is_wp_error($file)){
                    return '';
                }
            }
            $group = 'plugin_basename_raw';
            $key = md5($file);
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get_string($key);
            }
            $plugin_basename = $this->is_path_in_plugins_dir($file) ? plugin_basename($file) : '';
            $this->cache($group)->set($key, $plugin_basename);
            return $plugin_basename;
        }

		// ---------------------------------------------------------------------

        /**
    	 * Gets the plugin's main file from its basename, e.g. 'akismet/akismet.php'.
    	 *
    	 * Unlike a path, a basename already points at the plugin's main file, so this
    	 * resolves any installed plugin, whether it is active or not.
    	 *
    	 * @return string
    	 */
        public function plugin_basename_to_file($basename = ''){
            if(!is_string($basename) || $basename === ''){
                return '';
            }
            $basename = wp_normalize_path($basename);
            if(!$this->str_ends_with($basename, '.php')){
                return '';
            }
            if(count(explode('/', $basename)) > 2){
                return ''; // A plugin basename is either `file.php` or `dir/file.php`, never a path.
            }
            $group = 'plugin_basename_to_file';
            $key = md5($basename);
            if($this->cache($group)->exists($key)){
                return $this->cache($group)->get_string($key);
            }
            if(!function_exists('get_plugins')){
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugin_file = '';
            if(array_key_exists($basename, get_plugins())){
                $dir = wp_normalize_path(WP_PLUGIN_DIR);
                $plugin_file = $dir . '/' . $basename;
            }
            $this->cache($group)->set($key, $plugin_file);
            return $plugin_file;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function plugin_slug_to_basename($slug = ''){
            $basename = '';
            if(!function_exists('get_plugins')){
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugins = get_plugins();
            foreach($plugins as $name => $plugin){
                if(!$this->str_starts_with($name, $slug . '/')){
                    continue;
                }
                $basename = $name;
                break;
            }
            if(!$basename){
                $error_msg = __('Plugin not found.');
                return $this->error($error_msg);
            }
            return $basename;
    	}

		// ---------------------------------------------------------------------
        //
		// Queries
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return WP_Post|array|null
    	 */
        public function get_post($post = null, $output = OBJECT, $filter = 'raw'){
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

		// ---------------------------------------------------------------------

        /**
    	 * @return WP_Query
    	 */
        public function get_posts_args($args = []){
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

		// ---------------------------------------------------------------------

        /**
    	 * @return WP_Query
    	 */
        public function get_posts_query($args = []){
    		$query = new WP_Query();
    		$query->query($args);
    		return $query;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return int
    	 */
        public function get_the_id(){
            if(!did_action('parse_query')){
                return $this->get_the_id_early();
            }
            if(in_the_loop()){
                return get_the_ID();
            }
    		/*$object = get_queried_object();
    		if(is_null($object)){
    			return 0;
    		}
    		if(!$object instanceof WP_Post){
    			return 0;
    		}*/
            return get_queried_object_id();
        }

		// ---------------------------------------------------------------------

        /**
    	 * Alias for wp_get_current_user(), get_user_by() or get_userdata().
    	 *
    	 * @return bool|WP_User
    	 */
        public function get_user($user = null){
    	    if(is_null($user)){
    	        return is_user_logged_in() ? wp_get_current_user() : false;
    		}
    	    if($user instanceof WP_User){
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
    	    return get_user_by('email', $user); // 2.
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return WP_User_Query
    	 */
        public function get_users_query($args = []){
            $args = wp_parse_args($args, [
    	        'count_total' => false,
    	    ]);
    	    return new WP_User_Query($args);
    	}

		// ---------------------------------------------------------------------
        //
		// Rewrite
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function add_external_rule($regex = '', $query = '', $plugin_file = ''){
    		$rule = [
                'plugin_file' => $plugin_file,
    			'query' => str_replace(site_url('/'), '', $query),
    			'regex' => str_replace(site_url('/'), '', $regex),
    		];
    		if(doing_action('generate_rewrite_rules')){
    			$this->add_action_once('admin_notices', [$this, '_maybe_add_external_rules_notice']);
                $this->maybe_add_external_rule($rule);
    			return; // Just in time.
    		}
    		if(did_action('generate_rewrite_rules')){
    			return; // Too late.
    		}
            $group = 'external_rules';
            $key = $this->md5($rule);
            $this->add_action_once('admin_notices', [$this, '_maybe_add_external_rules_notice']);
            $this->add_action_once('generate_rewrite_rules', [$this, '_maybe_add_external_rules']);
            $this->cache($group)->set($key, $rule);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function external_rule_exists($regex = '', $query = ''){
    		$regex = str_replace('.+?', '.+', $regex); // Apache 1.3 does not support the reluctant (non-greedy) modifier.
    		$rewrite_rules = $this->get_rewrite_rules();
    		$rule = 'RewriteRule ^' . $regex . ' ' . $this->home_root() . $query . ' [QSA,L]';
    		return in_array($rule, $rewrite_rules);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return array
    	 */
        public function get_rewrite_rules(){
            $key = 'rewrite_rules';
            if($this->cache()->exists($key)){
                return $this->cache()->get($key);
            }
            if(!function_exists('get_home_path')){
            	require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if(!function_exists('extract_from_markers')){
            	require_once ABSPATH . 'wp-admin/includes/misc.php';
            }
    		$rewrite_rules = array_filter(extract_from_markers(get_home_path() . '.htaccess', 'WordPress'));
    		$this->cache()->set($key, $rewrite_rules);
    		return $rewrite_rules;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
        public function home_root(){
    		$home_root = parse_url(home_url());
    		if(isset($home_root['path'])){
    			$home_root = trailingslashit($home_root['path']);
    		} else {
    			$home_root = '/';
    		}
    		return $home_root;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool
    	 */
        public function is_external_rule($rule = []){
    	    return $this->array_keys_exist(['query', 'regex'], $rule);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * This method MUST be called inside the 'generate_rewrite_rules' action hook.
    	 *
    	 * @return void
    	 */
        public function maybe_add_external_rule($rule = []){
    		global $wp_rewrite;
    		if(!doing_action('generate_rewrite_rules')){
    	        return; // Too early or too late.
    	    }
    		if(!$this->is_external_rule($rule)){
    			return;
    		}
            if(isset($rule['plugin_file']) && $this->plugin($rule['plugin_file'])->is_deactivating()){
    			return;
            }
    		$wp_rewrite->add_external_rule($rule['regex'], $rule['query']);
    	}

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'generate_rewrite_rules' action hook.
    	 *
    	 * @return void
    	 */
        public function _maybe_add_external_rules($wp_rewrite){
    		if(!doing_action('generate_rewrite_rules')){
    	        return; // Too early or too late.
    	    }
            $group = 'external_rules';
            $external_rules = $this->cache($group)->get_group();
    	    foreach($external_rules as $external_rule){
    			$this->maybe_add_external_rule($external_rule);
    	    }
    	}

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
    	 * This method MUST be called inside the 'admin_notices' action hook.
    	 *
    	 * @return void
    	 */
        public function _maybe_add_external_rules_notice(){
    		if(!doing_action('admin_notices')){
    	        return; // Too early or too late.
    	    }
    		if(!current_user_can('manage_options')){
    			return;
    		}
            if(!$this->is('apache')){
                $message = _x('%1$s requires %2$s', 'The first plugin requires the second plugin.');
                //$message = sprintf($message, rtrim('Rewrite rules:', ':'), 'Apache');
                $message = str_replace(' )', ')', sprintf($message, __('.htaccess (for rewrite rules )'), sprintf(__('%1$s or %2$s'), 'Apache', 'LiteSpeed')));
                $this->add_admin_notice(__('Rewrite rules:') . ' ' . $message);
                return;
            }
            if(!function_exists('get_home_path')){
            	require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $htaccess = get_home_path() . '.htaccess';
            if(!file_exists($htaccess)){
                $error = $this->missing_file($htaccess);
                $this->add_admin_notice($error->get_error_message());
                return;
            }
            $external_rules = $this->cache('external_rules')->get_group();
            if(!$external_rules){
                return;
            }
            $add_admin_notice = false;
    		foreach($external_rules as $external_rule){
    			if(!$this->external_rule_exists($external_rule['regex'], $external_rule['query'])){
    				$add_admin_notice = true;
    				break;
    			}
    		}
    		if(!$add_admin_notice){
    	        return;
    		}
            if(!apache_mod_loaded('mod_rewrite')){
                $message = __('It looks like the Apache %s module is not installed.');
                $message = sprintf($message, '<code>mod_rewrite</code>');
                $this->add_admin_notice(__('Rewrite rules:') . ' ' . $message);
                return;
            }
            $message = __('You should update your %s file now.');
            $message = sprintf($message, '<code>.htaccess</code>');
            $message .= ' ' . $this->go_to(__('Flush permalinks'), admin_url('options-permalink.php')) . '.';
    	    $this->add_admin_notice(__('Rewrite rules:') . ' ' . $message);
    	}

		// ---------------------------------------------------------------------
        //
		// PHP Simple HTML DOM Parser
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return simple_html_dom|WP_Error
    	 */
    	public function file_get_html(...$args){
            $dir = $this->use_simple_html_dom();
    		if(is_wp_error($dir)){
    			return $dir;
    		}
    		return LDC\Deps\file_get_html(...$args);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string
    	 */
    	public function simple_html_dom_ver($set = ''){
            static $ver = '1.9.1';
            if($set === ''){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return simple_html_dom|WP_Error
    	 */
    	public function str_get_html(...$args){
            $dir = $this->use_simple_html_dom();
    		if(is_wp_error($dir)){
    			return $dir;
    		}
    		return LDC\Deps\str_get_html(...$args);
    	}

		// ---------------------------------------------------------------------

        /**
         * The copy bundled under `vendor-local`, loaded only when no other plugin
         * has loaded the library already.
         *
    	 * @return string|WP_Error
    	 */
    	public function use_simple_html_dom(...$args){
            return $this->use_local('simplehtmldom', 'simple_html_dom.php', 'LDC\Deps\simple_html_dom');
    	}

		// ---------------------------------------------------------------------
        //
		// Slick
        //
		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function enqueue_slick($deps = []){
            if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $ver = $this->slick_ver();
            $src = 'https://cdn.jsdelivr.net/npm/slick-carousel@' . $ver . '/slick/slick.min.css';
            $this->enqueue('slick', $src, [], $ver);
            $src = 'https://cdn.jsdelivr.net/npm/slick-carousel@' . $ver . '/slick/slick-theme.css';
            $this->enqueue('slick-theme', $src, ['slick'], $ver);
            $src = 'https://cdn.jsdelivr.net/npm/slick-carousel@' . $ver . '/slick/slick.min.js';
            $this->enqueue('slick', $src, $deps, $ver);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function load_slick($deps = []){
            if($this->scripts_maybe_doing_it_wrong()){
                return; // Too early.
            }
            $dir = $this->use_slick();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
            $base_path = $this->path_to_url($dir) . '/slick';
            $ver = $this->slick_ver();
            $this->enqueue('slick', $base_path . '/slick.css', $deps, $ver);
            $this->enqueue('slick-theme', $base_path . '/slick-theme.css', ['slick'], $ver);
            $this->enqueue('slick', $base_path . '/slick.min.js', $deps, $ver);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        public function slick_ver($set = ''){
            static $ver = '1.8.1';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function use_slick(){
            $ver = $this->slick_ver();
            $url = 'https://github.com/kenwheeler/slick/archive/refs/tags/v' . $ver . '.zip';
            return $this->use([
    			'expected_dir' => 'slick-' . $ver,
                'zipball_url' => $url,
            ]);
    	}

		// ---------------------------------------------------------------------
        //
		// Strings
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function base64_urldecode($data = '', $strict = false){
            $string = strtr($data, '-_', '+/');
    		return base64_decode($string, $strict);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function base64_urlencode($data = ''){
            $string = base64_encode($data);
            $string = strtr($string, '+/', '-_');
    		return rtrim($string, '=');
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function canonicalize($str = ''){
            $str = str_replace('\\', '_', $str); // Fix namespaces.
    		$str = sanitize_title($str);
    		$str = str_replace('-', '_', $str); // Fix slugified.
    		return trim($str, '_');
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function first_p($text = '', $dot = true){
    		return $this->one_p($text, $dot, 'first');
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function implode($array = [], $separator = ''){
            if(!$separator){
                $separator = wp_get_list_item_separator();
            }
    		return implode($separator, $array);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function implode_and($array = [], $last = ''){
            if(!$last){
                $last = trim(sprintf(__('%1$s and %2$s'), '', ''));
            }
    		return $this->implode_last($array, $last);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function implode_last($array = [], $last = '', $separator = ''){
            if(!$array || !is_array($array)){
    			return '';
    		}
            if(count($array) === 1){
    			return $array[0];
    		}
            if(!$last){
                return $this->implode($array, $separator);
            }
    		$last_item = array_pop($array);
    		return $this->implode($array, $separator) . ' ' . $last . ' ' . $last_item;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function implode_or($array = [], $last = ''){
            if(!$last){
                $last = trim(sprintf(__('%1$s or %2$s'), '', ''));
            }
    		return $this->implode_last($array, $last);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function last_p($text = '', $dot = true){
    		return $this->one_p($text, $dot, 'last');
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function one_p($text = '', $dot = true, $p = 'first'){
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
                    $p = $this->positive_int($p);
                    if(count($matches) >= $p){
                        $p --;
                        $match = $matches[$p];
                    } else {
                        $match = '';
                    }
            }
            if(!is_array($match)){
                return ''; // No sentence found: an empty string, or a $p past the end.
            }
            $one = trim($match[0]);
            if(!$dot){
                return $one;
            }
            $dot_chr = substr($text, strlen($match[0]) + $match[1], 1);
            if($dot_chr === ''){
                $dot_chr = '.';
            }
            $one .= $dot_chr;
            return $one;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function prepare($str = '', ...$args){
    		global $wpdb;
    		if(!$args){
    			return $str;
    		}
            if(strpos($str, '%') === false){
    			return $str;
    		}
    		$subject = $wpdb->prepare($str, ...$args);
    		$subject = $wpdb->remove_placeholder_escape($subject);
    		return str_replace("'", '', $subject);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function remove_whitespaces($str = ''){
    		return trim(preg_replace('/[\r\n\t ]+/', ' ', $str));
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function slugify($str = ''){
            $str = str_replace('\\', '-', $str); // Fix namespaces.
    		$str = sanitize_title($str);
    		$str = str_replace('_', '-', $str); // Fix canonicalized.
    		return trim($str, '-');
    	}

		// ---------------------------------------------------------------------

    	/**
         * Polyfill for `str_ends_with()` function added in PHP 8.0 and WordPress 5.9.
         *
    	 * @return bool
    	 */
    	public function str_ends_with($haystack = '', $needle = ''){
            if(function_exists('str_ends_with')){
                return str_ends_with($haystack, $needle);
            }
            if($haystack === ''){
    			return $needle === '';
    		}
            $len = strlen($needle);
            return substr($haystack, -$len, $len) === $needle;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function str_prefix($str = '', $prefix = ''){
            $prefix = $this->canonicalize($prefix);
    		$str = sanitize_text_field($str);
    		if(!$str){
    			return $prefix;
    		}
    		if(!$prefix){
    			return $str;
    		}
    		if($this->str_starts_with($str, $prefix)){
    			return $str; // Text is already prefixed.
    		}
    		return $prefix . '_' . $str;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function str_slug($str = '', $slug = ''){
            $slug = $this->slugify($slug);
    		$str = sanitize_text_field($str);
    		if(!$str){
    			return $slug;
    		}
    		if(!$slug){
    			return $str;
    		}
    		if($this->str_starts_with($str, $slug)){
    			return $str; // Text is already slugged.
    		}
    		return $slug . '-' . $str;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function str_split($str = '', $line_length = 55){
            $str = sanitize_text_field($str);
            if(!is_numeric($line_length) || $line_length < 1){
                return [$str]; // A line length of zero or less has no valid wrapping; return the text as one line instead of dividing by it.
            }
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

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function str_split_lines($str = '', $lines = 2){
            $str = sanitize_text_field($str);
            if(!is_numeric($lines) || $lines < 1){
                return [$str]; // Fewer than one line has no valid split; return the text as one line instead of dividing by it.
            }
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

		// ---------------------------------------------------------------------

    	/**
         * Polyfill for `str_starts_with()` function added in PHP 8.0 and WordPress 5.9.
         *
    	 * @return bool
    	 */
    	public function str_starts_with($haystack = '', $needle = ''){
            if(function_exists('str_starts_with')){
                return str_starts_with($haystack, $needle);
            }
    		if('' === $needle){
    			return true;
    		}
    		return strpos($haystack, $needle) === 0;
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function trailingdotit($text = ''){
            $text = sanitize_textarea_field($text);
    		return $this->str_ends_with($text, '.') ? $text : $text . '.';
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function unslashit($value = ''){
            return trim($value, '/\\');
    	}

		// ---------------------------------------------------------------------
        //
		// TGM Plugin Activation
        //
		// ---------------------------------------------------------------------

        /**
         * This method MUST be called inside the 'tgmpa_register' action hook.
         *
    	 * @return void
    	 */
        public function tgmpa($plugins = [], $config = []){
    		if(!doing_action('tgmpa_register')){
    			return; // Too early or too late.
    		}
            $dir = $this->use_tgm_plugin_activation();
            if(is_wp_error($dir)){
                return; // Silence is golden.
            }
    		tgmpa($plugins, $config);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function tgmpa_register($plugins = [], $config = []){
            $dir = $this->use_tgm_plugin_activation();
    		if(is_wp_error($dir)){
    			return; // Silence is golden.
    		}
    		if(doing_action('tgmpa_register')){
    			$this->tgmpa($plugins, $config);
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
    		$key = $this->md5($tgmpa);
    		$this->add_action_once('tgmpa_register', [$this, '_maybe_tgmpa_register']);
    		$this->cache($group)->set($key, $tgmpa);
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return void
    	 */
        public function tgmpa_register_plugin($plugin = []){
    		$dir = $this->use_tgm_plugin_activation();
    		if(is_wp_error($dir)){
    			return; // Silence is golden.
    		}
            $group = 'tgmpa_plugins';
    		$key = $this->md5($plugin);
    		$this->add_action_once('tgmpa_register', [$this, '_maybe_tgmpa_register']);
    		$this->cache($group)->set($key, $plugin);
    	}

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function tgmpa_ver($set = ''){
            static $ver = '2.6.1';
            if(empty($set)){
                return $ver; // Get.
            }
            $ver = $set; // Set.
            return $ver;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * @return bool|WP_Error
    	 */
        public function use_tgm_plugin_activation(){
            $ver = $this->tgmpa_ver();
            $url = 'https://github.com/TGMPA/TGM-Plugin-Activation/archive/refs/tags/' . $ver . '.zip';
            return $this->use([
                'autoload' => 'class-tgm-plugin-activation.php',
    			'expected_dir' => 'TGM-Plugin-Activation-' . $ver,
                'offline' => true,
                'validation_function' => 'tgmpa',
                'zipball_url' => $url,
            ]);
    	}

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * This method MUST be called inside the 'tgmpa_register' action hook.
         *
    	 * @return void
    	 */
        public function _maybe_tgmpa_register(){
            if(!doing_action('tgmpa_register')){
    			return; // Too early or too late.
    		}
            $group = 'tgmpa';
            $tgmpa = $this->cache($group)->get_group();
    		foreach($tgmpa as $args){
    			$this->tgmpa($args['plugins'], $args['config']);
    		}
            $group = 'tgmpa_plugins';
            $plugins = $this->cache($group)->get_group();
    		if(!$plugins){
    			return;
    		}
    		$id = $this->plugin()->slug('tgmpa');
    		$menu = $this->plugin()->slug('tgmpa-install-plugins');
    		$menu_title = $this->plugin()->meta('Name');
    		$this->tgmpa($plugins, [
    			'capability' => 'install_plugins',
    			'id' => $id,
    			'menu' => $menu,
    			'parent_slug' => 'plugins.php',
    			'strings' => [
    				'menu_title' => $menu_title,
    				'page_title' => esc_attr(sprintf(_x('Install %s', 'plugin'), __('Plugins'))),
    			],
    		]);
    	}

		// ---------------------------------------------------------------------
        //
		// Themes
        //
		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function theme_is($name = ''){
            $group = 'theme_is';
            if($this->cache($group)->exists($name)){
                return $this->cache($group)->get($name);
            }
        	$current_theme = wp_get_theme();
    		$theme_is = $name === $current_theme->get('Name');
    		$this->cache($group)->set($name, $theme_is);
        	return $theme_is;
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function theme_is_child_of($template = ''){
            $group = 'theme_is_child_of';
            if($this->cache($group)->exists($template)){
                return $this->cache($group)->get($template);
            }
    		$current_theme = wp_get_theme();
    		$theme_is_child_of = $template === $current_theme->get('Template');
            $this->cache($group)->set($template, $theme_is_child_of);
        	return $theme_is_child_of;
        }

		// ---------------------------------------------------------------------
        //
		// Uploads
        //
		// ---------------------------------------------------------------------

        /**
    	 * @return string|WP_Error
    	 */
        public function handle_upload($file = [], $dir = '', $mimes = null){
            if($dir){
                $dir = $this->check_dir($dir);
        	    if(is_wp_error($dir)){
        	        return $dir;
        	    }
            } else {
                $upload_dir = wp_upload_dir();
    			if($upload_dir['error']){
    				return $this->error($upload_dir['error']);
    			}
    			$dir = $upload_dir['path'];
            }
    		if(!$file){
                $error_msg = __('No data supplied.');
    			return $this->error($error_msg);
    		}
    		$file = shortcode_atts([
    			'error' => 0,
    			'name' => '',
    			'size' => 0,
    			'tmp_name' => '',
    			'type' => '',
    		], $file);
    		$uploaded_file = $this->test_uploaded_file($file['tmp_name']);
    		if(is_wp_error($uploaded_file)){
    			return $uploaded_file;
    		}
    		$error = $this->test_file_error($file['error']);
    		if(is_wp_error($error)){
    			return $error;
    		}
    		$size = $this->test_file_size($file['size']);
    		if(is_wp_error($size)){
    			return $size;
    		}
    		$filename = $this->test_file_type($file['tmp_name'], $file['name'], $mimes);
    		if(is_wp_error($filename)){
    			return $filename;
    		}
    		$size_check = $this->check_upload_size($file['size']);
    		if(is_wp_error($size_check)){
    			return $size_check;
    		}
            $new_file = $this->unique_filename($dir, $filename);
    		$move_new_file = @move_uploaded_file($file['tmp_name'], $new_file);
    		if($move_new_file === false){
                $error_msg = __('The uploaded file could not be moved to %s.');
                $error_msg = sprintf($error_msg, $this->relative_path($dir));
    			return $this->error($error_msg);
    		}
    		$stat = stat(dirname($new_file));
    		$perms = $stat['mode'] & 0000666;
    		chmod($new_file, $perms); // Set correct file permissions.
    		if(is_multisite()){
    			clean_dirsize_cache($new_file);
    		}
    		return $new_file;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * This method works only if the file was uploaded via HTTP POST.
    	 *
    	 * @return array|WP_Error
    	 */
        public function handle_uploaded_file($file = [], $dir = '', $mimes = null){
    		if(!$file){
                $error_msg = __('No data supplied.');
    			return $this->error($error_msg);
    		}
    		if(!is_array($file)){
    			if(!is_scalar($file)){
                    $error_msg = __('Invalid data provided.');
    				return $this->error($error_msg);
    			}
    			if(!isset($_FILES[$file])){
    				return $this->missing_file();
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
    			$uploaded_files[$index] = $this->handle_upload($file, $dir, $mimes);
    		}
    		return $uploaded_files;
    	}

		// ---------------------------------------------------------------------

        /**
    	 * This method works only if the files were uploaded via HTTP POST.
    	 *
    	 * @return array|WP_Error
    	 */
        public function handle_uploaded_files($files = [], $dir = '', $mimes = null){
    		if(!$files){
    			if(!$_FILES){
                    $error_msg = __('No data supplied.');
    				return $this->error($error_msg);
    			}
    			$files = $_FILES;
    		}
    		$uploaded_files = [];
    		foreach($files as $key => $file){
    			$uploaded_files[$key] = $this->handle_uploaded_file($file, $dir, $mimes);
    		}
    		return $uploaded_files;
    	}

		// ---------------------------------------------------------------------
        //
		// Urchin Tracking Module (UTM)
        //
		// ---------------------------------------------------------------------

        /**
         * Adds ldc().enable_utm(); after ldc.js, which keeps the UTM
         * parameters of the URL in the browser's localStorage. It all happens on
         * the visitor's side, so a page a cache like Varnish serves the same to
         * everyone still keeps each visitor's own.
         *
         * This method MUST be called before or inside the 'wp_enqueue_scripts' action hook.
         *
         * @return void
         */
        public function enable_utm(){
            $hook_name = 'wp_enqueue_scripts';
    		if(doing_action($hook_name) && wp_script_is('ldc')){ // Just in time: ldc.js is enqueued already.
    			$this->_maybe_enable_utm();
    			return;
    		}
    		if(did_action($hook_name) && !doing_action($hook_name)){ // Too late.
    			return;
    		}
    		$this->add_action_once($hook_name, [$this, '_maybe_enable_utm']);
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * This method MUST be called inside the 'wp_enqueue_scripts' action hook.
    	 *
    	 * @return void
         */
        public function _maybe_enable_utm(){
    		if(!doing_action('wp_enqueue_scripts')){ // Too early or too late.
    	        return;
    	    }
            $key = 'enable_utm';
            if($this->cache()->get_bool($key)){
                return; // Added already.
            }
            $this->cache()->set($key, true);
            $this->add_inline_script('ldc().enable_utm();');
        }

		// ---------------------------------------------------------------------
        //
		// Universally Unique Identifier
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return string
    	 */
    	public function emulate_uuid($data = ''){
            if($this->seems_uuid($data)){
                return $data;
            }
            $md5 = $this->md5($data);
            return sprintf('%s-%s-%s-%s-%s', substr($md5, 0, 8), substr($md5, 8, 4), substr($md5, 12, 4), substr($md5, 16, 4), substr($md5, 20, 12));
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_uuid3($uuid = ''){
            if(!is_string($uuid)){
                return false;
            }
            return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-3[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $uuid);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function is_uuid4($uuid = ''){
            return wp_is_uuid($uuid, 4);
    	}

		// ---------------------------------------------------------------------

    	/**
    	 * @return bool
    	 */
    	public function seems_uuid($uuid = ''){
            if(!is_string($uuid)){
                return false;
            }
            return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $uuid);
    	}

		// ---------------------------------------------------------------------
        //
		// Wordfence
        //
		// ---------------------------------------------------------------------

        /**
         * @return array
         */
        public function wf_bulk_countries(){
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

		// ---------------------------------------------------------------------

        /**
         * @return array
         */
        public function wf_get_code_execution_protection_rules(){
            $upload_dir = wp_get_upload_dir();
    		if($upload_dir['error']){
    			return [];
    		}
            $basedir = $upload_dir['basedir'];
            $htaccess = path_join($basedir, '.htaccess');
            if(!function_exists('extract_from_markers')){
                require_once ABSPATH . 'wp-admin/includes/misc.php';
            }
            $result = extract_from_markers($htaccess, 'Wordfence code execution protection');
            return array_values(array_filter($result));
        }

		// ---------------------------------------------------------------------

        /**
         * @return array
         */
        public function wf_get_countries($preferred_countries = []){
            $wf_countries = $this->wf_bulk_countries();
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

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function wf_get_country($ip = ''){
            if($ip && !WP_Http::is_ip_address($ip)){
                return '';
            }
            if(!$ip){
                $ip = $this->get_remote_ip();
            }
            return is_callable(['wfUtils', 'IP2Country']) ? wfUtils::IP2Country($ip) : '';
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function wf_get_ip(){
            return is_callable(['wfUtils', 'getIP']) ? wfUtils::getIP() : '';
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function wf_is_code_execution_protection_enabled(){
            $disable_code_execution_uploads = is_callable(['wfConfig', 'get']) ? wfConfig::get('disableCodeExecutionUploads') : false;
            if(!$disable_code_execution_uploads){
                return false;
            }
            $rules = $this->wf_get_code_execution_protection_rules();
            return $rules ? true : false;
        }

		// ---------------------------------------------------------------------
        //
		// Zoom API
        //
		// ---------------------------------------------------------------------

        /**
         * Returns the LDC_Zoom instance for the given endpoint and arguments, from
         * which the request is made. It works the way ldc()->remote() does, except
         * that the endpoint is relative to the Zoom API, the access token, JSON
         * headers and timeout are added for you, and only delete(), get(),
         * patch(), post() and put() are sent: any other method comes back as a
         * 405. The arguments may also be just the data to send.
         * E.g. ldc()->zoom('users/me/meetings', $args)->post($body);
         *
         * @see LDC::zoom_app_credentials()
         *
    	 * @return LDC_Zoom
    	 */
        public function zoom($endpoint = '', $args = []){
            return LDC_Zoom::get_instance($endpoint, $args);
        }

		// ---------------------------------------------------------------------

        /**
         * @return array|WP_Error
         */
        public function zoom_app_credentials($app_credentials = []){
            $key = 'zoom_app_credentials';
            if(!$app_credentials && $this->cache()->exists($key)){
                return $this->cache()->get($key);
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
                $error = $this->missing_params($missing);
                $this->cache()->set($key, $error);
                return $error;
            }
            $this->cache()->set($key, $app_credentials);
            return $app_credentials;
        }

		// ---------------------------------------------------------------------

        /**
         * The same as ldc()->zoom($endpoint, $body)->delete(), kept for the code
         * written for it.
         *
         * @return stdClass
         */
        public function zoom_delete($endpoint = '', $body = []){
            return $this->zoom($endpoint, $body)->delete();
        }

		// ---------------------------------------------------------------------

        /**
         * The same as ldc()->zoom($endpoint, $body)->get(), kept for the code
         * written for it.
         *
         * @return stdClass
         */
        public function zoom_get($endpoint = '', $body = []){
            return $this->zoom($endpoint, $body)->get();
        }

		// ---------------------------------------------------------------------

        /**
         * The same as ldc()->zoom($endpoint, $body)->patch(), kept for the code
         * written for it.
         *
         * @return stdClass
         */
        public function zoom_patch($endpoint = '', $body = []){
            return $this->zoom($endpoint, $body)->patch();
        }

		// ---------------------------------------------------------------------

        /**
         * The same as ldc()->zoom($endpoint, $body)->post(), kept for the code
         * written for it.
         *
         * @return stdClass
         */
        public function zoom_post($endpoint = '', $body = []){
            return $this->zoom($endpoint, $body)->post();
        }

		// ---------------------------------------------------------------------

        /**
         * The same as ldc()->zoom($endpoint, $body)->put(), kept for the code
         * written for it.
         *
         * @return stdClass
         */
        public function zoom_put($endpoint = '', $body = []){
            return $this->zoom($endpoint, $body)->put();
        }

		// ---------------------------------------------------------------------

        /**
         * The same as ldc()->zoom($endpoint, $body)->request($method), kept for the
         * code written for it.
         *
         * @return stdClass
         */
        public function zoom_request($endpoint = '', $body = [], $method = ''){
            return $this->zoom($endpoint, $body)->request($method);
        }

		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * @return string|WP_Error
         */
        public function zoom_request_access_token(){
            $key = 'zoom_access_token';
            if($this->cache()->exists($key)){
                return $this->cache()->get($key);
            }
            $app_credentials = $this->zoom_app_credentials();
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
            $response = $this->remote($url, $args)->post();
            if(!$response->success){
                return $response->wp_error;
            }
            if(!isset($response->json_params['access_token'])){
                $error = $this->missing_params(['access_token']);
                $this->cache()->set($key, $error);
                return $error;
            }
            $access_token = $response->json_params['access_token'];
            if(!$access_token){
                $error = $this->invalid_params(['access_token']);
                $this->cache()->set($key, $error);
                return $error;
            }
            $this->cache()->set($key, $access_token);
            return $access_token;
        }

		// ---------------------------------------------------------------------

    }
}
