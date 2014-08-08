<?php
// Direct Edit core functionality

// Global variables
$directScriptImage = '';
$directScriptLink = '';
$directScriptFile = '';

// Images setup
add_theme_support( 'post-thumbnails' );
add_image_size( 'preview', 9999, 9999 );
add_image_size( 'public', 9999, 9999 );

// Basic actions
add_action( 'wp_ajax_direct-add', 'de_list_add' );
add_action( 'wp_ajax_direct-delete', 'de_list_delete' );
add_action( 'wp_ajax_direct-delete-post', 'de_delete_post' );
add_action( 'wp_ajax_direct-edit-image', 'de_edit_image' );
add_action( 'wp_ajax_direct-hide-post', 'de_hide_post' );
add_action( 'wp_ajax_direct-move-left', 'de_list_move_left' );
add_action( 'wp_ajax_direct-move-right', 'de_list_move_right' );
add_action( 'wp_ajax_direct-save-page', 'de_save_page' );
add_action( 'wp_ajax_direct-show-post', 'de_show_post' );
add_action( 'wp_ajax_direct-upload-image', 'de_upload_image' );
add_action( 'wp_ajax_direct-upload-file', 'de_upload_file' );
add_action( 'wp_ajax_direct-save-menu', 'de_save_menu' );
// TODO: Sometimes we have to allow guests to upload images. Probably we need some smart condition here 
//add_action( 'wp_ajax_nopriv_direct-upload-image', 'de_upload_image' );
//add_action( 'wp_ajax_nopriv_direct-edit-image', 'de_edit_image' );

function de_list_add() {
	global $de_snippet_list;
	
	$response = array();
	
	if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
		$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );

		if ( $item instanceof De_Item_List && De_Store::is_editable( $item ) ) {
			$index = ( int ) $_POST[ 'index' ];

			$id = De_Store::add_list_item();
			if ( $id ) {
				$content = $de_snippet_list->snippet( $item, $item->get_setting( 'snippet' ), array( $id ) );
				
				if ( is_array( $item->list ) && count( $item->list ) && $index >= 0 && $index < count( $item->list ) ) {
					$list = array();
					
					foreach( $item->list as $key => $value ) {
						$list[] = $value;
						
						if ( $key == $index ) {
							$list[] = $id;
							$response[ 'activeItem' ] = $key + 1;
						}
					}
					
					$item->list = $list;
				} else {
					$item->list[] = $id;
					
					$response[ 'activeItem' ] = 0;
				}
			}
			
			$item->list = array_values( $item->list );
			$item->update();
			
			$response[ 'definition' ] = $item->list;
			$response[ 'newItemIdentifier' ] = $id;
			$response[ 'newItemContent' ] = $content;
		}
	}

	echo json_encode( $response );
	
	die();
}

function de_list_delete() {
	$response = array();
	
	if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
		$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );

		if ( $item instanceof De_Item_List && De_Store::is_editable( $item ) ) {
			$index = ( int ) $_POST[ 'index' ];

			if ( is_array( $item->list ) && count( $item->list ) && $index >= 0 && $index < count( $item->list ) ) {
				unset( $item->list[ $index ] );
				$item->list = array_values( $item->list );
				$item->update();
				
				$response[ 'activeItem' ] = min( $index, count( $item->list ) - 1 );
				$response[ 'definition' ] = $item->list;
			}
		}
	}

	echo json_encode( $response );
	
	die();
}

function de_delete_post() {
	$response = array();
	
	if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
		$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );

		if ( $item instanceof De_Item && De_Store::is_editable( $item ) && $item->get_setting( 'postId' ) && de_is_deleteable( $item->get_setting( 'postId' ) ) ) {
			// Delete posts in all languages if needed
			if ( De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_language_posts( $item->get_setting( 'postId' ) ) ) {
				foreach( De_Language_Wrapper::get_language_posts( $item->get_setting( 'postId' ) ) as $lang_post ) {
					wp_delete_post( $lang_post->ID, true );
				}
			} else {
				wp_delete_post( $item->get_setting( 'postId' ), true );
			}
			
			$response = array( 'action' => 'delete' );
		}
	}

	echo json_encode( $response );
	
	die();
}

function de_edit_image() {
	set_time_limit ( 180 );
	
	echo json_encode( De_Store::edit_image() );
	
	die();
}

function de_hide_post() {
	$response = array();
	
	if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
		$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );

		if ( $item instanceof De_Item && De_Store::is_editable( $item ) && $item->get_setting( 'postId' ) && de_is_hideable( $item->get_setting( 'postId' ) ) ) {
			$p = array();
			$p[ 'ID' ] = $item->get_setting( 'postId' );
			$p[ 'post_status' ] = 'draft';
			wp_update_post( $p );
			
			$response = array( 'action' => 'addclass', 'cssClass' => 'direct-hidden' );
		}
	}

	echo json_encode( $response );
	
	die();
}

function de_list_move_left() {
	$response = array();
	
	if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
		$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );

		if ( $item instanceof De_Item_List && De_Store::is_editable( $item ) ) {
			$index = ( int ) $_POST[ 'index' ];
			
			// Move item left
			if ( is_array( $item->list ) && count( $item->list ) > 1 && $index >= 0 && $index < count( $item->list ) ) {
				if ( $index > 0 ) {
					$indexNew = $index - 1;
					$temp = $item->list[ $index ];
					$item->list[ $index ] =$item->list[ $indexNew ];
					$item->list[ $indexNew ] = $temp;
				} else {
					$indexNew = count( $item->list ) - 1;
					$temp = array_shift( $item->list );
					array_push( $item->list, $temp );
				}
				
				$item->list = array_values( $item->list );
				$item->update();
				
				$response[ 'activeItem' ] = $indexNew;
				$response[ 'definition' ] = $item->list;
			}
		}
	}

	echo json_encode( $response );
	
	die();
}

function de_list_move_right() {
	$response = array();
	
	if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
		$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );

		if ( $item instanceof De_Item_List && De_Store::is_editable( $item ) ) {
			$index = ( int ) $_POST[ 'index' ];
	
			// Move item right
			if ( is_array( $item->list ) && count( $item->list ) > 1 && $index >= 0 && $index < count( $item->list ) ) {
				if ( $index < count( $item->list ) - 1 ) {
					$indexNew = $index + 1;
					$temp = $item->list[ $index ];
					$item->list[ $index ] = $item->list[ $indexNew ];
					$item->list[ $indexNew ] = $temp;
				} else {
					$indexNew = 0;
					$temp = array_pop( $item->list );
					array_unshift( $item->list, $temp );
				}

				$item->list = array_values( $item->list );
				$item->update();
				
				$response[ 'activeItem' ] = $indexNew;
				$response[ 'definition' ] = $item->list;
			}
		}
	}

	echo json_encode( $response );
	
	die();
}

function de_save_page() {
	global $user_ID;
	
	$response = array();
	
	foreach( $_POST as $key => $field ) {
		if ( $key == 'action' || $key == 'direct-page-options' || ! is_array( $field ) || empty( $field[ 'data' ][ 'reference' ] ) )
			continue;
		
		$item = De_Items::get( $field[ 'data' ][ 'reference' ] );
		if ( $item instanceof De_Item && De_Store::is_editable( $item ) ) {
			$content = De_Store::write( $item, $field );
			
			$response[ $key ] = $item->output_partial( $content );
		}
	}
	
	if ( De_Store::$redirect ) {
		$response[ 'redirect' ] = De_Store::$redirect;
	}
	
	// Edit page options
	if ( isset( $_POST[ 'direct-page-options' ] ) && is_array( $_POST[ 'direct-page-options' ] ) ) {
		if ( ! empty( $_POST[ 'direct-page-options' ][ 'postId' ] ) ) {
			$post_id = $_POST[ 'direct-page-options' ][ 'postId' ];
		} elseif ( ! empty( De_Store::$new_post_id ) ) {
			$post_id = De_Store::$new_post_id;
		} else {
			if ( ! empty( $_POST[ 'direct-page-options' ][ 'postType' ] ) && in_array( $_POST[ 'direct-page-options' ][ 'postType' ], get_post_types( array('show_ui' => true ) ) ) ) {
				$new_post_title = stripslashes( $_POST[ 'direct-page-options' ][ 'de_title' ] );
				
				$new_post = array(
					'post_content' => '',
					'post_title' => $new_post_title,
					'post_status' => 'draft',
					'post_date' => date('Y-m-d H:i:s'),
					'post_author' => $user_ID,
					'post_type' => $_POST[ 'direct-page-options' ][ 'postType' ],
					'post_category' => array( 0 )
				);
				$post_id = wp_insert_post( $new_post, $wp_error );

				$new_post = array(
					'ID' => $post_id,
					'post_name' => sanitize_title( $new_post_title )
				);
				wp_update_post( $new_post );

				update_post_meta( $post_id, 'de_new_page', 1 );

				if ( De_Language_Wrapper::has_multilanguage() ) {
					De_Language_Wrapper::set_post_language( $post_id, De_Language_Wrapper::get_default_language() );
					De_Url::register_url( $post_id, sanitize_title( $new_post_title ) );
					De_Language_Wrapper::create_language_posts( $post_id );
				} else {
					De_Url::register_url( $post_id, sanitize_title( $new_post_title ) );
				}
			}
		}

		if ( ! empty( $post_id ) && get_post( $post_id ) ) {
			// Include custom functions.php if needed
			$template = $_POST[ 'direct-page-options' ][ 'templateName' ];
			if ( is_dir( dirname( get_stylesheet_directory() . '/' . $template ) ) && file_exists( dirname( get_stylesheet_directory() . '/' . $template ) . '/functions.php' ) ) {
				require_once dirname( get_stylesheet_directory() . '/' . $template ) . '/functions.php';
			}
			
			if ( direct_bloginfo( 'title', false, $post_id ) != stripslashes( $_POST[ 'direct-page-options' ][ 'de_title' ] ) ) {
				update_post_meta( $post_id, 'de_title', stripslashes( $_POST[ 'direct-page-options' ][ 'de_title' ] ) );
			}
			if ( direct_bloginfo( 'description', false, $post_id ) != stripslashes( $_POST[ 'direct-page-options' ][ 'de_description' ] ) ) {
				update_post_meta( $post_id, 'de_description', stripslashes( $_POST[ 'direct-page-options' ][ 'de_description' ] ) );
			}
			if ( get_option( 'de_smart_urls' ) && get_option( 'permalink_structure' ) == '/%postname%/' ) {
				if ( sanitize_title( stripslashes( $_POST[ 'direct-page-options' ][ 'de_slug' ] ) ) && direct_bloginfo( 'slug', false, $post_id ) != De_Url::unique_slug( $post_id, sanitize_title( stripslashes( $_POST[ 'direct-page-options' ][ 'de_slug' ] ) ) ) ) {
					De_Url::register_url( $post_id, sanitize_title( stripslashes( $_POST[ 'direct-page-options' ][ 'de_slug' ] ) ) );
				}
			}
			// It is needed for menu translation only
			if( De_Language_Wrapper::has_multilanguage() ) {
				if ( direct_bloginfo( 'navigation_label', false, $post_id ) != stripslashes( $_POST[ 'direct-page-options' ][ 'de_navigation_label' ] ) ) {
					update_post_meta( $post_id, 'de_navigation_label', stripslashes( $_POST[ 'direct-page-options' ][ 'de_navigation_label' ] ) );
				}
			}
			// Save category list and post category
			if ( get_post_type( $post_id ) == 'post' ) {
				// Update category list
				if ( current_user_can( 'manage_categories' ) ) {
					$categories = json_decode( stripslashes( $_POST[ 'direct-page-options' ][ 'de_category_input' ] ) );
					$category_ids = array();
					foreach( $categories as $category ) {
						if ( strpos( $category->id, 'new-' ) === 0 ) {
							$c = wp_insert_term( $category->name, 'category' );
							$i = 0;
							while ( $c instanceof WP_Error ) {
								$c = wp_insert_term( $category->name . "-$i", 'category' );
								$i ++;
							}
							$category_ids[ $category->id ] = $c[ 'term_id' ];
						} else {
							$c = wp_update_term( $category->id, 'category', array( 'name' => $category->name ) );
							$i = 0;
							while ( $c instanceof WP_Error ) {
								$c = wp_update_term( $category->id, 'category', array( 'name' => $category->name . "-$i" ) );
								$i ++;
							}
							$category_ids[ $category->id ] = $category->id;
						}
					}
					$categories = get_categories( array( 'orderby' => 'name', 'hide_empty' => 0 ) );
					foreach( $categories as $category ) {
						if ( ! in_array( $category->term_id, $category_ids ) ) {
							wp_delete_term( $category->term_id, 'category' );
						}
					}
					$_POST[ 'direct-page-options' ][ 'de_category' ] = $category_ids[ $_POST[ 'direct-page-options' ][ 'de_category' ] ];
				}

				// Set post category
				$categories = array( $_POST[ 'direct-page-options' ][ 'de_category' ] );
				wp_set_post_categories( $post_id, $categories, false );
			}
			
			do_action( 'de_save_page_options', $_POST[ 'direct-page-options' ] );
			
			$response[ 'direct-page-options' ] = true;
			$response[ 'redirect' ] = De_Url::get_url( $post_id );
		}
	}
	
	$response = apply_filters( 'de_save_page_pre_return_response', $response );
	
	echo json_encode( $response );
	
	die();
}

function de_show_post() {
	$response = array();
	
	if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
		$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );

		if ( $item instanceof De_Item && De_Store::is_editable( $item ) && $item->get_setting( 'postId' ) && de_is_hideable( $item->get_setting( 'postId' ) ) ) {
			$p = array();
			$p[ 'ID' ] = $item->get_setting( 'postId' );
			$p[ 'post_status' ] = 'publish';
			wp_update_post( $p );
			
			$response = array( 'action' => 'removeclass', 'cssClass' => 'direct-hidden' );
		}
	}

	echo json_encode( $response );
	
	die();
}

function de_upload_image() {
	echo json_encode( De_Store::upload_image() );
	
	die();
}

function de_upload_file() {
	echo json_encode( De_Store::upload_file() );
	
	die();
}

function de_save_menu() {
	echo json_encode( De_Store::write_menus() );
	
	die();
}
