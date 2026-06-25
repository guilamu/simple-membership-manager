<?php
/**
 * Email Notifications
 *
 * Simplified RCP_Emails class and email trigger functions.
 *
 * @package Simple_Membership_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* --------------------------------------------------------------------- *
 * RCP_Emails class
 * --------------------------------------------------------------------- */

class RCP_Emails {

	private $from_address;
	private $from_name;
	private $content_type;
	private $headers;
	private $html = true;
	private $template;
	private $heading = '';
	public $member_id;
	public $payment_id;
	public $membership;
	private $tags;

	public function __construct() {
		if ( 'none' === $this->get_template() ) {
			$this->html = false;
		}
		add_action( 'rcp_email_send_before', array( $this, 'send_before' ) );
		add_action( 'rcp_email_send_after', array( $this, 'send_after' ) );
	}

	public function __set( $key, $value ) {
		$this->$key = $value;
	}

	public function get_from_name() {
		global $rcp_options;
		if ( ! $this->from_name ) {
			$this->from_name = ! empty( $rcp_options['from_name'] ) ? sanitize_text_field( $rcp_options['from_name'] ) : get_option( 'blogname' );
		}
		return apply_filters( 'rcp_emails_from_name', wp_specialchars_decode( $this->from_name ), $this );
	}

	public function get_from_address() {
		global $rcp_options;
		if ( ! $this->from_address ) {
			$this->from_address = ! empty( $rcp_options['from_email'] ) ? sanitize_text_field( $rcp_options['from_email'] ) : get_option( 'admin_email' );
		}
		return apply_filters( 'rcp_emails_from_address', $this->from_address, $this );
	}

	public function get_content_type() {
		if ( ! $this->content_type && $this->html ) {
			$this->content_type = apply_filters( 'rcp_email_default_content_type', 'text/html', $this );
		} elseif ( ! $this->html ) {
			$this->content_type = 'text/plain';
		}
		return apply_filters( 'rcp_email_content_type', $this->content_type, $this );
	}

	public function get_user_id() {
		return absint( $this->member_id );
	}

	public function get_membership() {
		return $this->membership;
	}

	public function get_payment_id() {
		return ! empty( $this->payment_id ) ? absint( $this->payment_id ) : false;
	}

	public function get_headers() {
		if ( ! $this->headers ) {
			$this->headers  = "From: {$this->get_from_name()} <{$this->get_from_address()}>\r\n";
			$this->headers .= "Reply-To: {$this->get_from_address()}\r\n";
			$this->headers .= "Content-Type: {$this->get_content_type()}; charset=utf-8\r\n";
		}
		return apply_filters( 'rcp_email_headers', $this->headers, $this );
	}

	public function get_template() {
		if ( ! $this->template ) {
			global $rcp_options;
			$this->template = ! empty( $rcp_options['email_template'] ) ? sanitize_text_field( $rcp_options['email_template'] ) : 'default';
		}
		return apply_filters( 'rcp_email_template', $this->template );
	}

	public function get_heading() {
		if ( ! $this->heading ) {
			global $rcp_options;
			$this->heading = ! empty( $rcp_options['email_header_text'] ) ? sanitize_text_field( $rcp_options['email_header_text'] ) : __( 'Hello', 'rcp' );
		}
		return apply_filters( 'rcp_email_heading', $this->heading );
	}

	public function build_email( $message ) {
		$message = $this->parse_tags( $message );
		if ( false === $this->html ) {
			return apply_filters( 'rcp_email_message', wp_strip_all_tags( $message ), $this );
		}
		$message   = $this->text_to_html( $message );
		$site_name = get_bloginfo( 'name' );
		$site_url  = home_url();

		$body  = '<!DOCTYPE html>';
		$body .= '<html lang="en">';
		$body .= '<head>';
		$body .= '<meta charset="utf-8">';
		$body .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
		$body .= '<title>' . esc_html( $site_name ) . '</title>';
		$body .= '<style type="text/css">';
		$body .= 'a { color: #ce000c; }';
		$body .= 'p { margin: 0 0 16px 0; }';
		$body .= 'p:last-child { margin-bottom: 0; }';
		$body .= '</style>';
		$body .= '</head>';
		$body .= '<body style="margin: 0; padding: 0; background-color: #f4f6f9; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">';

		// Outer wrapper table
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f4f6f9;">';
		$body .= '<tr><td style="padding: 40px 20px;" align="center">';

		// Card table
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width: 600px;">';

		// Header
		$body .= '<tr>';
		$body .= '<td style="background-color: #ce000c; border-radius: 8px 8px 0 0; padding: 32px 40px; text-align: center;">';
		$body .= '<h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; letter-spacing: -0.3px; line-height: 1.3;">';
		$body .= esc_html( $this->get_heading() );
		$body .= '</h1>';
		$body .= '</td>';
		$body .= '</tr>';

		// Body
		$body .= '<tr>';
		$body .= '<td style="background-color: #ffffff; padding: 40px; color: #374151; font-size: 16px; line-height: 1.7;">';
		$body .= '{email}';
		$body .= '</td>';
		$body .= '</tr>';

		// Footer
		$body .= '<tr>';
		$body .= '<td style="background-color: #f9fafb; border-top: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; padding: 20px 40px; text-align: center;">';
		$body .= '<p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px;">';
		$body .= '&copy; ' . esc_html( date( 'Y' ) ) . ' <a href="' . esc_url( $site_url ) . '" style="color: #ce000c; text-decoration: none; font-weight: 500;">' . esc_html( $site_name ) . '</a>';
		$body .= '</p>';
		$body .= '<p style="margin: 4px 0 0 0; color: #9ca3af; font-size: 12px;">You are receiving this email as a member of ' . esc_html( $site_name ) . '.</p>';
		$body .= '</td>';
		$body .= '</tr>';

		$body .= '</table>'; // end card
		$body .= '</td></tr>';
		$body .= '</table>'; // end outer wrapper
		$body .= '</body></html>';

		$body = str_replace( '{email}', $message, $body );
		return apply_filters( 'rcp_email_message', $body, $this );
	}

	public function send( $to, $subject, $message, $attachments = '' ) {
		if ( defined( 'RCP_DISABLE_EMAILS' ) && RCP_DISABLE_EMAILS ) {
			return true;
		}
		$this->setup_email_tags();
		do_action( 'rcp_email_send_before', $this );
		$subject = $this->parse_tags( $subject );
		$message = $this->build_email( $message );
		$attachments = apply_filters( 'rcp_email_attachments', $attachments, $this );
		$sent = wp_mail( $to, $subject, $message, $this->get_headers(), $attachments );
		do_action( 'rcp_email_send_after', $this );
		return $sent;
	}

	public function send_before() {
		add_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		add_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );
	}

	public function send_after() {
		remove_filter( 'wp_mail_from', array( $this, 'get_from_address' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'get_from_name' ) );
		remove_filter( 'wp_mail_content_type', array( $this, 'get_content_type' ) );
		$this->heading = '';
	}

	public function text_to_html( $message ) {
		if ( 'text/html' === $this->content_type || true === $this->html ) {
			$message = wpautop( make_clickable( $message ) );
			$message = str_replace( '&#038;', '&amp;', $message );
		}
		return $message;
	}

	private function parse_tags( $content ) {
		if ( empty( $this->tags ) || ! is_array( $this->tags ) ) {
			return $content;
		}
		$new_content = preg_replace_callback( "/%([A-z0-9\-\_]+)%/s", array( $this, 'do_tag' ), $content );
		$new_content = apply_filters( 'rcp_email_tags', $new_content, $this->member_id );
		return $new_content;
	}

	private function setup_email_tags() {
		$tags = $this->get_tags();
		foreach ( $tags as $tag ) {
			if ( isset( $tag['function'] ) && is_callable( $tag['function'] ) ) {
				$this->tags[ $tag['tag'] ] = $tag;
			}
		}
	}

	public function get_tags() {
		$tags = array(
			array( 'tag' => 'name', 'description' => __( 'Member first and last name', 'rcp' ), 'function' => 'rcp_email_tag_name' ),
			array( 'tag' => 'username', 'description' => __( 'Member username', 'rcp' ), 'function' => 'rcp_email_tag_user_name' ),
			array( 'tag' => 'useremail', 'description' => __( 'Member email', 'rcp' ), 'function' => 'rcp_email_tag_user_email' ),
			array( 'tag' => 'firstname', 'description' => __( 'Member first name', 'rcp' ), 'function' => 'rcp_email_tag_first_name' ),
			array( 'tag' => 'lastname', 'description' => __( 'Member last name', 'rcp' ), 'function' => 'rcp_email_tag_last_name' ),
			array( 'tag' => 'displayname', 'description' => __( 'Member display name', 'rcp' ), 'function' => 'rcp_email_tag_display_name' ),
			array( 'tag' => 'expiration', 'description' => __( 'Membership expiration date', 'rcp' ), 'function' => 'rcp_email_tag_expiration' ),
			array( 'tag' => 'subscription_name', 'description' => __( 'Membership level name', 'rcp' ), 'function' => 'rcp_email_tag_subscription_name' ),
			array( 'tag' => 'subscription_key', 'description' => __( 'Subscription key', 'rcp' ), 'function' => 'rcp_email_tag_subscription_key' ),
			array( 'tag' => 'member_id', 'description' => __( 'Member ID', 'rcp' ), 'function' => 'rcp_email_tag_member_id' ),
			array( 'tag' => 'blogname', 'description' => __( 'Site name', 'rcp' ), 'function' => 'rcp_email_tag_site_name' ),
		);
		return apply_filters( 'rcp_email_template_tags', $tags );
	}

	private function do_tag( $m ) {
		$tag = $m[1];
		if ( ! $this->email_tag_exists( $tag ) ) {
			return $m[0];
		}
		return call_user_func( $this->tags[ $tag ]['function'], $this->member_id, $this->payment_id, $tag, $this->membership );
	}

	public function email_tag_exists( $tag ) {
		return array_key_exists( $tag, $this->tags );
	}
}

/* --------------------------------------------------------------------- *
 * Email Tag Callback Functions
 * --------------------------------------------------------------------- */

function rcp_email_tag_name( $user_id ) {
	$user_info = get_userdata( $user_id );
	if ( ! $user_info ) {
		return '';
	}
	$first = $user_info->first_name;
	$last  = $user_info->last_name;
	if ( '' !== $first && '' !== $last ) {
		return $first . ' ' . $last;
	}
	return $user_info->display_name;
}

function rcp_email_tag_user_name( $user_id ) {
	$user_info = get_userdata( $user_id );
	return $user_info ? $user_info->user_login : '';
}

function rcp_email_tag_user_email( $user_id ) {
	$user_info = get_userdata( $user_id );
	return $user_info ? $user_info->user_email : '';
}

function rcp_email_tag_first_name( $user_id ) {
	$user_info = get_userdata( $user_id );
	return $user_info ? $user_info->first_name : '';
}

function rcp_email_tag_last_name( $user_id ) {
	$user_info = get_userdata( $user_id );
	return $user_info ? $user_info->last_name : '';
}

function rcp_email_tag_display_name( $user_id ) {
	$user_info = get_userdata( $user_id );
	return $user_info ? $user_info->display_name : '';
}

function rcp_email_tag_expiration( $user_id, $payment_id = 0, $tag = '', $membership = null ) {
	if ( $membership instanceof RCP_Membership ) {
		return $membership->get_expiration_date();
	}
	$customer = rcp_get_customer_by_user_id( $user_id );
	if ( $customer ) {
		$membership = rcp_get_customer_single_membership( $customer->get_id() );
		if ( $membership ) {
			return $membership->get_expiration_date();
		}
	}
	return '';
}

function rcp_email_tag_subscription_name( $user_id, $payment_id = 0, $tag = '', $membership = null ) {
	if ( $membership instanceof RCP_Membership ) {
		return $membership->get_membership_level_name();
	}
	$customer = rcp_get_customer_by_user_id( $user_id );
	if ( $customer ) {
		$membership = rcp_get_customer_single_membership( $customer->get_id() );
		if ( $membership ) {
			return $membership->get_membership_level_name();
		}
	}
	return '';
}

function rcp_email_tag_subscription_key( $user_id, $payment_id = 0, $tag = '', $membership = null ) {
	if ( $membership instanceof RCP_Membership ) {
		return $membership->get_subscription_key();
	}
	return '';
}

function rcp_email_tag_member_id( $user_id ) {
	return absint( $user_id );
}

function rcp_email_tag_site_name() {
	return get_bloginfo( 'name' );
}

/* --------------------------------------------------------------------- *
 * Email Trigger Functions
 * --------------------------------------------------------------------- */

function rcp_send_membership_email( $membership_id_or_object, $status = '' ) {
	if ( ! is_object( $membership_id_or_object ) ) {
		$membership = rcp_get_membership( $membership_id_or_object );
	} else {
		$membership = $membership_id_or_object;
	}

	if ( empty( $membership ) || ! is_a( $membership, 'RCP_Membership' ) || 0 === $membership->get_id() ) {
		return;
	}

	if ( empty( $status ) ) {
		$status = $membership->get_status();
	}

	global $rcp_options;

	$user_id   = $membership->get_user_id();
	$user_info = get_userdata( $user_id );
	if ( ! $user_info ) {
		return;
	}

	$message = '';
	$subject = '';
	$admin_subject = '';
	$admin_message = '';
	$site_name = stripslashes_deep( html_entity_decode( get_bloginfo( 'name' ), ENT_COMPAT, 'UTF-8' ) );

	$admin_emails = ! empty( $rcp_options['admin_notice_emails'] ) ? $rcp_options['admin_notice_emails'] : get_option( 'admin_email' );
	$admin_emails = apply_filters( 'rcp_admin_notice_emails', explode( ',', $admin_emails ) );
	$admin_emails = array_map( 'sanitize_email', $admin_emails );

	$attachments = apply_filters( 'rcp_email_attachments', array(), $user_id, $status, $membership );

	$emails = new RCP_Emails();
	$emails->member_id  = $user_id;
	$emails->membership = $membership;

	switch ( $status ) {
		case 'active':
			if ( ! empty( $rcp_options['disable_active_email'] ) ) {
				break;
			}
			$subject = ! empty( $rcp_options['active_subject'] ) ? $rcp_options['active_subject'] : sprintf( __( 'Your %s membership has been activated', 'rcp' ), $site_name );
			$message = ! empty( $rcp_options['active_email'] ) ? $rcp_options['active_email'] : sprintf( __( 'Your %s membership has been activated.', 'rcp' ), '%subscription_name%' );

			$subject        = apply_filters( 'rcp_subscription_active_subject', $subject, $user_id );
			$message        = apply_filters( 'rcp_subscription_active_email', $message, $user_id );

			if ( empty( $rcp_options['disable_new_user_notices'] ) ) {
				$admin_subject = ! empty( $rcp_options['active_subject_admin'] ) ? $rcp_options['active_subject_admin'] : sprintf( __( 'New membership on %s', 'rcp' ), $site_name );
				$admin_message = ! empty( $rcp_options['active_email_admin'] ) ? $rcp_options['active_email_admin'] : '';
				if ( empty( $admin_message ) ) {
					$admin_message = sprintf( __( '%s (%s) is now a member of %s', 'rcp' ), '%displayname%', '%username%', $site_name ) . ".\n\n";
					$admin_message .= sprintf( __( 'Membership level: %s', 'rcp' ), '%subscription_name%' ) . "\n\n";
					$admin_message .= __( 'Thank you', 'rcp' );
				}
			}
			break;

		case 'free':
			if ( ! empty( $rcp_options['disable_free_email'] ) ) {
				break;
			}
			$subject = ! empty( $rcp_options['free_subject'] ) ? $rcp_options['free_subject'] : sprintf( __( 'Your %s membership has been activated', 'rcp' ), $site_name );
			$message = ! empty( $rcp_options['free_email'] ) ? $rcp_options['free_email'] : sprintf( __( 'Your %s membership has been activated.', 'rcp' ), '%subscription_name%' );

			$subject = apply_filters( 'rcp_subscription_free_subject', $subject, $user_id );
			$message = apply_filters( 'rcp_subscription_free_email', $message, $user_id );

			if ( empty( $rcp_options['disable_new_user_notices'] ) ) {
				$admin_subject = ! empty( $rcp_options['free_subject_admin'] ) ? $rcp_options['free_subject_admin'] : sprintf( __( 'New free membership on %s', 'rcp' ), $site_name );
				$admin_message = ! empty( $rcp_options['free_email_admin'] ) ? $rcp_options['free_email_admin'] : '';
				if ( empty( $admin_message ) ) {
					$admin_message = sprintf( __( '%s (%s) is now a member of %s', 'rcp' ), '%displayname%', '%username%', $site_name ) . ".\n\n";
					$admin_message .= sprintf( __( 'Membership level: %s', 'rcp' ), '%subscription_name%' ) . "\n\n";
					$admin_message .= __( 'Thank you', 'rcp' );
				}
			}
			break;

		case 'trial':
			if ( ! empty( $rcp_options['disable_trial_email'] ) ) {
				break;
			}
			$subject = ! empty( $rcp_options['trial_subject'] ) ? $rcp_options['trial_subject'] : sprintf( __( 'Your %s membership has been activated', 'rcp' ), $site_name );
			$message = ! empty( $rcp_options['trial_email'] ) ? $rcp_options['trial_email'] : sprintf( __( 'Your %s membership has been activated.', 'rcp' ), '%subscription_name%' );

			$subject = apply_filters( 'rcp_subscription_trial_subject', $subject, $user_id );
			$message = apply_filters( 'rcp_subscription_trial_email', $message, $user_id );

			if ( empty( $rcp_options['disable_new_user_notices'] ) ) {
				$admin_subject = ! empty( $rcp_options['trial_subject_admin'] ) ? $rcp_options['trial_subject_admin'] : sprintf( __( 'New trial membership on %s', 'rcp' ), $site_name );
				$admin_message = ! empty( $rcp_options['trial_email_admin'] ) ? $rcp_options['trial_email_admin'] : '';
				if ( empty( $admin_message ) ) {
					$admin_message = sprintf( __( '%s (%s) is now a member of %s', 'rcp' ), '%displayname%', '%username%', $site_name ) . ".\n\n";
					$admin_message .= sprintf( __( 'Membership level: %s', 'rcp' ), '%subscription_name%' ) . "\n\n";
					$admin_message .= __( 'Thank you', 'rcp' );
				}
			}
			break;

		case 'cancelled':
			if ( ! empty( $rcp_options['disable_cancelled_email'] ) ) {
				break;
			}
			$subject = ! empty( $rcp_options['cancelled_subject'] ) ? $rcp_options['cancelled_subject'] : sprintf( __( 'Your %s membership has been cancelled', 'rcp' ), $site_name );
			$message = ! empty( $rcp_options['cancelled_email'] ) ? $rcp_options['cancelled_email'] : sprintf( __( 'Your %s membership has been cancelled. You will retain access to content until %s.', 'rcp' ), '%subscription_name%', '%expiration%' );

			$subject = apply_filters( 'rcp_subscription_cancelled_subject', $subject, $user_id );
			$message = apply_filters( 'rcp_subscription_cancelled_email', $message, $user_id );

			if ( empty( $rcp_options['disable_new_user_notices'] ) ) {
				$admin_subject = ! empty( $rcp_options['cancelled_subject_admin'] ) ? $rcp_options['cancelled_subject_admin'] : sprintf( __( 'Cancelled membership on %s', 'rcp' ), $site_name );
				$admin_message = ! empty( $rcp_options['cancelled_email_admin'] ) ? $rcp_options['cancelled_email_admin'] : '';
				if ( empty( $admin_message ) ) {
					$admin_message = sprintf( __( '%s (%s) has cancelled their membership to %s', 'rcp' ), '%displayname%', '%username%', $site_name ) . ".\n\n";
					$admin_message .= sprintf( __( 'Their membership level was: %s', 'rcp' ), '%subscription_name%' ) . "\n\n";
					$admin_message .= sprintf( __( 'They will retain access until: %s', 'rcp' ), '%expiration%' ) . "\n\n";
					$admin_message .= __( 'Thank you', 'rcp' );
				}
			}
			break;

		case 'expired':
			if ( ! empty( $rcp_options['disable_expired_email'] ) ) {
				break;
			}
			$subject = ! empty( $rcp_options['expired_subject'] ) ? $rcp_options['expired_subject'] : sprintf( __( 'Your %s membership has expired', 'rcp' ), $site_name );
			$message = ! empty( $rcp_options['expired_email'] ) ? $rcp_options['expired_email'] : sprintf( __( 'Your %s membership has expired.', 'rcp' ), '%subscription_name%' );

			$subject = apply_filters( 'rcp_subscription_expired_subject', $subject, $user_id );
			$message = apply_filters( 'rcp_subscription_expired_email', $message, $user_id );

			if ( empty( $rcp_options['disable_new_user_notices'] ) ) {
				$admin_subject = ! empty( $rcp_options['expired_subject_admin'] ) ? $rcp_options['expired_subject_admin'] : sprintf( __( 'Expired membership on %s', 'rcp' ), $site_name );
				$admin_message = ! empty( $rcp_options['expired_email_admin'] ) ? $rcp_options['expired_email_admin'] : '';
				if ( empty( $admin_message ) ) {
					$admin_message = sprintf( __( '%s\'s (%s) membership has expired.', 'rcp' ), '%displayname%', '%username%' ) . ".\n\n";
					$admin_message .= sprintf( __( 'Their membership level was: %s', 'rcp' ), '%subscription_name%' ) . "\n\n";
					$admin_message .= __( 'Thank you', 'rcp' );
				}
			}

			if ( $user_id ) {
				add_user_meta( $user_id, '_rcp_expired_email_sent', 'yes' );
			}
			break;
	}

	if ( ! empty( $message ) ) {
		$emails->send( $user_info->user_email, $subject, $message, $attachments );
	}

	if ( ! empty( $admin_message ) ) {
		$emails->send( $admin_emails, $admin_subject, $admin_message );
	}
}

function rcp_email_on_membership_activation( $membership_id, $membership ) {
	if ( $membership->is_trialing() ) {
		rcp_send_membership_email( $membership, 'trial' );
	} elseif ( ! $membership->is_paid() ) {
		rcp_send_membership_email( $membership, 'free' );
	} else {
		rcp_send_membership_email( $membership, 'active' );
	}
}
add_action( 'rcp_membership_post_activate', 'rcp_email_on_membership_activation', 10, 2 );

function rcp_email_on_membership_cancellation( $membership_id, $membership ) {
	if ( ! $membership->is_disabled() && ! $membership->was_upgraded() ) {
		rcp_send_membership_email( $membership, 'cancelled' );
	}
}
add_action( 'rcp_membership_post_cancel', 'rcp_email_on_membership_cancellation', 10, 2 );

function rcp_email_on_membership_expiration( $old_status, $membership_id ) {
	if ( 'expired' === $old_status || 'new' === $old_status ) {
		return;
	}
	$membership = rcp_get_membership( $membership_id );
	rcp_send_membership_email( $membership, 'expired' );
}
add_action( 'rcp_transition_membership_status_expired', 'rcp_email_on_membership_expiration', 10, 2 );
