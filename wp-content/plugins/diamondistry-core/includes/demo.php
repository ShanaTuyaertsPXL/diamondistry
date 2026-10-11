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
