<?php
/**
 * Private observation model, strict validation, and guided check definitions.
 *
 * @package Digicyfr_Accessibility_Checklist
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** All observation data is private to site administrators. */
final class DACL_Model {
	public const TYPE  = 'digicyfr_a11y_test';
	private const META = array( 'scope_kind', 'scope_post_id', 'scope_url', 'test_key', 'outcome', 'assignee_user_id', 'tester_user_id', 'tested_on', 'note', 'evidence_url', 'created_at_utc', 'updated_at_utc' );

	/** Register a nonpublic, nonexportable post type and protected meta. */
	public static function register(): void {
		$caps = array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'delete_posts', 'publish_posts', 'read_private_posts', 'read', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), 'manage_options' );
		register_post_type(
			self::TYPE,
			array(
				'label'               => __( 'Accessibility observations', 'digicyfr-accessibility-checklist' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => false,
				'supports'            => array( 'title' ),
				'capabilities'        => $caps,
				'map_meta_cap'        => false,
				'delete_with_user'    => false,
			)
		);
		foreach ( self::META as $name ) {
			$integer = in_array( $name, array( 'scope_post_id', 'assignee_user_id', 'tester_user_id' ), true );
			register_post_meta(
				self::TYPE,
				'_dacl_' . $name,
				array(
					'single'            => true,
					'type'              => $integer ? 'integer' : 'string',
					'show_in_rest'      => false,
					'auth_callback'     => static fn(): bool => current_user_can( 'manage_options' ),
					'sanitize_callback' => $integer ? 'absint' : ( in_array( $name, array( 'scope_url', 'evidence_url' ), true ) ? 'esc_url_raw' : ( 'note' === $name ? 'sanitize_textarea_field' : 'sanitize_text_field' ) ),
				)
			);
		}
	}

	/** Fixed human checks with W3C source links. */
	public static function checks(): array {
		return array(
			'keyboard'      => array( __( 'Keyboard access', 'digicyfr-accessibility-checklist' ), __( 'Navigate without a pointer; note an unreachable control, trap, or order problem.', 'digicyfr-accessibility-checklist' ), 'https://www.w3.org/WAI/test-evaluate/preliminary/#keyboard' ),
			'focus'         => array( __( 'Visible focus', 'digicyfr-accessibility-checklist' ), __( 'Observe whether keyboard focus stays visible on controls throughout this scope.', 'digicyfr-accessibility-checklist' ), 'https://www.w3.org/WAI/WCAG22/Understanding/focus-visible' ),
			'zoom_reflow'   => array( __( 'Zoom and reflow', 'digicyfr-accessibility-checklist' ), __( 'Enlarge text and narrow the layout; note clipped content or two-dimensional scrolling where applicable.', 'digicyfr-accessibility-checklist' ), 'https://www.w3.org/WAI/WCAG22/Understanding/reflow' ),
			'forms'         => array( __( 'Forms and errors', 'digicyfr-accessibility-checklist' ), __( 'Inspect labels and instructions; submit an invalid value and observe the text error.', 'digicyfr-accessibility-checklist' ), 'https://www.w3.org/WAI/test-evaluate/preliminary/#forms' ),
			'image_meaning' => array( __( 'Image meaning', 'digicyfr-accessibility-checklist' ), __( 'Inspect image purpose in context, including linked images; judge the alternative text by that purpose.', 'digicyfr-accessibility-checklist' ), 'https://www.w3.org/WAI/tutorials/images/' ),
			'key_journey'   => array( __( 'Key journey', 'digicyfr-accessibility-checklist' ), __( 'Try a named end-to-end task with a keyboard and note where it cannot be completed.', 'digicyfr-accessibility-checklist' ), 'https://www.w3.org/TR/WCAG-EM/' ),
		);
	}

	/** Outcomes remain observations, never a conformance score. */
	public static function outcomes(): array {
		return array(
			'not_tested'     => __( 'Not tested', 'digicyfr-accessibility-checklist' ),
			'pass'           => __( 'Observed pass', 'digicyfr-accessibility-checklist' ),
			'needs_work'     => __( 'Needs work', 'digicyfr-accessibility-checklist' ),
			'not_applicable' => __( 'Not applicable', 'digicyfr-accessibility-checklist' ),
		);
	}

	/**
	 * Count Unicode code points without relying on the optional mbstring extension.
	 *
	 * @param string $text text input.
	 */
	private static function length( string $text ): int {
		$result = preg_match_all( '/./us', $text );
		return false === $result ? PHP_INT_MAX : $result;
	}

	/**
	 * Require a complete HTTP(S) URL before WordPress cleaning.
	 *
	 * @param string $raw raw input.
	 * @param bool   $local local input.
	 */
	public static function valid_url( string $raw, bool $local ): string {
		if ( '' === $raw || strlen( $raw ) > 2048 || ! preg_match( '~^https?://~i', $raw ) || false === filter_var( $raw, FILTER_VALIDATE_URL ) ) {
			return '';
		}
		$parts = wp_parse_url( $raw );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		if ( $local ) {
			$home = wp_parse_url( home_url( '/' ) );
			if ( ! is_array( $home ) || strtolower( (string) $parts['host'] ) !== strtolower( (string) ( $home['host'] ?? '' ) ) || (int) ( $parts['port'] ?? 0 ) !== (int) ( $home['port'] ?? 0 ) ) {
				return '';
			}
		}
		$clean = esc_url_raw( $raw, array( 'http', 'https' ) );
		return '' !== $clean && strlen( $clean ) <= 2048 && false !== filter_var( $clean, FILTER_VALIDATE_URL ) ? $clean : '';
	}

	/**
	 * Return only a private observation of the correct type.
	 *
	 * @param int $id id input.
	 */
	public static function get( int $id ): ?array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return null;
		}
		$post = get_post( $id );
		if ( ! $post || self::TYPE !== $post->post_type || 'private' !== $post->post_status ) {
			return null;
		}
		$data = array(
			'id'          => $id,
			'scope_label' => $post->post_title,
		);
		foreach ( self::META as $name ) {
			$data[ $name ] = get_post_meta( $id, '_dacl_' . $name, true );
		}
		return $data;
	}

	/**
	 * Validate a complete form and return normalized values or field errors.
	 *
	 * @param array $input input input.
	 * @param int   $existing_id existing id input.
	 */
	public static function validate( array $input, int $existing_id = 0 ): array|WP_Error {
		$errors = array();
		$prior  = $existing_id > 0 ? self::get( $existing_id ) : null;
		$label  = sanitize_text_field( trim( (string) ( $input['scope_label'] ?? '' ) ) );
		if ( '' === $label || self::length( $label ) > 160 ) {
			$errors['scope_label'] = __( 'Enter a scope name of at most 160 characters.', 'digicyfr-accessibility-checklist' );
		}
		$scope_kind = sanitize_key( (string) ( $input['scope_kind'] ?? '' ) );
		if ( ! in_array( $scope_kind, array( 'page', 'journey' ), true ) ) {
			$errors['scope_kind'] = __( 'Choose Page or Journey.', 'digicyfr-accessibility-checklist' );
		}
		$post_raw = trim( (string) ( $input['scope_post_id'] ?? '' ) );
		$post_id  = '' === $post_raw ? 0 : filter_var( $post_raw, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		if ( false === $post_id ) {
			$errors['scope_post_id'] = __( 'Enter a valid published post or page ID.', 'digicyfr-accessibility-checklist' );
			$post_id                 = 0;
		}
		if ( 'journey' === $scope_kind && 0 !== $post_id ) {
			$errors['scope_post_id'] = __( 'A Journey uses a start URL, not a post ID.', 'digicyfr-accessibility-checklist' );
		}
		$scope_raw = trim( (string) ( $input['scope_url'] ?? '' ) );
		$scope_url = '';
		if ( 'page' === $scope_kind && $post_id > 0 ) {
			$source   = get_post( $post_id );
			$existing = $prior;
			if ( ! $source || ! in_array( $source->post_type, array( 'post', 'page' ), true ) || 'publish' !== $source->post_status ) {
				if ( $existing && (int) $existing['scope_post_id'] === $post_id ) {
					$scope_url = (string) $existing['scope_url'];
				} else {
					$errors['scope_post_id'] = __( 'The page or post must be published.', 'digicyfr-accessibility-checklist' );
				}
			} else {
				$prior_url = $existing && (int) $existing['scope_post_id'] === $post_id ? (string) $existing['scope_url'] : '';
				$scope_url = '' !== $prior_url ? $prior_url : self::valid_url( (string) get_permalink( $source ), true );
				if ( '' === $scope_url ) {
					$errors['scope_post_id'] = __( 'Could not resolve a same-site URL for this page.', 'digicyfr-accessibility-checklist' );
				}
			}
		} else {
			$scope_url = self::valid_url( $scope_raw, true );
			if ( '' === $scope_url ) {
				$errors['scope_url'] = __( 'Enter a complete URL on this site without credentials.', 'digicyfr-accessibility-checklist' );
			}
		}
		$test_key = sanitize_key( (string) ( $input['test_key'] ?? '' ) );
		if ( ! array_key_exists( $test_key, self::checks() ) ) {
			$errors['test_key'] = __( 'Choose one of the six guided checks.', 'digicyfr-accessibility-checklist' );
		}
		$outcome = sanitize_key( (string) ( $input['outcome'] ?? '' ) );
		if ( ! array_key_exists( $outcome, self::outcomes() ) ) {
			$errors['outcome'] = __( 'Choose a recorded outcome.', 'digicyfr-accessibility-checklist' );
		}
		$assignee_raw = trim( (string) ( $input['assignee_user_id'] ?? '0' ) );
		$assignee     = filter_var( $assignee_raw, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 0 ) ) );
		if ( false === $assignee || ( $assignee > 0 && ! get_userdata( $assignee ) ) ) {
			$errors['assignee_user_id'] = __( 'Enter an existing user ID or 0.', 'digicyfr-accessibility-checklist' );
			$assignee                   = 0;
		}
		$tested_on = trim( (string) ( $input['tested_on'] ?? '' ) );
		if ( 'not_tested' === $outcome ) {
			if ( '' !== $tested_on ) {
				$errors['tested_on'] = __( 'Leave the date blank until this check is tested.', 'digicyfr-accessibility-checklist' );
			}
		} elseif ( '' === $tested_on || ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $tested_on, $match ) || ! checkdate( (int) ( $match[2] ?? 0 ), (int) ( $match[3] ?? 0 ), (int) ( $match[1] ?? 0 ) ) || $tested_on > current_datetime()->format( 'Y-m-d' ) ) {
			$errors['tested_on'] = __( 'Enter a real site-local date that is not in the future.', 'digicyfr-accessibility-checklist' );
		}
		$raw_note = (string) ( $input['note'] ?? '' );
		$note     = sanitize_textarea_field( trim( $raw_note ) );
		if ( self::length( $note ) > 2000 ) {
			$errors['note'] = __( 'Keep the note within 2,000 characters.', 'digicyfr-accessibility-checklist' );
		} elseif ( in_array( $outcome, array( 'needs_work', 'not_applicable' ), true ) && '' === $note ) {
			$errors['note'] = __( 'Explain the problem or why this check does not apply.', 'digicyfr-accessibility-checklist' );
		}
		$evidence_raw = trim( (string) ( $input['evidence_url'] ?? '' ) );
		$evidence     = '' === $evidence_raw ? '' : self::valid_url( $evidence_raw, false );
		if ( '' !== $evidence_raw && '' === $evidence ) {
			$errors['evidence_url'] = __( 'Enter a complete HTTP(S) evidence URL without credentials.', 'digicyfr-accessibility-checklist' );
		}
		if ( array() !== $errors ) {
			return new WP_Error( 'validation', __( 'Correct the highlighted fields and save again.', 'digicyfr-accessibility-checklist' ), $errors );
		}
		$tester_id = 0;
		if ( 'not_tested' !== $outcome ) {
			$tester_id = $prior && $prior['outcome'] === $outcome && $prior['tested_on'] === $tested_on && $prior['test_key'] === $test_key && $prior['scope_kind'] === $scope_kind && (int) $prior['scope_post_id'] === (int) $post_id && $prior['scope_url'] === $scope_url && (int) $prior['tester_user_id'] > 0 ? (int) $prior['tester_user_id'] : get_current_user_id();
		}
		return array(
			'scope_kind'       => $scope_kind,
			'scope_label'      => $label,
			'scope_post_id'    => (int) $post_id,
			'scope_url'        => $scope_url,
			'test_key'         => $test_key,
			'outcome'          => $outcome,
			'assignee_user_id' => (int) $assignee,
			'tester_user_id'   => $tester_id,
			'tested_on'        => 'not_tested' === $outcome ? '' : $tested_on,
			'note'             => $note,
			'evidence_url'     => $evidence,
		);
	}

	/**
	 * Save one complete observation after explicit admin and type checks.
	 *
	 * @param array $input input input.
	 * @param int   $id id input.
	 */
	public static function save( array $input, int $id = 0 ): int|WP_Error {
		if ( ! current_user_can( 'manage_options' ) || ( $id > 0 && null === self::get( $id ) ) ) {
			return new WP_Error( 'forbidden', __( 'You cannot edit this observation.', 'digicyfr-accessibility-checklist' ) );
		}
		$data = self::validate( $input, $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$new = 0 === $id;
		$old = $new ? null : self::get( $id );
		if ( $new ) {
			$id = wp_insert_post(
				array(
					'post_type'   => self::TYPE,
					'post_status' => 'private',
					'post_title'  => wp_slash( $data['scope_label'] ),
					'post_author' => get_current_user_id(),
				),
				true
			);
			if ( is_wp_error( $id ) || 0 === $id ) {
				return new WP_Error( 'storage', __( 'The observation could not be saved.', 'digicyfr-accessibility-checklist' ) );
			}
		}
		$now                    = gmdate( 'Y-m-d H:i:s' );
		$data['created_at_utc'] = $new ? $now : (string) $old['created_at_utc'];
		$data['updated_at_utc'] = $now;
		$changed                = array();
		foreach ( self::META as $name ) {
			$value    = (string) $data[ $name ];
			$previous = $new ? '' : (string) $old[ $name ];
			if ( ! $new && $previous === $value ) {
				continue;
			}
			if ( false === update_post_meta( $id, '_dacl_' . $name, wp_slash( $value ) ) ) {
				if ( $new ) {
					wp_delete_post( $id, true );
				} else {
					foreach ( $changed as $restore ) {
						update_post_meta( $id, '_dacl_' . $restore, wp_slash( (string) $old[ $restore ] ) );
					}
				}
				return new WP_Error( 'storage', __( 'The observation could not be saved.', 'digicyfr-accessibility-checklist' ) );
			}
			$changed[] = $name;
		}
		if ( ! $new ) {
			$updated = wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => wp_slash( $data['scope_label'] ),
				),
				true
			);
			if ( is_wp_error( $updated ) || 0 === $updated ) {
				foreach ( $changed as $restore ) {
					update_post_meta( $id, '_dacl_' . $restore, wp_slash( (string) $old[ $restore ] ) );
				}
				return new WP_Error( 'storage', __( 'The observation could not be saved.', 'digicyfr-accessibility-checklist' ) );
			}
		}
		return $id;
	}

	/**
	 * Permanently remove only a record of this private type.
	 *
	 * @param int $id id input.
	 */
	public static function delete( int $id ): bool {
		return current_user_can( 'manage_options' ) && null !== self::get( $id ) && false !== wp_delete_post( $id, true );
	}

	/**
	 * A deleted assignee or tester remains visible by saved ID.
	 *
	 * @param int $id id input.
	 */
	public static function user_name( int $id ): string {
		if ( 0 === $id ) {
			return __( 'None', 'digicyfr-accessibility-checklist' );
		}
		$user = get_userdata( $id );
		if ( $user ) {
			return $user->display_name;
		}
		/* translators: %d is a deleted WordPress user ID. */
		return sprintf( __( 'Former user #%d', 'digicyfr-accessibility-checklist' ), $id );
	}
}
