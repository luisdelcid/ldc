<?php

if(!class_exists('LDC_Plugin') && class_exists('LDC_Multiton')){
	class LDC_Plugin extends LDC_Multiton {

		// ---------------------------------------------------------------------
        //
		// Protected
        //
		// ---------------------------------------------------------------------

        protected $basename = null, $file = '', $id = null;

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        protected function __construct($file = ''){
            $this->file = $file;
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the file that asked for the instance.
		 *
		 * Walks out of this class' own frames and out of the ldc()->plugin() wrapper,
		 * so the detected file is the same one ldc()->caller_file(1) returns to a
		 * method called from there, no matter which of the two entry points was
		 * used. The wrapper is a method of LDC, or of a class extending it.
		 *
		 * @return string
		 */
        protected static function caller_file(){
            $options = 2; // options: 0: provide args and ignore object, 1: provide args and object, 2: ignore args and object, 3: ignore args and provide object.
            $file = '';
            foreach(debug_backtrace($options) as $frame){
                $class = isset($frame['class']) ? $frame['class'] : '';
                $function = isset($frame['function']) ? $frame['function'] : '';
                if($class !== __CLASS__ && !(is_a($class, 'LDC', true) && $function === 'plugin')){ // Hardcoded.
                    break; // Left this class and the wrapper.
                }
                if(isset($frame['file'])){
                    $file = $frame['file'];
                }
            }
            return $file;
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the plugin's main file, caching the outcome per given value.
		 *
		 * @return string
		 */
        protected static function main_file($value = ''){
            if(!is_string($value) || $value === ''){
                return '';
            }
            $group = 'plugin_file';
            $key = md5($value);
            if(ldc()->cache($group)->exists($key)){
                return ldc()->cache($group)->get_string($key);
            }
            $plugin_file = self::resolve($value);
            ldc()->cache($group)->set($key, $plugin_file);
            return $plugin_file;
        }

		// ---------------------------------------------------------------------

        /**
		 * Resolves either a path to any file inside a plugin, or a plugin
		 * basename such as 'akismet/akismet.php', into the plugin's main file.
		 *
		 * A path only identifies the plugin's directory, so the main file is
		 * looked up among the active plugins first, and among the installed ones
		 * after that. A basename already names the main file. Either way, any
		 * installed plugin resolves, whether it is active or not — use
		 * ldc()->is_plugin_active() to ask about that.
		 *
		 * @return string
		 */
        protected static function resolve($value = ''){
            $plugin_basename = ldc()->plugin_basename_raw($value);
            if($plugin_basename === ''){
                return ldc()->plugin_basename_to_file($value); // Not a path inside the plugins directory, it may be a basename.
            }
            $dir = wp_normalize_path(WP_PLUGIN_DIR);
            if(strpos($plugin_basename, '/') === false){
                return $dir . '/' . $plugin_basename; // The entire plugin consists of just a single PHP file, like Hello Dolly.
            }
            $active_plugins = (array) get_option('active_plugins', []);
            $parts = explode('/', $plugin_basename);
            $plugin_dir = trailingslashit($parts[0]); // The plugin directory name (with trailing slash).
            $plugin_file = '';
            foreach($active_plugins as $active_plugin){
                if(!ldc()->str_starts_with($active_plugin, $plugin_dir)){
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
                if(!ldc()->str_starts_with($active_sitewide_plugin, $plugin_dir)){
                    continue;
                }
                $plugin_file = $dir . '/' . $active_sitewide_plugin;
            }
            if($plugin_file){
                return $plugin_file; // Plugin is active for the entire network.
            }
            $plugin_basename = ldc()->plugin_slug_to_basename($parts[0]);
            if(is_wp_error($plugin_basename)){
                return ''; // Plugin is not installed.
            }
            return $dir . '/' . $plugin_basename; // Plugin is installed, but inactive.
        }

		// ---------------------------------------------------------------------
        //
		// Public static
        //
		// ---------------------------------------------------------------------

        /**
		 * Overrides the parent method to resolve the argument. The given value —
		 * a path to any file inside a plugin, or a plugin basename such as
		 * 'akismet/akismet.php', or, when none is given, the file this was
		 * called from — is resolved to the plugin's main file, and one instance
		 * is kept per main file.
		 *
		 * NOTE: The signature must stay variadic to remain compatible with
		 * LDC_Multiton::get_instance().
		 *
         * @return self
         */
        public static function get_instance(...$args){
            $file = isset($args[0]) && is_string($args[0]) ? $args[0] : '';
            if($file === ''){
                $file = self::caller_file();
            }
            $file = self::main_file($file);
            return parent::get_instance($file);
        }

		// ---------------------------------------------------------------------
        //
		// Public
        //
		// ---------------------------------------------------------------------

        /**
         * @see plugin_basename()
	     *
	     * Differs from plugin_basename() in that it will return the basename of the plugin's main file.
	     *
	     * @return string
	     */
        public function basename(){
            if(is_null($this->basename)){
                $this->basename = $this->file === '' ? '' : plugin_basename($this->file);
            }
            return $this->basename;
        }

		// ---------------------------------------------------------------------

        /**
         * @see get_plugin_data()
	     *
         * @return array|WP_Error
         */
        public function data($markup = true, $translate = true){
            if($this->file === ''){
                $error_msg = __('Invalid plugin path.');
                return ldc()->error($error_msg);
            }
            $data = [
                'file' => $this->file,
                'markup' => $markup,
                'translate' => $translate,
            ];
            $group = 'plugin_data';
            $key = ldc()->md5($data);
            if(ldc()->cache($group)->exists($key)){
                return ldc()->cache($group)->get($key);
            }
            if(!function_exists('get_plugin_data')){
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $data = get_plugin_data($this->file, $markup, $translate);
            ldc()->cache($group)->set($key, $data);
            return $data;
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the plugin's main file.
		 *
         * @return string
         */
        public function file(){
            return $this->file;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function id(){
            if(is_null($this->id)){
                $this->id = '';
                $plugin_basename = $this->basename();
                if($plugin_basename !== '' && strpos($plugin_basename, '/') !== false){
                    $parts = explode('/', $plugin_basename);
                    $this->id = $parts[0];
                }
            }
            return $this->id;
        }

		// ---------------------------------------------------------------------

        /**
         * @return bool
         */
        public function is_deactivating(){
            global $pagenow;
            $plugin_basename = $this->basename();
            if($plugin_basename === ''){
                return false; // File is not a plugin.
            }
            return is_admin() && $pagenow === 'plugins.php' && isset($_GET['action'], $_GET['plugin']) && $_GET['action'] === 'deactivate' && $_GET['plugin'] === $plugin_basename;
        }

		// ---------------------------------------------------------------------

        /**
         * @return string|WP_Error
         */
        public function meta($key = '', $markup = false, $translate = false){
            $data = $this->data($markup, $translate);
            if(is_wp_error($data)){
                return $data;
            }
            if(isset($data[$key])){
                $arr = $data;
            } elseif(isset($data['sections'], $data['sections'][$key])){
                $arr = $data['sections'];
            } else {
                $error_msg = '"' . $key . '" ' . __('(not found)');
                return ldc()->error($error_msg);
            }
            return $arr[$key];
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function prefix($str = ''){
            return ldc()->str_prefix($str, $this->id());
        }

		// ---------------------------------------------------------------------

        /**
         * @return string
         */
        public function slug($str = ''){
            return ldc()->str_slug($str, $this->id());
        }

		// ---------------------------------------------------------------------

    }
}
