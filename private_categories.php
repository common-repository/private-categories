<?php
/*
Plugin Name: Private Categories
Plugin URI: http://willwyatt.com
Description: Show or hide posts based on categories and a user's level.
Version: 0.1a
Author: Will Wyatt
Author URI: http://willwyatt.com

Notes:
1. 	This plugin is inspired by the Post Levels plugin 
	http://fortes.com/2005/01/22/introducingpostlevels
2. 	This plugin requires at least 1.5.1 because of bug #902 (thanks 
	macmanx for answering this in 
	http://wordpress.org/support/topic/29351#post-165490).
	If you are on 1.5 you'll need to upgrade to 1.5.1 or patch your files manually.
3.	For this plugin to completly work, you'll need to change your
	templates in a couple of places. I'll reference Kubrick since everyone with
	1.5.1 should have it installed.
	
Caveats:
	1.	This plugin allows you to choose more than one category as a private category. If you have two
		categories marked as private categories and you have a post in two categories, both categories
		have to be private for the post to be private.
		For instance:
			Private Categories - Travel (infer), Work(private)
			Post 12's Categories Travel (infer), Work(private) - this post would be private
			Post 15's Categories Travel, Work, Consulting - this post would not be private
			Post 20's Categories Travel - This post would be private
			Post 25's Categories Consulting - This post would not be private
		In other words, if your post if placed into several categories, all the categories must
		be private categories for your post to be private. If you anticipate you'll be
	2. 	If the user 'forcefully browses' to a private category, they won't see any of the posts, but
		they will be able to see the title of the category in the 'You are currently browsing the archives...'
		in the sidebar.
	3. 	If you only have private posts in a given month, the month archives will still show up in the sidebar,
		even though no posts will be shown to the user.
		
Usage:
	1.	Put this file, private_categories.phps.txt into your /wp-content/plugins/ directory. Rename it to
		private_categories.php
		You should see Private Categories in your Plugins panel in the admin tool. Activate the plugin.
	2.	Navigate to Options >> Private Categories in the admin tool. Choose the categories you'd
		like to be private and the minimum level needed to see the private posts.
	3. 	If you want to hide the previous and next hidden post titles when viewing individual posts 
		you'll need to make two template changes.
		In the default theme, Kubrick, you'll want to change the following in single.php
			<div class="alignleft"><?php previous_post('&laquo; %','','yes') ?></div>
			<div class="alignright"><?php next_post(' % &raquo;','','yes') ?></div>
		to
		  	<div class="alignleft"><?php wwpc_private_previous_post('&laquo; %','','yes') ?></div>
			<div class="alignright"><?php wwpc_private_next_post(' % &raquo;','','yes') ?></div>
	4.	If you want to make it so hidden categories don't show up in the categories browser you'll
		need to make one template change.
		In the default theme, Kubrick, you'll want to change the following in sidebar.php
			<?php list_cats(0, '', 'name', 'asc', '', 1, 0, 1, 1, 1, 1, 0,'','','','','') ?>
		to
			<?php wwpc_show_categories(0, '', 'name', 'asc', '', 1, 0, 1, 1, 1, 1, 0,'','','','','') ?>
	5.	Composing a post, be sure to choose ALL the categories you marked private on the options page
		and your posts will be hidden. Cool!
		
Comments:
	Mad props to the WordPress developement team. This is my first plugin (I'm sure it shows). Plugin
	writing is much easier than I expected. I'd like to see some additional hooks (the previous and next
	links and the category browsers and an is_admin() would be great).
	
	Like any other plugin, I'd recommend testing before you roll this out to a production site. Since
	this plugin deals with making posts private, I'd make extra sure you test. I can't be held responsible
	if something that you think should be hidden isn't actually hidden. Everything works for me on my
	installation. YMMV.		

Terms of Use:
Except where otherwise noted, this software is:

    * Copyright Will Wyatt
    * Licensed under the terms of the CC/GNU GPL (http://creativecommons.org/licenses/GPL/2.0)
    * Provided as is, with NO WARRANTY whatsoever

The plugin is free. If you like it (or if you don't), I'd love to get some feedback.

Revision History:
05/16/2005 - 0.1a 	- Project Created at wp-plugins.org
05/16/2005 - 0.1 	- Initial Development

*/

// Get the minimum level for viewing hidden posts from the database
$wwpc_settings_level = get_settings('wwpc_level');

// Get the category ids of the private categories
$wwpc_keys = get_settings('wwpc_cats');

// Put the private keys into a syntax for the queries - for example '7','3'
if ($wwpc_keys <> ''){
	$wwpc_query_keys = '\'' .  implode('\',\'', $wwpc_keys ) . '\'';
}
else {
	$wwpc_query_keys = '';
}

// Put the private keys into a syntax for the category function - for exampld 7,3
if ($wwpc_keys <> ''){
	$wwpc_category_keys = implode(',', $wwpc_keys );
}
else {
	$wwpc_category_keys = '';
}

// Left join the post2cat table so we can get the category_ids for the posts.
$wwpc_index_categories_join = " /*index*/ LEFT JOIN {$wpdb->post2cat} ON {$wpdb->posts}.ID = {$wpdb->post2cat}.post_id ";

// The category pages don't need the join, they already have post2cat joined.
$wwpc_categories_join = " /*category*/ ";	

function wwpc_privatelevel_posts_join($join){
	global $wpdb, $wwpc_categories_join, $wwpc_index_categories_join;
	// If the page is not a category page, then we need the JOIN.
	if (!is_category()){
		return $wwpc_index_categories_join . $join;
	}
	else {
		return $wwpc_categories_join . $join;
	}
	
}

// In case we're running standalone, for some odd reason
if (function_exists('add_filter')){
	global $user_level, $wwpc_settings_level; get_currentuserinfo();

	// we need to get the categories for all the pages, except the category pages.
	add_filter('posts_join', 'wwpc_privatelevel_posts_join');
	
	// check a user's level, if they aren't logged in, they don't have a level.
	if (!empty($user_level)){
		// we need to check to see if the user's level is high enough
		// to see the posts. If it is, then call the function that
		// includes the private posts. Otherwise, we'll call the function
		// that excludes the posts.
		if ($user_level <  $wwpc_settings_level) {
			add_filter('posts_where', 'wwpc_privatelevel_posts_where_exclude'); 
		}
	}
	else
	{
		// call the function that will specifically exclude the posts that are marked as private.
		// There isn't any way that someone who isn't logged in should be able to view the posts
		// that are marked as private.
		add_filter('posts_where', 'wwpc_privatelevel_posts_where_exclude');    
	}
}

// If the user isn't logged in , or their userlevel isn't high enough to view private categories
// we need to add a clause to WHERE to not include the posts that have the private categories
function wwpc_privatelevel_posts_where_exclude($where){
	global $wwpc_query_keys;
	global $user_level, $wpdb;
	if (!is_single()){
		return str_replace('post_status = "publish"', '(post_status = "publish" AND ' . "({$wpdb->post2cat}.category_id NOT IN ($wwpc_query_keys) ))/*exclude*/", $where);
	}
	else {
		return $where;
	}
}

// This function is a replacement for the delivered previous_post. It will take into account if a user
// should be able to see private posts. If they can't then the posts won't show up in the previous_post link.
function wwpc_private_previous_post($format='%', $previous='previous post: ', $title='yes', $in_same_cat='no', $limitprev=1, $excluded_categories='') {
    global $id, $post, $wpdb;
    global $posts, $posts_per_page, $s, $wwpc_index_categories_join, $wwpc_categories_join, $user_level, $wwpc_settings_level, $wwpc_query_keys;

    if(($posts_per_page == 1) || is_single()) {

        $current_post_date = $post->post_date;
        $current_category = $post->post_category;

        $sqlcat = '';
        if ($in_same_cat != 'no') {
            $sqlcat = " AND post_category = '$current_category' ";
        }

        $sql_exclude_cats = '';
        if (!empty($excluded_categories)) {
        	$blah = explode('and', $excluded_categories);
            foreach($blah as $category) {
                $category = intval($category);
                $sql_exclude_cats .= " AND post_category != $category";
            }
        }
        
        $limitprev--;
        $lastpost = @$wpdb->get_row("SELECT ID, post_title FROM $wpdb->posts $wwpc_index_categories_join WHERE post_date < '$current_post_date' AND post_status = 'publish' $sqlcat $sql_exclude_cats " . wwpc_show_private() . " ORDER BY post_date DESC LIMIT $limitprev, 1");
        if ($lastpost) {
            $string = '<a href="'.get_permalink($lastpost->ID).'">'.$previous;
            if ($title == 'yes') {
                $string .= wptexturize($lastpost->post_title);
            }
            $string .= '</a>';
            $format = str_replace('%', $string, $format);
            echo $format;
        }
    }
}

// This function is a replacement for the delivered next_post. It will take into account if a user
// should be able to see private posts. If they can't then the posts won't show up in the next_post link.
function wwpc_private_next_post($format='%', $next='next post: ', $title='yes', $in_same_cat='no', $limitnext=1, $excluded_categories='') {
    global $posts_per_page, $post, $wpdb;
    global $posts, $posts_per_page, $s, $wwpc_index_categories_join, $wwpc_categories_join, $user_level, $wwpc_settings_level, $wwpc_query_keys;
    if(1 == $posts_per_page || is_single()) {

        $current_post_date = $post->post_date;
        $current_category = $post->post_category;

        $sqlcat = '';
        if ($in_same_cat != 'no') {
            $sqlcat = " AND post_category='$current_category' ";
        }

        $sql_exclude_cats = '';
        if (!empty($excluded_categories)) {
            $blah = explode('and', $excluded_categories);
            foreach($blah as $category) {
                $category = intval($category);
                $sql_exclude_cats .= " AND post_category != $category";
            }
        }

        $now = current_time('mysql', 1);

        $limitnext--;

        $nextpost = @$wpdb->get_row("SELECT ID,post_title FROM $wpdb->posts $wwpc_index_categories_join WHERE post_date > '$current_post_date' AND post_date_gmt < '$now' AND post_status = 'publish' $sqlcat $sql_exclude_cats AND ID != $post->ID " . wwpc_show_private() . " ORDER BY post_date ASC LIMIT $limitnext,1");
        if ($nextpost) {
            $string = '<a href="'.get_permalink($nextpost->ID).'">'.$next;
            if ($title=='yes') {
                $string .= wptexturize($nextpost->post_title);
            }
            $string .= '</a>';
            $format = str_replace('%', $string, $format);
            echo $format;
        }
    }
}

// Figure out if the user can see the private posts or not.
function wwpc_show_private(){
	global $wpdb, $user_level, $wwpc_settings_level, $wwpc_query_keys;

	if (!empty($user_level)){
		if ($user_level >=  $wwpc_settings_level) {
			$wwpc_exclude_cats = ' ';
		}
		else {
		$wwpc_exclude_cats = 'AND ' . "({$wpdb->post2cat}.category_id NOT IN ($wwpc_query_keys) )/*exclude*/";
		}
	}
	else {
		$wwpc_exclude_cats = 'AND ' . "({$wpdb->post2cat}.category_id NOT IN ($wwpc_query_keys) )/*exclude*/";
	}
	
	return $wwpc_exclude_cats;
}

// This function is a replacment for the delivered list_cats function. If the user can't see private categories
// the private categories won't show up in the categories browser.
function wwpc_show_categories($optionall = 1, $all = 'All', $sort_column = 'ID', $sort_order = 'asc', $file = '', $list = true, $optiondates = 0, $optioncount = 0, $hide_empty = 1, $use_desc_for_title = 1, $children=FALSE, $child_of=0, $categories=0, $recurse=0, $feed = '', $feed_image = '', $exclude = '', $hierarchical=FALSE){
	global $wpdb, $user_level, $wwpc_settings_level, $wwpc_category_keys;
	
	// If the user isn't logged in
	If (!empty($user_level)){
		if ($user_level <  $wwpc_settings_level) {
			// If the user's level isn't high enough, append the excluded categories to the delivered exclusion from the template.
			return list_cats($optionall, $all, $sort_column, $sort_order, $file, $list, $optiondates, $optioncount, $hide_empty, $use_desc_for_title, $children, $child_of, $categories, $recurse, $feed, $feed_image, $exclude . ',' . $wwpc_category_keys, $hierarchical);
		}
		else {
			// If the user's level is high enough, use the delivered exclusion from the template.
			return list_cats($optionall, $all, $sort_column, $sort_order, $file, $list, $optiondates, $optioncount, $hide_empty, $use_desc_for_title, $children, $child_of, $categories, $recurse, $feed, $feed_image, $exclude, $hierarchical);
		}
	}
	else {
		// If the user's level isn't high enough, append the excluded categories to the delivered exclusion from the template.
		return list_cats($optionall, $all, $sort_column, $sort_order, $file, $list, $optiondates, $optioncount, $hide_empty, $use_desc_for_title, $children, $child_of, $categories, $recurse, $feed, $feed_image, $exclude . ',' . $wwpc_category_keys, $hierarchical);
	}
}

// This function is used by the Private Categories admin panel.
function wwpc_categories($wwpc_admin_categories) {

	$wwpc_keys = get_settings('wwpc_cats');
	if ($wwpc_keys == ''){
		$wwpc_keys = array(
			'cat_id' => ''
		);
	}

	foreach($wwpc_admin_categories as $wwpc_admin_category) {
		echo '<label for="category-', $wwpc_admin_category['cat_ID'], '" class="selectit"><input value="', $wwpc_admin_category['cat_ID'],
			'" type="checkbox" name="post_category[]" id="category-', $wwpc_admin_category['cat_ID'], '"',
			(in_array($wwpc_admin_category['cat_ID'], $wwpc_keys) ? ' checked="checked"' : ""), '/> ', wp_specialchars($wwpc_admin_category['cat_name']), "</label>\n";

		if(isset($wwpc_admin_categories['children'])) {
			echo "\n<span class='cat-nest'>\n";
			write_nested_categories($wwpc_admin_categories['children']);
			echo "</span>\n";
		}
 	}
}

// Add the panel to the admin tool.
function wwpc_add_privacy_pages(){
	if (function_exists('add_options_page')){
		add_options_page('Private Categories Options', 'Private Categories', 8,'', 'wwpc_privacy_options_page');
	}
}

// mt_options_page() displays the page content for the Test Options submenu
function wwpc_privacy_options_page() { 

		if (isset($_POST['wwpc_update'])){
			if(empty($_POST['wwpc_settings_level'])){
				?><div class="updated"><p><strong><?php _e('Please enter a Level Option.', 'Private Categories') ?></strong></p></div><?php
			}
			else {
				$post_categories = $_POST['post_category'];
				
				if($post_categories){		
					foreach ($post_categories as $post_category) {
						$wwpc_admin_cats = $wwpc_admin_cats . ',' . $post_category;
					}
					
					$wwpc_admin_cats = ltrim($wwpc_admin_cats, ',');
				}
				
				update_option('wwpc_cats', $post_categories);
				update_option('wwpc_level', $_POST['wwpc_settings_level']);
				
				?><div class="updated"><p><strong><?php _e('Private Categories Settings Updated.', 'Private Categories') ?></strong></p></div><?php
			}
		}
		
		$wwpc_settings_level = get_settings('wwpc_level');
		
		?>
		<div class=wrap>
		<form method="post">
	    <h2>Private Categories Options Page</h2>
	    <fieldset name="cat_options">
			<legend><?php _e('General Options', 'Private Categories') ?></legend>
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
                <tr valign="middle">
                    <th width="33%" scope="row"><?php _e('Choose the private categories:'); ?></th>
                    <td><?php wwpc_categories(get_nested_categories()); ?></td>
                </tr>
			</table>
		</fieldset>
		<fieldset name="level_options">
			<legend><?php _e('Level Options', 'Private Categories') ?></legend>
			<table width="100%" cellspacing="2" cellpadding="5" class="editform">
                <tr valign="middle">
                    <th width="33%" scope="row"><?php _e('Minimum Level to view private categories (1 - 10):'); ?></th>
                    <td><input id="wwpc_settings_level" name="wwpc_settings_level" size="6" length="2" class="code" type="text" value="<?= $wwpc_settings_level ?>"></td>
                </tr>
			</table>
		</fieldset>
		<div class="submit"><input type="submit" name="wwpc_update" value="<?php _e('Update Options', 'Private Categories') ?> &raquo;" /></div>
		</form>
		</div>
		
		<div class=wrap>
		<h2>Usage Information</h2>
		Usage:
		<ol>
			<li>Be sure to read private_categories.php for installation instructions.</li>
			<li>When composing a post, be sure to choose ALL the categories you marked private on the options page and your posts will be hidden.</li>
		</ol>
<?
}

add_action('admin_menu', 'wwpc_add_privacy_pages');

?>