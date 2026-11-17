<?php

if(!class_exists('LDC_Async') && class_exists('LDC_Remote')){
	class LDC_Async extends LDC_Remote {

		// ---------------------------------------------------------------------
        //
		// Public
        //
		// ---------------------------------------------------------------------

        /**
         * Sends the request without waiting for the response, the way
         * spawn_cron() calls wp-cron.php, so there is no response to read: true
         * once WordPress has sent it, a WP_Error when it would not (a URL it
         * refuses, a host it blocks). Every verb goes through here. E.g.
         * ldc()->async($url)->post($body);
         *
         * The transport is the one WordPress picks, cURL where there is one. cURL
         * holds on for the response, up to a second, and that is what makes the
         * request arrive: nginx cancels a request whose caller hangs up before it
         * answers (a 499), so one sent through fsockopen, which hangs up the
         * moment it has written, would never reach PHP. Nor does WordPress say
         * when a connection fails: it never looks at how a request that does not
         * block went.
         *
         * So whatever answers should keep going once the caller hangs up, and
         * answer right away, as wp-cron.php does, which spares the caller that
         * second: ignore_user_abort(true), then fastcgi_finish_request().
         *
         * @see wp-includes/cron.php:spawn_cron()
         *
		 * @return true|WP_Error
		 */
        public function request($method = '', $body = null){
            add_filter('http_request_args', [$this, '_http_request_args'], PHP_INT_MAX, 2);
            $r = parent::request($method, $body);
            remove_filter('http_request_args', [$this, '_http_request_args'], PHP_INT_MAX);
            if(is_wp_error($r->raw)){
                return $r->raw;
            }
            return true;
        }

		// ---------------------------------------------------------------------
        //
		// WARNING: These methods’ access is marked private. This means they are not intended for use by plugin or theme developers, only in other LDC_Async methods.
        //
		// ---------------------------------------------------------------------

        /**
         * WARNING: This method’s access is marked private.
         *
         * This method MUST be called inside the 'http_request_args' filter hook.
         *
         * @return array
         */
        public function _http_request_args($args = [], $url = ''){
            if(!doing_filter('http_request_args')){
                return $args; // Too early or too late.
            }
            if($url !== $this->url){
                return $args; // Some other request, made while this one is sent.
            }
            $args['blocking'] = false;
            $args['stream'] = false; // WordPress makes a request that streams to a file wait for it.
            $args['timeout'] = 0.01; // As spawn_cron() does.
            return $args;
        }

		// ---------------------------------------------------------------------

    }
}
