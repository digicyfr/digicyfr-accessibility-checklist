<?php
/**
 * Remove only this plugin's private observation posts and their meta.
 *
 * @package Digicyfr_Accessibility_Checklist
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

register_post_type( 'digicyfr_a11y_test', array( 'public' => false ) );
while ( true ) {
	$dacl_ids = get_posts(
		array(
			'post_type'   => 'digicyfr_a11y_test',
			'post_status' => array( 'private', 'trash', 'draft', 'pending', 'future', 'publish', 'auto-draft', 'inherit' ),
			'numberposts' => 100,
			'fields'      => 'ids',
		)
	);
	if ( array() === $dacl_ids ) {
		break;
	}
	foreach ( $dacl_ids as $dacl_record_id ) {
		if ( false === wp_delete_post( (int) $dacl_record_id, true ) ) {
			break 2;
		}
	}
}
