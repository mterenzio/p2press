<?php
/**
 * Edit Tags Administration Panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once('admin.php');

wp_reset_vars( array('action', 'tag', 'taxonomy', 'post_type') );

if ( empty($taxonomy) )
	$taxonomy = 'post_tag';

if ( !is_taxonomy($taxonomy) )
	wp_die(__('Invalid taxonomy'));

$tax = get_taxonomy($taxonomy);

$title = $tax->label;

if ( empty($post_type) || !in_array( $post_type, get_post_types( array('public' => true) ) ) )
	$post_type = 'post';

if ( 'post' != $post_type ) {
	$parent_file = "edit.php?post_type=$post_type";
	$submenu_file = "edit-tags.php?taxonomy=$taxonomy&amp;post_type=$post_type";
} else {
	$parent_file = 'edit.php';
	$submenu_file = "edit-tags.php?taxonomy=$taxonomy";
}

if ( isset( $_GET['action'] ) && isset($_GET['delete_tags']) && ( 'delete' == $_GET['action'] || 'delete' == $_GET['action2'] ) )
	$action = 'bulk-delete';

switch($action) {

case 'add-tag':

	check_admin_referer('add-tag');

	if ( !current_user_can($tax->edit_cap) )
		wp_die(__('Cheatin&#8217; uh?'));

	$ret = wp_insert_term($_POST['tag-name'], $taxonomy, $_POST);
	$location = 'edit-tags.php?taxonomy=' . $taxonomy;
	if ( 'post' != $post_type )
		$location .= '&post_type=' . $post_type;

	if ( $referer = wp_get_original_referer() ) {
		if ( false !== strpos($referer, 'edit-tags.php') )
			$location = $referer;
	}

	if ( $ret && !is_wp_error( $ret ) )
		$location = add_query_arg('message', 1, $location);
	else
		$location = add_query_arg('message', 4, $location);
	wp_redirect($location);
	exit;
break;

case 'delete':
	$location = 'edit-tags.php?taxonomy=' . $taxonomy;
	if ( 'post' != $post_type )
		$location .= '&post_type=' . $post_type;
	if ( $referer = wp_get_referer() ) {
		if ( false !== strpos($referer, 'edit-tags.php') )
			$location = $referer;
	}

	if ( !isset( $_GET['tag_ID'] ) ) {
		wp_redirect($location);
		exit;
	}

	$tag_ID = (int) $_GET['tag_ID'];
	check_admin_referer('delete-tag_' .  $tag_ID);

	if ( !current_user_can($tax->delete_cap) )
		wp_die(__('Cheatin&#8217; uh?'));

	wp_delete_term( $tag_ID, $taxonomy);

	$location = add_query_arg('message', 2, $location);
	wp_redirect($location);
	exit;

break;

case 'bulk-delete':
	check_admin_referer('bulk-tags');

	if ( !current_user_can($tax->delete_cap) )
		wp_die(__('Cheatin&#8217; uh?'));

	$tags = (array) $_GET['delete_tags'];
	foreach( $tags as $tag_ID ) {
		wp_delete_term( $tag_ID, $taxonomy);
	}

	$location = 'edit-tags.php?taxonomy=' . $taxonomy;
	if ( 'post' != $post_type )
		$location .= '&post_type=' . $post_type;
	if ( $referer = wp_get_referer() ) {
		if ( false !== strpos($referer, 'edit-tags.php') )
			$location = $referer;
	}

	$location = add_query_arg('message', 6, $location);
	wp_redirect($location);
	exit;

break;

case 'edit':
	$title = sprintf(_x('Edit %s', '%s: singular taxonomy name'), $tax->singular_label);

	require_once ('admin-header.php');
	$tag_ID = (int) $_GET['tag_ID'];

	$tag = get_term($tag_ID, $taxonomy, OBJECT, 'edit');
	include('edit-tag-form.php');

break;

case 'editedtag':
	$tag_ID = (int) $_POST['tag_ID'];
	check_admin_referer('update-tag_' . $tag_ID);

	if ( !current_user_can($tax->edit_cap) )
		wp_die(__('Cheatin&#8217; uh?'));

	$ret = wp_update_term($tag_ID, $taxonomy, $_POST);

	$location = 'edit-tags.php?taxonomy=' . $taxonomy;
	if ( 'post' != $post_type )
		$location .= '&post_type=' . $post_type;

	if ( $referer = wp_get_original_referer() ) {
		if ( false !== strpos($referer, 'edit-tags.php') )
			$location = $referer;
	}

	if ( $ret && !is_wp_error( $ret ) )
		$location = add_query_arg('message', 3, $location);
	else
		$location = add_query_arg('message', 5, $location);

	wp_redirect($location);
	exit;
break;

default:

if ( ! empty($_GET['_wp_http_referer']) ) {
	 wp_redirect( remove_query_arg( array('_wp_http_referer', '_wpnonce'), stripslashes($_SERVER['REQUEST_URI']) ) );
	 exit;
}

wp_enqueue_script('admin-tags');
if ( current_user_can($tax->edit_cap) )
	wp_enqueue_script('inline-edit-tax');

require_once ('admin-header.php');

$messages[1] = __('Item added.');
$messages[2] = __('Item deleted.');
$messages[3] = __('Item updated.');
$messages[4] = __('Item not added.');
$messages[5] = __('Item not updated.');
$messages[6] = __('Items deleted.');

?>

<div class="wrap nosubsub">
<?php screen_icon(); ?>
<h2><?php echo esc_html( $title );
if ( !empty($_GET['s']) )
	printf( '<span class="subtitle">' . __('Search results for &#8220;%s&#8221;') . '</span>', esc_html( stripslashes($_GET['s']) ) ); ?>
</h2>

<?php if ( isset($_GET['message']) && ( $msg = (int) $_GET['message'] ) ) : ?>
<div id="message" class="updated"><p><?php echo $messages[$msg]; ?></p></div>
<?php $_SERVER['REQUEST_URI'] = remove_query_arg(array('message'), $_SERVER['REQUEST_URI']);
endif; ?>
<div id="ajax-response"></div>

<form class="search-form" action="" method="get">
<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>" />
<input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>" />
<p class="search-box">
	<label class="screen-reader-text" for="tag-search-input"><?php printf(_x('Search %s', '%s: plural taxonomy name'), $tax->label); ?>:</label>
	<input type="text" id="tag-search-input" name="s" value="<?php _admin_search_query(); ?>" />
	<input type="submit" value="<?php echo esc_attr( sprintf(_x('Search %s', '%s: plural taxonomy name'), $tax->label) ); ?>" class="button" />
</p>
</form>
<br class="clear" />

<div id="col-container">

<div id="col-right">
<div class="col-wrap">
<form id="posts-filter" action="" method="get">
<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>" />
<input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>" />
<div class="tablenav">
<?php
$pagenum = isset( $_GET['pagenum'] ) ? absint( $_GET['pagenum'] ) : 0;
if ( empty($pagenum) )
	$pagenum = 1;

if ( 'post_tag' == $taxonomy )
	$tags_per_page = (int) get_user_option( 'edit_tags_per_page' );
elseif ( 'category' == $taxonomy )
	$tags_per_page = (int) get_user_option( 'categories_per_page' );
else
	$tags_per_page = (int) get_user_option( 'edit_' .  $taxonomy . '_per_page' );

if ( empty($tags_per_page) || $tags_per_page < 1 )
	$tags_per_page = 20;

if ( 'post_tag' == $taxonomy ) {
	$tags_per_page = apply_filters( 'edit_tags_per_page', $tags_per_page );
	$tags_per_page = apply_filters( 'tagsperpage', $tags_per_page ); // Old filter
} elseif ( 'category' == $taxonomy ) {
	$tags_per_page = apply_filters( 'edit_categories_per_page', $tags_per_page ); // Old filter
} else {
	$tags_per_page = apply_filters( 'edit_' . $taxonomy . '_per_page', $tags_per_page );
}

$searchterms = !empty($_GET['s']) ? trim(stripslashes($_GET['s'])) : '';

$page_links = paginate_links( array(
	'base' => add_query_arg( 'pagenum', '%#%' ),
	'format' => '',
	'prev_text' => __('&laquo;'),
	'next_text' => __('&raquo;'),
	'total' => ceil(wp_count_terms($taxonomy, array('search' => $searchterms)) / $tags_per_page),
	'current' => $pagenum
));

if ( $page_links )
	echo "<div class='tablenav-pages'>$page_links</div>";
?>

<div class="alignleft actions">
<select name="action">
<option value="" selected="selected"><?php _e('Bulk Actions'); ?></option>
<option value="delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php esc_attr_e('Apply'); ?>" name="doaction" id="doaction" class="button-secondary action" />
<?php wp_nonce_field('bulk-tags'); ?>
</div>

<br class="clear" />
</div>

<div class="clear"></div>
<?php $table_type = ('category' == $taxonomy ? 'categories' : 'edit-tags'); ?>
<table class="widefat tag fixed" cellspacing="0">
	<thead>
	<tr>
<?php print_column_headers($table_type); ?>
	</tr>
	</thead>

	<tfoot>
	<tr>
<?php print_column_headers($table_type, false); ?>
	</tr>
	</tfoot>

	<tbody id="the-list" class="list:tag">
<?php tag_rows( $pagenum, $tags_per_page, $searchterms, $taxonomy ); ?>
	</tbody>
</table>

<div class="tablenav">
<?php
if ( $page_links )
	echo "<div class='tablenav-pages'>$page_links</div>";
?>

<div class="alignleft actions">
<select name="action2">
<option value="" selected="selected"><?php _e('Bulk Actions'); ?></option>
<option value="delete"><?php _e('Delete'); ?></option>
</select>
<input type="submit" value="<?php esc_attr_e('Apply'); ?>" name="doaction2" id="doaction2" class="button-secondary action" />
</div>

<br class="clear" />
</div>

<br class="clear" />
</form>

<?php if ( 'category' == $taxonomy ) : ?>
<div class="form-wrap">
<p><?php printf(__('<strong>Note:</strong><br />Deleting a category does not delete the posts in that category. Instead, posts that were only assigned to the deleted category are set to the category <strong>%s</strong>.'), apply_filters('the_category', get_cat_name(get_option('default_category')))) ?></p>
<p><?php printf(__('Categories can be selectively converted to tags using the <a href="%s">category to tag converter</a>.'), 'admin.php?import=wp-cat2tag') ?></p>
</div>
<?php elseif ( 'post_tag' == $taxonomy ) : ?>
<div class="form-wrap">
<p><?php printf(__('Tags can be selectively converted to categories using the <a href="%s">tag to category converter</a>'), 'admin.php?import=wp-cat2tag&amp;step=3') ;?>.</p>
</div>
<?php endif;
do_action('after-' . $taxonomy . '-table', $taxonomy);
?>

</div>
</div><!-- /col-right -->

<div id="col-left">
<div class="col-wrap">

<?php
if ( current_user_can( $tax->edit_cap ) )
	$tag_cloud = wp_tag_cloud( array( 'taxonomy' => $taxonomy, 'echo' => false, 'link' => 'edit' ) );
else
	$tag_cloud = wp_tag_cloud( array( 'taxonomy' => $taxonomy, 'echo' => false ) );

if ( $tag_cloud ) :
?>
<div class="tagcloud">
<h3><?php printf(_x('Popular %s', '%s: plural taxonomy name'), $tax->label); ?></h3>
<?php echo $tag_cloud; unset( $tag_cloud ); ?>
</div>
<?php
endif;

if ( current_user_can($tax->edit_cap) ) {
	if ( 'category' == $taxonomy )
		do_action('add_category_form_pre', (object)array('parent' => 0) );  // Back compat hook. Deprecated in preference to $taxonomy_pre_add_form
	else
		do_action('add_tag_form_pre', $taxonomy); // Back compat hook. Applies to all Taxonomies -not- categories
	do_action($taxonomy . '_pre_add_form', $taxonomy);
?>

<div class="form-wrap">
<h3><?php printf(_x('Add a New %s', '%s: singular taxonomy name'), $tax->singular_label); ?></h3>
<form id="addtag" method="post" action="edit-tags.php" class="validate">
<input type="hidden" name="action" value="add-tag" />
<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>" />
<?php wp_nonce_field('add-tag'); ?>

<div class="form-field form-required">
	<label for="tag-name"><?php echo _x('Name', 'Taxonomy Name'); ?></label>
	<input name="tag-name" id="tag-name" type="text" value="" size="40" aria-required="true" />
	<p><?php _e('The name is how it appears on your site.'); ?></p>
</div>
<?php if ( !is_multisite() ) : ?>
<div class="form-field">
	<label for="tag-slug"><?php echo _x('Slug', 'Taxonomy Slug'); ?></label>
	<input name="slug" id="tag-slug" type="text" value="" size="40" />
	<p><?php _e('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.'); ?></p>
</div>
<?php endif; // is_multisite() ?>
<?php if ( is_taxonomy_hierarchical($taxonomy) ) : ?>
<div class="form-field">
	<label for="parent"><?php echo _x('Parent', 'Taxonomy Parent'); ?></label>
	<?php wp_dropdown_categories(array('hide_empty' => 0, 'hide_if_empty' => false, 'taxonomy' => $taxonomy, 'name' => 'parent', 'orderby' => 'name', 'hierarchical' => true, 'show_option_none' => __('None'))); ?>
	<?php if ( 'category' == $taxonomy ) : // @todo: Generic text for hierarchical taxonomies ?>
		<p><?php _e('Categories, unlike tags, can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.'); ?></p>
	<?php endif; ?>
</div>
<?php endif; // is_taxonomy_hierarchical() ?>
<div class="form-field">
	<label for="tag-description"><?php echo _x('Description', 'Taxonomy Description'); ?></label>
	<textarea name="description" id="tag-description" rows="5" cols="40"></textarea>
	<p><?php _e('The description is not prominent by default; however, some themes may show it.'); ?></p>
</div>

<?php
if ( ! is_taxonomy_hierarchical($taxonomy) )
	do_action('add_tag_form_fields', $taxonomy);
do_action($taxonomy . '_add_form_fields', $taxonomy);
?>
<p class="submit"><input type="submit" class="button" name="submit" id="submit" value="<?php echo esc_attr(sprintf(_x('Add %s', '%s: singular  taxonomy name'), $tax->singular_label)); ?>" /></p>
<?php
if ( 'category' == $taxonomy )
	do_action('edit_category_form',	(object)array('parent' => 0) );  // Back compat hook. Deprecated in preference to $taxonomy_add_form
else
	do_action('add_tag_form', $taxonomy); // Back compat hook. Applies to all Taxonomies -not- categories
do_action($taxonomy . '_add_form', $taxonomy);
?>
</form></div>
<?php } ?>

</div>
</div><!-- /col-left -->

</div><!-- /col-container -->
</div><!-- /wrap -->

<?php inline_edit_term_row('edit-tags', $taxonomy); ?>

<?php
break;
}

include('admin-footer.php');

?>