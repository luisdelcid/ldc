<?php

if(!class_exists('LDC_Singleton')){
	class LDC_Singleton {

		// ---------------------------------------------------------------------

        private static $instances = [];

		// ---------------------------------------------------------------------

        /**
		 * Can be overridden in subclasses.
		 *
         * @return self
         */
        public static function get_instance(...$args){
            $class = get_called_class();
			$class_md5 = md5($class);
            if(isset(self::$instances[$class_md5])){
                return self::$instances[$class_md5];
            }
            self::$instances[$class_md5] = new $class();
            return self::$instances[$class_md5];
        }

		// ---------------------------------------------------------------------

        /**
         * MUST be overridden in a subclass.
         *
         * @return void
         */
        protected function __construct(){
			// Silence is golden.
		}

		// ---------------------------------------------------------------------

    	/**
    	 * @return WP_Error
    	 */
    	public function __call($name = '', $arguments = []){
            $message = __('Function %1$s was called <strong>incorrectly</strong>. %2$s %3$s');
            $message = sprintf($message, $name, '', '');
    		$message = trim($message);
    		return ldc()->error($message, $arguments);
    	}

		// ---------------------------------------------------------------------

    }
}
