<?php
class De_Store {
	public static $new_post_id;
	public static $redirect;
	
	public static function is_editable( De_Item $item ) {
		if ( $item->get_setting( 'disableEdit' ) && $item->get_setting( 'disableEdit' ) !== 'false' && $item->get_setting( 'disableEdit' ) !== 'no' ) {
			return false;
		}
		
		switch( $item->store ) {
			case 'postmeta':
			case 'wptitle':
			case 'wpcontent':
			case 'wpexcerpt':
			case 'post':
			case 'postdate':
				return ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_de_frontend' ) );
			break;
			case 'usermeta':
				return ( current_user_can( 'edit_users' ) || current_user_can( 'edit_de_frontend' ) );
			break;
			case 'option':
				return ( current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) );
			break;
			default:
				return false;
			break;
		}
	}
	
	public static function read( De_Item $item ) {
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
					de_wrap_the_title_remove();
					$content = get_the_title( $item->get_setting( 'postId' ) );
					de_wrap_the_title_restore();
					
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
				} else {
					return null;
				}
			break;
			case 'postdate':
				// $store = 'date' is available for dates only
				if ( $item instanceof De_Item_Date ) {
					$content = get_the_time( 'Y-m-d H:i:s', $item->get_setting( 'postId' ) );;
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
					self::$new_post_id = wp_insert_post( $new_post, $wp_error );

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
					
					if ( empty( $currentSlug ) || ( get_post_type( $item->get_setting( 'postId' ) ) != 'de_webform' && $currentSlug == wp_unique_post_slug( sanitize_title( $currentTitle ), $item->get_setting( 'postId' ), 'publish', $currentType, $currentParent ) ) ) {
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
					
					if ( empty( $currentDeSlug ) || $currentDeSlug == De_Url::unique_slug( $item->get_setting( 'postId' ), sanitize_title( $currentTitle ) ) ) {
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
					// Is not editable
					return null;
				break;
				case 'date':
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
					return null;
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
				$new_post_title = 'New ' . $obj->labels->singular_name . ' ' . $i;
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
		$responce = array();

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
					$min_width = min( $sourcewidth, ( ! empty( $imageOptions[ 'sourceMinWidth' ] ) ? $imageOptions[ 'sourceMinWidth' ] : 0 ) );
					$min_height = min( $sourceheight, ( ! empty( $imageOptions[ 'sourceMinHeight' ] ) ? $imageOptions[ 'sourceMinHeight' ] : 0 ) );
					
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
				
				$responce[ 'url' ] = $url;
				$responce[ 'data' ][ 'reference' ] =  $_POST[ 'data' ][ 'reference' ];
				$responce[ 'data' ][ 'image' ] = $id;
			}
		}
		
		return $responce;
	}
	
	public static function upload_file() {
		$responce = array();
		
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
				$responce[ 'data' ][ 'reference' ] =  $_POST[ 'data' ][ 'reference' ];
				$responce[ 'url' ] =  wp_get_attachment_url( $id );
				$responce[ 'extension' ] =  $path[ 'extension' ];
				$responce[ 'filename' ] =  $path[ 'basename' ];
			}
		}

		return $responce;
	}

	public static function edit_image() {
		$responce = array();
		
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
				
				if ( $sourcewidth == $scaledimagewidth && $sourcewidth == $containerwidth && ! $left && ! $top && $type == $f ) {
					copy( $sourcefilepath, $destfilepath );
				} else {
					$scaledimage = imagecreatetruecolor( $containerwidth, $containerheight );
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

				if( $imageOptions[ 'copies' ] ) {
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

							if ( $copy[ 'scale' ] ) {
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
							
							$scaledimageCopy = imagecreatetruecolor( $containerwidthCopy, $containerheightCopy );
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
								if ( $copy[ 'imgQuality' ] ) {
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
				
				$responce[ 'content' ] = $item->output_partial_image( $id, 'preview' );
			}
		}
		
		return $responce;
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
		if ( is_array( $data[ 'sizes' ][ 'preview' ][ 'copies' ] ) ) {
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
}
