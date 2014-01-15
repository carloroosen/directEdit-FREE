<?php
class De_Item {
	public $reference;
	public $store;
	public $settings;
	
	public static $local_options_keys = array(
		'type',
		'format',
		'placeholder',
		'buttons',
		'buttonOptions',
		'imageEditor',
		'fileManager',
		'toolbarContainer',
		'toolbarDock',
		'toolbarDockMargin',
		'docked',
		'formatRules',
		'constraints',
		'styles',
		'showButtons',
		'callback',
		'divClasses',
		'listSelector',
		'commands',
		'buttonDelete',
		'buttonShow',
		'buttonHide',
		'buttonFollowLink',
		'buttonEditLink',
		'alwaysSave',
		'unwrap',
		'sourceMaxResize',
		'sourceMinWidth',
		'sourceMinHeight',
		'validate'
	);
	
	public function __construct( $store, $settings ) {
		global $de_global_options;
		global $post_type;
		global $post;
		global $de_snippet_list;
		
		$this->store = strtolower( $store );
		$this->settings = $settings;
		
		// Check that all params are correct. If not, the item is not editable
		if ( in_array( $this->store, array( 'wptitle', 'wpcontent', 'wpexcerpt' ) ) && ! $this instanceof De_Item_Text ) {
			$this->settings[ 'disableEdit' ] = true;
		}

		// Check that the defined global option exists
		if ( ! empty( $this->settings[ 'options' ] ) && empty( $de_global_options[ "{$this->settings[ 'options' ]}" ] ) ) {
			unset( $this->settings[ 'options' ] );
		}
		
		// Some default settings
		if ( in_array( $this->store, array( 'postmeta', 'wptitle', 'wpcontent', 'wpexcerpt' ) ) ) {
			if ( empty( $this->settings[ 'postId' ] ) && empty( $this->settings[ 'postType' ] ) ) {
				if ( $de_snippet_list->in_the_loop ) {
					$this->settings[ 'postType' ] = $de_snippet_list->item_type;
				} else {
					$this->settings[ 'postType' ] = $post_type;
					$this->settings[ 'redirect' ] = true;
				}
			}
		}
		
		if ( De_Store::is_editable( $this ) ) {
			De_Items::add( $this );
		}
	}
	
	public function update() {
		De_Items::update( $this );
	}

	public function get_setting( $key ) {
		global $de_global_options;

		if ( is_array( $this->settings ) && ! empty( $this->settings[ $key ] ) ) {
			if( ! empty( $this->settings[ 'options' ] ) && ! empty( $de_global_options[ $this->settings[ 'options' ] ][ $key ] ) ) {
				return array_replace_recursive( ( array ) $de_global_options[ $this->settings[ 'options' ] ][ $key ], ( array ) $this->settings[ $key ] );
			} else {
				return $this->settings[ $key ];
			}
		} elseif( ! empty( $this->settings[ 'options' ] ) && ! empty( $de_global_options[ $this->settings[ 'options' ] ][ $key ] ) ) {
			return $de_global_options[ $this->settings[ 'options' ] ][ $key ];
		} else
			return null;
	}
	
	public function set_setting( $key, $value ) {
		$this->settings[ $key ] = $value;
		$this->update();
	}
	
	public function delete_setting( $key ) {
		unset( $this->settings[ $key ] );
		$this->update();
	}

	public function output( $content = null ) {
	}
	
	public function output_partial( $content = null ) {
	}

	public function output_partial_image( $id, $mode = 'public' ) {
		global $de_global_options;
		global $direct_image;
		global $de_snippet_image;
		
		$content = '';
		
		$direct_image = array();
		if ( $id ) {
			$data = wp_get_attachment_metadata( $id );
		}
		
		if( $this->get_setting( 'default' ) && empty( $data ) ) {
			$s = $this->get_setting( 'default' );
			if ( is_array( $s ) ) {
				if( isset( $s[ 'alt' ] ) )
					$s[ 'alt' ] = $s[ 'alt' ];
				if( isset( $s[ 'src' ] ) )
					$content = $s[ 'src' ];
			} else {
				$content = $s;
			}
		}
		if ( is_array( $this->get_setting( 'attr' ) ) ) {
			$direct_image = $this->get_setting( 'attr' );
		}

		if ( ! empty( $data ) ) {
			$wp_upload_dir = wp_upload_dir();
			$file = $data[ 'file' ];
			$info = pathinfo( $file );
			$dir = $info['dirname'];
			if ( $this->get_setting( 'useCopy' ) && ! empty( $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ] ) ) {
				$content = $wp_upload_dir[ 'baseurl' ] . '/' . $dir . '/' . $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ][ 'file' ];
			} else {
				$content = $wp_upload_dir[ 'baseurl' ] . '/' . $dir . '/' . $data[ 'sizes' ][ $mode ][ 'file' ];
			}
			if ( De_Store::is_editable( $this ) )
				$content .= '?v=' . time();
			
			$direct_image[ 'alt' ] = $data[ 'sizes' ][ $mode ][ 'alt' ];
		}
		
		if ( De_Store::is_editable( $this ) ) {
			// Data reference and id are needed for standalone images only
			if( $this instanceof De_Item_Image ) {
				$direct_image[ 'data-reference' ] = $this->reference;
				if ( empty( $direct_image[ 'id' ] ) )
					$direct_image[ 'id' ] = $this->reference;
			}
			
			// Difference between standalone and inline images
			// Inline images are inserted into rich text, so they should not get item settings. But they should get proper css class
			// Image options are stored in 'buttonOptions' 'image' item for inline images, and in root for standalone images
			if ( $this instanceof De_Item_Text ) {
				$direct_image[ 'class' ] = ( isset( $direct_image[ 'class' ] ) ? $direct_image[ 'class' ] . ' direct-image-inline' : 'direct-image-inline' );
				
				$buttonOptions = $this->get_setting( 'buttonOptions' );
				$imageOptions = $buttonOptions[ 'image' ];
			} else {
				$direct_image[ 'class' ] = ( isset( $direct_image[ 'class' ] ) ? $direct_image[ 'class' ] . ' direct-editable' : 'direct-editable' );
				$direct_image[ 'data-global-options' ] = $this->get_setting( 'options' );
				$direct_image[ 'data-local-options' ] = $this->build_local_options();
				
				$imageOptions = array(
					'imgHasRelativeScale' => $this->get_setting( 'imgHasRelativeScale' ),
					'imgWidth100' => $this->get_setting( 'imgWidth100' ),
					'imgFileFormat' => $this->get_setting( 'imgFileFormat' ),
					'imgQuality' => $this->get_setting( 'imgQuality' ),
					'copies' => $this->get_setting( 'copies' ),
					'styles' => $this->get_setting( 'styles' )
				);
			}
			
			if ( ! empty( $data ) ) {
				$direct_image[ 'data-image' ] = $id;

				if( ! empty( $data[ 'sizes' ][ $mode ][ 'style' ] ) ) {
					$s = $data[ 'sizes' ][ $mode ][ 'style' ];
					
					// Both inline and standalone images get proper class
					if ( ! empty( $imageOptions[ 'styles' ][ $s ][ 'class' ] ) ) {
						$direct_image[ 'class' ] .= ' ' . $imageOptions[ 'styles' ][ $s ][ 'class' ];
					}
					
					// Merge image options with style options if exist
					if ( ! empty( $imageOptions[ 'styles' ][ $s ][ 'imgHasRelativeScale' ] ) ) {
						$imageOptions[ 'imgHasRelativeScale' ] = $imageOptions[ 'styles' ][ $s ][ 'imgHasRelativeScale' ];
					}
					if ( ! empty( $imageOptions[ 'styles' ][ $s ][ 'imgWidth100' ] ) ) {
						$imageOptions[ 'imgWidth100' ] = $imageOptions[ 'styles' ][ $s ][ 'imgWidth100' ];
					}
					if ( ! empty( $imageOptions[ 'styles' ][ $s ][ 'imgFileFormat' ] ) ) {
						$imageOptions[ 'imgFileFormat' ] = $imageOptions[ 'styles' ][ $s ][ 'imgFileFormat' ];
					}
					if ( ! empty( $imageOptions[ 'styles' ][ $s ][ 'imgQuality' ] ) ) {
						$imageOptions[ 'imgQuality' ] = $imageOptions[ 'styles' ][ $s ][ 'imgQuality' ];
					}
				}
				
				// Merge image options with copy options for copy ( if exist )
				if ( $this->get_setting( 'useCopy' ) && ! empty( $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ] ) ) {
					if ( ! empty( $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ][ 'imgHasRelativeScale' ] ) ) {
						$imageOptions[ 'imgHasRelativeScale' ] = $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ][ 'imgHasRelativeScale' ];
					}
					if ( ! empty( $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ][ 'imgWidth100' ] ) ) {
						$imageOptions[ 'imgWidth100' ] = $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ][ 'imgWidth100' ];
					}
					if ( ! empty( $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ][ 'imgFileFormat' ] ) ) {
						$imageOptions[ 'imgFileFormat' ] = $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ][ 'imgFileFormat' ];
					}
					if ( ! empty( $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ][ 'imgQuality' ] ) ) {
						$imageOptions[ 'imgQuality' ] = $data[ 'sizes' ][ $mode ][ 'copies' ][ $this->get_setting( 'useCopy' ) ][ 'imgQuality' ];
					}
				}
				
				if ( ! empty( $imageOptions[ 'imgHasRelativeScale' ] ) && ! empty( $imageOptions[ 'imgWidth100' ] ) && intval( $imageOptions[ 'imgWidth100' ] ) ) {
					$width = round( $data[ 'sizes' ][ $mode ][ 'container-width' ] / $imageOptions[ 'imgWidth100' ] * 100 );
					$direct_image[ 'style' ] = ( isset( $direct_image[ 'style' ] ) ? $direct_image[ 'style' ] . '; width: ' . $width . '%;' : 'width: ' . $width . '%;' );
				}

				$direct_image[ 'data-source' ] = wp_get_attachment_url( $id );
				$direct_image[ 'data-containerW' ] = $data[ 'sizes' ][ $mode ][ 'container-width' ];
				$direct_image[ 'data-containerH' ] = $data[ 'sizes' ][ $mode ][ 'container-height' ];
				$direct_image[ 'data-left' ] = $data[ 'sizes' ][ $mode ][ 'left' ];
				$direct_image[ 'data-top' ] = $data[ 'sizes' ][ $mode ][ 'top' ];
				$direct_image[ 'data-imageScaledW' ] = $data[ 'sizes' ][ $mode ][ 'width' ];
				$direct_image[ 'data-imageScaledH' ] = $data[ 'sizes' ][ $mode ][ 'height' ];
				$direct_image[ 'data-style' ] = $data[ 'sizes' ][ $mode ][ 'style' ];
			}
			
			if ( $mode != 'public' ) {
				$direct_image[ 'data-status' ] = $mode;
			}
		}
		
		$direct_image[ 'src' ] = $content;
		
		if ( $this->get_setting( 'snippet' ) ) {
			$de_snippet_image->mode = $mode;
			$result = $de_snippet_image->snippet( $this, $this->get_setting( 'snippet' ) );
			$de_snippet_image->mode = 'public';
		} else {
			$result = '<img ' . self::attr_to_string( $direct_image ) . ' />';
		}
		
		$direct_image = null;
		
		return $result;
	}

	public static function attr_to_string( $attr ) {
		$a = array();
		
		if ( is_array( $attr ) ) {
			foreach( $attr as $k => $v ) {
				// Print arrays as json
				if ( is_array( $v ) ) {
					// Print json with functions for options array only
					if ( $k == 'options' )
						$v = htmlspecialchars( de_json_encode( $v ), ENT_QUOTES, 'UTF-8' );
					else
						$v = htmlspecialchars( json_encode( $v ), ENT_QUOTES, 'UTF-8' );
				}

				$a[] = $k . '="' . $v . '"';
			}
		}
		
		return ( strlen( implode( ' ', $a ) ) > 0 ? ' ' . implode( ' ', $a ) : '' );
	}
	
	public function build_local_options() {
		$result = array();
		
		foreach( $this->settings as $key => $value ) {
			if ( in_array( $key, self::$local_options_keys ) )
				$result[ $key ] = $value;
		}
		
		return $result;
	} 
}
