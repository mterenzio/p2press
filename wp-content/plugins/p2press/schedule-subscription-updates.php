<?php

function p2press_schedule_subscription_updates( ) {
	if ( !defined( 'P2PRESS_UPDATES_INSTANT' ) || !P2PRESS_UPDATES_INSTANT )
		wp_schedule_single_event( time( ), 'p2press_update_subscriptions_action' );
	else
		p2press_update_subscriptions( );

}

add_action( 'p2press_update_subscriptions_action', 'p2press_update_subscriptions' );
