<?php
/*
 * Plugin Name: LDC
 * Plugin URI: https://luisdelcid.com
 * Description: A personal collection of useful methods for WordPress developers.
 * Version: 26.11.17.2
 * Requires at least: 6.4
 * Requires PHP: 8.3
 * Author: Luis del Cid
 * Author URI: https://luisdelcid.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/luisdelcid/ldc
 */

/*
 * This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// Don't load directly.
defined('ABSPATH') || die('-1');

// Check must-use plugin status.
if(did_action('ldc')){

    // Load the Plugin Update Checker library.
    ldc()->use_plugin_update_checker();

    // Check for updates.
    ldc()->build_update_checker(__FILE__);

} else {

    // Load the must-use plugin installer.
    include_once plugin_dir_path(__FILE__) . 'includes/installer/install.php'; // Two levels above.

    // Register the installation handler.
    register_activation_hook(__FILE__, ['LDC_Installer', 'install']);

}
