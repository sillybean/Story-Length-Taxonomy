<?php
/*
Plugin Name: Story Length Taxonomy
Plugin URI: http://stephanieleary.com/code/wordpress/story-length-taxonomy/
Description: A plugin for fiction magazines. Creates a set of categories for story length (novella, novelette, etc.) according to SFWA's scale, and can automatically categorize posts appropriately based on word count.
Version: 1.0.1
Author: Stephanie Leary
Author URI: http://stephanieleary.com/

Copyright 2013  Stephanie Leary  (email : steph@sillybean.net)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action('init', 'create_fiction_tax');
register_activation_hook( __FILE__, 'activate_fiction_tax' );
function activate_fiction_tax() {
	create_fiction_tax();
	activate_fiction_terms();
	flush_rewrite_rules();
}

function create_fiction_tax() {
	register_taxonomy(
		'length',
		'post',
		array(
			'label' => __('Story Length', 'story-length-taxonomy'),
			'hierarchical' => true,
			'show_tagcloud' => false,
			'show_admin_column' => true,
			'update_count_callback' => '_update_post_term_count',
		)
	);
}

function activate_fiction_terms() {
	wp_insert_term(__('Short Story','story-length-taxonomy'), 'length', array('description' => '0-7500') );
	wp_insert_term(__('Novelette','story-length-taxonomy'), 'length', array('description' => '7501-17500') );
	wp_insert_term(__('Novella','story-length-taxonomy'), 'length', array('description' => '17501-40000') );
	wp_insert_term(__('Novel','story-length-taxonomy'), 'length', array('description' => '40001-*') );
}

add_action('save_post', 'set_fiction_length');
function set_fiction_length($postid) {	
	if ( $parent_id = wp_is_post_revision( $postid ) ) 
		$postid = $parent_id;
		
	// see if the post author set the length manually
	$postterms = wp_get_post_terms($postid, 'length');
	if (!empty($postterms))
		return;
	
	remove_action('save_post', 'set_fiction_length');
	
	$length = array();
	$thepost = get_post( $postid );
	$words = str_word_count( strip_tags( strip_shortcodes( $thepost->post_content ) ) );	
	$terms = get_terms( 'length', array( 'hide_empty' => false ) );
	
	foreach ( $terms as $term ) {
		$range = term_description( $term->term_id, 'length' );
		
		// Explode, and remove the <p> tags WP helpfully added.
		$split = explode( '-', strip_tags($range) );
		// handle any commas and spaces that might have been entered in the ranges. 
		$start = str_replace(',', '', trim($split[0]) );
		// Use last array element in case multiple hyphens were entered.
		$end = str_replace(',', '', trim($split[count($split) - 1]) );
		
		// handle things with no upper limit (presumably novels, but who knows)
		if ( $end == '*' && $words >= $start )
			$length[] = $term->term_id;
		elseif ( $words >= $start && $words <= $end ) {
			$length[] = $term->term_id;
		}
	}
	
	if (!empty($length))
		wp_set_post_terms( $postid, $length, 'length', false);
	
	add_action('save_post', 'set_fiction_length');
}

/* Change the labels on the edit tag screen */
function story_length_filter_gettext($translation, $text, $domain) {
	if ( is_admin() && isset($_GET['taxonomy']) && $_GET['taxonomy'] == 'length' ) {			
		$translations = &get_translations_for_domain( $domain );
		// This is for the Description column header in the tag list, which doesn't have context
		if ( $text == 'Description' ) {
			return $translations->translate( 'Word Count Range' );
		}
		if ( $text == 'The description is not prominent by default; however, some themes may show it.' ) {
			return $translations->translate( 'Enter the word count range for this category, e.g. 0-1000. For no upper limit, use a star: 40000-*.' );
		}
	}
	return $translation;
}
function story_length_filter_gettext_with_context($translation, $text, $context, $domain) {
	if ( is_admin() && isset($_GET['taxonomy']) && $_GET['taxonomy'] == 'length' ) {			
		$translations = &get_translations_for_domain( $domain );
		// This is for the Description label on the textarea, which has context
		if ( $text == 'Description' && $context == 'Taxonomy Description' ) {
			return $translations->translate( 'Word Count Range' );
		}
	}
	return $translation;
}
add_filter('gettext', 'story_length_filter_gettext', 10, 4);
add_filter('gettext_with_context', 'story_length_filter_gettext_with_context', 10, 4);
