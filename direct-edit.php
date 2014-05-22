<?php
/*
Plugin Name: Direct Edit
Plugin URI: http://directedit.co/
Description: DirectEdit is the fastest and easiest way to keep your website up-to-date. Edit your website directly in the front-end: after setting up your website you can do all the editing without ever seeing the back-end again. No more flipping back between front-end and back-end to see the result. <strong><a href="http://directedit.co/">Visit the plugin website for more details.</a></strong>
Version: 1.0.4
Author: Carlo Roosen, Elena Mukhina
Author URI: http://carloroosen.com/
*/

define( 'DIRECT_VERSION', '1.0.4' );
define( 'DIRECT_PATH', plugin_dir_path( __FILE__ ) );
define( 'DIRECT_URL', plugin_dir_url( __FILE__ ) );

// Global variables
global $de_global_options;
global $de_current_template;
// $wp_query->queried_object can be overwritten. Let's save it in a global variable.
global $direct_queried_object;

// Autoload De_ classes
spl_autoload_register( 'de_autoload' );
function de_autoload( $class ) {
	if ( strpos( $class, 'De_' ) === 0 ) {
		$fname = DIRECT_PATH . 'pro/core/classes/' . strtolower( str_replace ( '_', '-', $class ) ) . '.php';
		if ( file_exists( $fname ) ) {
			$result = require_once $fname;
			return $result;
		} else {
			$fname = DIRECT_PATH . 'core/classes/' . strtolower( str_replace ( '_', '-', $class ) ) . '.php';
			$result = require_once $fname;
			return $result;
		}
	}
}

// General setup
add_action( 'admin_bar_menu', 'de_adjust_menu', 100 );
add_action( 'de_cron', 'de_images_remove' );
add_action( 'init', 'de_load_global_options' );
add_action( 'init', 'de_session_init', 0 );
add_action( 'plugins_loaded', 'de_extensions_default', 100 );
add_action( 'plugins_loaded', 'de_load_translation_file' );
add_action( 'switch_theme', 'de_on_switch_theme' );
add_action( 'template_include', 'de_define_current_template', 1000 );
add_action( 'template_redirect', 'de_set_de_page', 0 );
add_action( 'template_redirect', 'de_add_de_page', 1 );
add_action( 'wp_head', 'de_hooks', 10 );
add_action( 'wp_enqueue_scripts', 'de_scripts_and_styles' );
add_action( 'wp_print_footer_scripts', 'de_footer_scripts', 10 );

add_filter( 'sanitize_title', 'de_transliterate', 5, 3 );

register_activation_hook( __FILE__, 'de_plugin_setup' );
register_deactivation_hook( __FILE__, 'de_plugin_deactivate' );

require_once ABSPATH . 'wp-admin/includes/plugin.php';
// Core functionality
require_once DIRECT_PATH . 'core/handler.php';
// Service functions
require_once DIRECT_PATH . 'core/service-functions.php';
// PRO functionality
de_pro_include( DIRECT_PATH . 'pro/direct-pro.php' );
// Template functions definition
de_pro_include( DIRECT_PATH . 'pro/core/template-functions.php' );
// Include backend functionality if is_admin()
if ( is_admin() ) {
	de_pro_include( DIRECT_PATH . 'pro/direct-admin.php', DIRECT_PATH . 'direct-admin.php' );
}
// Additional functionality
de_pro_include( DIRECT_PATH . 'pro/direct-webform.php' );

function de_adjust_menu( $wp_admin_bar ) {
	global $current_user;

	// Menu changes are needed to edit only
	if ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_users' ) || current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) ) {
		$wp_admin_bar->remove_menu( 'edit' );

		if ( ! is_admin() ) {
			$all_toolbar_nodes = $wp_admin_bar->get_nodes();
			foreach ( $all_toolbar_nodes as $node ) {
				if ( $node->parent == 'new-content' ) {
					$wp_admin_bar->remove_menu( $node->id );
				}
			}

			if ( current_user_can('edit_posts') || current_user_can( 'edit_de_frontend' ) ) {
				foreach( get_post_types( array( 'show_ui' => true ), 'objects' ) as $postType ) {
					if ( ! in_array( $postType->name, array( 'post', 'page' ) ) && ( in_array( $postType->name, array( 'de_list_item', 'de_webform' ) ) || strpos( $postType->name, 'de_' ) !== 0 ) )
						continue;

					$wp_admin_bar->add_node( array(
							'id' => 'new-' . $postType->name,
							'title' => __( $postType->labels->singular_name, 'direct-edit' ),
							'parent' => 'new-content',
							'href' => add_query_arg( array( 'de_add' => $postType->name ), get_site_url() ),
							'group' => '',
							'meta' => array()
						)
					);
				}
			}
			
			$wp_admin_bar->add_node( array(
					'id' => 'save-page',
					'title' => __( 'Save page', 'direct-edit' ),
					'parent' => '',
					'href' => '#',
					'group' => '',
					'meta' => array( 'title' => __( 'Save page', 'direct-edit' ) )
				)
			);
		}
	}
}

function de_images_remove() {
	global $wpdb;
	
	$images = $wpdb->get_results( "select p.* from $wpdb->posts as p, $wpdb->postmeta as pm where p.ID=pm.post_id and post_type='attachment' and post_mime_type like 'image/%' and UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(post_modified)>86400 and meta_key='_wp_attachment_metadata' and meta_value like '%s:7:\"preview\"%'" );
	foreach( $images as $image ) {
		$data = wp_get_attachment_metadata( $image->ID );
		if ( ! empty( $data[ 'sizes' ][ 'preview' ] ) && empty( $data[ 'sizes' ][ 'public' ] ) ) {
			wp_delete_attachment( $image->ID, false );
		}
	}
}

function de_load_global_options() {
	global $de_global_options;
	
	if ( file_exists( get_stylesheet_directory() . '/direct-edit' ) && file_exists( get_stylesheet_directory() . '/direct-edit/options/direct-options.json' ) ) {
		$de_global_options = json_decode( file_get_contents( get_stylesheet_directory() . '/direct-edit/options/direct-options.json' ), true );
	} else {
		$de_global_options = json_decode( file_get_contents( DIRECT_PATH . 'theme/options/direct-options.json' ), true );
	}
}

function de_session_init() {
	if ( ! session_id() ) {
		session_start();
	}
}

function de_extensions_default() {
	// Include multilanguage extensions
	require_once DIRECT_PATH . 'extensions/multilanguage/de_language-wrapper-default.php';
}

function de_load_translation_file() {
	load_plugin_textdomain( 'direct-edit', '', DIRECT_PATH . 'translations' );
}

function de_on_switch_theme() {
}

function de_define_current_template( $template ) {
	global $de_current_template;

	$de_current_template = str_replace( get_stylesheet_directory() . '/', '', str_replace( get_stylesheet_directory() . DIRECTORY_SEPARATOR, '', $template ) );

	return $template;
}

function de_set_de_page() {
	global $direct_queried_object;
	
	if ( empty( $direct_queried_object ) ) {
		$d = get_queried_object();
		if ( $d instanceof WP_Post ) {
			$direct_queried_object = $d;
		}
	}
}

function de_add_de_page() {
	global $wp_query;
	global $post_type;
	global $post;
	global $wp;
	global $de_current_template;
	global $direct_queried_object;
	
	if ( ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_de_frontend' ) ) ) {
		if ( isset( $_GET[ 'de_add' ] ) ) {
			// "Add new" functionality
			if ( in_array( $_GET[ 'de_add' ], get_post_types( array('show_ui' => true ) ) ) )
				$pt = $_GET[ 'de_add' ];
			else
				wp_die( __( 'Invalid post type', 'direct-edit' ) );
			
			include( ABSPATH . 'wp-admin/includes/post.php' );
			
			$post_type = $pt;
			$post = get_default_post_to_edit( $pt );
			$direct_queried_object = $post;
			$wp_query->posts = array( $post );
			$wp_query->post_count = 1;

			if ( get_option( 'de_options_custom_page_types' ) )
				$options = unserialize( base64_decode( get_option( 'de_options_custom_page_types' ) ) );
			else
				$options = array();

			foreach( $options as $option ) {
				if ( $post_type == 'de_' . sanitize_title( $option->name ) ) {
					if ( is_dir( get_stylesheet_directory() . '/custom/' . sanitize_title( $option->name ) ) && file_exists( get_stylesheet_directory() . '/custom/' . sanitize_title( $option->name ) . '/functions.php' ) ) {
						include get_stylesheet_directory() . '/custom/' . sanitize_title( $option->name ) . '/functions.php';
					}
					if ( is_dir( get_stylesheet_directory() . '/custom/' . sanitize_title( $option->name ) ) && file_exists( get_stylesheet_directory() . '/custom/' . sanitize_title( $option->name ) . '/single.php' ) ) {
						include get_stylesheet_directory() . '/custom/' . sanitize_title( $option->name ) . '/single.php';
						exit;
					}
				}
			}
			if ( file_exists ( get_stylesheet_directory() . '/single-' . $post_type . '.php' ) ) {
				$de_current_template = 'single-' . $post_type . '.php';
				include( get_stylesheet_directory() . '/single-' . $post_type . '.php' );
				exit;
			} elseif ( $post_type == 'page' && file_exists ( get_stylesheet_directory() . '/page.php' ) ) {
				$de_current_template = 'page.php';
				include( get_stylesheet_directory() . '/page.php' );
				exit;
			} elseif( $post_type != 'page' && file_exists ( get_stylesheet_directory() . '/single.php' ) ) {
				$de_current_template = 'single.php';
				include( get_stylesheet_directory() . '/single.php' );
				exit;
			} elseif( file_exists ( get_stylesheet_directory() . '/index.php' ) ) {
				$de_current_template = 'index.php';
				include( get_stylesheet_directory() . '/index.php' );
				exit;
			}
		}
	}
}

function de_hooks() {
	global $direct_queried_object;

	if ( ! is_admin() ) {
		if ( get_option( 'de_options_wp_hooks' ) ) {
			$options_wp_hooks = unserialize( base64_decode( get_option( 'de_options_wp_hooks' ) ) );
		} else {
			$options_wp_hooks = array( 'title' => 1, 'content' => 1, 'excerpt' => 1 );
		}
		if ( get_post_meta( $direct_queried_object->ID, 'de_wp_hooks', true ) ) {
			$de_wp_hooks = unserialize( base64_decode( get_post_meta( $direct_queried_object->ID, 'de_wp_hooks', true ) ) );
		} else {
			$de_wp_hooks = array();
		}
		
		if ( ! empty( $de_wp_hooks[ 'title' ] ) && $de_wp_hooks[ 'title' ] == 1 || empty( $de_wp_hooks[ 'title' ] ) && ! empty( $options_wp_hooks[ 'title' ] ) ) {
			add_filter( 'the_title', 'de_wrap_the_title' );
			// Remove the_title wrap for menu
			//add_filter( 'wp_nav_menu_items', 'de_wrap_the_title_restore' );
			//add_filter( 'wp_nav_menu_objects', 'de_wrap_the_title_remove' );
			//add_filter( 'wp_page_menu', 'de_wrap_the_title_restore' );
			//add_filter( 'wp_page_menu_args', 'de_wrap_the_title_remove' );
		}
		if ( ! empty( $de_wp_hooks[ 'content' ] ) && $de_wp_hooks[ 'content' ] == 1 || empty( $de_wp_hooks[ 'content' ] ) && ! empty( $options_wp_hooks[ 'content' ] ) ) {
			add_filter( 'the_content', 'de_wrap_the_content' );
		}
		if ( ! empty( $de_wp_hooks[ 'excerpt' ] ) && $de_wp_hooks[ 'excerpt' ] == 1 || empty( $de_wp_hooks[ 'excerpt' ] ) && ! empty( $options_wp_hooks[ 'excerpt' ] ) ) {
			add_filter( 'get_the_excerpt', 'de_wrap_the_excerpt' );
			add_filter( 'the_excerpt', 'de_wrap_the_excerpt' );
		}
	}
}

function de_wrap_the_title( $content ) {
	global $post;

	if ( in_the_loop() && ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_de_frontend' ) ) ) {
		$result = '';
		
		$class = 'De_Item_Text';
		try {
			$settings = array(
				'postId' => $post->ID,
				'unwrap' => true
			);
			$item = new $class( 'wptitle', $settings );
			
			$content = $content;

			$result = $item->output( $content );
		} catch ( Exception $e ) {
		}
	} else {
		$result = de_encode_emails( $content );
	}
	
	return $result;
}

function de_wrap_the_content( $content ) {
	global $post;

	if ( in_the_loop() && ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_de_frontend' ) ) ) {
		$result = '';
		
		$class = 'De_Item_Text';
		try {
			$settings = array(
				'postId' => $post->ID,
				'unwrap' => true
			);
			$item = new $class( 'wpcontent', $settings );
			
			$content = $content;

			$result = $item->output( $content );
		} catch ( Exception $e ) {
		}
	} else {
		$result = de_encode_emails( $content );
	}

	return $result;
}

function de_wrap_the_excerpt( $content ) {
	global $post;

	if ( in_the_loop() && ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_de_frontend' ) ) ) {
		$result = '';
		
		$class = 'De_Item_Text';
		try {
			$settings = array(
				'postId' => $post->ID,
				'unwrap' => false
			);
			$item = new $class( 'wpexcerpt', $settings );
			
			$content = $content;

			$result = $item->output( $content );
		} catch ( Exception $e ) {
		}
	} else {
		$result = de_encode_emails( $content );
	}

	return $result;
}

function de_scripts_and_styles() {
	global $direct_queried_object;
	
	if ( ! is_admin() && ( current_user_can('edit_posts') || current_user_can( 'edit_users' ) || current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) ) ) {
		if ( file_exists( get_stylesheet_directory() . '/direct-edit' ) && file_exists( get_stylesheet_directory() . '/direct-edit/css/direct-edit.css' ) ) {
			wp_enqueue_style( 'directEdit', get_stylesheet_directory_uri() . '/direct-edit/css/direct-edit.css' );
		} else {
			wp_enqueue_style( 'directEdit', DIRECT_URL . 'theme/css/direct-edit.css' );
		}
		if ( file_exists( get_stylesheet_directory() . '/direct-edit' ) && file_exists( get_stylesheet_directory() . '/direct-edit/css/jquery-ui-1.8.16.custom.css' ) ) {
			wp_enqueue_style( 'jquery-ui-custom', get_stylesheet_directory_uri() . '/direct-edit/css/jquery-ui-1.8.16.custom.css' );
		} else {
			wp_enqueue_style( 'jquery-ui-custom', DIRECT_URL . 'theme/css/jquery-ui-1.8.16.custom.css' );
		}
	}
	
	wp_enqueue_script( 'jquery' );

	if ( ! is_admin() && ( current_user_can('edit_posts') || current_user_can( 'edit_users' ) || current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) ) ) {
		$locale = get_locale();
		if ( empty( $locale ) ) {
			$locale = 'en_US';
		}

		wp_enqueue_script( 'jquery-ui-core' );
		wp_enqueue_script( 'jquery-ui-widget' );
		wp_enqueue_script( 'jquery-ui-mouse' );
		wp_enqueue_script( 'jquery-ui-slider' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'jquery-ui-draggable' );
		wp_enqueue_script( 'jquery-ui-droppable' );
		wp_enqueue_script( 'jquery-ui-resizable' );
		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-ui-button' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-accordion' );
		if ( file_exists( get_stylesheet_directory() . '/direct-edit/js/jquery.ui.datepicker-' . substr( $locale, 0, 2 ) . '.js' ) ) {
			wp_enqueue_script( 'jquery-ui-datepicker-' . $locale, get_stylesheet_directory_uri() . '/direct-edit/js/jquery.ui.datepicker-' . substr( $locale, 0, 2 ) . '.js', array( 'jquery', 'jquery-ui-datepicker' ) );
		} elseif ( file_exists( DIRECT_PATH . 'theme/js/jquery.ui.datepicker-' . substr( $locale, 0, 2 ) . '.js' ) ) {
			wp_enqueue_script( 'jquery-ui-datepicker-' . $locale, DIRECT_URL . 'theme/js/jquery.ui.datepicker-' . substr( $locale, 0, 2 ) . '.js', array( 'jquery', 'jquery-ui-datepicker' ) );
		}
		if ( file_exists( DIRECT_PATH . 'js/direct-edit-min.js' ) ) {
			wp_enqueue_script( 'direct-edit', DIRECT_URL . 'js/direct-edit-min.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
		} else {
			wp_enqueue_script( 'direct-text-editor', DIRECT_URL . 'js/direct-text-editor.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
			wp_enqueue_script( 'direct-image-editor', DIRECT_URL . 'js/direct-image-editor.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
			wp_enqueue_script( 'direct-file-uploader', DIRECT_URL . 'js/direct-file-uploader.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
			wp_enqueue_script( 'direct-link-editor', DIRECT_URL . 'js/direct-link-editor.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
			wp_enqueue_script( 'direct-list-editor', DIRECT_URL . 'js/direct-list-editor.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
			wp_enqueue_script( 'direct-date-editor', DIRECT_URL . 'js/direct-date-editor.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
			if ( ( current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) ) && is_object( $direct_queried_object ) && isset( $direct_queried_object->ID ) && get_option( 'de_menu_editor_enabled' ) && get_option( 'de_edit_menu_page' ) == $direct_queried_object->ID ) {
				wp_enqueue_script( 'direct-menu-editor', DIRECT_URL . 'js/direct-menu-editor.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
			}
			wp_enqueue_script( 'direct-edit', DIRECT_URL . 'js/direct-edit.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
		}
		if ( file_exists( get_stylesheet_directory() . '/direct-edit' ) && file_exists( get_stylesheet_directory() . '/direct-edit/js/direct-edit-custom.js' ) ) {
			wp_enqueue_script( 'direct-edit-custom', get_stylesheet_directory_uri() . '/direct-edit/js/direct-edit-custom.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
		} else {
			wp_enqueue_script( 'direct-edit-custom', DIRECT_URL . 'theme/js/direct-edit-custom.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
		}
		if ( file_exists( get_stylesheet_directory() . '/direct-edit' ) && file_exists( get_stylesheet_directory() . '/direct-edit/js/direct-translations-' . $locale . '.js' ) ) {
			wp_enqueue_script( 'direct-translations', get_stylesheet_directory_uri() . '/direct-edit/js/direct-translations-' . $locale . '.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
		} elseif( file_exists( DIRECT_PATH . 'theme/js/direct-translations-' . $locale . '.js' ) ) {
			wp_enqueue_script( 'direct-translations', DIRECT_URL . 'theme/js/direct-translations-' . $locale . '.js', array( 'jquery', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-slider', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-dialog', 'jquery-ui-button' ), DIRECT_VERSION, true );
		}
	}
}

function de_footer_scripts() {
	global $de_global_options;
	global $direct_queried_object;
	
	if ( ! is_admin() && ( current_user_can('edit_posts') || current_user_can( 'edit_users' ) || current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) ) ) {
	?>
<script>
	jQuery(document).ready(function() {
		directEdit(<?php echo de_json_encode( $de_global_options ); ?>);
		<?php if ( ! is_admin() && ( current_user_can('edit_posts') || current_user_can( 'edit_users' ) || current_user_can( 'edit_theme_options' ) || current_user_can( 'edit_de_frontend' ) ) ) { // Save button is only for users who can edit posts ?>
			jQuery('li#wp-admin-bar-save-page a').directSaveButton({ajaxUrl: '<?php echo admin_url( 'admin-ajax.php' ); ?>', success: function (m) {console.log(m)}});
		<?php } ?>
	});
</script>
	<?php
	}
}

function de_transliterate( $title, $raw_title = NULL, $context = 'query' ) {
	// Undo remove_accents
	if ($raw_title != NULL) {
		$title = $raw_title;
	}

	// Replace German symbols
	$title = str_replace( 'Ä', 'ae', $title );
	$title = str_replace( 'ä', 'ae', $title );
	$title = str_replace( 'Ö', 'oe', $title );
	$title = str_replace( 'ö', 'oe', $title );
	$title = str_replace( 'Ü', 'ue', $title );
	$title = str_replace( 'ü', 'ue', $title );
	$title = str_replace( 'β', 'ss', $title );
	$title = str_replace( 'ß', 'ss', $title );

	// Redo remove_accents
	if ($context == 'save') {
		$title = remove_accents($title);
	}

	return $title;
}

function de_plugin_setup() {
	wp_schedule_event( time(), 'daily', 'de_cron' );
}

function de_plugin_deactivate() {
	// Remove all possible dE capabilities
	$admin = get_role( 'administrator' );
	if ( is_object( $admin ) && user_can( $admin->ID, 'edit_de_frontend' ) ) {
		$admin->remove_cap( 'edit_de_frontend' );
	}
		if ( is_object( $admin ) && user_can( $admin->ID, 'edit_de_webform' ) ) {
		$admin->remove_cap( 'edit_de_webform' );
	}
	if ( is_object( $admin ) && user_can( $admin->ID, 'delete_de_webform' ) ) {
		$admin->remove_cap( 'delete_de_webform' );
	}
	
	$editor = get_role( 'editor' );
	if ( is_object( $editor ) && user_can( $editor->ID, 'edit_de_frontend' ) ) {
		$editor->remove_cap( 'edit_de_frontend', true );
	}
	if ( is_object( $editor ) && user_can( $editor->ID, 'edit_themes' ) ) {
		$editor->remove_cap( 'edit_themes', true );
	}
	if ( is_object( $editor ) && user_can( $editor->ID, 'edit_theme_options' ) ) {
		$editor->remove_cap( 'edit_theme_options', true );
	}
}


function de_filter_permalink( $permalink, $post ) {
	$id = ( is_object( $post ) ? $post->ID : $post );

	return De_Url::get_url( $id );
}
