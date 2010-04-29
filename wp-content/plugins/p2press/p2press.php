<?php
/*
Plugin Name: p2press
Plugin URI: http://p2press.org
Description: Turns Wordpress into a P2P microblogger
Author: Matt Terenzio
Version: 0.1
Author URI: http://jour.nali.st/blog
*/


//begin joseph scott modified code
// Uncomment this to not use cron to send out notifications
define( 'RSSCLOUD_NOTIFICATIONS_INSTANT', true );
define( 'P2PRESS_LOG', true );
define( 'P2PRESS_LOG_LOCATION', '/tmp/'.get_bloginfo('name').'log.html' );
define( 'RSSCLOUD_FEED_URL',  get_bloginfo('url').'/?feed=rss2&author=1' );

if ( !defined( 'RSSCLOUD_USER_AGENT' ) )
	define( 'RSSCLOUD_USER_AGENT', 'WordPress/RSSCloud 0.4.0' );

if ( !defined( 'RSSCLOUD_MAX_FAILURES' ) )
	define( 'RSSCLOUD_MAX_FAILURES', 5 );

if ( !defined( 'RSSCLOUD_HTTP_TIMEOUT' ) )
	define( 'RSSCLOUD_HTTP_TIMEOUT', 10 );

require dirname( __FILE__ ) . '/data-storage.php';

if ( !function_exists( 'rsscloud_hub_process_notification_request' ) ) {
	require dirname( __FILE__ ) . '/notification-request.php';
}	

if ( !function_exists( 'rsscloud_schedule_post_notifications' ) )
	require dirname( __FILE__ ) . '/schedule-post-notifications.php';

if ( !function_exists( 'rsscloud_send_post_notifications' ) )
	require dirname( __FILE__ ) . '/send-post-notifications.php';

add_filter( 'query_vars', 'rsscloud_query_vars' );
function rsscloud_query_vars( $vars ) {
	$vars[] = 'rsscloud';
	return $vars;
}

add_action( 'parse_request', 'rsscloud_parse_request' );
function rsscloud_parse_request( $wp ) {
	if ( array_key_exists( 'rsscloud', $wp->query_vars ) ) {
		if ( $wp->query_vars['rsscloud'] == 'notify' ) {
		    p2press_log('rsscloud got a subscription request. . .processing');
			rsscloud_hub_process_notification_request( );
		    exit;
		}
	}
}

function rsscloud_notify_result( $success, $msg ) {
	$success = strip_tags( $success );
	$success = ent2ncr( $success );
	$success = esc_html( $success );

	$msg = strip_tags( $msg );
	$msg = ent2ncr( $msg );
	$msg = esc_html( $msg );

	header( 'Content-Type: text/xml' );
	echo "<?xml version='1.0'?>\n";
	echo "<notifyResult success='{$success}' msg='{$msg}' />\n";
	exit;
}

add_action( 'rss2_head', 'rsscloud_add_rss_cloud_element' );
function rsscloud_add_rss_cloud_element( ) {
	$cloud = parse_url( get_option( 'home' ) . '/?rsscloud=notify' );

	$cloud['port']		= (int) $cloud['port'];
	if ( empty( $cloud['port'] ) )
		$cloud['port'] = 80;

	$cloud['path']	.= "?{$cloud['query']}";

	$cloud['host']	= strtolower( $cloud['host'] );

	echo "<cloud domain='{$cloud['host']}' port='{$cloud['port']}'";
	echo " path='{$cloud['path']}' registerProcedure=''";
	echo " protocol='http-post' />";
	echo "\n";
}

function rsscloud_generate_challenge( $length = 30 ) {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $chars_length = strlen( $chars );

    $string = '';
    for ( $i = 0; $i < $length; $i++ ) {
        $string .= $chars{mt_rand( 0, $chars_length )};
    }

    return $string;
}

//begin matt terenzio
require dirname( __FILE__ ) . '/SimplePie.php';
require_once(ABSPATH . WPINC . '/registration.php');

function p2press_log($msg) { 
	if ( defined( 'P2PRESS_LOG' ) && P2PRESS_LOG ) {
	    $filename = P2PRESS_LOG_LOCATION;
	    $fd = fopen($filename, "a");
	    $str = "[" . date("Y/m/d h:i:s", mktime()) . "] " . $msg;	
    	fwrite($fd, $str . "\n");
        fclose($fd);
    }
}

if ( !function_exists( 'p2press_hub_process_subscription_notification' ) )
	require dirname( __FILE__ ) . '/subscription_notification.php';

//activate deactivate hooks
register_activation_hook(__FILE__, 'p2press_activation');
//add_action('p2press_daily_subs_cron', 'p2press_update_daily_subs');

function p2press_activation() {
	p2press_log('activating twice daily subscription cron. . .');
	wp_schedule_event(time(), 'twicedaily', 'p2press_daily_subs_cron');
}
/*function initiate_subs() {
    p2press_log('initiating daily subs. . .');
    p2press_update_daily_subs();
}*/
//add_action('init_subs','initiate_subs');


if (!wp_next_scheduled('update_daily_subs')) {
	wp_schedule_event( time(), 'twicedaily', 'update_daily_subs' );
}

add_action( 'update_daily_subs', 'p2press_update_daily_subs' ); 


register_deactivation_hook(__FILE__, 'p2press_deactivation');
function p2press_deactivation() {
    p2press_log('deactivating daily subs. . .');
	wp_clear_scheduled_hook('update_daily_subs');
}

function p2press_update_daily_subs() {
    //get all subs
    p2press_log('updating daily subs. . .');
    global $wpdb;
    $feedurl = RSSCLOUD_FEED_URL;
    $parsed = parse_url($feedurl);
    $domain = $parsed['host'];
    //echo $domain;
    $subs = $wpdb->get_results("SELECT link_rss FROM $wpdb->links", ARRAY_A);    
    foreach ($subs as $sub) {
        $subclean = html_entity_decode($sub['link_rss']);
        $cloudinfo = p2press_get_sub_cloud_info($subclean);
        if ($cloudinfo) {
            $path=urlencode("/?p2press=update");
            if ($cloudinfo[0]['attribs']['']['path'] == '/?rsscloud=notify') {
                p2press_log('sending subscription request for '.$subclean.' to wordpress like cloud. . .');
                $postops = "rsscloud=notify&notifyProcedure=&port=80&path=".$path."&protocol=http-post&domain=".$domain."&url1=".urlencode($subclean);                        
            } else {
                p2press_log('sending subscription request for '.$subclean.'. . .');            
                $postops = "notifyProcedure=&port=80&path=".$path."&protocol=http-post&domain=".$domain."&url1=".urlencode($subclean);
            }
            $suburl = 'http://'.$cloudinfo[0]['attribs']['']['domain'].$cloudinfo[0]['attribs']['']['path'];
            $ch = curl_init($suburl);
            $port = $cloudinfo[0]['attribs']['']['port'];
            curl_setopt ($ch, CURLOPT_PORT, $port);
            curl_setopt ($ch, CURLOPT_POST, 1);
            curl_setopt ($ch, CURLOPT_POSTFIELDS, $postops);
            curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec ($ch);
            //print_r($response);
            curl_close ($ch);
            $xml = simplexml_load_string($response);
            if  ($xml['success'] == 'true') {
                p2press_log('subscription succcess. . .');
            } else {
                p2press_log('subscription failed. . .');
            }
        } else {
                p2press_log('no cloud available for that feed. . .');        
        }
    }
}

function p2press_get_sub_cloud_info($sub) {
        p2press_log('getting cloud info for '.$sub);
        $feed = new SimplePie();
        $feed->set_feed_url($sub);
        if ($success = $feed->init()) {
        //print_r($feed->init(), true);
        $feed->handle_content_type();
        $cloud = $feed->get_channel_tags(SIMPLEPIE_NAMESPACE_RSS_20, 'cloud');
        return $cloud;
        } else {
            return false;
        }
}



add_filter( 'query_vars', 'p2press_query_vars' );
function p2press_query_vars( $vars ) {
	$vars[] = 'p2press';
	return $vars;
}

add_action( 'parse_request', 'p2press_parse_request' );
function p2press_parse_request( $wp ) {
	if ( array_key_exists( 'p2press', $wp->query_vars ) ) {
		if ( $wp->query_vars['p2press'] == 'update' )
			p2press_hub_process_subscription_notification( );
		exit;
	}
}

function p2press_update_subscription( $url ) {
    p2press_log('updating feed for '.$url);
    $newitems = p2press_get_new_items($url);
    $updatedfeed = new SimplePie();
    $updatedfeed->set_feed_url($url);
    $updatedfeed->enable_cache(true);
    $updatedfeed->set_cache_duration(999999);
    $updatedfeed->init();
    $items = $updatedfeed->get_items();
    $num_new_items = count($newitems);
    p2press_log('found '.$num_new_items.' at '.$url);
    foreach ($newitems as $newitem) {
        foreach($items as $item) { 
        //store item as post
        if ($item->get_id(true) == $newitem) {
        
        //if (!$user_id = p2press_poster_exists(base64_encode($url))) {
            if ($author = $item->get_feed()->get_author()) {
                if (!$author->get_email()) {
                    $user_id = p2press_create_new_poster($url, $item->get_feed()->get_title());
                } else {
                    $user_id = p2press_create_new_poster($url, $item->get_feed()->get_title(), $author->get_email());
                }
            } else {
                $user_id = p2press_create_new_poster($url, $item->get_feed()->get_title());
            }
        //}
        $my_post = array();
        if ($item->get_title() == '') {
            $my_post['post_title'] = $item->get_content();    
        } else {
            $my_post['post_title'] = $item->get_title();
        }        
        if ($item->get_content() == '') {
            $my_post['post_content'] = $item->get_title();    
        } else {
            $my_post['post_content'] = $item->get_content();
        }
        $my_post['post_status'] = 'publish';
        $my_post['post_author'] = $user_id;
        $my_post['display_name'] = $item->get_feed()->get_title();
        wp_insert_post( $my_post );
        }
        }
    }
}

function p2press_create_new_poster($feed, $title, $email = null) {
    $username = sanitize_user(substr(base64_encode($feed), 0, 50), true);
    $user_info = get_userdatabylogin($username);
    if ($user_info->user_login != $username) {
	    p2press_log('creating a new user. . .');     
        $userdata = array();
        $userdata['user_login'] = $username;
        $userdata['user_email'] = $email; 
        //$userdata['user_nicename'] = sanitize_title($title);
        $userdata['display_name'] = sanitize_title($title);
        $random_password = wp_generate_password( 12, false );
        $userdata['user_pass'] = $random_password; 
        $userdata['user_url'] = $feed;
	    $user_id = wp_insert_user($userdata);
	    return $user_id;
	} else {
	    p2press_log('already a user. . .');
	    if ($user_info->user_email != $email) {
	        //update email for user
	    p2press_log('email'.$email);	        
	    }
	    if ($user_ifo->display_name != sanitize_title($title)) {
	        //update title
	    p2press_log(sanitize_title($title));	        
	    }	    
        return $user_info->ID;	
    }
}

function p2press_load_feed_from_cache($feed) {
    $cachedfeed = new SimplePie();
    $cachedfeed->set_feed_url($feed);
    $cachedfeed->enable_cache(true);
    $cachedfeed->set_cache_duration(999999);        
    if ($cachedfeed->init()) {       
        return $cachedfeed;
    } else {
        return false;
    }
}   

function p2press_load_feed_from_source($feed) {
    $updatedfeed = new SimplePie();    
    $updatedfeed->set_feed_url($feed);
    $updatedfeed->enable_cache(true);
    $updatedfeed->set_cache_duration(0);        
    if ($updatedfeed->init()) {
        return $updatedfeed;
    } else {
        return false;
    }
}

function p2press_get_new_items($feed) { 
    $cachedfeed = p2press_load_feed_from_cache($feed);
    $cacheditems = $cachedfeed->get_items();
    $cachedarray = array();
    foreach ($cacheditems as $cacheditem) {
        $cachedarray[] = $cacheditem->get_id(true);
    }
    $freshfeed = p2press_load_feed_from_source($feed);
    $freshitems = $freshfeed->get_items();            
    $fresharray = array();            
    foreach ($freshitems as $freshitem) {
        $fresharray[] = $freshitem->get_id(true);
    }
    $newitems = array_diff($fresharray, $cachedarray);
    return $newitems;
}

function p2press_subscription_notify_result( $success, $msg ) {
	$success = strip_tags( $success );
	$success = ent2ncr( $success );
	$success = esc_html( $success );

	$msg = strip_tags( $msg );
	$msg = ent2ncr( $msg );
	$msg = esc_html( $msg );

	header( 'Content-Type: text/xml' );
	echo "<?xml version='1.0'?>\n";
	echo "<result success='{$success}' msg='{$msg}' />\n";
	exit;
}

add_action( 'add_link', 'p2press_schedule_sub' );
add_action( 'edit_link', 'p2press_schedule_sub' );
function p2press_schedule_sub( ) {
    p2press_log('scheduling sub to new link. . .');
		wp_schedule_single_event( time( ), 'p2press_schedule_sub_action' );
}

add_action( 'p2press_schedule_sub_action', 'p2press_update_daily_subs' );

add_filter('feed_link','custom_feed_link', 1, 2);

function custom_feed_link($output, $feed) {

	$feed_url = get_bloginfo('url').'/?feed=rss2&author=1';

	$feed_array = array('rss2' => $feed_url);
	$feed_array[$feed] = $feed_url;
	$output = $feed_array[$feed];

	return $output;
}
