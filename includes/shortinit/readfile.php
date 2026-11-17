<?php

require_once(dirname(__FILE__) . '/functions.php');
if(!isset($_GET['file'], $_GET['levels'], $_GET['md5'])){
	ldc_404();
}
$abspath = ldc_dirname(__FILE__, $_GET['levels']);
$loader = $abspath . '/wp-load.php';
if(!file_exists($loader)){
    ldc_404();
}
define('SHORTINIT', true);
require_once($loader);
error_reporting(0);
nocache_headers();
$basedir = ABSPATH . 'wp-content/uploads';
$subdir = (isset($_GET['yyyy'], $_GET['mm']) ? ('/' . $_GET['yyyy'] . '/' . $_GET['mm']) : (isset($_GET['subdir']) ? '/' . $_GET['subdir'] : ''));
$file = $basedir . $subdir . '/' . $_GET['file'];
if(!is_file($file)){
	ldc_404();
}
$post_id = ldc_attachment_file_to_postid($file);
if(!$post_id){
    ldc_serve_file($file);
}
$option_name = 'ldc_hiding_rule_' . $_GET['md5']; // Hardcoded. Must be equal to `$option_name` in ldc()->add_hiding_rule().
$value = (array) get_option($option_name, []);
$exclude_other_media = (isset($value['exclude_other_media']) ? (array) $value['exclude_other_media'] : []);
if($exclude_other_media && in_array($post_id, $exclude_other_media)){
	ldc_serve_file($file);
}
$post = ldc_get_post($post_id);
$post_status = ldc_get_post_status($post_id);
$exclude_public_media = (isset($value['exclude_public_media']) ? (bool) $value['exclude_public_media'] : false);
if($exclude_public_media && 'publish' === $post_status){
	ldc_serve_file($file);
}
$user_id = ldc_get_current_user_id();
if(!$user_id){
    ldc_404();
}
$capability = (isset($value['capability']) ? (string) $value['capability'] : 'edit_others_posts');
if(ldc_current_user_can($capability)){
    ldc_serve_file($file);
}
if($user_id === $post->post_author){
    ldc_serve_file($file);
}
ldc_404();
