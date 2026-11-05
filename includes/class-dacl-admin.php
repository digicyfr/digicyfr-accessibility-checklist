<?php
/**
 * Private administrator forms, filtered records, and guarded CSV.
 *
 * @package Digicyfr_Accessibility_Checklist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Admin-only interface for human observations. */
final class DACL_Admin {
	private const PAGE       = 'digicyfr-accessibility-checklist';
	private const PAGE_SIZE  = 50;
	private const CSV_BATCH  = 500;
	private const MAX_EXPORT = 20000;

	/** Register only private admin hooks. */
	public static function boot(): void {
		add_action( 'init', array( __CLASS__, 'translations' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		foreach ( array( 'save', 'delete', 'export' ) as $action ) {
			add_action( 'admin_post_dacl_' . $action, array( __CLASS__, 'handle' ) );
		}
	}

	/** Load the bundled Polish catalog for self-hosted distribution. */
	public static function translations(): void {
		load_plugin_textdomain( 'digicyfr-accessibility-checklist', false, dirname( plugin_basename( DIGICYFR_ACCESSIBILITY_CHECKLIST_PATH . 'digicyfr-accessibility-checklist.php' ) ) . '/languages' );
	}

	/** Add one private menu for administrators. */
	public static function menu(): void {
		add_menu_page( __( 'Accessibility Checklist', 'digicyfr-accessibility-checklist' ), __( 'Accessibility Checks', 'digicyfr-accessibility-checklist' ), 'manage_options', self::PAGE, array( __CLASS__, 'screen' ), 'dashicons-yes-alt', 83 );
	}

	/** Restrict local assets to this menu screen. */
	public static function assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_' . self::PAGE !== $screen->id ) {
			return;
		}
		wp_enqueue_style( 'dacl-admin', DIGICYFR_ACCESSIBILITY_CHECKLIST_URL . 'assets/admin.css', array(), DIGICYFR_ACCESSIBILITY_CHECKLIST_VERSION );
		wp_enqueue_script( 'dacl-admin', DIGICYFR_ACCESSIBILITY_CHECKLIST_URL . 'assets/admin.js', array(), DIGICYFR_ACCESSIBILITY_CHECKLIST_VERSION, true );
	}

	/**
	 * Make a fixed private URL.
	 *
	 * @param array $args args input.
	 */
	private static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Allowlisted read-only filters, shared with CSV.
	 *
	 * @param array $raw raw input.
	 */
	public static function filters( array $raw ): array {
		$value    = static fn( string $key ): string => isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ? $raw[ $key ] : '';
		$kind     = sanitize_key( $value( 'scope_kind' ) );
		$test     = sanitize_key( $value( 'test_key' ) );
		$outcome  = sanitize_key( $value( 'outcome' ) );
		$assignee = trim( $value( 'assignee_user_id' ) );
		$date     = trim( $value( 'tested_on' ) );
		$search   = sanitize_text_field( trim( $value( 'scope_search' ) ) );
		if ( ! in_array( $kind, array( 'page', 'journey' ), true ) ) {
			$kind = '';
		}
		if ( ! array_key_exists( $test, DACL_Model::checks() ) ) {
			$test = '';
		}
		if ( ! array_key_exists( $outcome, DACL_Model::outcomes() ) ) {
			$outcome = '';
		}
		if ( '' !== $assignee && ! preg_match( '/^(0|[1-9][0-9]{0,9})$/', $assignee ) ) {
			$assignee = '';
		}
		if ( '' !== $date && ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts ) || ! checkdate( (int) ( $parts[2] ?? 0 ), (int) ( $parts[3] ?? 0 ), (int) ( $parts[1] ?? 0 ) ) ) ) {
			$date = '';
		}
		return array(
			'scope_kind'       => $kind,
			'test_key'         => $test,
			'outcome'          => $outcome,
			'assignee_user_id' => $assignee,
			'tested_on'        => $date,
			'scope_search'     => self::utf8_cap( $search, 100 ),
		);
	}

	/**
	 * Cap a search string by Unicode characters without byte truncation.
	 *
	 * @param string $text text input.
	 * @param int    $limit limit input.
	 */
	private static function utf8_cap( string $text, int $limit ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $limit, 'UTF-8' );
		}
		preg_match_all( '/./us', $text, $chars );
		return implode( '', array_slice( $chars[0], 0, $limit ) );
	}

	/**
	 * Build the identical private query for list and export.
	 *
	 * @param array $filters filters input.
	 * @param int   $page page input.
	 * @param int   $per_page per page input.
	 */
	public static function query_args( array $filters, int $page, int $per_page ): array {
		$meta = array( 'relation' => 'AND' );
		foreach ( array( 'scope_kind', 'test_key', 'outcome', 'assignee_user_id', 'tested_on' ) as $key ) {
			if ( '' !== $filters[ $key ] ) {
				$meta[] = array(
					'key'     => '_dacl_' . $key,
					'value'   => $filters[ $key ],
					'compare' => '=',
				);
			}
		}
		return array(
			'post_type'           => DACL_Model::TYPE,
			'post_status'         => 'private',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'fields'              => 'ids',
			'orderby'             => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
			's'                   => $filters['scope_search'],
			'meta_query'          => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Allowlisted private record filters, paged at 50 rows in the UI and capped for CSV.
		);
	}

	/** Render the capability-protected screen. */
	public static function screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot view private observations.', 'digicyfr-accessibility-checklist' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selection.
		$action = isset( $_GET['dacl_view'] ) && is_string( $_GET['dacl_view'] ) ? sanitize_key( wp_unslash( $_GET['dacl_view'] ) ) : 'list';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only record selection.
		$id = isset( $_GET['record_id'] ) && is_scalar( $_GET['record_id'] ) ? absint( $_GET['record_id'] ) : 0;
		?>
		<div class="wrap dacl-wrap">
			<h1><?php echo esc_html__( 'Accessibility Checklist', 'digicyfr-accessibility-checklist' ); ?></h1>
			<p><?php echo esc_html__( 'Record human observations for a chosen page or journey. Unrecorded checks are unknown. These six prompts are not a conformance evaluation, score, or certificate.', 'digicyfr-accessibility-checklist' ); ?> <a href="https://www.w3.org/WAI/test-evaluate/" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'W3C evaluation resources (opens in a new tab)', 'digicyfr-accessibility-checklist' ); ?></a></p>
			<?php self::notice(); ?>
			<?php
			if ( 'new' === $action || 'edit' === $action ) {
				self::form( 'edit' === $action ? $id : 0 );
			} elseif ( 'delete' === $action ) {
				self::delete_confirmation( $id );
			} else {
				self::listing();
			}
			?>
		</div>
		<?php
	}

	/** Announce a successful mutation after redirect. */
	private static function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag after safe redirect.
		$notice = isset( $_GET['dacl_notice'] ) && is_string( $_GET['dacl_notice'] ) ? sanitize_key( wp_unslash( $_GET['dacl_notice'] ) ) : '';
		$text   = match ( $notice ) {
			'saved' => __( 'Observation saved.', 'digicyfr-accessibility-checklist' ),
			'deleted' => __( 'Observation deleted.', 'digicyfr-accessibility-checklist' ),
			'failed' => __( 'The action failed. Check permissions and try again.', 'digicyfr-accessibility-checklist' ),
			default => '',
		};
		if ( '' !== $text ) {
			$kind = 'failed' === $notice ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $kind ) . ' dacl-result" tabindex="-1" role="' . ( 'failed' === $notice ? 'alert' : 'status' ) . '"><p>' . esc_html( $text ) . '</p></div>';
		}
	}

	/** Read a one-time validation state belonging to the current administrator. */
	private static function form_state(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- One-time display state after a nonce-checked save.
		$token = isset( $_GET['dacl_state'] ) && is_string( $_GET['dacl_state'] ) ? sanitize_key( wp_unslash( $_GET['dacl_state'] ) ) : '';
		if ( '' === $token ) {
			return array();
		}
		$key   = 'dacl_form_' . get_current_user_id() . '_' . $token;
		$state = get_transient( $key );
		delete_transient( $key );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Link a field error to its control.
	 *
	 * @param array  $errors errors input.
	 * @param string $key key input.
	 */
	private static function error( array $errors, string $key ): void {
		if ( isset( $errors[ $key ] ) ) {
			echo '<span class="dacl-error" id="dacl-error-' . esc_attr( $key ) . '">' . esc_html( $errors[ $key ] ) . '</span>';
		}
	}

	/**
	 * Mark a failing control and connect its field-specific message.
	 *
	 * @param array  $errors errors input.
	 * @param string $key key input.
	 */
	private static function aria( array $errors, string $key ): void {
		if ( isset( $errors[ $key ] ) ) {
			echo 'aria-invalid="true" aria-describedby="dacl-error-' . esc_attr( $key ) . '"';
		}
	}

	/**
	 * Use a labelled, no-JavaScript form for one new or existing record.
	 *
	 * @param int $id id input.
	 */
	private static function form( int $id ): void {
		$record = 0 === $id ? null : DACL_Model::get( $id );
		if ( 0 !== $id && ! $record ) {
			wp_die( esc_html__( 'Observation not found.', 'digicyfr-accessibility-checklist' ) );
		}
		$state  = self::form_state();
		$values = array_merge(
			array(
				'scope_kind'       => 'page',
				'scope_label'      => '',
				'scope_post_id'    => '',
				'scope_url'        => '',
				'test_key'         => 'keyboard',
				'outcome'          => 'not_tested',
				'assignee_user_id' => '0',
				'tested_on'        => '',
				'note'             => '',
				'evidence_url'     => '',
			),
			$record ?? array(),
			$state['input'] ?? array()
		);
		$errors = $state['errors'] ?? array();
		?>
		<p><a href="<?php echo esc_url( self::url() ); ?>"><?php echo esc_html__( 'Back to observations', 'digicyfr-accessibility-checklist' ); ?></a></p>
		<h2><?php echo esc_html( 0 === $id ? __( 'New observation', 'digicyfr-accessibility-checklist' ) : __( 'Edit observation', 'digicyfr-accessibility-checklist' ) ); ?></h2>
		<?php if ( $record ) : ?>
			<p><?php echo esc_html__( 'Created (site time):', 'digicyfr-accessibility-checklist' ); ?> <?php echo esc_html( wp_date( 'Y-m-d H:i', strtotime( $record['created_at_utc'] . ' UTC' ) ) ); ?> · <?php echo esc_html__( 'Updated (site time):', 'digicyfr-accessibility-checklist' ); ?> <?php echo esc_html( wp_date( 'Y-m-d H:i', strtotime( $record['updated_at_utc'] . ' UTC' ) ) ); ?></p>
		<?php endif; ?>
		<?php if ( array() !== $errors ) : ?>
			<div class="notice notice-error dacl-errors" role="alert" tabindex="-1"><p><?php echo esc_html__( 'Correct these fields and save again:', 'digicyfr-accessibility-checklist' ); ?></p><ul>
			<?php
			foreach ( $errors as $key => $message ) :
				?>
				<li><a href="#dacl-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $message ); ?></a></li><?php endforeach; ?>
			</ul></div>
		<?php endif; ?>
		<form class="dacl-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="dacl_save" /><input type="hidden" name="record_id" value="<?php echo esc_attr( (string) $id ); ?>" /><?php wp_nonce_field( 'dacl_save_' . $id ); ?>
			<label for="dacl-scope_kind"><?php echo esc_html__( 'Scope type', 'digicyfr-accessibility-checklist' ); ?> <strong><?php echo esc_html__( 'Required', 'digicyfr-accessibility-checklist' ); ?></strong></label>
			<select id="dacl-scope_kind" name="scope_kind" <?php self::aria( $errors, 'scope_kind' ); ?>><option value="page" <?php selected( $values['scope_kind'], 'page' ); ?>><?php echo esc_html__( 'Page or post', 'digicyfr-accessibility-checklist' ); ?></option><option value="journey" <?php selected( $values['scope_kind'], 'journey' ); ?>><?php echo esc_html__( 'Journey', 'digicyfr-accessibility-checklist' ); ?></option></select><?php self::error( $errors, 'scope_kind' ); ?>
			<label for="dacl-scope_label"><?php echo esc_html__( 'Page or task name', 'digicyfr-accessibility-checklist' ); ?> <strong><?php echo esc_html__( 'Required', 'digicyfr-accessibility-checklist' ); ?></strong></label>
			<input id="dacl-scope_label" name="scope_label" type="text" maxlength="160" value="<?php echo esc_attr( (string) $values['scope_label'] ); ?>" <?php self::aria( $errors, 'scope_label' ); ?> /><?php self::error( $errors, 'scope_label' ); ?>
			<label for="dacl-scope_post_id"><?php echo esc_html__( 'Published page or post ID (page scope only)', 'digicyfr-accessibility-checklist' ); ?></label>
			<input id="dacl-scope_post_id" name="scope_post_id" type="number" min="1" value="<?php echo esc_attr( (string) $values['scope_post_id'] ); ?>" <?php self::aria( $errors, 'scope_post_id' ); ?> /><?php self::error( $errors, 'scope_post_id' ); ?>
			<label for="dacl-scope_url"><?php echo esc_html__( 'Same-site URL (required for journey or page without ID)', 'digicyfr-accessibility-checklist' ); ?></label>
			<input id="dacl-scope_url" name="scope_url" type="url" maxlength="2048" value="<?php echo esc_attr( (string) $values['scope_url'] ); ?>" <?php self::aria( $errors, 'scope_url' ); ?> /><?php self::error( $errors, 'scope_url' ); ?>
			<p class="description"><?php echo esc_html__( 'For a page ID, its URL is saved automatically. The recorded URL is kept when its permalink later changes.', 'digicyfr-accessibility-checklist' ); ?></p>
			<label for="dacl-test_key"><?php echo esc_html__( 'Guided check', 'digicyfr-accessibility-checklist' ); ?> <strong><?php echo esc_html__( 'Required', 'digicyfr-accessibility-checklist' ); ?></strong></label>
			<select id="dacl-test_key" name="test_key" <?php self::aria( $errors, 'test_key' ); ?>>
			<?php
			foreach ( DACL_Model::checks() as $key => $check ) :
				?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $values['test_key'], $key ); ?>><?php echo esc_html( $check[0] ); ?></option><?php endforeach; ?></select><?php self::error( $errors, 'test_key' ); ?>
			<div class="dacl-guidance" aria-label="<?php echo esc_attr__( 'Human check instructions', 'digicyfr-accessibility-checklist' ); ?>"><p><?php echo esc_html__( 'Choose a meaningful scope and record what you actually tested. These prompts do not establish WCAG conformance.', 'digicyfr-accessibility-checklist' ); ?></p><ul>
			<?php
			foreach ( DACL_Model::checks() as $key => $check ) :
				?>
				<li data-check="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $check[0] ); ?>:</strong> <?php echo esc_html( $check[1] ); ?> <a href="<?php echo esc_url( $check[2] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__( 'W3C guidance (opens in a new tab)', 'digicyfr-accessibility-checklist' ); ?></a></li><?php endforeach; ?></ul></div>
			<fieldset id="dacl-outcome" <?php self::aria( $errors, 'outcome' ); ?>><legend><?php echo esc_html__( 'Observed outcome', 'digicyfr-accessibility-checklist' ); ?> <strong><?php echo esc_html__( 'Required', 'digicyfr-accessibility-checklist' ); ?></strong></legend>
			<?php
			foreach ( DACL_Model::outcomes() as $key => $label ) :
				?>
				<label><input type="radio" name="outcome" value="<?php echo esc_attr( $key ); ?>" <?php checked( $values['outcome'], $key ); ?> <?php self::aria( $errors, 'outcome' ); ?> /> <?php echo esc_html( $label ); ?></label><?php endforeach; ?></fieldset><?php self::error( $errors, 'outcome' ); ?>
			<label for="dacl-tested_on"><?php echo esc_html__( 'Tested on (site-local date; blank if not tested)', 'digicyfr-accessibility-checklist' ); ?></label>
			<input id="dacl-tested_on" name="tested_on" type="date" value="<?php echo esc_attr( (string) $values['tested_on'] ); ?>" <?php self::aria( $errors, 'tested_on' ); ?> /><?php self::error( $errors, 'tested_on' ); ?>
			<label for="dacl-assignee_user_id"><?php echo esc_html__( 'Assignee user ID (0 for none)', 'digicyfr-accessibility-checklist' ); ?></label>
			<input id="dacl-assignee_user_id" name="assignee_user_id" type="number" min="0" value="<?php echo esc_attr( (string) $values['assignee_user_id'] ); ?>" <?php self::aria( $errors, 'assignee_user_id' ); ?> /><?php self::error( $errors, 'assignee_user_id' ); ?>
			<p class="description"><?php echo esc_html__( 'Assignment is informational and does not grant access; only administrators can view records.', 'digicyfr-accessibility-checklist' ); ?></p>
			<label for="dacl-note"><?php echo esc_html__( 'What happened? (required for Needs work or Not applicable)', 'digicyfr-accessibility-checklist' ); ?></label>
			<textarea id="dacl-note" name="note" maxlength="2000" rows="5" <?php self::aria( $errors, 'note' ); ?>><?php echo esc_textarea( (string) $values['note'] ); ?></textarea><?php self::error( $errors, 'note' ); ?>
			<label for="dacl-evidence_url"><?php echo esc_html__( 'Evidence URL (optional)', 'digicyfr-accessibility-checklist' ); ?></label>
			<input id="dacl-evidence_url" name="evidence_url" type="url" maxlength="2048" value="<?php echo esc_attr( (string) $values['evidence_url'] ); ?>" <?php self::aria( $errors, 'evidence_url' ); ?> /><?php self::error( $errors, 'evidence_url' ); ?>
			<?php if ( $record && '' !== $record['evidence_url'] ) : ?>
				<?php $external = strtolower( (string) wp_parse_url( $record['evidence_url'], PHP_URL_HOST ) ) !== strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ); ?>
				<a href="<?php echo esc_url( $record['evidence_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $external ? __( 'External evidence (opens in a new tab)', 'digicyfr-accessibility-checklist' ) : __( 'Evidence link (opens in a new tab)', 'digicyfr-accessibility-checklist' ) ); ?></a>
			<?php endif; ?>
			<p class="description"><?php echo esc_html__( 'External evidence is allowed. Do not paste private or tokenized links; notes and URLs are included in CSV.', 'digicyfr-accessibility-checklist' ); ?></p>
			<?php submit_button( __( 'Save observation', 'digicyfr-accessibility-checklist' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Render explicit confirmation that also works without JavaScript.
	 *
	 * @param int $id id input.
	 */
	private static function delete_confirmation( int $id ): void {
		$record = DACL_Model::get( $id );
		if ( ! $record ) {
			wp_die( esc_html__( 'Observation not found.', 'digicyfr-accessibility-checklist' ) );
		}
		?>
		<h2><?php echo esc_html__( 'Delete observation?', 'digicyfr-accessibility-checklist' ); ?></h2>
		<p><?php echo esc_html( $record['scope_label'] ); ?> — <?php echo esc_html( DACL_Model::checks()[ $record['test_key'] ][0] ?? $record['test_key'] ); ?></p>
		<p><?php echo esc_html__( 'This permanently deletes this observation and its private note.', 'digicyfr-accessibility-checklist' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dacl_delete" /><input type="hidden" name="record_id" value="<?php echo esc_attr( (string) $id ); ?>" /><?php wp_nonce_field( 'dacl_delete_' . $id ); ?><?php submit_button( __( 'Delete permanently', 'digicyfr-accessibility-checklist' ), 'delete', 'submit', false ); ?> <a class="button" href="<?php echo esc_url( self::url() ); ?>"><?php echo esc_html__( 'Cancel', 'digicyfr-accessibility-checklist' ); ?></a></form>
		<?php
	}

	/** Render a bounded, filtered private list. */
	private static function listing(): void {
		global $wpdb;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only allowlisted filters.
		$filters = self::filters( wp_unslash( $_GET ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page number.
		$page  = isset( $_GET['dacl_page'] ) && is_scalar( $_GET['dacl_page'] ) ? max( 1, absint( $_GET['dacl_page'] ) ) : 1;
		$query = new WP_Query( self::query_args( $filters, $page, self::PAGE_SIZE ) );
		if ( '' !== $wpdb->last_error ) {
			wp_die( esc_html__( 'Could not read private observations.', 'digicyfr-accessibility-checklist' ) );
		}
		?>
		<p><a class="button button-primary" href="<?php echo esc_url( self::url( array( 'dacl_view' => 'new' ) ) ); ?>"><?php echo esc_html__( 'New observation', 'digicyfr-accessibility-checklist' ); ?></a></p>
		<form class="dacl-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"><input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
			<label><?php echo esc_html__( 'Scope name', 'digicyfr-accessibility-checklist' ); ?> <input type="search" name="scope_search" value="<?php echo esc_attr( $filters['scope_search'] ); ?>" /></label>
			<label><?php echo esc_html__( 'Scope type', 'digicyfr-accessibility-checklist' ); ?> <select name="scope_kind"><option value=""><?php echo esc_html__( 'All', 'digicyfr-accessibility-checklist' ); ?></option><option value="page" <?php selected( $filters['scope_kind'], 'page' ); ?>><?php echo esc_html__( 'Page or post', 'digicyfr-accessibility-checklist' ); ?></option><option value="journey" <?php selected( $filters['scope_kind'], 'journey' ); ?>><?php echo esc_html__( 'Journey', 'digicyfr-accessibility-checklist' ); ?></option></select></label>
			<label><?php echo esc_html__( 'Guided check', 'digicyfr-accessibility-checklist' ); ?> <select name="test_key"><option value=""><?php echo esc_html__( 'All', 'digicyfr-accessibility-checklist' ); ?></option>
			<?php
			foreach ( DACL_Model::checks() as $key => $check ) :
				?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['test_key'], $key ); ?>><?php echo esc_html( $check[0] ); ?></option><?php endforeach; ?></select></label>
			<label><?php echo esc_html__( 'Observed outcome', 'digicyfr-accessibility-checklist' ); ?> <select name="outcome"><option value=""><?php echo esc_html__( 'All', 'digicyfr-accessibility-checklist' ); ?></option>
			<?php
			foreach ( DACL_Model::outcomes() as $key => $label ) :
				?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filters['outcome'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
			<label><?php echo esc_html__( 'Assignee user ID', 'digicyfr-accessibility-checklist' ); ?> <input type="number" min="0" name="assignee_user_id" value="<?php echo esc_attr( $filters['assignee_user_id'] ); ?>" /></label>
			<label><?php echo esc_html__( 'Tested date', 'digicyfr-accessibility-checklist' ); ?> <input type="date" name="tested_on" value="<?php echo esc_attr( $filters['tested_on'] ); ?>" /></label>
			<?php submit_button( __( 'Apply filters', 'digicyfr-accessibility-checklist' ), 'secondary', 'submit', false ); ?>
		</form>
		<h2><?php echo esc_html__( 'Private observations', 'digicyfr-accessibility-checklist' ); ?></h2>
		<p><?php echo esc_html__( 'Each row is one human observation. Not recorded is unknown; record counts are not a sitewide pass rate.', 'digicyfr-accessibility-checklist' ); ?></p>
		<div class="dacl-table-scroll"><table class="widefat striped"><caption><?php echo esc_html__( 'Filtered accessibility observations', 'digicyfr-accessibility-checklist' ); ?></caption><thead><tr><th scope="col"><?php echo esc_html__( 'Scope', 'digicyfr-accessibility-checklist' ); ?></th><th scope="col"><?php echo esc_html__( 'Check', 'digicyfr-accessibility-checklist' ); ?></th><th scope="col"><?php echo esc_html__( 'Outcome', 'digicyfr-accessibility-checklist' ); ?></th><th scope="col"><?php echo esc_html__( 'Tested on', 'digicyfr-accessibility-checklist' ); ?></th><th scope="col"><?php echo esc_html__( 'Assignee', 'digicyfr-accessibility-checklist' ); ?></th><th scope="col"><?php echo esc_html__( 'Actions', 'digicyfr-accessibility-checklist' ); ?></th></tr></thead><tbody>
		<?php
		if ( array() === $query->posts ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No observations match these filters. An unrecorded check is unknown.', 'digicyfr-accessibility-checklist' ) . '</td></tr>';
		}
		foreach ( $query->posts as $id ) {
			$row = DACL_Model::get( (int) $id );
			if ( ! $row ) {
				wp_die( esc_html__( 'Could not read private observations.', 'digicyfr-accessibility-checklist' ) );
			}
			self::list_row( $row );
		}
		?>
		</tbody></table></div>
		<?php
		if ( $page > 1 ) {
			echo '<a class="button" href="' . esc_url( self::url( array_merge( $filters, array( 'dacl_page' => $page - 1 ) ) ) ) . '">' . esc_html__( 'Previous page', 'digicyfr-accessibility-checklist' ) . '</a> ';
		}
		if ( $page < (int) $query->max_num_pages ) {
			echo '<a class="button" href="' . esc_url( self::url( array_merge( $filters, array( 'dacl_page' => $page + 1 ) ) ) ) . '">' . esc_html__( 'Next page', 'digicyfr-accessibility-checklist' ) . '</a>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dacl_export" />
		<?php
		foreach ( $filters as $key => $value ) :
			?>
			<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" /><?php endforeach; ?><?php wp_nonce_field( 'dacl_export' ); ?><?php submit_button( __( 'Export filtered CSV', 'digicyfr-accessibility-checklist' ), 'secondary', 'submit', false ); ?></form>
		<p class="description"><?php echo esc_html__( 'CSV includes private notes and URLs. Exports over 20,000 filtered records are rejected rather than truncated.', 'digicyfr-accessibility-checklist' ); ?></p>
		<?php
	}

	/**
	 * Print one text-only row and current source state.
	 *
	 * @param array $row row input.
	 */
	private static function list_row( array $row ): void {
		$id      = (int) $row['id'];
		$check   = DACL_Model::checks()[ $row['test_key'] ][0] ?? $row['test_key'];
		$outcome = DACL_Model::outcomes()[ $row['outcome'] ] ?? $row['outcome'];
		$source  = '';
		if ( 'page' === $row['scope_kind'] && (int) $row['scope_post_id'] > 0 ) {
			$post = get_post( (int) $row['scope_post_id'] );
			if ( ! $post ) {
				$source = __( 'Source removed', 'digicyfr-accessibility-checklist' );
			} elseif ( 'publish' !== $post->post_status ) {
				$source = __( 'Source no longer public', 'digicyfr-accessibility-checklist' );
			} else {
				$current = get_permalink( $post );
				$source  = '<a href="' . esc_url( $current ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open current source (new tab)', 'digicyfr-accessibility-checklist' ) . '</a>';
				if ( $current !== $row['scope_url'] ) {
					$source .= ' ' . esc_html__( 'Permalink changed since observation', 'digicyfr-accessibility-checklist' );
				}
			}
		} elseif ( '' !== $row['scope_url'] ) {
			$source = '<a href="' . esc_url( $row['scope_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open recorded URL (new tab)', 'digicyfr-accessibility-checklist' ) . '</a>';
		}
		?>
		<tr><th scope="row"><?php echo esc_html( $row['scope_label'] ); ?> <small>#<?php echo esc_html( (string) $id ); ?></small><div><?php echo wp_kses_post( $source ); ?></div></th><td><?php echo esc_html( $check ); ?></td><td><?php echo esc_html( $outcome ); ?></td><td><?php echo esc_html( '' !== $row['tested_on'] ? $row['tested_on'] : '—' ); ?></td><td><?php echo esc_html( DACL_Model::user_name( (int) $row['assignee_user_id'] ) ); ?></td><td><a href="
		<?php
		echo esc_url(
			self::url(
				array(
					'dacl_view' => 'edit',
					'record_id' => $id,
				)
			)
		);
		?>
							"><?php echo esc_html__( 'Edit', 'digicyfr-accessibility-checklist' ); ?></a> · <a href="
		<?php
		echo esc_url(
			self::url(
				array(
					'dacl_view' => 'delete',
					'record_id' => $id,
				)
			)
		);
		?>
"><?php echo esc_html__( 'Delete', 'digicyfr-accessibility-checklist' ); ?></a></td></tr>
		<?php
	}

	/**
	 * Prefix formula-like CSV cells after leading whitespace or controls.
	 *
	 * @param mixed $value value input.
	 */
	public static function csv_safe( $value ): string {
		$text = (string) $value;
		return preg_match( '/^[\x00-\x20]*[=+\-@]/u', $text ) ? "'" . $text : $text;
	}

	/**
	 * Build fixed UTF-8 columns; never treat unrecorded checks as passes.
	 *
	 * @param array $row row input.
	 */
	private static function csv_fields( array $row ): array {
		$checks   = DACL_Model::checks();
		$outcomes = DACL_Model::outcomes();
		return array(
			$row['id'],
			$row['scope_kind'],
			$row['scope_label'],
			$row['scope_post_id'],
			$row['scope_url'],
			$row['test_key'],
			$checks[ $row['test_key'] ][0] ?? '',
			$row['outcome'],
			$outcomes[ $row['outcome'] ] ?? '',
			$row['assignee_user_id'],
			DACL_Model::user_name( (int) $row['assignee_user_id'] ),
			$row['tester_user_id'],
			DACL_Model::user_name( (int) $row['tester_user_id'] ),
			$row['tested_on'],
			$row['note'],
			$row['evidence_url'],
			$row['created_at_utc'],
			$row['updated_at_utc'],
			__( 'Manual observation; unrecorded checks unknown.', 'digicyfr-accessibility-checklist' ),
		);
	}

	/**
	 * Preflight and spool the complete filtered export before sending headers.
	 *
	 * @param mixed $stream stream input.
	 * @param array $filters filters input.
	 */
	public static function stream_csv( $stream, array $filters ): bool|WP_Error {
		global $wpdb;
		if ( ! current_user_can( 'manage_options' ) || ! is_resource( $stream ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot export private observations.', 'digicyfr-accessibility-checklist' ) );
		}
		$filters = self::filters( $filters );
		$header  = array( 'record_id', 'scope_kind', 'scope_label', 'scope_post_id', 'scope_url', 'test_key', 'test_label', 'outcome_key', 'outcome_label', 'assignee_user_id', 'assignee_name', 'tester_user_id', 'tester_name', 'tested_on', 'note', 'evidence_url', 'created_at_utc', 'updated_at_utc', 'coverage_note' );
		if ( false === fputcsv( $stream, $header, ',', '"', '' ) ) {
			return new WP_Error( 'storage', __( 'Could not write CSV.', 'digicyfr-accessibility-checklist' ) );
		}
		$page    = 1;
		$written = 0;
		do {
			$query = new WP_Query( self::query_args( $filters, $page, self::CSV_BATCH ) );
			if ( '' !== $wpdb->last_error ) {
				return new WP_Error( 'storage', __( 'Could not read private observations.', 'digicyfr-accessibility-checklist' ) );
			}
			if ( (int) $query->found_posts > self::MAX_EXPORT || $written + count( $query->posts ) > self::MAX_EXPORT ) {
				return new WP_Error( 'too_many', __( 'More than 20,000 filtered records; narrow the filters before exporting.', 'digicyfr-accessibility-checklist' ) );
			}
			foreach ( $query->posts as $id ) {
				$row = DACL_Model::get( (int) $id );
				if ( ! $row || false === fputcsv( $stream, array_map( array( __CLASS__, 'csv_safe' ), self::csv_fields( $row ) ), ',', '"', '' ) ) {
					return new WP_Error( 'storage', __( 'Could not write CSV.', 'digicyfr-accessibility-checklist' ) );
				}
				++$written;
			}
			$batch_count = count( $query->posts );
			++$page;
		} while ( self::CSV_BATCH === $batch_count );
		return true;
	}

	/** Guard every direct POST endpoint with method, capability, and action nonce. */
	public static function handle(): void {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict equality against the fixed HTTP method token.
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot change private observations.', 'digicyfr-accessibility-checklist' ), '', array( 'response' => 403 ) );
		}
		$action = isset( $_POST['action'] ) && is_string( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		$id     = isset( $_POST['record_id'] ) && is_scalar( $_POST['record_id'] ) ? absint( $_POST['record_id'] ) : 0;
		if ( ! in_array( $action, array( 'dacl_save', 'dacl_delete', 'dacl_export' ), true ) ) {
			wp_die( esc_html__( 'Unknown action.', 'digicyfr-accessibility-checklist' ) );
		}
		check_admin_referer( $action . ( 'dacl_export' === $action ? '' : '_' . $id ) );
		if ( 'dacl_export' === $action ) {
			$stream  = fopen( 'php://temp/maxmemory:2097152', 'w+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Complete CSV spool before response headers.
			$filters = self::filters( wp_unslash( $_POST ) );
			$result  = self::stream_csv( $stream, $filters );
			if ( true !== $result ) {
				if ( is_resource( $stream ) ) {
					fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local temporary spool.
				}
				wp_die( esc_html( is_wp_error( $result ) ? $result->get_error_message() : __( 'Could not export CSV.', 'digicyfr-accessibility-checklist' ) ) );
			}
			rewind( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rewind -- Local temporary spool.
			nocache_headers();
			header( 'Content-Type: text/csv; charset=UTF-8' );
			header( 'Content-Disposition: attachment; filename="digicyfr-accessibility-checklist.csv"' );
			fpassthru( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fpassthru -- Validated CSV is sent only after full spool.
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Local temporary spool.
			exit;
		}
		if ( 'dacl_delete' === $action ) {
			$success = DACL_Model::delete( $id );
			wp_safe_redirect( self::url( array( 'dacl_notice' => $success ? 'deleted' : 'failed' ) ) );
			exit;
		}
		$fields = array( 'scope_kind', 'scope_label', 'scope_post_id', 'scope_url', 'test_key', 'outcome', 'assignee_user_id', 'tested_on', 'note', 'evidence_url' );
		$input  = array();
		foreach ( $fields as $field ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Preserve raw field text for complete model validation and escaped error-form replay.
			$input[ $field ] = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? substr( wp_unslash( $_POST[ $field ] ), 0, 10000 ) : '';
		}
		$result = DACL_Model::save( $input, $id );
		if ( is_wp_error( $result ) && 'validation' === $result->get_error_code() ) {
			$token = strtolower( wp_generate_password( 16, false, false ) );
			set_transient(
				'dacl_form_' . get_current_user_id() . '_' . $token,
				array(
					'input'  => $input,
					'errors' => $result->get_error_data(),
				),
				5 * MINUTE_IN_SECONDS
			);
			wp_safe_redirect(
				self::url(
					array(
						'dacl_view'  => 0 === $id ? 'new' : 'edit',
						'record_id'  => $id,
						'dacl_state' => $token,
					)
				)
			);
			exit;
		}
		wp_safe_redirect( self::url( array( 'dacl_notice' => is_wp_error( $result ) ? 'failed' : 'saved' ) ) );
		exit;
	}
}
