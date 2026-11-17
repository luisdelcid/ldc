<?php

if(!class_exists('LDC_Cache') && class_exists('LDC_Multiton')){
	class LDC_Cache extends LDC_Multiton {

		// ---------------------------------------------------------------------
        //
		// Private
        //
		// ---------------------------------------------------------------------

        private static $store = [];

		// ---------------------------------------------------------------------

        /**
         * Reads or writes this group's slice of the store. Kept private so the
         * store can only ever be reached through a sanitized group.
         *
		 * @return array|bool
		 */
        private function data($data = null){
            if(is_null($data)){
                return isset(self::$store[$this->group]) ? self::$store[$this->group] : [];
            }
            if(!is_array($data)){
                return false; // Invalid data provided.
            }
            self::$store[$this->group] = $data;
            return true;
        }

		// ---------------------------------------------------------------------
        //
		// Protected
        //
		// ---------------------------------------------------------------------

        protected $group = '';

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        protected function __construct($group = ''){
            $this->group = $group;
        }

		// ---------------------------------------------------------------------
        //
		// Public static
        //
		// ---------------------------------------------------------------------

        /**
		 * Overrides the parent method to sanitize the argument. This is the only
		 * place the group is normalized, so `$this->group` is always the real
		 * storage group. One instance is kept per group — and, on multisite,
		 * per site.
		 *
		 * NOTE: The signature must stay variadic to remain compatible with
		 * LDC_Multiton::get_instance().
		 *
         * @return self
         */
        public static function get_instance(...$args){
            $group = isset($args[0]) ? $args[0] : '';
			$group = ldc()->cache_sanitize_group($group);
            return parent::get_instance($group);
        }

		// ---------------------------------------------------------------------
        //
		// Public
        //
		// ---------------------------------------------------------------------

        /**
         * Adds data to the cache if it doesn't already exist.
         *
		 * @return bool
		 */
        public function add($key = '', $value = null){
            if($this->exists($key)){
                return false;
            }
            return $this->set($key, $value);
        }

		// ---------------------------------------------------------------------

        /**
         * Removes the contents of the cache key in the group.
         *
		 * @return bool
		 */
        public function delete($key = ''){
            $sanitized = ldc()->cache_sanitize_key($key);
            if(!ldc()->cache_is_valid_key($sanitized)){
                return false;
            }
            $data = $this->data(); // Get.
            if(!isset($data[$sanitized])){
                return false;
            }
            unset($data[$sanitized]);
            return $this->data($data); // Set.
        }

		// ---------------------------------------------------------------------

        /**
         * Serves as a utility function to determine whether a key exists in the cache.
         *
		 * @return bool
		 */
        public function exists($key = ''){
            $sanitized = ldc()->cache_sanitize_key($key);
            if(!ldc()->cache_is_valid_key($sanitized)){
                return false;
            }
            $data = $this->data();
            return isset($data[$sanitized]);
        }

		// ---------------------------------------------------------------------

        /**
         * Removes all cache items in the group.
         *
		 * @return bool
		 */
        public function flush(){
            $data = $this->data();
            if(!$data){
                return false;
            }
            return $this->data([]);
        }

		// ---------------------------------------------------------------------

        /**
         * Retrieves the cache contents, if it exists.
         *
		 * @return mixed|null
		 */
        public function get($key = ''){
            $default = null;
            $sanitized = ldc()->cache_sanitize_key($key);
            if(!ldc()->cache_is_valid_key($sanitized)){
                return $default;
            }
            $data = $this->data();
            if(!isset($data[$sanitized])){
                return $default;
            }
            return $data[$sanitized];
        }

		// ---------------------------------------------------------------------

        /**
         * Retrieves the cache contents as an array.
         *
		 * @return array
		 */
        public function get_array($key = ''){
            $default = [];
            $value = $this->get($key);
            if(is_null($value)){
                return $default;
            }
            if(is_array($value)){
                return $value;
            }
            if(is_object($value)){
    			return get_object_vars($value);
    		}
            return $default;
        }

		// ---------------------------------------------------------------------

        /**
         * Retrieves the cache contents as a boolean.
         *
		 * @return bool
		 */
        public function get_bool($key = ''){
            $default = false;
            $value = $this->get($key);
            if(is_null($value)){
                return $default;
            }
            if(is_bool($value)){
                return $value;
            }
            return (bool) $value;
        }

		// ---------------------------------------------------------------------

        /**
         * Retrieves all cache items in the group.
         *
		 * @return array
		 */
        public function get_group(){
            return $this->data();
        }

		// ---------------------------------------------------------------------

        /**
         * Retrieves the cache contents as a integer.
         *
		 * @return int
		 */
        public function get_int($key = ''){
            $default = 0;
            $value = $this->get($key);
            if(is_null($value)){
                return $default;
            }
            if(is_int($value)){
                return $value;
            }
            if(is_numeric($value)){
                return (int) $value;
            }
            return $default;
        }

		// ---------------------------------------------------------------------

        /**
         * Retrieves the cache contents as a object.
         *
		 * @return object
		 */
        public function get_object($key = ''){
            $default = new stdClass();
            $value = $this->get($key);
            if(is_null($value)){
                return $default;
            }
            if(is_object($value)){
                return $value;
            }
            if(is_array($value)){
                return (object) $value;
            }
            return $default;
        }

		// ---------------------------------------------------------------------

        /**
         * Retrieves the cache contents as a string.
         *
		 * @return string
		 */
        public function get_string($key = ''){
            $default = '';
            $value = $this->get($key);
            if(is_null($value)){
                return $default;
            }
            if(is_string($value)){
                return $value;
            }
            if(is_scalar($value)){
                return (string) $value;
            }
            return $default;
        }

		// ---------------------------------------------------------------------

        /**
         * Replaces the contents in the cache, if contents already exist.
         *
		 * @return bool
		 */
        public function replace($key = '', $value = null){
            if(!$this->exists($key)){
                return false;
            }
            return $this->set($key, $value);
        }

		// ---------------------------------------------------------------------

        /**
         * Sets the data contents into the cache.
         *
		 * @return bool
		 */
        public function set($key = '', $value = null){
            $sanitized = ldc()->cache_sanitize_key($key);
            if(!ldc()->cache_is_valid_key($sanitized)){
                return false;
            }
            if(is_null($value)){
                return false;
            }
            if(is_object($value)){
        		$value = clone $value;
        	}
            $data = $this->data();
            if(isset($data[$sanitized]) && $data[$sanitized] === $value){
                return false;
            }
            $data[$sanitized] = $value;
            return $this->data($data);
        }

		// ---------------------------------------------------------------------

    }
}
