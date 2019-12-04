<?php
/*
Plugin Name: Post Branch
Author: KITERETZ
Plugin URI:
Description: Create branches of published posts to edit and preview, and publish branches to update the source posts.
Version: 1.0.0
Author URI: https://kiteretz.com
Domain Path: /languages
Text Domain: post-branch
*/

if ( ! defined( 'KZPB_PLUGIN_DIR_URL' ) ) {
	define( 'KZPB_PLUGIN_DIR_URL', plugin_dir_url( __FILE__ ) );
}

function kzpb_load_plugin_textdomain() {
	load_plugin_textdomain( 'post-branch', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'kzpb_load_plugin_textdomain', 0 );

function kzpb_admin_enqueue_scripts() {
	wp_enqueue_style( 'post-branch', KZPB_PLUGIN_DIR_URL . 'post-branch.css' );
}
add_action( 'admin_enqueue_scripts', 'kzpb_admin_enqueue_scripts' );

// 記事編集画面でのブランチ表示
function kzpb_show_checkout( $wp_admin_bar ) {
	global $post;
	global $pagenow;

	if ( ! is_admin_bar_showing()
		|| empty( $post )
		|| $post->post_type == 'page'
		|| $pagenow == 'edit.php'
		|| $pagenow == 'post-new.php')  return;

	$old_id = get_post_meta( $post->ID, '_kzpb_pre_post_id', true );

	// 既存記事はブランチ作成ボタン
	if ( empty( $old_id ) ) {
		$wp_admin_bar->add_menu( array(
			'id'    => 'new-branch',
			'title' => __( 'New Branch', 'post-branch' ),
			'href'  => KZPB_PLUGIN_DIR_URL . 'checkout-branch.php?checkout=' . $post->ID,
		) );
	// ブランチの記事なら「編集中」を表示
	} else {
		$wp_admin_bar->add_menu( array(
			'id'    => 'edit-branch',
			'title' => sprintf( __( 'Editing branch of #%d', 'post-branch' ), $old_id ),
			'href'  => get_permalink( $old_id ),
			'meta'  => array(
				'title' => __( 'Move to the source post', 'post-branch' ),
			),
		) );
	}
}
add_action( 'admin_bar_menu', 'kzpb_show_checkout', 9999 );

// 通知など
function kzpb_admin_notice() {
	if ( isset( $_REQUEST['post'] ) ) {
		$id = $_REQUEST['post'];
		if ( $old_id = get_post_meta( $id, '_kzpb_pre_post_id', true ) ) {
			echo '<div id="wpbs_notice" class="updated fade"><p>' . sprintf( __( 'This post is a branch of ID <a href="%s" target="_blank">%s</a>. Publishing this post overwrites the original.', 'post-branch' ),  get_permalink( $old_id ), $old_id ) . '</p></div>';
		}
	}
}
add_action( 'admin_notices', 'kzpb_admin_notice' );

function kzpb_admin_notice_saved_init() {
	if ( isset( $_REQUEST['message'] ) && $_REQUEST['message'] == 'kzpb_msg' )
		add_action( 'admin_notices', 'kzpb_admin_notice_saved' );
}

function kzpb_admin_notice_saved() {
	echo '<div id="kzpb_notice" class="updated fade"><p></p></div>';
}

// edit.php画面にて「 ○○のブランチ」を表示
function kzpb_display_branch_stat( $stat ) {
	global $post;
	if ( $old_id = get_post_meta( $post->ID, '_kzpb_pre_post_id', true ) ) {
		$stat[] = sprintf( __( 'Branch of #%d', 'post-branch' ), $old_id );
	}
	return $stat;
}
add_filter( 'display_post_states', 'kzpb_display_branch_stat' );

require_once( plugin_dir_path( __FILE__ ) . 'merge-branch.php');

//ブランチの削除でacfのフックを使用しています
require_once( plugin_dir_path( __FILE__ ) . 'delete-branch.php');
