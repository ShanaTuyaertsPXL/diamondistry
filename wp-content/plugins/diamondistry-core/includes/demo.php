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

// The account forms are not the only way in: the REST API also lets a user edit
// or delete themselves (and add application passwords). Block that for the demo.
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if (
			'GET' !== $request->get_method()
			&& 0 === strpos( $request->get_route(), '/wp/v2/users' )
			&& diamondistry_is_demo_user()
		) {
			return new WP_Error( 'diamondistry_demo_locked', 'Settings are locked on the shared demo account.', array( 'status' => 403 ) );
		}
		return $result;
	},
	10,
	3
);
