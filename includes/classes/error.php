<?php

if(!class_exists('LDC_Error') && class_exists('WP_Error')){
	final class LDC_Error extends WP_Error {

		// ---------------------------------------------------------------------
        //
		// Public
        //
		// ---------------------------------------------------------------------

    	/**
    	 * @return self
    	 */
    	public function __call($name = '', $arguments = []){
            $message = __('Function %1$s was called <strong>incorrectly</strong>. %2$s %3$s');
            $message = sprintf($message, $name, '', '');
    		$message = trim($message);
    		return new self('ldc_error', $message, $arguments); // Hardcoded.
    	}

		// ---------------------------------------------------------------------

    }
}
