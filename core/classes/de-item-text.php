<?php
class De_Item_Text extends De_Item {
	public function __construct( $store, $settings ) {
		global $direct_queried_object;
		
		parent::__construct( $store, $settings );

		if ( $this->store == 'wptitle' ) {
			if ( ( ! $this->get_setting( 'postId' ) || $direct_queried_object->ID == $this->get_setting( 'postId' ) ) && ! $this->get_setting( 'redirect' ) ) {
				$this->set_setting( 'redirect', true );
			}
		}

		if ( $this->store == 'wptitle' && ( ! $this->get_setting( 'type' ) || $this->get_setting( 'type' ) != 'text' || ! $this->get_setting( 'format' ) || $this->get_setting( 'format' ) != 'title' ) ) {
			$this->delete_setting( 'type' );
			$this->delete_setting( 'format' );
			$this->set_setting( 'options', 'title' );
		} elseif ( $this->store == 'wpcontent' && ( ! $this->get_setting( 'type' ) || $this->get_setting( 'type' ) != 'text' || ! $this->get_setting( 'format' ) ) ) {
			$this->delete_setting( 'type' );
			$this->delete_setting( 'format' );
			$this->set_setting( 'options', 'rich' );
		} elseif ( $this->store == 'wpexcerpt' && ( ! $this->get_setting( 'type' ) || $this->get_setting( 'type' ) != 'text' || ! $this->get_setting( 'format' ) ) ) {
			$this->delete_setting( 'type' );
			$this->delete_setting( 'format' );
			$this->set_setting( 'options', 'inline' );
		} else {
			if ( ! $this->get_setting( 'type' ) || $this->get_setting( 'type' ) != 'text' ) {
				$this->delete_setting( 'type' );
				$this->set_setting( 'options', 'plain' );
			}
		}
		
		if ( $this->get_setting( 'validate' ) === null ) {
			if ( get_option( 'de_text_validation' ) ) {
				$this->set_setting( 'validate', true );
			}
		}
	}
	
	public function output( $content = null ) {
		$attr = array();

		if ( get_option( 'de_menu_editor_enabled' ) && de_get_current_template() == 'edit-menu.php' && De_Store::is_editable( $this ) ) {
			// Direct Menu Editor output
			$container = 'div';
			if ( $this->get_setting( 'attr' ) && is_array( $this->get_setting( 'attr' ) ) ) {
				$attr = $this->get_setting( 'attr' );
			}
			$attr[ 'data-reference' ] = $this->reference;
			if ( empty( $attr[ 'id' ] ) )
				$attr[ 'id' ] = $this->reference;
			$attr[ 'class' ] = ( isset( $attr[ 'class' ] ) ? $attr[ 'class' ] . ' direct-menu-new-items' : 'direct-menu-new-items' );
			$attr[ 'data-global-options' ] = $this->get_setting( 'options' );
			$attr[ 'data-local-options' ] = $this->build_local_options();
			
			$result = '<' . $container . self::attr_to_string( $attr ) . '></' . $container . '>';
		} else {
			if ( $this->get_setting( 'container' ) ) {
				$container = $this->get_setting( 'container' );
			} elseif( $this->get_setting( 'format' ) == 'inline' ) {
				$container = 'span';
			} else {
				$container = 'div';
			}
			if ( $this->get_setting( 'attr' ) && is_array( $this->get_setting( 'attr' ) ) ) {
				$attr = $this->get_setting( 'attr' );
			}
			
			// Show Direct Edit only for users who have proper permissions
			if ( De_Store::is_editable( $this ) ) {
				$attr[ 'data-reference' ] = $this->reference;
				if ( empty( $attr[ 'id' ] ) )
					$attr[ 'id' ] = $this->reference;
				$attr[ 'class' ] = ( isset( $attr[ 'class' ] ) ? $attr[ 'class' ] . ' direct-editable' : 'direct-editable' );
				$attr[ 'data-global-options' ] = $this->get_setting( 'options' );
				$attr[ 'data-local-options' ] = $this->build_local_options();
			}
			
			$content_partial =  $this->output_partial( $content );
			$result = '<' . $container . self::attr_to_string( $attr ) . '>' . $content_partial[ 'content' ] . '</' . $container . '>';
		}
		
		return $result;
	}
	
	public function output_partial( $content = null ) {
		if ( $this->get_setting( 'default' ) && ! strlen( $content ) ) {
			$content = $this->get_setting( 'default' );
		}
		
		$content = de_encode_emails( $content );
		
		return array( 'content' => $content );
	}
}
