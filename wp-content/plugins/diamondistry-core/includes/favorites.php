<?php
/**
 * Saved favourite paintings.
 *
 * User meta `_diamondistry_favorites` stores a list of painting post IDs,
 * for example [12, 34].
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIAMONDISTRY_FAVORITES_META_KEY', '_diamondistry_favorites' );

function diamondistry_get_user_favorite_ids( $user_id ) {
	$raw = get_user_meta( $user_id, DIAMONDISTRY_FAVORITES_META_KEY, true );

	if ( ! is_array( $raw ) ) {
		return array();
	}

	$ids = array();
	foreach ( $raw as $id ) {
		$id = absint( $id );
		if ( $id ) {
			$ids[] = $id;
		}
	}

	return array_values( array_unique( $ids ) );
}

function diamondistry_ajax_toggle_favorite() {
	check_ajax_referer( 'diamondistry_favorite', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'You must be logged in.' ), 401 );
	}

	$painting_id = isset( $_POST['paintingId'] ) ? absint( $_POST['paintingId'] ) : 0;
	$painting    = $painting_id ? get_post( $painting_id ) : null;

	if (
		! $painting ||
		'ddp_painting' !== $painting->post_type ||
		'publish' !== $painting->post_status
	) {
		wp_send_json_error( array( 'message' => 'Painting not available.' ), 404 );
	}

	$user_id   = get_current_user_id();
	$favorites = diamondistry_get_user_favorite_ids( $user_id );

	if ( in_array( $painting_id, $favorites, true ) ) {
		$favorites   = array_values( array_diff( $favorites, array( $painting_id ) ) );
		$is_favorite = false;
	} else {
		$favorites[] = $painting_id;
		$is_favorite = true;
	}

	update_user_meta( $user_id, DIAMONDISTRY_FAVORITES_META_KEY, $favorites );

	wp_send_json_success(
		array(
			'favorite'  => $is_favorite,
			'favorites' => $favorites,
		)
	);
}

add_action( 'wp_ajax_diamondistry_toggle_favorite', 'diamondistry_ajax_toggle_favorite' );
