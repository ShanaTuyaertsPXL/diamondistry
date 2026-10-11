<?php
/**
 * Locked paintings: players spend diamonds to unlock them.
 *
 * Post meta `_diamondistry_unlock_cost` (int, 0 = free) is set in the painting editor.
 * User meta `_diamondistry_unlocked` stores unlocked painting IDs, for example [12, 34].
 * User meta `_diamondistry_unlock_paid` stores what was paid per painting, for example {"12": 100},
 * so locking again refunds exactly that, even if the price changed since.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIAMONDISTRY_UNLOCK_COST_META_KEY', '_diamondistry_unlock_cost' );
define( 'DIAMONDISTRY_UNLOCKED_META_KEY', '_diamondistry_unlocked' );
define( 'DIAMONDISTRY_UNLOCK_PAID_META_KEY', '_diamondistry_unlock_paid' );

function diamondistry_get_unlock_payments( $user_id ) {
	$raw = get_user_meta( $user_id, DIAMONDISTRY_UNLOCK_PAID_META_KEY, true );
	return is_array( $raw ) ? array_map( 'absint', $raw ) : array();
}

/** Lock a painting again and refund what the player paid for it. Returns the refund. */
function diamondistry_relock_painting( $user_id, $painting_id ) {
	$payments = diamondistry_get_unlock_payments( $user_id );
	$refund   = isset( $payments[ (string) $painting_id ] ) ? $payments[ (string) $painting_id ] : 0;

	unset( $payments[ (string) $painting_id ] );
	update_user_meta( $user_id, DIAMONDISTRY_UNLOCK_PAID_META_KEY, $payments );
	diamondistry_set_painting_unlocked( $user_id, $painting_id, false );

	if ( $refund ) {
		diamondistry_add_user_diamonds( $user_id, $refund );
	}

	return $refund;
}

function diamondistry_get_unlock_cost( $painting_id ) {
	return absint( get_post_meta( $painting_id, DIAMONDISTRY_UNLOCK_COST_META_KEY, true ) );
}

function diamondistry_get_user_unlocked_ids( $user_id ) {
	$raw = get_user_meta( $user_id, DIAMONDISTRY_UNLOCKED_META_KEY, true );
	return is_array( $raw ) ? array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) ) : array();
}

/**
 * Free paintings, unlocked ones, and ones a player already started (so adding a
 * price later never takes away work). Same rules for every account, admins too:
 * to test, unlock or add diamonds for yourself under Users → Players.
 */
function diamondistry_user_can_play( $user_id, $painting ) {
	$painting_id = is_object( $painting ) ? (int) $painting->ID : absint( $painting );

	if ( 0 === diamondistry_get_unlock_cost( $painting_id ) ) {
		return true;
	}

	// The weekly challenge painting is free for everyone during its week.
	if ( function_exists( 'diamondistry_is_current_challenge' ) && diamondistry_is_current_challenge( $painting_id ) ) {
		return true;
	}

	if ( ! $user_id ) {
		return false;
	}

	if ( in_array( $painting_id, diamondistry_get_user_unlocked_ids( $user_id ), true ) ) {
		return true;
	}

	// A priced challenge painting was free only during its week: starting it then must not keep it free.
	if ( get_post_meta( $painting_id, '_diamondistry_challenge_start', true ) ) {
		return false;
	}

	$progress = diamondistry_get_all_user_progress( $user_id );
	return ! empty( $progress[ (string) $painting_id ]['completedCells'] );
}

function diamondistry_set_painting_unlocked( $user_id, $painting_id, $unlocked ) {
	$ids = diamondistry_get_user_unlocked_ids( $user_id );
	$ids = $unlocked
		? array_values( array_unique( array_merge( $ids, array( absint( $painting_id ) ) ) ) )
		: array_values( array_diff( $ids, array( absint( $painting_id ) ) ) );

	update_user_meta( $user_id, DIAMONDISTRY_UNLOCKED_META_KEY, $ids );
}

/*
|--------------------------------------------------------------------------
| AJAX: unlock a painting
|--------------------------------------------------------------------------
*/

function diamondistry_ajax_unlock_painting() {
	check_ajax_referer( 'diamondistry_unlock', 'nonce' );

	if ( ! is_user_logged_in() ) {
		wp_send_json_error( array( 'message' => 'Log in to unlock paintings.' ), 401 );
	}

	$user_id     = get_current_user_id();
	$painting_id = isset( $_POST['paintingId'] ) ? absint( $_POST['paintingId'] ) : 0;
	$painting    = $painting_id ? get_post( $painting_id ) : null;

	if ( ! $painting || 'ddp_painting' !== $painting->post_type || 'publish' !== $painting->post_status ) {
		wp_send_json_error( array( 'message' => 'Painting not available.' ), 404 );
	}

	// Lock the user row (also refreshes the meta cache): two quick clicks must not charge twice.
	$locked = diamondistry_begin_progress_write( $user_id );

	if ( diamondistry_user_can_play( $user_id, $painting ) ) {
		diamondistry_commit_progress_write( $locked );
		wp_send_json_success( array( 'balance' => diamondistry_get_user_balance( $user_id ) ) );
	}

	$cost    = diamondistry_get_unlock_cost( $painting_id );
	$balance = diamondistry_get_user_balance( $user_id );

	if ( $balance < $cost ) {
		diamondistry_commit_progress_write( $locked );
		wp_send_json_error( array( 'message' => sprintf( 'You need %s more diamonds.', number_format_i18n( $cost - $balance ) ) ), 402 );
	}

	update_user_meta( $user_id, DIAMONDISTRY_BALANCE_META_KEY, $balance - $cost );
	diamondistry_set_painting_unlocked( $user_id, $painting_id, true );

	$payments                         = diamondistry_get_unlock_payments( $user_id );
	$payments[ (string) $painting_id ] = $cost;
	update_user_meta( $user_id, DIAMONDISTRY_UNLOCK_PAID_META_KEY, $payments );
	diamondistry_commit_progress_write( $locked );

	wp_send_json_success( array( 'balance' => $balance - $cost ) );
}

add_action( 'wp_ajax_diamondistry_unlock_painting', 'diamondistry_ajax_unlock_painting' );

/*
|--------------------------------------------------------------------------
| Locked view on the play page
|--------------------------------------------------------------------------
*/

function diamondistry_render_locked_painting( $painting ) {
	$cost      = diamondistry_get_unlock_cost( $painting->ID );
	$logged_in = is_user_logged_in();
	$balance   = $logged_in ? diamondistry_get_user_balance( get_current_user_id() ) : 0;
	$short     = max( 0, $cost - $balance );
	$here      = add_query_arg( 'painting', $painting->post_name, home_url( '/play/' ) );
	$cover     = get_the_post_thumbnail_url( $painting, 'large' );
	$category  = diamondistry_get_painting_category_label( $painting->ID );

	if ( $logged_in && ! $short ) {
		wp_enqueue_script( 'diamondistry-unlock', DIAMONDISTRY_CORE_URL . 'assets/js/unlock.js', array(), DIAMONDISTRY_CORE_VERSION, true );
		wp_localize_script(
			'diamondistry-unlock',
			'DiamondistryUnlock',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'diamondistry_unlock' ),
				'paintingId' => $painting->ID,
			)
		);
	}

	ob_start();
	?>
	<div class="diamondistry-app diamondistry-locked">
		<nav class="diamondistry-breadcrumb" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/paintings/' ) ); ?>">Paintings</a>
			<?php if ( $category ) : ?>
				<span aria-hidden="true">/</span><span><?php echo esc_html( $category ); ?></span>
			<?php endif; ?>
		</nav>

		<div class="diamondistry-locked__card">
			<div class="diamondistry-locked__art">
				<?php if ( $cover ) : ?>
					<img src="<?php echo esc_url( $cover ); ?>" alt="">
				<?php else : ?>
					<?php echo diamondistry_render_painting_preview( $painting, null ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
				<span class="diamondistry-locked__lock" aria-hidden="true">
					<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
				</span>
			</div>

			<div class="diamondistry-locked__body">
				<p class="diamondistry-locked__eyebrow">Locked painting</p>
				<h1><?php echo esc_html( get_the_title( $painting ) ); ?></h1>
				<p class="diamondistry-locked__price"><strong><?php echo esc_html( number_format_i18n( $cost ) ); ?> <?php echo diamondistry_gem(); ?></strong> to unlock</p>

				<?php if ( ! $logged_in ) : ?>
					<p class="diamondistry-locked__text">Log in to unlock this painting with the diamonds you earn from finished paintings.</p>
					<div class="diamondistry-locked__actions">
						<a class="dd-button dd-button-primary" href="<?php echo esc_url( wp_login_url( $here ) ); ?>">Log in to unlock</a>
						<a class="dd-button dd-button-secondary" href="<?php echo esc_url( wp_registration_url() ); ?>">Create account</a>
					</div>
				<?php elseif ( $short ) : ?>
					<p class="diamondistry-locked__text">You have <?php echo esc_html( number_format_i18n( $balance ) ); ?> <?php echo diamondistry_gem(); ?>. You need <strong><?php echo esc_html( number_format_i18n( $short ) ); ?> more</strong>. Finish a free painting to earn them.</p>
					<div class="diamondistry-locked__actions">
						<a class="dd-button dd-button-primary" href="<?php echo esc_url( home_url( '/paintings/' ) ); ?>">Find a painting <span aria-hidden="true">→</span></a>
					</div>
				<?php else : ?>
					<p class="diamondistry-locked__text">You have <?php echo esc_html( number_format_i18n( $balance ) ); ?> <?php echo diamondistry_gem(); ?>. After unlocking you keep <?php echo esc_html( number_format_i18n( $balance - $cost ) ); ?> <?php echo diamondistry_gem(); ?>, and the painting is yours to play forever.</p>
					<div class="diamondistry-locked__actions">
						<button type="button" class="dd-button dd-button-primary" data-unlock>Unlock for <?php echo esc_html( number_format_i18n( $cost ) ); ?> <?php echo diamondistry_gem(); ?></button>
					</div>
					<p class="diamondistry-locked__error" role="alert" hidden></p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/*
|--------------------------------------------------------------------------
| Admin: manage unlocks per player (Users → Players → player detail)
|--------------------------------------------------------------------------
*/

function diamondistry_handle_unlock_admin_actions() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) || empty( $_POST['diamondistry_player_action'] ) ) {
		return;
	}

	$action = sanitize_key( wp_unslash( $_POST['diamondistry_player_action'] ) );
	if ( ! in_array( $action, array( 'lock_painting', 'reset_unlocks' ), true ) ) {
		return;
	}

	check_admin_referer( 'diamondistry_player_admin_action', 'diamondistry_player_nonce' );

	$user_id     = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
	$painting_id = isset( $_POST['painting_id'] ) ? absint( $_POST['painting_id'] ) : 0;

	if ( ! $user_id || ! get_user_by( 'id', $user_id ) ) {
		return;
	}

	if ( 'reset_unlocks' === $action ) {
		foreach ( diamondistry_get_user_unlocked_ids( $user_id ) as $unlocked_id ) {
			diamondistry_relock_painting( $user_id, $unlocked_id );
		}
		delete_user_meta( $user_id, DIAMONDISTRY_UNLOCK_PAID_META_KEY );
		diamondistry_players_redirect( $user_id, 'unlocks-reset' );
	}

	if ( $painting_id && 'lock_painting' === $action ) {
		diamondistry_relock_painting( $user_id, $painting_id );
		diamondistry_players_redirect( $user_id, 'painting-locked' );
	}
}

add_action( 'admin_init', 'diamondistry_handle_unlock_admin_actions', 5 );

function diamondistry_render_player_unlocks( $user_id ) {
	$unlocked = diamondistry_get_user_unlocked_ids( $user_id );
	$payments = diamondistry_get_unlock_payments( $user_id );

	$form = function ( $action, $painting_id, $label ) use ( $user_id ) {
		?>
		<form method="post" style="display:inline-block;">
			<?php wp_nonce_field( 'diamondistry_player_admin_action', 'diamondistry_player_nonce' ); ?>
			<input type="hidden" name="diamondistry_player_action" value="<?php echo esc_attr( $action ); ?>">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
			<input type="hidden" name="painting_id" value="<?php echo esc_attr( $painting_id ); ?>">
			<button type="submit" class="button"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	};
	?>
	<hr>
	<h2>Unlocks</h2>

	<?php if ( empty( $unlocked ) ) : ?>
		<p><em>This player has not unlocked any paintings.</em></p>
	<?php else : ?>
		<p>Paintings this player unlocked. Locking again refunds what they paid for it.</p>
		<table class="widefat striped" style="max-width:900px;">
			<thead>
				<tr><th>Painting</th><th>Paid</th><th>Action</th></tr>
			</thead>
			<tbody>
				<?php foreach ( $unlocked as $painting_id ) : ?>
					<?php $paid = isset( $payments[ (string) $painting_id ] ) ? $payments[ (string) $painting_id ] : 0; ?>
					<tr>
						<td><?php echo esc_html( function_exists( 'diamondistry_get_painting_title_by_id' ) ? diamondistry_get_painting_title_by_id( $painting_id ) : get_the_title( $painting_id ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $paid ) ); ?> 💎</td>
						<td><?php $form( 'lock_painting', $painting_id, $paid ? sprintf( 'Lock again (refund %s 💎)', number_format_i18n( $paid ) ) : 'Lock again' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><?php $form( 'reset_unlocks', 0, 'Reset all unlocks' ); ?></p>
	<?php endif; ?>
	<?php
}

add_action( 'diamondistry_player_detail_after_paintings', 'diamondistry_render_player_unlocks' );
