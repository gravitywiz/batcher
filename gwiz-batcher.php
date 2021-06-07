<?php
/*
 * Plugin Name:  Gwiz Batcher
 * Plugin URI:   http://gravitywiz.com
 * Description:  A plugin template for bulk processing items in batches.
 * Author:       Gravity Wiz
 * Version:      0.1
 * Author URI:   http://gravitywiz.com
 */

add_action( 'init', 'gwiz_batcher' );

function gwiz_batcher() {

	if ( ! is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		return;
	}

	require_once( plugin_dir_path( __FILE__ ) . 'class-gwiz-batcher.php' );

	// Example configuration
	//  new Gwiz_Batcher( array(
	//      'title'        => 'GW Batcher',
	//      'id'           => 'gw-batcher',
	//      'size'         => 25,
	//      'get_items'    => function ( $size, $offset ) {
	//
	//          $paging  = array(
	//              'offset'    => $offset,
	//              'page_size' => $size,
	//          );
	//
	//          $entries = GFAPI::get_entries( null, array(), null, $paging, $total );
	//
	//          return array(
	//              'items' => $entries,
	//              'total' => $total,
	//          );
	//      },
	//      'process_item' => function ( $entry ) {
	//          // Process the item here.
	//      },
	//  ) );
}
