<?php
/*
Plugin Name: Post Branch
Author: KITERETZ inc.
Plugin URI:
Description: Create branches of published posts to preview, and publish branches to update the originals.
Version: 1.0.0
Author URI: https://kiteretz.com
Domain Path: /languages
Text Domain: post_branch
*/

require_once( dirname( __FILE__ ) . '/merge-branch.php');

//ブランチの削除でacfのフックを使用しています
require_once( dirname( __FILE__ ) . '/delete-branch.php');

if ( ! defined( 'KZPB_DOMAIN' ) ) {
	define( 'KZPB_DOMAIN', 'post_branch' );
}

if ( ! defined( 'KZPB_PLUGIN_URL' ) ) {
	define( 'KZPB_PLUGIN_URL', plugins_url() . '/' . dirname( plugin_basename( __FILE__ ) ) );
}

load_plugin_textdomain( KZPB_DOMAIN, KZPB_DOMAIN . '/languages', dirname( plugin_basename( __FILE__ ) ) . '/languages' );

// 記事編集画面でのブランチ表示
add_action( 'admin_bar_menu', 'wpbs_show_checkout', 150 );
function wpbs_show_checkout( $wp_admin_bar ) {

	global $post;
	global $pagenow;

	if ( !is_admin_bar_showing()
		|| empty( $post )
		|| $post->post_type == 'page'
		|| $pagenow == 'edit.php'
		|| $pagenow == 'post-new.php')  return;

	$org_id = get_post_meta( $post->ID, "_wpbs_pre_post_id", true );

	// 既存記事はブランチ作成ボタン
	if ( empty( $org_id )){
		$wp_admin_bar->add_menu( array (
			'id' => 'make branch',
			'title' => __( 'Create Branch', KZPB_DOMAIN ),
			'href' => KZPB_PLUGIN_URL.'/checkout-branch.php?checkout='.$post->ID
			)
		);
	// ブランチの記事なら「編集中」を表示
	} else {
		$wp_admin_bar->add_menu( array (
			'id' => 'edit branch',
			'title' => sprintf( __( 'Editing branch of %d', KZPB_DOMAIN ), $org_id ),
			'href' => get_permalink( $org_id ),
			'meta' => array(
					'title' => __('Move source post', KZPB_DOMAIN)
				)
			)
		);
	}
}

// 通知など
add_action( 'admin_notices', 'wpbs_admin_notice' );
function wpbs_admin_notice() {
	if ( isset($_REQUEST['post']) ) {
		$id = $_REQUEST['post'];
		if ( $old_id = get_post_meta( $id, '_wpbs_pre_post_id', true ) ) {
			echo '<div id="wpbs_notice" class="updated fade"><p>' . sprintf( __( "This post is a copy of the post id <a href='%s' target='__blank' >%s</a> Overwrite the original post by pressing the publish button.", KZPB_DOMAIN ),  get_permalink($old_id), $old_id ) . '</p></div>';
		}
	}
}

function wpbs_admin_notice_saved_init() {
	if ( isset($_REQUEST['message']) && $_REQUEST['message'] == 'wpbs_msg' )
		add_action( 'admin_notices', 'wpbs_admin_notice_saved' );
}

function wpbs_admin_notice_saved() {
	echo '<div id="wpbs_notice" class="updated fade"><p></p></div>';
}

// edit.php画面にて「 ○○のブランチ」を表示
add_filter( 'display_post_states', 'wpbs_display_branch_stat' );
function wpbs_display_branch_stat( $stat ) {
    global $post;
    if ( $org_id = get_post_meta( $post->ID, '_wpbs_pre_post_id', true ) ) {
        $stat[] = sprintf( __( 'Branch of %d', KZPB_DOMAIN ), $org_id );
    }
    return $stat;
}