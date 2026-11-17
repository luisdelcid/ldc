<?php

// Don't load directly.
defined('WP_UNINSTALL_PLUGIN') || die('-1');

// Check WPMU_PLUGIN_DIR constant status.
if(defined('WPMU_PLUGIN_DIR') && is_dir(WPMU_PLUGIN_DIR)){

    // Attempt to delete the must-use plugin file.
    if(file_exists(WPMU_PLUGIN_DIR . '/ldc-must-use.php')){
        @unlink(WPMU_PLUGIN_DIR . '/ldc-must-use.php');
    }

}
