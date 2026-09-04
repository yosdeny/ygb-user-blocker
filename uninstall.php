<?php
/**
 * Desinstalación del plugin YGB User Blocker.
 *
 * @package YGB_User_Blocker
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-user-blocker.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-user-blocker-install.php';

YGB_User_Blocker_Install::uninstall();