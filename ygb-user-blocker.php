<?php
/**
 * Plugin Name: YGB User Blocker
 * Description: Bloqueo indefinido de usuarios por ID o email. Impide acceso a login normal y WooCommerce, desconecta sesiones activas y redirige. Incluye mensajes personalizados.
 * Version: 1.0.0
 * Author: YGB
 * License: GPL-2.0+
 * Text Domain: ygb-user-blocker
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Bloqueo de acceso directo.
}

define( 'YGB_UB_VERSION', '1.0.0' );
define( 'YGB_UB_PLUGIN_FILE', __FILE__ );
define( 'YGB_UB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'YGB_UB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Incluir clases del plugin.
require_once YGB_UB_PLUGIN_DIR . 'includes/class-user-blocker.php';
require_once YGB_UB_PLUGIN_DIR . 'includes/class-user-blocker-install.php';
require_once YGB_UB_PLUGIN_DIR . 'includes/admin/class-admin-user-blocker.php';

// Hooks de activación / desactivación.
register_activation_hook( __FILE__, array( 'YGB_User_Blocker_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'YGB_User_Blocker_Install', 'deactivate' ) );

/**
 * Inicializa las clases principales.
 */
function ygb_ub_init() {
	YGB_User_Blocker::init();
	YGB_User_Blocker_Admin::init();
}
add_action( 'plugins_loaded', 'ygb_ub_init' );