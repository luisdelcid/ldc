<?php

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    defined('LDC_VERSION') or die('No script kiddies please!');

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    if(!function_exists('ldc_attachment_guid_to_postid')){
		function ldc_attachment_guid_to_postid($url = ''){
			if($url){
				/** original */
				$post_id = ldc_guid_to_postid($url);
				if($post_id){
					return $post_id;
				}
                /** resized */
				preg_match('/^(.+)(-\d+x\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
				if($matches){
					$url = $matches[1];
					if(isset($matches[3])){
						$url .= $matches[3];
					}
                    $post_id = ldc_guid_to_postid($url);
    				if($post_id){
    					return $post_id;
    				}
				}
				/** scaled */
				preg_match('/^(.+)(-scaled)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
				if($matches){
					$url = $matches[1];
					if(isset($matches[3])){
						$url .= $matches[3];
					}
                    $post_id = ldc_guid_to_postid($url);
    				if($post_id){
    					return $post_id;
    				}
				}
				/** edited */
				preg_match('/^(.+)(-e\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches);
				if($matches){
					$url = $matches[1];
					if(isset($matches[3])){
						$url .= $matches[3];
					}
                    $post_id = ldc_guid_to_postid($url);
    				if($post_id){
    					return $post_id;
    				}
				}
			}
			return 0;
		}
	}

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    if(!function_exists('ldc_base64_urldecode')){
		function ldc_base64_urldecode($data = ''){
			return base64_decode(strtr($data, '-_', '+/'));
		}
	}

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    if(!function_exists('ldc_base64_urlencode')){
		function ldc_base64_urlencode($data = ''){
			return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
		}
	}

      // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    if(!function_exists('ldc_guid_to_postid')){
		function ldc_guid_to_postid($guid = ''){
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
	}

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
