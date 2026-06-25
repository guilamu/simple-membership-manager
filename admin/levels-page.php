<?php
/**
 * Membership Levels Admin Page
 *
 * List, add, edit, and delete membership levels.
 *
 * @package Simple_Membership_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SMM_Levels_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'level',
			'plural'   => 'levels',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'name'        => __( 'Name', 'rcp' ),
			'access_level' => __( 'Access Level', 'rcp' ),
			'status'      => __( 'Status', 'rcp' ),
			'duration'    => __( 'Duration', 'rcp' ),
			'role'        => __( 'Role', 'rcp' ),
			'members'     => __( 'Members', 'rcp' ),
		);
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'name':
				$actions = array(
					'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( rcp_get_levels_admin_page( array( 'view' => 'edit', 'level_id' => $item->get_id() ) ) ), __( 'Edit', 'rcp' ) ),
					'delete' => sprintf( '<a href="%s" onclick="return confirm(\'%s\')">%s</a>', esc_url( wp_nonce_url( rcp_get_levels_admin_page( array( 'rcp-action' => 'delete_level', 'level_id' => $item->get_id() ) ), 'delete_level' ) ), __( 'Are you sure?', 'rcp' ), __( 'Delete', 'rcp' ) ),
				);
				return sprintf( '<strong>%1$s</strong> %2$s', esc_html( $item->get_name() ), $this->row_actions( $actions ) );
			case 'access_level':
				return $item->get_access_level();
			case 'status':
				$color = 'active' === $item->get_status() ? 'green' : 'gray';
				return '<span style="color: ' . esc_attr( $color ) . '; font-weight: bold;">' . esc_html( ucfirst( $item->get_status() ) ) . '</span>';
			case 'duration':
				if ( $item->is_lifetime() ) {
					return __( 'Lifetime', 'rcp' );
				}
				return esc_html( $item->get_duration() . ' ' . rcp_filter_duration_unit( $item->get_duration_unit(), $item->get_duration() ) );
			case 'role':
				return esc_html( $item->get_role() );
			case 'members':
				return rcp_get_subscription_member_count( $item->get_id() );
			default:
				return '';
		}
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 20;
		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		$levels = rcp_get_membership_levels( array( 'number' => $per_page, 'offset' => $offset ) );
		$total = rcp_count_membership_levels();

		$this->items = $levels;
		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		) );
	}
}

function rcp_member_levels_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}

	$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : '';

	if ( 'add' === $view ) {
		smm_render_add_level();
		return;
	}

	if ( 'edit' === $view && isset( $_GET['level_id'] ) ) {
		smm_render_edit_level( absint( $_GET['level_id'] ) );
		return;
	}

	$table = new SMM_Levels_Table();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1><?php _e( 'Membership Levels', 'rcp' ); ?>
			<a href="<?php echo esc_url( rcp_get_levels_admin_page( array( 'view' => 'add' ) ) ); ?>" class="page-title-action"><?php _e( 'Add New', 'rcp' ); ?></a>
		</h1>
		<form method="get">
			<input type="hidden" name="page" value="rcp-member-levels" />
			<?php $table->display(); ?>
		</form>
	</div>
	<?php
}

function smm_render_level_form_fields( $level = null ) {
	global $wp_roles;
	$all_roles = $wp_roles->get_names();
	?>
	<table class="widefat striped">
		<tbody>
		<tr>
			<th scope="row"><label for="rcp-level-name"><?php _e( 'Name', 'rcp' ); ?></label></th>
			<td><input type="text" id="rcp-level-name" name="name" value="<?php echo $level ? esc_attr( $level->get_name() ) : ''; ?>" class="regular-text" required/></td>
		</tr>
		<tr>
			<th scope="row"><label for="rcp-level-description"><?php _e( 'Description', 'rcp' ); ?></label></th>
			<td><textarea id="rcp-level-description" name="description" rows="3" class="large-text"><?php echo $level ? esc_textarea( $level->get_description() ) : ''; ?></textarea></td>
		</tr>
		<tr>
			<th scope="row"><label for="rcp-level-duration"><?php _e( 'Duration', 'rcp' ); ?></label></th>
			<td>
				<input type="number" id="rcp-level-duration" name="duration" value="<?php echo $level ? esc_attr( $level->get_duration() ) : '0'; ?>" min="0" style="width: 80px;"/>
				<select name="duration_unit">
					<?php foreach ( array( 'day' => __( 'Day(s)', 'rcp' ), 'month' => __( 'Month(s)', 'rcp' ), 'year' => __( 'Year(s)', 'rcp' ) ) as $unit => $label ) : ?>
						<option value="<?php echo esc_attr( $unit ); ?>" <?php echo $level ? selected( $unit, $level->get_duration_unit(), false ) : selected( 'month', $unit, false ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php _e( 'Set to 0 for lifetime membership.', 'rcp' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><label for="rcp-level-access"><?php _e( 'Access Level', 'rcp' ); ?></label></th>
			<td>
				<select id="rcp-level-access" name="level">
					<?php for ( $i = 0; $i <= 10; $i++ ) : ?>
						<option value="<?php echo esc_attr( $i ); ?>" <?php echo $level ? selected( $i, $level->get_access_level(), false ) : selected( 0, $i, false ); ?>><?php echo esc_html( $i ); ?></option>
					<?php endfor; ?>
				</select>
				<p class="description"><?php _e( '0 = lowest access, 10 = highest access.', 'rcp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rcp-level-role"><?php _e( 'User Role', 'rcp' ); ?></label></th>
			<td>
				<select id="rcp-level-role" name="role">
					<?php foreach ( $all_roles as $role_slug => $role_name ) : ?>
						<option value="<?php echo esc_attr( $role_slug ); ?>" <?php echo $level ? selected( $role_slug, $level->get_role(), false ) : selected( 'subscriber', $role_slug, false ); ?>><?php echo esc_html( translate_user_role( $role_name ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php _e( 'This role is assigned to the user when their membership is activated.', 'rcp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rcp-level-status"><?php _e( 'Status', 'rcp' ); ?></label></th>
			<td>
				<select id="rcp-level-status" name="status">
					<option value="active" <?php echo $level ? selected( 'active', $level->get_status(), false ) : selected( 'active', 'active', false ); ?>><?php _e( 'Active', 'rcp' ); ?></option>
					<option value="inactive" <?php echo $level ? selected( 'inactive', $level->get_status(), false ) : ''; ?>><?php _e( 'Inactive', 'rcp' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rcp-level-order"><?php _e( 'List Order', 'rcp' ); ?></label></th>
			<td><input type="number" id="rcp-level-order" name="list_order" value="<?php echo $level ? esc_attr( $level->get_list_order() ) : '0'; ?>" min="0" style="width: 80px;"/></td>
		</tr>
		</tbody>
	</table>
	<?php
}

function smm_render_add_level() {
	?>
	<div class="wrap">
		<h1><?php _e( 'Add Membership Level', 'rcp' ); ?></h1>
		<form method="POST" action="">
			<?php smm_render_level_form_fields(); ?>
			<p>
				<input type="hidden" name="rcp-action" value="add_level"/>
				<?php wp_nonce_field( 'rcp_add_level', 'rcp_add_level_nonce' ); ?>
				<input type="submit" class="button button-primary" value="<?php _e( 'Add Level', 'rcp' ); ?>"/>
				<a href="<?php echo esc_url( rcp_get_levels_admin_page() ); ?>" class="button"><?php _e( 'Cancel', 'rcp' ); ?></a>
			</p>
		</form>
	</div>
	<?php
}

function smm_render_edit_level( $level_id ) {
	$level = rcp_get_membership_level( $level_id );
	if ( ! $level ) {
		wp_die( __( 'Invalid level.', 'rcp' ) );
	}
	?>
	<div class="wrap">
		<h1><?php _e( 'Edit Membership Level', 'rcp' ); ?></h1>
		<form method="POST" action="">
			<?php smm_render_level_form_fields( $level ); ?>
			<p>
				<input type="hidden" name="level_id" value="<?php echo esc_attr( $level->get_id() ); ?>"/>
				<input type="hidden" name="rcp-action" value="edit_level"/>
				<?php wp_nonce_field( 'rcp_edit_level', 'rcp_edit_level_nonce' ); ?>
				<input type="submit" class="button button-primary" value="<?php _e( 'Update Level', 'rcp' ); ?>"/>
				<a href="<?php echo esc_url( rcp_get_levels_admin_page() ); ?>" class="button"><?php _e( 'Cancel', 'rcp' ); ?></a>
			</p>
		</form>
	</div>
	<?php
}

function smm_process_add_level() {
	if ( ! wp_verify_nonce( $_POST['rcp_add_level_nonce'], 'rcp_add_level' ) ) {
		wp_die( __( 'Nonce verification failed.', 'rcp' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}

	$args = array(
		'name'          => sanitize_text_field( $_POST['name'] ),
		'description'   => wp_kses_post( $_POST['description'] ),
		'duration'      => absint( $_POST['duration'] ),
		'duration_unit' => sanitize_text_field( $_POST['duration_unit'] ),
		'price'         => '0',
		'fee'           => '0',
		'level'         => absint( $_POST['level'] ),
		'role'          => sanitize_text_field( $_POST['role'] ),
		'status'        => sanitize_text_field( $_POST['status'] ),
		'list_order'    => absint( $_POST['list_order'] ),
	);

	$result = rcp_add_membership_level( $args );
	if ( is_wp_error( $result ) ) {
		wp_die( $result->get_error_message() );
	}

	wp_safe_redirect( rcp_get_levels_admin_page( array( 'rcp_message' => 'level_added' ) ) );
	exit;
}
add_action( 'rcp_action_add_level', 'smm_process_add_level' );

function smm_process_edit_level() {
	if ( ! wp_verify_nonce( $_POST['rcp_edit_level_nonce'], 'rcp_edit_level' ) ) {
		wp_die( __( 'Nonce verification failed.', 'rcp' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}

	$level_id = absint( $_POST['level_id'] );
	$args = array(
		'name'          => sanitize_text_field( $_POST['name'] ),
		'description'   => wp_kses_post( $_POST['description'] ),
		'duration'      => absint( $_POST['duration'] ),
		'duration_unit' => sanitize_text_field( $_POST['duration_unit'] ),
		'price'         => '0',
		'fee'           => '0',
		'level'         => absint( $_POST['level'] ),
		'role'          => sanitize_text_field( $_POST['role'] ),
		'status'        => sanitize_text_field( $_POST['status'] ),
		'list_order'    => absint( $_POST['list_order'] ),
	);

	$result = rcp_update_membership_level( $level_id, $args );
	if ( is_wp_error( $result ) ) {
		wp_die( $result->get_error_message() );
	}

	wp_safe_redirect( rcp_get_levels_admin_page( array( 'rcp_message' => 'level_updated' ) ) );
	exit;
}
add_action( 'rcp_action_edit_level', 'smm_process_edit_level' );

function smm_process_delete_level() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'delete_level' ) ) {
		wp_die( __( 'Nonce verification failed.', 'rcp' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}

	if ( empty( $_GET['level_id'] ) ) {
		wp_die( __( 'Invalid level.', 'rcp' ) );
	}

	rcp_delete_membership_level( absint( $_GET['level_id'] ) );
	wp_safe_redirect( rcp_get_levels_admin_page( array( 'rcp_message' => 'level_deleted' ) ) );
	exit;
}
add_action( 'rcp_action_delete_level', 'smm_process_delete_level' );
