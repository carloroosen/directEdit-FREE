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
	global $wpdb;
	global $options;
	global $user_ID;

	if ( basename( $_SERVER[ 'SCRIPT_FILENAME' ] ) == 'plugins.php' && isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'direct-edit' ) {
		// Check permissions
		if ( ! current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'direct-edit' ) );
		}

		if ( isset( $_REQUEST['action'] ) && 'upgrade' == $_REQUEST['action'] ) {
			$upgrade_url = 'http://directedit.co/downloads/direct-edit-upgrade.zip?key=' . urlencode( $_POST[ 'upgrade_key' ] ) . '&url=' . urlencode( get_option( 'siteurl' ) ) . '&activity=upgrade';
			$upgrade_path = download_url( $upgrade_url );
			if ( $upgrade_path && WP_Filesystem() ) {
				$result = unzip_file( $upgrade_path, DIRECT_PATH );
				unlink( $upgrade_path );
				
				if ( ! is_wp_error($result) ) {
					// Add PRO options to json options file
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
					
					// Save the key
					update_option( 'automatic_updates_key', $_POST[ 'upgrade_key' ] );
					
					wp_redirect( home_url( '/wp-admin/plugins.php?page=direct-edit&saved=true' ) );
					die();
				}
			}
			
			wp_redirect( home_url( '/wp-admin/plugins.php?page=direct-edit&error=upgrade' ) );
		} elseif ( isset( $_REQUEST['action'] ) && 'copy_files' == $_REQUEST['action'] ) {
			if ( ! file_exists( get_stylesheet_directory() . '/direct-edit' ) ) {
				$result = de_copy( DIRECT_PATH . 'theme', get_stylesheet_directory() . '/direct-edit' );
				if ( ! $result ) {
					@de_rmdir( get_stylesheet_directory() . '/direct-edit' );
					wp_redirect( home_url( '/wp-admin/plugins.php?page=direct-edit&error=copy_files' ) );
					die();
				}
			}
			
			wp_redirect( home_url( '/wp-admin/plugins.php?page=direct-edit&saved=true' ) );
		} elseif ( isset( $_REQUEST['action'] ) && 'remove_files' == $_REQUEST['action'] ) {
			if ( file_exists( get_stylesheet_directory() . '/direct-edit' ) ) {
				de_rmdir( get_stylesheet_directory() . '/direct-edit' );
			}
			
			wp_redirect( home_url( '/wp-admin/plugins.php?page=direct-edit&saved=true' ) );
		} elseif ( isset( $_REQUEST['action'] ) && 'wp_hooks' == $_REQUEST['action'] ) {
			$options = $wpdb->escape( $_REQUEST[ 'wp_hooks' ] );
			update_option( 'de_options_wp_hooks', base64_encode( serialize( $options ) ) );
			
			wp_redirect( home_url( '/wp-admin/plugins.php?page=direct-edit&saved=true' ) );
		} elseif ( isset( $_REQUEST[ 'action' ] ) && 'de_options' == $_REQUEST[ 'action' ] ) {
			update_option( 'de_disable_validation', $_REQUEST[ 'disable_validation' ] );
			
			wp_redirect( home_url( '/wp-admin/plugins.php?page=direct-edit&saved=true' ) );
		}
	}
	
	add_plugins_page( 'Direct Edit Options', 'Direct Edit', 'manage_options', 'direct-edit', 'de_plugin_page' );
}

function de_plugin_page() {
	global $wpdb;
	global $options;
	global $user_ID;

	// Check permissions
	if ( ! current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.', 'direct-edit' ) );
	}

	if ( isset( $_REQUEST[ 'saved' ] ) ) {
		echo '<div id="message" class="updated fade"><p><strong> Settings saved.</strong></p></div>';
	} elseif ( isset( $_REQUEST[ 'error' ] ) ) {
		if ( $_REQUEST[ 'error' ] == 'upgrade' ) {
			echo '<div id="message" class="updated fade"><p><strong> Upgrade error.</strong></p></div>';
		} elseif ( $_REQUEST[ 'error' ] == 'copy_files' ) {
			echo '<div id="message" class="updated fade"><p><strong> Settings could not be saved. Check folder permissions.</strong></p></div>';
		}
	}
		
	if ( get_option( 'de_options_wp_hooks' ) )
		$options_wp_hooks = unserialize( base64_decode( get_option( 'de_options_wp_hooks' ) ) );
	else
		$options_wp_hooks = array( 'title' => 1, 'content' => 1, 'excerpt' => 1 );
	?>
	<div class="wrap">
		<div id="icon-themes" class="icon32">
			<br>
		</div>
		<h2>Direct Edit <?php _e( 'Options', 'direct-edit' ); ?></h2>
		<div class="inside">
			<iframe src="http://directedit.co/iframe/" frameborder="0" scrolling="no" style="width: 50%; height: 550px; float:right;"></iframe>
		</div>
		<h3><i><?php _e( 'upgrade to PRO', 'direct-edit' );?></i></h3>
		<div class="inside">
			<form method="post">
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
				<input type="hidden" name="action" value="de_options" />
				<table border="0">
					<tbody>
						<tr>
							<td><input type="hidden" name="disable_validation" value="" /><label><input type="checkbox" name="disable_validation" value="1"<?php echo ( get_option( 'de_disable_validation' ) ? ' checked="checked"' : '' ); ?> /> <?php _e( 'disable text validation', 'direct-edit' ); ?></label></td>
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

	if ( basename( $_SERVER['PHP_SELF'] ) == 'post.php' || basename( $_SERVER['PHP_SELF'] ) == 'post-new.php' && $_SERVER['REQUEST_METHOD'] == 'POST' ) {
		if( $post->post_title )
			De_Url::register_url( $post->ID, sanitize_title( $post->post_title ) );
		else
			De_Url::register_url( $post->ID, sanitize_title( $post->post_name ) );
		
		if ( $_POST['de_wp_hooks'] ) {
			update_post_meta( $post->ID, 'de_wp_hooks', base64_encode( serialize( $_POST['de_wp_hooks'] ) ) );
		}
	}
}
