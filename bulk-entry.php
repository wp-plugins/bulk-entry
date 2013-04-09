<?php
/*
Plugin Name: Bulk Entry
Plugin URI: http://interconnectit.com
Description: A tool for the bulk entry of posts, pages, etc
Version: 1.1
Author: Tom J Nowell
Author Email: contact@tomjn.com
License:

  Copyright 2011 Tom J Nowell (contact@tomjn.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class BulkEntry {

	/*--------------------------------------------*
	 * Constants
	 *--------------------------------------------*/
	const NAME = 'Bulk Entry';
	const SLUG = 'bulk_entry';

	public $last_editor_id = 0;


	public $mcesettings = array();

	/**
	 * Constructor
	 */
	function __construct() {
		//Hook up to the init action
		add_action( 'init', array( &$this, 'init_bulk_entry' ) );
		add_action( 'wp_ajax_bulk_entry_new_card', array( &$this, 'wp_ajax_bulk_entry_new_card' ) );
		add_action( 'wp_ajax_bulk_entry_submit_post', array( &$this, 'wp_ajax_bulk_entry_submit_post' ) );
		add_action( 'after_wp_tiny_mce', array( $this, 'steal_away_mcesettings' ) );
	}

	/**
	 * Runs when the plugin is activated
	 */
	function install_bulk_entry() {
		// do not generate any output here
	}

	/**
	 * Runs when the plugin is initialized
	 */
	function init_bulk_entry() {
		// Setup localization
		load_plugin_textdomain( self::SLUG, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		// Load JavaScript and stylesheets
		$this->register_scripts_and_styles();


		if ( is_admin() ) {
			//this will run when in the WordPress admin
		} else {
			//this will run when on the frontend
		}

		/*
		 * TODO: Define custom functionality for your plugin here
		 *
		 * For more information:
		 * http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		add_action( 'admin_menu', array( $this, 'action_callback_admin_menu' ) );

	}

	function action_callback_admin_menu() {
		// TODO define your action method here
		add_management_page( 'Bulk Entry', 'Bulk Entry', 'edit_posts', self::SLUG, array( $this, 'admin_menu_page' ) );
	}

	function steal_away_mcesettings( $mcesettings ) {
		$this->mcesettings = $mcesettings;
	}

	function get_editor_id() {
		$this->last_editor_id++;
		return 'bulk-entry-editor'.time().$this->last_editor_id;
	}

	function wp_ajax_bulk_entry_submit_post() {
		$editor_id = $_POST['bulk_entry_editor_id'];

		$valid = check_ajax_referer( $editor_id, 'bulk_entry_post_nonce', false );
		if ( !$valid ) {
			$message = "Invalid nonce sent, if you've had this page open a while, try refreshing.";
			$message_card = $this->message_card( 'Security Problem', $message );
			echo '{ "content" : '.json_encode( $message_card ) . ' }';
			die();
		}

		$type = $_POST['bulk_entry_posttype'];
		$status = $_POST['bulk_entry_poststatus'];
		$content = $_POST['bulk_entry_postcontent'];
		$title = $_POST['bulk_entry_posttitle'];

		$posttype = get_post_type_object( $type );


		$reply = '';

		if ( $posttype == null ) {
			$reply .= $this->message_card( '&nbsp;', 'No such post type exists' );
		} else {
			if ( ! current_user_can( $posttype->cap->publish_posts ) ) {
				$reply .= 'You don\'t have permission to do that';
			} else {
				// Create post object
				$my_post = array(
					'post_title'    => $title,
					'post_content'  => $content,
					'post_status'   => $status,
					'post_type'		=> $type
				);

				// Insert the post into the database
				$post_id = wp_insert_post( $my_post );
				$permalink = get_permalink( $post_id );
				$editlink = get_edit_post_link( $post_id );
				$message = $title.'" created, <a href="'.$editlink.'">open in full editor</a> or <a href="'.$permalink.'">click here to view </a>';
				$reply .= $this->message_card( '&nbsp;', $message );
			}
		}
		echo '{ "content" : '.json_encode( $reply ).' }';
		die();
	}

	function wp_ajax_bulk_entry_new_card() {

		$valid = check_ajax_referer( 'bulkentry-toolbar', 'bulkentry_toolbar_nonce', false );
		if ( !$valid ) {
			echo '{ "content" : '.json_encode( $this->message_card( 'Security Problem', "Invalid nonce sent, if you've had this page open a while, try refreshing." ) ) . ' }';
			die();
		}

		ob_start();
		{
			for ( $i = 0; $i < absint( $_POST['bulk_entry_postcount'] ); $i++ ) {
				echo $this->card();
			}
			ob_start();
			{
				_WP_Editors::editor_js();
			}
			ob_end_clean();
			$content = ob_get_contents();
		}
		ob_end_clean();

		$ids = array();
		foreach ( $this->mcesettings as $editor_id => $init ) {
			$ids[] = $editor_id;
		}
		$data = '{ "content": '.json_encode( $content ).', "editor_ids" : '.json_encode( $ids ).' }';
		echo $data;
		die();

	}


	function admin_menu_page() {
		echo '<div class="wrap bulk-entry--wrap">';
		echo '<h2>Bulk Entry</h2>';
		echo '<div style="display:none;">';
		wp_editor( 'preload', $this->get_editor_id(), array( 'teeny' => true ) );
		echo '</div>';
		echo $this->toolbar();
		$canvas = '<div id="bulk-entry-canvas" class="bulk-entry-canvas">';
		$canvas .= '</div>';
		echo $canvas;
		echo '</div>';
	}

	function start_block( $custom_classes = array() ) {
		$custom_classes[] = 'bulk-entry-block';
		$custom_classes = apply_filters( 'bulk_entry_start_block_classes', $custom_classes );
		$classes = implode( ' ', $custom_classes );
		$block = '<div class="'.$classes.'">';
		$block = apply_filters( 'bulk_entry_start_block_html', $block );
		return $block;
	}

	function start_left_block(){
		$block = '<div class="bulk-entry-block--left"><div class="bulk-entry-block--label">';
		$block = apply_filters( 'bulk_entry_start_left_block_html', $block );
		return $block;
	}
	function start_right_block(){
		$block = '<div class="bulk-entry-block--right">';
		$block = apply_filters( 'bulk_entry_start_right_block_html', $block );
		return $block;
	}

	function end_block() {
		$block = '</div>';
		$block = apply_filters( 'bulk_entry_end_block_html', $block );
		return $block;
	}

	function end_left_block() {
		$block = '</div></div>';
		$block = apply_filters( 'bulk_entry_end_left_block_html', $block );
		return $block;
	}
	function end_right_block() {
		$block = '</div>';
		$block = apply_filters( 'bulk_entry_end_right_block_html', $block );
		return $block;
	}

	function message_card( $label, $message ) {
		$classes = array( 'bulk-entry-message' );
		$classes = apply_filters( 'bulk_entry_message_card_classes', $classes );
		$card = $this->start_block( $classes );
		$card .= '<form method="post" action="">';
		$card .= $this->start_left_block();
		$label = apply_filters( 'bulk_entry_message_label', $label );
		$card .= $label;
		$card .= $this->end_left_block();
		$card .= $this->start_right_block();
		$card .= '<div class="bulk-entry-block--content bulk-entry-card--content">';
		$card .= '<p><a href="#" class="bulk-entry-card-delete" >x</a> ';
		$message = apply_filters( 'bulk_entry_message_message', $message );
		$card .= $message;
		$card .= '</p>';
		$card .= '</div>';
		$card .= $this->end_right_block();
		$card .= $this->end_block();
		return $card;
	}

	function toolbar() {
		$toolbar = $this->start_block();
		$toolbar .= '<form method="post" action="">';
		$toolbar .= $this->start_left_block();
		$label = "I'd like a ";
		$label = apply_filters( 'bulk_entry_toolbar_label', $label );
		$toolbar .= $label;
		$toolbar .= $this->end_left_block();
		$toolbar .= $this->start_right_block();
		$toolbar .= '<div id="bulk-entry-toolbar" class="bulk-entry-toolbar">';
		$toolbar .= '<table class="widefat mceToolbar mceToolbarRow1 Enabled"><tr><td>';

		$fields = array();

		$field = '<div class="bulk-entry-toolbar-field">';
		$field .= '<input type="hidden" id="bulk-entry-add-post-count" name="bulk-entry-add-post-count" class="bulk-entry-toolbar-field--number" value="1"/>';
		$field .= '</div>';
		$fields[] = $field;

		$field = '<div class="bulk-entry-toolbar-field">';

		$stati = get_post_stati( array( 'show_in_admin_status_list' => true ), 'objects' );
		$field .= '<select id="bulk-entry-add-post-status" name="bulk-entry-add-post-status" class="">';
		foreach ( $stati as $status ) {
			// don't show the scheduled status yet
			if ( $status->name == 'future' ){
				continue;
			}
			$field .= '<option value="'.$status->name.'">'.$status->label.'</option>';
		}
		$field .= '</select>';
		$field .= '</div>';
		$fields[] = $field;

		$args = array(
			'show_ui' => true
		);
		$post_types = get_post_types( $args, 'objects' );

		$field = '<div class="bulk-entry-toolbar-field">';
		$field .= '<select id="bulk-entry-add-post-type" name="bulk-entry-add-post-type" class="">';
		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type->name, 'editor' ) && post_type_supports( $post_type->name, 'title' ) ) {
				$field .= '<option value="'.$post_type->name.'">'.$post_type->labels->singular_name.'</option>';
			}
		}
		$field .= '</select>';
		$field .= '</div>';
		$fields[] = $field;

		$fields = apply_filters( 'bulk_entry_toolbar_fields', $fields );
		$fields = implode( ' ', $fields );
		$toolbar .= $fields;

		$button = '<div class="bulk-entry-toolbar-field">';
		$button .= '<input id="bulk-entry-toolbar-add-posts" type="button" name="bulk-entry-add-cards-button" class="button button-primary" value="Go"/>';
		$button .= '</div>';
		$buttons[] = $button;

		$buttons = apply_filters( 'bulk_entry_toolbar_buttons', $buttons );
		$buttons = implode( ' ', $buttons );
		$toolbar .= $buttons;

		$toolbar .= '</td></tr></table>';
		$toolbar .= '</div>';
		$toolbar .= $this->end_right_block();
		$ajax_nonce = wp_create_nonce( 'bulkentry-toolbar' );
		$toolbar .= '<input id="bulk-entry-toolbar-nonce" type="hidden" name="bulk_entry_editor_nonce" value="'.$ajax_nonce.'" />';
		$toolbar .= '</form>';
		$toolbar .= $this->end_block();
		return $toolbar;
	}

	function card() {

		$card = $this->start_block();
		$card .= '<form method="post" action="">';
		$card .= $this->start_left_block();
		$poststatus = $_POST['bulk_entry_poststatus'];
		$posttype = $_POST['bulk_entry_posttype'];

		$type = get_post_type_object( $posttype );
		$status = get_post_stati( array( 'name' => $poststatus ), 'objects' );
		$status = $status[$poststatus];

		$label = $status->label.' ';
		$label .= $type->labels->singular_name;
		$label = apply_filters( 'bulk_entry_content_card_label', $label );

		$card .= $label;
		$card .= $this->end_left_block();

		$card .= $this->start_right_block();
		$card .= '<div class="bulk-entry-block--content bulk-entry-card--content">';

		$fields = array();

		$fields[] = '<div class="bulk-entry-card-field"><input type="text" name="bulk-entry-card--title" class="widefat bulk-entry-card--title" value="Title"/></div>';

		$editor_id = $this->get_editor_id();
		ob_start();
		wp_editor( 'content', $editor_id, array( 'textarea_rows' => 10, 'media_buttons' => false, 'teeny' => true ) );
		$editor = ob_get_contents();
		ob_end_clean();
		$fields[] = '<div class="bulk-entry-card-field">'.$editor.'</div>';


		$fields = apply_filters( 'bulk_entry_content_card_fields', $fields );
		$fields = implode( '', $fields );
		$card .= $fields;

		$card .= '<div class="bulk-entry-card--buttons">';
		$card .= '<a href="#" class="bulk-entry-card-control bulk-entry-card-delete" >Discard</a> <input type="submit" class="bulk-entry-card-control button button-primary" value="Save"/>';
		$card .= '</div>';
		$card .= '</div>';
		$card .= $this->end_right_block();

		$hidden_fields = array();

		$ajax_nonce = wp_create_nonce( $editor_id );
		$hidden_fields[] = '<input type="hidden" name="bulk_entry_editor_nonce" value="'.$ajax_nonce.'" />';
		$hidden_fields[] = '<input type="hidden" name="bulk_entry_editor_id" value="'.$editor_id.'" />';
		$hidden_fields[] = '<input type="hidden" name="bulk_entry_poststatus" value="'.$poststatus.'" />';
		$hidden_fields[] = '<input type="hidden" name="bulk_entry_posttype" value="'.$posttype.'" />';

		$hidden_fields = apply_filters( 'bulk_entry_content_card_hidden_fields', $hidden_fields );
		$hidden_fields = implode( '', $hidden_fields );

		$card .= $hidden_fields;
		//$card .= '<input type="hidden" name="bulk_entry_" value="'.$_POST[''].'" />';
		$card .= '</form>';
		$card .= $this->end_block();
		return $card;
	}

	/**
	 * Registers and enqueues stylesheets for the administration panel and the
	 * public facing site.
	 */
	private function register_scripts_and_styles() {
		if ( is_admin() ) {
			$this->load_file( self::SLUG . '-admin-script', '/js/admin.js', true );
			$this->load_file( self::SLUG . '-admin-style', '/css/admin.css' );
		}
	} // end register_scripts_and_styles

	/**
	 * Helper function for registering and enqueueing scripts and styles.
	 *
	 * @name	The 	ID to register with WordPress
	 * @file_path		The path to the actual file
	 * @is_script		Optional argument for if the incoming file_path is a JavaScript source file.
	 */
	private function load_file( $name, $file_path, $is_script = false ) {

		$url = plugins_url( $file_path, __FILE__ );
		$file = plugin_dir_path( __FILE__ ) . $file_path;

		if ( file_exists( $file ) ) {
			if ( $is_script ) {
				wp_register_script( $name, $url, array( 'jquery' ) ); //depends on jquery
				wp_enqueue_script( $name );
			} else {
				wp_register_style( $name, $url );
				wp_enqueue_style( $name );
			} // end if
		} // end if

	} // end load_file
} // end class
new BulkEntry();
