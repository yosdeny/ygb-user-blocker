<?php
/**
 * Instalación y desinstalación del plugin YGB User Blocker.
 *
 * @package YGB_User_Blocker
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YGB_User_Blocker_Install {

	/**
	 * Activa el plugin: crea tabla, opciones y capability.
	 */
	public static function activate() {
		YGB_User_Blocker::create_table();

		if ( false === get_option( YGB_User_Blocker::OPTION_MESSAGE_ENABLED ) ) {
			add_option( YGB_User_Blocker::OPTION_MESSAGE_ENABLED, false );
		}
		if ( false === get_option( YGB_User_Blocker::OPTION_MESSAGE_TEXT ) ) {
			add_option(
				YGB_User_Blocker::OPTION_MESSAGE_TEXT,
				__( 'Tu cuenta ha sido bloqueada. Contacta con el administrador.', 'ygb-user-blocker' )
			);
		}

		self::add_capabilities();
	}

	/**
	 * Desactiva el plugin (no borra datos intencionadamente).
	 */
	public static function deactivate() {
		// No se elimina la tabla ni opciones para preservar bloqueos.
	}

	/**
	 * Desinstala: elimina tabla, opciones, meta y capability.
	 */
	public static function uninstall() {
		YGB_User_Blocker::drop_table();
		delete_option( YGB_User_Blocker::OPTION_MESSAGE_ENABLED );
		delete_option( YGB_User_Blocker::OPTION_MESSAGE_TEXT );

		global $wpdb;
		$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => YGB_User_Blocker::USER_META_KEY ) );

		self::remove_capabilities();
	}

	/**
	 * Añade la capability personalizada al rol administrador.
	 */
	private static function add_capabilities() {
		$role = get_role( 'administrator' );
		if ( $role && ! $role->has_cap( 'ygb_ub_manage' ) ) {
			$role->add_cap( 'ygb_ub_manage' );
		}
	}

	/**
	 * Elimina la capability personalizada del rol administrador.
	 */
	private static function remove_capabilities() {
		$role = get_role( 'administrator' );
		if ( $role && $role->has_cap( 'ygb_ub_manage' ) ) {
			$role->remove_cap( 'ygb_ub_manage' );
		}
	}
}