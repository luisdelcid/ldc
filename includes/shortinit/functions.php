<?php

// -----------------------------------------------------------------------------

/**
 * @return void
 */
function ldc_404(){
    status_header(404);
	die('404 &#8212; File not found.');
}

// -----------------------------------------------------------------------------

/**
 * @return int
 */
function ldc_attachment_file_to_postid($file = ''){
	if(!defined('WP_CONTENT_URL')){
		define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
	}
    $upload_dir = wp_get_upload_dir();
    $url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $file);
    return ldc_attachment_url_to_postid($url);
}

// -----------------------------------------------------------------------------

/**
 * @return int
 */
function ldc_attachment_url_to_postid($url = ''){
    $post_id = ldc_guid_to_postid($url);
    if($post_id){
        return $post_id;
    }
    preg_match('/^(.+)(\-\d+x\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches); // Resized.
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
    preg_match('/^(.+)(\-scaled)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches); // Scaled.
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
    preg_match('/^(.+)(\-e\d+)(\.' . substr($url, strrpos($url, '.') + 1) . ')?$/', $url, $matches); // Edited.
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
    return 0;
}

// -----------------------------------------------------------------------------

/**
 * @return string
 */
function ldc_canonicalize($str = ''){
    $str = str_replace('\\', '_', $str); // Fix namespaces.
    $str = sanitize_title($str);
    $str = str_replace('-', '_', $str); // Fix slugified.
    return trim($str, '_');
}

// -----------------------------------------------------------------------------

/**
 * @return bool
 */
function ldc_current_user_can($capability = ''){
    $user_id = ldc_get_current_user_id();
    return ldc_user_can($user_id, $capability);
}

// -----------------------------------------------------------------------------

/**
 * @return string
 */
function ldc_dirname($path = '', $levels = 1){
    $levels = (int) $levels;
    $path = dirname($path);
    if($levels > 1){
        $levels --;
        return ldc_dirname($path, $levels);
    } else {
        return $path;
    }
}

// -----------------------------------------------------------------------------

/**
 * @return int
 */
function ldc_get_current_user_id(){
    global $wpdb;
    $siteurl = get_option('siteurl');
    if(!$siteurl){
        return 0;
    }
    $cookie_hash = 'wordpress_logged_in_' . md5($siteurl);
    if(!isset($_COOKIE[$cookie_hash])){
        return 0;
    }
    $cookie = $_COOKIE[$cookie_hash];
    $cookie_parts = explode('|', $cookie); // 0 => user_login, 1 => expiration, 2 => token, 3 => hmac
    if(4 !== count($cookie_parts)){ // Check if the cookie has the correct number of parts, if not then we can't be sure that $cookie_parts[0] is the user name.
        return 0;
    }
    $query = $wpdb->prepare("SELECT * FROM $wpdb->users WHERE user_login = %s LIMIT 1", $cookie_parts[0]);
    $user = $wpdb->get_row($query);
    if(!$user){
        return 0;
    }
    return $user->ID;
}

// -----------------------------------------------------------------------------

/**
 * @return object
 */
function ldc_get_post($post_id = 0){
    global $wpdb;
	$query = $wpdb->prepare("SELECT * FROM $wpdb->posts WHERE ID = %d", $post_id);
	return $wpdb->get_row($query);
}

// -----------------------------------------------------------------------------

/**
 * @return array|null|string
 */
function ldc_get_post_meta($post_id = 0, $key = '', $single = false){
    global $wpdb;
    $query = $wpdb->prepare("SELECT meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key = %s", $post_id, $key);
    if($single){
        return $wpdb->get_var($query);
    } else {
        return $wpdb->get_col($query);
    }
}

// -----------------------------------------------------------------------------

/**
 * @return bool|string
 */
function ldc_get_post_status($post_id = 0){
	$post = ldc_get_post($post_id);
	if(!is_object($post)){
		return false;
	}
	$post_status = $post->post_status;
	if('attachment' === $post->post_type && 'inherit' === $post_status){
		if(0 === $post->post_parent || !ldc_get_post($post->post_parent) || $post->ID === $post->post_parent){
			$post_status = 'publish'; // Unattached attachments with inherit status are assumed to be published.
		} elseif('trash' === ldc_get_post_status($post->post_parent)){
			$post_status = ldc_get_post_meta($post->post_parent, '_wp_trash_meta_status', true); // Get parent status prior to trashing.
			if(!$post_status){
				$post_status = 'publish'; // Assume publish as above.
			}
		} else {
			$post_status = ldc_get_post_status($post->post_parent);
		}
	} elseif('attachment' === $post->post_type && !in_array($post_status, ['auto-draft', 'private', 'trash'], true)){
		$post_status = 'publish'; // Ensure uninherited attachments have a permitted status either 'private', 'trash', 'auto-draft'. This is to match the logic in wp_insert_post().
	}
	return $post_status;
}

// -----------------------------------------------------------------------------

/**
 * @return int
 */
function ldc_guid_to_postid($guid = ''){
    global $wpdb;
    $query = $wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid = %s", $guid);
    $post_id = $wpdb->get_var($query);
    if(null === $post_id){
        return 0;
    }
    return (int) $post_id;
}

// -----------------------------------------------------------------------------

/**
 * @return string
 */
function ldc_remove_whitespaces($str = ''){
	return trim(preg_replace('/[\r\n\t ]+/', ' ', $str));
}

// -----------------------------------------------------------------------------

/**
 * @return void
 */
function ldc_serve_file($file = ''){
    $mime = wp_check_filetype($file);
	if(false === $mime['type'] && function_exists('mime_content_type')){
		$mime['type'] = mime_content_type($file);
	}
	if($mime['type']){
		$mimetype = $mime['type'];
	} else {
		$mimetype = 'image/' . substr($file, strrpos($file, '.') + 1);
	}
	header('Content-Type: ' . $mimetype); // Always send this.
	if(false === strpos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS')){
		header('Content-Length: ' . filesize($file));
	}
	readfile($file); // If we made it this far, just serve the file.
	flush();
	die;
}

// -----------------------------------------------------------------------------

/**
 * @return string
 */
function ldc_slugify($str = ''){
    $str = str_replace('\\', '-', $str); // Fix namespaces.
    $str = sanitize_title($str);
    $str = str_replace('_', '-', $str); // Fix canonicalized.
    return trim($str, '-');
}

// -----------------------------------------------------------------------------

/**
 * Polyfill for `str_ends_with()` function added in PHP 8.0 and WordPress 5.9.
 *
 * @return bool
 */
function ldc_str_ends_with($haystack = '', $needle = ''){
    if(function_exists('str_ends_with')){
        return str_ends_with($haystack, $needle);
    }
    if($haystack === ''){
        return $needle === '';
    }
    $len = strlen($needle);
    return substr($haystack, -$len, $len) === $needle;
}

// -----------------------------------------------------------------------------

/**
 * @return string
 */
function ldc_str_prefix($str = '', $prefix = ''){
    $prefix = ldc_canonicalize($prefix);
    $str = sanitize_text_field($str);
    if(!$str){
        return $prefix;
    }
    if(!$prefix){
        return $str;
    }
    if(ldc_str_starts_with($str, $prefix)){
        return $str; // Text is already prefixed.
    }
    return $prefix . '_' . $str;
}

// -----------------------------------------------------------------------------

/**
 * @return string
 */
function ldc_str_slug($str = '', $slug = ''){
    $slug = ldc_slugify($slug);
    $str = sanitize_text_field($str);
    if(!$str){
        return $slug;
    }
    if(!$slug){
        return $str;
    }
    if(ldc_str_starts_with($str, $slug)){
        return $str; // Text is already slugged.
    }
    return $slug . '-' . $str;
}

// -----------------------------------------------------------------------------

/**
 * Polyfill for `str_starts_with()` function added in PHP 8.0 and WordPress 5.9.
 *
 * @return bool
 */
function ldc_str_starts_with($haystack = '', $needle = ''){
    if(function_exists('str_starts_with')){
        return str_starts_with($haystack, $needle);
    }
    if('' === $needle){
        return true;
    }
    return strpos($haystack, $needle) === 0;
}

// -----------------------------------------------------------------------------

/**
 * @return void
 */
function ldc_test(){
    status_header(200);
	die('Hello, World!');
}

// -----------------------------------------------------------------------------

/**
 * @return bool
 */
function ldc_user_can($user_id = 0, $capability = ''){
    global $wpdb;
    $query = $wpdb->prepare("SELECT meta_value FROM $wpdb->usermeta WHERE user_id = %d AND meta_key = %s LIMIT 1", $user_id, $wpdb->prefix . 'capabilities');
    $roles = get_option($wpdb->prefix . 'user_roles');
    $user_capabilities = [];
    $user_roles = $wpdb->get_var($query);
	$user_roles = maybe_unserialize($user_roles);
	$user_roles = (array) $user_roles;
    foreach(array_keys($user_roles) as $user_role){
    	if(isset($roles[$user_role])){
            foreach(array_keys($roles[$user_role]['capabilities']) as $role_capability){
                if(!in_array($role_capability, $user_capabilities)){
                    $user_capabilities[] = $role_capability;
                }
            }
    	} else {
            if(!in_array($user_role, $user_capabilities)){
                $user_capabilities[] = $user_role;
            }
    	}
    }
    return in_array($capability, $user_capabilities);
}

// -----------------------------------------------------------------------------
