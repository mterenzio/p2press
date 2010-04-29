<?php
/**
 * Network installation administration panel.
 *
 * A multi-step process allowing the user to enable a network of WordPress sites.
 *
 * @since 3.0.0
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once( './admin.php' );

if ( ! is_super_admin() )
	wp_die( __( 'You do not have sufficient permissions to manage options for this site.' ) );

if ( is_multisite() && ! defined( 'MULTISITE' ) )
	wp_die( __('The Network creation panel is not for WordPress MU networks.') );

// We need to create references to ms global tables to enable Network.
foreach ( $wpdb->tables( 'ms_global' ) as $table => $prefixed_table )
	$wpdb->$table = $prefixed_table;

/**
 * Check for an existing network.
 *
 * @since 3.0.0
 * @return Whether a network exists.
 */
function network_domain_check() {
	global $wpdb;
	if ( $wpdb->get_var( "SHOW TABLES LIKE '$wpdb->site'" ) )
		return $wpdb->get_var( "SELECT domain FROM $wpdb->site ORDER BY id ASC LIMIT 1" );
	return false;
}

/**
 * Allow subdomain install
 *
 * @since 3.0.0
 * @return bool - whether subdomain install is allowed
 */
function allow_subdomain_install() {
	$domain = preg_replace( '|https?://[^/]|', '', get_option( 'siteurl' ) );
	if( false !== strpos( $domain, '/' ) || 'localhost' == $_SERVER[ 'HTTP_HOST' ] )
		return false;

	return true;
}
/**
 * Get base domain of network.
 *
 * @since 3.0.0
 * @return string Base domain.
 */
function get_clean_basedomain() {
	if ( $existing_domain = network_domain_check() )
		return $existing_domain;
	$domain = preg_replace( '|https?://|', '', get_option( 'siteurl' ) );
	if ( $slash = strpos( $domain, '/' ) )
		$domain = substr( $domain, 0, $slash );
	return $domain;
}

if ( ! network_domain_check() && ( ! defined( 'WP_ALLOW_MULTISITE' ) || ! WP_ALLOW_MULTISITE ) )
	wp_die( __( 'You must define the <code>WP_ALLOW_MULTISITE</code> constant as true in your wp-config.php file to allow creation of a Network.' ) );

$title = __( 'Create a Network of WordPress Sites' );
$parent_file = 'tools.php';

// @todo: Documentation?
// add_contextual_help( $current_screen, ... );

include( './admin-header.php' );
?>
<div class="wrap">
<?php screen_icon(); ?>
<h2><?php echo esc_html( $title ); ?></h2>

<form method="post">
<?php
/**
 * Prints step 1 for Network installation process.
 *
 * @todo Realistically, step 1 should be a welcome screen explaining what a Network is and such. Navigating to Tools > Network
 * 	should not be a sudden "Welcome to a new install process! Fill this out and click here." See also contextual help todo.
 *
 * @since 3.0.0
 */
function network_step1( $errors = false ) {

	if ( get_option( 'siteurl' ) != get_option( 'home' ) ) {
		echo '<div class="error"><p><strong>' . __('Error:') . '</strong> ' . sprintf( __( 'Your <strong>WordPress address</strong> must match your <strong>Site address</strong> before creating a Network. See <a href="%s">General Settings</a>.' ), esc_url( admin_url( 'options-general.php' ) ) ) . '</strong></p></div>';
		include ('./admin-footer.php' );
		die();
	}

	$active_plugins = get_option( 'active_plugins' );
	if ( ! empty( $active_plugins ) ) {
		echo '<div class="updated"><p><strong>' . __('Warning:') . '</strong> ' . sprintf( __( 'Please <a href="%s">deactivate</a> your plugins before enabling the Network feature.' ), admin_url( 'plugins.php' ) ) . '</p></div><p>' . __(' Once the network is created, you may reactivate your plugins.' ) . '</p>';
		include( './admin-footer.php' );
		die();
	}

	$hostname = get_clean_basedomain();
	$has_ports = strstr( $hostname, ':' );
	if ( ( false !== $has_ports && ! in_array( $has_ports, array( ':80', ':443' ) ) )
		|| ( $no_ip = preg_match( '|[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+|', $hostname ) ) ) {
		echo '<div class="error"><p><strong>' . __( 'Error:') . '</strong> ' . __( 'You cannot install a network of sites with your server address.' ) . '</strong></p></div>';
		if ( $no_ip )
			echo '<p>' . __('You cannot use an IP address such as <code>127.0.0.1</code>.' ) . '</p>';
		else
			echo '<p>' . sprintf( __('You cannot use port numbers such as <code>%s</code>.' ), $has_ports ) . '</p>';
		echo '<a href="' . esc_url( admin_url() ) . '">' . __( 'Return to Dashboard' ) . '</a>';
		include( './admin-footer.php' );
		die();
	}

	wp_nonce_field( 'install-network-1' );

	$error_codes = array();
	if ( is_wp_error( $errors ) ) {
		echo '<div class="error"><p><strong>' . __( 'ERROR: The network could not be created.' ) . '</strong></p>';
		foreach ( $errors->get_error_messages() as $error )
			echo "<p>$error</p>";
		echo '</div>';
		$error_codes = $errors->get_error_codes();
	}

	$site_name = ( ! empty( $_POST['sitename'] ) && ! in_array( 'empty_sitename', $error_codes ) ) ? $_POST['sitename'] : sprintf( _x('%s Sites', 'Default network name' ), get_option( 'blogname' ) );
	$admin_email = ( ! empty( $_POST['email'] ) && ! in_array( 'invalid_email', $error_codes ) ) ? $_POST['email'] : get_option( 'admin_email' );
	?>
	<p><?php _e( 'Welcome to the Network installation process!' ); ?></p>
	<p><?php _e( "Fill in the information below and you'll be on your way to creating a network of WordPress sites. We'll create configuration files in the next step." ); ?></p>
	<?php

	// @todo IIS and ! $is_apache
	if ( isset( $_POST['subdomain_install'] ) ) {
		$subdomain_install = (bool) $_POST['subdomain_install'];
	} elseif ( apache_mod_loaded('mod_rewrite') ) { // assume nothing
		$subdomain_install = true;
	} else {
		$subdomain_install = false;
		if ( got_mod_rewrite() ) // dangerous assumptions
			echo '<div class="updated inline"><p><strong>' . __( 'Note:' ) . '</strong> ' . __( 'Please make sure the Apache <code>mod_rewrite</code> module is installed as it will be used at the end of this install.' ) . '</p>';
		else
			echo '<div class="error inline"><p><strong>' . __( 'Warning!' ) . '</strong> ' . __( 'It looks like the Apache <code>mod_rewrite</code> module is not installed.' ) . '</p>';
		echo '<p>' . __( 'If <code>mod_rewrite</code> is disabled, ask your administrator to enable that module, or look at the <a href="http://httpd.apache.org/docs/mod/mod_rewrite.html">Apache documentation</a> or <a href="http://www.google.com/search?q=apache+mod_rewrite">elsewhere</a> for help setting it up.' ) . '</p></div>';
	}

	if ( allow_subdomain_install() ) : ?>
		<h3><?php esc_html_e( 'Addresses of Sites in your Network' ); ?></h3>
		<p><?php _e( 'Please choose whether you would like sites in your WordPress network to use sub-domains or sub-directories. <strong>You cannot change this later.</strong>' ); ?></p>
		<p><?php _e( "You will need a wildcard DNS record if you're going to use the virtual host (sub-domain) functionality." ); ?></p>
		<?php // @todo: Link to an MS readme? ?>
		<table class="form-table">
			<tr>
				<th><label><input type='radio' name='subdomain_install' value='1'<?php checked( $subdomain_install ); ?> /> Sub-domains</label></th>
				<td><?php printf( _x( 'like <code>site1.%1$s</code> and <code>site2.%1$s</code>', 'subdomain examples' ), $hostname ); ?></td>
			</tr>
			<tr>
				<th><label><input type='radio' name='subdomain_install' value='0'<?php checked( ! $subdomain_install ); ?> /> Sub-directories</label></th>
				<td><?php printf( _x( 'like <code>%1$s/site1</code> and <code>%1$s/site2</code>', 'subdirectory examples' ), $hostname ); ?></td>
			</tr>
		</table>

<?php
	endif;

		$is_www = ( 0 === strpos( $hostname, 'www.' ) );
		if ( $is_www ) :
		?>
		<h3><?php esc_html_e( 'Server Address' ); ?></h3>
		<p><?php printf( __( 'We recommend you change your siteurl to <code>%1$s</code> before enabling the network feature. It will still be possible to visit your site using the "www" prefix with an address like <code>%2$s</code> but any links will not have the "www" prefix.' ), substr( $hostname, 4 ), $hostname ); ?></h3>
		<table class="form-table">
			<tr>
				<th scope='row'><?php esc_html_e( 'Server Address' ); ?></th>
				<td>
					<?php printf( __( 'The Internet address of your network will be <code>%s</code>.' ), $hostname ); ?>
				</td>
			</tr>
		</table>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Network Details' ); ?></h3>
		<table class="form-table">
		<?php if ( 'localhost' == $hostname ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sub-directory Install' ); ?></th>
				<td><?php _e('Because you are using <code>localhost</code>, the sites in your WordPress network must use sub-directories. Consider using <code>localhost.localdomain</code> if you wish to use sub-domains.'); ?></td>
			</tr>
		<?php elseif ( !allow_subdomain_install() ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Sub-directory Install' ); ?></th>
				<td><?php _e('Because your install is in a directory, the sites in your WordPress network must use sub-directories.'); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( ! $is_www ) : ?>
			<tr>
				<th scope='row'><?php esc_html_e( 'Server Address' ); ?></th>
				<td>
					<?php printf( __( 'The Internet address of your network will be <code>%s</code>.' ), $hostname ); ?>
				</td>
			</tr>
		<?php endif; ?>
			<tr>
				<th scope='row'><?php esc_html_e( 'Network Title' ); ?></th>
				<td>
					<input name='sitename' type='text' size='45' value='<?php echo esc_attr( $site_name ); ?>' />
					<br /><?php _e( 'What would you like to call your network?' ); ?>
				</td>
			</tr>
			<tr>
				<th scope='row'><?php esc_html_e( 'Admin E-mail Address' ); ?></th>
				<td>
					<input name='email' type='text' size='45' value='<?php echo esc_attr( $admin_email ); ?>' />
					<br /><?php _e( 'Your email address.' ); ?>
				</td>
			</tr>
		</table>
		<p class='submit'><input class="button-primary" name='submit' type='submit' value='<?php esc_attr_e( 'Install' ); ?>' /></p>
		<?php
}

/**
 * Prints step 2 for Network installation process.
 *
 * @since 3.0.0
 */
function network_step2( $errors = false ) {
	global $base, $wpdb;
	$hostname = get_clean_basedomain();

	// Wildcard DNS message.
	if ( is_wp_error( $errors ) )
		echo '<div class="error">' . $errors->get_error_message() . '</div>';

	if ( $_POST ) {
		$vhost = !allow_subdomain_install() ? false : (bool) $_POST['subdomain_install'];
	} else {
		if ( is_multisite() ) {
			$vhost = is_subdomain_install();
?>
	<div class="updated"><p><strong><?php _e( 'Notice: The Network feature is already enabled.' ); ?></strong> <?php _e( 'The original configuration steps are shown here for reference.' ); ?></p></div>
<?php	} else {
			$vhost = (bool) $wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE site_id = 1 AND meta_key = 'subdomain_install'" );
?>
	<div class="error"><p><strong><?php _e('Warning:'); ?></strong> <?php _e( 'An existing WordPress network was detected.' ); ?></p></div>
	<p><?php _e( 'Please complete the configuration steps. To create a new network, you will need to empty or remove the network database tables.' ); ?></p>
<?php
		}
	}

	if ( $_POST || ! is_multisite() ) {
?>
		<h3><?php esc_html_e( 'Enabling the Network' ); ?></h3>
		<p><?php _e( 'Complete the following steps to enable the features for creating a network of sites.' ); ?></p>
		<div class="updated inline"><p><?php _e( '<strong>Caution:</strong> We recommend you backup your existing <code>wp-config.php</code> and <code>.htaccess</code> files.' ); ?></p></div>
<?php
	}
?>
		<ol>
			<li><p><?php printf( __( 'Create a <code>blogs.dir</code> directory in <code>%s</code>. This directory is used to stored uploaded media for your additional sites and must be writeable by the web server.' ), WP_CONTENT_DIR ); ?></p></li>
			<li><p><?php printf( __( 'Add the following to your <code>wp-config.php</code> file in <code>%s</code>:' ), ABSPATH ); ?></p>
				<textarea class="code" readonly="readonly" cols="100" rows="7">
define( 'MULTISITE', true );
define( 'VHOST', '<?php echo $vhost ? 'yes' : 'no'; ?>' );
$base = '<?php echo $base; ?>';
define( 'DOMAIN_CURRENT_SITE', '<?php echo $hostname; ?>' );
define( 'PATH_CURRENT_SITE', '<?php echo $base; ?>' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );</textarea>
<?php
	$keys_salts = array( 'AUTH_KEY' => '', 'SECURE_AUTH_KEY' => '', 'LOGGED_IN_KEY' => '', 'NONCE_KEY' => '', 'AUTH_SALT' => '', 'SECURE_AUTH_SALT' => '', 'LOGGED_IN_SALT' => '', 'NONCE_SALT' => '' );
	foreach ( $keys_salts as $c => $v ) {
		if ( defined( $c ) )
			unset( $keys_salts[ $c ] );
	}
	if ( ! empty( $keys_salts ) ) {
		$from_api = wp_remote_get( 'https://api.wordpress.org/secret-key/1.1/salt/' );
		if ( is_wp_error( $from_api ) ) {
			foreach ( $keys_salts as $c => $v ) {
				$keys_salts[ $c ] = wp_generate_password( 64, true, true );
			}
		} else {
			$from_api = explode( "\n", wp_remote_retrieve_body( $from_api ) );
			foreach ( $keys_salts as $c => $v ) {
				$keys_salts[ $c ] = substr( array_shift( $from_api ), 28, 64 );
			}
		}
		$num_keys_salts = count( $keys_salts );
?>
	<p><?php
		echo _n( 'This unique authentication key is also missing from your <code>wp-config.php</code> file.', 'These unique authentication keys are also missing from your <code>wp-config.php</code> file.', $num_keys_salts ); ?> <?php _e( 'To make your installation more secure, you should also add:' ) ?></p>
	<textarea class="code" readonly="readonly" cols="100" rows="<?php echo $num_keys_salts; ?>"><?php
	foreach ( $keys_salts as $c => $v ) {
		echo "\ndefine( '$c', '$v' );";
	}
?></textarea>
<?php
	}
?>
</li>
<?php
// Construct an htaccess file.
$htaccess_file = 'RewriteEngine On
RewriteBase ' . $base . '
RewriteRule ^index\.php$ - [L]

# uploaded files
RewriteRule ^' . ( $vhost ? '' : '([_0-9a-zA-Z-]+/)?' ) . 'files/(.+) wp-includes/ms-files.php?file=$' . ( $vhost ? 1 : 2 ) . ' [L]' . "\n";

if ( ! $vhost )
	$htaccess_file .= "\n# add a trailing slash to /wp-admin\n" . 'RewriteRule ^([_0-9a-zA-Z-]+/)?wp-admin$ $1wp-admin/ [R=301,L]' . "\n";

$htaccess_file .= "\n" . 'RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]';

// @todo custom content dir.
if ( ! $vhost )
	$htaccess_file .= "\n" . 'RewriteRule  ^([_0-9a-zA-Z-]+/)?(wp-(content|admin|includes).*) $2 [L]
RewriteRule  ^([_0-9a-zA-Z-]+/)?(.*\.php)$ $2 [L]';

$htaccess_file .= "\nRewriteRule . index.php [L]";

?>
			<li><p><?php printf( __( 'Add the following to your <code>.htaccess</code> file in <code>%s</code>, replacing other WordPress rules:' ), ABSPATH ); ?></p>
				<textarea class="code" readonly="readonly" cols="100" rows="<?php echo $vhost ? 11 : 16; ?>">
<?php echo wp_htmledit_pre( $htaccess_file ); ?>
</textarea></li>
		</ol>
<?php if ( !is_multisite() ) { ?>
		<p><?php printf( __( 'Once you complete these steps, your network is enabled and configured.') ); ?> <a href="<?php echo esc_url( admin_url() ); ?>"><?php _e( 'Return to Dashboard' ); ?></a></p>
<?php
	}
}

$base = trailingslashit( stripslashes( dirname( dirname( $_SERVER['SCRIPT_NAME'] ) ) ) );

if ( $_POST ) {
	check_admin_referer( 'install-network-1' );

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	// create network tables
	install_network();
	$hostname = get_clean_basedomain();
	$subdomain_install = !allow_subdomain_install() ? false : (bool) $_POST['subdomain_install'];
	if ( ! network_domain_check() ) {
		$result = populate_network( 1, get_clean_basedomain(), sanitize_email( $_POST['email'] ), $_POST['sitename'], $base, $subdomain_install );
		if ( is_wp_error( $result ) ) {
			if ( 1 == count( $result->get_error_codes() ) && 'no_wildcard_dns' == $result->get_error_code() )
				network_step2( $result );
			else
				network_step1( $result );
		} else {
			network_step2();
		}
	} else {
		network_step2();
	}
} elseif ( is_multisite() || network_domain_check() ) {
	network_step2();
} else {
	network_step1();
}
?>
</form>
</div>

<?php include( './admin-footer.php' ); ?>
