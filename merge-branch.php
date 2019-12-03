<?php

// rest_after_insertフックは投稿タイプごとに登録が必要
add_action( 'init', 'add_kzpb_after_insert_hooks', 999);
function add_kzpb_after_insert_hooks() {

	$additional_types = get_post_types( array( '_builtin' => false, 'show_ui' => true ) );

	foreach ( $additional_types as $post_type ) {
		add_action( 'rest_after_insert_' . $post_type, 'kzpb_merge_post', 10,2);
	}

	add_action( 'rest_after_insert_post', 'kzpb_merge_post', 10, 2 );

}

//merge相当 publish時に分岐元の投稿を更新する
function kzpb_merge_post( $post, $request ) {

	$id = $post->ID;
	$org_id = get_post_meta( $id, '_kzpb_pre_post_id', true );

	if( $post->post_status != 'publish' || empty( $org_id )) {
		return;
	}

	// post ブランチ元のIDで今の投稿をDBにアップデートする
	$new = array(
		'ID' => $org_id,
		'post_author' => $post->post_author,
		'post_date' => get_post( $org_id )->post_date,
		'post_date_gmt' => get_post( $org_id )->post_date_gmt,
		'post_content' => $post->post_content,
		'post_title' => $post->post_title,
		'post_excerpt' => $post->post_excerpt,
		'post_status' => 'publish',
		'comment_status' => $post->comment_status,
		'ping_status' => $post->ping_status,
		'post_password' => $post->post_password,
		'post_name' => get_post( $org_id )->post_name,
		'to_ping' => $post->to_ping,
		'pinged' => $post->pinged,
		'post_modified' => $post->post_modified,
		'post_modified_gmt' => $post->post_modified_gmt,
		'post_content_filtered' => $post->post_content_filtered,
		'post_parent' => $post->post_parent,
		'guid' => $post->guid,
		'menu_order' => $post->menu_order,
		'post_type' => $post->post_type,
		'post_mime_type' => $post->post_mime_type
	);
	wp_update_post( apply_filters( 'kzpb_draft_to_publish_update_post', $new ) );

	//postmeta
	$keys = get_post_custom_keys( $id );

	$custom_field = array();
	foreach ( (array) $keys as $key ) {
		if ( preg_match( '/^_feedback_/', $key ) )
			continue;

		if ( preg_match( '/_kzpb_pre_post_id/', $key ) )
			continue;

		if ( preg_match( '/_wp_old_slug/', $key ) )
			continue;

		$key = apply_filters( 'kzpb_draft_to_publish_postmeta_filter', $key );

		delete_post_meta( $org_id, $key );
		$values = get_post_custom_values($key, $id );
		foreach ( $values as $value ) {
			add_post_meta( $org_id, $key, maybe_unserialize ( $value ) );
		}
	}

	$args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_status' => null, 'post_parent' => $id );
	$attachments = get_posts( $args );
	if ($attachments) {
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
			$new = apply_filters( 'kzpb_pre_draft_to_publish_attachment', $new );
			$attachment_newid = wp_insert_post( $new );
			$keys = get_post_custom_keys( $attachment->ID );

			$custom_field = array();
			foreach ( (array) $keys as $key ) {
				$value = get_post_meta( $attachment->ID, $key, true );

				delete_post_meta( $org_id, $key );
				add_post_meta( $org_id, $key, maybe_unserialize( $value ) );
			}
		}
	}

	//taxonomy
	$taxonomies = get_object_taxonomies( $post->post_type );
	foreach ($taxonomies as $taxonomy) {
		$post_terms = wp_get_object_terms($id, $taxonomy, array( 'orderby' => 'term_order' ));
		$post_terms = apply_filters( 'kzpb_pre_draft_to_publish_taxonomies', $post_terms );
		$terms = array();
		for ($i=0; $i<count($post_terms); $i++) {
			$terms[] = $post_terms[$i]->slug;
		}
		wp_set_object_terms($org_id, $terms, $taxonomy);
	}
}


//マージ後のURLをフィルターしてブランチ元の投稿へ飛んでもらう
function filter_branched_url( $url, $post ) {

	$id = $post->ID;
	$org_id = get_post_meta( $id, '_kzpb_pre_post_id', true );

	if( $post->post_status == 'publish' && !empty( $org_id )) {

		$url = get_permalink( $org_id );

	}

	return $url;

}
add_filter( 'post_link', 'filter_branched_url', 10, 2);
add_filter( 'post_type_link', 'filter_branched_url', 10, 2);
