<?php

//通常の投稿はacfを使っていないのでREST-API送出直前にブランチを削除
add_filter( 'rest_prepare_post', 'kzpb_delete_branch', 20,  3);
function kzpb_delete_branch( $response , $status, $request){
	$id = $response->data['id'];
	$publish_status = $response->data['status'];
	$org_id = get_post_meta( $id, '_kzpb_pre_post_id', true );

	if( $publish_status == 'publish' && !empty ( $org_id )){
		wp_delete_post( $id );
	}

	return $response;
}

//カスタム投稿タイプは全てacfを使用しているのでacfの保存処理が終了してからブランチを削除
add_action( 'acf/save_post', 'kzpb_delete_custom_type_branch', 15);
function kzpb_delete_custom_type_branch( $id ){
	$publish_status = get_post_status( $id );
	$post_type = get_post_type( $id );
	$org_id = get_post_meta( $id, '_kzpb_pre_post_id', true );

	if( $publish_status == 'publish' && !empty ( $org_id ) && $post_type != 'page'){
		wp_delete_post( $id );
	}
}
