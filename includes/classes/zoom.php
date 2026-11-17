<?php

if(!class_exists('LDC_Zoom') && class_exists('LDC_Remote')){
	class LDC_Zoom extends LDC_Remote {

		// ---------------------------------------------------------------------
        //
		// Public static
        //
		// ---------------------------------------------------------------------

        /**
		 * Overrides the parent method to turn the endpoint into its URL. The
		 * endpoint is relative to the Zoom API, so 'users/me', '/users/me' and
		 * 'https://api.zoom.us/v2/users/me' are the same request, and the same
		 * instance.
		 *
		 * NOTE: The signature must stay variadic to remain compatible with
		 * LDC_Multiton::get_instance().
		 *
         * @return self
         */
        public static function get_instance(...$args){
			$endpoint = isset($args[0]) && is_string($args[0]) ? trim($args[0]) : '';
			$args = isset($args[1]) && is_array($args[1]) ? $args[1] : [];
            $base = 'https://api.zoom.us/v2'; // Hardcoded.
            if(str_starts_with($endpoint, $base)){
                $endpoint = substr($endpoint, strlen($base));
            }
            $endpoint = ltrim($endpoint, '/');
            return parent::get_instance($endpoint === '' ? $base : $base . '/' . $endpoint, $args);
        }

		// ---------------------------------------------------------------------
        //
		// Public
        //
		// ---------------------------------------------------------------------

        /**
         * Adds what every call to the Zoom API needs, then makes it the way
         * LDC_Remote does: the access token, JSON both ways, and 15 seconds to
         * answer, unless the arguments say otherwise. Every verb goes through
         * here. E.g. ldc()->zoom('users/me')->get();
         *
         * The arguments may also be just the data to send, which becomes the
         * body, and a body given to the verb replaces it:
         * ldc()->zoom('users/me/meetings', $args)->post($body);
         *
         * Only DELETE, GET, PATCH, POST and PUT are sent: they are the methods
         * the Zoom API answers. Any other one — head(), options(), trace(), or
         * the method of the arguments — comes back as a 405 without being sent.
         *
         * A GET has no body: its data goes in the query string, so it is kept an
         * array, without a Content-Type. JSON-encoded, WordPress could not put
         * it there: http_build_query() of a string is a TypeError.
         *
         * Without an access token nothing is sent either, and the response is
         * the error that kept it from being had.
         *
         * @see LDC::zoom_app_credentials()
         *
		 * @return stdClass
		 */
        public function request($method = '', $body = null){
            $method = is_string($method) ? strtoupper(trim($method)) : '';
            if($method === '' && ldc()->is_wp_http_request_array($this->args) && isset($this->args['method'])){
                $method = strtoupper(trim($this->args['method'])); // Read before it is sanitized, which drops a method it does not know.
            }
            if($method === ''){
                $method = 'GET';
            }
            if(!in_array($method, ['DELETE', 'GET', 'PATCH', 'POST', 'PUT'], true)){ // Hardcoded. The methods the Zoom API answers.
                $error_msg = __('Invalid parameter(s): %s');
                $error_msg = sprintf($error_msg, $method) . '.';
                return $this->_unsent(ldc()->error($error_msg, [
                    'status' => 405, // WP_Http::METHOD_NOT_ALLOWED
                ]));
            }
            $args = ldc()->sanitize_wp_http_request_array($this->args, $this->url); // The data alone becomes the body.
            if(!is_null($body)){
                $args['body'] = $body; // Overrides the body from the arguments, if any.
            }
            $args['method'] = $method;
            if(!isset($args['headers'])){
                $args['headers'] = [];
            }
            $args['headers']['Accept'] = 'application/json';
            if(!isset($args['headers']['Authorization'])){
                $access_token = ldc()->zoom_get_access_token();
                if(is_wp_error($access_token)){
                    return $this->_unsent($access_token);
                }
                $args['headers']['Authorization'] = 'Bearer ' . $access_token;
            }
            if($method === 'GET'){
                unset($args['headers']['Content-Type']);
            } else {
                $args['headers']['Content-Type'] = 'application/json';
            }
            if(!isset($args['timeout'])){
                $args['timeout'] = 15;
            }
            return ldc()->remote($this->url, $args)->request();
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC_Zoom methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * The response to a request that was never sent: the error that kept it
         * from being sent, shaped the way LDC_Remote::request() shapes one.
         *
		 * @return stdClass
		 */
        public function _unsent($error = null){
            $r = ldc()->remote_retrieve_object($error);
            $r->duration = 0;
            return $r;
        }

		// ---------------------------------------------------------------------

    }
}
