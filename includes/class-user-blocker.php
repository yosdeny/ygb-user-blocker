<?php
/**
 * Clase principal del plugin YGB User Blocker.
 *
 * @package YGB_User_Blocker
 * @since 1.0.0
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YGB_User_Blocker {

	/**
	 * Nombre de la tabla personalizada.
	 *
	 * @var string
	 */
	const TABLE_NAME = 'ygb_ub_blocked_users';

	/**
	 * Opción para mensaje global activado/desactivado.
	 *
	 * @var string
	 */
	const OPTION_MESSAGE_ENABLED = 'ygb_ub_user_block_message_enabled';

	/**
	 * Opción para el texto del mensaje global.
	 *
	 * @var string
	 */
	const OPTION_MESSAGE_TEXT = 'ygb_ub_user_block_message';

	/**
	 * Meta key para detección rápida en usuarios.
	 *
	 * @var string
	 */
	const USER_META_KEY = '_ygb_ub_blocked_user';

	/**
	 * Inicializa el módulo registrando hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_logout_blocked_user' ), 1 );
		add_filter( 'authenticate', array( __CLASS__, 'block_login' ), 1, 3 );
		add_filter( 'registration_errors', array( __CLASS__, 'block_registration' ), 10, 3 );

		// WooCommerce.
		add_filter( 'woocommerce_process_login_errors', array( __CLASS__, 'block_wc_login' ), 10, 3 );
		add_action( 'woocommerce_login_form', array( __CLASS__, 'maybe_redirect_wc_login' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_wc_pages' ), 1 );

		// Trazas si existe el logger del plugin original.
		add_action( 'ygb_ub_user_blocker_blocked', array( __CLASS__, 'log_trace_event' ), 10, 2 );
		add_action( 'ygb_ub_user_blocker_unblocked', array( __CLASS__, 'log_trace_event' ), 10, 2 );
	}

	/**
	 * Crea la tabla personalizada si no existe.
	 */
	public static function create_table() {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE_NAME;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			email VARCHAR(100) NOT NULL,
			reason TEXT NOT NULL,
			custom_message TEXT NULL,
			blocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			blocked_by BIGINT UNSIGNED DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY email (email),
			KEY user_id (user_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		self::maybe_add_custom_message_column();
	}

	/**
	 * Añade la columna custom_message si no existe (para actualizaciones).
	 */
	public static function maybe_add_custom_message_column() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		$cols = $wpdb->get_col( "DESCRIBE {$table}" );
		if ( ! in_array( 'custom_message', $cols, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN custom_message TEXT NULL AFTER reason" );
		}
	}

	/**
	 * Elimina la tabla al desinstalar.
	 */
	public static function drop_table() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Comprueba si un email está bloqueado.
	 *
	 * @param string $email Email a comprobar.
	 * @return bool
	 */
	public static function is_email_blocked( $email ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		$email = sanitize_email( $email );
		if ( empty( $email ) ) {
			return false;
		}
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE email = %s", $email ) );
	}

	/**
	 * Comprueba si un usuario está bloqueado por ID o email.
	 *
	 * @param int|WP_User $user_id ID o objeto usuario.
	 * @return bool
	 */
	public static function is_user_blocked( $user_id ) {
		if ( $user_id instanceof WP_User ) {
			$email = $user_id->user_email;
			$uid   = $user_id->ID;
		} else {
			$uid   = (int) $user_id;
			$user  = get_userdata( $uid );
			$email = $user ? $user->user_email : '';
		}

		if ( $uid > 0 && self::is_user_id_blocked( $uid ) ) {
			return true;
		}
		return ! empty( $email ) && self::is_email_blocked( $email );
	}

	/**
	 * Comprueba si un ID de usuario está bloqueado.
	 *
	 * @param int $user_id ID.
	 * @return bool
	 */
	public static function is_user_id_blocked( $user_id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", (int) $user_id ) );
	}

	/**
	 * Bloquea un usuario por email (y opcionalmente ID).
	 *
	 * @param string $email          Email.
	 * @param string $reason         Motivo.
	 * @param int    $user_id        ID opcional.
	 * @param int    $blocked_by     ID del admin que bloquea.
	 * @param string $custom_message Mensaje personalizado opcional.
	 * @return bool
	 */
	public static function block_email( $email, $reason, $user_id = 0, $blocked_by = 0, $custom_message = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$email          = sanitize_email( $email );
		$reason         = sanitize_textarea_field( $reason );
		$custom_message = sanitize_textarea_field( $custom_message );

		if ( empty( $email ) || empty( $reason ) ) {
			return false;
		}

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s", $email ) );
		if ( $existing ) {
			$wpdb->update(
				$table,
				array(
					'user_id'        => (int) $user_id,
					'reason'         => $reason,
					'blocked_by'     => (int) $blocked_by,
					'custom_message' => $custom_message,
				),
				array( 'id' => $existing )
			);
		} else {
			$wpdb->insert(
				$table,
				array(
					'user_id'        => (int) $user_id,
					'email'          => $email,
					'reason'         => $reason,
					'custom_message' => $custom_message,
					'blocked_by'     => (int) $blocked_by,
					'blocked_at'     => current_time( 'mysql' ),
				)
			);
		}

		if ( (int) $user_id > 0 ) {
			update_user_meta( $user_id, self::USER_META_KEY, 1 );
		}

		do_action( 'ygb_ub_user_blocker_blocked', $email, $reason );
		return true;
	}

	/**
	 * Desbloquea por email o ID.
	 *
	 * @param string|int $identifier Email o ID.
	 * @return bool
	 */
	public static function unblock( $identifier ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$identifier = trim( $identifier );
		if ( empty( $identifier ) ) {
			return false;
		}

		if ( is_numeric( $identifier ) ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d OR email = %s", (int) $identifier, $identifier ) );
		} else {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $identifier ) );
		}

		if ( ! $row ) {
			return false;
		}

		$email   = $row->email;
		$user_id = (int) $row->user_id;
		$result  = $wpdb->delete( $table, array( 'id' => $row->id ) );

		if ( $result && $user_id > 0 ) {
			delete_user_meta( $user_id, self::USER_META_KEY );
		}

		do_action( 'ygb_ub_user_blocker_unblocked', $email, $user_id );
		return true;
	}

	/**
	 * Obtiene todos los usuarios bloqueados.
	 *
	 * @return array
	 */
	public static function get_blocked_users() {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY blocked_at DESC" );
	}

	/**
	 * Guarda o actualiza el mensaje personalizado de un bloqueo.
	 *
	 * @param string $identifier Email o ID.
	 * @param string $message    Mensaje personalizado.
	 * @return bool
	 */
	public static function set_custom_message( $identifier, $message ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$identifier = trim( $identifier );
		if ( empty( $identifier ) ) {
			return false;
		}

		if ( is_numeric( $identifier ) ) {
			$where = array( 'user_id' => (int) $identifier );
		} else {
			$where = array( 'email' => $identifier );
		}

		$result = $wpdb->update(
			$table,
			array( 'custom_message' => sanitize_textarea_field( $message ) ),
			$where
		);

		return false !== $result;
	}

	/**
	 * Obtiene el mensaje personalizado de un bloqueo si existe.
	 *
	 * @param string|int $identifier Email o ID.
	 * @return string
	 */
	public static function get_custom_message( $identifier ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_NAME;

		$identifier = trim( $identifier );
		if ( empty( $identifier ) ) {
			return '';
		}

		if ( is_numeric( $identifier ) ) {
			return (string) $wpdb->get_var( $wpdb->prepare( "SELECT custom_message FROM {$table} WHERE user_id = %d LIMIT 1", (int) $identifier ) );
		}

		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT custom_message FROM {$table} WHERE email = %s LIMIT 1", $identifier ) );
	}

	/**
	 * Cierra la sesión si el usuario actual está bloqueado.
	 */
	public static function maybe_logout_blocked_user() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( ! $user || 0 === $user->ID ) {
			return;
		}

		if ( self::is_user_blocked( $user ) ) {
			wp_logout();
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Bloquea el login normal.
	 *
	 * @param null|WP_User|WP_Error $user     Objeto usuario, error o null.
	 * @param string                $username Nombre de usuario o email.
	 * @param string                $password Contraseña.
	 * @return WP_Error|WP_User|null
	 */
	public static function block_login( $user, $username, $password ) {
		if ( empty( $username ) ) {
			return $user;
		}

		if ( is_email( $username ) ) {
			$blocked    = self::is_email_blocked( $username );
			$identifier = $username;
		} else {
			$user_obj   = get_user_by( 'login', $username );
			$blocked    = $user_obj ? self::is_user_blocked( $user_obj ) : false;
			$identifier = $user_obj ? $user_obj->user_email : $username;
		}

		if ( $blocked ) {
			self::maybe_redirect_on_login();
			return new WP_Error( 'user_blocked', self::get_blocked_message( $identifier ) );
		}

		return $user;
	}

	/**
	 * Bloquea el registro con email bloqueado.
	 *
	 * @param WP_Error $errors               Errores existentes.
	 * @param string   $sanitized_user_login Login saneado.
	 * @param string   $user_email           Email.
	 * @return WP_Error
	 */
	public static function block_registration( $errors, $sanitized_user_login, $user_email ) {
		if ( self::is_email_blocked( $user_email ) ) {
			$errors->add( 'user_blocked', self::get_blocked_message( $user_email ) );
		}
		return $errors;
	}

	/**
	 * Bloquea login de WooCommerce.
	 *
	 * @param WP_Error $errors   Errores.
	 * @param string   $username Nombre de usuario.
	 * @param string   $password Contraseña.
	 * @return WP_Error
	 */
	public static function block_wc_login( $errors, $username, $password ) {
		if ( empty( $username ) ) {
			return $errors;
		}

		if ( is_email( $username ) ) {
			$blocked    = self::is_email_blocked( $username );
			$identifier = $username;
		} else {
			$user_obj   = get_user_by( 'login', $username );
			$blocked    = $user_obj ? self::is_user_blocked( $user_obj ) : false;
			$identifier = $user_obj ? $user_obj->user_email : $username;
		}

		if ( $blocked ) {
			$errors->add( 'user_blocked', self::get_blocked_message( $identifier ) );
			self::maybe_redirect_on_login();
		}

		return $errors;
	}

	/**
	 * Redirige desde el formulario de login de WC si hay sesión bloqueada.
	 */
	public static function maybe_redirect_wc_login() {
		if ( is_user_logged_in() && self::is_user_blocked( wp_get_current_user() ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Redirige desde páginas de WooCommerce si usuario bloqueado.
	 */
	public static function maybe_redirect_wc_pages() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user = wp_get_current_user();
		if ( ! $user || 0 === $user->ID ) {
			return;
		}

		if ( ! self::is_user_blocked( $user ) ) {
			return;
		}

		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			wp_safe_redirect( home_url() );
			exit;
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			wp_safe_redirect( home_url() );
			exit;
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Redirección silenciosa o con mensaje según configuración.
	 */
	private static function maybe_redirect_on_login() {
		if ( ! self::is_message_enabled() ) {
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Obtiene el mensaje de bloqueo (personalizado o global).
	 *
	 * @param string|int $identifier Email o ID del usuario.
	 * @return string
	 */
	public static function get_blocked_message( $identifier = '' ) {
		if ( ! self::is_message_enabled() ) {
			return '';
		}

		$general = get_option( self::OPTION_MESSAGE_TEXT, '' );
		if ( empty( $general ) ) {
			$general = __( 'Tu cuenta ha sido bloqueada. Contacta con el administrador.', 'ygb-user-blocker' );
		}

		if ( ! empty( $identifier ) ) {
			$custom = self::get_custom_message( $identifier );
			if ( ! empty( $custom ) ) {
				return $custom;
			}
		}

		return $general;
	}

	/**
	 * Comprueba si el mensaje está activado.
	 *
	 * @return bool
	 */
	public static function is_message_enabled() {
		return (bool) get_option( self::OPTION_MESSAGE_ENABLED, false );
	}

	/**
	 * Registra en trazas del plugin original si existe.
	 *
	 * @param string $email Email.
	 * @param mixed  $extra Datos extra.
	 */
	public static function log_trace_event( $email, $extra = '' ) {
		if ( class_exists( 'YGB_Escudo_2_Ajax' ) && method_exists( 'YGB_Escudo_2_Ajax', 'log_trace' ) ) {
			$ip = class_exists( 'YGB_Escudo_2_Security' ) ? YGB_Escudo_2_Security::get_client_ip() : '0.0.0.0';
			YGB_Escudo_2_Ajax::log_trace(
				$ip,
				'user_blocker_event',
				array(
					'email' => $email,
					'extra' => is_array( $extra ) ? wp_json_encode( $extra ) : $extra,
				)
			);
		}
	}
}