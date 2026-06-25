<?php
/**
 * Customers Admin Page
 *
 * List table and customer detail view.
 *
 * @package Simple_Membership_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class SMM_Customers_Table extends WP_List_Table {

	public function __construct() {
		parent::__construct( array(
			'singular' => 'customer',
			'plural'   => 'customers',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		return array(
			'id'       => __( 'ID', 'rcp' ),
			'user'     => __( 'User', 'rcp' ),
			'registered' => __( 'Date Registered', 'rcp' ),
			'memberships' => __( 'Memberships', 'rcp' ),
		);
	}

	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return $item->get_id();
			case 'user':
				$user = get_userdata( $item->get_user_id() );
				$name = $user ? $user->display_name : __( '(no user)', 'rcp' );
				$email = $user ? $user->user_email : '';
				return sprintf( '<a href="%s"><strong>%s</strong></a><br>%s', esc_url( rcp_get_customers_admin_page( array( 'customer_id' => $item->get_id(), 'view' => 'edit' ) ) ), esc_html( $name ), esc_html( $email ) );
			case 'registered':
				return esc_html( $item->get_date_registered() );
			case 'memberships':
				$count = count( $item->get_memberships() );
				return sprintf( '<a href="%s">%d</a>', esc_url( rcp_get_memberships_admin_page( array( 'customer_id' => $item->get_id() ) ) ), $count );
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

		$args = array(
			'number' => $per_page,
			'offset' => $offset,
		);

		$customers = rcp_get_customers( $args );
		$total = rcp_count_customers();

		$this->items = $customers;
		$this->set_pagination_args( array(
			'total_items' => $total,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		) );
	}
}

function rcp_customers_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have permission.', 'rcp' ) );
	}

	$view = isset( $_GET['view'] ) ? sanitize_text_field( $_GET['view'] ) : '';

	if ( 'edit' === $view && isset( $_GET['customer_id'] ) ) {
		smm_render_customer_detail( absint( $_GET['customer_id'] ) );
		return;
	}

	$table = new SMM_Customers_Table();
	$table->prepare_items();
	?>
	<div class="wrap">
		<h1><?php _e( 'Customers', 'rcp' ); ?></h1>
		<form method="get">
			<input type="hidden" name="page" value="rcp-customers" />
			<?php $table->display(); ?>
		</form>
	</div>
	<?php
}

function smm_render_customer_detail( $customer_id ) {
	$customer = rcp_get_customer( $customer_id );
	if ( ! $customer ) {
		wp_die( __( 'Invalid customer.', 'rcp' ) );
	}

	$user = get_userdata( $customer->get_user_id() );
	$memberships = $customer->get_memberships( array( 'disabled' => '' ) );
	?>
	<div class="wrap">
		<h1><?php _e( 'Customer', 'rcp' ); ?> #<?php echo $customer->get_id(); ?></h1>
		<div id="poststuff">
			<div class="postbox">
				<h2 class="hndle"><span><?php _e( 'Customer Details', 'rcp' ); ?></span></h2>
				<div class="inside">
					<table class="widefat">
						<tbody>
						<tr>
							<th><?php _e( 'User:', 'rcp' ); ?></th>
							<td><?php echo esc_html( $user ? $user->display_name . ' (' . $user->user_email . ')' : '(no user)' ); ?></td>
						</tr>
						<tr>
							<th><?php _e( 'Date Registered:', 'rcp' ); ?></th>
							<td><?php echo esc_html( $customer->get_date_registered() ); ?></td>
						</tr>
						<tr>
							<th><?php _e( 'Email Verification:', 'rcp' ); ?></th>
							<td><?php echo esc_html( ucfirst( $customer->get_email_verification_status() ) ); ?></td>
						</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="postbox">
				<h2 class="hndle"><span><?php _e( 'Memberships', 'rcp' ); ?> (<?php echo count( $memberships ); ?>)</span></h2>
				<div class="inside">
					<?php if ( ! empty( $memberships ) ) : ?>
						<table class="widefat striped">
							<thead>
							<tr>
								<th><?php _e( 'ID', 'rcp' ); ?></th>
								<th><?php _e( 'Level', 'rcp' ); ?></th>
								<th><?php _e( 'Status', 'rcp' ); ?></th>
								<th><?php _e( 'Expiration', 'rcp' ); ?></th>
								<th><?php _e( 'Actions', 'rcp' ); ?></th>
							</tr>
							</thead>
							<tbody>
							<?php foreach ( $memberships as $membership ) : ?>
								<tr>
									<td><?php echo $membership->get_id(); ?></td>
									<td><?php echo esc_html( $membership->get_membership_level_name() ); ?></td>
									<td><?php echo esc_html( rcp_get_status_label( $membership->get_status() ) ); ?></td>
									<td><?php echo 'none' === $membership->get_expiration_date() ? __( 'Never', 'rcp' ) : esc_html( $membership->get_expiration_date() ); ?></td>
									<td>
										<a href="<?php echo esc_url( rcp_get_memberships_admin_page( array( 'membership_id' => $membership->get_id(), 'view' => 'edit' ) ) ); ?>"><?php _e( 'Edit', 'rcp' ); ?></a>
										<?php if ( ! $membership->is_active() ) : ?>
											| <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'rcp-action' => 'activate_membership', 'membership_id' => $membership->get_id() ), admin_url( 'admin.php' ) ), 'activate_membership' ) ); ?>"><?php _e( 'Activate', 'rcp' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p><?php _e( 'No memberships found.', 'rcp' ); ?></p>
					<?php endif; ?>
					<p>
						<a href="<?php echo esc_url( rcp_get_memberships_admin_page( array( 'view' => 'add', 'customer_id' => $customer->get_id() ) ) ); ?>" class="button button-primary"><?php _e( 'Add Membership', 'rcp' ); ?></a>
					</p>
				</div>
			</div>

			<?php $notes = $customer->get_notes(); ?>
			<?php if ( $notes ) : ?>
				<div class="postbox">
					<h2 class="hndle"><span><?php _e( 'Notes', 'rcp' ); ?></span></h2>
					<div class="inside">
						<div class="smm-notes"><?php echo nl2br( esc_html( $notes ) ); ?></div>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
