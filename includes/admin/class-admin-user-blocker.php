<?php
/**
 * Interfaz de administración del plugin YGB User Blocker.
 *
 * @package YGB_User_Blocker
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YGB_User_Blocker_Admin {

	/**
	 * Inicializa los hooks de administración.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_submenu' ), 45 );
		add_action( 'admin_post_ygb_ub_block_users', array( __CLASS__, 'handle_block_users' ) );
		add_action( 'admin_post_ygb_ub_unblock_user', array( __CLASS__, 'handle_unblock_user' ) );
		add_action( 'admin_post_ygb_ub_save_user_block_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_ygb_ub_update_custom_message', array( __CLASS__, 'handle_update_custom_message' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_admin_notice' ) );
	}

	/**
	 * Añade submenú bajo Usuarios.
	 */
	public static function add_submenu() {
		add_users_page(
			__( 'Usuarios Bloqueados', 'ygb-user-blocker' ),
			__( 'Usuarios Bloqueados', 'ygb-user-blocker' ),
			'ygb_ub_manage',
			'ygb-user-blocker',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Carga estilos inline.
	 *
	 * @param string $hook Hook de la página actual.
	 */
	public static function enqueue_styles( $hook ) {
		if ( false === strpos( $hook, 'ygb-user-blocker' ) && ! in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
			return;
		}
		wp_add_inline_style(
			'wp-admin',
			'
			.ygb-ublock-form{background:#fff;padding:20px;border:1px solid #c3c4c7;border-radius:6px;max-width:800px;margin-bottom:30px;}
			.ygb-ublock-form h2{margin-top:0;}
			.ygb-ublock-results{max-height:300px;overflow-y:auto;border:1px solid #ddd;padding:10px;margin:10px 0;}
			.ygb-ublock-results table{width:100%;border-collapse:collapse;}
			.ygb-ublock-results th{background:#f0f0f1;padding:6px;text-align:left;}
			.ygb-ublock-results td{padding:6px;border-bottom:1px solid #eee;}
			.ygb-blocked-table td, .ygb-blocked-table th{padding:8px;}
			.ygb-motivo{color:#666;font-size:12px;}
			.notice.ygb-user-blocked-notice{border-left-color:#d63638 !important;background:#f8d7da !important;padding:12px 16px;}
			.notice.ygb-user-blocked-notice p{margin:0.3em 0;color:#721c24;}
			.notice.ygb-user-blocked-notice strong{display:block;font-size:15px;margin-bottom:6px;color:#a02020;}
			.ygb-custom-message{display:none;margin-top:8px;}
			.ygb-custom-message textarea{width:100%;min-height:80px;margin-bottom:5px;}
			.ygb-custom-msg-preview{font-style:italic;color:#666;margin-top:4px;}
			'
		);
	}

	/**
	 * Muestra un aviso superior si el usuario editado está bloqueado.
	 */
	public static function maybe_show_admin_notice() {
		if ( ! current_user_can( 'ygb_ub_manage' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		$user_id = 0;
		if ( 'profile' === $screen->id ) {
			$user_id = get_current_user_id();
		} elseif ( 'user-edit' === $screen->id && isset( $_GET['user_id'] ) ) {
			$user_id = absint( $_GET['user_id'] );
		}

		if ( ! $user_id ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		if ( ! YGB_User_Blocker::is_user_blocked( $user ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . YGB_User_Blocker::TABLE_NAME;
		$block = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d OR email = %s ORDER BY id DESC LIMIT 1",
				$user->ID,
				$user->user_email
			)
		);

		$reason     = $block ? $block->reason : __( 'Sin motivo registrado', 'ygb-user-blocker' );
		$blocked_at = $block ? $block->blocked_at : current_time( 'mysql' );
		$blocked_by = $block && $block->blocked_by ? get_userdata( $block->blocked_by ) : null;
		$admin_name = $blocked_by ? $blocked_by->display_name : __( 'Desconocido', 'ygb-user-blocker' );
		$manage_url = admin_url( 'users.php?page=ygb-user-blocker' );
		?>
		<div class="notice notice-error ygb-user-blocked-notice">
			<strong>⛔ <?php esc_html_e( 'Este usuario está bloqueado', 'ygb-user-blocker' ); ?></strong>
			<p>
				<?php
				printf(
					/* translators: 1: motivo, 2: fecha, 3: admin */
					esc_html__( 'Motivo: %1$s | Fecha: %2$s | Bloqueado por: %3$s', 'ygb-user-blocker' ),
					esc_html( $reason ),
					esc_html( $blocked_at ),
					esc_html( $admin_name )
				);
				?>
			</p>
			<a href="<?php echo esc_url( $manage_url ); ?>" class="button button-small"><?php esc_html_e( 'Gestionar bloqueos', 'ygb-user-blocker' ); ?></a>
		</div>
		<?php
	}

	/**
	 * Renderiza la página principal.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'ygb_ub_manage' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'ygb-user-blocker' ) );
		}

		$search          = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$message_enabled = (bool) get_option( YGB_User_Blocker::OPTION_MESSAGE_ENABLED, false );
		$message_text    = get_option( YGB_User_Blocker::OPTION_MESSAGE_TEXT, '' );
		$blocked_users   = YGB_User_Blocker::get_blocked_users();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Usuarios Bloqueados', 'ygb-user-blocker' ); ?></h1>

			<?php if ( isset( $_GET['blocked'] ) && '1' === $_GET['blocked'] ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Usuario(s) bloqueado(s) correctamente.', 'ygb-user-blocker' ); ?></p></div>
			<?php elseif ( isset( $_GET['unblocked'] ) && '1' === $_GET['unblocked'] ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Usuario desbloqueado correctamente.', 'ygb-user-blocker' ); ?></p></div>
			<?php elseif ( isset( $_GET['settings'] ) && '1' === $_GET['settings'] ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Configuración guardada.', 'ygb-user-blocker' ); ?></p></div>
			<?php elseif ( isset( $_GET['custom'] ) && '1' === $_GET['custom'] ) : ?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Mensaje personalizado actualizado.', 'ygb-user-blocker' ); ?></p></div>
			<?php endif; ?>

			<!-- Formulario de búsqueda y bloqueo -->
			<div class="ygb-ublock-form">
				<h2><?php esc_html_e( 'Buscar y bloquear usuarios', 'ygb-user-blocker' ); ?></h2>
				<form method="get" action="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">
					<input type="hidden" name="page" value="ygb-user-blocker">
					<input type="search" name="search" value="<?php echo esc_attr( $search ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Email o nombre de usuario', 'ygb-user-blocker' ); ?>">
					<button class="button"><?php esc_html_e( 'Buscar', 'ygb-user-blocker' ); ?></button>
				</form>

				<?php if ( ! empty( $search ) ) : ?>
					<?php $users = self::search_users( $search ); ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ygb_ub_block_users">
						<?php wp_nonce_field( 'ygb_ub_block_users' ); ?>

						<p><strong><?php esc_html_e( 'Resultados:', 'ygb-user-blocker' ); ?></strong></p>
						<div class="ygb-ublock-results">
							<?php if ( empty( $users ) ) : ?>
								<p><?php esc_html_e( 'No se encontraron usuarios.', 'ygb-user-blocker' ); ?></p>
							<?php else : ?>
								<table>
									<thead>
										<tr>
											<th><input type="checkbox" id="ygb-check-all"></th>
											<th><?php esc_html_e( 'Nombre', 'ygb-user-blocker' ); ?></th>
											<th><?php esc_html_e( 'Email', 'ygb-user-blocker' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $users as $u ) : ?>
											<tr>
												<td><input type="checkbox" name="user_ids[]" value="<?php echo (int) $u->ID; ?>"></td>
												<td><?php echo esc_html( $u->display_name ); ?> (<?php echo esc_html( $u->user_login ); ?>)</td>
												<td><?php echo esc_html( $u->user_email ); ?></td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $users ) ) : ?>
							<label><strong><?php esc_html_e( 'Motivo del bloqueo (obligatorio):', 'ygb-user-blocker' ); ?></strong></label><br>
							<textarea name="reason" rows="3" class="large-text" required></textarea>
							<p class="description"><?php esc_html_e( 'Se guardará para trazabilidad.', 'ygb-user-blocker' ); ?></p>

							<label><strong><?php esc_html_e( 'Mensaje personalizado (opcional):', 'ygb-user-blocker' ); ?></strong></label><br>
							<textarea name="custom_message" rows="3" class="large-text"></textarea>
							<p class="description"><?php esc_html_e( 'Si se deja vacío, se usará el mensaje global.', 'ygb-user-blocker' ); ?></p>

							<button type="submit" class="button button-primary"><?php esc_html_e( 'Bloquear seleccionados', 'ygb-user-blocker' ); ?></button>
						<?php endif; ?>
					</form>
				<?php endif; ?>
			</div>

			<!-- Configuración global -->
			<div class="ygb-ublock-form">
				<h2><?php esc_html_e( 'Configuración', 'ygb-user-blocker' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ygb_ub_save_user_block_settings">
					<?php wp_nonce_field( 'ygb_ub_save_user_block_settings' ); ?>
					<label>
						<input type="checkbox" name="message_enabled" value="1" <?php checked( $message_enabled ); ?>>
						<?php esc_html_e( 'Mostrar mensaje personalizado al bloquear', 'ygb-user-blocker' ); ?>
					</label>
					<br><br>
					<label><strong><?php esc_html_e( 'Mensaje global:', 'ygb-user-blocker' ); ?></strong></label><br>
					<textarea name="message_text" rows="3" class="large-text"><?php echo esc_textarea( $message_text ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Si se deja vacío, se usará el mensaje por defecto.', 'ygb-user-blocker' ); ?></p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Guardar configuración', 'ygb-user-blocker' ); ?></button>
				</form>
			</div>

			<!-- Usuarios bloqueados -->
			<h2><?php esc_html_e( 'Usuarios bloqueados', 'ygb-user-blocker' ); ?></h2>
			<table class="wp-list-table widefat fixed striped ygb-blocked-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Email', 'ygb-user-blocker' ); ?></th>
						<th><?php esc_html_e( 'Usuario', 'ygb-user-blocker' ); ?></th>
						<th><?php esc_html_e( 'Motivo', 'ygb-user-blocker' ); ?></th>
						<th><?php esc_html_e( 'Mensaje personalizado', 'ygb-user-blocker' ); ?></th>
						<th><?php esc_html_e( 'Fecha', 'ygb-user-blocker' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'ygb-user-blocker' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $blocked_users ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No hay usuarios bloqueados.', 'ygb-user-blocker' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $blocked_users as $blocked ) : ?>
							<?php $email_id = sanitize_title_with_dashes( $blocked->email ); ?>
							<tr>
								<td><?php echo esc_html( $blocked->email ); ?></td>
								<td>
									<?php
									if ( $blocked->user_id ) {
										$user = get_userdata( $blocked->user_id );
										echo $user ? esc_html( $user->display_name ) . ' (' . esc_html( $user->user_login ) . ')' : esc_html__( 'Usuario eliminado', 'ygb-user-blocker' );
									} else {
										esc_html_e( 'N/A', 'ygb-user-blocker' );
									}
									?>
								</td>
								<td><span class="ygb-motivo"><?php echo esc_html( $blocked->reason ); ?></span></td>
								<td>
									<?php if ( ! empty( $blocked->custom_message ) ) : ?>
										<div class="ygb-custom-msg-preview"><?php echo esc_html( $blocked->custom_message ); ?></div>
									<?php else : ?>
										<em><?php esc_html_e( 'Usando mensaje global', 'ygb-user-blocker' ); ?></em>
									<?php endif; ?>
									<button type="button" class="button button-small ygb-edit-custom" data-target="custom-<?php echo esc_attr( $email_id ); ?>"><?php esc_html_e( 'Editar', 'ygb-user-blocker' ); ?></button>
									<div class="ygb-custom-message" id="custom-<?php echo esc_attr( $email_id ); ?>">
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<input type="hidden" name="action" value="ygb_ub_update_custom_message">
											<input type="hidden" name="identifier" value="<?php echo esc_attr( $blocked->email ); ?>">
											<?php wp_nonce_field( 'ygb_ub_update_custom_message' ); ?>
											<textarea name="custom_message" rows="3"><?php echo esc_textarea( $blocked->custom_message ?? '' ); ?></textarea>
											<button type="submit" class="button button-small"><?php esc_html_e( 'Guardar', 'ygb-user-blocker' ); ?></button>
										</form>
									</div>
								</td>
								<td><?php echo esc_html( $blocked->blocked_at ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<input type="hidden" name="action" value="ygb_ub_unblock_user">
										<input type="hidden" name="identifier" value="<?php echo esc_attr( $blocked->email ); ?>">
										<?php wp_nonce_field( 'ygb_ub_unblock_user' ); ?>
										<button type="submit" class="button button-small"><?php esc_html_e( 'Desbloquear', 'ygb-user-blocker' ); ?></button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#ygb-check-all').on('change', function() {
				$('input[name="user_ids[]"]').prop('checked', this.checked);
			});

			$('.ygb-edit-custom').on('click', function() {
				var target = $(this).data('target');
				$('#' + target).toggle();
			});
		});
		</script>
		<?php
	}

	/**
	 * Procesa el bloqueo de usuarios seleccionados.
	 */
	public static function handle_block_users() {
		check_admin_referer( 'ygb_ub_block_users' );
		if ( ! current_user_can( 'ygb_ub_manage' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'ygb-user-blocker' ) );
		}

		$reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		if ( empty( $reason ) ) {
			wp_safe_redirect( add_query_arg( 'blocked', '0', admin_url( 'users.php?page=ygb-user-blocker' ) ) );
			exit;
		}

		$custom_message = isset( $_POST['custom_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_message'] ) ) : '';

		$user_ids = isset( $_POST['user_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['user_ids'] ) ) : array();
		if ( empty( $user_ids ) ) {
			wp_safe_redirect( add_query_arg( 'blocked', '0', admin_url( 'users.php?page=ygb-user-blocker' ) ) );
			exit;
		}

		$blocked_by = get_current_user_id();
		foreach ( $user_ids as $uid ) {
			$user = get_userdata( $uid );
			if ( $user ) {
				YGB_User_Blocker::block_email( $user->user_email, $reason, $uid, $blocked_by, $custom_message );
			}
		}

		wp_safe_redirect( add_query_arg( 'blocked', '1', admin_url( 'users.php?page=ygb-user-blocker' ) ) );
		exit;
	}

	/**
	 * Procesa la actualización del mensaje personalizado.
	 */
	public static function handle_update_custom_message() {
		check_admin_referer( 'ygb_ub_update_custom_message' );
		if ( ! current_user_can( 'ygb_ub_manage' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'ygb-user-blocker' ) );
		}

		$identifier     = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		$custom_message = isset( $_POST['custom_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_message'] ) ) : '';

		if ( ! empty( $identifier ) ) {
			YGB_User_Blocker::set_custom_message( $identifier, $custom_message );
		}

		wp_safe_redirect( add_query_arg( 'custom', '1', admin_url( 'users.php?page=ygb-user-blocker' ) ) );
		exit;
	}

	/**
	 * Procesa el desbloqueo.
	 */
	public static function handle_unblock_user() {
		check_admin_referer( 'ygb_ub_unblock_user' );
		if ( ! current_user_can( 'ygb_ub_manage' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'ygb-user-blocker' ) );
		}

		$identifier = isset( $_POST['identifier'] ) ? sanitize_text_field( wp_unslash( $_POST['identifier'] ) ) : '';
		if ( ! empty( $identifier ) ) {
			YGB_User_Blocker::unblock( $identifier );
		}

		wp_safe_redirect( add_query_arg( 'unblocked', '1', admin_url( 'users.php?page=ygb-user-blocker' ) ) );
		exit;
	}

	/**
	 * Guarda la configuración global.
	 */
	public static function save_settings() {
		check_admin_referer( 'ygb_ub_save_user_block_settings' );
		if ( ! current_user_can( 'ygb_ub_manage' ) ) {
			wp_die( esc_html__( 'No autorizado.', 'ygb-user-blocker' ) );
		}

		update_option( YGB_User_Blocker::OPTION_MESSAGE_ENABLED, isset( $_POST['message_enabled'] ) );
		update_option( YGB_User_Blocker::OPTION_MESSAGE_TEXT, sanitize_textarea_field( wp_unslash( $_POST['message_text'] ?? '' ) ) );

		wp_safe_redirect( add_query_arg( 'settings', '1', admin_url( 'users.php?page=ygb-user-blocker' ) ) );
		exit;
	}

	/**
	 * Busca usuarios por email, login o nombre.
	 *
	 * @param string $search Término de búsqueda.
	 * @return array
	 */
	private static function search_users( $search ) {
		$args = array(
			'search'         => '*' . $search . '*',
			'search_columns' => array( 'user_email', 'user_login', 'display_name' ),
			'number'         => 20,
			'orderby'        => 'display_name',
			'order'          => 'ASC',
		);
		$query = new WP_User_Query( $args );
		return $query->get_results();
	}
}