<?php
/**
 * Content Restriction
 *
 * Content filters, shortcodes, and the "Restrict this content" metabox.
 *
 * @package Simple_Membership_Manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* --------------------------------------------------------------------- *
 * Content Filters
 * --------------------------------------------------------------------- */

function rcp_filter_restricted_content( $content ) {
	global $post, $rcp_options;
	if ( $post && ! rcp_user_can_access( get_current_user_id(), $post->ID ) ) {
		$message = rcp_get_restricted_content_message();
		return rcp_format_teaser( $message );
	}
	return $content;
}
add_filter( 'the_content', 'rcp_filter_restricted_content', 100 );

function rcp_hide_comments( $template ) {
	$post_id = get_the_ID();
	if ( ! empty( $post_id ) ) {
		if ( ! rcp_user_can_access( get_current_user_id(), $post_id ) ) {
			$template = '';
		}
	}
	return $template;
}
add_filter( 'comments_template', 'rcp_hide_comments', 9999999 );

function rcp_restricted_message_filter( $message ) {
	return do_shortcode( wpautop( $message ) );
}
add_filter( 'rcp_restricted_message', 'rcp_restricted_message_filter', 10, 1 );

function rcp_restricted_message_pending_verification( $message ) {
	if ( rcp_is_pending_verification() ) {
		$message = '<div class="rcp_message error"><p class="rcp_error rcp_pending_member"><span>' . __( 'Your account is pending email verification.', 'rcp' ) . '</span></p></div>';
	}
	return $message;
}
add_filter( 'rcp_restricted_message', 'rcp_restricted_message_pending_verification', 9999 );

function rcp_post_password_required_rest_api( $required, $post ) {
	if ( $post !== null && defined( 'REST_REQUEST' ) && REST_REQUEST && rcp_is_restricted_content( $post->ID ) && ! rcp_user_can_access( get_current_user_id(), $post->ID ) ) {
		$required = true;
	}
	return $required;
}
add_filter( 'post_password_required', 'rcp_post_password_required_rest_api', 10, 2 );

/* --------------------------------------------------------------------- *
 * Shortcodes
 * --------------------------------------------------------------------- */

add_filter( 'rcp_restrict_shortcode_return', 'wpautop' );
add_filter( 'rcp_restrict_shortcode_return', 'do_shortcode' );
add_filter( 'widget_text', 'do_shortcode' );

function rcp_restrict_shortcode( $atts, $content = null ) {
	$atts = shortcode_atts(
		array(
			'userlevel'    => 'none',
			'message'      => '',
			'paid'         => false,
			'level'        => 0,
			'subscription' => '',
		),
		$atts,
		'restrict'
	);

	$atts['userlevel']    = sanitize_text_field( $atts['userlevel'] );
	$atts['subscription'] = sanitize_text_field( $atts['subscription'] );
	$atts['message']      = wp_kses_post( $atts['message'] );

	global $user_ID;

	if ( strlen( $atts['message'] ) > 0 ) {
		$teaser = $atts['message'];
	} else {
		$teaser = rcp_get_restricted_content_message( ! empty( $atts['paid'] ) );
	}

	$subscriptions = array_map( 'trim', explode( ',', $atts['subscription'] ) );
	$has_access = false;
	$classes = 'rcp_restricted';

	$customer         = rcp_get_customer();
	$is_active        = rcp_user_has_active_membership();
	$has_access_level = rcp_user_has_access( get_current_user_id(), $atts['level'] );

	if ( $atts['paid'] ) {
		if ( rcp_user_has_paid_membership() && $has_access_level ) {
			$has_access = true;
		}
		$classes = 'rcp_restricted rcp_paid_only';
	} elseif ( $has_access_level ) {
		$has_access = true;
	}

	if ( ! empty( $subscriptions ) && ! empty( $subscriptions[0] ) ) {
		if ( $is_active && ! empty( $customer ) && count( array_intersect( rcp_get_customer_membership_level_ids( $customer->get_id() ), $subscriptions ) ) ) {
			$has_access = true;
		} else {
			$has_access = false;
		}
	}

	if ( 'none' === $atts['userlevel'] && ! is_user_logged_in() ) {
		$has_access = false;
	}
	if ( 'none' !== $atts['userlevel'] ) {
		$levels = array_map( 'trim', explode( ',', $atts['userlevel'] ) );
		$is_level_number = check_array_only_numbers( $levels );
		$level_exists = false;
		$user_memberships_levels = array();

		if ( is_object( $customer ) ) {
			if ( $is_level_number ) {
				$user_memberships_levels = rcp_get_member_levels( $customer );
				if ( ! empty( $user_memberships_levels ) ) {
					$level_exists = true;
				}
				if ( $level_exists ) {
					foreach ( $levels as $level ) {
						if ( in_array( absint( $level ), $user_memberships_levels, true ) ) {
							$has_access = true;
							break;
						} else {
							$has_access = false;
						}
					}
				}
			} else {
				foreach ( $levels as $level ) {
					if ( current_user_can( strtolower( $level ) ) ) {
						$has_access = true;
						break;
					} else {
						$has_access = false;
					}
				}
			}
		}
	}

	if ( ! empty( $customer ) && $customer->is_pending_verification() ) {
		$has_access = false;
	}

	if ( current_user_can( 'manage_options' ) ) {
		$has_access = true;
	}

	$has_access = (bool) apply_filters( 'rcp_restrict_shortcode_has_access', $has_access, $user_ID, $atts );

	if ( $has_access ) {
		return apply_filters( 'rcp_restrict_shortcode_return', $content );
	} else {
		return '<div class="' . esc_attr( $classes ) . '">' . rcp_format_teaser( $teaser ) . '</div>';
	}
}
add_shortcode( 'restrict', 'rcp_restrict_shortcode' );

function rcp_is_paid_user_shortcode( $atts, $content = null ) {
	if ( rcp_user_has_paid_membership() ) {
		return do_shortcode( $content );
	}
	return '';
}
add_shortcode( 'is_paid', 'rcp_is_paid_user_shortcode' );

function rcp_is_free_user_shortcode( $atts, $content = null ) {
	$atts = shortcode_atts( array( 'hide_from_paid' => true ), $atts, 'is_free' );
	if ( $atts['hide_from_paid'] ) {
		if ( is_user_logged_in() && ! rcp_user_has_paid_membership() ) {
			return do_shortcode( $content );
		}
	} elseif ( is_user_logged_in() ) {
		return do_shortcode( $content );
	}
	return '';
}
add_shortcode( 'is_free', 'rcp_is_free_user_shortcode' );

function rcp_is_expired_user_shortcode( $atts, $content = null ) {
	if ( rcp_user_has_expired_membership() ) {
		return do_shortcode( $content );
	}
	return '';
}
add_shortcode( 'is_expired', 'rcp_is_expired_user_shortcode' );

function rcp_not_logged_in( $atts, $content = null ) {
	if ( ! is_user_logged_in() ) {
		return do_shortcode( $content );
	}
	return '';
}
add_shortcode( 'not_logged_in', 'rcp_not_logged_in' );

function rcp_is_not_paid( $atts, $content = null ) {
	if ( ! rcp_user_has_paid_membership() ) {
		return do_shortcode( $content );
	}
	return '';
}
add_shortcode( 'is_not_paid', 'rcp_is_not_paid' );

/* --------------------------------------------------------------------- *
 * Metabox
 * --------------------------------------------------------------------- */

function rcp_add_meta_boxes() {
	foreach ( rcp_get_metabox_post_types() as $post_type ) {
		add_meta_box( 'rcp_meta_box', __( 'Restrict this content', 'rcp' ), 'rcp_render_meta_box', $post_type, 'normal', 'high' );
	}
}
add_action( 'add_meta_boxes', 'rcp_add_meta_boxes' );

function rcp_render_meta_box() {
	global $post;
	echo '<input type="hidden" name="rcp_meta_box" value="' . wp_create_nonce( basename( __FILE__ ) ) . '" />';
	do_action( 'rcp_metabox_fields_before' );
	include SMM_PLUGIN_DIR . 'admin/metabox-view.php';
	do_action( 'rcp_metabox_fields_after' );
}

function rcp_save_meta_data( $post_id ) {
	if ( ! isset( $_POST['rcp_meta_box'] ) || ! wp_verify_nonce( $_POST['rcp_meta_box'], basename( __FILE__ ) ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( 'page' === $_POST['post_type'] ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}
	} elseif ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$is_paid = false;
	$restrict_by = ! empty( $_POST['rcp_restrict_by'] ) ? sanitize_text_field( $_POST['rcp_restrict_by'] ) : 'unrestricted';

	switch ( $restrict_by ) {
		case 'unrestricted':
			delete_post_meta( $post_id, 'rcp_access_level' );
			delete_post_meta( $post_id, 'rcp_subscription_level' );
			delete_post_meta( $post_id, 'rcp_user_level' );
			break;
		case 'subscription-level':
			$level_set = sanitize_text_field( $_POST['rcp_subscription_level_any_set'] );
			switch ( $level_set ) {
				case 'any':
					update_post_meta( $post_id, 'rcp_subscription_level', 'any' );
					break;
				case 'any-paid':
					$is_paid = true;
					update_post_meta( $post_id, 'rcp_subscription_level', 'any-paid' );
					break;
				case 'specific':
					$is_paid = true;
					$raw_levels = ( ! empty( $_POST['rcp_subscription_level'] ) && is_array( $_POST['rcp_subscription_level'] ) ) ? $_POST['rcp_subscription_level'] : array();
					$levels = array_map( 'absint', $raw_levels );
					foreach ( $levels as $level ) {
						$price = rcp_get_subscription_price( $level );
						if ( empty( $price ) ) {
							$is_paid = false;
							break;
						}
					}
					update_post_meta( $post_id, 'rcp_subscription_level', $levels );
					break;
			}
			delete_post_meta( $post_id, 'rcp_access_level' );
			break;
		case 'access-level':
			update_post_meta( $post_id, 'rcp_access_level', absint( $_POST['rcp_access_level'] ) );
			delete_post_meta( $post_id, 'rcp_subscription_level' );
			break;
		case 'registered-users':
			delete_post_meta( $post_id, 'rcp_access_level' );
			delete_post_meta( $post_id, 'rcp_subscription_level' );
			break;
	}

	global $rcp_options;
	$content_excerpts = isset( $rcp_options['content_excerpts'] ) ? $rcp_options['content_excerpts'] : 'individual';
	$show_excerpt = isset( $_POST['rcp_show_excerpt'] );
	$user_role = ! empty( $_POST['rcp_user_level'] ) ? $_POST['rcp_user_level'] : 'all';
	if ( ! is_array( $user_role ) ) {
		$user_role = array( $user_role );
	}
	$user_role = array_map( 'sanitize_text_field', $user_role );

	if ( 'individual' === $content_excerpts && $show_excerpt ) {
		update_post_meta( $post_id, 'rcp_show_excerpt', $show_excerpt );
	} else {
		delete_post_meta( $post_id, 'rcp_show_excerpt' );
	}

	if ( ! empty( $_POST['rcp_restrict_by'] ) && 'unrestricted' !== $_POST['rcp_restrict_by'] ) {
		update_post_meta( $post_id, 'rcp_user_level', $user_role );
	}

	if ( $is_paid ) {
		update_post_meta( $post_id, '_is_paid', $is_paid );
	} else {
		delete_post_meta( $post_id, '_is_paid' );
	}

	do_action( 'rcp_save_post_meta', $post_id );
}
add_action( 'save_post', 'rcp_save_meta_data' );
