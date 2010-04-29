<?php
function rsscloud_hub_process_notification_request( ) {
	// Get the current set of notifications

	$notify = rsscloud_get_hub_notifications( );
	if ( empty( $notify ) )
		$notify = array( );

	// Must provide at least one URL to get notifications about
	if ( empty( $_POST['url1'] ) ) {
		p2press_log('processing a notification request failed: no url');	
		rsscloud_notify_result( 'false', 'No feed for url1.' );
    } else {
        p2press_log('processing a notification request for'.$_POST['url1']);
    }
	// Only support http-post
	$protocol = 'http-post';
	if ( !empty( $_POST['protocol'] ) && strtolower( $_POST['protocol'] ) !== 'http-post' ) {
		do_action( 'rsscloud_protocol_not_post' );
		p2press_log('failed: notification request was not HTTP-POST');
		rsscloud_notify_result( 'false', 'Only http-post notifications are supported at this time.' );
	}

	// Assume port 80
	$port = 80;
	if ( !empty( $_POST['port'] ) )
		$port = (int) $_POST['port'];

	// Path is required
	if ( empty( $_POST['path'] ) ) {
	    p2press_log('failed: no path notification request for');
		rsscloud_notify_result( 'false', 'No path provided.' );
    }
	$path = str_replace( '@', '', $_POST['path'] );
	if ( $path{0} != '/' )
		$path = '/' . $path;

	// Figure out what the blog and notification URLs are
	$rss2_url = get_bloginfo( 'rss2_url' );
	if ( defined( 'RSSCLOUD_FEED_URL' ) )
		$rss2_url = RSSCLOUD_FEED_URL;

	$notify_url = $_SERVER['REMOTE_ADDR'] . ':' . $port . $path;

	if ( !empty( $_POST['domain'] ) ) {
		$domain = str_replace( '@', '', $_POST['domain'] );
		$notify_url = $domain . ':' . $port . $path;

		$challenge = rsscloud_generate_challenge( );
        p2press_log('sending challenge for notification request to'.$notify_url);
		$result = wp_remote_get( $notify_url . '&url=' . urlencode( esc_url_raw($_POST['url1']) ) . '&challenge=' . $challenge, array( 'method' => 'GET', 'timeout' => RSSCLOUD_HTTP_TIMEOUT, 'user-agent' => RSSCLOUD_USER_AGENT, 'port' => $port, ) );
	} else {
	    p2press_log('sending initial notification response to'.$notify_url);
		$result = wp_remote_post( $notify_url, array( 'method' => 'POST', 'timeout' => RSSCLOUD_HTTP_TIMEOUT, 'user-agent' => RSSCLOUD_USER_AGENT, 'port' => $port, 'body' => array( 'url' => $_POST['url1'] ) ) );
	}

	if ( isset( $result->errors['http_request_failed'][0] ) ) {
	    p2press_log('Error testing notification URL : '.$result->errors['http_request_failed'][0]);
		rsscloud_notify_result( 'false', 'Error testing notification URL : ' . $result->errors['http_request_failed'][0] );
    }
    
	$status_code = (int) $result['response']['code'];

	if ( $status_code < 200 || $status_code > 299 ) {
	    p2press_log('Error testing notification URL.  The URL returned HTTP status code: ' . $result['response']['code'] . ' - ' . $result['response']['message'] . '.' );
		rsscloud_notify_result( 'false', 'Error testing notification URL.  The URL returned HTTP status code: ' . $result['response']['code'] . ' - ' . $result['response']['message'] . '.' );
    }
	// challenge must match for domain requests
	if ( !empty( $_POST['domain'] ) ) {
		if ( empty( $result['body'] ) || $result['body'] != $challenge ) {
		    p2press_log('Challenge failed: The response body did not match the challenge string: '.$result['body'].' doesn\'t match '.$challenge);
			rsscloud_notify_result( 'false', 'The response body did not match the challenge string' );
        }
	}

	// Passed all the tests, add this to the list of notifications for
	foreach ( $_POST as $key => $feed_url ) {
		if ( !preg_match( '|url\d+|', $key ) )
			continue;

		// Only allow requests for the RSS2 posts feed
		if ( $feed_url != $rss2_url ) {
		    p2press_log('Failed: Notification request was for '.$feed_url.' not '.$rss2_url);
			rsscloud_notify_result( 'false', "You can only request updates for {$rss2_url}" );
        }
		$notify[$feed_url][$notify_url]['protocol'] = $protocol;
		$notify[$feed_url][$notify_url]['status'] = 'active';
		$notify[$feed_url][$notify_url]['failure_count'] = 0;
	}

	do_action( 'rsscloud_add_notify_subscription' );

	rsscloud_update_hub_notifications( $notify );
	p2press_log('registration successful for '.$_POST['domain']);
	rsscloud_notify_result( 'true', 'Registration successful.' );
} // function rsscloud_hub_notify
