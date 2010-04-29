<?php
/**
 * Privacy Options Settings Administration Panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** Load WordPress Administration Bootstrap */
require_once('./admin.php');

if ( ! current_user_can( 'manage_options' ) )
	wp_die( __( 'You do not have sufficient permissions to manage options for this site.' ) );

$title = __('Privacy Settings');
$parent_file = 'options-general.php';

include('./admin-header.php');
?>

<div class="wrap">
<?php screen_icon(); ?>
<h2><?php echo esc_html( $title ); ?></h2>

<form method="post" action="options.php">
<?php settings_fields('privacy'); ?>

<table class="form-table">
<tr valign="top">
<th scope="row"><?php _e('Site Visibility') ?> </th>
<td><fieldset><legend class="screen-reader-text"><span><?php _e('Site Visibility') ?> </span></legend>
<input id="blog-public" type="radio" name="blog_public" value="1" <?php checked('1', get_option('blog_public')); ?> />
<label for="blog-public"><?php _e('I would like my site to be visible to everyone, including search engines (like Google, Bing, Technorati) and archivers');?></label><br/>
<input id="blog-norobots" type="radio" name="blog_public" value="0" <?php checked('0', get_option('blog_public')); ?> />
<label for="blog-norobots"><?php _e('I would like to block search engines, but allow normal visitors'); ?></label>
<?php do_action('blog_privacy_selector'); ?>
</fieldset></td>
</tr>
<?php do_settings_fields('privacy', 'default'); ?>
</table>

<?php do_settings_sections('privacy'); ?>

<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
</p>
</form>

</div>

<?php include('./admin-footer.php') ?>