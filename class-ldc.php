<?php namespace your_namespace;

if(!class_exists(ltrim(__NAMESPACE__ . '\LDC', '\\'))){
	class LDC {

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		const PREFIX = 'ldc_';

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return WP_Error
		 */
		public static function __callStatic($name, $arguments){
			$error_msg = __("Method '%s' not implemented. Must be overridden in subclass.");
			$error_msg = sprintf($error_msg, $name);
			$error_msg = self::first_p($error_msg);
			return self::error($error_msg, $arguments);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return void
		 */
		public static function _admin_init(){
			$hide_the_dashboard = self::_get('hide_the_dashboard', []);
			if($hide_the_dashboard){
				if(!wp_doing_ajax() and !current_user_can($hide_the_dashboard['capability'])){
					$location = $hide_the_dashboard['location'];
					if(empty($location)){
						$location = home_url();
					} else {
						$location = wp_sanitize_redirect($location);
						if(!wp_validate_redirect($location)){
							$location = home_url();
						}
					}
					wp_safe_redirect($location);
					exit;
				}
			}
			$hide_wp = self::_get('hide_wp', false);
			if($hide_wp){
				remove_action('welcome_panel', 'wp_welcome_panel');
			}
		}

		/**
		 * @return void
		 */
		public static function _admin_notices(){
			$admin_notices = self::_get('admin_notices', []);
			if($admin_notices){
				foreach($admin_notices as $admin_notice){
					echo $admin_notice;
				}
			}
		}

		/**
		 * @return void
		 */
		public static function _after_setup_theme(){
			$track_utm = self::_get('track_utm', false);
			if($track_utm){
				self::utm_set();
			}
		}

		/**
		 * @return array
		 */
		public static function _ajax_query_attachments_args($query){
			$hide_others_media = self::_get('hide_others_media', []);
			if($hide_others_media){
				if(!current_user_can($hide_others_media['capability'])){
					$query['author'] = get_current_user_id();
				}
			}
			return $query;
		}

		/**
		 * @return false|WP_User|WP_error
		 */
		public static function _authenticate($user, $username_or_email){
			if(!is_null($user)){
				return $user;
			}
			$user = false; // Returning a non-null value will effectively short-circuit the user authentication process.
			if(is_email($username_or_email)){
				$user = get_user_by('email', $username_or_email);
			}
			if(!$user){
				$user = get_user_by('login', $username_or_email);
			}
			return $user;
		}

		/**
		 * @return void
		 */
		public static function _current_screen($current_screen){
			global $pagenow;
			$hide_others_posts = self::_get('hide_others_posts', []);
			if($hide_others_posts){
				if('edit.php' === $pagenow and !current_user_can($hide_others_posts['capability'])){
					$views = self::_get('views', []);
					if(!in_array($current_screen->id, $views)){
						$views[] = $current_screen->id;
						self::_set('views', $views);
						add_filter('views_' . $current_screen->id, function($views){
							foreach($views as $index => $view){
								$views[$index] = preg_replace('/ <span class="count">\([0-9]+\)<\/span>/', '', $view);
							}
							return $views;
						});
					}
				}
			}
		}

		/**
		 * @return array
		 */
		public static function _fl_builder_photo_sizes_select($sizes){
			$cl_image_sizes = self::_get('cl_image_sizes', []);
			if($cl_image_sizes){
				if(isset($sizes['full'])){
					$id = self::attachment_url_to_postid($sizes['full']['url']);
					if($id){
						foreach($cl_image_sizes as $size => $args){
							if(isset($sizes[$size])){
								continue;
							}
							$meta_key = self::PREFIX . 'cl_' . self::md5($args['options']);
							$result = get_post_meta($id, $meta_key, true);
							if(!$result){
								continue;
							}
							$filename = $result['public_id'] . '.' . $result['format'];
							$height = $result['height'];
							$url = $result['secure_url'];
							$width = $result['width'];
							if(!$filename or !$height or !$url or !$width){
								continue;
							}
							$sizes[$size] = [
								'filename' => $filename,
								'height' => $height,
								'url' => $url,
								'width' => $width,
							];
						}
					}
				}
			}
			$bb_sort_image_sizes = self::_get('bb_sort_image_sizes', false);
			if($bb_sort_image_sizes){
				uasort($sizes, function($a, $b) {
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
				});
			}
			return $sizes;
		}

		/**
		 * @return void
		 */
		public static function _generate_rewrite_rules($wp_rewrite){
			$external_rules = self::_get('external_rules', []);
			if($external_rules){
				foreach($external_rules as $rule){
					if($rule['file'] and self::is_plugin_deactivating($rule['file'])){
						continue;
					}
					$wp_rewrite->add_external_rule($rule['regex'], $rule['query']);
				}
			}
		}

		/**
		 * @return mixed
		 */
		public static function _get($name = '', $default = null){
			$name = trim($name);
			if('' === $name){
				return null;
			}
			if(!isset($GLOBALS[self::PREFIX . 'vars'])){
				$GLOBALS[self::PREFIX . 'vars'] = [];
			}
			$var = $default;
			if(array_key_exists($name, $GLOBALS[self::PREFIX . 'vars'])){
				$var = $GLOBALS[self::PREFIX . 'vars'][$name];
			}
			return $var;
		}

		/**
		 * @return false|int
		 */
		public static function _image_downsize($out, $id, $size){
			$cl_image_sizes = self::_get('cl_image_sizes', []);
			if($cl_image_sizes){
				if(!$out){
					if(wp_attachment_is_image($id)){
						if(is_scalar($size)){
							if(isset($cl_image_sizes[$size])){
								$meta_key = self::PREFIX . 'cl_' . self::md5($cl_image_sizes[$size]['options']);
								$result = get_post_meta($id, $meta_key, true);
								if(!$result){
									$result = self::cl_upload_attachment($id, $size);
								}
								if(!is_wp_error($result)){
									$url = $result['secure_url'];
									$width = $result['width'];
									$height = $result['height'];
									if($url and $width and $height){
										$out = [$url, $width, $height, true];
									}
								}
							}
						}
					}
				}
			}
			return $out;
		}

		/**
		 * @return array
		 */
		public static function _image_size_names_choose($sizes){
			$cl_image_sizes = self::_get('cl_image_sizes', []);
			if($cl_image_sizes){
				foreach($cl_image_sizes as $size => $args){
					$sizes[$size] = $args['name'];
				}
			}
			$image_sizes = self::_get('image_sizes', []);
			if($image_sizes){
				foreach($image_sizes as $size => $name){
					$sizes[$size] = $name;
				}
			}
			return $sizes;
		}

		/**
		 * @return void
		 */
		public static function _login_enqueue_scripts(){
			$custom_login_logo = self::_get('custom_login_logo', []);
			if($custom_login_logo){ ?>
				<style type="text/css">
					#login h1 a,
					.login h1 a {
						background-image: url(<?php echo $custom_login_logo[0]; ?>);
						background-size: <?php echo $custom_login_logo[1]; ?>px <?php echo $custom_login_logo[2]; ?>px;
						height: <?php echo $custom_login_logo[2]; ?>px;
						width: <?php echo $custom_login_logo[1]; ?>px;
					}
				</style><?php
			}
		}

		/**
		 * @return string
		 */
		public static function _login_headertext($login_header_text){
			$local_login_header = self::_get('local_login_header', false);
			if($local_login_header){
				$login_header_text = get_option('blogname');
			}
			return $login_header_text;
		}

		/**
		 * @return string
		 */
		public static function _login_headerurl($login_header_url){
			$local_login_header = self::_get('local_login_header', false);
			if($local_login_header){
				$login_header_url = home_url();
			}
			return $login_header_url;
		}

		/**
		 * @return bool
		 */
		public static function _maybe_add_action($hook_name = '', $callback = [], $priority = 10, $accepted_args = 1){
			return self::_maybe_add_filter($hook_name, $callback, $priority, $accepted_args);
		}

		/**
		 * @return bool
		 */
		public static function _maybe_add_filter($hook_name = '', $callback = [], $priority = 10, $accepted_args = 1){
			$actions = self::_get('actions', []);
			if(in_array($hook_name, $actions)){
				return false;
			}
			$actions[] = $hook_name;
			self::_set('actions', $actions);
			return add_filter($hook_name, $callback, $priority, $accepted_args);
		}

		/**
		 * @return array
		 */
		public static function _pre_get_posts($query){
			global $pagenow;
			$hide_others_posts = self::_get('hide_others_posts', []);
			if($hide_others_posts){
				if('edit.php' === $pagenow and !current_user_can($hide_others_posts['capability'])){
					$query->set('author', get_current_user_id());
				}
			}
			return $query;
		}

		/**
		 * @return array
		 */
		public static function _rest_authentication_errors($errors){
			$hide_the_rest_api = self::_get('hide_the_rest_api', []);
			if($hide_the_rest_api){
				if(empty($errors)){
					if(!current_user_can($hide_the_rest_api['capability'])){
						$errors = self::error(__('You need a higher level of permission.'), [
							'status' => 401,
						]);
					}
				}
			}
			return $errors;
		}

		/**
		 * @return string
		 */
		public static function _sanitize_file_name($filename){
			$sanitize_file_names = self::_get('sanitize_file_names', []);
			if($sanitize_file_names){
				$filename = implode('.', array_map(function($piece){
					return preg_replace('/[^A-Za-z0-9_-]/', '', $piece);
				}, explode('.', $filename)));
			}
			return $filename;
		}

		/**
		 * @return bool
		 */
		public static function _show_admin_bar($show){
			$hide_the_toolbar = self::_get('hide_the_toolbar', []);
			if($hide_the_toolbar){
				if(!current_user_can($hide_the_toolbar['capability'])){
					$show = false;
				}
			}
			return $show;
		}

		/**
		 * @return bool
		 */
		public static function _set($name = '', $value = null){
			$name = trim($name);
			if('' === $name){
				return false;
			}
			$old_value = self::_get($name);
			if($old_value === $value){
				return false;
			}
			$GLOBALS[self::PREFIX . 'vars'][$name] = $value;
			return true;
		}

		/**
		 * @return void
		 */
		public static function _template_redirect(){
			$hide_the_entire_site = self::_get('hide_the_entire_site', []);
			if($hide_the_entire_site){
				$exclude_other_pages = in_array(get_the_ID(), (array) $hide_the_entire_site['exclude_other_pages']);
				$exclude_special_pages = ((is_front_page() and in_array('front_end', (array) $hide_the_entire_site['exclude_special_pages'])) or (is_home() and in_array('home', (array) $hide_the_entire_site['exclude_special_pages'])));
				if(!$exclude_other_pages and !$exclude_special_pages){
					if(is_user_logged_in()){
						if(!current_user_can($hide_the_entire_site['capability'])){
							wp_die('<h1>' . __('You need a higher level of permission.') . '</h1>' . '<p>' . __('Sorry, you are not allowed to access this page.') . '</p>', 403);
						}
					} else {
						auth_redirect();
					}
				}
			}
		}

		/**
		 * @return void
		 */
		public static function _tgmpa_register(){
			$tgmpa = self::_get('tgmpa', []);
			if($tgmpa){
				foreach($tgmpa as $args){
					tgmpa($args['plugins'], $args['config']);
				}
			}
		}

		/**
		 * @return bool
		 */
		static public function _wordfence_ls_require_captcha($required){
			return false;
		}

		/**
		 * @return bool
		 */
		static public function _wp_before_admin_bar_render(){
			$hide_wp = self::_get('hide_wp', false);
			if($hide_wp){
				global $wp_admin_bar;
				$wp_admin_bar->remove_node('wp-logo');
			}
		}

		/**
		 * @return array
		 */
		public static function _wp_check_filetype_and_ext($wp_check_filetype_and_ext, $file, $filename, $mimes, $real_mime){
			$fix_audio_video_ext = self::_get('fix_audio_video_ext', false);
			if($fix_audio_video_ext){
				if(!$wp_check_filetype_and_ext['ext'] or !$wp_check_filetype_and_ext['type']){
					if(0 === strpos($real_mime, 'audio/') or 0 === strpos($real_mime, 'video/')){
						$filetype = wp_check_filetype($filename);
						if(in_array(substr($filetype['type'], 0, strcspn($filetype['type'], '/')), ['audio', 'video'])){
							$wp_check_filetype_and_ext['ext'] = $filetype['ext'];
							$wp_check_filetype_and_ext['type'] = $filetype['type'];
						}
					}
				}
			}
			return $wp_check_filetype_and_ext;
		}

		/**
		 * @return void
		 */
		public static function _wp_enqueue_scripts(){
			$enqueue_scripts = self::_get('enqueue_scripts', false);
			$track_utm = self::_get('track_utm', false);
			if($enqueue_scripts or $track_utm){
				if(!wp_script_is('jquery')){
					wp_enqueue_script('jquery');
				}
			}
			if($enqueue_scripts){
				$handle = rtrim(self::PREFIX, '_');
				$file = plugin_dir_path(__FILE__) . 'ldc.js';
				self::local_enqueue($handle, $file, ['jquery']);
			}
			if($track_utm){
				$utm = self::utm_current_params();
				$utm['str'] = build_query($utm);
				wp_localize_script('jquery', 'utm', $utm);
			}
			$enqueue_stylesheet = self::_get('enqueue_stylesheet', false);
			if($enqueue_stylesheet){
				self::local_enqueue(get_stylesheet(), get_stylesheet_directory() . '/style.css');
			}
		}

		/**
		 * @return void
		 */
		public static function _wp_head(){
			$hide_recaptcha_badge = self::_get('hide_recaptcha_badge', false);
			if($hide_recaptcha_badge){ ?>
				<style type="text/css">
					.grecaptcha-badge {
						visibility: hidden !important;
					}
				</style><?php
			}
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

		/**
		 * @return void
		 */
		public static function add_admin_notice($message = '', $class = 'warning', $is_dismissible = false){
			$html = self::admin_notice_html($message, $class, $is_dismissible);
			$md5 = md5($html);
			$admin_notices = self::_get('admin_notices', []);
			$admin_notices[$md5] = $html;
			self::_set('admin_notices', $admin_notices);
			self::_maybe_add_action('admin_notices', [__CLASS__, '_admin_notices']);
		}

		/**
		 * @return void
		 */
		public static function add_external_rule($regex = '', $query = '', $file = ''){
			$rule = [
				'file' => $file,
				'query' => str_replace(home_url('/'), '', $query),
				'regex' => str_replace(home_url('/'), '', $regex),
			];
			$md5 = self::md5($rule);
			$external_rules = self::_get('external_rules', []);
			$external_rules[$md5] = $rule;
			self::_set('external_rules', $external_rules);
			self::_maybe_add_action('generate_rewrite_rules', [__CLASS__, '_generate_rewrite_rules']);
		}

		/**
		 * @return void
		 */
		public static function add_image_size($name = '', $width = 0, $height = 0, $crop = false){
			$size = sanitize_title($name);
			$image_sizes = self::_get('image_sizes', []);
			$image_sizes[$size] = $name;
			self::_set('image_sizes', $image_sizes);
			add_image_size($size, $width, $height, $crop);
			self::_maybe_add_filter('image_size_names_choose', [__CLASS__, '_image_size_names_choose']);
		}

		/**
		 * @return void
		 */
		public static function add_larger_image_sizes(){
			self::add_image_size('HD', 1280, 1280);
			self::add_image_size('Full HD', 1920, 1920);
			self::add_image_size('4K', 3840, 3840);
		}

		/**
		 * @return string
		 */
		public static function admin_notice_html($message = '', $class = 'warning', $is_dismissible = false){
			if(!in_array($class, ['error', 'info', 'success', 'warning'])){
				$class = 'warning';
			}
			if($is_dismissible){
				$class .= ' is-dismissible';
			}
			return '<div class="notice notice-' . $class . '"><p>' . $message . '</p></div>';
		}

		/**
		 * @return bool
		 */
		public static function are_plugins_active($plugins = []){
			if(!is_array($plugins)){
				return false;
			}
			foreach($plugins as $plugin){
				if(!self::is_plugin_active($plugin)){
					return false;
				}
			}
			return true;
		}

		/**
		 * @return bool
		 */
		public static function array_keys_exist($keys = [], $array = []){
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

		/**
		 * @return int
		 */
		public static function attachment_url_to_postid($url = ''){
			$post_id = self::guid_to_postid($url);
			if($post_id){
				return $post_id;
			}
			preg_match('/^(.+)(\-\d+x\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches); // resized
			if($matches){
				$url = $matches[1];
				if(isset($matches[3])){
					$url .= $matches[3];
				}
				$post_id = self::guid_to_postid($url);
				if($post_id){
					return $post_id;
				}
			}
			preg_match('/^(.+)(\-scaled)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches); // scaled
			if($matches){
				$url = $matches[1];
				if(isset($matches[3])){
					$url .= $matches[3];
				}
				$post_id = self::guid_to_postid($url);
				if($post_id){
					return $post_id;
				}
			}
			preg_match('/^(.+)(\-e\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches); // edited
			if($matches){
				$url = $matches[1];
				if(isset($matches[3])){
					$url .= $matches[3];
				}
				$post_id = self::guid_to_postid($url);
				if($post_id){
					return $post_id;
				}
			}
			return 0;
		}

		/**
		 * @return string
		 */
		public static function base64_urldecode($data = '', $strict = false){
			return base64_decode(strtr($data, '-_', '+/'), $strict);
		}

		/**
		 * @return string
		 */
		public static function base64_urlencode($data = ''){
			return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
		}

		/**
		 * @return string
		 */
		public static function basename($path = '', $suffix = ''){
			return wp_basename(preg_replace('/\?.*/', '', $path), $suffix);
		}

		/**
		 * @return void
		 */
		public static function bb_sort_image_sizes(){
			self::_set('bb_sort_image_sizes', true);
			self::_maybe_add_filter('fl_builder_photo_sizes_select', [__CLASS__, '_fl_builder_photo_sizes_select']);
		}

		/**
		 * @return Puc_v4p13_Plugin_UpdateChecker|Puc_v4p13_Theme_UpdateChecker|Puc_v4p13_Vcs_BaseChecker
		 */
		public static function build_update_checker(...$args){
			$remote_lib = self::use_plugin_update_checker();
			if(is_wp_error($remote_lib)){
				return $remote_lib;
			}
			return \Puc_v4_Factory::buildUpdateChecker(...$args);
		}

		/**
		 * @return string
		 */
		public static function canonicalize($key = ''){
			$key = sanitize_title($key);
			$key = str_replace('-', '_', $key);
			return $key;
		}

		/**
		 * @return array
		 */
		public static function cf7_additional_setting($name = '', $contact_form = null){
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return [];
			}
			return $contact_form->additional_setting($name, false);
		}

		/**
		 * @return WPCF7_ContactForm|null
		 */
		public static function cf7_contact_form($contact_form = null){
			$current_contact_form = wpcf7_get_current_contact_form();
			if(empty($contact_form)){ // null, false, 0 and other PHP falsey values
				return $current_contact_form;
			}
			if($contact_form instanceof \WPCF7_ContactForm){
				return $contact_form;
			}
			if(is_numeric($contact_form) or $contact_form instanceof \WP_Post){
				$contact_form = wpcf7_contact_form($contact_form); // replace the current contact form
				if(!is_null($current_contact_form)){
					wpcf7_contact_form($current_contact_form->id()); // restore the current contact form
				}
				return $contact_form; // null or WPCF7_ContactForm
			}
			if(is_string($contact_form)){
				$contact_form = wpcf7_get_contact_form_by_title($contact_form); // replace the current contact form
				if(!is_null($current_contact_form)){
					wpcf7_contact_form($current_contact_form->id()); // restore the current contact form
				}
				return $contact_form; // null or WPCF7_ContactForm
			}
			return null;
		}

		/**
		 * @return bool
		 */
		public static function cf7_fake_mail($contact_form = null, $submission = null){
			if(!did_action('wpcf7_before_send_mail')){
				return false; // too early
			}
			if(did_action('wpcf7_mail_failed') or did_action('wpcf7_mail_sent')){
				return false; // too late
			}
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return false;
			}
			$submission = self::cf7_submission($submission);
			if(is_null($submission)){
				return false;
			}
			if(!$submission->is('init')){
				return false; // try to prevent conflicts with other statuses
			}
			if(self::cf7_skip_mail($contact_form) or self::cf7_send_mail($contact_form)){ // skip or send
				$message = $contact_form->message('mail_sent_ok');
				$message = wp_strip_all_tags($message);
				$submission->set_response($message);
				$submission->set_status('mail_sent');
				return true;
			}
			$message = $contact_form->message('mail_sent_ng');
			$message = wp_strip_all_tags($message);
			$submission->set_response($message);
			$submission->set_status('mail_failed');
			return false;
		}

		/**
		 * @return array
		 */
		public static function cf7_invalid_fields($fields = [], $contact_form = null){
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return [];
			}
			if(!self::is_array_assoc($fields)){
				return [];
			}
			$invalid = [];
			$tags = wp_list_pluck($contact_form->scan_form_tags(), 'type', 'name');
			foreach($fields as $name => $type){
				if(!empty($tags[$name])){
					if(!in_array($tags[$name], (array) $type)){
						$invalid[] = $name;
					}
				}
			}
			return $invalid;
		}

		/**
		 * @return bool
		 */
		public static function cf7_is_false($name = '', $contact_form = null){
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return false;
			}
			return self::is_false(self::cf7_pref($name, $contact_form));
		}

		/**
		 * @return bool
		 */
		public static function cf7_is_true($name = '', $contact_form = null){
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return false;
			}
			return $contact_form->is_true($name);
		}

		/**
		 * @return array
		 */
		public static function cf7_missing_fields($fields = [], $contact_form = null){
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return [];
			}
			if(!self::is_array_assoc($fields)){
				return [];
			}
			$missing = [];
			$tags = wp_list_pluck($contact_form->scan_form_tags(), 'type', 'name');
			foreach(array_keys($fields) as $name){
				if(empty($tags[$name])){
					$missing[] = $name;
				}
			}
			return $missing;
		}

		/**
		 * @return string
		 */
		public static function cf7_pref($name = '', $contact_form = null){
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return '';
			}
			$pref = $contact_form->pref($name);
			if(is_null($pref)){
				return '';
			}
			return $pref;
		}

		/**
		 * @return array|null|string
		 */
		public static function cf7_raw_posted_data($key = ''){
			$cf7_posted_data = self::_get('cf7_posted_data', []);
			if(empty($cf7_posted_data)){
				$posted_data = array_filter((array) $_POST, function($key){
					return '_' !== substr($key, 0, 1);
				}, ARRAY_FILTER_USE_KEY);
				$cf7_posted_data = self::cf7_sanitize_posted_data($posted_data);
				self::_set('cf7_posted_data', $cf7_posted_data);
			}
			if('' === $key){
				return $cf7_posted_data;
			}
			if(isset($key, $cf7_posted_data)){
				return $cf7_posted_data[$key];
			}
			return null;
		}

		/**
		 * @return string
		 */
		public static function cf7_sanitize_posted_data($value = []){
			if(!empty($value)){
				if(is_array($value)){
					$value = array_map([__CLASS__, 'cf7_sanitize_posted_data'], $value);
				} elseif(is_string($value)){
					$value = wp_check_invalid_utf8($value);
					$value = wp_kses_no_null($value);
				}
			}
			return $value;
		}

		/**
		 * @return bool
		 */
		public static function cf7_send_mail($contact_form = null){
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return false;
			}
			$skip_mail = self::cf7_skip_mail($contact_form);
			if($skip_mail){
				return true;
			}
			$result = \WPCF7_Mail::send($contact_form->prop('mail'), 'mail');
			if(!$result){
				return false;
			}
			$additional_mail = [];
			if($mail_2 = $contact_form->prop('mail_2') and $mail_2['active']){
				$additional_mail['mail_2'] = $mail_2;
			}
			$additional_mail = apply_filters('wpcf7_additional_mail', $additional_mail, $contact_form);
			foreach($additional_mail as $name => $template){
				\WPCF7_Mail::send($template, $name);
			}
			return true;
		}

		/**
		 * @return bool
		 */
		public static function cf7_shortcode_attr($name = '', $contact_form = null){
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return '';
			}
			$att = $contact_form->shortcode_attr($name);
			if(is_null($att)){
				return '';
			}
			return $att;
		}

		/**
		 * @return bool
		 */
		public static function cf7_skip_mail($contact_form = null){
			$contact_form = self::cf7_contact_form($contact_form);
			if(is_null($contact_form)){
				return false;
			}
			$skip_mail = ($contact_form->in_demo_mode() or $contact_form->is_true('skip_mail') or !empty($contact_form->skip_mail));
			$skip_mail = apply_filters('wpcf7_skip_mail', $skip_mail, $contact_form);
			return boolval($skip_mail);
		}

		/**
		 * @return WPCF7_Submission|null
		 */
		public static function cf7_submission($submission = null){
			$current_submission = \WPCF7_Submission::get_instance();
			if(empty($submission)){ // null, false, 0 and other PHP falsey values
				return $current_submission;
			}
			if($submission instanceof \WPCF7_Submission){
				return $submission;
			}
			return null;
		}

		/**
		 * @return bool
		 */
		public static function cf7_tag_has_data_option($tag = null){
			if(!$tag instanceof \WPCF7_FormTag){
				return false;
			}
			return ($tag->get_data_option() ? true : false);
		}

		/**
		 * @return bool
		 */
		public static function cf7_tag_has_free_text($tag = null){
			if(!$tag instanceof \WPCF7_FormTag){
				return false;
			}
			return $tag->has_option('free_text');
		}

		/**
		 * @return bool
		 */
		public static function cf7_tag_has_pipes($tag = null){
			if(!$tag instanceof \WPCF7_FormTag){
				return false;
			}
			if(WPCF7_USE_PIPE and $tag->pipes instanceof \WPCF7_Pipes and !$tag->pipes->zero()){
				$pipes = $tag->pipes->to_array();
				foreach($pipes as $pipe){
					if($pipe[0] !== $pipe[1]){
						return true;
					}
				}
			}
			return false;
		}

		/**
		 * @return string|WP_Error
		 */
		static public function check_upload_dir($path = ''){
			$path = wp_normalize_path($path);
			$upload_dir = wp_get_upload_dir();
			if($upload_dir['error']){
				return self::error($upload_dir['error']);
			}
			$basedir = wp_normalize_path($upload_dir['basedir']);
			if(0 !== strpos($path, $basedir)){
				$error_msg = sprintf(__('Unable to locate needed folder (%s).'), __('The uploads directory'));
				return self::error($error_msg);
			}
			return $path;
		}

		/**
		 * @return bool|WP_Error
		 */
		static public function check_upload_size($file_size = 0){ // wp-includes/rest-api/endpoints/class-wp-rest-attachments-controller.php
			if(!is_multisite()){
				return true;
			}
			if(get_site_option('upload_space_check_disabled')){
				return true;
			}
			$space_left = get_upload_space_available();
			if($space_left < $file_size){
				$error_msg = sprintf(__('Not enough space to upload. %s KB needed.'), number_format(($file_size - $space_left) / KB_IN_BYTES));
				return self::error($error_msg);
			}
			if($file_size > (KB_IN_BYTES * get_site_option('fileupload_maxk', 1500))){
				$error_msg = sprintf(__('This file is too big. Files must be less than %s KB in size.'), get_site_option('fileupload_maxk', 1500));
				return self::error($error_msg);
			}
			if(!function_exists('upload_is_user_over_quota')){
				require_once(ABSPATH . 'wp-admin/includes/ms.php'); // Include multisite admin functions to get access to upload_is_user_over_quota().
			}
			if(upload_is_user_over_quota(false)){
				$error_msg = __('You have used your space quota. Please delete files before uploading.');
				return self::error($error_msg);
			}
			return true;
		}

		/**
		 * @return void
		 */
		public static function cl_add_image_size($name = '', $options = []){
			$cl_image_sizes = self::_get('cl_image_sizes', []);
			$config = self::cl_config();
			if(is_wp_error($config)){
				return;
			}
			$size = sanitize_title($name);
			if(remove_image_size($size)){
				$image_sizes = self::_get('image_sizes', []);
				if(isset($image_sizes[$size])){
					unset($image_sizes[$size]);
					self::_set('image_sizes', $image_sizes);
				}
			}
			add_image_size($size); // fake - required
			$cl_image_sizes[$size] = [
				'name' => $name,
				'options' => $options,
			];
			self::_set('cl_image_sizes', $cl_image_sizes);
			self::_maybe_add_filter('fl_builder_photo_sizes_select', [__CLASS__, '_fl_builder_photo_sizes_select']);
			self::_maybe_add_filter('image_downsize', [__CLASS__, '_image_downsize'], 10, 3);
			self::_maybe_add_filter('image_size_names_choose', [__CLASS__, '_image_size_names_choose']);
		}

		/**
		 * @return array|WP_Error
		 */
		public static function cl_config($config = []){
			$cl_config = self::_get('cl_config', []);
			if($cl_config){
				return $cl_config;
			}
			$remote_lib = self::use_cloudinary();
			if(is_wp_error($remote_lib)){
				return $remote_lib;
			}
			if(self::array_keys_exist(['api_key', 'api_secret', 'cloud_name'], $config)){
				$cl_config = \Cloudinary::config($config);
				self::_set('cl_config', $cl_config);
				return $cl_config;
			}
			$missing = [];
			if(!defined('CL_API_KEY')){
				$missing[] = 'API Key';
			}
			if(!defined('CL_API_SECRET')){
				$missing[] = 'API Secret';
			}
			if(!defined('CL_CLOUD_NAME')){
				$missing[] = 'Cloud Name';
			}
			if($missing){
				return self::error(sprintf(__('Missing parameter(s): %s'), self::implode_and($missing)) . '.');
			}
			$cl_config = \Cloudinary::config([
				'api_key' => CL_API_KEY,
				'api_secret' => CL_API_SECRET,
				'cloud_name' => CL_CLOUD_NAME,
			]);
			self::_set('cl_config', $cl_config);
			return $cl_config;
		}

		/**
		 * @return array|WP_Error
		 */
		public static function cl_upload($file = '', $options = []){
			$config = self::cl_config();
			if(is_wp_error($config)){
				return $config;
			}
			if(!@file_exists($file)){
				return self::error(__('File doesn&#8217;t exist?'), $file);
			}
			$message = '';
			try {
				$result = \Cloudinary\Uploader::upload($file, $options);
			} catch(Throwable $t){
				$message = $t->getMessage();
			} catch(Exception $e){
				$message = $e->getMessage();
			}
			if($message){
				return self::error($message);
			}
			return $result;
		}

		/**
		 * @return array|WP_Error
		 */
		public static function cl_upload_attachment($id = 0, $size = ''){
			if(!wp_attachment_is_image($id)){
				return self::error(__('File is not an image.'));
			}
			if(!is_scalar($size)){
				return self::error(sprintf(__('Invalid parameter(s): %s'), 'size') . '.');
			}
			$cl_image_sizes = self::_get('cl_image_sizes', []);
			$size = sanitize_title($size);
			if(!isset($cl_image_sizes[$size])){
				return self::error(sprintf(__('Invalid parameter(s): %s'), 'size') . '.');
			}
			$meta_key = self::PREFIX . 'cl_' . self::md5($cl_image_sizes[$size]['options']);
			$result = get_post_meta($id, $meta_key, true);
			if($result){
				return $result;
			}
			$file = get_attached_file($id);
			$result = self::cl_upload($file, $cl_image_sizes[$size]['options']);
			if(is_wp_error($result)){
				return $result;
			}
			update_post_meta($id, $meta_key, $result);
			return $result;
		}

		/**
		 * @return WP_Role|null
		 */
		public static function clone_role($source = '', $destination = '', $display_name = ''){
			$role = get_role($source);
			if(is_null($role)){
				return null;
			}
			$destination = self::canonicalize($destination);
			return add_role($destination, $display_name, $role->capabilities);
		}

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
			return ($current_screen->id === $id);
		}

		/**
		 * @return string
		 */
		public static function current_time($type = 'U', $offset_or_tz = ''){ // If $offset_or_tz is an empty string, the output is adjusted with the GMT offset in the WordPress option.
			if('timestamp' === $type){
				$type = 'U';
			}
			if('mysql' === $type){
				$type = 'Y-m-d H:i:s';
			}
			$timezone = $offset_or_tz ? self::timezone($offset_or_tz) : wp_timezone();
			$datetime = new \DateTime('now', $timezone);
			return $datetime->format($type);
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function custom_login_logo($attachment_id = 0, $half = true){
			if(!wp_attachment_is_image($attachment_id)){
				return self::error(__('File is not an image.'));
			}
			$custom_logo = wp_get_attachment_image_src($attachment_id, 'medium');
			$height = $custom_logo[2];
			$width = $custom_logo[1];
			if($half){
				$height = $height / 2;
				$width = $width / 2;
			}
			$custom_login_logo = [$custom_logo[0], $width, $height];
			self::_set('custom_login_logo', $custom_login_logo);
			self::_maybe_add_action('login_enqueue_scripts', [__CLASS__, '_login_enqueue_scripts']);
			return true;
		}

		/**
		 * @return string
		 */
		public static function date_convert($string = '', $fromtz = '', $totz = '', $format = 'Y-m-d H:i:s'){
			$datetime = date_create($string, self::timezone($fromtz));
			if($datetime === false){
				return gmdate($format, 0);
			}
			return $datetime->setTimezone(self::timezone($totz))->format($format);
		}

		/**
		 * @return string|WP_Error
		 */
		public static function dir_to_url($path = ''){
			return str_replace(wp_normalize_path(ABSPATH), site_url('/'), wp_normalize_path($path));
		}

		/**
		 * @return string|WP_Error
		 */
		public static function download_dir(){
			$upload_dir = wp_get_upload_dir();
			if($upload_dir['error']){
				return self::error($upload_dir['error']);
			}
			$path = $upload_dir['basedir'];
			$download_dir = $path . '/downloads';
			if(!wp_mkdir_p($download_dir)){
				return self::error(__('Could not create directory.'));
			}
			if(!wp_is_writable($download_dir)){
				return self::error(__('Destination directory for file streaming does not exist or is not writable.'));
			}
			return $download_dir;
		}

		/**
		 * @return void
		 */
		public static function enqueue($handle = '', $src = '', $deps = [], $ver = false, $in_footer = true){
			$mimes = [
				'css' => 'text/css',
				'js' => 'application/javascript',
			];
			$filetype = wp_check_filetype(self::basename($src), $mimes);
			switch($filetype['type']){
				case 'application/javascript':
					wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);
					break;
				case 'text/css':
					wp_enqueue_style($handle, $src, $deps, $ver);
					break;
			}
		}

		/**
		 * @return void
		 */
		public static function enqueue_scripts(){
			self::_set('enqueue_scripts', true);
			self::_maybe_add_action('wp_enqueue_scripts', [__CLASS__, '_wp_enqueue_scripts'], 9);
		}

		/**
		 * @return void
		 */
		public static function enqueue_stylesheet(){
			self::_set('enqueue_stylesheet', true);
			self::_maybe_add_action('wp_enqueue_scripts', [__CLASS__, '_wp_enqueue_scripts'], 9);
		}

		/**
		 * @return WP_Error
		 */
		public static function error($message = '', $data = ''){
			if(is_wp_error($message)){
				$data = $message->get_error_data();
				$message = $message->get_error_message();
			}
			if(empty($message)){
				$message = __('Something went wrong.');
			}
			return new \WP_Error(self::PREFIX . 'error', $message, $data);
		}

		/**
		 * @return bool
		 */
		public static function external_rule_exists($regex = '', $query = ''){
			$rewrite_rules = self::_get('rewrite_rules', []);
			if(empty($rewrite_rules)){
				$rewrite_rules = array_filter(extract_from_markers(get_home_path() . '.htaccess', 'WordPress'));
				self::_set('rewrite_rules', $rewrite_rules);
			}
			$regex = str_replace('.+?', '.+', $regex); // Apache 1.3 does not support the reluctant (non-greedy) modifier.
			$rule = 'RewriteRule ^' . $regex . ' ' . self::home_root() . $query . ' [QSA,L]';
			return in_array($rule, $rewrite_rules);
		}

		/**
		 * @return string
		 */
		public static function fa_file_type($post = null){
			if('attachment' !== get_post_status($post)){
				return '';
			}
			if(wp_attachment_is('audio', $post)){
				return 'audio';
			}
			if(wp_attachment_is('image', $post)){
				return 'image';
			}
			if(wp_attachment_is('video', $post)){
				return 'video';
			}
			$type = get_post_mime_type($post);
			switch($type){
				case 'application/zip':
				case 'application/x-rar-compressed':
				case 'application/x-7z-compressed':
				case 'application/x-tar':
					return 'archive';
					break;
				case 'application/vnd.ms-excel':
				case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
					return 'excel';
					break;
				case 'application/pdf':
					return 'pdf';
					break;
				case 'application/vnd.ms-powerpoint':
				case 'application/vnd.openxmlformats-officedocument.presentationml.presentation':
					return 'powerpoint';
					break;
				case 'application/msword':
				case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
					return 'word';
					break;
				default:
					return 'file';
			}
		}

		/**
		 * @return string
		 */
		public static function file(){
			return __FILE__;
		}

		/**
		 * @return simple_html_dom|WP_Error
		 */
		public static function file_get_html(...$args){
			$remote_lib = self::use_simple_html_dom();
			if(is_wp_error($remote_lib)){
				return $remote_lib;
			}
			return file_get_html(...$args);
		}

		/**
		 * @return WP_Filesystem_Base|WP_Error
		 */
		public static function filesystem(){
			global $wp_filesystem;
			if(!function_exists('get_filesystem_method')){
				require_once(ABSPATH . 'wp-admin/includes/file.php');
			}
			if('direct' !== get_filesystem_method()){
				return self::error(__('Could not access filesystem.'));
			}
			if($wp_filesystem instanceof \WP_Filesystem_Base){
				return $wp_filesystem;
			}
			if(!WP_Filesystem()){
				return self::error(__('Filesystem error.'));
			}
			return $wp_filesystem;
		}

		/**
		 * @return string
		 */
		public static function first_p($text = '', $dot = true){
			return self::one_p($text, $dot, 'first');
		}

		/**
		 * @return void
		 */
		public static function fix_audio_video_ext(){
			self::_set('fix_audio_video_ext', true);
			self::_maybe_add_filter('wp_check_filetype_and_ext', [__CLASS__, '_wp_check_filetype_and_ext'], 10, 5);
		}

		/**
		 * @return string
		 */
		public static function format_function($function_name = '', $args = []){
			$str = '<div style="color: #24831d; font-family: monospace; font-weight: 400;">' . $function_name . '(';
			$function_args = [];
			foreach($args as $arg){
				$arg = shortcode_atts([
					'default' => 'null',
					'name' => '',
					'type' => '',
				], $arg);
				if($arg['default'] and $arg['name'] and $arg['type']){
					$function_args[] = '<span style="color: #cd2f23; font-family: monospace; font-style: italic; font-weight: 400;">' . $arg['type'] . '</span> <span style="color: #0f55c8; font-family: monospace; font-weight: 400;">$' . $arg['name'] . '</span> = <span style="color: #000; font-family: monospace; font-weight: 400;">' . $arg['default'] . '</span>';
				}
			}
			if($function_args){
				$str .= ' ' . implode(', ', $function_args) . ' ';
			}
			$str .= ')</div>';
			return $str;
		}

		/**
		 * @return int
		 */
		public static function get_memory_size(){
			if(!function_exists('exec')){
				$current_limit = ini_get('memory_limit');
				$current_limit_int = wp_convert_hr_to_bytes($current_limit);
				return $current_limit_int;
			}
			exec('free -b', $output);
			$output = sanitize_text_field($output[1]);
			$output = explode(' ', $output);
			return (int) $output[1];
		}

		/**
		 * @return array
		 */
		public static function get_posts_query($args = null){
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
				$parsed_args['post_status'] = ('attachment' === $parsed_args['post_type']) ? 'inherit' : 'publish';
			}
			if(!empty($parsed_args['numberposts']) and empty($parsed_args['posts_per_page'])){
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
			$query = new \WP_Query;
			$query->query($parsed_args);
			return $query;
		}

		/**
		 * @return int
		 */
		public static function guid_to_postid($guid = '', $check_rewrite_rules = false){
			global $wpdb;
			$query = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid = %s", $guid);
			$post_id = $wpdb->get_var($query);
			if(null !== $post_id){
				return intval($post_id);
			}
			if($check_rewrite_rules){
				return url_to_postid($guid);
			}
			return 0;
		}

		/**
		 * @return array|WP_Error
		 */
		public static function handle_file($file = [], $dir = '', $mimes = null){
			if(empty($file)){
				$error_msg = __('No data supplied.');
				return self::error($error_msg);
			}
			if(!is_array($file)){
				if(is_scalar($file)){
					if(empty($_FILES[$file])){
						$error_msg = __('File does not exist! Please double check the name and try again.');
						return self::error($error_msg);
					} else {
						$file = $_FILES[$file];
					}
				} else {
					$error_msg = __('Invalid data provided.');
					return self::error($error_msg);
				}
			}
			$keys = ['error', 'name', 'size', 'tmp_name', 'type'];
			foreach($keys as $key){
				if(isset($file[$key])){
					$file[$key] = (array) $file[$key];
				} else {
					$file[$key] = [];
				}
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

		/**
		 * @return array|WP_Error
		 */
		public static function handle_files($files = [], $dir = '', $mimes = null){
			if(empty($files)){
				if(empty($_FILES)){
					$error_msg = __('No data supplied.');
					return self::error($error_msg);
				} else {
					$files = $_FILES;
				}
			}
			$uploaded_files = [];
			foreach($files as $key => $file){
				$uploaded_files[$key] = self::handle_file($file, $dir, $mimes);
			}
			return $uploaded_files;
		}

		/**
		 * @return string|WP_Error
		 */
		public static function handle_upload($file = [], $dir = '', $mimes = null){
			if(!empty($dir)){
				if(!@is_dir($dir) or !wp_is_writable($dir)){
					$error_msg =  __('Destination directory for file streaming does not exist or is not writable.');
					return self::error($error_msg);
				}
			}
			if(empty($file)){
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
				$upload_dir = self::check_upload_dir($dir);
				if(is_wp_error($upload_dir)){
					return $upload_dir;
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
			if(false === $move_new_file){
				$error_path = str_replace(ABSPATH, '', $dir);
				$error_msg = sprintf(__('The uploaded file could not be moved to %s.'), $error_path);
				return self::error($error_msg);
			}
			$stat = stat(dirname($new_file));
			$perms = $stat['mode'] & 0000666;
			chmod($new_file, $perms); // Set correct file permissions.
			if(is_multisite()){
				clean_dirsize_cache($new_file);
			}
			return $new_file;
		}

		/**
		 * @return void
		 */
		public static function hide_recaptcha_badge(){
			self::_set('hide_recaptcha_badge', true);
			self::_maybe_add_action('wp_head', [__CLASS__, '_wp_head']);
		}

		/**
		 * @return void
		 */
		public static function hide_the_dashboard($capability = 'edit_posts', $location = ''){
			self::_set('hide_the_dashboard', [
				'capability' => $capability,
				'location' => $location,
			]);
			self::_maybe_add_action('admin_init', [__CLASS__, '_admin_init']);
		}

		/**
		 * @return void
		 */
		public static function hide_the_toolbar($capability = 'edit_posts'){
			self::_set('hide_the_toolbar', [
				'capability' => $capability,
			]);
			self::_maybe_add_filter('show_admin_bar', [__CLASS__, '_show_admin_bar']);
		}

		/**
		 * @return void
		 */
		public static function hide_others_media($capability = 'edit_others_posts'){
			self::_set('hide_others_media', [
				'capability' => $capability,
			]);
			self::_maybe_add_filter('ajax_query_attachments_args', [__CLASS__, '_ajax_query_attachments_args']);
		}

		/**
		 * @return void
		 */
		public static function hide_others_posts($capability = 'edit_others_posts'){
			self::_set('hide_others_posts', [
				'capability' => $capability,
			]);
			self::_maybe_add_action('current_screen', [__CLASS__, '_current_screen']);
			self::_maybe_add_action('pre_get_posts', [__CLASS__, '_pre_get_posts']);
		}

		/**
		 * @return void
		 */
		public static function hide_the_rest_api($capability = 'read'){
			self::_set('hide_the_rest_api', [
				'capability' => $capability,
			]);
			self::_maybe_add_filter('rest_authentication_errors', [__CLASS__, '_rest_authentication_errors']);
		}

		/**
		 * @return void
		 */
		public static function hide_the_entire_site($capability = 'read', $exclude_other_pages = [], $exclude_special_pages = []){
			self::_set('hide_the_entire_site', [
				'capability' => $capability,
				'exclude_other_pages' => $exclude_other_pages,
				'exclude_special_pages' => $exclude_special_pages,
			]);
			self::_maybe_add_action('template_redirect', [__CLASS__, '_template_redirect']);
		}

		/**
		 * @return void
		 */
		public static function hide_uploads_subdir($subdir = '', $file = ''){
			$hide_uploads_subdir = self::_get('hide_uploads_subdir', []);
			$args = [
				'file' => $file,
				'subdir' => $subdir,
			];
			$md5 = self::md5($args);
			if(isset($hide_uploads_subdir[$md5])){
				return;
			}
			$hide_uploads_subdir[$md5] = $args;
			if(is_multisite()){
				return;
			}
			$uploads_use_yearmonth_folders = false;
			$subdir = ltrim(untrailingslashit(trim($subdir)), '/');
			if($subdir){
				$subdir = '/' . $subdir;
			} else {
				$subdir = '';
				if(get_option('uploads_use_yearmonth_folders')){
					$subdir = '/(\d{4})/(\d{2})';
					$uploads_use_yearmonth_folders = true;
				}
			}
			$upload_dir = wp_get_upload_dir();
			if($upload_dir['error']){
				return self::error($upload_dir['error']);
			}
			$path = plugin_dir_path(__FILE__) . 'readfile.php';
			$tmp = str_replace(wp_normalize_path(ABSPATH), '', wp_normalize_path($path));
			$parts = explode('/', $tmp);
			$levels = count($parts);
			$query = self::dir_to_url($path);
			$regex = $upload_dir['baseurl'] . $subdir. '/(.+)';
			if($uploads_use_yearmonth_folders){
				$atts['yyyy'] = '$1';
				$atts['mm'] = '$2';
				$atts['file'] = '$3';
			} else {
				$atts['file'] = '$1';
			}
			$atts['levels'] = $levels;
			$query = add_query_arg($atts, $query);
			self::add_external_rule($regex, $query, $file);
		}

		/**
		 * @return void
		 */
		public static function hide_wp(){
			self::_set('hide_wp', true);
			self::local_login_header();
			self::_maybe_add_action('admin_init', [__CLASS__, '_admin_init']);
			self::_maybe_add_action('wp_before_admin_bar_render', [__CLASS__, '_wp_before_admin_bar_render']);
		}

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

		/**
		 * @return string
		 */
		public static function implode_and($array = [], $and = '&'){
			if(empty($array)){
				return '';
			}
			if(1 === count($array)){
				return $array[0];
			}
			$last = array_pop($array);
			return implode(', ', $array) . ' ' . trim($and) . ' ' . $last;
		}

		/**
		 * @return bool
		 */
		public static function is_array_assoc($array = []){
			if(!is_array($array)){
				return false;
			}
			$end = count($array) - 1;
			if(array_keys($array) === range(0, $end)){
				return false;
			}
			return $array;
		}

		/**
		 * @return bool
		 */
		public static function is_cloudflare(){
			return !empty($_SERVER['CF-ray']);
		}

		/**
		 * @return bool
		 */
		public static function is_doing_heartbeat(){
			return (wp_doing_ajax() and isset($_POST['action']) and 'heartbeat' === $_POST['action']);
		}

		/**
		 * @return bool
		 */
		public static function is_extension_allowed($extension = ''){
			foreach(wp_get_mime_types() as $exts => $mime){
				if(preg_match('!^(' . $exts . ')$!i', $extension)){
					return true;
				}
			}
			return false;
		}

		/**
		 * @return bool
		 */
		public static function is_false($data = ''){
			return in_array((string) $data, ['0', 'false', 'off'], true);
		}

		/**
		 * @return bool|string
		 */
		public static function is_google_workspace($email = ''){
			if(!is_email($email)){
				return false;
			}
			list($local, $domain) = explode('@', $email, 2);
			if('gmail.com' === strtolower($domain)){
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

		/**
		 * @return bool|string
		 */
		public static function is_mysql_date($subject = ''){
			$pattern = '/^\d{4}\-(0[1-9]|1[0-2])\-(0[1-9]|[12]\d|3[01]) ([01]\d|2[0-3]):([0-5]\d):([0-5]\d)$/';
			if(!preg_match($pattern, $subject)){
				return false;
			}
			return $subject;
		}

		/**
		 * @return bool
		 */
		public static function is_plugin_active($plugin = ''){
			if(!function_exists('is_plugin_active')){
				require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			}
			return is_plugin_active($plugin);
		}

		/**
		 * @return bool
		 */
		public static function is_plugin_deactivating($file = ''){
			global $pagenow;
			if(!@is_file($file)){
				return false;
			}
			return (is_admin() and 'plugins.php' === $pagenow and isset($_GET['action'], $_GET['plugin']) and 'deactivate' === $_GET['action'] and plugin_basename($file) === $_GET['plugin']);
		}

		/**
		 * @return bool
		 */
		public static function is_post_revision_or_auto_draft($post = null){
			return (wp_is_post_revision($post) or 'auto-draft' === get_post_status($post));
		}

		/**
		 * @return bool
		 */
		public static function is_true($data = ''){
			return in_array((string) $data, ['1', 'on', 'true'], true);
		}

		/**
		 * @return bool|WP_Error
		 */
		static public function is_wp_error($error = []){
			if(is_wp_error($error)){
				return $error;
			}
			if(!self::array_keys_exist(['code', 'data', 'message'], $error)){
				return false;
			}
			if(4 === count($error)){
				if(!array_key_exists('additional_errors', $error)){
					return false;
				}
			} else {
				if(3 !== count($error)){
					return false;
				}
			}
			if(!$error['code'] or !$error['message']){
				return false;
			}
			return new \WP_Error($error['code'], $error['message'], $error['data']);
		}

		/**
		 * @return array|bool
		 */
		static public function is_wp_http_request($args = [], $method_verification = false){
			if(!is_array($args)){
				return false;
			}
			$wp_http_request_args = ['method', 'timeout', 'redirection', 'httpversion', 'user-agent', 'reject_unsafe_urls', 'blocking', 'headers', 'cookies', 'body', 'compress', 'decompress', 'sslverify', 'sslcertificates', 'stream', 'filename', 'limit_response_size'];
			$wp_http_request = true;
			foreach(array_keys($args) as $arg){
				if(!in_array($arg, $wp_http_request_args)){
					$wp_http_request = false;
					break;
				}
			}
			if(!$method_verification){
				return $wp_http_request;
			}
			if(empty($args['method'])){
				return false;
			}
			if(!in_array($args['method'], ['DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'])){
				return false;
			}
			return $args;
		}

		/**
		 * @return stdClass|WP_Error
		 */
		public static function jwt_decode(...$args){
			$remote_lib = self::use_jwt();
			if(is_wp_error($remote_lib)){
				return $remote_lib;
			}
			return \Firebase\JWT\JWT::decode(...$args);
		}

		/**
		 * @return string|WP_Error
		 */
		public static function jwt_encode(...$args){
			$remote_lib = self::use_jwt();
			if(is_wp_error($remote_lib)){
				return $remote_lib;
			}
			return \Firebase\JWT\JWT::encode(...$args);
		}

		/**
		 * @return array
		 */
		public static function ksort_deep($array = []){
			if(!self::is_array_assoc($array)){
				return $array;
			}
			ksort($array);
			foreach($array as $key => $value){
				$array[$key] = self::ksort_deep($value);
			}
			return $array;
		}

		/**
		 * @return string
		 */
		public static function last_p($text = '', $dot = true){
			return self::one_p($text, $dot, 'last');
		}

		/**
		 * @return array
		 */
		public static function list_pluck($list = [], $index_key = ''){
			$newlist = [];
			foreach($list as $value){
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

		/**
		 * @return void
		 */
		public static function local_enqueue($handle = '', $file = '', $deps = []){
			if(!file_exists($file)){
				return;
			}
			$src = self::dir_to_url($file);
			$ver = filemtime($file);
			self::enqueue($handle, $src, $deps, $ver, true);
		}

		/**
		 * @return void
		 */
		public static function local_login_header(){
			self::_set('local_login_header', true);
			self::_maybe_add_filter('login_headertext', [__CLASS__, '_login_headertext']);
			self::_maybe_add_filter('login_headerurl', [__CLASS__, '_login_headerurl']);
		}

		/**
		 * @return string
		 */
		public static function localize($data = []){
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

		/**
		 * @return bool
		 */
		public static function maybe_generate_attachment_metadata($attachment_id = 0){
			$attachment = get_post($attachment_id);
			if(null === $attachment){
				return false;
			}
			if('attachment' !== $attachment->post_type){
				return false;
			}
			wp_raise_memory_limit('image');
			if(!function_exists('wp_generate_attachment_metadata')){
				require_once(ABSPATH . 'wp-admin/includes/image.php');
			}
			wp_maybe_generate_attachment_metadata($attachment);
			return true;
		}

		/**
		 * @return string
		 */
		public static function md5($data = ''){
			if(is_object($data)){
				if($data instanceof \Closure){
					$md5_closure = self::md5_closure($data);
					if(is_wp_error($md5_closure)){
						$data = serialize($data);
						return md5($data);
					} else {
						return $md5_closure;
					}
				} else {
					$data = wp_json_encode($data);
					$data = json_decode($data, true);
				}
			}
			if(is_array($data)){
				$data = self::ksort_deep($data);
				$data = serialize($data);
			}
			return md5($data);
		}

		/**
		 * @return string|WP_Error
		 */
		public static function md5_closure($data = null, $spl_object_hash = false){
			if($data instanceof \Closure){
				$wrapper = self::serializable_closure($data);
				if(is_wp_error($wrapper)){
					return $wrapper;
				}
				$serialized = serialize($wrapper);
				if(!$spl_object_hash){
					$spl_object_hash = spl_object_hash($data);
					$serialized = str_replace($spl_object_hash, 'spl_object_hash', $serialized);
				}
				return md5($serialized);
			} else {
				return self::error(__('Invalid object type.'));
			}
		}

		/**
		 * @return string
		 */
		public static function md5_to_uuid4($md5 = ''){
			if(32 !== strlen($md5)){
				return '';
			}
			return substr($md5, 0, 8) . '-' . substr($md5, 8, 4) . '-' . substr($md5, 12, 4) . '-' . substr($md5, 16, 4) . '-' . substr($md5, 20, 12);
		}

		/**
		 * @return array
		 */
		public static function offset_or_tz($offset_or_tz = ''){ // Default GMT offset or timezone string. Must be either a valid offset (-12 to 14) or a valid timezone string.
			if(is_numeric($offset_or_tz)){
				return [
					'gmt_offset' => $offset_or_tz,
					'timezone_string' => '',
				];
			} else {
				if(preg_match('/^UTC[+-]/', $offset_or_tz)){ // Map UTC+- timezones to gmt_offsets and set timezone_string to empty.
					return [
						'gmt_offset' => intval(preg_replace('/UTC\+?/', '', $offset_or_tz)),
						'timezone_string' => '',
					];
				} else {
					if(in_array($offset_or_tz, timezone_identifiers_list())){
						return [
							'gmt_offset' => 0,
							'timezone_string' => $offset_or_tz,
						];
					} else {
						return [
							'gmt_offset' => 0,
							'timezone_string' => 'UTC',
						];
					}
				}
			}
		}

		/**
		 * @return string
		 */
		public static function one_p($text = '', $dot = true, $p = 'first'){
			if(false === strpos($text, '.')){
				if($dot){
					$text .= '.';
				}
				return $text;
			} else {
				$text = sanitize_text_field($text);
				$text = explode('.', $text);
				$text = array_map('trim', $text);
				$text = array_filter($text);
				switch($p){
					case 'first':
						$text = array_shift($text);
						break;
					case 'last':
						$text = array_pop($text);
						break;
					default:
						$p = absint($p);
						if(count($text) >= $p){
							$p --;
							$text = $text[$p];
						} else {
							$text = __('Error');
						}
				}
				if($dot){
					$text .= '.';
				}
				return $text;
			}
		}

		/**
		 * @return string
		 */
		public static function prepare($str = '', ...$args){
			global $wpdb;
			if(!$args){
				return $str;
			}
			if(false === strpos($str, '%')){
				return $str;
			} else {
				return str_replace("'", '', $wpdb->remove_placeholder_escape($wpdb->prepare($str, ...$args)));
			}
		}

		/**
		 * @return array
		 */
		public static function post_type_labels($singular = '', $plural = '', $all = true){
			if(empty($singular)){
				return [];
			}
			if(empty($plural)){
				$plural = $singular;
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

		/**
		 * @return string
		 */
		public static function read_file_chunk($handle = null, $chunk_size = 0, $chunk_lenght = 0){
			$giant_chunk = '';
			if(is_resource($handle) and $chunk_size){
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

		/**
		 * @return string
		 */
		public static function recaptcha_branding(){
			return 'This site is protected by reCAPTCHA and the Google <a href="https://policies.google.com/privacy" target="_blank">Privacy Policy</a> and <a href="https://policies.google.com/terms" target="_blank">Terms of Service</a> apply.';
		}

		/**
		 * @return string
		 */
		public static function remote_country(){
			switch(true){
				case !empty($_SERVER['HTTP_CF_IPCOUNTRY']):
					$country = $_SERVER['HTTP_CF_IPCOUNTRY'];
					break;
				case is_callable(['wfUtils', 'IP2Country']):
					$country = \wfUtils::IP2Country(self::remote_ip());
					break;
				default:
					$country = '';
			}
			return strtoupper($country);
		}

		/**
		 * @return array|string|WP_Error
		 */
		public static function remote_delete($url = '', $args = []){
			return self::remote_request('DELETE', $url, $args);
		}

		/**
		 * @return string|WP_Error
		 */
		public static function remote_download($url = '', $args = []){
			$args = wp_parse_args($args, [
				'filename' => '',
				'timeout' => 300,
			]);
			if(empty($args['filename'])){
				$download_dir = self::download_dir();
				if(is_wp_error($download_dir)){
					return $download_dir;
				}
				$filename = self::basename($url);
				$filename = wp_unique_filename($download_dir, $filename);
				$args['filename'] = trailingslashit($download_dir) . $filename;
			} else {
				$filename = self::check_upload_dir($args['filename']);
				if(is_wp_error($filename)){
					return $filename;
				}
			}
			$args['stream'] = true;
			$args['timeout'] = self::sanitize_timeout($args['timeout']);
			$response = self::remote_request('GET', $url, $args);
			if(is_wp_error($response)){
				@unlink($args['filename']);
				return $response;
			}
			return $args['filename'];
		}

		/**
		 * @return array|string|WP_Error
		 */
		public static function remote_get($url = '', $args = []){
			return self::remote_request('GET', $url, $args);
		}

		/**
		 * @return array|string|WP_Error
		 */
		public static function remote_head($url = '', $args = []){
			return self::remote_request('HEAD', $url, $args);
		}

		/**
		 * @return string
		 */
		public static function remote_ip($default = ''){
			switch(true){
				case !empty($_SERVER['HTTP_CF_CONNECTING_IP']):
					$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
					break;
				case is_callable(['wfUtils', 'getIP']):
					$ip = \wfUtils::getIP();
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
					return $default;
			}
			if(false === strpos($ip, ',')){
				$ip = trim($ip);
			} else {
				$ip = explode(',', $ip);
				$ip = array_map('trim', $ip);
				$ip = array_filter($ip);
				if(!$ip){
					return $default;
				}
				$ip = $ip[0];
			}
			if(!\WP_Http::is_ip_address($ip)){
				return $default;
			}
			return $ip;
		}

		/**
		 * @return string|WP_Error
		 */
		public static function remote_lib($url = '', $expected_dir = ''){
			$download_dir = self::download_dir();
			if(is_wp_error($download_dir)){
				return $download_dir;
			}
			$fs = self::filesystem();
			if(is_wp_error($fs)){
				return $fs;
			}
			$name = 'remote_lib_' . md5($url);
			$to = trailingslashit($download_dir) . $name;
			if(empty($expected_dir)){
				$expected_dir = $to;
			} else {
				$expected_dir = ltrim($expected_dir, '/');
				$expected_dir = untrailingslashit($expected_dir);
				$expected_dir = trailingslashit($to) . $expected_dir;
			}
			$dirlist = $fs->dirlist($expected_dir, false);
			if(!empty($dirlist)){
				return $expected_dir;
			}
			$file = self::remote_download($url);
			if(is_wp_error($file)){
				return $file;
			}
			$result = unzip_file($file, $to);
			@unlink($file);
			if(is_wp_error($result)){
				$fs->rmdir($to, true);
				return $result;
			}
			if(!$fs->dirlist($expected_dir, false)){
				$fs->rmdir($to, true);
				return self::error(__('Destination directory for file streaming does not exist or is not writable.'));
			}
			return $expected_dir;
		}

		/**
		 * @return array|string|WP_Error
		 */
		public static function remote_options($url = '', $args = []){
			return self::remote_request('OPTIONS', $url, $args);
		}

		/**
		 * @return array|string|WP_Error
		 */
		public static function remote_patch($url = '', $args = []){
			return self::remote_request('PATCH', $url, $args);
		}

		/**
		 * @return array|string|WP_Error
		 */
		public static function remote_post($url = '', $args = []){
			return self::remote_request('POST', $url, $args);
		}

		/**
		 * @return array|string|WP_Error
		 */
		public static function remote_put($url = '', $args = []){
			return self::remote_request('PUT', $url, $args);
		}

		/**
		 * @return array|string|WP_Error
		 */
		public static function remote_request($method = '', $url = '', $args = []){
			$args = self::sanitize_remote_args($args);
			if(empty($args['cookies'])){
				$location = wp_sanitize_redirect($url);
				if(wp_validate_redirect($location)){
					$args['cookies'] = $_COOKIE;
				}
			}
			$args['method'] = strtoupper($method);
			if(empty($args['user-agent'])){
				if(empty($_SERVER['HTTP_USER_AGENT'])){
					$args['user-agent'] = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36';
				} else {
					$args['user-agent'] = $_SERVER['HTTP_USER_AGENT'];
				}
			}
			$is_json = self::remote_request_is_json($args);
			if(is_wp_error($is_json)){
				return $is_json;
			}
			if($is_json){
				$args['body'] = wp_json_encode($args);
			}
			$response = wp_remote_request($url, $args);
			if(is_wp_error($response)){
				return $response;
			}
			$body = wp_remote_retrieve_body($response);
			$code = wp_remote_retrieve_response_code($response);
			$headers = wp_remote_retrieve_headers($response);
			$request = new \WP_REST_Request($method);
			$request->set_body($body);
			$request->set_headers($headers);
			$is_valid = $request->has_valid_params();
			if(is_wp_error($is_valid)){
				return $is_valid; // regardless of the response code
			}
			$is_json = $request->is_json_content_type();
			$json_params = [];
			if($is_json){
				$json_params = $request->get_json_params();
				$error = self::is_wp_error($json_params);
				if(is_wp_error($error)){
					return $error; // regardless of the response code
				}
			}
			if($code >= 200 and $code < 300){
				if($is_json){
					return $json_params;
				}
				return $body;
			}
			$message = wp_remote_retrieve_response_message($response);
			if(empty($message)){
				$message = get_status_header_desc($code);
			}
			if(empty($message)){
				$message = __('Something went wrong.');
			}
			return self::error($message, [
				'body' => $body,
				'headers' => $headers,
				'status' => $code,
			]);
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function remote_request_is_json($args = []){
			if(!self::is_wp_http_request($args, true)){
				return self::error(__('Invalid request method.'));
			}
			if(empty($args['headers'])){
				return false;
			}
			$request = new \WP_REST_Request($args['method']);
			$request->set_headers($args['headers']);
			return $request->is_json_content_type();
		}

		/**
		 * @return array|string|WP_Error
		 */
		public static function remote_trace($url = '', $args = []){
			return self::remote_request('TRACE', $url, $args);
		}

		/**
		 * @return string
		 */
		public static function remove_whitespaces($str = ''){
			return trim(preg_replace('/[\n\r\s\t]+/', ' ', $str));
		}

		/**
		 * @return void
		 */
		public static function sanitize_file_names(){
			self::_set('sanitize_file_names', true);
			self::_maybe_add_filter('sanitize_file_name', [__CLASS__, '_sanitize_file_name']);
		}

		/**
		 * @return array
		 */
		static public function sanitize_remote_args($args = []){
			if(!is_array($args)){
				$args = wp_parse_args($args);
			}
			if(self::is_wp_http_request($args)){
				return $args;
			}
			return [
				'body' => $args,
			];
		}

		/**
		 * @return int
		 */
		public static function sanitize_timeout($timeout = 0){
			$timeout = (int) $timeout;
			if($timeout < 0){
				$timeout = 0;
			}
			$max_execution_time = (int) ini_get('max_execution_time');
			if(0 !== $max_execution_time){
				if(0 === $timeout or $timeout > $max_execution_time){
					$timeout = $max_execution_time - 1;
				}
			}
			if(self::is_cloudflare()){ // TODO: check for Cloudflare Enterprise
				if(0 === $timeout or $timeout > 98){
					$timeout = 98; // If the max_execution_time is set to greater than 98 seconds, reduce it a bit to prevent edge-case timeouts that may happen before the page is fully loaded.
				}
			}
			return $timeout;
		}

		/**
		 * @return Opis\Closure\SerializableClosure|WP_Error
		 */
		public static function serializable_closure(...$args){
			$remote_lib = self::use_serializable_closures();
			if(is_wp_error($remote_lib)){
				return $remote_lib;
			}
			return new \Opis\Closure\SerializableClosure(...$args);
		}

		/**
		 * @return void
		 */
		public static function set_update_license($slug = '', $license = ''){
			if(!$slug or !$license){
				return;
			}
			$update_licenses = self::_get('update_licenses', []);
			if(isset($update_licenses[$slug])){
				return;
			}
			$update_licenses[$slug] = $license;
			self::_set('update_licenses', $update_licenses);
			add_filter('puc_request_info_query_args-' . $slug, function($queryArgs) use($slug){
				$update_licenses = self::_get('update_licenses', []);
				if(!isset($update_licenses[$slug])){
					return $queryArgs;
				}
				$queryArgs['license'] = $update_licenses[$slug];
				return $queryArgs;
			});
		}

		/**
		 * @return WP_Error|WP_User
		 */
		static public function signon($username_or_email = '', $password = '', $remember = false){
			if(is_user_logged_in()){
				return wp_get_current_user();
			} else {
				add_filter('wordfence_ls_require_captcha', [__CLASS__, '_wordfence_ls_require_captcha']);
				$user = wp_signon([
					'remember' => $remember,
					'user_login' => $username_or_email,
					'user_password' => $password,
				]);
				remove_filter('wordfence_ls_require_captcha', [__CLASS__, '_wordfence_ls_require_captcha']);
				if(is_wp_error($user)){
					return $user;
				}
				return wp_set_current_user($user->ID);
			}
		}

		/**
		 * @return WP_Error|WP_User
		 */
		static public function signon_without_password($username_or_email = '', $remember = false){
			if(is_user_logged_in()){
				return wp_get_current_user();
			} else {
				add_filter('authenticate', [__CLASS__, '_authenticate'], 10, 2);
				add_filter('wordfence_ls_require_captcha', [__CLASS__, '_wordfence_ls_require_captcha']);
				$user = wp_signon([
					'remember' => $remember,
					'user_login' => $username_or_email,
					'user_password' => '',
				]);
				remove_filter('wordfence_ls_require_captcha', [__CLASS__, '_wordfence_ls_require_captcha']);
				remove_filter('authenticate', [__CLASS__, '_authenticate']);
				if(is_wp_error($user)){
					return $user;
				}
				return wp_set_current_user($user->ID);
			}
		}

		/**
		 * @return simple_html_dom
		 */
		public static function str_get_html(...$args){
			$remote_lib = self::use_simple_html_dom();
			if(is_wp_error($remote_lib)){
				return $remote_lib;
			}
			return str_get_html(...$args);
		}

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
				if((($length + $word_length) <= $line_length) or empty($oputput[$index])){
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
				if((($length + $word_length) <= $line_length) or empty($oputput[$index])){
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

		/**
		 * @return bool|WP_Error
		 */
		public static function test_error($error = 0){ // A successful upload will pass this test.
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
			if($error > 0){
				if(empty($upload_error_strings[$error])){
					$error_msg = __('Something went wrong.');
				} else {
					$error_msg = $upload_error_strings[$error];
				}
				return self::error($error_msg);
			}
			return true;
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function test_size($file_size = 0){ // A non-empty file will pass this test.
			if(0 === $file_size){
				if(is_multisite()){
					$error_msg = __('File is empty. Please upload something more substantial.');
				} else {
					$error_msg = sprintf(__('File is empty. Please upload something more substantial. This error could also be caused by uploads being disabled in your %1$s file or by %2$s being defined as smaller than %3$s in %1$s.'), 'php.ini', 'post_max_size', 'upload_max_filesize');
				}
				return self::error($error_msg);
			}
			return true;
		}

		/**
		 * @return string|WP_Error
		 */
		public static function test_type($tmp_name = '', $name = '', $mimes = null){ // A correct MIME type will pass this test.
			$wp_filetype = wp_check_filetype_and_ext($tmp_name, $name, $mimes);
			$ext = empty($wp_filetype['ext']) ? '' : $wp_filetype['ext'];
			$type = empty($wp_filetype['type']) ? '' : $wp_filetype['type'];
			$proper_filename = empty($wp_filetype['proper_filename']) ? '' : $wp_filetype['proper_filename']; // Check to see if wp_check_filetype_and_ext() determined the filename was incorrect.
			if($proper_filename){
				$name = $proper_filename;
			}
			if((!$type or !$ext) and !current_user_can('unfiltered_upload')){
				$error_msg = __('Sorry, you are not allowed to upload this file type.');
				return self::error($error_msg);
			}
			return $name;
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function test_uploaded_file($tmp_name = ''){ // A properly uploaded file will pass this test.
			if(!is_uploaded_file($tmp_name)){
				$error_msg = __('Specified file failed upload test.');
				return self::error($error_msg);
			}
			return true;
		}

		/**
		 * @return void
		 */
		public static function tgmpa($plugins = [], $config = []){
			if(did_action('tgmpa_register')){
				return;
			}
			$remote_lib = self::use_tgm_plugin_activation();
			if(is_wp_error($remote_lib)){
				return;
			}
			$args = [
				'config' => $config,
				'plugins' => $plugins,
			];
			$md5 = self::md5($args);
			$tgmpa = self::_get('tgmpa', []);
			$tgmpa[$md5] = $args;
			self::_set('tgmpa', $tgmpa);
			self::_maybe_add_action('tgmpa_register', [__CLASS__, '_tgmpa_register']);
		}

		/**
		 * @return DateTimeZone
		 */
		public static function timezone($offset_or_tz = ''){
			return new \DateTimeZone(self::timezone_string($offset_or_tz));
		}

		/**
		 * @return string
		 */
		public static function timezone_string($offset_or_tz = ''){
			$offset_or_tz = self::offset_or_tz($offset_or_tz);
			$timezone_string = $offset_or_tz['timezone_string'];
			if($timezone_string){
				return $timezone_string;
			}
			$offset = floatval($offset_or_tz['gmt_offset']);
			$hours = intval($offset);
			$minutes = ($offset - $hours);
			$sign = ($offset < 0) ? '-' : '+';
			$abs_hour = abs($hours);
			$abs_mins = abs($minutes * 60);
			$tz_offset = sprintf('%s%02d:%02d', $sign, $abs_hour, $abs_mins);
			return $tz_offset;
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function use_cloudinary(){
			$class = 'Cloudinary';
			if(class_exists($class)){
				return true;
			}
			$dir = self::remote_lib('https://github.com/cloudinary/cloudinary_php/archive/refs/tags/1.20.1.zip', 'cloudinary_php-1.20.1');
			if(is_wp_error($dir)){
				return $dir;
			}
			$file = $dir . '/autoload.php';
			if(!file_exists($file)){
				return self::error(__('File doesn&#8217;t exist?'), $file);
			}
			require_once($file);
			return class_exists($class);
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function use_jwt(){
			$class = 'Firebase\JWT\JWT';
			if(class_exists($class)){
				return true;
			}
			$dir = self::remote_lib('https://github.com/firebase/php-jwt/archive/refs/tags/v5.5.1.zip', 'php-jwt-5.5.1');
			if(is_wp_error($dir)){
				return $dir;
			}
			$src = $dir . '/src';
			if(!file_exists($src)){
				return self::error(__('File doesn&#8217;t exist?'), $src);
			}
			$files = [
				$src . '/BeforeValidException.php',
				$src . '/ExpiredException.php',
				$src . '/JWK.php',
				$src . '/JWT.php',
				$src . '/Key.php',
				$src . '/SignatureInvalidException.php',
			];
			foreach($files as $file){
				require_once($file);
			}
			return class_exists($class);
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function use_plugin_update_checker(){
			$class = 'Puc_v4_Factory';
			if(class_exists($class)){
				return true;
			}
			$dir = self::remote_lib('https://github.com/YahnisElsts/plugin-update-checker/archive/refs/tags/v4.13.zip', 'plugin-update-checker-4.13');
			if(is_wp_error($dir)){
				return $dir;
			}
			$file = $dir . '/plugin-update-checker.php';
			if(!file_exists($file)){
				return self::error(__('File doesn&#8217;t exist?'), $file);
			}
			require_once($file);
			return class_exists($class);
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function use_tgm_plugin_activation(){
			$class = 'TGM_Plugin_Activation';
			if(class_exists($class)){
				return true;
			}
			$dir = self::remote_lib('https://github.com/TGMPA/TGM-Plugin-Activation/archive/refs/tags/2.6.1.zip', 'TGM-Plugin-Activation-2.6.1');
			if(is_wp_error($dir)){
				return $dir;
			}
			$file = $dir . '/class-tgm-plugin-activation.php';
			if(!file_exists($file)){
				return self::error(__('File doesn&#8217;t exist?'), $file);
			}
			require_once($file);
			return class_exists($class);
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function use_serializable_closures(){
			$class = 'Opis\Closure\SerializableClosure';
			if(class_exists($class)){
				return true;
			}
			$dir = self::remote_lib('https://github.com/opis/closure/archive/refs/tags/3.6.3.zip', 'closure-3.6.3');
			if(is_wp_error($dir)){
				return $dir;
			}
			$file = $dir . '/autoload.php';
			if(!file_exists($file)){
				return self::error(__('File doesn&#8217;t exist?'), $file);
			}
			require_once($file);
			return class_exists($class);
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function use_simple_html_dom(){
			$class = 'simple_html_dom';
			if(class_exists($class)){
				return true;
			}
			$dir = self::remote_lib('https://github.com/simplehtmldom/simplehtmldom/archive/refs/tags/1.9.1.zip', 'simplehtmldom-1.9.1');
			if(is_wp_error($dir)){
				return $dir;
			}
			$file = $dir . '/simple_html_dom.php';
			if(!file_exists($file)){
				return self::error(__('File doesn&#8217;t exist?'), $file);
			}
			require_once($file);
			return class_exists($class);
		}

		/**
		 * @return bool|WP_Error
		 */
		public static function use_xlsxwriter(){
			$class = 'XLSXWriter';
			if(class_exists($class)){
				return true;
			}
			$dir = self::remote_lib('https://github.com/mk-j/PHP_XLSXWriter/archive/refs/tags/0.38.zip', 'PHP_XLSXWriter-0.38');
			if(is_wp_error($dir)){
				return $dir;
			}
			$file = $dir . '/xlsxwriter.class.php';
			if(!file_exists($file)){
				return self::error(__('File doesn&#8217;t exist?'), $file);
			}
			require_once($file);
			return class_exists($class);
		}

		/**
		 * @return array
		 */
		public static function utm_current_params(){
			$params = [];
			foreach(self::utm_keys() as $key){
				if(isset($_COOKIE['utm_' . $key . '_' . COOKIEHASH])){
					$params[$key] = $_COOKIE['utm_' . $key . '_' . COOKIEHASH];
				} elseif(isset($_GET['utm_' . $key])){
					$params[$key] = $_GET['utm_' . $key];
				} else {
					$params[$key] = '';
				}
			}
			return $params;
		}

		/**
		 * @return array
		 */
		public static function utm_keys(){
			$params = self::utm_params();
			return array_keys($params);
		}

		/**
		 * @return array
		 */
		public static function utm_params(){
			$params = [
				'campaign' => 'Campaign',
				'content' => 'Content',
				'id' => 'ID',
				'medium' => 'Medium',
				'source' => 'Source',
				'term' => 'Term',
			];
			return $params;
		}

		/**
		 * @return void
		 */
		public static function utm_set(){
			$at_least_one = false;
			$keys = self::utm_keys();
			foreach($keys as $key){
				if(isset($_GET['utm_' . $key])){
					$at_least_one = true;
					break;
				}
			}
			if(!$at_least_one){
				return;
			}
			self::utm_unset();
			$cookie_lifetime = time() + (14 * DAY_IN_SECONDS);
			$secure = ('https' === parse_url(home_url(), PHP_URL_SCHEME));
			foreach($keys as $key){
				if(!isset($_GET['utm_' . $key])){
					continue;
				}
				$value = wp_unslash($_GET['utm_' . $key]);
				$value = esc_attr($value);
				setcookie('utm_' . $key . '_' . COOKIEHASH, $value, $cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN, $secure);
			}
		}

		/**
		 * @return void
		 */
		public static function utm_track(){
			self::_set('track_utm', true);
			self::_maybe_add_action('after_setup_theme', [__CLASS__, '_after_setup_theme']);
			self::_maybe_add_action('wp_enqueue_scripts', [__CLASS__, '_wp_enqueue_scripts'], 9);
		}

		/**
		 * @return void
		 */
		public static function utm_unset(){
			$past = time() - YEAR_IN_SECONDS;
			foreach(self::utm_keys() as $key){
				if(!isset($_COOKIE['utm_' . $key . '_' . COOKIEHASH])){
					continue;
				}
				setcookie('utm_' . $key . '_' . COOKIEHASH, ' ', $past, COOKIEPATH, COOKIE_DOMAIN);
			}
		}

		/**
		 * @return XLSXWriter|WP_Error
		 */
		public static function xlsx_writer(...$args){
			$remote_lib = self::use_xlsxwriter();
			if(is_wp_error($remote_lib)){
				return $remote_lib;
			}
			return new \XLSXWriter(...$args);
		}

		/**
		 * @return string
		 */
		public static function zoom_api_url($endpoint = ''){
			$base = 'https://api.zoom.us/v2';
			$endpoint = str_replace($base, '', $endpoint);
			$endpoint = ltrim($endpoint, '/');
			$endpoint = untrailingslashit($endpoint);
			$endpoint = trailingslashit($base) . $endpoint;
			return $endpoint;
		}

		/**
		 * @return string|WP_Error
		 */
		public static function zoom_auth($api_key = '', $api_secret = ''){
			$zoom_jwt = self::_get('zoom_jwt', '');
			if($zoom_jwt){
				return $zoom_jwt;
			}
			if($api_key and $api_secret){
				$payload = [
					'exp' => time() + DAY_IN_SECONDS,
					'iss' => $api_key,
				];
				$zoom_jwt = self::jwt_encode($payload, $api_secret);
				self::_set('zoom_jwt', $zoom_jwt);
				return $zoom_jwt;
			}
			$missing = [];
			if(!defined('ZOOM_API_KEY')){
				$missing[] = 'API Key';
			}
			if(!defined('ZOOM_API_SECRET')){
				$missing[] = 'API Secret';
			}
			if($missing){
				return self::error(sprintf(__('Missing parameter(s): %s'), self::implode_and($missing)) . '.');
			}
			$payload = [
				'exp' => time() + DAY_IN_SECONDS,
				'iss' => ZOOM_API_KEY,
			];
			$zoom_jwt = self::jwt_encode($payload, ZOOM_API_SECRET);
			self::_set('zoom_jwt', $zoom_jwt);
			return $zoom_jwt;
		}

		/**
		 * @return array|WP_Error
		 */
		public static function zoom_delete($endpoint = '', $args = [], $timeout = 30){
			return self::zoom_request('DELETE', $endpoint, $args, $timeout);
		}

		/**
		 * @return array|WP_Error
		 */
		public static function zoom_get($endpoint = '', $args = [], $timeout = 30){
			return self::zoom_request('GET', $endpoint, $args, $timeout);
		}

		/**
		 * @return array|WP_Error
		 */
		public static function zoom_patch($endpoint = '', $args = [], $timeout = 30){
			return self::zoom_request('PATCH', $endpoint, $args, $timeout);
		}

		/**
		 * @return array|WP_Error
		 */
		public static function zoom_post($endpoint = '', $args = [], $timeout = 30){
			return self::zoom_request('POST', $endpoint, $args, $timeout);
		}

		/**
		 * @return array|WP_Error
		 */
		public static function zoom_put($endpoint = '', $args = [], $timeout = 30){
			return self::zoom_request('PUT', $endpoint, $args, $timeout);
		}

		/**
		 * @return array|WP_Error
		 */
		public static function zoom_request($method = '', $endpoint = '', $args = [], $timeout = 30){
			$jwt = self::zoom_auth();
			if(is_wp_error($jwt)){
				return $jwt;
			}
			$url = self::zoom_api_url($endpoint);
			if(!is_array($args)){
				$args = wp_parse_args($args);
			}
			$args = [
				'body' => $args,
				'headers' => [
					'Accept' => 'application/json',
					'Authorization' => 'Bearer ' . $jwt,
					'Content-Type' => 'application/json',
				],
				'timeout' => self::sanitize_timeout($timeout),
			];
			return self::remote_request($method, $url, $args);
		}

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	}
}
