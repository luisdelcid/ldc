<?php

if(!class_exists('LDC_Remote') && class_exists('LDC_Multiton')){
	class LDC_Remote extends LDC_Multiton {

		// ---------------------------------------------------------------------
        //
		// Protected
        //
		// ---------------------------------------------------------------------

        protected $args = [], $url = '';

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        protected function __construct($url = '', $args = []){
			$this->url = $url;
            $this->args = $args;
        }

		// ---------------------------------------------------------------------
        //
		// Public static
        //
		// ---------------------------------------------------------------------

        /**
		 * Overrides the parent method to validate the arguments. One instance is
		 * kept per `$url` and `$args` variant.
		 *
		 * NOTE: The signature must stay variadic to remain compatible with
		 * LDC_Multiton::get_instance().
		 *
         * @return self
         */
        public static function get_instance(...$args){
			$url = isset($args[0]) && is_string($args[0]) ? $args[0] : '';
			$args = isset($args[1]) && is_array($args[1]) ? $args[1] : [];
            return parent::get_instance($url, $args);
        }

		// ---------------------------------------------------------------------
        //
		// Public
        //
		// ---------------------------------------------------------------------

        /**
		 * @return stdClass
		 */
        public function delete($body = null){
            return $this->request('DELETE', $body);
        }

		// ---------------------------------------------------------------------

        /**
		 * @return stdClass
		 */
        public function get($body = null){
            return $this->request('GET', $body);
        }

		// ---------------------------------------------------------------------

        /**
		 * @return stdClass
		 */
        public function head($body = null){
            return $this->request('HEAD', $body);
        }

		// ---------------------------------------------------------------------

        /**
		 * @return stdClass
		 */
        public function options($body = null){
            return $this->request('OPTIONS', $body);
        }

		// ---------------------------------------------------------------------

        /**
		 * @return stdClass
		 */
        public function patch($body = null){
            return $this->request('PATCH', $body);
        }

		// ---------------------------------------------------------------------

        /**
		 * @return stdClass
		 */
        public function post($body = null){
            return $this->request('POST', $body);
        }

		// ---------------------------------------------------------------------

        /**
		 * @return stdClass
		 */
        public function put($body = null){
            return $this->request('PUT', $body);
        }

		// ---------------------------------------------------------------------

        /**
         * @see wp-includes/http.php:wp_remote_request()
         *
		 * @return stdClass
		 */
        public function request($method = '', $body = null){
            $args = $this->args;
            if(!is_null($body)){
                $args['body'] = $body; // Overrides the body from the arguments, if any.
            }
            $method = ldc()->sanitize_wp_http_request_method($method);
            if($method !== ''){
                $args['method'] = $method;
            }
            $args = ldc()->sanitize_wp_http_request_array($args, $this->url);
            $time_start = microtime(true);
            $response = wp_remote_request($this->url, $args);
            $time_end = microtime(true);
            $r = ldc()->remote_retrieve_object($response);
            $r->duration = $time_end - $time_start;
            return $r;
        }

		// ---------------------------------------------------------------------

        /**
		 * @return stdClass
		 */
        public function trace($body = null){
            return $this->request('TRACE', $body);
        }

		// ---------------------------------------------------------------------

    }
}
