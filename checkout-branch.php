<?php

require_once( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );

if ( isset( $_GET['checkout'] ) && is_user_logged_in() ) {
	$id = $_GET['checkout'];
} else {
	wp_safe_redirect( home_url() );
	exit;
}

//checkout -b 相当 オリジナルの投稿を取得して新規投稿を作成、新規投稿へリダイレクト

// post
$pub = get_post( $id, ARRAY_A );
unset( $pub['ID'] );
$pub['post_status'] = 'draft';
$pub['post_name'] = $pub['post_name'] . '-branch';

$pub['post_date'] = current_time('mysql');
$pub['post_date_gmt'] = current_time( 'mysql', 1 );

$pub['post_modified'] = current_time('mysql');
$pub['post_modified_gmt'] = current_time( 'mysql', 1 );

$pub = apply_filters( 'kzpb_pre_publish_to_draft_post', $pub );
$draft_id = wp_insert_post( $pub );

// postmeta
$keys = get_post_custom_keys( $id );
$custom_field = array();
foreach ( (array) $keys as $key ) {
	if ( preg_match( '/^_feedback_/', $key ) )
		continue;

	if ( preg_match( '/_wp_old_slug/', $key ) )
		continue;

	$key = apply_filters( 'kzpb_publish_to_draft_postmeta_filter', $key );

	$values = get_post_custom_values( $key, $id );
	foreach ( $values as $value ) {
		add_post_meta( $draft_id, $key, maybe_unserialize( $value ) );
	}
}

//attachment
$args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => $id );
$attachments = get_posts( $args );
if ( $attachments ) {
	foreach ( $attachments as $attachment ) {
		$new = array(
			'post_author' => $attachment->post_author,
			'post_date' => $attachment->post_date,
			'post_date_gmt' => $attachment->post_date_gmt,
			'post_content' => $attachment->post_content,
			'post_title' => $attachment->post_title,
			'post_excerpt' => $attachment->post_excerpt,
			'post_status' => $attachment->post_status,
			'comment_status' => $attachment->comment_status,
			'ping_status' => $attachment->ping_status,
			'post_password' => $attachment->post_password,
			'post_name' => $attachment->post_name,
			'to_ping' => $attachment->to_ping,
			'pinged' => $attachment->pinged,
			'post_modified' => $attachment->post_modified,
			'post_modified_gmt' => $attachment->post_modified_gmt,
			'post_content_filtered' => $attachment->post_content_filtered,
			'post_parent' => $draft_id,
			'guid' => $attachment->guid,
			'menu_order' => $attachment->menu_order,
			'post_type' => $attachment->post_type,
			'post_mime_type' => $attachment->post_mime_type,
			'comment_count' => $attachment->comment_count
		);
		$new = apply_filters( 'kzpb_pre_publish_to_draft_attachment', $new );
		$attachment_new_id = wp_insert_post( $new );
		$keys = get_post_custom_keys( $attachment->ID );

		$custom_field = array();
		foreach ( (array) $keys as $key ) {
			$value = get_post_meta( $attachment->ID, $key, true );

		add_post_meta( $attachment_new_id, $key, maybe_unserialize ( $value ) );
		}
	}
}

//tax
$taxonomies = get_object_taxonomies( $pub['post_type'] );
foreach ( $taxonomies as $taxonomy ) {
	$post_terms = wp_get_object_terms($id, $taxonomy, array( 'orderby' => 'term_order' ) );
	$post_terms = apply_filters( 'kzpb_pre_publish_to_draft_taxonomies', $post_terms );
	$terms = array();
	for ( $i = 0; $i < count( $post_terms ); $i++ ) {
		$terms[] = $post_terms[ $i ]->slug;
	}
	wp_set_object_terms( $draft_id, $terms, $taxonomy );
}

add_post_meta( $draft_id, '_kzpb_pre_post_id', $id );

if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) {
	wp_safe_redirect( admin_url( 'post.php?post=' . $draft_id . '&action=edit' ) );
	exit;
}
