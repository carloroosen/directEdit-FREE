<?php
// Print functions without double quotes in json
function de_json_encode( $options ) {
	array_walk_recursive( $options, 'de_mark_json_functions' );
	$json = json_encode( $options );
	$json = str_replace( array( '"[{%', '%}]"' ), '', $json );
	
	return $json;
}

function de_mark_json_functions( &$value, $key ) {
	if( is_string( $value ) && ( strpos( $value, 'function(' ) === 0 || strpos( $value, 'function (' ) === 0 ) ) {
			$value = '[{%' . $value . '%}]';
	}
}

// Our conditional functions
function de_is_front_page( $post_id ) {
	if ( get_option( 'show_on_front' ) == 'page' && get_option( 'page_on_front' ) && ( get_option( 'page_on_front' ) == $post_id || de_is_language_post( $post_id, get_option( 'page_on_front' ) ) ) ) {
		// Page on front or Language page on front
		return true;
	} else {
		return false;
	}
}

function de_is_home( $post_id ) {
	if ( get_option( 'show_on_front' ) == 'page' && get_option( 'page_for_posts' ) && ( get_option( 'page_for_posts' ) == $post_id || de_is_language_post( $post_id, get_option( 'page_for_posts' ) ) ) ) {
		// Page for posts or Language page for posts
		return true;
	} else {
		return false;
	}
}

// dE custom post type archive page
function de_is_de_archive( $post_id ) {
	foreach( get_post_types( array( 'show_ui' => true ), 'objects' ) as $post_type ) {
		if ( get_option( 'de_page_for_' . $post_type->name ) == $post_id || de_is_language_post( $post_id, get_option( 'de_page_for_' . $post_type->name ) ) ) {
			return true;
		}
	}
	
	return false;
}

// Return true if $lang_post_id is a translation of $post_id
function de_is_language_post( $lang_post_id, $post_id ) {
	if ( De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $lang_post_id ) && De_Language_Wrapper::get_post_language( $post_id ) && De_Language_Wrapper::get_language_post( $lang_post_id, De_Language_Wrapper::get_default_language() )->ID == De_Language_Wrapper::get_language_post( $post_id, De_Language_Wrapper::get_default_language() )->ID ) {
		return true;
	} else {
		return false;
	}
}

// Return true for hidden posts
function de_is_hidden( $post_id ) {
	return ( get_post_status( $post_id ) == 'draft' );
}

// Return true if the current user can see a post ( editor can see hidden posts )
function de_is_accessible( $post_id ) {
	return ( ! get_post_status( $post_id ) == 'draft' || current_user_can( 'edit_post', $post_id ) || current_user_can( 'edit_de_frontend' ) );
}

// Can the page be removed?
function de_is_deleteable( $post_id ) {
	// No post
	if( ! $post_id || ! get_post( $post_id ) ) {
		return false;
	}
	
	// We can't delete front page, blog archive and dE custom types archives
	if ( de_is_front_page( $post_id ) || de_is_home( $post_id ) || de_is_de_archive( $post_id ) || ( get_option( 'de_wp_login_redirect' ) && get_option( 'de_login_form' ) == $post_id ) ) {
		return false;
	}
	
	return true;
}

// Can we hide this page?
function de_is_hideable( $post_id ) {
	// No post
	if( ! $post_id || ! get_post( $post_id ) ) {
		return false;
	}

	// We can't delete front page and blog archive
	if ( de_is_front_page( $post_id ) || de_is_home( $post_id ) ) {
		return false;
	}

	return true;
}

function de_wrap_the_title_restore ( $result = null ) {
	add_filter( 'the_title', 'de_wrap_the_title' );
	
	return $result;
}

function de_wrap_the_title_remove ( $result = null ) {
	remove_filter( 'the_title', 'de_wrap_the_title' );
	
	return $result;
}

// Recursively copy some folder
function de_copy( $source, $target ) {
	global $wp_filesystem;
	
	if ( $wp_filesystem->is_dir( $source ) ) {
		$wp_filesystem->mkdir( $target );
		
		$d = $wp_filesystem->dirlist( $source, true, false );
		foreach( $d as $entry ) {
			if ( $entry['name'] == '.' || $entry['name'] == '..' )
				continue;
			de_copy( implode( DIRECTORY_SEPARATOR, array( $source, $entry['name'] ) ), implode( DIRECTORY_SEPARATOR, array( $target, $entry['name'] ) ) );
		}
	} else {
		$wp_filesystem->copy( $source, $target );
	}
	
	return true;
}

// Delete directory with files in it
function de_rmdir( $source ) {
	global $wp_filesystem;
	
	if ( $wp_filesystem->is_dir( $source ) ) {
		$wp_filesystem->rmdir( $source, true );
	}
}

// Return the current template filename
function de_get_current_template() {
	global $de_current_template;
	
	return $de_current_template;
}

function de_get_login_form_permalink() {
	if( get_option( 'de_wp_login_redirect' ) ) {
		$id = get_option( 'de_login_form' );
		if ( file_exists( get_stylesheet_directory() . '/de_webform/log-in.php' ) && $id && De_Language_Wrapper::has_multilanguage() && De_Language_Wrapper::get_post_language( $id ) && De_Language_Wrapper::get_language_post( $id, De_Language_Wrapper::get_current_language() ) ) {
			return get_permalink( De_Language_Wrapper::get_language_post( $id, De_Language_Wrapper::get_current_language() )->ID );
		} elseif ( file_exists( get_stylesheet_directory() . '/de_webform/log-in.php' ) && $id && get_permalink( $id ) ) {
			return get_permalink( $id );
		} else {
			return add_query_arg( 'redirect_to', urlencode( home_url() ), home_url( 'wp-login.php' ) );
		}
	} else {
		return add_query_arg( 'redirect_to', urlencode( home_url() ), home_url( 'wp-login.php' ) );
	}
}

// Email encoding
function de_encode_emails( $string ) {
	// abort if $string doesn't contain a @-sign
	if ( strpos( $string, '@' ) === false )
		return $string;

	$regexp = '{
		(?:mailto:)?
		(?:
			[-!#$%&*+/=?^_`.{|}~\w\x80-\xFF]+
		|
			".*?"
		)
		\@
		(?:
			[-a-z0-9\x80-\xFF]+(\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
		|
			\[[\d.a-fA-F:]+\]
		)
	}xi';

	return preg_replace_callback( $regexp, 'de_encode_matches', $string );
}

function de_encode_matches( $matches ) {
	$string = $matches[ 0 ];
	$chars = str_split( $string );
	$seed = mt_rand( 0, ( int ) abs( crc32( $string ) / strlen( $string ) ) );

	foreach ( $chars as $key => $char ) {
		$ord = ord( $char );

		if ( $ord < 128 ) { // ignore non-ascii chars
			$r = ( $seed * ( 1 + $key ) ) % 100; // pseudo "random function"

			if ( $r > 60 && $char != '@' ) {
				// plain character (not encoded), if not @-sign
			} elseif ( $r < 45 ) {
				// hexadecimal
				$chars[ $key ] = '&#x' . dechex( $ord ) . ';';
			} else {
				// decimal ( ascii )
				$chars[ $key ] = '&#' . $ord . ';';
			}
		}
	}

	return implode( '', $chars );
}

function de_pro_include( $path_pro, $path_free = null ) {
	if ( file_exists( DIRECT_PATH . 'pro' ) && file_exists( $path_pro ) ) {
		require_once $path_pro;
	} elseif( $path_free ) {
		require_once $path_free;
	}
}

function de_print_json($json) {
	$result      = '';
	$pos         = 0;
	$strLen      = strlen($json);
	$indentStr   = '  ';
	$newLine     = "\n";
	$prevChar    = '';
	$outOfQuotes = true;

	for ($i=0; $i<=$strLen; $i++) {
		// Grab the next character in the string.
		$char = substr($json, $i, 1);

		// Are we inside a quoted string?
		if ($char == '"' && $prevChar != '\\') {
			$outOfQuotes = !$outOfQuotes;

		// If this character is the end of an element,
		// output a new line and indent the next line.
		} elseif(($char == '}' || $char == ']') && $outOfQuotes) {
			$result .= $newLine;
			$pos --;
			for ($j=0; $j<$pos; $j++) {
				$result .= $indentStr;
			}
		}

		// Add the character to the result string.
		$result .= $char;

		// If the last character was the beginning of an element,
		// output a new line and indent the next line.
		if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
			$result .= $newLine;
			if ($char == '{' || $char == '[') {
				$pos ++;
			}

			for ($j = 0; $j < $pos; $j++) {
				$result .= $indentStr;
			}
		}

		$prevChar = $char;
	}

	return $result;
}

function de_unique_imagename( $dir, $name, $ext ) {
	$number = '';

	$name2 = $name;
	$ext2 = strtolower( $ext );
	while ( file_exists( $dir . "/$name2$ext" ) || file_exists( $dir . "/$name2$ext2" ) || file_exists( $dir . "/$name2.png" ) || file_exists( $dir . "/$name2.jpg" ) || file_exists( $dir . "/$name2.jpeg" ) || file_exists( $dir . "/$name2.gif" ) ) {
		$name2 = $name . ++$number;
	}

	return $name2 . $ext2;
}

function de_datepicker_to_php( $date_string ) {
	$pattern = array( 'dd', 'd', 'DD', 'o', 'MM', 'M', 'm', 'mm', 'yy','y' );
	$replace = array( 'd' , 'j', 'l' , 'z', 'F' , 'M', 'n', 'm' , 'Y', 'y' );
	foreach( $pattern as &$p ) {
		$p = '/'.$p.'/';
	}
	
	return preg_replace( $pattern, $replace, $date_string );
}

if ( ! function_exists( 'array_replace_recursive' ) ) {
	function array_replace_recursive() {
		// Get array arguments
		$arrays = func_get_args();

		// Define the original array
		$original = array_shift( $arrays );

		// Loop through arrays
		foreach ( $arrays as $array ) {
			// Loop through array key/value pairs
			foreach ( $array as $key => $value ) {
				// Value is an array
				if ( is_array( $value ) ) {
					// Traverse the array; replace or add result to original array
					$original[ $key ] = array_replace_recursive( $original[ $key ], $array[ $key ] );
				}

				// Value is not an array
				else {
					// Replace or add current value to original array
					$original[ $key ] = $value;
				}
			}
		}

		// Return the joined array
		return $original;
	}
}

// function from Ryan Rud (http://adryrun.com)
function de_find_sharp( $orig, $final ) {
    $final    = $final * (750.0 / $orig);
    $a        = 52;
    $b        = -0.27810650887573124;
    $c        = .00047337278106508946;

    $result = $a + $b * $final + $c * $final * $final;

    return max(round($result), 0);
}

if ( ! function_exists( 'array_replace' ) ) {
	function array_replace(){
		$array=array();
		
		$n=func_num_args();
		while ( $n-- >0 ) {
			$array+=func_get_arg( $n );
		}
		
		return $array;
	}
} 
