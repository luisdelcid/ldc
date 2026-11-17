<?php

if(!class_exists('LDC_Tool') && class_exists('LDC_Multiton')){
	class LDC_Tool extends LDC_Multiton {

		// ---------------------------------------------------------------------
        //
		// Protected
        //
		// ---------------------------------------------------------------------

        protected $tool = [];

		// ---------------------------------------------------------------------

        /**
         * @return void
         */
        protected function __construct($tool = []){
            $this->tool = $tool;
        }

		// ---------------------------------------------------------------------

    }
}
