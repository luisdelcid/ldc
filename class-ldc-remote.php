<?php

if(!class_exists('ldc_remote')){
    final class ldc_remote {

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // private
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private $args = [], $url = '';

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        private function request($method = '', $body = []){
            $this->args['method'] = $method;
            if($body){
                if(array_key_exists('body', $this->args) and is_array($this->args['body']) and is_array($body)){
                    $this->args['body'] = array_merge($this->args['body'], $body);
                } else {
                    $this->args['body'] = $body;
                }
            }
            $response = wp_remote_request($this->url, $this->args);
            return ldc()->response($response);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // public
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function __construct($url = '', $args = []){
            $this->args = $args;
            $this->url = $url;
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function delete($body = []){
            return $this->request('DELETE', $body);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function get($body = []){
            return $this->request('GET', $body);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function head($body = []){
            return $this->request('HEAD', $body);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function options($body = []){
            return $this->request('OPTIONS', $body);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function patch($body = []){
            return $this->request('PATCH', $body);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function post($body = []){
            return $this->request('POST', $body);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function put($body = []){
            return $this->request('PUT', $body);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function trace($body = []){
            return $this->request('TRACE', $body);
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    }
}
