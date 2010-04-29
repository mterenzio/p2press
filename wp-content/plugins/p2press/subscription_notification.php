<?php
function p2press_hub_process_subscription_notification( ) {

	// Get subs
	global $wpdb;
    $subs = $wpdb->get_results("SELECT link_rss FROM $wpdb->links", ARRAY_A);
    //print_r($subs);
	if ( empty( $subs) )
		$subs = array( );

	// Must provide a url
	if ( empty( $_REQUEST['url'] ) ) {
		p2press_subscription_notify_result( 'false', 'No url given.' );
    } else {
        p2press_log('got a ping from '.html_entity_decode($_REQUEST['url']));
    }


    // check if we are subscribed to url
$doesfollow = false;
foreach ($subs as $sub) {
        $sublink = html_entity_decode($sub['link_rss']);
        p2press_log('comparing ping '.$_REQUEST['url'].' to subscription '.$sublink);
    if (html_entity_decode($_REQUEST['url']) == $sublink) {
        //p2press_update_daily_subs();
        if ( !empty( $_REQUEST['challenge'] ) )	 {
           //update cache so we don't get a ton on first request??
            p2press_log($sublink.' returning a challenge. . .');
            echo $_REQUEST['challenge'];
            exit;
        } else {
            p2press_log($sublink.' ping is now being acted upon. . .');
            p2press_update_subscription( html_entity_decode($_REQUEST['url']) );
            $doesfollow = true;
	        p2press_subscription_notify_result( 'true', 'Update received.' );
	    }
	} else {
	    continue;
	}
}
if (!$doesfollow) {
    p2press_log($_REQUEST['url'].'does not appear to a be a subscription of mine');
	p2press_subscription_notify_result( 'false', 'I do not follow you.' ); 
}

 
}