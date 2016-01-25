<?php
class De_Store {
	public static $new_post_id;
	public static $redirect;
	
	public static function is_editable( De_Item $item ) {
		global $direct_queried_object;
		
		if ( $item->get_setting( 'disableEdit' ) && $item->get_setting( 'disableEdit' ) !== 'false' && $item->get_setting( 'disableEdit' ) !== 'no' ) {
			return false;
		}
		
		/* Menu editor is hidden. Probably it will be removed at all in future versions. */
		/*
		// Menu has its own check
		if ( $item instanceof De_Item_Menu ) {
			if( get_option( 'de_menu_editor_enabled' ) && de_get_current_template() == 'edit-menu.php' && $item->get_setting( 'menu' ) ) {
				return ( current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) );
			} else {
				return false;
			}
		}
		
		// Nothing is editable for Direct Menu Editor except for wpcontent on Edit Menu page only
		if ( get_option( 'de_menu_editor_enabled' ) && de_get_current_template() == 'edit-menu.php' ) {
			switch( $item->store ) {
				case 'wpcontent':
					return ( current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) );
				break;
				default:
					return false;
				break;
			}
		}
		*/

		switch( $item->store ) {
			case 'postmeta':
			case 'wptitle':
			case 'wpcontent':
			case 'wpexcerpt':
			case 'post':
			case 'postdate':
				return ( current_user_can( 'edit_post', $item->get_setting( 'postId' ) ) || current_user_can( 'edit_de_frontend' ) );
			break;
			case 'usermeta':
				return ( current_user_can( 'edit_user', $item->get_setting( 'postId' ) ) || current_user_can( 'edit_de_frontend' ) );
			break;
			case 'option':
				return ( current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) );
			break;
			default:
				$classname = 'De_Store_' . ucfirst( $item->store );
				if ( class_exists( $classname ) ) {
					return $classname::is_editable( $item );
				} else {
					return false;
				}
			break;
		}
	}
	
	public static function read( De_Item $item ) {
		global $wp_filter;
		
		switch( $item->store ) {
			case 'postmeta':
				if ( $item->get_setting( 'key' ) ) {
					if ( $item->get_setting( 'postId' ) ) {
						$content = get_post_meta( $item->get_setting( 'postId' ), $item->get_setting( 'key' ), true );
						
						if ( empty( $content ) && De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $item->get_setting( 'postId' ) ) && De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() ) ) {
							$content = get_post_meta( De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() )->ID, $item->get_setting( 'key' ), true );
						}
					} else {
						return '';
					}
				} else {
					return null;
				}
			break;
			case 'wptitle':
				if ( $item->get_setting( 'postId' ) ) {
					$wrap_the_title = false;
					
					foreach( $wp_filter as $filter ) {
						if ( isset( $filter[ 'de_wrap_the_title' ] ) ) {
							$wrap_the_title = true;
							break;
						}
					}
					
					if ( $wrap_the_title ) {
						de_wrap_the_title_remove();
					}
					$content = get_the_title( $item->get_setting( 'postId' ) );
					if ( $wrap_the_title ) {
						de_wrap_the_title_restore();
					}
					
					if ( empty( $content ) && De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $item->get_setting( 'postId' ) ) && De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() ) ) {
						$content = get_the_title( De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() )->ID );
					}
				} else {
					return '';
				}
			break;
			case 'wpcontent':
				if ( $item->get_setting( 'postId' ) ) {
					$p = get_post( $item->get_setting( 'postId' ) );
					if ( $p ) {
						$content = $p->post_content;
					}
					
					if ( empty( $content ) && De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $item->get_setting( 'postId' ) ) && De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() ) ) {
						$p = De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() );
						if ( $p ) {
							$content = $p->post_content;
						}
					}
					
					apply_filters( 'the_content', $content );
				} else {
					return '';
				}
			break;
			case 'wpexcerpt':
				if ( $item->get_setting( 'postId' ) ) {
					$p = get_post( $item->get_setting( 'postId' ) );
					$content = $p->post_excerpt;
					$text = $p->post_content;
					
					if ( '' == $content ) {
						$text = str_replace("</p>", "</p>\n", $text);
						$excerpt_length = apply_filters( 'excerpt_length', 55 );
						$text = wp_trim_words( $text, $excerpt_length, '...' );
						$content = wpautop( $text );
					}
					
					if ( empty( $content ) && De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $item->get_setting( 'postId' ) ) && De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() ) ) {
						$p = De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() );
						$content = $p->post_excerpt;
						$text = $p->post_content;
						
						if ( '' == $content ) {
							$text = str_replace("</p>", "</p>\n", $text);
							$text = wp_trim_words( $text, 55, '...' );
							$content = wpautop( $text );
						}
					}
					
					apply_filters( 'the_excerpt', $content );
				} else {
					return '';
				}
			break;
			case 'usermeta':
				if ( $item->get_setting( 'key' ) ) {
					if ( $item->get_setting( 'userId' ) ) {
						$content = get_user_meta( $item->get_setting( 'userId' ), $item->get_setting( 'key' ), true );
					} else {
						return '';
					}
				} else {
					return null;
				}
			break;
			case 'option':
				if ( $item->get_setting( 'key' ) ) {
					$content = get_option( $item->get_setting( 'key' ) );
				} else {
					return null;
				}
			break;
			case 'post':
				// $store = 'post' is available for links only
				if ( $item instanceof De_Item_Link ) {
					$content = get_permalink( $item->get_setting( 'postId' ) );
				} elseif ( $item instanceof De_Item_Postwrapper ) {
					return true;
				} else {
					return null;
				}
			break;
			case 'postdate':
				// $store = 'date' is available for dates only
				if ( $item instanceof De_Item_Date ) {
					$content = get_the_time( 'Y-m-d H:i:s', $item->get_setting( 'postId' ) );
				} else {
					return null;
				}
			break;
			default:
				return null;
			break;
		}
		
		if ( $item instanceof De_Item_List ) {
			// We don't use $content here. Output is based on $item->list.
			$item->list = unserialize( $content );
			if ( empty( $item->list ) ) {
				$item->list = array();
				// Temporal solution. Get all de_list_item posts. Must be removed when "add" functionality will be ready
				//$de_list_items = get_posts( array( 'post_type' => 'de_list_item' ) );
				//foreach( $de_list_items as $de_list_item ) {
				//	$item->list[] = $de_list_item->ID;
				//}
			}
			$item->update();
			
			$content = '';
		}

		return $content;
	}

	public static function write( De_Item $item, $field ) {
		global $user_ID;

		if ( De_Store::is_editable( $item ) ) {
			if ( in_array( $item->store, array( 'postmeta', 'wptitle', 'wpcontent', 'wpexcerpt', 'postdate' ) ) && ! $item->get_setting( 'postId' ) ) {
				if ( ! self::$new_post_id ) {
					if ( in_array( $item->get_setting( 'postType' ), get_post_types( array('show_ui' => true ) ) ) )
						$obj = get_post_type_object( $item->get_setting( 'postType' ) );
					else
						return null;

					$new_post = array(
						'post_content' => '',
						'post_title' => 'New ' . $obj->labels->singular_name,
						'post_status' => 'draft',
						'post_date' => current_time( 'mysql' ),
						'post_author' => $user_ID,
						'post_type' => $item->get_setting( 'postType' ),
						'post_category' => array( 0 )
					);
					self::$new_post_id = wp_insert_post( $new_post );

					$new_post_title = 'New ' . $obj->labels->singular_name . ' ' . self::$new_post_id;
					$new_post = array(
						'ID' => self::$new_post_id,
						'post_title' => $new_post_title,
						'post_name' => sanitize_title( $new_post_title )
					);
					wp_update_post( $new_post );

					update_post_meta( self::$new_post_id, 'de_new_page', 1 );

					if ( De_Language_Wrapper::has_multilanguage() ) {
						De_Language_Wrapper::set_post_language( self::$new_post_id, De_Language_Wrapper::get_default_language() );
						De_Url::register_url( self::$new_post_id, sanitize_title( $new_post_title ) );
						De_Language_Wrapper::create_language_posts( self::$new_post_id );
					} else {
						De_Url::register_url( self::$new_post_id, sanitize_title( $new_post_title ) );
					}
					
					if ( $item->get_setting( 'redirect' ) && $item->get_setting( 'redirect' ) !== 'false' && $item->get_setting( 'redirect' ) !== 'no' ) {
						self::$redirect = add_query_arg( array( 'de_message' => 'saved' ), De_Url::get_url( self::$new_post_id ) );
					}
				}
				
				$item->set_setting( 'postId', self::$new_post_id );
			}

			$content = '';
			if ( isset( $field[ 'content' ] ) ) {
				$content = stripslashes( $field[ 'content' ] );
			}

			if ( $item instanceof De_Item_Link && isset( $field[ 'link' ] ) ) {
				$content = stripslashes( $field[ 'link' ] );
			}
			if ( $item instanceof De_Item_File && isset( $field[ 'url' ] ) ) {
				$content = stripslashes( $field[ 'url' ] );
			}

			if (  $item instanceof De_Item_Text ) {
				// Save inline images (make them public)
				if ( ! class_exists( 'simple_html_dom' ) ) {
					require_once( DIRECT_PATH . 'lib/simple_html_dom.php' );
				}
				
				$html = new simple_html_dom();
				
				$html->load( $content );
				foreach( $html->find('img[data-status=preview]') as $img ) {
					$id = $img->attr[ 'data-image' ];
					
					if ( self::save_image( $item, $id ) ) {
						$img->outertext = $item->output_partial_image( $id );
					}
				}

				$content = $html->save();
			} elseif ( $item instanceof De_Item_Image ) {
				// Save standalone images (make them public)
				$id = $field[ 'data' ][ 'image' ];
				
				if ( self::save_image( $item, $id ) ) {
					$content = $id;
				}
			} elseif ( $item instanceof De_Item_List ) {
				// We store $item->list as the content
				$content = serialize( $item->list );
			}

			// Use the received content to update but some predefined values to return if the received content is empty( the same functionality as in read() )
			switch( $item->store ) {
				case 'postmeta':
					if ( $item->get_setting( 'key' ) ) {
						if ( $item instanceof De_Item_Image && get_post_meta( $item->get_setting( 'postId' ), $item->get_setting( 'key' ), true ) && get_post_meta( $item->get_setting( 'postId' ), $item->get_setting( 'key' ), true ) != $id ) {
							wp_delete_attachment( get_post_meta( $item->get_setting( 'postId' ), $item->get_setting( 'key' ), true ), true );
						}
						update_post_meta( $item->get_setting( 'postId' ), $item->get_setting( 'key' ), $content );

						if ( empty( $content ) && De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $item->get_setting( 'postId' ) ) && De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() ) ) {
							$content = get_post_meta( De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() )->ID, $item->get_setting( 'key' ), true );
						}

						return $content;
					} else {
						return null;
					}
				break;
				case 'wptitle':
					// No tags in post title
					$content = strip_tags( $content );
					
					$currentSlug = get_post_field( 'post_name', $item->get_setting( 'postId' ) );
					$currentTitle = get_post_field( 'post_title', $item->get_setting( 'postId' ) );
					$currentType = get_post_field( 'post_type', $item->get_setting( 'postId' ) );
					$currentParent = get_post_field( 'post_parent', $item->get_setting( 'postId' ) );
					$currentDeSlug = get_post_meta( $item->get_setting( 'postId' ), 'de_slug', true );

					$myPost = array();
					$myPost[ 'ID' ] = $item->get_setting( 'postId' );
					$myPost[ 'post_title' ] = $content;
					
					wp_update_post( $myPost );
					
					if ( sanitize_title( $content ) && ( empty( $currentSlug ) || ( get_post_type( $item->get_setting( 'postId' ) ) != 'de_webform' && $currentSlug == wp_unique_post_slug( sanitize_title( $currentTitle ), $item->get_setting( 'postId' ), 'publish', $currentType, $currentParent ) ) ) ) {
						$newSlug = sanitize_title( $content );
						$myPost = array();
						$myPost[ 'ID' ] = $item->get_setting( 'postId' );
						$myPost['post_name'] = $newSlug;
						
						wp_update_post( $myPost );
						
						// We make redirect for our dE slugs only
						//if ( $item->get_setting( 'redirect' ) && $item->get_setting( 'redirect' ) !== 'false' && $item->get_setting( 'redirect' ) !== 'no' ) {
						//	self::$redirect = get_permalink( $item->get_setting( 'postId' ) );
						//}
					}
					
					if ( sanitize_title( $content ) && ( empty( $currentDeSlug ) || $currentDeSlug == De_Url::unique_slug( $item->get_setting( 'postId' ), sanitize_title( $currentTitle ) ) ) ) {
						De_Url::register_url( $item->get_setting( 'postId' ), sanitize_title( $content ) );

						if ( $item->get_setting( 'redirect' ) && $item->get_setting( 'redirect' ) !== 'false' && $item->get_setting( 'redirect' ) !== 'no' ) {
							self::$redirect = add_query_arg( array( 'de_message' => 'saved' ), De_Url::get_url( $item->get_setting( 'postId' ) ) );
						}
					}

					// Rename language posts if they don't have own titles
					if ( De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $item->get_setting( 'postId' ) ) == De_Language_Wrapper::get_default_language() ) {
						foreach( De_Language_Wrapper::get_language_posts( $item->get_setting( 'postId' ) ) as $lang_post ) {
							if ( $item->get_setting( 'postId' ) == $lang_post->ID ) {
								continue;
							}
							
							if ( get_post_meta( $lang_post->ID, 'de_title_not_translated', true ) ) {
								$lang_title = $content . ' (' . De_Language_Wrapper::get_post_language( $lang_post->ID ) . ')';
								
								$myPost = array();
								$myPost[ 'ID' ] = $lang_post->ID;
								$myPost[ 'post_title' ] = $lang_title;

								wp_update_post( $myPost );
								
								De_Url::register_url( $lang_post->ID, sanitize_title( $lang_title ) );
							}
						}
					} else {
						if ( get_post_meta( $item->get_setting( 'postId' ), 'de_title_not_translated', true ) ) {
							delete_post_meta( $item->get_setting( 'postId' ), 'de_title_not_translated' );
						}
					}

					if ( empty( $content ) && De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $item->get_setting( 'postId' ) ) && De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() ) ) {
						$content = get_the_title( De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() )->ID );
					}

					return $content;
				break;
				case 'wpcontent':
					$myPost = array();
					$myPost[ 'ID' ] = $item->get_setting( 'postId' );
					$myPost[ 'post_content' ] = $content;
					
					wp_update_post( $myPost );
					
					if ( empty( $content ) && De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $item->get_setting( 'postId' ) ) && De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() ) ) {
						$p = De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() );
						if ( $p ) {
							$content = $p->post_content;
						}
					}

					return $content;
				break;
				case 'wpexcerpt':
					$myPost = array();
					$myPost[ 'ID' ] = $item->get_setting( 'postId' );
					$myPost[ 'post_excerpt' ] = $content;
					
					wp_update_post( $myPost );
					
					$p = get_post( $item->get_setting( 'postId' ) );
					$text = $p->post_content;
					
					if ( '' == $content ) {
						$text = str_replace("</p>", "</p>\n", $text);
						$text = wp_trim_words( $text, 55, '...' );
						$content = wpautop( $text );
					}
					
					if ( empty( $content ) && De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $item->get_setting( 'postId' ) ) && De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() ) ) {
						$p = De_Language_Wrapper::get_language_post( $item->get_setting( 'postId' ), De_Language_Wrapper::get_default_language() );
						$content = $p->post_excerpt;
						$text = $p->post_content;
						
						if ( '' == $content ) {
							$text = str_replace("</p>", "</p>\n", $text);
							$text = wp_trim_words( $text, 55, '...' );
							$content = wpautop( $text );
						}
					}

					return $content;
				break;
				case 'usermeta':
					if ( $item->get_setting( 'key' ) ) {
						if ( $item->get_setting( 'userId' ) ) {
							update_user_meta( $item->get_setting( 'userId' ), $item->get_setting( 'key' ), $content );

							return $content;
						} else {
							// TODO: let's think, how to create a new user
							return '';
						}
					} else {
						return null;
					}
				break;
				case 'option':
					if ( $item->get_setting( 'key' ) ) {
						update_option( $item->get_setting( 'key' ), $content );

						return $content;
					} else {
						return null;
					}
				break;
				case 'post':
					if ( ( $item instanceof De_Item_Link || $item instanceof De_Item_Postwrapper ) && isset( $field[ 'index' ] ) && isset( $field[ 'count' ] ) ) {
						// Save post order
						update_post_meta( $item->get_setting( 'postId' ), sanitize_text_field( $field[ 'index' ] ), absint( $field[ 'count' ] ) );
						return absint( $field[ 'count' ] );
					} else {
						// Is not editable
						return null;
					}
				break;
				case 'postdate':
					if ( $content == mysql2date( 'Y-m-d H:i:s', $content ) ) {
						$myPost = array();
						$myPost[ 'ID' ] = $item->get_setting( 'postId' );
						$myPost[ 'post_date' ] = $content;
						$myPost[ 'post_date_gmt' ] = get_gmt_from_date( $content );
						
						wp_update_post( $myPost );

						return $content;
					} else {
						return null;
					}
				break;
				default:
					$classname = 'De_Store_' . ucfirst( $item->store );
					if ( class_exists( $classname ) ) {
						return $classname::write( $item, $field, $content );
					} else {
						return null;
					}
				break;
			}
		}
		
		return null;
	}
	
	public static function add_list_item() {
		global $user_ID;
		
		if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
			$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );

			if ( $item instanceof De_Item_List && De_Store::is_editable( $item ) ) {
				if ( $item->get_setting( 'itemType' ) ) {
					$item_type = $item->get_setting( 'itemType' );
				} else {
					$item_type = 'de_list_item';
				}
				$obj = get_post_type_object( $item_type );
				
				$new_post = array(
					'post_content' => '',
					'post_status' => 'publish',
					'post_date' => date('Y-m-d H:i:s'),
					'post_author' => $user_ID,
					'post_type' => $item_type,
					'post_category' => array(0)
				);
				$id = wp_insert_post( $new_post );
				$new_post_title = 'New ' . $obj->labels->singular_name . ' ' . $id;
				$new_post = array(
					'ID' => $id,
					'post_title' => $new_post_title,
					'post_name' => sanitize_title( $new_post_title )
				);
				wp_update_post( $new_post );
				
				return $id;
			}
		}
	}
	
	public static function upload_image() {
		$response = array();

		if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
			$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );
			
			if ( $item instanceof De_Item && De_Store::is_editable( $item ) ) {
				$file_id = 'file';
				
				$time = current_time( 'mysql' );
				if ( ( $item->store == 'wptitle' || $item->store == 'wpexcerpt' || $item->store == 'wpcontent' || $item->store == 'postmeta' ) && $item->get_setting( 'postId' ) ) {
					$post_id = $item->get_setting( 'postId' );
					
					if ( $post = get_post( $post_id ) ) {
						if ( substr( $post->post_date, 0, 4 ) > 0 )
							$time = $post->post_date;
					}
				} else {
					$post_id = 0;
				}
				
				$name = $_FILES[ $file_id ][ 'name' ];
				$file = wp_handle_upload( $_FILES[ $file_id ], array( 'test_form' => false, 'unique_filename_callback' => 'de_unique_imagename' ), $time );

				if ( isset( $file[ 'error' ] ) ) {
					$error = new WP_Error( 'upload_error', $file[ 'error' ] );
					echo $error->get_error_message();
					// Error
					return false;
				}

				$name_parts = pathinfo( $name );
				$name = trim( substr( $name, 0, -( 1 + strlen($name_parts['extension'] ) ) ) );

				$url = $file[ 'url' ];
				$type = $file[ 'type' ];
				$file = $file[ 'file' ];
				$title = $name;
				$content = '';

				// Use image exif/iptc data for title and caption defaults if possible
				if ( $image_meta = @wp_read_image_metadata( $file ) ) {
					if ( trim( $image_meta[ 'title' ] ) && ! is_numeric( sanitize_title( $image_meta[ 'title' ] ) ) )
						$title = $image_meta[ 'title' ];
					if ( trim( $image_meta[ 'caption' ] ) )
						$content = $image_meta[ 'caption' ];
				}

				// Resize the source image if sourceMaxResize is set
				if ( $item instanceof De_Item_Text ) {
					$buttonOptions = $item->get_setting( 'buttonOptions' );
					$imageOptions = $buttonOptions[ 'image' ];
				} else {
					$imageOptions = array(
						'sourceMaxResize' => $item->get_setting( 'sourceMaxResize' ),
						'sourceMinWidth' => $item->get_setting( 'sourceMinWidth' ),
						'sourceMinHeight' => $item->get_setting( 'sourceMinHeight' )
					);
				}
				if ( ! empty( $imageOptions[ 'sourceMaxResize' ] ) ) {
					list( $sourcewidth, $sourceheight ) = getimagesize( $file );
					$max_width = max( $imageOptions[ 'sourceMaxResize' ], ( ! empty( $imageOptions[ 'sourceMinWidth' ] ) ? $imageOptions[ 'sourceMinWidth' ] : 0 ) );
					$max_height = max( $imageOptions[ 'sourceMaxResize' ], ( ! empty( $imageOptions[ 'sourceMinHeight' ] ) ? $imageOptions[ 'sourceMinHeight' ] : 0 ) );
					/*
					$min_width = min( $sourcewidth, ( ! empty( $imageOptions[ 'sourceMinWidth' ] ) ? $imageOptions[ 'sourceMinWidth' ] : 0 ) );
					$min_height = min( $sourceheight, ( ! empty( $imageOptions[ 'sourceMinHeight' ] ) ? $imageOptions[ 'sourceMinHeight' ] : 0 ) );
					*/
					
					// this will scale the image down such that the SMALLEST side equals sourceMaxResize
					// this way the other side will never be too small
					$scale = min( max( $max_width / $sourcewidth , $max_height / $sourceheight) , 1);

					$new_width = (int) $sourcewidth * $scale;
					$new_height = (int) $sourceheight * $scale;	

					/*
					$new_width = $sourcewidth;
					$new_height = $sourceheight;
					if ( $sourcewidth > $max_width ) {
						$new_width = $max_width;
						$new_height = ( int ) ( $max_width * $sourceheight / $sourcewidth );
						if ( $new_height < $min_height ) {
							$new_width = ( int ) ( $sourcewidth * $min_height / $sourceheight );
							$new_height = $min_height;
						}
					} elseif ( $sourceheight > $max_height ) {
						$new_width = ( int ) ( $sourcewidth * $max_height / $sourceheight );
						$new_height = $max_height;
						if ( $new_width < $min_width ) {
							$new_width = $min_width;
							$new_height = ( int ) ( $min_width * $sourceheight / $sourcewidth );
						}
					}
					*/
					
					$info = getimagesize( $file );
					if ( $new_width < $sourcewidth || $info[ 'mime' ] == 'image/png' ) {
						switch( $info[ 'mime' ] ) {
							// GIF
							case 'image/gif':
							default:
									$sourceimage = @imageCreateFromGif( $file );
							break;
							
							// PNG
							case 'image/png':
									$sourceimage = @imageCreateFromPNG( $file );
							break;
							
							// JPG
							case 'image/jpg':
							case 'image/jpeg':
									$sourceimage = @imageCreateFromJpeg( $file );
							break;
						}

						$scaledimage = imagecreatetruecolor( $new_width, $new_height );
						if ( function_exists( 'imagesetinterpolation' ) ) {
							imagesetinterpolation( $scaledimage, IMG_BICUBIC );
						}
						imageAlphaBlending( $scaledimage, true );
						$white = imageColorExact ( $scaledimage, 255, 255, 255 );
						imageFill ( $scaledimage, 0, 0, $white );

						imagecopyresampled ( 
							$scaledimage,
							$sourceimage,
							0,
							0,
							0,
							0,
							$new_width,
							$new_height,
							$sourcewidth,
							$sourceheight
						);

						if ( $info[ 'mime' ] != 'image/jpg' && $info[ 'mime' ] != 'image/jpeg' ) {
							// Remove source
							@unlink( $file );
							
							$file_parts = pathinfo( $file );
							$file = substr( $file, 0, -( strlen( $file_parts['extension'] ) ) ) . 'jpg';
							$url = substr( $url, 0, -( strlen( $file_parts['extension'] ) ) ) . 'jpg';
							$type = 'image/jpeg';
						}
						
						// Save resized source
						imagejpeg( $scaledimage, $file, 100 );
					}
				}

				// Construct the attachment array
				$attachment = array(
					'post_mime_type' => $type,
					'guid' => $url,
					'post_title' => $title,
					'post_content' => $content,
				);
				if ( ! empty( $post )  ) {
					$attachment[ 'post_parent' ] = $post_id;
				}

				// Save the data
				$id = wp_insert_attachment( $attachment, $file, $post_id );
				if ( is_wp_error( $id ) ) {
					// Error
					return false;
				}

				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
				
				$response[ 'url' ] = $url;
				$response[ 'data' ][ 'reference' ] =  $_POST[ 'data' ][ 'reference' ];
				$response[ 'data' ][ 'image' ] = $id;
			}
		}
		
		return $response;
	}
	
	public static function upload_file() {
		$response = array();
		
		if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
			$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );
			
			if ( $item instanceof De_Item && De_Store::is_editable( $item ) ) {
				$file_id = 'file';
				
				$time = current_time( 'mysql' );
				if ( $item->store == 'postmeta' && $item->get_setting( 'postId' ) ) {
					$post_id = $item->get_setting( 'postId' );
					
					if ( $post = get_post( $post_id ) ) {
						if ( substr( $post->post_date, 0, 4 ) > 0 )
							$time = $post->post_date;
					}
				} else {
					$post_id = 0;
				}

				$name = $_FILES[ $file_id ][ 'name' ];
				$file = wp_handle_upload( $_FILES[ $file_id ], array( 'test_form' => false ), $time );

				if ( isset( $file[ 'error' ] ) ) {
					$error = new WP_Error( 'upload_error', $file[ 'error' ] );
					echo $error->get_error_message();
					// Error
					return false;
				}

				$name_parts = pathinfo( $name );
				$name = trim( substr( $name, 0, -( 1 + strlen($name_parts['extension'] ) ) ) );

				$url = $file[ 'url' ];
				$type = $file[ 'type' ];
				$file = $file[ 'file' ];
				$title = $name;
				$content = '';

				// Construct the attachment array
				$attachment = array(
					'post_mime_type' => $type,
					'guid' => $url,
					'post_parent' => $post_id,
					'post_title' => $title,
					'post_content' => $content,
				);
				// TODO: We leave post_parent empty for new posts
				if ( ! empty( $post )  ) {
					$attachment[ 'post_parent' ] = $post_id;
				}

				// Save the data
				$id = wp_insert_attachment( $attachment, $file, $post_id );
				if ( is_wp_error( $id ) ) {
					// Error
					return false;
				}

				wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
				
				$path = pathinfo( wp_get_attachment_url( $id ) );
				
				// We must pass only data-reference and url
				$response[ 'data' ][ 'reference' ] =  $_POST[ 'data' ][ 'reference' ];
				$response[ 'url' ] =  wp_get_attachment_url( $id );
				$response[ 'extension' ] =  $path[ 'extension' ];
				$response[ 'filename' ] =  $path[ 'basename' ];
			}
		}

		return $response;
	}

	public static function edit_image() {
		$response = array();
		
		if ( ! empty( $_POST[ 'data' ][ 'reference' ] ) ) {
			$item = De_Items::get( $_POST[ 'data' ][ 'reference' ] );
			
			if ( $item instanceof De_Item && De_Store::is_editable( $item ) ) {
				$id = $_POST[ 'data' ][ 'image' ];
				
				if ( ! wp_attachment_is_image( $id ) )
					return false;

				// Difference between standalone and inline images
				// Image options are stored in 'buttonOptions' 'image' item for inline images, and in root for standalone images
				if ( $item instanceof De_Item_Text ) {
					$buttonOptions = $item->get_setting( 'buttonOptions' );
					$imageOptions = $buttonOptions[ 'image' ];
				} else {
					$imageOptions = array(
						'imgHasRelativeScale' => $item->get_setting( 'imgHasRelativeScale' ),
						'imgWidth100' => $item->get_setting( 'imgWidth100' ),
						'imgFileFormat' => $item->get_setting( 'imgFileFormat' ),
						'imgQuality' => $item->get_setting( 'imgQuality' ),
						'copies' => $item->get_setting( 'copies' ),
						'styles' => $item->get_setting( 'styles' )
					);
				}

				// Files
				if ( ! empty( $imageOptions[ 'imgFileFormat' ] ) && in_array( strtolower( $imageOptions[ 'imgFileFormat' ] ), array( 'gif', 'jpg', 'png' ) ) ) {
					$f = strtolower( $imageOptions[ 'imgFileFormat' ] );
				} else {
					$f = 'jpg';
				}
				$sourcefileurl = wp_get_attachment_url( $id );
				$sourcefilepath = get_attached_file( $id );
				$wp_upload_dir = wp_upload_dir();
				$data = wp_get_attachment_metadata( $id );
				$file = $data[ 'file' ];
				$info = pathinfo( $file );
				$dir = $info[ 'dirname' ];
				$ext = $info[ 'extension' ];
				$name = wp_basename( $file, ".$ext" );
				$suffix = 'preview';
				$destfilename = $name . '-' . $suffix . '.' . $f;
				$destfileurl = $wp_upload_dir[ 'baseurl' ] . '/' . $dir . '/' . $destfilename;
				$destfilepath = $wp_upload_dir[ 'basedir' ] . '/' . $dir . '/' . $destfilename;

				// Scale
				list( $sourcewidth, $sourceheight ) = getimagesize( $sourcefilepath );
				$containerwidth = $_POST[ 'data' ][ 'containerW' ];
				$containerheight = $_POST[ 'data' ][ 'containerH' ];
				$left = $_POST[ 'data' ][ 'left' ];
				$top = $_POST[ 'data' ][ 'top' ];
				$scaledimagewidth = $_POST[ 'data' ][ 'imageScaledW' ];
				$scaledimageheight = $_POST[ 'data' ][ 'imageScaledH' ];

				$info = getimagesize( $sourcefilepath );
				// Check type
				switch( $info[ 'mime' ] ) {
					// GIF
					case 'image/gif':
					default:
							$sourceimage = @imageCreateFromGif( $sourcefilepath );
							$type = 'gif';
					break;
					
					// PNG
					case 'image/png':
							$sourceimage = @imageCreateFromPNG( $sourcefilepath );
							$type = 'png';
					break;
					
					// JPG
					case 'image/jpg':
					case 'image/jpeg':
							$sourceimage = @imageCreateFromJpeg( $sourcefilepath );
							$type = 'jpg';
					break;
				}
				
				if ( $sourcewidth == $scaledimagewidth && $sourcewidth == $containerwidth && $sourceheight == $scaledimageheight && $sourceheight == $containerheight && ! $left && ! $top && $type == $f ) {
					copy( $sourcefilepath, $destfilepath );
				} else {
					$scaledimage = imagecreatetruecolor( $containerwidth, $containerheight );
					if ( function_exists( 'imagesetinterpolation' ) ) {
						imagesetinterpolation( $scaledimage, IMG_BICUBIC );
					}
					if( $f == 'png' ) {
						imageAlphaBlending( $scaledimage, false );
						imageSaveAlpha( $scaledimage, true);
						imageFill ( $scaledimage, 0, 0, IMG_COLOR_TRANSPARENT );
					} else {
						imageAlphaBlending( $scaledimage, true );
						$white = imageColorExact ( $scaledimage, 255, 255, 255 );
						imageFill ( $scaledimage, 0, 0, $white );
					}

					$k = $sourcewidth / $scaledimagewidth;
					imagecopyresampled ( 
						$scaledimage,
						$sourceimage,
						max( $left, 0 ),
						max( $top, 0 ),
						max( - $k* $left, 0 ),
						max( - $k * $top, 0 ),
						min( $containerwidth, $scaledimagewidth ),
						min( $containerheight, $scaledimageheight ),
						min( $k * $containerwidth, $sourcewidth ),
						min( $k * $containerheight, $sourceheight )
					);

					// TODO: it would be cool to add image size text in dev mode
					//$text = $containerwidth . ' x ' . $containerheight;
					//$white = imagecolorallocate( $scaledimage, 255, 255, 255 );
					//imagestring( $scaledimage, 5, 11, 21, $text, $white );

					if( $f == 'jpg' ) {
						if ( ! empty( $imageOptions[ 'imgQuality' ] ) ) {
							$q = (int) $imageOptions[ 'imgQuality' ];
						} else {
							$q = 60;
						}

						imagejpeg( $scaledimage, $destfilepath, $q );
					} elseif( $f == 'gif' ) {
						imagegif( $scaledimage, $destfilepath );
					} else {
						imagepng( $scaledimage, $destfilepath );
					}
				}

				if( ! empty( $imageOptions[ 'copies' ] ) ) {
					$copies = $imageOptions[ 'copies' ];

					if ( is_array( $copies ) ) {
						foreach( $copies as $copyName => $copy ) {
							if ( ! empty( $copy[ 'imgFileFormat' ] ) && in_array( strtolower( $copy[ 'imgFileFormat' ] ), array( 'gif', 'jpg', 'png' ) ) ) {
								$f_copy = strtolower( $copy[ 'imgFileFormat' ] );
							} else {
								$f_copy = $f;
							}

							$destfilenameCopy = $name . '-' . $suffix . $copyName . '.' . $f_copy;
							$destfileurlCopy = $wp_upload_dir[ 'baseurl' ] . '/' . $dir . '/' . $destfilenameCopy;
							$destfilepathCopy = $wp_upload_dir[ 'basedir' ] . '/' . $dir . '/' . $destfilenameCopy;

							if ( ! empty( $copy[ 'scale' ] ) ) {
								$containerwidthCopy = $containerwidth * $copy[ 'scale' ];
								$containerheightCopy = $containerheight * $copy[ 'scale' ];
								$scale = $copy[ 'scale' ];
							} elseif ( $copy[ 'width' ] || $copy[ 'height' ] ) {
								if ( $copy[ 'width' ] && $copy[ 'height' ] ) {
									$scale = max( $copy[ 'width' ] / $containerwidth, $copy[ 'height' ] / $containerheight );
									$containerwidthCopy = min( $copy[ 'width' ], $containerwidth * $scale );
									$containerheightCopy = min( $copy[ 'height' ], $containerheight * $scale );
								} elseif( $copy[ 'width' ] ) {
									$scale = $copy[ 'width' ] / $containerwidth;
									$containerwidthCopy = $copy[ 'width' ];
									$containerheightCopy = $containerheight * $scale;
								} else {
									$scale = $copy[ 'height' ] / $containerheight;
									$containerwidthCopy = $containerwidth * $scale;
									$containerheightCopy = $copy[ 'height' ];
								}
							} else {
								$scale = 1;
								$containerwidthCopy = $containerwidth;
								$containerheightCopy = $containerheight;
							}
							$scaledimagewidthCopy = $scaledimagewidth * $scale;
							$scaledimageheightCopy = $scaledimageheight * $scale;
							$leftCopy = $left * $scale;
							$topCopy = $top * $scale;
							if ( $containerwidthCopy < $scaledimagewidthCopy ) {
								$leftCopy -= ( $scaledimagewidthCopy - $containerwidthCopy ) / 2;
							}
							if ( $containerheightCopy < $scaledimageheightCopy ) {
								$topCopy -= ( $scaledimageheightCopy - $containerheightCopy ) / 2;
							}
							
							$scaledimageCopy = imagecreatetruecolor( $containerwidthCopy, $containerheightCopy );
							if ( function_exists( 'imagesetinterpolation' ) ) {
								imagesetinterpolation( $scaledimageCopy, IMG_BICUBIC );
							}
							if( $f == 'png' ) {
								imageAlphaBlending( $scaledimageCopy, false );
								imageSaveAlpha( $scaledimageCopy, true);
								imageFill ( $scaledimageCopy, 0, 0, IMG_COLOR_TRANSPARENT );
							} else {
								imageAlphaBlending( $scaledimageCopy, true );
								$white = imageColorExact ( $scaledimageCopy, 255, 255, 255 );
								imageFill ( $scaledimageCopy, 0, 0, $white );
							}

							$kCopy = $sourcewidth / $scaledimagewidthCopy;
							imagecopyresampled ( 
								$scaledimageCopy,
								$sourceimage,
								max( $leftCopy, 0 ),
								max( $topCopy, 0 ),
								max( - $kCopy* $leftCopy, 0 ),
								max( - $kCopy * $topCopy, 0 ),
								min( $containerwidthCopy, $scaledimagewidthCopy ),
								min( $containerheightCopy, $scaledimageheightCopy ),
								min( $kCopy * $containerwidthCopy, $sourcewidth ),
								min( $kCopy * $containerheightCopy, $sourceheight )
							);

							if ( ! empty( $copy[ 'filter' ] ) ) {
								switch( $copy[ 'filter' ] ) {
									case 'monochrome':
										imagefilter ( $scaledimageCopy, IMG_FILTER_GRAYSCALE );
									break;
								}
							}
							
							// TODO: it would be cool to add image size text in dev mode
							//$text = $containerwidthCopy . ' x ' . $containerheightCopy;
							//$white = imagecolorallocate( $scaledimageCopy, 255, 255, 255 );
							//imagestring( $scaledimageCopy, 5, 11, 21, $text, $white );

							if( $f_copy == 'jpg' ) {
								if ( ! empty( $copy[ 'imgQuality' ] ) ) {
									$q_copy = (int) $copy[ 'imgQuality' ];
								} elseif ( ! empty( $imageOptions[ 'imgQuality' ] ) ) {
									$q_copy = (int) $imageOptions[ 'imgQuality' ];
								} else {
									$q_copy = 60;
								}

								imagejpeg( $scaledimageCopy, $destfilepathCopy, $q_copy );
							} elseif( $f_copy == 'gif' ) {
								imagegif( $scaledimageCopy, $destfilepathCopy );
							} else {
								imagepng( $scaledimageCopy, $destfilepathCopy );
							}
							
							$data[ 'sizes' ][ 'preview' ][ 'copies' ][ $copyName ][ 'file' ] = $destfilenameCopy;
						}
					}
				}
				
				// Update metadata
				$data[ 'sizes' ][ 'preview' ][ 'file' ] = $destfilename;
				$data[ 'sizes' ][ 'preview' ][ 'width' ] = $scaledimagewidth;
				$data[ 'sizes' ][ 'preview' ][ 'height' ] = $scaledimageheight;
				$data[ 'sizes' ][ 'preview' ][ 'container-width' ] = $containerwidth;
				$data[ 'sizes' ][ 'preview' ][ 'container-height' ] = $containerheight;
				$data[ 'sizes' ][ 'preview' ][ 'left' ] = $left;
				$data[ 'sizes' ][ 'preview' ][ 'top' ] = $top;
				$data[ 'sizes' ][ 'preview' ][ 'alt' ] = $_POST[ 'data' ][ 'alt' ];
				$data[ 'sizes' ][ 'preview' ][ 'style' ] = $_POST[ 'data' ][ 'style' ];
				
				wp_update_attachment_metadata( $id, $data );
				
				$response[ 'content' ] = $item->output_partial_image( $id, 'preview' );
			}
		}
		
		return $response;
	}
	
	public static function save_image( De_Item $item, $id ) {
		if ( ! wp_attachment_is_image( $id ) )
			return false;
		
		$wp_upload_dir = wp_upload_dir();
		$data = wp_get_attachment_metadata( $id );
		$file = $data[ 'file' ];
		$info = pathinfo( $file );
		$dir = $info['dirname'];
		$ext = $info['extension'];
		$info2 = pathinfo( $data[ 'sizes' ][ 'preview' ][ 'file' ] );
		$ext2 = $info2['extension'];
		$name = wp_basename( $file, ".$ext" );
		$suffix = 'preview';
		$sourcefilename = $name . '-' . $suffix . '.' . $ext2;
		$sourcefileurl = $wp_upload_dir[ 'baseurl' ] . '/' . $dir . '/' . $sourcefilename;
		$sourcefilepath = $wp_upload_dir[ 'basedir' ] . '/' . $dir . '/' . $sourcefilename;
		$suffix = 'public';
		$destfilename = $name . '-' . $suffix . '.' . $ext2;
		$destfileurl = $wp_upload_dir[ 'baseurl' ] . '/' . $dir . '/' . $destfilename;
		$destfilepath = $wp_upload_dir[ 'basedir' ] . '/' . $dir . '/' . $destfilename;
		copy( $sourcefilepath, $destfilepath );
		unlink( $sourcefilepath );
		
		$dataCopy = array();
		if ( ! empty( $data[ 'sizes' ][ 'preview' ][ 'copies' ] ) && is_array( $data[ 'sizes' ][ 'preview' ][ 'copies' ] ) ) {
			foreach( $data[ 'sizes' ][ 'preview' ][ 'copies' ] as $k => $v ) {
				$info2Copy = pathinfo( $v[ 'file' ] );
				$ext2Copy = $info2Copy['extension'];
				$suffix = 'preview';
				$sourcefilenameCopy = $name . '-' . $suffix . $k . '.' . $ext2Copy;
				$sourcefileurlCopy = $wp_upload_dir[ 'baseurl' ] . '/' . $dir . '/' . $sourcefilenameCopy;
				$sourcefilepathCopy = $wp_upload_dir[ 'basedir' ] . '/' . $dir . '/' . $sourcefilenameCopy;
				$suffix = 'public';
				$destfilenameCopy = $name . '-' . $suffix . $k .'.' . $ext2Copy;
				$destfileurlCopy = $wp_upload_dir[ 'baseurl' ] . '/' . $dir . '/' . $destfilenameCopy;
				$destfilepathCopy = $wp_upload_dir[ 'basedir' ] . '/' . $dir . '/' . $destfilenameCopy;
				copy( $sourcefilepathCopy, $destfilepathCopy );
				unlink( $sourcefilepathCopy );
				$dataCopy[ $k ][ 'file' ] = $destfilenameCopy;
			}
		}
		
		$data = wp_get_attachment_metadata( $id );
		$data[ 'sizes' ][ 'public' ][ 'copies' ] = $dataCopy;
		$data[ 'sizes' ][ 'public' ][ 'file' ] = $destfilename;
		$data[ 'sizes' ][ 'public' ][ 'width' ] = $data[ 'sizes' ][ 'preview' ][ 'width' ];
		$data[ 'sizes' ][ 'public' ][ 'height' ] = $data[ 'sizes' ][ 'preview' ][ 'height' ];
		$data[ 'sizes' ][ 'public' ][ 'alt' ] = $data[ 'sizes' ][ 'preview' ][ 'alt' ];
		$data[ 'sizes' ][ 'public' ][ 'container-width' ] = $data[ 'sizes' ][ 'preview' ][ 'container-width' ];
		$data[ 'sizes' ][ 'public' ][ 'container-height' ] = $data[ 'sizes' ][ 'preview' ][ 'container-height' ];
		$data[ 'sizes' ][ 'public' ][ 'left' ] = $data[ 'sizes' ][ 'preview' ][ 'left' ];
		$data[ 'sizes' ][ 'public' ][ 'top' ] = $data[ 'sizes' ][ 'preview' ][ 'top' ];
		$data[ 'sizes' ][ 'public' ][ 'style' ] = $data[ 'sizes' ][ 'preview' ][ 'style' ];
		unset( $data[ 'sizes' ][ 'preview' ] );
		wp_update_attachment_metadata( $id, $data );
		
		// post_parent can be empty for new posts
		if ( $item->store == 'postmeta' && ! empty( self::$new_post_id ) ) {
			$p = get_post( $id );
			
			if ( empty( $p->post_parent ) ) {
				$params = array(
					'ID' => $id,
					'post_parent' => self::$new_post_id
				);
				wp_update_post( $params );
			}
		}

		return true;
	}
	
	/* Menu editor is hidden. Probably it will be removed at all in future versions. */
	/*
	public static function read_menus() {
		$response = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'selectionPath' => array()
		);
		
		$menus = wp_get_nav_menus( array('orderby' => 'name') );
		foreach( $menus as $value ) {
			$menu_items_array = array();
			$items = array();
			
			$menu_items = wp_get_nav_menu_items( $value->term_id );
			foreach( $menu_items as $menu_item ) {
				$menu_items_array[ $menu_item->menu_item_parent ][] = $menu_item;
			}

			if ( count( $menu_items_array[ 0 ] ) ) {
				foreach( $menu_items_array[ 0 ] as $menu_item ) {
					$items[] = self::read_menu_recursive( $menu_item, $menu_items_array );
				}
				
				$response[ 'menus' ][ $value->slug ] = array(
					'items' => $items
				);
			}
		}

		// Add Pages
		if ( get_option( 'de_menu_editor_pages' ) ) {
			$items = array();
			
			$args = array(
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
				'post_type' => 'page',
				'post_status' => 'any'
			);
			$menuitems = get_posts( $args );
			foreach ( $menuitems as $menuitem ) {
				if (  de_is_de_archive( $menuitem->ID ) ) {
					continue;
				}
				
				$item = array(
					'name' => $menuitem->post_title,
					'data' => array(
						'menu-item-object' => 'page',
						'menu-item-object-id' => $menuitem->ID,
						'menu-item-status' => 'publish',
						'menu-item-type' => 'post_type'
					)
				);
				
				if ( ! self::menu_is_editable( $menuitem ) ) {
					$item[ 'disabled' ] = 1;
				}
				
				$items[] = $item;
				
				// Do we have Home menuitem?
				if ( get_permalink( $menuitem->ID ) == home_url() ) {
					$home = $menuitem;
				}
			}

			if ( ! isset( $home ) ) {
				$item = array(
					'name' => __( 'Home', 'direct-edit' ),
					'data' => array(
						'menu-item-object' => 'custom',
						'menu-item-status' => 'publish',
						'menu-item-type' => 'custom',
						'menu-item-url' => home_url()
					)
				);

				if ( ! get_option( 'de_menu_editor_pages' ) ) {
					$item[ 'disabled' ] = 1;
				}

				array_unshift( $items, $item );
			}
			
			if ( count( $items ) ) {
				$response[ 'new' ][ 'pages' ] = array(
					'name' => __( 'Pages', 'direct-edit' ),
					'items' => $items
				);
			}
		}
		
		// Add DE archive Pages
		if ( get_option( 'de_menu_editor_de_archive_pages' ) ) {
			$items = array();
			
			$args = array(
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
				'post_type' => 'page',
				'post_status' => 'any'
			);
			$menuitems = get_posts( $args );
			foreach ( $menuitems as $menuitem ) {
				if (  ! de_is_de_archive( $menuitem->ID ) ) {
					continue;
				}

				$item = array(
					'name' => $menuitem->post_title,
					'data' => array(
						'menu-item-object' => 'page',
						'menu-item-object-id' => $menuitem->ID,
						'menu-item-status' => 'publish',
						'menu-item-type' => 'post_type'
					)
				);
				
				$items[] = $item;
			}
			
			if ( count( $items ) ) {
				$response[ 'new' ][ 'de_archive_pages' ] = array(
					'name' => __( 'Archive Pages', 'direct-edit' ),
					'items' => $items
				);
			}
		}

		// Add DE Webforms
		if ( get_option( 'de_menu_editor_de_webforms' ) ) {
			$items = array();
			
			$args = array(
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
				'post_type' => 'de_webform',
				'post_status' => 'any'
			);
			$menuitems = get_posts( $args );
			foreach ( $menuitems as $menuitem ) {
				$item = array(
					'name' => $menuitem->post_title,
					'data' => array(
						'menu-item-object' => 'de_webform',
						'menu-item-object-id' => $menuitem->ID,
						'menu-item-status' => 'publish',
						'menu-item-type' => 'post_type'
					)
				);
				
				$items[] = $item;
			}
			
			if ( count( $items ) ) {
				$response[ 'new' ][ 'de_webforms' ] = array(
					'name' => __( 'Webforms', 'direct-edit' ),
					'items' => $items
				);
			}
		}

		// Add Categories
		if ( get_option( 'de_menu_editor_categories' ) ) {
			$items = array();
			
			$args = array(
				'orderby' => 'name',
				'order' => 'ASC'
			);
			$categories = get_categories( $args );
			foreach ( $categories as $category ) {
				$item = array(
					'name' => $category->name,
					'data' => array(
						'menu-item-object' => 'category',
						'menu-item-object-id' => $category->cat_ID,
						'menu-item-status' => 'publish',
						'menu-item-type' => 'taxonomy'
					)
				);
				
				$items[] = $item;
			}
			
			if ( count( $items ) ) {
				$response[ 'new' ][ 'categories' ] = array(
					'name' => __( 'Categories', 'direct-edit' ),
					'items' => $items
				);
			}
		}

		// Add Taxonomies
		foreach( get_taxonomies( array( '_builtin' => false ) ) as $value ) {
			if ( get_option( 'de_menu_editor_taxonomies_' . $value->name ) ) {
				$items = array();
				
				$args = array(
					'orderby' => 'name',
					'order' => 'ASC'
				);
				$terms = get_terms( $value->name, $args );
				foreach ( $terms as $term ) {
					$item = array(
						'name' => $term->name,
						'data' => array(
							'menu-item-object' => $value->name,
							'menu-item-object-id' => $term->term_ID,
							'menu-item-status' => 'publish',
							'menu-item-type' => 'taxonomy'
						)
					);
					
					$items[] = $item;
				}
				
				if ( count( $items ) ) {
					$response[ 'new' ][ $value->name ] = array(
						'name' => __( $value->label, 'direct-edit' ),
						'items' => $items
					);
				}
			}
		}
		
		// Add Posts
		if ( get_option( 'de_menu_editor_posts' ) ) {
			$items = array();
			
			$args = array(
				'posts_per_page' => -1,
				'orderby' => 'title',
				'order' => 'ASC',
				'post_type' => 'post',
				'post_status' => 'any'
			);
			$menuitems = get_posts( $args );
			foreach ( $menuitems as $menuitem ) {
				$item = array(
					'name' => $menuitem->post_title,
					'data' => array(
						'menu-item-object' => 'post',
						'menu-item-object-id' => $menuitem->ID,
						'menu-item-status' => 'publish',
						'menu-item-type' => 'post_type'
					)
				);
				
				$items[] = $item;
			}
			
			if ( count( $items ) ) {
				$response[ 'new' ][ 'posts' ] = array(
					'name' => __( 'Posts', 'direct-edit' ),
					'items' => $items
				);
			}
		}

		// Add Custom Posts
		foreach( get_post_types( array( '_builtin' => false ), 'object' ) as $value ) {
			if ( $value->name == 'de_list_item' || $value->name == 'de_webform' ) {
				continue;
			}
			
			if ( get_option( 'de_menu_editor_posts_' . $value->name ) ) {
				$items = array();
				
				$args = array(
					'posts_per_page' => -1,
					'orderby' => 'title',
					'order' => 'ASC',
					'post_type' => $value->name,
					'post_status' => 'any'
				);
				$menuitems = get_posts( $args );
				foreach ( $menuitems as $menuitem ) {
					$item = array(
						'name' => $menuitem->post_title,
						'data' => array(
							'menu-item-object' => $value->name,
							'menu-item-object-id' => $menuitem->ID,
							'menu-item-status' => 'publish',
							'menu-item-type' => 'post_type'
						)
					);
					
					$items[] = $item;
				}
				
				if ( count( $items ) ) {
					$response[ 'new' ][ $value->name ] = array(
						'name' => __( $value->label, 'direct-edit' ),
						'items' => $items
					);
				}
			}
		}

		return $response;
	}
	
	public static function read_menu_recursive( $menu_item, $menu_items_array ) {
		$item = array(
			'name' => $menu_item->title,
			'data' => array(
				'ID' => $menu_item->ID,
				'menu-item-object' => $menu_item->object,
				'menu-item-object-id' => $menu_item->object_id,
				'menu-item-status' => $menu_item->status,
				'menu-item-type' => $menu_item->type,
				'menu-item-url' => $menu_item->url
			)
		);
		if ( ! empty( $menu_items_array[ $menu_item->ID ] ) )
		foreach( $menu_items_array[ $menu_item->ID ] as $menu_item_child ) {
			$item[ 'items' ][] = self::read_menu_recursive( $menu_item_child, $menu_items_array );
		}

		return $item;
	}

	public static function write_menus() {
		if ( ! empty( $_POST[ 'menus' ] ) && is_array( $_POST[ 'menus' ] ) ) {
			foreach( $_POST[ 'menus' ] as $key => $menu_item ) {
				$menu = wp_get_nav_menu_object( $key );
				$unsorted_menu_items = wp_get_nav_menu_items( $menu->term_id, array( 'orderby' => 'ID', 'output' => ARRAY_A, 'output_key' => 'ID' ) );

				$menu_items = array();
				// Index menu items by db ID
				foreach ( $unsorted_menu_items as $item ) {
					$menu_items[ $item->db_id ] = $item;
				}

				if ( ! empty( $menu_item[ 'items' ] ) && is_array( $menu_item[ 'items' ] ) ) {
					$position = 1;
					foreach( $menu_item[ 'items' ] as $item ) {
						self::write_menu_recursive( $menu->term_id, $item, 0, $position, $menu_items );
					}
				}

				// Remove menu items from the menu that weren't in $_POST
				if ( ! empty( $menu_items ) ) {
					foreach ( array_keys( $menu_items ) as $menu_item_id ) {
						if ( is_nav_menu_item( $menu_item_id ) ) {
							wp_delete_post( $menu_item_id );
						}
					}
				}
			}
		}
		
		return self::read_menus();
	}
	
	public static function write_menu_recursive( $menu_id, $menu_item, $parent_id, &$position, &$menu_items ) {
		$id = ( isset( $menu_item[ 'data' ][ 'ID' ] ) ? $menu_item[ 'data' ][ 'ID' ] : 0 );
		$args = $menu_item[ 'data' ];
		$args[ 'menu-item-parent-id' ] = $parent_id;
		$args[ 'menu-item-position' ] = $position;
		$args[ 'menu-item-title' ] = $menu_item[ 'name' ];
		unset( $menu_item[ 'data' ][ 'ID' ] );
		$menu_item_db_id = wp_update_nav_menu_item( $menu_id, $id, $args );

		if ( is_wp_error( $menu_item_db_id ) ) {
			die( 1 );
		} elseif ( isset( $menu_items[ $menu_item_db_id ] ) ) {
			unset( $menu_items[ $menu_item_db_id ] );
		}

		$position ++;
		
		if ( ! empty( $menu_item[ 'items' ] ) && is_array( $menu_item[ 'items' ] ) ) {
			foreach( $menu_item[ 'items' ] as $item ) {
				self::write_menu_recursive( $menu_id, $item, $menu_item_db_id, $position, $menu_items );
			}
		}
	}
	
	public static function menu_is_editable( $item ) {
		if ( ( $item->type == 'post_type' && $item->object == 'page' && ! de_is_de_archive( $item->object_id ) ) || ( $item->type == 'custom' && $item->object == 'custom' && $item->url == home_url() ) ) {
			return get_option( 'de_menu_editor_pages' );
		} elseif ( $item->type == 'post_type' && $item->object == 'page' && de_is_de_archive( $item->object_id ) ) {
			return get_option( 'de_menu_editor_de_archive_pages' );
		} elseif ( $item->type == 'post_type' && $item->object == 'de_webform' ) {
			return get_option( 'de_menu_editor_de_webforms' );
		} elseif( $item->type == 'taxonomy' && $item->object == 'category' ) {
			return get_option( 'de_menu_editor_categories' );
		} elseif ( $item->type == 'taxonomy' ) {
			return get_option( 'de_menu_editor_taxonomies_' . $item->object );
		} elseif ( $item->type == 'post_type' && $item->object == 'post' ) {
			return get_option( 'de_menu_editor_posts' );
		} elseif ( $item->type == 'post_type' ) {
			return get_option( 'de_menu_editor_posts_' . $item->object );
		} else {
			return false;
		}
	}
	*/
}
