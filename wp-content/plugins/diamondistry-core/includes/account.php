<?php
/**
 * Frontend accounts: login, register, password reset and profile.
 *
 * Players never see wp-login.php or wp-admin. Forms post back to their own
 * page and are handled on template_redirect so we can redirect (PRG).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Pages
|--------------------------------------------------------------------------
*/

function diamondistry_account_pages() {
	return array(
		'about'        => 'About',
		'how-it-works' => 'How it works',
		'login'        => 'Log in',
		'register'     => 'Create account',
		'account'      => 'My account',
		'challenge'    => 'Weekly challenge',
	);
}

function diamondistry_ensure_account_pages() {
	// Bump when a page is added: only missing pages are created.
	if ( '2' === get_option( 'diamondistry_pages_seeded' ) ) {
		return;
	}

	foreach ( diamondistry_account_pages() as $slug => $title ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}

		wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => $slug,
				'post_title'  => $title,
			)
		);
	}

	update_option( 'diamondistry_pages_seeded', '2', false );
}

add_action( 'init', 'diamondistry_ensure_account_pages', 30 );

function diamondistry_page_url( $slug, $args = array() ) {
	return add_query_arg( $args, home_url( '/' . $slug . '/' ) );
}

/*
|--------------------------------------------------------------------------
| Point WordPress auth URLs at the frontend
|--------------------------------------------------------------------------
*/

function diamondistry_filter_login_url( $url, $redirect ) {
	// The wp-admin session-expired modal needs the real wp-login.php (interim login).
	if ( is_admin() ) {
		return $url;
	}

	$args = $redirect ? array( 'redirect_to' => rawurlencode( $redirect ) ) : array();
	return diamondistry_page_url( 'login', $args );
}

add_filter( 'login_url', 'diamondistry_filter_login_url', 10, 2 );

add_filter(
	'register_url',
	function () {
		return diamondistry_page_url( 'register' );
	}
);

add_filter(
	'lostpassword_url',
	function () {
		return diamondistry_page_url( 'login', array( 'action' => 'lostpassword' ) );
	}
);

/**
 * Send GET visits to wp-login.php to the frontend pages. POSTs, logout,
 * interim (re-auth modal) and post password requests keep working.
 * Escape hatch if the frontend login ever breaks: /wp-login.php?native=1
 */
function diamondistry_redirect_wp_login() {
	if ( 'GET' !== $_SERVER['REQUEST_METHOD'] || isset( $_GET['interim-login'] ) || isset( $_GET['native'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
	$args   = array();

	if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$args['redirect_to'] = rawurlencode( wp_unslash( $_GET['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated on use.
	}

	switch ( $action ) {
		case 'login':
			$target = diamondistry_page_url( 'login', $args );
			break;
		case 'register':
			$target = diamondistry_page_url( 'register' );
			break;
		case 'lostpassword':
			$target = diamondistry_page_url( 'login', array( 'action' => 'lostpassword' ) );
			break;
		case 'rp':
		case 'resetpass':
			$target = diamondistry_page_url(
				'login',
				array(
					'action' => 'rp',
					'key'    => isset( $_GET['key'] ) ? rawurlencode( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
					'login'  => isset( $_GET['login'] ) ? rawurlencode( sanitize_user( wp_unslash( $_GET['login'] ) ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
				)
			);
			break;
		default:
			return;
	}

	wp_safe_redirect( $target );
	exit;
}

add_action( 'login_init', 'diamondistry_redirect_wp_login' );

/*
|--------------------------------------------------------------------------
| Keep players out of wp-admin
|--------------------------------------------------------------------------
*/

function diamondistry_user_is_player() {
	return is_user_logged_in() && ! current_user_can( 'edit_posts' );
}

add_action(
	'admin_init',
	function () {
		if ( diamondistry_user_is_player() && ! wp_doing_ajax() ) {
			wp_safe_redirect( diamondistry_page_url( 'account' ) );
			exit;
		}
	}
);

add_filter(
	'show_admin_bar',
	function ( $show ) {
		return diamondistry_user_is_player() ? false : $show;
	}
);

add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( is_page( array( 'login', 'register', 'account', 'dashboard' ) ) ) {
			$robots['noindex'] = true;
		}
		return $robots;
	}
);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function diamondistry_get_redirect_target( $fallback ) {
	$raw = isset( $_REQUEST['redirect_to'] ) ? trim( (string) wp_unslash( $_REQUEST['redirect_to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.

	// wp_validate_redirect() passes an empty string through, and wp_redirect('') silently does nothing.
	if ( '' === $raw ) {
		return $fallback;
	}

	return wp_validate_redirect( $raw, $fallback );
}

/** Messages for the current request, keyed by form. */
function diamondistry_form_messages( $form = null, $message = null, $type = 'error' ) {
	static $messages = array();

	if ( null !== $message ) {
		$messages[ $form ][] = array(
			'type' => $type,
			'text' => $message,
		);
	}

	if ( null === $form ) {
		return $messages;
	}

	return isset( $messages[ $form ] ) ? $messages[ $form ] : array();
}

function diamondistry_form_error( $form, $message ) {
	diamondistry_form_messages( $form, $message, 'error' );
}

function diamondistry_render_form_messages( $form ) {
	$out = '';
	foreach ( diamondistry_form_messages( $form ) as $msg ) {
		$out .= sprintf(
			'<div class="dd-notice dd-notice--%1$s" role="%2$s">%3$s</div>',
			esc_attr( $msg['type'] ),
			'error' === $msg['type'] ? 'alert' : 'status',
			esc_html( $msg['text'] )
		);
	}
	return $out;
}

/** Strip whitespace between tags so wpautop cannot inject <p>/<br> into shortcode output. */
function diamondistry_compact_html( $html ) {
	return trim( preg_replace( '/>\s+</', '><', $html ) );
}

function diamondistry_post_field( $key ) {
	return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked in the dispatcher.
}

/** Raw password field: passwords may legitimately contain any character. */
function diamondistry_post_password( $key ) {
	return isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- checked in dispatcher, passwords are not sanitized.
}

/*
|--------------------------------------------------------------------------
| Form dispatcher
|--------------------------------------------------------------------------
*/

function diamondistry_handle_account_forms() {
	if ( is_page( array( 'login', 'register' ) ) ) {
		// Never leak auth URLs (reset links, redirect targets) to other origins.
		header( 'Referrer-Policy: no-referrer' );
	}

	// Reset link from the email: move the key into a cookie and drop it from the URL.
	if ( is_page( 'login' ) && isset( $_GET['action'], $_GET['key'], $_GET['login'] ) && 'rp' === $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		$login = sanitize_user( wp_unslash( $_GET['login'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$key   = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		diamondistry_set_reset_cookie( $login . ':' . $key );
		wp_safe_redirect( diamondistry_page_url( 'login', array( 'action' => 'rp' ) ) );
		exit;
	}

	// Logged in players have no business on login/register.
	if ( is_user_logged_in() && is_page( array( 'login', 'register' ) ) ) {
		wp_safe_redirect( diamondistry_get_redirect_target( diamondistry_page_url( 'account' ) ) );
		exit;
	}

	if ( ! is_user_logged_in() && is_page( 'account' ) ) {
		wp_safe_redirect( wp_login_url( diamondistry_page_url( 'account' ) ) );
		exit;
	}

	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || empty( $_POST['dd_form'] ) ) {
		return;
	}

	$form = sanitize_key( wp_unslash( $_POST['dd_form'] ) );

	if ( ! isset( $_POST['dd_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dd_nonce'] ) ), 'dd_' . $form ) ) {
		diamondistry_form_error( $form, 'Your session expired. Please try again.' );
		return;
	}

	$handlers = array(
		'login'        => 'diamondistry_handle_login',
		'register'     => 'diamondistry_handle_register',
		'lostpassword' => 'diamondistry_handle_lostpassword',
		'resetpass'    => 'diamondistry_handle_resetpass',
		'profile'      => 'diamondistry_handle_profile',
		'password'     => 'diamondistry_handle_password',
		'delete'       => 'diamondistry_handle_delete_account',
	);

	if ( ! isset( $handlers[ $form ] ) ) {
		return;
	}

	// Profile forms are only valid for a logged in user.
	if ( in_array( $form, array( 'profile', 'password', 'delete' ), true ) && ! is_user_logged_in() ) {
		return;
	}

	// The shared demo account keeps its settings, so the next visitor can still log in.
	if ( in_array( $form, array( 'profile', 'password', 'delete' ), true ) && diamondistry_is_demo_user() ) {
		diamondistry_form_error( $form, 'Settings are locked on the shared demo account.' );
		return;
	}

	call_user_func( $handlers[ $form ] );
}

add_action( 'template_redirect', 'diamondistry_handle_account_forms' );

function diamondistry_handle_login() {
	$user = wp_signon(
		array(
			'user_login'    => diamondistry_post_field( 'log' ),
			'user_password' => diamondistry_post_password( 'pwd' ),
			'remember'      => ! empty( $_POST['rememberme'] ), // phpcs:ignore WordPress.Security.NonceVerification
		),
		is_ssl()
	);

	if ( is_wp_error( $user ) ) {
		// One generic message: do not reveal whether the account exists.
		diamondistry_form_error( 'login', 'That email/username and password combination did not work.' );
		return;
	}

	wp_safe_redirect( diamondistry_get_redirect_target( home_url( '/dashboard/' ) ) );
	exit;
}

function diamondistry_handle_register() {
	if ( ! get_option( 'users_can_register' ) ) {
		diamondistry_form_error( 'register', 'Registration is currently closed.' );
		return;
	}

	// Honeypot: real people leave this hidden field empty.
	if ( '' !== diamondistry_post_field( 'website' ) ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	$username = sanitize_user( diamondistry_post_field( 'user_login' ), true );
	$email    = sanitize_email( diamondistry_post_field( 'user_email' ) );
	$password = diamondistry_post_password( 'user_pass' );

	if ( '' === $username || ! validate_username( $username ) ) {
		diamondistry_form_error( 'register', 'Choose a username using letters, numbers, dots, dashes or underscores.' );
	} elseif ( username_exists( $username ) ) {
		diamondistry_form_error( 'register', 'That username is already taken.' );
	}

	if ( ! is_email( $email ) ) {
		diamondistry_form_error( 'register', 'Please enter a valid email address.' );
	} elseif ( email_exists( $email ) ) {
		diamondistry_form_error( 'register', 'An account with this email already exists. Try logging in instead.' );
	}

	if ( strlen( $password ) < 8 ) {
		diamondistry_form_error( 'register', 'Your password needs at least 8 characters.' );
	}

	if ( diamondistry_form_messages( 'register' ) ) {
		return;
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $username,
			'role'         => get_option( 'default_role', 'subscriber' ),
		)
	);

	if ( is_wp_error( $user_id ) ) {
		diamondistry_form_error( 'register', $user_id->get_error_message() );
		return;
	}

	wp_new_user_notification( $user_id, null, 'admin' );

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );

	wp_safe_redirect( diamondistry_get_redirect_target( home_url( '/paintings/' ) ) );
	exit;
}

function diamondistry_handle_lostpassword() {
	$login = diamondistry_post_field( 'user_login' );

	if ( '' === $login ) {
		diamondistry_form_error( 'lostpassword', 'Enter your username or email address.' );
		return;
	}

	// Result is ignored on purpose so the response never reveals which accounts exist.
	retrieve_password( $login );

	wp_safe_redirect( diamondistry_page_url( 'login', array( 'checkemail' => '1' ) ) );
	exit;
}

define( 'DIAMONDISTRY_RESET_COOKIE', 'dd-resetpass' );

function diamondistry_reset_cookie_path() {
	return (string) wp_parse_url( diamondistry_page_url( 'login' ), PHP_URL_PATH );
}

function diamondistry_set_reset_cookie( $value ) {
	setcookie(
		DIAMONDISTRY_RESET_COOKIE,
		$value,
		array(
			'expires'  => $value ? 0 : time() - YEAR_IN_SECONDS,
			'path'     => diamondistry_reset_cookie_path(),
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

/** The reset key lives in an httponly cookie (like core wp-login.php), never in the visible URL. */
function diamondistry_get_reset_user() {
	$raw = isset( $_COOKIE[ DIAMONDISTRY_RESET_COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ DIAMONDISTRY_RESET_COOKIE ] ) ) : '';

	if ( false === strpos( $raw, ':' ) ) {
		return new WP_Error( 'invalid_key' );
	}

	list( $login, $key ) = explode( ':', $raw, 2 );

	return check_password_reset_key( $key, sanitize_user( $login ) );
}

function diamondistry_handle_resetpass() {
	$user = diamondistry_get_reset_user();

	if ( is_wp_error( $user ) ) {
		diamondistry_form_error( 'resetpass', 'This reset link is invalid or has expired. Request a new one below.' );
		return;
	}

	$password = diamondistry_post_password( 'pass1' );

	if ( strlen( $password ) < 8 ) {
		diamondistry_form_error( 'resetpass', 'Your password needs at least 8 characters.' );
		return;
	}

	if ( diamondistry_post_password( 'pass2' ) !== $password ) {
		diamondistry_form_error( 'resetpass', 'The passwords do not match.' );
		return;
	}

	reset_password( $user, $password );
	diamondistry_set_reset_cookie( '' );

	wp_safe_redirect( diamondistry_page_url( 'login', array( 'password' => 'reset' ) ) );
	exit;
}

function diamondistry_handle_profile() {
	$user  = wp_get_current_user();
	$email = sanitize_email( diamondistry_post_field( 'user_email' ) );
	$data  = array(
		'ID'           => $user->ID,
		'first_name'   => diamondistry_post_field( 'first_name' ),
		'last_name'    => diamondistry_post_field( 'last_name' ),
		'display_name' => diamondistry_post_field( 'display_name' ),
	);

	if ( '' === $data['display_name'] ) {
		$data['display_name'] = $user->user_login;
	}

	if ( $email !== $user->user_email ) {
		if ( ! is_email( $email ) ) {
			diamondistry_form_error( 'profile', 'Please enter a valid email address.' );
			return;
		}
		if ( email_exists( $email ) ) {
			diamondistry_form_error( 'profile', 'That email address is already in use.' );
			return;
		}
		if ( ! wp_check_password( diamondistry_post_password( 'current_pass' ), $user->user_pass, $user->ID ) ) {
			diamondistry_form_error( 'profile', 'Enter your current password to change your email address.' );
			return;
		}
		$data['user_email'] = $email;
	}

	if ( ! diamondistry_handle_avatar_upload( $user->ID ) ) {
		return;
	}

	$result = wp_update_user( $data );

	if ( is_wp_error( $result ) ) {
		diamondistry_form_error( 'profile', $result->get_error_message() );
		return;
	}

	update_user_meta(
		$user->ID,
		DIAMONDISTRY_TAGLINE_META_KEY,
		mb_substr( diamondistry_post_field( 'tagline' ), 0, DIAMONDISTRY_TAGLINE_MAX )
	);

	if ( defined( 'DIAMONDISTRY_LEADERBOARD_HIDDEN_META_KEY' ) ) {
		update_user_meta( $user->ID, DIAMONDISTRY_LEADERBOARD_HIDDEN_META_KEY, empty( $_POST['leaderboard_hidden'] ) ? 0 : 1 ); // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatcher.
	}

	$updated = empty( $_POST['dd_avatar_remove'] ) ? 'profile' : 'photo-removed'; // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatcher.
	wp_safe_redirect( diamondistry_page_url( 'account', array( 'updated' => $updated ) ) );
	exit;
}

function diamondistry_handle_password() {
	$user     = wp_get_current_user();
	$password = diamondistry_post_password( 'pass1' );

	if ( ! wp_check_password( diamondistry_post_password( 'current_pass' ), $user->user_pass, $user->ID ) ) {
		diamondistry_form_error( 'password', 'Your current password is not correct.' );
		return;
	}

	if ( strlen( $password ) < 8 ) {
		diamondistry_form_error( 'password', 'Your new password needs at least 8 characters.' );
		return;
	}

	if ( diamondistry_post_password( 'pass2' ) !== $password ) {
		diamondistry_form_error( 'password', 'The new passwords do not match.' );
		return;
	}

	// wp_update_user re-issues the auth cookie for the current user.
	wp_update_user(
		array(
			'ID'        => $user->ID,
			'user_pass' => $password,
		)
	);

	wp_safe_redirect( diamondistry_page_url( 'account', array( 'updated' => 'password' ) ) );
	exit;
}

/*
|--------------------------------------------------------------------------
| Profile photo and tagline
|--------------------------------------------------------------------------
*/

define( 'DIAMONDISTRY_AVATAR_META_KEY', '_diamondistry_avatar_id' );
define( 'DIAMONDISTRY_TAGLINE_META_KEY', '_diamondistry_tagline' );
define( 'DIAMONDISTRY_TAGLINE_MAX', 80 );

function diamondistry_get_user_tagline( $user_id ) {
	$tagline = (string) get_user_meta( $user_id, DIAMONDISTRY_TAGLINE_META_KEY, true );
	return '' !== $tagline ? $tagline : "Keep creating, you're doing great!";
}

function diamondistry_delete_user_avatar( $user_id ) {
	$old = (int) get_user_meta( $user_id, DIAMONDISTRY_AVATAR_META_KEY, true );
	if ( $old ) {
		wp_delete_attachment( $old, true );
	}
	delete_user_meta( $user_id, DIAMONDISTRY_AVATAR_META_KEY );
}

/**
 * Upload or remove the profile photo. Returns false (with a form error) on failure.
 * Images only, max 2 MB; the previous photo is deleted so files do not pile up.
 */
function diamondistry_handle_avatar_upload( $user_id ) {
	if ( ! empty( $_POST['dd_avatar_remove'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatcher.
		diamondistry_delete_user_avatar( $user_id );
		return true;
	}

	if ( empty( $_FILES['dd_avatar']['name'] ) ) {
		return true;
	}

	$file  = $_FILES['dd_avatar']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validated below and by media_handle_upload.
	$mimes = array(
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
	);

	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		diamondistry_form_error( 'profile', 'Your photo could not be uploaded. Please try again.' );
		return false;
	}

	if ( (int) $file['size'] > 2 * MB_IN_BYTES ) {
		diamondistry_form_error( 'profile', 'Your photo is too large. The maximum is 2 MB.' );
		return false;
	}

	$check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
	if ( empty( $check['type'] ) || ! @getimagesize( $file['tmp_name'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
		diamondistry_form_error( 'profile', 'Please upload a JPG, PNG or WebP image.' );
		return false;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$attachment_id = media_handle_upload(
		'dd_avatar',
		0,
		array(),
		array(
			'test_form' => false,
			'mimes'     => $mimes,
		)
	);

	if ( is_wp_error( $attachment_id ) ) {
		diamondistry_form_error( 'profile', $attachment_id->get_error_message() );
		return false;
	}

	diamondistry_delete_user_avatar( $user_id );
	update_user_meta( $user_id, DIAMONDISTRY_AVATAR_META_KEY, $attachment_id );

	return true;
}

/** Every get_avatar() on the site uses the uploaded photo when there is one. */
function diamondistry_filter_avatar_data( $args, $id_or_email ) {
	$user_id = 0;

	if ( is_numeric( $id_or_email ) ) {
		$user_id = (int) $id_or_email;
	} elseif ( $id_or_email instanceof WP_User ) {
		$user_id = $id_or_email->ID;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$user_id = (int) $id_or_email->post_author;
	} elseif ( $id_or_email instanceof WP_Comment ) {
		$user_id = (int) $id_or_email->user_id;
	} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$found   = get_user_by( 'email', $id_or_email );
		$user_id = $found ? $found->ID : 0;
	}

	$attachment_id = $user_id ? (int) get_user_meta( $user_id, DIAMONDISTRY_AVATAR_META_KEY, true ) : 0;
	$url           = $attachment_id ? wp_get_attachment_image_url( $attachment_id, (int) $args['size'] > 96 ? 'medium' : 'thumbnail' ) : '';

	if ( $url ) {
		$args['url']          = $url;
		$args['found_avatar'] = true;
	}

	return $args;
}

add_filter( 'pre_get_avatar_data', 'diamondistry_filter_avatar_data', 10, 2 );

/**
 * No Gravatar: anyone without an uploaded photo gets an inline SVG with their
 * initials, so no email hash ever leaves the site (privacy).
 */
function diamondistry_initials_avatar( $avatar, $id_or_email, $args ) {
	$user = false;

	if ( is_numeric( $id_or_email ) ) {
		$user = get_user_by( 'id', (int) $id_or_email );
	} elseif ( $id_or_email instanceof WP_User ) {
		$user = $id_or_email;
	} elseif ( $id_or_email instanceof WP_Post ) {
		$user = get_user_by( 'id', (int) $id_or_email->post_author );
	} elseif ( $id_or_email instanceof WP_Comment ) {
		$user = $id_or_email->user_id ? get_user_by( 'id', (int) $id_or_email->user_id ) : false;
	} elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
		$user = get_user_by( 'email', $id_or_email );
	}

	// Uploaded photo: let WordPress build the normal <img> via pre_get_avatar_data.
	if ( $user && get_user_meta( $user->ID, DIAMONDISTRY_AVATAR_META_KEY, true ) ) {
		return $avatar;
	}

	$name     = $user ? $user->display_name : '';
	$words    = preg_split( '/\s+/u', trim( (string) preg_replace( '/[^\p{L}\p{N}\s]/u', '', $name ) ) );
	$initials = '';
	foreach ( array_slice( array_filter( $words ), 0, 2 ) as $word ) {
		$initials .= mb_strtoupper( mb_substr( $word, 0, 1 ) );
	}

	$palettes = array(
		array( '#8f74ff', '#6e4df6' ),
		array( '#ff8fb8', '#ff5f93' ),
		array( '#7fb6ff', '#5f9df7' ),
		array( '#5fd39b', '#3cba7a' ),
		array( '#ffb877', '#f08a3c' ),
	);
	$colors = $palettes[ ( $user ? $user->ID : 0 ) % count( $palettes ) ];
	$size   = (int) $args['size'];

	$svg = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="%1$s"/><stop offset="1" stop-color="%2$s"/></linearGradient></defs><rect width="64" height="64" fill="url(#g)"/>%3$s</svg>',
		$colors[0],
		$colors[1],
		'' === $initials
			// No letters to show: a white brand diamond instead.
			? '<g transform="translate(16 15) scale(1.333)" fill="#fff"><path d="M7 3.5h10L22 9H2z"/><path fill-opacity=".8" d="M2 9h10v12z"/><path fill-opacity=".6" d="M12 9h10L12 21z"/></g>'
			: sprintf(
				'<text x="32" y="32" dy=".35em" text-anchor="middle" font-family="Nunito, Arial, sans-serif" font-weight="800" font-size="%d" fill="#fff">%s</text>',
				mb_strlen( $initials ) > 1 ? 24 : 28,
				htmlspecialchars( $initials, ENT_XML1 )
			)
	);

	return sprintf(
		'<img alt="%1$s" src="%2$s" class="%3$s" width="%4$d" height="%4$d" decoding="async">',
		esc_attr( $args['alt'] ),
		esc_attr( 'data:image/svg+xml;charset=utf-8,' . rawurlencode( $svg ) ),
		esc_attr( trim( 'avatar avatar-' . $size . ' photo ' . ( is_array( $args['class'] ) ? implode( ' ', $args['class'] ) : (string) $args['class'] ) ) ),
		$size
	);
}

add_filter( 'pre_get_avatar', 'diamondistry_initials_avatar', 10, 3 );

// Deleting a user also removes their uploaded photo.
add_action( 'delete_user', 'diamondistry_delete_user_avatar' );

/*
|--------------------------------------------------------------------------
| Shared form markup
|--------------------------------------------------------------------------
*/

function diamondistry_form_open( $form, $extra = '', $attrs = '' ) {
	return sprintf(
		'<form class="dd-form" method="post" action=""%5$s>%1$s<input type="hidden" name="dd_form" value="%2$s"><input type="hidden" name="dd_nonce" value="%3$s">%4$s',
		diamondistry_render_form_messages( $form ),
		esc_attr( $form ),
		esc_attr( wp_create_nonce( 'dd_' . $form ) ),
		$extra,
		$attrs ? ' ' . $attrs : ''
	);
}

function diamondistry_field( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'name'         => '',
			'label'        => '',
			'type'         => 'text',
			'value'        => '',
			'autocomplete' => 'off',
			'required'     => true,
			'hint'         => '',
			'maxlength'    => 0,
		)
	);

	$id = 'dd-' . $args['name'];

	ob_start();
	?>
	<div class="dd-field">
		<label class="dd-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
		<input
			class="dd-field__input"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( $args['name'] ); ?>"
			type="<?php echo esc_attr( $args['type'] ); ?>"
			value="<?php echo esc_attr( $args['value'] ); ?>"
			autocomplete="<?php echo esc_attr( $args['autocomplete'] ); ?>"
			<?php echo $args['required'] ? 'required' : ''; ?>
			<?php echo $args['hint'] ? 'aria-describedby="' . esc_attr( $id ) . '-hint"' : ''; ?>
			<?php echo $args['maxlength'] ? 'maxlength="' . absint( $args['maxlength'] ) . '"' : ''; ?>
		>
		<?php if ( $args['hint'] ) : ?>
			<p class="dd-field__hint" id="<?php echo esc_attr( $id ); ?>-hint"><?php echo esc_html( $args['hint'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/*
|--------------------------------------------------------------------------
| [diamondistry_login]: login, lost password and reset password
|--------------------------------------------------------------------------
*/

function diamondistry_render_login() {
	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	if ( 'lostpassword' === $action ) {
		return diamondistry_render_lostpassword();
	}

	if ( 'rp' === $action ) {
		return diamondistry_render_resetpass();
	}

	$redirect = isset( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput -- validated on use, escaped on output.
	$notice   = '';

	if ( isset( $_GET['checkemail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$notice = '<div class="dd-notice dd-notice--success" role="status">If an account matches, a reset link is on its way to your inbox.</div>';
	} elseif ( isset( $_GET['password'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$notice = '<div class="dd-notice dd-notice--success" role="status">Your password has been changed. You can log in now.</div>';
	} elseif ( isset( $_GET['account'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$notice = '<div class="dd-notice dd-notice--success" role="status">Your account and all its data have been deleted.</div>';
	}

	ob_start();
	?>
	<div class="dd-auth__card">
		<h1 class="dd-auth__title">Welcome back</h1>
		<p class="dd-auth__lead">Log in to continue your paintings and collect your diamonds.</p>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup. ?>
		<?php echo diamondistry_render_demo_login_hint(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
		<?php echo diamondistry_form_open( 'login', '<input type="hidden" name="redirect_to" value="' . esc_attr( $redirect ) . '">' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'name'         => 'log',
					'label'        => 'Email or username',
					'value'        => diamondistry_post_field( 'log' ),
					'autocomplete' => 'username',
				)
			);
			echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'name'         => 'pwd',
					'label'        => 'Password',
					'type'         => 'password',
					'autocomplete' => 'current-password',
				)
			);
			?>
			<div class="dd-form__row">
				<label class="dd-check"><input type="checkbox" name="rememberme" value="1" checked> Remember me</label>
				<a class="dd-link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Forgot password?</a>
			</div>
			<button class="dd-button dd-button-primary dd-button--block" type="submit">Log in</button>
		</form>
		<p class="dd-auth__switch">New here? <a class="dd-link" href="<?php echo esc_url( wp_registration_url() ); ?>">Create an account</a></p>
		<?php echo diamondistry_auth_privacy_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode(
	'diamondistry_login',
	function () {
		return diamondistry_compact_html( diamondistry_render_login() );
	}
);

function diamondistry_render_lostpassword() {
	ob_start();
	?>
	<div class="dd-auth__card">
		<h1 class="dd-auth__title">Forgot your password?</h1>
		<p class="dd-auth__lead">No worries. Enter your email or username and we will send you a link to choose a new one.</p>
		<?php echo diamondistry_form_open( 'lostpassword' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'name'         => 'user_login',
					'label'        => 'Email or username',
					'value'        => diamondistry_post_field( 'user_login' ),
					'autocomplete' => 'username',
				)
			);
			?>
			<button class="dd-button dd-button-primary dd-button--block" type="submit">Send reset link</button>
		</form>
		<p class="dd-auth__switch"><a class="dd-link" href="<?php echo esc_url( wp_login_url() ); ?>">← Back to log in</a></p>
		<?php echo diamondistry_auth_privacy_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
	</div>
	<?php
	return ob_get_clean();
}

function diamondistry_render_resetpass() {
	$user = diamondistry_get_reset_user();

	ob_start();
	?>
	<div class="dd-auth__card">
		<h1 class="dd-auth__title">Choose a new password</h1>
		<?php if ( is_wp_error( $user ) ) : ?>
			<div class="dd-notice dd-notice--error" role="alert">This reset link is invalid or has expired.</div>
			<a class="dd-button dd-button-primary dd-button--block" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">Request a new link</a>
		<?php else : ?>
			<p class="dd-auth__lead">Pick something you have not used before. At least 8 characters.</p>
			<?php
			echo diamondistry_form_open( 'resetpass' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'name'         => 'pass1',
					'label'        => 'New password',
					'type'         => 'password',
					'autocomplete' => 'new-password',
				)
			);
			echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'name'         => 'pass2',
					'label'        => 'Repeat new password',
					'type'         => 'password',
					'autocomplete' => 'new-password',
				)
			);
			?>
				<button class="dd-button dd-button-primary dd-button--block" type="submit">Save password</button>
			</form>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/*
|--------------------------------------------------------------------------
| [diamondistry_register]
|--------------------------------------------------------------------------
*/

function diamondistry_render_register() {
	ob_start();
	?>
	<div class="dd-auth__card">
		<h1 class="dd-auth__title">Create your account</h1>
		<p class="dd-auth__lead">Save your progress, earn diamonds and build your collection.</p>
		<?php if ( ! get_option( 'users_can_register' ) ) : ?>
			<div class="dd-notice dd-notice--error" role="alert">Registration is currently closed.</div>
		<?php else : ?>
			<?php echo diamondistry_form_open( 'register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'name'         => 'user_login',
						'label'        => 'Username',
						'value'        => diamondistry_post_field( 'user_login' ),
						'autocomplete' => 'username',
						'hint'         => 'Letters, numbers, dots, dashes or underscores.',
					)
				);
				echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'name'         => 'user_email',
						'label'        => 'Email',
						'type'         => 'email',
						'value'        => diamondistry_post_field( 'user_email' ),
						'autocomplete' => 'email',
					)
				);
				echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'name'         => 'user_pass',
						'label'        => 'Password',
						'type'         => 'password',
						'autocomplete' => 'new-password',
						'hint'         => 'At least 8 characters.',
					)
				);
				?>
				<div class="dd-field dd-field--trap" aria-hidden="true">
					<label for="dd-website">Website</label>
					<input id="dd-website" name="website" type="text" tabindex="-1" autocomplete="off">
				</div>
				<?php if ( get_privacy_policy_url() ) : ?>
					<p class="dd-field__hint">By creating an account you agree to our <a class="dd-link" href="<?php echo esc_url( get_privacy_policy_url() ); ?>">privacy policy</a>.</p>
				<?php endif; ?>
				<button class="dd-button dd-button-primary dd-button--block" type="submit">Create account</button>
			</form>
		<?php endif; ?>
		<p class="dd-auth__switch">Already have an account? <a class="dd-link" href="<?php echo esc_url( wp_login_url() ); ?>">Log in</a></p>
		<?php echo diamondistry_auth_privacy_link(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the helper. ?>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode(
	'diamondistry_register',
	function () {
		return diamondistry_compact_html( diamondistry_render_register() );
	}
);

/*
|--------------------------------------------------------------------------
| [diamondistry_account]
|--------------------------------------------------------------------------
*/

function diamondistry_get_user_painting_counts( $user_id ) {
	$counts = array(
		'progress'   => 0,
		'completed'  => 0,
		'favourites' => count( diamondistry_get_user_favorite_ids( $user_id ) ),
	);

	foreach ( diamondistry_get_all_user_progress( $user_id ) as $painting_id => $progress ) {
		$painting = get_post( absint( $painting_id ) );
		if ( ! $painting || 'publish' !== $painting->post_status || ! is_array( $progress ) ) {
			continue;
		}

		$state = diamondistry_get_painting_card_state( $painting, $progress );
		if ( isset( $counts[ $state['status'] ] ) ) {
			++$counts[ $state['status'] ];
		}
	}

	return $counts;
}

function diamondistry_render_account() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$user    = wp_get_current_user();
	$counts  = diamondistry_get_user_painting_counts( $user->ID );
	$balance = diamondistry_get_user_balance( $user->ID );
	$updated = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$posted  = isset( $_POST['dd_form'] ) ? sanitize_key( wp_unslash( $_POST['dd_form'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

	// After a failed profile save, keep what the user typed.
	$value = function ( $key, $fallback ) use ( $posted ) {
		return 'profile' === $posted ? diamondistry_post_field( $key ) : $fallback;
	};

	$stats = array(
		array( 'Diamonds', number_format_i18n( $balance ), 'is-pink' ),
		array( 'Completed', $counts['completed'], 'is-green' ),
		array( 'In progress', $counts['progress'], 'is-purple' ),
		array( 'Favourites', $counts['favourites'], 'is-blue' ),
	);

	ob_start();
	?>
	<div class="dd-account">
		<header class="dd-account__head">
			<span class="dd-account__avatar"><?php echo get_avatar( $user->ID, 160 ); ?></span>
			<div class="dd-account__who">
				<p class="dd-eyebrow">My account</p>
				<h1 class="dd-account__name"><?php echo esc_html( $user->display_name ); ?></h1>
				<p class="dd-account__since">Member since <?php echo esc_html( date_i18n( 'F Y', strtotime( $user->user_registered ) ) ); ?></p>
			</div>
			<div class="dd-account__actions">
				<a class="dd-button dd-button-primary" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">My paintings <span aria-hidden="true">→</span></a>
				<a class="dd-button dd-button-secondary" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Log out</a>
			</div>
		</header>

		<ul class="dd-account__stats">
			<?php foreach ( $stats as $stat ) : ?>
				<li class="dd-stat <?php echo esc_attr( $stat[2] ); ?>">
					<strong class="dd-stat__value"><?php echo esc_html( $stat[1] ); ?></strong>
					<span class="dd-stat__label"><?php echo esc_html( $stat[0] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( diamondistry_is_demo_user() ) : ?>
			<div class="dd-notice dd-notice--info" role="status">This is the shared demo account: play as much as you like, but its settings are locked.</div>
		<?php endif; ?>

		<div class="dd-account__grid">
			<section class="dd-panel" aria-labelledby="dd-profile-title">
				<h2 class="dd-panel__title" id="dd-profile-title">Profile</h2>
				<p class="dd-panel__lead">Your photo, name and the line shown on your dashboard.</p>
				<?php if ( 'profile' === $updated ) : ?>
					<div class="dd-notice dd-notice--success" role="status">Your profile has been saved.</div>
				<?php elseif ( 'photo-removed' === $updated ) : ?>
					<div class="dd-notice dd-notice--success" role="status">Your photo has been removed.</div>
				<?php endif; ?>
				<?php echo diamondistry_form_open(
					'profile',
					// First submit button = what Enter triggers, so Enter saves instead of hitting "Remove".
					'<button class="dd-default-submit" type="submit" tabindex="-1" aria-hidden="true">Save profile</button>',
					'enctype="multipart/form-data"'
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div class="dd-avatar-field">
						<span class="dd-avatar-field__preview"><?php echo get_avatar( $user->ID, 160 ); ?></span>
						<div class="dd-avatar-field__body">
							<input class="dd-avatar-field__input" id="dd-avatar" type="file" name="dd_avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="dd-avatar-hint">
							<?php $has_photo = (bool) get_user_meta( $user->ID, DIAMONDISTRY_AVATAR_META_KEY, true ); ?>
							<label class="dd-button dd-button-secondary dd-button--sm" for="dd-avatar"><?php echo $has_photo ? 'Change photo' : 'Upload photo'; ?></label>
							<?php if ( $has_photo ) : ?>
								<button class="dd-text-button dd-text-button--danger" type="submit" name="dd_avatar_remove" value="1" formnovalidate>Remove</button>
							<?php endif; ?>
							<p class="dd-field__hint" id="dd-avatar-hint">JPG, PNG or WebP, max 2 MB.</p>
						</div>
					</div>
					<?php
					echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'name'      => 'tagline',
							'label'     => 'Your line',
							'value'     => $value( 'tagline', (string) get_user_meta( $user->ID, DIAMONDISTRY_TAGLINE_META_KEY, true ) ),
							'required'  => false,
							'maxlength' => DIAMONDISTRY_TAGLINE_MAX,
							'hint'      => 'Shown under your name on your dashboard. Leave empty for the default.',
						)
					);
					?>
					<div class="dd-form__cols">
						<?php
						echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							array(
								'name'         => 'first_name',
								'label'        => 'First name',
								'value'        => $value( 'first_name', $user->first_name ),
								'autocomplete' => 'given-name',
								'required'     => false,
							)
						);
						echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							array(
								'name'         => 'last_name',
								'label'        => 'Last name',
								'value'        => $value( 'last_name', $user->last_name ),
								'autocomplete' => 'family-name',
								'required'     => false,
							)
						);
						?>
					</div>
					<?php
					echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'name'         => 'display_name',
							'label'        => 'Display name',
							'value'        => $value( 'display_name', $user->display_name ),
							'autocomplete' => 'nickname',
						)
					);
					echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'name'         => 'user_email',
							'label'        => 'Email',
							'type'         => 'email',
							'value'        => $value( 'user_email', $user->user_email ),
							'autocomplete' => 'email',
						)
					);
					echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'name'         => 'current_pass',
							'label'        => 'Current password',
							'type'         => 'password',
							'autocomplete' => 'current-password',
							'required'     => false,
							'hint'         => 'Only needed when you change your email.',
						)
					);
					?>
					<?php if ( defined( 'DIAMONDISTRY_LEADERBOARD_HIDDEN_META_KEY' ) ) : ?>
						<label class="dd-check"><input type="checkbox" name="leaderboard_hidden" value="1" <?php checked( (bool) get_user_meta( $user->ID, DIAMONDISTRY_LEADERBOARD_HIDDEN_META_KEY, true ) ); ?>> Hide my name and photo on leaderboards</label>
					<?php endif; ?>
					<button class="dd-button dd-button-primary" type="submit">Save profile</button>
				</form>
			</section>

			<section class="dd-panel" aria-labelledby="dd-password-title">
				<h2 class="dd-panel__title" id="dd-password-title">Password</h2>
				<p class="dd-panel__lead">Use at least 8 characters. You stay logged in on this device.</p>
				<?php if ( 'password' === $updated ) : ?>
					<div class="dd-notice dd-notice--success" role="status">Your password has been changed.</div>
				<?php endif; ?>
				<?php
				echo diamondistry_form_open( 'password' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'name'         => 'current_pass',
						'label'        => 'Current password',
						'type'         => 'password',
						'autocomplete' => 'current-password',
					)
				);
				echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'name'         => 'pass1',
						'label'        => 'New password',
						'type'         => 'password',
						'autocomplete' => 'new-password',
					)
				);
				echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					array(
						'name'         => 'pass2',
						'label'        => 'Repeat new password',
						'type'         => 'password',
						'autocomplete' => 'new-password',
					)
				);
				?>
					<button class="dd-button dd-button-secondary" type="submit">Change password</button>
				</form>
			</section>
		</div>

		<?php echo function_exists( 'diamondistry_render_delete_account_panel' ) ? diamondistry_render_delete_account_panel() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
	return ob_get_clean();
}

add_shortcode(
	'diamondistry_account',
	function () {
		return diamondistry_compact_html( diamondistry_render_account() );
	}
);

/** Small privacy link under the login, register and password forms, once the page is published. */
function diamondistry_auth_privacy_link() {
	$url = get_privacy_policy_url();
	return $url ? '<p class="dd-auth__legal"><a href="' . esc_url( $url ) . '">Privacy policy</a></p>' : '';
}
