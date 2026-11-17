<?php

if(!class_exists('LDC_CL_Image') && class_exists('LDC_Multiton')){
	class LDC_CL_Image extends LDC_Multiton {

		// ---------------------------------------------------------------------
        //
		// Private
        //
		// ---------------------------------------------------------------------

        private $response = null;

		// ---------------------------------------------------------------------

        /**
		 * Picks the meta key for a context, with or without a transformation.
		 *
		 * Without options the key carries no options hash, which is what makes
		 * the original asset addressable — ldc()->cl_meta_key_with_options()
		 * rejects an empty options array on purpose.
		 *
		 * @return string|WP_Error
		 */
        private function meta_key($context = '', $options = []){
            if(!$options){
                return ldc()->cl_meta_key($context); // The original, no transformation.
            }
            return ldc()->cl_meta_key_with_options($context, $options);
        }

		// ---------------------------------------------------------------------

        /**
		 * Resolves the transformation every public method takes.
		 *
		 * An array is used as is. A string is the slug of a transformation
		 * registered with ldc()->cl_register_image_transformation(), so its
		 * options are read from the one place they are defined. Nothing
		 * at all — an empty array or an empty string — is the original, the
		 * same as "None" in the Beaver Builder photo module.
		 *
         * @return array|WP_Error
         */
        private function options($options = []){
            if(!$options){
                return []; // The original, no transformation.
            }
            if(is_array($options)){
                return $options;
            }
            if(!is_string($options)){
                return ldc()->invalid_params(['options']);
            }
            $transformation = ldc()->_cl_image_transformation($options);
            if(!$transformation){
                return ldc()->invalid_params([$options]); // Never registered.
            }
            return $transformation['options'];
        }

		// ---------------------------------------------------------------------

        /**
		 * Uploads the image to Cloudinary once and keeps the response, so every
		 * later transformation travels as a public ID instead of a whole file.
		 *
         * @return array|WP_Error
         */
        private function upload(){
            if(!is_null($this->response)){
                return $this->response; // Already resolved during this request.
            }
            $meta_key = ldc()->cl_meta_key('response');
            if(is_wp_error($meta_key)){
                return $meta_key;
            }
            $meta_value = $this->meta_value($meta_key);
            if($meta_value){
                if(is_wp_error($meta_value)){
                    return $meta_value;
                }
                $this->response = (array) $meta_value;
                return $this->response; // Already uploaded.
            }
            $max_file_size = 10 * MB_IN_BYTES; // TODO: Check for paid plans. https://cloudinary.com/pricing/compare-plans
            $file = $this->file_candidate($max_file_size);
            if(is_wp_error($file)){
                return $file;
            }
            try {
                $upload_api = new LDC\Deps\Cloudinary\Api\Upload\UploadApi();
                $response = $upload_api->upload($file);
            } catch(\Throwable $t){
                $response = ldc()->error($t->getMessage());
            } catch(\Exception $e){
                $response = ldc()->error($e->getMessage());
            }
            if(is_wp_error($response)){
                return $response;
            }
            if(ldc()->cl_is_error($response)){
                return ldc()->error($response->getMessage());
            }
            $response = $response->getArrayCopy(); // The response is an ArrayObject. Store a plain array so the meta never carries a prefixed class name.
            update_post_meta($this->id, $meta_key, $response);
            $this->response = $response;
            return $response;
        }

		// ---------------------------------------------------------------------
        //
		// Protected
        //
		// ---------------------------------------------------------------------

        protected $id = 0;

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        protected function __construct($id = 0){
            $this->id = $id;
        }

		// ---------------------------------------------------------------------
        //
		// Public static
        //
		// ---------------------------------------------------------------------

        /**
		 * Overrides the parent method to sanitize the argument. One instance is
		 * kept per attachment.
		 *
		 * NOTE: The signature must stay variadic to remain compatible with
		 * LDC_Multiton::get_instance().
		 *
         * @return self
         */
        public static function get_instance(...$args){
            $id = isset($args[0]) ? ldc()->positive_int($args[0]) : 0;
            return parent::get_instance($id);
        }

		// ---------------------------------------------------------------------
        //
		// Public
        //
		// ---------------------------------------------------------------------

        /**
		 * Gets the image's public ID on Cloudinary.
		 *
         * @return string|WP_Error
         */
        public function cdn_id(){
            $response = $this->upload();
            if(is_wp_error($response)){
                return $response;
            }
            return (isset($response['public_id']) ? $response['public_id'] : '');
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the Cloudinary URL, its dimensions and whether it is a
		 * transformation, shaped like wp_get_attachment_image_src().
		 *
		 * @see wp_get_attachment_image_src()
		 *
         * @return array|WP_Error
         */
        public function cdn_src($options = []){
            $options = $this->options($options);
            if(is_wp_error($options)){
                return $options;
            }
            $url = $this->cdn_url($options);
            if(is_wp_error($url)){
                return $url;
            }
            if(!$options){
                $response = $this->upload(); // The upload response already carries the original's dimensions.
                if(is_wp_error($response)){
                    return $response;
                }
                $width = isset($response['width']) ? ldc()->positive_int($response['width']) : 0;
                $height = isset($response['height']) ? ldc()->positive_int($response['height']) : 0;
                return [$url, $width, $height, false];
            }
            $info = $this->file_info($options); // The transformation must be measured, so the file is fetched once.
            if(is_wp_error($info)){
                return $info;
            }
            return [$url, $info['width'], $info['height'], true];
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the Cloudinary URL of the original image, or of a transformation
		 * when options are given.
		 *
         * @return string|WP_Error
         */
        public function cdn_url($options = []){
            $options = $this->options($options);
            if(is_wp_error($options)){
                return $options;
            }
            $meta_key = $this->meta_key('url', $options);
            if(is_wp_error($meta_key)){
                return $meta_key;
            }
            $meta_value = $this->meta_value($meta_key);
            if($meta_value){
                if(is_wp_error($meta_value)){
                    return $meta_value;
                }
                return $meta_value; // Already transformed.
            }
            $public_id = $this->cdn_id();
            if(is_wp_error($public_id)){
                return $public_id;
            }
            try {
                $response = (string) LDC\Deps\Cloudinary\Asset\Image::fromParams($public_id, $options); // Takes the same array the v1 cloudinary_url() took.
            } catch(\Throwable $t){
                $response = ldc()->error($t->getMessage());
            } catch(\Exception $e){
                $response = ldc()->error($e->getMessage());
            }
            if(is_wp_error($response)){
                return $response;
            }
            if(ldc()->cl_is_error($response)){
                return ldc()->error($response->getMessage());
            }
            update_post_meta($this->id, $meta_key, $response);
            return $response;
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the local path of the downloaded image.
		 *
         * @return string|WP_Error
         */
        public function file($options = []){
            $info = $this->file_info($options);
            if(is_wp_error($info)){
                return $info;
            }
            return $info['file'];
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the local file to upload, the largest one that fits the size limit.
		 *
         * @return string|WP_Error
         */
        public function file_candidate($max_file_size = 0){
            if(!wp_attachment_is_image($this->id)){
                $error_msg = __('This file is not an image. Please try another.');
                $error_msg = ldc()->first_p($error_msg);
                return ldc()->error($error_msg);
            }
            $image_file = get_attached_file($this->id);
            if(!file_exists($image_file)){
                $error_msg = __('The attached file cannot be found.');
                return ldc()->error($error_msg);
            }
            $type = wp_check_filetype($image_file);
            if(!$type['ext'] || !$type['type']){
                $error_msg = __('The uploaded file is not a valid image. Please try again.');
                $error_msg = ldc()->first_p($error_msg);
                return ldc()->error($error_msg);
            }
            $image_meta = wp_get_attachment_metadata($this->id);
            $max_file_size_in_kb = $max_file_size / KB_IN_BYTES;
            if(!$image_meta){
                $filesize = wp_filesize($image_file);
                if($filesize <= $max_file_size){
                    return $image_file;
                }
                $error_msg = __('This file is too big. Files must be less than %s KB in size.');
                $error_msg = sprintf($error_msg, $max_file_size_in_kb);
                return ldc()->error($error_msg);
            }
            if(isset($image_meta['original_image'])){
                $original_image = path_join(dirname($image_file), $image_meta['original_image']); // Alias for wp_get_original_image_path().
                if(!file_exists($original_image)){
                    $error_msg = __('The attached file cannot be found.');
                    return ldc()->error($error_msg);
                }
                $filesize = wp_filesize($original_image);
                if($filesize <= $max_file_size){
                    return $original_image;
                }
            }
            if(isset($image_meta['filesize'])){
                if($image_meta['filesize'] <= $max_file_size){
                    return $image_file;
                }
            } else {
                $filesize = wp_filesize($image_file);
                if($filesize <= $max_file_size){
                    return $image_file;
                }
            }
            $sizes = wp_list_pluck($image_meta['sizes'], 'filesize', 'file');
            arsort($sizes);
            $basedir = dirname($image_file);
            $path = '';
            foreach($sizes as $file => $filesize){
                if($filesize > $max_file_size){
                    continue;
                }
                $path = path_join($basedir, $file);
                if(!file_exists($path)){
                    continue;
                }
                break;
            }
            if(!$path){
                $error_msg = __('This file is too big. Files must be less than %s KB in size.');
                $error_msg = sprintf($error_msg, $max_file_size_in_kb);
                return ldc()->error($error_msg);
            }
            return $path;
        }

		// ---------------------------------------------------------------------

        /**
		 * Downloads the image from Cloudinary once and keeps it locally, along
		 * with its dimensions, so the API is not hit again for the same options.
		 *
         * @return array|WP_Error
         */
        public function file_info($options = []){
            $options = $this->options($options);
            if(is_wp_error($options)){
                return $options;
            }
            $meta_key_f = $this->meta_key('file', $options);
            $meta_key_h = $this->meta_key('height', $options);
            $meta_key_w = $this->meta_key('width', $options);
            if(is_wp_error($meta_key_f)){
                return $meta_key_f;
            }
            $meta_value_f = $this->meta_value($meta_key_f);
            $meta_value_h = $this->meta_value($meta_key_h);
            $meta_value_w = $this->meta_value($meta_key_w);
            if($meta_value_f){
                if(is_wp_error($meta_value_f)){
                    return $meta_value_f;
                }
                if(file_exists($meta_value_f)){
                    return [
                        'file' => $meta_value_f,
                        'filename' => wp_basename($meta_value_f),
                        'height' => $meta_value_h,
                        'url' => ldc()->path_to_url($meta_value_f),
                        'width' => $meta_value_w,
                    ]; // Already downloaded.
                }
                delete_post_meta($this->id, $meta_key_f);
                delete_post_meta($this->id, $meta_key_h);
                delete_post_meta($this->id, $meta_key_w);
            }
            $url = $this->cdn_url($options);
            if(is_wp_error($url)){
                return $url;
            }
            $file = ldc()->download_url($url, [], false, 'cl');
            if(is_wp_error($file)){
                return $file;
            }
            list($width, $height) = wp_getimagesize($file);
            update_post_meta($this->id, $meta_key_f, $file);
            update_post_meta($this->id, $meta_key_h, $height);
            update_post_meta($this->id, $meta_key_w, $width);
            return [
                'file' => $file,
                'filename' => wp_basename($file),
                'height' => $height,
                'url' => ldc()->path_to_url($file),
                'width' => $width,
            ];
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the attachment ID this instance belongs to.
		 *
         * @return int
         */
        public function id(){
            return $this->id;
        }

		// ---------------------------------------------------------------------

        /**
         * @return array|string|WP_Error
         */
        public function meta_value($meta_key = ''){
            if(!$meta_key){
                $error_msg = sprintf(__('The "%s" argument must be a non-empty string.'), 'meta_key');
                return ldc()->error($error_msg);
            }
            if(!wp_attachment_is_image($this->id)){
                $error_msg = __('This file is not an image. Please try another.');
                $error_msg = ldc()->first_p($error_msg);
                return ldc()->error($error_msg);
            }
            return get_post_meta($this->id, $meta_key, true);
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the local URL, its dimensions and whether it is a transformation,
		 * shaped like wp_get_attachment_image_src().
		 *
		 * @see wp_get_attachment_image_src()
		 *
         * @return array|WP_Error
         */
        public function src($options = []){
            $options = $this->options($options);
            if(is_wp_error($options)){
                return $options;
            }
            $info = $this->file_info($options);
            if(is_wp_error($info)){
                return $info;
            }
            return [$info['url'], $info['width'], $info['height'], (bool) $options];
        }

		// ---------------------------------------------------------------------

        /**
		 * Gets the local URL of the downloaded image.
		 *
         * @return string|WP_Error
         */
        public function url($options = []){
            $info = $this->file_info($options);
            if(is_wp_error($info)){
                return $info;
            }
            return $info['url'];
        }

		// ---------------------------------------------------------------------

    }
}
