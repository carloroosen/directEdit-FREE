<?php
class De_Url {
	public static function register_url( $de_post_id, $de_slug ) {
		$de_post = get_post( $de_post_id );
		
		if ( $de_post ) {
			if( get_option( 'de_page_for_' . $de_post->post_type ) ) {
				if ( De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $de_post ) ) {
					$parent_post_id = get_option( 'de_page_for_' . $de_post->post_type );
					
					if ( ! De_Language_Wrapper::get_post_language( $parent_post_id ) ) {
						De_Language_Wrapper::set_post_language( $parent_post_id, De_Language_Wrapper::get_default_language() );
					}
					
					if ( ! De_Language_Wrapper::get_language_post( $parent_post_id, De_Language_Wrapper::get_post_language( $de_post ) ) ) {
						De_Language_Wrapper::create_language_posts( $parent_post_id );
					}
					
					$de_post_parent = De_Language_Wrapper::get_language_post( $parent_post_id, De_Language_Wrapper::get_post_language( $de_post ) )->ID;
				} else {
					$de_post_parent = get_option( 'de_page_for_' . $de_post->post_type );
				}
			} else {
				$de_post_parent = 0;
			}

			if ( $de_post_parent ) {
				update_post_meta( $de_post_id, 'de_post_parent', $de_post_parent );
			} else {
				delete_post_meta( $de_post_id, 'de_post_parent' );
			}

			$de_slug = self::unique_slug( $de_post_id, $de_slug );
			update_post_meta( $de_post_id, 'de_slug', $de_slug );
			
			return $de_slug;
		}

		return null;
	}

	public static function get_url( $de_post_id ) {
		if ( get_option( 'de_smart_urls' ) && get_option( 'permalink_structure' ) == '/%postname%/' ) {
			$de_post = get_post( $de_post_id );
			$result = '';
			
			if ( $de_post ) {
				// Prevent infinite loop
				remove_filter( 'page_link', 'de_filter_permalink' );
				remove_filter( 'post_link', 'de_filter_permalink' );
				remove_filter( 'post_type_link', 'de_filter_permalink' );
				
				if ( de_is_front_page( $de_post_id ) ) {
					if ( De_Language_Wrapper::has_multilanguage() ) {
						$result = home_url( '/' . De_Language_Wrapper::get_post_language( $de_post_id ) . '/' );
					} else {
						// Return home url
						$result = home_url();
					}
				} else {
					$de_slug = get_post_meta( $de_post_id, 'de_slug', true );
					if ( empty( $de_slug ) ) {
						$result = get_permalink( $de_post_id );
					} else {
						$de_post_parent = get_post_meta( $de_post_id, 'de_post_parent', true );
						if ( $de_post_parent ) {
							$p = self::get_url( $de_post_parent );
							if ( $p ) {
								$result = $p . "$de_slug/";
							} else {
								$result = get_permalink( $de_post_id );
							}
						} else {
							if ( De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $de_post_id ) ) {
								$result = home_url( "/" . De_Language_Wrapper::get_post_language( $de_post_id ) . "/$de_slug/" );
							} else {
								$result = home_url( "/$de_slug/" );
							}
						}
					}
				}
				
				// Re-hook this again
				add_filter( 'page_link', 'de_filter_permalink', 10, 2 );
				add_filter( 'post_link', 'de_filter_permalink', 10, 2 );
				add_filter( 'post_type_link', 'de_filter_permalink', 10, 2 );
			}
			
			return $result;
		} else {
			// Do we need to return smart urls for drafts? If yes, this code should be fixed (%pagename% for pages %postname% for posts)
			//if ( get_post_status ( $de_post_id ) == 'publish' ) {
				return get_permalink( $de_post_id );
			//} else {
			//	require_once ABSPATH . '/wp-admin/includes/post.php';
			//	list( $permalink, $postname ) = get_sample_permalink( $de_post_id );

			//	return str_replace( '%pagename%', $postname, $permalink );
			//}
		}
	}
	
	public static function get_post( $de_url ) {
		global $wpdb;
		global $post;

		// dE slug
		if( $de_url )
			$de_url_array = explode( '/', $de_url );

		if ( De_Language_Wrapper::has_multilanguage() && is_array( $de_url_array ) && count( $de_url_array ) ) {
			if ( in_array( $de_url_array[ 0 ], De_Language_Wrapper::get_languages() ) ) {
				$de_curlang = array_shift( $de_url_array );
			}
		}

		if ( is_array( $de_url_array ) && count( $de_url_array ) ) {
			$i = 0;
			$de_post_parent = 0;

			while ( $i < count( $de_url_array ) ) {
				if ( $de_post_parent ) {
					$querystr = "
						SELECT wposts.*
						FROM $wpdb->posts wposts, $wpdb->postmeta deslug, $wpdb->postmeta depostparent
						WHERE wposts.ID = deslug.post_id
						AND deslug.meta_key = 'de_slug'
						AND deslug.meta_value = '" . esc_sql( $de_url_array[ $i ] ) . "'
						AND wposts.ID = depostparent.post_id
						AND depostparent.meta_key = 'de_post_parent'
						AND depostparent.meta_value = " . esc_sql( $de_post_parent ) . "
					";
					if ( ! ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_de_frontend' ) ) ) {
						$querystr .= " AND wposts.post_status != 'draft'";
					}
				} else {
					$querystr = "
						SELECT wposts.*
						FROM $wpdb->posts wposts, $wpdb->postmeta deslug
						WHERE wposts.ID = deslug.post_id
						AND deslug.meta_key = 'de_slug'
						AND deslug.meta_value = '" . esc_sql( $de_url_array[ $i ] ) . "'
						AND not exists(
							SELECT * from $wpdb->postmeta
							WHERE post_id = wposts.ID
							AND meta_key = 'de_post_parent'
						)
					";
					if ( ! ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_de_frontend' ) ) ) {
						$querystr .= " AND wposts.post_status != 'draft'";
					}
				}

				if ( De_Language_Wrapper::has_multilanguage() && isset( $de_curlang ) ) {
					unset( $result );
					foreach( $wpdb->get_results( $querystr ) as $r ) {
						if ( De_Language_Wrapper::get_post_language( $r->ID ) == $de_curlang ) {
							$result = $r;
							break;
						}
					}
				} else {
					$result = $wpdb->get_row( $querystr );
				}

				if ( ! $result ) {
					break;
				}
				
				if ( $i == count( $de_url_array ) - 1 ) {
					if ( get_option( 'de_smart_urls' ) && get_option( 'permalink_structure' ) == '/%postname%/' || de_is_de_archive( $result->ID ) ) {
						return get_post( $result->ID );
					} else {
						break;
					}
				} else {
					$de_post_parent = $result->ID;
				}
				
				$i ++;
			}
		}
		
		// WP slug
		if ( is_singular() ) {
			return $post;
		} else {
			return null;
		}
	}
	
	public static function unique_slug( $de_post_id, $de_slug ) {
		$de_post = get_post( $de_post_id );

		if ( $de_post && $de_slug ) {
			$de_post_parent = get_post_meta( $de_post_id, 'de_post_parent', true );

			$i = 2;
			$s = $de_slug;
			if ( $de_post_parent ) {
				$args = array(
					'post_type' => 'any',
					'post__not_in' => array(
						$de_post_id
					),
					'meta_query' => array (
						array(
							'key' => 'de_slug',
							'value' => $s
						),
						array(
							'key' => 'de_post_parent',
							'value' => $de_post_parent
						)
					)
				);
			} else {
				$args = array(
					'post_type' => 'any',
					'post__not_in' => array(
						$de_post_id
					),
					'meta_query' => array (
						array(
							'key' => 'de_slug',
							'value' => $s
						),
						array(
							'key' => 'de_post_parent',
							'compare' => 'NOT EXISTS'
						)
					)
				);
			}
			$result = get_posts( $args );
			if ( De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $de_post_id ) ) {
				// In multilingual case we need unique slugs for the same language only
				$r = $result;
				$result = array();
				foreach( $r as $value ) {
					if ( De_Language_Wrapper::get_post_language( $de_post_id ) == De_Language_Wrapper::get_post_language( $value->ID ) )
						$result[] = $value;
				}
			}
			while( is_array( $result ) && count( $result ) ) {
				$s = $de_slug . "-$i";
				if ( $de_post_parent ) {
					$args = array(
						'post_type' => 'any',
						'post__not_in' => array(
							$de_post_id
						),
						'meta_query' => array (
							array(
								'key' => 'de_slug',
								'value' => $s
							),
							array(
								'key' => 'de_post_parent',
								'value' => $de_post_parent
							)
						)
					);
				} else {
					$args = array(
						'post_type' => 'any',
						'post__not_in' => array(
							$de_post_id
						),
						'meta_query' => array (
							array(
								'key' => 'de_slug',
								'value' => $s
							),
							array(
								'key' => 'de_post_parent',
								'compare' => 'NOT EXISTS'
							)
						)
					);
				}
				$result = get_posts( $args );
				
				$i ++;
			}
			
			$de_slug = $s;
			
			return $de_slug;
		}
		
		return null;
	}
}
