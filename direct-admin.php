<?php
add_action( 'add_meta_boxes', 'de_metaboxes_add', 0 );
add_action( 'admin_menu', 'de_plugin_menu' );
add_action( 'save_post','de_metaboxes_save', 1000, 2 );

function de_metaboxes_add() {
	global $post;

	if ( $post && $post->ID ) {
		add_meta_box( 'deWpHooks', __( 'Hooks on standard wp-functions ', 'direct-edit' ), 'de_wp_hooks_meta_box', $post->post_type, 'normal', 'core' );
	}
}

function de_wp_hooks_meta_box( $post ) {
	$postId = $post->ID;
	
	if ( get_post_meta( $postId, 'de_wp_hooks', true ) ) {
		$de_wp_hooks = unserialize( base64_decode( get_post_meta( $postId, 'de_wp_hooks', true ) ) );
	} else {
		$de_wp_hooks = array();
	}
	
	echo '<fieldset>';
	echo '<label for="de_wp_hooks_title">' . __( 'Title', 'direct-edit' ) . '</label>';
	echo '<br />';
	echo '<select id="de_wp_hooks_title" name="de_wp_hooks[title]">';
	echo '<option value=""' . ( empty( $de_wp_hooks[ 'title' ] ) ? ' selected="selected"' : '' ) . '>Use default settings</option>';
	echo '<option value="-1"' . ( $de_wp_hooks[ 'title' ] == -1 ? ' selected="selected"' : '' ) . '>Disable</option>';
	echo '<option value="1"' . ( $de_wp_hooks[ 'title' ] == 1 ? ' selected="selected"' : '' ) . '>Enable</option>';
	echo '</select>';
	echo '<br />';
	echo '<label for="de_wp_hooks_content">' . __( 'Content', 'direct-edit' ) . '</label>';
	echo '<br />';
	echo '<select id="de_wp_hooks_content" name="de_wp_hooks[content]">';
	echo '<option value=""' . ( empty( $de_wp_hooks[ 'content' ] ) ? ' selected="selected"' : '' ) . '>Use default settings</option>';
	echo '<option value="-1"' . ( $de_wp_hooks[ 'content' ] == -1 ? ' selected="selected"' : '' ) . '>Disable</option>';
	echo '<option value="1"' . ( $de_wp_hooks[ 'content' ] == 1 ? ' selected="selected"' : '' ) . '>Enable</option>';
	echo '</select>';
	echo '<br />';
	echo '<label for="de_wp_hooks_excerpt">' . __( 'Excerpt', 'direct-edit' ) . '</label>';
	echo '<br />';
	echo '<select id="de_wp_hooks_excerpt" name="de_wp_hooks[excerpt]">';
	echo '<option value=""' . ( empty( $de_wp_hooks[ 'excerpt' ] ) ? ' selected="selected"' : '' ) . '>Use default settings</option>';
	echo '<option value="-1"' . ( $de_wp_hooks[ 'excerpt' ] == -1 ? ' selected="selected"' : '' ) . '>Disable</option>';
	echo '<option value="1"' . ( $de_wp_hooks[ 'excerpt' ] == 1 ? ' selected="selected"' : '' ) . '>Enable</option>';
	echo '</select>';
	echo '</fieldset>';
}

function de_plugin_menu() {
	add_options_page( __( 'Direct Edit Options', 'direct-edit' ), __( 'Direct Edit', 'direct-edit' ), 'manage_options', 'direct-edit', 'de_plugin_page' );
}

function de_plugin_page() {
	global $wpdb;
	global $options;
	global $user_ID;
	
	// Check permissions
	if ( ! current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'direct-edit' ) );
	}

	// Save options
	if ( isset( $_REQUEST['action'] ) ) {
		if ( 'upgrade' == $_REQUEST['action'] ) {
			check_admin_referer( 'de_nonce_upgrade', '_de_nonce' );
			
			$upgrade_url = 'http://directedit.co/downloads/direct-edit-upgrade.zip?key=' . urlencode( sanitize_text_field( $_POST[ 'upgrade_key' ] ) ) . '&url=' . urlencode( get_option( 'siteurl' ) ) . '&activity=upgrade';
			$upgrade_path = download_url( $upgrade_url );
			if ( is_wp_error( $upgrade_path ) ) {
				add_settings_error( 'direct-edit', 'de-error', __( 'Upgrade error.', 'direct-edit' ) );
			} else {
				if ( $upgrade_path ) {
					$url = wp_nonce_url( basename( $_SERVER['PHP_SELF'] ) . '?page=direct-edit', 'de_nonce_copy_files', '_de_nonce' );
					if ( false === ( $creds = request_filesystem_credentials( $url, '', false, get_stylesheet_directory(), array( 'action' => 'upgrade', 'upgrade_key' => sanitize_text_field( $_POST[ 'upgrade_key' ] ) ) ) ) ) {
						return;
					}
					if ( ! WP_Filesystem( $creds ) ) {
						request_filesystem_credentials( $url, '', true, get_stylesheet_directory(), array( 'action' => 'copy_files' ) );
						return;
					}

					global $wp_filesystem;
					$plugin_path = str_replace( ABSPATH, $wp_filesystem->abspath(), DIRECT_PATH );

					$result = unzip_file( $upgrade_path, $plugin_path );
					unlink( $upgrade_path );
					
					if ( is_wp_error( $result ) ) {
						add_settings_error( 'direct-edit', 'de-error', __( 'Upgrade error.', 'direct-edit' ) );
					} else {
						/* Add PRO options to json options file
						 * It seems it's not needed for now
						
						if ( file_exists( get_stylesheet_directory() . '/direct-edit' ) && file_exists( get_stylesheet_directory() . '/direct-edit/options/direct-options.json' ) ) {
							$options = json_decode( file_get_contents( get_stylesheet_directory_uri() . '/direct-edit/options/direct-options.json' ), true );
							$options_upgrade = json_decode( file_get_contents( DIRECT_PATH . 'pro/upgrade/theme/options/direct-options.json' ), true );
							
							$changed = false;
							foreach( $options_upgrade as $key_upgrade => $option_upgrade ) {
								if ( empty( $options[ $key_upgrade ] ) ) {
									$options[ $key_upgrade ] = $option_upgrade;
									$changed = true;
								}
							}
							
							if ( $changed ) {
								file_put_contents ( get_stylesheet_directory() . '/direct-edit/options/direct-options.json', de_print_json( json_encode( $options ) ) );
							}
						}
						*/
						
						// Save the key
						update_option( 'automatic_updates_key', $_POST[ 'upgrade_key' ] );
						
						add_settings_error( 'direct-edit', 'de-updated', __( 'Settings saved.', 'direct-edit' ), 'updated' );
					}
				}
			}
		} elseif ( 'copy_files' == $_REQUEST['action'] ) {
			check_admin_referer( 'de_nonce_copy_files', '_de_nonce' );
			
			$url = wp_nonce_url( basename( $_SERVER['PHP_SELF'] ) . '?page=direct-edit', 'de_nonce_copy_files', '_de_nonce' );
			if ( false === ( $creds = request_filesystem_credentials( $url, '', false, get_stylesheet_directory(), array( 'action' => 'copy_files' ) ) ) ) {
				return;
			}
			if ( ! WP_Filesystem( $creds ) ) {
				request_filesystem_credentials( $url, '', true, get_stylesheet_directory(), array( 'action' => 'copy_files' ) );
				return;
			}

			global $wp_filesystem;
			$plugin_path = str_replace( ABSPATH, $wp_filesystem->abspath(), DIRECT_PATH );
			$theme_path = trailingslashit( get_stylesheet_directory() );

			if ( ! $wp_filesystem->is_dir( $theme_path . 'direct-edit' ) ) {
				de_copy( $plugin_path . 'theme', $theme_path . 'direct-edit' );
			}

			add_settings_error( 'direct-edit', 'de-updated', __( 'Settings saved.', 'direct-edit' ), 'updated' );
		} elseif ( 'remove_files' == $_REQUEST['action'] ) {
			check_admin_referer( 'de_nonce_remove_files', '_de_nonce' );
			
			if ( file_exists( get_stylesheet_directory() . '/direct-edit' ) ) {
				de_rmdir( get_stylesheet_directory() . '/direct-edit' );
			}
			
			add_settings_error( 'direct-edit', 'de-updated', __( 'Settings saved.', 'direct-edit' ), 'updated' );
		} elseif ( 'wp_hooks' == $_REQUEST['action'] ) {
			check_admin_referer( 'de_nonce_wp_hooks', '_de_nonce' );
			
			$options = array_map( 'sanitize_text_field', ( array ) $_REQUEST[ 'wp_hooks' ] );
			update_option( 'de_options_wp_hooks', base64_encode( serialize( $options ) ) );
			
			add_settings_error( 'direct-edit', 'de-updated', __( 'Settings saved.', 'direct-edit' ), 'updated' );
		} elseif ( 'de_options' == $_REQUEST[ 'action' ] ) {
			check_admin_referer( 'de_nonce_de_options', '_de_nonce' );
			
			update_option( 'de_text_validation', $_REQUEST[ 'text_validation' ] );
			
			add_settings_error( 'direct-edit', 'de-updated', __( 'Settings saved.', 'direct-edit' ), 'updated' );
		}
	}

	settings_errors();

	if ( get_option( 'de_options_wp_hooks' ) )
		$options_wp_hooks = unserialize( base64_decode( get_option( 'de_options_wp_hooks' ) ) );
	else
		$options_wp_hooks = array( 'title' => 1, 'content' => 1, 'excerpt' => 1 );
	?>
	<div class="wrap">
		<h2><?php _e( 'Direct Edit Options', 'direct-edit' ); ?></h2>
		<div class="inside">
			<iframe src="http://directedit.co/iframe/" frameborder="0" scrolling="no" style="width: 50%; height: 550px; float:right;"></iframe>
		</div>
		<h3><i><?php _e( 'upgrade to PRO', 'direct-edit' );?></i></h3>
		<div class="inside">
			<form method="post">
				<?php wp_nonce_field( 'de_nonce_upgrade', '_de_nonce' ); ?>
				<input type="hidden" name="action" value="upgrade" />
				<table border="0">
					<tbody>
						<tr>
							<td style="width: 30px;"><?php _e( 'key', 'direct-edit' ); ?></td>
							<td><input type="text" name="upgrade_key" id="upgrade_key" style="width: 240px;" /> <input type="submit" value="upgrade" /></td>
						</tr>
					</tbody>
				</table>
			</form>
		</div>
		<h3><i><?php _e( 'setup wizard', 'direct-edit' );?></i></h3>
		<?php if ( ! file_exists( get_stylesheet_directory() . '/direct-edit' ) ) { ?>
		<h3><?php _e( 'copy files to current theme', 'direct-edit' );?></h3>
		<div class="inside">
			<form method="post">
				<?php wp_nonce_field( 'de_nonce_copy_files', '_de_nonce' ); ?>
				<input type="hidden" name="action" value="copy_files" />
				<table border="0">
					<tbody>
						<tr>
							<td><input type="submit" value="copy" /></td>
						</tr>
					</tbody>
				</table>
			</form>
		</div>
		<?php } else { ?>
		<h3><?php _e( 'remove /direct-edit folder from theme', 'direct-edit' );?></h3>
		<div class="inside">
			<form method="post">
				<?php wp_nonce_field( 'de_nonce_remove_files', '_de_nonce' ); ?>
				<input type="hidden" name="action" value="remove_files" />
				<table border="0">
					<tbody>
						<tr>
							<td><input type="submit" value="remove" onclick="return confirm( 'Do you really want to remove /direct-edit folder from theme?' );" /></td>
						</tr>
					</tbody>
				</table>
			</form>
		</div>
		<?php } ?>
		<h3><i><?php _e( 'hooks on standard wp-functions', 'direct-edit' );?></i></h3>
		<div class="inside">
			<form method="post">
				<?php wp_nonce_field( 'de_nonce_wp_hooks', '_de_nonce' ); ?>
				<input type="hidden" name="action" value="wp_hooks" />
				<table border="0">
					<tbody>
						<tr>
							<td><input type="hidden" name="wp_hooks[title]" value="" /><label><input type="checkbox" name="wp_hooks[title]" value="1"<?php echo ( ! empty( $options_wp_hooks[ 'title' ] ) ? ' checked="checked"' : '' ); ?> /> <?php _e( 'Title', 'direct-edit' ); ?></label></td>
						</tr>
						<tr>
							<td><input type="hidden" name="wp_hooks[content]" value="" /><label><input type="checkbox" name="wp_hooks[content]" value="1"<?php echo ( ! empty( $options_wp_hooks[ 'content' ] ) ? ' checked="checked"' : '' ); ?> /> <?php _e( 'Content', 'direct-edit' ); ?></label></td>
						</tr>
						<tr>
							<td><input type="hidden" name="wp_hooks[excerpt]" value="" /><label><input type="checkbox" name="wp_hooks[excerpt]" value="1"<?php echo ( ! empty( $options_wp_hooks[ 'excerpt' ] ) ? ' checked="checked"' : '' ); ?> /> <?php _e( 'Excerpt', 'direct-edit' ); ?></label></td>
						</tr>
						<tr>
							<td><input type="submit" value="save" /></td>
						</tr>
					</tbody>
				</table>
			</form>
		</div>
		<h3><i><?php _e( 'options', 'direct-edit' );?></i></h3>
		<div class="inside">
			<form method="post">
				<?php wp_nonce_field( 'de_nonce_de_options', '_de_nonce' ); ?>
				<input type="hidden" name="action" value="de_options" />
				<table border="0">
					<tbody>
						<tr>
							<td><input type="hidden" name="text_validation" value="" /><label><input type="checkbox" name="text_validation" value="1"<?php echo ( get_option( 'de_text_validation' ) ? ' checked="checked"' : '' ); ?> /> <?php _e( 'validate text', 'direct-edit' ); ?></label></td>
						</tr>
						<tr>
							<td><input type="submit" value="save" /></td>
						</tr>
					</tbody>
				</table>
			</form>
		</div>
	</div>
	<?php
}

function de_metaboxes_save( $post_id, $post ) {
	if ( ! current_user_can( 'edit_posts' )  )
		return false;

	if ( ( basename( $_SERVER['PHP_SELF'] ) == 'post.php' || basename( $_SERVER['PHP_SELF'] ) == 'post-new.php' ) && $_SERVER['REQUEST_METHOD'] == 'POST' ) {
		if( $post->post_title )
			De_Url::register_url( $post->ID, sanitize_title( $post->post_title ) );
		else
			De_Url::register_url( $post->ID, sanitize_title( $post->post_name ) );
		
		if ( $_POST['de_wp_hooks'] ) {
			update_post_meta( $post->ID, 'de_wp_hooks', base64_encode( serialize( $_POST['de_wp_hooks'] ) ) );
		}
	}
}
