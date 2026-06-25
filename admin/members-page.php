<?php
/**
 * Memberships Admin Page
 *
 * List table, add/edit forms, and membership actions.
 *
 * @package Simple_Membership_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SMM_Memberships_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'membership',
			'plural'   => 'memberships',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox" />',
			'id'             => __( 'ID', 'rcp' ),
			'user'           => __( 'User', 'rcp' ),
			'level'          => __( 'Level', 'rcp' ),
			'status'         => __( 'Status', 'rcp' ),
			'expiration'     => __( 'Expiration', 'rcp' ),
			'created'        => __( 'Created', 'rcp' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'id'         => array( 'id', true ),
			'status'     => array( 'status', false ),
			'expiration' => array( 'expiration_date', false ),
			'created'    => array( 'created_date', false ),
		);
	}

	protected function get_bulk_actions() {
		return array();
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="membership[]" value="%d" />', $item->get_id() );
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return $item->get_id();
			case 'user':
				$user = get_userdata( $item->get_user_id() );
				if ( $user ) {
					return sprintf( '<a href="%s">%s</a>', esc_url( rcp_get_customers_admin_page( array( 'customer_id' => $item->get_customer_id(), 'view' => 'edit' ) ) ), esc_html( $user->display_name ) );
				}
				return __( '(no user)', 'rcp' );
			case 'level':
				return esc_html( $item->get_membership_level_name() );
			case 'status':
				$status = $item->get_status();
				$label = rcp_get_status_label( $status );
				$color = 'active' === $status ? 'green' : ( 'expired' === $status || 'cancelled' === $status ? 'red' : 'orange' );
				return '<span style="color: ' . esc_attr( $color ) . '; font-weight: bold;">' . esc_html( $label ) . '</span>';
			case 'expiration':
				$exp = $item->get_expiration_date();
				return 'none' === $exp ? __( 'Never', 'rcp' ) : esc_html( $exp );
			case 'created':
				return esc_html( $item->get_created_date() );
			default:
				return '';
		}
	}

	public function column_id( $item ) {
		$actions = array(
			'edit'    => sprintf( '<a href="%s">%s</a>', esc_url( rcp_get_memberships_admin_page( array( 'membership_id' => $item->get_id(), 'view' => 'edit' ) ) ), __( 'Edit', 'rcp' ) ),
		);
		if ( $item->is_active() ) {
			$actions['cancel'] = sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( array( 'rcp-action' => 'cancel_membership', 'membership_id' => $item->get_id() ), admin_url( 'admin.php' ) ), 'cancel_membership' ) ), __( 'Cancel', 'rcp' ) );
		}
		if ( ! $item->is_expired() ) {
			$actions['expire'] = sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( array( 'rcp-action' => 'expire_membership', 'membership_id' => $item->get_id() ), admin_url( 'admin.php' ) ), 'expire_membership' ) ), __( 'Expire', 'rcp' ) );
		}
		if ( $item->is_active() ) {
			$actions['activate'] = '';
		} else {
			$actions['activate'] = sprintf( '<a href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( array( 'rcp-action' => 'activate_membership', 'membership_id' => $item->get_id() ), admin_url( 'admin.php' ) ), 'activate_membership' ) ), __( 'Activate', 'rcp' ) );
		}
		$actions = array_filter( $actions );
		return sprintf( '%1$s %2$s', $item->get_id(), $this->row_actions( $actions ) );
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page = 20;
		$current_page = $this->get_pagenum();
		$offset = ( $current_page - 1 ) * $per_page;

		$args = array(
			'number' => $per_page,
			'offset' => $offset,
			'disabled' => 0,
		);

		$status = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
		if ( ! empty( $status ) && 'all' !== $status ) {
			$args['status'] = $status;
		}

		if ( ! empty( $_GET['customer_id'] ) ) {
			$args['customer_id'] = absint( $_GET['customer_id'] );
		}

		$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		if ( ! empty( $search ) ) {
			global $wpdb;
			$user = get_user_by( 'login', $search );
			if ( ! $user ) {
				$user = get_user_by( 'email', $search );
			}
			if ( $user ) {
				$args['user_id'] = $user->ID;
			}
		}

		if ( isset( $_GET['orderby'] ) ) {
			$args['orderby'] = sanitize_text_field( $_GET['orderby'] );
		}
		if ( isset( $_GET['order'] ) ) {
			$args['order'] = sanitize_text_field( $_GET['order'] );
		}

		$memberships = rcp_get_memberships( $args );
		$total = rcp_count_memberships( $args );

		$this->items = $memberships;

		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		) );
	}

	protected function get_views() {
		$views = array();
		$current = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all';

		$all_count = rcp_count_memberships( array( 'disabled' => 0 ) );
		$views['all'] = sprintf(
			'<a href="%s"%s>%s (%d)</a>',
			esc_url( rcp_get_memberships_admin_page() ),
			'all' === $current ? ' class="current"' : '',
			__( 'All', 'rcp' ),
			$all_count
		);

		foreach ( array( 'active', 'expired', 'cancelled', 'pending' ) as $status ) {
			$count = rcp_count_memberships( array( 'status' => $status, 'disabled' => 0 ) );
			if ( $count > 0 ) {
				$views[ $status ] = sprintf(
					'<a href="%s"%s>%s (%d)</a>',
					esc_url( rcp_get_memberships_admin_page( array( 'status' => $status ) ) ),
					$current === $status ? ' class="current"' : '',
					rcp_get_status_label( $status ),
					$count
				);
			}
		}
		return $views;
	}
}

function rcp_members_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission to access this page.', 'rcp' ) );
	}

	$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : '';

	if ( 'add' === $view ) {
		smm_render_add_membership();
		return;
	}

	if ( 'edit' === $view && isset( $_GET['membership_id'] ) ) {
		smm_render_edit_membership( absint( $_GET['membership_id'] ) );
		return;
	}

	$table = new SMM_Memberships_Table();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1><?php _e( 'Memberships', 'rcp' ); ?>
			<a href="<?php echo esc_url( rcp_get_memberships_admin_page( array( 'view' => 'add' ) ) ); ?>" class="page-title-action"><?php _e( 'Add Membership', 'rcp' ); ?></a>
		</h1>
		<form method="get">
			<input type="hidden" name="page" value="rcp-members" />
			<?php $table->views(); ?>
			<?php $table->search_box( __( 'Search', 'rcp' ), 'rcp-membership-search' ); ?>
			<?php $table->display(); ?>
		</form>
	</div>
	<?php
}

function smm_render_add_membership() {
	?>
	<div class="wrap">
		<h1><?php _e( 'Add Membership', 'rcp' ); ?></h1>
		<?php
		if ( ! empty( $_GET['smm_error'] ) ) :
			$error_msg = '';
			switch ( $_GET['smm_error'] ) {
				case 'invalid_email':
					$error_msg = __( 'Please enter a valid customer email address.', 'rcp' );
					break;
				case 'invalid_level':
					$error_msg = __( 'Please select a valid membership level.', 'rcp' );
					break;
			}
			if ( $error_msg ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error_msg ); ?></p></div>
			<?php endif;
		endif;
		?>
		<form method="POST" action="" id="smm-add-membership-form">
			<table class="widefat striped">
				<tbody>
				<tr>
					<th scope="row"><label for="rcp-customer-email"><?php _e( 'Customer Email:', 'rcp' ); ?></label></th>
					<td>
						<?php
						if ( ! empty( $_GET['customer_id'] ) ) :
							$customer = rcp_get_customer( absint( $_GET['customer_id'] ) );
							if ( $customer ) :
								$user = get_userdata( $customer->get_user_id() );
								?>
								<input type="hidden" name="customer_id" value="<?php echo esc_attr( $customer->get_id() ); ?>"/>
								<strong><?php echo esc_html( $user ? $user->display_name : '' ); ?></strong> (<?php echo esc_html( $user ? $user->user_email : '' ); ?>)
								<?php
							endif;
						else : ?>
							<input type="text" id="rcp-customer-email" name="user_email" placeholder="<?php esc_attr_e( 'Email of existing user', 'rcp' ); ?>" value="<?php echo ! empty( $_GET['email'] ) ? esc_attr( rawurldecode( $_GET['email'] ) ) : ''; ?>" class="regular-text" required />
							<p class="description"><?php _e( 'Enter the email of an existing user. A customer record will be created automatically.', 'rcp' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-membership-level"><?php _e( 'Membership Level:', 'rcp' ); ?></label></th>
					<td>
						<?php
						$levels = rcp_get_membership_levels( array( 'number' => 999 ) );
						if ( $levels ) {
							echo '<select id="rcp-membership-level" name="object_id">';
							foreach ( $levels as $level ) {
								echo '<option value="' . esc_attr( $level->get_id() ) . '">' . esc_html( $level->get_name() ) . '</option>';
							}
							echo '</select>';
						} else {
							_e( 'No levels found', 'rcp' );
						}
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-status"><?php _e( 'Membership Status:', 'rcp' ); ?></label></th>
					<td>
						<select name="status" id="rcp-status">
							<?php
							$statuses = array( 'active', 'expired', 'cancelled', 'pending' );
							foreach ( $statuses as $status ) {
								echo '<option value="' . esc_attr( $status ) . '">' . rcp_get_status_label( $status ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-membership-created"><?php _e( 'Date Created:', 'rcp' ); ?></label></th>
					<td><input type="text" id="rcp-membership-created" name="created_date" value="<?php echo esc_attr( date( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-membership-expiration"><?php _e( 'Expiration Date:', 'rcp' ); ?></label></th>
					<td>
						<input type="text" id="rcp-membership-expiration" name="expiration_date" value="" placeholder="YYYY-MM-DD" />
						<label><input type="checkbox" name="expiration_date_none" value="1"/> <?php _e( 'Never expires', 'rcp' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-recurring"><?php _e( 'Auto Renew:', 'rcp' ); ?></label></th>
					<td><input type="checkbox" name="auto_renew" id="rcp-recurring" value="1"/></td>
				</tr>
				</tbody>
			</table>
			<p>
				<input type="hidden" name="rcp-action" value="add_membership"/>
				<?php wp_nonce_field( 'rcp_add_membership', 'rcp_add_membership_nonce' ); ?>
				<input type="submit" class="button button-primary" value="<?php _e( 'Add Membership', 'rcp' ); ?>"/>
			</p>
		</form>
	</div>

	<style>
		/* jQuery UI Autocomplete dropdown */
		.ui-autocomplete {
			max-height: 250px;
			overflow-y: auto;
			overflow-x: hidden;
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 4px;
			box-shadow: 0 2px 8px rgba(0,0,0,.12);
			z-index: 100100;
		}
		.ui-menu-item-wrapper {
			padding: 6px 12px;
			cursor: pointer;
		}
		.ui-menu-item-wrapper.ui-state-active,
		.ui-menu-item-wrapper:hover {
			background: #2271b1;
			color: #fff;
		}
		/* Validation error state */
		.smm-field-error {
			border-color: #d63638 !important;
			box-shadow: 0 0 0 1px #d63638 !important;
		}
		.smm-error-message {
			color: #d63638;
			font-weight: 600;
			margin-top: 4px;
		}
	</style>

	<script>
	jQuery(document).ready(function($) {
		var $emailField = $('#rcp-customer-email');

		/* ---- Autocomplete ---- */
		if ($emailField.length && typeof smm_admin !== 'undefined') {
			$emailField.autocomplete({
				source: function(request, response) {
					$.getJSON(smm_admin.ajax_url, {
						action: 'smm_search_users',
						nonce:  smm_admin.nonce,
						term:   request.term
					}, response);
				},
				minLength: 2,
				select: function(event, ui) {
					$emailField.val(ui.item.value);
					// Clear any previous error state on selection.
					$emailField.removeClass('smm-field-error');
					$emailField.next('.smm-error-message').remove();
					return false;
				}
			});
		}

		/* ---- Client-side validation ---- */
		$('#smm-add-membership-form').on('submit', function(e) {
			if (!$emailField.length) {
				return; // Customer already set via customer_id.
			}

			var val = $.trim($emailField.val());
			$emailField.removeClass('smm-field-error');
			$emailField.next('.smm-error-message').remove();

			if (!val) {
				e.preventDefault();
				$emailField.addClass('smm-field-error').focus();
				$emailField.after('<p class="smm-error-message"><?php echo esc_js( __( 'Please enter a customer email address.', 'rcp' ) ); ?></p>');
				return false;
			}

			// Basic email format check.
			if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
				e.preventDefault();
				$emailField.addClass('smm-field-error').focus();
				$emailField.after('<p class="smm-error-message"><?php echo esc_js( __( 'Please enter a valid email address.', 'rcp' ) ); ?></p>');
				return false;
			}
		});

		/* Clear error on typing */
		$emailField.on('input', function() {
			$(this).removeClass('smm-field-error');
			$(this).next('.smm-error-message').remove();
		});
	});
	</script>
	<?php
}


function smm_render_edit_membership( $membership_id ) {
	$membership = rcp_get_membership( $membership_id );
	if ( ! $membership ) {
		wp_die( __( 'Invalid membership.', 'rcp' ) );
	}
	$customer = $membership->get_customer();
	$user = $customer ? get_userdata( $customer->get_user_id() ) : false;
	?>
	<div class="wrap">
		<h1><?php _e( 'Edit Membership', 'rcp' ); ?> #<?php echo $membership->get_id(); ?></h1>
		<form method="POST" action="">
			<table class="widefat striped">
				<tbody>
				<tr>
					<th scope="row"><?php _e( 'Customer:', 'rcp' ); ?></th>
					<td>
						<a href="<?php echo esc_url( rcp_get_customers_admin_page( array( 'customer_id' => $membership->get_customer_id(), 'view' => 'edit' ) ) ); ?>"><?php echo esc_html( $user ? $user->display_name : '#' . $membership->get_customer_id() ); ?></a>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-membership-level"><?php _e( 'Membership Level:', 'rcp' ); ?></label></th>
					<td>
						<?php
						$levels = rcp_get_membership_levels( array( 'number' => 999 ) );
						echo '<select id="rcp-membership-level" name="object_id">';
						foreach ( $levels as $level ) {
							echo '<option value="' . esc_attr( $level->get_id() ) . '"' . selected( $level->get_id(), $membership->get_object_id(), false ) . '>' . esc_html( $level->get_name() ) . '</option>';
						}
						echo '</select>';
						?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-status"><?php _e( 'Status:', 'rcp' ); ?></label></th>
					<td>
						<select name="status" id="rcp-status">
							<?php
							$statuses = array( 'active', 'expired', 'cancelled', 'pending' );
							foreach ( $statuses as $status ) {
								echo '<option value="' . esc_attr( $status ) . '"' . selected( $status, $membership->get_status(), false ) . '>' . rcp_get_status_label( $status ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-membership-created"><?php _e( 'Date Created:', 'rcp' ); ?></label></th>
					<td><input type="text" id="rcp-membership-created" name="created_date" value="<?php echo esc_attr( $membership->get_created_date( false ) ); ?>"/></td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-membership-expiration"><?php _e( 'Expiration Date:', 'rcp' ); ?></label></th>
					<td>
						<input type="text" id="rcp-membership-expiration" name="expiration_date" value="<?php echo esc_attr( $membership->get_expiration_date( false ) === 'none' ? '' : $membership->get_expiration_date( false ) ); ?>" placeholder="YYYY-MM-DD" />
						<label><input type="checkbox" name="expiration_date_none" value="1" <?php checked( 'none', $membership->get_expiration_date( false ) ); ?>/> <?php _e( 'Never expires', 'rcp' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="rcp-recurring"><?php _e( 'Auto Renew:', 'rcp' ); ?></label></th>
					<td><input type="checkbox" name="auto_renew" id="rcp-recurring" value="1" <?php checked( $membership->is_recurring() ); ?>/></td>
				</tr>
				</tbody>
			</table>
			<p>
				<input type="hidden" name="membership_id" value="<?php echo esc_attr( $membership->get_id() ); ?>"/>
				<input type="hidden" name="rcp-action" value="edit_membership"/>
				<?php wp_nonce_field( 'rcp_edit_membership', 'rcp_edit_membership_nonce' ); ?>
				<input type="submit" class="button button-primary" value="<?php _e( 'Update Membership', 'rcp' ); ?>"/>
				<input type="submit" class="button" name="rcp_delete_membership" value="<?php _e( 'Delete (Disable)', 'rcp' ); ?>" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to disable this membership?', 'rcp' ); ?>');"/>
			</p>
		</form>
		<h3><?php _e( 'Notes', 'rcp' ); ?></h3>
		<form method="POST" action="">
			<textarea name="new_note" rows="3" class="large-text"></textarea>
			<p>
				<input type="hidden" name="membership_id" value="<?php echo esc_attr( $membership->get_id() ); ?>"/>
				<input type="hidden" name="rcp-action" value="add_membership_note"/>
				<?php wp_nonce_field( 'rcp_add_membership_note', 'rcp_add_membership_note_nonce' ); ?>
				<input type="submit" class="button" value="<?php _e( 'Add Note', 'rcp' ); ?>"/>
			</p>
		</form>
		<?php $notes = $membership->get_notes(); ?>
		<?php if ( $notes ) : ?>
			<div class="smm-notes">
				<?php echo nl2br( esc_html( $notes ) ); ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/* --------------------------------------------------------------------- *
 * Membership Actions
 * --------------------------------------------------------------------- */

function smm_process_add_membership() {
	if ( ! wp_verify_nonce( $_POST['rcp_add_membership_nonce'], 'rcp_add_membership' ) ) {
		wp_die( __( 'Nonce verification failed.', 'rcp' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}

	$membership_level = rcp_get_membership_level( absint( $_POST['object_id'] ) );
	if ( ! $membership_level instanceof \RCP\Membership_Level ) {
		wp_safe_redirect( rcp_get_memberships_admin_page( array( 'view' => 'add', 'smm_error' => 'invalid_level' ) ) );
		exit;
	}

	if ( ! empty( $_POST['customer_id'] ) ) {
		$customer = rcp_get_customer( absint( $_POST['customer_id'] ) );
	} else {
		$customer_email = ! empty( $_POST['user_email'] ) ? sanitize_email( $_POST['user_email'] ) : false;
		if ( empty( $customer_email ) ) {
			wp_safe_redirect( rcp_get_memberships_admin_page( array( 'view' => 'add', 'smm_error' => 'invalid_email' ) ) );
			exit;
		}
		$user = get_user_by( 'email', $customer_email );
		if ( empty( $user ) ) {
			$login_base = sanitize_user( strstr( $customer_email, '@', true ), true );
			if ( empty( $login_base ) ) {
				$login_base = 'user';
			}
			$user_login = $login_base;
			$suffix     = 1;
			while ( username_exists( $user_login ) ) {
				$user_login = $login_base . $suffix;
				$suffix++;
			}
			$user_id = wp_insert_user( array(
				'user_login' => $user_login,
				'user_email' => $customer_email,
				'user_pass'  => wp_generate_password(),
			) );
			if ( is_wp_error( $user_id ) ) {
				wp_die( __( 'Error creating user account.', 'rcp' ) );
			}
			$user = get_userdata( $user_id );
		}
		$customer = rcp_get_customer_by_user_id( $user->ID );
		if ( empty( $customer ) ) {
			$customer_id = rcp_add_customer( array( 'user_id' => $user->ID ) );
			$customer = rcp_get_customer( $customer_id );
		}
	}

	if ( ! is_object( $customer ) ) {
		wp_die( __( 'Error locating customer.', 'rcp' ) );
	}

	$expiration_date = ! empty( $_POST['expiration_date_none'] ) ? 'none' : ( ! empty( $_POST['expiration_date'] ) ? date( 'Y-m-d 23:59:59', strtotime( $_POST['expiration_date'] ) ) : '' );

	$data = array(
		'object_id'        => absint( $_POST['object_id'] ),
		'object_type'      => 'membership',
		'initial_amount'   => $membership_level->get_fee() + $membership_level->get_price(),
		'recurring_amount' => $membership_level->get_price(),
		'created_date'     => ! empty( $_POST['created_date'] ) ? date( 'Y-m-d H:i:s', strtotime( $_POST['created_date'] ) ) : current_time( 'mysql' ),
		'expiration_date'  => $expiration_date,
		'auto_renew'       => ! empty( $_POST['auto_renew'] ) ? 1 : 0,
		'status'           => sanitize_text_field( $_POST['status'] ),
		'signup_method'    => 'manual',
	);

	$membership_id = $customer->add_membership( $data );
	if ( empty( $membership_id ) ) {
		wp_die( __( 'Error adding membership.', 'rcp' ) );
	}

	$membership = rcp_get_membership( $membership_id );
	if ( $membership ) {
		// Activation (role assignment + emails) for an 'active' status is handled by
		// rcp_activate_membership_on_insert() on the rcp_new_membership_added action.
		$membership->add_note( sprintf( __( 'Membership manually created by %s.', 'rcp' ), wp_get_current_user()->user_login ) );
	}

	wp_safe_redirect( rcp_get_memberships_admin_page( array( 'membership_id' => $membership_id, 'view' => 'edit', 'rcp_message' => 'membership_updated' ) ) );
	exit;
}
add_action( 'rcp_action_add_membership', 'smm_process_add_membership' );

function smm_process_edit_membership() {
	if ( ! wp_verify_nonce( $_POST['rcp_edit_membership_nonce'], 'rcp_edit_membership' ) ) {
		wp_die( __( 'Nonce verification failed.', 'rcp' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}

	$membership_id = absint( $_POST['membership_id'] );
	$membership = rcp_get_membership( $membership_id );
	if ( ! $membership ) {
		wp_die( __( 'Invalid membership.', 'rcp' ) );
	}

	if ( ! empty( $_POST['rcp_delete_membership'] ) ) {
		$membership->disable();
		wp_safe_redirect( rcp_get_memberships_admin_page( array( 'rcp_message' => 'membership_deleted' ) ) );
		exit;
	}

	$update = array();

	if ( isset( $_POST['object_id'] ) ) {
		$object_id = absint( $_POST['object_id'] );
		if ( $object_id && $object_id !== $membership->get_object_id() ) {
			$update['object_id'] = $object_id;
		}
	}

	if ( ! empty( $_POST['created_date'] ) ) {
		$created_date = date( 'Y-m-d H:i:s', strtotime( $_POST['created_date'] ) );
		if ( $created_date !== $membership->get_created_date( false ) ) {
			$update['created_date'] = $created_date;
		}
	}

	if ( ! empty( $update ) ) {
		$membership->update( $update );
	}

	$status = ! empty( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
	if ( ! empty( $status ) && $status !== $membership->get_status() ) {
		switch ( $status ) {
			case 'cancelled':
				$membership->cancel();
				break;
			case 'expired':
				$membership->expire();
				break;
			case 'active':
				$membership->activate();
				break;
			default:
				$membership->set_status( $status );
		}
	}

	$expiration_date = ! empty( $_POST['expiration_date_none'] ) ? 'none' : ( ! empty( $_POST['expiration_date'] ) ? date( 'Y-m-d 23:59:59', strtotime( $_POST['expiration_date'] ) ) : '' );
	if ( ! empty( $expiration_date ) && $expiration_date !== $membership->get_expiration_date( false ) ) {
		$membership->set_expiration_date( $expiration_date );
	}

	$membership->set_recurring( ! empty( $_POST['auto_renew'] ) );
	$membership->add_note( sprintf( __( 'Membership edited by %s.', 'rcp' ), wp_get_current_user()->user_login ) );

	wp_safe_redirect( rcp_get_memberships_admin_page( array( 'membership_id' => $membership_id, 'view' => 'edit', 'rcp_message' => 'membership_updated' ) ) );
	exit;
}
add_action( 'rcp_action_edit_membership', 'smm_process_edit_membership' );

function smm_process_add_membership_note() {
	if ( ! wp_verify_nonce( $_POST['rcp_add_membership_note_nonce'], 'rcp_add_membership_note' ) ) {
		wp_die( __( 'Nonce verification failed.', 'rcp' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}

	$membership_id = absint( $_POST['membership_id'] );
	$note = sanitize_text_field( wp_unslash( $_POST['new_note'] ) );
	if ( empty( $note ) ) {
		wp_die( __( 'Please enter a note.', 'rcp' ) );
	}

	$membership = rcp_get_membership( $membership_id );
	if ( $membership ) {
		$membership->add_note( $note );
	}

	wp_safe_redirect( rcp_get_memberships_admin_page( array( 'membership_id' => $membership_id, 'view' => 'edit', 'rcp_message' => 'membership_note_added' ) ) );
	exit;
}
add_action( 'rcp_action_add_membership_note', 'smm_process_add_membership_note' );

function smm_process_activate_membership() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'activate_membership' ) ) {
		wp_die( __( 'Nonce verification failed.', 'rcp' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}
	$membership = rcp_get_membership( absint( $_GET['membership_id'] ) );
	if ( ! $membership ) {
		wp_die( __( 'Invalid membership.', 'rcp' ) );
	}
	$membership->activate();
	wp_safe_redirect( rcp_get_memberships_admin_page( array( 'membership_id' => $membership->get_id(), 'view' => 'edit', 'rcp_message' => 'membership_activated' ) ) );
	exit;
}
add_action( 'rcp_action_activate_membership', 'smm_process_activate_membership' );

function smm_process_expire_membership() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'expire_membership' ) ) {
		wp_die( __( 'Nonce verification failed.', 'rcp' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}
	$membership = rcp_get_membership( absint( $_GET['membership_id'] ) );
	if ( ! $membership ) {
		wp_die( __( 'Invalid membership.', 'rcp' ) );
	}
	$membership->expire();
	wp_safe_redirect( rcp_get_memberships_admin_page( array( 'membership_id' => $membership->get_id(), 'view' => 'edit', 'rcp_message' => 'membership_expired' ) ) );
	exit;
}
add_action( 'rcp_action_expire_membership', 'smm_process_expire_membership' );

function smm_process_cancel_membership() {
	if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'cancel_membership' ) ) {
		wp_die( __( 'Nonce verification failed.', 'rcp' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}
	$membership = rcp_get_membership( absint( $_GET['membership_id'] ) );
	if ( ! $membership ) {
		wp_die( __( 'Invalid membership.', 'rcp' ) );
	}
	$membership->add_note( sprintf( __( 'Membership cancelled via admin by %s.', 'rcp' ), wp_get_current_user()->user_login ) );
	$membership->cancel();
	wp_safe_redirect( rcp_get_memberships_admin_page( array( 'membership_id' => $membership->get_id(), 'view' => 'edit', 'rcp_message' => 'membership_cancelled' ) ) );
	exit;
}
add_action( 'rcp_action_cancel_membership', 'smm_process_cancel_membership' );
