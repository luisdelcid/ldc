<?php

if(!class_exists('ldc_github')){
	final class ldc_github {

		// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
        //
        // public
        //
        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

        public function require($owner = '', $repo = '', $release = 'latest'){
            $url = 'https://api.github.com/repos/' . $owner . '/' . $repo . '/releases/' . $release;
            $response = ldc()->remote($url)->get();
            if($response->success){
                $url = 'https://github.com/' . $owner . '/' . $repo . '/archive/refs/tags/' . $response->data['tag_name'] . '.zip';
                $dir = $repo . '-' . $response->data['name'];
                return ldc()->require($url, $dir);
            } else {
                return $response->to_wp_error();
            }
        }

        // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	}
}
