<?php
/**
 * Shared demo account for portfolio visitors. The login is shown on the
 * login page; its settings are locked so one visitor cannot lock out the next.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ponytail: public on purpose, it is printed on the login page.
define( 'DIAMONDISTRY_DEMO_LOGIN', 'demo' );
define( 'DIAMONDISTRY_DEMO_PASSWORD', 'diamonds' );

function diamondistry_is_demo_user( $user_id = 0 ) {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	return $user && DIAMONDISTRY_DEMO_LOGIN === $user->user_login;
}

/** Login hint with a button that fills in the demo credentials. Empty until the demo user exists. */
function diamondistry_render_demo_login_hint() {
	if ( ! get_user_by( 'login', DIAMONDISTRY_DEMO_LOGIN ) ) {
		return '';
	}

	return sprintf(
		'<div class="dd-demo-hint"><p><strong>Just looking around?</strong> Log in with the shared demo account.</p><div class="dd-demo-hint__row"><span><code>%1$s</code> / <code>%2$s</code></span><button type="button" class="dd-text-button" data-demo-fill data-login="%1$s" data-password="%2$s">Fill in →</button></div></div>',
		esc_attr( DIAMONDISTRY_DEMO_LOGIN ),
		esc_attr( DIAMONDISTRY_DEMO_PASSWORD )
	);
}

// The account forms are not the only way in: the REST API (routes match
// case-insensitively, so no path check) also lets a user edit or delete
// themselves. Take away the capability itself for the demo user.
add_filter(
	'map_meta_cap',
	function ( $caps, $cap, $user_id, $args ) {
		if (
			in_array( $cap, array( 'edit_user', 'delete_user', 'promote_user' ), true )
			&& isset( $args[0] ) && (int) $args[0] === (int) $user_id
			&& diamondistry_is_demo_user( $user_id )
		) {
			return array( 'do_not_allow' );
		}
		return $caps;
	},
	10,
	4
);

add_filter(
	'wp_is_application_passwords_available_for_user',
	function ( $available, $user ) {
		return $available && ! diamondistry_is_demo_user( $user->ID );
	},
	10,
	2
);

/*
 * Nightly reset, so every visitor finds the demo the same way: 120 diamonds,
 * one finished free painting, nothing unlocked or favourited.
 */
function diamondistry_reset_demo_account() {
	$user = get_user_by( 'login', DIAMONDISTRY_DEMO_LOGIN );
	if ( ! $user ) {
		return;
	}

	$finished = array();
	foreach ( get_posts( array( 'post_type' => 'ddp_painting', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'DESC' ) ) as $painting ) {
		$data = diamondistry_get_painting_data( $painting );
		if ( $data && ! (int) get_post_meta( $painting->ID, '_diamondistry_unlock_cost', true ) && ! diamondistry_is_current_challenge( $painting->ID ) ) {
			$done                                 = time() - DAY_IN_SECONDS;
			$finished[ (string) $painting->ID ] = array(
				'completedCells' => range( 0, $data['width'] * $data['height'] - 1 ),
				'selectedColor'  => null,
				'completed'      => true,
				'rewardClaimed'  => true,
				'lastWorkedAt'   => $done,
				'startedAt'      => $done - 600,
				'completedAt'    => $done,
				'lastSaveAt'     => $done,
				'implausible'    => false,
				'updatedAt'      => gmdate( 'Y-m-d H:i:s', $done ),
				'revision'       => 1,
			);
			break;
		}
	}

	// Same per-user lock as saves, so the reset never interleaves with a visitor's move.
	$locked = diamondistry_begin_progress_write( $user->ID );
	update_user_meta( $user->ID, DIAMONDISTRY_PROGRESS_META_KEY, $finished );
	update_user_meta( $user->ID, DIAMONDISTRY_BALANCE_META_KEY, 120 );
	delete_user_meta( $user->ID, DIAMONDISTRY_FAVORITES_META_KEY );
	delete_user_meta( $user->ID, DIAMONDISTRY_UNLOCKED_META_KEY );
	delete_user_meta( $user->ID, DIAMONDISTRY_UNLOCK_PAID_META_KEY );
	diamondistry_commit_progress_write( $locked );
}
add_action( 'diamondistry_reset_demo', 'diamondistry_reset_demo_account' );

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'diamondistry_reset_demo' ) ) {
			wp_schedule_event( ( new DateTimeImmutable( 'tomorrow 04:00', wp_timezone() ) )->getTimestamp(), 'daily', 'diamondistry_reset_demo' );
		}
	}
);
