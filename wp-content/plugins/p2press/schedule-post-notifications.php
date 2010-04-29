<?php

add_action( 'publish_post', 'rsscloud_schedule_post_notifications' );
function rsscloud_schedule_post_notifications( ) {
    p2press_log('blog was updated. . .');
	if ( !defined( 'RSSCLOUD_NOTIFICATIONS_INSTANT' ) || !RSSCLOUD_NOTIFICATIONS_INSTANT ) {
		wp_schedule_single_event( time( ), 'rsscloud_send_post_notifications_action' );
	} else {
		rsscloud_send_post_notifications( );
	}

}

add_action( 'rsscloud_send_post_notifications_action', 'rsscloud_send_post_notifications' );
