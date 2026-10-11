<?php
/**
 * Privacy: players can delete their own account, and Tools → Export / Erase
 * Personal Data covers everything Diamondistry stores per user.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Every user meta key the game writes. */
function diamondistry_privacy_meta_keys() {
	return array(
		DIAMONDISTRY_PROGRESS_META_KEY,
		DIAMONDISTRY_BALANCE_META_KEY,
		DIAMONDISTRY_FAVORITES_META_KEY,
		DIAMONDISTRY_UNLOCKED_META_KEY,
		DIAMONDISTRY_UNLOCK_PAID_META_KEY,
		DIAMONDISTRY_AVATAR_META_KEY,
		DIAMONDISTRY_TAGLINE_META_KEY,
	);
}

/*
|--------------------------------------------------------------------------
| Delete own account (frontend)
|--------------------------------------------------------------------------
*/

function diamondistry_handle_delete_account() {
	$user = wp_get_current_user();

	// Admins and editors manage their account in wp-admin, never by accident from here.
	if ( current_user_can( 'edit_posts' ) ) {
		diamondistry_form_error( 'delete', 'Staff accounts cannot be deleted here. Use wp-admin instead.' );
		return;
	}

	if ( empty( $_POST['confirm_delete'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- checked in dispatcher.
		diamondistry_form_error( 'delete', 'Tick the box to confirm that you want to delete your account.' );
		return;
	}

	if ( ! wp_check_password( diamondistry_post_password( 'current_pass' ), $user->user_pass, $user->ID ) ) {
		diamondistry_form_error( 'delete', 'Your password is not correct.' );
		return;
	}

	require_once ABSPATH . 'wp-admin/includes/user.php';

	// The delete_user hook removes the uploaded photo; user meta goes with the user.
	wp_logout();
	wp_delete_user( $user->ID );

	wp_safe_redirect( diamondistry_page_url( 'login', array( 'account' => 'deleted' ) ) );
	exit;
}

function diamondistry_render_delete_account_panel() {
	if ( current_user_can( 'edit_posts' ) ) {
		return '';
	}

	ob_start();
	?>
	<section class="dd-panel dd-panel--danger" aria-labelledby="dd-delete-title">
		<h2 class="dd-panel__title" id="dd-delete-title">Delete account</h2>
		<p class="dd-panel__lead">This permanently removes your account, progress, diamonds, unlocks, favourites and photo. This cannot be undone.</p>
		<?php
		echo diamondistry_form_open( 'delete' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo diamondistry_field( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'name'         => 'current_pass',
				'label'        => 'Your password',
				'type'         => 'password',
				'autocomplete' => 'current-password',
			)
		);
		?>
			<label class="dd-check"><input type="checkbox" name="confirm_delete" value="1" required> I understand this deletes everything</label>
			<button class="dd-button dd-button--danger" type="submit">Delete my account</button>
		</form>
	</section>
	<?php
	return ob_get_clean();
}

/*
|--------------------------------------------------------------------------
| Tools → Export Personal Data
|--------------------------------------------------------------------------
*/

function diamondistry_privacy_exporter( $email ) {
	$user = get_user_by( 'email', $email );
	if ( ! $user ) {
		return array(
			'data' => array(),
			'done' => true,
		);
	}

	$title = function ( $id ) {
		$post = get_post( absint( $id ) );
		return $post ? get_the_title( $post ) : sprintf( 'Painting #%d', absint( $id ) );
	};

	$profile = array(
		array(
			'name'  => 'Diamond balance',
			'value' => diamondistry_get_user_balance( $user->ID ),
		),
		array(
			'name'  => 'Dashboard line',
			'value' => (string) get_user_meta( $user->ID, DIAMONDISTRY_TAGLINE_META_KEY, true ),
		),
		array(
			'name'  => 'Favourite paintings',
			'value' => implode( ', ', array_map( $title, diamondistry_get_user_favorite_ids( $user->ID ) ) ),
		),
	);

	$photo_id = (int) get_user_meta( $user->ID, DIAMONDISTRY_AVATAR_META_KEY, true );
	if ( $photo_id ) {
		$profile[] = array(
			'name'  => 'Profile photo',
			'value' => (string) wp_get_attachment_url( $photo_id ),
		);
	}

	$data = array(
		array(
			'group_id'    => 'diamondistry-profile',
			'group_label' => 'Diamondistry profile',
			'item_id'     => 'diamondistry-profile-' . $user->ID,
			'data'        => $profile,
		),
	);

	foreach ( diamondistry_get_all_user_progress( $user->ID ) as $painting_id => $progress ) {
		if ( ! is_array( $progress ) ) {
			continue;
		}
		$worked = diamondistry_get_progress_last_worked_at( $progress );
		$data[] = array(
			'group_id'    => 'diamondistry-progress',
			'group_label' => 'Diamondistry painting progress',
			'item_id'     => 'diamondistry-progress-' . absint( $painting_id ),
			'data'        => array(
				array(
					'name'  => 'Painting',
					'value' => $title( $painting_id ),
				),
				array(
					'name'  => 'Diamonds placed',
					'value' => isset( $progress['completedCells'] ) && is_array( $progress['completedCells'] ) ? count( $progress['completedCells'] ) : 0,
				),
				array(
					'name'  => 'Completed',
					'value' => ! empty( $progress['completed'] ) ? 'Yes' : 'No',
				),
				array(
					'name'  => 'Reward claimed',
					'value' => ! empty( $progress['rewardClaimed'] ) ? 'Yes' : 'No',
				),
				array(
					'name'  => 'Last worked on',
					'value' => $worked ? wp_date( 'Y-m-d H:i', $worked ) : '',
				),
			),
		);
	}

	$payments = diamondistry_get_unlock_payments( $user->ID );
	foreach ( diamondistry_get_user_unlocked_ids( $user->ID ) as $painting_id ) {
		$data[] = array(
			'group_id'    => 'diamondistry-unlocks',
			'group_label' => 'Diamondistry unlocked paintings',
			'item_id'     => 'diamondistry-unlock-' . $painting_id,
			'data'        => array(
				array(
					'name'  => 'Painting',
					'value' => $title( $painting_id ),
				),
				array(
					'name'  => 'Diamonds paid',
					'value' => isset( $payments[ (string) $painting_id ] ) ? $payments[ (string) $painting_id ] : 0,
				),
			),
		);
	}

	return array(
		'data' => $data,
		'done' => true,
	);
}

add_filter(
	'wp_privacy_personal_data_exporters',
	function ( $exporters ) {
		$exporters['diamondistry'] = array(
			'exporter_friendly_name' => 'Diamondistry game data',
			'callback'               => 'diamondistry_privacy_exporter',
		);
		return $exporters;
	}
);

/*
|--------------------------------------------------------------------------
| Tools → Erase Personal Data
|--------------------------------------------------------------------------
*/

function diamondistry_privacy_eraser( $email ) {
	$user    = get_user_by( 'email', $email );
	$removed = false;

	if ( $user ) {
		diamondistry_delete_user_avatar( $user->ID );
		foreach ( diamondistry_privacy_meta_keys() as $key ) {
			$removed = delete_user_meta( $user->ID, $key ) || $removed;
		}
	}

	return array(
		'items_removed'  => $removed,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => true,
	);
}

add_filter(
	'wp_privacy_personal_data_erasers',
	function ( $erasers ) {
		$erasers['diamondistry'] = array(
			'eraser_friendly_name' => 'Diamondistry game data',
			'callback'             => 'diamondistry_privacy_eraser',
		);
		return $erasers;
	}
);
