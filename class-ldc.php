<?php

if(!class_exists('ldc')){
	final class ldc {

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // private static
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		private static $admin_notices = [], $hooks = [], $image_sizes = [], $instances = [];

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // public static
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public static function instance($extension = ''){
			$class_name = $extension ? 'ldc_' . str_replace('-', '_', sanitize_title($extension)) : 'ldc';
			if(array_key_exists($class_name, self::$instances)){
				return self::$instances[$class_name];
			} else {
				if(class_exists($class_name)){
					self::$instances[$class_name] = new $class_name;
					return self::$instances[$class_name];
				} else {
					$slug = str_replace('_', '-', $class_name);
					if(file_exists(plugin_dir_path(__FILE__) . 'class-' . $slug . '.php')){
						require_once(plugin_dir_path(__FILE__) . 'class-' . $slug . '.php');
						self::$instances[$class_name] = new $class_name;
						return self::$instances[$class_name];
					} else {
						$library = ldc('github')->require('luisdelcid', $slug);
						if(is_wp_error($library)){
							wp_die($library);
						}
						if(file_exists($library['dir'] . '/class-' . $slug . '.php')){
							require_once($library['dir'] . '/class-' . $slug . '.php');
							self::$instances[$class_name] = new $class_name;
							return self::$instances[$class_name];
						} else {
							wp_die('Required file class-' . $slug . '.php is missing.');
						}
					}
				}
			}
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // public
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function add_admin_notice($admin_notice = '', $class = 'error', $is_dismissible = false){
	        $md5 = md5($admin_notice);
	        if(!array_key_exists($md5, self::$admin_notices)){
	            if(!in_array($class, ['error', 'info', 'success', 'warning'])){
	                $class = 'warning';
	            }
	            if($is_dismissible){
	                $class .= ' is-dismissible';
	            }
	            self::$admin_notices[$md5] = '<div class="notice notice-' . $class . '"><p>' . $admin_notice . '</p></div>';
				$this->one('admin_notices', function(){
					foreach(self::$admin_notices as $admin_notice){
						echo $admin_notice;
					}
				});
	        }
	    }

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function add_image_size($name = '', $width = 0, $height = 0, $crop = false){
			$size = sanitize_title($name);
	        if(!array_key_exists($size, self::$image_sizes)){
	            self::$image_sizes[$size] = $name;
				add_image_size($size, $width, $height, $crop);
				$this->one('image_size_names_choose', function(){
					foreach(self::$image_sizes as $size => $name){
						$sizes[$size] = $name;
					}
					return $sizes;
				});
	        }
	    }

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function are_plugins_active($plugins = []){
	        if(!is_array($plugins)){
	            return false;
	        }
	        foreach($plugins as $plugin){
	            if(!$this->is_plugin_active($plugin)){
	                return false;
	            }
	        }
	        return true;
	    }

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function array_keys_exist($keys = [], $array = []){
			if(!is_array($keys) or !is_array($array)){
				return false;
			}
			foreach($keys as $key){
				if(!array_key_exists($key, $array)){
					return false;
				}
			}
			return true;
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function attachment_url_to_postid($url = ''){
	        // original
	        $post_id = $this->guid_to_postid($url);
	        if($post_id){
	            return $post_id;
	        }
	        // resized
	        preg_match('/^(.+)(-\d+x\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
	        if($matches){
	            $url = $matches[1];
	            if(isset($matches[3])){
	                $url .= $matches[3];
	            }
	            $post_id = $this->guid_to_postid($url);
	            if($post_id){
	                return $post_id;
	            }
	        }
	        // scaled
	        preg_match('/^(.+)(-scaled)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
	        if($matches){
	            $url = $matches[1];
	            if(isset($matches[3])){
	                $url .= $matches[3];
	            }
	            $post_id = $this->guid_to_postid($url);
	            if($post_id){
	                return $post_id;
	            }
	        }
	        // edited
	        preg_match('/^(.+)(-e\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
	        if($matches){
	            $url = $matches[1];
	            if(isset($matches[3])){
	                $url .= $matches[3];
	            }
	            $post_id = $this->guid_to_postid($url);
	            if($post_id){
	                return $post_id;
	            }
	        }
	        return 0;
	    }

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function base64_urldecode($data = '', $strict = false){
			return base64_decode(strtr($data, '-_', '+/'), $strict);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function base64_urlencode($data = ''){
			return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		function build_update_checker(...$args){
			if(!class_exists('Puc_v4_Factory')){
				$library = ldc('github')->require('YahnisElsts', 'plugin-update-checker');
				if(is_wp_error($library)){
					return null;
				}
				require_once($library['dir'] . '/plugin-update-checker.php');
	        }
			return Puc_v4_Factory::buildUpdateChecker(...$args);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function clone_role($source = '', $destination = '', $display_name = ''){
			$role = get_role($source);
			if(is_null($role)){
				return null;

			}
			return add_role(sanitize_title($destination), $display_name, $role->capabilities);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

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

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function current_screen_is($id = ''){
			global $current_screen;
			if(!is_string($id)){
				return false;
			}
			if(!isset($current_screen)){
				return false;
			}
			return ($current_screen->id == $id);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function destroy_other_sessions(){
			$this->one('init', 'wp_destroy_other_sessions');
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function did($tag = ''){
			return did_action($tag);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function do($tag = '', ...$arg){
			return do_action($tag, ...$arg);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function download($url = '', $args = []){
			$args = wp_parse_args($args, [
				'filename' => '',
				'timeout' => 300,
			]);
			if($args['filename']){
				if(!$this->in_uploads($args['filename'])){
					return $this->error(sprintf(__('Unable to locate needed folder (%s).'), 'uploads'));
				}
			} else {
				$download_dir = $this->download_dir();
				if(is_wp_error($download_dir)){
					return $download_dir;
				}
				$args['filename'] = $download_dir . '/' . uniqid() . '-' . preg_replace('/\?.*/', '', basename($url));
			}
			$args['stream'] = true;
			$args['timeout'] = $this->sanitize_timeout($args['timeout']);
			$response = $this->remote($url, $args)->get();
			if(!$response->success){
				@unlink($args['filename']);
				return $response->to_wp_error();
			}
			return $args['filename'];
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function download_dir(){
			$upload_dir = wp_get_upload_dir();
			$download_dir = $upload_dir['basedir'] . '/ldc/downloads';
			if(!wp_mkdir_p($download_dir)){
				return $this->error(__('Could not create directory.'));
			}
			if(!wp_is_writable($download_dir)){
				return $this->error(__('Destination directory for file streaming does not exist or is not writable.'));
			}
			return $download_dir;
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function download_url(){
			$upload_dir = wp_get_upload_dir();
			return $upload_dir['baseurl'] . '/ldc/downloads';
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function error($message = '', $data = ''){
			if(!$message){
				$message = __('Something went wrong.');
			}
			return new WP_Error('ldc_error', $message, $data);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function get_memory_size(){
			if(!function_exists('exec')){
				return 0;
			}
			exec('free -b', $output);
			$output = explode(' ', trim(preg_replace('/\s+/', ' ', $output[1])));
			return (int) $output[1];
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function filesystem(){
			global $wp_filesystem;
			if(!function_exists('get_filesystem_method')){
				require_once(ABSPATH . 'wp-admin/includes/file.php');
			}
			if(get_filesystem_method() != 'direct'){
				return $this->error(__('Could not access filesystem.'));
			}
			if(!WP_Filesystem()){
				return $this->error(__('Filesystem error.'));
			}
			return $wp_filesystem;
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function fix_audio_video_type(){
			$this->one('wp_check_filetype_and_ext', function($wp_check_filetype_and_ext, $file, $filename, $mimes, $real_mime){
				if($wp_check_filetype_and_ext['ext'] and $wp_check_filetype_and_ext['type']){
					return $wp_check_filetype_and_ext;
				}
				if(strpos($real_mime, 'audio/') === 0 or strpos($real_mime, 'video/') === 0){
					$filetype = wp_check_filetype($filename);
					if(in_array(substr($filetype['type'], 0, strcspn($filetype['type'], '/')), ['audio', 'video'])){
						$wp_check_filetype_and_ext['ext'] = $filetype['ext'];
						$wp_check_filetype_and_ext['type'] = $filetype['type'];
					}
				}
				return $wp_check_filetype_and_ext;
			}, 10, 5);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function guid_to_postid($guid = ''){
			global $wpdb;
			if($guid){
				$str = "SELECT ID FROM $wpdb->posts WHERE guid = %s";
				$sql = $wpdb->prepare($str, $guid);
				$post_id = $wpdb->get_var($sql);
				if($post_id){
					return (int) $post_id;
				}
			}
			return 0;
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function in_uploads($filename = ''){
			$upload_dir = wp_get_upload_dir();
			return (strpos($filename, $upload_dir['basedir']) === 0);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function is_array_assoc($array = []){
			if(!is_array($array)){
				return false;
			}
			return (array_keys($array) !== range(0, count($array) - 1));
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function is_doing_heartbeat(){
			return (defined('DOING_AJAX') and DOING_AJAX and isset($_POST['action']) and $_POST['action'] == 'heartbeat');
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function is_extension_allowed($extension = ''){
			foreach(wp_get_mime_types() as $exts => $mime){
				if(preg_match('!^(' . $exts . ')$!i', $extension)){
					return true;
				}
			}
			return false;
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function is_plugin_active($plugin = ''){
			if(!function_exists('is_plugin_active')){
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			}
			return is_plugin_active($plugin);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function is_plugin_deactivating($file = ''){
			global $pagenow;
			if(!is_file($file)){
				return false;
			}
			return (is_admin() and $pagenow == 'plugins.php' and isset($_GET['action'], $_GET['plugin']) and $_GET['action'] == 'deactivate' and $_GET['plugin'] == plugin_basename($file));
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function is_post_revision_or_auto_draft($post = null){
			return (wp_is_post_revision($post) or get_post_status($post) == 'auto-draft');
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function ksort_deep($data = []){
			if($this->is_array_assoc($data)){
				ksort($data);
				foreach($data as $index => $item){
					$data[$index] = $this->ksort_deep($item);
				}
			}
			return $data;
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function maybe_generate_attachment_metadata($attachment = null){
			$attachment = get_post($attachment);
			if(!$attachment or $attachment->post_type != 'attachment'){
				return false;
			}
			wp_raise_memory_limit('admin');
			wp_maybe_generate_attachment_metadata($attachment);
			return wp_get_attachment_metadata($attachment->ID);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function md5($data = null){
			if(is_object($data)){
				if($data instanceof Closure){
					return $this->md5_closure($data);
				} else {
					$data = json_decode(wp_json_encode($data), true);
				}
			}
			if(is_array($data)){
				$data = $this->ksort_deep($data);
				$data = maybe_serialize($data);
			}
			return md5($data);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function md5_closure($data = null, $spl_object_hash = false){
			$serialized = $this->serialize_closure($data);
			if(is_wp_error($serialized)){
				return $serialized;
			}
			if(!$spl_object_hash){
				$serialized = str_replace(spl_object_hash($data), 'spl_object_hash', $serialized);
			}
			return md5($serialized);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function md5_to_uuid4($md5){
			if(strlen($md5) != 32){
				return '';
			}
			return substr($md5, 0, 8) . '-' . substr($md5, 8, 4) . '-' . substr($md5, 12, 4) . '-' . substr($md5, 16, 4) . '-' . substr($md5, 20, 12);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function off($tag = '', $function_to_remove = '', $priority = 10){
			return remove_filter($tag, $function_to_remove, $priority);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function on($tag = '', $function_to_add = '', $priority = 10, $accepted_args = 1){
			add_filter($tag, $function_to_add, $priority, $accepted_args);
			return _wp_filter_build_unique_id($tag, $function_to_add, $priority);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function one($tag = '', $function_to_add = '', $priority = 10, $accepted_args = 1){
			if(!array_key_exists($tag, self::$hooks)){
				self::$hooks[$tag] = [];
			}
			$idx = _wp_filter_build_unique_id($tag, $function_to_add, $priority);
			if($function_to_add instanceof Closure){
				$md5 = $this->md5_closure($function_to_add);
				if(is_wp_error($md5)){
					$md5 = md5($idx);
				}
			} else {
				$md5 = md5($idx);
			}
			if(array_key_exists($md5, self::$hooks[$tag])){
				return self::$hooks[$tag][$md5];
			} else {
				add_filter($tag, $function_to_add, $priority, $accepted_args);
				self::$hooks[$tag][$md5] = $idx;
				return $idx;
			}
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function prepare($str = '', ...$args){
			global $wpdb;
			if(!$args){
				return $str;
			}
			if(strpos($str, '%') === false){
				return $str;
			} else {
				return str_replace("'", '', $wpdb->remove_placeholder_escape($wpdb->prepare(...$args)));
			}
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function post_type_labels($singular = '', $plural = '', $all = true){
			if(!$singular or !$plural){
				return [];
			}
			return [
				'name' => $plural,
				'singular_name' => $singular,
				'add_new' => 'Add New',
				'add_new_item' => 'Add New ' . $singular,
				'edit_item' => 'Edit ' . $singular,
				'new_item' => 'New ' . $singular,
				'view_item' => 'View ' . $singular,
				'view_items' => 'View ' . $plural,
				'search_items' => 'Search ' . $plural,
				'not_found' => 'No ' . strtolower($plural) . ' found.',
				'not_found_in_trash' => 'No ' . strtolower($plural) . ' found in Trash.',
				'parent_item_colon' => 'Parent ' . $singular . ':',
				'all_items' => ($all ? 'All ' : '') . $plural,
				'archives' => $singular . ' Archives',
				'attributes' => $singular . ' Attributes',
				'insert_into_item' => 'Insert into ' . strtolower($singular),
				'uploaded_to_this_item' => 'Uploaded to this ' . strtolower($singular),
				'featured_image' => 'Featured image',
				'set_featured_image' => 'Set featured image',
				'remove_featured_image' => 'Remove featured image',
				'use_featured_image' => 'Use as featured image',
				'filter_items_list' => 'Filter ' . strtolower($plural) . ' list',
				'items_list_navigation' => $plural . ' list navigation',
				'items_list' => $plural . ' list',
				'item_published' => $singular . ' published.',
				'item_published_privately' => $singular . ' published privately.',
				'item_reverted_to_draft' => $singular . ' reverted to draft.',
				'item_scheduled' => $singular . ' scheduled.',
				'item_updated' => $singular . ' updated.',
			];
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function read_file_chunk($handle = null, $chunk_size = 0){
			$giant_chunk = '';
			if(is_resource($handle) and is_int($chunk_size)){
				$byte_count = 0;
				while(!feof($handle)){
					$length = apply_filters('ldc_file_chunk_lenght', (8 * KB_IN_BYTES));
					$chunk = fread($handle, $length);
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

		public function remote($url = '', $args = []){
			if(!class_exists('ldc_remote')){
				require_once(plugin_dir_path(__FILE__) . 'class-ldc-remote.php');
			}
			return new ldc_remote($url, $args);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function remove_whitespaces($str = ''){
			return trim(preg_replace('/[\r\n\t\s]+/', ' ', $str));
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function replace_script($handle = '', $src = '', $deps = [], $ver = false, $in_footer = false){
			if(wp_script_is($handle)){
				wp_dequeue_script($handle);
			}
			if(wp_script_is($handle, 'registered')){
				wp_deregister_script($handle);
			}
			wp_register_script($handle, $src, $deps, $ver, $in_footer);
			wp_enqueue_script($handle);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function replace_style($handle = '', $src = '', $deps = [], $ver = false){
			if(wp_style_is($handle)){
				wp_dequeue_style($handle);
			}
			if(wp_style_is($handle, 'registered')){
				wp_deregister_style($handle);
			}
			wp_register_style($handle, $src, $deps, $ver);
			wp_enqueue_style($handle);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		function require($url = '', $dir = ''){
			$download_dir = $this->download_dir();
            if(is_wp_error($download_dir)){
                return $download_dir;
            }
            $md5 = md5($url);
            $to = $download_dir . '/' . $md5;
            if($dir){
                $dir = $to . '/' . ltrim(untrailingslashit($dir), '/');
            } else {
                $dir = $to;
            }
            $filesystem = $this->filesystem();
            if(is_wp_error($filesystem)){
                return $filesystem;
            }
            if(!$filesystem->dirlist($dir, false)){
                $file = $this->download($url);
                if(is_wp_error($file)){
                    return $file;
                }
                $result = unzip_file($file, $to);
				@unlink($file);
                if(is_wp_error($result)){
                    $filesystem->rmdir($to, true);
                    return $result;
                }
				if(!$filesystem->dirlist($dir, false)){
					$filesystem->rmdir($to, true);
                    return $this->error(__('Destination directory for file streaming does not exist or is not writable.'));
                }
            }
			return [
				'dir' => $dir,
				'url' => $this->download_url() . '/' . $md5,
			];
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function response($response = null){
			if(!class_exists('ldc_response')){
				require_once(plugin_dir_path(__FILE__) . 'class-ldc-response.php');
			}
			return new ldc_response($response);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function response_error($data = '', $code = 0, $message = ''){
			if(!$code){
				$code = 500;
			}
			if(!$message){
				$message = get_status_header_desc($code);
			}
			if(!$message){
				$message = __('Something went wrong.');
			}
			$success = false;
			return $this->response(compact('code', 'data', 'message', 'success'));
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function response_success($data = '', $code = 0, $message = ''){
			if(!$code){
				$code = 200;
			}
			if(!$message){
				$message = get_status_header_desc($code);
			}
			if(!$message){
				$message = 'OK';
			}
			$success = true;
			return $this->response(compact('code', 'data', 'message', 'success'));
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function sanitize_timeout($timeout = 0){
			$timeout = (int) $timeout;
			$max_execution_time = (int) ini_get('max_execution_time');
			if($max_execution_time){
				if(!$timeout or $timeout > $max_execution_time){
					$timeout = $max_execution_time - 1; // Prevents error 504
				}
			}
			if(isset($_SERVER['HTTP_CF_RAY'])){
				if(!$timeout or $timeout > 99){
					$timeout = 99; // Prevents error 524: https://support.cloudflare.com/hc/en-us/articles/115003011431#524error
				}
			}
			return $timeout;
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function seems_response($response = []){
			return $this->array_keys_exist(['code', 'data', 'message', 'success'], $response);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function seems_successful($data = null){
			if(!is_numeric($data)){
				if($data instanceof ldc_response){
					$data = $data->code;
				} else {
					return false;
				}
			} else {
				$data = (int) $data;
			}
			return ($data >= 200 and $data < 300);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function seems_wp_http_requests_response($data = null){
			return ($this->array_keys_exist(['body', 'cookies', 'filename', 'headers', 'http_response', 'response'], $data) and ($data['http_response'] instanceof WP_HTTP_Requests_Response));
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function serialize_closure($data = null){
			if($data instanceof Closure){
				if(!class_exists('Opis\Closure\SerializableClosure')){
					$library = ldc('github')->require('opis', 'closure');
					if(is_wp_error($library)){
						return $library;
					}
					require_once($library['dir'] . '/autoload.php');
		        }
				$wrapper = new Opis\Closure\SerializableClosure($data);
				return maybe_serialize($wrapper);
			} else {
				return $this->error(__('Invalid object type.'));
			}
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function signon_without_password($username_or_email = '', $remember = false){
			if(is_user_logged_in()){
				return wp_get_current_user();
			} else {
				$idx = $this->on('authenticate', function($user, $username_or_email){
					if(is_null($user)){
						if(is_email($username_or_email)){
							$user = get_user_by('email', $username_or_email);
						}
						if(is_null($user)){
							$user = get_user_by('login', $username_or_email);
							if(is_null($user)){
								return $this->error(__('The requested user does not exist.'));
							}
						}
					}
					return $user;
				}, 10, 2);
				$user = wp_signon([
					'remember' => $remember,
					'user_login' => $username_or_email,
					'user_password' => '',
				]);
				$this->off('authenticate', $idx);
				return $user;
			}
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function support_authorization_header(){
			$this->one('mod_rewrite_rules', function($rules){
				$rule = 'RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]';
				if(strpos($rule, $rules) === false){
					$rules = str_replace('RewriteEngine On', 'RewriteEngine On' . "\n" . $rule, $rules);
				}
				return $rules;
			});
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function support_sessions($defaults = true){
			$this->one('init', function() use($defaults){
				if(!session_id()){
					session_start();
				}
				if($defaults){
					if(!array_key_exists('ldc_current_user_id', $_SESSION)){
						$_SESSION['ldc_current_user_id'] = get_current_user_id();
					}
					if(!array_key_exists('ldc_utm', $_SESSION)){
						$_SESSION['ldc_utm'] = [];
						foreach($_GET as $key => $value){
							if(substr($key, 0, 4) == 'utm_'){
								$_SESSION['ldc_utm'][$key] = $value;
							}
						}
					}
				}
			}, 9);
			$this->one('wp_login', function($user_login, $user) use($defaults){
				if($defaults){
					$_SESSION['ldc_current_user_id'] = $user->ID;
				}
			}, 9, 2);
			$this->one('wp_logout', function(){
				if(session_id()){
					session_destroy();
				}
			}, 9);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		public function upload($file = '', $parent = 0){
			$filetype_and_ext = wp_check_filetype_and_ext($file, $file);
			if(!$filetype_and_ext['type']){
				return $this->error(__('Sorry, this file type is not permitted for security reasons.'));
			}
			$upload_dir = wp_get_upload_dir();
			$post_id = wp_insert_attachment([
				'guid' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file),
				'post_mime_type' => $filetype_and_ext['type'],
				'post_status' => 'inherit',
				'post_title' => preg_replace('/\.[^.]+$/', '', basename($file)),
			], $file, $parent, true);
			if(is_wp_error($post_id)){
				return $post_id;
			}
			return $post_id;
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	}
}

// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

if(!function_exists('ldc')){
	function ldc($extension = ''){
		return ldc::instance($extension);
	}
}
